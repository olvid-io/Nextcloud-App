<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Crypto\ECSdsaVerifier;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Utils\Encoded;

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
		// parse request
		try {
			$items = Encoded::decodeList($rawInput);
			if (count($items) < 2) {
				throw new Exception('Not enough list items');
			}
			$bytesChallengeResponse = Encoded::decodeBytes($items[0]);
			if (strlen($bytesChallengeResponse) !== Constants::ENGINE_RESPONSE_LENGTH
				&& strlen($bytesChallengeResponse) !== Constants::ENGINE_RESPONSE_LENGTH_SHA512) {
				throw new Exception('Bad challengeResponse length');
			}
			$bytesNonce = Encoded::decodeBytes($items[1]);
			if (strlen($bytesNonce) !== Constants::ENGINE_NONCE_LENGTH) {
				throw new Exception('Bad nonce length');
			}
		} catch (Exception $e) {
			$this->logger->warning('getSession: parse error: ', ['exception' => $e]);
			return $this->parsingError();
		}

		// Retrieve and delete the cached challenge
		// If the user did not exist at requestChallenge time the challenge was never
		// stored, so we return permissionDenied to avoid leaking whether a userId exists.
		$cacheKey = 'challenge-' . base64_encode($bytesNonce);
		$cachedValue = $this->cache->get($cacheKey);
		if ($cachedValue === null) {
			$this->logger->error('getSession: challenge not found in cache');
			return $this->permissionDenied();
		}
		$this->cache->remove($cacheKey);

		// Decode cached {userId, challenge} pair
		try {
			$challengeItems = Encoded::decodeList((string)base64_decode($cachedValue));
			if (count($challengeItems) < 2) {
				throw new Exception('Malformed cached challenge');
			}
			$userId = Encoded::decodeString($challengeItems[0]);
			$challenge = Encoded::decodeBytes($challengeItems[1]);
		} catch (Exception $e) {
			$this->logger->error('getSession: cannot decode cached challenge: ', ['exception' => $e]);
			return $this->generalError();
		}

		// get OlvidUser in database
		$olvidUser = $this->context->db->user->getByUserIdOrNull($userId);
		if (!$olvidUser->hasIdentity()) {
			$this->logger->warning('getSession: user has no Olvid identity');
			return $this->permissionDenied();
		}

		// Verify signature
		// formattedChallenge: PREFIX ‖ challenge ‖ challengeResponse[0..15]
		$formattedChallenge = Constants::ENGINE_TOKEN_SIGNATURE_PREFIX
			. $challenge
			. substr($bytesChallengeResponse, 0, 16);
		$signature = substr($bytesChallengeResponse, 16);

		try {
			if (!ECSdsaVerifier::verify($olvidUser->getBytesIdentity(), $formattedChallenge, $signature)) {
				$this->logger->error('getSession: signature verification failed');
				return $this->permissionDenied();
			}
		} catch (Exception $e) {
			$this->logger->error('getSession: signature verification error: ', ['exception' => $e]);
			return $this->permissionDenied();
		}

		// Generate bearer token and return it
		return new BinaryResponse(Encoded::encodeList([
			Encoded::encodeBytes(self::STATUS_OK),
			Encoded::encodeBytes(json_encode($this->context->signatory->generateBearerToken($userId, Constants::IDENTITY_SESSION_DURATION_S))),
		]));
	}
}
