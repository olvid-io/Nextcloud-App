<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\PutKey;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\ApiHandler;
use OCA\Olvid\Api\Olvid\PutKey\JsonPutKeyRequest;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCA\Olvid\Utils\OlvidServer\OlvidServerUtils;
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

		// set user identity
		$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY, $putKeyRequest->identity);
		OlvidUserDetails::signUserDetails($user, $this->config, $this->appConfig);

		// revoke previous api key if any
		$previousApiKey = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY);
		if ($previousApiKey) {
			try {
				OlvidServerUtils::revokeApiKey($this->appConfig, $previousApiKey);
			} catch (Exception $e) {
				$this->logger->error("/putKey: cannot revoke previous api key: " . $e->getMessage());
			}
		}

		// create and set new api key
		// this might fail if an olvid server api have not been set
		try {
			$newApiKey = OlvidServerUtils::requestNewApiKey($this->appConfig);
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY, $newApiKey);
		} catch (Exception $e) {
			$this->logger->warning("/putKey: cannot create new api key: " . $e->getMessage());
		}

		return $this->success();
    }
}
