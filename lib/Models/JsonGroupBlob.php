<?php

namespace OCA\Olvid\Models;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use JsonSerializable;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Db\OlvidGroup;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\IGroup;
use OCP\IUser;

class JsonGroupBlob implements JsonSerializable {
	// this is the nextcloud internal group id (not serialized in blob)
	public string $groupId;

	// this is the Olvid randomly generated group id, serialized in blob
	public ?string $bytesGroupUid;
	public ?JsonGroupDetails $groupDetails;
	public ?string $photoUid;
	public ?string $encodedPhotoKey;
	public ?string $pushTopic;
	/**
	 * @var JsonGroupMemberAndPermissions[]
	 */
	public array $groupMembersAndPermissions;
	public ?string $serializedSharedSettings;
	public int $timestamp;

	public function __construct(
		string            $groupId,
		?string           $bytesGroupUid,
		?JsonGroupDetails $groupDetails,
		?string           $photoUid,
		?string           $encodedPhotoKey,
		?string           $pushTopic,
		array             $groupMembersAndPermissions,
		?string           $serializedSharedSettings,
		int               $timestamp,
	) {
		$this->groupId = $groupId;
		$this->bytesGroupUid = $bytesGroupUid;
		$this->groupDetails = $groupDetails;
		$this->photoUid = $photoUid;
		$this->encodedPhotoKey = $encodedPhotoKey;
		$this->pushTopic = $pushTopic;
		$this->groupMembersAndPermissions = $groupMembersAndPermissions;
		$this->serializedSharedSettings = $serializedSharedSettings;
		$this->timestamp = $timestamp;
	}

	public static function computeBlob(OlvidGroup $olvidGroup, array $groupMembers, OlvidAppConfigManager $olvidAppConfigManager, OlvidUserConfigManager $olvidUserConfigManager): JsonGroupBlob {
		// build previous members map (to re-use invitation nonce)
		$previousMembers = [];
		if ($olvidGroup->getSignedGroupBlob()) {
			$publicKeyPem = $olvidAppConfigManager->getJwkKeyPublicKey();
			$decoded = JWT::decode($olvidGroup->getSignedGroupBlob(), new Key($publicKeyPem, 'ES256'));
			$originalBlob = JsonGroupBlob::fromArray($olvidGroup->getGroupId(), (array)$decoded);

			foreach ($originalBlob->groupMembersAndPermissions as $prev) {
				if ($prev?->keycloakUserId !== null) {
					$previousMembers[$prev->keycloakUserId] = $prev;
				}
			}
		}

		// compute group member and permissions
		$groupMembersAndPermissions = [];
		foreach ($groupMembers as $member) {
			if (!$olvidUserConfigManager->hasIdentity($member->getUID())) {
				continue;
			}
			// TODO check if there are other things to re-use from previous blob
			// re-user previous permissions and invitation nonce
			if (isset($previousMembers[$member->getUID()])) {
				$permissions = $previousMembers[$member->getUID()]->permissions;
				$invitationNonce = $previousMembers[$member->getUID()]->groupInvitationNonce;
			}
			// default user permissions
			else {
				$permissions = ["eo", "sm"]; // TODO improve set default set
				$invitationNonce = RandomUtil::random_bytes(Constants::UID_SIZE);
			}
			$groupMembersAndPermissions[] = new JsonGroupMemberAndPermissions(
				$member->getUID(),
				$olvidUserConfigManager->getIdentity($member->getUID()),
				JsonUserDetails::getSignedDetails($member, $olvidUserConfigManager, $olvidAppConfigManager),
				$permissions,
				$invitationNonce
			);
		}

		return new JsonGroupBlob(
			$olvidGroup->getGroupId(),
			$olvidGroup->getGroupUid(),
			new JsonGroupDetails($olvidGroup->getDiscussionName(), $olvidGroup->getDiscussionDescription()),
			null,
			null,
			$olvidGroup->getPushTopic(),
			$groupMembersAndPermissions,
			null,
			TimeUtil::currentTimeMillis()
		);
	}

	public function sign(OlvidAppConfigManager $olvidAppConfigManager): string {
		// get signature key
		$keyId = $olvidAppConfigManager->getJwkKeyId();
		$keyType = $olvidAppConfigManager->getJwkKeyType();
		$privateKey = $olvidAppConfigManager->getJwkKeyPrivateKey();

		// sign details and store in database
		$signedBlob = JWT::encode($this->jsonSerialize(), $privateKey, $keyType, $keyId);
		return $signedBlob;
	}

	/*
	 ** JSON tools
	 */
	public static function fromArray(string $groupId, array $data): JsonGroupBlob {
		return new JsonGroupBlob(
			$groupId,
			isset($data['guid']) ? (base64_decode($data['guid']) ?: null) : null,
			JsonGroupDetails::fromArray(isset($data['details']) ? (array)$data['details'] : []),
			isset($data['photo_label']) ? base64_decode($data['photo_label']) : null,
			isset($data['photo_key']) ? base64_decode($data['photo_key']) : null,
			$data['pt'] ?? null,
			array_map(fn($m) => JsonGroupMemberAndPermissions::fromArray((array)$m), $data['gm_perms'] ?? []),
			$data['sss'] ?? null,
			$data['timestamp'] ?? 0,
		);
	}

	public function jsonSerialize(): array {
		return array_filter([
			'guid' => $this->bytesGroupUid !== null ? base64_encode($this->bytesGroupUid) : null,
			'details' => $this->groupDetails?->jsonSerialize(),
			'photo_label' => $this->photoUid !== null ? base64_encode($this->photoUid) : null,
			'photo_key' => $this->encodedPhotoKey !== null ? base64_encode($this->encodedPhotoKey) : null,
			'pt' => $this->pushTopic,
			'gm_perms' => $this->groupMembersAndPermissions,
			'sss' => $this->serializedSharedSettings,
			'timestamp' => $this->timestamp,
		], fn($v) => $v !== null);
	}
}
