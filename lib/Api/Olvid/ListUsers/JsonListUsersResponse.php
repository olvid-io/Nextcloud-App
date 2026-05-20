<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\ListUsers;

use JsonSerializable;
use OCA\Olvid\Api\Constants;

class JsonListUsersResponse implements JsonSerializable {
	// array OlvidUsers
	public array $users;
	public int $timestamp;

	public function jsonSerialize(): array {
		return [
			Constants::LIST_USERS_RESPONSE_USERS => $this->users,
			Constants::LIST_USERS_RESPONSE_TIMESTAMP => $this->timestamp

		];
	}
}
