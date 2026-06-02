<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use OCA\Olvid\Db\OlvidDatabase;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupDeletedEvent;

/** @template-implements IEventListener<GroupDeletedEvent> */
class GroupDeletedListener implements IEventListener {
	public function __construct(private readonly OlvidDatabase $db) {}

	public function handle(Event $event): void {
		if (!($event instanceof GroupDeletedEvent)) {
			return;
		}
		$olvidGroup = $this->db->group->findByGroupIdOrNull($event->getGroup()->getGID());
		if ($olvidGroup !== null) {
			$this->db->group->delete($olvidGroup);
		}

		// TODO do we delete other components history ? GroupKicked, GroupDeleted, ... (all except last for example ?)
	}
}
