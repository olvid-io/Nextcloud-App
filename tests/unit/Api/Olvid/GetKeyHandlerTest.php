<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Directory\BaseJsonResponse;
use OCA\Olvid\Api\Directory\GetKey;

class GetKeyHandlerTest extends ApiHandlerTestCase {
	public function testHandlerReturnsInvalidRequestWhenUserIdMissing(): void {
		$caller = $this->mockUser('caller');
		$handler = $this->makeHandler(GetKey::class);
		$response = $handler->handler([], $caller);

		$this->assertErrorResponse($response, BaseJsonResponse::ERROR_CODE_INVALID_REQUEST);
	}

	public function testHandlerReturnsInvalidRequestWhenUserNotFound(): void {
		$caller = $this->mockUser('caller');
		$this->userManager->method('get')->willReturn(null);

		$handler = $this->makeHandler(GetKey::class);
		$response = $handler->handler([
			Constants::GET_KEY_REQUEST_USER_ID => 'unknown-user',
		], $caller);

		$this->assertErrorResponse($response, BaseJsonResponse::ERROR_CODE_INVALID_REQUEST);
	}

	public function testHandlerReturnsStoredSignatureForKnownUser(): void {
		$caller = $this->mockUser('caller');
		$bob = $this->mockUser('bob', 'Bob Builder');
		$this->userManager->method('get')->with('bob')->willReturn($bob);
		$this->olvidUserConfig->method('getSignedDetails')->with('bob')->willReturn('header.payload.sig');

		$handler = $this->makeHandler(GetKey::class);
		$response = $handler->handler([
			Constants::GET_KEY_REQUEST_USER_ID => 'bob',
		], $caller);

		$data = $this->getResponseData($response);
		$this->assertSame('header.payload.sig', $data[Constants::GET_KEY_RESPONSE_SIGNATURE]);
	}
}
