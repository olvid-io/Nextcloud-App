<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\DB\Types;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<OlvidFederatedUser> */
class OlvidFederatedUserMapper extends QBMapper {
	public const TABLE_NAME = 'olvid_fed_user';

	public function __construct(
		IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($db, self::TABLE_NAME, OlvidFederatedUser::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByServerAndRemoteUserId(int $serverId, string $remoteUserId): OlvidFederatedUser {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('server_id', $qb->createNamedParameter($serverId, Types::INTEGER)))
			->andWhere($qb->expr()->eq('remote_user_id', $qb->createNamedParameter($remoteUserId, Types::STRING)));
		return $this->findEntity($qb);
	}

	public function getByServerAndRemoteUserIdOrNull(int $serverId, string $remoteUserId): ?OlvidFederatedUser {
		try {
			return $this->getByServerAndRemoteUserId($serverId, $remoteUserId);
		} catch (DoesNotExistException) {
			return null;
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error('OlvidFederatedUserMapper: getByServerAndRemoteUserIdOrNull: unexpected exception', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Upserts a synced federated user record. Called by OlvidFederationSyncTask only.
	 * @throws Exception
	 */
	public function upsert(
		int $serverId,
		string $remoteUserId,
		string $bytesIdentity,
		string $signedDetails,
		?string $firstname,
		?string $lastname,
		?string $position,
		?string $company,
		?string $fullSearchField,
		int $registrationTimestamp,
	): OlvidFederatedUser {
		$existing = $this->getByServerAndRemoteUserIdOrNull($serverId, $remoteUserId);
		$user = $existing ?? new OlvidFederatedUser();
		$user->setServerId($serverId);
		$user->setRemoteUserId($remoteUserId);
		$user->setBytesIdentity($bytesIdentity);
		$user->setSignedDetails($signedDetails);
		$user->setFirstname($firstname);
		$user->setLastname($lastname);
		$user->setPosition($position);
		$user->setCompany($company);
		$user->setFullSearchField($fullSearchField);
		$user->setRegistrationTimestamp($registrationTimestamp);

		return $existing === null ? $this->insert($user) : $this->update($user);
	}

	/**
	 * @param string[] $filters
	 * @return OlvidFederatedUser[]
	 * @throws Exception
	 */
	public function searchByServers(array $serverIds, array $filters): array {
		if (empty($serverIds)) {
			return [];
		}
		$qb = $this->db->getQueryBuilder()->select('*')->from($this->getTableName());
		$qb = $qb->where($qb->expr()->in('server_id', $qb->createNamedParameter($serverIds, IQueryBuilder::PARAM_INT_ARRAY)));
		foreach ($filters as $filter) {
			$qb = $qb->andWhere($qb->expr()->like('full_search_field', $qb->createNamedParameter('%' . strtolower($filter) . '%', Types::STRING)));
		}
		return $this->findEntities($qb);
	}

	/**
	 * @return OlvidFederatedUser[]
	 * @throws Exception
	 */
	public function getAllByServer(int $serverId): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('server_id', $qb->createNamedParameter($serverId, Types::INTEGER)));
		return $this->findEntities($qb);
	}

	/**
	 * @throws Exception
	 */
	public function deleteByServer(int $serverId): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('server_id', $qb->createNamedParameter($serverId, Types::INTEGER)));
		$qb->executeStatement();
	}
}
