<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\OIDCIdentityProvider\Util\DiscoveryGenerator;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Device\GetKey;
use OCA\Olvid\Api\Device\GetMagicSession;
use OCA\Olvid\Api\Device\Groups;
use OCA\Olvid\Api\Device\ListUsers;
use OCA\Olvid\Api\Device\Me;
use OCA\Olvid\Api\Device\PutKey;
use OCA\Olvid\Api\Device\Search;
use OCA\Olvid\Api\Engine\GetSession;
use OCA\Olvid\Api\Engine\RequestChallenge;
use OCA\Olvid\Api\Engine\Verify;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\AppFramework\ApiController as IApiController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

// oidc dependencies

class DirectoryApiController extends IApiController {
	// PROPOSAL: if this controller grows, consider splitting into:
	//   DirectoryApiController       → ping, openid, olvid, jwks (no handlers)
	//   OlvidIdentityController  → me, putKey, getKey, search, getMagicSession
	//   OlvidSessionController   → verify, requestChallenge, getSession
	// Each controller would only inject the handlers it actually uses.
	public function __construct(
        string $appName,
        IRequest $request,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly DiscoveryGenerator $discoveryGenerator,
		private readonly IURLGenerator $urlGenerator,
		private readonly Me $meHandler,
		private readonly PutKey $putKeyHandler,
		private readonly GetKey $getKeyHandler,
		private readonly Search $searchHandler,
		private readonly ListUsers $listUsersHandler,
		private readonly Groups $groupsHandler,
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
					'x'   => $this->olvidAppConfig->getJwkKeyPublicKeyX(),
					'y'   => $this->olvidAppConfig->getJwkKeyPublicKeyY(),
					'use' => 'sig',
					'kid' => $this->olvidAppConfig->getJwkKeyId(),
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
		return $this->getMagicSessionHandler->handle();
	}

	/*
	 ** App authenticated API
	 */
	// we use GET method when calling /me on identity creation
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/me')]
	public function meGet(): Response {
		return $this->meHandler->handle();
	}

	#[PublicPage]
    #[NoCSRFRequired]
    #[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/me')]
    public function mePost(): Response {
        return $this->meHandler->handle();
    }

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/putKey')]
	public function putKey(): Response {
		return $this->putKeyHandler->handle();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getKey')]
	public function getKey(): Response {
		return $this->getKeyHandler->handle();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/search')]
	public function search(): Response {
		return $this->searchHandler->handle();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/listUsers')]
	public function list(): Response {
		return $this->listUsersHandler->handle();
	}

	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/verify')]
	public function verify(): Response {
		return $this->verifyHandler->handle();
	}

 	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/groups')]
	public function groups(): Response {
		return $this->groupsHandler->handle();
	}
}
