<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\ResponseDefinitions;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;

/**
 * @template-extends AbstractAuthenticatedDeviceApiHandler<JSONResponse<Http::STATUS_OK, OlvidDirectoryGetKeyResponse|OlvidDirectoryApiError, array{}>>
 * @psalm-import-type OlvidDirectoryGetKeyResponse from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryApiError from ResponseDefinitions
 */
class GetKey extends AbstractAuthenticatedDeviceApiHandler {
	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryGetKeyResponse|OlvidDirectoryApiError, array{}>
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse {
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
		$signedDetails = $otherOlvidUser?->getSignedDetails();
		if (!$signedDetails) {
			return $this->invalidRequest();
		}

		// set user signed details in response
		return new JSONResponse([
			Constants::GET_KEY_RESPONSE_SIGNATURE => $signedDetails,
		]);
	}
}
