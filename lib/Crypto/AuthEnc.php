<?php

declare(strict_types=1);

namespace OCA\Olvid\Crypto;

use Exception;
use OCA\Olvid\Utils\RandomUtil;

/**
 * AuthEncAES256ThenSHA256: authenticated encryption (encrypt-then-MAC).
 *
 * Ciphertext layout: [IV (8 bytes)] [AES-256-CTR ciphertext] [HMAC-SHA256 (32 bytes)]
 * The HMAC covers IV || ciphertext.
 * The AES CTR nonce is the 8-byte IV right-zero-padded to 16 bytes.
 */
class AuthEnc {
	private const IV_LENGTH = 8;

	/**
	 * @throws Exception
	 */
	public static function encrypt(string $macKey, string $encKey, string $plaintext): string {
		$iv = RandomUtil::random_bytes(self::IV_LENGTH);
		if ($iv === null) {
			throw new Exception('Failed to generate IV');
		}

		$iv16 = $iv . str_repeat("\x00", 8);
		$ciphertext = openssl_encrypt($plaintext, 'aes-256-ctr', $encKey, OPENSSL_RAW_DATA, $iv16);
		if ($ciphertext === false) {
			throw new Exception('AES-256-CTR encryption failed');
		}

		$mac = hash_hmac('sha256', $iv . $ciphertext, $macKey, true);
		return $iv . $ciphertext . $mac;
	}
}
