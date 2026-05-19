<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\ServerConfigurationUtils;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IRequest;
use OCA\OIDCIdentityProvider\Db\ClientMapper as OidcClientMapper;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	private IAppConfig $appConfig;
	private OidcClientMapper $oidcClientMapper;
	private LoggerInterface $logger;
	private IURLGenerator $urlGenerator;

	public function __construct(
		string $appName,
		IRequest $request,
		IAppConfig $appConfig,
		OidcClientMapper $clientMapper,
		LoggerInterface $logger,
		IURLGenerator $urlGenerator
	) {
		parent::__construct($appName, $request);
		$this->appConfig = $appConfig;
		$this->oidcClientMapper = $clientMapper;
		$this->logger = $logger;
		$this->urlGenerator = $urlGenerator;

	}
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		try {
			$configurationLink = ServerConfigurationUtils::getServerConfigurationLink($this->appConfig, $this->oidcClientMapper, $this->urlGenerator);
		} catch (Exception $e) {
			$this->logger->error(get_class($this) . ": cannot generate configuration link: " . $e);
			// TODO handle error (show error in window ?)
			$configurationLink = "";
		}
		return new TemplateResponse(
			Application::APP_ID,
			'index',
			['configurationLink' => $configurationLink]
		);
	}
}
