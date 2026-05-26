<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use Firebase\JWT\JWT;
use OCA\Olvid\Api\Constants;
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
	public function handler(array $jsonParameters, ?IUser $user): JSONResponse {
        // --- 1. Parse request ---
        try {
            $user = $jsonParameters[Constants::GET_MAGIC_SESSION_REQUEST_USERNAME] ?? null;
            $token    = $jsonParameters[Constants::GET_MAGIC_SESSION_REQUEST_TOKEN]    ?? null;
            if ($user === null || $token === null) {
                throw new Exception('Missing username or token');
            }
        } catch (Exception $e) {
            $this->logger->warning('getMagicSession: parse error: ' . $e->getMessage());
            return $this->invalidRequest();
        }

        // --- 2. Look up user (always return same error to avoid leaking whether user exists) ---
        $targetUser = $this->userManager->get($user);
        if ($targetUser === null) {
            $this->logger->warning('getMagicSession: user not found: ' . $user);
            return $this->invalidRequest();
        }

        // --- 3. Validate magic token ---
        $storedJson = $this->olvidUserConfig->getMagicToken($targetUser->getUID());
        if ($storedJson === null) {
            $this->logger->warning('getMagicSession: no magic token stored for user');
            return $this->invalidRequest();
        }

		// Decode magic token
        $stored = json_decode($storedJson, true);
        if (!is_array($stored) || !isset($stored['token']) || $stored['token'] !== $token) {
            $this->logger->warning('getMagicSession: invalid token for user');
            return $this->invalidRequest();
        }

        // Check magic token expiration (null = no expiration)
        if (isset($stored['expiration']) && $stored['expiration'] !== null) {
            if ($stored['expiration'] < time()) {
                $this->logger->warning('getMagicSession: expired magic token for user');
                return $this->invalidRequest();
            }
        }

        // --- 4. Generate session JWT ---
		$privateKeyPem = $this->olvidAppConfig->getJwkKeyPrivateKey();
		$keyId         = $this->olvidAppConfig->getJwkKeyId();
		if ($privateKeyPem === null) {
			$this->logger->error('getMagicSession: JWK private key not configured — run occ maintenance:repair');
			return $this->internalError();
		}
		$privateKey = openssl_pkey_get_private($privateKeyPem);
		if ($privateKey === false) {
			$this->logger->error('getMagicSession: failed to load JWK private key from PEM');
			return $this->internalError();
		}
		$now       = time();
		$expiresIn = Constants::MAGIC_SESSION_DURATION_S;

		$accessToken = JWT::encode([
			'iss' => 'olvid-nextcloud',
			'sub' => $targetUser->getUID(),
			'iat' => $now,
			'exp' => $now + $expiresIn,
			'type' => 'session'
		], $privateKey, 'ES256', $keyId);

		return new JSONResponse([
			'access_token'  => $accessToken,
			'expires_in'    => $expiresIn,
			'token_type'    => 'Bearer',
			'refresh_token' => null,
		]);
    }
}
