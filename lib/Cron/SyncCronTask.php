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
		(new OlvidDatabaseSynchronizationTask($this->context, $this->logger))->run();

		// then we can sync with olvid server
		(new OlvidServerSynchronizationTask($this->context, $this->logger))->run();
	}
}
