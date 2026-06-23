<?php

namespace OCA\Olvid\Utils\OlvidServer;

use OCA\Olvid\Utils\OlvidAppConfigManager;
use Psr\Log\LoggerInterface;

// TODO remove static, this is not nextcloud paradigm (inject interfaces in constructor)
class OlvidServer {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidAppConfigManager $olvidAppConfig,
	) {
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function requestNewApiKey(): string {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REQUEST_NEW_API_KEY;
		$serverResponse = $this->serverApiRequest($query);
		$this->logger->info('OlvidServer:: requestNewApiKey');
		return $serverResponse['apiKey'];
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function revokeApiKey(string $apiKeyToRevoke): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REVOKE_API_KEY;
		$query->apiKeyToRevoke = $apiKeyToRevoke;
		try {
			$this->logger->info('OlvidServer:: revokeApiKey');
			$this->serverApiRequest($query);
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
	public function requestNewPushTopic(): string {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REQUEST_NEW_PUSH_TOPIC;
		$serverResponse = $this->serverApiRequest($query);
		$this->logger->info('OlvidServer:: requestNewPushTopic');
		return $serverResponse['pushTopic'];
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function revokePushTopic(String $pushTopic): void {
		// TODO TODEL ?not implemented server side
		//		$query = new JsonOlvidServerRequest();
		//		$query->q = JsonOlvidServerRequest::QUERY_DELETE_PUSH_TOPIC;
		//		$query->pushTopic = $pushTopic;
		//		$this->serverApiRequest($query);
		//		$this->logger->info('OlvidServer:: revokePushTopic');
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function sendGroupNotification(String $pushTopic): bool {
		// TODO TODEL
		$this->logger->error('OlvidServer:: sendGroupNotification: push topic ' . $pushTopic);

		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_NOTIFY_PUSH_TOPIC_USERS;
		$query->pushTopic = $pushTopic;
		$this->serverApiRequest($query);
		$this->logger->info('OlvidServer:: sendGroupNotification');
		return true;
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function sendSingleUserNotification(String $userIdentity): bool {
		// TODO TODEL
		$this->logger->error('OlvidServer:: sendSingleUserNotification: user identity ' . $userIdentity);

		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_NOTIFY_SINGLE_USER;
		$query->userIdentity = $userIdentity;
		$this->serverApiRequest($query);
		$this->logger->info('OlvidServer:: sendSingleUserNotification');
		return true;
	}

	/**
	 * @throws OlvidServerException|InvalidConfigurationException
	 */
	private function serverApiRequest(JsonOlvidServerRequest $jsonRequest): array {
		$jsonRequest->keycloakApiKey = $this->olvidAppConfig->getOlvidServerApiKey();
		$serverUrl = $this->olvidAppConfig->getOlvidServerUrl();
		if ($serverUrl == null || $jsonRequest->keycloakApiKey == null) {
			throw new InvalidConfigurationException();
		}
		if (!$jsonRequest->q) {
			throw new InvalidRequestException();
		}

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
