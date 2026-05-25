<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Olvid\BaseJsonResponse;
use OCA\Olvid\Api\Olvid\PutKey\PutKey;
use OCA\Olvid\Models\OlvidUserDetails;

class PutKeyHandlerTest extends ApiHandlerTestCase {
	public function testHandlerReturnsInvalidRequestWhenIdentityMissing(): void {
		$user = $this->mockUser('alice');

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler($user, []);

		$this->assertErrorResponse($response, BaseJsonResponse::ERROR_CODE_INVALID_REQUEST);
	}

	// Branch 1: no previous identity → store identity, sign details, request new api key
	public function testHandlerStoresNewIdentityWhenNoPreviousIdentity(): void {
		$user = $this->mockUser('alice', 'Alice Wonder');

		$store = [];
		$this->config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, mixed $value) use (&$store): void {
				$store[$key] = $value;
			}
		);
		$this->config->method('getUserValue')->willReturnCallback(
			fn(string $uid, string $app, string $key) => $store[$key] ?? ''
		);
		$this->configureAppConfigWithKeys();

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler($user, [
			Constants::PUT_KEY_REQUEST_IDENTITY => 'new-olvid-identity',
		]);

		// check response
		$this->assertSuccessResponse($response);

		// check store
		$this->assertSame('new-olvid-identity', $store[Constants::USER_ATTRIBUTE_OLVID_IDENTITY]);
		// Signed details must have been cached as a valid three-part JWT
		$this->assertArrayHasKey(Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS, $store);
		$this->assertCount(3, explode('.', $store[Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS]));
		$this->assertNotNull(OlvidUserDetails::parseSignedDetails($user, $this->config));

		// No session revocation on first upload
		$this->assertArrayNotHasKey(Constants::USER_ATTRIBUTE_OLVID_SESSION_REVOKED_BEFORE, $store);
	}

	// Branch 2: same identity re-uploaded → re-sign details, no revocation
	public function testHandlerSucceedsWhenSameIdentityReUploaded(): void {
		$user = $this->mockUser('alice', 'Alice Wonder');

		$store = [Constants::USER_ATTRIBUTE_OLVID_IDENTITY => 'same-identity'];
		$this->config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, mixed $value) use (&$store): void {
				$store[$key] = $value;
			}
		);
		$this->config->method('getUserValue')->willReturnCallback(
			fn(string $uid, string $app, string $key) => $store[$key] ?? ''
		);
		$this->configureAppConfigWithKeys();

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler($user, [
			Constants::PUT_KEY_REQUEST_IDENTITY => 'same-identity',
		]);

		$this->assertSuccessResponse($response);
		// No session revocation when re-uploading the same identity
		$this->assertArrayNotHasKey(Constants::USER_ATTRIBUTE_OLVID_SESSION_REVOKED_BEFORE, $store);
	}

	// Branch 3: different identity uploaded → revoke session, clear nonce, set new identity
	public function testHandlerRevokesSessionWhenIdentityIsOverridden(): void {
		$user = $this->mockUser('alice', 'Alice Wonder');

		$store = [Constants::USER_ATTRIBUTE_OLVID_IDENTITY => 'old-identity'];
		$this->config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, mixed $value) use (&$store): void {
				$store[$key] = $value;
			}
		);
		$this->config->method('getUserValue')->willReturnCallback(
			fn(string $uid, string $app, string $key) => $store[$key] ?? ''
		);
		$this->configureAppConfigWithKeys();

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler($user, [
			Constants::PUT_KEY_REQUEST_IDENTITY => 'new-identity',
		]);

		$this->assertSuccessResponse($response);
		$this->assertSame('new-identity', $store[Constants::USER_ATTRIBUTE_OLVID_IDENTITY]);
		// Session must be revoked when identity changes
		$this->assertArrayHasKey(Constants::USER_ATTRIBUTE_OLVID_SESSION_REVOKED_BEFORE, $store);
		$this->assertGreaterThan(0, $store[Constants::USER_ATTRIBUTE_OLVID_SESSION_REVOKED_BEFORE]);
		// Nonce must be cleared so any previously enrolled identity is unbound
		$this->assertNull($store[Constants::USER_ATTRIBUTE_OLVID_NONCE]);
	}
}
