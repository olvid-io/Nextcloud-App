<?php

namespace OCA\Olvid\Utils;

class TimeUtil {
	// use timestamp in s for signed JWT (used internally)
	public static function currentTimeS(): int {
		return time();
	}

	// use timestamp in ms for Olvid ecosystem
	public static function currentTimeMillis(): int {
		return (int)(microtime(true) * 1000);
	}
}
