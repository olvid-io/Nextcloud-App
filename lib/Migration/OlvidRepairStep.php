<?php

namespace OCA\Olvid\Migration;

use Exception;
use OCA\Olvid\Listener\EveryoneGroupEventListener;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\RandomUtil;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Log\LoggerInterface;

class OlvidRepairStep implements IRepairStep {
	public function __construct(
		private readonly LoggerInterface $logger,
		private readonly OlvidAppConfigManager $olvidAppConfig,
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
	) {
	}

	public function getName(): string {
		return 'Repair olvid';
	}

	/**
	 * @param IOutput $output
	 * @throws Exception
	 */
	public function run(IOutput $output): void {
		/*
		 ** Create JWKS key
		 */
		$output->info('Olvid: Check for jwks key material.');
		$this->logger->info('Olvid: Check for jwks key material.');
		$this->createJwks($output);

		/*
		 * Clean database
		 */
		// TODO check group consistency in app storage
		// TODO check user consistency in user storage

		/*
		 ** Everyone group
		 */
		$this->syncEveryoneGroup();
	}

	private function createJwks(IOutput $output): void {
		$keyId = $this->olvidAppConfig->getJwkKeyId();
		if (!$keyId) {
			$output->info('Olvid: Create a new JWKS key');
			$this->logger->info('Olvid: Create a new JWKS key');

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
			$this->olvidAppConfig->setJwkKeyType('ES256');
			$this->olvidAppConfig->setJwkKeyPrivateKey($privateKey);
			$this->olvidAppConfig->setJwkKeyPublicKey($details['key']);
			$this->olvidAppConfig->setJwkKeyPublicKeyX($x);
			$this->olvidAppConfig->setJwkKeyPublicKeyY($y);
			$this->olvidAppConfig->setJwkKeyId($keyId);
		}
	}

	private function syncEveryoneGroup(): void {
		if ($this->olvidAppConfig->isEveryoneGroupEnabled()) {
			// create group if necessary
			$everyoneGroup = $this->groupManager->get(EveryoneGroupEventListener::EVERYONE_GROUP_ID);
			if (!$everyoneGroup) {
				$everyoneGroup = $this->groupManager->createGroup(EveryoneGroupEventListener::EVERYONE_GROUP_ID);
				$this->logger->info('syncEveryoneGroup: everyone group created');
			}

			// add any missing member to group
			$everyoneMembersUid = array_map(function ($user) { return $user->getUID(); }, $everyoneGroup->getUsers());
			$allUsers = $this->userManager->search('');
			foreach ($allUsers as $user) {
				if (!in_array($user->getUID(), $everyoneMembersUid)) {
					$everyoneGroup->addUser($user);
					$this->logger->info('syncEveryoneGroup: added user to everyone group: ' . $user->getUID());
				}
			}
		} else {
			// delete group if it exists
			$everyoneGroup = $this->groupManager->get(EveryoneGroupEventListener::EVERYONE_GROUP_ID);
			$everyoneGroup?->delete();
		}
	}
}
