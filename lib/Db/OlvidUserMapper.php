<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<OlvidUser> */
class OlvidUserMapper extends QBMapper {
	public function __construct(
		IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($db, 'olvid_user', OlvidUser::class);
	}

	/**
	 * @param string $userId
	 * @return OlvidUser
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByUserId(string $userId): OlvidUser {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));
		return $this->findEntity($qb);
	}

	/**
	 * @param string[] $userIds
	 * @return OlvidUser[]
	 * @throws Exception
	 */
	public function getUsersById(array $userIds): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->in('user_id', $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY)));
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @return ?OlvidUser
	 */
	public function getByUserIdOrNull(string $userId): ?OlvidUser {
		try {
			return $this->getByUserId($userId);
		} catch (DoesNotExistException) {
			return null;
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error('OlvidUserMapper: getByUserIdOrNull: unexpected exception', ['exception' => $e]);
			return OlvidUser::create($userId);
		}
	}

	/**
	 * @param string $userId
	 * @return OlvidUser
	 *
	 */
	public function getOrCreate(string $userId): OlvidUser {
		try {
			$user = $this->getByUserIdOrNull($userId);
			return $user === null ? $this->insert(OlvidUser::create($userId)) : $user;
		} catch (Exception $e) {
			$this->logger->error('OlvidUserMapper: getOrCreate: unexpected exception', ['exception' => $e]);
			return OlvidUser::create($userId);
		}
	}

	/**
	 * @param string $nonce
	 * @return array[OlvidUser]
	 * @throws Exception
	 */
	public function searchNonce(string $nonce): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('nonce', $qb->createNamedParameter($nonce)));
		return $this->findEntities($qb);
	}

	/**
	 * @param string $userId
	 * @return bool
	 */
	public function hasUserAnIdentity(string $userId): bool {
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('id')->from($this->getTableName())
				->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->isNotNull('bytes_identity'));
			$this->findOneQuery($qb);
			return true;
		} catch (DoesNotExistException) {
			return false;
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error('OlvidUserMapper: hasUserAnIdentity: unexpected exception', ['exception' => $e]);
			return false;
		}
	}

	/**
	 * @param OlvidUser $olvidUser
	 * @return OlvidUser|null
	 */
	public function updateNoFail(OlvidUser $olvidUser): ?OlvidUser {
		try {
			return $this->update($olvidUser);
		} catch (Exception) {
			return null;
		}
	}

	/**
	 * @return OlvidUser[]
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
}
