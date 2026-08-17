<?php

namespace OCA\Olvid\Cron;

use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

/*
 * Refresh all signatures before they expire.
 */
class OlvidRefreshSignatureTask {
	public function __construct(
		private readonly OlvidContext $context,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @throws Exception
	 */
	public function run(): void {
		$this->refreshUserSignature();

		$this->refreshGroupsSignature();
	}

	/**
	 * @throws Exception
	 */
	public function refreshUserSignature(): void {
		$allEnabledOlvidUsers = $this->context->db->user->getAllWithIdentity();
		$count = 0;
		foreach ($allEnabledOlvidUsers as $olvidUser) {
			$jsonUserDetails = $olvidUser->computeJsonUserDetails();
			$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));
			try {
				$this->context->db->user->update($olvidUser);
				$count++;
			} catch (Exception $e) {
				$this->logger->error('OlvidRefreshSignatureTask: refreshUserSignature: cannot update user details in db', ['exception' => $e]);
			}
		}
		$this->logger->info("OlvidRefreshSignatureTask: refreshUserSignature: refreshed ${count} user signatures");
	}

	/**
	 * @throws Exception
	 */
	public function refreshGroupsSignature(): void {
		$allEnabledOlvidGroups = $this->context->db->group->getEnabledGroups();
		$count = 0;
		foreach ($allEnabledOlvidGroups as $olvidGroup) {
			$nextcloudGroup = $this->context->nextcloud->groupManager->get($olvidGroup->getGroupId());
			if ($nextcloudGroup) {
				$jsonGroupBlob = $olvidGroup->computeBlob($nextcloudGroup->getUsers(), $this->context);
				$olvidGroup->setSignedGroupBlob($this->context->signatory->sign($jsonGroupBlob->jsonSerialize()));
				try {
					$this->context->db->group->update($olvidGroup);
					$count++;
				} catch (Exception $e) {
					$this->logger->error('OlvidRefreshSignatureTask: refreshGroupsSignature: cannot update group blob in db', ['exception' => $e]);
				}
			} else {
				$this->logger->error('OlvidRefreshSignatureTask: refreshGroupsSignature: associated Nextcloud group not found');
			}
		}
		$this->logger->info("OlvidRefreshSignatureTask: refreshGroupsSignature: refreshed ${count} group signatures");
	}
}
