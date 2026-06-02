<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\QBMapper;
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
}
