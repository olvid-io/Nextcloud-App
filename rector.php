<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([
		__DIR__ . '/lib',
		__DIR__ . '/tests',
	])
	// target PHP version is read from composer.json ("php": "^8.1")
	->withPhpSets()
	// note: composer based sets (twig, phpunit set group, ...) are not usable as they expect vendor/composer/installed.json
	// and this project uses composer/ as vendor dir
	->withPreparedSets(
		deadCode: true,
		codeQuality: true,
		codingStyle: true,
		typeDeclarations: true,
		privatization: true,
		instanceOf: true,
		earlyReturn: true,
		strictBooleans: true,
		rectorPreset: true,
		phpunitCodeQuality: true,
	);
