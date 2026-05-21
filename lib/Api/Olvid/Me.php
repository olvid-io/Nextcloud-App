<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCA\Olvid\Utils\AppConfigManager;
use OCA\Olvid\Utils\OlvidServer\OlvidServerUtils;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;

class Me extends OlvidAppHandler {
	public function handler(IUser $user, array $jsonParameters): Response {
		// Parse request
		try {
			$deviceUid = isset($jsonParameters[Constants::ME_REQUEST_DEVICE_UID]) ? (string)$jsonParameters[Constants::ME_REQUEST_DEVICE_UID] : null;
			$timestamp = (int)($jsonParameters[Constants::ME_REQUEST_TIMESTAMP] ?? 0);
		} catch (Exception $e) {
			$this->logger->warning('Me: parse error: ' . $e->getMessage());
			return $this->invalidRequestDevice();
		}

		// check request content
		if (!$user) {
			return $this->invalidRequestDevice();
		}

		// sign details if necessary and set in response
		$signature = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS);
		if (!$signature) {
			$signature = OlvidUserDetails::signUserDetails($user, $this->config, $this->appConfig);
		}
		$response[Constants::ME_RESPONSE_SIGNATURE] = $signature;

		// get current api key, create a new one if there is an identity and no associated api key (fallback)
		$apiKey = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY);
		$identity = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY);
		if ($identity && !$apiKey) {
			// this might fail if an olvid server api have not been set
			try {
				$newApiKey = OlvidServerUtils::requestNewApiKey($this->appConfig);
				$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY, $newApiKey);
			} catch (Exception $e) {
				$this->logger->error("Me: cannot create user api key: " . $e);
			}
		}
		$response[Constants::ME_RESPONSE_API_KEY] = $apiKey;

		$response[Constants::ME_RESPONSE_SERVER] = AppConfigManager::getOlvidServerUrl($this->appConfig) ?? "";
		$response[Constants::ME_RESPONSE_REVOCATION_ALLOWED] = true;
		$response[Constants::ME_RESPONSE_TRANSFER_RESTRICTED] = false;

		// TODO is this really necessary ?
		$response[Constants::ME_RESPONSE_MINIMUM_BUILD_VERSIONS] = [
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_ANDROID_LABEL => 0,
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_IOS_LABEL => 0,
		];

		$response[Constants::ME_RESPONSE_PUSH_TOPICS] = [];

		$response[Constants::ME_RESPONSE_NONCE] = uuid_create();

		$response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] = [];
		$response[Constants::ME_RESPONSE_CURRENT_TIMESTAMP] = time();

        return new JSONResponse($response, 200);
    }
}
