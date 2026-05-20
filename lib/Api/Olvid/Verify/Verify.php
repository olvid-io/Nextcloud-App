<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\Verify;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\EngineApiHandler;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Utils\AppConfigManager;
use OCA\Olvid\Utils\Encoded;

/**
 * POST /olvid-rest/verify
 *
 * Mixed protocol: JSON request body, binary Encoded response.
 *
 * Request body: {"signature": "<ES256 JWT>"}
 *
 * The JWT was signed by this app's EC P-256 key (same key exposed at /.well-known/jwks).
 * Payload contains "id" (Nextcloud UID) and "identity" (base64 Olvid identity).
 *
 * Response: Encoded list [STATUS_OK=0x00, boolean] on success,
 *           Encoded list [STATUS_GENERAL_ERROR=0xff] on parse/crypto error.
 */
class Verify extends EngineApiHandler {
    protected function handler(string $rawInput): BinaryResponse {

        // --- 1. Parse request ---
        $signature = null;
        try {
            $json      = json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
            $signature = $json[Constants::VERIFY_REQUEST_SIGNATURE] ?? null;
            if ($signature === null) {
                throw new Exception('Missing signature field');
            }
        } catch (Exception $e) {
            $this->logger->warning('verify: parse error: ' . $e->getMessage());
            return $this->generalError();
        }

        // --- 2. Verify JWT and check identity ---
        try {
            // Split JWT
            $parts = explode('.', $signature);
            if (count($parts) !== 3) {
                throw new Exception('Invalid JWT format');
            }
            [$headerB64, $payloadB64, $sigB64] = $parts;

            // Decode payload
            $payloadJson = base64_decode(strtr($payloadB64, '-_', '+/'));
            $payload     = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);

            $userId   = $payload[Constants::DETAILS_KEY_ID]       ?? null;
            $identity = $payload[Constants::DETAILS_KEY_IDENTITY] ?? null;
            if ($userId === null || $identity === null || $identity === '') {
                throw new Exception('Missing id or identity in JWT payload');
            }

            // Get app's EC public key (PEM)
            $publicKeyPem = AppConfigManager::getJwkKeyPublicKey($this->appConfig);
            if (!$publicKeyPem) {
                throw new Exception('No JWK public key configured');
            }

            // Verify ES256 signature: JWT uses raw R||S (64 bytes), openssl needs DER
            $signingInput = $headerB64 . '.' . $payloadB64;
            $rawSig       = base64_decode(strtr($sigB64, '-_', '+/'));
            if (strlen($rawSig) !== 64) {
                throw new Exception('Unexpected ES256 signature length: ' . strlen($rawSig));
            }

            $pubKey = openssl_pkey_get_public($publicKeyPem);
            if ($pubKey === false) {
                throw new Exception('Invalid public key PEM');
            }

            $verified = openssl_verify($signingInput, self::rawToDer($rawSig), $pubKey, OPENSSL_ALGO_SHA256) === 1;
            if (!$verified) {
                $this->logger->debug('verify: signature verification failed');
                return $this->binaryResult(false);
            }

            // Check identity matches stored value
            $user = $this->userManager->get($userId);
            if ($user === null) {
                $this->logger->debug('verify: user not found: ' . $userId);
                return $this->binaryResult(false);
            }

            $storedIdentity = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY);
            if ($storedIdentity === '' || $storedIdentity !== $identity) {
                $this->logger->debug('verify: identity mismatch for user ' . $userId);
                return $this->binaryResult(false);
            }

            return $this->binaryResult(true);

        } catch (Exception $e) {
            $this->logger->warning('verify: error: ' . $e->getMessage());
            return $this->generalError();
        }
    }

    /**
     * Convert a raw ECDSA signature (R||S, 64 bytes) to DER format for openssl_verify.
     */
    private static function rawToDer(string $raw): string {
        $r = ltrim(substr($raw, 0, 32), "\x00") ?: "\x00";
        $s = ltrim(substr($raw, 32, 32), "\x00") ?: "\x00";
        if (ord($r[0]) >= 0x80) {
            $r = "\x00" . $r;
        }
        if (ord($s[0]) >= 0x80) {
            $s = "\x00" . $s;
        }
        $seq = "\x02" . chr(strlen($r)) . $r . "\x02" . chr(strlen($s)) . $s;
        return "\x30" . chr(strlen($seq)) . $seq;
    }

    private function binaryResult(bool $value): BinaryResponse {
        return new BinaryResponse(Encoded::encodeList([
            Encoded::encodeBytes(self::STATUS_OK),
            Encoded::encodeBoolean($value),
        ]));
    }
}
