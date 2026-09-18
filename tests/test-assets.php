<?php
/**
 * Tests for which copy of an asset gets served.
 *
 * The readable source is what the repository and the wordpress.org review see;
 * the minified copy is what visitors download. Getting this wrong is silent in
 * both directions — serving the source costs every visitor twice the bytes,
 * and serving a minified file that no longer matches its source is worse, so
 * CI regenerates them and fails on a difference.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'PIENSA_COOKIE_CONSENT_PATH' ) ) {
	define( 'PIENSA_COOKIE_CONSENT_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'PIENSA_COOKIE_CONSENT_URL' ) ) {
	define( 'PIENSA_COOKIE_CONSENT_URL', 'https://sitio.test/wp-content/plugins/piensa-cookie-consent/' );
}

if ( ! defined( 'PIENSA_COOKIE_CONSENT_FILE' ) ) {
	define( 'PIENSA_COOKIE_CONSENT_FILE', PIENSA_COOKIE_CONSENT_PATH . 'piensa-cookie-consent.php' );
}

require_once __DIR__ . '/../includes/class-core.php';

/**
 * The assets the plugin ships a minified copy of.
 *
 * @return string[]
 */
function piensa_test_minified_assets() {
	return [
		'assets/js/piensa-cookie-consent.js',
		'assets/js/piensa-cookie-consent-admin.js',
		'assets/js/piensa-cookie-consent-audit.js',
		'assets/css/piensa-cookie-consent.css',
		'assets/css/piensa-cookie-consent-admin.css',
	];
}

return function ( $assert ) {
	foreach ( piensa_test_minified_assets() as $relative ) {
		$minified = preg_replace( '/\.(js|css)$/', '.min.$1', $relative );

		$assert(
			file_exists( PIENSA_COOKIE_CONSENT_PATH . $minified ),
			"$minified is present in the package"
		);

		$assert(
			Piensa_Cookie_Consent_Core::asset_url( $relative ) === PIENSA_COOKIE_CONSENT_URL . $minified,
			"$relative is served minified"
		);

		// Worth stating as a test rather than trusting: a minified file that is
		// no smaller means the build did not run.
		$source_size = filesize( PIENSA_COOKIE_CONSENT_PATH . $relative );
		$min_size    = filesize( PIENSA_COOKIE_CONSENT_PATH . $minified );

		$assert( $min_size < $source_size, "$minified is smaller than its source" );
	}

	// The vendored library arrives minified, so there is no second copy and the
	// original has to keep being served.
	$assert(
		Piensa_Cookie_Consent_Core::asset_url( 'assets/js/cookieconsent.js' ) === PIENSA_COOKIE_CONSENT_URL . 'assets/js/cookieconsent.js',
		'an asset with no minified copy is served as it is'
	);

	// A site debugging a problem should read the same code this repository
	// holds, not a minified line.
	define( 'SCRIPT_DEBUG', true );

	$assert(
		Piensa_Cookie_Consent_Core::asset_url( 'assets/js/piensa-cookie-consent.js' ) === PIENSA_COOKIE_CONSENT_URL . 'assets/js/piensa-cookie-consent.js',
		'SCRIPT_DEBUG serves the readable source'
	);
};
