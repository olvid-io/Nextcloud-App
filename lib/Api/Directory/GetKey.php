<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

class GetKey extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): Response {
		// Parse request
		try {
			$otherNextcloudUserId = isset($jsonParameters[Constants::GET_KEY_REQUEST_USER_ID]) ? (string)$jsonParameters[Constants::GET_KEY_REQUEST_USER_ID] : null;
			if (!$otherNextcloudUserId) {
				return $this->invalidRequest();
			}
		} catch (Exception $e) {
			$this->logger->warning('getKey: parse error: ', ['exception' => $e]);
			return $this->invalidRequest();
		}

		// get user in database
		$otherOlvidUser = $this->context->db->user->getByUserIdOrNull($otherNextcloudUserId);
		if (!$otherOlvidUser?->getSignedDetails()) {
			return $this->invalidRequest();
		}

		// set user signed details in response
		$response[Constants::GET_KEY_RESPONSE_SIGNATURE] = $otherOlvidUser->getSignedDetails();

		return new JSONResponse($response);
	}
}
