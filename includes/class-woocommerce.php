<?php
/**
 * WooCommerce integration.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Holds WooCommerce's order attribution back until the visitor consents, and
 * declares the cookies WooCommerce sets.
 *
 * Order attribution (WooCommerce 8.5+, on by default) loads Sourcebuster.js,
 * which writes seven `sbjs_*` cookies on the first page view: where the visit
 * came from, the campaign, the browser and how many pages have been seen. They
 * are served from the site's own domain, so the blocker, which works by third
 * party host, never touched them. Found on a live shop when a Kit Digital
 * review rejected the site for setting non-technical cookies before consent —
 * and they stayed after the visitor clicked Reject, too.
 *
 * WooCommerce has its own switch for this: the `wc_order_attribution_allow_tracking`
 * filter, and `wc_order_attribution.setOrderTracking()` in the browser, which
 * starts Sourcebuster when given true and deletes every `sbjs_*` cookie when
 * given false. WooCommerce only wires them to consent when the separate WP
 * Consent API plugin is installed, so this does it directly.
 */
class Piensa_Cookie_Consent_WooCommerce {

	/**
	 * Consent category the attribution cookies belong to.
	 *
	 * Marketing, the same level WooCommerce itself asks for through the WP
	 * Consent API: the cookies record which campaign brought the visitor.
	 */
	const CATEGORY = 'marketing';

