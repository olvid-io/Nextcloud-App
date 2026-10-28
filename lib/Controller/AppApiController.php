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
use OCA\Olvid\Db\OlvidGroup;
use OCA\Olvid\ResponseDefinitions;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\RandomUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type OlvidUser from ResponseDefinitions
 * @psalm-import-type OlvidUserFull from ResponseDefinitions
 * @psalm-import-type OlvidMe from ResponseDefinitions
 * @psalm-import-type OlvidGroupFull from ResponseDefinitions
 * @psalm-import-type OlvidGroup from ResponseDefinitions
 * @psalm-import-type OlvidGroupRef from ResponseDefinitions
 * @psalm-import-type OlvidStatistics from ResponseDefinitions
 */
class AppApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private readonly ?string $userId,
		private readonly LoggerInterface $logger,
		private readonly IUserSession $userSession,
		private readonly OlvidContext $context,
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
	 *
	 * 200: Current user profile returned
	 * @noinspection PhpUnused
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me')]
	public function meGet(): DataResponse {
		$nextcloudUser = $this->userSession->getUser();
		$olvidUser = $this->context->db->user->getOrCreate($nextcloudUser->getUID(), $nextcloudUser->getDisplayName());
		return new DataResponse([
			'id' => $nextcloudUser->getUID(),
			'displayName' => $nextcloudUser->getDisplayName() ?? $nextcloudUser->getUID(),
			'firstname' => $olvidUser->getFirstname(),
			'lastname' => $olvidUser->getLastname(),
			'position' => $olvidUser->getPosition(),
			'company' => $olvidUser->getCompany(),
			'useOlvid' => $olvidUser->hasIdentity() ?? false,
			'isInactive' => ($olvidUser->hasIdentity() ?? false) && $olvidUser->isInactive(),
			'isAdmin' => $this->context->nextcloud->groupManager->isUserANextcloudAdmin($this->userId),
		]);
	}

	/**
	 * Updates the current user's Olvid profile fields
	 *
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $position Job position
	 * @param string $company Company name
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Profile updated
	 * 400: Neither firstname nor lastname is set
	 * @throws Exception
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
	 * @return DataResponse<Http::STATUS_OK, array{configurationUrl: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Magic link generated
	 * 500: Magic link could not be generated
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me/getMagicLink')]
	public function meGetMagicLink(): DataResponse {
		return $this->userGetMagicLink->handle($this->userSession->getUser());
	}

	/**
	 * Unlinks the current user's Olvid identity from this directory
	 * When $revoke is true the identity is also permanently blocked for all contacts
	 *
	 * @param bool $revoke Permanently revoke the identity (irreversible)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Identity unlinked
	 * 404: No Olvid identity linked to the current user
	 * @throws Exception
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
	 *
	 * 200: Groups returned
	 * @noinspection PhpUnused
	 * @throws Exception
	 */
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me/groups')]
	public function meGroups(): DataResponse {
		$nextcloudUser = $this->userSession->getUser();
		$nextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($nextcloudUser);
		$result = [];
		foreach ($nextcloudGroups as $nextcloudGroup) {
			$gid = $nextcloudGroup->getGID();
			$olvidGroup = $this->context->db->group->getByGroupIdOrNull($gid);
			$bytesPhotoUid = $olvidGroup?->getBytesGroupPhotoUid();
			$result[] = [
				'id' => $gid,
				'displayName' => $nextcloudGroup->getDisplayName() ?? $nextcloudGroup->getGID(),
				'enabled' => $olvidGroup?->getEnabled() ?? false,
				'photoUid' => $bytesPhotoUid !== null ? base64_encode($bytesPhotoUid) : null,
			];
		}

		return new DataResponse(['groups' => $result]);
	}

	// ── Group endpoints (admin) ──────────────────────────────────────────────

	/**
	 * Returns all Nextcloud groups with their Olvid discussion configuration and member list
	 *
	 * @return DataResponse<Http::STATUS_OK, array{groups: list<OlvidGroupFull>}, array{}>
	 *
	 * 200: Groups returned
	 * @noinspection PhpUnused
	 * @throws Exception
	 */
	#[ApiRoute(verb: 'GET', url: '/app/groups')]
	public function groupsGet(): DataResponse {
		$nextcloudGroups = $this->context->nextcloud->groupManager->getAll();

		$response = ['groups' => []];
		foreach ($nextcloudGroups as $nextcloudGroup) {
			$gid = $nextcloudGroup->getGID();
			$olvidGroup = $this->context->db->group->getByGroupIdOrNull($gid);
			$members = [];
			foreach ($nextcloudGroup->getUsers() as $member) {
				$members[] = [
					'id' => $member->getUID(),
					'displayName' => $member->getDisplayName() ?? $member->getUID(),
					// TODO this can be optimized (build a user cache ? batch member requests ?)
					'useOlvid' => $this->context->db->user->hasUserAnIdentity($member->getUID()),
				];
			}

			// base64-encode the raw binary photoUid for safe JSON transport; null when no avatar is set
			$bytesPhotoUid = $olvidGroup?->getbytesGroupPhotoUid();
			$response['groups'][] = [
				'id' => $gid,
				'displayName' => $nextcloudGroup->getDisplayName() ?? $nextcloudGroup->getGID(),
				'enabled' => $olvidGroup?->getEnabled() ?? false,
				'discussionName' => $olvidGroup?->getDiscussionName() ?? null,
				'discussionDescription' => $olvidGroup?->getDiscussionDescription() ?? null,
				'photoUid' => $bytesPhotoUid !== null ? base64_encode($bytesPhotoUid) : null,
				'members' => $members,
			];
		}

		return new DataResponse($response);
	}

	/**
	 * Creates a new Nextcloud group
	 *
	 * @param string $id Group ID (must be unique and non-empty)
	 * @return DataResponse<Http::STATUS_OK, OlvidGroupFull, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Group created
	 * 400: Missing or already existing group ID, or group could not be created
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'POST', url: '/app/groups')]
	public function groupsPost(string $id): DataResponse {
		$groupId = trim($id);

		if ($groupId === '') {
			return new DataResponse(['error' => 'id is required'], Http::STATUS_BAD_REQUEST);
		}

		if ($this->context->nextcloud->groupManager->get($groupId) !== null) {
			return new DataResponse(['error' => 'group already exists'], Http::STATUS_BAD_REQUEST);
		}

		// create nextcloud group
		$nextcloudGroup = $this->context->nextcloud->groupManager->createGroup($groupId);
		if ($nextcloudGroup === null) {
			return new DataResponse(['error' => 'group not created'], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse([
			'id' => $groupId,
			'displayName' => $nextcloudGroup->getDisplayName() ?? $nextcloudGroup->getGID(),
			'enabled' => false,
			'discussionName' => null,
			'discussionDescription' => null,
			'photoUid' => null,
			'members' => [],
		]);
	}

	/**
	 * Updates a group's Olvid discussion settings (enabled flag, custom name, description)
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param bool|null $enabled Enable or disable the Olvid discussion for this group
	 * @param string|null $discussionName Override the Olvid discussion name (ignored if null or empty)
	 * @param string|null $discussionDescription Discussion description
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Group settings updated
	 * 404: Group not found
	 * 500: Group settings could not be updated
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'PUT', url: '/app/groups/{groupId}')]
	public function groupsPut(string $groupId, ?bool $enabled, ?string $discussionName, ?string $discussionDescription): DataResponse {
		$nextcloudGroup = $this->context->nextcloud->groupManager->get($groupId);
		return $this->updateGroupsHandler->handle(
			$groupId,
			$nextcloudGroup?->getDisplayName(),
			$this->context->nextcloud->groupManager->getNextcloudGroupMemberIds($nextcloudGroup),
			$enabled,
			$discussionName,
			$discussionDescription,
		);
	}

	/**
	 * Delete a Nextcloud group
	 *
	 * @param string $groupId Group ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, OlvidGroupRef, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>|DataResponse<Http::STATUS_INTERNAL_SERVER_ERROR, array{}, array{}>
	 *
	 * 200: Group deleted
	 * 404: Group not found
	 * 500: Group could not be deleted
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'DELETE', url: '/app/groups/{groupId}')]
	public function groupsDelete(string $groupId): DataResponse {
		$nextcloudGroup = $this->context->nextcloud->groupManager->get($groupId);
		if ($nextcloudGroup === null) {
			return new DataResponse(['error' => 'group not found'], Http::STATUS_NOT_FOUND);
		}

		if ($nextcloudGroup->delete()) {
			return new DataResponse([
				'id' => $groupId,
				'displayName' => $nextcloudGroup->getDisplayName() ?? $nextcloudGroup->getGID(),
			], Http::STATUS_OK);
		}
		return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
	}

	/**
	 * Returns the avatar image for a group. Requires a valid photoUid query parameter
	 * The photoUid acts as a cache-buster; clients should update the URL when it changes
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param string $photoUid Base64-encoded photo UID (query parameter, used for cache validation)
	 * @return DataDisplayResponse<Http::STATUS_OK|Http::STATUS_NOT_FOUND, array{}>
	 *
	 * 200: Avatar image returned
	 * 404: Group or avatar not found, or photoUid mismatch
	 * @throws Exception
	 * @throws MultipleObjectsReturnedException
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
	 * @param ?string $photoData Base64-encoded image data (PNG or JPEG, JSON body) or null to unset current
	 * @return DataResponse<Http::STATUS_OK, array{photoUid: string|null}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Avatar updated or removed
	 * 400: Invalid image data
	 * 404: Group not found
	 * 500: Avatar could not be saved
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'PUT', url: '/app/groups/{groupId}/avatar')]
	public function groupsAvatarPut(string $groupId, ?string $photoData): DataResponse {
		$nextcloudGroup = $this->context->nextcloud->groupManager->get($groupId);
		return $this->avatarUpload->handle(
			$groupId,
			$nextcloudGroup?->getDisplayName(),
			$this->context->nextcloud->groupManager->getNextcloudGroupMemberIds($nextcloudGroup),
			$photoData,
		);
	}

	/**
	 * Adds a user to a group
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: User added to group
	 * 404: Group or user not found
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'POST', url: '/app/groups/{groupId}/members/{userId}')]
	public function groupsMemberPost(string $groupId, string $userId): DataResponse {
		$nextcloudGroup = $this->context->nextcloud->groupManager->get($groupId);
		if ($nextcloudGroup === null) {
			return new DataResponse(['error' => 'group not found'], Http::STATUS_NOT_FOUND);
		}

		$nextcloudUser = $this->context->nextcloud->userManager->get($userId);
		if ($nextcloudUser === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}

		$nextcloudGroup->addUser($nextcloudUser);
		return new DataResponse([]);
	}

	/**
	 * Removes a user from a group
	 *
	 * @param string $groupId Group ID (URL path)
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: User removed from group
	 * 400: Cannot remove yourself from the admin group
	 * 404: Group or user not found
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'DELETE', url: '/app/groups/{groupId}/members/{userId}')]
	public function groupsMemberDelete(string $groupId, string $userId): DataResponse {
		$nextcloudGroup = $this->context->nextcloud->groupManager->get($groupId);
		if ($nextcloudGroup === null) {
			return new DataResponse(['error' => 'group not found'], Http::STATUS_NOT_FOUND);
		}

		$nextcloudUser = $this->context->nextcloud->userManager->get($userId);
		if ($nextcloudUser === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}

		// protect against last admin deletion !
		if ($nextcloudGroup->getGID() === 'admin' && $nextcloudUser->getUID() === $this->userSession->getUser()->getUID()) {
			return new DataResponse(['error' => 'you cannot remove yourself from admin group'], Http::STATUS_BAD_REQUEST);
		}

		$nextcloudGroup->removeUser($nextcloudUser);
		return new DataResponse([]);
	}

	// ── User management endpoints (admin only) ───────────────────────────────

	/**
	 * Returns all Nextcloud users with their Olvid profile fields
	 *
	 * @return DataResponse<Http::STATUS_OK, array{users: list<OlvidUserFull>}, array{}>
	 *
	 * 200: Users returned
	 * @noinspection PhpUnused
	 * @throws Exception
	 */
	#[ApiRoute(verb: 'GET', url: '/app/users')]
	public function usersGet(): DataResponse {
		$nextcloudUsers = $this->context->nextcloud->userManager->getAll();

		$result = [];
		foreach ($nextcloudUsers as $nextcloudUser) {
			$olvidUser = $this->context->db->user->getOrCreate($nextcloudUser->getUID(), $nextcloudUser->getDisplayName());
			$result[] = [
				'id' => $nextcloudUser->getUID(),
				'displayName' => $nextcloudUser->getDisplayName() ?? $nextcloudUser->getUID(),
				'firstname' => $olvidUser->getFirstname(),
				'lastname' => $olvidUser->getLastname(),
				'position' => $olvidUser->getPosition(),
				'company' => $olvidUser->getCompany(),
				'useOlvid' => $olvidUser->hasIdentity() ?? false,
				'isInactive' => ($olvidUser->hasIdentity() ?? false) && $olvidUser->isInactive(),
			];
		}

		return new DataResponse(['users' => $result]);
	}

	/**
	 * Creates a new Nextcloud user with optional Olvid profile fields
	 *
	 * @param string $uid User ID (required)
	 * @param string $firstname First name (optional)
	 * @param string $lastname Last name (optional)
	 * @param string $position Job position (optional)
	 * @param string $company Company name (optional)
	 * @param ?string $password Initial password (optional)
	 * @param list<string>|null $groups Groups to add user to (optional)
	 * @return DataResponse<Http::STATUS_OK, OlvidUserFull, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: User created
	 * 400: Missing uid, user already exists, or unknown group
	 * 500: User could not be created
	 * @noinspection PhpUnused
	 * @throws Exception
	 */
	#[ApiRoute(verb: 'POST', url: '/app/users')]
	public function usersPost(string $uid = '', ?string $password = '', string $firstname = '', string $lastname = '', string $position = '', string $company = '', ?array $groups = []): DataResponse {
		$uid = trim($uid);

		if ($uid === '') {
			return new DataResponse(['error' => 'uid is required'], Http::STATUS_BAD_REQUEST);
		}

		// check argument validity
		if ($this->context->nextcloud->userManager->get($uid) !== null) {
			return new DataResponse(['error' => 'user already exists'], Http::STATUS_BAD_REQUEST);
		}

		$nextcloudGroups = [];
		if ($groups !== null && $groups !== []) {
			foreach ($groups as $group) {
				$nextcloudGroup = $this->context->nextcloud->groupManager->get($group);
				if ($nextcloudGroup === null) {
					return new DataResponse(['error' => 'group does not exist: ' . $group], Http::STATUS_BAD_REQUEST);
				}

				$nextcloudGroups[] = $nextcloudGroup;
			}
		}

		if ($password === null || trim($password) === '') {
			$password = RandomUtil::uuid_create();
		}

		// create nextcloud user
		try {
			$nextcloudUser = $this->context->nextcloud->userManager->createUser($uid, $password);
			if ($nextcloudUser === null) {
				return new DataResponse(['error' => 'Could not create user'], Http::STATUS_INTERNAL_SERVER_ERROR);
			}
		} catch (Exception $exception) {
			$this->logger->error('createUser failed', ['exception' => $exception]);
			return new DataResponse(['error' => $exception->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		// create Olvid user
		$olvidUser = $this->context->db->user->getOrCreate($uid, $nextcloudUser->getDisplayName());

		if (trim($firstname) !== '') {
			$olvidUser->setFirstname(trim($firstname));
		}

		if (trim($lastname) !== '') {
			$olvidUser->setLastname(trim($lastname));
		}

		if (trim($position) !== '') {
			$olvidUser->setPosition(trim($position));
		}

		if (trim($company) !== '') {
			$olvidUser->setCompany(trim($company));
		}

		$this->context->db->user->update($olvidUser);

		// add user to groups
		foreach ($nextcloudGroups as $nextcloudGroup) {
			$nextcloudGroup->addUser($nextcloudUser);
		}

		return new DataResponse([
			'id' => $nextcloudUser->getUID(),
			'displayName' => $nextcloudUser->getDisplayName() ?? $nextcloudUser->getUID(),
			'useOlvid' => $olvidUser->hasIdentity(),
			'isInactive' => ($olvidUser->hasIdentity() ?? false) && $olvidUser->isInactive(),
			'firstname' => $olvidUser->getFirstname(),
			'lastname' => $olvidUser->getLastname(),
			'position' => $olvidUser->getPosition(),
			'company' => $olvidUser->getCompany(),
		], Http::STATUS_OK);
	}

	/**
	 * Updates the Olvid profile fields of a specific user
	 *
	 * @param string $userId User ID (URL path)
	 * @param string $firstname First name
	 * @param string $lastname Last name
	 * @param string $position Job position
	 * @param string $company Company name
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: User profile updated
	 * 400: Neither firstname nor lastname is set
	 * 404: User not found
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'PUT', url: '/app/users/{userId}')]
	public function updateUser(string $userId, string $firstname = '', string $lastname = '', string $position = '', string $company = ''): DataResponse {
		$user = $this->context->nextcloud->userManager->get($userId);
		if ($user === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}

		return $this->userUpdate->handle($user, $firstname, $lastname, $position, $company);
	}

	/**
	 * Deletes a Nextcloud user.
	 * Cannot be used to delete the currently authenticated user
	 *
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: User deleted
	 * 400: Cannot delete the currently authenticated user
	 * 404: User not found
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'DELETE', url: '/app/users/{userId}')]
	public function deleteUser(string $userId): DataResponse {
		if ($userId === $this->userId) {
			return new DataResponse(['error' => 'Cannot delete yourself'], Http::STATUS_BAD_REQUEST);
		}

		$nextcloudUser = $this->context->nextcloud->userManager->get($userId);
		if ($nextcloudUser === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}

		$nextcloudUser->delete();
		return new DataResponse([]);
	}

	/**
	 * Returns the groups a specific user belongs to
	 *
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{groups: list<array{id: string, displayName: string, enabled: bool, discussionName: string|null, discussionDescription: string|null, photoUid: string|null}>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Groups returned
	 * 404: User not found
	 * @noinspection PhpUnused
	 * @throws Exception
	 */
	#[ApiRoute(verb: 'GET', url: '/app/users/{userId}/groups')]
	public function getUserGroups(string $userId): DataResponse {
		$nextcloudUser = $this->context->nextcloud->userManager->get($userId);
		if ($nextcloudUser === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}

		$nextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($nextcloudUser);
		$olvidGroups = $this->context->db->group->getAll();
		$olvidGroupMap = array_combine(array_map(fn ($og) => $og->getGroupId(), $olvidGroups), $olvidGroups);

		$result = [];
		foreach ($nextcloudGroups as $nextcloudGroup) {
			$olvidGroup = $olvidGroupMap[$nextcloudGroup->getGID()] ?? null;
			// base64-encode the raw binary photoUid for safe JSON transport; null when no avatar is set
			$bytesPhotoUid = $olvidGroup?->getbytesGroupPhotoUid();
			$result[] = [
				'id' => $nextcloudGroup->getGID(),
				'displayName' => $nextcloudGroup->getDisplayName() ?? $nextcloudGroup->getGID(),
				'enabled' => $olvidGroup?->getEnabled() ?? false,
				'discussionName' => $olvidGroup?->getDiscussionName() ?? null,
				'discussionDescription' => $olvidGroup?->getDiscussionDescription() ?? null,
				'photoUid' => $bytesPhotoUid !== null ? base64_encode((string)$bytesPhotoUid) : null,
			];
		}

		return new DataResponse(['groups' => $result]);
	}

	/**
	 * Generates a magic link (configuration URL) to enroll a specific user's Olvid identity
	 *
	 * @param string $userId User ID (URL path)
	 * @return DataResponse<Http::STATUS_OK, array{configurationUrl: string}, array{}>|DataResponse<Http::STATUS_NOT_FOUND|Http::STATUS_INTERNAL_SERVER_ERROR, array{error: string}, array{}>
	 *
	 * 200: Magic link generated
	 * 404: User not found
	 * 500: Magic link could not be generated
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/users/{userId}/magicLink')]
	public function usersGetMagicLink(string $userId): DataResponse {
		$nextcloudUser = $this->context->nextcloud->userManager->get($userId);
		if ($nextcloudUser === null) {
			return new DataResponse(['error' => 'user not found'], Http::STATUS_NOT_FOUND);
		}

		return $this->userGetMagicLink->handle($nextcloudUser);
	}

	/**
	 * Unlinks the Olvid identity of a specific user
	 * When $revoke is true the identity is also permanently blocked for all contacts
	 *
	 * @param string $userId User ID (URL path)
	 * @param bool $revoke Permanently revoke the identity (irreversible)
	 * @return DataResponse<Http::STATUS_OK, array{}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{error: string}, array{}>
	 *
	 * 200: Identity unlinked
	 * 404: User not found or no Olvid identity linked
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'DELETE', url: '/app/users/{userId}/identity')]
	public function usersIdentityDelete(string $userId, bool $revoke = false): DataResponse {
		return $this->userDeleteIdentity->handle($userId, $revoke);
	}

	/**
	 * Searches for users by display name or uid. Returns up to 20 results
	 *
	 * @param string $query Search string (query parameter)
	 * @return DataResponse<Http::STATUS_OK, array{users: list<OlvidUser>}, array{}>
	 *
	 * 200: Matching users returned
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/users/search')]
	public function usersSearch(string $query = ''): DataResponse {
		$nextcloudUsers = $this->context->nextcloud->userManager->search($query, 20);

		$result = [];
		foreach ($nextcloudUsers as $nextcloudUser) {
			$result[] = [
				'id' => $nextcloudUser->getUID(),
				'displayName' => $nextcloudUser->getDisplayName() ?? $nextcloudUser->getUID(),
				'useOlvid' => $this->context->db->user->hasUserAnIdentity($nextcloudUser->getUID()),
			];
		}

		return new DataResponse(['users' => $result]);
	}

	// ── Misc endpoints (admin only) ───────────────────────────────
	/**
	 * Returns whether the Olvid app configuration is valid or not
	 * If not UI will show an alert message to the user
	 *
	 * @return DataResponse<Http::STATUS_OK, array{isConfigurationValid: boolean}, array{}>
	 *
	 * 200: Configuration validity returned
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/isAppConfigurationValid')]
	public function isAppConfigurationValid(): DataResponse {
		$isConfigurationValid = true;
		if (!$this->context->nextcloud->appManager->getOlvidServerUrl() || !$this->context->nextcloud->appManager->getOlvidServerApiKey()) {
			$isConfigurationValid = false;
		}

		return new DataResponse([
			'isConfigurationValid' => $isConfigurationValid,
		]);
	}

	/**
	 * Returns Olvid usage statistics (users, groups, inactive users)
	 *
	 * @return DataResponse<Http::STATUS_OK, OlvidStatistics, array{}>
	 *
	 * 200: Statistics returned
	 * @throws Exception
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/statistics')]
	public function getStatistics(): DataResponse {
		$allNextcloudUsers = $this->context->nextcloud->userManager->getAll();
		$allOlvidUsers = $this->context->db->user->getAll();

		$olvidUsersCount = count(array_filter($allOlvidUsers, fn ($olvidUser): bool => $olvidUser->hasIdentity()));
		$nextcloudUsersCount = count($allNextcloudUsers);
		$olvidInactiveUsersCount = count(array_filter($allOlvidUsers, fn ($olvidUser): bool => $olvidUser->hasIdentity() && $olvidUser->isInactive()));

		$allNextcloudGroups = $this->context->nextcloud->groupManager->getAll();
		$allOlvidGroups = $this->context->db->group->getAll();

		$olvidGroupsCount = count(array_filter($allOlvidGroups, fn ($olvidGroup) => $olvidGroup->getEnabled()));
		$nextcloudGroupsCount = count($allNextcloudGroups);

		return new DataResponse([
			'olvidUsersCount' => $olvidUsersCount,
			'nextcloudUsersCount' => $nextcloudUsersCount,
			'olvidInactiveUsers' => $olvidInactiveUsersCount,

			'olvidGroupsCount' => $olvidGroupsCount,
			'nextcloudGroupsCount' => $nextcloudGroupsCount,
		]);
	}
}
