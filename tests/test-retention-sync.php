<?php
/**
 * Tests for the Cookiedatabase.org retention sync.
 *
 * Nothing here can go blank in front of a visitor and nothing here can turn
 * into an unbounded network call on the front end: a lookup runs once a day
 * from cron, and every failure mode — no network, a bad response, a cookie
 * the database has never heard of — must leave the plugin's own hardcoded
 * duration standing rather than showing nothing.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

$GLOBALS['piensa_test_retention'] = [
	'options'   => [],
	'requested' => [],
	'response'  => null,
	'filters'   => [],
];

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Option name.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) { // phpcs:ignore
		return array_key_exists( $key, $GLOBALS['piensa_test_retention']['options'] )
			? $GLOBALS['piensa_test_retention']['options'][ $key ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key      Option name.
	 * @param mixed  $value    Value.
	 * @param mixed  $autoload Ignored.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) { // phpcs:ignore
		unset( $autoload );
		$GLOBALS['piensa_test_retention']['options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * @param string $hook  Hook.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) { // phpcs:ignore
		if ( array_key_exists( $hook, $GLOBALS['piensa_test_retention']['filters'] ) ) {
			return $GLOBALS['piensa_test_retention']['filters'][ $hook ];
		}

		return $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	/**
	 * @param string $hook     Hook.
	 * @param mixed  $callback Callback.
	 * @return void
	 */
	function add_action( $hook, $callback ) { // phpcs:ignore
		unset( $hook, $callback );
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * @param string $hook Hook.
	 * @return bool
	 */
	function wp_next_scheduled( $hook ) { // phpcs:ignore
		unset( $hook );
		return false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	/**
	 * @param int    $timestamp Timestamp.
	 * @param string $recurrence Recurrence.
	 * @param string $hook Hook.
	 * @return void
	 */
	function wp_schedule_event( $timestamp, $recurrence, $hook ) { // phpcs:ignore
		unset( $timestamp, $recurrence, $hook );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return string
	 */
	function wp_json_encode( $value ) { // phpcs:ignore
		return json_encode( $value ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * @param string $url  Address.
	 * @param array  $args Request arguments.
	 * @return array|WP_Error
	 */
	function wp_remote_post( $url, $args = [] ) { // phpcs:ignore
		$GLOBALS['piensa_test_retention']['requested'][] = [
			'url'  => $url,
			'body' => isset( $args['body'] ) ? $args['body'] : '',
		];

		return $GLOBALS['piensa_test_retention']['response'];
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value.
	 * @return bool
	 */
	function is_wp_error( $thing ) { // phpcs:ignore
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal stand-in for a failed HTTP request.
	 */
	class WP_Error {}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	/**
	 * @param array $response Response.
	 * @return int
	 */
	function wp_remote_retrieve_response_code( $response ) { // phpcs:ignore
		return isset( $response['code'] ) ? $response['code'] : 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	/**
	 * @param array $response Response.
	 * @return string
	 */
	function wp_remote_retrieve_body( $response ) { // phpcs:ignore
		return isset( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $value Value.
	 * @return string
	 */
	function sanitize_text_field( $value ) { // phpcs:ignore
		return trim( (string) $value );
	}
}

require_once __DIR__ . '/../includes/class-retention-sync.php';

/**
 * Reset the stubbed environment between scenarios.
 *
 * @param array|WP_Error|null $response What wp_remote_post() returns next.
 *
 * @return void
 */
function piensa_test_reset_retention( $response ) {
	$GLOBALS['piensa_test_retention'] = [
		'options'   => [],
		'requested' => [],
		'response'  => $response,
		'filters'   => [],
	];
}

return function ( $assert ) {
	$sync = 'Piensa_Cookie_Consent_Retention_Sync';

	// The exact response shape confirmed against the live API for these four
	// cookies.
	$live_response = [
		'code' => 200,
		'body' => json_encode(
			[
				'data' => [
					'en' => [
						'_ga'  => [ 'retention' => '2 years' ],
						'_gid' => [ 'retention' => '1 day' ],
						// _fbp and fr are sent but the database has nothing on
						// them, so they are simply absent from the response —
						// not present with an empty value.
					],
				],
			]
		),
	];

	piensa_test_reset_retention( $live_response );
	$sync::sync();

	$assert( 1 === count( $GLOBALS['piensa_test_retention']['requested'] ), 'a sync makes one request' );
	$assert(
		'2 years' === $sync::get_retention( '_ga', 'unused fallback' ),
		'a cookie the database answered for is updated'
	);
	$assert(
		'1 day' === $sync::get_retention( '_gid', 'unused fallback' ),
		'the exact wording the database returns is kept, even if it differs from ours'
	);
	$assert(
		'Not declared by the provider' === $sync::get_retention( '_fbp', 'Not declared by the provider' ),
		'a cookie the database has nothing on falls back to our own text'
	);
	$assert( $sync::last_synced() > 0, 'a successful sync is timestamped' );

	// A network failure must not erase what the last successful sync learned.
	piensa_test_reset_retention( null );
	$GLOBALS['piensa_test_retention']['response'] = new WP_Error();
	// Re-seed the cache as if the earlier sync had already run, since resetting
	// wiped the stubbed options store along with the stubbed request log.
	update_option( $sync::OPTION, [ '_ga' => [ 'retention' => '2 years', 'synced_at' => 1 ] ], false );

	$sync::sync();

	$assert(
		'2 years' === $sync::get_retention( '_ga', 'unused fallback' ),
		'a network failure leaves the previous cache in place'
	);

	// A malformed response is treated the same way: ignored, not emptied.
	piensa_test_reset_retention( [ 'code' => 200, 'body' => 'not json' ] );
	update_option( $sync::OPTION, [ '_ga' => [ 'retention' => '2 years', 'synced_at' => 1 ] ], false );
	$sync::sync();
	$assert( '2 years' === $sync::get_retention( '_ga', 'unused fallback' ), 'a malformed response leaves the cache in place' );

	// A non-2xx status is ignored outright.
	piensa_test_reset_retention( [ 'code' => 500, 'body' => '' ] );
	$sync::sync();
	$assert( [] === get_option( $sync::OPTION, [] ), 'a server error writes nothing' );

	// An uncalled cookie name has no cached entry and returns exactly the
	// fallback handed to it, whatever that is.
	piensa_test_reset_retention( [ 'code' => 200, 'body' => '{"data":{"en":{}}}' ] );
	$assert(
		'cualquier texto' === $sync::get_retention( 'never_synced_cookie', 'cualquier texto' ),
		'an unknown cookie name returns the caller\'s own fallback untouched'
	);

	// On by default.
	piensa_test_reset_retention( $live_response );
	$assert( true === $sync::is_enabled(), 'the sync is on by default' );

	// The filter switches the whole thing off, and sync() makes no request at
	// all rather than making one and discarding the answer — a site that
	// opted out should generate no outbound traffic to cookiedatabase.org.
	$GLOBALS['piensa_test_retention']['filters']['piensa_cookie_consent_sync_retention'] = false;

	$assert( false === $sync::is_enabled(), 'the filter is honoured' );

	$sync::sync();

	$assert( [] === $GLOBALS['piensa_test_retention']['requested'], 'a disabled sync makes no request' );
};
