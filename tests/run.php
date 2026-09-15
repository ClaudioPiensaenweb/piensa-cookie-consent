<?php
/**
 * Minimal test runner.
 *
 * PHPUnit plus the WordPress test suite is the right answer for tests that
 * need a database; it is not the right answer for pure logic, where the setup
 * costs more than the tests. Each test file returns a callable that takes an
 * assert function. Anything needing WordPress belongs in a suite of its own.
 *
 * Usage: php tests/run.php
 *
 * @package Piensa_Cookie_Consent
 */

$passed = 0;
$failed = [];

$assert = function ( $condition, $description ) use ( &$passed, &$failed ) {
	if ( $condition ) {
		++$passed;
		return;
	}

	$failed[] = $description;
};

$files = glob( __DIR__ . '/test-*.php' );

if ( ! $files ) {
	echo 'No test files found.' . PHP_EOL;
	exit( 1 );
}

foreach ( $files as $file ) {
	$test = require $file;

	if ( ! is_callable( $test ) ) {
		echo 'Skipped ' . basename( $file ) . ': it returns no callable.' . PHP_EOL;
		continue;
	}

	$test( $assert );
}

echo PHP_EOL;

if ( $failed ) {
	foreach ( $failed as $description ) {
		echo 'FAIL  ' . $description . PHP_EOL;
	}
	echo PHP_EOL . sprintf( '%d passed, %d failed.', $passed, count( $failed ) ) . PHP_EOL;
	exit( 1 );
}

echo sprintf( '%d assertions passed.', $passed ) . PHP_EOL;
