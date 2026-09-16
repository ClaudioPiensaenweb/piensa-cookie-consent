<?php
/**
 * Tests for the per-language banner text.
 *
 * The WordPress functions involved are stubbed with an in-memory option store,
 * which is enough: the logic under test is which value wins, not how options
 * are persisted.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'PIENSA_COOKIE_CONSENT_PATH' ) ) {
	define( 'PIENSA_COOKIE_CONSENT_PATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['piensa_test_options'] = [];

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Option name.
	 * @param mixed  $default Value when unset.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) { // phpcs:ignore
		return array_key_exists( $key, $GLOBALS['piensa_test_options'] )
			? $GLOBALS['piensa_test_options'][ $key ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key      Option name.
	 * @param mixed  $value    Value.
	 * @param bool   $autoload Ignored.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) { // phpcs:ignore
		unset( $autoload );
		$GLOBALS['piensa_test_options'][ $key ] = $value;
		return true;
	}
}

foreach ( [ 'sanitize_text_field', 'sanitize_textarea_field' ] as $piensa_test_stub ) {
	if ( ! function_exists( $piensa_test_stub ) ) {
		eval( 'function ' . $piensa_test_stub . '( $value ) { return trim( (string) $value ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Ignored.
	 * @return string
	 */
	function __( $text, $domain = null ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}

require_once __DIR__ . '/../includes/class-banner-text.php';

return function ( $assert ) {
	$languages = Piensa_Cookie_Consent_Banner_Text::get_languages();

	foreach ( [ 'es', 'en', 'de', 'fr' ] as $code ) {
		$assert( isset( $languages[ $code ] ), "$code is shipped" );

		$text = Piensa_Cookie_Consent_Banner_Text::get_defaults_for( $code );

		foreach ( array_keys( Piensa_Cookie_Consent_Banner_Text::get_fields() ) as $field ) {
			$assert(
				isset( $text[ $field ] ) && '' !== trim( $text[ $field ] ),
				"$code: $field has a default"
			);
		}
	}

	// The bug this release fixes: Spanish showing English. Every Spanish
	// default must differ from its English counterpart, or the shipped file
	// has an untranslated string in it.
	$spanish = Piensa_Cookie_Consent_Banner_Text::get_defaults_for( 'es' );
	$english = Piensa_Cookie_Consent_Banner_Text::get_defaults_for( 'en' );

	foreach ( $spanish as $field => $value ) {
		$assert(
			! isset( $english[ $field ] ) || $value !== $english[ $field ],
			"es: $field is not the English string"
		);
	}

	// An override wins.
	$settings = [ 'banner_text' => [ 'es' => [ 'banner_title' => 'Cookies, por favor' ] ] ];
	$text     = Piensa_Cookie_Consent_Banner_Text::get_text( 'es', $settings );
	$assert( 'Cookies, por favor' === $text['banner_title'], 'an override is used' );
	$assert( $spanish['banner_accept_all'] === $text['banner_accept_all'], 'other fields keep their default' );

	// An emptied field falls back rather than rendering blank, which on a
	// button would leave nothing to click.
	$settings = [ 'banner_text' => [ 'es' => [ 'banner_accept_all' => '   ' ] ] ];
	$text     = Piensa_Cookie_Consent_Banner_Text::get_text( 'es', $settings );
	$assert( $spanish['banner_accept_all'] === $text['banner_accept_all'], 'an emptied field falls back' );

	// Sanitising drops languages and fields the plugin does not know.
	$clean = Piensa_Cookie_Consent_Banner_Text::sanitize(
		[
			'es' => [
				'banner_title' => ' Hola ',
				'not_a_field'  => 'x',
			],
			'zz' => [ 'banner_title' => 'nope' ],
		]
	);
	$assert( 'Hola' === $clean['es']['banner_title'], 'a known field is kept and trimmed' );
	$assert( ! isset( $clean['es']['not_a_field'] ), 'an unknown field is dropped' );
	$assert( ! isset( $clean['zz'] ), 'an unknown language is dropped' );
	$assert( [] === Piensa_Cookie_Consent_Banner_Text::sanitize( 'not an array' ), 'a non-array is rejected' );
};
