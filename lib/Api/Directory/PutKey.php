<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Models\JsonRevocationData;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\Response;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

class PutKey extends AbstractAuthenticatedDeviceApiHandler {
	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): Response {
		try {
			$base64Identity = isset($jsonParameters[Constants::PUT_KEY_REQUEST_IDENTITY]) ? (string)$jsonParameters[Constants::PUT_KEY_REQUEST_IDENTITY] : null;
		} catch (Exception $e) {
			$this->logger->warning('putKey: parse error: ', ['exception' => $e]);
			return $this->invalidRequest();
		}

		if (!$base64Identity) {
			$this->logger->error('putKey: identity not set');
			return $this->invalidRequest();
		}
		// TODO: also check that identity is a valid Olvid identity
		$bytesIdentity = base64_decode($base64Identity);

		// check if this identity have not been revoked
		$revocations = $this->context->db->revocation->getByTypeAndBytesIdentityOrNull($bytesIdentity, JsonRevocationData::REVOCATION_TYPE_REVOKE_ID);
		if ($revocations !== null && count($revocations) > 0) {
			$this->logger->warning('putKey: rejected for user: ' . $nextcloudUser->getUID() . ', revocation date: ' . $revocations[0]->getTimestamp());
			return self::identityWasRevoked();
		}

		// prevent concurrent executions of this task for a same user
		$lockKey = Application::APP_ID . '/olvid-rest/putKey/' . $nextcloudUser->getUID();
		try {
			$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException) {
			return $this->invalidRequest();
		}

		// create Olvid user if it does not exist
		$olvidUser = $this->context->db->user->getOrCreate($nextcloudUser->getUID());

		try {
			$base64PreviousIdentity = base64_encode($olvidUser->getBytesIdentity() ?? '');

			// no identity already registered
			if (!$base64PreviousIdentity) {
				// user is not supposed to have an api key, revoke it if there is one
				if ($olvidUser->getApiKey()) {
					$this->context->olvidServer->revokeApiKeyNoFail($olvidUser->getApiKey());
					$olvidUser->setApiKey(null);
				}

				// create and set new api key
				// this might fail if an olvid server api have not been set
				try {
					$newApiKey = $this->context->olvidServer->requestNewApiKey();
					$olvidUser->setApiKey($newApiKey);
				} catch (InvalidConfigurationException) {
					$this->logger->warning('PutKey: cannot create user api key: invalid configuration');
				} catch (Exception $e) {
					$this->logger->warning('PutKey: cannot create user api key: unexpected exception', ['exception' => $e]);
				}

				// set user identity
				$olvidUser->setBytesIdentity($bytesIdentity);

				// sign user details and store them
				$jsonUserDetails = $olvidUser->computeJsonUserDetails($nextcloudUser->getDisplayName());
				$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));

				// save user in database
				$olvidUser = $this->context->db->user->update($olvidUser);

				// recompute all groups blob for this user enabled groups
				$nextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($nextcloudUser);
				foreach ($nextcloudGroups as $nextcloudGroup) {
					// check group exists and is enabled
					$olvidGroup = $this->context->db->group->getByGroupIdOrNull($nextcloudGroup->getGID());
					if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
						continue;
					}

					try {
						// re-compute group blob and save in db
						$jsonGroupBlob = $olvidGroup->computeBlob($nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->context);
						$olvidGroup->setSignedGroupBlob($this->context->signatory->sign($jsonGroupBlob->jsonSerialize()));
						$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
						$this->context->db->group->update($olvidGroup);
					} catch (Exception $e) {
						$this->logger->error('putKey: cannot update group blob: ' . $nextcloudGroup->getGID(), ['exception' => $e]);
						continue;
					}

					// notify group members
					$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
				}
			}
			// trying to put the same identity
			elseif ($base64PreviousIdentity === $base64Identity) {
				// if user lack an api key, try to get and set one
				if (!$olvidUser->getApiKey()) {
					// this might fail if an olvid server api have not been set
					try {
						$newApiKey = $this->context->olvidServer->requestNewApiKey();
						$olvidUser->setApiKey($newApiKey);
					} catch (InvalidConfigurationException) {
						$this->logger->warning('PutKey: cannot create user api key: invalid configuration');
					} catch (Exception $e) {
						$this->logger->warning('PutKey: cannot create user api key: unexpected exception', ['exception' => $e]);
					}
				}

				// sign user details and store them
				$jsonUserDetails = $olvidUser->computeJsonUserDetails($nextcloudUser->getDisplayName());
				$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));
				$olvidUser = $this->context->db->user->update($olvidUser);
			}
			// new and previous identity are different,
			// trying to override previous identity: we always allow self revocation, just erase previous identity
			else {
				// revoke current api key if there is one
				if ($olvidUser->getApiKey()) {
					$this->context->olvidServer->revokeApiKeyNoFail($olvidUser->getApiKey());
					$olvidUser->setApiKey(null);
				}

				// clear old identity
				$olvidUser->setBytesIdentity(null);
				// re-generate the nonce so that any identity enrolled with this user is automatically unbound from keycloak
				$olvidUser->setNonce(RandomUtil::uuid_create());

				// sign out the user: set revoked_before to mark any token signed before now to be revoked
				$olvidUser->setSessionRevokedBefore(TimeUtil::currentTimeMillis());

				// create and set new api key
				// this might fail if an olvid server api have not been set
				try {
					$newApiKey = $this->context->olvidServer->requestNewApiKey();
					$olvidUser->setApiKey($newApiKey);
				} catch (InvalidConfigurationException) {
					$this->logger->warning('PutKey: cannot create user api key: invalid configuration');
				} catch (Exception $e) {
					$this->logger->warning('PutKey: cannot create user api key: unexpected exception', ['exception' => $e]);
				}

				// we can now set new identity
				$olvidUser->setBytesIdentity($bytesIdentity);

				// sign user details and store them
				$jsonUserDetails = $olvidUser->computeJsonUserDetails($nextcloudUser->getDisplayName());
				$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));

				// save olvid user in database
				$olvidUser = $this->context->db->user->update($olvidUser);

				// recompute all groups blob for this user enabled groups
				$nextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($nextcloudUser);
				foreach ($nextcloudGroups as $nextcloudGroup) {
					// check group exists and is enabled
					$olvidGroup = $this->context->db->group->getByGroupIdOrNull($nextcloudGroup->getGID());
					if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
						continue;
					}

					try {
						// re-compute group blob and save in db
						$jsonGroupBlob = $olvidGroup->computeBlob($nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->context);
						$olvidGroup->setSignedGroupBlob($this->context->signatory->sign($jsonGroupBlob->jsonSerialize()));
						$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
						$this->context->db->group->update($olvidGroup);
					} catch (Exception $e) {
						$this->logger->error('putKey: cannot update group blob: ' . $nextcloudGroup->getGID(), ['exception' => $e]);
						continue;
					}

					// notify group members
					$this->context->olvidServer->sendGroupNotificationNoFail($olvidGroup->getPushTopic());
				}
			}

			// delete any magic token for this user
			if ($olvidUser->getMagicToken() !== null) {
				$olvidUser->setMagicToken(null);
				$this->context->db->user->update($olvidUser);
			}

			return $this->success();
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}
}
