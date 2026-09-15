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
		$settings = Piensa_Cookie_Consent_Admin::get_settings();

		// No banner in this region means no consent is being managed, so there
		// is no default state to declare.
		if ( ! Piensa_Cookie_Consent_Geo::should_show_cmp( $settings ) ) {
			return;
		}

		// Always denied, which is both what Google documents for the default
		// call and what keeps the markup identical for every visitor. Reading
		// the consent cookie here would vary the page and make it unsafe to
		// cache: a page cache would serve one visitor's granted state to
		// everyone. The front-end script issues the 'update' call from the
		// visitor's own cookie as soon as it runs.
		$defaults = [
			'analytics_storage'  => 'denied',
			'ad_storage'         => 'denied',
			'ad_user_data'       => 'denied',
			'ad_personalization' => 'denied',
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

}
