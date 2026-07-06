<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Models;

use OCA\Olvid\Models\JsonUserDetails;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class OlvidUserDetailsTest extends TestCase {
	private static string $testPrivateKey;

	public static function setUpBeforeClass(): void {
		$res = openssl_pkey_new([
			'digest_alg' => 'sha256',
			'private_key_type' => OPENSSL_KEYTYPE_EC,
			'curve_name' => 'prime256v1',
		]);
		$privateKey = '';
		openssl_pkey_export($res, $privateKey);
		self::$testPrivateKey = $privateKey;
	}

	// --- signUserDetails ---

	public function testSignUserDetailsReturnsThreePartJwt(): void {
		[$user, $userConfig, $appConfig] = $this->buildSigningMocks('alice', 'Alice Wonder', ['identity' => 'alice-identity-string']);

		$userDetails = JsonUserDetails::computeDetails($user, $userConfig);
		$jwt = $userDetails->sign($userConfig, $appConfig);

		$this->assertCount(3, explode('.', $jwt), 'Expected a JWT with header.payload.signature');
	}

	public function testSignUserDetailsPayloadContainsCorrectFields(): void {
		[$user, $userConfig, $appConfig] = $this->buildSigningMocks('alice', 'Alice Wonder', ['identity' => 'alice-identity']);

		$userDetails = JsonUserDetails::computeDetails($user, $userConfig);
		$jwt = $userDetails->sign($userConfig, $appConfig);

		// Decode the payload part (index 1) — JWT uses base64url, add padding for decode
		$b64 = str_replace(['-', '_'], ['+', '/'], explode('.', $jwt)[1]);
		$payload = json_decode(base64_decode($b64), true);

		$this->assertSame('alice', $payload['id']);
		$this->assertSame('Alice Wonder', $payload['first-name']);
		$this->assertSame('alice-identity', $payload['identity']);
		$this->assertArrayNotHasKey('last-name', $payload);
	}

	public function testSignUserDetailsCachesJwtInConfig(): void {
		[$user, $userConfig, $appConfig] = $this->buildSigningMocks('alice', 'Alice Wonder', []);

		$cachedValue = null;
		$userConfig->method('setSignedDetails')->willReturnCallback(
			function (string $uid, string $value) use (&$cachedValue): void {
				$cachedValue = $value;
			}
		);

		$userDetails = JsonUserDetails::computeDetails($user, $userConfig);
		$jwt = $userDetails->sign($userConfig, $appConfig);

		$this->assertNotNull($cachedValue);
		$this->assertSame($jwt, $cachedValue);
	}

	// --- getCurrentUserDetails ---

	public function testGetCurrentUserDetailsReturnsNullWhenNoSignatureCached(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$userConfig = $this->createMock(OlvidUserConfigManager::class);
		$userConfig->method('getSignedDetails')->willReturn(null);

		$this->assertNull(JsonUserDetails::parseSignedDetails($user, $userConfig));
	}

	public function testGetCurrentUserDetailsParsesSignatureStoredBySignUserDetails(): void {
		[$user, $userConfig, $appConfig] = $this->buildSigningMocks('alice', 'Alice Wonder', ['identity' => 'alice-identity']);

		$stored = null;
		$userConfig->method('setSignedDetails')->willReturnCallback(
			function (string $uid, string $value) use (&$stored): void {
				$stored = $value;
			}
		);

		$userDetails = JsonUserDetails::computeDetails($user, $userConfig);
		$userDetails->sign($userConfig, $appConfig);

		$this->assertNotNull($stored, 'Expected sign() to cache a JWT');

		// Read it back via parseSignedDetails using a fresh config mock
		$userConfig2 = $this->createMock(OlvidUserConfigManager::class);
		$userConfig2->method('getSignedDetails')->willReturn($stored);

		$details = JsonUserDetails::parseSignedDetails($user, $userConfig2);

		$this->assertNotNull($details);
		$this->assertSame('alice', $details->id);
		$this->assertSame('Alice Wonder', $details->firstname);
		$this->assertSame('alice-identity', $details->identity);
	}

	// --- Helpers ---

	/**
	 * @return array{IUser, OlvidUserConfigManager, OlvidAppConfigManager}
	 */
	private function buildSigningMocks(string $uid, string $displayName, array $userDetails): array {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);

		$userConfig = $this->createMock(OlvidUserConfigManager::class);
		$userConfig->method('getFirstname')->with($uid)->willReturn($userDetails['firstname'] ?? null);
		$userConfig->method('getLastname')->with($uid)->willReturn($userDetails['lastname'] ?? null);
		$userConfig->method('getPosition')->with($uid)->willReturn($userDetails['position'] ?? null);
		$userConfig->method('getCompany')->with($uid)->willReturn($userDetails['company'] ?? null);
		$userConfig->method('getB64Identity')->with($uid)->willReturn($userDetails['identity'] ?? null);
		$userConfig->method('getFullSearchField')->willReturn(null);

		$appConfig = $this->createMock(OlvidAppConfigManager::class);
		$appConfig->method('getJwkKeyId')->willReturn('test-key-id');
		$appConfig->method('getJwkKeyType')->willReturn('ES256');
		$appConfig->method('getJwkKeyPrivateKey')->willReturn(self::$testPrivateKey);

		return [$user, $userConfig, $appConfig];
	}
}
