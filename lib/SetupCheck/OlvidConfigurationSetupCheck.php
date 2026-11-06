<?php

namespace OCA\Olvid\SetupCheck;

use OCA\Olvid\Utils\Context\OlvidContext;
use OCP\IL10N;
use OCP\SetupCheck\ISetupCheck;
use OCP\SetupCheck\SetupResult;
use Throwable;

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
		if (!$this->context->nextcloud->appManager->getOlvidServerUrl()) {
			return SetupResult::error($this->l10n->t('Olvid server url is not set. Configure it in Olvid section of Admin settings.'));
		}
		if (!$this->context->nextcloud->appManager->getOlvidServerApiKey()) {
			return SetupResult::error($this->l10n->t('Olvid server Api Key is not set. Configure it in Olvid Admin settings section, or send us an email to get one: contact@olvid.io'));
		}

		try {
			if ($this->context->olvidServer->requestCheckCredentialsNoFail()) {
				return SetupResult::success($this->l10n->t('Olvid app is properly configured.'));
			}
		} catch (Throwable) {}
		return SetupResult::error($this->l10n->t('Olvid server is unreachable or not properly configured, check parameters in Olvid section of Admin settings.'));
	}
}
