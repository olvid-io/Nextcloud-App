<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OCA\OIDCIdentityProvider\Util\DiscoveryGenerator;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\GetKey;
use OCA\Olvid\Api\Olvid\GetMagicSession\GetMagicSession;
use OCA\Olvid\Api\Olvid\GetSession\GetSession;
use OCA\Olvid\Api\Olvid\Me;
use OCA\Olvid\Api\Olvid\PutKey\PutKey;
use OCA\Olvid\Api\Olvid\RequestChallenge\RequestChallenge;
use OCA\Olvid\Api\Olvid\Search\Search;
use OCA\Olvid\Api\Olvid\Verify\Verify;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\ApiController as IApiController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TextPlainResponse;
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
	// TODO todel, but how ? ... (app check for it as first step of )
	#[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
    #[ApiRoute(verb: 'GET', url: '/.well-known/openid-configuration')]
    public function openid(): Response {
		// alternative try 1: get well known and returns it, fail in that configuration because certificate are not correct
//		$wellKnownUrl = $this->urlGenerator->getAbsoluteURL("/.well-known/openid-configuration");
//		$wellKnownContent = json_decode(file_get_contents($wellKnownUrl));
//		return new JSONResponse($wellKnownContent);
		// alternative try 2: forge a minimalist well known (miss a lot of fields)
//		$response["issuer"] = $this->urlGenerator->getBaseUrl();
//		$response["jwks_uri"] = $this->urlGenerator->linkToOCSRouteAbsolute("") . "/apps/olvid/.well-known/jwks";
//		return new JsonResponse($response);
		$discoveryResponse = $this->discoveryGenerator->generateDiscovery($this->request);
		$patchedData = $discoveryResponse->getData();
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
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/me')]
    public function me(): Response {
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
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/verify')]
	public function verify(): Response {
		return (new Verify($this->config, $this->appConfig, $this->userManager, $this->cacheFactory, $this->logger))->handle();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/requestChallenge')]
	public function requestChallenge(): Response {
		return (new RequestChallenge($this->config, $this->appConfig, $this->userManager, $this->cacheFactory, $this->logger))->handle();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getSession')]
	public function getSession(): Response {
		return (new GetSession($this->config, $this->appConfig, $this->userManager, $this->cacheFactory, $this->logger))->handle();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getMagicSession')]
	public function getMagicSession(): Response {
		return (new GetMagicSession($this->config, $this->appConfig, $this->userManager, $this->accountManager, $this->userSession, $this->groupManager, $this->logger))->handle($this->getUser(), $this->request);
	}

	/*
	 * Get user that made request (if authenticated)
	 * This handle oidc token and session cookie authentication
	 */
	// TODO not sure this is the right way to do
	private function getUser(): ?IUser {
		if ($this->request->getHeader("Authorization")) {
			// Try validating as our own app-issued JWT
			$rawHeader = $this->request->getHeader("Authorization");
			$token = str_starts_with(strtolower($rawHeader), "bearer ") ? trim(substr($rawHeader, 7)) : $rawHeader;

			try {
				$publicKey = AppConfigManager::getJwkKeyPublicKey($this->appConfig);
				$decoded = JWT::decode($token, new Key($publicKey, 'ES256'));
				if ($decoded->type !== "session") {
					throw new Exception("Invalid JWK key type: " . $decoded->type);
				}
				$user = $this->userManager->get($decoded->sub);
				$this->logger->debug($decoded->sub . ' logged in using bearer token');
				return $user;
			} catch (Exception $e) {
				$this->logger->error('Bearer token is not a valid app-issued JWT: ' . $e->getMessage());
			}
		} else {
			$this->logger->error('Missing authorization header');
		}
		return null;
	}
}
