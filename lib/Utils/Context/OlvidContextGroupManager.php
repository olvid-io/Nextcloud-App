<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils\Context;

use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;

/*
 * Implements a custom group manager for Olvid application to fit internal requirements.
 * For group it currently does nothing specific, but it will be useful to create a group abstraction to implements
 * circles.
 */
class OlvidContextGroupManager {
	public function __construct(
		private readonly IGroupManager $groupManager,
	) {
	}

	public function get(string $groupId): ?IGroup {
		return $this->groupManager->get($groupId);
	}

	/**
	 * @return IGroup[]
	 */
	public function getAll(): array {
		return $this->groupManager->search('');
	}

	/**
	 * @return IGroup[]
	 */
	public function getUserGroups(IUser $user): array {
		return $this->groupManager->getUserGroups($user);
	}

	/**
	 * Checks if a userId is in admin group (and a nextcloud administrator)
	 */
	public function isUserANextcloudAdmin(string $userId): bool {
		return $this->groupManager->isAdmin($userId);
	}

	public function createGroup(string $groupId): ?IGroup {
		return $this->groupManager->createGroup($groupId);
	}

	/**
	 * @param IGroup $nextcloudGroup
	 * @return string[]
	 */
	public function getNextcloudGroupMemberIds(IGroup $nextcloudGroup): array {
		return array_map(fn (IUser $gm) => $gm->getUID(), $nextcloudGroup->getUsers());
	}
}
