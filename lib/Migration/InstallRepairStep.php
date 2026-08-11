<?php

namespace OCA\Olvid\Migration;

use Exception;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\RandomUtil;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class InstallRepairStep implements IRepairStep {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidAppConfigManager $olvidAppConfig,
	) {
	}

	public function getName(): string {
		return 'Install Olvid App';
	}

	public function run(IOutput $output): void {
		try {
			self::createSignatureKeyIfNecessary($output, $this->olvidAppConfig);
		} catch (Exception) {
			$this->logger->error('install step: cannot create signature key');
		}
	}

	public static function createSignatureKeyIfNecessary(IOutput $output, OlvidAppConfigManager $olvidAppConfig): void {
		$keyId = $olvidAppConfig->getJwkKeyId();
		if (!$keyId) {
			$output->info('create a new JWKS key');

			$keyId = RandomUtil::uuid_create();

			// generate key pair
			$config = [
				'digest_alg' => 'sha256',
				'private_key_type' => OPENSSL_KEYTYPE_EC,
				'curve_name' => 'prime256v1' // This is the OpenSSL name for P-256
			];
			$res = openssl_pkey_new($config);
			openssl_pkey_export($res, $privateKey);
			$details = openssl_pkey_get_details($res);

			// compute public key coordinates to display in jwks format
			$x = rtrim(strtr(base64_encode($details['ec']['x']), '+/', '-_'), '='); // base64 url encode
			$y = rtrim(strtr(base64_encode($details['ec']['y']), '+/', '-_'), '='); // base64 url encode

			// store key in app config
			$olvidAppConfig->setJwkKeyType('ES256');
			$olvidAppConfig->setJwkKeyPrivateKey($privateKey);
			$olvidAppConfig->setJwkKeyPublicKey($details['key']);
			$olvidAppConfig->setJwkKeyPublicKeyX($x);
			$olvidAppConfig->setJwkKeyPublicKeyY($y);
			$olvidAppConfig->setJwkKeyId($keyId);
		}
	}
}
