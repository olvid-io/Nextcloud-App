<?php

declare(strict_types=1);

namespace Controller;

use OCA\Olvid\AppInfo\Application;
use OCA\Olvid\Controller\OlvidApiController;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

final class ApiTest extends TestCase {
	public function testIndex(): void {
		$request = $this->createMock(IRequest::class);
		$controller = new OlvidApiController(Application::APP_ID, $request);

		$this->assertEquals($controller->index()->getData()['message'], 'Hello world!');
	}
}
