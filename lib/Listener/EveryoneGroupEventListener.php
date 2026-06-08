<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Settings\Events\DeclarativeSettingsSetValueEvent;
use OCP\User\Events\UserCreatedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<UserDeletedEvent> */
class EveryoneGroupEventListener implements IEventListener {
	public const EVERYONE_GROUP_ID = 'everyone';

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserCreatedEvent) {
			$this->logger->info('EveryoneGroupEventListener: UserCreatedEvent: ' . $event->getUser()->getUID());
			$this->userCreatedHandler($event);
		} elseif ($event instanceof DeclarativeSettingsSetValueEvent) {
			$this->logger->info('EveryoneGroupEventListener: DeclarativeSettingsSetValueEvent: ' . $event->getUser()->getUID());
			$this->declarativeSettingsSetHandler($event);
		}
	}

	public function userCreatedHandler(UserCreatedEvent $event): void {
		// check everyone group is enabled
		if (!$this->olvidAppConfig->isEveryoneGroupEnabled()) {
			return;
		}
		$everyoneGroup = $this->groupManager->get(self::EVERYONE_GROUP_ID);
		if (!$everyoneGroup) {
			$this->logger->error('EveryoneGroupEventListener: userAddedHandler: EveryoneGroup not found');
			return;
		}
		$everyoneGroup->addUser($event->getUser());
		$this->logger->info('EveryoneGroupEventListener: userAddedHandler: user add to everyone group: ' . $event->getUser()->getUID());
	}

	public function declarativeSettingsSetHandler(DeclarativeSettingsSetValueEvent $event): void {
		if ($event->getApp() !== Application::APP_ID) {
			return;
		}
		if ($event->getFieldId() !== OlvidAppConfigManager::APP_CONFIG_ENABLE_EVERYONE_GROUP) {
			return;
		}
		if ($event->getValue() === true) {
			// create everyone group
			$everyoneGroup = $this->groupManager->get(self::EVERYONE_GROUP_ID);
			if ($everyoneGroup) {
				$this->logger->error('EveryoneGroupEventListener: declarativeSettingsSetHandler: EveryoneGroup already exists');
				return;
			}
			$everyoneGroup = $this->groupManager->createGroup(self::EVERYONE_GROUP_ID);
			$allUsers = $this->userManager->search('');
			foreach ($allUsers as $user) {
				$everyoneGroup->addUser($user);
			}
			$this->logger->info('EveryoneGroupEventListener: declarativeSettingsSetHandler: created everyone group');
		} elseif ($event->getValue() === false) {
			$everyoneGroup = $this->groupManager->get(self::EVERYONE_GROUP_ID);
			if ($everyoneGroup) {
				$this->logger->error('EveryoneGroupEventListener: declarativeSettingsSetHandler: EveryoneGroup not found');
				return;
			}
			$everyoneGroup->delete();
			$this->logger->info('EveryoneGroupEventListener: declarativeSettingsSetHandler: deleted everyone group');
		}
	}
}
