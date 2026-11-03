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

		// The defaults are always denied and the HTML is the same for every
		// visitor, so pages stay safe to cache. The script reads the visitor's
		// own consent cookie in the browser and issues the update from there.
		// It carries the piensaCookieConsentMode marker, without which the
		// blocker would neutralise it for containing a gtag() call.
		$config = [
			'revision'       => isset( $settings['policy_revision'] ) ? (int) $settings['policy_revision'] : 0,
			'urlPassthrough' => ! empty( $settings['url_passthrough'] ),
			'rules'          => self::client_rules( $settings ),
		];

		$file   = PIENSA_COOKIE_CONSENT_PATH . 'assets/js/piensa-cookie-consent-head.min.js';
		$script = 'window.piensaCookieConsentHead=' . wp_json_encode( $config ) . ';';
		// Inline rather than enqueued: it has to run before every other
		// script on the page, which no enqueue can guarantee.
		$script .= is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions

		// Emits the tag with whatever attributes the site's filters require,
		// which a hand-built <script> element would ignore.
		wp_print_inline_script_tag( $script );
	}

	/**
	 * The blocker's rules, for the guard in the browser.
	 *
	 * The same lists the server-side blocker decides with, so a script added
	 * after load is held back exactly when it would have been in the HTML.
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function client_rules( $settings ) {
		$lines = function ( $raw ) {
			return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', is_string( $raw ) ? $raw : '' ) ) ) );
		};

		$map     = Piensa_Cookie_Consent_Scanner::get_domain_category_map();
		$allowed = $lines( isset( $settings['allowed_domains'] ) ? $settings['allowed_domains'] : '' );
		$rules   = [
			'site'           => [ (string) wp_parse_url( home_url(), PHP_URL_HOST ) ],
			'essential'      => Piensa_Cookie_Consent_Scanner::get_essential_hosts(),
			'essentialPaths' => Piensa_Cookie_Consent_Scanner::get_essential_paths(),
			'allowed'        => $allowed,
			'blocked'        => $lines( isset( $settings['blocked_domains'] ) ? $settings['blocked_domains'] : '' ),
			'analytics'      => isset( $map['analytics'] ) ? array_values( $map['analytics'] ) : [],
			'marketing'      => isset( $map['marketing'] ) ? array_values( $map['marketing'] ) : [],
			'enabled'        => ! empty( $settings['enable_blocker'] ),
			'blockUnknown'   => ! empty( $settings['block_unknown_third_party'] ),
		];

		$overrides = isset( $settings['domain_overrides'] ) && is_array( $settings['domain_overrides'] ) ? $settings['domain_overrides'] : [];
		foreach ( $overrides as $host => $category ) {
			if ( $category === 'necessary' ) {
				$rules['allowed'][] = (string) $host;
			} elseif ( $category === 'analytics' || $category === 'marketing' ) {
				array_unshift( $rules[ $category ], (string) $host );
			}
		}

		return $rules;
	}
}
