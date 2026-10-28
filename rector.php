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
	)
	->withSkip([
		// stubs must mirror the real classes signatures
		__DIR__ . '/tests/Stubs.php',
		// can introduce runtime TypeErrors, enable file by file with manual review
		\Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector::class,
		// these rules trust declared types and rewrite (or drop) null/falsy checks, which is unsafe on untrusted input
		\Rector\Strict\Rector\BooleanNot\BooleanInBooleanNotRuleFixerRector::class,
		\Rector\Strict\Rector\Ternary\BooleanInTernaryOperatorRuleFixerRector::class,
		\Rector\Strict\Rector\If_\BooleanInIfConditionRuleFixerRector::class,
		\Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector::class,
		\Rector\DeadCode\Rector\If_\ReduceAlwaysFalseIfOrRector::class,
		// moves trailing comments (`$a = 1; // comment`) to the next line, attaching them to the wrong statement
		\Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector::class,
		// prefer `!== null` over `instanceof \Fully\Qualified\Name`
		\Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector::class,
	]);
