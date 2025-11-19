<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\GetKey;

use OCA\Olvid\Api\ApiHandler;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;

class GetKey extends ApiHandler {
	public function handler(?IUser $user, IRequest $request, array $jsonParameters): Response {
		// parse request
		$getKeyRequest = new JsonGetKeyRequest($jsonParameters);
		if (!$getKeyRequest->userId) {
			return $this->invalidRequestDevice();
		}

		// get user is database
		$otherUser = $this->userManager->get($getKeyRequest->userId);
		if (!$otherUser) {
			return $this->invalidRequestDevice();
		}

		$response = new JsonGetKeyResponse();

		$response->signature = $this->config->getUserValue($otherUser->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS);

		return new JSONResponse($response);
    }
}
