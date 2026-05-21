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
use OCA\Olvid\Api\Olvid\OlvidAppHandler;
use OCA\Olvid\Api\Olvid\PutKey\PutKey;
use OCA\Olvid\Api\Olvid\RequestChallenge\RequestChallenge;
use OCA\Olvid\Api\Olvid\Search\Search;
use OCA\Olvid\Api\Olvid\Verify\Verify;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\AppFramework\ApiController as IApiController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

// oidc dependencies

class OlvidApiController extends IApiController {
	// PROPOSAL: if this controller grows, consider splitting into:
	//   OlvidApiController       → ping, openid, olvid, jwks (no handlers)
	//   OlvidIdentityController  → me, putKey, getKey, search, getMagicSession
	//   OlvidSessionController   → verify, requestChallenge, getSession
	// Each controller would only inject the handlers it actually uses.
	public function __construct(
        string $appName,
        IRequest $request,
		private readonly IAppConfig $appConfig,
		private readonly IConfig $config,
        private readonly IUserManager $userManager,
		private readonly LoggerInterface $logger,
		private readonly DiscoveryGenerator $discoveryGenerator,
		private readonly IURLGenerator $urlGenerator,
		private readonly Me $meHandler,
		private readonly PutKey $putKeyHandler,
		private readonly GetKey $getKeyHandler,
		private readonly Search $searchHandler,
		private readonly Verify $verifyHandler,
		private readonly RequestChallenge $requestChallengeHandler,
		private readonly GetSession $getSessionHandler,
		private readonly GetMagicSession $getMagicSessionHandler,
    ) {
        parent::__construct($appName, $request);
    }

	/*
	 ** Public API
	 */
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

	// Expose our public key to allow clients to verify plugin signature
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

	/*
	 ** Engine authentication API
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/requestChallenge')]
	public function requestChallenge(): Response {
		return $this->requestChallengeHandler->handle();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getSession')]
	public function getSession(): Response {
		return $this->getSessionHandler->handle();
	}

	/*
	 ** App non-authenticated API
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getMagicSession')]
	public function getMagicSession(): Response {
		// unauthenticated entrypoint
		/** @noinspection PhpParamsInspection */
		return $this->getMagicSessionHandler->handle(null);
	}

	/*
	 ** App authenticated API
	 */
	#[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/me')]
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/me')]
    public function me(): Response {
		$user = $this->requiresAuth();
		if ($user === null) {
			return OlvidAppHandler::permissionDeniedDevice();
		}
        return $this->meHandler->handle($user);
    }

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/putKey')]
	public function putKey(): Response {
		$user = $this->requiresAuth();
		if ($user === null) {
			return OlvidAppHandler::permissionDeniedDevice();
		}
		return $this->putKeyHandler->handle($user);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getKey')]
	public function getKey(): Response {
		$user = $this->requiresAuth();
		if ($user === null) {
			return OlvidAppHandler::permissionDeniedDevice();
		}
		return $this->getKeyHandler->handle($user);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/search')]
	public function listUsers(): Response {
		$user = $this->requiresAuth();
		if ($user === null) {
			return OlvidAppHandler::permissionDeniedDevice();
		}
		return $this->searchHandler->handle($user);
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/verify')]
	public function verify(): Response {
		$user = $this->requiresAuth();
		if ($user === null) {
			return OlvidAppHandler::permissionDeniedDevice();
		}
		return $this->verifyHandler->handle($user);
	}

	private function requiresAuth(): ?IUser {
		if (!$this->request->getHeader("Authorization")) {
			$this->logger->error('Missing authentication header');
			return null;
		}

		// parse token
		$rawHeader = $this->request->getHeader("Authorization");
		$token = str_starts_with(strtolower($rawHeader), "bearer ") ? trim(substr($rawHeader, 7)) : $rawHeader;

		// parse token
		try {
			$publicKey = AppConfigManager::getJwkKeyPublicKey($this->appConfig);
			$decoded = JWT::decode($token, new Key($publicKey, 'ES256'));
		} catch (Exception $e) {
			$this->logger->error('Bearer token is not a valid app-issued JWT: ' . $e->getMessage());
			return null;
		}

		if ($decoded->type !== "session") {
			$this->logger->error("Invalid JWK key type: " . $decoded->type);
			return null;
		}

		$user = $this->userManager->get($decoded->sub);
		$this->logger->debug($decoded->sub . ' logged in using bearer token');

		// check token was not revoked
		$sessionsRevokedBefore = $this->config->getUserValue(
			$user->getUID(),
			Application::APP_ID,
			Constants::USER_ATTRIBUTE_OLVID_SESSION_REVOKED_BEFORE
		);
		if ($sessionsRevokedBefore !== null && $sessionsRevokedBefore != 0) {
			// if token was issued before last revocation ignore it
			if ($decoded->iat <= $sessionsRevokedBefore) {
				$this->logger->debug($decoded->sub . ' token expired');
				return null;
			}
		}

		return $user;
	}
}
