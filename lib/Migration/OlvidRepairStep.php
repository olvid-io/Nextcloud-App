<?php

namespace OCA\Olvid\Migration;

use Exception;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\IAppConfig;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class OlvidRepairStep implements IRepairStep {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly IAppConfig $appConfig,
	) {}

	public function getName(): string {
		return "Setup olvid";
	}

	/**
	 * @param IOutput $output
	 * @throws Exception
	 */
	public function run(IOutput $output): void {
		/*
		 ** Create JWKS key
		 */
		$output->info("Olvid: Check for jwks key material.");
		$this->logger->info("Olvid: Check for jwks key material.");
		$keyId = AppConfigManager::getJwkKeyId($this->appConfig);
		if (!$keyId) {
			$output->info("Olvid: Create a new JWKS key");
			$this->logger->info("Olvid: Create a new JWKS key");

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

