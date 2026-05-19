<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Controller;

use OCA\OIDCIdentityProvider\Db\ClientMapper as OidcClientMapper;
use OCA\OIDCIdentityProvider\Util\DiscoveryGenerator;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Controller\OlvidApiController;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ApiTest extends TestCase {
	private OlvidApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getValueString')->willReturnCallback(
			fn(string $app, string $key) => match ($key) {
				'olvid-jwk-key-id' => 'test-kid',
				'olvid-jwk-public-key-x' => 'test-x-coord',
				'olvid-jwk-public-key-y' => 'test-y-coord',
				default => '',
			}
		);

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToOCSRouteAbsolute')->willReturn('https://cloud.example.com/ocs/v2.php');

		$this->controller = new OlvidApiController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$this->createMock(IConfig::class),
			$appConfig,
			$this->createMock(IUserManager::class),
			$this->createMock(IAccountManager::class),
			$this->createMock(IUserSession::class),
			$this->createMock(IGroupManager::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(OidcClientMapper::class),
			$this->createMock(DiscoveryGenerator::class),
			$urlGenerator,
		);
	}

	public function testPingReturnsPong(): void {
		$response = $this->controller->ping();

		$this->assertInstanceOf(TextPlainResponse::class, $response);
		$this->assertSame('pong', $response->render());
	}

	public function testJwksReturnsEcP256KeyStructure(): void {
		$response = $this->controller->jwks();

		$this->assertInstanceOf(JSONResponse::class, $response);
		$data = $response->getData();
		$this->assertArrayHasKey('keys', $data);
		$this->assertCount(1, $data['keys']);

		$key = $data['keys'][0];
		$this->assertSame('EC', $key['kty']);
		$this->assertSame('P-256', $key['crv']);
		$this->assertSame('ES256', $key['alg']);
		$this->assertSame('sig', $key['use']);
		$this->assertSame('test-kid', $key['kid']);
		$this->assertSame('test-x-coord', $key['x']);
		$this->assertSame('test-y-coord', $key['y']);
	}
}
