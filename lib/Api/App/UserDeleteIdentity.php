<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\App;

use Exception;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Models\JsonRevocationData;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\Context\OlvidServer\OlvidServerException;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use Psr\Log\LoggerInterface;

class UserDeleteIdentity {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
	) {
	}

	/**
	 * @throws \OCP\DB\Exception
	 */
	public function handle(string $userId, bool $revoke): DataResponse {
		$nextcloudUser = $this->context->nextcloud->userManager->get($userId);
		$olvidUser = $this->context->db->user->getByUserIdOrNull($userId);

		// delete any cached signed details (ignore exception)
		// delete user api key if he has one
		if ($olvidUser?->getApiKey() !== null) {
			try {
				$this->context->olvidServer->revokeApiKey($olvidUser->getApiKey());
				$olvidUser->setApiKey(null);
				$this->context->db->user->updateNoFail($olvidUser);
			} catch (InvalidConfigurationException|OlvidServerException $e) {
				$this->logger->error('UserDeleteIdentity: cannot revoke api key', ['exception' => $e]);
			}
		}

		// check user use Olvid
		if (!$olvidUser->hasIdentity()) {
			return new DataResponse(null, Http::STATUS_NOT_FOUND);
		}

		// revoke identity
		try {
			if ($revoke) {
				$this->context->db->revocation->computeAndSaveRevocation($userId, $olvidUser->getBytesIdentity(), JsonRevocationData::REVOCATION_TYPE_REVOKE_ID, $this->context);
			} else {
				$this->context->db->revocation->computeAndSaveRevocation($userId, $olvidUser->getBytesIdentity(), JsonRevocationData::REVOCATION_TYPE_DELETE_USER, $this->context);
			}
		} catch (Exception $exception) {
			$this->logger->error('UserDeleteIdentity: cannot create revocation', ['exception' => $exception]);
		}

		// sign out user (ignore exception)
		try {
			$olvidUser->setSessionRevokedBefore(TimeUtil::currentTimeMillis());
		} catch (Exception $exception) {
			$this->logger->error('UserDeleteIdentity: cannot revoke session', ['exception' => $exception]);
		}

		// delete user identity, nonce and signed details
		// keep $bytesUserIdentity to compute group kicks, but remove user identity to not count him as a group member for blob computing
		$bytesUserIdentity = $olvidUser->getBytesIdentity();
		$olvidUser->setBytesIdentity(null);
		$olvidUser->setNonce(null);
		$olvidUser->setSignedDetails(null);
		$this->context->db->user->update($olvidUser);

		// remove user from Olvid groups (ignore exceptions)
		$nextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($nextcloudUser);
		foreach ($nextcloudGroups as $nextcloudGroup) {
			try {
				$olvidGroup = $this->context->db->group->getByGroupIdOrNull($nextcloudGroup->getGID());
				if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
					continue;
				}

				// create group kick in database
				$this->context->db->groupKicked->computeAndSaveGroupKick($olvidGroup, $userId, $bytesUserIdentity, $this->context);

				// update group blob
				$jsonGroupBlob = $olvidGroup->computeBlob($nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->context);
				$signedBlob = $this->context->signatory->sign($jsonGroupBlob->jsonSerialize());
				$olvidGroup->setSignedGroupBlob($signedBlob);
				$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
				$olvidGroup = $this->context->db->group->update($olvidGroup);

				// notify users
				if ($olvidGroup->getPushTopic() !== null) {
					// we can use push topic to notify members and removed user
					$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
				}
			} catch (Exception $exception) {
				$this->logger->error('UserDeleteIdentity: cannot kick user from group', ['exception' => $exception]);
			}
		}

		// notify every user (ignore exception)
		$globalPushTopic = $this->context->nextcloud->appManager->getGlobalPushTopic();
		if ($globalPushTopic !== null) {
			$this->context->olvidServer->sendGroupNotificationNoFail($globalPushTopic);
		}

		return new DataResponse(null, Http::STATUS_OK);
	}
}
