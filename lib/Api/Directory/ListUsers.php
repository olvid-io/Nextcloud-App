<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;

class ListUsers extends AbstractAuthenticatedDeviceApiHandler {
	/**
	 * @throws \OCP\DB\Exception
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse {
		try {
			$timestamp = (int)($jsonParameters[Constants::LIST_USERS_REQUEST_TIMESTAMP] ?? 0);
		} catch (Exception $e) {
			$this->logger->warning('listUsers: parse error: ', ['exception' => $e]);
			return $this->invalidRequest();
		}

		$response = [
			Constants::LIST_USERS_RESPONSE_USERS => [],
		];
		$olvidUsers = $this->context->db->user->listRegisteredUsersSince($timestamp);
		foreach ($olvidUsers as $olvidUser) {
			// only add users with a valid identity on server
			if ($olvidUser->hasIdentity()) {
				$response[Constants::LIST_USERS_RESPONSE_USERS][] = $olvidUser->computeJsonUserDetails();
			}
		}

		// current timestamp in milliseconds
		$response[Constants::LIST_USERS_RESPONSE_TIMESTAMP] = TimeUtil::currentTimeMillis();

		return new JSONResponse($response);
	}
}
