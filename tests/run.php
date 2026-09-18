<?php
/**
 * Minimal test runner.
 *
 * PHPUnit plus the WordPress test suite is the right answer for tests that
 * need a database; it is not the right answer for pure logic, where the setup
 * costs more than the tests. Each test file returns a callable that takes an
 * assert function. Anything needing WordPress belongs in a suite of its own.
 *
 * Each file runs in its own process, via run-file.php. Test files stub
 * WordPress functions and classes to get at the logic underneath, and two of
 * them stubbing the same name in one process is a fatal error that has nothing
 * to do with the code being tested.
 *
 * Usage: php tests/run.php
 *
 * @package Piensa_Cookie_Consent
 */

$passed = 0;
$failed = [];

$files = glob( __DIR__ . '/test-*.php' );

if ( ! $files ) {
	echo 'No test files found.' . PHP_EOL;
	exit( 1 );
}

$php    = defined( 'PHP_BINARY' ) && PHP_BINARY ? PHP_BINARY : 'php';
$runner = __DIR__ . '/run-file.php';

foreach ( $files as $file ) {
	$command = escapeshellarg( $php ) . ' ' . escapeshellarg( $runner ) . ' ' . escapeshellarg( $file );

	exec( $command . ' 2>&1', $output, $status );

	if ( 0 !== $status ) {
		$failed[] = basename( $file ) . ': the file did not run (' . trim( implode( ' ', $output ) ) . ')';
		$output   = [];
		continue;
	}

	foreach ( $output as $line ) {
		if ( 'PASS' === $line ) {
			++$passed;
			continue;
		}

		if ( 0 === strpos( $line, 'FAIL ' ) ) {
			$failed[] = substr( $line, 5 );
			continue;
		}

		// Anything else on stdout is a warning or a notice the test provoked,
		// which is worth failing on rather than scrolling past.
		if ( '' !== trim( $line ) ) {
			$failed[] = basename( $file ) . ': unexpected output: ' . $line;
		}
	}

	$output = [];
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
