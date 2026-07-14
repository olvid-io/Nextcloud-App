<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Models\JsonUserDetails;
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
class Verify extends AbstractEngineApiHandler {
	protected function handler(string $rawInput): BinaryResponse {
		// Parse request
		try {
			$json = json_decode($rawInput, true);
			$signature = $json[Constants::VERIFY_REQUEST_SIGNATURE] ?? null;
			if ($signature === null) {
				throw new Exception('Missing signature field');
			}
		} catch (Exception $e) {
			$this->logger->warning('verify: parse error: ', ['exception' => $e]);
			return $this->generalError();
		}

		// Verify JWT and check identity
		try {
			$decodedPayload = $this->context->signatory->verify($signature);
			$jsonUserDetails = JsonUserDetails::fromArray((array)$decodedPayload);
			if ($jsonUserDetails?->id === null || $jsonUserDetails?->identity === null) {
				throw new Exception('Missing id or identity in JWT payload');
			}
			$userId = $jsonUserDetails->id;
			$base64Identity = $jsonUserDetails->identity;

			// Check identity matches stored value
			$olvidUser = $this->context->db->user->getByUserIdOrNull($userId);
			if ($olvidUser === null || !$olvidUser->hasIdentity()
				|| base64_encode($olvidUser->getBytesIdentity()) !== $base64Identity) {
				$this->logger->debug('verify: identity mismatch for user ' . $userId);
				return $this->binaryResult(false);
			}

			return $this->binaryResult(true);
		} catch (Exception $e) {
			$this->logger->warning('verify: error: ', ['exception' => $e]);
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
