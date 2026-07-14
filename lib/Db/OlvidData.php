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
 *  bytes_data_uid: base64-encoded 32-byte UID used as the lookup key.
 *  bytes_encoded_data_key: Olvid Encoded AuthEncAES256ThenSHA256 symmetric key
 *    Use Encoded::encodeSymmetricKey /
 *    Encoded::decodeSymmetricKey to read/write this field.
 *    Dictionary entries: "mackey" (32 bytes HMAC-SHA256 key)
 *    and "enckey" (32 bytes AES-256 key).
 *  bytes_data: raw plaintext blob supplied by the device at store time.
 *
 * @method string getBytesDataUid()
 * @method void setBytesDataUid(string $dataUid)
 * @method string getBytesEncodedKey()
 * @method void setBytesEncodedKey(string $identity)
 * @method string getBytesData()
 * @method void setBytesData(string $bytesData)
 */
class OlvidData extends Entity {
	/** @var string */
	protected string $bytesDataUid = '';

	/** @var string Olvid-encoded AuthEncAES256ThenSHA256 key */
	protected string $bytesEncodedKey = '';

	/** @var string */
	protected string $bytesData = '';

	public function __construct() {
		$this->addType('bytesDataUid', Types::BLOB);
		$this->addType('bytesEncodedKey', Types::BLOB);
		$this->addType('bytesData', Types::BLOB);
	}

	public function jsonSerialize(): array {
		return [
			'bytesDataUid' => base64_encode($this->bytesDataUid),
			'bytesEncodedDataKey' => base64_encode($this->bytesEncodedKey),
			'bytesData' => base64_encode($this->bytesData)
		];
	}

	public function __toString(): string {
		return 'OlvidData{'
			. 'id=' . $this->getId()
			. ', bytesDataUid=' . base64_encode($this->bytesDataUid)
			. ', bytesEncodedDataKey=' . base64_encode($this->bytesEncodedKey)
			. ', bytesData=' . base64_encode($this->bytesData)
			. '}';
	}
}
