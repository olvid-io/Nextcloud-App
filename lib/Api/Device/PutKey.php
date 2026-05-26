<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Device;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCA\Olvid\Utils\OlvidServer\OlvidServerUtils;
use OCP\AppFramework\Http\Response;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

class PutKey extends AbstractAuthenticatedDeviceApiHandler {
	public function handler(array $jsonParameters, ?IUser $user): Response {
		try {
			$identity = isset($jsonParameters[Constants::PUT_KEY_REQUEST_IDENTITY]) ? (string)$jsonParameters[Constants::PUT_KEY_REQUEST_IDENTITY] : null;
		} catch (Exception $e) {
			$this->logger->warning('putKey: parse error: ' . $e->getMessage());
			return $this->invalidRequest();
		}

		if (!$identity) {
			$this->logger->error("putKey: identity not set");
			return $this->invalidRequest();
		}
		// TODO: also check that identity is a valid Olvid identity

		// TODO feature revocation
		// TODO feature allow self revocation

		// prevent concurrent executions of this task for a same user
		$lockKey = Application::APP_ID .  "/olvid-rest/putKey/" . $user->getUID();
		try {
			$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException) {
			return $this->invalidRequest();
		}

		try {
			$previousIdentity = $this->olvidUserConfig->getIdentity($user->getUID());
			$previousApiKey = $this->olvidUserConfig->getApiKey($user->getUID());

			// no identity already registered
			if (!$previousIdentity) {
				// user is not supposed to have an api key, revoke it if there is one
				if ($previousApiKey) {
					try {
						OlvidServerUtils::revokeApiKey($this->olvidAppConfig, $previousApiKey);
					} catch (Exception $e) {
						$this->logger->error("putKey: cannot revoke previous api key: " . $e->getMessage());
					}
				}

				// create and set new api key
				// this might fail if an olvid server api have not been set
				try {
					$newApiKey = OlvidServerUtils::requestNewApiKey($this->olvidAppConfig);
					$this->olvidUserConfig->setApiKey($user->getUID(), $newApiKey);
				} catch (Exception $e) {
					// TODO if cannot set attribute return an error
					$this->logger->warning("putKey: cannot create new api key: " . $e->getMessage());
				}

				// set user identity
				$this->olvidUserConfig->setIdentity($user->getUID(), $identity);

				// sign user details and store them
				$userDetails = OlvidUserDetails::computeDetails($user, $this->olvidUserConfig);
				$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);
			}
			// trying to put the same identity
			else if ($previousIdentity === $identity) {
				// if user lack an api key, try to get and set one
				if (!$previousApiKey) {
					// this might fail if an olvid server api have not been set
					try {
						$newApiKey = OlvidServerUtils::requestNewApiKey($this->olvidAppConfig);
						$this->olvidUserConfig->setApiKey($user->getUID(), $newApiKey);
					} catch (Exception $e) {
						// TODO if cannot set attribute return an error
						$this->logger->warning("putKey: cannot create new api key: " . $e->getMessage());
					}
				}

				// sign user details and store them
				$userDetails = OlvidUserDetails::computeDetails($user, $this->olvidUserConfig);
				$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);
			}
			// trying to override previous identity
			else if ($previousIdentity !== $identity) {
				// revoke current api key if there is one
				if ($previousApiKey) {
					try {
						OlvidServerUtils::revokeApiKey($this->olvidAppConfig, $previousApiKey);
					} catch (Exception $e) {
						$this->logger->error("putKey: cannot revoke previous api key: " . $e->getMessage());
					}
				}

				// clear old identity
				$this->olvidUserConfig->setIdentity($user->getUID(), '');
				// clear the nonce so that any identity enrolled with this user is automatically unbound from keycloak
				$this->olvidUserConfig->setNonce($user->getUID(), '');

				// sign out the user: set revoked_before to mark any token signed before now to be revoked
				$this->olvidUserConfig->setSessionRevokedBefore($user->getUID(), time());

				// create and set new api key
				// this might fail if an olvid server api have not been set
				try {
					$newApiKey = OlvidServerUtils::requestNewApiKey($this->olvidAppConfig);
					$this->olvidUserConfig->setApiKey($user->getUID(), $newApiKey);
				} catch (Exception $e) {
					// TODO if cannot set attribute return an error
					$this->logger->warning("putKey: cannot create new api key: " . $e->getMessage());
				}

				// we can now set new identity
				$this->olvidUserConfig->setIdentity($user->getUID(), $identity);

				// sign user details and store them
				$userDetails = OlvidUserDetails::computeDetails($user, $this->olvidUserConfig);
				$userDetails->sign($this->olvidUserConfig, $this->olvidAppConfig);
			}

			return $this->success();
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
    }
}
