<?php

declare(strict_types=1);

namespace OCA\Olvid\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method string getRemoteUrl()
 * @method void setRemoteUrl(string $remoteUrl)
 *
 * @method bool getEnabled()
 * @method void setEnabled(bool $enabled)
 *
 * @method bool getOlvidCapable()
 * @method void setOlvidCapable(bool $olvidCapable)
 *
 * @method string|null getJwksCache()
 * @method void setJwksCache(string|null $jwksCache)
 *
 * @method int|null getJwksFetchedAt()
 * @method void setJwksFetchedAt(int|null $jwksFetchedAt)
 *
 * @method int getLastSyncTimestamp()
 * @method void setLastSyncTimestamp(int $lastSyncTimestamp)
 *
 * @method int getLastRevocationSyncTimestamp()
 * @method void setLastRevocationSyncTimestamp(int $lastRevocationSyncTimestamp)
 *
 * @method int|null getLastSyncAttempt()
 * @method void setLastSyncAttempt(int|null $lastSyncAttempt)
 *
 * @method string|null getLastSyncStatus()
 * @method void setLastSyncStatus(string|null $lastSyncStatus)
 */
class OlvidFederatedServer extends Entity {
	protected string $remoteUrl = '';
	protected bool $enabled = true;
	protected bool $olvidCapable = false;
	protected ?string $jwksCache = null;
	protected ?int $jwksFetchedAt = null;
	protected int $lastSyncTimestamp = 0;
	protected int $lastRevocationSyncTimestamp = 0;
	protected ?int $lastSyncAttempt = null;
	protected ?string $lastSyncStatus = null;

	public function __construct() {
		$this->addType('remoteUrl', Types::STRING);
		$this->addType('enabled', Types::BOOLEAN);
		$this->addType('olvidCapable', Types::BOOLEAN);
		$this->addType('jwksCache', Types::TEXT);
		$this->addType('jwksFetchedAt', Types::BIGINT);
		$this->addType('lastSyncTimestamp', Types::BIGINT);
		$this->addType('lastRevocationSyncTimestamp', Types::BIGINT);
		$this->addType('lastSyncAttempt', Types::BIGINT);
		$this->addType('lastSyncStatus', Types::STRING);
	}

	public static function create(string $remoteUrl): OlvidFederatedServer {
		$server = new OlvidFederatedServer();
		$server->setRemoteUrl($remoteUrl);
		return $server;
	}

	/**
	 * The domain used in the `uid@domain` federated identity convention: the remote URL
	 * with its scheme stripped.
	 */
	public function getDomain(): string {
		return OlvidFederatedServer::stripUrlScheme($this->getRemoteUrl());
	}

	/**
	 * Parses the cached JWKS document into the shape expected by Firebase\JWT\JWK::parseKeySet().
	 * @return array{keys: array}|null
	 */
	public function getDecodedJwksCache(): ?array {
		if ($this->jwksCache === null) {
			return null;
		}
		$decoded = json_decode($this->getJwksCache(), true);
		return is_array($decoded) && isset($decoded['keys']) ? $decoded : null;
	}


	/**
	 * Strips the scheme and any trailing slash from a URL, used throughout federation code
	 * for the `uid@domain` identity convention (domains only, no scheme).
	 */
	public static function stripUrlScheme(string $url): string {
		if (str_starts_with($url, 'https://')) {
			$url = substr($url, 8);
		} elseif (str_starts_with($url, 'http://')) {
			$url = substr($url, 7);
		}
		return rtrim($url, '/');
	}
}
