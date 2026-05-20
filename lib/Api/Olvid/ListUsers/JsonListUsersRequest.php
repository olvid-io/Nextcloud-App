<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\ListUsers;

use OCA\Olvid\Api\Constants;

class JsonListUsersRequest {
	public ?string $identity;

	public function __construct(array $data) {
		$this->identity = $data[Constants::PUT_KEY_REQUEST_IDENTITY];
	}
}
