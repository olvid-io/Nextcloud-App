<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils;

use Exception;

/**
 * Binary serialization format used by the Olvid Engine API.
 *
 * Wire format for each element: [type(1 byte)] [length(4 bytes big-endian)] [data(length bytes)]
 * Type 0x00 = byte array / string
 * Type 0x03 = list of encoded elements
 * Type 0x04 = dictionary (alternating encoded-key / encoded-value pairs)
 * Type 0x90 = symmetric key (packed list: [algoClass+algoImpl bytes][dictionary])
 *
 * All multi-byte integers are big-endian (network byte order).
 */
class Encoded {
	private const TYPE_BYTES = "\x00";
	private const TYPE_LIST = "\x03";
	private const TYPE_DICTIONARY = "\x04";
	private const TYPE_SYM_KEY = "\x90";

	public static function encodeBytes(string $bytes): string {
		return self::TYPE_BYTES . pack('N', strlen($bytes)) . $bytes;
	}

	/** Strings are UTF-8 bytes, encoded identically to byte arrays. */
	public static function encodeString(string $str): string {
		return self::encodeBytes($str);
	}

	/** @param string[] $encodedItems Already-encoded elements to pack into a list. */
	public static function encodeList(array $encodedItems): string {
		$body = implode('', $encodedItems);
		return self::TYPE_LIST . pack('N', strlen($body)) . $body;
	}

	/** Booleans: type 0x02, length 1, data 0x01 (true) or 0x00 (false). */
	public static function encodeBoolean(bool $value): string {
		return "\x02" . pack('N', 1) . ($value ? "\x01" : "\x00");
	}

	// endregion

	// region Decoding

	/**
	 * Decode a list element and return the raw encoded child elements.
	 *
	 * @return string[] Raw encoded element strings.
	 * @throws Exception On malformed data.
	 */
	public static function decodeList(string $data): array {
		if (strlen($data) < 5) {
			throw new Exception('Encoded data too short');
		}
		if ($data[0] !== self::TYPE_LIST) {
			throw new Exception('Expected list type');
		}
		['length' => $bodyLen] = unpack('Nlength', substr($data, 1, 4));
		if (strlen($data) !== 5 + $bodyLen) {
			throw new Exception('List length mismatch');
		}

		$items = [];
		$offset = 5;
		while ($offset < strlen($data)) {
			if ($offset + 5 > strlen($data)) {
				throw new Exception('Truncated element header');
			}
			['length' => $elemLen] = unpack('Nlength', substr($data, $offset + 1, 4));
			$total = 5 + $elemLen;
			if ($offset + $total > strlen($data)) {
				throw new Exception('Element overflows buffer');
			}
			$items[] = substr($data, $offset, $total);
			$offset += $total;
		}
		return $items;
	}

	/**
	 * Extract the raw payload from an encoded byte-array element.
	 *
	 * @throws Exception On type mismatch or malformed data.
	 */
	public static function decodeBytes(string $encoded): string {
		if (strlen($encoded) < 5) {
			throw new Exception('Encoded element too short');
		}
		if ($encoded[0] !== self::TYPE_BYTES) {
			throw new Exception('Expected bytes type');
		}
		['length' => $len] = unpack('Nlength', substr($encoded, 1, 4));
		if (strlen($encoded) !== 5 + $len) {
			throw new Exception('Bytes length mismatch');
		}
		return substr($encoded, 5);
	}

	/**
	 * Decode a UTF-8 string from an encoded byte-array element.
	 *
	 * @throws Exception On type mismatch or malformed data.
	 */
	public static function decodeString(string $encoded): string {
		return self::decodeBytes($encoded);
	}

	// region Dictionary / symmetric key

	/**
	 * Encode a dictionary. Each entry is a string key name mapped to raw bytes (the value
	 * will be wrapped in encodeBytes). Order is preserved from the array.
	 *
	 * Wire layout per entry: Encoded.of(key_name_utf8) || Encoded.of(value_bytes)
	 *
	 * @param array<string, string> $entries key name → raw bytes
	 */
	public static function encodeDictionary(array $entries): string {
		$body = '';
		foreach ($entries as $keyName => $valueBytes) {
			$body .= self::encodeBytes($keyName);
			$body .= self::encodeBytes($valueBytes);
		}
		return self::TYPE_DICTIONARY . pack('N', strlen($body)) . $body;
	}

