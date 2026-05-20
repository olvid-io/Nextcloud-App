<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\MagicLink\MagicLink;
use OCA\Olvid\AppInfo\Application;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\ApiController;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/*
 ** This Api is the backend for the Nextcloud application
 */
class AppApiController extends ApiController {
	public function __construct(
		string   $appName,
		IRequest $request,
		private readonly IConfig  $config,
		private readonly ?string  $userId,
		private readonly IUserSession $userSession,
		private readonly IAppConfig $appConfig,
		private readonly IURLGenerator $urlGenerator,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/status')]
	public function status(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'unauthenticated'], 401);
		}
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
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'unauthenticated'], 401);
		}
		return (new MagicLink($this->config, $this->appConfig, $this->urlGenerator, $this->logger))->handle($this->userId);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/revokeIdentity')]
	public function revokeIdentity(): JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'unauthenticated'], 401);
		}
		$this->config->deleteUserValue(
			$this->userId,
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_IDENTITY
		);
		return new JSONResponse();
	}

	// TODO is this necessary ?
	private function getUser(): ?IUser {
		return $this->userSession->getUser();
	}
}
