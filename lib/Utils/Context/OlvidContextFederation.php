<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils\Context;

use OCA\Federation\TrustedServers;
use OCA\Olvid\Db\OlvidFederatedServerMapper;
use OCA\Olvid\Db\OlvidFederatedUserMapper;
use OCA\Olvid\Utils\Context\OlvidFederation\FederatedIdentityMatch;
use OCA\Olvid\Utils\Context\OlvidFederation\FederatedKeyRegistry;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Thin wrapper around Nextcloud core's `federation` app trust store. Olvid federation has
 * no trust concept of its own: a remote instance is only federated with if it is already a
 * Nextcloud-trusted server (Settings > Sharing) with a fully exchanged shared secret.
 *
 * The `federation` app is a soft dependency: if it's disabled, every method here degrades
 * to "nothing is trusted" rather than throwing.
 */
class OlvidContextFederation {
	public function __construct(
		public readonly FederatedKeyRegistry $keyRegistry,
		private readonly IAppManager $appManager,
		private readonly LoggerInterface $logger,
		private readonly OlvidFederatedServerMapper $federatedServerMapper,
		private readonly OlvidFederatedUserMapper $federatedUserMapper,
	) {
	}

	public function isFederationAppAvailable(): bool {
		return $this->appManager->isEnabledForUser('federation');
	}

	public function isTrustedServer(string $url): bool {
		if (!$this->isFederationAppAvailable()) {
			return false;
		}
		try {
			return Server::get(TrustedServers::class)->isTrustedServer($url);
		} catch (Throwable $e) {
			$this->logger->error('FederationTrust: isTrustedServer: unexpected exception', ['exception' => $e]);
			return false;
		}
	}

	public function getServerSharedSecretOrNull(string $url): ?string {
		if (!$this->isFederationAppAvailable()) {
			return null;
		}
		try {
			$secret = Server::get(TrustedServers::class)->getSharedSecret($url);
			return $secret !== '' ? $secret : null;
		} catch (Throwable $e) {
			$this->logger->error('FederationTrust: getSharedSecretOrNull: unexpected exception', ['exception' => $e]);
			return null;
		}
	}

	/**
	 * Trusted servers where the shared-secret handshake has fully completed
	 * (TrustedServers::STATUS_OK), i.e. servers we can actually authenticate calls to/from.
	 *
	 * @return list<string> remote URLs
	 */
	public function getFullyTrustedServerUrls(): array {
		if (!$this->isFederationAppAvailable()) {
			return [];
		}
		try {
			$servers = Server::get(TrustedServers::class)->getServers() ?? [];
			return array_values(array_map(
				static fn (array $server) => $server['url'],
				array_filter($servers, static fn (array $server) => ($server['status'] ?? null) === TrustedServers::STATUS_OK),
			));
		} catch (Throwable $e) {
			$this->logger->error('FederationTrust: getFullyTrustedServerUrls: unexpected exception', ['exception' => $e]);
			return [];
		}
	}

	/**
	 * Resolves a `remoteUid@domain` identity string against the federated user cache.
	 *
	 * The `@` suffix is only ever treated as a domain if it matches a currently
	 * trusted+Olvid-enabled federated server — otherwise the whole string is an opaque local
	 * uid (safe even when local uids can themselves contain `@`, e.g. LDAP/email-style uids).
	 *
	 * @returns array<server:OlvidSer>
	 */
	public function resolveFederatedUserOrNull(string $federatedUserId): ?FederatedIdentityMatch {
		$atPosition = strrpos($federatedUserId, '@');
		if ($atPosition === false) {
			return null;
		}

		$domain = substr($federatedUserId, $atPosition + 1);
		$server = $this->federatedServerMapper->getEnabledByDomainOrNull($domain);
		if ($server === null) {
			return null;
		}

		$remoteUserId = substr($federatedUserId, 0, $atPosition);
		$federatedUser = $this->federatedUserMapper->getByServerAndRemoteUserIdOrNull($server->getId(), $remoteUserId);
		if ($federatedUser === null) {
			return null;
		}

		return new FederatedIdentityMatch($server, $federatedUser);
	}
}
