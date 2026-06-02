<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;

/** @template-extends QBMapper<OlvidGroupDeletion> */
class OlvidGroupDeletionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'olvid_group_deletion', OlvidGroupDeletion::class);
	}

	public function expungeOlderThan(int $timestamp): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lt('timestamp', $qb->createNamedParameter($timestamp, Types::BIGINT)));
		$qb->executeStatement();
	}

	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName());
		$qb->executeStatement();
	}

	/** @return OlvidGroupDeletion[] */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function getByGroupId(string $groupId): OlvidGroupDeletion {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId, Types::STRING)));
		return $this->findEntity($qb);
	}

	public function getByGroupIdOrNull(string $groupId): ?OlvidGroupDeletion {
		try {
			return $this->getByGroupId($groupId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return OlvidGroupDeletion[] */
	public function getSignatureAfterTimestamp(int $earliestRevocationTimestamp) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('signature')->from($this->getTableName())
			->where($qb->expr()->gt('timestamp', $qb->createNamedParameter($earliestRevocationTimestamp, Types::BIGINT)));
		return $this->findEntities($qb);
	}
}
