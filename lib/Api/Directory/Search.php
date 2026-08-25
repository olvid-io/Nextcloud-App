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
			$searchedOlvidUsers = $this->context->db->user->searchEnabledUsers($filters);

			// federated hits are merged in transparently: the directory intentionally does
			// not let the client distinguish a local result from a trusted-server one
			$federatedServers = $this->context->db->federatedServer->getEnabledOlvidCapable();
			$federatedServersById = [];
			foreach ($federatedServers as $federatedServer) {
				$federatedServersById[$federatedServer->getId()] = $federatedServer;
			}
			$searchedFederatedUsers = $this->context->db->federatedUser->searchByServers(array_keys($federatedServersById), $filters);

			$count = 0;
			foreach ($searchedOlvidUsers as $searchedOlvidUser) {
				// do not add yourself
				if ($searchedOlvidUser->getUserId() == $nextcloudUser->getUID()) {
					continue;
				}
				if ($count >= Constants::SEARCH_COUNT_LIMIT) {
					break;
				}
				$response[Constants::SEARCH_RESPONSE_RESULTS][] = $searchedOlvidUser->computeJsonUserDetails();
				$count++;
			}
			foreach ($searchedFederatedUsers as $searchedFederatedUser) {
				if ($count >= Constants::SEARCH_COUNT_LIMIT) {
					break;
				}
				$federatedServer = $federatedServersById[$searchedFederatedUser->getServerId()] ?? null;
				if ($federatedServer === null) {
					continue;
				}
				$response[Constants::SEARCH_RESPONSE_RESULTS][] = $searchedFederatedUser->computeJsonUserDetails($federatedServer->getDomain());
				$count++;
			}
			$response[Constants::SEARCH_RESPONSE_COUNT] = count($searchedOlvidUsers) + count($searchedFederatedUsers);
			return new JSONResponse($response);
		} catch (\OCP\DB\Exception $e) {
			$this->logger->error('search: database error', ['exception' => $e]);
			return self::internalError();
		}
	}
}
