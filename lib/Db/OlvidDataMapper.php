<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Exception;
use OCP\IDBConnection;

/**
 * Mapper for the olvid_data table.
 *
 * Rows are keyed by data_uid (base64 of the 32-byte UID sent by the device).
 * The getData handler uses getByUid() to retrieve the blob; avatar upload and
 * storeData use the inherited insert()/update() methods.
 *
 * @template-extends QBMapper<OlvidData>
 */
class OlvidDataMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'olvid_data', OlvidData::class);
	}

	/** @return OlvidData[]
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
	 * @param string $bytesDataUid
	 * @return OlvidData
	 * @throws DoesNotExistException when no row exists for that UID.
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getByUid(string $bytesDataUid): OlvidData {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('bytes_data_uid', $qb->createNamedParameter($bytesDataUid)));
		return $this->findEntity($qb);
	}

	/** Same as getByUid() but returns null instead of throwing on a missing row.
	 * @param string $bytesDataUid
	 * @return OlvidData|null
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getByUidOrNull(string $bytesDataUid): ?OlvidData {
		try {
			return $this->getByUid($bytesDataUid);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
