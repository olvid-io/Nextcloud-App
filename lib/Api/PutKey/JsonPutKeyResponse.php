<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\PutKey;

use JsonSerializable;

class JsonPutKeyResponse implements JsonSerializable{
	public function jsonSerialize(): array {
		return [];
	}
}
