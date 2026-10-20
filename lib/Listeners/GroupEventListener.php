<?php

declare(strict_types=1);

namespace OCA\Olvid\Listeners;

use OCA\Circles\Model\Member;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\DB\Exception;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Group\Events\GroupChangedEvent;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IUser;
use Psr\Log\LoggerInterface;

/** @template-implements IEventListener<GroupDeletedEvent|GroupChangedEvent|UserAddedEvent|UserRemovedEvent> */
class GroupEventListener implements IEventListener {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
	) {
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function handle(Event $event): void {
		// Nextcloud groups
		if ($event instanceof GroupDeletedEvent) {
			$this->logger->info('GroupEventListener: GroupDeletedEvent: ' . $event->getGroup()->getGID());
			$this->groupDeletedHandler($event->getGroup()->getGID());
		} elseif ($event instanceof GroupChangedEvent) {
			$this->logger->info('GroupEventListener: GroupChangedEvent: ' . $event->getGroup()->getGID());
			$this->groupChangedHandler($event->getGroup()->getGID(), array_map(fn (IUser $gm) => $gm->getUID(), $event->getGroup()->getUsers()));
		} elseif ($event instanceof UserAddedEvent) {
			$this->logger->info('GroupEventListener: UserAddedEvent: ' . $event->getGroup()->getGID() . ': ' . $event->getUser()->getUID());
			$this->userAddedHandler($event->getGroup()->getGID(), $event->getUser()->getUID(), array_map(fn (IUser $gm) => $gm->getUID(), $event->getGroup()->getUsers()));
		} elseif ($event instanceof UserRemovedEvent) {
			$this->logger->info('GroupEventListener: UserRemovedEvent: ' . $event->getGroup()->getGID() . ': ' . $event->getUser()->getUID());
			$this->userRemovedHandler($event->getGroup()->getGID(), $event->getUser()->getUID(), array_map(fn (IUser $gm) => $gm->getUID(), $event->getGroup()->getUsers()));
		} else {
			$this->logger->info('GroupEventListener: unknown event: ' . get_class($event));
		}
	}

	/**
	 * @param array<string> $groupMemberIds
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function groupChangedHandler(string $groupId, array $groupMemberIds): void {
		// check group is enabled
		$olvidGroup = $this->context->db->group->getByGroupIdOrNull($groupId);
		if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
			return;
		}

		// if group have no custom name we must change it, update blob and notify members
		if ($olvidGroup->getDiscussionName() === null || trim($olvidGroup->getDiscussionName()) === '') {
			$jsonGroupBlob = $olvidGroup->computeBlob($groupMemberIds, $this->context);
			$signedBlob = $this->context->signatory->sign($jsonGroupBlob->jsonSerialize());
			$olvidGroup->setSignedGroupBlob($signedBlob);
			$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
			$olvidGroup = $this->context->db->group->update($olvidGroup);

			if ($olvidGroup->getPushTopic() !== null) {
				$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
			}
		}
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws Exception
	 */
	public function groupDeletedHandler(GroupDeletedEvent $event): void {
		// check group is enabled
		$olvidGroup = $this->context->db->group->getByGroupIdOrNull($event->getGroup()->getGID());
		if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
			return;
		}

		// if group is enabled: create an olvid deletion and send a notification to members
		$this->context->db->groupDeletion->computeAndSaveGroupDeletion($this->context, $olvidGroup);
		if ($olvidGroup->getPushTopic() !== null) {
			$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
		}

		// delete olvid group
		$this->context->db->group->delete($olvidGroup);
	}

	/**
	 * @param string $groupId
	 * @param string $userId
	 * @param array<string> $groupMemberIds
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function userAddedHandler(string $groupId, string $userId, array $groupMemberIds): void {
		// check group is enabled
		$olvidGroup = $this->context->db->group->getByGroupIdOrNull($groupId);
		if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
			return;
		}

		// check user use Olvid
		$olvidUser = $this->context->db->user->getByUserIdOrNull($userId);
		if (!$olvidUser?->hasIdentity()) {
			return;
		}

		// update group blob
		$jsonGroupBlob = $olvidGroup->computeBlob($groupMemberIds, $this->context);
		$signedBlob = $this->context->signatory->sign($jsonGroupBlob->jsonSerialize());
		$olvidGroup->setSignedGroupBlob($signedBlob);
		$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
		$olvidGroup = $this->context->db->group->update($olvidGroup);

		// notify users
		if ($olvidGroup->getPushTopic() !== null) {
			// notify current members using group topic
			$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
			// notify new member individually
			$this->context->olvidServer->sendSingleUserNotificationNoFail(base64_encode($olvidUser->getBytesIdentity()));
		}
	}

	/**
	 * @param string $groupId
	 * @param string $userId
	 * @param array<string> $groupMemberIds
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	public function userRemovedHandler(string $groupId, string $userId, array $groupMemberIds): void {
		// check group is enabled
		$olvidGroup = $this->context->db->group->getByGroupIdOrNull($groupId);
		if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
			return;
		}

		$olvidUser = $this->context->db->user->getByUserIdOrNull($userId);
		if (!$olvidUser?->hasIdentity()) {
			return;
		}

		// create group kick in database
		$this->context->db->groupKicked->computeAndSaveGroupKick($olvidGroup, $olvidUser->getUserId(), $olvidUser->getBytesIdentity(), $this->context);

		// update group blob
		$jsonGroupBlob = $olvidGroup->computeBlob($groupMemberIds, $this->context);
		$signedBlob = $this->context->signatory->sign($jsonGroupBlob->jsonSerialize());
		$olvidGroup->setSignedGroupBlob($signedBlob);
		$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
		$olvidGroup = $this->context->db->group->update($olvidGroup);

		// notify users
		if ($olvidGroup->getPushTopic() !== null) {
			// we can use push topic to notify members and removed user
			$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
		}
	}
}
