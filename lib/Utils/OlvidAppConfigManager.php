<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCP\IAppConfig;

class OlvidAppConfigManager {
	// Olvid Server
	public const APP_CONFIG_OLVID_SERVER_URL = 'olvid-server-url';
	public const APP_CONFIG_OLVID_SERVER_API_KEY = 'olvid-server-api-key';
	public const APP_CONFIG_GLOBAL_PUSH_TOPIC = 'olvid-global-push-topic';

	// App options
	public const APP_CONFIG_ENABLE_EVERYONE_GROUP = 'olvid-enable-everyone-group';

	// json web key
	private const APP_CONFIG_JWK_KEY_ID = 'olvid-jwk-key-id';
	private const APP_CONFIG_JWK_KEY_TYPE = 'olvid-jwk-key-type';
	private const APP_CONFIG_JWK_PRIVATE_KEY = 'olvid-jwk-private-key';
	private const APP_CONFIG_JWK_PUBLIC_KEY = 'olvid-jwk-public-key';
	private const APP_CONFIG_JWK_PUBLIC_KEY_X = 'olvid-jwk-public-key-x';
	private const APP_CONFIG_JWK_PUBLIC_KEY_Y = 'olvid-jwk-public-key-y';

	private const ALL_KEYS = [
		self::APP_CONFIG_OLVID_SERVER_URL,
		self::APP_CONFIG_OLVID_SERVER_API_KEY,
		self::APP_CONFIG_GLOBAL_PUSH_TOPIC,
		self::APP_CONFIG_JWK_KEY_ID,
		self::APP_CONFIG_JWK_KEY_TYPE,
		self::APP_CONFIG_JWK_PRIVATE_KEY,
		self::APP_CONFIG_JWK_PUBLIC_KEY,
		self::APP_CONFIG_JWK_PUBLIC_KEY_X,
		self::APP_CONFIG_JWK_PUBLIC_KEY_Y,
		self::APP_CONFIG_ENABLE_EVERYONE_GROUP
	];

	public function __construct(
		private readonly IAppConfig $appConfig,
	) {
	}

	private function getStringOrNull(string $key): ?string {
		$value = $this->appConfig->getValueString(Application::APP_ID, $key);
		return trim($value) !== '' ? $value : null;
	}

	/*
	 * Get fields set up in settings
	 */
	// olvid server url
	public function getOlvidServerUrl(): string {
		return $this->appConfig->getValueString(Application::APP_ID, self::APP_CONFIG_OLVID_SERVER_URL, Constants::DEFAULT_OLVID_SERVER);
	}
	// api key
	public function getOlvidServerApiKey(): ?string {
		return $this->getStringOrNull(self::APP_CONFIG_OLVID_SERVER_API_KEY);
	}
	// global push topic
	public function getGlobalPushTopic(): ?string {
		return $this->getStringOrNull(self::APP_CONFIG_GLOBAL_PUSH_TOPIC);
	}
	public function setGlobalPushTopic(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_GLOBAL_PUSH_TOPIC, $value);
	}

	/*
	 * Application options
	 */
	// enable everyone group (set in settings)
	public function isEveryoneGroupEnabled(): ?bool {
//		return $this->appConfig->getValueBool(Application::APP_ID, self::APP_CONFIG_ENABLE_EVERYONE_GROUP);
		// TODO use boolean value when checkbox are fixed
		$value =  $this->getStringOrNull(self::APP_CONFIG_ENABLE_EVERYONE_GROUP);
		return !($value === null || $value === "false");
	}

	/*
	 * Json Web Key (jwk)
	 */
	// id
	public function getJwkKeyId(): ?string {
		return $this->getStringOrNull(self::APP_CONFIG_JWK_KEY_ID);
	}
	public function setJwkKeyId(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_JWK_KEY_ID, $value);
	}
	// type
	public function getJwkKeyType(): ?string {
		return $this->getStringOrNull(self::APP_CONFIG_JWK_KEY_TYPE);
	}
	public function setJwkKeyType(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_JWK_KEY_TYPE, $value);
	}
	// private key
	public function getJwkKeyPrivateKey(): ?string {
		return $this->getStringOrNull(self::APP_CONFIG_JWK_PRIVATE_KEY);
	}
	public function setJwkKeyPrivateKey(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_JWK_PRIVATE_KEY, $value);
	}
	// public key
	public function getJwkKeyPublicKey(): ?string {
		return $this->getStringOrNull(self::APP_CONFIG_JWK_PUBLIC_KEY);
	}
	public function setJwkKeyPublicKey(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_JWK_PUBLIC_KEY, $value);
	}
	// public key X
	public function getJwkKeyPublicKeyX(): ?string {
		return $this->getStringOrNull(self::APP_CONFIG_JWK_PUBLIC_KEY_X);
	}
	public function setJwkKeyPublicKeyX(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_JWK_PUBLIC_KEY_X, $value);
	}
	// public key Y
	public function getJwkKeyPublicKeyY(): ?string {
		return $this->getStringOrNull(self::APP_CONFIG_JWK_PUBLIC_KEY_Y);
	}
	public function setJwkKeyPublicKeyY(string $value): void {
		$this->appConfig->setValueString(Application::APP_ID, self::APP_CONFIG_JWK_PUBLIC_KEY_Y, $value);
	}

	/*
	 * Clean method
	 */
	public function deleteAppConfig(): void {
		foreach (self::ALL_KEYS as $key) {
			$this->appConfig->deleteKey(Application::APP_ID, $key);
		}
	}
}
