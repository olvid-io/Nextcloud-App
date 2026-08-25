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

/** @template-extends QBMapper<OlvidFederatedRevocation> */
class OlvidFederatedRevocationMapper extends QBMapper {
	public const TABLE_NAME = 'olvid_fed_revocation';

	public function __construct(
		IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($db, self::TABLE_NAME, OlvidFederatedRevocation::class);
	}

	/**
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	private function getByServerAndIdentity(int $serverId, string $bytesIdentity): OlvidFederatedRevocation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('server_id', $qb->createNamedParameter($serverId, Types::INTEGER)))
			->andWhere($qb->expr()->eq('bytes_identity', $qb->createNamedParameter($bytesIdentity, Types::BLOB)));
		return $this->findEntity($qb);
	}

	private function getByServerAndIdentityOrNull(int $serverId, string $bytesIdentity): ?OlvidFederatedRevocation {
		try {
			return $this->getByServerAndIdentity($serverId, $bytesIdentity);
		} catch (DoesNotExistException) {
			return null;
		} catch (MultipleObjectsReturnedException|Exception $e) {
			$this->logger->error('OlvidFederatedRevocationMapper: getByServerAndIdentityOrNull: unexpected exception', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Upserts a synced federated revocation record. Called by OlvidFederationSyncTask only.
	 * @throws Exception
	 */
	public function upsert(
		int $serverId,
		string $remoteUserId,
		string $bytesIdentity,
		int $revocationType,
		string $signedRevocation,
		int $timestamp,
	): OlvidFederatedRevocation {
		$existing = $this->getByServerAndIdentityOrNull($serverId, $bytesIdentity);
		$revocation = $existing ?? new OlvidFederatedRevocation();
		$revocation->setServerId($serverId);
		$revocation->setRemoteUserId($remoteUserId);
		$revocation->setBytesIdentity($bytesIdentity);
		$revocation->setRevocationType($revocationType);
		$revocation->setSignedRevocation($signedRevocation);
		$revocation->setTimestamp($timestamp);

		return $existing === null ? $this->insert($revocation) : $this->update($revocation);
	}

	/**
	 * @return OlvidFederatedRevocation[]
	 * @throws Exception
	 */
	public function getByBytesIdentity(string $bytesIdentity): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('bytes_identity', $qb->createNamedParameter($bytesIdentity, Types::BLOB)));
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
