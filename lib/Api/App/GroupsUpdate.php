<?php

namespace OCA\Olvid\Api\App;

use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Db\OlvidGroup;
use OCA\Olvid\Db\OlvidGroupMapper;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\DB\Exception;
use OCP\IGroupManager;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

class GroupsUpdate {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly IGroupManager $groupManager,
		private readonly OlvidGroupMapper $olvidGroupMapper,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidDatabase $db,
		private readonly OlvidServer $olvidServer,
	) {
	}

	public function handle(string $groupId): Response {
		// group status (enable/disabled) have been changed in this update
		$enableStatusChanged = false;
		// if the push topic is new we notify users individually, as they do not had time to register to it
		$newlyCreatedPushTopic = false;

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

					// create a new push topic for this group (override existing one if there were)
					try {
						$olvidGroup->setPushTopic($this->olvidServer->requestNewPushTopic());
						$newlyCreatedPushTopic = true;
					} catch (OlvidServerException|InvalidConfigurationException $exception) {
						$this->logger->error('GroupsUpdate: cannot create new push topic: ' . $exception->getMessage());
					}
				}
				// group have been disabled, create or update a group deletion in database
				else {
					$this->db->groupDeletion->computeAndSaveGroupDeletion($this->olvidAppConfig, $olvidGroup);
				}
			}

			/*
			 * we can now recompute blob and store it database
			 */
			$blob = JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig);
			$signedBlob = $blob->sign($this->olvidAppConfig);
			$olvidGroup->setSignedGroupBlob($signedBlob);
			$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
			$olvidGroup = $this->insertOrUpdateOlvidGroup($olvidGroup);

			/*
			** send notifications
			 */
			try {
				if (!$olvidGroup->getEnabled() && !$enableStatusChanged) {
					// if group is not enabled and was not disabled now, do not send a notification
				}
				// notify users individually
				if ($newlyCreatedPushTopic || $olvidGroup->getPushTopic() === null) {
					foreach ($nextcloudGroup->getUsers() as $user) {
						$userIdentity = $this->olvidUserConfig->getIdentity($user->getUID());
						if ($userIdentity !== null) {
							$this->olvidServer->sendSingleUserNotification($userIdentity);
						}
					}
				}
				// use existing group push topic
				else {
					$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
				}
			} catch (OlvidServerException|InvalidConfigurationException $exception) {
				$this->logger->error('GroupsUpdate: cannot send notifications: ' . $exception->getMessage());
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
