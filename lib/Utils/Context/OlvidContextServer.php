<?php

namespace OCA\Olvid\Utils\Context;

use Exception;
use OCA\Olvid\Utils\Context\OlvidServer\ApiKeyNotFoundException;
use OCA\Olvid\Utils\Context\OlvidServer\InternalErrorException;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidApiKeyException;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidRequestException;
use OCA\Olvid\Utils\Context\OlvidServer\JsonOlvidServerRequest;
use OCA\Olvid\Utils\Context\OlvidServer\MissingBotPermissionException;
use OCA\Olvid\Utils\Context\OlvidServer\NetworkException;
use OCA\Olvid\Utils\Context\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;

class OlvidContextServer {
	private ?string $cachedServerUrl = null;
	private ?string $cachedApiKey = null;

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IClientService $clientService,
		private readonly OlvidAppConfigManager $olvidAppConfig,
	) {
	}

	private function getCachedApiKey(): string {
		if ($this->cachedApiKey === null) {
			$this->cachedApiKey = $this->olvidAppConfig->getOlvidServerApiKey();
		}
		return $this->cachedApiKey;
	}
	private function getCachedServerUrl(): string {
		if ($this->cachedServerUrl === null) {
			$this->cachedServerUrl = $this->olvidAppConfig->getOlvidServerUrl();
		}
		return $this->cachedServerUrl;
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
	 * @param string $apiKeyToRevoke
	 * @return false
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
	 * @param string $apiKeyToRevoke
	 * @return false
	 */
	public function revokeApiKeyNoFail(string $apiKeyToRevoke): bool {
		try {
			return $this->revokeApiKey($apiKeyToRevoke);
		} catch (InvalidConfigurationException) {
			$this->logger->error('Olvid: revokeApiKey: invalid server configuration');
			return false;
		} catch (OlvidServerException $e) {
			$this->logger->error('Olvid: revokeApiKey: unexpected server exception', ['exception' => $e]);
			return false;
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
	 * @param String $pushTopic
	 * @return void
	 * @noinspection PhpUnused
	 */
	public function revokePushTopic(String $pushTopic): void {
		// not implemented server side
		//		$query = new JsonOlvidServerRequest();
		//		$query->query = JsonOlvidServerRequest::QUERY_DELETE_PUSH_TOPIC;
		//		$query->pushTopic = $pushTopic;
		//		$this->serverApiRequest($query);
		//		$this->logger->info('OlvidServer:: revokePushTopic');
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	private function sendGroupNotification(String $pushTopic): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_NOTIFY_PUSH_TOPIC_USERS;
		$query->pushTopic = $pushTopic;
		$this->serverApiRequest($query);
		$this->logger->info('OlvidServer:: sendGroupNotification');
		return true;
	}

	/**
	 * @param String $base64UserIdentity
	 * @return bool
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	private function sendSingleUserNotification(String $base64UserIdentity): bool {

		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_NOTIFY_SINGLE_USER;
		$query->userIdentity = $base64UserIdentity;
		$this->serverApiRequest($query);
		$this->logger->info('OlvidServer:: sendSingleUserNotification');
		return true;
	}

	/**
	 * @param string $pushTopic
	 * @return bool
	 */
	public function sendGroupNotificationNoFail(string $pushTopic): bool {
		try {
			return $this->sendGroupNotification($pushTopic);
		} catch (InvalidConfigurationException) {
			$this->logger->error('Olvid: sendGroupNotification: invalid server configuration');
			return false;
		} catch (OlvidServerException $e) {
			$this->logger->error('Olvid: sendGroupNotification: unexpected server exception', ['exception' => $e]);
			return false;
		}
	}

	/**
	 * @param string $base64UserIdentity
	 * @return bool
	 */
	public function sendSingleUserNotificationNoFail(string $base64UserIdentity): bool {
		try {
			return $this->sendSingleUserNotification($base64UserIdentity);
		} catch (InvalidConfigurationException) {
			$this->logger->error('Olvid: sendSingleUserNotification: invalid server configuration');
			return false;
		} catch (OlvidServerException $e) {
			$this->logger->error('Olvid: sendSingleUserNotification: unexpected server exception', ['exception' => $e]);
			return false;
		}
	}

	/**
	 * @throws NetworkException|OlvidServerException|InvalidConfigurationException
	 */
	private function serverApiRequest(JsonOlvidServerRequest $jsonRequest): array {
		$jsonRequest->keycloakApiKey = $this->getCachedApiKey();
		$serverUrl = $this->getCachedServerUrl();
		if ($serverUrl == null || $jsonRequest->keycloakApiKey == null) {
			throw new InvalidConfigurationException();
		}
		if (!$jsonRequest->q) {
			throw new InvalidRequestException();
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->post($serverUrl . '/keycloakQuery', [
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body' => json_encode($jsonRequest)
			]);
			$jsonResponse = json_decode($response->getBody(), associative: true);
		} catch (Exception $e) {
			throw new NetworkException($e->getMessage());
		}

		if ($jsonResponse != null && array_key_exists('error', $jsonResponse) && $jsonResponse['error']) {
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
