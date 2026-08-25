<?php

declare(strict_types=1);

namespace OCA\Olvid\Cron;

use Exception;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Db\OlvidFederatedServer;
use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\Http\Client\IClientService;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Federation directory sync. Runs independently of the existing daily SyncCronTask (that
 * cadence is far too infrequent for cross-instance directory freshness):
 *
 * 1. Discovers peers directly from Nextcloud's own fully-trusted server list — no
 *    Olvid-specific admin-managed peer list exists.
 * 2. Probes each peer for Olvid support and refreshes its cached JWKS.
 * 3. Pulls incremental user + revocation updates from Olvid-capable peers, verifying every
 *    signed user blob against that peer's own just-fetched key before caching anything
 *    derived from it.
 */
class OlvidFederationSyncTask extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
		private readonly OlvidAppConfigManager $appConfig,
		private readonly IClientService $clientService,
		private readonly IURLGenerator $urlGenerator,
	) {
		parent::__construct($time);
		parent::setInterval(300); // 5 minutes
		parent::setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	public function run($argument): void {
		if (!$this->context->federation->isFederationAppAvailable()) {
			$this->logger->debug('OlvidFederationSyncTask: skipped, federation app is not available');
			return;
		}
		if (!$this->appConfig->isFederationEnabled()) {
			$this->logger->debug('OlvidFederationSyncTask: skipped, olvid-federation-enabled is off');
			return;
		}

		$this->discoverPeers();

		foreach ($this->context->db->federatedServer->getAll() as $server) {
			if (!$server->getEnabled()) {
				continue;
			}

			$this->refreshCapabilityAndJwks($server);
			if (!$server->getOlvidCapable()) {
				continue;
			}

			$this->pullUsers($server);
			$this->pullRevocations($server);
		}
	}

	/**
	 * Adds newly trusted servers as (not-yet-probed) federated server rows, and drops the
	 * cache for any server that's no longer Nextcloud-trusted.
	 */
	private function discoverPeers(): void {
		$trustedUrls = $this->context->federation->getFullyTrustedServerUrls();
		$this->logger->debug('OlvidFederationSyncTask: discoverPeers: found ' . count($trustedUrls) . ' fully-trusted server(s): ' . implode(', ', $trustedUrls));
		$known = $this->context->db->federatedServer->getAll();
		$knownUrls = array_map(static fn (OlvidFederatedServer $s) => $s->getRemoteUrl(), $known);

		foreach ($trustedUrls as $url) {
			if (!in_array($url, $knownUrls, true)) {
				try {
					$this->context->db->federatedServer->insert(OlvidFederatedServer::create($url));
				} catch (Exception $e) {
					$this->logger->error('OlvidFederationSyncTask: discoverPeers: cannot add server ' . $url, ['exception' => $e]);
				}
			}
		}

		foreach ($known as $server) {
			if (!in_array($server->getRemoteUrl(), $trustedUrls, true)) {
				$this->logger->info('OlvidFederationSyncTask: dropping cache for no-longer-trusted server ' . $server->getRemoteUrl());
				try {
					$this->context->db->federatedUser->deleteByServer($server->getId());
					$this->context->db->federatedRevocation->deleteByServer($server->getId());
					$this->context->db->federatedServer->delete($server);
				} catch (Exception $e) {
					$this->logger->error('OlvidFederationSyncTask: discoverPeers: cannot drop server ' . $server->getRemoteUrl(), ['exception' => $e]);
				}
			}
		}
	}

	private function refreshCapabilityAndJwks(OlvidFederatedServer $server): void {
		$server->setLastSyncAttempt(TimeUtil::currentTimeMillis());
		try {
			$client = $this->clientService->newClient();
			$headers = ['Accept' => 'application/json', 'OCS-APIRequest' => 'true'];

			$olvidResponse = $client->get($this->apiUrl($server, '.well-known/olvid'), ['headers' => $headers, 'timeout' => 10]);
			if ($olvidResponse->getStatusCode() !== Http::STATUS_OK) {
				throw new Exception('unexpected status probing .well-known/olvid: ' . $olvidResponse->getStatusCode());
			}

			$jwksResponse = $client->get($this->apiUrl($server, '.well-known/owned-jwks'), ['headers' => $headers, 'timeout' => 10]);
			if ($jwksResponse->getStatusCode() !== Http::STATUS_OK) {
				throw new Exception('unexpected status fetching .well-known/owned-jwks: ' . $jwksResponse->getStatusCode());
			}
			$jwks = json_decode((string)$jwksResponse->getBody(), true);
			if (!is_array($jwks) || !isset($jwks['keys'])) {
				throw new Exception('malformed jwks document');
			}

			$server->setOlvidCapable(true);
			$server->setJwksCache(json_encode($jwks));
			$server->setJwksFetchedAt(TimeUtil::currentTimeMillis());
			$server->setLastSyncStatus('ok');
		} catch (Throwable $e) {
			$this->logger->warning('OlvidFederationSyncTask: refreshCapabilityAndJwks: ' . $server->getRemoteUrl() . ' unreachable or not Olvid-capable', ['exception' => $e]);
			$server->setOlvidCapable(false);
			$server->setLastSyncStatus('unreachable: ' . $e->getMessage());
		}

		try {
			$this->context->db->federatedServer->update($server);
		} catch (Exception $e) {
			$this->logger->error('OlvidFederationSyncTask: refreshCapabilityAndJwks: cannot persist server state', ['exception' => $e]);
		}
	}

	private function pullUsers(OlvidFederatedServer $server): void {
		$keySet = $this->parseKeySetOrNull($server);
		if ($keySet === null) {
			return;
		}
		$sharedSecret = $this->context->federation->getServerSharedSecretOrNull($server->getRemoteUrl());
		if ($sharedSecret === null) {
			$this->logger->warning('OlvidFederationSyncTask: pullUsers: no shared secret for ' . $server->getRemoteUrl());
			return;
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->post($this->apiUrl($server, 'federations/users'), [
				'headers' => ['Accept' => 'application/json', 'OCS-APIRequest' => 'true'],
				'json' => [
					Constants::FEDERATION_REQUEST_CALLER_URL => $this->ownCallerUrl(),
					Constants::FEDERATION_REQUEST_SHARED_SECRET => $sharedSecret,
					Constants::FEDERATION_REQUEST_TIMESTAMP => $server->getLastSyncTimestamp(),
				],
				'timeout' => 30,
			]);
			$body = json_decode((string)$response->getBody(), true);
		} catch (Throwable $e) {
			$this->logger->warning('OlvidFederationSyncTask: pullUsers: request failed for ' . $server->getRemoteUrl(), ['exception' => $e]);
			return;
		}

		if (!is_array($body) || !isset($body[Constants::FEDERATION_USERS_RESPONSE_USERS]) || !is_array($body[Constants::FEDERATION_USERS_RESPONSE_USERS])) {
			$this->logger->warning('OlvidFederationSyncTask: pullUsers: malformed response from ' . $server->getRemoteUrl());
			return;
		}

		$domainSuffix = '@' . $server->getDomain();
		$maxRegistrationTimestamp = $server->getLastSyncTimestamp();

		foreach ($body[Constants::FEDERATION_USERS_RESPONSE_USERS] as $entry) {
			$signedDetails = is_array($entry) ? ($entry[Constants::FEDERATION_USER_SIGNED_DETAILS] ?? null) : null;
			$registrationTimestamp = is_array($entry) ? (int)($entry[Constants::FEDERATION_USER_REGISTRATION_TIMESTAMP] ?? 0) : 0;
			if (!is_string($signedDetails)) {
				continue;
			}

			try {
				$decoded = JWT::decode($signedDetails, $keySet);
				$details = JsonUserDetails::fromArray((array)$decoded);
			} catch (Throwable $e) {
				$this->logger->warning('OlvidFederationSyncTask: pullUsers: rejected unverifiable signature from ' . $server->getRemoteUrl(), ['exception' => $e]);
				continue;
			}

			if ($details?->id === null || $details->base64Identity === null || !str_ends_with($details->id, $domainSuffix)) {
				$this->logger->warning('OlvidFederationSyncTask: pullUsers: rejected identity not matching claimed domain from ' . $server->getRemoteUrl());
				continue;
			}
			$remoteUserId = substr($details->id, 0, -strlen($domainSuffix));
			$bytesIdentity = base64_decode($details->base64Identity);
			if ($remoteUserId === '' || $bytesIdentity === false || $bytesIdentity === '') {
				continue;
			}

			try {
				$this->context->db->federatedUser->upsert(
					$server->getId(),
					$remoteUserId,
					$bytesIdentity,
					$signedDetails,
					$details->firstname,
					$details->lastname,
					$details->position,
					$details->company,
					$details->computeFullSearchString(),
					$registrationTimestamp,
				);
				$maxRegistrationTimestamp = max($maxRegistrationTimestamp, $registrationTimestamp);
			} catch (Exception $e) {
				$this->logger->error('OlvidFederationSyncTask: pullUsers: cannot cache user from ' . $server->getRemoteUrl(), ['exception' => $e]);
			}
		}

		try {
			$server->setLastSyncTimestamp($maxRegistrationTimestamp);
			$this->context->db->federatedServer->update($server);
		} catch (Exception $e) {
			$this->logger->error('OlvidFederationSyncTask: pullUsers: cannot persist sync cursor', ['exception' => $e]);
		}
	}

	private function pullRevocations(OlvidFederatedServer $server): void {
		$sharedSecret = $this->context->federation->getServerSharedSecretOrNull($server->getRemoteUrl());
		if ($sharedSecret === null) {
			$this->logger->warning('OlvidFederationSyncTask: pullRevocations: no shared secret for ' . $server->getRemoteUrl());
			return;
		}

		try {
			$client = $this->clientService->newClient();
			$response = $client->post($this->apiUrl($server, 'federations/revocations'), [
				'headers' => ['Accept' => 'application/json', 'OCS-APIRequest' => 'true'],
				'json' => [
					Constants::FEDERATION_REQUEST_CALLER_URL => $this->ownCallerUrl(),
					Constants::FEDERATION_REQUEST_SHARED_SECRET => $sharedSecret,
					Constants::FEDERATION_REQUEST_TIMESTAMP => $server->getLastRevocationSyncTimestamp(),
				],
				'timeout' => 30,
			]);
			$body = json_decode((string)$response->getBody(), true);
		} catch (Throwable $e) {
			$this->logger->warning('OlvidFederationSyncTask: pullRevocations: request failed for ' . $server->getRemoteUrl(), ['exception' => $e]);
			return;
		}

		if (!is_array($body) || !isset($body[Constants::FEDERATION_REVOCATIONS_RESPONSE_REVOCATIONS]) || !is_array($body[Constants::FEDERATION_REVOCATIONS_RESPONSE_REVOCATIONS])) {
			$this->logger->warning('OlvidFederationSyncTask: pullRevocations: malformed response from ' . $server->getRemoteUrl());
			return;
		}

		$maxTimestamp = $server->getLastRevocationSyncTimestamp();
		foreach ($body[Constants::FEDERATION_REVOCATIONS_RESPONSE_REVOCATIONS] as $entry) {
			if (!is_array($entry) || !isset($entry[Constants::FEDERATION_REVOCATION_BYTES_IDENTITY], $entry[Constants::FEDERATION_REVOCATION_SIGNED_REVOCATION], $entry[Constants::FEDERATION_REVOCATION_TIMESTAMP], $entry[Constants::FEDERATION_REVOCATION_TYPE])) {
				continue;
			}
			$bytesIdentity = base64_decode((string)$entry[Constants::FEDERATION_REVOCATION_BYTES_IDENTITY]);
			if ($bytesIdentity === false || $bytesIdentity === '') {
				continue;
			}

			try {
				$this->context->db->federatedRevocation->upsert(
					$server->getId(),
					(string)($entry[Constants::FEDERATION_REVOCATION_REMOTE_USER_ID] ?? ''),
					$bytesIdentity,
					(int)$entry[Constants::FEDERATION_REVOCATION_TYPE],
					(string)$entry[Constants::FEDERATION_REVOCATION_SIGNED_REVOCATION],
					(int)$entry[Constants::FEDERATION_REVOCATION_TIMESTAMP],
				);
				$maxTimestamp = max($maxTimestamp, (int)$entry[Constants::FEDERATION_REVOCATION_TIMESTAMP]);
			} catch (Exception $e) {
				$this->logger->error('OlvidFederationSyncTask: pullRevocations: cannot cache revocation from ' . $server->getRemoteUrl(), ['exception' => $e]);
			}
		}

		try {
			$server->setLastRevocationSyncTimestamp($maxTimestamp);
			$this->context->db->federatedServer->update($server);
		} catch (Exception $e) {
			$this->logger->error('OlvidFederationSyncTask: pullRevocations: cannot persist sync cursor', ['exception' => $e]);
		}
	}

	/** @return array<string, \Firebase\JWT\Key>|null */
	private function parseKeySetOrNull(OlvidFederatedServer $server): ?array {
		$jwks = $server->getDecodedJwksCache();
		if ($jwks === null) {
			return null;
		}
		try {
			return JWK::parseKeySet($jwks);
		} catch (Throwable $e) {
			$this->logger->warning('OlvidFederationSyncTask: cannot parse cached JWKS for ' . $server->getRemoteUrl(), ['exception' => $e]);
			return null;
		}
	}

	private function apiUrl(OlvidFederatedServer $server, string $path): string {
		return rtrim($server->getRemoteUrl(), '/') . '/ocs/v2.php/apps/olvid/' . $path;
	}

	/**
	 * This instance's own address, as the *peer* needs to look it up in its own trusted-
	 * servers list. This job always runs via cron (a CLI context), where
	 * IURLGenerator::getBaseUrl() reads the live request's host and falls back to something
	 * like "http://localhost" (there is no real request under cron). getAbsoluteURL()
	 * checks \OC::$CLI first and reads the `overwrite.cli.url` config instead — the same
	 * mechanism core's own RequestSharedSecret/GetSharedSecret background jobs rely on for
	 * this exact problem (see apps/federation/lib/BackgroundJob/RequestSharedSecret.php).
	 */
	private function ownCallerUrl(): string {
		return rtrim($this->urlGenerator->getAbsoluteURL('/'), '/');
	}
}
