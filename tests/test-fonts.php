<?php
/**
 * Tests for serving Google Fonts locally.
 *
 * Found on emedemuro.es: Bricks loaded its fonts from fonts.googleapis.com on
 * every page, sending each visitor's IP address to Google before the banner
 * had been answered, and the blocker let it through as a technical host.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) { // phpcs:ignore
		return htmlspecialchars( $url, ENT_QUOTES ); // phpcs:ignore
	}
}

require_once __DIR__ . '/../includes/class-fonts.php';

return function ( $assert ) {
	$bricks = "<link rel='dns-prefetch' href='//fonts.googleapis.com' />\n"
		. "<link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>\n"
		. "<link rel='stylesheet' id='bricks-google-fonts-css' href='https://fonts.googleapis.com/css2?family=Lexend+Peta:wght@100;400&#038;display=swap' media='all' />\n"
		. '<style>@import url("https://fonts.googleapis.com/css?family=Roboto");body{}</style>';

	$asked  = [];
	$cached = Piensa_Cookie_Consent_Fonts::rewrite(
		$bricks,
		function ( $url ) use ( &$asked ) {
			$asked[] = $url;
			return 'https://example.es/wp-content/uploads/piensa-cookie-consent/fonts/' . md5( $url ) . '/fonts.css';
		}
	);

	$assert( false === strpos( $cached, 'googleapis' ) && false === strpos( $cached, 'gstatic' ), 'With local copies, nothing on the page points at Google' );
	$assert( false !== strpos( $cached, "id='bricks-google-fonts-css'" ) && false !== strpos( $cached, '/fonts.css"' ), 'The stylesheet keeps its tag and points at the local copy' );
	$assert( in_array( 'https://fonts.googleapis.com/css2?family=Lexend+Peta:wght@100;400&display=swap', $asked, true ), 'HTML entities in the URL are decoded before it is fetched' );
	$assert( false !== strpos( $cached, '@import url("https://example.es/' ), 'An @import is rewritten too' );

	$pending = Piensa_Cookie_Consent_Fonts::rewrite(
		$bricks,
		function () {
			return null;
		}
	);
	$assert( false === strpos( $pending, 'googleapis' ) && false === strpos( $pending, 'gstatic' ), 'Without a local copy yet, the reference is removed rather than left to reach Google' );
	$assert( false !== strpos( $pending, 'body{}' ), 'The rest of the styles stay' );

	$untouched = '<link rel="stylesheet" href="https://example.es/style.css">';
	$assert(
		Piensa_Cookie_Consent_Fonts::rewrite(
			$untouched,
			function () {
				return 'x';
			}
		) === $untouched,
		'Other stylesheets are left alone'
	);
};
