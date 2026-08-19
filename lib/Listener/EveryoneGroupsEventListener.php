<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<UserDeletedEvent> */
class EveryoneGroupsEventListener implements IEventListener {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly IGroupManager $groupManager,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserCreatedEvent) {
			$this->logger->info('EveryoneGroupsEventListener: UserCreatedEvent: ' . $event->getUser()->getUID());
			$this->userCreatedHandler($event);
		}
	}

	public function userCreatedHandler(UserCreatedEvent $event): void {
		// check everyone group is enabled
		$everyoneGroupIds = $this->olvidAppConfig->getEveryoneGroupIds();
		if (!$everyoneGroupIds) {
			return;
		}

		foreach ($everyoneGroupIds as $groupId) {
			$group = $this->groupManager->get($groupId);
			if (!$group) {
				$this->logger->error('EveryoneGroupsEventListener: userAddedHandler: group not found: ' . $groupId);
				continue;
			}
			$group->addUser($event->getUser());
			$this->logger->info('EveryoneGroupsEventListener: userAddedHandler: user add to everyone group: ' . $event->getUser()->getUID());
		}
	}
}
