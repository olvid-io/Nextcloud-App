<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\PutKey;

use OCA\Olvid\Api\Constants;

class JsonPutKeyRequest {
	public string $identity;

	public function __construct(array $data) {
		$this->identity = (string)$data[Constants::PUT_KEY_REQUEST_IDENTITY];
	}
}
