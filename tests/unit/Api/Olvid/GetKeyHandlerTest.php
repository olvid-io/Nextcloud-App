<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\BaseJsonResponse;
use OCA\Olvid\Api\Olvid\GetKey;
use OCA\Olvid\AppInfo\Application;

class GetKeyHandlerTest extends ApiHandlerTestCase {
	public function testHandlerReturnsInvalidRequestWhenUserIdMissing(): void {
		$handler = $this->makeHandler(GetKey::class);
		$response = $handler->handler(null, $this->request, []);

		$this->assertErrorResponse($response, BaseJsonResponse::ERROR_CODE_INVALID_REQUEST);
	}

	public function testHandlerReturnsInvalidRequestWhenUserNotFound(): void {
		$this->userManager->method('get')->willReturn(null);

		$handler = $this->makeHandler(GetKey::class);
		$response = $handler->handler(null, $this->request, [
			Constants::GET_KEY_REQUEST_USER_ID => 'unknown-user',
		]);

		$this->assertErrorResponse($response, BaseJsonResponse::ERROR_CODE_INVALID_REQUEST);
	}

	public function testHandlerReturnsStoredSignatureForKnownUser(): void {
		$bob = $this->mockUser('bob', 'Bob Builder');
		$this->userManager->method('get')->with('bob')->willReturn($bob);
		$this->config->method('getUserValue')
			->with('bob', Application::APP_ID, Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS)
			->willReturn('header.payload.sig');

		$handler = $this->makeHandler(GetKey::class);
		$response = $handler->handler(null, $this->request, [
			Constants::GET_KEY_REQUEST_USER_ID => 'bob',
		]);

		$data = $this->getResponseData($response);
		$this->assertSame('header.payload.sig', $data[Constants::GET_KEY_RESPONSE_SIGNATURE]);
	}
}
