<?php

declare(strict_types=1);

namespace OCA\Olvid\Settings;

namespace OCA\Olvid\DeclarativeSettings;

use OCA\Olvid\Utils\AppConfigManager;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsForm;

class Admin implements IDeclarativeSettingsForm {
	public function getSchema(): array {
		return [
			'id' => 'olvid', // unique form id
			'priority' => 10, // declarative section priority (ordering)
			'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN, // admin, personal
			'section_id' => 'olvid', // existing section id or your custom section id
			'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_INTERNAL, // external, internal (handled by core to store in appconfig and preferences)
			'title' => 'Olvid', // NcSettingsSection name
			'description' => '',
			'doc_url' => '', // NcSettingsSection doc_url for documentation or help page, empty string if not needed
			'fields' => [
				[
					'id' => AppConfigManager::APP_CONFIG_OLVID_SERVER_API_KEY,
					'title' => 'Olvid Server Api Key',
					'description' => 'Optional api key used to generate Olvid+ licenses for users',
					'type' => DeclarativeSettingsTypes::PASSWORD,
					'options' => "",
					'default' => "",
					'placeholder'=> "00000000-0000-0000-0000-000000000000",
				],
			]
		];
	}
}
