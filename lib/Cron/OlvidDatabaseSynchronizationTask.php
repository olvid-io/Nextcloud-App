<?php

namespace OCA\Olvid\Cron;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\RandomUtil;
use Psr\Log\LoggerInterface;

/*
 * This task check elements in database correspond to elements in Nextcloud database.
 */
class OlvidDatabaseSynchronizationTask {
	public function __construct(
		private readonly OlvidContext $context,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function run(): void {
		$this->logger->info('OlvidDatabaseSynchronizationTask: task start');

		// delete olvid users if there is no associated nextcloud user
		$this->syncAndValidateOlvidUsers();

		// delete olvid group if there is no associated nextcloud user
		$this->syncAndValidateOlvidGroups();

		// delete olvid data if they are not referenced anymore
		$this->syncAndValidateOlvidData();

		// OlvidRevocation, OlvidGroupDeletion, OlvidGroupKicked are long term database that cannot be cleaned
		// even if original group / user does not exist anymore

		$this->logger->info('OlvidDatabaseSynchronizationTask: task end');
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	private function syncAndValidateOlvidUsers(): void {
		// retrieve users from olvid and nextcloud
		$allOlvidUsers = $this->context->db->user->getAll();
		$allNextcloudUsers = $this->context->nextcloud->userManager->search('');

		/*
		 * Check all Olvid users have an associated nextcloud user, or delete it
		 */
		$nextcloudUserMap = array_combine(array_map(function ($user) { return $user->getUID(); }, $allNextcloudUsers), $allNextcloudUsers);
		foreach ($allOlvidUsers as $olvidUser) {
			if (!array_key_exists($olvidUser->getUserId(), $nextcloudUserMap)) {
				try {
					$userId = $olvidUser->getUserId();
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: found user with no Nextcloud user: ${userId}");
					$this->context->db->user->delete($olvidUser);
				} catch (Exception $e) {
					$this->logger->error('OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: cannot delete user', ['exception' => $e]);
				}
			}
		}

		/*
		 * Check user field validity depending on there Olvid status
		 */
		foreach ($allOlvidUsers as $olvidUser) {
			$userId = $olvidUser->getUserId();
			// user registered its identity
			if ($olvidUser->hasIdentity()) {
				// re-compute user details if user does not have it
				if (!$olvidUser->getSignedDetails()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: found enabled user with no signed details: ${userId}");
					$jsonUserDetails = $olvidUser->computeJsonUserDetails($allNextcloudUsers[$olvidUser->getUserId()]->getDisplayName());
					$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));
				}
				// else check user details signature is valid
				else {
					$verified = false;
					try {
						$verified = (bool)($this->context->signatory->verify($olvidUser->getSignedDetails()));
					} catch (Exception) {
					}
					if (!$verified) {
						$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: cannot parse signed user details, re-computing them: ${userId}");
						$jsonUserDetails = $olvidUser->computeJsonUserDetails($allNextcloudUsers[$olvidUser->getUserId()]->getDisplayName());
						$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));
					}
				}

				// set a nonce if necessary
				if (!$olvidUser->getNonce()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: found enabled user with no nonce: ${userId}");
					$olvidUser->setNonce(RandomUtil::uuid_create());
				}

				// request an api key if necessary
				if (!$olvidUser->getApiKey()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: found enabled user with no api key: ${userId}");
					$jsonUserDetails = $olvidUser->computeJsonUserDetails($allNextcloudUsers[$olvidUser->getUserId()]->getDisplayName());
					$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));
				}
			}
			// user DO NOT registered its identity
			else {
				// user is not supposed to have signed details
				if ($olvidUser->getSignedDetails()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: found a disabled user with signed details: ${userId}");
					$olvidUser->setSignedDetails(null);
				}
				// user is not supposed to have a nonce
				if ($olvidUser->getNonce()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: found a disabled user with a nonce: ${userId}");
					$olvidUser->setNonce(null);
				}
				// revoke api key
				if ($olvidUser->getApiKey()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: found a disabled user with an api key: ${userId}");
					$this->context->olvidServer->revokeApiKeyNoFail($olvidUser->getApiKey());
					$olvidUser->setApiKey(null);
				}
			}
			if (count($olvidUser->getUpdatedFields()) > 0) {
				try {
					$this->context->db->user->update($olvidUser);
				} catch (Exception $e) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: cannot update user: ${userId}", ['exception' => $e]);
				}
			}
		}
	}

	private function syncAndValidateOlvidGroups(): void {
		// retrieve groups from olvid and nextcloud
		$allOlvidGroups = $this->context->db->group->getAll();
		$allNextcloudGroups = $this->context->nextcloud->groupManager->search('');

		/*
		 * Check all Olvid groups have an associated nextcloud group, or delete it
		 */
		$nextcloudGroupMap = array_combine(array_map(function ($group) { return $group->getGID(); }, $allNextcloudGroups), $allNextcloudGroups);
		foreach ($allOlvidGroups as $olvidGroup) {
			if (!array_key_exists($olvidGroup->getGroupId(), $nextcloudGroupMap)) {
				try {
					$groupId = $olvidGroup->getGroupId();
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidUsers: found a group with no Nextcloud group: ${$groupId}");
					$this->context->db->group->delete($olvidGroup);
				} catch (Exception $e) {
					$this->logger->error('OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: cannot delete group', ['exception' => $e]);
				}
			}
		}

		/*
		 * Check group field validity depending on there Olvid status
		 */
		foreach ($allOlvidGroups as $olvidGroup) {
			$groupId = $olvidGroup->getGroupId();
			$nextcloudGroup = $nextcloudGroupMap[$groupId];

			// group is enabled in Olvid
			if ($olvidGroup->getEnabled()) {
				// group must have an olvid bytes group uid
				if (!$olvidGroup->getBytesGroupUid()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: found an enabled group with no uid: {$groupId}");
					$olvidGroup->setBytesGroupUid(RandomUtil::random_bytes(Constants::UID_SIZE));
				}

				// group must have a signed blob
				if (!$olvidGroup->getSignedGroupBlob()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: found an enabled group with no blob: {$groupId}");
					try {
						$jsonGroupBlob = $olvidGroup->computeBlob($nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->context);
						$signedBlob = $this->context->signatory->sign($jsonGroupBlob->jsonSerialize());
						$olvidGroup->setSignedGroupBlob($signedBlob);
					} catch (Exception $e) {
						$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: cannot compute group blob : {$groupId}", ['exception' => $e]);
					}
				}
				// group must have a push topic
				if (!$olvidGroup->getPushTopic()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: found an enabled group with no push topic: {$groupId}");
					try {
						$olvidGroup->setPushTopic($this->context->olvidServer->requestNewPushTopic());
					} catch (Exception $e) {
						$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: cannot request new push topic : {$groupId}", ['exception' => $e]);
					}
				}
			}
			// group is disabled
			else {
				// $bytesGroupUid: always keep this field, to re-use existing group if re-enabling it

				// group is not supposed to have a signed blob
				if ($olvidGroup->getSignedGroupBlob()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: found a disabled group with a blob: {$groupId}");
					$olvidGroup->setSignedGroupBlob(null);
				}
				// group is not supposed to have a push topic
				if ($olvidGroup->getPushTopic()) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: found a disabled group with a push topic: {$groupId}");
					$this->context->olvidServer->revokePushTopicNoFail($olvidGroup->getPushTopic());
					$olvidGroup->setPushTopic(null);
				}
			}
			if (count($olvidGroup->getUpdatedFields()) > 0) {
				try {
					$this->context->db->group->update($olvidGroup);
				} catch (Exception $e) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidGroups: cannot update group: {$groupId}", ['exception' => $e]);
				}
			}
		}
	}

	private function syncAndValidateOlvidData(): void {
		$allOlvidData = $this->context->db->data->getAll();
		$olvidGroupsWithPhoto = $this->context->db->group->getGroupsWithPhoto();
		$olvidGroupByDataUidMap = array_combine(array_map(function ($group) { return $group->getBytesGroupPhotoUid(); }, $olvidGroupsWithPhoto), $olvidGroupsWithPhoto);

		foreach ($allOlvidData as $olvidData) {
			if (!array_key_exists($olvidData->getBytesDataUid(), $olvidGroupByDataUidMap)) {
				$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidData: deleting orphan olvid data: {$olvidData->getBytesDataUid()}");
				try {
					$this->context->db->data->delete($olvidData);
				} catch (Exception $e) {
					$this->logger->error("OlvidDatabaseSynchronizationTask: syncAndValidateOlvidData: cannot delete olvid data: {$olvidData->getBytesDataUid()}", ['exception' => $e]);
				}
			}
		}
	}
}
