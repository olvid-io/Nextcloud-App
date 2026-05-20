<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\Search;

use JsonSerializable;
use OCA\Olvid\Api\Constants;

class JsonSearchResponse implements JsonSerializable {
	// array<OlvidUserDetails>
	public array $results = [];
	// array<OlvidUserDetails>
	public array $resultsUnactivatedUsers = [];
	public int $count = 0;
	public int $countUnactivatedUsers = 0;

	public function jsonSerialize(): array {
		return [
			Constants::SEARCH_RESPONSE_RESULTS => $this->results,
			Constants::SEARCH_RESPONSE_RESULTS_UNACTIVATED_USERS => $this->resultsUnactivatedUsers,
			Constants::SEARCH_RESPONSE_COUNT => $this->count,
			Constants::SEARCH_RESPONSE_COUNT_UNACTIVATED_USERS => $this->countUnactivatedUsers,
		];
	}
}
