<?php

declare(strict_types=1);

// Load Nextcloud core bootstrap when running inside a fully configured NC
// environment. If NC is not installed (e.g. standalone dev machine), skip it
// gracefully — unit tests work with vendor/autoload.php alone.
$ncBootstrap = __DIR__ . '/../../../tests/bootstrap.php';
if (file_exists($ncBootstrap)) {
	try {
		require_once $ncBootstrap;
	} catch (\Exception $e) {
		// NC not installed/configured; running in standalone unit-test mode.
	}
}

require_once __DIR__ . '/../composer/autoload.php';
require_once __DIR__ . '/Stubs.php';

if (class_exists('\OC_App') && class_exists('\OC_Hook')) {
	\OC_App::loadApp(\OCA\Olvid\AppInfo\Application::APP_ID);
	\OC_Hook::clear();
}
