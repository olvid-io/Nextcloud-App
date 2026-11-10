<?php

namespace OCA\Olvid\Utils\Context;

use Exception;
use OCA\Olvid\Utils\Context\OlvidServer\InternalErrorException;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidDirectoryKeyException;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidRequestException;
use OCA\Olvid\Utils\Context\OlvidServer\JsonOlvidServerRequest;
use OCA\Olvid\Utils\Context\OlvidServer\LicenseKeyNotFoundException;
use OCA\Olvid\Utils\Context\OlvidServer\MissingBotPermissionException;
use OCA\Olvid\Utils\Context\OlvidServer\NetworkException;
use OCA\Olvid\Utils\Context\OlvidServer\OlvidServerException;
use OCP\Http\Client\IClientService;
use Psr\Log\LoggerInterface;
use Throwable;

class OlvidContextServer {
	private ?string $cachedServerUrl = null;

	private ?string $cachedDirectoryKey = null;

	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IClientService $clientService,
		private readonly OlvidContextAppManager $olvidAppManager,
	) {
	}

	private function getCachedDirectoryKey(): ?string {
		if ($this->cachedDirectoryKey === null) {
			$this->cachedDirectoryKey = $this->olvidAppManager->getDirectoryLicenseKey();
		}

		return $this->cachedDirectoryKey;
	}

	private function getCachedServerUrl(): ?string {
		if ($this->cachedServerUrl === null) {
			$this->cachedServerUrl = $this->olvidAppManager->getOlvidServerUrl();
		}

		return $this->cachedServerUrl;
	}

	/**
	 * Check current directory configuration against olvid server.
	 * Pass any value to serverUrl and/or directoryKey to override configured value.
	 *
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function requestCheckCredentials(?string $serverUrl = null, ?string $directoryKey = null): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REQUEST_CHECK_CREDENTIALS;
		$serverResponse = $this->serverRequest($query, $serverUrl, $directoryKey);
		$this->logger->info('OlvidServer:: requestCheckAuthentication');
		if (!array_key_exists('ok', $serverResponse)) {
			$this->logger->error('OlvidServer:: requestCheckAuthentication: success not set in response');
			throw new InternalErrorException();
		}

		return $serverResponse['ok'];
	}

	public function requestCheckCredentialsNoFail(?string $serverUrl = null, ?string $directoryKey = null): bool {
		try {
			if ($this->requestCheckCredentials($serverUrl, $directoryKey)) {
				return true;
			}
			$this->logger->error('OlvidServer::requestCheckCredentialsNoFail: directory license key was rejected by the Olvid server.');
			return false;
		} catch (InvalidDirectoryKeyException|LicenseKeyNotFoundException) {
			$this->logger->error('OlvidServer::requestCheckCredentialsNoFail: directory license key was rejected by the Olvid server.');
			return false;
		} catch (NetworkException) {
			$this->logger->error('OlvidServer::requestCheckCredentialsNoFail: Cannot reach the Olvid server, check the server url.');
			return false;
		} catch (Throwable) {
			$this->logger->error('OlvidServer::requestCheckCredentialsNoFail: Olvid server returned an unexpected error, check the server url.');
			return false;
		}
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function requestNewLicenseKey(): string {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REQUEST_NEW_API_KEY;
		$serverResponse = $this->serverRequest($query);
		$this->logger->info('OlvidServer:: requestNewLicenseKey');
		if (!array_key_exists('apiKey', $serverResponse)) {
			$this->logger->error('OlvidServer:: requestNewLicenseKey: apiKey not set in response');
			throw new InternalErrorException();
		}

		return $serverResponse['apiKey'];
	}

	/**
	 *
	 * @return true
	 *
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function revokeLicenseKey(string $licenseKeyToRevoke): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_REVOKE_API_KEY;
		$query->apiKeyToRevoke = $licenseKeyToRevoke;
		try {
			$this->logger->info('OlvidServer:: revokeLicenseKey');
			$this->serverRequest($query);
			return true;
		} catch (LicenseKeyNotFoundException) {
			// this means the key is maybe already revoked --> return a success!
			return true;
		}
	}

	public function revokeLicenseKeyNoFail(string $licenseKeyToRevoke): bool {
		try {
			return $this->revokeLicenseKey($licenseKeyToRevoke);
		} catch (InvalidConfigurationException) {
			$this->logger->error('Olvid: revokeLicenseKey: invalid server configuration');
			return false;
		} catch (OlvidServerException $e) {
			$this->logger->error('Olvid: revokeLicenseKey: unexpected server exception', ['exception' => $e]);
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
		$serverResponse = $this->serverRequest($query);
		$this->logger->info('OlvidServer:: requestNewPushTopic');
		if (!array_key_exists('pushTopic', $serverResponse)) {
			$this->logger->error('OlvidServer:: requestNewPushTopic: pushTopic not set in response');
			throw new InternalErrorException();
		}

		return $serverResponse['pushTopic'];
	}

	/**
	 *
	 * @return true
	 * @noinspection PhpUnused
	 */
	public function revokePushTopic(string $pushTopic): bool {
		// not implemented server side
		//		$query = new JsonOlvidServerRequest();
		//		$query->query = JsonOlvidServerRequest::QUERY_DELETE_PUSH_TOPIC;
		//		$query->pushTopic = $pushTopic;
		//		$this->serverRequest($query);
		//		$this->logger->info('OlvidServer:: revokePushTopic');
		return true;
	}


	public function revokePushTopicNoFail(string $pushTopic): bool {
		try {
			return $this->revokePushTopic($pushTopic);
		} catch (InvalidConfigurationException) {
			$this->logger->error('Olvid: revokePushTopic: invalid server configuration');
			return false;
		} catch (OlvidServerException $e) {
			$this->logger->error('Olvid: revokePushTopic: unexpected server exception', ['exception' => $e]);
			return false;
		}
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	private function sendGroupNotification(string $pushTopic): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_NOTIFY_PUSH_TOPIC_USERS;
		$query->pushTopic = $pushTopic;
		$this->serverRequest($query);
		$this->logger->info('OlvidServer:: sendGroupNotification');
		return true;
	}

	/**
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	private function sendSingleUserNotification(string $base64UserIdentity): bool {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_NOTIFY_SINGLE_USER;
		$query->userIdentity = $base64UserIdentity;
		$this->serverRequest($query);
		$this->logger->info('OlvidServer:: sendSingleUserNotification');
		return true;
	}

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
	 * @param string[] $knownLicenseKeys
	 * @param string[] $knownPushTopics
	 * @throws OlvidServerException
	 * @throws InvalidConfigurationException
	 */
	public function serverSynchronization(array $knownLicenseKeys, array $knownPushTopics): array {
		$query = new JsonOlvidServerRequest();
		$query->q = JsonOlvidServerRequest::QUERY_API_KEY_SYNCHRONIZATION;
		$query->knownApiKeys = $knownLicenseKeys;
		$query->knownPushTopics = $knownPushTopics;
		$this->logger->info('OlvidServer:: serverSynchronization');
		return $this->serverRequest($query);
	}

	/**
	 * pass $serverUrl and or $directoryKey to force value
	 *
	 * @throws NetworkException|OlvidServerException|InvalidConfigurationException
	 */
	private function serverRequest(JsonOlvidServerRequest $jsonRequest, ?string $serverUrl = null, ?string $directoryKey = null): array {
		$jsonRequest->keycloakApiKey = $directoryKey !== null ? $directoryKey : $this->getCachedDirectoryKey();
		$serverUrl = $serverUrl !== null ? $serverUrl : $this->getCachedServerUrl();

		if ($serverUrl === null || $jsonRequest->keycloakApiKey === null) {
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
		} catch (Exception $exception) {
			$this->logger->error(sprintf('OlvidContextServer: serverRequest: exception (query: %d)', $jsonRequest->q), ['exception' => $exception]);
			throw new NetworkException($exception);
		}
		if ($jsonResponse === null) {
			throw new InternalErrorException();
		}

		if (array_key_exists('error', $jsonResponse) && $jsonResponse['error']) {
			throw match ($jsonResponse['error']) {
				OlvidServerException::ERROR_INVALID_REQUEST => new InvalidRequestException(),
				OlvidServerException::ERROR_INVALID_DIRECTORY_KEY => new InvalidDirectoryKeyException(),
				OlvidServerException::ERROR_API_KEY_NOT_FOUND => new LicenseKeyNotFoundException(),
				OlvidServerException::ERROR_MISSING_BOT_PERMISSION => new MissingBotPermissionException(),
				default => new InternalErrorException(),
			};
		}

		return $jsonResponse;
	}
}
