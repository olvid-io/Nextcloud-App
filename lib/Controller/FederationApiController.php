<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use OCA\Olvid\Api\Federation\ExportRevocations;
use OCA\Olvid\Api\Federation\ExportUsers;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * Server-to-server federation API. Called by trusted Nextcloud instances' own sync jobs,
 * never by Olvid client devices — see AbstractFederationApiHandler for the auth model.
 */
#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class FederationApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly ExportUsers $exportUsersHandler,
		private readonly ExportRevocations $exportRevocationsHandler,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @noinspection PhpUnused
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/federations/users')]
	public function users(): Response {
		return $this->exportUsersHandler->handle();
	}

	/**
	 * @noinspection PhpUnused
	 */
	#[PublicPage]
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'POST', url: '/federations/revocations')]
	public function revocations(): Response {
		return $this->exportRevocationsHandler->handle();
	}
}
