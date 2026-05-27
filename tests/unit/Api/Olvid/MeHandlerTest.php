<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Device\Me;

class MeHandlerTest extends ApiHandlerTestCase {
	public function testHandlerReturnsCachedSignatureWithoutResigning(): void {
		$user = $this->mockUser('alice');
		$this->olvidUserConfig->method('getSignedDetails')->with('alice')->willReturn('cached.jwt.value');
		$this->olvidUserConfig->method('getApiKey')->with('alice')->willReturn('stored-api-key');
		$this->olvidAppConfig->method('getOlvidServerUrl')->willReturn('');
		$this->olvidAppConfig->method('getGlobalPushTopic')->willReturn(null);

		$handler = $this->makeHandler(Me::class);
		$response = $handler->handler([], $user);

		$data = $this->getResponseData($response);
		$this->assertSame('cached.jwt.value', $data[Constants::ME_RESPONSE_SIGNATURE]);
		$this->assertSame('stored-api-key', $data[Constants::ME_RESPONSE_API_KEY]);
	}

	public function testHandlerSignsAndReturnsDetailsWhenNoCacheExists(): void {
		$user = $this->mockUser('alice', 'Alice Wonder');
		$this->olvidUserConfig->method('getSignedDetails')->with('alice')->willReturn(null);
		$this->olvidUserConfig->method('getIdentity')->with('alice')->willReturn('alice-olvid-identity');
		$this->olvidUserConfig->method('getApiKey')->with('alice')->willReturn('stored-api-key');
		$this->configureAppConfigWithKeys();

		$handler = $this->makeHandler(Me::class);
		$response = $handler->handler([], $user);

		$data = $this->getResponseData($response);
		// A freshly generated ES256 JWT has exactly three dot-separated parts
		$this->assertCount(3, explode('.', $data[Constants::ME_RESPONSE_SIGNATURE]));
		$this->assertSame('https://server.test.olvid.io', $data[Constants::ME_RESPONSE_SERVER]);
		$this->assertTrue($data[Constants::ME_RESPONSE_REVOCATION_ALLOWED]);
		$this->assertFalse($data[Constants::ME_RESPONSE_TRANSFER_RESTRICTED]);
		$this->assertIsArray($data[Constants::ME_RESPONSE_PUSH_TOPICS]);
		$this->assertEmpty($data[Constants::ME_RESPONSE_SIGNED_REVOCATIONS]);
	}
}
