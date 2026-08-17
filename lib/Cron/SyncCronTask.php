<?php

namespace OCA\Olvid\Cron;

use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class SyncCronTask extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
	) {
		parent::__construct($time);

		// Run once every day
		parent::setInterval(3600 * 24);
		parent::setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	/**
	 * @throws Exception
	 */
	public function run($argument): void {
		// Clean and sync local database first
		try {
			(new OlvidDatabaseSynchronizationTask($this->context, $this->logger))->run();
		} catch (Exception $exception) {
			$this->logger->error('SyncCronTask: OlvidDatabaseSynchronizationTask: unexpected exception', ['exception' => $exception]);
		}

		// then we can sync with olvid server
		try {
			(new OlvidServerSynchronizationTask($this->context, $this->logger))->run();
		} catch (Exception $exception) {
			$this->logger->error('SyncCronTask: OlvidServerSynchronizationTask: unexpected exception', ['exception' => $exception]);
		}

		// Renew all server signatures
		try {
			(new OlvidRefreshSignatureTask($this->context, $this->logger))->run();
		} catch (Exception $exception) {
			$this->logger->error('SyncCronTask: OlvidRefreshSignatureTask: unexpected exception', ['exception' => $exception]);
		}
	}
}
