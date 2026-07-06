<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use Firebase\JWT\JWT;
use OCA\Olvid\Models\JsonGroupMemberKickedData;
use OCA\Olvid\Utils\OlvidAppConfigManager;
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
	public function findAll(): array {
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
	private function getByGroupAndUserId(string $groupId, string $userId): ?OlvidGroupKicked {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('group_id', $qb->createNamedParameter($groupId, Types::STRING)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId, Types::STRING)));
		return $this->findEntity($qb);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	private function getByGroupAndUserIdOrNull(string $groupId, string $userId): ?OlvidGroupKicked {
		try {
			return $this->getByGroupAndUserId($groupId, $userId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return OlvidGroupKicked[]
	 * @throws Exception
	 */
	public function getSignatureAfterTimestamp(String $userId, int $earliestRevocationTimestamp) : array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('signature')->from($this->getTableName())
			->where($qb->expr()->gt('timestamp', $qb->createNamedParameter($earliestRevocationTimestamp, Types::BIGINT)))
			->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntities($qb);
	}

	/*
 ** Helper methods
 */
	/**
	 * Compute and sign a group kick to remove a user from a group and store it in database (create or update existing kick).
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function computeAndSaveGroupKick(OlvidAppConfigManager $olvidAppConfig, OlvidGroup $olvidGroup, String $userId, String $userIdentity): OlvidGroupKicked {
		// get signature key
		$keyId = $olvidAppConfig->getJwkKeyId();
		$keyType = $olvidAppConfig->getJwkKeyType();
		$privateKey = $olvidAppConfig->getJwkKeyPrivateKey();

		// sign kick
		$currentTimestamp = TimeUtil::currentTimeMillis();
		$kickData = new JsonGroupMemberKickedData();
		$kickData->groupUid = $olvidGroup->getGroupUid(); // Olvid group Uid (not nextcloud id)
		$kickData->identity = $userIdentity;
		$kickData->timestamp = $currentTimestamp;
		$signedKickData = JWT::encode($kickData->jsonSerialize(), $privateKey, $keyType, $keyId);

		$groupKick = $this->getByGroupAndUserIdOrNull($olvidGroup->getGroupId(), $userId);

		// create a new kick
		if ($groupKick === null) {
			$groupKick = OlvidGroupKicked::create($olvidGroup->getGroupId(), $userId, $currentTimestamp, $signedKickData);
			return $this->insert($groupKick);
		}
		// update existing deletion
		else {
			$groupKick->setSignature($signedKickData);
			$groupKick->setTimestamp($currentTimestamp);
			return $this->update($groupKick);
		}
	}
}
