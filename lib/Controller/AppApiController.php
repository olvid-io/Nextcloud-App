<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\Api\App\GetMagicLink;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCA\Olvid\Utils\OlvidGroupConfigManager;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;

/*
 ** This Api is the backend for the Nextcloud application
 */
class AppApiController extends ApiController {
	public function __construct(
		string                                   $appName,
		IRequest                                 $request,
		private readonly IUserSession            $userSession,
		private readonly IGroupManager           $groupManager,
		private readonly OlvidGroupConfigManager $olvidGroupConfig,
		private readonly IUserManager            $userManager,
		private readonly IConfig                 $config,
		private readonly IAppConfig              $appConfig,
		private readonly ?string                 $userId,
		private readonly GetMagicLink            $getMagicLinkHandler,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/status')]
	public function status(): JSONResponse {

		$identity = $this->config->getUserValue(
			$this->userId,
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_IDENTITY,
		);

		return new JSONResponse(['olvidIdentityUploaded' => $identity !== '']);
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

	  $this->config->deleteUserValue(
			$this->userId,
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_IDENTITY
		);

		return new JSONResponse();
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/me')]
	public function getMe(): JSONResponse {

		return new JSONResponse([
			'firstname' => $this->config->getUserValue($this->userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_FIRSTNAME),
			'lastname'  => $this->config->getUserValue($this->userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_LASTNAME),
			'position'  => $this->config->getUserValue($this->userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_POSITION),
			'company'   => $this->config->getUserValue($this->userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_COMPANY),
		]);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/app/me')]
	public function updateMe(): JSONResponse {

		$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];

		$previousUserDetails = OlvidUserDetails::computeDetails($this->userSession->getUser(), $this->config);

		// update details
		$updated = false;
		if ($previousUserDetails->firstname !== $jsonParameters['firstname']) {
			$this->config->setUserValue($this->userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_FIRSTNAME, $jsonParameters['firstname']);
			$updated = true;
		}
		if ($previousUserDetails->lastname !== $jsonParameters['lastname']) {
			$this->config->setUserValue($this->userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_LASTNAME, $jsonParameters['lastname']);
			$updated = true;
		}
		if ($previousUserDetails->position !== $jsonParameters['position']) {
			$this->config->setUserValue($this->userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_POSITION, $jsonParameters['position']);
			$updated = true;
		}
		if ($previousUserDetails->company !== $jsonParameters['company']) {
			$this->config->setUserValue($this->userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_COMPANY, $jsonParameters['company']);
			$updated = true;
		}

		// details did not changed, stop here
		if (!$updated) {
			return new JSONResponse([]);
		}

		// re-compute details and sign them
		$userDetails = OlvidUserDetails::computeDetails($this->userSession->getUser(), $this->config);
		$userDetails->sign($this->config, $this->appConfig);

		// update full search field
		$userDetails->updateFullSearchString($this->userId, $this->config);

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
			$members = [];
			foreach ($group->getUsers() as $member) {
				$members[] = [
					"id"       => $member->getUID(),
					"name"     => $member->getDisplayName(),
					"useOlvid" => (bool)$this->config->getUserValue($member->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY),
				];
			}
			$response["groups"][] = [
				"id"          => $gid,
				"name"        => $group->getDisplayName(),
				"enabled"     => $this->olvidGroupConfig->getIsOlvidDiscussionEnabled($gid),
				"customName"  => $this->olvidGroupConfig->getCustomName($gid),
				"description" => $this->olvidGroupConfig->getDescription($gid),
				"members"     => $members,
			];
		}
		return new JSONResponse($response);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'PUT', url: '/app/groups/{groupId}')]
	public function updateGroup(string $groupId): JSONResponse {
		if ($this->groupManager->get($groupId) === null) {
			return new JSONResponse(['error' => 'group not found'], 404);
		}

		$body = json_decode(file_get_contents('php://input'), true) ?? [];

		if (isset($body['enabled'])) {
			$this->olvidGroupConfig->setIsOlvidDiscussionEnabled($groupId, $body['enabled']);
		}
		if (array_key_exists('customName', $body)) {
			$this->olvidGroupConfig->setCustomName($groupId, (string)$body['customName']);
		}
		if (array_key_exists('description', $body)) {
			$this->olvidGroupConfig->setDescription($groupId, (string)$body['description']);
		}

		return new JSONResponse([]);
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
				"useOlvid" => (bool)$this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY),
			];
		}
		return new JSONResponse(['users' => $result]);
	}
}
