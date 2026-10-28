<?php

declare(strict_types=1);

namespace OCA\Olvid\Listeners;

use Exception;
use OCA\Olvid\Models\JsonRevocationData;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\Context\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<UserDeletedEvent> */
class UserEventListener implements IEventListener {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof UserDeletedEvent) {
			$this->logger->info('UserEventListener: UserDeletedEvent: ' . $event->getUser()->getUID());
			$this->userDeletedHandler($event);
		}
	}

	public function userDeletedHandler(UserDeletedEvent $event): void {
		$userId = $event->getUser()->getUID();

		try {
			$olvidUser = $this->context->db->user->getByUserIdOrNull($userId);
		} catch (MultipleObjectsReturnedException $e) {
			$this->logger->error('UserEventListener: userDeletedHandler: more than one user found', ['exception' => $e]);
			return;
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('UserEventListener: userDeletedHandler: cannot get user', ['exception' => $e]);
			return;
		}

		// user not found, probably already deleted ignore
		if ($olvidUser === null) {
			return ;
		}

		// delete user api key if he has one (ignore exceptions)
		if ($olvidUser->getApiKey() !== null) {
			try {
				$this->context->olvidServer->revokeApiKey($olvidUser->getApiKey());
				$olvidUser->setApiKey(null);
				$this->context->db->user->updateNoFail($olvidUser);
			} catch (InvalidConfigurationException) {
				$this->logger->error('UserEventListener: userDeletedHandler: cannot revoke api key: invalid server configuration');
			} catch (OlvidServerException $e) {
				$this->logger->error('UserEventListener: userDeletedHandler: cannot revoke api key: unexpected exception', ['exception' => $e]);
			}
		}

		// if user use olvid revoke it's identity and remove it from groups
		if ($olvidUser->hasIdentity()) {
			// revoke identity
			try {
				$this->context->db->revocation->computeAndSaveRevocation($userId, $olvidUser->getBytesIdentity(), JsonRevocationData::REVOCATION_TYPE_DELETE_USER, $this->context);
			} catch (Exception $exception) {
				$this->logger->error('UserEventListener: userDeletedHandler: cannot create revocation', ['exception' => $exception]);
			}

			// keep bytesIdentity before deletion to compute group kicks
			$bytesUserIdentity = $olvidUser->getBytesIdentity();
			// delete user in database and clean database
			try {
				$this->context->db->user->delete($olvidUser);
			} catch (\OCP\DB\Exception $exception) {
				$this->logger->error('UserEventListener: userDeletedHandler: cannot delete olvid user in db', ['exception' => $exception]);
			}

			// notify every user (ignore exceptions)
			$globalPushTopic = $this->context->nextcloud->appManager->getGlobalPushTopic();
			if ($globalPushTopic !== null) {
				$this->context->olvidServer->sendGroupNotificationNoFail($globalPushTopic);
			}

			// remove user from Olvid groups (ignore exceptions)
			$nextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($event->getUser());
			foreach ($nextcloudGroups as $nextcloudGroup) {
				try {
					$olvidGroup = $this->context->db->group->getByGroupIdOrNull($nextcloudGroup->getGID());
					if ($olvidGroup === null) {
						continue;
					}
					if (!$olvidGroup->getEnabled()) {
						continue;
					}

					// create group kick in database
					$this->context->db->groupKicked->computeAndSaveGroupKick($olvidGroup, $userId, $bytesUserIdentity, $this->context);

					// update group blob
					$jsonGroupBlob = $olvidGroup->computeBlob($this->context->nextcloud->groupManager->getNextcloudGroupMemberIds($nextcloudGroup), $this->context);
					$signedBlob = $this->context->signatory->sign($jsonGroupBlob->jsonSerialize());
					$olvidGroup->setSignedGroupBlob($signedBlob);
					$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
					$olvidGroup = $this->context->db->group->update($olvidGroup);

					// notify users
					if ($olvidGroup->getPushTopic() !== null) {
						// we can use push topic to notify members and removed user
						$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
					}
				} catch (Exception $exception) {
					$this->logger->error('UserEventListener: userDeletedHandler: cannot kick user from group', ['exception' => $exception]);
				}
			}
		}
		// user has no identity, just delete OlvidUser in database
		else {
			try {
				$this->context->db->user->delete($olvidUser);
			} catch (\OCP\DB\Exception $exception) {
				$this->logger->error('UserEventListener: userDeletedHandler: cannot delete olvid user in db', ['exception' => $exception]);
			}
		}
	}
}
