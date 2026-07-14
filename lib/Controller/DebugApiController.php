<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

// TODO TODEL
#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class DebugApiController extends OCSController {
	public function __construct(
		IRequest $request,
		private readonly IAppConfig $appConfig,
		private readonly IUserSession $userSession,
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/debug')]
	public function debug(): JSONResponse {
		$appFields = [];
		try {
			$appFields = $this->appConfig->getAllValues(Application::APP_ID);
		} catch (Exception $e) {
			$this->logger->error('debug: Cannot compute user fields: ' . $e);
		}

		$userFields = [];
		try {
			$olvidUser = $this->context->db->user->getByUserIdOrNull($this->userSession->getUser()->getUID());
			$userFields = $olvidUser?->jsonSerialize() ?? [];
		} catch (Exception $e) {
			$this->logger->error('debug: Cannot compute user fields: ' . $e);
		}

		return new JSONResponse([
			'app' => $appFields,
			'user' => $userFields,
		], 200);
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/users')]
	public function users(): JSONResponse {
		$users = [];
		foreach ($this->context->nextcloud->userManager->search('') as $user) {
			try {
				$olvidUser = $this->context->db->user->getByUserIdOrNull($user->getUID());
				$users[$user->getUID()] = $olvidUser?->jsonSerialize();
			} catch (Exception $e) {
				$this->logger->error('debug: Cannot compute user fields: ' . $e);
			}
		}

		return new JSONResponse([
			'users' => $users,
		], 200);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/db')]
	public function db(): JSONResponse {
		$response = [];

		$response['user'] = [];
		$entities = $this->context->db->user->getAll();
		foreach ($entities as $entity) {
			$response['user'][] = $entity->jsonSerialize();
		}

		$response['group'] = [];
		$entities = $this->context->db->group->getAll();
		foreach ($entities as $entity) {
			$response['group'][] = $entity->jsonSerialize();
		}

		$response['revocation'] = [];
		$entities = $this->context->db->revocation->getAll();
		foreach ($entities as $entity) {
			$response['revocation'][] = $entity->jsonSerialize();
		}

		$response['groupDeletion'] = [];
		$entities = $this->context->db->groupDeletion->getAll();
		foreach ($entities as $entity) {
			$response['groupDeletion'][] = $entity->jsonSerialize();
		}

		$response['groupKicked'] = [];
		$entities = $this->context->db->groupKicked->getAll();
		foreach ($entities as $entity) {
			$response['groupKicked'][] = $entity->jsonSerialize();
		}

		$response['data'] = [];
		$entities = $this->context->db->data->getAll();
		foreach ($entities as $entity) {
			$response['data'][] = $entity->jsonSerialize();
		}

		return new JSONResponse($response);
	}

	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/groups')]
	public function groups(): JSONResponse {
		$response = [];

		$response['groups'] = [];
		$userNextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($this->userSession->getUser());
		foreach ($userNextcloudGroups as $nextcloudGroup) {
			$olvidGroup = $this->context->db->group->getByGroupIdOrNull($nextcloudGroup->getGID());
			$response['groups'][] = $olvidGroup->jsonSerialize();
		}

		$earliestRevocationTimestamp = TimeUtil::currentTimeMillis() - Constants::DEFAULT_REVOCATION_LISTS_MAX_AGE_MILLIS;
		// get all deleted groups
		$response['groupDeleted'] = $this->context->db->groupDeletion->getAfterTimestamp($earliestRevocationTimestamp);

		// get all groups user was removed from
		$response['groupKicked'] = $this->context->db->groupKicked->getByUserIdAfterTimestamp($this->userSession->getUser()->getUID(), $earliestRevocationTimestamp);

		return new JSONResponse($response);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @noinspection PhpUnused
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/groupsReset')]
	public function groupsReset(): JSONResponse {
		$this->context->db->group->deleteAll();
		$this->context->db->groupDeletion->deleteAll();
		$this->context->db->groupKicked->deleteAll();
		return new JSONResponse('Done');
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/reset')]
	public function reset(): TextPlainResponse {
		$olvidUser = $this->context->db->user->getByUserIdOrNull($this->userSession->getUser()->getUID());
		if ($olvidUser !== null) {
			$this->context->db->user->delete($olvidUser);
		}
		return new TextPlainResponse('reset ' . $this->userSession->getUser()->getUID());
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @noinspection PhpUnused
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/resetAll')]
	public function resetAll(): JSONResponse {
		// do not reset app config, it's a pain and not useful
		// reset app data
		// $this->olvidAppConfig->deleteAppConfig();

		// reset users data
		$nextcloudUsers = $this->context->nextcloud->userManager->search('');
		foreach ($nextcloudUsers as $nextcloudUser) {
			$olvidUser = $this->context->db->user->getByUserIdOrNull($nextcloudUser->getUID());
			if ($olvidUser !== null) {
				$this->context->db->user->delete($olvidUser);
			}
		}

		// reset groups data
		$groups = $this->context->db->group->getAll();
		$this->context->db->group->deleteAll();
		$this->context->db->groupDeletion->deleteAll();
		$this->context->db->groupKicked->deleteAll();
		$this->context->db->revocation->deleteAll();
		$this->context->db->data->deleteAll();

		return new JSONResponse([
			'success' => true,
			'cleaned-app' => true,
			'cleaned-users' => count($nextcloudUsers),
			'cleaned-groups' => count($groups),
		]);
	}

	/**
	 * @throws \OCP\DB\Exception
	 * @noinspection PhpUnused
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/resetRevocations')]
	public function resetRevocations(): JSONResponse {
		$revocations = $this->context->db->revocation->getAll();
		$this->context->db->revocation->deleteAll();
		return new JSONResponse(['deletedRevocations' => array_map(function ($revocation) { return $revocation->getUserId(); }, $revocations)]);
	}
}
