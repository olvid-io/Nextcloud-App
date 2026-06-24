<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\App;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Db\OlvidData;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Db\OlvidGroup;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Utils\Encoded;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\DB\Exception;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

/**
 * PUT /app/groups/{groupId}/avatar
 *
 * Accepts a base64 JPEG data URL (already cropped and compressed by the
 * frontend AvatarPicker component), stores it as a new row in olvid_data, and
 * updates the group's group_photo_uid to point to it.
 *
 * A fresh 32-byte UID is generated on every upload so the ?v= cache-buster in
 * the avatar URL always changes — browsers will fetch the new image.
 * The old olvid_data row is not deleted (orphan cleanup can be added later).
 *
 * Request body (JSON):
 *   { "photoData": "data:image/jpeg;base64,<base64-encoded JPEG>" }
 *
 * Response (JSON):
 *   { "photoUid": "<base64-encoded 32-byte UID>" }   — used by the frontend as ?v= param
 */
class GroupAvatarUpload {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IGroupManager $groupManager,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidDatabase $db,
		private readonly OlvidServer $olvidServer,
	) {
	}

	public function handle(string $groupId): JSONResponse {
		// Validate the Nextcloud group exists
		$nextcloudGroup = $this->groupManager->get($groupId);
		if ($nextcloudGroup === null) {
			return new JSONResponse(['error' => 'group not found'], 404);
		}

		// Parse and decode the base64 JPEG data URL
		$body = json_decode(file_get_contents('php://input'), true) ?? [];
		$photoData = $body['photoData'] ?? null;
		if (!is_string($photoData) || $photoData === '') {
			return new JSONResponse(['error' => 'photoData is required'], 400);
		}

		// Strip the "data:image/<type>;base64," prefix and decode
		$base64 = preg_replace('/^data:image\/\w+;base64,/', '', $photoData);
		$jpegBytes = base64_decode($base64, true);
		if ($jpegBytes === false || strlen($jpegBytes) === 0) {
			return new JSONResponse(['error' => 'invalid image data'], 400);
		}

		// Generate a new UID + AuthEncAES256ThenSHA256 key
		// A new UID on every upload changes the ?v= cache-buster URL.
		$photoUid = RandomUtil::random_bytes(Constants::UID_SIZE);
		$macKey = RandomUtil::random_bytes(32);
		$encKey = RandomUtil::random_bytes(32);
		if ($photoUid === null || $macKey === null || $encKey === null) {
			$this->logger->error('GroupAvatarUpload: failed to generate random bytes');
			return new JSONResponse(['error' => 'server crypto error'], 500);
		}

		// Encode the key as an Olvid AuthEncAES256ThenSHA256 key (type 0x90) so the
		// getData endpoint can serve this image encrypted to Olvid devices in the future.
		$encodedKey = Encoded::encodeSymmetricKey(0x02, 0x00, [
			'mackey' => $macKey,
			'enckey' => $encKey,
		]);

		try {
			// Insert the image into olvid_data
			$olvidData = new OlvidData();
			$olvidData->setDataUid(base64_encode($photoUid));
			$olvidData->setEncodedDataKey($encodedKey);
			$olvidData->setData($jpegBytes);
			$this->db->dataMapper->insert($olvidData);

			// Get-or-create OlvidGroup and update group_photo_uid
			$olvidGroup = $this->db->group->findByGroupIdOrNull($groupId);
			// create a new minimal olvid group entity, might be updated properly on group updates
			if ($olvidGroup === null) {
				$olvidGroup = OlvidGroup::create($groupId);
				$olvidGroup->setSignedGroupBlob('');
				$olvidGroup->setGroupPhotoUid($photoUid);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$this->db->group->insert($olvidGroup);
			} else {
				$olvidGroup->setGroupPhotoUid($photoUid);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$this->db->group->update($olvidGroup);
			}

			// re-compute Olvid blob
			$blob = JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db);
			$signedBlob = $blob->sign($this->olvidAppConfig);
			$olvidGroup->setSignedGroupBlob($signedBlob);
			$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
			$this->db->group->update($olvidGroup);

			// send notifications if group is enabled and have a push topic
			try {
				if ($olvidGroup->getEnabled()) {
					if ($olvidGroup->getPushTopic() !== null) {
						$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
					} else {
						$this->logger->warning("GroupAvatarUpload: no push topic to notify group members");
					}
				}
			} catch (OlvidServerException|InvalidConfigurationException $exception) {
				$this->logger->error('GroupAvatarUpload: cannot send notifications: ' . $exception->getMessage());
			}
		} catch (Exception $e) {
			$this->logger->error('GroupAvatarUpload: DB error', ['exception' => $e]);
			return new JSONResponse(['error' => 'database error'], 500);
		}

		// Return the new photo UID so the frontend can update the ?v= cache-buster
		return new JSONResponse(['photoUid' => base64_encode($photoUid)]);
	}
}
