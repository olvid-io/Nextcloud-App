<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\GetMagicSession;

use Exception;
use Firebase\JWT\JWT;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\OlvidAppHandler;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

/**
 * POST /olvid-rest/getMagicSession
 *
 * JSON endpoint. Validates a per-user magic token and returns a bearer token.
 *
 * Request body: {"username": "...", "token": "..."}
 * Response: JSON token on success, error JSON on failure.
 */
class GetMagicSession extends OlvidAppHandler {
	// unauthenticated entrypoint, argument $user is null
	public function handler(?IUser $user, array $jsonParameters): JSONResponse {
        // --- 1. Parse request ---
        try {
            $user = $jsonParameters[Constants::GET_MAGIC_SESSION_REQUEST_USERNAME] ?? null;
            $token    = $jsonParameters[Constants::GET_MAGIC_SESSION_REQUEST_TOKEN]    ?? null;
            if ($user === null || $token === null) {
                throw new Exception('Missing username or token');
            }
        } catch (Exception $e) {
            $this->logger->warning('getMagicSession: parse error: ' . $e->getMessage());
            return $this->invalidRequestDevice();
        }

        // --- 2. Look up user (always return same error to avoid leaking whether user exists) ---
        $targetUser = $this->userManager->get($user);
        if ($targetUser === null) {
            $this->logger->warning('getMagicSession: user not found: ' . $user);
            return $this->invalidRequestDevice();
        }

        // --- 3. Validate magic token ---
        $storedJson = $this->config->getUserValue(
			$targetUser->getUID(),
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_MAGIC_TOKEN);
        if ($storedJson === '') {
            $this->logger->warning('getMagicSession: no magic token stored for user');
            return $this->invalidRequestDevice();
        }

		// Decode magic token
        $stored = json_decode($storedJson, true);
        if (!is_array($stored) || !isset($stored['token']) || $stored['token'] !== $token) {
            $this->logger->warning('getMagicSession: invalid token for user');
            return $this->invalidRequestDevice();
        }

        // Check magic token expiration (null = no expiration)
        if (isset($stored['expiration']) && $stored['expiration'] !== null) {
            if ($stored['expiration'] < time()) {
                $this->logger->warning('getMagicSession: expired magic token for user');
                return $this->invalidRequestDevice();
            }
        }

        // --- 4. Generate session JWT ---
		$privateKeyPem = AppConfigManager::getJwkKeyPrivateKey($this->appConfig);
		$keyId         = AppConfigManager::getJwkKeyId($this->appConfig);
		if ($privateKeyPem === null) {
			$this->logger->error('getMagicSession: JWK private key not configured — run occ maintenance:repair');
			return $this->internalErrorDevice();
		}
		$privateKey = openssl_pkey_get_private($privateKeyPem);
		if ($privateKey === false) {
			$this->logger->error('getMagicSession: failed to load JWK private key from PEM');
			return $this->internalErrorDevice();
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
