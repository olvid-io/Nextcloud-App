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

class ListUsers extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $user): Response {
		try {
			$timestamp = (int)($jsonParameters[Constants::LIST_USERS_REQUEST_TIMESTAMP] ?? 0);
		} catch (Exception $e) {
			$this->logger->warning('listUsers: parse error: ' . $e->getMessage());
			return $this->invalidRequest();
		}

		// TODO: handle request: add a user attribute with the registration timestamp
		// then we can filter to return only users that registered since $listUsersRequests->timestamp

		$response = [
			Constants::LIST_USERS_RESPONSE_USERS => [],
		];
		$users = $this->userManager->search("");
		foreach ($users as $user) {
			// only add users with a valid identity on server
			if ($this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY)) {
				$response[Constants::LIST_USERS_RESPONSE_USERS][] = OlvidUserDetails::parseSignedDetails($user, $this->config);
			}
		}

		// current timestamp in milliseconds
		$response[Constants::LIST_USERS_RESPONSE_TIMESTAMP] = (int)(microtime(true) * 1000);

		return new JSONResponse($response);
    }
}
