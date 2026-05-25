<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Models;

use OCA\Olvid\Api\Constants;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Models\OlvidUserDetails;
use OCP\IAppConfig;
use OCP\IConfig;
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
		[$user, $config, $appConfig] = $this->buildSigningMocks('alice', 'Alice Wonder', [Constants::USER_ATTRIBUTE_OLVID_IDENTITY => "alice-identity-string"]);

		$userDetails = OlvidUserDetails::computeDetails($user, $config);
		$jwt = $userDetails->sign($config, $appConfig);

		$this->assertCount(3, explode('.', $jwt), 'Expected a JWT with header.payload.signature');
	}

	public function testSignUserDetailsPayloadContainsCorrectFields(): void {
		[$user, $config, $appConfig] = $this->buildSigningMocks('alice', 'Alice Wonder', [Constants::USER_ATTRIBUTE_OLVID_IDENTITY => 'alice-identity']);

		$userDetails = OlvidUserDetails::computeDetails($user, $config);
		$jwt = $userDetails->sign($config, $appConfig);

		// Decode the payload part (index 1) — JWT uses base64url, add padding for decode
		$b64 = str_replace(['-', '_'], ['+', '/'], explode('.', $jwt)[1]);
		$payload = json_decode(base64_decode($b64), true);

		$this->assertSame('alice', $payload[Constants::DETAILS_KEY_ID]);
		$this->assertSame('Alice Wonder', $payload[Constants::DETAILS_KEY_FIRST_NAME]);
		$this->assertSame('alice-identity', $payload[Constants::DETAILS_KEY_IDENTITY]);
		$this->assertSame('', $payload[Constants::DETAILS_KEY_LAST_NAME]);
	}

	public function testSignUserDetailsCachesJwtInConfig(): void {
		[$user, $config, $appConfig] = $this->buildSigningMocks('alice', 'Alice Wonder', []);

		$cachedKey = null;
		$cachedValue = null;
		$config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $value) use (&$cachedKey, &$cachedValue): void {
				$cachedKey = $key;
				$cachedValue = $value;
			}
		);

		$userDetails = OlvidUserDetails::computeDetails($user, $config);
		$jwt = $userDetails->sign($config, $appConfig);

		$this->assertSame(Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS, $cachedKey);
		$this->assertSame($jwt, $cachedValue);
	}

	// --- getCurrentUserDetails ---

	public function testGetCurrentUserDetailsReturnsNullWhenNoSignatureCached(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('');

		$this->assertNull(OlvidUserDetails::parseSignedDetails($user, $config));
	}

	public function testGetCurrentUserDetailsParsesSignatureStoredBySignUserDetails(): void {
		[$user, $config, $appConfig] = $this->buildSigningMocks('alice', 'Alice Wonder', [Constants::USER_ATTRIBUTE_OLVID_IDENTITY => 'alice-identity']);

		$stored = null;
		$config->method('setUserValue')->willReturnCallback(
			function (string $uid, string $app, string $key, string $value) use (&$stored): void {
				if ($key === Constants::USER_ATTRIBUTE_OLVID_SIGNED_DETAILS) {
					$stored = $value;
				}
			}
		);

		$userDetails = OlvidUserDetails::computeDetails($user, $config);
		$userDetails->sign($config, $appConfig);

		$this->assertNotNull($stored, 'Expected signUserDetails to cache a JWT');

		// Read it back via getCurrentUserDetails using a fresh config mock
		$config2 = $this->createMock(IConfig::class);
		$config2->method('getUserValue')->willReturn($stored);

		$details = OlvidUserDetails::parseSignedDetails($user, $config2);

		$this->assertNotNull($details);
		$this->assertSame('alice', $details->id);
		$this->assertSame('Alice Wonder', $details->firstname);
		$this->assertSame('alice-identity', $details->identity);
	}

	// --- Helpers ---

	/**
	 * @return array{IUser, IConfig, IAppConfig}
	 */
	private function buildSigningMocks(string $uid, string $displayName, array $userDetails): array {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')
			->willReturnCallback(fn (string $uid, string $appId, string $key) => match ($key) {
				Constants::USER_ATTRIBUTE_OLVID_FIRSTNAME => $userDetails[Constants::USER_ATTRIBUTE_OLVID_FIRSTNAME] ?? "",
				Constants::USER_ATTRIBUTE_OLVID_LASTNAME => $userDetails[Constants::USER_ATTRIBUTE_OLVID_LASTNAME] ?? "",
				Constants::USER_ATTRIBUTE_OLVID_POSITION => $userDetails[Constants::USER_ATTRIBUTE_OLVID_POSITION] ?? "",
				Constants::USER_ATTRIBUTE_OLVID_COMPANY => $userDetails[Constants::USER_ATTRIBUTE_OLVID_COMPANY] ?? "",
				Constants::USER_ATTRIBUTE_OLVID_IDENTITY => $userDetails[Constants::USER_ATTRIBUTE_OLVID_IDENTITY] ?? "",
				default => ''
			});

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			fn(string $app, string $key) => match ($key) {
				'olvid-jwk-key-id' => 'test-key-id',
				'olvid-jwk-key-type' => 'ES256',
				'olvid-jwk-private-key' => self::$testPrivateKey,
				default => '',
			}
		);

		return [$user, $config, $appConfig];
	}
}
