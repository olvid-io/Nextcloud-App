<?php

declare(strict_types=1);

namespace OCA\Olvid\Models;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use JsonSerializable;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Db\OlvidGroup;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;

class JsonGroupBlob implements JsonSerializable {
	use JsonSerializableTrait;

	#[JsonField('', excludeFromJson: true)]
	public string $groupId = '';

	#[JsonField('guid', isBytes: true)]
	public ?string $bytesGroupUid = null;

	#[JsonField('details', class: JsonGroupDetails::class)]
	public ?JsonGroupDetails $groupDetails = null;

	#[JsonField('photo_label', isBytes: true)]
	public ?string $photoUid = null;

	#[JsonField('photo_key', isBytes: true)]
	public ?string $encodedPhotoKey = null;

	#[JsonField('pt')]
	public ?string $pushTopic = null;

	#[JsonField('gm_perms', class: JsonGroupMemberAndPermissions::class, isArray: true)]
	public array $groupMembersAndPermissions = [];

	#[JsonField('sss')]
	public ?string $serializedSharedSettings = null;

	#[JsonField('timestamp')]
	public int $timestamp = 0;

	public static function fromArray(string $groupId, array $data): self {
		$instance = static::hydrateFromArray($data);
		$instance->groupId = $groupId;
		return $instance;
	}

	public static function computeBlob(OlvidGroup $olvidGroup, String $defaultName, array $groupMembers, OlvidAppConfigManager $olvidAppConfigManager, OlvidUserConfigManager $olvidUserConfigManager): JsonGroupBlob {
		$previousMembers = [];
		if ($olvidGroup->getSignedGroupBlob()) {
			try {
				$publicKeyPem = $olvidAppConfigManager->getJwkKeyPublicKey();
				$decoded = JWT::decode($olvidGroup->getSignedGroupBlob(), new Key($publicKeyPem, 'ES256'));
				$originalBlob = JsonGroupBlob::fromArray($olvidGroup->getGroupId(), (array)$decoded);
				foreach ($originalBlob->groupMembersAndPermissions as $prev) {
					if ($prev->keycloakUserId !== null) {
						$previousMembers[$prev->keycloakUserId] = $prev;
					}
				}
			} catch (Exception $e) {
				// corrupt or expired blob — start fresh, no previous members to reuse
			}
		}

		$groupMembersAndPermissions = [];
		foreach ($groupMembers as $member) {
			if (!$olvidUserConfigManager->hasIdentity($member->getUID())) {
				continue;
			}
			if (isset($previousMembers[$member->getUID()])) {
				$permissions = $previousMembers[$member->getUID()]->permissions;
				$invitationNonce = $previousMembers[$member->getUID()]->groupInvitationNonce;
			} else {
				$permissions = ['eo', 'sm']; // TODO improve default set
				$invitationNonce = RandomUtil::random_bytes(Constants::GROUP_INVITATION_NONCE_SIZE);
			}
			$groupMembersAndPermissions[] = new JsonGroupMemberAndPermissions(
				$member->getUID(),
				$olvidUserConfigManager->getIdentity($member->getUID()),
				JsonUserDetails::getSignedDetails($member, $olvidUserConfigManager, $olvidAppConfigManager),
				$permissions,
				$invitationNonce
			);
		}

		// compute group name
		$blobGroupName = $olvidGroup->getDiscussionName() === null || !trim($olvidGroup->getDiscussionName()) ? $defaultName : $olvidGroup->getDiscussionName();

		$blob = new JsonGroupBlob();
		$blob->groupId = $olvidGroup->getGroupId();
		$blob->bytesGroupUid = $olvidGroup->getGroupUid();
		$blob->groupDetails = new JsonGroupDetails($blobGroupName, $olvidGroup->getDiscussionDescription());
		$blob->pushTopic = $olvidGroup->getPushTopic();
		$blob->groupMembersAndPermissions = $groupMembersAndPermissions;
		$blob->timestamp = TimeUtil::currentTimeMillis();
		return $blob;
	}

	public function sign(OlvidAppConfigManager $olvidAppConfigManager): string {
		$keyId = $olvidAppConfigManager->getJwkKeyId();
		$keyType = $olvidAppConfigManager->getJwkKeyType();
		$privateKey = $olvidAppConfigManager->getJwkKeyPrivateKey();
		return JWT::encode($this->jsonSerialize(), $privateKey, $keyType, $keyId);
	}
}
