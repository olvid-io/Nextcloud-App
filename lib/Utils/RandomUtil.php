<?php

namespace OCA\Olvid\Utils;

use Exception;

class RandomUtil {
	public static function uuid_create(): string {
		// TODO change to 32u
		$bytes = RandomUtil::random_bytes(16);
		$bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40); // version 4
		$bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80); // variant RFC 4122
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
	}

	public static function random_bytes(int $size): ?string {
		try {
			return random_bytes($size);
		} catch (Exception $e) {
			return null;
		}
	}
}
