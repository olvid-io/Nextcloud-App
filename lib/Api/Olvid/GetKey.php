<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;

class GetKey extends ApiHandler {
	public function handler(?IUser $user, IRequest $request, array $jsonParameters): Response {
		// Parse request
		try {
			$userId = isset($jsonParameters[Constants::GET_KEY_REQUEST_USER_ID]) ? (string)$jsonParameters[Constants::GET_KEY_REQUEST_USER_ID] : null;
			if (!$userId) {
				return $this->invalidRequestDevice();
			}
		} catch (Exception $e) {
			$this->logger->warning('GetKey: parse error: ' . $e->getMessage());
			return $this->invalidRequestDevice();
		}

		// get user in database
		$otherUser = $this->userManager->get($userId);
		if (!$otherUser) {
			return $this->invalidRequestDevice();
		}

		$response[Constants::GET_KEY_RESPONSE_SIGNATURE] = $this->config->getUserValue($otherUser->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS);
		return new JSONResponse($response);
    }
}
