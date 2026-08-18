<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;

class Search extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse {
		try {
			/** @var [String] $filter */
			$filters = isset($jsonParameters[Constants::SEARCH_REQUEST_FILTER]) ? (array)$jsonParameters[Constants::SEARCH_REQUEST_FILTER] : null;
		} catch (Exception $e) {
			$this->logger->warning('search: parse error', ['exception' => $e]);
			return $this->invalidRequest();
		}

		$response = [
			Constants::SEARCH_RESPONSE_RESULTS => [],
			Constants::SEARCH_RESPONSE_COUNT => 0,
			Constants::SEARCH_RESPONSE_RESULTS_UNACTIVATED_USERS => [],
			Constants::SEARCH_RESPONSE_COUNT_UNACTIVATED_USERS => 0,
		];

		try {
			$searchedOlvidUsers = $this->context->db->user->searchEnabledUsers($filters, $this->logger);
			for ($i = 0; $i < min(count($searchedOlvidUsers), Constants::SEARCH_COUNT_LIMIT); $i++) {
				$searchedOlvidUser = $searchedOlvidUsers[$i];
				// do not add yourself
				if ($searchedOlvidUser->getUserId() == $nextcloudUser->getUID()) {
					continue;
				}
				$response[Constants::SEARCH_RESPONSE_RESULTS][] = $searchedOlvidUser->computeJsonUserDetails();
			}
			$response[Constants::SEARCH_RESPONSE_COUNT] = count($searchedOlvidUsers);
			return new JSONResponse($response);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('search: database error', ['exception' => $e]);
			return self::internalError();
		}
	}
}
