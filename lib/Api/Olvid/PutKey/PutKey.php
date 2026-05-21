<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Olvid\PutKey;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\OlvidAppHandler;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCA\Olvid\Utils\OlvidServer\OlvidServerUtils;
use OCP\AppFramework\Http\Response;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use OCP\PreConditionNotMetException;

class PutKey extends OlvidAppHandler {
	/**
	 * @param IUser $user
	 * @throws PreConditionNotMetException
	 */
	public function handler(IUser $user, array $jsonParameters): Response {
		$putKeyRequest = new JsonPutKeyRequest($jsonParameters);

		if (!$putKeyRequest->identity) {
			$this->logger->error("putKey: identity not set");
			return $this->invalidRequestDevice();
		}
		// TODO: also check that identity is a valid Olvid identity

		// TODO feature revocation
		// TODO feature allow self revocation

		// prevent concurrent executions of this task for a same user
		$lockKey = Application::APP_ID .  "/olvid-rest/putKey/" . $user->getUID();
		try {
			$this->lockingProvider->acquireLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		} catch (LockedException) {
			return $this->invalidRequestDevice();
		}

		try {
			$previousIdentity = $this->config->getUserValue(
				$user->getUID(),
				Application::APP_ID,
				Constants::USER_ATTRIBUTE_OLVID_IDENTITY
			);
			$previousApiKey = $this->config->getUserValue(
				$user->getUID(),
				Application::APP_ID,
				Constants::USER_ATTRIBUTE_OLVID_API_KEY
			);

			// no identity already registered
			if (!$previousIdentity) {
				// user is not supposed to have an api key, revoke it if there is one
				if ($previousApiKey) {
					try {
						OlvidServerUtils::revokeApiKey($this->appConfig, $previousApiKey);
					} catch (Exception $e) {
						$this->logger->error("putKey: cannot revoke previous api key: " . $e->getMessage());
					}
				}

				// create and set new api key
				// this might fail if an olvid server api have not been set
				try {
					$newApiKey = OlvidServerUtils::requestNewApiKey($this->appConfig);
					$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY, $newApiKey);
				} catch (Exception $e) {
					// TODO if cannot set attribute return an error
					$this->logger->warning("putKey: cannot create new api key: " . $e->getMessage());
				}

				// set user identity
				$this->config->setUserValue(
					$user->getUID(),
					Application::APP_ID,
					Constants::USER_ATTRIBUTE_OLVID_IDENTITY,
					$putKeyRequest->identity
				);
				// sign user details and store them
				OlvidUserDetails::signUserDetails($user, $this->config, $this->appConfig);
			}
			// trying to put the same identity
			else if ($previousIdentity === $putKeyRequest->identity) {
				// if user lack an api key, try to get and set one
				if (!$previousApiKey) {
					// this might fail if an olvid server api have not been set
					try {
						$newApiKey = OlvidServerUtils::requestNewApiKey($this->appConfig);
						$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY, $newApiKey);
					} catch (Exception $e) {
						// TODO if cannot set attribute return an error
						$this->logger->warning("putKey: cannot create new api key: " . $e->getMessage());
					}
				}
				// sign user details and store them
				OlvidUserDetails::signUserDetails($user, $this->config, $this->appConfig);
			}
			// trying to override previous identity
			else if ($previousIdentity !== $putKeyRequest->identity) {
				// revoke current api key if there is one
				if ($previousApiKey) {
					try {
						OlvidServerUtils::revokeApiKey($this->appConfig, $previousApiKey);
					} catch (Exception $e) {
						$this->logger->error("putKey: cannot revoke previous api key: " . $e->getMessage());
					}
				}

				// clear old identity
				$this->config->setUserValue(
					$user->getUID(),
					Application::APP_ID,
					Constants::USER_ATTRIBUTE_OLVID_IDENTITY,
					null
				);
				// clear the nonce so that any identity enrolled with this user is automatically unbound from keycloak
				$this->config->setUserValue(
					$user->getUID(),
					Application::APP_ID,
					Constants::USER_ATTRIBUTE_OLVID_NONCE,
					null
				);

				// sign out the user: set revoked_before to mark any token signed before now to be revoked
				$this->config->setUserValue(
					$user->getUID(),
					Application::APP_ID,
					Constants::USER_ATTRIBUTE_OLVID_SESSION_REVOKED_BEFORE,
					time()
				);

				// create and set new api key
				// this might fail if an olvid server api have not been set
				try {
					$newApiKey = OlvidServerUtils::requestNewApiKey($this->appConfig);
					$this->config->setUserValue($user->getUID(), Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_API_KEY, $newApiKey);
				} catch (Exception $e) {
					// TODO if cannot set attribute return an error
					$this->logger->warning("putKey: cannot create new api key: " . $e->getMessage());
				}

				// we can now set new identity
				$this->config->setUserValue(
					$user->getUID(),
					Application::APP_ID,
					Constants::USER_ATTRIBUTE_OLVID_IDENTITY,
					$putKeyRequest->identity
				);
				// sign user details and store them
				OlvidUserDetails::signUserDetails($user, $this->config, $this->appConfig);
			}

			return $this->success();
		} finally {
			$this->lockingProvider->releaseLock($lockKey, ILockingProvider::LOCK_EXCLUSIVE);
		}
    }
}
