<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Api\Olvid;

use OCA\Olvid\Api\Device\BaseJsonResponse;
use OCA\Olvid\Db\OlvidDatabase;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCA\Olvid\Utils\OlvidServer\OlvidServer;
use OCA\Olvid\Utils\OlvidUserConfigManager;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use OCP\Lock\ILockingProvider;

/**
 * Base class for handler unit tests.
 *
 * Generates a real EC P-256 key pair once per class (setUpBeforeClass) and
 * provides helpers for building mocks and making assertions on the JSON
 * response format used by all ApiHandler subclasses.
 */
abstract class ApiHandlerTestCase extends TestCase {
	protected static string $testPrivateKey;

	/** @var IConfig&\PHPUnit\Framework\MockObject\MockObject */
	protected IConfig $config;
	/** @var IAppConfig&\PHPUnit\Framework\MockObject\MockObject */
	protected IAppConfig $appConfig;
	/** @var OlvidUserConfigManager&\PHPUnit\Framework\MockObject\MockObject */
	protected OlvidUserConfigManager $olvidUserConfig;
	/** @var OlvidAppConfigManager&\PHPUnit\Framework\MockObject\MockObject */
	protected OlvidAppConfigManager $olvidAppConfig;
	/** @var IUserManager&\PHPUnit\Framework\MockObject\MockObject */
	protected IUserManager $userManager;
	/** @var IAccountManager&\PHPUnit\Framework\MockObject\MockObject */
	protected IAccountManager $accountManager;
	/** @var IUserSession&\PHPUnit\Framework\MockObject\MockObject */
	protected IUserSession $userSession;
	/** @var IGroupManager&\PHPUnit\Framework\MockObject\MockObject */
	protected IGroupManager $groupManager;
	/** @var LoggerInterface&\PHPUnit\Framework\MockObject\MockObject */
	protected LoggerInterface $logger;
	/** @var IRequest&\PHPUnit\Framework\MockObject\MockObject */
	protected IRequest $request;
	/** @var ILockingProvider&\PHPUnit\Framework\MockObject\MockObject */
	protected ILockingProvider $lockingProvider;
	/** @var IURLGenerator&\PHPUnit\Framework\MockObject\MockObject */
	protected IURLGenerator $urlGenerator;
	/** @var OlvidDatabase&\PHPUnit\Framework\MockObject\MockObject */
	protected OlvidDatabase $db;
	/** @var OlvidServer&\PHPUnit\Framework\MockObject\MockObject */
	protected OlvidServer $olvidServer;

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

	protected function setUp(): void {
		parent::setUp();
		$this->config = $this->createMock(IConfig::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->olvidUserConfig = $this->createMock(OlvidUserConfigManager::class);
		$this->olvidAppConfig = $this->createMock(OlvidAppConfigManager::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->accountManager = $this->createMock(IAccountManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->request = $this->createMock(IRequest::class);
		$this->lockingProvider = $this->createMock(ILockingProvider::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->db = $this->createMock(OlvidDatabase::class);
		$this->olvidServer = $this->createMock(OlvidServer::class);
	}

	/** Build a mock IUser with the given uid and display name. */
	protected function mockUser(string $uid = 'testuser', string $displayName = 'Test User'): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$user->method('getDisplayName')->willReturn($displayName);
		return $user;
	}

	/**
	 * Configure the olvidAppConfig mock to return real JWK key material so that
	 * JsonUserDetails::sign() can produce a valid ES256 JWT.
	 */
	protected function configureAppConfigWithKeys(): void {
		$this->olvidAppConfig->method('getJwkKeyId')->willReturn('test-key-id');
		$this->olvidAppConfig->method('getJwkKeyType')->willReturn('ES256');
		$this->olvidAppConfig->method('getJwkKeyPrivateKey')->willReturn(self::$testPrivateKey);
		$this->olvidAppConfig->method('getOlvidServerUrl')->willReturn('https://server.test.olvid.io');
	}

	/** Instantiate a handler with the shared mock dependencies. */
	protected function makeHandler(string $handlerClass): object {
		return new $handlerClass(
			$this->request,
			$this->logger,
			$this->config,
			$this->appConfig,
			$this->userManager,
			$this->groupManager,
			$this->accountManager,
			$this->lockingProvider,
			$this->olvidUserConfig,
			$this->olvidAppConfig,
			$this->db,
			$this->olvidServer
		);
	}

	/**
	 * Deserialize the response body regardless of whether getData() returns
	 * a JsonSerializable object or a plain array.
	 */
	protected function getResponseData(Response $response): array {
		$this->assertInstanceOf(JSONResponse::class, $response);
		/** @var JSONResponse $response */
		return json_decode(json_encode($response->getData()), true) ?? [];
	}

	protected function assertErrorResponse(Response $response, int $expectedErrorCode): void {
		$data = $this->getResponseData($response);
		$this->assertSame(BaseJsonResponse::STATUS_ERROR, $data['status']);
		$this->assertSame($expectedErrorCode, $data['error']);
	}

	protected function assertSuccessResponse(Response $response): void {
		$data = $this->getResponseData($response);
		$this->assertSame(BaseJsonResponse::STATUS_SUCCESS, $data['status']);
	}
}
