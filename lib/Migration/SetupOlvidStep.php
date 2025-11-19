<?php

namespace OCA\Olvid\Migration;

use OCA\OIDCIdentityProvider\Exceptions\ClientNotFoundException;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCP\DB\Exception;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

use OCA\OIDCIdentityProvider\Db\ClientMapper as OidcClientMapper;
use OCA\OIDCIdentityProvider\Db\Client as OidcClient;

class SetupOlvidStep implements IRepairStep {
	protected LoggerInterface $logger;
	private IConfig $config;
	private IAppConfig $appConfig;
	private oidcClientMapper $oidcClientMapper;

	public function __construct(
		LoggerInterface $logger,
		IConfig $config,
		IAppConfig $appConfig,
		oidcClientMapper $clientMapper,
	) {
		$this->config = $config;
		$this->appConfig = $appConfig;
		$this->logger = $logger;
		$this->oidcClientMapper = $clientMapper;
	}

	// TODO split steps ?
	public function getName(): string {
		return "Setup olvid";
	}

	/**
	 * @param IOutput $output
	 * @throws \Exception
	 */
	public function run(IOutput $output): void {
		// first of all, check oidc application is there
		// TODO how not to hardcode oidc id ? :S
		if (!in_array("oidc", $this->appConfig->getApps())) {
			throw new \Exception("You must install oidc application before installing Olvid");
		}

		// this allows to authenticate using oidc bearer tokens (used to access olvid api from olvid devices)
		$this->config->setSystemValue("oidc_provider_bearer_validation", true);

		/*
		 ** JWKS
		 */
		$this->createJwksKey($output);

		/*
		 * OpenId connect client
		 */
		// check if we already created an oidc client for olvid
		$clientId = $this->appConfig->getValueString(Application::APP_ID, Constants::APP_CONFIG_CLIENT_ID_KEY);
		if ($clientId) {
			try {
				$olvidClient = $this->oidcClientMapper->getByIdentifier($clientId);
				if (!$olvidClient) {
					throw new ClientNotFoundException();
				}
				// Olvid client exists, we are good !
				return;
			} catch (ClientNotFoundException $e) {
				$this->logger->error("SetupOidcStep: previous oidc client not found in db" . $e->getMessage());
				throw new \Exception("Previous oidc client not found in db");
			}
		}

		// create a new olvid client
		$olvidClient = new OidcClient(
			 Constants::OIDC_CLIENT_NAME,
			Constants::OIDC_REDIRECT_URIS,
			Constants::OIDC_ALGORITHM,
			Constants::OIDC_TYPE,
			Constants::OIDC_FLOW_TYPE,
			Constants::OIDC_TOKEN_TYPE,
			Constants::OIDC_ALLOWED_SCOPES,
			Constants::OIDC_EMAIL_REGEXP,
		);

		try {
			$olvidClient = $this->oidcClientMapper->insert($olvidClient);
		} catch (Exception $e) {
			$this->logger->error("SetupOidcStep: cannot insert new oidc client in db: " . $e->getMessage());
			return;
		}

		// store olvid client in app configuration
		$this->appConfig->setValueString(Application::APP_ID, Constants::APP_CONFIG_CLIENT_ID_KEY, $olvidClient->getClientIdentifier());

		$this->logger->info("SetupOidcStep: setup a new OIDC client: " . $olvidClient->getClientIdentifier());
	}

	public function createJwksKey(IOutput $output): void {
		$output->info("Check for jwks key material.");
		$keyId = $this->appConfig->getValueString(Application::APP_ID, Constants::APP_CONFIG_JWK_KEY_ID);
		if (!$keyId) {
			$keyId = uuid_create();

			// generate key pair
			$config = [
				"digest_alg" => "sha256",
				"private_key_type" => OPENSSL_KEYTYPE_EC,
				"curve_name" => "prime256v1" // This is the OpenSSL name for P-256
			];
			$res = openssl_pkey_new($config);
			openssl_pkey_export($res, $privateKey);
			$details = openssl_pkey_get_details($res);

			// store keys
			$this->appConfig->setValueString(Application::APP_ID, Constants::APP_CONFIG_JWK_KEY_TYPE, "ES256");
			$this->appConfig->setValueString(Application::APP_ID, Constants::APP_CONFIG_JWK_PRIVATE_KEY, $privateKey);
			$this->appConfig->setValueString(Application::APP_ID, Constants::APP_CONFIG_JWK_PUBLIC_KEY, $details["key"]);
			$x = rtrim(strtr(base64_encode($details['ec']['x']), '+/', '-_'), '='); // base64 url encode
			$y = rtrim(strtr(base64_encode($details['ec']['y']), '+/', '-_'), '='); // base64 url encode
			$this->appConfig->setValueString(Application::APP_ID, Constants::APP_CONFIG_JWK_PUBLIC_KEY_X, $x);
			$this->appConfig->setValueString(Application::APP_ID, Constants::APP_CONFIG_JWK_PUBLIC_KEY_Y, $y);
			$this->appConfig->setValueString(Application::APP_ID, Constants::APP_CONFIG_JWK_KEY_ID, $keyId);
		}
	}
}

