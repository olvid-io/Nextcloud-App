<?php

declare(strict_types=1);

namespace OCA\Olvid\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * Creates the olvid_data table used by the getData / storeData endpoints.
 *
 * Each row stores a blob that an Olvid device uploaded via storeData.
 * The server re-encrypts the blob with a fresh IV whenever getData is called
 * so that each response is unique even if the underlying data has not changed.
 *
 * data_uid          – base64(32-byte UID) sent by the device; used as the
 *                     lookup key. VARCHAR so it can carry a unique index.
 * encoded_data_key  – Olvid-encoded AuthEncAES256ThenSHA256 symmetric key
 *                     (binary, type 0x90). Contains "mackey" (HMAC-SHA256) and
 *                     "enckey" (AES-256) in an Encoded dictionary.
 * data              – raw plaintext blob supplied by the device at store time.
 */
class Version1001Date20260623000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('olvid_data')) {
			$t = $schema->createTable('olvid_data');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			// VARCHAR(64) is enough for base64(32 bytes) = 44 chars, with room to spare
			$t->addColumn('data_uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('encoded_data_key', Types::BLOB, ['notnull' => true]);
			$t->addColumn('data', Types::BLOB, ['notnull' => true]);
			$t->setPrimaryKey(['id']);
			// One row per UID — storeData must upsert, not insert blindly
			$t->addUniqueIndex(['data_uid'], 'olvid_data_uid_idx');
		}

		return $schema;
	}
}
