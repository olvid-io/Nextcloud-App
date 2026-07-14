<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Crypto\AuthEnc;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Utils\Encoded;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/**
 * POST /olvid-rest/getData
 *
 * Unauthenticated endpoint. A device supplies a 32-byte UID; the server looks
 * up the corresponding blob, re-encrypts it with a fresh random IV, and returns
 * the ciphertext. The device already holds the symmetric key (stored in group blob)
 * so it can decrypt client-side.
 *
 * Re-encrypting on every call means each response is unique even when the
 * underlying data has not changed, which prevents replay-based correlation.
 *
 * ── Request ────────────────────────────────────────────────────────────────
 * Raw body: exactly Constants::UID_SIZE (32) bytes — the data UID.
 * (Not an Encoded list — just the bare bytes.)
 *
 * ── Response (Encoded list) ─────────────────────────────────────────────────
 *   [0x00, encryptedData]   success — ciphertext = [IV(8)][ciphertext][HMAC(32)]
 *   [0x09]                  UID unknown or stored key is corrupted
 *   [0xff]                  request body has the wrong length
 */
class GetData extends AbstractEngineApiHandler {
	// Algorithm class 0x02 = ALGO_CLASS_AUTHENTICATED_SYMMETRIC_ENCRYPTION
	private const EXPECTED_ALGO_CLASS = 0x02;
	// Algorithm implementation 0x00 = AES-256-CTR + HMAC-SHA256
	private const EXPECTED_ALGO_IMPL = 0x00;

	// Dictionary keys used inside the Encoded symmetric key — must match the Java constants:
	//   MACKey.MACKEY_KEY_NAME  = "mackey"
	//   SymEncKey.SYMENC_KEY_NAME = "enckey"
	private const DICT_KEY_MAC = 'mackey';
	private const DICT_KEY_ENC = 'enckey';

	// Expected key material lengths (bytes)
	private const MAC_KEY_LENGTH = 32; // HMAC-SHA256
	private const ENC_KEY_LENGTH = 32; // AES-256

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	protected function handler(string $rawInput): BinaryResponse {
		// validate data uid length
		if (strlen($rawInput) !== Constants::UID_SIZE) {
			$this->logger->warning('getData: wrong UID length, got ' . strlen($rawInput));
			return $this->generalError();
		}
		$bytesDataUid = $rawInput;

		// search for data entry in database
		$olvidData = $this->context->db->data->getByUidOrNull($bytesDataUid);
		if ($olvidData === null) {
			$this->logger->warning('getData: no data found for UID');
			return $this->notFound();
		}

		// Decode the stored Olvid symmetric key (type 0x90)
		// The key was stored by storeData as an Encoded AuthEncAES256ThenSHA256Key:
		//   [0x90][len] [Encoded.of([0x02, 0x00])] [Encoded dict{"mackey"->32B, "enckey"->32B}]
		try {
			$keyComponents = Encoded::decodeSymmetricKey($olvidData->getBytesEncodedKey());
		} catch (Exception $e) {
			$this->logger->warning('getData: cannot decode stored key: ', ['exception' => $e]);
			return $this->notFound();
		}

		// Verify this is the algorithm we expect
		if ($keyComponents['algoClass'] !== self::EXPECTED_ALGO_CLASS
			|| $keyComponents['algoImpl'] !== self::EXPECTED_ALGO_IMPL) {
			$this->logger->warning('getData: unexpected key algorithm '
				. $keyComponents['algoClass'] . '/' . $keyComponents['algoImpl']);
			return $this->notFound();
		}

		// split key to extract components
		$dict = $keyComponents['dict'];
		$macKey = $dict[self::DICT_KEY_MAC] ?? null;
		$encKey = $dict[self::DICT_KEY_ENC] ?? null;

		// check key format
		if ($macKey === null || $encKey === null
			|| strlen($macKey) !== self::MAC_KEY_LENGTH
			|| strlen($encKey) !== self::ENC_KEY_LENGTH) {
			$this->logger->warning('getData: missing or wrong-length key material');
			return $this->notFound();
		}

		// Re-encrypt the plaintext
		// A fresh random IV is generated every call so each response is unique.
		try {
			$encryptedData = AuthEnc::encrypt($macKey, $encKey, $olvidData->getBytesData());
		} catch (Exception $e) {
			$this->logger->error('getData: encryption failed: ', ['exception' => $e]);
			return $this->generalError();
		}

		// Return encrypted blob
		return new BinaryResponse(Encoded::encodeList([
			Encoded::encodeBytes(self::STATUS_OK),
			Encoded::encodeBytes($encryptedData),
		]));
	}
}
