<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils\Context;

use InvalidArgumentException;
use OCP\IUser;
use OCP\IUserManager;

/*
 ** Implements a custom user manager for Olvid application to fit internal requirements.
 ** Currently we have no specific user constraint but it might come (to handle disabled users for example)
 ** It also adapt the search method to search by display name within all backends
 */
class OlvidContextUserManager {
	public function __construct(
		private readonly IUserManager $userManager,
	) {

	}

	/**
	 * get a user by user id
	 * return null if user does not exist
	 *
	 * @return IUser|null Either the user or null if the specified user does not exist
	 */
	public function get(string $uid): ?IUser {
		return $this->userManager->get($uid);
	}

	/**
	 * get all users
	 *
	 * @return IUser[]
	 */
	public function getAll(): array {
		$users = [];
		$this->userManager->callForAllUsers(function (IUser $user) use (&$users): void {
			$users[] = $user;
		});
		return $users;
	}

	/**
	 * Check if a user exists.
	 */
	public function userExists(string $uid): bool {
		return $this->userManager->userExists($uid);
	}

	/**
	 * search by display name
	 *
	 * @return IUser[]
	 */
	public function search(string $pattern, ?int $limit = null): array {
		return $this->userManager->searchDisplayName($pattern, $limit);
	}

	/**
	 * @throws InvalidArgumentException
	 * @return ?IUser the created user or null
	 */
	public function createUser(string $uid, string $password): ?IUser {
		$user = $this->userManager->createUser($uid, $password);
		return $user === false ? null : $user;
	}
}
