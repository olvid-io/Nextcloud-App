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
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<OlvidRevocation> */
class OlvidRevocationMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		protected readonly LoggerInterface $logger,
	) {
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
	private function getByUserIdAndIdentity(string $userId, string $signature): ?OlvidRevocation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			// TODO db rename to user_id when rename table
			->where($qb->expr()->eq('username', $qb->createNamedParameter($userId, Types::STRING)))
			// TODO db rename to identity when refactor
			->andWhere($qb->expr()->eq('olvid_id', $qb->createNamedParameter($signature, Types::STRING)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	private function getByUserIdAndIdentityOrNull(string $userId, string $signature): ?OlvidRevocation {
		try {
			return $this->getByUserIdAndIdentity($userId, $signature);
		} catch (DoesNotExistException $e) {
			return null;
		}
	}

	/**
	 * @return ?OlvidRevocation[]
	 */
	public function findSignedRevocationsSinceTimestampOrNull(int $timestamp): ?array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('signature')->from($this->getTableName())
				->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($timestamp, Types::BIGINT)));
			return $this->findEntities($qb);
		} catch (Exception $e) {
			$this->logger->error('findSignedRevocationsSinceTimestampOrNull', ['exception' => $e]);
			return null;
		}
	}


	/**
	 * Search revocations with type REVOCATION_TYPE_REVOKE_ID for a given identity.
	 * @return ?OlvidRevocation[]
	 */
	public function findRevokeByIdentityOrNull(string $identity): ?array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from($this->getTableName())
				// TODO db rename to identity when refactor
				->where($qb->expr()->eq('olvid_id', $qb->createNamedParameter($identity, Types::TEXT)))
				->andWhere($qb->expr()->eq('revocation_type', $qb->createNamedParameter(JsonRevocationData::REVOCATION_TYPE_REVOKE_ID, Types::INTEGER)));
			return $this->findEntities($qb);
		} catch (Exception $e) {
			$this->logger->error('findByIdentityOrNull', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @throws \OCP\DB\Exception|MultipleObjectsReturnedException
	 */
	public function computeAndSaveRevocation(string $userId, string $b64Identity, int $revocationType, OlvidAppConfigManager $olvidAppConfig): OlvidRevocation {
		// get signature key
		$keyId = $olvidAppConfig->getJwkKeyId();
		$keyType = $olvidAppConfig->getJwkKeyType();
		$privateKey = $olvidAppConfig->getJwkKeyPrivateKey();

		// sign revocation data
		$currentTimestamp = TimeUtil::currentTimeMillis();
		$revocationData = new JsonRevocationData();
		$revocationData->identity = base64_decode($b64Identity);
		$revocationData->revocationType = $revocationType;
		$revocationData->timestamp = $currentTimestamp;
		$signedRevocationData = JWT::encode($revocationData->jsonSerialize(), $privateKey, $keyType, $keyId);

		// create or update revocation
		$revocation = $this->getByUserIdAndIdentityOrNull($userId, $b64Identity);

		if ($revocation === null) {
			$revocation = OlvidRevocation::create(
				$b64Identity,
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
