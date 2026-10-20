<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\App;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Db\OlvidData;
use OCA\Olvid\Db\OlvidGroup;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Encoded;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\DB\Exception;
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
		private readonly OlvidContext $context,
	) {
	}

	/*
	* Update a group avatar task, shared between Nextcloud groups and circles.
	* - $groupId: the Nextcloud group id or circle single id — same generic
	*   string key used in the olvid_group table either way.
	* - $displayName/$memberUsers: already resolved by the caller (an
	*   IGroup lookup for a group, a Circle's own member list for a
	*   circle) — this method has no NC-group- or circle-specific logic
	*   left in it, existence is signalled by $displayName being null.
	* - $photoData: Base64-encoded image data (PNG or JPEG, JSON body) or null to unset current
	* This task notify Olvid concerned Olvid devices if necessary
	*
	* @param string[] $memberUserIds
	*/
	public function handle(string $groupId, ?string $displayName, array $memberUserIds, ?string $photoData): DataResponse {
		if ($displayName === null) {
			return new DataResponse(['error' => 'group not found'], Http::STATUS_NOT_FOUND);
		}

		// unset current photo
		if ($photoData === null) {
			// set photo uid to null
			$photoUid = null;
		}
		// handle a new picture
		else {
			// Strip the "data:image/<type>;base64," prefix and decode
			$base64 = preg_replace('/^data:image\/\w+;base64,/', '', $photoData);
			$jpegBytes = base64_decode($base64, true);
			if ($jpegBytes === false || strlen($jpegBytes) === 0) {
				return new DataResponse(['error' => 'invalid image data'], Http::STATUS_BAD_REQUEST);
			}

			// Generate a new UID + AuthEncAES256ThenSHA256 key
			// A new UID on every upload changes the ?v= cache-buster URL.
			$photoUid = RandomUtil::random_bytes(Constants::UID_SIZE);
			$macKey = RandomUtil::random_bytes(32);
			$encKey = RandomUtil::random_bytes(32);
			if ($photoUid === null || $macKey === null || $encKey === null) {
				$this->logger->error('GroupAvatarUpload: failed to generate random bytes');
				return new DataResponse(['error' => 'server crypto error'], Http::STATUS_INTERNAL_SERVER_ERROR);
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
				$olvidData->setBytesDataUid($photoUid);
				$olvidData->setBytesEncodedKey($encodedKey);
				$olvidData->setBytesData($jpegBytes);
				$this->context->db->data->insert($olvidData);
			} catch (Exception $e) {
				$this->logger->error('GroupAvatarUpload: DB error: cannot create OlvidData', ['exception' => $e]);
				return new DataResponse(['error' => 'database error'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		}

		try {
			// Get-or-create OlvidGroup and update group_photo_uid
			$olvidGroup = $this->context->db->group->getByGroupIdOrNull($groupId);
			// create a new minimal olvid group entity, might be updated properly on group updates
			if ($olvidGroup === null) {
				$olvidGroup = OlvidGroup::create($groupId, $displayName);
				$olvidGroup->setSignedGroupBlob(null);
				$olvidGroup->setBytesGroupPhotoUid($photoUid);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$this->context->db->group->insert($olvidGroup);
			} else {
				// delete previous data if necessary
				if ($olvidGroup->getBytesGroupPhotoUid()) {
					$previousOlvidData = $this->context->db->data->getByUidOrNull($olvidGroup->getBytesGroupPhotoUid());
					$this->context->db->data->delete($previousOlvidData);
				}
				$olvidGroup->setBytesGroupPhotoUid($photoUid);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$this->context->db->group->update($olvidGroup);
			}

			// re-compute Olvid blob
			$jsonGroupBlob = $olvidGroup->computeBlob($memberUserIds, $this->context);
			$signedBlob = $this->context->signatory->sign($jsonGroupBlob->jsonSerialize());
			$olvidGroup->setSignedGroupBlob($signedBlob);
			$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
			$this->context->db->group->update($olvidGroup);

			// send notifications if group is enabled and have a push topic
			if ($olvidGroup->getEnabled()) {
				if ($olvidGroup->getPushTopic() !== null) {
					$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
				} else {
					$this->logger->warning('GroupAvatarUpload: no push topic to notify group members');
				}
			}
		} catch (Exception|MultipleObjectsReturnedException $e) {
			$this->logger->error('GroupAvatarUpload: DB error', ['exception' => $e]);
			return new DataResponse(['error' => 'database error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// Return the new photo UID so the frontend can update the ?v= cache-buster
		return new DataResponse(['photoUid' => $photoUid ? base64_encode($photoUid) : null]);
	}
}
