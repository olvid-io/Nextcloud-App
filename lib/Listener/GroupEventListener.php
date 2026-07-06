<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use Exception;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupChangedEvent;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<GroupDeletedEvent|GroupChangedEvent|UserAddedEvent|UserRemovedEvent> */
class GroupEventListener implements IEventListener {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidDatabase $db,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidServer $olvidServer,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof GroupDeletedEvent) {
			$this->logger->info('GroupEventListener: GroupDeletedEvent: ' . $event->getGroup()->getGID());
			$this->groupDeletedHandler($event);
		} elseif ($event instanceof GroupChangedEvent) {
			$this->logger->info('GroupEventListener: GroupChangedEvent: ' . $event->getGroup()->getGID());
			$this->groupChangedHandler($event);
		} elseif ($event instanceof UserAddedEvent) {
			$this->logger->info('GroupEventListener: UserAddedEvent: ' . $event->getGroup()->getGID() . ': ' . $event->getUser()->getUID());
			$this->userAddedHandler($event);
		} elseif ($event instanceof UserRemovedEvent) {
			$this->logger->info('GroupEventListener: UserRemovedEvent: ' . $event->getGroup()->getGID() . ': ' . $event->getUser()->getUID());
			$this->userRemovedHandler($event);
		} else {
			$this->logger->info('GroupEventListener: unknown event: ' . get_class($event));
		}
	}

	public function groupChangedHandler(GroupChangedEvent $event): void {
		// get olvid group
		$olvidGroup = $this->db->group->findByGroupIdOrNull($event->getGroup()->getGID());
		if (!$olvidGroup === null) {
			return;
		}

		// if group have no custom name we must change it, update blob and notify members
		if ($olvidGroup->getEnabled()) {
			if ($olvidGroup->getDiscussionName() === null || trim($olvidGroup->getDiscussionName()) === '') {
				$blob = JsonGroupBlob::computeBlob($olvidGroup, $event->getGroup()->getDisplayName(), $event->getGroup()->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db);
				$signedBlob = $blob->sign($this->olvidAppConfig);
				$olvidGroup->setSignedGroupBlob($signedBlob);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$olvidGroup = $this->db->group->update($olvidGroup);

				if ($olvidGroup->getPushTopic() !== null) {
					try {
						$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
					} catch (Exception $e) {
						$this->logger->error('groupChangedHandler: cannot send notification: ', ['exception' => $e]);
					}
				}
			}
		}
	}

	public function groupDeletedHandler(GroupDeletedEvent $event): void {
		// get olvid group
		$olvidGroup = $this->db->group->findByGroupIdOrNull($event->getGroup()->getGID());
		if (!$olvidGroup === null) {
			return;
		}

		// if group is enabled: create an olvid deletion and send a notification to members
		if ($olvidGroup->getEnabled()) {
			$this->db->groupDeletion->computeAndSaveGroupDeletion($this->olvidAppConfig, $olvidGroup);
			if ($olvidGroup->getPushTopic() !== null) {
				try {
					$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
				} catch (Exception $e) {
					$this->logger->error('groupDeletedHandler: cannot send notification: ', ['exception' => $e]);
				}
			}
		}

		// delete olvid group
		$this->db->group->delete($olvidGroup);
	}

	public function userAddedHandler(UserAddedEvent $event): void {
		// check group is enabled
		$olvidGroup = $this->db->group->findByGroupIdOrNull($event->getGroup()->getGID());
		if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
			return;
		}

		// check user use Olvid
		if (!$this->olvidUserConfig->hasIdentity($event->getUser()->getUID())) {
			return;
		}

		// update group blob
		$blob = JsonGroupBlob::computeBlob($olvidGroup, $event->getGroup()->getDisplayName(), $event->getGroup()->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db);
		$signedBlob = $blob->sign($this->olvidAppConfig);
		$olvidGroup->setSignedGroupBlob($signedBlob);
		$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
		$olvidGroup = $this->db->group->update($olvidGroup);

		// notify users
		if ($olvidGroup->getPushTopic() !== null) {
			// notify current members using group topic
			try {
				$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
			} catch (OlvidServerException|InvalidConfigurationException $exception) {
				$this->logger->error('GroupEventListener: userAddedHandler: cannot send group notification: ' . $exception->getMessage());
			}
			// notify new member individually
			try {
				$this->olvidServer->sendSingleUserNotification($this->olvidUserConfig->getB64Identity($event->getUser()->getUID()));
			} catch (OlvidServerException|InvalidConfigurationException $exception) {
				$this->logger->error('GroupEventListener: userAddedHandler: cannot send new user notification: ' . $exception->getMessage());
			}
		}
	}

	public function userRemovedHandler(UserRemovedEvent $event): void {
		// check group is enabled
		$olvidGroup = $this->db->group->findByGroupIdOrNull($event->getGroup()->getGID());
		if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
			return;
		}

		// check user use Olvid
		$userIdentity = $this->olvidUserConfig->getB64Identity($event->getUser()->getUID());
		if ($userIdentity === null) {
			return;
		}

		// create group kick in database
		$this->db->groupKicked->computeAndSaveGroupKick($this->olvidAppConfig, $olvidGroup, $event->getUser()->getUID(), $userIdentity);

		// update group blob
		$blob = JsonGroupBlob::computeBlob($olvidGroup, $event->getGroup()->getDisplayName(), $event->getGroup()->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db);
		$signedBlob = $blob->sign($this->olvidAppConfig);
		$olvidGroup->setSignedGroupBlob($signedBlob);
		$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
		$olvidGroup = $this->db->group->update($olvidGroup);

		// notify users
		if ($olvidGroup->getPushTopic() !== null) {
			// we can use push topic to notify members and removed user
			try {
				$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
			} catch (OlvidServerException|InvalidConfigurationException $exception) {
				$this->logger->error('GroupEventListener: userRemovedHandler: cannot send group notification: ' . $exception->getMessage());
			}
		}
	}
}
