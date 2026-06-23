<?php

declare(strict_types=1);

namespace OCA\Olvid\Utils;

use OCA\Olvid\AppInfo\Application;
use OCP\IConfig;

class OlvidUserConfigManager {
	private const USER_CONFIG_FIRSTNAME = 'olvid-firstname';
	private const USER_CONFIG_LASTNAME = 'olvid-lastname';
	private const USER_CONFIG_COMPANY = 'olvid-company';
	private const USER_CONFIG_POSITION = 'olvid-position';
	private const USER_CONFIG_IDENTITY = 'olvid-identity';
	private const USER_CONFIG_API_KEY = 'olvid-api-key';
	private const USER_CONFIG_NONCE = 'olvid-nonce';
	private const USER_CONFIG_SIGNED_DETAILS = 'olvid-signed-details';
	private const USER_CONFIG_ROLE = 'olvid-role';
	private const USER_CONFIG_FULL_SEARCH_FIELD = 'olvid-search';
	private const USER_CONFIG_IS_BOT = 'olvid-is-bot';
	private const USER_CONFIG_MAGIC_TOKEN = 'olvid-magic-token';
	private const USER_CONFIG_MAGIC_TOKEN_EXPIRATION = 'olvid-magic-token-expiration';
	private const USER_CONFIG_SESSION_REVOKED_BEFORE = 'olvid-session-revoked-before';

	private const ALL_KEYS = [
		self::USER_CONFIG_FIRSTNAME,
		self::USER_CONFIG_LASTNAME,
		self::USER_CONFIG_COMPANY,
		self::USER_CONFIG_POSITION,
		self::USER_CONFIG_IDENTITY,
		self::USER_CONFIG_API_KEY,
		self::USER_CONFIG_NONCE,
		self::USER_CONFIG_SIGNED_DETAILS,
		self::USER_CONFIG_ROLE,
		self::USER_CONFIG_FULL_SEARCH_FIELD,
		self::USER_CONFIG_IS_BOT,
		self::USER_CONFIG_MAGIC_TOKEN,
		self::USER_CONFIG_SESSION_REVOKED_BEFORE,
	];

	public function __construct(
		private readonly IConfig $config,
	) {
	}

	private function getStringOrNull(string $uid, string $key): ?string {
		$value = $this->config->getUserValue($uid, Application::APP_ID, $key);
		return trim($value) !== '' ? $value : null;
	}

	private function setString(string $uid, string $key, string $value): void {
		$this->config->setUserValue($uid, Application::APP_ID, $key, $value);
	}

	/*
	 * Public API
	 */
	// firstname
	public function getFirstname(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_FIRSTNAME);
	}
	public function setFirstname(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_FIRSTNAME, $value);
	}

	// lastname
	public function getLastname(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_LASTNAME);
	}
	public function setLastname(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_LASTNAME, $value);
	}

	// company
	public function getCompany(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_COMPANY);
	}
	public function setCompany(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_COMPANY, $value);
	}

	// position
	public function getPosition(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_POSITION);
	}
	public function setPosition(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_POSITION, $value);
	}

	// identity
	public function getIdentity(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_IDENTITY);
	}
	public function setIdentity(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_IDENTITY, $value);
	}
	public function hasIdentity(string $uid): bool {
		return $this->getIdentity($uid) !== null;
	}

	// api key
	public function getApiKey(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_API_KEY);
	}
	public function setApiKey(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_API_KEY, $value);
	}

	// nonce
	public function getNonce(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_NONCE);
	}
	public function setNonce(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_NONCE, $value);
	}

	// signed details
	public function getSignedDetails(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_SIGNED_DETAILS);
	}
	public function setSignedDetails(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_SIGNED_DETAILS, $value);
	}

	// role
	public function getRole(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_ROLE);
	}
	public function setRole(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_ROLE, $value);
	}

	// full search field
	public function getFullSearchField(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_FULL_SEARCH_FIELD);
	}
	public function setFullSearchField(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_FULL_SEARCH_FIELD, $value);
	}

	// is bot
	public function getIsBot(string $uid): bool {
		return $this->getStringOrNull($uid, self::USER_CONFIG_IS_BOT) === '1';
	}
	public function setIsBot(string $uid, bool $value): void {
		$this->setString($uid, self::USER_CONFIG_IS_BOT, $value ? '1' : '0');
	}

	// magic token
	public function getMagicToken(string $uid): ?string {
		return $this->getStringOrNull($uid, self::USER_CONFIG_MAGIC_TOKEN);
	}
	public function setMagicToken(string $uid, string $value): void {
		$this->setString($uid, self::USER_CONFIG_MAGIC_TOKEN, $value);
	}

	// magic token expiration
	public function getMagicTokenExpiration(string $uid): ?int {
		$value = $this->getStringOrNull($uid, self::USER_CONFIG_MAGIC_TOKEN_EXPIRATION);
		$timestamp = (int)$value;
		return $timestamp !== 0 ? $timestamp : null;
	}
	public function setMagicTokenExpiration(string $uid, int $value): void {
		$this->setString($uid, self::USER_CONFIG_MAGIC_TOKEN_EXPIRATION, (string)$value);
	}

	public function clearMagicToken(string $uid): void {
		$this->config->deleteUserValue($uid, Application::APP_ID, self::USER_CONFIG_MAGIC_TOKEN);
		$this->config->deleteUserValue($uid, Application::APP_ID, self::USER_CONFIG_MAGIC_TOKEN_EXPIRATION);
	}

	// session revoked before
	public function getSessionRevokedBefore(string $uid): ?int {
		$value = $this->getStringOrNull($uid, self::USER_CONFIG_SESSION_REVOKED_BEFORE);
		$timestamp = (int)$value;
		return $timestamp !== 0 ? $timestamp : null;
	}
	public function setSessionRevokedBefore(string $uid, int $value): void {
		$this->setString($uid, self::USER_CONFIG_SESSION_REVOKED_BEFORE, (string)$value);
	}

	/*
	 * Clean methods
	 */
	public function deleteUserConfig(string $uid): void {
		foreach (self::ALL_KEYS as $key) {
			$this->config->deleteUserValue($uid, Application::APP_ID, $key);
		}
	}

	public function removeIdentity(string $uid): void {
		$this->config->deleteUserValue($uid, Application::APP_ID, self::USER_CONFIG_IDENTITY);
	}
}
