<?php

declare(strict_types=1);

namespace OCA\Olvid\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20260527000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('olvid_group')) {
			$t = $schema->createTable('olvid_group');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('group_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('group_uid', Types::BLOB, ['notnull' => false, 'length' => 255]);
			$t->addColumn('last_modification_timestamp', Types::BIGINT, ['notnull' => true, 'default' => 0]);
			$t->addColumn('push_topic', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('group_photo_uid', Types::BLOB, ['notnull' => false, 'length' => 255]);
			$t->addColumn('serialized_shared_settings', Types::TEXT, ['notnull' => false]);
			$t->addColumn('signed_group_blob', Types::TEXT, ['notnull' => true]);
			$t->addColumn('enabled', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$t->addColumn('discussion_name', Types::TEXT, ['notnull' => false]);
			$t->addColumn('discussion_description', Types::TEXT, ['notnull' => false]);
			$t->setPrimaryKey(['id']);
			$t->addUniqueIndex(['group_id'], 'olvid_group_group_id_idx');
		}

		if (!$schema->hasTable('olvid_group_deletion')) {
			$t = $schema->createTable('olvid_group_deletion');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('group_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('timestamp', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('signature', Types::TEXT, ['notnull' => true]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['group_id'], 'olvid_grp_del_grp_idx');
			$t->addIndex(['timestamp'], 'olvid_grp_del_ts_idx');
		}

		if (!$schema->hasTable('olvid_group_kicked')) {
			$t = $schema->createTable('olvid_group_kicked');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('group_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('user_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$t->addColumn('timestamp', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('signature', Types::TEXT, ['notnull' => true]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['user_id'], 'olvid_grp_kick_uid_idx');
			$t->addIndex(['timestamp'], 'olvid_grp_kick_ts_idx');
		}

		if (!$schema->hasTable('olvid_revocation')) {
			$t = $schema->createTable('olvid_revocation');
			$t->addColumn('id', Types::BIGINT, ['notnull' => true, 'autoincrement' => true]);
			$t->addColumn('olvid_id', Types::STRING, ['notnull' => true, 'length' => 255]);
			$t->addColumn('timestamp', Types::BIGINT, ['notnull' => true]);
			$t->addColumn('revocation_type', Types::INTEGER, ['notnull' => true]);
			$t->addColumn('signature', Types::TEXT, ['notnull' => true]);
			$t->addColumn('username', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('firstname', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('lastname', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('mail', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('position', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('company', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->addColumn('full_search_string', Types::STRING, ['notnull' => false, 'length' => 255]);
			$t->setPrimaryKey(['id']);
			$t->addIndex(['olvid_id'], 'olvid_rev_olvid_id_idx');
			$t->addIndex(['timestamp'], 'olvid_rev_ts_idx');
			$t->addIndex(['revocation_type'], 'olvid_rev_type_idx');
		}

		return $schema;
	}
}
