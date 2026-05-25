<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

class Search extends AbstractDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $user): Response {
		try {
			$filter = isset($jsonParameters[Constants::SEARCH_REQUEST_FILTER]) ? (string)$jsonParameters[Constants::SEARCH_REQUEST_FILTER] : null;
		} catch (Exception $e) {
			$this->logger->warning('search: parse error: ' . $e->getMessage());
			return $this->invalidRequest();
		}

		// TODO feature handle searchResultCount

		// TODO handle filters

		$response = [
			Constants::SEARCH_RESPONSE_RESULTS => [],
			Constants::SEARCH_RESPONSE_RESULTS_UNACTIVATED_USERS => 0,
			Constants::SEARCH_RESPONSE_COUNT => 0,
			Constants::SEARCH_RESPONSE_COUNT_UNACTIVATED_USERS => 0,
		];

		$users = $this->userManager->search("");
		foreach ($users as $user) {
			// only add users with a valid identity on server
			if ($this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY)) {
				$response[Constants::SEARCH_RESPONSE_RESULTS][] = OlvidUserDetails::parseSignedDetails($user, $this->config);
			}
		}

		return new JSONResponse($response);
    }
}
