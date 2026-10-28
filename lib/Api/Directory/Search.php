<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\ResponseDefinitions;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;

/**
 * @template-extends AbstractAuthenticatedDeviceApiHandler<JSONResponse<Http::STATUS_OK, OlvidDirectorySearchResponse|OlvidDirectoryApiError, array{}>>
 * @psalm-import-type OlvidDirectorySearchResponse from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryApiError from ResponseDefinitions
 */
class Search extends AbstractAuthenticatedDeviceApiHandler {
	/**
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectorySearchResponse|OlvidDirectoryApiError, array{}>
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse {
		try {
			/** @var [String] $filter */
			$filters = isset($jsonParameters[Constants::SEARCH_REQUEST_FILTER]) ? (array)$jsonParameters[Constants::SEARCH_REQUEST_FILTER] : [];
		} catch (Exception $exception) {
			$this->logger->warning('search: parse error', ['exception' => $exception]);
			return $this->invalidRequest();
		}

		$response = [
			Constants::SEARCH_RESPONSE_RESULTS => [],
			Constants::SEARCH_RESPONSE_COUNT => 0,
			Constants::SEARCH_RESPONSE_RESULTS_UNACTIVATED_USERS => [],
			Constants::SEARCH_RESPONSE_COUNT_UNACTIVATED_USERS => 0,
		];

		try {
			// search for limit + 1 elements to have enough elements if we skip self element
			$searchedOlvidUsers = $this->context->db->user->searchEnabledUsers($filters, Constants::SEARCH_COUNT_LIMIT + 1);
			$countSkippedCount = 0;
			for ($i = 0; $i < min(count($searchedOlvidUsers), Constants::SEARCH_COUNT_LIMIT); ++$i) {
				$searchedOlvidUser = $searchedOlvidUsers[$i];
				// do not add yourself
				if ($searchedOlvidUser->getUserId() == $nextcloudUser->getUID()) {
					$countSkippedCount += 1;
					continue;
				}

				$response[Constants::SEARCH_RESPONSE_RESULTS][] = $searchedOlvidUser->computeJsonUserDetails();
			}

			$response[Constants::SEARCH_RESPONSE_COUNT] = count($searchedOlvidUsers) - $countSkippedCount;
			return new JSONResponse($response);
		} catch (\OCP\DB\Exception $exception) {
			$this->logger->error('search: database error', ['exception' => $exception]);
			return self::internalError();
		}
	}
}
