<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use Firebase\JWT\JWT;
use OCA\Olvid\Models\JsonGroupDeletionData;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\TimeUtil;
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
	 * @throws MultipleObjectsReturnedException
	 * @throws DoesNotExistException
	 * @throws Exception
	 */
	public function getByBytesGroupUid(string $bytesGroupUid): OlvidGroupDeletion {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('bytes_group_uid', $qb->createNamedParameter($bytesGroupUid, Types::STRING)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByBytesGroupUidOrNull(string $bytesGroupUid): ?OlvidGroupDeletion {
		try {
			return $this->getByBytesGroupUid($bytesGroupUid);
		} catch (DoesNotExistException) {
			return null;
		}
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

		$groupDeletion = $this->getByBytesGroupUidOrNull($olvidGroup->getGroupId());
		// create a new deletion
		if ($groupDeletion === null) {
			$groupDeletion = OlvidGroupDeletion::create($olvidGroup->getBytesGroupUid(), $currentTimestamp, $signedDeletionData);
			return $this->insert($groupDeletion);
		}
		// update existing deletion
		else {
			$groupDeletion->setSignedDeletion($signedDeletionData);
			$groupDeletion->setTimestamp($currentTimestamp);
			return $this->update($groupDeletion);
		}
	}
}
