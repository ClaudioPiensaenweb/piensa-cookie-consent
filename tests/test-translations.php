<?php
/**
 * Tests that what the visitor reads is actually translated.
 *
 * The cookie declaration is assembled from gettext strings, so a string added
 * without updating the catalogues appears to a Spanish visitor in English —
 * which is how "2 years" and "24 hours" ended up in the retention column of a
 * Spanish site. Nothing fails, nothing is logged, and the only way to notice is
 * to look at the banner in the right language.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

/**
 * The languages the plugin ships the visitor-facing text in.
 */
const PIENSA_TEST_LOCALES = [ 'es_ES', 'de_DE', 'fr_FR' ];

/**
 * Files whose translatable strings reach the visitor rather than the admin.
 *
 * @return string[]
 */
function piensa_test_visitor_files() {
	return [
		'includes/class-scanner.php',
		'includes/class-blocker.php',
		'includes/class-consent.php',
	];
}

/**
 * Every literal string passed to a gettext function in a file.
 *
 * Only single-quoted literals are collected: a string built at runtime cannot
 * be in a catalogue anyway, and would be a bug of its own.
 *
 * @param string $path File to read.
 *
 * @return string[]
 */
function piensa_test_extract_strings( $path ) {
	$source = file_get_contents( $path );
	$found  = [];

	$pattern = '/\b(?:__|_e|esc_html__|esc_html_e|esc_attr__|esc_attr_e)\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'piensa-cookie-consent\'\s*\)/';

	if ( preg_match_all( $pattern, $source, $matches ) ) {
		foreach ( $matches[1] as $string ) {
			$found[] = stripslashes( $string );
		}
	}

	return array_values( array_unique( $found ) );
}

/**
 * Read a .po file into msgid => msgstr.
 *
 * @param string $path Catalogue.
 *
 * @return array<string, string>
 */
function piensa_test_read_po( $path ) {
	$entries = [];

	if ( ! file_exists( $path ) ) {
		return $entries;
	}

	foreach ( explode( "\n\n", file_get_contents( $path ) ) as $block ) {
		$id      = piensa_test_po_field( $block, 'msgid' );
		$message = piensa_test_po_field( $block, 'msgstr' );

		if ( null === $id || null === $message ) {
			continue;
		}

		$entries[ $id ] = $message;
	}

	return $entries;
}

/**
 * One field of a .po entry, continuation lines included.
 *
 * gettext wraps long values onto following lines, so reading only the line the
 * keyword is on reports a perfectly good translation as missing — which is what
 * this test did on its first run.
 *
 * @param string $block Entry.
 * @param string $field Either msgid or msgstr.
 *
 * @return string|null
 */
function piensa_test_po_field( $block, $field ) {
	$lines   = explode( "\n", $block );
	$value   = null;
	$reading = false;

	foreach ( $lines as $line ) {
		if ( preg_match( '/^' . $field . '\s+"((?:[^"\\\\]|\\\\.)*)"\s*$/', $line, $start ) ) {
			$value   = $start[1];
			$reading = true;
			continue;
		}

		if ( $reading && preg_match( '/^"((?:[^"\\\\]|\\\\.)*)"\s*$/', $line, $more ) ) {
			$value .= $more[1];
			continue;
		}

		$reading = false;
	}

	return null === $value ? null : stripslashes( $value );
}

return function ( $assert ) {
	$root = dirname( __DIR__ ) . '/';

	$strings = [];

	foreach ( piensa_test_visitor_files() as $file ) {
		$assert( file_exists( $root . $file ), "$file exists" );
		$strings = array_merge( $strings, piensa_test_extract_strings( $root . $file ) );
	}

	$strings = array_values( array_unique( $strings ) );

	$assert( count( $strings ) > 10, 'visitor-facing strings were found to check' );

	$template = piensa_test_read_po( $root . 'languages/piensa-cookie-consent.pot' );

	foreach ( $strings as $string ) {
		$assert(
			array_key_exists( $string, $template ),
			'the template knows "' . $string . '"'
		);
	}

	foreach ( PIENSA_TEST_LOCALES as $locale ) {
		$catalogue = piensa_test_read_po( $root . 'languages/piensa-cookie-consent-' . $locale . '.po' );

		$assert( ! empty( $catalogue ), "$locale has a catalogue" );

		foreach ( $strings as $string ) {
			$assert(
				isset( $catalogue[ $string ] ) && '' !== trim( $catalogue[ $string ] ),
				"$locale translates \"$string\""
			);
		}

		// A catalogue nobody compiled is a catalogue WordPress will not load.
		$compiled = $root . 'languages/piensa-cookie-consent-' . $locale . '.mo';

		$assert( file_exists( $compiled ), "$locale is compiled" );
		$assert(
			file_exists( $compiled ) && filemtime( $compiled ) >= filemtime( $root . 'languages/piensa-cookie-consent-' . $locale . '.po' ),
			"$locale's compiled catalogue is not older than its source"
		);
	}

	// Spanish is the language this plugin is primarily used in, so it carries
	// the whole interface and not only what the visitor sees.
	$spanish      = piensa_test_read_po( $root . 'languages/piensa-cookie-consent-es_ES.po' );
	$untranslated = 0;

	foreach ( $spanish as $msgid => $msgstr ) {
		if ( '' !== $msgid && '' === trim( $msgstr ) ) {
			++$untranslated;
		}
	}

	$assert( 0 === $untranslated, "Spanish is complete ($untranslated strings left)" );
};
