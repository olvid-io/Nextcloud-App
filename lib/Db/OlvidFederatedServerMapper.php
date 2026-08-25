<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\DB\Types;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

/** @template-extends QBMapper<OlvidFederatedServer> */
class OlvidFederatedServerMapper extends QBMapper {
	public const TABLE_NAME = 'olvid_fed_server';

	public function __construct(
		IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($db, self::TABLE_NAME, OlvidFederatedServer::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByRemoteUrl(string $remoteUrl): OlvidFederatedServer {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('remote_url', $qb->createNamedParameter($remoteUrl, Types::STRING)));
		return $this->findEntity($qb);
	}

	public function getByRemoteUrlOrNull(string $remoteUrl): ?OlvidFederatedServer {
		try {
			return $this->getByRemoteUrl($remoteUrl);
		} catch (DoesNotExistException) {
			return null;
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error('OlvidFederatedServerMapper: getByRemoteUrlOrNull: unexpected exception', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getById(int $id): OlvidFederatedServer {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, Types::INTEGER)));
		return $this->findEntity($qb);
	}

	public function getByIdOrNull(int $id): ?OlvidFederatedServer {
		try {
			return $this->getById($id);
		} catch (DoesNotExistException) {
			return null;
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error('OlvidFederatedServerMapper: getByIdOrNull: unexpected exception', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @return OlvidFederatedServer[]
	 * @throws Exception
	 */
	public function getAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName());
		return $this->findEntities($qb);
	}

	/**
	 * @return OlvidFederatedServer[]
	 * @throws Exception
	 */
	public function getEnabledOlvidCapable(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('enabled', $qb->createNamedParameter(true, Types::BOOLEAN)))
			->andWhere($qb->expr()->eq('olvid_capable', $qb->createNamedParameter(true, Types::BOOLEAN)));
		return $this->findEntities($qb);
	}

	/**
	 * Finds the enabled+capable federated server whose domain (remote URL, scheme stripped)
	 * matches the given domain. Used to route a `uid@domain` federated identity to its origin.
	 */
	public function getEnabledByDomainOrNull(string $domain): ?OlvidFederatedServer {
		foreach ($this->getEnabledOlvidCapable() as $server) {
			if (strcasecmp($server->getDomain(), $domain) === 0) {
				return $server;
			}
		}
		return null;
	}

	/**
	 * @throws Exception
	 */
	public function deleteByRemoteUrl(string $remoteUrl): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('remote_url', $qb->createNamedParameter($remoteUrl, Types::STRING)));
		$qb->executeStatement();
	}
}
