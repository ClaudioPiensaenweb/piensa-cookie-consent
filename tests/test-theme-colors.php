<?php
/**
 * Tests for the theme colour heuristic.
 *
 * Runs without WordPress: the class takes a palette and returns colours, and
 * the only WordPress function it touches is stubbed below. Run with
 * `composer test` or `php tests/run.php`.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

$GLOBALS['piensa_test_palette'] = [];

if ( ! function_exists( 'wp_get_global_settings' ) ) {
	/**
	 * Stub of the WordPress function the class reads the palette from.
	 *
	 * @param array $path Settings path, ignored here.
	 *
	 * @return array
	 */
	function wp_get_global_settings( $path ) { // phpcs:ignore
		unset( $path );
		return [ 'theme' => $GLOBALS['piensa_test_palette'] ];
	}
}

require_once __DIR__ . '/../includes/class-theme-colors.php';

/**
 * Palettes taken from real themes, plus the awkward cases.
 *
 * @return array<string, array>
 */
function piensa_test_palettes() {
	return [
		'twentytwentyfour'  => [
			[
				'slug'  => 'base',
				'name'  => 'Base',
				'color' => '#f9f9f9',
			],
			[
				'slug'  => 'contrast',
				'name'  => 'Contrast',
				'color' => '#111111',
			],
			[
				'slug'  => 'accent-1',
				'name'  => 'Accent 1',
				'color' => '#FFE2C7',
			],
			[
				'slug'  => 'accent-2',
				'name'  => 'Accent 2',
				'color' => '#C8A78B',
			],
		],
		'twentytwentythree' => [
			[
				'slug'  => 'base',
				'name'  => 'Base',
				'color' => '#ffffff',
			],
			[
				'slug'  => 'contrast',
				'name'  => 'Contrast',
				'color' => '#000000',
			],
			[
				'slug'  => 'primary',
				'name'  => 'Primary',
				'color' => '#9DFF20',
			],
			[
				'slug'  => 'secondary',
				'name'  => 'Secondary',
				'color' => '#345C00',
			],
		],
		'dark'              => [
			[
				'slug'  => 'background',
				'name'  => 'Background',
				'color' => '#14161a',
			],
			[
				'slug'  => 'foreground',
				'name'  => 'Foreground',
				'color' => '#f2f2f2',
			],
			[
				'slug'  => 'primary',
				'name'  => 'Primary',
				'color' => '#4f8cff',
			],
		],
		'tinted-brand'      => [
			[
				'slug'  => 'base',
				'name'  => 'Base',
				'color' => '#ffffff',
			],
			[
				'slug'  => 'surface',
				'name'  => 'Surface',
				'color' => '#fdeee8',
			],
			[
				'slug'  => 'contrast',
				'name'  => 'Contrast',
				'color' => '#1b2a4a',
			],
			[
				'slug'  => 'primary',
				'name'  => 'Primary',
				'color' => '#ee5a24',
			],
		],
		'unusual-slugs-rgb' => [
			[
				'slug'  => 'lienzo',
				'name'  => 'Lienzo',
				'color' => 'rgb(250, 248, 245)',
			],
			[
				'slug'  => 'tinta',
				'name'  => 'Tinta',
				'color' => 'rgb(26, 26, 26)',
			],
			[
				'slug'  => 'marca',
				'name'  => 'Marca',
				'color' => '#e4572e',
			],
		],
	];
}

/**
 * Relative luminance, duplicated here so the test does not rely on the
 * implementation it is checking.
 *
 * @param string $hex Hex colour.
 *
 * @return float
 */
function piensa_test_luminance( $hex ) {
	$channels = [];

	foreach ( [ 1, 3, 5 ] as $offset ) {
		$value      = hexdec( substr( $hex, $offset, 2 ) ) / 255;
		$channels[] = $value <= 0.03928 ? $value / 12.92 : pow( ( $value + 0.055 ) / 1.055, 2.4 );
	}

	return ( 0.2126 * $channels[0] ) + ( 0.7152 * $channels[1] ) + ( 0.0722 * $channels[2] );
}

