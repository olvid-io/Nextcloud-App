<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

// TODO TODEL
class DebugApiController extends ApiController
{
	public function __construct(
		string                                   $appName,
		IRequest                                 $request,
		private readonly IConfig                 $config,
		private readonly IAppConfig              $appConfig,
		private readonly IGroupManager           $groupManager,
		private readonly IUserSession            $userSession,
		private readonly IUserManager            $userManager,
		private readonly LoggerInterface         $logger,
		private readonly OlvidAppConfigManager   $olvidAppConfig,
		private readonly OlvidUserConfigManager  $olvidUserConfig,
		private readonly OlvidDatabase           $db,
	)
	{
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/me')]
	public function me(): JSONResponse
	{
		try {
			$userFields = $this->config->getAllUserValues($this->userSession->getUser()->getUID());
		} catch (Exception $e) {
			$this->logger->error("debug: Cannot compute user fields: " . $e);
		}

		return new JSONResponse([
			"user" => $userFields[Application::APP_ID],
			"fullUser" => $userFields,
		], 200);
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/groups')]
	public function groups(): JSONResponse
	{
		$response = [];

		$userGroups = $this->groupManager->getUserGroups($this->userSession->getUser());
		foreach ($userGroups as $nextcloudGroup) {
			$olvidGroup = $this->db->group->findByGroupIdOrNull($nextcloudGroup->getGID());

			$response[$nextcloudGroup->getGID()] = [
				"groupId" => $olvidGroup?->getgroupId(),
				"groupUid" => $olvidGroup?->getGroupUid() !== null ? base64_encode($olvidGroup?->getGroupUid()) : null,
				"lastModificationTimestamp" => $olvidGroup?->getLastModificationTimestamp(),
				"pushTopic" => $olvidGroup?->getPushTopic(),
				"groupPhotoUid" => $olvidGroup?->getGroupPhotoUid(),
				"serializedSharedSettings" => $olvidGroup?->getSerializedSharedSettings(),
				"signedGroupBlob" => $olvidGroup?->getSignedGroupBlob(),
				"enabled" => $olvidGroup?->getEnabled(),
				"discussionName" => $olvidGroup?->getDiscussionName(),
				"discussionDescription" => $olvidGroup?->getDiscussionDescription(),
				"blob" => $olvidGroup !== null ? JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig) : null,
			];
		}

		return new JSONResponse($response);
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/groupsReset')]
	public function groupsReset(): JSONResponse
	{
		$this->db->group->deleteAll();
		$this->db->groupDeletion->deleteAll();
		return new JSONResponse("Done");
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/debug')]
	public function debug(): JSONResponse
	{
		try {
			$appFields = $this->appConfig->getAllValues(Application::APP_ID);
		} catch (Exception $e) {
			$this->logger->error("debug: Cannot compute user fields: " . $e);
		}

		try {
			$userFields = $this->config->getAllUserValues($this->userSession->getUser()->getUID());
		} catch (Exception $e) {
			$this->logger->error("debug: Cannot compute user fields: " . $e);
		}

		return new JSONResponse([
			"app" => $appFields,
			"user" => $userFields[Application::APP_ID],
			"fullUser" => $userFields,
		], 200);
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/reset')]
	public function reset(): TextPlainResponse {
		$user = $this->userSession->getUser();
		if ($user) {
			$this->olvidUserConfig->deleteUserConfig($user->getUID());
			return new TextPlainResponse("reset " . $user->getDisplayName());
		}
		else {
			return new TextPlainResponse("not logged in");
		}
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/resetAll')]
	public function resetAll(): JSONResponse {
		// reset app data
		$this->olvidAppConfig->deleteAppConfig();

		// reset users data
		$users = $this->userManager->search("");
		foreach ($users as $user) {
			$this->olvidUserConfig->deleteUserConfig($user->getUID());
		}

		// reset groups data
		$groups = $this->db->group->findAll();
		$this->db->group->deleteAll();
		$this->db->groupDeletion->deleteAll();

		return new JSONResponse([
			"success" => true,
			"cleaned-app" => true,
			"cleaned-users" => count($users),
			"cleaned-groups" => count($groups),
		]);
	}
}
