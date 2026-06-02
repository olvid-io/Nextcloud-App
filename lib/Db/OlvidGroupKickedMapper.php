<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Types;
use OCP\IDBConnection;

/** @template-extends QBMapper<OlvidGroupKicked> */
class OlvidGroupKickedMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'olvid_group_kicked', OlvidGroupKicked::class);
	}

	public function expungeOlderThan(int $timestamp): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lt('timestamp', $qb->createNamedParameter($timestamp, Types::BIGINT)));
		$qb->executeStatement();
	}

	/** @return OlvidGroupKicked[] */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/** @return OlvidGroupKicked[] */
	public function getSignatureAfterTimestamp(String $userId, int $earliestRevocationTimestamp) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('signature')->from($this->getTableName())
			->where($qb->expr()->gt('timestamp', $qb->createNamedParameter($earliestRevocationTimestamp, Types::BIGINT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntities($qb);
	}
}
