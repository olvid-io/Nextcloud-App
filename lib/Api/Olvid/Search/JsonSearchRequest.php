<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\Search;

use OCA\Olvid\Api\Constants;

class JsonSearchRequest {
	// string[]
	public ?array $filter;

	public function __construct(array $data) {
		$this->filter = isset($data[Constants::SEARCH_REQUEST_FILTER]) ? (string)$data[Constants::SEARCH_REQUEST_FILTER] : null;
	}
}
