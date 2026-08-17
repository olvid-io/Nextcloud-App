<?php

namespace OCA\Olvid\Migration;

use Exception;
use OCA\Olvid\Cron\OlvidDatabaseSynchronizationTask;
use OCA\Olvid\Cron\OlvidRefreshSignatureTask;
use OCA\Olvid\Cron\OlvidServerSynchronizationTask;
use OCA\Olvid\Listener\EveryoneGroupEventListener;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

// Step run after database migration and during `occ maintenance:repair` command
class RepairStep implements IRepairStep {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
	) {
	}

	public function getName(): string {
		return 'Repair Olvid App';
	}

	/**
	 * @param IOutput $output
	 * @throws Exception
	 */
	public function run(IOutput $output): void {
		/*
		 ** Create JWKS key
		 */
		InstallRepairStep::createSignatureKeyIfNecessary($output, $this->context->nextcloud->appManager);

		/*
		 ** Everyone group
		 */
		$this->syncEveryoneGroup();

		/*
		 * Sync database
		 */
		try {
			(new OlvidDatabaseSynchronizationTask($this->context, $this->logger))->run();
		} catch (Exception $exception) {
			$this->logger->error('RepairStep: OlvidDatabaseSynchronizationTask: unexpected exception', ['exception' => $exception]);
		}

		/*
		 * Sync with olvid server (api keys and push topics)
		 */
		try {
			(new OlvidServerSynchronizationTask($this->context, $this->logger))->run();
		} catch (Exception $exception) {
			$this->logger->error('RepairStep: OlvidServerSynchronizationTask: unexpected exception', ['exception' => $exception]);
		}

		/*
		 * Refresh all server signatures
		 */
		try {
			(new OlvidRefreshSignatureTask($this->context, $this->logger))->run();
		} catch (Exception $exception) {
			$this->logger->error('RepairStep: OlvidServerSynchronizationTask: unexpected exception', ['exception' => $exception]);
		}
	}

	private function syncEveryoneGroup(): void {
		if ($this->context->nextcloud->appManager->isEveryoneGroupEnabled()) {
			// create group if necessary
			$everyoneGroup = $this->groupManager->get(EveryoneGroupEventListener::EVERYONE_GROUP_ID);
			if (!$everyoneGroup) {
				$everyoneGroup = $this->groupManager->createGroup(EveryoneGroupEventListener::EVERYONE_GROUP_ID);
				$this->logger->info('syncEveryoneGroup: everyone group created');
			}

			// add any missing member to group
			$everyoneMembersUid = array_map(function ($user) { return $user->getUID(); }, $everyoneGroup->getUsers());
			$allUsers = $this->userManager->search('');
			foreach ($allUsers as $user) {
				if (!in_array($user->getUID(), $everyoneMembersUid)) {
					$everyoneGroup->addUser($user);
					$this->logger->info('syncEveryoneGroup: added user to everyone group: ' . $user->getUID());
				}
			}
		} else {
			// delete group if it exists
			$everyoneGroup = $this->groupManager->get(EveryoneGroupEventListener::EVERYONE_GROUP_ID);
			$everyoneGroup?->delete();
		}
	}
}
