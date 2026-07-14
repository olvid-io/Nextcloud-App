<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IUser;

class Me extends AbstractAuthenticatedDeviceApiHandler {
	/**
	 * @throws \OCP\DB\Exception
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): Response {
		// parse request (don't fail on parse error)
		try {
			// $deviceUid = isset($jsonParameters[Constants::ME_REQUEST_DEVICE_UID]) ? (string)$jsonParameters[Constants::ME_REQUEST_DEVICE_UID] : null;
			$timestamp = (int)($jsonParameters[Constants::ME_REQUEST_TIMESTAMP] ?? 0);
		} catch (Exception $e) {
			$this->logger->warning('me: parse error: ', ['exception' => $e]);
		}

		$currentTimestamp = TimeUtil::currentTimeMillis();

		// get or create the base OlvidUser
		$olvidUser = $this->context->db->user->getOrCreate($nextcloudUser->getUID());

		// compute user details
		$jsonUserDetails = $olvidUser->computeJsonUserDetails($nextcloudUser->getDisplayName());

		// set or update full search string attributes
		$olvidUser->setFullSearchField($jsonUserDetails->computeFullSearchString());

		// get signed user details (or sign user details if necessary)
		if ($olvidUser->getSignedDetails() === null) {
			$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));
		}
		$response[Constants::ME_RESPONSE_SIGNATURE] = $olvidUser->getSignedDetails();

		// if identity was already uploaded create an api key if necessary
		if ($olvidUser->hasIdentity() && $olvidUser->getApiKey() === null) {
			// this might fail if an olvid server api have not been set
			try {
				$apiKey = $this->context->olvidServer->requestNewApiKey();
				$olvidUser->setApiKey($apiKey);
			} catch (InvalidConfigurationException) {
				$this->logger->error('Me: cannot create user api key: invalid configuration');
			} catch (Exception $e) {
				$this->logger->error('Me: cannot create user api key: unexpected exception', ['exception' => $e]);
			}
		}
		$response[Constants::ME_RESPONSE_API_KEY] = $olvidUser->getApiKey();

		// on first /me call create a nonce that will be used to ask server if user is still in server database if user had been logged out
		if ($olvidUser->hasIdentity() && $olvidUser->getNonce() === null) {
			$olvidUser->setNonce(RandomUtil::uuid_create());
			$response[Constants::ME_RESPONSE_NONCE] = base64_encode($olvidUser->getNonce());
		}

		// save olvid user to database
		$this->context->db->user->update($olvidUser);

		$response[Constants::ME_RESPONSE_SERVER] = $this->context->nextcloud->appManager->getOlvidServerUrl() ?? '';
		$response[Constants::ME_RESPONSE_REVOCATION_ALLOWED] = true;
		$response[Constants::ME_RESPONSE_TRANSFER_RESTRICTED] = false;

		// TODO deprecated, delete it when possible
		$response[Constants::ME_RESPONSE_MINIMUM_BUILD_VERSIONS] = [
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_ANDROID_LABEL => Constants::MIN_BUILD_ANDROID,
			Constants::ME_RESPONSE_MINIMUM_BUILD_VERSION_IOS_LABEL => Constants::MIN_BUILD_IOS,
		];

		$globalPushTopic = $this->context->nextcloud->appManager->getGlobalPushTopic();
		// global push topic not set try to request one
		if (!$globalPushTopic) {
			try {
				$globalPushTopic = $this->context->olvidServer->requestNewPushTopic();
				$this->context->nextcloud->appManager->setGlobalPushTopic($globalPushTopic);
			} catch (InvalidConfigurationException) {
				$this->logger->error('Me: cannot create global push topic: invalid configuration');
			} catch (Exception $e) {
				$this->logger->error('Me: cannot create global push topic unexpected exception', ['exception' => $e]);
			}
		}
		$response[Constants::ME_RESPONSE_PUSH_TOPICS] = $globalPushTopic ? [$globalPushTopic] : null;

		// list every revocation since timestamp passed in request
		$signedRevocations = $this->context->db->revocation->getSinceTimestampOrNull($timestamp);

		$response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] = array_map(function ($revocation) { return $revocation->getSignedRevocation(); }, $signedRevocations ?? []);
		$response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] = count($response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS]) !== 0 ? $response[Constants::ME_RESPONSE_SIGNED_REVOCATIONS] : null;

		$response[Constants::ME_RESPONSE_CURRENT_TIMESTAMP] = $currentTimestamp;

		return new JSONResponse($response, Http::STATUS_OK);
	}
}
