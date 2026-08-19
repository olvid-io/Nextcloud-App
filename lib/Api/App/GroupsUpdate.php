<?php

namespace OCA\Olvid\Api\App;

use OCA\Olvid\Db\OlvidGroup;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\Context\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class GroupsUpdate {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
	) {
	}

	/*
	 * Update an OlvidGroup task
	 * - enabled: if not null update enable status for the group.
	 * - $discussionName: if not null and not empty update discussion name in Olvid.
	 * - $discussionName: if not null update discussion description in Olvid.
	 * This task notify Olvid concerned Olvid devices if necessary
	 */
	public function handle(string $groupId, ?bool $enabled, ?string $discussionName, ?string $discussionDescription): DataResponse {
		// group status (enable/disabled) have been changed in this update
		$enableStatusChanged = false;
		// if the push topic is new we notify users individually, as they do not had time to register to it
		$newlyCreatedPushTopic = false;

		try {
			$nextcloudGroup = $this->context->nextcloud->groupManager->get($groupId);
			if ($nextcloudGroup === null) {
				return new DataResponse(['error' => 'group not found'], Http::STATUS_NOT_FOUND);
			}

			// get or create OlvidGroup entity in database
			$olvidGroup = $this->context->db->group->getByGroupIdOrNull($groupId);
			if ($olvidGroup === null) {
				$olvidGroup = $this->context->db->group->insert(OlvidGroup::create($groupId, $nextcloudGroup->getDisplayName()));
			}

			$updated = false;
			if ($enabled !== null && $enabled !== $olvidGroup->getEnabled()) {
				$olvidGroup->setEnabled($enabled);
				$enableStatusChanged = true;
				$updated = true;
			}
			if ($discussionName !== null && trim($discussionName) && $discussionName !== $olvidGroup->getDiscussionName()) {
				$olvidGroup->setDiscussionName($discussionName);
				$updated = true;
			}
			if ($discussionDescription !== null && $discussionDescription !== $olvidGroup->getDiscussionDescription()) {
				$olvidGroup->setDiscussionDescription($discussionDescription);
				$updated = true;
			}

			// if nothing changed we can end now
			if (!$updated) {
				return new DataResponse(null, Http::STATUS_OK);
			}
			// always save current group state to be sure modifications are saved in all cases
			$this->context->db->group->update($olvidGroup);

			// group was enabled or disabled, manage groupDeletion in database
			if ($enableStatusChanged) {
				// group have been enabled, remove any GroupDeletion in database
				if ($olvidGroup->getEnabled()) {
					$groupDeletion = $this->context->db->groupDeletion->getByBytesGroupUidOrNull($olvidGroup->getBytesGroupUid());
					if ($groupDeletion !== null) {
						$this->context->db->groupDeletion->delete($groupDeletion);
					}

					// create a new push topic for this group (override existing one if there were)
					try {
						$olvidGroup->setPushTopic($this->context->olvidServer->requestNewPushTopic());
						$newlyCreatedPushTopic = true;
					} catch (InvalidConfigurationException) {
						$this->logger->error('GroupsUpdate: cannot create new push topic: invalid configuration');
					} catch (OlvidServerException $exception) {
						$this->logger->error('GroupsUpdate: cannot create new push topic: unexpected exception', ['exception' => $exception]);
					}
				}
				// group have been disabled, create or update a group deletion in database
				else {
					$this->context->db->groupDeletion->computeAndSaveGroupDeletion($this->context, $olvidGroup);
				}
			}

			/*
			 * if group is enabled we can now recompute blob and store it database
			 * else we can delete blob
			 */
			if ($olvidGroup->getEnabled()) {
				$jsonGroupBlob = $olvidGroup->computeBlob($nextcloudGroup->getUsers(), $this->context);
				$signedBlob = $this->context->signatory->sign($jsonGroupBlob->jsonSerialize());
				$olvidGroup->setSignedGroupBlob($signedBlob);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$olvidGroup = $this->context->db->group->update($olvidGroup);
			} elseif ($olvidGroup->getSignedGroupBlob()) {
				$olvidGroup->setSignedGroupBlob(null);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$olvidGroup = $this->context->db->group->update($olvidGroup);
			}

			/*
			** send notifications
			 */
			if (!$olvidGroup->getEnabled() && !$enableStatusChanged) {
				// if group is disabled and was not disabled now, do not send a notification
			} else {
				// notify users individually
				if ($newlyCreatedPushTopic || $olvidGroup->getPushTopic() === null) {
					$olvidUserMembers = $this->context->db->user->getUsersById(array_map(function ($nu) { return $nu->getUID(); }, $nextcloudGroup->getUsers()));
					foreach ($olvidUserMembers as $olvidUserMember) {
						$olvidUser = $this->context->db->user->getByUserIdOrNull($olvidUserMember->getUserId());
						if ($olvidUser?->hasIdentity()) {
							$this->context->olvidServer->sendSingleUserNotificationNoFail(base64_encode($olvidUser->getBytesIdentity()));
						}
					}
				}
				// use existing group push topic
				else {
					$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
				}
			}

			// delete push topic for disabled group
			if (!$olvidGroup->getEnabled() && $olvidGroup->getPushTopic() !== null) {
				$this->context->olvidServer->revokePushTopicNoFail($olvidGroup->getPushTopic());
				$olvidGroup->setPushTopic(null);
				$olvidGroup = $this->context->db->group->update($olvidGroup);
			}

			return new DataResponse($olvidGroup);
		} catch (Exception|MultipleObjectsReturnedException $exception) {
			$this->logger->error('Unexpected exception', ['exception' => $exception]);
			return new DataResponse(['error' => 'internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
