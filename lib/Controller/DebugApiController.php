<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IAppConfig;
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
		string                           $appName,
		IRequest                         $request,
		private readonly IConfig         $config,
		private readonly IUserSession    $userSession,
		private readonly IAppConfig      $appConfig,
		private readonly IUserManager    $userManager,
		private readonly LoggerInterface $logger,
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
				$userConfig["identity"] = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY);
				$userConfig["identity"] = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY);
				$userConfig["api-key"] = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY);
				$userConfig["nonce"] = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_NONCE);
				$userConfig["signed-details"] = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS);
				$userConfig["role"] = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_ROLE);
				$userConfig["is-bot"] = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IS_BOT);
			}
		} catch (Exception $e) {
			$this->logger->error("debug: Cannot compute configuration link: " . $e);
		}

		return new JSONResponse([
			"appConfig" => [
				"olvidServerUrl" => AppConfigManager::getOlvidServerUrl($this->appConfig),
				"olvidServerApiKey" => AppConfigManager::getOlvidServerApiKey($this->appConfig),
				"jwkKeyId" => AppConfigManager::getJwkKeyId($this->appConfig),
				"jwkPublicKey" => AppConfigManager::getJwkKeyPublicKey($this->appConfig),
			],
			"user" => $userConfig
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

	private function resetUser(Iuser $user): void {
		try {
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_COMPANY, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_POSITION, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_NONCE, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_ROLE, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_FULL_SEARCH_FIELD, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IS_BOT, "");
		} catch (PreConditionNotMetException) {}
	}
}
