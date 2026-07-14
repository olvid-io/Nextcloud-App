<?php

namespace OCA\Olvid\Utils\Context\OlvidServer;

use JsonSerializable;

// KeycloakQueryJson in keycloak plugin
class JsonOlvidServerRequest implements JsonSerializable {
	public const QUERY_REQUEST_NEW_API_KEY = 1;
	public const QUERY_REVOKE_API_KEY = 2;
	public const QUERY_NOTIFY_PUSH_TOPIC_USERS = 3;
	public const QUERY_REQUEST_NEW_PUSH_TOPIC = 4;
	public const QUERY_DELETE_PUSH_TOPIC = 5;
	public const QUERY_API_KEY_SYNCHRONIZATION = 6;
	public const QUERY_NOTIFY_SINGLE_USER = 7;
	public const QUERY_REQUEST_NEW_BOT_API_KEY = 8;
	public const QUERY_CURRENT_KEYCLOAK_VERSION = 9;


	public int $q;
	public string $keycloakApiKey;
	public string $keycloakVersion;
	public string $pluginVersion;
	public string $apiKeyToRevoke;
	public string $pushTopic;
	public array $knownApiKeys;
	public array $knownPushTopics;
	public string $userIdentity;

	public function jsonSerialize(): array {
		return (array)$this;
	}
}
