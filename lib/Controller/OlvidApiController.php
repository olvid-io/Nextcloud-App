<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\GetKey\GetKey;
use OCA\Olvid\Api\Me\Me;
use OCA\Olvid\Api\PutKey\PutKey;
use OCA\Olvid\Api\Search\Search;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\AppConfigManager;
use OCA\Olvid\Utils\ServerConfigurationUtils;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\ApiController as IApiController;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;

// oidc dependencies
use OCA\OIDCIdentityProvider\Util\DiscoveryGenerator;
use OCA\OIDCIdentityProvider\Db\ClientMapper as OidcClientMapper;
use OCA\OIDCIdentityProvider\Event\TokenValidationRequestEvent as OidcTokenValidationRequestEvent;

class OlvidApiController extends IApiController {
    private IConfig $config;
	private IAppConfig $appConfig;
    private IUserManager $userManager;
    private IAccountManager $accountManager;
    private IUserSession $userSession;
    private IGroupManager $groupManager;
	private IEventDispatcher $eventDispatcher;
	private LoggerInterface $logger;
	private OidcClientMapper $oidcClientMapper;
	private DiscoveryGenerator $discoveryGenerator;
	private IURLGenerator $urlGenerator;

	public function __construct(
        string $appName,
        IRequest $request,
		IConfig $config,
		IAppConfig $appConfig,
        IUserManager $userManager,
        IAccountManager $accountManager,
        IUserSession $userSession,
        IGroupManager $groupManager,
		IEventDispatcher $eventDispatcher,
		LoggerInterface $logger,
		OidcClientMapper $clientMapper,
		DiscoveryGenerator $discoveryGenerator,
		IURLGenerator $urlGenerator,

    ) {
        parent::__construct($appName, $request);
		$this->config = $config;
		$this->appConfig = $appConfig;
        $this->userManager = $userManager;
        $this->accountManager = $accountManager;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
		$this->eventDispatcher = $eventDispatcher;
		$this->logger = $logger;
		$this->oidcClientMapper = $clientMapper;
		$this->discoveryGenerator = $discoveryGenerator;
		$this->urlGenerator = $urlGenerator;
    }

    #[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/olvid-rest/ping')]
    public function ping(): TextPlainResponse {
		return new TextPlainResponse("pong");
    }

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/reset')]
	public function reset(): TextPlainResponse {
		$user = $this->getUser();
		if ($user) {
			$this->resetUser($user);
			return new TextPlainResponse("reset " . $user->getDisplayName());
		}
		else {
			return new TextPlainResponse("not logged in");
		}
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/resetAll')]
	public function resetAll(): TextPlainResponse {
		$users = $this->userManager->search("");
		foreach ($users as $user) {
			$this->resetUser($user);
		}
		return new TextPlainResponse("reset " . count($users) . " users");
	}

	private function resetUser(Iuser $user): void {
		try {
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_COMPANY, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_POSITION, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IDENTITY, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_NONCE, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_ROLE, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_FULL_SEARCH_FIELD, "");
			$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_IS_BOT, "");
		} catch (PreConditionNotMetException) {}
	}

	/*
	 * Proxy to oidc application openid-configuration file content
	 * We override jwks url to use our own key to sign user details
	 */
    #[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/.well-known/openid-configuration')]
    public function openid(): Response {
		$discoveryResponse = $this->discoveryGenerator->generateDiscovery($this->request);
		$patchedData = $discoveryResponse->getData();
		// TODO how to properly generate url
		$patchedData["jwks_uri"] = $this->urlGenerator->linkToOCSRouteAbsolute("") . "/apps/olvid/.well-known/jwks";
		$discoveryResponse->setData($patchedData);
        return $discoveryResponse;
    }

	/*
	 * Expose our public key to allow clients to verify plugin signature
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/.well-known/jwks')]
	public function jwks(): Response {
		$jwks = [
			'keys' => [
				[
					'kty' => 'EC',
					'crv' => 'P-256',
					'x'   => AppConfigManager::getJwkKeyPublicKeyX($this->appConfig),
					'y'   => AppConfigManager::getJwkKeyPublicKeyY($this->appConfig),
					'use' => 'sig',
					'kid' => AppConfigManager::getJwkKeyId($this->appConfig),
					'alg' => 'ES256'
				]
			]
		];
		return new JSONResponse($jwks);
	}


	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/me')]
	public function meGet(): Response {
		// TODO check  user authentication !!

		return (new Me($this->config, $this->appConfig, $this->userManager, $this->accountManager, $this->userSession, $this->groupManager, $this->logger))->handle($this->getUser(), $this->request);
	}

	#[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[ApiRoute(verb: 'POST', url: '/olvid-rest/me')]
    public function mePost(): Response {
		// TODO check  user authentication !!

        return (new Me($this->config, $this->appConfig, $this->userManager, $this->accountManager, $this->userSession, $this->groupManager, $this->logger))->handle($this->getUser(), $this->request);
    }

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/putKey')]
	public function putKey(): Response {
		// TODO check  user authentication !!
		return (new PutKey($this->config, $this->appConfig, $this->userManager, $this->accountManager, $this->userSession, $this->groupManager, $this->logger))->handle($this->getUser(), $this->request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getKey')]
	public function getKey(): Response {
		// TODO check  user authentication ??!!
		return (new GetKey($this->config, $this->appConfig, $this->userManager, $this->accountManager, $this->userSession, $this->groupManager, $this->logger))->handle($this->getUser(), $this->request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/search')]
	public function listUsers(): Response {
		return (new Search($this->config, $this->appConfig, $this->userManager, $this->accountManager, $this->userSession, $this->groupManager, $this->logger))->handle($this->getUser(), $this->request);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/configuration')]
	public function configuration(): Response {
		try {
			$response = ServerConfigurationUtils::getServerConfigurationLink($this->appConfig, $this->oidcClientMapper, $this->request);
			return new TextPlainResponse($response);
		} catch (Exception $e) {
			$this->logger->error("Cannot generate configuration link: ". $e);
			return new Response(500);
		}
	}

	/*
	 * Get user that made request (if authenticated)
	 * This handle oidc token and session cookie authentication
	 */
	private function getUser(): ?IUser {
		$user = null;
		if ($this->userSession->isLoggedIn()) {
			$user = $this->userSession->getUser();
		}
		else if (class_exists(OidcTokenValidationRequestEvent::class)) {
			$token = $this->request->getHeader("Authorization");
			if (str_starts_with(strtolower($token), "bearer ")) {
				$token = trim(substr($token, 7));
			}
			$event = new OidcTokenValidationRequestEvent($token);
			$this->eventDispatcher->dispatchTyped($event);
			if ($event->getIsValid()) {
				$userId = $event-> getUserId();
				$user = $this->userManager->get($userId);
				$this->logger->debug('The provided token is valid and was issued for user ' . $userId);
			} else {
				$this->logger->debug('The provided token is invalid');
			}
		} else {
			$this->logger->debug('The oidc app is not installed/available');
		}
		return $user;
	}
}
