<?php

declare(strict_types=1);

namespace OCA\Olvid\Tests\Unit\Controller;

use OCA\Olvid\Api\Directory\GetKey;
use OCA\Olvid\Api\Directory\GetMagicSession;
use OCA\Olvid\Api\Directory\Groups;
use OCA\Olvid\Api\Directory\ListUsers;
use OCA\Olvid\Api\Directory\Me;
use OCA\Olvid\Api\Directory\PutKey;
use OCA\Olvid\Api\Directory\Search;
use OCA\Olvid\Api\Engine\GetSession;
use OCA\Olvid\Api\Engine\RequestChallenge;
use OCA\Olvid\Api\Engine\Verify;
use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Controller\DirectoryApiController;
use OCA\Olvid\Utils\OlvidAppConfigManager;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\TextPlainResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase {
	private DirectoryApiController $controller;

	protected function setUp(): void {
		parent::setUp();

		$olvidAppConfig = $this->createMock(OlvidAppConfigManager::class);
		$olvidAppConfig->method('getJwkKeyId')->willReturn('test-kid');
		$olvidAppConfig->method('getJwkKeyPublicKeyX')->willReturn('test-x-coord');
		$olvidAppConfig->method('getJwkKeyPublicKeyY')->willReturn('test-y-coord');

		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToOCSRouteAbsolute')->willReturn('https://cloud.example.com/ocs/v2.php');

		$this->controller = new DirectoryApiController(
			Application::APP_ID,
			$this->createMock(IRequest::class),
			$olvidAppConfig,
			$urlGenerator,
			$this->createMock(Me::class),
			$this->createMock(PutKey::class),
			$this->createMock(GetKey::class),
			$this->createMock(Search::class),
			$this->createMock(ListUsers::class),
			$this->createMock(Groups::class),
			$this->createMock(Verify::class),
			$this->createMock(RequestChallenge::class),
			$this->createMock(GetSession::class),
			$this->createMock(GetMagicSession::class),
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
