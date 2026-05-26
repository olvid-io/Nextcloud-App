<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils;

use OCA\Olvid\AppInfo\Application;
use OCP\IAppConfig;

class OlvidGroupConfigManager {
	private const GROUP_CONFIG_PREFIX      = 'group-';
	private const GROUP_CONFIG_ENABLED     = '-enabled';
	private const GROUP_CONFIG_CUSTOM_NAME = '-custom-name';
	private const GROUP_CONFIG_DESCRIPTION = '-description';

	public function __construct(private readonly IAppConfig $appConfig) {}

	private static function getKey(string $gid, string $suffix): string {
		return self::GROUP_CONFIG_PREFIX . $gid . $suffix;
	}

	private function getStringOrNull(string $key): ?string {
		$value = $this->appConfig->getValueString(Application::APP_ID, $key);
		return trim($value) !== '' ? $value : null;
	}

	/*
	 * Public API
	 */
	// enable olvid discussion
	public function getIsOlvidDiscussionEnabled(string $groupId): bool{
		return $this->appConfig->getValueBool(Application::APP_ID, self::getKey($groupId, self::GROUP_CONFIG_ENABLED));
	}
	public function setIsOlvidDiscussionEnabled(string $groupId, bool $enabled): void {
		$this->appConfig->setValueBool(Application::APP_ID, self::getKey($groupId, self::GROUP_CONFIG_ENABLED), $enabled);
	}

	// custom Olvid name
	public function getCustomName(string $groupId): ?string {
		return $this->getStringOrNull(self::getKey($groupId, self::GROUP_CONFIG_CUSTOM_NAME));
	}
	public function setCustomName(string $groupId, string $name): void {
		$this->appConfig->setValueString(Application::APP_ID, self::getKey($groupId, self::GROUP_CONFIG_CUSTOM_NAME), $name);
	}

	// Olvid description
	public function getDescription(string $groupId): ?string {
		return $this->getStringOrNull(self::getKey($groupId, self::GROUP_CONFIG_DESCRIPTION));
	}
	public function setDescription(string $groupId, string $description): void {
		$this->appConfig->setValueString(Application::APP_ID, self::getKey($groupId, self::GROUP_CONFIG_DESCRIPTION), $description);
	}

	/*
	 ** Clean method
	 */
	public function deleteGroupConfig(string $groupId): void {
		$this->appConfig->deleteKey(Application::APP_ID, self::getKey($groupId, self::GROUP_CONFIG_ENABLED));
		$this->appConfig->deleteKey(Application::APP_ID, self::getKey($groupId, self::GROUP_CONFIG_CUSTOM_NAME));
		$this->appConfig->deleteKey(Application::APP_ID, self::getKey($groupId, self::GROUP_CONFIG_DESCRIPTION));
	}
}
