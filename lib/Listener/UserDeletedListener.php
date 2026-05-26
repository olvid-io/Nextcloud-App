<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;

/** @template-implements IEventListener<UserDeletedEvent> */
class UserDeletedListener implements IEventListener {
	public function __construct(private readonly OlvidUserConfigManager $userConfig) {}

	public function handle(Event $event): void {
		if (!($event instanceof UserDeletedEvent)) {
			return;
		}
		$this->userConfig->deleteUserConfig($event->getUid());
	}
}
