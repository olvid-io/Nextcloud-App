<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCA\Olvid\Utils\AppConfigManager;
use OCA\Olvid\Utils\OlvidServer\OlvidServerUtils;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

class Me extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $user): Response {
		// parse request (don't fail on parse error)
		try {
			$deviceUid = isset($jsonParameters[Constants::ME_REQUEST_DEVICE_UID]) ? (string)$jsonParameters[Constants::ME_REQUEST_DEVICE_UID] : null;
			$timestamp = (int)($jsonParameters[Constants::ME_REQUEST_TIMESTAMP] ?? 0);
		} catch (Exception $e) {
			$this->logger->warning('me: parse error: ' . $e->getMessage());
		}

		// TODO feature revocation

		// compute user details
		$userDetails = OlvidUserDetails::computeDetails($user, $this->config);

		// set or update full search string attributes
		$userDetails->updateFullSearchString($user->getUID(), $this->config);

		// set signature (compute if necessary)
		$signature = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS);
		if (!$signature) {
			$signature = $userDetails->sign($this->config, $this->appConfig);
		}
		$response[Constants::ME_RESPONSE_SIGNATURE] = $signature;

		// get current api  key, create a new one if there is an identity and no associated api key (fallback)
		$apiKey = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY);
		$identity = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY);
		if ($identity && !$apiKey) {
			// this might fail if an olvid server api have not been set
			try {
				$apiKey = OlvidServerUtils::requestNewApiKey($this->appConfig);
				$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY, $apiKey);
			} catch (Exception $e) {
				$this->logger->error("Me: cannot create user api key: " . $e);
			}
		}
		$response[Constants::ME_RESPONSE_API_KEY] = $apiKey;

		$response[Constants::ME_RESPONSE_SERVER] = AppConfigManager::getOlvidServerUrl($this->appConfig) ?? "";
		$response[Constants::ME_RESPONSE_REVOCATION_ALLOWED] = true;
		$response[Constants::ME_RESPONSE_TRANSFER_RESTRICTED] = false;

		// TODO deprecated, delete it when possible
		$response[Constants::ME_RESPONSE_MINIMUM_BUILD_VERSIONS] = [
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_ANDROID_LABEL => Constants::MIN_BUILD_ANDROID,
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_IOS_LABEL => Constants::MIN_BUILD_IOS,
		];

		$globalPushTopic = AppConfigManager::getGlobalPushTopic($this->appConfig);
		// global push topic not set try to request one
		if (!$globalPushTopic) {
			// this might fail if an olvid server api have not been set
			try {
				$globalPushTopic = OlvidServerUtils::requestNewPushTopic($this->appConfig);
				AppConfigManager::setGlobalPushTopic($this->appConfig, $globalPushTopic);
			} catch (Exception $e) {
				$this->logger->error("Me: cannot create global push topic: " . $e);
			}
		}
		$response[Constants::ME_RESPONSE_PUSH_TOPICS] = $globalPushTopic ? [$globalPushTopic] : [];

		// on first /me call create a nonce that will be used to ask server if user is still in server database if user had been logged out
		if ($userDetails->identity) {
			$nonce = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_NONCE);
			if (!$nonce) {
				$nonce = uuid_create();
				$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_NONCE, $nonce);
			}
			$response[Constants::ME_RESPONSE_NONCE] = $nonce;
		}

		// TODO feature revocations
		$response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] = [];

		$response[Constants::ME_RESPONSE_CURRENT_TIMESTAMP] = time();

        return new JSONResponse($response, 200);
    }
}
