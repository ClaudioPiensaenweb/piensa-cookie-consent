<?php
/**
 * Tests for the WooCommerce integration.
 *
 * Found on a live shop: WooCommerce's order attribution wrote seven `sbjs_*`
 * cookies on the first page view, before the banner had been answered, and
 * kept them after the visitor clicked Reject. A Kit Digital review rejected
 * the site for it. They come from the site's own domain, so nothing that
 * blocks by third-party host could have caught them.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

$GLOBALS['pcc_test_show_cmp'] = true;
$GLOBALS['pcc_test_options']  = [];
$GLOBALS['pcc_test_plugins']  = [];

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

if ( ! class_exists( 'WooCommerce' ) ) {
	/**
	 * Stand-in so the integration sees WooCommerce as active.
	 */
	class WooCommerce {} // phpcs:ignore
}

if ( ! class_exists( 'Piensa_Cookie_Consent_Geo' ) ) {
	/**
	 * Stub: whether the banner is shown is set by the test.
	 */
	class Piensa_Cookie_Consent_Geo { // phpcs:ignore
		/**
		 * @param array $settings Settings.
		 * @return bool
		 */
		public static function should_show_cmp( $settings ) { // phpcs:ignore
			return $GLOBALS['pcc_test_show_cmp'];
		}
	}
}

if ( ! class_exists( 'Piensa_Cookie_Consent_Admin' ) ) {
	/**
	 * Stub.
	 */
	class Piensa_Cookie_Consent_Admin { // phpcs:ignore
		/**
		 * @return array
		 */
		public static function get_settings() {
			return [];
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) { // phpcs:ignore
		return $text;
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string|false
	 */
	function wp_json_encode( $value ) { // phpcs:ignore
		return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

if ( ! function_exists( 'home_url' ) ) {
	/**
	 * @return string
	 */
	function home_url() { // phpcs:ignore
		return 'https://shop.example';
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * @param string $url       URL.
	 * @param int    $component Component.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) { // phpcs:ignore
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $name     Option.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	function get_option( $name, $fallback = false ) { // phpcs:ignore
		return array_key_exists( $name, $GLOBALS['pcc_test_options'] ) ? $GLOBALS['pcc_test_options'][ $name ] : $fallback;
	}
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	/**
	 * @param string $plugin Plugin basename.
	 * @return bool
	 */
	function is_plugin_active( $plugin ) { // phpcs:ignore
		return in_array( $plugin, $GLOBALS['pcc_test_plugins'], true );
	}
}

require_once dirname( __DIR__ ) . '/includes/class-woocommerce.php';

return function ( $assert ) {
	$integration = new Piensa_Cookie_Consent_WooCommerce();

	$empty = [
		'necessary' => [ 'cookies' => [] ],
		'analytics' => [ 'cookies' => [] ],
		'marketing' => [ 'cookies' => [] ],
	];

	$names = static function ( $cookies ) {
		return array_map(
			static function ( $cookie ) {
				return $cookie['name'];
			},
			$cookies
		);
	};

	// Every page starts with attribution off while the banner is in charge.
	$GLOBALS['pcc_test_show_cmp'] = true;
	$assert( false === $integration->filter_allow_tracking( true ), 'attribution starts off when the banner manages consent' );

	// Where no banner is shown nothing would ever turn it back on, so
	// WooCommerce keeps its own answer.
	$GLOBALS['pcc_test_show_cmp'] = false;
	$assert( true === $integration->filter_allow_tracking( true ), 'attribution is left alone where no banner is shown' );
	$assert( false === $integration->filter_allow_tracking( false ), 'an attribution already off stays off' );
	$GLOBALS['pcc_test_show_cmp'] = true;

	// The browser half listens to both events and asks for marketing.
	$script = Piensa_Cookie_Consent_WooCommerce::get_bridge_script();
	$assert( false !== strpos( $script, 'cc:onConsent' ), 'the bridge reacts to consent read on each page' );
	$assert( false !== strpos( $script, 'cc:onChange' ), 'the bridge reacts to consent being changed' );
	$assert( false !== strpos( $script, 'setOrderTracking' ), 'the bridge uses WooCommerce\'s own switch' );
	$assert( false !== strpos( $script, '"marketing"' ), 'the bridge asks for the marketing category' );

	// The attribution cookies are declared, as marketing, all seven of them.
	$GLOBALS['pcc_test_options'] = [];
	$declared                    = $integration->declare_cookies( $empty );
	$marketing                   = $names( $declared['marketing']['cookies'] );

	foreach ( Piensa_Cookie_Consent_WooCommerce::ATTRIBUTION_COOKIES as $cookie ) {
		$assert( in_array( $cookie, $marketing, true ), 'declared as marketing: ' . $cookie );
	}

	$assert( 7 === count( $marketing ), 'exactly the seven attribution cookies are marketing' );
	$assert( [] === $declared['analytics']['cookies'], 'nothing is added to analytics' );

	// Every declared cookie carries what the policy table prints.
	foreach ( array_merge( $declared['necessary']['cookies'], $declared['marketing']['cookies'] ) as $cookie ) {
		$complete = ! empty( $cookie['name'] ) && ! empty( $cookie['domain'] ) && ! empty( $cookie['description'] ) && ! empty( $cookie['duration'] );
		$assert( $complete, 'name, domain, purpose and duration are filled in: ' . $cookie['name'] );
	}

	$assert( 'shop.example' === $declared['marketing']['cookies'][0]['domain'], 'declared on the site\'s own domain' );

	// The session cookie is a pattern, or withdrawing consent could not
	// match it and the table would show a literal asterisk as a name.
	$session = array_values(
		array_filter(
			$declared['necessary']['cookies'],
			static function ( $cookie ) {
				return 'wp_woocommerce_session_*' === $cookie['name'];
			}
		)
	);
	$assert( ! empty( $session[0]['is_pattern'] ), 'the session cookie is declared as a pattern' );

	// With attribution switched off in WooCommerce there is nothing to declare.
	$GLOBALS['pcc_test_options'] = [ 'woocommerce_feature_order_attribution_enabled' => 'no' ];
	$declared                    = $integration->declare_cookies( $empty );
	$assert( [] === $declared['marketing']['cookies'], 'no attribution cookies are declared when the feature is off' );
	$assert( ! empty( $declared['necessary']['cookies'] ), 'the cart cookies are still declared' );

	// Stripe's cookies only when the Stripe gateway is there.
	$GLOBALS['pcc_test_options'] = [];
	$assert( ! in_array( '__stripe_mid', $names( $integration->declare_cookies( $empty )['necessary']['cookies'] ), true ), 'no Stripe cookies without the gateway' );

	$GLOBALS['pcc_test_plugins'] = [ 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' ];
	$necessary                   = $names( $integration->declare_cookies( $empty )['necessary']['cookies'] );
	$assert( in_array( '__stripe_mid', $necessary, true ) && in_array( '__stripe_sid', $necessary, true ), 'the Stripe cookies are declared with the gateway' );
};
