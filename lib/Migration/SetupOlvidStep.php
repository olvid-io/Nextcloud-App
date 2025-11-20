<?php

namespace OCA\Olvid\Migration;

use OCA\OIDCIdentityProvider\Exceptions\ClientNotFoundException;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\AppConfigManager;
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

		// TODO do not hardcode
		/*
		 ** Olvid server
		 */
		AppConfigManager::setOlvidServerUrl($this->appConfig, "https://server.dev.olvid.io");

		/*
		 ** JWKS
		 */
		$this->createJwksKey($output);

		/*
		 * OpenId connect client
		 */
		// check if we already created an oidc client for olvid
		$clientId = AppConfigManager::getOidcClientId($this->appConfig);
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
		AppConfigManager::setOidcClientId($this->appConfig, $olvidClient->getClientIdentifier());

		$this->logger->info("SetupOidcStep: setup a new OIDC client: " . $olvidClient->getClientIdentifier());
	}

	public function createJwksKey(IOutput $output): void {
		$output->info("Check for jwks key material.");
		$keyId = AppConfigManager::getJwkKeyId($this->appConfig);
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

			// compute public key coordinates to display in jwks format
			$x = rtrim(strtr(base64_encode($details['ec']['x']), '+/', '-_'), '='); // base64 url encode
			$y = rtrim(strtr(base64_encode($details['ec']['y']), '+/', '-_'), '='); // base64 url encode

			// store key in app config
			AppConfigManager::setJwkKeyType($this->appConfig, "ES256");
			AppConfigManager::setJwkKeyPrivateKey($this->appConfig, $privateKey);
			AppConfigManager::setJwkKeyPublicKey($this->appConfig, $details["key"]);
			AppConfigManager::setJwkKeyPublicKeyX($this->appConfig, $x);
			AppConfigManager::setJwkKeyPublicKeyY($this->appConfig, $y);
			AppConfigManager::setJwkKeyId($this->appConfig, $keyId);
		}
	}
}

