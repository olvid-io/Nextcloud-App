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
 * @method string getUserId()
 * @method void setUserId(string $userId)
 *
 * @method string getSignedKick()
 * @method void setSignedKick(string $signedKick)
 *
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 */
class OlvidGroupKicked extends Entity {
	protected string $bytesGroupUid = '';
	protected string $userId = '';
	protected int $timestamp = 0;
	protected string $signedKick = '';

	public function __construct() {
		$this->addType('bytesGroupUid', Types::BLOB);
		$this->addType('userId', Types::STRING);
		$this->addType('timestamp', Types::BIGINT);
		$this->addType('signedKick', Types::TEXT);
	}

	public static function create(string $bytesGroupUid, string $userId, int $currentTimestamp, string $signedKick): OlvidGroupKicked {
		$groupKick = new OlvidGroupKicked();
		$groupKick->setBytesGroupUid($bytesGroupUid);
		$groupKick->setUserId($userId);
		$groupKick->setTimestamp($currentTimestamp);
		$groupKick->setSignedKick($signedKick);
		return $groupKick;
	}


	public function jsonSerialize(): array {
		return [
			'bytesGroupUid' => base64_encode($this->bytesGroupUid),
			'userId' => $this->userId,
			'timestamp' => $this->timestamp,
			'signedKick' => $this->signedKick,
		];
	}

	public function __toString(): string {
		return 'OlvidGroupKicked{'
			. 'id=' . $this->getId()
			. ', bytesGroupUid=' . base64_encode($this->bytesGroupUid)
			. ', userId=' . $this->userId
			. ', timestamp=' . $this->timestamp
			. ', signedKick=' . $this->signedKick
			. '}';
	}
}
