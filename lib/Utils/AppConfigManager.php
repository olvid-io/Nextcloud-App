<?php

namespace OCA\Olvid\Utils;

use OCA\Olvid\AppInfo\Application;
use OCP\IAppConfig;

class AppConfigManager {
	// Olvid Server
	private const APP_CONFIG_OLVID_SERVER_URL = "olvid-server-url";
	// TODO how to associate those values to settings ?
	public const APP_CONFIG_OLVID_SERVER_API_KEY = "olvid-server-api-key";
	// Open Id Connect
	public const APP_CONFIG_OIDC_CLIENT_ID = "olvid-oidc-client-id";
	// json web key
	private const APP_CONFIG_JWK_KEY_ID = "olvid-jwk-key-id";
	private const APP_CONFIG_JWK_KEY_TYPE = "olvid-jwk-key-type";
	private const APP_CONFIG_JWK_PRIVATE_KEY = "olvid-jwk-private-key";
	private const APP_CONFIG_JWK_PUBLIC_KEY = "olvid-jwk-public-key";
	private const APP_CONFIG_JWK_PUBLIC_KEY_X = "olvid-jwk-public-key-x";
	private const APP_CONFIG_JWK_PUBLIC_KEY_Y = "olvid-jwk-public-key-y";

	/*
	 * Generic methods
	 */
	private static function getValue(IAppConfig $appConfig, string $key): ?string {
		$value = $appConfig->getValueString(Application::APP_ID, $key);
		return trim($value) ? $value : null;
	}
	public static function setValue(IAppConfig $appConfig, string $key, ?string $value): void {
		$appConfig->setValueString(Application::APP_ID, $key, $value ?? "");
	}

	/*
	 * Olvid Server
	 */
	// url
	public static function getOlvidServerUrl(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_OLVID_SERVER_URL);
	}
	public static function setOlvidServerUrl(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_OLVID_SERVER_URL, $value);
	}
	// api key
	public static function getOlvidServerApiKey(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_OLVID_SERVER_API_KEY);
	}
	public static function setOlvidServerApiKey(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_OLVID_SERVER_API_KEY, $value);
	}

	/*
	 * Open Id Connect (oidc)
	 */
	public static function getOidcClientId(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_OIDC_CLIENT_ID);
	}
	public static function setOidcClientId(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_OIDC_CLIENT_ID, $value);
	}

	/*
	 * Json Web Key (jwk)
	 */
	// id
	public static function getJwkKeyId(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_JWK_KEY_ID);
	}
	public static function setJwkKeyId(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_JWK_KEY_ID, $value);
	}
	// type
	public static function getJwkKeyType(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_JWK_KEY_TYPE);
	}
	public static function setJwkKeyType(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_JWK_KEY_TYPE, $value);
	}
	// private key
	public static function getJwkKeyPrivateKey(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_JWK_PRIVATE_KEY);
	}
	public static function setJwkKeyPrivateKey(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_JWK_PRIVATE_KEY, $value);
	}
	// public key
	public static function getJwkKeyPublicKey(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_JWK_PUBLIC_KEY);
	}
	public static function setJwkKeyPublicKey(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_JWK_PUBLIC_KEY, $value);
	}
	// public key X
	public static function getJwkKeyPublicKeyX(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_JWK_PUBLIC_KEY_X);
	}
	public static function setJwkKeyPublicKeyX(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_JWK_PUBLIC_KEY_X, $value);
	}
	// public key Y
	public static function getJwkKeyPublicKeyY(IAppConfig $appConfig): ?string {
		return AppConfigManager::getValue($appConfig, self::APP_CONFIG_JWK_PUBLIC_KEY_Y);
	}
	public static function setJwkKeyPublicKeyY(IAppConfig $appConfig, ?string $value): void {
		AppConfigManager::setValue($appConfig, self::APP_CONFIG_JWK_PUBLIC_KEY_Y, $value);
	}
}
