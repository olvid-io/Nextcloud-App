<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils\Context\OlvidFederation;

use Firebase\JWT\JWK;
use Firebase\JWT\Key;
use OCA\Olvid\Db\OlvidFederatedServerMapper;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Aggregates this server's own signing key with every trusted, Olvid-capable federated
 * server's cached public key into a single JWKS. Published as-is at /.well-known/jwks so
 * Olvid clients can verify federated users' signatures with their existing local
 * JWT/JWKS verification — no client-side changes needed, since kid selects the right key.
 *
 * The same aggregation backs /olvid-rest/verify's signature check.
 */
class FederatedKeyRegistry {
	public function __construct(
		private readonly OlvidAppConfigManager $appConfig,
		private readonly OlvidFederatedServerMapper $federatedServerMapper,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Raw JWKS document, own key only.
	 * @return array{keys: array}
	 */
	public function getOwnJwks(): array {
		return ['keys' => [$this->getOwnJwkEntry()]];
	}

	/**
	 * Raw JWKS document, own key first.
	 * @return array{keys: array}
	 */
	public function getAggregatedJwks(): array {
		$keys = [$this->getOwnJwkEntry()];
		foreach ($this->federatedServerMapper->getEnabledOlvidCapable() as $server) {
			$decoded = $server->getDecodedJwksCache();
			if ($decoded === null) {
				continue;
			}
			foreach ($decoded['keys'] as $key) {
				$keys[] = $key;
			}
		}
		return ['keys' => $keys];
	}

	/**
	 * @return array<string, Key> kid => Key, suitable for JWT::decode()'s multi-key form.
	 *                            A malformed cached JWKS for one federated server does not prevent verification
	 *                            against the others or against this server's own key.
	 */
	public function getAggregatedKeySet(): array {
		$keySet = [];
		try {
			$keySet = JWK::parseKeySet(['keys' => [$this->getOwnJwkEntry()]]);
		} catch (Throwable $e) {
			$this->logger->error('FederatedKeyRegistry: failed to parse own key', ['exception' => $e]);
		}

		foreach ($this->federatedServerMapper->getEnabledOlvidCapable() as $server) {
			$decoded = $server->getDecodedJwksCache();
			if ($decoded === null) {
				continue;
			}
			try {
				// union: first occurrence wins on a (extremely unlikely) kid collision
				$keySet += JWK::parseKeySet($decoded);
			} catch (Throwable $e) {
				$this->logger->warning('FederatedKeyRegistry: failed to parse cached JWKS for ' . $server->getRemoteUrl(), ['exception' => $e]);
			}
		}

		return $keySet;
	}

	private function getOwnJwkEntry(): array {
		return [
			'kty' => 'EC',
			'crv' => 'P-256',
			'x' => $this->appConfig->getJwkKeyPublicKeyX(),
			'y' => $this->appConfig->getJwkKeyPublicKeyY(),
			'use' => 'sig',
			'kid' => $this->appConfig->getJwkKeyId(),
			'alg' => 'ES256',
		];
	}
}
