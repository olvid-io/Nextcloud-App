<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\Api\App\GetMagicLink\GetMagicLink;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

/*
 ** This Api is the backend for the Nextcloud application
 */
class AppApiController extends ApiController {
	public function __construct(
		string   $appName,
		IRequest $request,
		private readonly IConfig  $config,
		private readonly ?string  $userId,
		private readonly GetMagicLink $getMagicLinkHandler,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/status')]
	public function status(): JSONResponse {
		if ($err = $this->requireAuth()) return $err;

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
      if ($err = $this->requireAuth()) return $err;
	  return $this->getMagicLinkHandler->handle($this->userId);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/app/revokeIdentity')]
	public function revokeIdentity(): JSONResponse {
      if ($err = $this->requireAuth()) return $err;

	  $this->config->deleteUserValue(
			$this->userId,
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_IDENTITY
		);

		return new JSONResponse();
	}

	private function requireAuth(): ?JSONResponse {
		if ($this->userId === null) {
			return new JSONResponse(['error' => 'unauthenticated'], 401);
		}
		return null;
	}
}
