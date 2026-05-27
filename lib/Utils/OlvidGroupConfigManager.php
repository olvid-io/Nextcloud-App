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
	private const GROUP_BLOB = '-signed-blob';
	private const GROUP_CONFIG_LAST_MODIFICATION_TIMESTAMP = '-last-modification-timestamp';

	private const ALL_KEYS = [
		self::GROUP_CONFIG_ENABLED,
		self::GROUP_CONFIG_CUSTOM_NAME,
		self::GROUP_CONFIG_DESCRIPTION,
		self::GROUP_BLOB,
		self::GROUP_CONFIG_LAST_MODIFICATION_TIMESTAMP,
	];


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

	// signed blob
	// Olvid description
	public function getBlob(string $groupId): ?string {
		return $this->getStringOrNull(self::getKey($groupId, self::GROUP_BLOB));
	}
	public function setBlob(string $groupId, string $blob): void {
		$this->appConfig->setValueString(Application::APP_ID, self::getKey($groupId, self::GROUP_BLOB), $blob);
	}


	// last modification timestamp
	public function getLastModificationTimestamp(string $groupId): ?int {
		return $this->getStringOrNull(self::getKey($groupId, self::GROUP_CONFIG_LAST_MODIFICATION_TIMESTAMP));
	}
	public function setLastModificationTimestamp(string $groupId, int $timestamp): void {
		$this->appConfig->setValueString(Application::APP_ID, self::getKey($groupId, self::GROUP_CONFIG_LAST_MODIFICATION_TIMESTAMP), (string)$timestamp);
	}

	/*
	 ** Clean method
	 */
	public function deleteGroupConfig(string $groupId): void {
		foreach (self::ALL_KEYS as $key) {
			$this->appConfig->deleteKey(Application::APP_ID, self::getKey($groupId, $key));
		}
	}
	public function getGroupConfig(string $groupId): array {
		return $this->appConfig->getAllValues(Application::APP_ID, self::GROUP_CONFIG_PREFIX . $groupId);
	}
}
