<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Me;

use Exception;
use OCA\Olvid\Api\ApiHandler;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCA\Olvid\Utils\AppConfigManager;
use OCA\Olvid\Utils\OlvidServer\OlvidServerUtils;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IUser;
use OCP\PreConditionNotMetException;

class Me extends ApiHandler {
	/**
	 * @throws PreConditionNotMetException
	 */
	public function handler(?IUser $user, IRequest $request, array $jsonParameters): Response {
		$meRequest = new JsonMeRequest($jsonParameters);

		$response = new JsonMeResponse();

		if (!$user) {
			return $this->invalidRequestDevice();
		}

		$signature = $this->config->getUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS);
		if (!$signature) {
			$signature = OlvidUserDetails::signUserDetails($user, $this->config, $this->appConfig);
		}
		$response->signature = $signature;

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
		$response->apiKey = $apiKey;

		$response->server = AppConfigManager::getOlvidServerUrl($this->appConfig) ?? "";
		$response->revocationAllowed = true;
		$response->transferRestricted = false;

		// TODO
		$response->minimumBuildVersions = [
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_ANDROID_LABEL => 0,
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_IOS_LABEL => 0,
		];

		$response->pushTopicNames = [];

		$response->nonce = uuid_create();

		$response->signedRevocations = [];
		$response->currentTimestamp = time();

        return new JSONResponse($response, 200);
    }
}
