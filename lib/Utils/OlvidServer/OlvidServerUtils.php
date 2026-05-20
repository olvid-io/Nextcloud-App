<?php

namespace OCA\Olvid\Utils\OlvidServer;

use Exception;
use JsonSerializable;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\IAppConfig;

class OlvidServerUtils {
	/**
	 * @throws OlvidServerException
	 * // TODO
	 * @throws Exception
	 */
	public static function requestNewApiKey(IAppConfig $appConfig): string
	{
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REQUEST_NEW_API_KEY;
		$query->keycloakApiKey = AppConfigManager::getOlvidServerApiKey($appConfig) ?? "";
		$serverUrl = AppConfigManager::getOlvidServerUrl($appConfig);
		if ($serverUrl == null || $query->keycloakApiKey == null) {
//			throw new InvalidApiKeyException();
			// TODO
			throw new Exception("InvalidApiKeyException");
		}

		$serverResponse = OlvidServerUtils::serverApiRequest($serverUrl, $query);

		if ($serverResponse["error"]) {
			throw new ("Olvid server returned an error: " . $serverResponse["error"]);
		}

		return $serverResponse["apiKey"];
	}

	/**
	 * @throws OlvidServerException
	 * // TODO
	 * @throws Exception
	 */
	public static function revokeApiKey(IAppConfig $appConfig, string $apiKeyToRevoke): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REVOKE_API_KEY;
		$query->apiKeyToRevoke = $apiKeyToRevoke;
		$query->keycloakApiKey = AppConfigManager::getOlvidServerApiKey($appConfig);
		$serverUrl = AppConfigManager::getOlvidServerUrl($appConfig);
		if ($serverUrl == null || $query->keycloakApiKey == null) {
//			throw new InvalidApiKeyException();
			// TODO
			throw new Exception("InvalidApiKeyException");
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
	 * // TODO
	 * @throws Exception
	 */
	private static function serverApiRequest(string $serverUrl, JsonSerializable $jsonRequest): array {
		$session = curl_init($serverUrl . "/keycloakQuery");
		curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
		curl_setopt( $session, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		curl_setopt($session, CURLOPT_POSTFIELDS, json_encode($jsonRequest));
		$server_output = curl_exec($session);
		curl_close($session);
		$jsonResponse = json_decode($server_output, associative: true);

		if ($jsonResponse["error"]) {
			switch ($jsonResponse["error"]) {
				case OlvidServerException::ERROR_INVALID_REQUEST:
//					throw new InvalidRequestException();
					// TODO
					throw new Exception("InvalidRequestException");
				case OlvidServerException::ERROR_INTERNAL:
//					throw new InternalErrorException();
					// TODO
					throw new Exception("InternalErrorException");
				case OlvidServerException::ERROR_INVALID_API_KEY:
//					throw new InvalidApiKeyException();
					// TODO
					throw new Exception("Exception");
				case OlvidServerException::ERROR_API_KEY_NOT_FOUND:
//					throw new ApiKeyNotFoundException();
					// TODO
					throw new Exception("ApiKeyNotFoundException");
				case OlvidServerException::ERROR_MISSING_BOT_PERMISSION:
//					throw new MissingBotPermissionException();
					// TODO
					throw new Exception("MissingBotPermissionException");
			}
		}
		return $jsonResponse;
	}
}
