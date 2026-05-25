<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\Api\App\GetMagicLink;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Http\SseEvent;
use OCA\Olvid\Http\SseResponse;
use OCA\Olvid\Models\OlvidUserDetails;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

/*
 ** This Api is the backend for the Nextcloud application
 */
class AppApiController extends ApiController {
	public function __construct(
		string   $appName,
		IRequest $request,
		private readonly IUserSession $userSession,
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
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
		if ($err = $this->requireAuth()) return $err;

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
		if ($err = $this->requireAuth()) return $err;

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
	#[ApiRoute(verb: 'GET', url: '/app/enrollmentStatus')]
	public function enrollmentStatus(): Response {
		if ($err = $this->requireAuth()) return $err;
		$config = $this->config;
		$userId = $this->userId;
		return new SseResponse(function () use ($config, $userId): ?SseEvent {
			$identity = $config->getUserValue($userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY);
			return $identity !== '' ? new SseEvent('enrolled') : null;
		});
	}

	private function requireAuth(): ?JSONResponse {
		if ($this->userId === null || $this->userSession->getUser() === null) {
			return new JSONResponse(['error' => 'unauthenticated'], 401);
		}
		return null;
	}
}
