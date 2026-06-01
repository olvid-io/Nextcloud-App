<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class OlvidGroup extends Entity {
	protected string $groupId = '';
	protected ?string $groupUid = null;
	protected int $lastModificationTimestamp = 0;
	protected ?string $pushTopic = null;
	protected ?string $groupPhotoUid = null;
	protected ?string $serializedSharedSettings = null;
	protected string $signedGroupBlob = '';
	protected ?bool $enabled = false;
	protected ?string $discussionName = null;
	protected ?string $discussionDescription = null;

	public function __construct() {
		$this->addType('groupId', Types::STRING);
		$this->addType('groupUid', Types::BLOB);
		$this->addType('lastModificationTimestamp', Types::BIGINT);
		$this->addType('pushTopic', Types::STRING);
		$this->addType('groupPhotoUid', Types::BLOB);
		$this->addType('serializedSharedSettings', Types::TEXT);
		$this->addType('signedGroupBlob', Types::TEXT);
		$this->addType('enabled', Types::BOOLEAN);
		$this->addType('discussionName', Types::TEXT);
		$this->addType('discussionDescription', Types::TEXT);
	}

	public static function create(string $groupId): OlvidGroup {
		$group = new OlvidGroup();
		$group->setGroupId($groupId);
		$group->setGroupUid(RandomUtil::random_bytes(Constants::UID_SIZE));
		$group->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
		return $group;
	}

	public function getGroupId(): string {
		return $this->groupId;
	}

	public function setGroupId(string $groupId): void {
		$this->groupId = $groupId;
		$this->markFieldUpdated('groupId');
	}

	public function getGroupUid(): ?string {
		return $this->groupUid;
	}

	public function setGroupUid(?string $groupUid): void {
		$this->groupUid = $groupUid;
		$this->markFieldUpdated('groupUid');
	}

	public function getLastModificationTimestamp(): int {
		return $this->lastModificationTimestamp;
	}

	public function setLastModificationTimestamp(int $lastModificationTimestamp): void {
		$this->lastModificationTimestamp = $lastModificationTimestamp;
		$this->markFieldUpdated('lastModificationTimestamp');
	}

	public function getPushTopic(): ?string {
		return $this->pushTopic;
	}

	public function setPushTopic(?string $pushTopic): void {
		$this->pushTopic = $pushTopic;
		$this->markFieldUpdated('pushTopic');
	}

	public function getGroupPhotoUid(): ?string {
		return $this->groupPhotoUid;
	}

	public function setGroupPhotoUid(?string $groupPhotoUid): void {
		$this->groupPhotoUid = $groupPhotoUid;
		$this->markFieldUpdated('groupPhotoUid');
	}

	public function getSerializedSharedSettings(): ?string {
		return $this->serializedSharedSettings;
	}

	public function setSerializedSharedSettings(?string $serializedSharedSettings): void {
		$this->serializedSharedSettings = $serializedSharedSettings;
		$this->markFieldUpdated('serializedSharedSettings');
	}

	public function getSignedGroupBlob(): string {
		return $this->signedGroupBlob;
	}

	public function setSignedGroupBlob(string $signedGroupBlob): void {
		$this->signedGroupBlob = $signedGroupBlob;
		$this->markFieldUpdated('signedGroupBlob');
	}

	public function getEnabled(): ?bool {
		return $this->enabled;
	}

	public function setEnabled(?bool $enabled): void {
		$this->enabled = $enabled;
		$this->markFieldUpdated('enabled');
	}

	public function getDiscussionName(): ?string {
		return $this->discussionName;
	}

	public function setDiscussionName(?string $discussionName): void {
		$this->discussionName = $discussionName;
		$this->markFieldUpdated('discussionName');
	}

	public function getDiscussionDescription(): ?string {
		return $this->discussionDescription;
	}

	public function setDiscussionDescription(?string $discussionDescription): void {
		$this->discussionDescription = $discussionDescription;
		$this->markFieldUpdated('discussionDescription');
	}

	public function __toString(): string {
		return 'OlvidGroup{'
			. 'id=' . $this->getId()
			. ', groupId=' . $this->groupId
			. ', groupUid=' . $this->groupUid
			. ', lastModificationTimestamp=' . $this->lastModificationTimestamp
			. ', pushTopic=' . $this->pushTopic
			. ', groupPhotoUid=' . $this->groupPhotoUid
			. ', enabled=' . ($this->enabled ? 'true' : 'false')
			. ', discussionName=' . $this->discussionName
			. ', discussionDescription=' . $this->discussionDescription
			. '}';
	}
}
