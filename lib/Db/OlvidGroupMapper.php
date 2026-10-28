<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<OlvidGroup> */
class OlvidGroupMapper extends QBMapper {
	public const TABLE_NAME = 'olvid_group';

	public function __construct(
		IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($db, self::TABLE_NAME, OlvidGroup::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByGroupId(string $groupId): OlvidGroup {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId, Types::STRING)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByGroupIdOrNull(string $groupId): ?OlvidGroup {
		try {
			return $this->getByGroupId($groupId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getOrCreate(string $groupId, string $nextcloudDisplayName): OlvidGroup {
		try {
			return $this->getByGroupId($groupId);
		} catch (DoesNotExistException) {
			try {
				return $this->insert(OlvidGroup::create($groupId, $nextcloudDisplayName));
			} catch (Exception $e) {
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}

				// a concurrent request inserted it first: read the committed row
				try {
					return $this->getByGroupId($groupId);
				} catch (DoesNotExistException) {
					// the row was inserted concurrently, then deleted before we could read it
					throw new Exception('OlvidGroupMapper: getOrCreate: ' . $groupId . ' was deleted concurrently', 0, $e);
				}
			}
		}
	}

	/**
	 * @return OlvidGroup[]
	 * @throws Exception
	 */
	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * @return OlvidGroup[]
	 * @throws Exception
	 */
	public function getEnabledGroups(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(true, Types::BOOLEAN)));
		return $this->findEntities($qb);
	}

	/**
	 * @return OlvidGroup[]
	 * @throws Exception
	 */
	public function getGroupsWithPhoto(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->isNotNull('bytes_group_photo_uid'));
		return $this->findEntities($qb);
	}

	/*
	 * On a group deletion we can automatically delete associated OlvidData for group photo
	 * Do not block group deletion if we cannot delete group photo
	 */
	public function delete(Entity|OlvidGroup $entity): Entity {
		parent::delete($entity);
		// delete associated data
		if ($entity->getBytesGroupPhotoUid()) {
			try {
				$dataMapper = new OlvidDataMapper($this->db);
				$olvidData = $dataMapper->getByUid($entity->getBytesGroupPhotoUid());
				$dataMapper->delete($olvidData);
			} catch (MultipleObjectsReturnedException|Exception $e) {
				$this->logger->error('OlvidGroupMapper::delete cannot delete associated data', ['exception' => $e]);
			} catch (DoesNotExistException) {
				// ignore
			}
		}

		return $entity;
	}

	/**
	 * @throws Exception
	 */
	public function deleteAll(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName());
		$qb->executeStatement();
	}
}
