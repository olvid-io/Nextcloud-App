<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;

/*
 * DeviceApi:
 *   Entrypoint called by client applications to interact with directory.
 *   This API (mostly) use json as input/output format.
 * AbstractAuthenticatedDeviceApiHandler:
 * Some entrypoint requires authentication. We use bearer tokens issued by call
 * to /requestChallenge and /magicSession.
 */
/**
 * @template TResponse of JSONResponse
 * @template-extends AbstractDeviceApiHandler<TResponse>
 * @psalm-import-type OlvidDirectoryApiError from ResponseDefinitions
 */
abstract class AbstractAuthenticatedDeviceApiHandler extends AbstractDeviceApiHandler {
	/**
	 * @return TResponse|JSONResponse<Http::STATUS_OK, OlvidDirectoryApiError, array{}>
	 */
	public function handle(?array $jsonParameters = null): JSONResponse {
		// check authentication
		$this->user = $this->requiresAuth();
		if ($this->user === null) {
			return AbstractDeviceApiHandler::permissionDenied();
		}

		return parent::handle($jsonParameters);
	}

	private function requiresAuth(): ?IUser {
		if (!$this->request->getHeader('Authorization')) {
			$this->logger->error('Missing authentication header');
			return null;
		}

		// parse token
		$rawHeader = $this->request->getHeader('Authorization');
		$token = str_starts_with(strtolower($rawHeader), 'bearer ') ? trim(substr($rawHeader, 7)) : $rawHeader;

		// check token validity
		try {
			return $this->context->signatory->verifyBearerToken($token, $this->context);
		} catch (Exception $exception) {
			$this->logger->error('Bearer token is invalid: ' . $exception->getMessage());
			return null;
		}
	}
}
