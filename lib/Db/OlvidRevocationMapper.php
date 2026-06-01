<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\Types;
use OCP\IDBConnection;

/** @template-extends QBMapper<OlvidRevocation> */
class OlvidRevocationMapper extends QBMapper {
	public const REVOCATION_TYPE_IDENTITY = 0;

	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'olvid_revocation', OlvidRevocation::class);
	}

	public function findActiveByOlvidId(string $olvidId): ?OlvidRevocation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($this->getTableName())
			->where($qb->expr()->eq('olvid_id', $qb->createNamedParameter($olvidId)))
			->andWhere($qb->expr()->eq('revocation_type', $qb->createNamedParameter(self::REVOCATION_TYPE_IDENTITY, Types::INTEGER)));
		try {
			return $this->findEntity($qb);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function eraseUserData(int $id): void {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('firstname',           $qb->createNamedParameter(null))
			->set('lastname',            $qb->createNamedParameter(null))
			->set('mail',                $qb->createNamedParameter(null))
			->set('position',            $qb->createNamedParameter(null))
			->set('company',             $qb->createNamedParameter(null))
			->set('username',            $qb->createNamedParameter(null))
			->set('full_search_string',  $qb->createNamedParameter(null))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, Types::BIGINT)));
		$qb->executeStatement();
	}
}
