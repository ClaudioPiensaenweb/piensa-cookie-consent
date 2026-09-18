<?php
/**
 * Tests for the geographic gate.
 *
 * This decides whether the banner exists at all, so a mistake here is either a
 * site with no consent notice in the EEA, or a notice shown where it was
 * deliberately switched off. Neither announces itself.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) { // phpcs:ignore
		return trim( strip_tags( (string) $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) { // phpcs:ignore
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

require_once __DIR__ . '/../includes/class-geo.php';

/**
 * Run a request with the given headers.
 *
 * @param array $headers Header name => value.
 *
 * @return void
 */
function piensa_test_with_headers( $headers ) {
	foreach ( array_keys( $_SERVER ) as $key ) {
		if ( 0 === strpos( $key, 'HTTP_' ) ) {
			unset( $_SERVER[ $key ] );
		}
	}

	foreach ( $headers as $name => $value ) {
		$_SERVER[ 'HTTP_' . strtoupper( str_replace( '-', '_', $name ) ) ] = $value;
	}
}

return function ( $assert ) {
	$show = 'Piensa_Cookie_Consent_Geo::should_show_cmp';

	piensa_test_with_headers( [] );

	// The default, and what most sites run: everyone sees it.
	$assert( true === $show( [] ), 'with no setting the banner is shown' );
	$assert( true === $show( [ 'geo_mode' => 'all' ] ), 'mode all shows the banner' );
	$assert( false === $show( [ 'geo_mode' => 'none' ] ), 'mode none hides the banner' );

	// EEA mode, with the country coming from the CDN.
	piensa_test_with_headers( [ 'CF-IPCountry' => 'ES' ] );
	$assert( true === $show( [ 'geo_mode' => 'eea' ] ), 'a Spanish visitor is inside the EEA' );

	piensa_test_with_headers( [ 'CF-IPCountry' => 'de' ] );
	$assert( true === $show( [ 'geo_mode' => 'eea' ] ), 'the country is read case-insensitively' );

	piensa_test_with_headers( [ 'CF-IPCountry' => 'US' ] );
	$assert( false === $show( [ 'geo_mode' => 'eea' ] ), 'a visitor from outside the EEA is not shown it' );

	// The United Kingdom and Switzerland are on the list on purpose, though
	// neither is in the EEA: UK GDPR and PECR ask for the same notice, and so
	// does the revised Swiss data protection act. Leaving them out would mean a
	// site with no notice for visitors who are entitled to one.
	piensa_test_with_headers( [ 'CF-IPCountry' => 'GB' ] );
	$assert( true === $show( [ 'geo_mode' => 'eea' ] ), 'the United Kingdom is covered' );

	piensa_test_with_headers( [ 'CF-IPCountry' => 'CH' ] );
	$assert( true === $show( [ 'geo_mode' => 'eea' ] ), 'Switzerland is covered' );

	// The safe direction when the country is unknown is to show the notice,
	// because the alternative is dropping cookies on someone entitled to be
	// asked first.
	piensa_test_with_headers( [] );
	$assert( true === $show( [ 'geo_mode' => 'eea' ] ), 'an unknown country still gets the banner' );

	piensa_test_with_headers( [ 'CF-IPCountry' => 'XX' ] );
	$assert( true === $show( [ 'geo_mode' => 'eea' ] ), 'the placeholder country XX counts as unknown' );

	// A custom list.
	piensa_test_with_headers( [ 'CF-IPCountry' => 'MX' ] );
	$assert(
		true === $show(
			[
				'geo_mode'      => 'custom',
				'geo_countries' => 'ES, MX, AR',
			]
		),
		'a country on the list is shown the banner'
	);
	$assert(
		false === $show(
			[
				'geo_mode'      => 'custom',
				'geo_countries' => 'ES FR',
			]
		),
		'a country off the list is not'
	);
	$assert(
		true === $show(
			[
				'geo_mode'      => 'custom',
				'geo_countries' => '',
			]
		),
		'an empty list falls back to showing it'
	);

	// A named header wins over the automatic search, which is how a site behind
	// something unusual points the plugin at the right one.
	piensa_test_with_headers(
		[
			'CF-IPCountry' => 'US',
			'X-My-Country' => 'ES',
		]
	);
	$assert(
		'ES' === Piensa_Cookie_Consent_Geo::get_country_code( 'X-My-Country' ),
		'a named header is read instead of the defaults'
	);
	$assert(
		'US' === Piensa_Cookie_Consent_Geo::get_country_code( 'auto' ),
		'auto finds the CDN header'
	);

	// The list parser.
	$list = Piensa_Cookie_Consent_Geo::parse_country_list( ' es,  fr FR,, de ' );
	$assert( [ 'ES', 'FR', 'DE' ] === $list, 'the list is upper-cased, split and de-duplicated' );
	$assert( [] === Piensa_Cookie_Consent_Geo::parse_country_list( null ), 'a non-string list is empty' );

	piensa_test_with_headers( [] );
};
