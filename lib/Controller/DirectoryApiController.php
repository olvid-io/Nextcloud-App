<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Directory\GetKey;
use OCA\Olvid\Api\Directory\GetMagicSession;
use OCA\Olvid\Api\Directory\Groups;
use OCA\Olvid\Api\Directory\ListUsers;
use OCA\Olvid\Api\Directory\Me;
use OCA\Olvid\Api\Directory\PutKey;
use OCA\Olvid\Api\Directory\Search;
use OCA\Olvid\Api\Engine\GetData;
use OCA\Olvid\Api\Engine\GetSession;
use OCA\Olvid\Api\Engine\RequestChallenge;
use OCA\Olvid\Api\Engine\RevocationTest;
use OCA\Olvid\Api\Engine\Verify;
use OCA\Olvid\ResponseDefinitions;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * @psalm-import-type OlvidDirectoryApiError from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryApiSuccess from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryOlvidDiscovery from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryJwks from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryOpenIdConfiguration from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryMeResponse from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryGetMagicSessionResponse from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryGetKeyResponse from ResponseDefinitions
 * @psalm-import-type OlvidDirectorySearchResponse from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryListUsersResponse from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryGroupsResponse from ResponseDefinitions
 */
class DirectoryApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly IURLGenerator $urlGenerator,
		private readonly Me $meHandler,
		private readonly PutKey $putKeyHandler,
		private readonly GetKey $getKeyHandler,
		private readonly Search $searchHandler,
		private readonly ListUsers $listUsersHandler,
		private readonly Groups $groupsHandler,
		private readonly RevocationTest $revocationTest,
		private readonly Verify $verifyHandler,
		private readonly RequestChallenge $requestChallengeHandler,
		private readonly GetSession $getSessionHandler,
		private readonly GetData $getDataHandler,
		private readonly GetMagicSession $getMagicSessionHandler,
	) {
		parent::__construct($appName, $request);
	}

	// ── Public utility ───────────────────────────────────────────────────────
	/**
	 * This is a fake route used to compute the nextcloud server url to pass in
	 * magic links.
	 */
	#[ApiRoute(verb: 'GET', url: '/')]
	public function index(): void {
	}

	/**
	 * @return TextPlainResponse<Http::STATUS_OK, string>
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/ping')]
	public function ping(): TextPlainResponse {
		return new TextPlainResponse('pong');
	}

	// ── Well-known discovery endpoints ───────────────────────────────────────

	/**
	 * Legacy well-known endpoint; only jwks_uri field is actively used by Olvid clients
	 *
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryOpenIdConfiguration, array{}>
	 * @noinspection PhpUnused
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/.well-known/openid-configuration')]
	public function openid(): Response {
		$discoveryPayload = [
			'issuer' => $this->urlGenerator->getBaseUrl(),
			'authorization_endpoint' => '',
			'token_endpoint' => '',
			'userinfo_endpoint' => '',
			'jwks_uri' => $this->urlGenerator->linkToOCSRouteAbsolute('olvid.directoryApi.jwks'),
			'scopes_supported' => [],
			'response_types_supported' => [],
			'response_modes_supported' => [],
			'grant_types_supported' => [],
			'acr_values_supported' => [],
			'subject_types_supported' => [],
			'id_token_signing_alg_values_supported' => [],
			'token_endpoint_auth_methods_supported' => [],
			'display_values_supported' => [],
			'claim_types_supported' => [],
			'claims_supported' => [],
			'end_session_endpoint' => '',
		];

		$response = new JSONResponse($discoveryPayload);
		$response->addHeader('Access-Control-Allow-Origin', '*');
		$response->addHeader('Access-Control-Allow-Methods', 'GET');

		return $response;
	}

	/**
	 * Olvid directory discovery: supported API version and minimum client build numbers
	 *
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryOlvidDiscovery, array{}>
	 * @noinspection PhpUnused
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/.well-known/olvid')]
	public function olvid(): Response {
		$response['supportIdentityAuthentication'] = true;
		$response['apiVersion'] = Constants::OLVID_DIRECTORY_API_VERSION;
		$minBuildVersions['android'] = Constants::MIN_BUILD_ANDROID;
		$minBuildVersions['ios'] = Constants::MIN_BUILD_IOS;
		$minBuildVersions['desktop'] = Constants::MIN_BUILD_DESKTOP;
		$minBuildVersions['daemon'] = Constants::MIN_BUILD_DAEMON;
		$response['minBuildVersions'] = $minBuildVersions;
		return new JSONResponse($response);
	}

	/**
	 * JWKS endpoint — exposes the server's EC public key for client signature verification
	 *
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryJwks, array{}>
	 * @noinspection PhpUnused
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
					'x' => $this->olvidAppConfig->getJwkKeyPublicKeyX(),
					'y' => $this->olvidAppConfig->getJwkKeyPublicKeyY(),
					'use' => 'sig',
					'kid' => $this->olvidAppConfig->getJwkKeyId(),
					'alg' => 'ES256'
				]
			]
		];
		return new JSONResponse($jwks);
	}

	// ── Engine authentication API (binary protocol — excluded from OpenAPI spec) ──

	/**
	 * Engine auth step 1: request a challenge
	 * Binary protocol; not representable in OpenAPI
	 * @noinspection PhpUnused
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/requestChallenge')]
	public function requestChallenge(): Response {
		return $this->requestChallengeHandler->handle();
	}

	/**
	 * Engine auth step 2: exchange signed challenge for a session token
	 * Binary protocol; not representable in OpenAPI
	 * @noinspection PhpUnused
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getSession')]
	public function getSession(): Response {
		return $this->getSessionHandler->handle();
	}

	// ── Engine data API (binary protocol — excluded from OpenAPI spec) ──
	/**
	 * Engine data retrieval by UID
	 * Binary protocol; not representable in OpenAPI
	 * @noinspection PhpUnused
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getData')]
	public function getData(): Response {
		return $this->getDataHandler->handle();
	}

	// ── App unauthenticated API ───────────────────────────────────────────────

	/**
	 * Exchanges a per-user magic token for a short-lived bearer session token (1 hour)
	 *
	 * @param string $username Nextcloud user ID
	 * @param string $token Single-use magic token generated by the Magic Link flow
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryGetMagicSessionResponse|OlvidDirectoryApiError, array{}>
	 * @noinspection PhpUnused
	 * @noinspection PhpDocSignatureInspection
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getMagicSession')]
	public function getMagicSession(): Response {
		return $this->getMagicSessionHandler->handle();
	}

	// ── App authenticated API (Bearer JWT required) ───────────────────────────

	/**
	 * Returns the caller's directory profile, API key, push topics, and pending revocations
	 * Used on identity creation (GET variant)
	 *
	 * @param string $deviceUid Optional device UID to register
	 * @param int $timestamp Millisecond timestamp; only revocations newer than this are returned
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryMeResponse|OlvidDirectoryApiError, array{}>
	 * @noinspection PhpUnused
	 * @noinspection PhpDocSignatureInspection
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/olvid-rest/me')]
	public function meGet(): Response {
		return $this->meHandler->handle();
	}

	/**
	 * Returns the caller's directory profile, API key, push topics, and pending revocations
	 * Used on subsequent syncs (POST variant)
	 *
	 * @param string $deviceUid Optional device UID to register
	 * @param int $timestamp Millisecond timestamp; only revocations newer than this are returned
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryMeResponse|OlvidDirectoryApiError, array{}>
	 * @noinspection PhpUnused
	 * @noinspection PhpDocSignatureInspection
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/me')]
	public function mePost(): Response {
		return $this->meHandler->handle();
	}

	/**
	 * Registers or replaces the caller's Olvid identity (base64-encoded) in the directory
	 *
	 * @param string $identity Base64-encoded Olvid identity bytes
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryApiSuccess|OlvidDirectoryApiError, array{}>
	 * @noinspection PhpUnused
	 * @noinspection PhpDocSignatureInspection
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/putKey')]
	public function putKey(): Response {
		return $this->putKeyHandler->handle();
	}

	/**
	 * Retrieves the signed identity details of another directory user
	 * Note: the request body uses "user-id" which cannot be a PHP identifier;
	 * the handler reads it directly from php://input
	 *
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryGetKeyResponse|OlvidDirectoryApiError, array{}>
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/getKey')]
	public function getKey(): Response {
		return $this->getKeyHandler->handle();
	}

	/**
	 * Searches the directory by display name or identity fields
	 *
	 * @param string $filter Search string; empty returns all users up to the server limit
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectorySearchResponse|OlvidDirectoryApiError, array{}>
	 * @noinspection PhpDocSignatureInspection
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/search')]
	public function search(): Response {
		return $this->searchHandler->handle();
	}

	/**
	 * Returns all enrolled users, optionally filtered to those registered after a timestamp
	 *
	 * @param int $timestamp Millisecond timestamp; 0 returns all users
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryListUsersResponse|OlvidDirectoryApiError, array{}>
	 * @noinspection PhpDocSignatureInspection
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/listUsers')]
	public function list(): Response {
		return $this->listUsersHandler->handle();
	}

	/**
	 * Verifies an ES256 JWT signature issued by the directory server
	 * Binary response protocol; not representable in OpenAPI
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/verify')]
	public function verify(): Response {
		return $this->verifyHandler->handle();
	}

	/**
	 * Returns signed group blobs, deletions, and kicks updated since the given timestamp
	 *
	 * @param int|null $timestamp Millisecond timestamp; null defaults to last 60 days
	 * @return DataResponse<Http::STATUS_OK, OlvidDirectoryGroupsResponse|OlvidDirectoryApiError, array{}>
	 * @noinspection PhpDocSignatureInspection
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/groups')]
	public function groups(): Response {
		return $this->groupsHandler->handle();
	}

	/**
	 * Tests whether a given nonce is present in the revocation list
	 * Binary response protocol; not representable in OpenAPI
	 * @noinspection PhpUnused
	 */
	#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/olvid-rest/revocationTest')]
	public function revocationTest(): Response {
		return $this->revocationTest->handle();
	}
}
