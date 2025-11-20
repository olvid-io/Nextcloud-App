<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Me;

use OCA\Olvid\Api\ApiHandler;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCA\Olvid\Utils\AppConfigManager;
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

		$response->apiKey = "";

		$response->server = AppConfigManager::getOlvidServerUrl($this->appConfig) ?? "";
		$response->revocationAllowed = true;
		$response->transferRestricted = false;

		// TODO
		$response->minimumBuildVersions = [
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_ANDROID_LABEL => 0,
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_IOS_LABEL => 0,
		];

		$response->pushTopicNames = [];

		$response->nonce = "nonce";

		$response->signedRevocations = [];
		$response->currentTimestamp = time();

        return new JSONResponse($response, 200);
    }
}
