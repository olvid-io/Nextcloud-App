<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\Api\App\GroupAvatarGet;
use OCA\Olvid\Api\App\GroupAvatarUpload;
use OCA\Olvid\Api\App\GroupsUpdate;
use OCA\Olvid\Api\App\UserDeleteIdentity;
use OCA\Olvid\Api\App\UserGetMagicLink;
use OCA\Olvid\Api\App\UserUpdate;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\ResponseDefinitions;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type OlvidMe from ResponseDefinitions
 * @psalm-import-type OlvidUserFull from ResponseDefinitions
 * @psalm-import-type OlvidUser from ResponseDefinitions
 * @psalm-import-type OlvidGroupFull from ResponseDefinitions
 * @psalm-import-type OlvidGroup from ResponseDefinitions
 * @psalm-import-type OlvidGroupRef from ResponseDefinitions
 */
class AppApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly OlvidDatabase $db,
		private readonly IUserManager $userManager,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly ?string $userId,
		private readonly UserGetMagicLink $userGetMagicLink,
		private readonly UserDeleteIdentity $userDeleteIdentity,
		private readonly GroupsUpdate $updateGroupsHandler,
		private readonly UserUpdate $userUpdate,
		private readonly GroupAvatarGet $avatarGet,
		private readonly GroupAvatarUpload $avatarUpload,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	// ── Current-user endpoints (accessible to all authenticated users) ──────

	/**
	 * Returns the current user's Olvid profile fields and admin status
	 *
	 * @return DataResponse<Http::STATUS_OK, OlvidMe, array{}>
	 * @noinspection PhpUnused
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me')]
	public function meGet(): DataResponse {
		return new DataResponse([
			'firstname' => $this->olvidUserConfig->getFirstname($this->userId),
			'lastname' => $this->olvidUserConfig->getLastname($this->userId),
			'position' => $this->olvidUserConfig->getPosition($this->userId),
			'company' => $this->olvidUserConfig->getCompany($this->userId),
			'olvidIdentityUploaded' => $this->olvidUserConfig->hasIdentity($this->userId),
			'isAdmin' => $this->groupManager->isAdmin($this->userId),
		]);
	}

	/**
	 * Updates the current user's Olvid profile fields
	 *
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $position Job position
	 * @param string $company Company name
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 * @noinspection PhpUnused
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/app/me')]
	public function mePut(string $firstname = '', string $lastname = '', string $position = '', string $company = ''): DataResponse {
		return $this->userUpdate->handle($this->userSession->getUser(), $firstname, $lastname, $position, $company);
	}

	/**
	 * Generates a magic link (configuration URL) to enroll the current user's Olvid identity
	 *
	 * @return DataResponse<Http::STATUS_OK, array{configurationUrl: string}, array{}>
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me/getMagicLink')]
	public function meGetMagicLink(): DataResponse {
		return $this->userGetMagicLink->handle($this->userId);
	}

	/**
	 * Unlinks the current user's Olvid identity from this directory
	 * When $revoke is true the identity is also permanently blocked for all contacts
	 *
	 * @param bool $revoke Permanently revoke the identity (irreversible)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>
	 * @throws \OCP\DB\Exception
	 * @noinspection PhpUnused
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/app/me/identity')]
	public function meIdentityDelete(bool $revoke = false): DataResponse {
		return $this->userDeleteIdentity->handle($this->userId, $revoke);
	}

	/**
	 * Returns all Nextcloud groups the current user belongs to, with their Olvid discussion status
	 *
	 * @return DataResponse<Http::STATUS_OK, array{groups: list<OlvidGroup>}, array{}>
	 * @noinspection PhpUnused
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me/groups')]
	public function meGroups(): DataResponse {
		$user = $this->userSession->getUser();
		$groups = $this->groupManager->getUserGroups($user);
		$result = [];
		foreach ($groups as $group) {
			$gid = $group->getGID();
			$olvidGroup = $this->db->group->findByGroupIdOrNull($gid);
			$photoUidBytes = $olvidGroup?->getGroupPhotoUid();
			$result[] = [
				'id' => $gid,
				'displayName' => $group->getDisplayName(),
				'enabled' => $olvidGroup?->getEnabled() ?? false,
				'photoUid' => $photoUidBytes !== null ? base64_encode($photoUidBytes) : null,
			];
		}
		return new DataResponse(['groups' => $result]);
	}

	// ── Group endpoints (admin) ──────────────────────────────────────────────

	/**
	 * Returns all Nextcloud groups with their Olvid discussion configuration and member list
	 *
	 * @return DataResponse<Http::STATUS_OK, array{groups: list<OlvidGroupFull>}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/groups')]
	public function groupsGet(): DataResponse {
		$groups = $this->groupManager->search('');

		$response = ['groups' => []];
		foreach ($groups as $group) {
			$gid = $group->getGID();
			$olvidGroup = $this->db->group->findByGroupIdOrNull($gid);
			$members = [];
			foreach ($group->getUsers() as $member) {
				$members[] = [
					'id' => $member->getUID(),
					'displayName' => $member->getDisplayName(),
					'useOlvid' => $this->olvidUserConfig->hasIdentity($member->getUID()),
				];
			}
			// base64-encode the raw binary photoUid for safe JSON transport; null when no avatar is set
			$photoUidBytes = $olvidGroup?->getGroupPhotoUid();
			$response['groups'][] = [
				'id' => $gid,
				'displayName' => $group->getDisplayName(),
				'enabled' => $olvidGroup?->getEnabled() ?? false,
				'customName' => $olvidGroup?->getDiscussionName() ?? null,
				'description' => $olvidGroup?->getDiscussionDescription() ?? null,
				'photoUid' => $photoUidBytes !== null ? base64_encode($photoUidBytes) : null,
				'members' => $members,
			];
		}
		return new DataResponse($response);
	}

	/**
	 * Creates a new Nextcloud group
	 *
	 * @param string $id Group ID (must be unique and non-empty)
	 * @return DataResponse<Http::STATUS_OK, OlvidGroupFull, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'POST', url: '/app/groups')]
	public function groupsPost(string $id): DataResponse {
		$groupId = trim($id);

		if ($groupId === '') {
			return new DataResponse(['error' => 'id is required'], Http::STATUS_BAD_REQUEST);
		}

		if ($this->groupManager->get($groupId) !== null) {
			return new DataResponse(['error' => 'group already exists'], Http::STATUS_BAD_REQUEST);
		}

		// create nextcloud group
		$nextcloudGroup = $this->groupManager->createGroup($groupId);

		return new DataResponse([
			'id' => $groupId,
			'displayName' => $nextcloudGroup->getDisplayName(),
			'enabled' => false,
			'customName' => null,
			'description' => null,
			'photoUid' => null,
			'members' => [],
		]);
	}

	/**
	 * Updates a group's Olvid discussion settings (enabled flag, custom name, description)
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param bool|null $enabled Enable or disable the Olvid discussion for this group
	 * @param string|null $customName Override the discussion display name (null clears the override)
	 * @param string|null $description Discussion description
	 * @return DataResponse<Http::STATUS_OK, OlvidGroupFull, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'PUT', url: '/app/groups/{groupId}')]
	public function groupsPut(string $groupId, ?bool $enabled, ?string $customName, ?string $description): DataResponse {
		return $this->updateGroupsHandler->handle($groupId, $enabled, $customName, $description);
	}

	/**
	 * Returns the avatar image for a group. Requires a valid photoUid query parameter
	 * The photoUid acts as a cache-buster; clients should update the URL when it changes
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param string $photoUid Base64-encoded photo UID (query parameter, used for cache validation)
	 * @return DataDisplayResponse<Http::STATUS_NOT_FOUND, array{}>
	 * @throws Exception|MultipleObjectsReturnedException
	 * @noinspection PhpUnused
	 */
	# disable CSRF verification for static read only resources
	# do not need to be admin to access a group image
	# We expect a valid photoUid query parameter to be passed
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/groups/{groupId}/avatar')]
	public function groupsAvatarGet(string $groupId, string $photoUid = ''): DataDisplayResponse {
		return $this->avatarGet->handle($groupId, $photoUid);
	}

	/**
	 * Uploads or replaces the avatar image for a group
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param string $photoData Base64-encoded image data (PNG or JPEG, JSON body)
	 * @return DataResponse<Http::STATUS_OK, array{photoUid: string}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'PUT', url: '/app/groups/{groupId}/avatar')]
	public function groupsAvatarPut(string $groupId, string $photoData): DataResponse {
		return $this->avatarUpload->handle($groupId, $photoData);
	}

	/**
	 * Adds a user to a group
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'POST', url: '/app/groups/{groupId}/members/{userId}')]
	public function groupsMemberPost(string $groupId, string $userId): DataResponse {
		$group = $this->groupManager->get($groupId);
		if ($group === null) {
			return new DataResponse(['error' => 'group not found'], Http::STATUS_NOT_FOUND);
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}
		$group->addUser($user);
		return new DataResponse([]);
	}

	/**
	 * Removes a user from a group
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'DELETE', url: '/app/groups/{groupId}/members/{userId}')]
	public function groupsMemberDelete(string $groupId, string $userId): DataResponse {
		$group = $this->groupManager->get($groupId);
		if ($group === null) {
			return new DataResponse(['error' => 'group not found'], Http::STATUS_NOT_FOUND);
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}
		// protect against last admin deletion !
		if ($group->getGID() === 'admin' && $user->getUID() === $this->userSession->getUser()->getUID()) {
			return new DataResponse(['error' => 'you can remove yourself from admin group'], Http::STATUS_BAD_REQUEST);
		}
		$group->removeUser($user);
		return new DataResponse([]);
	}

	// ── User search (admin, used by groups sidebar) ──────────────────────────

	/**
	 * Searches for users by display name or uid. Returns up to 20 results
	 *
	 * @param string $query Search string (query parameter)
	 * @return DataResponse<Http::STATUS_OK, array{users: list<OlvidUser>}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/users/search')]
	public function usersSearch(string $query = ''): DataResponse {
		$users = $this->userManager->search($query, 20);

		$result = [];
		foreach ($users as $user) {
			$result[] = [
				'id' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'useOlvid' => $this->olvidUserConfig->hasIdentity($user->getUID()),
			];
		}
		return new DataResponse(['users' => $result]);
	}

	// ── User management endpoints (admin only) ───────────────────────────────

	/**
	 * Returns all Nextcloud users with their Olvid profile fields
	 *
	 * @return DataResponse<Http::STATUS_OK, array{users: list<OlvidUserFull>}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/users')]
	public function usersGet(): DataResponse {
		$users = $this->userManager->search('');

		$result = [];
		foreach ($users as $user) {
			$uid = $user->getUID();
			$result[] = [
				'id' => $uid,
				'displayName' => $user->getDisplayName(),
				'useOlvid' => $this->olvidUserConfig->hasIdentity($uid),
				'firstname' => $this->olvidUserConfig->getFirstname($uid),
				'lastname' => $this->olvidUserConfig->getLastname($uid),
				'position' => $this->olvidUserConfig->getPosition($uid),
				'company' => $this->olvidUserConfig->getCompany($uid),
			];
		}
		return new DataResponse(['users' => $result]);
	}

	/**
	 * Creates a new Nextcloud user with optional Olvid profile fields
	 *
	 * @param string $uid User ID (required)
	 * @param string $password Initial password (required)
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $position Job position
	 * @param string $company Company name
	 * @return DataResponse<Http::STATUS_OK, OlvidUserFull, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'POST', url: '/app/users')]
	public function usersPost(string $uid = '', string $password = '', string $firstname = '', string $lastname = '', string $position = '', string $company = ''): DataResponse {
		$uid = trim($uid);

		if ($uid === '' || $password === '') {
			return new DataResponse(['error' => 'uid and password are required'], Http::STATUS_BAD_REQUEST);
		}

		if ($this->userManager->get($uid) !== null) {
			return new DataResponse(['error' => 'user already exists'], Http::STATUS_BAD_REQUEST);
		}

		try {
			$user = $this->userManager->createUser($uid, $password);
			if ($user === false) {
				return new DataResponse(['error' => 'Could not create user'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		} catch (Exception $e) {
			$this->logger->error('createUser failed: ' . $e->getMessage());
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		if (trim($firstname) !== '') {
			$this->olvidUserConfig->setFirstname($uid, trim($firstname));
		}
		if (trim($lastname) !== '') {
			$this->olvidUserConfig->setLastname($uid, trim($lastname));
		}
		if (trim($position) !== '') {
			$this->olvidUserConfig->setPosition($uid, trim($position));
		}
		if (trim($company) !== '') {
			$this->olvidUserConfig->setCompany($uid, trim($company));
		}

		return new DataResponse([
			'id' => $user->getUID(),
			'displayName' => $user->getDisplayName(),
			'useOlvid' => false,
			'firstname' => $this->olvidUserConfig->getFirstname($uid),
			'lastname' => $this->olvidUserConfig->getLastname($uid),
			'position' => $this->olvidUserConfig->getPosition($uid),
			'company' => $this->olvidUserConfig->getCompany($uid),
		]);
	}

	/**
	 * Updates the Olvid profile fields of a specific user
	 *
	 * @param string $userId User ID (URL path)
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $position Job position
	 * @param string $company Company name
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'PUT', url: '/app/users/{userId}')]
	public function updateUser(string $userId, string $firstname = '', string $lastname = '', string $position = '', string $company = ''): DataResponse {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}

		return $this->userUpdate->handle($user, $firstname, $lastname, $position, $company);
	}

	/**
	 * Deletes a Nextcloud user. Optionally revokes their Olvid identity first
	 * Cannot be used to delete the currently authenticated user
	 *
	 * @param string $userId User ID (URL path)
	 * @param bool $revoke Permanently revoke the user's Olvid identity before deletion (irreversible)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws \OCP\DB\Exception
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'DELETE', url: '/app/users/{userId}')]
	public function deleteUser(string $userId, bool $revoke = false): DataResponse {
		if ($userId === $this->userId) {
			return new DataResponse(['error' => 'Cannot delete yourself'], Http::STATUS_BAD_REQUEST);
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}
		$this->userDeleteIdentity->handle($userId, $revoke);
		$user->delete();
		return new DataResponse([]);
	}

	/**
	 * Returns the groups a specific user belongs to
	 *
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{groups: list<OlvidGroupRef>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/users/{userId}/groups')]
	public function getUserGroups(string $userId): DataResponse {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}
		$groups = $this->groupManager->getUserGroups($user);
		$result = [];
		foreach ($groups as $group) {
			$result[] = [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}
		return new DataResponse(['groups' => $result]);
	}

	/**
	 * Generates a magic link (configuration URL) to enroll a specific user's Olvid identity
	 *
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{configurationUrl: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/users/{userId}/magicLink')]
	public function usersGetMagicLink(string $userId): DataResponse {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}
		return $this->userGetMagicLink->handle($userId);
	}

	/**
	 * Unlinks the Olvid identity of a specific user
	 * When $revoke is true the identity is also permanently blocked for all contacts
	 *
	 * @param string $userId User ID (URL path)
	 * @param bool $revoke Permanently revoke the identity (irreversible)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 * @throws \OCP\DB\Exception
	 * @noinspection PhpUnused
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/app/users/{userId}/identity')]
	public function usersIdentityDelete(string $userId, bool $revoke = false): DataResponse {
		return $this->userDeleteIdentity->handle($userId, $revoke);
	}
}
