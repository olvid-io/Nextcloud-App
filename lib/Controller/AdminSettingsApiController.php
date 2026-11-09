<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\ResponseDefinitions;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Context\OlvidServer\LicenseKeyNotFoundException;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidDirectoryKeyException;
use OCA\Olvid\Utils\Context\OlvidServer\NetworkException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IL10N;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Admin-only endpoints backing the Olvid admin settings page (lib/Settings/Admin.php).
 *
 * @psalm-import-type OlvidAdminConfiguration from ResponseDefinitions
 *
 */
class AdminSettingsApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private readonly LoggerInterface $logger,
		private readonly IL10N $l,
		private readonly OlvidContext $context,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Returns the Olvid server configuration and whether it is valid
	 *
	 * @return DataResponse<Http::STATUS_OK, OlvidAdminConfiguration, array{}>
	 *
	 * 200: configuration returned
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'GET', url: '/app/admin/configuration')]
	public function configurationGet(): DataResponse {
		return new DataResponse($this->computeConfiguration());
	}

	/**
	 * Updates the Olvid server configuration and checks it against the Olvid server
	 *
	 * @param string $serverUrl Olvid distribution server url
	 * @param string $directoryKey key to authenticate against Olvid Server
	 * @return DataResponse<Http::STATUS_OK, OlvidAdminConfiguration, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Configuration saved, validity returned
	 * 400: Invalid server url
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'PUT', url: '/app/admin/configuration')]
	public function configurationPut(string $serverUrl = '', string $directoryKey = ''): DataResponse {
		$serverUrl = rtrim(trim($serverUrl), '/');
		if ($serverUrl !== '' && !$this->isValidHttpUrl($serverUrl)) {
			return new DataResponse(['error' => $this->l->t('Invalid server url')], Http::STATUS_BAD_REQUEST);
		}
		$this->context->nextcloud->appManager->setOlvidServerUrl($serverUrl);

		if ($directoryKey !== null) {
			$this->context->nextcloud->appManager->setDirectoryLicenseKey(trim($directoryKey));
		}

		$configuration = $this->computeConfiguration();

		// save if configuration is valid in app configuration
		$this->context->nextcloud->appManager->setIsOlvidServerConfigurationValid($configuration["server"]["isConfigurationValid"]);

		return new DataResponse($configuration);
	}

	/**
	 * Test an Olvid server configuration and checks it against the Olvid server
	 *
	 * @param string $serverUrl Olvid distribution server url
	 * @param string $directoryKey the key to use for the test
	 * @return DataResponse<Http::STATUS_OK, OlvidAdminServerConfigurationTest, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Configuration was tested
	 * 400: Invalid server url
	 * @noinspection PhpUnused
	 */
	#[ApiRoute(verb: 'POST', url: '/app/admin/configuration/server/test')]
	public function configurationServerTest(string $serverUrl = '', string $directoryKey = ''): DataResponse {
		$serverUrl = rtrim(trim($serverUrl), '/');
		if ($serverUrl === '' || !$this->isValidHttpUrl($serverUrl)) {
			return new DataResponse(['error' => $this->l->t('Invalid server url')], Http::STATUS_BAD_REQUEST);
		}

		[$isConfigurationValid, $message] = $directoryKey !== null
			? $this->checkCredentials($serverUrl, $directoryKey)
			: [false, $this->l->t('No directory license key configured. Send us an email to get one: contact@olvid.io')];

		return new DataResponse([
			'isConfigurationValid' => $isConfigurationValid,
			'message' => $message,
		]);
	}

	/*
	 * Compute configuration as expected by front end
	 */
	private function computeConfiguration(): array {
		$appManager = $this->context->nextcloud->appManager;
		$directoryKey = $appManager->getDirectoryLicenseKey();
		[$isConfigurationValid, $message] = $directoryKey !== null
			? $this->checkCredentials()
			: [false, $this->l->t('No directory license key configured. Send us an email to get one: contact@olvid.io')];

		return [
			'server' => [
				'serverUrl' => $appManager->getOlvidServerUrl(),
				'directoryKey' => $directoryKey,
				'isConfigurationValid' => $isConfigurationValid,
				'message' => $message,
			]
		];
	}

	/**
	 * @return array{bool, string}
	 */
	private function checkCredentials(?string $serverUrl = null, ?string $directoryKey = null): array {
		try {
			if ($this->context->olvidServer->requestCheckCredentials($serverUrl, $directoryKey)) {
				return [true, $this->l->t('Olvid server is reachable and license is valid.')];
			}
			return [false, $this->l->t('License key was rejected by the Olvid server.')];
		} catch (InvalidDirectoryKeyException|LicenseKeyNotFoundException) {
			return [false, $this->l->t('License key was rejected by the Olvid server.')];
		} catch (NetworkException) {
			return [false, $this->l->t('Cannot reach the Olvid server, check the server url.')];
		} catch (Throwable $e) {
			$this->logger->error('AdminSettingsApiController: checkCredentials failed', ['exception' => $e]);
			return [false, $this->l->t('Olvid server returned an unexpected error, check the server url.')];
		}
	}

	private function isValidHttpUrl(string $url): bool {
		if (filter_var($url, FILTER_VALIDATE_URL) === false) {
			return false;
		}
		$scheme = parse_url($url, PHP_URL_SCHEME);
		return $scheme === 'https' || $scheme === 'http';
	}
}
