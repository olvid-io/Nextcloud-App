<?php

namespace OCA\Olvid\Utils\OlvidServer;

use JsonSerializable;
use OCA\Olvid\Utils\OlvidAppConfigManager;

class OlvidServerUtils {
	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public static function requestNewApiKey(OlvidAppConfigManager $olvidAppConfig): string {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REQUEST_NEW_API_KEY;
		$query->keycloakApiKey = $olvidAppConfig->getOlvidServerApiKey() ?? '';
		$serverUrl = $olvidAppConfig->getOlvidServerUrl();
		if ($serverUrl == null || $query->keycloakApiKey == null) {
			throw new InvalidConfigurationException();
		}

		$serverResponse = OlvidServerUtils::serverApiRequest($serverUrl, $query);
		return $serverResponse['apiKey'];
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public static function revokeApiKey(OlvidAppConfigManager $olvidAppConfig, string $apiKeyToRevoke): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REVOKE_API_KEY;
		$query->apiKeyToRevoke = $apiKeyToRevoke;
		$query->keycloakApiKey = $olvidAppConfig->getOlvidServerApiKey();
		$serverUrl = $olvidAppConfig->getOlvidServerUrl();
		if ($serverUrl == null || $query->keycloakApiKey == null) {
			throw new InvalidConfigurationException();
		}

		try {
			OlvidServerUtils::serverApiRequest($serverUrl, $query);
			return true;
		} catch (ApiKeyNotFoundException) {
			// this means the key is maybe already revoked --> return a success!
			return true;
		}
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public static function requestNewPushTopic(OlvidAppConfigManager $olvidAppConfig): string {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REQUEST_NEW_PUSH_TOPIC;
		$query->keycloakApiKey = $olvidAppConfig->getOlvidServerApiKey() ?? '';
		$serverUrl = $olvidAppConfig->getOlvidServerUrl();
		if ($serverUrl == null || $query->keycloakApiKey == null) {
			throw new InvalidConfigurationException();
		}

		$serverResponse = OlvidServerUtils::serverApiRequest($serverUrl, $query);
		return $serverResponse['pushTopic'];
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public static function revokePushTopic(OlvidAppConfigManager $olvidAppConfig, String $pushTopic): void {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_DELETE_PUSH_TOPIC;
		$query->pushTopic = $pushTopic;
		$query->keycloakApiKey = $olvidAppConfig->getOlvidServerApiKey() ?? '';
		$serverUrl = $olvidAppConfig->getOlvidServerUrl();
		if ($serverUrl == null || $query->keycloakApiKey == null) {
			throw new InvalidConfigurationException();
		}

		OlvidServerUtils::serverApiRequest($serverUrl, $query);
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public static function sendGroupNotification(OlvidAppConfigManager $olvidAppConfig, String $pushTopic): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_NOTIFY_PUSH_TOPIC_USERS;
		$query->pushTopic = $pushTopic;
		$query->keycloakApiKey = $olvidAppConfig->getOlvidServerApiKey();
		$serverUrl = $olvidAppConfig->getOlvidServerUrl();
		if ($serverUrl == null || $query->keycloakApiKey == null) {
			throw new InvalidConfigurationException();
		}
		OlvidServerUtils::serverApiRequest($serverUrl, $query);
		return true;
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public static function sendSingleUserNotification(OlvidAppConfigManager $olvidAppConfig, String $userIdentity): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_NOTIFY_SINGLE_USER;
		$query->keycloakApiKey = $olvidAppConfig->getOlvidServerApiKey();
		$query->userIdentity = base64_decode($userIdentity);
		$serverUrl = $olvidAppConfig->getOlvidServerUrl();
		if ($serverUrl == null || $query->keycloakApiKey == null) {
			throw new InvalidConfigurationException();
		}
		OlvidServerUtils::serverApiRequest($serverUrl, $query);
		return true;
	}

	/**
	 * @throws OlvidServerException
	 */
	private static function serverApiRequest(string $serverUrl, JsonSerializable $jsonRequest): array {
		$session = curl_init($serverUrl . '/keycloakQuery');
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($session, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
		curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($jsonRequest));
		$server_output = curl_exec($session);
		curl_close($session);
		$jsonResponse = json_decode($server_output, associative: true);

		if (array_key_exists('error', $jsonResponse) && $jsonResponse['error']) {
			switch ($jsonResponse['error']) {
				case OlvidServerException::ERROR_INVALID_REQUEST:
					throw new InvalidRequestException();
				case OlvidServerException::ERROR_INTERNAL:
					throw new InternalErrorException();
				case OlvidServerException::ERROR_INVALID_API_KEY:
					throw new InvalidApiKeyException();
				case OlvidServerException::ERROR_API_KEY_NOT_FOUND:
					throw new ApiKeyNotFoundException();
				case OlvidServerException::ERROR_MISSING_BOT_PERMISSION:
					throw new MissingBotPermissionException();
			}
		}
		return $jsonResponse;
	}
}
