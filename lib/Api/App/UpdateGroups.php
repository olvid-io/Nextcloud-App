<?php

namespace OCA\Olvid\Api\App;

use Firebase\JWT\JWT;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Db\OlvidGroup;
use OCA\Olvid\Db\OlvidGroupDeletion;
use OCA\Olvid\Db\OlvidGroupMapper;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Models\JsonGroupDeletionData;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\OlvidServerUtils;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\DB\Exception;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class UpdateGroups {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly IGroupManager $groupManager,
		private readonly OlvidGroupMapper $olvidGroupMapper,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidDatabase $db,
	) {
	}

	public function handle(string $groupId): Response {
		$enableStatusChanged = false;

		try {
			$nextcloudGroup = $this->groupManager->get($groupId);
			if ($nextcloudGroup === null) {
				return new JSONResponse(['error' => 'group not found'], 404);
			}

			// get or create OlvidGroup entity in database
			$olvidGroup = $this->olvidGroupMapper->findByGroupIdOrNull($groupId);
			if ($olvidGroup === null) {
				$olvidGroup = OlvidGroup::create($groupId);
			}

			// update group fields
			$request = json_decode(file_get_contents('php://input'), true) ?? [];
			if (isset($request['enabled']) && $request['enabled'] !== $olvidGroup->getEnabled()) {
				$olvidGroup->setEnabled($request['enabled']);
				$enableStatusChanged = true;
			}
			if (array_key_exists('customName', $request) && $request['customName'] !== $olvidGroup->getDiscussionName()) {
				$olvidGroup->setDiscussionName((string)$request['customName']);
			}
			if (array_key_exists('description', $request) && $request['description'] !== $olvidGroup->getDiscussionDescription()) {
				$olvidGroup->setDiscussionDescription((string)$request['description']);
			}

			// if nothing changed we can end now
			if (count($olvidGroup->getUpdatedFields()) === 0) {
				return new Response(200);
			}

			// group was enabled or disabled, manage groupDeletion in database
			if ($enableStatusChanged) {
				// group have been enabled, remove any GroupDeletion in database
				if ($olvidGroup->getEnabled()) {
					$groupDeletion = $this->db->groupDeletion->getByGroupIdOrNull($groupId);
					if ($groupDeletion !== null) {
						$this->db->groupDeletion->delete($groupDeletion);
					}
				}
				// group have been disabled, create or update a group deletion in database
				else {
					// get signature key
					$keyId = $this->olvidAppConfig->getJwkKeyId();
					$keyType = $this->olvidAppConfig->getJwkKeyType();
					$privateKey = $this->olvidAppConfig->getJwkKeyPrivateKey();

					// sign deletion
					$currentTimestamp = TimeUtil::currentTimeMillis();
					$deletionData = new JsonGroupDeletionData();
					$deletionData->groupUid = $olvidGroup->getGroupUid(); // Olvid group Uid (not nextcloud id)
					$deletionData->timestamp = $currentTimestamp;
					$signedDeletionData = JWT::encode($deletionData->jsonSerialize(), $privateKey, $keyType, $keyId);

					$groupDeletion = $this->db->groupDeletion->getByGroupIdOrNull($groupId);

					// create a new deletion
					if ($groupDeletion === null) {
						$groupDeletion = OlvidGroupDeletion::create($groupId, $currentTimestamp, $signedDeletionData);
						$this->db->groupDeletion->insert($groupDeletion);
					}
					// update existing deletion
					else {
						$groupDeletion->setSignature($signedDeletionData);
						$groupDeletion->setTimestamp($currentTimestamp);
						$this->db->groupDeletion->update($groupDeletion);
					}
				}
			}

			/*
			** manage push topics before computing the new blob, and we will send them laters
			 */
			// send notification if groups is enabled, or if group was disabled not
			$needToSendNotification = $olvidGroup->getEnabled() || ($enableStatusChanged);
			// if group is disabled we revoke any existing push topic after notification sending
			$revokeExistingPushTopic = !$olvidGroup->getEnabled();
			// if group is enabled we create a push topic if necessary
			$createPushTopicIfNecessary = $olvidGroup->getEnabled();
			// if we must send notification send them after blob re-computing
			$sendNotificationCallback = null;

			try {
				if ($needToSendNotification) {
					// we have no push topic, send notifications individually
					if ($olvidGroup->getPushTopic() === null) {
						$sendNotificationCallback = function (IGroup $nextcloudGroup, OlvidGroup $olvidGroup) {
							foreach ($nextcloudGroup->getUsers() as $user) {
								$userIdentity = $this->olvidUserConfig->getIdentity($user->getUID());
								if ($userIdentity !== null) {
									OlvidServerUtils::sendSingleUserNotification($this->olvidAppConfig, $userIdentity);
								}
							}
						};
					}
					// else use existing push topics
					else {
						$sendNotificationCallback = function (IGroup $nextcloudGroup, OlvidGroup $olvidGroup) {
							OlvidServerUtils::sendGroupNotification($this->olvidAppConfig, $olvidGroup->getPushTopic());
						};
					}
				}

				// create push topic if necessary, and set it in database
				if ($olvidGroup->getPushTopic() === null && $createPushTopicIfNecessary) {
					$pushTopic = OlvidServerUtils::requestNewPushTopic($this->olvidAppConfig);
					$olvidGroup->setPushTopic($pushTopic);
					$olvidGroup = $this->db->group->update($olvidGroup);
				}
				// revoke existing push topic if necessary
				elseif ($olvidGroup->getPushTopic() !== null && $revokeExistingPushTopic) {
					OlvidServerUtils::revokePushTopic($this->olvidAppConfig, $olvidGroup->getPushTopic());
					$olvidGroup->setPushTopic(null);
					$this->db->group->update($olvidGroup);
				}
			} catch (\Exception $e) {
				$this->logger->error('cannot prepare group update notifications', ['exception' => $e]);
			}

			/*
			 * we can now recompute blob and store it database
			 */
			$blob = JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig);
			$signedBlob = $blob->sign($this->olvidAppConfig);
			$olvidGroup->setSignedGroupBlob($signedBlob);
			$olvidGroup = $this->insertOrUpdateOlvidGroup($olvidGroup);

			/*
			 * Send notifications now, we are ready
			 */
			if ($sendNotificationCallback !== null) {
				try {
					$sendNotificationCallback($nextcloudGroup, $olvidGroup);
				} catch (\Exception $e) {
					$this->logger->error('cannot notify group members', ['exception' => $e]);
				}
			}

			return new JSONResponse($olvidGroup);
		} catch (Exception $exception) {
			$this->logger->error('Unexpected exception', ['exception' => $exception]);
			return new JSONResponse([], 500);
		}
	}

	/**
	 * @throws Exception
	 */
	private function insertOrUpdateOlvidGroup(OlvidGroup $olvidGroup): OlvidGroup {
		if ($olvidGroup->getId() !== null) {
			return $this->db->group->update($olvidGroup);
		} else {
			return $this->db->group->insert($olvidGroup);
		}
	}
}
