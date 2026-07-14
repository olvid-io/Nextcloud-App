<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Models\JsonGroupDetails;
use OCA\Olvid\Models\JsonGroupMemberAndPermissions;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IUser;
use ReflectionException;

/**
 * Nextcloud group id
 * @method string getGroupId()
 * @method void setGroupId(string $groupId)
 *
 * Olvid group Uid as bytes
 * @method string getBytesGroupUid()
 * @method void setBytesGroupUid(string $bytesGroupUid)
 *
 * @method bool getEnabled()
 * @method void setEnabled(bool $enabled)
 *
 * @method string|null getSignedGroupBlob()
 * @method void setSignedGroupBlob(string|null $signedGroupBlob)
 *
 * @method int getLastModificationTimestamp()
 * @method void setLastModificationTimestamp(int $lastModificationTimestamp)
 *
 * @method string|null getPushTopic()
 * @method void setPushTopic(string|null $pushTopic)
 *
 * @method string|null getBytesGroupPhotoUid()
 * @method void setBytesGroupPhotoUid(string|null $bytesGroupPhotoUid)
 *
 * @method string|null getSerializedSharedSettings()
 * @method void setSerializedSharedSettings(string|null $serializedSharedSettings)
 *
 * @method string|null getDiscussionName()
 * @method void setDiscussionName(string|null $discussionName)
 *
 * @method string|null getDiscussionDescription()
 * @method void setDiscussionDescription(string|null $discussionDescription)
 */
class OlvidGroup extends Entity {
	// Nextcloud group id
	protected string $groupId = '';
	// Olvid group Uid as bytes
	protected string $bytesGroupUid = '';
	protected bool $enabled = false;
	protected ?string $signedGroupBlob = null;
	protected int $lastModificationTimestamp = 0;
	protected ?string $pushTopic = null;
	protected ?string $bytesGroupPhotoUid = null;
	protected ?string $serializedSharedSettings = null;
	protected ?string $discussionName = null;
	protected ?string $discussionDescription = null;

	public function __construct() {
		// Nextcloud group id
		$this->addType('groupId', Types::STRING);
		// Olvid group Uid as bytes
		$this->addType('bytesGroupUid', Types::BLOB);
		$this->addType('enabled', Types::BOOLEAN);
		$this->addType('signedGroupBlob', Types::TEXT);
		$this->addType('lastModificationTimestamp', Types::BIGINT);
		$this->addType('pushTopic', Types::STRING);
		$this->addType('bytesGroupPhotoUid', Types::BLOB);
		$this->addType('serializedSharedSettings', Types::TEXT);
		$this->addType('discussionName', Types::TEXT);
		$this->addType('discussionDescription', Types::TEXT);
	}

	public static function create(string $groupId): OlvidGroup {
		$group = new OlvidGroup();
		$group->setGroupId($groupId);
		$group->setBytesGroupUid(RandomUtil::random_bytes(Constants::UID_SIZE));
		$group->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
		return $group;
	}

