<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\ListUsers;

use OCA\Olvid\Api\Constants;

class JsonListUsersRequest {
	public int $timestamp;

	public function __construct(array $data) {
		$this->timestamp = (int)($data[Constants::LIST_USERS_REQUEST_TIMESTAMP] ?? 0);
	}
}
