<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\ResponseDefinitions;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/*
 * DeviceApi:
 *   Entrypoint called by client applications to interact with directory.
 *   This API (mostly) use json as input/output format.
 */
/**
 * @template TResponse of JSONResponse
 * @psalm-import-type OlvidDirectoryApiError from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryApiSuccess from ResponseDefinitions
 */
abstract class AbstractDeviceApiHandler {
	protected ?IUser $user = null;

	public function __construct(
		protected readonly IRequest $request,
		protected readonly LoggerInterface $logger,
		protected readonly ILockingProvider $lockingProvider,
		protected readonly OlvidContext $context,
	) {
	}

	/**
	 * @return TResponse|JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public function handle(?array $jsonParameters = null): JSONResponse {
		// parse json payload from php://input when no params injected by the controller
		if ($jsonParameters === null) {
			try {
				$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];
			} catch (Exception $exception) {
				$this->logger->error(get_class($this) . ': cannot parse request: ', ['exception' => $exception]);
				return $this->internalError();
			}
		}

		try {
			return $this->handler($jsonParameters, $this->user);
		} catch (Throwable $throwable) {
			$this->logger->error(get_class($this) . ': unexpected exception in handler', ['exception' => $throwable]);
			return $this->internalError();
		}
	}

	// $user is passed only for authenticated handlers
	/**
	 * @return TResponse
	 */
	abstract public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse;

	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// standard responses
	////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiSuccess, array{}>
	 */
	public static function success(): JSONResponse {
		return new JSONResponse(['status' => BaseJsonResponse::STATUS_SUCCESS], Http::STATUS_OK);
	}

	// error responses for devices
	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public static function permissionDenied(): JSONResponse {
		return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_PERMISSION_DENIED, 'permission denied');
	}

	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public static function invalidRequest(): JSONResponse {
		return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_INVALID_REQUEST, 'invalid request');
	}

	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public static function identityAlreadyUploaded(): JSONResponse {
		return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_ALREADY_UPLOADED, 'identity already uploaded, use override option');
	}

	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public static function identityWasRevoked(): JSONResponse {
		return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_WAS_REVOKED, 'Identity has been revoked');
	}

	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public static function identityNotUploadedYet(): JSONResponse {
		return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_IDENTITY_NOT_UPLOADED_YET, 'Identity was not uploaded yet');
	}

	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public static function internalError(): JSONResponse {
		return AbstractDeviceApiHandler::createErrorResponse(BaseJsonResponse::ERROR_CODE_INTERNAL_ERROR, 'internal error');
	}

	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public static function createErrorResponse(int $errorCode, string $message): JSONResponse {
		return new JSONResponse([
			'status' => BaseJsonResponse::STATUS_ERROR,
			'error' => $errorCode,
			'message' => $message,
		], Http::STATUS_OK);
	}
}
