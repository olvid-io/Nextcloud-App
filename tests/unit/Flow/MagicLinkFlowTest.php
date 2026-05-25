<?php


declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Flow;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\GetMagicSession\GetMagicSession;
use OCA\Olvid\Api\App\GetMagicLink\GetMagicLink;
use OCA\Olvid\Tests\Unit\Api\Olvid\ApiHandlerTestCase;

/**
 * Simulates the full magic-link device onboarding flow:
 *   1. Web app calls getMagicLink → gets a configurationUrl with embedded token
 *   2. Device extracts token from the URL and calls getMagicSession → gets a bearer JWT
 *   3. Device uses the JWT on subsequent calls (validated manually here)
 */
class MagicLinkFlowTest extends ApiHandlerTestCase
{
	public function testFullMagicLinkFlow(): void
	{
		$user = $this->mockUser('alice', 'Alice Wonder');
		$this->userManager->method('get')->with('alice')->willReturn($user);
		$this->configureAppConfigWithKeys();

		$store = [];
		$this->config->method('setUserValue')->willReturnCallback(
			function ($uid, $app, $key, $value) use (&$store): void {
				$store["$uid:$key"] = $value;
			}
		);
		$this->config->method('getUserValue')->willReturnCallback(
			function ($uid, $app, $key) use (&$store) {
				return $store["$uid:$key"] ?? '';
			}
		);

		// --- Step 1: web app requests the magic link ---
		$magicLink = new GetMagicLink(
			$this->config,
			$this->appConfig,
			$this->urlGenerator,   // needs a mock — see note below
			$this->logger,
		);
		$linkResponse = $magicLink->handle('alice');
		$linkData = $this->getResponseData($linkResponse);

		$this->assertArrayHasKey('configurationUrl', $linkData);
		$url = $linkData['configurationUrl'];
		$this->assertStringStartsWith('https://configuration.olvid.io/#', $url);

		// --- Step 2: device extracts token from URL payload ---
		$encoded = substr($url, strlen('https://configuration.olvid.io/#'));
		$payload = json_decode(base64_decode($encoded), true);
		$extractedToken = $payload['magic']['token'];
		$extractedUsername = $payload['magic']['username'];

		// --- Step 3: device exchanges token for a bearer JWT ---
		$sessionHandler = $this->makeGetMagicSessionHandler();
		$sessionResponse = $sessionHandler->handler(null, [
			Constants::GET_MAGIC_SESSION_REQUEST_USERNAME => $extractedUsername,
			Constants::GET_MAGIC_SESSION_REQUEST_TOKEN => $extractedToken,
		]);
		$sessionData = $this->getResponseData($sessionResponse);

		$this->assertArrayHasKey('access_token', $sessionData);
		$this->assertSame('Bearer', $sessionData['token_type']);

		// --- Step 4: verify the JWT is valid and contains the right claims ---
		$keyResource  = openssl_pkey_get_private(self::$testPrivateKey);
		$publicKeyPem = openssl_pkey_get_details($keyResource)['key'];
		$decoded      = JWT::decode($sessionData['access_token'], new Key($publicKeyPem, 'ES256'));

		$this->assertSame('alice', $decoded->sub);
		$this->assertSame('session', $decoded->type);
		$this->assertGreaterThan(time(), $decoded->exp);
	}

	public function testMagicSessionFailsWithExpiredToken(): void
	{
		$user = $this->mockUser('alice');
		$this->userManager->method('get')->with('alice')->willReturn($user);

		// Store an already-expired token
		$expiredJson = json_encode(['token' => 'valid-token', 'expiration' => time() - 1]);
		$this->config->method('getUserValue')->willReturn($expiredJson);

		$handler = $this->makeGetMagicSessionHandler();
		$response = $handler->handler(null, [
			Constants::GET_MAGIC_SESSION_REQUEST_USERNAME => 'alice',
			Constants::GET_MAGIC_SESSION_REQUEST_TOKEN => 'valid-token',
		]);

		// Expired token must be rejected with an error in the body (all handlers return HTTP 200)
		$this->assertErrorResponse($response, \OCA\Olvid\Api\Olvid\BaseJsonResponse::ERROR_CODE_INVALID_REQUEST);
	}

	// GetMagicSession has a different constructor than OlvidAppHandler handlers
	private function makeGetMagicSessionHandler(): GetMagicSession
	{
		return new GetMagicSession(
			$this->config,
			$this->appConfig,
			$this->userManager,
			$this->accountManager,
			$this->userSession,
			$this->groupManager,
			$this->lockingProvider,
			$this->logger,
		);
	}
}