	/**
	 * @param String $defaultName
	 * @param IUser[] $groupMembers
	 * @param OlvidContext $context
	 * @return JsonGroupBlob
	 * @throws Exception
	 */
	public function computeBlob(String $defaultName, array $groupMembers, OlvidContext $context): JsonGroupBlob {
		$previousMembers = [];
		if ($this->getSignedGroupBlob()) {
			try {
				$decoded = $context->signatory->verify($this->getSignedGroupBlob());
				$originalBlob = JsonGroupBlob::fromArray((array)$decoded, $this->getGroupId());
				foreach ($originalBlob->groupMembersAndPermissions as $prev) {
					if ($prev->keycloakUserId !== null) {
						$previousMembers[$prev->keycloakUserId] = $prev;
					}
				}
			} catch (ReflectionException) {
				// corrupt or expired blob — start fresh, no previous members to reuse
			}
		}

		$groupMembersAndPermissions = [];

		$olvidUserMembers = $context->db->user->getUsersById(array_map(function ($nu) { return $nu->getUID(); }, $groupMembers));
		foreach ($olvidUserMembers as $olvidUserMember) {
			if (!$olvidUserMember?->hasIdentity()) {
				continue;
			}
			if (isset($previousMembers[$olvidUserMember->getUserId()])) {
				$permissions = $previousMembers[$olvidUserMember->getUserId()]->permissions;
				$invitationNonce = $previousMembers[$olvidUserMember->getUserId()]->groupInvitationNonce;
			} else {
				$permissions = ['eo', 'sm']; // TODO improve default set
				$invitationNonce = RandomUtil::random_bytes(Constants::GROUP_INVITATION_NONCE_SIZE);
			}

			$signedUserDetails = $olvidUserMember->getSignedDetails();
			if ($signedUserDetails === null) {
				$jsonUserDetails = $olvidUserMember->computeJsonUserDetails($context->nextcloud->userManager->getDisplayName($olvidUserMember->getUserId()));
				$signedUserDetails = $context->signatory->sign($jsonUserDetails->jsonSerialize());
				$olvidUserMember->setSignedDetails($signedUserDetails);
			}

			$groupMembersAndPermissions[] = new JsonGroupMemberAndPermissions(
				$olvidUserMember->getUserId(),
				base64_encode($olvidUserMember->getBytesIdentity()),
				$signedUserDetails,
				$permissions,
				$invitationNonce
			);
		}

		// compute group name
		$blobGroupName = $this->getDiscussionName() === null || !trim($this->getDiscussionName()) ? $defaultName : $this->getDiscussionName();

		$blob = new JsonGroupBlob();
		$blob->groupId = $this->getGroupId();
		$blob->bytesGroupUid = $this->getBytesGroupUid();
		$blob->photoUid = $this->getBytesGroupPhotoUid();
		if ($blob->photoUid !== null) {
			try {
				$olvidData = $context->db->data->getByUidOrNull($blob->photoUid);
				if ($olvidData !== null) {
					$blob->encodedPhotoKey = $olvidData->getBytesEncodedKey();
				}
			} catch (MultipleObjectsReturnedException|Exception) {
			}
		}
		$blob->groupDetails = new JsonGroupDetails($blobGroupName, $this->getDiscussionDescription());
		$blob->pushTopic = $this->getPushTopic();
		$blob->groupMembersAndPermissions = $groupMembersAndPermissions;
		$blob->timestamp = TimeUtil::currentTimeMillis();
		return $blob;
	}

	public function jsonSerialize(): array {
		return [
			'groupId' => $this->groupId,
			'bytesGroupUid' => base64_encode($this->bytesGroupUid),
			'enabled' => $this->enabled,
			'signedGroupBlob' => $this->signedGroupBlob,
			'lastModificationTimestamp' => $this->lastModificationTimestamp,
			'pushTopic' => $this->pushTopic,
			'bytesGroupPhotoUid' => base64_encode($this->bytesGroupPhotoUid ?? ''),
			'serializedSharedSettings' => $this->serializedSharedSettings,
			'discussionName' => $this->discussionName,
			'discussionDescription' => $this->discussionDescription,
		];
	}

	public function __toString(): string {
		return 'OlvidGroup{'
			. 'id=' . $this->getId()
			. ', groupId=' . $this->groupId
			. ', bytesGroupUid=' . base64_encode($this->bytesGroupUid)
			. ', enabled=' . ($this->enabled ? 'true' : 'false')
			. ', bytesSignedGroupBlob=' . $this->signedGroupBlob
			. ', lastModificationTimestamp=' . $this->lastModificationTimestamp
			. ', pushTopic=' . $this->pushTopic
			. ', bytesGroupPhotoUid=' . base64_encode($this->bytesGroupPhotoUid ?? '')
			. ', serializedSharedSettings=' . $this->serializedSharedSettings
			. ', discussionName=' . $this->discussionName
			. ', discussionDescription=' . $this->discussionDescription
			. '}';
	}
}
