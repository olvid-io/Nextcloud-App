<?php

namespace OCA\Olvid\Migration;

use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class UninstallRepairStep implements IRepairStep {
	public function __construct(
		private readonly OlvidContext $context,
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
				$olvidUser->setApiKey(null);
				$this->context->db->user->update($olvidUser);
			}
		}

		// revoke group push topics
		$output->info('Olvid: revoking group push topics');
		$olvidGroups = $this->context->db->group->getAll();
		foreach ($olvidGroups as $olvidGroup) {
			if ($olvidGroup->getPushTopic()) {
				$this->context->olvidServer->revokePushTopic($olvidGroup->getPushTopic());
				$olvidGroup->setPushTopic(null);
				$this->context->db->group->update($olvidGroup);
			}
		}

		// revoke global push topic
		$output->info('Olvid: revoking global push topics');
		if ($this->context->nextcloud->appManager->getGlobalPushTopic()) {
			$this->context->olvidServer->revokePushTopic($this->context->nextcloud->appManager->getGlobalPushTopic());
			$this->context->nextcloud->appManager->setGlobalPushTopic(null);
		}

		// do not delete application config and database tables anymore, as it causes issues to re-run the same migrations
		// in a re-installation scenario.
	}
}
