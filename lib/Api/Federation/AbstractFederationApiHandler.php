<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Federation;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Db\OlvidFederatedServer;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IRequest;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * FederationApi:
 *   Server-to-server entrypoints under /federations/*, called by trusted Nextcloud
 *   instances' own sync jobs — never by Olvid client devices.
 *
 *   Authentication is the shared secret Nextcloud core's `federation` app already
 *   establishes between mutually trusted instances (see FederationTrust), not the device
 *   bearer-token scheme AbstractAuthenticatedDeviceApiHandler uses. The caller must supply
 *   the exact URL Nextcloud core knows it by (`callerUrl`) and that server's shared secret.
 */
abstract class AbstractFederationApiHandler {
	protected ?string $callerUrl = null;

	public function __construct(
		protected readonly IRequest $request,
		protected readonly LoggerInterface $logger,
		protected readonly OlvidContext $context,
		protected readonly OlvidAppConfigManager $appConfig,
		protected readonly IURLGenerator $urlGenerator,
	) {
	}

	public function handle(): JSONResponse|Response {
		if (!$this->appConfig->isFederationEnabled()) {
			return new JSONResponse([Constants::FEDERATION_RESPONSE_ERROR => 'federation_disabled'], Http::STATUS_SERVICE_UNAVAILABLE);
		}

		try {
			$jsonParameters = json_decode(file_get_contents('php://input'), true) ?? [];
		} catch (Exception $exception) {
			$this->logger->error(get_class($this) . ': cannot parse request', ['exception' => $exception]);
			return new JSONResponse([Constants::FEDERATION_RESPONSE_ERROR => 'invalid_request'], Http::STATUS_BAD_REQUEST);
		}

		$callerUrl = isset($jsonParameters[Constants::FEDERATION_REQUEST_CALLER_URL]) ? (string)$jsonParameters[Constants::FEDERATION_REQUEST_CALLER_URL] : '';
		$sharedSecret = isset($jsonParameters[Constants::FEDERATION_REQUEST_SHARED_SECRET]) ? (string)$jsonParameters[Constants::FEDERATION_REQUEST_SHARED_SECRET] : '';

		if ($callerUrl === '' || $sharedSecret === '') {
			return new JSONResponse([Constants::FEDERATION_RESPONSE_ERROR => 'missing_authentication'], Http::STATUS_FORBIDDEN);
		}

		if (!$this->context->federation->isTrustedServer($callerUrl)) {
			$this->logger->warning('FederationApi: untrusted caller: ' . $callerUrl);
			return new JSONResponse([Constants::FEDERATION_RESPONSE_ERROR => 'untrusted_server'], Http::STATUS_FORBIDDEN);
		}

		$expectedSecret = $this->context->federation->getServerSharedSecretOrNull($callerUrl);
		if ($expectedSecret === null || !hash_equals($expectedSecret, $sharedSecret)) {
			$this->logger->warning('FederationApi: invalid shared secret for caller: ' . $callerUrl);
			return new JSONResponse([Constants::FEDERATION_RESPONSE_ERROR => 'invalid_shared_secret'], Http::STATUS_FORBIDDEN);
		}

		$this->callerUrl = $callerUrl;

		try {
			return $this->handler($jsonParameters);
		} catch (Exception $exception) {
			$this->logger->error(get_class($this) . ': unexpected exception in handler', ['exception' => $exception]);
			return new JSONResponse([Constants::FEDERATION_RESPONSE_ERROR => 'internal_error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	abstract public function handler(array $jsonParameters): Response;

	/** This instance's own domain, as used in the `uid@domain` federated identity convention. */
	protected function getOwnDomain(): string {
		return OlvidFederatedServer::stripUrlScheme($this->urlGenerator->getBaseUrl());
	}
}
