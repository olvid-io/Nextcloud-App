<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

class Me extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): Response {
		// parse request (don't fail on parse error)
		try {
			$deviceUid = isset($jsonParameters[Constants::ME_REQUEST_DEVICE_UID]) ? (string)$jsonParameters[Constants::ME_REQUEST_DEVICE_UID] : null;
			$timestamp = (int)($jsonParameters[Constants::ME_REQUEST_TIMESTAMP] ?? 0);
		} catch (Exception $e) {
			$this->logger->warning('me: parse error: ', ['exception' => $e]);
		}

		$currentTimestamp = TimeUtil::currentTimeMillis();

		// compute user details
		$userDetails = JsonUserDetails::computeDetails($nextcloudUser, $this->olvidUserConfig);

		// set or update full search string attributes
		$userDetails->updateFullSearchString($nextcloudUser->getUID(), $this->olvidUserConfig);

		// set signature (compute if necessary)
		$signature = $this->olvidUserConfig->getSignedDetails($nextcloudUser->getUID());
		if (!$signature) {
			$signature = $userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);
		}
		$response[Constants::ME_RESPONSE_SIGNATURE] = $signature;

		// get current api  key, create a new one if there is an identity and no associated api key (fallback)
		$apiKey = $this->olvidUserConfig->getApiKey($nextcloudUser->getUID());
		$identity = $this->olvidUserConfig->getB64Identity($nextcloudUser->getUID());
		if ($identity && !$apiKey) {
			// this might fail if an olvid server api have not been set
			try {
				$apiKey = $this->olvidServer->requestNewApiKey();
				$this->olvidUserConfig->setApiKey($nextcloudUser->getUID(), $apiKey);
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
				$this->logger->error('Me: cannot create global push topic: ', ['exception' => $e]);
			}
		}
		$response[Constants::ME_RESPONSE_PUSH_TOPICS] = $globalPushTopic ? [$globalPushTopic] : null;

		// on first /me call create a nonce that will be used to ask server if user is still in server database if user had been logged out
		if ($userDetails->identity) {
			$nonce = $this->olvidUserConfig->getNonce($nextcloudUser->getUID());
			if (!$nonce) {
				$nonce = RandomUtil::uuid_create();
				$this->olvidUserConfig->setNonce($nextcloudUser->getUID(), $nonce);
			}
			$response[Constants::ME_RESPONSE_NONCE] = $nonce;
		}

		// list every revocation since timestamp passed in request
		$signedRevocations = $this->db->revocation->findSignedRevocationsSinceTimestampOrNull($timestamp);

		$response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] = array_map(function ($revocation) { return $revocation->getSignature(); }, $signedRevocations ?? []);
		$response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] = count($response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS]) !== 0 ? $response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] : null;

		$response[Constants::ME_RESPONSE_CURRENT_TIMESTAMP] = $currentTimestamp;

		return new JSONResponse($response, Http::STATUS_OK);
	}
}