	/**
	 * Decode a dictionary. Returns an associative array of key name → raw bytes.
	 *
	 * @return array<string, string>
	 * @throws Exception
	 */
	public static function decodeDictionary(string $encoded): array {
		if (strlen($encoded) < 5 || $encoded[0] !== self::TYPE_DICTIONARY) {
			throw new Exception('Expected dictionary type');
		}
		['length' => $bodyLen] = unpack('Nlength', substr($encoded, 1, 4));
		if (strlen($encoded) !== 5 + $bodyLen) {
			throw new Exception('Dictionary length mismatch');
		}

		$result = [];
		$offset = 5;
		$end = strlen($encoded);
		while ($offset < $end) {
			if ($offset + 5 > $end) {
				throw new Exception('Truncated dict key header');
			}
			['length' => $kLen] = unpack('Nlength', substr($encoded, $offset + 1, 4));
			if ($offset + 5 + $kLen > $end) {
				throw new Exception('Dict key overflows buffer');
			}
			$keyName = self::decodeBytes(substr($encoded, $offset, 5 + $kLen));
			$offset += 5 + $kLen;

			if ($offset + 5 > $end) {
				throw new Exception('Truncated dict value header');
			}
			['length' => $vLen] = unpack('Nlength', substr($encoded, $offset + 1, 4));
			if ($offset + 5 + $vLen > $end) {
				throw new Exception('Dict value overflows buffer');
			}
			$result[$keyName] = self::decodeBytes(substr($encoded, $offset, 5 + $vLen));
			$offset += 5 + $vLen;
		}
		return $result;
	}

	/**
	 * Encode an Olvid symmetric key (type 0x90).
	 *
	 * Layout: [0x90][4-byte len] [Encoded.of([algoClass, algoImpl])] [Encoded dict]
	 *
	 * @param int $algoClass e.g. 0x02 for authenticated symmetric encryption
	 * @param int $algoImpl e.g. 0x00 for AES-256-CTR + HMAC-SHA256
	 * @param array<string, string> $dict key name → raw bytes
	 */
	public static function encodeSymmetricKey(int $algoClass, int $algoImpl, array $dict): string {
		$keyType = self::encodeBytes(chr($algoClass) . chr($algoImpl));
		$encodedDict = self::encodeDictionary($dict);
		$payload = $keyType . $encodedDict;
		return self::TYPE_SYM_KEY . pack('N', strlen($payload)) . $payload;
	}

	/**
	 * Decode an Olvid symmetric key (type 0x90).
	 *
	 * @return array{algoClass: int, algoImpl: int, dict: array<string, string>}
	 * @throws Exception
	 */
	public static function decodeSymmetricKey(string $encoded): array {
		if (strlen($encoded) < 5 || $encoded[0] !== self::TYPE_SYM_KEY) {
			throw new Exception('Expected symmetric key type (0x90)');
		}
		['length' => $bodyLen] = unpack('Nlength', substr($encoded, 1, 4));
		if (strlen($encoded) !== 5 + $bodyLen) {
			throw new Exception('Symmetric key length mismatch');
		}

		// Unpack exactly two child items: keyType then dict
		$items = [];
		$offset = 5;
		$end = strlen($encoded);
		while ($offset < $end) {
			if ($offset + 5 > $end) {
				throw new Exception('Truncated child item header');
			}
			['length' => $itemLen] = unpack('Nlength', substr($encoded, $offset + 1, 4));
			if ($offset + 5 + $itemLen > $end) {
				throw new Exception('Child item overflows buffer');
			}
			$items[] = substr($encoded, $offset, 5 + $itemLen);
			$offset += 5 + $itemLen;
		}
		if (count($items) !== 2) {
			throw new Exception('Symmetric key must have exactly 2 child items');
		}

		$algoBytes = self::decodeBytes($items[0]);
		if (strlen($algoBytes) !== 2) {
			throw new Exception('Invalid algo bytes length');
		}

		return [
			'algoClass' => ord($algoBytes[0]),
			'algoImpl' => ord($algoBytes[1]),
			'dict' => self::decodeDictionary($items[1]),
		];
	}

	// endregion
}
