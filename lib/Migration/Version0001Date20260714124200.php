<?php

declare(strict_types=1);

namespace OCA\Olvid\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version0001Date20260714124200 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();
		if (!$schema->hasTable('olvid_user')) {
			$t = $schema->createTable('olvid_user');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('user_id', Types::STRING, ['notnull' => true]);
			$t->addColumn('bytes_identity', Types::BLOB, ['notnull' => false]);
			$t->addColumn('api_key', Types::STRING, ['notnull' => false]);
			$t->addColumn('nonce', Types::STRING, ['notnull' => false]);
			$t->addColumn('magic_token', Types::STRING, ['notnull' => false]);
			$t->addColumn('magic_token_expiration', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$t->addColumn('session_revoked_before', Types::BIGINT, ['notnull' => false, 'default' => null]);
			$t->addColumn('signed_details', Types::TEXT, ['notnull' => false]);
			$t->addColumn('firstname', Types::STRING, ['notnull' => false]);
			$t->addColumn('lastname', Types::STRING, ['notnull' => false]);
			$t->addColumn('position', Types::STRING, ['notnull' => false]);
			$t->addColumn('company', Types::STRING, ['notnull' => false]);
			$t->addColumn('full_search_field', Types::STRING, ['notnull' => false]);

			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['user_id'], 'olvid_user_user_id_idx');
		}

		if (!$schema->hasTable('olvid_group')) {
			$t = $schema->createTable('olvid_group');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('group_id', Types::STRING, ['notnull' => true]);
			$t->addColumn('bytes_group_uid', Types::BLOB, ['notnull' => true]);
			$t->addColumn('enabled', Types::BOOLEAN, ['notnull' => true, 'default' => false]);
			$t->addColumn('signed_group_blob', Types::TEXT, ['notnull' => false, 'default' => null]);
			$t->addColumn('last_modification_timestamp', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('push_topic', Types::STRING, ['notnull' => false]);
			$t->addColumn('bytes_group_photo_uid', Types::BLOB, ['notnull' => false]);
			$t->addColumn('serialized_shared_settings', Types::TEXT, ['notnull' => false]);
			$t->addColumn('discussion_name', Types::TEXT, ['notnull' => false]);
			$t->addColumn('discussion_description', Types::TEXT, ['notnull' => false]);

			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['group_id'], 'olvid_group_group_id_idx');
		}

		if (!$schema->hasTable('olvid_group_deletion')) {
			$t = $schema->createTable('olvid_group_deletion');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('bytes_group_uid', Types::BLOB, ['notnull' => true]);
			$t->addColumn('timestamp', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('signed_deletion', Types::TEXT, ['notnull' => true]);

			$t->setPrimaryKey(['id']);
			$t->addIndex(['bytes_group_uid'], 'olvid_grp_del_grp_idx');
			$t->addIndex(['timestamp'], 'olvid_grp_del_ts_idx');
		}

		if (!$schema->hasTable('olvid_group_kicked')) {
			$t = $schema->createTable('olvid_group_kicked');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('bytes_group_uid', Types::BLOB, ['notnull' => true]);
			$t->addColumn('user_id', Types::STRING, ['notnull' => true]);
			$t->addColumn('timestamp', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('signed_kick', Types::TEXT, ['notnull' => true]);

			$t->setPrimaryKey(['id']);
			$t->addIndex(['user_id'], 'olvid_grp_kick_uid_idx');
			$t->addIndex(['timestamp'], 'olvid_grp_kick_ts_idx');
		}

		if (!$schema->hasTable('olvid_revocation')) {
			$t = $schema->createTable('olvid_revocation');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('user_id', Types::STRING, ['notnull' => true]);
			$t->addColumn('bytes_identity', Types::BLOB, ['notnull' => true]);
			$t->addColumn('timestamp', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('revocation_type', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('signed_revocation', Types::TEXT, ['notnull' => true]);

			$t->setPrimaryKey(['id']);
			$t->addIndex(['bytes_identity'], 'olvid_rev_bytes_identity_idx');
			$t->addIndex(['timestamp'], 'olvid_rev_ts_idx');
			$t->addIndex(['revocation_type'], 'olvid_rev_type_idx');
		}

		if (!$schema->hasTable('olvid_data')) {
			$t = $schema->createTable('olvid_data');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('bytes_data_uid', Types::BLOB, ['notnull' => true]);
			$t->addColumn('bytes_encoded_key', Types::BLOB, ['notnull' => true]);
			$t->addColumn('bytes_data', Types::BLOB, ['notnull' => true]);

			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['bytes_data_uid'], 'olvid_data_uid_idx');
		}

		return $schema;
	}
}
