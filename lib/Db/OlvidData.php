<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Currently this table is used to store group photos only, but it might be extended
 * to add any file to share with one or more user(s).
 *
 * The server holds both the plaintext and the symmetric key; it
 * re-encrypts the data with a fresh IV on every request so the device can decrypt
 * it client-side. This "encrypt at serve time" pattern means each response looks
 * different even though the underlying data has not changed.
 *
 * Fields:
 *   data_uid          – base64-encoded 32-byte UID used as the lookup key.
 *                       Stored as a VARCHAR so it can be indexed efficiently.
 *   encoded_data_key  – Olvid Encoded AuthEncAES256ThenSHA256 symmetric key
 *                       Use Encoded::encodeSymmetricKey /
 *                       Encoded::decodeSymmetricKey to read/write this field.
 *                       Dictionary entries: "mackey" (32 bytes HMAC-SHA256 key)
 *                       and "enckey" (32 bytes AES-256 key).
 *   data              – raw plaintext blob supplied by the device at store time.
 */
class OlvidData extends Entity {
	/** @var string Base64-encoded 32-byte UID (see Constants::UID_SIZE). */
	protected string $dataUid = '';

	/**
	 * @var string Olvid-encoded AuthEncAES256ThenSHA256 key (binary, type 0x90).
	 *             Decoded via Encoded::decodeSymmetricKey() to obtain mackey/enckey.
	 */
	protected string $encodedDataKey = '';

	/** @var string Raw plaintext stored by the device via storeData. */
	protected string $data = '';

	public function __construct() {
		$this->addType('dataUid', Types::STRING);
		$this->addType('encodedDataKey', Types::BLOB);
		$this->addType('data', Types::BLOB);
	}

	public function getDataUid(): string {
		return $this->dataUid;
	}

	public function setDataUid(string $dataUid): void {
		$this->dataUid = $dataUid;
		$this->markFieldUpdated('dataUid');
	}

	public function getEncodedDataKey(): string {
		return $this->encodedDataKey;
	}

	public function setEncodedDataKey(string $encodedDataKey): void {
		$this->encodedDataKey = $encodedDataKey;
		$this->markFieldUpdated('encodedDataKey');
	}

	public function getData(): string {
		return $this->data;
	}

	public function setData(string $data): void {
		$this->data = $data;
		$this->markFieldUpdated('data');
	}
}
