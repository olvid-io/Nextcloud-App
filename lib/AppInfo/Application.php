<?php

declare(strict_types=1);

namespace OCA\Olvid\AppInfo;

use OCA\Olvid\DeclarativeSettings\Admin;
use OCA\Olvid\Listener\GroupEventListener;
use OCA\Olvid\Listener\UserEventListener;
use OCA\Olvid\Profile\OlvidProfileLinkAction;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Group\Events\GroupChangedEvent;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'olvid';

	public function __construct() {
		parent::__construct(self::APP_ID);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDeclarativeSettings(Admin::class);
		$context->registerProfileLinkAction(OlvidProfileLinkAction::class);

		// user events
		$context->registerEventListener(UserDeletedEvent::class, UserEventListener::class);
		$context->registerEventListener(UserChangedEvent::class, UserEventListener::class);

		// group events
		$context->registerEventListener(GroupChangedEvent::class, GroupEventListener::class);
		$context->registerEventListener(GroupDeletedEvent::class, GroupEventListener::class);
		$context->registerEventListener(UserAddedEvent::class, GroupEventListener::class);
		$context->registerEventListener(UserRemovedEvent::class, GroupEventListener::class);
	}

	public function boot(IBootContext $context): void {
	}
}
