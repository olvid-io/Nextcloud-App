<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;

/*
 * Authenticated entry point
 * Authenticate with an Olvid bearer token (jwt)
 */
abstract class AbstractAuthenticatedDeviceApiHandler extends AbstractDeviceApiHandler {
	public function handle(): Response {
		// check authentication
		$this->user = $this->requiresAuth();
		if ($this->user === null) {
			return AbstractDeviceApiHandler::permissionDenied();
		}

		return parent::handle();
	}

	private function requiresAuth(): ?IUser {
		if (!$this->request->getHeader("Authorization")) {
			$this->logger->error('Missing authentication header');
			return null;
		}

		// parse token
		$rawHeader = $this->request->getHeader("Authorization");
		$token = str_starts_with(strtolower($rawHeader), "bearer ") ? trim(substr($rawHeader, 7)) : $rawHeader;

		// parse token
		try {
			$publicKey = AppConfigManager::getJwkKeyPublicKey($this->appConfig);
			$decoded = JWT::decode($token, new Key($publicKey, 'ES256'));
		} catch (Exception $e) {
			$this->logger->error('Bearer token is invalid: ' . $e->getMessage());
			return null;
		}

		if ($decoded->type !== "session") {
			$this->logger->error("Invalid JWK key type: " . $decoded->type);
			return null;
		}

		$user = $this->userManager->get($decoded->sub);
		$this->logger->debug($decoded->sub . ' logged in using bearer token');

		// check token was not revoked
		$sessionsRevokedBefore = $this->config->getUserValue(
			$user->getUID(),
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_SESSION_REVOKED_BEFORE
		);
		if ($sessionsRevokedBefore !== null && $sessionsRevokedBefore != 0) {
			// if token was issued before last revocation ignore it
			if ($decoded->iat <= $sessionsRevokedBefore) {
				$this->logger->debug($decoded->sub . ' token expired');
				return null;
			}
		}

		return $user;
	}

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// standard responses
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public static function success(): JSONResponse {
		return new JSONResponse(["status"=> BaseJsonResponse::STATUS_SUCCESS], 200);
    }

    // error responses for devices
    public static function permissionDenied(): JSONResponse {
        return AbstractAuthenticatedDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_PERMISSION_DENIED, "permission denied");
    }

    public static function invalidRequest(): JSONResponse {
        return AbstractAuthenticatedDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_INVALID_REQUEST, "invalid request");
    }

    public static function identityAlreadyUploaded(): JSONResponse {
        return AbstractAuthenticatedDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_ALREADY_UPLOADED, "identity already uploaded, use override option");
    }

    public static function identityWasRevoked(): JSONResponse {
        return AbstractAuthenticatedDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_WAS_REVOKED, "Identity has been revoked");
    }

    public static function identityNotUploadedYet(): JSONResponse {
        return AbstractAuthenticatedDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_NOT_UPLOADED_YET, "Identity was not uploaded yet");
    }

    public static function internalError(): JSONResponse {
        return AbstractAuthenticatedDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_INTERNAL_ERROR, "internal error");
    }

	public static function createErrorResponse(int $errorCode, string $message): JSONResponse {
		$response = new BaseJsonResponse($message, $errorCode, BaseJsonResponse::STATUS_ERROR);
		$response->message = $message;
		$response->error = $errorCode;
		$response->status = BaseJsonResponse::STATUS_ERROR;
		return new JSONResponse($response, 200);
	}
}
