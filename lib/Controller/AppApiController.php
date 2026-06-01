<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\Api\App\GetMagicLink;
use OCA\Olvid\Api\App\UpdateGroups;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Db\OlvidGroupMapper;
use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\OlvidAppConfigManager;
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
		private readonly LoggerInterface         $logger,
		private readonly IUserSession            $userSession,
		private readonly IGroupManager           $groupManager,
		private readonly OlvidGroupMapper        $olvidGroupMapper,
		private readonly IUserManager            $userManager,
		private readonly OlvidUserConfigManager  $olvidUserConfig,
		private readonly OlvidAppConfigManager   $olvidAppConfig,
		private readonly ?string                 $userId,
		private readonly GetMagicLink            $getMagicLinkHandler,
		private readonly UpdateGroups            $updateGroupsHandler,
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
			'lastname'  => $this->olvidUserConfig->getLastname($this->userId),
			'position'  => $this->olvidUserConfig->getPosition($this->userId),
			'company'   => $this->olvidUserConfig->getCompany($this->userId),
		]);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/app/me')]
	public function updateMe(): JSONResponse {
		$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];

		$previousUserDetails = JsonUserDetails::computeDetails($this->userSession->getUser(), $this->olvidUserConfig);

		// update details
		$updated = false;
		if ($previousUserDetails->firstname !== $jsonParameters['firstname']) {
			$this->olvidUserConfig->setFirstname($this->userId, $jsonParameters['firstname']);
			$updated = true;
		}
		if ($previousUserDetails->lastname !== $jsonParameters['lastname']) {
			$this->olvidUserConfig->setLastname($this->userId, $jsonParameters['lastname']);
			$updated = true;
		}
		if ($previousUserDetails->position !== $jsonParameters['position']) {
			$this->olvidUserConfig->setPosition($this->userId, $jsonParameters['position']);
			$updated = true;
		}
		if ($previousUserDetails->company !== $jsonParameters['company']) {
			$this->olvidUserConfig->setCompany($this->userId, $jsonParameters['company']);
			$updated = true;
		}

		// details did not changed, stop here
		if (!$updated) {
			return new JSONResponse([]);
		}

		// re-compute details and sign them
		$userDetails = JsonUserDetails::computeDetails($this->userSession->getUser(), $this->olvidUserConfig);
		$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);

		// update full search field
		$userDetails->updateFullSearchString($this->userId, $this->olvidUserConfig);

		// notify user for change (if he registered)
		if ($userDetails->identity) {
			// TODO feature push topics
			// TODO notify
		}

		return new JSONResponse();
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/groups')]
	public function getGroups(): JSONResponse {
		$groups = $this->groupManager->search("", null);

		$response = ["groups" => []];
		foreach ($groups as $group) {
			$gid = $group->getGID();
			$olvidGroup = $this->olvidGroupMapper->findByGroupIdOrNull($gid);
			$members = [];
			foreach ($group->getUsers() as $member) {
				$members[] = [
					"id"       => $member->getUID(),
					"name"     => $member->getDisplayName(),
					"useOlvid" => $this->olvidUserConfig->hasIdentity($member->getUID()),
				];
			}
			$response["groups"][] = [
				"id"          => $gid,
				"name"        => $group->getDisplayName(),
				"enabled"     => $olvidGroup?->getEnabled() ?? false,
				"customName"  => $olvidGroup?->getDiscussionName() ?? null, "",
				"description" => $olvidGroup?->getDiscussionDescription() ?? "",
				"members"     => $members,
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
				"id"       => $user->getUID(),
				"name"     => $user->getDisplayName(),
				"useOlvid" => $this->olvidUserConfig->hasIdentity($user->getUID()),
			];
		}
		return new JSONResponse(['users' => $result]);
	}
}
