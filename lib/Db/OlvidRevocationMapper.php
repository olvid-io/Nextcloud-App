<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use Exception;
use OCA\Olvid\Models\JsonRevocationData;
use OCA\Olvid\Utils\Context\OlvidContext;
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
	public function getAll(): array {
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
	private function getByUserIdAndBytesIdentity(string $userId, string $bytesIdentity): ?OlvidRevocation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, Types::STRING)))
			->andWhere($qb->expr()->eq('bytes_identity', $qb->createNamedParameter($bytesIdentity, Types::BLOB)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	private function getByUserIdAndBytesIdentityOrNull(string $userId, string $bytesIdentity): ?OlvidRevocation {
		try {
			return $this->getByUserIdAndBytesIdentity($userId, $bytesIdentity);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return ?OlvidRevocation[]
	 */
	public function getSinceTimestampOrNull(int $timestamp): ?array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from($this->getTableName())
				->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($timestamp, Types::BIGINT)));
			return $this->findEntities($qb);
		} catch (Exception $e) {
			$this->logger->error('getSinceTimestampOrNull', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @return ?OlvidRevocation[]
	 */
	public function getUserRevocationsSinceTimestampOrNull(string $userId, int $timestamp): ?array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from($this->getTableName())
				->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($timestamp, Types::BIGINT)))
				->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, Types::STRING)));
			return $this->findEntities($qb);
		} catch (Exception $e) {
			$this->logger->error('getUserRevocationsSinceTimestampOrNull', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Search revocations by type for a given identity.
	 * @return ?OlvidRevocation[]
	 */
	public function getByTypeAndBytesIdentityOrNull(string $bytesIdentity, int $revocationType): ?array {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')->from($this->getTableName())
				->where($qb->expr()->eq('bytes_identity', $qb->createNamedParameter($bytesIdentity, Types::BLOB)))
				->andWhere($qb->expr()->eq('revocation_type', $qb->createNamedParameter($revocationType, Types::INTEGER)));
			return $this->findEntities($qb);
		} catch (Exception $e) {
			$this->logger->error('findRevokeByIdentityOrNull', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function computeAndSaveRevocation(string $userId, string $bytesIdentity, int $revocationType, OlvidContext $context): OlvidRevocation {
		// sign revocation data
		$currentTimestamp = TimeUtil::currentTimeMillis();
		$revocationData = new JsonRevocationData();
		$revocationData->identity = $bytesIdentity;
		$revocationData->revocationType = $revocationType;
		$revocationData->timestamp = $currentTimestamp;
		$signedRevocationData = $context->signatory->sign($revocationData->jsonSerialize());

		// create or update revocation
		$revocation = $this->getByUserIdAndBytesIdentityOrNull($userId, $bytesIdentity);

		if ($revocation === null) {
			$revocation = OlvidRevocation::create(
				$userId,
				$bytesIdentity,
				$revocationType,
				$signedRevocationData,
				$currentTimestamp,
			);
			return $this->insert($revocation);
		} else {
			$revocation->setRevocationType($revocationType);
			$revocation->setTimestamp($currentTimestamp);
			$revocation->setSignedRevocation($signedRevocationData);
			return $this->update($revocation);
		}
	}
}
