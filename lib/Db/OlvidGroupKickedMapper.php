<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Models\JsonGroupMemberKickedData;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;

/** @template-extends QBMapper<OlvidGroupKicked> */
class OlvidGroupKickedMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'olvid_group_kicked', OlvidGroupKicked::class);
	}

	/** @return OlvidGroupKicked[]
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
	private function getByBytesGroupUidAndUserId(string $bytesGroupUid, string $userId): ?OlvidGroupKicked {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('bytes_group_uid', $qb->createNamedParameter($bytesGroupUid, Types::STRING)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, Types::STRING)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	private function getByBytesGroupUidAndUserIdOrNull(string $bytesGroupUid, string $userId): ?OlvidGroupKicked {
		try {
			return $this->getByBytesGroupUidAndUserId($bytesGroupUid, $userId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return OlvidGroupKicked[]
	 * @throws Exception
	 */
	public function getByUserIdAfterTimestamp(String $userId, int $earliestRevocationTimestamp) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->gt('timestamp', $qb->createNamedParameter($earliestRevocationTimestamp, Types::BIGINT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntities($qb);
	}

	/*
	 ** Helper methods
	 */
	/**
	 * Compute and sign a group kick to remove a user from a group and store it in database (create or update existing kick).
	 * We pass userId and bytesUserIdentity as we probably already deleted them from OlvidUser, not to add user in group blob after re-computing.
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function computeAndSaveGroupKick(OlvidGroup $olvidGroup, string $userId, string $bytesUserIdentity, OlvidContext $context): OlvidGroupKicked {
		// sign kick
		$currentTimestamp = TimeUtil::currentTimeMillis();
		$kickData = new JsonGroupMemberKickedData();
		$kickData->bytesGroupUid = $olvidGroup->getBytesGroupUid();
		$kickData->base64Identity = base64_encode($bytesUserIdentity);
		$kickData->timestamp = $currentTimestamp;
		$signedKickData = $context->signatory->sign($kickData->jsonSerialize());

		$groupKick = $this->getByBytesGroupUidAndUserIdOrNull($olvidGroup->getGroupId(), $userId);

		// create a new kick
		if ($groupKick === null) {
			$groupKick = OlvidGroupKicked::create($olvidGroup->getGroupId(), $userId, $currentTimestamp, $signedKickData);
			return $this->insert($groupKick);
		}
		// update existing deletion
		else {
			$groupKick->setSignedKick($signedKickData);
			$groupKick->setTimestamp($currentTimestamp);
			return $this->update($groupKick);
		}
	}
}
