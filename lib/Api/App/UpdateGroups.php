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
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class UpdateGroups {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface         $logger,
		private readonly IUserSession            $userSession,
		private readonly IGroupManager           $groupManager,
		private readonly OlvidGroupMapper        $olvidGroupMapper,
		private readonly IUserManager            $userManager,
		private readonly OlvidUserConfigManager  $olvidUserConfig,
		private readonly OlvidAppConfigManager   $olvidAppConfig,
		private readonly ?string                 $userId,
		private readonly GetMagicLink            $getMagicLinkHandler,
		private readonly OlvidDatabase           $db

	) {}

	public function handle(string $groupId): Response {
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

			// create a push topic if group does not have one (new group, or existing without push topic)
			// (might fail if no api key filled)
			try {
				$pushTopic = OlvidServerUtils::requestNewPushTopic($this->olvidAppConfig);
				$olvidGroup->setPushTopic($pushTopic);
			} catch (\Exception $e) {
				$this->logger->error("cannot create push topic: " . $e);
			}

			// update group fields
			$request = json_decode(file_get_contents('php://input'), true) ?? [];
			$enableStatusChanged = false;
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

			// we can now recompute blob and store it database
			$blob = JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig);
			$signedBlob = $blob->sign($this->olvidAppConfig);
			$olvidGroup->setSignedGroupBlob($signedBlob);
			$olvidGroup = $this->insertOrUpdateOlvidGroup($olvidGroup);

			/*
			 ** notify group members
			 */
			try {
				// use group push topic
				if ($olvidGroup->getPushTopic() !== null) {
					OlvidServerUtils::sendGroupNotification($this->olvidAppConfig, $olvidGroup->getPushTopic());
				}
				// create a new push topic for this group
				else {
					$pushTopic = OlvidServerUtils::requestNewPushTopic($this->olvidAppConfig);
					$olvidGroup->setPushTopic($pushTopic);
					$olvidGroup = $this->db->group->update($olvidGroup);

					// we cannot use this new push topic, notify each user individually
					// TODO notify
				}
			} catch (\Exception $e) {
				$this->logger->error("cannot notify group members", ["exception" => $e]);
			}

			return new JSONResponse($olvidGroup);
		} catch (Exception $exception) {
			$this->logger->error("Unexpected exception", ["exception" => $exception]);
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
