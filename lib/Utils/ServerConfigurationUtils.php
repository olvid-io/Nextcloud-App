<?php

namespace OCA\Olvid\Utils;

use Exception;
use OCP\IAppConfig;

use OCA\OIDCIdentityProvider\Exceptions\ClientNotFoundException;
use OCA\OIDCIdentityProvider\Db\ClientMapper as OidcClientMapper;
use OCP\IURLGenerator;

class ServerConfigurationUtils {
	/**
	 * @throws Exception
	 */
	public static function getServerConfigurationLink(IAppConfig $appConfig, OidcClientMapper $oidcClientMapper, IURLGenerator $urlGenerator) : string {
		// get client identifier
		$clientIdentifier = AppConfigManager::getOidcClientId($appConfig);
		if (!$clientIdentifier) {
			throw new Exception('No OpenId Client provided');
		}

		// get client
		try {
			$client = $oidcClientMapper->getByIdentifier($clientIdentifier);
		} catch (ClientNotFoundException $e) {
			throw new Exception("Olvid client not found: {$e->getMessage()}");
		}

		# TODO change server url used (and check associated redirect URI)
		$serverUrl = AppConfigManager::getOlvidServerUrl($appConfig) ?? "";
		# TODO improve link computation ...
		$keycloakUrl = $urlGenerator->linkToOCSRouteAbsolute("") . "/apps/olvid";
		$clientId = $client->getClientIdentifier();
		$clientSecret = $client->getSecret();

		$encodedConf = base64_encode(json_encode([
			"server" => $serverUrl,
			"keycloak" => [
				"server" => $keycloakUrl,
				"cid" => $clientId,
				"secret" => $clientSecret,
			]
		]));
		return "https://configuration.olvid.io/#$encodedConf";
	}
}
