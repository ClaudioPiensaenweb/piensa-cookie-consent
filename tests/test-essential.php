<?php
/**
 * Tests for the resources the blocker must never neutralise.
 *
 * Found on a live shop: `js.stripe.com/dahlia/stripe.js` was being served as
 * `type="text/plain"` on the checkout, the cart and every product page. Stripe's
 * script is what draws the card fields, so the shop could not take a payment
 * from anybody who had not accepted marketing cookies — and nothing about that
 * looks like an error. The page renders, the banner behaves, and the orders
 * simply do not arrive.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
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

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) { // phpcs:ignore
		unset( $hook );
		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = null ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}

require_once __DIR__ . '/../includes/class-scanner.php';

return function ( $assert ) {
	$essential = 'Piensa_Cookie_Consent_Scanner::is_essential_url';

	// The exact URL that was being blocked on a live checkout.
	$assert(
		$essential( 'https://js.stripe.com/dahlia/stripe.js' ),
		'the Stripe script from the live shop is essential'
	);

	// The rest of a payment flow.
	foreach (
		[
			'https://m.stripe.network/inner.html',
			'https://api.stripe.com/v1/tokens',
			'https://www.paypal.com/sdk/js?client-id=x',
			'https://www.paypalobjects.com/api/checkout.js',
			'https://sis.redsys.es/sis/realizarPago',
			'https://checkoutshopper-live.adyen.com/checkoutshopper/sdk/x/adyen.js',
			'https://js.braintreegateway.com/web/3.0/js/client.js',
			'https://x.klarnacdn.net/kp/lib/v1/api.js',
			'https://js.mollie.com/v1/mollie.js',
			'https://web.squarecdn.com/v1/square.js',
			'https://pay.google.com/gp/p/js/pay.js',
			'https://applepay.cdn-apple.com/jsapi/v1/apple-pay-sdk.js',
		] as $url
	) {
		$assert( $essential( $url ), 'payment resource is essential: ' . $url );
	}

	// Fraud and bot checks, exempt on the same grounds. reCAPTCHA has to be
	// recognised by its path: it is served from the same hosts as Google Maps,
	// so categorising the host as marketing took the CAPTCHA on every contact
	// form down with the maps.
	foreach (
		[
			'https://www.google.com/recaptcha/api.js',
			'https://www.gstatic.com/recaptcha/releases/abc/recaptcha__es.js',
			'https://challenges.cloudflare.com/turnstile/v0/api.js',
			'https://js.hcaptcha.com/1/api.js',
		] as $url
	) {
		$assert( $essential( $url ), 'fraud check is essential: ' . $url );
	}

	// Protocol-relative URLs are still written by plenty of themes.
	$assert( $essential( '//js.stripe.com/v3/' ), 'a protocol-relative payment URL is recognised' );

	// Case is not a way around it.
	$assert( $essential( 'https://JS.Stripe.COM/v3/' ), 'the match is case-insensitive' );

	// What must NOT get a free pass. The match is on a dot boundary, so a host
	// that merely ends in the same letters is still a stranger.
	foreach (
		[
			'https://www.googletagmanager.com/gtag/js?id=G-1',
			'https://connect.facebook.net/en_US/fbevents.js',
			'https://www.youtube.com/embed/abc',
			'https://maps.google.com/maps',
			'https://notstripe.com/pay.js',
			'https://evil-stripe.com/pay.js',
			'https://stripe.com.attacker.test/pay.js',
			'https://hcaptcha.com.attacker.test/a.js',
		] as $url
	) {
		$assert( ! $essential( $url ), 'not essential: ' . $url );
	}

	// Nothing sensible to say about nothing.
	$assert( ! $essential( '' ), 'an empty URL is not essential' );
	$assert( ! $essential( null ), 'a null URL is not essential' );
	$assert( ! $essential( 'data:image/gif;base64,R0lGOD' ), 'a data URI is not essential' );

	// The map has to agree with the list, or the scanner would show a payment
	// gateway as an unrecognised third party in the admin table while the
	// blocker let it through.
	$map = Piensa_Cookie_Consent_Scanner::get_domain_category_map();

	$assert( isset( $map['necessary'] ), 'the category map has a necessary category' );
	$assert(
		isset( $map['necessary'] ) && in_array( 'stripe.com', $map['necessary'], true ),
		'the map lists the payment hosts as necessary'
	);
};
