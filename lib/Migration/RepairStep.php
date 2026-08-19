<?php

namespace OCA\Olvid\Migration;

use Exception;
use OCA\Olvid\Cron\OlvidDatabaseSynchronizationTask;
use OCA\Olvid\Cron\OlvidRefreshSignatureTask;
use OCA\Olvid\Cron\OlvidServerSynchronizationTask;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

// Step run after database migration and during `occ maintenance:repair` command
class RepairStep implements IRepairStep {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
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
}
