<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getServerId()
 * @method void setServerId(int $serverId)
 *
 * @method string getRemoteUserId()
 * @method void setRemoteUserId(string $remoteUserId)
 *
 * @method string getBytesIdentity()
 * @method void setBytesIdentity(string $bytesIdentity)
 *
 * @method int getRevocationType()
 * @method void setRevocationType(int $revocationType)
 *
 * @method string getSignedRevocation()
 * @method void setSignedRevocation(string $signedRevocation)
 *
 * @method int getTimestamp()
 * @method void setTimestamp(int $timestamp)
 */
class OlvidFederatedRevocation extends Entity {
	protected int $serverId = 0;
	protected string $remoteUserId = '';
	protected string $bytesIdentity = '';
	protected int $revocationType = 0;
	protected string $signedRevocation = '';
	protected int $timestamp = 0;

	public function __construct() {
		$this->addType('serverId', Types::BIGINT);
		$this->addType('remoteUserId', Types::STRING);
		$this->addType('bytesIdentity', Types::BLOB);
		$this->addType('revocationType', Types::INTEGER);
		$this->addType('signedRevocation', Types::TEXT);
		$this->addType('timestamp', Types::BIGINT);
	}
}
