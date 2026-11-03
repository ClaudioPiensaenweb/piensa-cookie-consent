<?php
/**
 * Tests for keeping the declaration in step with what the site really sets.
 *
 * Visitors' browsers report cookie names; whatever is recognised joins the
 * declaration, the site's own cookies are never flagged, and nothing shaped
 * like a value is ever stored.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = null ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}

require_once __DIR__ . '/../includes/class-scanner.php';
require_once __DIR__ . '/../includes/class-monitor.php';

return function ( $assert ) {
	$clean = Piensa_Cookie_Consent_Monitor::clean_names( [ '_ga', '_ga_ABC123', 'cc_cookie', '_ga', 'value=with spaces', '{"json":1}', 42, str_repeat( 'a', 101 ) ] );
	$assert( $clean === [ '_ga', '_ga_ABC123', 'cc_cookie' ], 'Only name-shaped strings are kept, once each' );
	$assert( Piensa_Cookie_Consent_Monitor::clean_names( 'not a list' ) === [], 'A report that is not a list is ignored' );
	$assert( count( Piensa_Cookie_Consent_Monitor::clean_names( array_map( 'strval', range( 1, 500 ) ) ) ) === Piensa_Cookie_Consent_Monitor::MAX_NAMES, 'A report is capped' );

	$definitions = [
		'necessary' => [
			'cookies' => [
				[ 'name' => 'cc_cookie' ],
				[ 'name' => 'wordpress_*' ],
			],
		],
		'analytics' => [
			'cookies' => [
				[ 'name' => '_ga_*' ],
			],
		],
		'marketing' => [ 'cookies' => [] ],
	];

	$assert( Piensa_Cookie_Consent_Monitor::declared_category( 'cc_cookie', $definitions ) === 'necessary', 'The consent cookie is known' );
	$assert( Piensa_Cookie_Consent_Monitor::declared_category( 'wordpress_logged_in_abc', $definitions ) === 'necessary', 'A declared pattern matches its family' );
	$assert( Piensa_Cookie_Consent_Monitor::declared_category( '_ga_XYZ', $definitions ) === 'analytics', 'GA4 property cookies match _ga_*' );
	$assert( Piensa_Cookie_Consent_Monitor::declared_category( '_fbp', $definitions ) === null, 'An undeclared cookie is not known' );

	$detected = [
		[
			'name'     => '_clck',
			'domain'   => 'example.es',
			'category' => 'analytics',
		],
		[
			'name'     => 'cc_cookie',
			'domain'   => 'example.es',
			'category' => 'necessary',
		],
		[
			'name'     => 'mystery',
			'domain'   => 'example.es',
			'category' => 'unknown',
		],
		[
			'name'     => 'ajs_user_id',
			'domain'   => 'example.es',
			'category' => 'marketing',
			'type'     => 'storage',
		],
	];

	$merged = Piensa_Cookie_Consent_Scanner::add_detected_cookies( $definitions, $detected );
	$names  = function ( $category ) use ( $merged ) {
		return array_column( $merged[ $category ]['cookies'], 'name' );
	};

	$assert( in_array( '_clck', $names( 'analytics' ), true ), 'A classified cookie found on the site joins the declaration' );
	$assert( count( array_keys( $names( 'necessary' ), 'cc_cookie', true ) ) === 1, 'An already declared cookie is not declared twice' );
	$assert( ! in_array( 'mystery', array_merge( $names( 'necessary' ), $names( 'analytics' ), $names( 'marketing' ) ), true ), 'An unclassified cookie stays out until classified' );
	$storage = $merged['marketing']['cookies'][0];
	$assert( $storage['name'] === 'ajs_user_id' && $storage['duration'] === 'Until deleted', 'A storage key is declared as one' );
	foreach ( $merged as $data ) {
		foreach ( $data['cookies'] as $cookie ) {
			if ( isset( $cookie['description'] ) ) {
				$assert( $cookie['description'] !== '' && $cookie['duration'] !== '', 'Every added cookie has a purpose and a duration: ' . $cookie['name'] );
			}
		}
	}

	$assert( Piensa_Cookie_Consent_Monitor::token_matches( 'abc', 'abc' ), 'The right token is accepted' );
	$assert( ! Piensa_Cookie_Consent_Monitor::token_matches( 'abd', 'abc' ), 'A wrong token is refused' );
	$assert( ! Piensa_Cookie_Consent_Monitor::token_matches( '', '' ), 'No token configured refuses everyone' );
};
