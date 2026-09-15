<?php
/**
 * Google Consent Mode v2 signalling.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Emits the Consent Mode default state before any Google tag can run.
 *
 * Consent Mode only works if the default state is set before the tags load, so
 * this prints directly into `wp_head` at the earliest priority rather than
 * going through the enqueue system, which would place it too late.
 */
class Piensa_Cookie_Consent_Consent_Mode {

	/**
	 * Scanner, used to find out which categories are active.
	 *
	 * @var Piensa_Cookie_Consent_Scanner
	 */
	private $scanner;

	/**
	 * Constructor.
	 *
	 * @param Piensa_Cookie_Consent_Scanner $scanner Scanner instance.
	 */
	public function __construct( $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_head', [ $this, 'inject_consent_mode' ], 1 );
	}

	/**
	 * Print the Consent Mode default state.
	 *
	 * @return void
	 */
	public function inject_consent_mode() {
		$categories    = $this->scanner->get_active_categories();
		$has_analytics = ! empty( $categories['analytics'] );
		$has_marketing = ! empty( $categories['marketing'] );

		$settings = Piensa_Cookie_Consent_Admin::get_settings();

		if ( ! Piensa_Cookie_Consent_Geo::should_show_cmp( $settings ) ) {
			// Outside the targeted region no banner is shown, so consent cannot
			// be asked for: whatever the site has enabled is what applies.
			$analytics_granted = $has_analytics;
			$marketing_granted = $has_marketing;
		} else {
			$consent           = $this->get_current_consent();
			$analytics_granted = $has_analytics && $consent['analytics'];
			$marketing_granted = $has_marketing && $consent['marketing'];
		}

		$analytics_value = $analytics_granted ? 'granted' : 'denied';
		$marketing_value = $marketing_granted ? 'granted' : 'denied';

		$defaults = [
			'analytics_storage'  => $analytics_value,
			'ad_storage'         => $marketing_value,
			'ad_user_data'       => $marketing_value,
			'ad_personalization' => $marketing_value,
		];

		$script  = 'window.dataLayer = window.dataLayer || [];';
		$script .= 'function gtag(){dataLayer.push(arguments);}';
		// wp_json_encode() produces the object literal, so no value is
		// interpolated into the script by hand.
		$script .= sprintf( "gtag('consent', 'default', %s);", wp_json_encode( $defaults ) );

		// Emits the tag with whatever attributes the site's filters require,
		// which a hand-built <script> element would ignore.
		wp_print_inline_script_tag( $script );
	}

	/**
	 * Read the visitor's consent for the categories Consent Mode cares about.
	 *
	 * @return array{analytics: bool, marketing: bool}
	 */
	private function get_current_consent() {
		return [
			'analytics' => Piensa_Cookie_Consent_Consent::has_consent( 'analytics' ),
			'marketing' => Piensa_Cookie_Consent_Consent::has_consent( 'marketing' ),
		];
	}
}
