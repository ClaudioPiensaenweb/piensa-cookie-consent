<?php
/**
 * Inline SVG icons.
 *
 * WordPress.org guideline #8 forbids pulling assets from third-party CDNs, so
 * the handful of icons the banner needs ship inline rather than as a webfont.
 *
 * Icons are from Lucide (https://lucide.dev), ISC licence:
 *
 *   Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2022 as
 *   part of Feather (MIT). All other copyright (c) for Lucide are held by
 *   Lucide Contributors 2022.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies inline SVG markup for the icons used by the banner and the admin UI.
 */
class Piensa_Cookie_Consent_Icons {

	/**
	 * Icon body markup, keyed by icon name.
	 *
	 * Only the inner paths are stored; the wrapping `<svg>` element is built in
	 * `get()` so size and class stay under the caller's control.
	 *
	 * @var array<string, string>
	 */
	private static $paths = [
		'cookie'       => '<path d="M11 17h.01"/><path d="M11.496 2c.324-.016.558.292.529.615a4 4 0 0 0 4.235 4.368.713.713 0 0 1 .758.757 4 4 0 0 0 4.366 4.237c.323-.03.63.204.614.527a10 10 0 0 1-2.915 6.566A1 1 0 1 1 4.93 4.918 10 10 0 0 1 11.496 2"/><path d="M12 12h.01"/><path d="M16 16h.01"/><path d="M16 3h.01"/><path d="M21 4h.01"/><path d="M21 8h.01"/><path d="M7 14h.01"/><path d="M9 8h.01"/>',
		'shield'       => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 22V2"/>',
		'shield-check' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
		'lock'         => '<rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
	];

	/**
	 * Icon names that were offered by earlier releases, mapped to their
	 * replacement so a stored setting keeps rendering something sensible.
	 *
	 * @var array<string, string>
	 */
	private static $aliases = [
		'cookie-bite' => 'cookie',
		'fingerprint' => 'shield-check',
	];

	/**
	 * Resolve an icon name, following aliases.
	 *
	 * @param string $name Requested icon name.
	 *
	 * @return string Canonical icon name.
	 */
	public static function resolve( $name ) {
		$name = (string) $name;

		if ( isset( self::$aliases[ $name ] ) ) {
			$name = self::$aliases[ $name ];
		}

		return isset( self::$paths[ $name ] ) ? $name : 'cookie';
	}

	/**
	 * Return the inline SVG for an icon.
	 *
	 * The markup is decorative, so it is hidden from assistive technology; the
	 * surrounding control carries the accessible name.
	 *
	 * @param string $name  Icon name.
	 * @param int    $size  Width and height in pixels.
	 * @param string $class Extra CSS classes.
	 *
	 * @return string SVG markup, safe to echo.
	 */
	public static function get( $name, $size = 24, $class = '' ) {
		$name    = self::resolve( $name );
		$classes = trim( 'pcc-icon ' . $class );

		return sprintf(
			'<svg class="%s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
			esc_attr( $classes ),
			(int) $size,
			(int) $size,
			self::$paths[ $name ]
		);
	}

	/**
	 * The SVG elements and attributes `wp_kses()` must let through.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function get_allowed_html() {
		$shared = [
			'stroke'           => true,
			'stroke-width'     => true,
			'stroke-linecap'   => true,
			'stroke-linejoin'  => true,
			'fill'             => true,
		];

		return [
			'svg'  => array_merge(
				$shared,
				[
					'class'       => true,
					'width'       => true,
					'height'      => true,
					'viewbox'     => true,
					'xmlns'       => true,
					'aria-hidden' => true,
					'focusable'   => true,
					'role'        => true,
				]
			),
			'path' => array_merge( $shared, [ 'd' => true ] ),
			'rect' => array_merge( $shared, [ 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ] ),
		];
	}

	/**
	 * Return every icon body, for handing to the front-end script.
	 *
	 * @return array<string, string>
	 */
	public static function get_all_paths() {
		return self::$paths;
	}

	/**
	 * Return the icon choices offered in the admin UI.
	 *
	 * @return array<string, string> Icon name => translated label.
	 */
	public static function get_choices() {
		return [
			'cookie'       => __( 'Cookie', 'piensa-cookie-consent' ),
			'shield'       => __( 'Shield', 'piensa-cookie-consent' ),
			'shield-check' => __( 'Verified shield', 'piensa-cookie-consent' ),
			'lock'         => __( 'Padlock', 'piensa-cookie-consent' ),
		];
	}
}
