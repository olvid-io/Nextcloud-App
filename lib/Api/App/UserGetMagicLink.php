<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\App;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\RandomUtil;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

class UserGetMagicLink {
	public function __construct(
		private readonly OlvidUserConfigManager $userConfig,
		private readonly OlvidAppConfigManager $appConfig,
		private readonly IURLGenerator $urlGenerator,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * Build the magic configuration link URL.
	 *
	 * Link payload format (JSON, base64-encoded after the '#'):
	 * {
	 *   "server": "<olvidServerUrl>",
	 *   "keycloak": {"server": "<directoryUrl>"},
	 *   "magic": {"username": "<uid>", "token": "<token>"}
	 * }
	 *
	 * @throws Exception
	 */
	public function handle(string $userId): DataResponse {
		try {
			// create token and store in db
			$token = RandomUtil::uuid_create();
			$this->userConfig->setMagicToken($userId, $token);
			$this->userConfig->setMagicTokenExpiration($userId, TimeUtil::currentTimeMillis() + Constants::MAGIC_LINK_DURATION_S * 1000);

			// build the magic link url
			$serverUrl = $this->appConfig->getOlvidServerUrl() ?? '';
			$nextcloudUrl = $this->urlGenerator->linkToOCSRouteAbsolute('olvid.ocs.olvid');

			// compute magic link payload
			$payload = [
				'server' => $serverUrl,
				'keycloak' => [
					'server' => $nextcloudUrl,
				],
				'magic' => [
					'username' => $userId,
					'token' => $token,
				],
			];

			// build url
			$encoded = base64_encode((string)json_encode($payload));
			$configurationUrl = "https://configuration.olvid.io/#$encoded";
			return new DataResponse(['configurationUrl' => $configurationUrl], Http::STATUS_OK);
		} catch (Exception $e) {
			$this->logger->error('magicLink: ', ['exception' => $e]);
			return new DataResponse(['error' => 'internal error'], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
