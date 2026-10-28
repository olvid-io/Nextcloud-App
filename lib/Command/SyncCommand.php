<?php

declare(strict_types=1);

namespace OCA\Olvid\Command;

use OCA\Olvid\Cron\OlvidDatabaseSynchronizationTask;
use OCA\Olvid\Cron\OlvidRefreshSignatureTask;
use OCA\Olvid\Cron\OlvidServerSynchronizationTask;
use OCA\Olvid\Utils\Context\OlvidContext;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

// usage: occ olvid:sync [--database] [--server] [--signatures]
class SyncCommand extends Command {
	public function __construct(
		private readonly OlvidContext $context,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this
			->setName('olvid:sync')
			->setDescription('Synchronize Olvid app database, Olvid server state and signatures (runs all steps if no option is given)')
			->addOption('database', null, InputOption::VALUE_NONE, 'Clean and synchronize the local Olvid database')
			->addOption('server', null, InputOption::VALUE_NONE, 'Synchronize api keys and push topics with the Olvid server')
			->addOption('signatures', null, InputOption::VALUE_NONE, 'Refresh all user and group signatures');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$runDatabase = (bool)$input->getOption('database');
		$runServer = (bool)$input->getOption('server');
		$runSignatures = (bool)$input->getOption('signatures');
		if (!$runDatabase && !$runServer && !$runSignatures) {
			$runDatabase = true;
			$runServer = true;
			$runSignatures = true;
		}

		// forward task logs to the console (info and above shown by default, debug with -v)
		$logger = new ConsoleLogger($output, [
			LogLevel::INFO => OutputInterface::VERBOSITY_NORMAL,
			LogLevel::DEBUG => OutputInterface::VERBOSITY_VERBOSE,
		]);

		$failed = false;

		// signature key is created by install / repair steps, every other step relies on it
		if (!$this->context->nextcloud->appManager->getJwkKeyId()) {
			$output->writeln('<error>Signature key is missing, run `occ maintenance:repair` first</error>');
			return self::FAILURE;
		}

		if ($runDatabase) {
			$failed |= !$this->runStep($output, 'Database synchronization', fn () => (new OlvidDatabaseSynchronizationTask($this->context, $logger))->run());
		}

		if ($runServer) {
			$failed |= !$this->runStep($output, 'Server synchronization', fn () => (new OlvidServerSynchronizationTask($this->context, $logger))->run());
		}

		if ($runSignatures) {
			$failed |= !$this->runStep($output, 'Signatures refresh', fn () => (new OlvidRefreshSignatureTask($this->context, $logger))->run());
		}

		return $failed ? self::FAILURE : self::SUCCESS;
	}

	private function runStep(OutputInterface $output, string $name, callable $step): bool {
		$output->writeln(sprintf('<info>%s...</info>', $name));
		try {
			$step();
			$output->writeln(sprintf('<info>%s: done</info>', $name));
			return true;
		} catch (Throwable $throwable) {
			$output->writeln(sprintf('<error>%s: failed: %s</error>', $name, $throwable->getMessage()));
			if ($output->isVerbose()) {
				$output->writeln($throwable->getTraceAsString());
			}

			return false;
		}
	}
}
