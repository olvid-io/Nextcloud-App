<?php

declare(strict_types=1);

namespace OCA\Olvid\Controller;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\AppFramework\OCSController;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

// TODO TODEL
#[OpenAPI(scope: OpenAPI::SCOPE_IGNORE)]
class DebugApiController extends OCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		private readonly IConfig $config,
		private readonly IAppConfig $appConfig,
		private readonly IGroupManager $groupManager,
		private readonly IUserSession $userSession,
		private readonly IUserManager $userManager,
		private readonly LoggerInterface $logger,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidDatabase $db,
	) {
		parent::__construct($appName, $request);
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/debug')]
	public function debug(): JSONResponse {
		try {
			$appFields = $this->appConfig->getAllValues(Application::APP_ID);
		} catch (Exception $e) {
			$this->logger->error('debug: Cannot compute user fields: ' . $e);
		}

		try {
			$userFields = $this->config->getAllUserValues($this->userSession->getUser()->getUID());
		} catch (Exception $e) {
			$this->logger->error('debug: Cannot compute user fields: ' . $e);
		}

		return new JSONResponse([
			'app' => $appFields,
			'user' => $userFields[Application::APP_ID],
			'fullUser' => $userFields,
		], 200);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/me')]
	public function me(): JSONResponse {
		try {
			$userFields = $this->config->getAllUserValues($this->userSession->getUser()->getUID());
		} catch (Exception $e) {
			$this->logger->error('debug: Cannot compute user fields: ' . $e);
		}

		return new JSONResponse([
			'user' => $userFields[Application::APP_ID],
			'fullUser' => $userFields,
		], 200);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/users')]
	public function users(): JSONResponse {
		$users = [];
		foreach ($this->userManager->search("") as $user) {
			try {
				$users[$user->getUID()] = $this->config->getAllUserValues($user->getUID())[Application::APP_ID];
			} catch (Exception $e) {
				$this->logger->error('debug: Cannot compute user fields: ' . $e);
			}
		}

		return new JSONResponse([
			'users' => $users,
		], 200);
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/db')]
	public function db(): JSONResponse {
		$response = [];

		$response['revocation'] = [];
		$entities = $this->db->revocation->findAll();
		foreach ($entities as $entity) {
			$response['revocation'][] = [
				'olvidId' => $entity->getOlvidId(),
				'timestamp' => $entity->getTimestamp(),
				'revocationType' => $entity->getRevocationType(),
				'signature' => $entity->getSignature(),
				'username' => $entity->getUsername(),
				'firstname' => $entity->getFirstname(),
				'lastname' => $entity->getLastname(),
				'mail' => $entity->getMail(),
				'position' => $entity->getPosition(),
				'company' => $entity->getCompany(),
				'fullSearchString' => $entity->getFullSearchString(),
			];
		}

		$response['group'] = [];
		$entities = $this->db->group->findAll();
		foreach ($entities as $entity) {
			$response['group'][] = [
				'groupId' => $entity->getGroupId(),
				'groupUid' => $entity->getGroupUid() !== null ? base64_encode($entity->getGroupUid()): null,
				'lastModificationTimestamp' => $entity->getLastModificationTimestamp(),
				'pushTopic' => $entity->getPushTopic(),
				'groupPhotoUid' => $entity->getGroupPhotoUid() !== null ? base64_encode($entity->getGroupPhotoUid()): null,
				'serializedSharedSettings' => $entity->getSerializedSharedSettings(),
				'enabled' => $entity->getEnabled(),
				'signedGroupBlob' => $entity->getSignedGroupBlob(),
				'discussionName' => $entity->getDiscussionName(),
				'discussionDescription' => $entity->getDiscussionDescription(),
			];
		}

		$response['groupDeletion'] = [];
		$entities = $this->db->groupDeletion->findAll();
		foreach ($entities as $entity) {
			$response['groupDeletion'][] = [
				'groupId' => $entity->getGroupId(),
				'signature' => $entity->getSignature(),
				'timestamp' => $entity->getTimestamp(),
			];
		}

		$response['groupKicked'] = [];
		$entities = $this->db->groupKicked->findAll();
		foreach ($entities as $entity) {
			$response['groupKicked'][] = [
				'groupId' => $entity->getGroupId(),
				'userId' => $entity->getUserId(),
				'signature' => $entity->getSignature(),
				'timestamp' => $entity->getTimestamp(),
			];
		}

		$response['data'] = [];
		$entities = $this->db->data->findAll();
		foreach ($entities as $entity) {
			$response['data'][] = [
				'encodedDataKey' => base64_encode($entity->getEncodedDataKey()),
				'dataUid' => $entity->getDataUid(),
				'data' => base64_encode($entity->getData()),
			];
		}

		return new JSONResponse($response);
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/groups')]
	public function groups(): JSONResponse {
		$response = [];

		$response['groups'] = [];
		$userGroups = $this->groupManager->getUserGroups($this->userSession->getUser());
		foreach ($userGroups as $nextcloudGroup) {
			$olvidGroup = $this->db->group->findByGroupIdOrNull($nextcloudGroup->getGID());
			$response['groups'][] = [
				'groupId' => $olvidGroup?->getgroupId(),
				'groupUid' => $olvidGroup?->getGroupUid() !== null ? base64_encode($olvidGroup?->getGroupUid()) : null,
				'lastModificationTimestamp' => $olvidGroup?->getLastModificationTimestamp(),
				'pushTopic' => $olvidGroup?->getPushTopic(),
				// TODO change this: this field is supposed to be already base64encoded, as it is in olvid_data table
				'groupPhotoUid' => base64_encode($olvidGroup?->getGroupPhotoUid() ?? ''),
				'serializedSharedSettings' => $olvidGroup?->getSerializedSharedSettings(),
				'signedGroupBlob' => $olvidGroup?->getSignedGroupBlob(),
				'enabled' => $olvidGroup?->getEnabled(),
				'discussionName' => $olvidGroup?->getDiscussionName(),
				'discussionDescription' => $olvidGroup?->getDiscussionDescription(),
				'blob' => $olvidGroup !== null ? JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db) : null,
			];
		}

		$earliestRevocationTimestamp = TimeUtil::currentTimeMillis() - Constants::DEFAULT_REVOCATION_LISTS_MAX_AGE_MILLIS;
		// get all deleted groups
		$response['groupDeleted'] = $this->db->groupDeletion->getSignatureAfterTimestamp($earliestRevocationTimestamp);

		// get all groups user was removed from
		$response['groupKicked'] = $this->db->groupKicked->getSignatureAfterTimestamp($this->userSession->getUser()->getUID(), $earliestRevocationTimestamp);

		return new JSONResponse($response);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/groupsReset')]
	public function groupsReset(): JSONResponse {
		$this->db->group->deleteAll();
		$this->db->groupDeletion->deleteAll();
		$this->db->groupKicked->deleteAll();
		return new JSONResponse('Done');
	}

	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/reset')]
	public function reset(): TextPlainResponse {
		$user = $this->userSession->getUser();
		if ($user) {
			$this->olvidUserConfig->deleteUserConfig($user->getUID());
			return new TextPlainResponse('reset ' . $user->getDisplayName());
		} else {
			return new TextPlainResponse('not logged in');
		}
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/resetAll')]
	public function resetAll(): JSONResponse {
		// do not reset app config, it's painful and not useful
		// reset app data
		// $this->olvidAppConfig->deleteAppConfig();

		// reset users data
		$users = $this->userManager->search('');
		foreach ($users as $user) {
			$this->olvidUserConfig->deleteUserConfig($user->getUID());
		}

		// reset groups data
		$groups = $this->db->group->findAll();
		$this->db->group->deleteAll();
		$this->db->groupDeletion->deleteAll();
		$this->db->groupKicked->deleteAll();
		$this->db->revocation->deleteAll();
		$this->db->data->deleteAll();

		return new JSONResponse([
			'success' => true,
			'cleaned-app' => true,
			'cleaned-users' => count($users),
			'cleaned-groups' => count($groups),
		]);
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	#[NoCSRFRequired]
	#[ApiRoute(verb: 'GET', url: '/debug/resetRevocations')]
	public function resetRevocations(): JSONResponse {
		$revocations = $this->db->revocation->findAll();
		$this->db->revocation->deleteAll();
		return new JSONResponse(['deletedRevocations' => array_map(function ($revocation) { return $revocation->getUsername(); }, $revocations)]);
	}
}