/**
 * WCAG contrast ratio.
 *
 * @param string $a Hex colour.
 * @param string $b Hex colour.
 *
 * @return float
 */
function piensa_test_contrast( $a, $b ) {
	$first  = piensa_test_luminance( $a );
	$second = piensa_test_luminance( $b );

	return ( max( $first, $second ) + 0.05 ) / ( min( $first, $second ) + 0.05 );
}

return function ( $assert ) {
	$required = [
		'theme_bg',
		'theme_primary_color',
		'theme_secondary_color',
		'theme_btn_primary_bg',
		'theme_btn_primary_color',
		'theme_btn_secondary_bg',
		'theme_btn_secondary_color',
	];

	foreach ( piensa_test_palettes() as $name => $palette ) {
		$GLOBALS['piensa_test_palette'] = $palette;

		$result = Piensa_Cookie_Consent_Theme_Colors::suggest();

		$assert( ! empty( $result ), "$name: produces a suggestion" );

		foreach ( $required as $key ) {
			$assert(
				isset( $result[ $key ] ) && preg_match( '/^#[0-9a-f]{6}$/', $result[ $key ] ),
				"$name: $key is a hex colour"
			);
		}

		// The banner text has to be readable on the banner.
		$assert(
			piensa_test_contrast( $result['theme_primary_color'], $result['theme_bg'] ) >= 4.5,
			"$name: body text meets WCAG AA on the background"
		);

		// A button label has to be readable on its button.
		$assert(
			piensa_test_contrast( $result['theme_btn_primary_color'], $result['theme_btn_primary_bg'] ) >= 4.5,
			"$name: primary button label meets WCAG AA"
		);

		// And the button has to be distinguishable from the panel it sits on,
		// which is the bug that made a pale accent-1 useless.
		$assert(
			piensa_test_contrast( $result['theme_btn_primary_bg'], $result['theme_bg'] ) >= 3.0,
			"$name: primary button stands out from the background"
		);
	}

	// A theme with a tinted light tone should get it, not flat white, and the
	// brand colour should survive onto the button rather than being flattened
	// to black. Both were happening before the heuristic was loosened.
	$GLOBALS['piensa_test_palette'] = piensa_test_palettes()['tinted-brand'];
	$tinted                         = Piensa_Cookie_Consent_Theme_Colors::suggest();

	$assert( '#fdeee8' === $tinted['theme_bg'], 'tinted background is preferred over flat white' );
	$assert( '#ee5a24' === $tinted['theme_btn_primary_bg'], 'the brand colour reaches the button' );
	$assert( '#ffffff' !== $tinted['theme_primary_color'], 'body text is not the background' );

	// A palette with nothing to work from must decline rather than guess.
	$GLOBALS['piensa_test_palette'] = [
		[
			'slug'  => 'base',
			'name'  => 'Base',
			'color' => '#ffffff',
		],
	];
	$assert( [] === Piensa_Cookie_Consent_Theme_Colors::suggest(), 'single-colour palette: declines' );

	$GLOBALS['piensa_test_palette'] = [];
	$assert( [] === Piensa_Cookie_Consent_Theme_Colors::suggest(), 'empty palette: declines' );

	// Colours the palette can legitimately carry.
	$GLOBALS['piensa_test_palette'] = [
		[
			'slug'  => 'base',
			'name'  => 'Base',
			'color' => '#FFF',
		],
		[
			'slug'  => 'contrast',
			'name'  => 'Contrast',
			'color' => 'rgba(17, 17, 17, 0.9)',
		],
		[
			'slug'  => 'primary',
			'name'  => 'Primary',
			'color' => 'var(--brand)',
		],
	];

	$shorthand = Piensa_Cookie_Consent_Theme_Colors::suggest();

	$assert( '#ffffff' === $shorthand['theme_bg'], 'three-digit hex is expanded' );
	$assert( '#111111' === $shorthand['theme_primary_color'], 'rgba() is read, alpha dropped' );
};
