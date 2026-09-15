<?php
/**
 * Reading the visitor's stored consent.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single point of truth for what the current visitor has consented to.
 *
 * The blocker, Consent Mode and the WP Consent API integration all need the
 * same answer, so the cookie is parsed once and cached for the request.
 */
class Piensa_Cookie_Consent_Consent {

	/**
	 * Name of the cookie written by the front-end consent script.
	 *
	 * Kept as-is from earlier releases: renaming it would silently discard the
	 * consent every existing visitor has already given.
	 */
	const COOKIE = 'cc_cookie';

	/**
	 * Categories granted for this request, or null until first read.
	 *
	 * @var string[]|null
	 */
	private static $cache = null;

	/**
	 * Return the categories the visitor has consented to.
	 *
	 * `necessary` is always granted: it covers cookies the site cannot work
	 * without, which do not require consent under the ePrivacy Directive.
	 *
	 * @return string[]
	 */
	public static function get_granted_categories() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$granted = [ 'necessary' ];

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading the visitor's own consent cookie, not acting on a submitted request.
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			$raw     = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
			$decoded = json_decode( $raw, true );

			if ( is_array( $decoded ) && ! empty( $decoded['categories'] ) && is_array( $decoded['categories'] ) ) {
				foreach ( $decoded['categories'] as $category ) {
					if ( is_string( $category ) ) {
						$granted[] = sanitize_key( $category );
					}
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		self::$cache = array_values( array_unique( array_filter( $granted ) ) );

		return self::$cache;
	}

	/**
	 * Return whether a category has been consented to.
	 *
	 * @param string $category Category name.
	 *
	 * @return bool
	 */
	public static function has_consent( $category ) {
		return in_array( sanitize_key( (string) $category ), self::get_granted_categories(), true );
	}

	/**
	 * Forget the cached value.
	 *
	 * Only useful in tests, where several consent states run in one process.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = null;
	}
}
