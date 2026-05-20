<?php

declare(strict_types=1);

namespace OCA\Olvid\Api\App\MagicLink;

use Exception;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Utils\AppConfigManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\PreConditionNotMetException;
use Psr\Log\LoggerInterface;

/**
 * GET /olvid-rest/magicLink
 *
 * Authenticated endpoint. Returns a magic configuration link that the user can
 * scan with their Olvid app to authenticate without the full challenge/response flow.
 *
 * The link embeds a per-user token stored in IConfig. If no token exists (or it has
 * expired), a new UUID token is generated and persisted.
 *
 * Response: {"configurationUrl": "https://configuration.olvid.io/#<base64>"}
 */
class MagicLink {
    public function __construct(
        private readonly IConfig          $config,
        private readonly IAppConfig       $appConfig,
        private readonly IURLGenerator    $urlGenerator,
        private readonly LoggerInterface  $logger,
    ) {}

    public function handle(?string $userId): JSONResponse {
        if ($userId === null) {
            return new JSONResponse(['status' => 'error', 'error' => 'permission denied'], 403);
        }

        try {
            $token            = $this->getOrCreateToken($userId);
            $configurationUrl = $this->buildLink($userId, $token);
            return new JSONResponse(['configurationUrl' => $configurationUrl]);
        } catch (Exception $e) {
            $this->logger->error('magicLink: ' . $e->getMessage());
            return new JSONResponse(['status' => 'error', 'error' => 'internal error'], 500);
        }
    }

    /**
     * Return the stored magic token if it exists and is still valid; otherwise generate a new one.
     *
     * @throws PreConditionNotMetException
     */
    private function getOrCreateToken(string $userId): string {
        $stored = $this->config->getUserValue($userId, Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_MAGIC_TOKEN);
        if ($stored !== '') {
            $data = json_decode($stored, true);
            if (is_array($data) && isset($data['token']) && $data['token'] !== '') {
                $expiration = $data['expiration'] ?? null;
                if ($expiration === null || $expiration > (int)(microtime(true) * 1000)) {
                    return $data['token'];
                }
            }
        }

        $token = uuid_create();
        $this->config->setUserValue(
            $userId,
            Application::APP_ID,
            Constants::USER_ATTRIBUTE_OLVID_MAGIC_TOKEN,
            (string) json_encode(['token' => $token, 'expiration' => microtime(true) * 1000 * 60 * 5]), // 5 min expiration
        );
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
        $serverUrl    = AppConfigManager::getOlvidServerUrl($this->appConfig) ?? '';
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
