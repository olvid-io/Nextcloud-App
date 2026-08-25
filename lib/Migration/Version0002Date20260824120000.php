<?php

declare(strict_types=1);

namespace OCA\Olvid\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0002Date20260824120000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('olvid_fed_server')) {
			$t = $schema->createTable('olvid_fed_server');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('remote_url', Types::STRING, ['notnull' => true]);
			$t->addColumn('enabled', Types::BOOLEAN, ['notnull' => true, 'default' => true]);
			$t->addColumn('olvid_capable', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$t->addColumn('jwks_cache', Types::TEXT, ['notnull' => false, 'default' => null]);
			$t->addColumn('jwks_fetched_at', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$t->addColumn('last_sync_timestamp', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('last_revocation_sync_timestamp', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('last_sync_attempt', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$t->addColumn('last_sync_status', Types::STRING, ['notnull' => false, 'default' => null]);

			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['remote_url'], 'olvid_fed_srv_url_idx');
		}

		if (!$schema->hasTable('olvid_fed_user')) {
			$t = $schema->createTable('olvid_fed_user');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('server_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('remote_user_id', Types::STRING, ['notnull' => true]);
			$t->addColumn('bytes_identity', Types::BLOB, ['notnull' => true]);
			$t->addColumn('signed_details', Types::TEXT, ['notnull' => true]);
			$t->addColumn('firstname', Types::STRING, ['notnull' => false]);
			$t->addColumn('lastname', Types::STRING, ['notnull' => false]);
			$t->addColumn('position', Types::STRING, ['notnull' => false]);
			$t->addColumn('company', Types::STRING, ['notnull' => false]);
			$t->addColumn('full_search_field', Types::STRING, ['notnull' => false]);
			$t->addColumn('registration_timestamp', Types::BIGINT, ['notnull' => true, 'default' => 0]);

			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['server_id', 'remote_user_id'], 'olvid_fed_usr_srv_uid_idx');
			$t->addIndex(['full_search_field'], 'olvid_fed_usr_search_idx');
		}

		if (!$schema->hasTable('olvid_fed_revocation')) {
			$t = $schema->createTable('olvid_fed_revocation');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('server_id', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('remote_user_id', Types::STRING, ['notnull' => true]);
			$t->addColumn('bytes_identity', Types::BLOB, ['notnull' => true]);
			$t->addColumn('revocation_type', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('signed_revocation', Types::TEXT, ['notnull' => true]);
			$t->addColumn('timestamp', Types::BIGINT, ['notnull' => true]);

			$t->setPrimaryKey(['id']);
			$t->addIndex(['server_id'], 'olvid_fed_rev_srv_idx');
			$t->addIndex(['bytes_identity'], 'olvid_fed_rev_identity_idx');
			$t->addIndex(['timestamp'], 'olvid_fed_rev_ts_idx');
		}

		return $schema;
	}
}
