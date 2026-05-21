<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\Search;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\OlvidAppHandler;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;

class Search extends OlvidAppHandler {
	public function handler(IUser $user, array $jsonParameters): Response {
		// TODO handle request
		$searchRequest = new JsonSearchRequest($jsonParameters);

		// TODO handle request

		// TODO filter

		$response = new JsonSearchResponse();
		$users = $this->userManager->search("");
		foreach ($users as $user) {
			// only add users with a valid identity on server
			if ($this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY)) {
				$response->results[] = OlvidUserDetails::getCurrentUserDetails($user, $this->config);
			}
		}

		return new JSONResponse($response);
    }
}
