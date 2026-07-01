<?php

declare(strict_types=1);

namespace OCA\Olvid\Listener;

use Exception;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Models\JsonRevocationData;
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
		$userId = $event->getUser()->getUID();

		// delete user api key if he has one (ignore exceptions)
		$userApiKey = $this->olvidUserConfig->getApiKey($userId);
		if ($userApiKey !== null) {
			try {
				$this->olvidServer->revokeApiKey($userApiKey);
				$this->olvidUserConfig->unsetApiKey($userId);
			} catch (InvalidConfigurationException|OlvidServerException $e) {
				$this->logger->error('userDeletedHandler: cannot revoke api key: ', ['exception' => $e]);
			}
		}

		// check user use Olvid
		$userIdentity = $this->olvidUserConfig->getIdentity($userId);
		if ($userIdentity === null) {
			return;
		}

		// remove user from Olvid groups (ignore exceptions)
		$nextcloudGroups = $this->groupManager->getUserGroups($event->getUser());
		foreach ($nextcloudGroups as $nextcloudGroup) {
			try {
				$olvidGroup = $this->db->group->findByGroupIdOrNull($nextcloudGroup->getGID());
				if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
					continue;
				}

				// create group kick in database
				$this->db->groupKicked->computeAndSaveGroupKick($this->olvidAppConfig, $olvidGroup, $userId, $userIdentity);

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
						$this->logger->error('GroupEventListener: userRemovedHandler: cannot send group notification', ['exception' => $exception]);
					}
				}
			} catch (Exception $exception) {
				$this->logger->error('GroupEventListener: userRemovedHandler: cannot kick user from group', ['exception' => $exception]);
			}
		}

		// delete user config and clean database
		$this->olvidUserConfig->deleteUserConfig($userId);

		// revoke identity
		try {
			$this->db->revocation->computeAndSaveRevocation($userId, $this->olvidUserConfig->getIdentity($userId), JsonRevocationData::REVOCATION_TYPE_DELETE_USER, $this->olvidAppConfig);
		} catch (Exception $exception) {
			$this->logger->error('GroupEventListener: userRemovedHandler: cannot create revocation', ['exception' => $exception]);
		}

		// sign out user (ignore exception)
		try {
			$this->olvidUserConfig->setSessionRevokedBefore($userId, TimeUtil::currentTimeMillis());
		} catch (Exception) {
			$this->logger->error('GroupEventListener: userRemovedHandler: cannot revoke user sessions', ['exception' => $exception]);
		}

		// notify every user (ignore exceptions)
		try {
			$globalPushTopic = $this->olvidAppConfig->getGlobalPushTopic();
			if ($globalPushTopic !== null) {
				$this->olvidServer->sendGroupNotification($globalPushTopic);
			}
		} catch (Exception $exception) {
			$this->logger->error('GroupEventListener: userRemovedHandler: cannot notify other users', ['exception' => $exception]);
		}
	}

	public function userChangedHandler(UserChangedEvent $event): void {
		$userId = $event->getUser()->getUID();

		if ($event->getFeature() == 'displayName') {
			// check user use Olvid
			$userIdentity = $this->olvidUserConfig->getIdentity($userId);
			if ($userIdentity === null) {
				return;
			}

			$firstname = trim($this->olvidUserConfig->getFirstname($userId) ?? '');
			$lastname = trim($this->olvidUserConfig->getLastname($userId) ?? '');

			// if user have custom firstname or lastname it does not use nextcloud display name, do nothing
			if ($firstname || $lastname) {
				return;
			}

			// re-compute details and sign them
			$userDetails = JsonUserDetails::computeDetails($event->getUser(), $this->olvidUserConfig);
			$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);

			// update full search field
			$userDetails->updateFullSearchString($userId, $this->olvidUserConfig);

			// notify user
			try {
				$this->olvidServer->sendSingleUserNotification($userIdentity);
			} catch (OlvidServerException|InvalidConfigurationException $exception) {
				$this->logger->error('GroupEventListener: userChangedHandler: cannot send user notification: ' . $exception->getMessage());
			}
		}
	}
}
