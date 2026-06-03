<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<UserDeletedEvent> */
class UserEventListener implements IEventListener {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidUserConfigManager $userConfig,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$this->logger->info('UserEventListener: UserDeletedEvent: ' . $event->getUser()->getUID());
			$this->userDeletedHandler($event);
		} elseif ($event instanceof UserChangedEvent) {
			$this->logger->info('UserEventListener: UserChangedEvent: ' . $event->getUser()->getUID());
			$this->userChangedHandler($event);
		}
	}

	public function userDeletedHandler(UserDeletedEvent $event): void {
		$this->userConfig->deleteUserConfig($event->getUser()->getUID());
	}

	public function userChangedHandler(UserChangedEvent $event): void {
		if ($event->getFeature() == 'displayName') {
		}
	}
}
