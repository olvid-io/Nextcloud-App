<?php

namespace OCA\Olvid\Cron;

use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\Context\OlvidServer\OlvidServerException;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

/*
 * This task send all api keys and push topics known locally to the olvid server.
 * Olvid server will remove any unused element in its database. It will also return api keys and push topic he does know,
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
		if ($this->context->nextcloud->appManager->getOlvidServerUrl() === null
			|| $this->context->nextcloud->appManager->getOlvidServerApiKey() === null) {
			$this->logger->error('OlvidServerSynchronizationTask: olvid server is not properly configured');
			return ;
		}

		$this->logger->info('OlvidServerSynchronizationTask: task start');
		// sync with server and remove ghost api keys and push topics
		$syncResponse = self::syncWithServer();

		// request api keys for olvid user with no api keys (might have been clean by previous sync task)
		self::requestMissingApiKeys();
		// request missing push topic (global and for olvid groups) (might have been clean by previous sync task)
		self::requestMissingPushTopics();

		if ($syncResponse === null) {
			$this->logger->info('OlvidServerSynchronizationTask: task end with an error');
		} else {
			$unknownApiKeysCount = array_key_exists('unknownApiKeys', $syncResponse) ? sizeof($syncResponse['unknownApiKeys']) : 0;
			$unknownPushTopicsCount = array_key_exists('unknownPushTopics', $syncResponse) ? sizeof($syncResponse['unknownPushTopics']) : 0;
			$this->logger->info("OlvidServerSynchronizationTask: task end (cleaned api keys: $unknownApiKeysCount, cleaned push topics: $unknownPushTopicsCount)");
		}
	}

	/**
	 * @throws Exception
	 */
	private function syncWithServer(): ?array {
		/*
		 * Build list of known api key and push topics to send to server for synchronization
		 */
		// list api keys attributes to olvid users
		$allOlvidUsers = $this->context->db->user->getAll();
		$knownApiKeys = [];
		foreach ($allOlvidUsers as $olvidUser) {
			if ($olvidUser->getApiKey() !== null) {
				$knownApiKeys[] = $olvidUser->getApiKey();
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
			if ($olvidGroup->getPushTopic() !== null) {
				$knownPushTopics[] = $olvidGroup->getPushTopic();
			}
		}

		/*
		 * Execute server request
		 */
		try {
			$response = $this->context->olvidServer->serverSynchronization($knownApiKeys, $knownPushTopics);
		} catch (InvalidConfigurationException|OlvidServerException $e) {
			$this->logger->error('OlvidServerSynchronizationTask: cannot perform server synchronization', ['exception' => $e]);
			return null;
		}

		/*
		 * Parse response
		 */
		$unknownApiKeys = array_key_exists('unknownApiKeys', $response) ? $response['unknownApiKeys'] : [];
		$unknownPushTopics = array_key_exists('unknownPushTopics', $response) ? $response['unknownPushTopics'] : [];
		//		$licenseCount = $response['licenseCount'];

		/*
		 * Handle unknown api keys
		 */
		if ($unknownApiKeys) {
			// build an apiKey to user map
			$apiKeyToUserMap = [];
			foreach ($allOlvidUsers as $olvidUser) {
				if ($olvidUser->getApiKey() !== null) {
					$apiKeyToUserMap[$olvidUser->getApiKey()] = $olvidUser;
				}
			}
			// remove api key for users with an unknown api key
			foreach ($unknownApiKeys as $unknownApiKey) {
				$apiKeyToUserMap[$unknownApiKey]?->setApiKey(null);
				try {
					$this->context->db->user->update($apiKeyToUserMap[$unknownApiKey]);
				} catch (Exception $e) {
					$this->logger->error('OlvidServerSynchronizationTask: cannot update user api key in database', ['exception' => $e]);
				}
			}
		}

		/*
		 * Handle unknown push topic
		 */
		// unset global push topic if unknown
		if (in_array($globalPushTopic, $unknownPushTopics)) {
			$this->context->nextcloud->appManager->setGlobalPushTopic(null);
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
				try {
					$pushTopicsToGroupMap[$unknownPushTopic]?->setPushTopic(null);
					$this->context->db->group->update($pushTopicsToGroupMap[$unknownPushTopic]);
				} catch (Exception $e) {
					$this->logger->error('OlvidServerSynchronizationTask: cannot update group push topic in database', ['exception' => $e]);
				}
			}
		}

		return $response;
	}

	/**
	 * Request api keys for olvid users with an identity and with no api key
	 * @throws Exception
	 */
	private function requestMissingApiKeys(): void {
		$olvidUsersWithIdentity = $this->context->db->user->getAllWithIdentity();
		foreach ($olvidUsersWithIdentity as $olvidUserWithIdentity) {
			try {
				if ($olvidUserWithIdentity->getApiKey() === null) {
					$olvidUserWithIdentity->setApiKey($this->context->olvidServer->requestNewApiKey());
					$this->context->db->group->update($olvidUserWithIdentity);
				}
			} catch (InvalidConfigurationException|OlvidServerException $e) {
				$this->logger->error('requestMissingPushTopics: cannot request user api key', ['exception' => $e]);
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
