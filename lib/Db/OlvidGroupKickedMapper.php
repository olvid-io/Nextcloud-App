<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCA\Olvid\Api\Constants;
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
	public const TABLE_NAME = 'olvid_group_kicked';

	public function __construct(IDBConnection $db) {
		parent::__construct($db, self::TABLE_NAME, OlvidGroupKicked::class);
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
	 * @throws Exception
	 */
	public function deleteExpiredElements(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->lte('timestamp', $qb->createNamedParameter(TimeUtil::currentTimeMillis() - Constants::DEFAULT_REVOCATION_LISTS_MAX_AGE_MILLIS, Types::BIGINT)));
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
			->where($qb->expr()->eq('bytes_group_uid', $qb->createNamedParameter($bytesGroupUid, Types::BINARY)))
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

	/**
	 * @return list<OlvidGroupKicked>
	 * @throws Exception
	 */
	public function getByUserIdAfterTimestamp(String $userId, int $earliestRevocationTimestamp) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->gt('timestamp', $qb->createNamedParameter($earliestRevocationTimestamp, Types::BIGINT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, Types::STRING)));
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

		$groupKick = $this->getByBytesGroupUidAndUserIdOrNull($olvidGroup->getBytesGroupUid(), $userId);

		// create a new kick
		if ($groupKick === null) {
			$groupKick = OlvidGroupKicked::create($olvidGroup->getBytesGroupUid(), $userId, $currentTimestamp, $signedKickData);
			try {
				return $this->insert($groupKick);
			} catch (Exception $e) {
				if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}

				// a concurrent request inserted it first: read the committed row
				$groupKick = $this->getByBytesGroupUidAndUserIdOrNull($olvidGroup->getBytesGroupUid(), $userId);
				if ($groupKick === null) {
					// the row was inserted concurrently, then deleted before we could read it
					throw new Exception('OlvidGroupKicked: computeAndSaveGroupKick ' . $olvidGroup->getGroupId() . ' / ' . $userId . ' was deleted concurrently', 0, $e);
				}
			}
		}

		// else update existing kick
		$groupKick->setSignedKick($signedKickData);
		$groupKick->setTimestamp($currentTimestamp);
		return $this->update($groupKick);
	}
}
