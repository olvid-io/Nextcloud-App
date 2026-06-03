<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\Api\App\GetMagicLink;
use OCA\Olvid\Api\App\GroupsUpdate;
use OCA\Olvid\Api\App\MeUpdate;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Db\OlvidGroupMapper;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/*
 ** This Api is the backend for the Nextcloud application
 */
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
		private readonly GetMagicLink $getMagicLinkHandler,
		private readonly GroupsUpdate $updateGroupsHandler,
		private readonly MeUpdate $meUpdate,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/status')]
	public function status(): JSONResponse {
		return new JSONResponse(['olvidIdentityUploaded' => $this->olvidUserConfig->hasIdentity($this->userId)]);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/getMagicLink')]
	public function getMagicLink(): JSONResponse {
		return $this->getMagicLinkHandler->handle($this->userId);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/revokeIdentity')]
	public function revokeIdentity(): JSONResponse {

		// TODO implements

		$this->olvidUserConfig->setIdentity($this->userId, '');

		return new JSONResponse();
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me')]
	public function getMe(): JSONResponse {

		return new JSONResponse([
			'firstname' => $this->olvidUserConfig->getFirstname($this->userId),
			'lastname' => $this->olvidUserConfig->getLastname($this->userId),
			'position' => $this->olvidUserConfig->getPosition($this->userId),
			'company' => $this->olvidUserConfig->getCompany($this->userId),
		]);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/app/me')]
	public function updateMe(): JSONResponse {
		return $this->meUpdate->handle($this->userSession->getUser());
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/groups')]
	public function getGroups(): JSONResponse {
		$groups = $this->groupManager->search('', null);

		$response = ['groups' => []];
		foreach ($groups as $group) {
			$gid = $group->getGID();
			$olvidGroup = $this->olvidGroupMapper->findByGroupIdOrNull($gid);
			$members = [];
			foreach ($group->getUsers() as $member) {
				$members[] = [
					'id' => $member->getUID(),
					'name' => $member->getDisplayName(),
					'useOlvid' => $this->olvidUserConfig->hasIdentity($member->getUID()),
				];
			}
			$response['groups'][] = [
				'id' => $gid,
				'name' => $group->getDisplayName(),
				'enabled' => $olvidGroup?->getEnabled() ?? false,
				'customName' => $olvidGroup?->getDiscussionName() ?? null, '',
				'description' => $olvidGroup?->getDiscussionDescription() ?? '',
				'members' => $members,
			];
		}
		return new JSONResponse($response);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/app/groups/{groupId}')]
	public function updateGroup(string $groupId): Response {
		return $this->updateGroupsHandler->handle($groupId);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/app/groups/{groupId}/members/{userId}')]
	public function addGroupMember(string $groupId, string $userId): JSONResponse {
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

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'DELETE', url: '/app/groups/{groupId}/members/{userId}')]
	public function removeGroupMember(string $groupId, string $userId): JSONResponse {
		$group = $this->groupManager->get($groupId);
		if ($group === null) {
			return new JSONResponse(['error' => 'group not found'], 404);
		}
		$user = $this->userManager->get($userId);
		if ($user === null) {
			return new JSONResponse(['error' => 'user not found'], 404);
		}
		$group->removeUser($user);
		return new JSONResponse([]);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/users/search')]
	public function searchUsers(): JSONResponse {
		$query = $this->request->getParam('query', '');
		$users = $this->userManager->search($query, 20);

		$result = [];
		foreach ($users as $user) {
			$result[] = [
				'id' => $user->getUID(),
				'name' => $user->getDisplayName(),
				'useOlvid' => $this->olvidUserConfig->hasIdentity($user->getUID()),
			];
		}
		return new JSONResponse(['users' => $result]);
	}
}
