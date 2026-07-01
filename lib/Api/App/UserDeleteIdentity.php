<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\App;

use Exception;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Models\JsonRevocationData;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IGroupManager;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UserDeleteIdentity {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidUserConfigManager $olvidUserConfig,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly OlvidServer $olvidServer,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly OlvidDatabase $db,
	) {
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function handle(string $userId, bool $revoke): JSONResponse {
		$user = $this->userManager->get($userId);

		// delete any cached signed details (ignore exception)
		// delete user api key if he has one
		$userApiKey = $this->olvidUserConfig->getApiKey($userId);
		if ($userApiKey !== null) {
			try {
				$this->olvidServer->revokeApiKey($userApiKey);
				$this->olvidUserConfig->unsetApiKey($userId);
			} catch (InvalidConfigurationException|OlvidServerException $e) {
				$this->logger->error('UserDeleteIdentity: cannot revoke api key', ['exception' => $e]);
			}
		}

		// check user use Olvid
		$userIdentity = $this->olvidUserConfig->getIdentity($userId);
		if ($userIdentity === null) {
			return new JSONResponse([], 400);
		}

		// remove user from Olvid groups (ignore exceptions)
		$nextcloudGroups = $this->groupManager->getUserGroups($user);
		foreach ($nextcloudGroups as $nextcloudGroup) {
			try {
				$olvidGroup = $this->db->group->findByGroupIdOrNull($nextcloudGroup->getGID());
				if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
					continue;
				}

				// create group kick in database
				$this->db->groupKicked->computeAndSaveGroupKick($this->olvidAppConfig, $olvidGroup, $userId, $userIdentity);

				// update group blob
				$blob = JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db);
				$signedBlob = $blob->sign($this->olvidAppConfig);
				$olvidGroup->setSignedGroupBlob($signedBlob);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$olvidGroup = $this->db->group->update($olvidGroup);

				// notify users
				if ($olvidGroup->getPushTopic() !== null) {
					// we can use push topic to notify members and removed user
					try {
						$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
					} catch (OlvidServerException|InvalidConfigurationException $exception) {
						$this->logger->error('UserDeleteIdentity: cannot send group notification', ['exception' => $exception]);
					}
				}
			} catch (Exception $exception) {
				$this->logger->error('UserDeleteIdentity: cannot kick user from group', ['exception' => $exception]);
			}
		}

		// delete user identity, nonce and signed details
		$this->olvidUserConfig->unsetIdentity($userId);
		$this->olvidUserConfig->unsetNonce($userId);
		$this->olvidUserConfig->unsetSignedDetails($userId);

		// revoke identity
		try {
			if (!$revoke) {
				$this->db->revocation->computeAndSaveRevocation($userId, $this->olvidUserConfig->getIdentity($userId), JsonRevocationData::REVOCATION_TYPE_DELETE_USER, $this->olvidAppConfig);
			} else {
				$this->db->revocation->computeAndSaveRevocation($userId, $this->olvidUserConfig->getIdentity($userId), JsonRevocationData::REVOCATION_TYPE_REVOKE_ID, $this->olvidAppConfig);
			}
		} catch (Exception $exception) {
			$this->logger->error('UserDeleteIdentity: cannot create revocation', ['exception' => $exception]);
		}

		// sign out user (ignore exception)
		try {
			$this->olvidUserConfig->setSessionRevokedBefore($userId, TimeUtil::currentTimeMillis());
		} catch (Exception $exception) {
			$this->logger->error('UserDeleteIdentity: cannot revoke session', ['exception' => $exception]);
		}

		// notify every user (ignore exception)
		try {
			$globalPushTopic = $this->olvidAppConfig->getGlobalPushTopic();
			if ($globalPushTopic !== null) {
				$this->olvidServer->sendGroupNotification($globalPushTopic);
			}
		} catch (Exception $exception) {
			$this->logger->error('UserDeleteIdentity: cannot revoke session', ['exception' => $exception]);
		}

		return new JSONResponse([]);
	}
}
