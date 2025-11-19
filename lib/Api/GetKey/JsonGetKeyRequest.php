<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\GetKey;

use JsonSerializable;
use OCA\Olvid\Api\Constants;

class JsonGetKeyRequest implements JsonSerializable {
	public string $userId;

	public function __construct(array $data) {
		$this->userId = $data[Constants::GET_KEY_REQUEST_USER_ID];
	}

	public function jsonSerialize(): array {
		return [
			Constants::GET_KEY_REQUEST_USER_ID => $this->userId,
		];
	}
}
