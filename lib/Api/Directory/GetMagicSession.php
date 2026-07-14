<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;

/**
 * POST /olvid-rest/getMagicSession
 *
 * JSON endpoint. Validates a per-user magic token and returns a bearer token.
 * This entrypoint does not consume the magic token, it will be deleted later in
 * /putKey when the key will be successfully uploaded to server and the user will
 * be able to authenticate with identity authentication.
 *
 * Request body: {"username": "...", "token": "..."}
 * Response: JSON token on success, error JSON on failure.
 */
class GetMagicSession extends AbstractDeviceApiHandler {
	// unauthenticated entrypoint, argument $user is null
	/**
	 * @param array $jsonParameters
	 * @param IUser|null $nextcloudUser: always null (unauthenticated entry point)
	 * @return JSONResponse
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse {
		// Parse request
		try {
			$nextcloudUserId = $jsonParameters['username'] ?? null;
			$token = $jsonParameters['token'] ?? null;
			if ($nextcloudUserId === null || $token === null) {
				throw new Exception('Missing username or token');
			}
		} catch (Exception $e) {
			$this->logger->warning('getMagicSession: parse error: ', ['exception' => $e]);
			return $this->invalidRequest();
		}

		// Look up user (always return same error to avoid leaking whether user exists)
		$targetUser = $this->context->nextcloud->userManager->get($nextcloudUserId);
		if ($targetUser === null) {
			$this->logger->warning('getMagicSession: user not found: ' . $nextcloudUserId);
			return $this->invalidRequest();
		}

		// Validate magic token
		$olvidUser = $this->context->db->user->getByUserIdOrNull($nextcloudUserId);
		if ($olvidUser?->getMagicToken() === null || $token !== $olvidUser->getMagicToken()) {
			$this->logger->warning('getMagicSession: invalid magic token: ' . $nextcloudUserId);
			return $this->invalidRequest();
		}

		// Check magic token expiration
		if ($olvidUser->getMagicTokenExpiration() === null || $olvidUser->getMagicTokenExpiration() < TimeUtil::currentTimeMillis()) {
			$this->logger->warning('getMagicSession: expired magic token for user');
			// delete expired token
			try {
				$olvidUser->setMagicToken(null);
				$this->context->db->user->update($olvidUser);
			} catch (\OCP\DB\Exception) {
			}
			return $this->invalidRequest();
		}

		// generate and return bearer token
		// magic token is deleted in /putKey when process is finished
		return new JSONResponse($this->context->signatory->generateBearerToken($targetUser->getUID(), Constants::MAGIC_SESSION_DURATION_S));
	}
}
