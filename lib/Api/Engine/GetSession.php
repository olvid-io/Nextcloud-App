<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use Firebase\JWT\JWT;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Crypto\ECSdsaVerifier;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Utils\Encoded;
use OCA\Olvid\Utils\TimeUtil;

/**
 * POST /olvid-rest/getSession
 *
 * Unauthenticated endpoint (binary Olvid Encoded protocol).
 *
 * Request body: Encoded list [ challengeResponse (80 or 112 bytes), nonce (32 bytes) ]
 *
 * challengeResponse layout:
 *   bytes  0–15 : extra data included in the signed message
 *   bytes 16–79 : EC-SDSA signature (32-byte SHA-256 hash + 32-byte y scalar)
 *     OR
 *   bytes 16–111: EC-SDSA signature (64-byte SHA-512 hash + 32-byte y scalar)
 *
 * The signed message is: TOKEN_SIGNATURE_PREFIX ‖ challenge ‖ challengeResponse[0..15]
 *
 * On success the response contains a bearer token JSON payload as an Encoded byte array.
 */
class GetSession extends AbstractEngineApiHandler {
    protected function handler(string $rawInput): BinaryResponse {
        // --- 1. Parse ---
        try {
            $items = Encoded::decodeList($rawInput);
            if (count($items) < 2) {
                throw new Exception('Not enough list items');
            }
            $challengeResponse = Encoded::decodeBytes($items[0]);
            if (strlen($challengeResponse) !== Constants::ENGINE_RESPONSE_LENGTH &&
                strlen($challengeResponse) !== Constants::ENGINE_RESPONSE_LENGTH_SHA512) {
                throw new Exception('Bad challengeResponse length');
            }
            $nonce = Encoded::decodeBytes($items[1]);
            if (strlen($nonce) !== Constants::ENGINE_NONCE_LENGTH) {
                throw new Exception('Bad nonce length');
            }
        } catch (Exception $e) {
            $this->logger->warning('getSession: parse error: ' . $e->getMessage());
            return $this->parsingError();
        }

        // --- 2. Retrieve and delete the cached challenge ------------------------
        // If the user did not exist at requestChallenge time the challenge was never
        // stored, so we return permissionDenied to avoid leaking whether a userId exists.
        $cacheKey    = 'challenge-' . base64_encode($nonce);
        $cachedValue = $this->cache->get($cacheKey);
        if ($cachedValue === null) {
            $this->logger->error('getSession: challenge not found in cache');
            return $this->permissionDenied();
        }
        $this->cache->remove($cacheKey);

        // --- 3. Decode cached {userId, challenge} pair --------------------------
        try {
            $challengeItems = Encoded::decodeList((string) base64_decode($cachedValue));
            if (count($challengeItems) < 2) {
                throw new Exception('Malformed cached challenge');
            }
            $userId    = Encoded::decodeString($challengeItems[0]);
            $challenge = Encoded::decodeBytes($challengeItems[1]);
        } catch (Exception $e) {
            $this->logger->error('getSession: cannot decode cached challenge: ' . $e->getMessage());
            return $this->generalError();
        }

        // --- 4. Look up user ----------------------------------------------------
        $user = $this->userManager->get($userId);
        if ($user === null) {
            $this->logger->warning('getSession: user not found');
            return $this->permissionDenied();
        }

        // --- 5. Get user Olvid identity -----------------------------------------
        $identityB64 = $this->userConfig->getIdentity($user->getUID());
        if ($identityB64 === null) {
            $this->logger->warning('getSession: user has no Olvid identity');
            return $this->permissionDenied();
        }
        $identityBytes = base64_decode($identityB64, true);
        if ($identityBytes === false) {
            $this->logger->error('getSession: cannot base64-decode identity');
            return $this->permissionDenied();
        }

        // --- 6. Verify signature ------------------------------------------------
        // formattedChallenge = PREFIX ‖ challenge ‖ challengeResponse[0..15]
        $formattedChallenge = Constants::ENGINE_TOKEN_SIGNATURE_PREFIX
            . $challenge
            . substr($challengeResponse, 0, 16);
        $signature = substr($challengeResponse, 16);

        try {
            if (!ECSdsaVerifier::verify($identityBytes, $formattedChallenge, $signature)) {
                $this->logger->error('getSession: signature verification failed');
                return $this->permissionDenied();
            }
        } catch (Exception $e) {
            $this->logger->error('getSession: signature verification error: ' . $e->getMessage());
            return $this->permissionDenied();
        }

        // --- 7. Generate bearer token --------------------------------------------
		$privateKeyPem = $this->olvidAppConfig->getJwkKeyPrivateKey();
		$keyId         = $this->olvidAppConfig->getJwkKeyId();
		if ($privateKeyPem === null) {
			$this->logger->error('getSession: JWK private key not configured — run occ maintenance:repair');
			return $this->generalError();
		}
		$privateKey = openssl_pkey_get_private($privateKeyPem);
		if ($privateKey === false) {
			$this->logger->error('getSession: failed to load JWK private key from PEM');
			return $this->generalError();
		}
		// timestamp in jwt must be in seconds
		$now        = TimeUtil::currentTimeS();
		$expiresIn  = Constants::IDENTITY_SESSION_DURATION_S;

		$accessToken = JWT::encode([
			'iss' => 'olvid-nextcloud',
			'sub' => $user->getUID(),
			'iat' => $now,
			'exp' => $now + $expiresIn,
			'type' => 'session'
		], $privateKey, 'ES256', $keyId);

		$tokenJson = (string) json_encode([
			'access_token'  => $accessToken,
			'expires_in'    => $expiresIn,
			'token_type'    => 'Bearer',
			'refresh_token' => null,
		]);

        return new BinaryResponse(Encoded::encodeList([
            Encoded::encodeBytes(self::STATUS_OK),
            Encoded::encodeBytes($tokenJson),
        ]));
    }
}
