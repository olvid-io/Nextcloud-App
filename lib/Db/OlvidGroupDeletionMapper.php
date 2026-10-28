<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Models\JsonGroupDeletionData;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;

/** @template-extends QBMapper<OlvidGroupDeletion> */
class OlvidGroupDeletionMapper extends QBMapper {
	public const TABLE_NAME = 'olvid_group_deletion';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME, OlvidGroupDeletion::class);
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
	 * @throws Exception
	 */
	public function deleteExpiredElements(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lte('timestamp', $qb->createNamedParameter(TimeUtil::currentTimeMillis() - Constants::DEFAULT_REVOCATION_LISTS_MAX_AGE_MILLIS, Types::BIGINT)));
		$qb->executeStatement();
	}

	/** @return OlvidGroupDeletion[]
	 * @throws Exception
	 */
	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getByBytesGroupUidOrNull(string $bytesGroupUid): ?OlvidGroupDeletion {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('bytes_group_uid', $qb->createNamedParameter($bytesGroupUid, Types::BINARY)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @return list<OlvidGroupDeletion>
	 * @throws Exception
	 */
	public function getAfterTimestamp(int $earliestRevocationTimestamp) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->gt('timestamp', $qb->createNamedParameter($earliestRevocationTimestamp, Types::BIGINT)));
		return $this->findEntities($qb);
	}

	/*
	 ** Helper methods
	 */
	/**
	 * Compute and sign a group deletion for the group and store it in database (create or update existing deletion).
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function computeAndSaveGroupDeletion(OlvidContext $context, OlvidGroup $olvidGroup): OlvidGroupDeletion {
		// sign deletion
		$currentTimestamp = TimeUtil::currentTimeMillis();
		$deletionData = new JsonGroupDeletionData();
		$deletionData->bytesGroupUid = $olvidGroup->getBytesGroupUid();
		$deletionData->timestamp = $currentTimestamp;
		$signedDeletionData = $context->signatory->sign($deletionData->jsonSerialize());

		$groupDeletion = $this->getByBytesGroupUidOrNull($olvidGroup->getBytesGroupUid());
		// create a new deletion
		if ($groupDeletion === null) {
			$groupDeletion = OlvidGroupDeletion::create($olvidGroup->getBytesGroupUid(), $currentTimestamp, $signedDeletionData);
			try {
				return $this->insert($groupDeletion);
			} catch (Exception $e) {
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}

				// a concurrent request inserted it first: read the committed row
				$groupDeletion = $this->getByBytesGroupUidOrNull($olvidGroup->getBytesGroupUid());
				if ($groupDeletion === null) {
					// the row was inserted concurrently, then deleted before we could read it
					throw new Exception('OlvidGroupDeletion: computeAndSaveGroupDeletion ' . $olvidGroup->getGroupId() . ' was deleted concurrently', 0, $e);
				}
			}
		}

		// else update existing deletion
		$groupDeletion->setSignedDeletion($signedDeletionData);
		$groupDeletion->setTimestamp($currentTimestamp);
		return $this->update($groupDeletion);
	}
}
