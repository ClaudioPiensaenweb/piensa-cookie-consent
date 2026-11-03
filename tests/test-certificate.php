<?php
/**
 * Tests for the Kit Digital compliance certificate.
 *
 * The checks are what goes to Red.es as evidence, so each one must fail when
 * its requirement is not met, not only pass when it is.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function wp_strip_all_tags( $text ) { // phpcs:ignore
		return strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) { // phpcs:ignore
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

require_once __DIR__ . '/../includes/class-policy-page.php';
require_once __DIR__ . '/../includes/class-certificate.php';

return function ( $assert ) {
	$compliant = [
		'geo_mode'          => 'all',
		'geo_countries'     => '',
		'accept_label'      => 'Aceptar todas',
		'reject_label'      => 'Rechazar no necesarias',
		'manage_label'      => 'Gestionar preferencias',
		'enable_blocker'    => true,
		'block_unknown'     => true,
		'floating_button'   => true,
		'consent_log'       => true,
		'log_counts'        => [
			'total'     => 10,
			'all'       => 6,
			'necessary' => 3,
			'partial'   => 1,
		],
		'cookie_policy_url' => 'https://emedemuro.es/politica-cookies/',
		'privacy_url'       => 'https://emedemuro.es/politica-privacidad/',
		'policy_page'       => [
			'status'          => 200,
			'error'           => '',
			'has_declaration' => true,
			'terms'           => Piensa_Cookie_Consent_Certificate::find_terms( '<p>Cookies propias y de terceros; técnicas, analíticas y publicitarias.</p>' ),
		],
		'definitions'       => [
			'necessary' => [
				'cookies' => [
					[
						'name'         => 'pcc_consent',
						'domain'       => 'emedemuro.es',
						'description'  => 'Guarda tu decisión.',
						'duration'     => '6 meses',
						'origin_label' => 'Propia',
					],
				],
			],
			'analytics' => [
				'cookies' => [
					[
						'name'         => '_ga',
						'domain'       => 'emedemuro.es',
						'description'  => 'Google Analytics.',
						'duration'     => '2 años',
						'origin_label' => 'De terceros (Google)',
					],
				],
			],
		],
		'unknown_cookies'   => [],
	];

	$status = function ( $facts, $id ) {
		foreach ( Piensa_Cookie_Consent_Certificate::evaluate( $facts ) as $check ) {
			if ( $check['id'] === $id ) {
				return $check['status'];
			}
		}
		return null;
	};

	$checks = Piensa_Cookie_Consent_Certificate::evaluate( $compliant );
	$assert( count( $checks ) === 10, 'Ten requirements are checked' );
	$assert( Piensa_Cookie_Consent_Certificate::overall( $checks ) === 'pass', 'A compliant site passes' );

	// The point of the emedemuro.es requirement: a hand-written policy without
	// the types fails, naming what is missing.
	$handwritten                = $compliant;
	$handwritten['policy_page'] = [
		'status'          => 200,
		'error'           => '',
		'has_declaration' => false,
		'terms'           => Piensa_Cookie_Consent_Certificate::find_terms( '<p>Cookies de análisis. Cookies publicitarias.</p>' ),
	];
	$assert( $status( $handwritten, 'types' ) === 'fail', 'A policy without first-party, third-party and technical cookies fails' );
	$types = array_values( array_filter( Piensa_Cookie_Consent_Certificate::evaluate( $handwritten ), function ( $c ) { return $c['id'] === 'types'; } ) ); // phpcs:ignore
	$assert( false !== strpos( $types[0]['evidence'], 'propias' ) && false !== strpos( $types[0]['evidence'], 'técnicas' ), 'The failure names the missing types' );
	$assert( Piensa_Cookie_Consent_Certificate::overall( Piensa_Cookie_Consent_Certificate::evaluate( $handwritten ) ) === 'fail', 'One failure fails the certificate' );

	$no_banner             = $compliant;
	$no_banner['geo_mode'] = 'none';
	$assert( $status( $no_banner, 'banner' ) === 'fail', 'No banner fails' );

	$no_spain                  = $compliant;
	$no_spain['geo_mode']      = 'custom';
	$no_spain['geo_countries'] = 'FR,DE';
	$assert( $status( $no_spain, 'banner' ) === 'fail', 'A banner that leaves Spain out fails' );

	$no_blocker                   = $compliant;
	$no_blocker['enable_blocker'] = false;
	$assert( $status( $no_blocker, 'blocking' ) === 'fail', 'No prior blocking fails' );

	$partial                  = $compliant;
	$partial['block_unknown'] = false;
	$assert( $status( $partial, 'blocking' ) === 'warn', 'Letting unknown third parties through is flagged' );

	$no_url                      = $compliant;
	$no_url['cookie_policy_url'] = '';
	$assert( $status( $no_url, 'policy_url' ) === 'fail', 'No policy URL fails' );

	$broken                          = $compliant;
	$broken['policy_page']['status'] = 404;
	$assert( $status( $broken, 'policy_url' ) === 'fail', 'A policy page that does not load fails' );

	$incomplete = $compliant;
	unset( $incomplete['definitions']['analytics']['cookies'][0]['duration'] );
	$assert( $status( $incomplete, 'declared' ) === 'fail', 'A cookie without a duration fails' );

	$unknown                    = $compliant;
	$unknown['unknown_cookies'] = [ 'mystery' ];
	$assert( $status( $unknown, 'unknown' ) === 'warn', 'An unclassified detected cookie is flagged' );

	$no_withdraw                                   = $compliant;
	$no_withdraw['floating_button']                = false;
	$no_withdraw['policy_page']['has_declaration'] = false;
	$assert( $status( $no_withdraw, 'withdraw' ) === 'fail', 'No way to withdraw consent fails' );

	$empty_log                        = $compliant;
	$empty_log['log_counts']['total'] = 0;
	$assert( $status( $empty_log, 'log' ) === 'warn', 'An empty consent log is flagged' );

	$terms = Piensa_Cookie_Consent_Certificate::find_terms( '<p>COOKIES PROPIAS</p>' );
	$assert( $terms['propias'] && ! $terms['de terceros'], 'Terms are found regardless of case, and only those present' );

	$flat = Piensa_Cookie_Consent_Certificate::flatten_cookies( $compliant['definitions'] );
	$assert( count( $flat ) === 2 && $flat[1]['type'] === 'Analítica' && $flat[1]['origin'] === 'De terceros (Google)', 'Cookies are listed with their type and origin' );
};
