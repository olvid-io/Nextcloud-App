<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\ConfigurationToolsUtil;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCA\OIDCIdentityProvider\Db\ClientMapper as OidcClientMapper;

/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {
	private IConfig $config;
	private IAppConfig $appConfig;
	private OidcClientMapper $oidcClientMapper;
	use ConfigurationToolsUtil;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		IAppConfig $appConfig,
		OidcClientMapper $clientMapper,

	) {
		parent::__construct($appName, $request);
		$this->config = $config;
		$this->appConfig = $appConfig;
		$this->oidcClientMapper = $clientMapper;
	}
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		$configurationLink = self::getServerConfigurationLink($this->appConfig, $this->oidcClientMapper, $this->request);
		return new TemplateResponse(
			Application::APP_ID,
			'index',
			['configurationLink' => $configurationLink]
		);
	}
}
