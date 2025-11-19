<?php

namespace OCA\Olvid\Utils;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCP\IAppConfig;
use OCP\IRequest;

use OCA\OIDCIdentityProvider\Exceptions\ClientNotFoundException;
use OCA\OIDCIdentityProvider\Util\DiscoveryGenerator;
use OCA\OIDCIdentityProvider\Db\ClientMapper as OidcClientMapper;
use OCA\OIDCIdentityProvider\Event\TokenValidationRequestEvent as OidcTokenValidationRequestEvent;

trait ConfigurationToolsUtil {
	/**
	 * @throws Exception
	 */
	public static function getServerConfigurationLink(IAppConfig $appConfig, OidcClientMapper $oidcClientMapper, IRequest $request) : string {
		// get client identifier
		$clientIdentifier = $appConfig->getValueString(Application::APP_ID, Constants::APP_CONFIG_CLIENT_ID_KEY);
		if (!$clientIdentifier) {
			throw new Exception('No OpenId Client provided');
		}

		// get client
		try {
			$client = $oidcClientMapper->getByIdentifier($clientIdentifier);
		} catch (ClientNotFoundException $e) {
			throw new Exception("Olvid client not found: {$e->getMessage()}");
		}

		# TODO change (and check associated redirect URI)
		$serverUrl = "https://server.dev.olvid.io";
		$apiUrl = substr($request->getRequestUri(), 0, strlen($request->getRequestUri()) - strlen("/olvid-rest/configuration"));
		$keycloakUrl = "{$request->getServerProtocol()}://{$request->getServerHost()}$apiUrl";
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
