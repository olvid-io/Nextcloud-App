<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\App;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCA\Olvid\Utils\TimeUtil;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;

/**
 * GET /app/getMagicLink
 *
 * Authenticated endpoint. Returns a magic configuration link that the user can
 * scan with their Olvid app to authenticate without the full challenge/response flow.
 *
 * The link embeds a per-user token stored in IConfig. If no token exists (or it has
 * expired), a new UUID token is generated and persisted.
 *
 * Response: {"configurationUrl": "https://configuration.olvid.io/#<base64>"}
 */
class GetMagicLink {
    public function __construct(
        private readonly OlvidUserConfigManager $userConfig,
        private readonly OlvidAppConfigManager  $appConfig,
        private readonly IURLGenerator          $urlGenerator,
        private readonly LoggerInterface        $logger,
    ) {}

    public function handle(string $userId): JSONResponse {
        try {
            $token            = $this->createToken($userId);
            $configurationUrl = $this->buildLink($userId, $token);
            return new JSONResponse(['configurationUrl' => $configurationUrl]);
        } catch (Exception $e) {
            $this->logger->error('magicLink: ' . $e->getMessage());
            return new JSONResponse(['status' => 'error', 'error' => 'internal error'], 500);
        }
    }

    /**
     * Generate a new token, overriding any existing one.
     *
     * @throws PreConditionNotMetException
     */
    private function createToken(string $userId): string {
        $token = uuid_create();
		$this->userConfig->setMagicToken($userId, $token);
		$this->userConfig->setMagicTokenExpiration($userId, TimeUtil::currentTimeMillis() + Constants::MAGIC_LINK_DURATION_S * 1000);
        return $token;
    }

    /**
     * Build the magic configuration link URL.
     *
     * Link payload format (JSON, base64-encoded after the '#'):
     * {
     *   "server": "<olvidServerUrl>",
     *   "keycloak": {"server": "<nextcloudUrl>"},
     *   "magic": {"username": "<uid>", "token": "<token>"}
     * }
     *
     * @throws Exception
     */
    private function buildLink(string $userId, string $token): string {
        $serverUrl    = $this->appConfig->getOlvidServerUrl() ?? '';
        $nextcloudUrl = $this->urlGenerator->linkToOCSRouteAbsolute('') . '/apps/olvid';

        $payload = [
            'server'   => $serverUrl,
            'keycloak' => [
                'server' => $nextcloudUrl,
            ],
            'magic' => [
                'username' => $userId,
                'token'    => $token,
            ],
        ];

        $encoded = base64_encode((string) json_encode($payload));
        return "https://configuration.olvid.io/#$encoded";
    }
}
