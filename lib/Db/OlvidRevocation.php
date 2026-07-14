<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getBytesIdentity()
 * @method void setBytesIdentity(string $identity)
 * @method int getRevocationType()
 * @method void setRevocationType(int $revocationType)
 * @method string getSignedRevocation()
 * @method void setSignedRevocation(string $signedRevocation)
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 */
class OlvidRevocation extends Entity {
	protected string $userId = '';
	protected string $bytesIdentity = '';
	// initialize to null, else they won't be considered as updated if set to 0
	protected ?int $revocationType = null;
	protected string $signedRevocation = '';
	protected int $timestamp = 0;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('bytesIdentity', Types::BLOB);
		$this->addType('timestamp', Types::BIGINT);
		$this->addType('revocationType', Types::INTEGER);
		$this->addType('signedRevocation', Types::TEXT);
	}

	public static function create(string $userId, string $bytesIdentity, int $revocationType, string $signedRevocation, int $timestamp): OlvidRevocation {
		$revocation = new OlvidRevocation();
		$revocation->setBytesIdentity($bytesIdentity);
		$revocation->setTimestamp($timestamp);
		$revocation->setRevocationType($revocationType);
		$revocation->setSignedRevocation($signedRevocation);
		$revocation->setUserId($userId);
		return $revocation;
	}

	public function jsonSerialize(): array {
		return [
			'userId' => $this->userId,
			'bytesIdentity' => base64_encode($this->bytesIdentity),
			'revocationType' => $this->revocationType,
			'signedRevocation' => $this->signedRevocation,
			'timestamp' => $this->timestamp
		];
	}

	public function __toString(): string {
		return 'OlvidRevocation{'
			. 'id=' . $this->getId()
			. ', userId=' . $this->userId
			. ', bytesIdentity=' . base64_encode($this->bytesIdentity)
			. ', revocationType=' . $this->revocationType
			. ', signedRevocation=' . $this->signedRevocation
			. ', timestamp=' . $this->timestamp
			. '}';
	}
}
