<?php
/**
 * Tests for the consent log's handling of what an anonymous request sends.
 *
 * The endpoint is public and, since 1.13, has no nonce: a cached page kept an
 * expired one and every consent after that was lost. What it stores has to be
 * made safe here instead.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	/**
	 * @param mixed $data Data.
	 * @return string
	 */
	function wp_json_encode( $data ) { // phpcs:ignore
		return json_encode( $data ); // phpcs:ignore
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function sanitize_text_field( $text ) { // phpcs:ignore
		return trim( strip_tags( (string) $text ) ); // phpcs:ignore
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) { // phpcs:ignore
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

require_once __DIR__ . '/../includes/class-consent-log.php';

return function ( $assert ) {
	$assert(
		Piensa_Cookie_Consent_Consent_Log::allowed_categories( '["necessary","analytics","<script>","marketing"]' ) === '["necessary","analytics","marketing"]',
		'Only the categories the site has are stored'
	);
	$assert( Piensa_Cookie_Consent_Consent_Log::allowed_categories( 'not json' ) === '[]', 'A malformed list stores nothing' );

	$server = $_SERVER;

	$_SERVER = [ 'REMOTE_ADDR' => '203.0.113.5' ];
	$assert( Piensa_Cookie_Consent_Consent_Log::client_ip() === '203.0.113.5', 'Without a proxy, the connecting address' );

	$_SERVER = [
		'REMOTE_ADDR'           => '172.70.1.1',
		'HTTP_CF_CONNECTING_IP' => '198.51.100.7',
	];
	$assert( Piensa_Cookie_Consent_Consent_Log::client_ip() === '198.51.100.7', 'Behind Cloudflare, the visitor\'s address' );

	$_SERVER = [
		'REMOTE_ADDR'          => '10.0.0.1',
		'HTTP_X_FORWARDED_FOR' => '198.51.100.9, 10.0.0.1',
	];
	$assert( Piensa_Cookie_Consent_Consent_Log::client_ip() === '198.51.100.9', 'Behind a proxy, the first forwarded address' );

	$_SERVER = [
		'REMOTE_ADDR'          => '203.0.113.5',
		'HTTP_X_FORWARDED_FOR' => 'not-an-ip',
	];
	$assert( Piensa_Cookie_Consent_Consent_Log::client_ip() === '203.0.113.5', 'A forwarded value that is not an address is ignored' );

	$_SERVER = $server;
};
