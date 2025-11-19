<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\PutKey;

use OCA\Olvid\Api\ApiHandler;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;

use OCP\IUser;
use OCP\PreConditionNotMetException;

class PutKey extends ApiHandler {
	/**
	 * @throws PreConditionNotMetException
	 */
	public function handler(?IUser $user, IRequest $request, array $jsonParameters): Response {
		$putKeyRequest = new JsonPutKeyRequest($jsonParameters);

		if (!$putKeyRequest->identity) {
			return $this->invalidRequestDevice();
		}

		$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY, $putKeyRequest->identity);
		OlvidUserDetails::signUserDetails($user, $this->config, $this->appConfig);

		return $this->success();
    }
}
