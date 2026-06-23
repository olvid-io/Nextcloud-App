<?php

declare(strict_types=1);

namespace OCA\Olvid\Crypto;

use Exception;

/**
 * Parses an Olvid identity byte-string to extract the server-authentication public key.
 *
 * Identity binary layout (big-endian):
 *   [server URL bytes] [0x00 terminator] [compact server-auth key] [compact encryption key]
 *
 * Compact server-auth key (33 bytes):
 *   byte 0   : algorithm implementation
 *                0x00 → EC-SDSA over MDC
 *                0x01 → EC-SDSA over Curve25519
 *   bytes 1–32: Y coordinate of the public-key point (big-endian unsigned 32 bytes)
 */
class OlvidIdentity {
	public const ALGO_MDC = 0x00;
	public const ALGO_CURVE25519 = 0x01;

	private const COMPACT_KEY_ALGO_BYTE_LENGTH = 1;
	private const COMPACT_KEY_Y_BYTE_LENGTH = 32;
	private const COMPACT_KEY_TOTAL_LENGTH = self::COMPACT_KEY_ALGO_BYTE_LENGTH + self::COMPACT_KEY_Y_BYTE_LENGTH;

	/**
	 * Parse the binary identity and return the Edwards curve and the Y coordinate
	 * of the server-authentication public key.
	 *
	 * @return array{curve: EdwardCurve, ay: \GMP}
	 * @throws Exception on malformed identity bytes
	 */
	public static function parseServerAuthKey(string $identityBytes): array {
		// Find the null-byte server-URL terminator
		$nullPos = strpos($identityBytes, "\x00");
		if ($nullPos === false) {
			throw new Exception('No null-byte terminator found in identity');
		}

		$keyOffset = $nullPos + 1;
		if (strlen($identityBytes) < $keyOffset + self::COMPACT_KEY_TOTAL_LENGTH) {
			throw new Exception('Identity too short to contain a server-auth public key');
		}

		$algoImpl = ord($identityBytes[$keyOffset]);
		$yBytes = substr($identityBytes, $keyOffset + self::COMPACT_KEY_ALGO_BYTE_LENGTH, self::COMPACT_KEY_Y_BYTE_LENGTH);
		$ay = gmp_init(bin2hex($yBytes), 16);

		$curve = match ($algoImpl) {
			self::ALGO_MDC => EdwardCurve::mdc(),
			self::ALGO_CURVE25519 => EdwardCurve::curve25519(),
			default => throw new Exception(sprintf('Unknown server-auth algorithm 0x%02x', $algoImpl)),
		};

		return ['curve' => $curve, 'ay' => $ay];
	}
}
