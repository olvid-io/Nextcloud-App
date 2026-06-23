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
}
