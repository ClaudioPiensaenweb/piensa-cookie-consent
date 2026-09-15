<?php
/**
 * WP Consent API integration.
 *
 * @link https://github.com/WordPress/wp-consent-level-api
 * @link https://wordpress.org/plugins/wp-consent-api/
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Declares this plugin as the site's consent manager to the WP Consent API.
 *
 * Plugins that set cookies can then ask the API whether they are allowed to,
 * instead of each one shipping a banner of its own.
 */
class Piensa_Cookie_Consent_Consent_API {

	/**
	 * Maps this plugin's categories onto the API's consent levels.
	 *
	 * @var array<string, string>
	 */
	private static $category_map = [
		'necessary'   => 'functional',
		'functional'  => 'preferences',
		'preferences' => 'preferences',
		'analytics'   => 'statistics',
		'statistics'  => 'statistics',
		'marketing'   => 'marketing',
	];

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wp_consent_api_registered_' . PIENSA_COOKIE_CONSENT_BASENAME, '__return_true' );
		add_filter( 'wp_get_consent_type', [ $this, 'get_consent_type' ] );
		add_filter( 'wp_has_consent', [ $this, 'filter_has_consent' ], 10, 2 );
	}

	/**
	 * Report the consent model in use.
	 *
	 * The banner blocks scripts until the visitor accepts, which is opt-in.
	 *
	 * @return string
	 */
	public function get_consent_type() {
		return 'optin';
	}

	/**
	 * Answer a consent question asked through the API.
	 *
	 * @param bool   $has_consent Whether consent was granted, as resolved so far.
	 * @param string $category    Consent category being asked about.
	 *
	 * @return bool
	 */
	public function filter_has_consent( $has_consent, $category ) {
		$category = sanitize_key( (string) $category );

		// Which of this plugin's categories map onto the level being asked about.
		$ours = array_keys( self::$category_map, $category, true );

		if ( empty( $ours ) ) {
			// A level this plugin does not manage: answer only if the name
			// happens to match one of our own categories, otherwise defer.
			return Piensa_Cookie_Consent_Consent::has_consent( $category ) ? true : (bool) $has_consent;
		}

		foreach ( $ours as $our_category ) {
			if ( Piensa_Cookie_Consent_Consent::has_consent( $our_category ) ) {
				return true;
			}
		}

		return false;
	}
}
