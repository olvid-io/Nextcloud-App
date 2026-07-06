<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use Firebase\JWT\JWT;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;

/**
 * POST /olvid-rest/getMagicSession
 *
 * JSON endpoint. Validates a per-user magic token and returns a bearer token.
 *
 * Request body: {"username": "...", "token": "..."}
 * Response: JSON token on success, error JSON on failure.
 */
class GetMagicSession extends AbstractDeviceApiHandler {
	// unauthenticated entrypoint, argument $user is null
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse {
		// --- 1. Parse request ---
		try {
			$nextcloudUser = $jsonParameters[Constants::GET_MAGIC_SESSION_REQUEST_USERNAME] ?? null;
			$token = $jsonParameters[Constants::GET_MAGIC_SESSION_REQUEST_TOKEN] ?? null;
			if ($nextcloudUser === null || $token === null) {
				throw new Exception('Missing username or token');
			}
		} catch (Exception $e) {
			$this->logger->warning('getMagicSession: parse error: ', ['exception' => $e]);
			return $this->invalidRequest();
		}

		// --- 2. Look up user (always return same error to avoid leaking whether user exists) ---
		$targetUser = $this->userManager->get($nextcloudUser);
		if ($targetUser === null) {
			$this->logger->warning('getMagicSession: user not found: ' . $nextcloudUser);
			return $this->invalidRequest();
		}

		// --- 3. Validate magic token ---
		$magicToken = $this->olvidUserConfig->getMagicToken($targetUser->getUID());
		if ($magicToken === null || $token !== $magicToken) {
			$this->logger->warning('getMagicSession: invalid magic token: ' . $nextcloudUser);
			return $this->invalidRequest();
		}

		// Check magic token expiration
		$magicTokenExpiration = $this->olvidUserConfig->getMagicTokenExpiration($targetUser->getUID());
		if ($magicTokenExpiration === null || $magicTokenExpiration < TimeUtil::currentTimeMillis()) {
			$this->logger->warning('getMagicSession: expired magic token for user');
			// delete expired token
			$this->olvidUserConfig->clearMagicToken($targetUser->getUID());
			return $this->invalidRequest();
		}

		// --- 4. Generate session JWT ---
		$privateKeyPem = $this->olvidAppConfig->getJwkKeyPrivateKey();
		$keyId = $this->olvidAppConfig->getJwkKeyId();
		if ($privateKeyPem === null) {
			$this->logger->error('getMagicSession: JWK private key not configured — run occ maintenance:repair');
			return $this->internalError();
		}
		$privateKey = openssl_pkey_get_private($privateKeyPem);
		if ($privateKey === false) {
			$this->logger->error('getMagicSession: failed to load JWK private key from PEM');
			return $this->internalError();
		}
		// timestamp in jwt must be in seconds
		$now = TimeUtil::currentTimeS();
		$expiresIn = Constants::MAGIC_SESSION_DURATION_S;

		$accessToken = JWT::encode([
			'iss' => 'olvid-nextcloud',
			'sub' => $targetUser->getUID(),
			'iat' => $now,
			'exp' => $now + $expiresIn,
			'type' => 'session'
		], $privateKey, 'ES256', $keyId);

		// magic token is deleted in /putKey when process is finished

		return new JSONResponse([
			'access_token' => $accessToken,
			'expires_in' => $expiresIn,
			'token_type' => 'Bearer',
			'refresh_token' => null,
		]);
	}
}
