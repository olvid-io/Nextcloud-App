<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\JsonGroupBlob;
use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\Response;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

class PutKey extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): Response {
		try {
			$identity = isset($jsonParameters[Constants::PUT_KEY_REQUEST_IDENTITY]) ? (string)$jsonParameters[Constants::PUT_KEY_REQUEST_IDENTITY] : null;
		} catch (Exception $e) {
			$this->logger->warning('putKey: parse error: ', ['exception' => $e]);
			return $this->invalidRequest();
		}

		if (!$identity) {
			$this->logger->error('putKey: identity not set');
			return $this->invalidRequest();
		}
		// TODO: also check that identity is a valid Olvid identity

		// check if this identity have not been revoked
		$revocations = $this->db->revocation->findRevokeByIdentityOrNull($identity);
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

		try {
			$previousIdentity = $this->olvidUserConfig->getB64Identity($nextcloudUser->getUID());
			$previousApiKey = $this->olvidUserConfig->getApiKey($nextcloudUser->getUID());

			// no identity already registered
			if (!$previousIdentity) {
				// user is not supposed to have an api key, revoke it if there is one
				if ($previousApiKey) {
					try {
						$this->olvidServer->revokeApiKey($previousApiKey);
					} catch (Exception $e) {
						$this->logger->error('putKey: cannot revoke previous api key: ', ['exception' => $e]);
					}
				}

				// create and set new api key
				// this might fail if an olvid server api have not been set
				try {
					$newApiKey = $this->olvidServer->requestNewApiKey();
					$this->olvidUserConfig->setApiKey($nextcloudUser->getUID(), $newApiKey);
				} catch (Exception $e) {
					// TODO if cannot set attribute return an error
					$this->logger->warning('putKey: cannot create new api key: ', ['exception' => $e]);
				}

				// set user identity
				$this->olvidUserConfig->setB64Identity($nextcloudUser->getUID(), $identity);

				// sign user details and store them
				$userDetails = JsonUserDetails::computeDetails($nextcloudUser, $this->olvidUserConfig);
				$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);

				// recompute all groups blob for this user enabled groups
				$nextcloudGroups = $this->groupManager->getUserGroups($nextcloudUser);
				foreach ($nextcloudGroups as $nextcloudGroup) {
					// check group exists / is enabled
					$olvidGroup = $this->db->group->findByGroupIdOrNull($nextcloudGroup->getGID());
					if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
						continue;
					}

					try {
						// get olvid group
						// re-compute blob and save in db
						$blob = JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db);
						$signedBlob = $blob->sign($this->olvidAppConfig);
						$olvidGroup->setSignedGroupBlob($signedBlob);
						$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
						$this->db->group->update($olvidGroup);
					} catch (Exception $e) {
						$this->logger->error('putKey: cannot update group blob: ' . $nextcloudGroup->getGID(), ['exception' => $e]);
						continue;
					}
					// notify group members
					try {
						$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
					} catch (Exception) {
						$this->logger->warning('putKey: send group update notification: ' . $nextcloudGroup->getGID());
						continue;
					}
				}
			}
			// trying to put the same identity
			elseif ($previousIdentity === $identity) {
				// if user lack an api key, try to get and set one
				if (!$previousApiKey) {
					// this might fail if an olvid server api have not been set
					try {
						$newApiKey = $this->olvidServer->requestNewApiKey();
						$this->olvidUserConfig->setApiKey($nextcloudUser->getUID(), $newApiKey);
					} catch (Exception $e) {
						// TODO if cannot set attribute return an error
						$this->logger->warning('putKey: cannot create new api key: ', ['exception' => $e]);
					}
				}

				// sign user details and store them
				$userDetails = JsonUserDetails::computeDetails($nextcloudUser, $this->olvidUserConfig);
				$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);
			}
			// trying to override previous identity: we always allow self revocation, just erase previous identity
			elseif ($previousIdentity !== $identity) {
				// revoke current api key if there is one
				if ($previousApiKey) {
					try {
						$this->olvidServer->revokeApiKey($previousApiKey);
					} catch (Exception $e) {
						$this->logger->error('putKey: cannot revoke previous api key: ', ['exception' => $e]);
					}
				}

				// clear old identity
				$this->olvidUserConfig->unsetIdentity($nextcloudUser->getUID());
				// re-generate the nonce so that any identity enrolled with this user is automatically unbound from keycloak
				$this->olvidUserConfig->setNonce($nextcloudUser->getUID(), RandomUtil::uuid_create());

				// sign out the user: set revoked_before to mark any token signed before now to be revoked
				$this->olvidUserConfig->setSessionRevokedBefore($nextcloudUser->getUID(), TimeUtil::currentTimeMillis());

				// create and set new api key
				// this might fail if an olvid server api have not been set
				try {
					$newApiKey = $this->olvidServer->requestNewApiKey();
					$this->olvidUserConfig->setApiKey($nextcloudUser->getUID(), $newApiKey);
				} catch (Exception $e) {
					// TODO if cannot set attribute return an error
					$this->logger->warning('putKey: cannot create new api key: ', ['exception' => $e]);
				}

				// we can now set new identity
				$this->olvidUserConfig->setB64Identity($nextcloudUser->getUID(), $identity);

				// sign user details and store them
				$userDetails = JsonUserDetails::computeDetails($nextcloudUser, $this->olvidUserConfig);
				$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);

				// recompute all groups blob for this user enabled groups
				$nextcloudGroups = $this->groupManager->getUserGroups($nextcloudUser);
				foreach ($nextcloudGroups as $nextcloudGroup) {
					// check group exists / is enabled
					$olvidGroup = $this->db->group->findByGroupIdOrNull($nextcloudGroup->getGID());
					if ($olvidGroup === null || !$olvidGroup->getEnabled()) {
						continue;
					}

					try {
						// get olvid group
						// re-compute blob and save in db
						$blob = JsonGroupBlob::computeBlob($olvidGroup, $nextcloudGroup->getDisplayName(), $nextcloudGroup->getUsers(), $this->olvidAppConfig, $this->olvidUserConfig, $this->db);
						$signedBlob = $blob->sign($this->olvidAppConfig);
						$olvidGroup->setSignedGroupBlob($signedBlob);
						$olvidGroup->setLastModificationTimestamp(TimeUtil::currentTimeMillis());
						$this->db->group->update($olvidGroup);
					} catch (Exception $e) {
						$this->logger->error('putKey: cannot update group blob: ' . $nextcloudGroup->getGID(), ['exception' => $e]);
						continue;
					}
					// notify group members
					try {
						$this->olvidServer->sendGroupNotification($olvidGroup->getPushTopic());
					} catch (Exception) {
						$this->logger->warning('putKey: send group update notification: ' . $nextcloudGroup->getGID());
						continue;
					}
				}
			}

			// delete any magic token for this user
			$this->olvidUserConfig->clearMagicToken($nextcloudUser->getUID());

			return $this->success();
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
	}
}
