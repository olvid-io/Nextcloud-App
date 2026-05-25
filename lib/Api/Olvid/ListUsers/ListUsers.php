<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\ListUsers;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\OlvidAppHandler;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;

class ListUsers extends OlvidAppHandler {
	public function handler(IUser $user, array $jsonParameters): Response {
		$listUsersRequest = new JsonListUsersRequest($jsonParameters);

		// TODO: handle request: add a user attribute with the registration timestamp
		// then we can filter to return only users that registered since $listUsersRequests->timestamp

		$response = new JsonListUsersResponse();
		$users = $this->userManager->search("");
		foreach ($users as $user) {
			// only add users with a valid identity on server
			if ($this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY)) {
				$response->users[] = OlvidUserDetails::parseSignedDetails($user, $this->config);
			}
		}
		// current timestamp in milliseconds
		$response->timestamp = (int)(microtime(true) * 1000);

		return new JSONResponse($response);
    }
}
