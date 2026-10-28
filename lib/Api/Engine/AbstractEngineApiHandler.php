<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Engine;

use Exception;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Http\BinaryResponse;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Encoded;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Base class for binary Olvid Engine API endpoints.
 *
 * Request and response bodies use the binary Encoded protocol as `application/octet-stream`.
 * All responses are Encoded lists whose first element is a single-byte status code.
 *
 * Subclasses implement handler(string $rawInput): BinaryResponse and use the
 * protected error helpers (parsingError, permissionDenied, generalError) to return
 * standard failure responses without repeating the Encoded framing boilerplate.
 */
abstract class AbstractEngineApiHandler {
	// Status bytes shared across all engine endpoints (from AbstractEngineEntryPoint)
	protected const STATUS_OK = "\x00";

	protected const STATUS_NOT_FOUND = "\x09";

	protected const STATUS_PERMISSION_DENIED = "\x0e";

	protected const STATUS_PARSING_ERROR = "\xfe";

	protected const STATUS_GENERAL_ERROR = "\xff";

	// cache is used to store session challenges (no need to persist them)
	private ?ICache $cache = null;

	public function __construct(
		private readonly ICacheFactory $cacheFactory,
		protected readonly LoggerInterface $logger,
		protected readonly OlvidContext $context,
	) {
	}

	/**
	 * Entry point called by the controller. Reads the raw request body and
	 * dispatches to handler(), wrapping any unhandled exception as a general error.
	 */
	public function handle(): BinaryResponse {
		$rawInput = (string)file_get_contents('php://input');
		try {
			return $this->handler($rawInput);
		} catch (Exception $exception) {
			$this->logger->error(static::class . ': unexpected exception', ['exception' => $exception]);
			return $this->generalError();
		}
	}

	// -------------------------------------------------------------------------
	// Session cache
	// if a distributed cache is not available try to create in memory cache as a fallback
	// -------------------------------------------------------------------------
	protected function getCache(): ?ICache {
		if ($this->cache !== null) {
			return $this->cache;
		}

		try {
			$this->cache = $this->cacheFactory->createDistributed(Application::APP_ID);
			return $this->cache;
		} catch (Throwable $throwable) {
			$this->logger->error(static::class . ': cannot create distributed cache, fallback to memory cache', ['exception' => $throwable]);
		}

		try {
			$this->cache = $this->cacheFactory->createInMemory();
			return $this->cache;
		} catch (Throwable $throwable) {
			$this->logger->error(static::class . ': cannot create memory cache, fallback to local cache', ['exception' => $throwable]);
		}

		$this->cache = $this->cacheFactory->createLocal();
		return $this->cache;
	}

	abstract protected function handler(string $rawInput): BinaryResponse;

	// -------------------------------------------------------------------------
	// Error response helpers
	// -------------------------------------------------------------------------

	protected function notFound(): BinaryResponse {
		return $this->statusResponse(self::STATUS_NOT_FOUND);
	}

	protected function parsingError(): BinaryResponse {
		return $this->statusResponse(self::STATUS_PARSING_ERROR);
	}

	protected function permissionDenied(): BinaryResponse {
		return $this->statusResponse(self::STATUS_PERMISSION_DENIED);
	}

	protected function generalError(): BinaryResponse {
		return $this->statusResponse(self::STATUS_GENERAL_ERROR);
	}

	private function statusResponse(string $status): BinaryResponse {
		return new BinaryResponse(Encoded::encodeList([
			Encoded::encodeBytes($status),
		]));
	}
}
