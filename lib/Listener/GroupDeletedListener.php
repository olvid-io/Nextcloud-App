<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use OCA\Olvid\Utils\OlvidGroupConfigManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;

/** @template-implements IEventListener<GroupDeletedEvent> */
class GroupDeletedListener implements IEventListener {
	public function __construct(private readonly OlvidGroupConfigManager $olvidGroupConfig) {}

	public function handle(Event $event): void {
		if (!($event instanceof GroupDeletedEvent)) {
			return;
		}
		$this->olvidGroupConfig->deleteGroupConfig($event->getGroup()->getGID());
	}
}
