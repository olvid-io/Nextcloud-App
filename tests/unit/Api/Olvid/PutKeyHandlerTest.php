<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\BaseJsonResponse;
use OCA\Olvid\Api\Olvid\PutKey\PutKey;

class PutKeyHandlerTest extends ApiHandlerTestCase {
	public function testHandlerReturnsInvalidRequestWhenIdentityMissing(): void {
		$user = $this->mockUser('alice');

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler($user, $this->request, []);

		$this->assertErrorResponse($response, BaseJsonResponse::ERROR_CODE_INVALID_REQUEST);
	}

	public function testHandlerStoresIdentityAndReturnsSuccess(): void {
		$user = $this->mockUser('alice', 'Alice Wonder');

		// Simulate a config store so that values written by setUserValue can
		// be read back by getUserValue within the same handler invocation.
		$store = [];
		$this->config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $value) use (&$store): void {
				$store[$key] = $value;
			}
		);
		$this->config->method('getUserValue')->willReturnCallback(
			fn(string $uid, string $app, string $key) => $store[$key] ?? ''
		);
		$this->configureAppConfigWithKeys();

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler($user, $this->request, [
			Constants::PUT_KEY_REQUEST_IDENTITY => 'new-olvid-identity',
		]);

		$this->assertSuccessResponse($response);
		$this->assertSame('new-olvid-identity', $store[Constants::USER_ATTRIBUTE_OLVID_IDENTITY]);
		// The handler also caches the signed JWT — verify it was produced
		$this->assertArrayHasKey(Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS, $store);
		$this->assertCount(3, explode('.', $store[Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS]));
	}
}
