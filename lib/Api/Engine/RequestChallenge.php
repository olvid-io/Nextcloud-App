<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Utils\Encoded;
use OCA\Olvid\Utils\RandomUtil;

/**
 * POST /olvid-rest/requestChallenge
 *
 * Unauthenticated endpoint. Request and response use the binary Olvid Encoded protocol
 * (application/octet-stream), not JSON.
 *
 * Request body: Encoded list [ userId (string), nonce (32 bytes) ]
 * Response body: Encoded list [ status (1 byte), challenge (32 bytes), nonce (32 bytes) ]
 *
 * On success the challenge is cached for 60 s under key "challenge-<base64(nonce)>" so that
 * a subsequent /getSession call can verify the signed response.
 */
class RequestChallenge extends AbstractEngineApiHandler {
	private const CACHE_TTL = 60;

	protected function handler(string $rawInput): BinaryResponse {
		// parse request
		try {
			$items = Encoded::decodeList($rawInput);
			if (count($items) < 2) {
				throw new Exception('Not enough list items');
			}
			$userId = Encoded::decodeString($items[0]);
			$bytesNonce = Encoded::decodeBytes($items[1]);
			if (strlen($bytesNonce) !== Constants::ENGINE_NONCE_LENGTH) {
				throw new Exception('Invalid nonce length');
			}
		} catch (Exception $e) {
			$this->logger->warning('requestChallenge: parse error: ', ['exception' => $e]);
			return $this->parsingError();
		}

		// generate challenge (always, even for unknown users, to avoid leaking existence)
		try {
			$bytesChallenge = RandomUtil::random_bytes(Constants::ENGINE_CHALLENGE_LENGTH);
		} catch (Exception $e) {
			$this->logger->error('requestChallenge: cannot generate random bytes: ', ['exception' => $e]);
			return $this->generalError();
		}

		// prepare response
		$response = new BinaryResponse(Encoded::encodeList([
			Encoded::encodeBytes(self::STATUS_OK),
			Encoded::encodeBytes($bytesChallenge),
			Encoded::encodeBytes($bytesNonce),
		]));

		// look up user and cache challenge only when user has an identity
		$olvidUser = $this->context->db->user->getByUserIdOrNull($userId);
		if ($olvidUser == null) {
			$this->logger->warning('requestChallenge: user not found');
			return $response;
		}
		if (!$olvidUser->hasIdentity()) {
			$this->logger->warning('requestChallenge: user has no Olvid identity');
			return $response;
		}

		// we can cache challenge now, user exists and have an identity to resolve it
		$cacheKey = 'challenge-' . base64_encode($bytesNonce);
		$cacheValue = base64_encode(Encoded::encodeList([
			Encoded::encodeString($userId),
			Encoded::encodeBytes($bytesChallenge),
		]));
		$this->cache->set($cacheKey, $cacheValue, self::CACHE_TTL);

		return $response;
	}
}
