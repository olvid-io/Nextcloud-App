<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class OlvidGroupKicked extends Entity {
	protected string $groupId = '';
	protected string $userId = '';
	protected int $timestamp = 0;
	protected string $signature = '';

	public function __construct() {
		$this->addType('groupId', Types::STRING);
		$this->addType('userId', Types::STRING);
		$this->addType('timestamp', Types::BIGINT);
		$this->addType('signature', Types::TEXT);
	}

	public static function create(string $getGroupId, string $userId, int $currentTimestamp, string $signedKickData): OlvidGroupKicked {
		$groupKick = new OlvidGroupKicked();
		$groupKick->setGroupId($getGroupId);
		$groupKick->setUserId($userId);
		$groupKick->setTimestamp($currentTimestamp);
		$groupKick->setSignature($signedKickData);
		return $groupKick;
	}

	public function getGroupId(): string {
		return $this->groupId;
	}

	public function setGroupId(string $groupId): void {
		$this->groupId = $groupId;
		$this->markFieldUpdated('groupId');
	}

	public function getUserId(): string {
		return $this->userId;
	}

	public function setUserId(string $userId): void {
		$this->userId = $userId;
		$this->markFieldUpdated('userId');
	}

	public function getTimestamp(): int {
		return $this->timestamp;
	}

	public function setTimestamp(int $timestamp): void {
		$this->timestamp = $timestamp;
		$this->markFieldUpdated('timestamp');
	}

	public function getSignature(): string {
		return $this->signature;
	}

	public function setSignature(string $signature): void {
		$this->signature = $signature;
		$this->markFieldUpdated('signature');
	}

	public function __toString(): string {
		return 'OlvidGroupKicked{'
			. 'id=' . $this->getId()
			. ', groupId=' . $this->groupId
			. ', userId=' . $this->userId
			. ', timestamp=' . $this->timestamp
			. '}';
	}
}
