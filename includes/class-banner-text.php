<?php
/**
 * The text the banner shows, in every language it ships with.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves banner text per language, defaults merged with any overrides.
 *
 * These strings are read by visitors, not by administrators, so they are not
 * part of the plugin's gettext catalogue: a site running in Spanish may well
 * need to show the banner in German. They ship pre-translated and are stored
 * per language in the settings.
 */
class Piensa_Cookie_Consent_Banner_Text {

	/**
	 * Settings key holding the per-language overrides.
	 */
	const OPTION_KEY = 'banner_text';

	/**
	 * Defaults as loaded from the data file, or null before first read.
	 *
	 * @var array|null
	 */
	private static $defaults = null;

	/**
	 * The fields that make up one language's text.
	 *
	 * @return array<string, string> Field => label for the admin.
	 */
	public static function get_fields() {
		return [
			'banner_title'             => __( 'Banner title', 'piensa-cookie-consent' ),
			'banner_description'       => __( 'Banner description', 'piensa-cookie-consent' ),
			'banner_accept_all'        => __( 'Accept all', 'piensa-cookie-consent' ),
			'banner_reject_all'        => __( 'Reject non-essential', 'piensa-cookie-consent' ),
			'banner_manage_prefs'      => __( 'Manage preferences', 'piensa-cookie-consent' ),
			'banner_save_prefs'        => __( 'Save preferences', 'piensa-cookie-consent' ),
			'banner_preferences_title' => __( 'Preferences dialog title', 'piensa-cookie-consent' ),
			'necessary_label'          => __( 'Necessary cookies', 'piensa-cookie-consent' ),
			'necessary_description'    => __( 'Necessary cookies description', 'piensa-cookie-consent' ),
			'necessary_legal_note'     => __( 'Necessary cookies legal note', 'piensa-cookie-consent' ),
			'analytics_label'          => __( 'Analytics cookies', 'piensa-cookie-consent' ),
			'analytics_description'    => __( 'Analytics cookies description', 'piensa-cookie-consent' ),
			'marketing_label'          => __( 'Marketing cookies', 'piensa-cookie-consent' ),
			'marketing_description'    => __( 'Marketing cookies description', 'piensa-cookie-consent' ),
		];
	}

	/**
	 * Fields rendered as a textarea rather than a single line.
	 *
	 * @return string[]
	 */
	public static function get_long_fields() {
		return [
			'banner_description',
			'necessary_description',
			'necessary_legal_note',
			'analytics_description',
			'marketing_description',
		];
	}

	/**
	 * Load the shipped defaults.
	 *
	 * @return array<string, array<string, string>>
	 */
	private static function load_defaults() {
		if ( null !== self::$defaults ) {
			return self::$defaults;
		}

		self::$defaults = [];
		$path           = PIENSA_COOKIE_CONSENT_PATH . 'includes/data/banner-text.json';

		if ( is_readable( $path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A file shipped inside the plugin, not a remote resource.
			$data = json_decode( (string) file_get_contents( $path ), true );

			if ( is_array( $data ) ) {
				foreach ( $data as $code => $strings ) {
					if ( '_comment' === $code || ! is_array( $strings ) ) {
						continue;
					}
					self::$defaults[ $code ] = $strings;
				}
			}
		}

		return self::$defaults;
	}

	/**
	 * Languages the banner ships text for.
	 *
	 * @return array<string, string> Code => display name.
	 */
	public static function get_languages() {
		$languages = [];

		foreach ( self::load_defaults() as $code => $strings ) {
			$languages[ $code ] = isset( $strings['_label'] ) ? $strings['_label'] : strtoupper( $code );
		}

		return $languages;
	}

	/**
	 * Default text for one language.
	 *
	 * @param string $code Language code.
	 *
	 * @return array<string, string>
	 */
	public static function get_defaults_for( $code ) {
		$defaults = self::load_defaults();

		if ( ! isset( $defaults[ $code ] ) ) {
			return [];
		}

		$strings = $defaults[ $code ];
		unset( $strings['_label'] );

		return $strings;
	}

	/**
	 * Text for one language: the site's overrides over the shipped defaults.
	 *
	 * @param string $code     Language code.
	 * @param array  $settings Plugin settings.
	 *
	 * @return array<string, string>
	 */
	public static function get_text( $code, array $settings ) {
		$text      = self::get_defaults_for( $code );
		$overrides = isset( $settings[ self::OPTION_KEY ][ $code ] ) && is_array( $settings[ self::OPTION_KEY ][ $code ] )
			? $settings[ self::OPTION_KEY ][ $code ]
			: [];

		foreach ( self::get_fields() as $field => $unused ) {
			// An empty override means "use the default", not "show nothing":
			// clearing a field should restore the shipped string rather than
			// leave a blank button.
			if ( isset( $overrides[ $field ] ) && '' !== trim( (string) $overrides[ $field ] ) ) {
				$text[ $field ] = (string) $overrides[ $field ];
			}
		}

		return $text;
	}

	/**
	 * Text for every shipped language, ready for the front-end script.
	 *
	 * @param array $settings Plugin settings.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_all( array $settings ) {
		$all = [];

		foreach ( array_keys( self::get_languages() ) as $code ) {
			$all[ $code ] = self::get_text( $code, $settings );
		}

		return $all;
	}

	/**
	 * Sanitise the submitted overrides.
	 *
	 * @param mixed $value Raw value from the settings form.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function sanitize( $value ) {
		$clean     = [];
		$languages = self::get_languages();
		$fields    = self::get_fields();
		$long      = self::get_long_fields();

		if ( ! is_array( $value ) ) {
			return $clean;
		}

		foreach ( $value as $code => $strings ) {
			if ( ! isset( $languages[ $code ] ) || ! is_array( $strings ) ) {
				continue;
			}

			foreach ( $fields as $field => $unused ) {
				if ( ! isset( $strings[ $field ] ) ) {
					continue;
				}

				$clean[ $code ][ $field ] = in_array( $field, $long, true )
					? sanitize_textarea_field( $strings[ $field ] )
					: sanitize_text_field( $strings[ $field ] );
			}
		}

		return $clean;
	}
}
