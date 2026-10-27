<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Models\JsonGroupDeletionData;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\TimeUtil;
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

	/** @return OlvidGroupDeletion[]
	 * @throws Exception
	 */
	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * @return OlvidGroupDeletion[]
	 * @throws Exception
	 */
	public function getAllByBytesGroupUidOrNull(string $bytesGroupUid): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('bytes_group_uid', $qb->createNamedParameter($bytesGroupUid, Types::BLOB)));
		return $this->findEntities($qb);
	}

	/** @return OlvidGroupDeletion[]
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

		$groupDeletions = $this->getAllByBytesGroupUidOrNull($olvidGroup->getBytesGroupUid());
		// create a new deletion
		if (sizeof($groupDeletions) === 0) {
			$groupDeletion = OlvidGroupDeletion::create($olvidGroup->getBytesGroupUid(), $currentTimestamp, $signedDeletionData);
			return $this->insert($groupDeletion);
		}
		// update existing deletion
		else {
			// safeguard: delete any extra group deletion
			if (sizeof($groupDeletions) > 1) {
				for ($i = 1; $i < sizeof($groupDeletions); $i++) {
					$this->delete($groupDeletions[$i]);
				}
			}
			$groupDeletion = $groupDeletions[0];
			$groupDeletion->setSignedDeletion($signedDeletionData);
			$groupDeletion->setTimestamp($currentTimestamp);
			return $this->update($groupDeletion);
		}
	}
}
