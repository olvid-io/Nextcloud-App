<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\GetKey;

use JsonSerializable;
use OCA\Olvid\Api\Constants;

class JsonGetKeyResponse implements JsonSerializable {
	public string $signature;

	public function jsonSerialize(): array {
		return [
			Constants::GET_KEY_RESPONSE_SIGNATURE => $this->signature,
		];
	}
}
