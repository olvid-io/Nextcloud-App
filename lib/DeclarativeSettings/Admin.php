<?php

declare(strict_types=1);

namespace OCA\Olvid\Settings;

namespace OCA\Olvid\DeclarativeSettings;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\IL10N;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsForm;

class Admin implements IDeclarativeSettingsForm {
	public function __construct(
		private IL10N $l,
	) {
	}

	public function getSchema(): array {
		// TODO translation: add strings in translation files
		return [
			'id' => 'olvid', // unique form id
			'priority' => 10, // declarative section priority (ordering)
			'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN, // admin, personal
			'section_id' => 'olvid', // existing section id or your custom section id
			'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_INTERNAL, // external, internal (handled by core to store in appconfig and preferences)
			'title' => 'Olvid', // NcSettingsSection name
			'description' => $this->l->t(''),
			'doc_url' => '', // NcSettingsSection doc_url for documentation or help page, empty string if not needed
			'fields' => [
				[
					'id' => OlvidAppConfigManager::APP_CONFIG_OLVID_SERVER_URL,
					'title' => $this->l->t('Olvid Server'),
					'description' => $this->l->t('Optionally specify an alternative olvid distribution server to use'),
					'type' => DeclarativeSettingsTypes::URL,
					'options' => '',
					'default' => Constants::DEFAULT_OLVID_SERVER,
					'placeholder' => '',
				],
				[
					'id' => OlvidAppConfigManager::APP_CONFIG_OLVID_SERVER_API_KEY,
					'title' => $this->l->t('Olvid Server Api Key'),
					'description' => $this->l->t('Optional api key used to generate Olvid+ licenses for users'),
					'type' => DeclarativeSettingsTypes::PASSWORD,
					'options' => '',
					'default' => '',
					'placeholder' => '',
				],
				[
					'id' => OlvidAppConfigManager::APP_CONFIG_ENABLE_EVERYONE_GROUP,
					'title' => 'Enable Everyone Group',
					'description' => 'Create and maintain a Nextcloud group with every user. You can then create an Olvid discussion in your Olvid Groups console.',
					'type' => DeclarativeSettingsTypes::SELECT,
					'options' => ['false', 'true'],
					'default' => 'false',
				],
				// TODO implements using checkbox when this is issue is fix https://github.com/nextcloud/server/issues/60903
				//				[
				//					'id' => OlvidAppConfigManager::APP_CONFIG_ENABLE_EVERYONE_GROUP,
				//					'title' => 'Enable Everyone Group',
				//					'description' => 'Create and maintain a Nextcloud group with every user. You can then create an Olvid discussion in your Olvid Groups console.',
				//					'type' => DeclarativeSettingsTypes::CHECKBOX,
				//					'options' => [false, true],
				//					'default' => false,
				//				],
			]
		];
	}
}
