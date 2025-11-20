<?php

declare(strict_types=1);

namespace OCA\Olvid\Api;

use Exception;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

abstract class ApiHandler {
	protected IConfig $config;
	protected IAppConfig $appConfig;
    protected IUserManager $userManager;
    protected IAccountManager $accountManager;
    protected IUserSession $userSession;
    protected IGroupManager $groupManager;
	protected LoggerInterface $logger;

    public function __construct(
		IConfig $config,
		IAppConfig $appConfig,
        IUserManager $userManager,
        IAccountManager $accountManager,
        IUserSession $userSession,
        IGroupManager $groupManager,
		LoggerInterface $logger,
    ) {
		$this->config = $config;
		$this->appConfig = $appConfig;
        $this->userManager = $userManager;
        $this->accountManager = $accountManager;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
		$this->logger = $logger;
    }

    public function handle(?IUser $user, IRequest $rawRequest): Response {
		// parse json payload
		try {
			$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];
		} catch (Exception $exception) {
			$this->logger->error(get_class($this) . ": cannot parse request: " . $exception);
			return $this->internalErrorDevice();
		}

		try {
			return $this->handler($user, $rawRequest, $jsonParameters);
		}
		catch (Exception $e) {
			$this->logger->error(get_class($this) . ": unexpected exception in handler: " . $e);
			return $this->internalErrorDevice();
		}
    }

	abstract public function handler(?IUser $user, IRequest $request, array $jsonParameters);

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// standard responses
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	protected function success(): Response {
		return new JSONResponse(["status"=> BaseJsonResponse::STATUS_SUCCESS], 200);
    }

    // error responses for devices
    protected function permissionDeniedDevice(): Response {
        return $this->createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_PERMISSION_DENIED, "permission denied");
    }

    protected function invalidRequestDevice(): Response {
        return $this->createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_INVALID_REQUEST, "invalid request");
    }

    protected function identityAlreadyUploaded(): Response {
        return $this->createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_ALREADY_UPLOADED, "identity already uploaded, use override option");
    }

    protected function identityWasRevoked(): Response {
        return $this->createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_WAS_REVOKED, "Identity has been revoked");
    }

    protected function identityNotUploadedYet(): Response {
        return $this->createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_NOT_UPLOADED_YET, "Identity was not uploaded yet");
    }

    protected function internalErrorDevice(): Response {
        return $this->createDeviceErrorResponse(BaseJsonResponse::ERROR_CODE_INTERNAL_ERROR, "internal error");
    }

	protected function createDeviceErrorResponse(int $errorCode, string $message): Response {
		$response = new BaseJsonResponse($message, $errorCode, BaseJsonResponse::STATUS_ERROR);
		$response->message = $message;
		$response->error = $errorCode;
		$response->status = BaseJsonResponse::STATUS_ERROR;
		return new JSONResponse($response, 200);
	}
}
