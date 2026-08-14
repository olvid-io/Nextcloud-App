<?php

namespace OCA\Olvid\SetupCheck;

use Exception;
use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;

class OlvidConfigurationSetupCheck implements ISetupCheck {
	public function __construct(
		private readonly IL10N $l10n,
		private readonly OlvidContext $context,
	) {
	}

	public function getName(): string {
		return $this->l10n->t('Olvid configuration');
	}

	public function getCategory(): string {
		return 'system';
	}

	public function run(): SetupResult {
		if (!$this->context->nextcloud->appManager->getOlvidServerApiKey()) {
			// TODO improve error message
			return SetupResult::error($this->l10n->t('Olvid server api key is not configured, Olvid App cannot work properly'));
		}

		try {
			$pushTopic = $this->context->olvidServer->requestNewPushTopic();
			$this->context->olvidServer->revokePushTopicNoFail($pushTopic);
			return SetupResult::success($this->l10n->t('Olvid app is properly configured.'));
		} catch (Exception) {
			// TODO improve error message
			return SetupResult::error($this->l10n->t('Olvid server is unreachable or not properly configured.'));
		}
	}
}
