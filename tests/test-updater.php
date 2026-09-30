<?php
/**
 * Tests for the self-hosted update channel.
 *
 * The failure these cover is the quietest one in the plugin: an update channel
 * that is never consulted looks exactly like a plugin that is already up to
 * date. The address of the manifest was shown only as the settings field's
 * placeholder, so the stored setting stayed empty, get_update_url() returned
 * nothing, and no site ever asked whether a newer version existed.
 *
 * WordPress is stubbed down to what the updater touches.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'PIENSA_COOKIE_CONSENT_VERSION' ) ) {
	define( 'PIENSA_COOKIE_CONSENT_VERSION', '1.6.3' );
}

$GLOBALS['piensa_test_updater'] = [
	'settings'   => [],
	'transients' => [],
	'requested'  => [],
	'manifest'   => [
		'version'      => '1.7.0',
		'package'      => 'https://example.test/piensa-cookie-consent-1.7.0-agency.zip',
		'details_url'  => 'https://example.test/releases/v1.7.0',
		'checksum'     => 'abc123',
		'checksum_alg' => 'sha256',
		'requires'     => '6.0',
		'requires_php' => '7.4',
	],
];

/**
 * Stub of the settings store the updater reads.
 */
class Piensa_Cookie_Consent_Admin {

	/**
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		return $GLOBALS['piensa_test_updater']['settings'];
	}
}

if ( ! function_exists( 'plugin_basename' ) ) {
	/**
	 * @param string $file Plugin file.
	 * @return string
	 */
	function plugin_basename( $file ) { // phpcs:ignore
		return 'piensa-cookie-consent/piensa-cookie-consent.php';
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) { // phpcs:ignore
		unset( $hook );
		return $value;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @return string
	 */
	function home_url() { // phpcs:ignore
		return 'https://cliente.test';
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param array  $args Arguments.
	 * @param string $url  Address.
	 * @return string
	 */
	function add_query_arg( $args, $url ) { // phpcs:ignore
		$pairs = [];

		foreach ( $args as $key => $value ) {
			$pairs[] = rawurlencode( $key ) . '=' . rawurlencode( $value );
		}

		return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . implode( '&', $pairs );
	}
}

if ( ! function_exists( 'get_site_transient' ) ) {
	/**
	 * @param string $key Transient name.
	 * @return mixed
	 */
	function get_site_transient( $key ) { // phpcs:ignore
		return isset( $GLOBALS['piensa_test_updater']['transients'][ $key ] )
			? $GLOBALS['piensa_test_updater']['transients'][ $key ]
			: false;
	}
}

if ( ! function_exists( 'set_site_transient' ) ) {
	/**
	 * @param string $key   Transient name.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Lifetime.
	 * @return bool
	 */
	function set_site_transient( $key, $value, $ttl = 0 ) { // phpcs:ignore
		unset( $ttl );
		$GLOBALS['piensa_test_updater']['transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * @param string $url  Address.
	 * @param array  $args Request arguments.
	 * @return array
	 */
	function wp_remote_get( $url, $args = [] ) { // phpcs:ignore
		unset( $args );
		$GLOBALS['piensa_test_updater']['requested'][] = $url;

		return [
			'code' => 200,
			'body' => wp_json_encode( $GLOBALS['piensa_test_updater']['manifest'] ),
		];
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function wp_json_encode( $value ) { // phpcs:ignore
		return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param array $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) { // phpcs:ignore
		return $response['code'];
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param array $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) { // phpcs:ignore
		return $response['body'];
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) { // phpcs:ignore
		return false;
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * @param string $url Address.
	 * @return string
	 */
	function esc_url_raw( $url ) { // phpcs:ignore
		return $url;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) { // phpcs:ignore
		return trim( (string) $value );
	}
}

require_once __DIR__ . '/../includes/class-updater.php';

/**
 * A fresh updater with the given settings and no cached manifest.
 *
 * @param array $settings Plugin settings.
 *
 * @return Piensa_Cookie_Consent_Updater
 */
function piensa_test_updater( $settings = [] ) {
	$GLOBALS['piensa_test_updater']['settings']   = $settings;
	$GLOBALS['piensa_test_updater']['transients'] = [];
	$GLOBALS['piensa_test_updater']['requested']  = [];

	return new Piensa_Cookie_Consent_Updater( __FILE__ );
}

return function ( $assert ) {
	$slug = 'piensa-cookie-consent/piensa-cookie-consent.php';

	/**
	 * Change one field of the manifest the stubbed server returns.
	 */
	$manifest = function ( $key, $value ) {
		$GLOBALS['piensa_test_updater']['manifest'][ $key ] = $value;
	};

	// The bug: nothing configured, which is every install, because the address
	// was only ever shown as the field's placeholder.
	$updater  = piensa_test_updater( [] );
	$response = $updater->check_updates( new stdClass() );

	$assert(
		! empty( $GLOBALS['piensa_test_updater']['requested'] ),
		'an unconfigured site asks for the manifest at all'
	);
	$assert(
		isset( $response->response[ $slug ] ),
		'an unconfigured site is offered the update'
	);
	$assert(
		isset( $response->response[ $slug ] ) && '1.7.0' === $response->response[ $slug ]->new_version,
		'the offered version is the one in the manifest'
	);
	$assert(
		isset( $response->response[ $slug ] ) && 'abc123' === $response->response[ $slug ]->ag_checksum,
		'the checksum travels with the offer, so the download can be verified'
	);

	// The default really is the official channel.
	$requested = $GLOBALS['piensa_test_updater']['requested'][0];
	$assert(
		0 === strpos( $requested, Piensa_Cookie_Consent_Updater::DEFAULT_MANIFEST ),
		'the default channel is the published manifest'
	);

	// A site pointing somewhere else keeps pointing there.
	$updater = piensa_test_updater( [ 'update_server_url' => 'https://interno.test/manifest.json' ] );
	$updater->check_updates( new stdClass() );
	$assert(
		0 === strpos( $GLOBALS['piensa_test_updater']['requested'][0], 'https://interno.test/manifest.json' ),
		'an explicit address wins over the default'
	);

	// Equal or older versions must not be offered, or the site would loop
	// reinstalling what it already has.
	$manifest( 'version', PIENSA_COOKIE_CONSENT_VERSION );
	$updater  = piensa_test_updater( [] );
	$response = $updater->check_updates( new stdClass() );
	$assert( ! isset( $response->response[ $slug ] ), 'the installed version is not offered to itself' );

	$manifest( 'version', '1.0.0' );
	$updater  = piensa_test_updater( [] );
	$response = $updater->check_updates( new stdClass() );
	$assert( ! isset( $response->response[ $slug ] ), 'an older version is not offered' );

	$manifest( 'version', '1.7.0' );

	// A manifest with no package is not actionable, and offering it would give
	// the site an update button that fails.
	$manifest( 'package', '' );
	$updater  = piensa_test_updater( [] );
	$response = $updater->check_updates( new stdClass() );
	$assert( ! isset( $response->response[ $slug ] ), 'a manifest with no package is ignored' );

	$manifest( 'package', 'https://example.test/piensa-cookie-consent-1.7.0-agency.zip' );

	// The manifest is read once and cached, so a dashboard load does not mean
	// a request to the update server every time.
	$updater = piensa_test_updater( [] );
	$updater->check_updates( new stdClass() );
	$updater->check_updates( new stdClass() );
	$assert( 1 === count( $GLOBALS['piensa_test_updater']['requested'] ), 'the manifest is fetched once and then cached' );

	// WordPress's own "Check Again" link, on Dashboard → Updates, is meant to
	// bypass every cache in the chain. Ours sat in front of it regardless: the
	// site's own cache answered before our filter ever ran again, so clicking
	// the button showed the same stale verdict until six hours had passed —
	// indistinguishable, to the person clicking it, from the button doing
	// nothing.
	$updater = piensa_test_updater( [] );
	$updater->check_updates( new stdClass() );
	$assert( 1 === count( $GLOBALS['piensa_test_updater']['requested'] ), 'the first check is a real request' );

	$_GET['force-check'] = '1';
	$updater->check_updates( new stdClass() );
	unset( $_GET['force-check'] );

	$assert(
		2 === count( $GLOBALS['piensa_test_updater']['requested'] ),
		'force-check bypasses the cache and makes a fresh request'
	);

	// And without it present, the cache still holds — force-check is not
	// treated as a permanent switch that stays flipped after one request.
	$updater->check_updates( new stdClass() );
	$assert(
		2 === count( $GLOBALS['piensa_test_updater']['requested'] ),
		'the next ordinary check is cached again'
	);

	// "Require a valid signature" defaults to on in real installs — the stub
	// settings above bypass class-admin's own defaulting, which is exactly
	// why this went unnoticed: every existing scenario left the setting
	// simply absent, which reads as false here, never as the true a fresh
	// site actually gets. Passed explicitly, it reproduces the real default.
	//
	// The signature below is real, made with the private half of the key
	// built into the plugin, over the exact payload build_signature_payload()
	// assembles: version|package|checksum. Nothing about the private key
	// itself is in this repository; only what it signed.
	$signed_version   = '9.9.9';
	$signed_package   = 'https://example.test/piensa-cookie-consent-9.9.9-agency.zip';
	$signed_checksum  = 'deadbeef00112233445566778899aabbccddeeff00112233445566778899aa';
	$valid_signature  = 'WL1+XcVWzwd/0QoLficIgl83UHS/i6lQoFe/PpaRj6+cRdSY1UPoQNCWvHXGobk4T3skGgG1qQ+PQYyPeCX5by4BT10JRiwXvyCBvjzhLhOjt6Ac6xKx0pjPOry+brjAHYpYxdks90NOn4xGG7rVSp3Axgf5iAkexUsp+m9m8wwk1OIjN4v1orlZDPeoPPgwcH7rgZ2UI+8738w2TL7ZLbW1Xx6zpwT1MfnpcARfzq0+/M9vtm7pi3kAAf6IT7MU30pmE3Kmvg1rc21//7FaxrehVMkB3C32VQy67VPt2lPtuJwQtAgPgx1up7+7/f9MmZU16BJ334d08zkoQNQ0HLv7WDcAgrvrI2+4xn8NOlPHQXAcLkXt+Y9H/XZc8QWqixD7Tz2p7AeV+VCGKyxKbwoqRjVZGXiI6ELCGveDAWZ+MWEDqAwbChGOZ5zgQKLucUPFjvHRDSAYgqWlTJENXebHH30MxYBP0LK11Ms+fw0RLOMwK22ut8gambWPdPgkHBuvYw+66gnyzLxQxzg49tu05pPyBaOmausoIGelnfslzO3FidoK7AAx1nnqd6eXQ+IzsFF5Mn/aZEsEzLMYvvPGsaxhv3xTTCi5gNYStepdT0gEXELL8apo8xs+wTxt+38jTAcHr4A0ORl9cZ+v6u29tJ1iKYVngQuABdsRPbo=';

	$manifest( 'version', $signed_version );
	$manifest( 'package', $signed_package );
	$manifest( 'checksum', $signed_checksum );
	$manifest( 'signature', $valid_signature );

	$updater  = piensa_test_updater( [ 'update_require_signature' => true ] );
	$response = $updater->check_updates( new stdClass() );
	$assert(
		isset( $response->response[ $slug ] ),
		'the built-in public key verifies a real signature, with no site-level key configured'
	);

	// A tampered or wrong signature must still be rejected — the built-in key
	// makes verification possible everywhere, not verification pointless.
	$manifest( 'signature', substr( $valid_signature, 0, -4 ) . 'AAAA' );
	$updater  = piensa_test_updater( [ 'update_require_signature' => true ] );
	$response = $updater->check_updates( new stdClass() );
	$assert(
		! isset( $response->response[ $slug ] ),
		'a tampered signature is still rejected'
	);

	// Turning the requirement off is unaffected: an unsigned manifest is
	// offered exactly as it always could be.
	$manifest( 'signature', '' );
	$updater  = piensa_test_updater( [ 'update_require_signature' => false ] );
	$response = $updater->check_updates( new stdClass() );
	$assert(
		isset( $response->response[ $slug ] ),
		'signature checking can still be switched off'
	);

	$manifest( 'version', '1.7.0' );
	$manifest( 'package', 'https://example.test/piensa-cookie-consent-1.7.0-agency.zip' );
	$manifest( 'checksum', 'abc123' );
	$manifest( 'signature', null );

	// Automatic installs. The filter answers only for this plugin's own
	// update object, and only once told to: WordPress's automatic updater
	// asks this same question for every plugin on the site, and answering
	// for somebody else's item would auto-update things this plugin has
	// nothing to do with.
	$other_item          = (object) [ 'plugin' => 'some-other-plugin/some-other-plugin.php' ];
	$own_item            = (object) [ 'plugin' => $slug ];

	$updater = piensa_test_updater( [ 'enable_auto_update' => true ] );
	$assert(
		'unrelated' === $updater->maybe_auto_update( 'unrelated', $other_item ),
		'a different plugin\'s update decision is left untouched'
	);
	$assert(
		true === $updater->maybe_auto_update( false, $own_item ),
		'enabling the setting answers yes for this plugin, regardless of the incoming value'
	);

	$updater = piensa_test_updater( [ 'enable_auto_update' => false ] );
	$assert(
		false === $updater->maybe_auto_update( true, $own_item ),
		'disabling the setting answers no even if WordPress\'s own default leaned yes'
	);
};
