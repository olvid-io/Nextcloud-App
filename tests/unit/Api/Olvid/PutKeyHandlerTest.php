<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\Api\Device\BaseJsonResponse;
use OCA\Olvid\Api\Device\PutKey;
use OCA\Olvid\Models\JsonUserDetails;

class PutKeyHandlerTest extends ApiHandlerTestCase {
	public function testHandlerReturnsInvalidRequestWhenIdentityMissing(): void {
		$user = $this->mockUser('alice');

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler([], $user);

		$this->assertErrorResponse($response, BaseJsonResponse::ERROR_CODE_INVALID_REQUEST);
	}

	// Branch 1: no previous identity → store identity, sign details, request new api key
	public function testHandlerStoresNewIdentityWhenNoPreviousIdentity(): void {
		$user = $this->mockUser('alice', 'Alice Wonder');

		$store = [];
		$this->olvidUserConfig->method('getIdentity')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['identity'] ?? null; }
		);
		$this->olvidUserConfig->method('setIdentity')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['identity'] = $value !== '' ? $value : null;
			}
		);
		$this->olvidUserConfig->method('getApiKey')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['apiKey'] ?? null; }
		);
		$this->olvidUserConfig->method('setApiKey')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['apiKey'] = $value;
			}
		);
		$this->olvidUserConfig->method('setSignedDetails')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['signedDetails'] = $value;
			}
		);
		$this->olvidUserConfig->method('getSignedDetails')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['signedDetails'] ?? null; }
		);
		// computeDetails calls these
		$this->olvidUserConfig->method('getFirstname')->willReturn(null);
		$this->olvidUserConfig->method('getLastname')->willReturn(null);
		$this->olvidUserConfig->method('getPosition')->willReturn(null);
		$this->olvidUserConfig->method('getCompany')->willReturn(null);
		$this->olvidUserConfig->method('getFullSearchField')->willReturn(null);
		$this->configureAppConfigWithKeys();

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler([Constants::PUT_KEY_REQUEST_IDENTITY => 'new-olvid-identity'], $user);

		// check response
		$this->assertSuccessResponse($response);

		// check store
		$this->assertSame('new-olvid-identity', $store['identity']);
		// Signed details must have been cached as a valid three-part JWT
		$this->assertArrayHasKey('signedDetails', $store);
		$this->assertCount(3, explode('.', $store['signedDetails']));
		$this->assertNotNull(JsonUserDetails::parseSignedDetails($user, $this->olvidUserConfig));

		// No session revocation on first upload
		$this->assertArrayNotHasKey('sessionRevokedBefore', $store);
	}

	// Branch 2: same identity re-uploaded → re-sign details, no revocation
	public function testHandlerSucceedsWhenSameIdentityReUploaded(): void {
		$user = $this->mockUser('alice', 'Alice Wonder');

		$store = ['identity' => 'same-identity'];
		$this->olvidUserConfig->method('getIdentity')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['identity'] ?? null; }
		);
		$this->olvidUserConfig->method('setIdentity')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['identity'] = $value !== '' ? $value : null;
			}
		);
		$this->olvidUserConfig->method('getApiKey')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['apiKey'] ?? null; }
		);
		$this->olvidUserConfig->method('setApiKey')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['apiKey'] = $value;
			}
		);
		$this->olvidUserConfig->method('setSignedDetails')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['signedDetails'] = $value;
			}
		);
		$this->olvidUserConfig->method('getSignedDetails')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['signedDetails'] ?? null; }
		);
		// computeDetails calls these
		$this->olvidUserConfig->method('getFirstname')->willReturn(null);
		$this->olvidUserConfig->method('getLastname')->willReturn(null);
		$this->olvidUserConfig->method('getPosition')->willReturn(null);
		$this->olvidUserConfig->method('getCompany')->willReturn(null);
		$this->olvidUserConfig->method('getFullSearchField')->willReturn(null);
		$this->configureAppConfigWithKeys();

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler([
			Constants::PUT_KEY_REQUEST_IDENTITY => 'same-identity',
		], $user);

		$this->assertSuccessResponse($response);
		// No session revocation when re-uploading the same identity
		$this->assertArrayNotHasKey('sessionRevokedBefore', $store);
	}

	// Branch 3: different identity uploaded → revoke session, clear nonce, set new identity
	public function testHandlerRevokesSessionWhenIdentityIsOverridden(): void {
		$user = $this->mockUser('alice', 'Alice Wonder');

		$store = ['identity' => 'old-identity'];
		$this->olvidUserConfig->method('getIdentity')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['identity'] ?? null; }
		);
		$this->olvidUserConfig->method('setIdentity')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['identity'] = $value !== '' ? $value : null;
			}
		);
		$this->olvidUserConfig->method('getApiKey')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['apiKey'] ?? null; }
		);
		$this->olvidUserConfig->method('setApiKey')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['apiKey'] = $value;
			}
		);
		$this->olvidUserConfig->method('setNonce')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['nonce'] = $value !== '' ? $value : null;
			}
		);
		$this->olvidUserConfig->method('setSessionRevokedBefore')->willReturnCallback(
			function (string $uid, int $value) use (&$store): void {
				$store['sessionRevokedBefore'] = $value;
			}
		);
		$this->olvidUserConfig->method('setSignedDetails')->willReturnCallback(
			function (string $uid, string $value) use (&$store): void {
				$store['signedDetails'] = $value;
			}
		);
		$this->olvidUserConfig->method('getSignedDetails')->willReturnCallback(
			function (string $uid) use (&$store) { return $store['signedDetails'] ?? null; }
		);
		// computeDetails calls these
		$this->olvidUserConfig->method('getFirstname')->willReturn(null);
		$this->olvidUserConfig->method('getLastname')->willReturn(null);
		$this->olvidUserConfig->method('getPosition')->willReturn(null);
		$this->olvidUserConfig->method('getCompany')->willReturn(null);
		$this->olvidUserConfig->method('getFullSearchField')->willReturn(null);
		$this->configureAppConfigWithKeys();

		$handler = $this->makeHandler(PutKey::class);
		$response = $handler->handler([
			Constants::PUT_KEY_REQUEST_IDENTITY => 'new-identity',
		], $user);

		$this->assertSuccessResponse($response);
		$this->assertSame('new-identity', $store['identity']);
		// Session must be revoked when identity changes
		$this->assertArrayHasKey('sessionRevokedBefore', $store);
		$this->assertGreaterThan(0, $store['sessionRevokedBefore']);
		// Nonce must be cleared so any previously enrolled identity is unbound
		$this->assertNull($store['nonce']);
	}
}
