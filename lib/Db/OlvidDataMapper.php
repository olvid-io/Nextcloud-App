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
	 * Find a data row by its base64-encoded UID.
	 *
	 * @param string $dataUid base64( raw 32-byte UID )
	 * @return OlvidData
	 * @throws DoesNotExistException when no row exists for that UID.
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function getByUid(string $dataUid): OlvidData {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->where($qb->expr()->eq('data_uid', $qb->createNamedParameter($dataUid)));
		return $this->findEntity($qb);
	}

	/** Same as getByUid() but returns null instead of throwing on a missing row.
	 * @param string $dataUid
	 * @return OlvidData|null
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function getByUidOrNull(string $dataUid): ?OlvidData {
		try {
			return $this->getByUid($dataUid);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
