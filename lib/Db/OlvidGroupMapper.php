<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;
use function PHPUnit\Framework\throwException;

/** @template-extends QBMapper<OlvidGroup> */
class OlvidGroupMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'olvid_group', OlvidGroup::class);
	}

	/**
	 * @throws DoesNotExistException
	 */
	public function findByGroupId(string $groupId): OlvidGroup {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId)));
		return $this->findEntity($qb);
	}

	public function findByGroupIdOrNull(string $groupId): ?OlvidGroup {
		try {
			return $this->findByGroupId($groupId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return OlvidGroup[] */
	public function findAllEnabled(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(true, Types::BOOLEAN)));
		return $this->findEntities($qb);
	}

	/** @return OlvidGroup[] */
	public function findAllWithPushTopic(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->isNotNull('push_topic'));
		return $this->findEntities($qb);
	}

	/** @return OlvidGroup[] */
	public function findAllWithGroupPhoto(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->isNotNull('group_photo_uid'));
		return $this->findEntities($qb);
	}

	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName());
		$qb->executeStatement();
	}
}
