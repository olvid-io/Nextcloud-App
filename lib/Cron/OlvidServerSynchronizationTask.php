<?php

namespace OCA\Olvid\Cron;

use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\Context\OlvidServer\OlvidServerException;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

/*
 * This task send all license keys and push topics known locally to the olvid server.
 * Olvid server will remove any unused element in its database. It will also return license keys and push topic he does know,
 * so we can refresh them.
 */
class OlvidServerSynchronizationTask {
	public function __construct(
		private readonly OlvidContext $context,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @throws Exception
	 */
	public function run(): void {
		/*
		 * first check olvid server is properly set up
		 */
		if (!$this->context->nextcloud->appManager->getOlvidServerUrl()) {
			$this->logger->error('OlvidServerSynchronizationTask: olvid server is not properly configured');
			return ;
		}
		if ($this->context->olvidServer->requestCheckCredentialsNoFail()) {
			$this->context->nextcloud->appManager->setIsOlvidServerConfigurationValid(true);
		} else {
			$this->context->nextcloud->appManager->setIsOlvidServerConfigurationValid(false);
			$this->logger->error('OlvidServerSynchronizationTask: olvid server is not properly configured');
			return ;
		}

		$this->logger->info('OlvidServerSynchronizationTask: task start');
		// sync with server and remove ghost license keys and push topics
		$syncResponse = self::syncWithServer();

		// request license keys for olvid user with no license (might have been clean by previous sync task)
		self::requestMissingLicenseKeys();
		// request missing push topic (global and for olvid groups) (might have been clean by previous sync task)
		self::requestMissingPushTopics();

		if ($syncResponse === null) {
			$this->logger->info('OlvidServerSynchronizationTask: task end with an error');
		} else {
			$unknownLicenseKeysCount = array_key_exists('unknownApiKeys', $syncResponse) ? count($syncResponse['unknownApiKeys']) : 0;
			$unknownPushTopicsCount = array_key_exists('unknownPushTopics', $syncResponse) ? count($syncResponse['unknownPushTopics']) : 0;
			$this->logger->info(sprintf('OlvidServerSynchronizationTask: task end (cleaned license keys: %d, cleaned push topics: %d)', $unknownLicenseKeysCount, $unknownPushTopicsCount));
		}
	}

	/**
	 * @throws Exception
	 */
	private function syncWithServer(): ?array {
		/*
		 * Build list of known license key and push topics to send to server for synchronization
		 */
		// list license keys associated with an olvid user
		$allOlvidUsers = $this->context->db->user->getAll();
		$knownLicenseKeys = [];
		foreach ($allOlvidUsers as $olvidUser) {
			$licenseKey = $olvidUser->getLicenseKey();
			if ($licenseKey !== null) {
				$knownLicenseKeys[] = $licenseKey;
			}
		}

		// list used push topics
		$knownPushTopics = [];
		// global push topic
		$globalPushTopic = $this->context->nextcloud->appManager->getGlobalPushTopic();
		if ($globalPushTopic !== null) {
			$knownPushTopics[] = $globalPushTopic;
		}

		// group push topics
		$allOlvidGroups = $this->context->db->group->getAll();
		foreach ($allOlvidGroups as $olvidGroup) {
			$pushTopic = $olvidGroup->getPushTopic();
			if ($pushTopic !== null) {
				$knownPushTopics[] = $pushTopic;
			}
		}

		/*
		 * Execute server request
		 */
		try {
			$response = $this->context->olvidServer->serverSynchronization($knownLicenseKeys, $knownPushTopics);
		} catch (InvalidConfigurationException|OlvidServerException $e) {
			$this->logger->error('OlvidServerSynchronizationTask: cannot perform server synchronization', ['exception' => $e]);
			return null;
		}

		/*
		 * Parse response
		 */
		$unknownLicenseKeys = array_key_exists('unknownApiKeys', $response) ? $response['unknownApiKeys'] : [];
		$unknownPushTopics = array_key_exists('unknownPushTopics', $response) ? $response['unknownPushTopics'] : [];
		//		$licenseCount = $response['licenseCount'];

		/*
		 * Handle unknown license keys
		 */
		if ($unknownLicenseKeys) {
			// build a license key to user map
			$licenseKeyToUserMap = [];
			foreach ($allOlvidUsers as $olvidUser) {
				if ($olvidUser->getLicenseKey() !== null) {
					$licenseKeyToUserMap[$olvidUser->getLicenseKey()] = $olvidUser;
				}
			}

			// remove license key for users with an unknown license
			foreach ($unknownLicenseKeys as $licenseKey) {
				if (array_key_exists($licenseKey, $licenseKeyToUserMap) && $licenseKeyToUserMap[$licenseKey] !== null) {
					$licenseKeyToUserMap[$licenseKey]->setLicenseKey(null);
					try {
						$this->context->db->user->update($licenseKeyToUserMap[$licenseKey]);
					} catch (Exception $e) {
						$this->logger->error('OlvidServerSynchronizationTask: cannot update user license key in database', ['exception' => $e]);
					}
				}
			}
		}

		/*
		 * Handle unknown push topic
		 */
		// unset global push topic if unknown
		if (in_array($globalPushTopic, $unknownPushTopics)) {
			$this->context->nextcloud->appManager->unsetGlobalPushTopic();
		}

		// unset groups push topic if unknown
		if ($unknownPushTopics) {
			// build a push topic to group map
			$pushTopicsToGroupMap = [];
			foreach ($allOlvidGroups as $olvidGroup) {
				if ($olvidGroup->getPushTopic() !== null) {
					$pushTopicsToGroupMap[$olvidGroup->getPushTopic()] = $olvidGroup;
				}
			}

			// revoke push topic for groups with an unknown push topic
			foreach ($unknownPushTopics as $unknownPushTopic) {
				if (array_key_exists($unknownPushTopic, $pushTopicsToGroupMap) && $pushTopicsToGroupMap[$unknownPushTopic] !== null) {
					try {
						$pushTopicsToGroupMap[$unknownPushTopic]->setPushTopic(null);
						$this->context->db->group->update($pushTopicsToGroupMap[$unknownPushTopic]);
					} catch (Exception $e) {
						$this->logger->error('OlvidServerSynchronizationTask: cannot update group push topic in database', ['exception' => $e]);
					}
				}
			}
		}

		return $response;
	}

	/**
	 * Request license keys for olvid users with an identity and with no license key
	 * @throws Exception
	 */
	private function requestMissingLicenseKeys(): void {
		$olvidUsersWithIdentity = $this->context->db->user->getAllWithIdentity();
		foreach ($olvidUsersWithIdentity as $olvidUserWithIdentity) {
			try {
				if ($olvidUserWithIdentity->getLicenseKey() === null) {
					$olvidUserWithIdentity->setLicenseKey($this->context->olvidServer->requestNewLicenseKey());
					$this->context->db->user->update($olvidUserWithIdentity);
				}
			} catch (InvalidConfigurationException|OlvidServerException $e) {
				$this->logger->error('requestMissingPushTopics: cannot request user license key', ['exception' => $e]);
			} catch (Exception $e) {
				$this->logger->error('requestMissingPushTopics: cannot update user in database', ['exception' => $e]);
			}
		}
	}

	/*
	 * Request a global push topic if there is no
	 * Request push topics for enabled group with no push topic
	 */
	private function requestMissingPushTopics(): void {
		// global push topic
		try {
			if ($this->context->nextcloud->appManager->getGlobalPushTopic() === null) {
				$this->context->nextcloud->appManager->setGlobalPushTopic($this->context->olvidServer->requestNewPushTopic());
			}
		} catch (InvalidConfigurationException|OlvidServerException $e) {
			$this->logger->error('requestMissingPushTopics: cannot request global push topic', ['exception' => $e]);
		}

		// group push topics
		$enabledOlvidGroups = $this->context->db->group->getEnabledGroups();
		foreach ($enabledOlvidGroups as $enabledOlvidGroup) {
			try {
				if ($enabledOlvidGroup->getPushTopic() === null) {
					$enabledOlvidGroup->setPushTopic($this->context->olvidServer->requestNewPushTopic());
					$this->context->db->group->update($enabledOlvidGroup);
				}
			} catch (InvalidConfigurationException|OlvidServerException $e) {
				$this->logger->error('requestMissingPushTopics: cannot request group push topic', ['exception' => $e]);
			} catch (Exception $e) {
				$this->logger->error('requestMissingPushTopics: cannot update group in database', ['exception' => $e]);
			}
		}
	}
}
