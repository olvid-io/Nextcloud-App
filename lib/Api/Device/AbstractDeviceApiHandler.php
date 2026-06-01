<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;

abstract class AbstractDeviceApiHandler {
	protected ?IUser $user = null;

    public function __construct(
		protected readonly IRequest                $request,
		protected readonly LoggerInterface         $logger,
		protected readonly IConfig                 $config,
		protected readonly IAppConfig              $appConfig,
		protected readonly IUserManager            $userManager,
		protected readonly IGroupManager           $groupManager,
        protected readonly IAccountManager         $accountManager,
		protected readonly ILockingProvider        $lockingProvider,
		protected readonly OlvidUserConfigManager  $olvidUserConfig,
		protected readonly OlvidAppConfigManager   $olvidAppConfig,
		protected readonly OlvidDatabase           $db,
    ) {}

    public function handle(): Response {
		// parse json payload
		try {
			$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];
		} catch (Exception $exception) {
			$this->logger->error(get_class($this) . ": cannot parse request: ", ["exception" => $exception]);
			return $this->internalError();
		}

		try {
			return $this->handler($jsonParameters, $this->user);
		}
		catch (Exception $exception) {
			$this->logger->error(get_class($this) . ": unexpected exception in handler", ["exception" => $exception]);
			return $this->internalError();
		}
    }

	// $user is passed only for authenticated handlers
	abstract public function handler(array $jsonParameters, ?IUser $user): Response;

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// standard responses
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	public static function success(): JSONResponse {
		return new JSONResponse(["status"=> BaseJsonResponse::STATUS_SUCCESS], 200);
    }

    // error responses for devices
    public static function permissionDenied(): JSONResponse {
        return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_PERMISSION_DENIED, "permission denied");
    }

    public static function invalidRequest(): JSONResponse {
        return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_INVALID_REQUEST, "invalid request");
    }

    public static function identityAlreadyUploaded(): JSONResponse {
        return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_ALREADY_UPLOADED, "identity already uploaded, use override option");
    }

    public static function identityWasRevoked(): JSONResponse {
        return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_WAS_REVOKED, "Identity has been revoked");
    }

    public static function identityNotUploadedYet(): JSONResponse {
        return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_NOT_UPLOADED_YET, "Identity was not uploaded yet");
    }

    public static function internalError(): JSONResponse {
        return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_INTERNAL_ERROR, "internal error");
    }

	public static function createErrorResponse(int $errorCode, string $message): JSONResponse {
		$response = new BaseJsonResponse($message, $errorCode, BaseJsonResponse::STATUS_ERROR);
		$response->message = $message;
		$response->error = $errorCode;
		$response->status = BaseJsonResponse::STATUS_ERROR;
		return new JSONResponse($response, 200);
	}
}
