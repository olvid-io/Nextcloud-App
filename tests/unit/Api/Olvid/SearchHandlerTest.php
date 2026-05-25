<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\Search\Search;

class SearchHandlerTest extends ApiHandlerTestCase {
	public function testHandlerReturnsEmptyResultsWhenNoUsersHaveIdentity(): void {
		$caller = $this->mockUser('caller');
		$this->userManager->method('search')->with('')->willReturn([
			$this->mockUser('alice'),
			$this->mockUser('bob'),
		]);
		$this->config->method('getUserValue')->willReturn(''); // no identity for anyone

		$handler = $this->makeHandler(Search::class);
		$response = $handler->handler($caller, []);

		$data = $this->getResponseData($response);
		$this->assertCount(0, $data[Constants::SEARCH_RESPONSE_RESULTS]);
	}

	public function testHandlerReturnsOnlyUsersWithIdentitySet(): void {
		$caller = $this->mockUser('caller');
		$alice = $this->mockUser('alice', 'Alice Wonder');
		$bob = $this->mockUser('bob', 'Bob Builder');
		$this->userManager->method('search')->with('')->willReturn([$alice, $bob]);

		// Build a fake signed-details JWT for alice whose middle part is
		// base64-encoded JSON (the format getCurrentUserDetails() expects).
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

		$this->config->method('getUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key) use ($aliceFakeJwt): string {
				if ($uid === 'alice' && $key === Constants::USER_ATTRIBUTE_OLVID_IDENTITY) {
					return 'alice-olvid-id';
				}
				if ($uid === 'alice' && $key === Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS) {
					return $aliceFakeJwt;
				}
				return ''; // bob has no identity → excluded
			}
		);

		$handler = $this->makeHandler(Search::class);
		$response = $handler->handler($caller, []);

		$data = $this->getResponseData($response);
		$this->assertCount(1, $data[Constants::SEARCH_RESPONSE_RESULTS]);
	}
}
