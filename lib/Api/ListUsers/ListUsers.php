<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\ListUsers;

use OCA\Olvid\Api\ApiHandler;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;

class ListUsers extends ApiHandler {
	public function handler(?IUser $user, IRequest $request, array $jsonParameters): Response {
		$listUsersRequest = new JsonListUsersRequest($jsonParameters);

		// TODO handle request

		$response = new JsonListUsersResponse();
		$users = $this->userManager->search("");
		foreach ($users as $user) {
			// only add users with a valid identity on server
			if ($this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY)) {
				$response->users[] = OlvidUserDetails::getCurrentUserDetails($user, $this->config);
			}
		}
		$response->timestamp = time();

		return new JSONResponse($response);
    }
}
