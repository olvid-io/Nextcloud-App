<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use Exception;
use Firebase\JWT\JWT;
use OCA\Olvid\Models\JsonRevocationData;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Types;
use OCP\IDBConnection;

/** @template-extends QBMapper<OlvidRevocation> */
class OlvidRevocationMapper extends QBMapper {
	public const REVOCATION_TYPE_IDENTITY = 0;

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'olvid_revocation', OlvidRevocation::class);
	}

	/** @return OlvidRevocation[]
	 * @throws \OCP\DB\Exception
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName());
		$qb->executeStatement();
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	private function getByUserId(string $userId): ?OlvidRevocation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, Types::STRING)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	private function getByUserIdOrNull(string $userId): ?OlvidRevocation {
		try {
			return $this->getByUserId($userId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @throws \OCP\DB\Exception|MultipleObjectsReturnedException
	 */
	public function computeAndSaveRevocation(string $userId, string $identity, int $revocationType, OlvidAppConfigManager $olvidAppConfig): OlvidRevocation {
		// get signature key
		$keyId = $olvidAppConfig->getJwkKeyId();
		$keyType = $olvidAppConfig->getJwkKeyType();
		$privateKey = $olvidAppConfig->getJwkKeyPrivateKey();

		// sign revocation data
		$currentTimestamp = TimeUtil::currentTimeMillis();
		$revocationData = new JsonRevocationData();
		$revocationData->identity = $identity;
		$revocationData->revocationType = $revocationType;
		$revocationData->timestamp = $currentTimestamp;
		$signedRevocationData = JWT::encode($revocationData->jsonSerialize(), $privateKey, $keyType, $keyId);

		// create or update revocation
		$revocation = $this->getByUserIdOrNull($userId);

		if ($revocation === null) {
			$revocation = OlvidRevocation::create(
				$identity,
				$currentTimestamp,
				$revocationType,
				$signedRevocationData,
				$userId,
			);
		} else {
			$revocation->setRevocationType($revocationType);
			$revocation->setTimestamp($currentTimestamp);
			$revocation->setSignature($signedRevocationData);
		}

		return $this->insertOrUpdate($revocation);
	}
}
