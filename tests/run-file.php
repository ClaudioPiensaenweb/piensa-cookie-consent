<?php
/**
 * Run one test file and report its results on stdout.
 *
 * Invoked by run.php once per file, in a process of its own. Test files stub
 * WordPress functions and classes, and two files stubbing the same name in one
 * process is a fatal error — so each gets a clean interpreter rather than the
 * suite being written around what the other files happen to have declared.
 *
 * Output is one line per assertion: `PASS` or `FAIL <description>`.
 *
 * @package Piensa_Cookie_Consent
 */

if ( PHP_SAPI !== 'cli' ) {
	exit( 1 );
}

$file = isset( $argv[1] ) ? $argv[1] : '';

if ( ! $file || ! file_exists( $file ) ) {
	fwrite( STDERR, 'No such test file: ' . $file . PHP_EOL );
	exit( 1 );
}

$assert = function ( $condition, $description ) {
	echo $condition ? 'PASS' . PHP_EOL : 'FAIL ' . $description . PHP_EOL;
};

$test = require $file;

if ( ! is_callable( $test ) ) {
	fwrite( STDERR, 'Returns no callable: ' . $file . PHP_EOL );
	exit( 1 );
}

$test( $assert );
