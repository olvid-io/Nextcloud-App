<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

class ListUsers extends AbstractAuthenticatedDeviceApiHandler {
	/**
	 * @throws \OCP\DB\Exception
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): Response {
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
		$olvidUsers = $this->context->db->user->getAll();
		foreach ($olvidUsers as $olvidUser) {
			// only add users with a valid identity on server
			if ($olvidUser->hasIdentity()) {
				$response[Constants::LIST_USERS_RESPONSE_USERS][] = $olvidUser->computeJsonUserDetails($nextcloudUser->getDisplayName());
			}
		}

		// current timestamp in milliseconds
		$response[Constants::LIST_USERS_RESPONSE_TIMESTAMP] = TimeUtil::currentTimeMillis();

		return new JSONResponse($response);
	}
}
