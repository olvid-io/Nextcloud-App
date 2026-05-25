<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Utils\Encoded;

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
        // --- 1. Parse ---
        try {
            $items = Encoded::decodeList($rawInput);
            if (count($items) < 2) {
                throw new Exception('Not enough list items');
            }
            $userId = Encoded::decodeString($items[0]);
            $nonce  = Encoded::decodeBytes($items[1]);
            if (strlen($nonce) !== Constants::ENGINE_NONCE_LENGTH) {
                throw new Exception('Invalid nonce length');
            }
        } catch (Exception $e) {
            $this->logger->warning('requestChallenge: parse error: ' . $e->getMessage());
            return $this->parsingError();
        }

        // --- 2. Generate challenge (always, even for unknown users, to avoid leaking existence) ---
        try {
            $challenge = random_bytes(Constants::ENGINE_CHALLENGE_LENGTH);
        } catch (Exception $e) {
            $this->logger->error('requestChallenge: cannot generate random bytes: ' . $e->getMessage());
            return $this->generalError();
        }

        $response = new BinaryResponse(Encoded::encodeList([
            Encoded::encodeBytes(self::STATUS_OK),
            Encoded::encodeBytes($challenge),
            Encoded::encodeBytes($nonce),
        ]));

        // --- 3. Look up user and cache challenge only when user has an identity ---
        $user = $this->userManager->get($userId);
        if ($user === null) {
            $this->logger->warning('requestChallenge: user not found');
            return $response;
        }

        $identity = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY);
        if ($identity === '') {
            $this->logger->warning('requestChallenge: user has no Olvid identity');
            return $response;
        }

        $cacheKey   = 'challenge-' . base64_encode($nonce);
        $cacheValue = base64_encode(Encoded::encodeList([
            Encoded::encodeString($userId),
            Encoded::encodeBytes($challenge),
        ]));
        $this->cache->set($cacheKey, $cacheValue, self::CACHE_TTL);

        return $response;
    }
}
