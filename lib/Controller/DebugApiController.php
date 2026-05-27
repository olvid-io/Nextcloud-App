<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\AppInfo\Application;
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
		private readonly OlvidAppConfigManager   $olvidAppConfig,
		private readonly OlvidUserConfigManager  $olvidUserConfig,
		private readonly IUserSession            $userSession,
		private readonly IUserManager            $userManager,
		private readonly LoggerInterface         $logger,
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
	public function resetAll(): TextPlainResponse {
		$users = $this->userManager->search("");
		foreach ($users as $user) {
			$this->olvidUserConfig->deleteUserConfig($user->getUID());
		}
		return new TextPlainResponse("reset " . count($users) . " users");
	}
}
