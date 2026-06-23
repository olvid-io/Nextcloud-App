<?php

declare(strict_types=1);

namespace OCA\Olvid\Crypto;

use Exception;

/**
 * Olvid EC-SDSA (Schnorr-like) signature verification.
 *
 * Signature format (64 or 96 bytes depending on hash):
 *   [hash (32 or 64 bytes)] [y scalar (32 bytes)]
 *
 * Verification of (message, signature) against a public key A with Y coordinate Ay:
 *
 *   1. Parse: hash = sig[0..len−33], y_bytes = sig[len−32..len−1]
 *   2. e = bigUInt(hash),  y = bigUInt(y_bytes)
 *   3. Require y < q
 *   4. For each candidate P in mulAdd(y, G, e, A):
 *        hashInput = [P.y (32 bytes)] ‖ [Ay (32 bytes)] ‖ message
 *        if SHA256(hashInput) == hash (or SHA512 for the longer variant): VALID
 */
class ECSdsaVerifier {
	private const L = 32; // curve byte length (same for MDC and Curve25519)

	/**
	 * Verify an EC-SDSA signature.
	 *
	 * @param string $identityBytes Raw Olvid identity bytes (contains server-auth public key).
	 * @param string $message Signed message bytes.
	 * @param string $signature EC-SDSA signature (64 or 96 bytes).
	 * @return bool True iff the signature is valid.
	 * @throws Exception On malformed identity or unsupported parameters.
	 */
	public static function verify(string $identityBytes, string $message, string $signature): bool {
		['curve' => $curve, 'ay' => $ay] = OlvidIdentity::parseServerAuthKey($identityBytes);

		$sigLen = strlen($signature);
		if ($sigLen === self::L + 32) {
			$isSha512 = false; // 32-byte SHA-256 hash + 32-byte y scalar
		} elseif ($sigLen === self::L + 64) {
			$isSha512 = true;  // 64-byte SHA-512 hash + 32-byte y scalar
		} else {
			return false; // bad signature length
		}

		$hashBytes = substr($signature, 0, $sigLen - self::L);
		$yBytes = substr($signature, $sigLen - self::L);

		$e = gmp_init(bin2hex($hashBytes), 16);
		$y = gmp_init(bin2hex($yBytes), 16);

		// y must be a valid scalar (< group order)
		if (gmp_cmp($y, $curve->q) >= 0) {
			return false;
		}

		// Compute y·G + e·A candidates
		$candidates = $curve->mulAdd($y, $e, $ay);

		// Fixed part of the hash input: position l..2l−1 = public key Ay bytes
		$ayBytes = $curve->gmpToBytes($ay);
		$algo = $isSha512 ? 'sha512' : 'sha256';

		foreach ($candidates as [$cx, $cy]) {
			// hashInput = [candidate.Y (32 bytes)] ‖ [Ay (32 bytes)] ‖ [message]
			$hashInput = $curve->gmpToBytes($cy) . $ayBytes . $message;
			$recomputed = hash($algo, $hashInput, true);
			if (hash_equals($recomputed, $hashBytes)) {
				return true;
			}
		}
		return false;
	}
}
