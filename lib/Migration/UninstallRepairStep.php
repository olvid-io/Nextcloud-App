<?php

namespace OCA\Olvid\Migration;

use OCA\Olvid\Db\OlvidDataMapper;
use OCA\Olvid\Db\OlvidGroupDeletionMapper;
use OCA\Olvid\Db\OlvidGroupKickedMapper;
use OCA\Olvid\Db\OlvidGroupMapper;
use OCA\Olvid\Db\OlvidRevocationMapper;
use OCA\Olvid\Db\OlvidUserMapper;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UninstallRepairStep implements IRepairStep {
	public function __construct(
		private readonly OlvidContext $context,
		private readonly IDBConnection $db,
	) {
	}

	public function getName(): string {
		return 'Uninstall Olvid App';
	}

	public function run(IOutput $output): void {
		// delete api keys from server
		$output->info('Olvid: revoking user api keys');
		$olvidUsers = $this->context->db->user->getAll();
		foreach ($olvidUsers as $olvidUser) {
			if ($olvidUser->getApiKey()) {
				$this->context->olvidServer->revokeApiKeyNoFail($olvidUser->getApiKey());
			}
		}

		// revoke group push topics
		$output->info('Olvid: revoking group push topics');
		$olvidGroups = $this->context->db->group->getAll();
		foreach ($olvidGroups as $olvidGroup) {
			if ($olvidGroup->getPushTopic()) {
				$this->context->olvidServer->revokePushTopic($olvidGroup->getPushTopic());
			}
		}

		// revoke global push topic
		$output->info('Olvid: revoking global push topics');
		if ($this->context->nextcloud->appManager->getGlobalPushTopic()) {
			$this->context->olvidServer->revokePushTopic($this->context->nextcloud->appManager->getGlobalPushTopic());
		}

		// delete olvid app config
		$output->info('Olvid: deleting app config');
		$this->context->nextcloud->appManager->deleteAppConfig();

		// delete tables in database
		$output->info('Olvid: dropping database tables');
		$this->db->dropTable(OlvidDataMapper::TABLE_NAME);
		$this->db->dropTable(OlvidGroupDeletionMapper::TABLE_NAME);
		$this->db->dropTable(OlvidGroupKickedMapper::TABLE_NAME);
		$this->db->dropTable(OlvidGroupMapper::TABLE_NAME);
		$this->db->dropTable(OlvidRevocationMapper::TABLE_NAME);
		$this->db->dropTable(OlvidUserMapper::TABLE_NAME);
	}
}
