<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Olvid group Uid as bytes
 * @method string getBytesGroupUid()
 * @method void setBytesGroupUid(string $bytesGroupUid)
 *
 * @method string getSignedDeletion()
 * @method void setSignedDeletion(string $signedDeletion)
 *
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 */
class OlvidGroupDeletion extends Entity {
	protected string $bytesGroupUid = '';
	protected int $timestamp = 0;
	protected string $signedDeletion = '';

	public function __construct() {
		// Olvid group uid as bytes
		$this->addType('bytesGroupUid', Types::BLOB);
		$this->addType('signedDeletion', Types::TEXT);
		$this->addType('timestamp', Types::BIGINT);
	}

	public static function create(string $bytesGroupUid, int $timestamp, string $signedDeletion): OlvidGroupDeletion {
		$groupDeletion = new OlvidGroupDeletion();
		$groupDeletion->setBytesGroupUid($bytesGroupUid);
		$groupDeletion->setTimestamp($timestamp);
		$groupDeletion->setSignedDeletion($signedDeletion);
		return $groupDeletion;
	}

	public function jsonSerialize(): array {
		return [
			'bytesGroupUid' => base64_encode($this->bytesGroupUid),
			'timestamp' => $this->timestamp,
			'signedDeletion' => $this->signedDeletion,
		];
	}

	public function __toString(): string {
		return 'OlvidGroupDeletion{'
			. 'id=' . $this->getId()
			. ', bytesGroupUid=' . base64_encode($this->bytesGroupUid)
			. ', timestamp=' . $this->timestamp
			. ', signedDeletion=' . $this->signedDeletion
			. '}';
	}
}
