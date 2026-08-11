<?php

namespace OCA\Olvid\Cron;

use OCA\Olvid\Utils\Context\OlvidContext;
use OCA\Olvid\Utils\Context\OlvidServer\InvalidConfigurationException;
use OCA\Olvid\Utils\Context\OlvidServer\OlvidServerException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\DB\Exception;
use Psr\Log\LoggerInterface;

class OlvidServerSynchronizationTask extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private readonly LoggerInterface $logger,
		private readonly OlvidContext $context,
	) {
		parent::__construct($time);

		// Run once every day
		parent::setInterval(3600 * 24);
		parent::setTimeSensitivity(self::TIME_INSENSITIVE);
	}

	/**
	 * @throws Exception
	 */
	public function run($argument): void {
		self::staticRun($this->context, $this->logger);
	}

	/**
	 * @throws Exception
	 */
	public static function staticRun(OlvidContext $context, LoggerInterface $logger): void {
		/*
		 * first check olvid server is properly set up
		 */
		if ($context->nextcloud->appManager->getOlvidServerUrl() === null
			|| $context->nextcloud->appManager->getOlvidServerApiKey() === null) {
			$logger->error('OlvidServerSynchronizationTask: olvid server is not properly configured');
			return ;
		}

		$logger->info('OlvidServerSynchronizationTask: task start');
		// sync with server and remove ghost api keys and push topics
		$syncResponse = self::syncWithServer($context, $logger);

		// request api keys for olvid user with no api keys (might have been clean by previous sync task)
		self::requestMissingApiKeys($context, $logger);
		// request missing push topic (global and for olvid groups) (might have been clean by previous sync task)
		self::requestMissingPushTopics($context, $logger);

		if ($syncResponse === null) {
			$logger->info('OlvidServerSynchronizationTask: task end with an error');
		} else {
			$unknownApiKeysCount = array_key_exists('unknownApiKeys', $syncResponse) ? sizeof($syncResponse['unknownApiKeys']) : 0;
			$unknownPushTopicsCount = array_key_exists('unknownPushTopics', $syncResponse) ? sizeof($syncResponse['unknownPushTopics']) : 0;
			$logger->info("OlvidServerSynchronizationTask: task end (cleaned api keys: $unknownApiKeysCount, cleaned push topics: $unknownPushTopicsCount)");
		}
	}

	/**
	 * @throws Exception
	 */
	private static function syncWithServer(OlvidContext $context, LoggerInterface $logger): ?array {
		/*
		 * Build list of known api key and push topics to send to server for synchronization
		 */
		// list api keys attributes to olvid users
		$allOlvidUsers = $context->db->user->getAll();
		$knownApiKeys = [];
		foreach ($allOlvidUsers as $olvidUser) {
			if ($olvidUser->getApiKey() !== null) {
				$knownApiKeys[] = $olvidUser->getApiKey();
			}
		}

		// list used push topics
		$knownPushTopics = [];
		// global push topic
		$globalPushTopic = $context->nextcloud->appManager->getGlobalPushTopic();
		if ($globalPushTopic !== null) {
			$knownPushTopics[] = $globalPushTopic;
		}
		// group push topics
		$allOlvidGroups = $context->db->group->getAll();
		foreach ($allOlvidGroups as $olvidGroup) {
			if ($olvidGroup->getPushTopic() !== null) {
				$knownPushTopics[] = $olvidGroup->getPushTopic();
			}
		}

		/*
		 * Execute server request
		 */
		try {
			$response = $context->olvidServer->serverSynchronization($knownApiKeys, $knownPushTopics);
		} catch (InvalidConfigurationException|OlvidServerException $e) {
			$logger->error('OlvidServerSynchronizationTask: cannot perform server synchronization', ['exception' => $e]);
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
					$context->db->user->update($apiKeyToUserMap[$unknownApiKey]);
				} catch (Exception $e) {
					$logger->error('OlvidServerSynchronizationTask: cannot update user api key in database', ['exception' => $e]);
				}
			}
		}

		/*
		 * Handle unknown push topic
		 */
		// unset global push topic if unknown
		if (in_array($globalPushTopic, $unknownPushTopics)) {
			$context->nextcloud->appManager->setGlobalPushTopic(null);
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
					$context->db->group->update($pushTopicsToGroupMap[$unknownPushTopic]);
				} catch (Exception $e) {
					$logger->error('OlvidServerSynchronizationTask: cannot update group push topic in database', ['exception' => $e]);
				}
			}
		}

		return $response;
	}

	/**
	 * Request api keys for olvid users with an identity and with no api key
	 * @throws Exception
	 */
	private static function requestMissingApiKeys(OlvidContext $context, LoggerInterface $logger): void {
		$olvidUsersWithIdentity = $context->db->user->getAllWithIdentity();
		foreach ($olvidUsersWithIdentity as $olvidUserWithIdentity) {
			try {
				if ($olvidUserWithIdentity->getApiKey() === null) {
					$olvidUserWithIdentity->setApiKey($context->olvidServer->requestNewApiKey());
					$context->db->group->update($olvidUserWithIdentity);
				}
			} catch (InvalidConfigurationException|OlvidServerException $e) {
				$logger->error('requestMissingPushTopics: cannot request user api key', ['exception' => $e]);
			} catch (Exception $e) {
				$logger->error('requestMissingPushTopics: cannot update user in database', ['exception' => $e]);
			}
		}
	}

	/*
	 * Request a global push topic if there is no
	 * Request push topics for enabled group with no push topic
	 */
	private static function requestMissingPushTopics(OlvidContext $context, LoggerInterface $logger): void {
		// global push topic
		try {
			if ($context->nextcloud->appManager->getGlobalPushTopic() === null) {
				$context->nextcloud->appManager->setGlobalPushTopic($context->olvidServer->requestNewPushTopic());
			}
		} catch (InvalidConfigurationException|OlvidServerException $e) {
			$logger->error('requestMissingPushTopics: cannot request global push topic', ['exception' => $e]);
		}

		// group push topics
		$enabledOlvidGroups = $context->db->group->getEnabledGroups();
		foreach ($enabledOlvidGroups as $enabledOlvidGroup) {
			try {
				if ($enabledOlvidGroup->getPushTopic() === null) {
					$enabledOlvidGroup->setPushTopic($context->olvidServer->requestNewPushTopic());
					$context->db->group->update($enabledOlvidGroup);
				}
			} catch (InvalidConfigurationException|OlvidServerException $e) {
				$logger->error('requestMissingPushTopics: cannot request group push topic', ['exception' => $e]);
			} catch (Exception $e) {
				$logger->error('requestMissingPushTopics: cannot update group in database', ['exception' => $e]);
			}
		}
	}
}
