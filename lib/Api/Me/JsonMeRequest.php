<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Me;

use OCA\Olvid\Api\Constants;

class JsonMeRequest {
	public int $timestamp;
	public string $deviceUid;

	public function __construct(array $data) {
		$this->timestamp = (int)$data[Constants::ME_REQUEST_TIMESTAMP];
		$this->deviceUid = $data[Constants::ME_REQUEST_DEVICE_UID];
	}
}
