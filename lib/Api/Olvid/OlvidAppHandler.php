<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid;

use Exception;
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

abstract class OlvidAppHandler {
    public function __construct(
		protected readonly IConfig $config,
		protected readonly IAppConfig $appConfig,
        protected readonly IUserManager $userManager,
        protected readonly IAccountManager $accountManager,
        protected readonly IUserSession $userSession,
        protected readonly IGroupManager $groupManager,
		protected readonly ILockingProvider $lockingProvider,
		protected readonly LoggerInterface $logger,
    ) {}

    public function handle(IUser $user): Response {
		// parse json payload
		try {
			$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];
		} catch (Exception $exception) {
			$this->logger->error(get_class($this) . ": cannot parse request: " . $exception);
			return $this->internalErrorDevice();
		}

		try {
			return $this->handler($user, $jsonParameters);
		}
		catch (Exception $e) {
			$this->logger->error(get_class($this) . ": unexpected exception in handler: " . $e);
			return $this->internalErrorDevice();
		}
    }

	abstract public function handler(IUser $user, array $jsonParameters);

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// standard responses
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public static function success(): JSONResponse {
		return new JSONResponse(["status"=> BaseJsonResponse::STATUS_SUCCESS], 200);
    }

    // error responses for devices
    public static function permissionDeniedDevice(): JSONResponse {
        return OlvidAppHandler::createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_PERMISSION_DENIED, "permission denied");
    }

    public static function invalidRequestDevice(): JSONResponse {
        return OlvidAppHandler::createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_INVALID_REQUEST, "invalid request");
    }

    public static function identityAlreadyUploaded(): JSONResponse {
        return OlvidAppHandler::createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_ALREADY_UPLOADED, "identity already uploaded, use override option");
    }

    public static function identityWasRevoked(): JSONResponse {
        return OlvidAppHandler::createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_WAS_REVOKED, "Identity has been revoked");
    }

    public static function identityNotUploadedYet(): JSONResponse {
        return OlvidAppHandler::createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_NOT_UPLOADED_YET, "Identity was not uploaded yet");
    }

    public static function internalErrorDevice(): JSONResponse {
        return OlvidAppHandler::createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_INTERNAL_ERROR, "internal error");
    }

	public static function createDeviceErrorResponse(int $errorCode, string $message): JSONResponse {
		$response = new BaseJsonResponse($message, $errorCode, BaseJsonResponse::STATUS_ERROR);
		$response->message = $message;
		$response->error = $errorCode;
		$response->status = BaseJsonResponse::STATUS_ERROR;
		return new JSONResponse($response, 200);
	}
}
