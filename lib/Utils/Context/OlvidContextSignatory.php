<?php

namespace OCA\Olvid\Utils\Context;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\IUser;
use stdClass;

class OlvidContextSignatory {
	private ?string $cachedKeyId = null;
	private ?string $cachedKeyType = null;
	private ?Key $cachedPublicKey = null;
	private ?string $cachedPrivateKey = null;

	public function __construct(
		private readonly OlvidAppConfigManager $appConfigManager,
	) {
	}

	public function verify(string $jwt): stdClass {
		return JWT::decode($jwt, $this->getCachedPublicKey());
	}
	public function sign(array $jsonPayload): string {
		return JWT::encode($jsonPayload, $this->getCachedPrivateKey(), $this->getCachedKeyType(), $this->getCachedKeyId());
	}

	/*
	 ** Bearer token sessions
	 * Bearer token are JWT, signed by server keys.
	 * To revoke a session we set a sessionRevokedBefore
	 */
	// timestamp in jwt must be in seconds !!
	public function generateBearerToken(string $userId, int $expiresInS): array {
		$now = TimeUtil::currentTimeS();
		$payload = [
			'iss' => 'olvid-nextcloud',
			'sub' => $userId,
			'iat' => $now,
			'exp' => $now + $expiresInS,
			'type' => 'session'
		];
		$accessToken = $this->sign($payload);
		return [
			'access_token' => $accessToken,
			'expires_in' => $expiresInS,
			'token_type' => 'Bearer',
			'refresh_token' => null,
		];
	}

	/**
	 * Check a bearer token is valid and was not revoked.
	 * Return IUser nextcloudUser associated to this token.
	 * @param string $bearerToken
	 * @param OlvidContext $context
	 * @return IUser
	 * @throws Exception
	 */
	public function verifyBearerToken(string $bearerToken, OlvidContext $context): IUser {
		// decode token and check type
		$decodedToken = JWT::decode($bearerToken, $this->getCachedPublicKey());
		if ($decodedToken->type !== 'session') {
			throw new Exception("Invalid token type: '$decodedToken->type'.");
		}
		// check user exists (in nextcloud)
		$nextcloudUser = $context->nextcloud->userManager->get($decodedToken->sub);
		if (!$nextcloudUser) {
			throw new Exception("User not found: '$decodedToken->sub'.");
		}
		// check user session were not revoked
		$olvidUser = $context->db->user->getByUserIdOrNull($nextcloudUser->getUID());
		if ($olvidUser?->getSessionRevokedBefore() !== null) {
			// if token was issued before last revocation ignore it
			// WARN iat is in seconds, while sessionRevokedBefore is in ms
			if ($decodedToken->iat * 1000 <= $olvidUser->getSessionRevokedBefore()) {
				throw new Exception("Session was revoked: '{$olvidUser->getUserId()}'.");
			}
		}
		// check user did not revoke its identity since session creation
		// (in case user was deleted and SessionRevokedBefore was unset)
		// WARN iat is in seconds, while sessionRevokedBefore is in ms
		$revocations = $context->db->revocation->getUserRevocationsSinceTimestampOrNull($olvidUser->getUserId(), $decodedToken->iat * 1000);
		if ($revocations !== null && sizeof($revocations) > 0) {
			throw new Exception("User identity was revoked: '{$olvidUser->getUserId()}'.");
		}
		return $nextcloudUser;
	}

	private function getCachedKeyId(): ?string {
		if ($this->cachedKeyId == null) {
			$this->cachedKeyId = $this->appConfigManager->getJwkKeyId();
		}
		return $this->cachedKeyId;
	}
	private function getCachedKeyType(): ?string {
		if ($this->cachedKeyType == null) {
			$this->cachedKeyType = $this->appConfigManager->getJwkKeyType();
		}
		return $this->cachedKeyType;
	}
	private function getCachedPublicKey(): Key {
		if ($this->cachedPublicKey == null) {
			$this->cachedPublicKey = new Key($this->appConfigManager->getJwkKeyPublicKey(), 'ES256');
		}
		return $this->cachedPublicKey;
	}
	private function getCachedPrivateKey(): ?string {
		if ($this->cachedPrivateKey == null) {
			$this->cachedPrivateKey = $this->appConfigManager->getJwkKeyPrivateKey();
		}
		return $this->cachedPrivateKey;
	}
}
