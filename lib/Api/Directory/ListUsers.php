<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

class ListUsers extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $user): Response {
		try {
			$timestamp = (int)($jsonParameters[Constants::LIST_USERS_REQUEST_TIMESTAMP] ?? 0);
		} catch (Exception $e) {
			$this->logger->warning('listUsers: parse error: ', ['exception' => $e]);
			return $this->invalidRequest();
		}

		// TODO: handle request: add a user attribute with the registration timestamp
		// then we can filter to return only users that registered since $listUsersRequests->timestamp

		$response = [
			Constants::LIST_USERS_RESPONSE_USERS => [],
		];
		$users = $this->userManager->search('');
		foreach ($users as $user) {
			// only add users with a valid identity on server
			if ($this->olvidUserConfig->hasIdentity($user->getUID())) {
				$response[Constants::LIST_USERS_RESPONSE_USERS][] = JsonUserDetails::parseSignedDetails($user, $this->olvidUserConfig);
			}
		}

		// current timestamp in milliseconds
		$response[Constants::LIST_USERS_RESPONSE_TIMESTAMP] = TimeUtil::currentTimeMillis();

		return new JSONResponse($response);
	}
}
