<?php

declare(strict_types=1);

namespace OCA\Olvid\AppInfo;

use OCA\Olvid\DeclarativeSettings\Admin;
use OCA\Olvid\Listener\GroupDeletedListener;
use OCA\Olvid\Listener\UserDeletedListener;
use OCA\Olvid\Profile\OlvidProfileLinkAction;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'olvid';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDeclarativeSettings(Admin::class);
		$context->registerProfileLinkAction(OlvidProfileLinkAction::class);
		// clean database on entity deletion
		$context->registerEventListener(GroupDeletedEvent::class, GroupDeletedListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
	}

	public function boot(IBootContext $context): void {}
}
