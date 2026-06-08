<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\Api\App\GroupsUpdate;
use OCA\Olvid\Api\App\UserGetMagicLink;
use OCA\Olvid\Api\App\UserUpdate;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Attribute\AdminRequired;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Db\OlvidGroupMapper;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class AppApiController extends ApiController {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly IUserSession $userSession,
		private readonly IGroupManager $groupManager,
		private readonly OlvidGroupMapper $olvidGroupMapper,
		private readonly OlvidDatabase $db,
		private readonly OlvidServer $olvidServer,
		private readonly IUserManager $userManager,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly ?string $userId,
		private readonly UserGetMagicLink $userGetMagicLink,
		private readonly GroupsUpdate $updateGroupsHandler,
		private readonly UserUpdate $userUpdate,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	// ── Current-user endpoints (accessible to all authenticated users) ──────
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me')]
	public function meGet(): JSONResponse {
		return new JSONResponse([
			'firstname' => $this->olvidUserConfig->getFirstname($this->userId),
			'lastname' => $this->olvidUserConfig->getLastname($this->userId),
			'position' => $this->olvidUserConfig->getPosition($this->userId),
			'company' => $this->olvidUserConfig->getCompany($this->userId),
			'olvidIdentityUploaded' => $this->olvidUserConfig->hasIdentity($this->userId),
			'isAdmin' => $this->groupManager->isAdmin($this->userId),
		]);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/app/me')]
	public function mePut(): JSONResponse {
		$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];
		return $this->userUpdate->handle($this->userSession->getUser(), $jsonParameters['firstname'], $jsonParameters['lastname'], $jsonParameters['position'], $jsonParameters['company']);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me/getMagicLink')]
	public function meGetMagicLink(): JSONResponse {
		return $this->userGetMagicLink->handle($this->userId);
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/app/me/identity')]
	public function meIdentityDelete(): JSONResponse {
		// TODO: implement full Olvid revocation protocol (notify Olvid server, create revocation record in olvid_revocation table)
		$this->olvidUserConfig->clearIdentity($this->userId);
		return new JSONResponse();
	}

	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me/groups')]
	public function meGroups(): JSONResponse {
		$user = $this->userSession->getUser();
		$groups = $this->groupManager->getUserGroups($user);
		$result = [];
		foreach ($groups as $group) {
			$gid = $group->getGID();
			$olvidGroup = $this->olvidGroupMapper->findByGroupIdOrNull($gid);
			$result[] = [
				'id' => $gid,
				'displayName' => $group->getDisplayName(),
				'olvidEnabled' => $olvidGroup?->getEnabled() ?? false,
			];
		}
		return new JSONResponse(['groups' => $result]);
	}

	// ── Group endpoints (admin) ──────────────────────────────────────────────
	#[ApiRoute(verb: 'GET', url: '/app/groups')]
	public function groupsGet(): JSONResponse {
		$groups = $this->groupManager->search('', null);

		$response = ['groups' => []];
		foreach ($groups as $group) {
			$gid = $group->getGID();
			$olvidGroup = $this->olvidGroupMapper->findByGroupIdOrNull($gid);
			$members = [];
			foreach ($group->getUsers() as $member) {
				$members[] = [
					'id' => $member->getUID(),
					'displayName' => $member->getDisplayName(),
					'useOlvid' => $this->olvidUserConfig->hasIdentity($member->getUID()),
				];
			}
			$response['groups'][] = [
				'id' => $gid,
				'displayName' => $group->getDisplayName(),
				'enabled' => $olvidGroup?->getEnabled() ?? false,
				'customName' => $olvidGroup?->getDiscussionName() ?? null,
				'description' => $olvidGroup?->getDiscussionDescription() ?? '',
				'members' => $members,
			];
		}
		return new JSONResponse($response);
	}

	#[ApiRoute(verb: 'POST', url: '/app/groups')]
	public function groupsPost(): JSONResponse {
		$body = json_decode(file_get_contents('php://input'), true) ?? [];
		$gid = trim($body['id'] ?? '');

		if ($gid === '') {
			return new JSONResponse(['error' => 'id is required'], 400);
		}

		if ($this->groupManager->get($gid) !== null) {
			return new JSONResponse(['error' => 'group already exists'], 400);
		}

		$group = $this->groupManager->createGroup($gid);
		return new JSONResponse([
			'id' => $group->getGID(),
			'displayName' => $group->getDisplayName(),
			'enabled' => false,
			'members' => [],
		]);
	}

	#[ApiRoute(verb: 'PUT', url: '/app/groups/{groupId}')]
	public function groupsPut(string $groupId): Response {
		return $this->updateGroupsHandler->handle($groupId);
	}

	#[ApiRoute(verb: 'POST', url: '/app/groups/{groupId}/members/{userId}')]
	public function groupsMemberPost(string $groupId, string $userId): JSONResponse {
		$group = $this->groupManager->get($groupId);
		if ($group === null) {
			return new JSONResponse(['error' => 'group not found'], 404);
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new JSONResponse(['error' => 'user not found'], 404);
		}
		$group->addUser($user);
		return new JSONResponse([]);
	}

	#[ApiRoute(verb: 'DELETE', url: '/app/groups/{groupId}/members/{userId}')]
	public function groupsMemberDelete(string $groupId, string $userId): JSONResponse {
		$group = $this->groupManager->get($groupId);
		if ($group === null) {
			return new JSONResponse(['error' => 'group not found'], 404);
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new JSONResponse(['error' => 'user not found'], 404);
		}
		// protect against last admin deletion !
		if ($group->getGID() === 'admin' && $user->getUID() === $this->userSession->getUser()->getUID()) {
			return new JSONResponse(['error' => 'you can remove yourself from admin group'], 400);
		}
		$group->removeUser($user);
		return new JSONResponse([]);
	}

	// ── User search (admin, used by groups sidebar) ──────────────────────────
	#[ApiRoute(verb: 'GET', url: '/app/users/search')]
	public function usersSearch(): JSONResponse {
		$query = $this->request->getParam('query', '');
		$users = $this->userManager->search($query, 20);

		$result = [];
		foreach ($users as $user) {
			$result[] = [
				'id' => $user->getUID(),
				'displayName' => $user->getDisplayName(),
				'useOlvid' => $this->olvidUserConfig->hasIdentity($user->getUID()),
			];
		}
		return new JSONResponse(['users' => $result]);
	}

	// ── User management endpoints (admin only) ───────────────────────────────

	#[ApiRoute(verb: 'GET', url: '/app/users')]
	public function usersGet(): JSONResponse {
		$users = $this->userManager->search('', null);

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
		return new JSONResponse(['users' => $result]);
	}

	#[ApiRoute(verb: 'POST', url: '/app/users')]
	public function usersPost(): JSONResponse {
		$body = json_decode(file_get_contents('php://input'), true) ?? [];
		$uid = trim($body['uid'] ?? '');
		$password = $body['password'] ?? '';

		if ($uid === '' || $password === '') {
			return new JSONResponse(['error' => 'uid and password are required'], 400);
		}

		if ($this->userManager->get($uid) !== null) {
			return new JSONResponse(['error' => 'user already exists'], 400);
		}

		try {
			$user = $this->userManager->createUser($uid, $password);
			if ($user === false) {
				return new JSONResponse(['error' => 'Could not create user'], 500);
			}
		} catch (Exception $e) {
			$this->logger->error('createUser failed: ' . $e->getMessage());
			return new JSONResponse(['error' => $e->getMessage()], 500);
		}

		foreach (['firstname', 'lastname', 'position', 'company'] as $field) {
			$value = trim($body[$field] ?? '');
			if ($value === '') {
				continue;
			}
			match ($field) {
				'firstname' => $this->olvidUserConfig->setFirstname($uid, $value),
				'lastname' => $this->olvidUserConfig->setLastname($uid, $value),
				'position' => $this->olvidUserConfig->setPosition($uid, $value),
				'company' => $this->olvidUserConfig->setCompany($uid, $value),
			};
		}

		return new JSONResponse([
			'id' => $user->getUID(),
			'displayName' => $user->getDisplayName(),
			'useOlvid' => false,
			'firstname' => $this->olvidUserConfig->getFirstname($uid),
			'lastname' => $this->olvidUserConfig->getLastname($uid),
			'position' => $this->olvidUserConfig->getPosition($uid),
			'company' => $this->olvidUserConfig->getCompany($uid),
		]);
	}

	#[ApiRoute(verb: 'GET', url: '/app/users/{userId}/magicLink')]
	public function usersGetMagicLink(string $userId): JSONResponse {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new JSONResponse(['error' => 'user not found'], 404);
		}
		return $this->userGetMagicLink->handle($userId);
	}

	#[ApiRoute(verb: 'PUT', url: '/app/users/{userId}')]
	public function updateUser(string $userId): JSONResponse {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new JSONResponse(['error' => 'user not found'], 404);
		}

		$body = json_decode(file_get_contents('php://input'), true) ?? [];
		return $this->userUpdate->handle($user, $body['firstname'], $body['lastname'], $body['position'], $body['company']);
	}

	#[ApiRoute(verb: 'DELETE', url: '/app/users/{userId}')]
	public function deleteUser(string $userId): JSONResponse {
		if ($userId === $this->userId) {
			return new JSONResponse(['error' => 'Cannot delete yourself'], 400);
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new JSONResponse(['error' => 'user not found'], 404);
		}
		$user->delete();
		return new JSONResponse([]);
	}

	#[ApiRoute(verb: 'GET', url: '/app/users/{userId}/groups')]
	public function getUserGroups(string $userId): JSONResponse {
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new JSONResponse(['error' => 'user not found'], 404);
		}
		$groups = $this->groupManager->getUserGroups($user);
		$result = [];
		foreach ($groups as $group) {
			$result[] = [
				'id' => $group->getGID(),
				'displayName' => $group->getDisplayName(),
			];
		}
		return new JSONResponse(['groups' => $result]);
	}
}