	/**
	 * Cookies Sourcebuster.js writes, as listed in WooCommerce's own clean-up.
	 */
	const ATTRIBUTION_COOKIES = [
		'sbjs_migrations',
		'sbjs_current_add',
		'sbjs_first_add',
		'sbjs_current',
		'sbjs_first',
		'sbjs_udata',
		'sbjs_session',
	];

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wc_order_attribution_allow_tracking', [ $this, 'filter_allow_tracking' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_consent_bridge' ], 100 );
		add_filter( 'piensa_cookie_consent_cookie_definitions', [ $this, 'declare_cookies' ] );
	}

	/**
	 * Start every page with attribution off.
	 *
	 * The same answer for every visitor, so the page stays safe to cache: the
	 * browser turns attribution on from the visitor's own consent cookie as
	 * soon as the banner script has read it.
	 *
	 * @param bool $allow Whether WooCommerce would track.
	 *
	 * @return bool
	 */
	public function filter_allow_tracking( $allow ) {
		if ( ! self::manages_consent() ) {
			return $allow;
		}

		return false;
	}

	/**
	 * Turn attribution on or off whenever consent is read or changes.
	 *
	 * `cc:onConsent` fires on every page view once a choice exists and
	 * `cc:onChange` when the visitor changes it. Printed right after
	 * WooCommerce's script, so the listeners are in place before the banner
	 * script, which is deferred, raises either event.
	 *
	 * @return void
	 */
	public function enqueue_consent_bridge() {
		if ( ! self::manages_consent() || ! wp_script_is( 'wc-order-attribution', 'registered' ) ) {
			return;
		}

		wp_add_inline_script( 'wc-order-attribution', self::get_bridge_script(), 'after' );
	}

	/**
	 * The browser half of the integration.
	 *
	 * @return string
	 */
	public static function get_bridge_script() {
		return '(function(){'
			. 'var category=' . wp_json_encode( self::CATEGORY ) . ';'
			. 'function apply(event){'
			. 'var attribution=window.wc_order_attribution;'
			. 'if(!attribution||typeof attribution.setOrderTracking!=="function"){return;}'
			. 'var allowed=false;'
			. 'if(window.CookieConsent&&typeof window.CookieConsent.acceptedCategory==="function"){'
			. 'allowed=window.CookieConsent.acceptedCategory(category);'
			. '}else{'
			. 'var cookie=event&&event.detail&&event.detail.cookie;'
			. 'allowed=!!(cookie&&cookie.categories&&cookie.categories.indexOf(category)!==-1);'
			. '}'
			. 'attribution.setOrderTracking(allowed);'
			. '}'
			. 'window.addEventListener("cc:onConsent",apply);'
			. 'window.addEventListener("cc:onChange",apply);'
			. '})();';
	}

	/**
	 * Add WooCommerce's cookies to the declaration the visitor reads.
	 *
	 * @param array<string, array<string, mixed>> $definitions Cookie definitions by category.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function declare_cookies( $definitions ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return $definitions;
		}

		$domain = self::get_cookie_domain();

		$necessary = [
			[
				'name'        => 'woocommerce_cart_hash',
				'description' => __( 'WooCommerce: detects changes to the cart contents.', 'piensa-cookie-consent' ),
				'duration'    => __( 'Session', 'piensa-cookie-consent' ),
			],
			[
				'name'        => 'woocommerce_items_in_cart',
				'description' => __( 'WooCommerce: records whether the cart has products.', 'piensa-cookie-consent' ),
				'duration'    => __( 'Session', 'piensa-cookie-consent' ),
			],
			[
				'name'        => 'wp_woocommerce_session_*',
				'is_pattern'  => true,
				'description' => __( 'WooCommerce: keeps the cart and the checkout of each visitor.', 'piensa-cookie-consent' ),
				'duration'    => __( '2 days', 'piensa-cookie-consent' ),
			],
		];

		if ( self::stripe_active() ) {
			$necessary[] = [
				'name'        => '__stripe_mid',
				'description' => __( 'Stripe: fraud prevention when paying by card.', 'piensa-cookie-consent' ),
				'duration'    => __( '1 year', 'piensa-cookie-consent' ),
			];
			$necessary[] = [
				'name'        => '__stripe_sid',
				'description' => __( 'Stripe: fraud prevention when paying by card.', 'piensa-cookie-consent' ),
				'duration'    => __( '30 minutes', 'piensa-cookie-consent' ),
			];
		}

		foreach ( $necessary as $cookie ) {
			$definitions['necessary']['cookies'][] = $cookie + [ 'domain' => $domain ];
		}

		if ( self::attribution_enabled() && isset( $definitions[ self::CATEGORY ] ) ) {
			foreach ( self::ATTRIBUTION_COOKIES as $name ) {
				$definitions[ self::CATEGORY ]['cookies'][] = [
					'name'        => $name,
					'domain'      => $domain,
					'description' => 'sbjs_session' === $name
						? __( 'WooCommerce order attribution: marks the current visit.', 'piensa-cookie-consent' )
						: __( 'WooCommerce order attribution: records which source or campaign brought the visit, to attribute the order.', 'piensa-cookie-consent' ),
					'duration'    => 'sbjs_session' === $name
						? __( '30 minutes', 'piensa-cookie-consent' )
						: __( 'Session', 'piensa-cookie-consent' ),
				];
			}
		}

		return $definitions;
	}

	/**
	 * Whether this plugin is managing consent for the current request.
	 *
	 * Where no banner is shown, no consent is ever recorded and the browser
	 * half would never turn attribution back on, so WooCommerce is left alone.
	 *
	 * @return bool
	 */
	private static function manages_consent() {
		return Piensa_Cookie_Consent_Geo::should_show_cmp( Piensa_Cookie_Consent_Admin::get_settings() );
	}

	/**
	 * Whether WooCommerce's order attribution feature is switched on.
	 *
	 * @return bool
	 */
	private static function attribution_enabled() {
		return 'no' !== get_option( 'woocommerce_feature_order_attribution_enabled', 'yes' );
	}

	/**
	 * Whether the WooCommerce Stripe gateway is active.
	 *
	 * @return bool
	 */
	private static function stripe_active() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' );
	}

	/**
	 * Domain the site's own cookies are set on.
	 *
	 * @return string
	 */
	private static function get_cookie_domain() {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return is_string( $host ) ? $host : '';
	}
}
