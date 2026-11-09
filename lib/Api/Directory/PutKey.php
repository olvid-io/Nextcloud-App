<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\Directory;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\JsonRevocationData;
use OCA\Olvid\ResponseDefinitions;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IUser;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;

/**
 * @template-extends AbstractAuthenticatedDeviceApiHandler<JSONResponse<Http::STATUS_OK, OlvidDirectoryApiSuccess|OlvidDirectoryApiError, array{}>>
 * @psalm-import-type OlvidDirectoryApiSuccess from ResponseDefinitions
 * @psalm-import-type OlvidDirectoryApiError from ResponseDefinitions
 */
class PutKey extends AbstractAuthenticatedDeviceApiHandler {
	/**
	 * @throws MultipleObjectsReturnedException
	 * @throws \OCP\DB\Exception
	 * @return JSONResponse<Http::STATUS_OK, OlvidDirectoryApiSuccess|OlvidDirectoryApiError, array{}>
	 * @psalm-suppress LessSpecificImplementedReturnType
	 */
	public function handler(array $jsonParameters, ?IUser $nextcloudUser): JSONResponse {
		try {
			$base64Identity = isset($jsonParameters[Constants::PUT_KEY_REQUEST_IDENTITY]) ? (string)$jsonParameters[Constants::PUT_KEY_REQUEST_IDENTITY] : null;
			if (!$base64Identity) {
				$this->logger->error('putKey: identity not set');
				return $this->invalidRequest();
			}

			$bytesIdentity = base64_decode($base64Identity, true);
			if ($bytesIdentity === false) {
				$this->logger->error('putKey: cannot decode base64 identity');
				return $this->invalidRequest();
			}

			// check database field constraint are respected
			if (strlen($bytesIdentity) > 1024) {
				$this->logger->error('putKey: identity is too long');
				return $this->invalidRequest();
			}

			// TODO: also check that identity is a valid Olvid identity
		} catch (Exception $exception) {
			$this->logger->warning('putKey: parse error: ', ['exception' => $exception]);
			return $this->invalidRequest();
		}

		// check if this identity have not been revoked
		$revocations = $this->context->db->revocation->getByTypeAndBytesIdentity($bytesIdentity, JsonRevocationData::REVOCATION_TYPE_REVOKE_ID);
		if ($revocations !== []) {
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
			// create Olvid user if it does not exist
			$olvidUser = $this->context->db->user->getOrCreate($nextcloudUser->getUID(), $nextcloudUser->getDisplayName());

			$base64PreviousIdentity = base64_encode($olvidUser->getBytesIdentity() ?? '');

			// no identity already registered
			if (!$base64PreviousIdentity) {
				// user is not supposed to have a license key, revoke it if there is one
				if ($olvidUser->getLicenseKey()) {
					$this->context->olvidServer->revokeLicenseKeyNoFail($olvidUser->getLicenseKey());
					$olvidUser->setLicenseKey(null);
				}

				// create and set new license key
				// this might fail if an olvid directory license have not been set
				try {
					$newLicenseKey = $this->context->olvidServer->requestNewLicenseKey();
					$olvidUser->setLicenseKey($newLicenseKey);
				} catch (InvalidConfigurationException) {
					$this->logger->warning('PutKey: cannot create user license key: invalid configuration');
				} catch (Exception $e) {
					$this->logger->warning('PutKey: cannot create user license key: unexpected exception', ['exception' => $e]);
				}

				// set user identity, and registration timestamp
				$olvidUser->setBytesIdentity($bytesIdentity);
				$olvidUser->setRegistrationTimestamp(TimeUtil::currentTimeMillis());

				// sign user details and store them
				$jsonUserDetails = $olvidUser->computeJsonUserDetails();
				$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));

				// save user in database
				$olvidUser = $this->context->db->user->update($olvidUser);

				// TODO delay this asynchronously to avoid any timeout if there is a lot of groups
				// recompute all groups blob for this user enabled groups
				$nextcloudGroups = $this->context->nextcloud->groupManager->getUserGroups($nextcloudUser);
				foreach ($nextcloudGroups as $nextcloudGroup) {
					// check group exists and is enabled
					$olvidGroup = $this->context->db->group->getByGroupIdOrNull($nextcloudGroup->getGID());
					if ($olvidGroup === null) {
						continue;
					}
					if (!$olvidGroup->getEnabled()) {
						continue;
					}

					try {
						// re-compute group blob and save in db
						$jsonGroupBlob = $olvidGroup->computeBlob($this->context->nextcloud->groupManager->getNextcloudGroupMemberIds($nextcloudGroup), $this->context);
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
				// if user lack a license key, try to get and set one
				if (!$olvidUser->getLicenseKey()) {
					// this might fail if an olvid directory license have not been set
					try {
						$newLicenseKey = $this->context->olvidServer->requestNewLicenseKey();
						$olvidUser->setLicenseKey($newLicenseKey);
					} catch (InvalidConfigurationException) {
						$this->logger->warning('PutKey: cannot create user license key: invalid configuration');
					} catch (Exception $e) {
						$this->logger->warning('PutKey: cannot create user license key: unexpected exception', ['exception' => $e]);
					}
				}

				// sign user details and store them
				$jsonUserDetails = $olvidUser->computeJsonUserDetails();
				$olvidUser->setSignedDetails($this->context->signatory->sign($jsonUserDetails->jsonSerialize()));
				$olvidUser = $this->context->db->user->update($olvidUser);
			}
			// new and previous identity are different,
			else {
				// trying to override previous identity: not supposed to happen in Nextcloud, always return an error
				// and let user / admin revoke identity in application
				return $this->identityAlreadyUploaded();
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
