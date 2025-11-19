<?php

declare(strict_types=1);

namespace OCA\Olvid\AppInfo;

use OCA\Olvid\Http\WellKnown\OlvidJwksHandler;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

class Application extends App implements IBootstrap {
	public const APP_ID = 'olvid';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		// Register OlvidDiscoveryHandler
		$context->registerWellKnownHandler(OlvidJwksHandler::class);
	}

	public function boot(IBootContext $context): void {}
}
