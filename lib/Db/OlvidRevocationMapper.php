<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Models\JsonRevocationData;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<OlvidRevocation> */
class OlvidRevocationMapper extends QBMapper {
	public const TABLE_NAME = 'olvid_revocation';

	public function __construct(
		IDBConnection $db,
		protected readonly LoggerInterface $logger,
	) {
		parent::__construct($db, self::TABLE_NAME, OlvidRevocation::class);
	}

	/** @return OlvidRevocation[]
	 * @throws Exception
	 */
	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
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
			->andWhere($qb->expr()->eq('bytes_identity', $qb->createNamedParameter($bytesIdentity, Types::BINARY)));
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
	 * @return OlvidRevocation[]
	 * @throws Exception
	 */
	public function getSinceTimestamp(int $timestamp): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($timestamp, Types::BIGINT)));
		return $this->findEntities($qb);
	}

	/**
	 * @return OlvidRevocation[]
	 * @throws Exception
	 */
	public function getUserRevocationsSinceTimestamp(string $userId, int $timestamp): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->gte('timestamp', $qb->createNamedParameter($timestamp, Types::BIGINT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, Types::STRING)));
		return $this->findEntities($qb);
	}

	/**
	 * Search revocations by type for a given identity.
	 * @return OlvidRevocation[]
	 * @throws Exception
	 */
	public function getByTypeAndBytesIdentity(string $bytesIdentity, int $revocationType): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('bytes_identity', $qb->createNamedParameter($bytesIdentity, Types::BINARY)))
			->andWhere($qb->expr()->eq('revocation_type', $qb->createNamedParameter($revocationType, Types::INTEGER)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
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
			try {
				return $this->insert($revocation);
			} catch (Exception $e) {
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
				// a concurrent request inserted it first: read the committed row
				$revocation = $this->getByUserIdAndBytesIdentityOrNull($userId, $bytesIdentity);
				if ($revocation === null) {
					// the row was inserted concurrently, then deleted before we could read it
					throw new Exception('OlvidRevocationMapper: computeAndSaveRevocation ' . $userId . ' was deleted concurrently', 0, $e);
				}
			}
		}

		// else update existing revocation
		$revocation->setRevocationType($revocationType);
		$revocation->setTimestamp($currentTimestamp);
		$revocation->setSignedRevocation($signedRevocationData);
		return $this->update($revocation);
	}
}
