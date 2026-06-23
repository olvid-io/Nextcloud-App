<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Directory\Search;

class SearchHandlerTest extends ApiHandlerTestCase {
	public function testHandlerReturnsEmptyResultsWhenNoUsersHaveIdentity(): void {
		$caller = $this->mockUser('caller');
		$this->userManager->method('search')->with('')->willReturn([
			$this->mockUser('alice'),
			$this->mockUser('bob'),
		]);
		$this->olvidUserConfig->method('hasIdentity')->willReturn(false);

		$handler = $this->makeHandler(Search::class);
		$response = $handler->handler([], $caller);

		$data = $this->getResponseData($response);
		$this->assertCount(0, $data[Constants::SEARCH_RESPONSE_RESULTS]);
	}

	public function testHandlerReturnsOnlyUsersWithIdentitySet(): void {
		$caller = $this->mockUser('caller');
		$alice = $this->mockUser('alice', 'Alice Wonder');
		$bob = $this->mockUser('bob', 'Bob Builder');
		$this->userManager->method('search')->with('')->willReturn([$alice, $bob]);

		// Build a fake signed-details JWT for alice whose middle part is
		// base64-encoded JSON (the format parseSignedDetails() expects).
		$alicePayload = base64_encode(json_encode([
			Constants::DETAILS_KEY_ID => 'alice',
			Constants::DETAILS_KEY_FIRST_NAME => 'Alice Wonder',
			Constants::DETAILS_KEY_LAST_NAME => '',
			Constants::DETAILS_KEY_POSITION => '',
			Constants::DETAILS_KEY_COMPANY => '',
			Constants::DETAILS_KEY_IDENTITY => 'alice-olvid-id',
			Constants::DETAILS_KEY_TIMESTAMP => 0,
		]));
		$aliceFakeJwt = "header.{$alicePayload}.sig";

		$this->olvidUserConfig->method('hasIdentity')->willReturnCallback(
			fn (string $uid) => $uid === 'alice'
		);
		$this->olvidUserConfig->method('getSignedDetails')->willReturnCallback(
			fn (string $uid) => $uid === 'alice' ? $aliceFakeJwt : null
		);

		$handler = $this->makeHandler(Search::class);
		$response = $handler->handler([], $caller);

		$data = $this->getResponseData($response);
		$this->assertCount(1, $data[Constants::SEARCH_RESPONSE_RESULTS]);
	}
}
