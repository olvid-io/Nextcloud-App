<?php

declare(strict_types=1);

namespace OCA\Olvid\Settings;

namespace OCA\Olvid\DeclarativeSettings;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsForm;
use Psr\Log\LoggerInterface;

class Admin implements IDeclarativeSettingsForm {
	public function __construct(
		private readonly IL10N $l,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
	) {
	}

	public function getSchema(): array {
		return [
			'id' => 'olvid',
			'priority' => 10,
			'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
			'section_id' => 'olvid',
			'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_INTERNAL,
			'title' => 'Olvid',
			'description' => $this->l->t('Configure Olvid application.'),
			'doc_url' => '',
			'fields' => [
				[
					'id' => OlvidAppConfigManager::APP_CONFIG_OLVID_SERVER_URL,
					'title' => $this->l->t('Olvid Server url'),
					'description' => $this->l->t('Olvid distribution server url.'),
					'type' => DeclarativeSettingsTypes::URL,
					'options' => '',
					'default' => Constants::DEFAULT_OLVID_SERVER,
					'placeholder' => '',
				],
				[
					'id' => OlvidAppConfigManager::APP_CONFIG_OLVID_SERVER_API_KEY,
					'title' => $this->l->t('Olvid Server Api Key'),
					'description' => $this->l->t('Api Key used to communicate with your Olvid distribution server. Send us an email to get one: contact@olvid.io'),
					'type' => DeclarativeSettingsTypes::PASSWORD,
					'options' => '',
					'default' => '',
					'placeholder' => '',
				],
				[
					'id' => OlvidAppConfigManager::APP_CONFIG_EVERYONE_GROUP_IDS,
					'title' => $this->l->t('Automatic groups'),
					'description' => $this->l->t('Automatically add new Nextcloud accounts to those groups. This is useful for maintaining Olvid discussions with all users.'),
					'type' => DeclarativeSettingsTypes::MULTI_SELECT,
					// do disable to add admin group to those groups
					'options' => $this->getNonAdminGroupIds(),
					'default' => []
				],
				[
					'id' => OlvidAppConfigManager::APP_CONFIG_OPEN_GROUP_IDS,
					'title' => $this->l->t('Open groups'),
					'description' => $this->l->t('A list of groups users can choose to join or leave themselves'),
					'type' => DeclarativeSettingsTypes::MULTI_SELECT,
					// do disable to add admin group to those groups
					'options' => $this->getNonAdminGroupIds(),
					'default' => []
				],
			]
		];
	}

	public function getNonAdminGroupIds(): array {
		$nonAdminGroupIds = [];
		foreach ($this->groupManager->search("") as $group) {
			if ($group->getGID() === "admin") {
				continue;
			}
			$nonAdminGroupIds[] = $group->getGID();
		}
		return $nonAdminGroupIds;
	}
}
