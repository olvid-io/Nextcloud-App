<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use Firebase\JWT\JWT;
use OCA\Olvid\Models\JsonGroupDeletionData;
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
	public function expungeOlderThan(int $timestamp): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lt('timestamp', $qb->createNamedParameter($timestamp, Types::BIGINT)));
		$qb->executeStatement();
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

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByGroupIdOrNull(string $groupId): ?OlvidGroupDeletion {
		try {
			return $this->getByGroupId($groupId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return OlvidGroupDeletion[]
	 * @throws Exception
	 */
	public function getSignatureAfterTimestamp(int $earliestRevocationTimestamp) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('signature')->from($this->getTableName())
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
	public function computeAndSaveGroupDeletion(OlvidAppConfigManager $olvidAppConfig, OlvidGroup $olvidGroup): OlvidGroupDeletion {
		// get signature key
		$keyId = $olvidAppConfig->getJwkKeyId();
		$keyType = $olvidAppConfig->getJwkKeyType();
		$privateKey = $olvidAppConfig->getJwkKeyPrivateKey();

		// sign deletion
		$currentTimestamp = TimeUtil::currentTimeMillis();
		$deletionData = new JsonGroupDeletionData();
		$deletionData->groupUid = $olvidGroup->getGroupUid(); // Olvid group Uid (not nextcloud id)
		$deletionData->timestamp = $currentTimestamp;
		$signedDeletionData = JWT::encode($deletionData->jsonSerialize(), $privateKey, $keyType, $keyId);

		$groupDeletion = $this->getByGroupIdOrNull($olvidGroup->getGroupId());

		// create a new deletion
		if ($groupDeletion === null) {
			$groupDeletion = OlvidGroupDeletion::create($olvidGroup->getGroupId(), $currentTimestamp, $signedDeletionData);
			return $this->insert($groupDeletion);
		}
		// update existing deletion
		else {
			$groupDeletion->setSignature($signedDeletionData);
			$groupDeletion->setTimestamp($currentTimestamp);
			return $this->update($groupDeletion);
		}
	}
}
