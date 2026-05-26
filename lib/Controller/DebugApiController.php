<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;

// TODO TODEL
class DebugApiController extends ApiController
{
	public function __construct(
		string                                   $appName,
		IRequest                                 $request,
		private readonly IConfig                 $config,
		private readonly OlvidUserConfigManager  $userConfig,
		private readonly IUserSession            $userSession,
		private readonly OlvidAppConfigManager   $olvidAppConfig,
		private readonly IUserManager            $userManager,
		private readonly LoggerInterface         $logger,
	)
	{
		parent::__construct($appName, $request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/debug')]
	public function debug(): JSONResponse
	{
		$userConfig = [];
		try {
			$user = $this->userSession->getUser();
			if ($user != null) {
				$userConfig["display-name"] = $user->getDisplayName();
				$userConfig["identity"] = $this->userConfig->getIdentity($user->getUID());
				$userConfig["api-key"] = $this->userConfig->getApiKey($user->getUID());
				$userConfig["nonce"] = $this->userConfig->getNonce($user->getUID());
				$userConfig["signed-details"] = $this->userConfig->getSignedDetails($user->getUID());
				$userConfig["role"] = $this->userConfig->getRole($user->getUID());
				$userConfig["is-bot"] = $this->userConfig->getIsBot($user->getUID());
			}
		} catch (Exception $e) {
			$this->logger->error("debug: Cannot compute userConfig: " . $e);
		}
		try {
			$userFields = $this->config->getAllUserValues($user->getUID());
		} catch (Exception $e) {
			$this->logger->error("debug: Cannot compute user fields: " . $e);
		}

		return new JSONResponse([
			"appConfig" => [
				"olvidServerUrl" => $this->olvidAppConfig->getOlvidServerUrl(),
				"olvidServerApiKey" => $this->olvidAppConfig->getOlvidServerApiKey(),
				"jwkKeyId" => $this->olvidAppConfig->getJwkKeyId(),
				"jwkPublicKey" => $this->olvidAppConfig->getJwkKeyPublicKey(),
			],
			"user" => $userConfig,
			"userFields" => $userFields,
		], 200);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/reset')]
	public function reset(): TextPlainResponse {
		$user = $this->userSession->getUser();
		if ($user) {
			$this->resetUser($user);
			return new TextPlainResponse("reset " . $user->getDisplayName());
		}
		else {
			return new TextPlainResponse("not logged in");
		}
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/resetAll')]
	public function resetAll(): TextPlainResponse {
		$users = $this->userManager->search("");
		foreach ($users as $user) {
			$this->resetUser($user);
		}
		return new TextPlainResponse("reset " . count($users) . " users");
	}

	private function resetUser(IUser $user): void {
		try {
			$this->userConfig->deleteUserConfig($user->getUID());
		} catch (PreConditionNotMetException) {}
	}
}
