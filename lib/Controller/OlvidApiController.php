<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\GetKey\GetKey;
use OCA\Olvid\Api\GetMagicSession\GetMagicSession;
use OCA\Olvid\Api\GetSession\GetSession;
use OCA\Olvid\Api\Me\Me;
use OCA\Olvid\Api\PutKey\PutKey;
use OCA\Olvid\Api\RequestChallenge\RequestChallenge;
use OCA\Olvid\Api\Search\Search;
use OCA\Olvid\Api\Verify\Verify;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\ApiController as IApiController;
use OCP\IAppConfig;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

// oidc dependencies
use OCA\OIDCIdentityProvider\Util\DiscoveryGenerator;

class OlvidApiController extends IApiController {
	public function __construct(
        string $appName,
        IRequest $request,
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
        private readonly IUserManager $userManager,
        private readonly IAccountManager $accountManager,
        private readonly IUserSession $userSession,
        private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
		private readonly DiscoveryGenerator $discoveryGenerator,
		private readonly IURLGenerator $urlGenerator,
		private readonly ICacheFactory $cacheFactory,
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

	// directory well known
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/.well-known/olvid')]
	public function olvid(): Response {
		$response["supportIdentityAuthentication"] = true;
		$response["apiVersion"] = Constants::OLVID_DIRECTORY_API_VERSION;
		$minBuildVersions["android"] = Constants::MIN_BUILD_ANDROID;
		$minBuildVersions["ios"] = Constants::MIN_BUILD_IOS;
		$minBuildVersions["desktop"] = Constants::MIN_BUILD_DESKTOP;
		$minBuildVersions["daemon"] = Constants::MIN_BUILD_DAEMON;
		$response["minBuildVersions"] = $minBuildVersions;
		return new JSONResponse($response);
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
