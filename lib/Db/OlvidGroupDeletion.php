<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

class OlvidGroupDeletion extends Entity {
	protected string $groupId = '';
	protected int $timestamp = 0;
	protected string $signature = '';

	public function __construct() {
		$this->addType('groupId', Types::STRING);
		$this->addType('timestamp', Types::BIGINT);
		$this->addType('signature', Types::TEXT);
	}

	public static function create(string $groupId, int $timestamp, string $signature): OlvidGroupDeletion {
		$groupDeletion = new OlvidGroupDeletion();
		$groupDeletion->setGroupId($groupId);
		$groupDeletion->setTimestamp($timestamp);
		$groupDeletion->setSignature($signature);
		return $groupDeletion;
	}

	public function getGroupId(): string {
		return $this->groupId;
	}

	public function setGroupId(string $groupId): void {
		$this->groupId = $groupId;
		$this->markFieldUpdated('groupId');
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
		return 'OlvidGroupDeletion{'
			. 'id=' . $this->getId()
			. ', groupId=' . $this->groupId
			. ', timestamp=' . $this->timestamp
			. '}';
	}
}
