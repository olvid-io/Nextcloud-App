<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

/*
 * Authenticated entry point
 * Authenticate with an Olvid bearer token (jwt)
 */
abstract class AbstractAuthenticatedDeviceApiHandler extends AbstractDeviceApiHandler {
	public function handle(?array $jsonParameters = null): Response {
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

		// parse token
		try {
			$publicKey = $this->olvidAppConfig->getJwkKeyPublicKey();
			$decoded = JWT::decode($token, new Key($publicKey, 'ES256'));
		} catch (Exception $e) {
			$this->logger->error('Bearer token is invalid: ', ['exception' => $e]);
			return null;
		}

		if ($decoded->type !== 'session') {
			$this->logger->error('Invalid JWK key type: ' . $decoded->type);
			return null;
		}

		$user = $this->userManager->get($decoded->sub);
		// user might have been deleted
		if ($user === null) {
			return null;
		}

		// check token was not revoked
		$sessionsRevokedBefore = $this->olvidUserConfig->getSessionRevokedBefore($user->getUID());
		if ($sessionsRevokedBefore !== null) {
			// if token was issued before last revocation ignore it
			// WARN iat is in seconds, while sessionRevokedBefore is in ms
			if ($decoded->iat * 1000 <= $sessionsRevokedBefore) {
				$this->logger->debug($decoded->sub . ' session was revoked');
				return null;
			}
		}

		return $user;
	}
}
