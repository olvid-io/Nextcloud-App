<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
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
		$userDetails = JsonUserDetails::computeDetails($user, $this->olvidUserConfig);

		// set or update full search string attributes
		$userDetails->updateFullSearchString($user->getUID(), $this->olvidUserConfig);

		// set signature (compute if necessary)
		$signature = $this->olvidUserConfig->getSignedDetails($user->getUID());
		if (!$signature) {
			$signature = $userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);
		}
		$response[Constants::ME_RESPONSE_SIGNATURE] = $signature;

		// get current api  key, create a new one if there is an identity and no associated api key (fallback)
		$apiKey = $this->olvidUserConfig->getApiKey($user->getUID());
		$identity = $this->olvidUserConfig->getIdentity($user->getUID());
		if ($identity && !$apiKey) {
			// this might fail if an olvid server api have not been set
			try {
				$apiKey = $this->olvidServer->requestNewApiKey();
				$this->olvidUserConfig->setApiKey($user->getUID(), $apiKey);
			} catch (Exception $e) {
				$this->logger->error('Me: cannot create user api key: ' . $e);
			}
		}
		$response[Constants::ME_RESPONSE_API_KEY] = $apiKey;

		$response[Constants::ME_RESPONSE_SERVER] = $this->olvidAppConfig->getOlvidServerUrl() ?? '';
		$response[Constants::ME_RESPONSE_REVOCATION_ALLOWED] = true;
		$response[Constants::ME_RESPONSE_TRANSFER_RESTRICTED] = false;

		// TODO deprecated, delete it when possible
		$response[Constants::ME_RESPONSE_MINIMUM_BUILD_VERSIONS] = [
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_ANDROID_LABEL => Constants::MIN_BUILD_ANDROID,
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_IOS_LABEL => Constants::MIN_BUILD_IOS,
		];

		$globalPushTopic = $this->olvidAppConfig->getGlobalPushTopic();
		// global push topic not set try to request one
		if (!$globalPushTopic) {
			// this might fail if an olvid server api have not been set
			try {
				$globalPushTopic = $this->olvidServer->requestNewPushTopic();
				$this->olvidAppConfig->setGlobalPushTopic($globalPushTopic);
			} catch (Exception $e) {
				$this->logger->error('Me: cannot create global push topic: ' . $e->getMessage());
			}
		}
		$response[Constants::ME_RESPONSE_PUSH_TOPICS] = $globalPushTopic ? [$globalPushTopic] : [];

		// on first /me call create a nonce that will be used to ask server if user is still in server database if user had been logged out
		if ($userDetails->identity) {
			$nonce = $this->olvidUserConfig->getNonce($user->getUID());
			if (!$nonce) {
				$nonce = RandomUtil::uuid_create();
				$this->olvidUserConfig->setNonce($user->getUID(), $nonce);
			}
			$response[Constants::ME_RESPONSE_NONCE] = $nonce;
		}

		// TODO feature revocations
		$response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] = [];

		$response[Constants::ME_RESPONSE_CURRENT_TIMESTAMP] = TimeUtil::currentTimeMillis();

		return new JSONResponse($response, 200);
	}
}
