<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IGroupManager;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<UserDeletedEvent> */
class UserEventListener implements IEventListener {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidDatabase $db,
		private readonly OlvidServer $olvidServer,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly IGroupManager $groupManager,
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
		// check user use Olvid
		$userIdentity = $this->olvidUserConfig->getIdentity($event->getUser()->getUID());
		if ($userIdentity === null) {
			return;
		}

		// remove user from Olvid groups
		$nextcloudGroups = $this->groupManager->getUserGroups($event->getUser());
		foreach ($nextcloudGroups as $nextcloudGroup) {
			$olvidGroup = $this->db->group->findByGroupIdOrNull($nextcloudGroup->getGID());
			if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
				continue;
			}

			// create group kick in database
			$this->db->groupKicked->computeAndSaveGroupKick($this->olvidAppConfig, $olvidGroup, $event->getUser()->getUID(), $userIdentity);

			// update group blob
			$blob = JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db);
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

		// TODO is this necessary ?
		$this->olvidUserConfig->deleteUserConfig($event->getUser()->getUID());
	}

	public function userChangedHandler(UserChangedEvent $event): void {
		if ($event->getFeature() == 'displayName') {
			// check user use Olvid
			$userIdentity = $this->olvidUserConfig->getIdentity($event->getUser()->getUID());
			if ($userIdentity === null) {
				return;
			}

			$firstname = trim($this->olvidUserConfig->getFirstname($event->getUser()->getUID()) ?? '');
			$lastname = trim($this->olvidUserConfig->getLastname($event->getUser()->getUID()) ?? '');

			// if user have custom firstname or lastname it does not use nextcloud display name, do nothing
			if ($firstname || $lastname) {
				return;
			}

			// re-compute details and sign them
			$userDetails = JsonUserDetails::computeDetails($event->getUser(), $this->olvidUserConfig);
			$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);

			// update full search field
			$userDetails->updateFullSearchString($event->getUser()->getUID(), $this->olvidUserConfig);

			// notify user
			try {
				$this->olvidServer->sendSingleUserNotification($userIdentity);
			} catch (OlvidServerException|InvalidConfigurationException $exception) {
				$this->logger->error('GroupEventListener: userChangedHandler: cannot send user notification: ' . $exception->getMessage());
			}
		}
	}
}
