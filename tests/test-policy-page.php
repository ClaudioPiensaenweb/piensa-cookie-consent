<?php
/**
 * Tests for placing the cookie declaration on the policy page by itself.
 *
 * Found on a Kit Digital review: the policy page had been written by hand for
 * CookieYes, defined no cookie by origin, and nobody had pasted the shortcode.
 * The page is built with Bricks, whose text element never passes through
 * `the_content`, so the declaration is placed in the finished HTML.
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

require_once __DIR__ . '/../includes/class-policy-page.php';

return function ( $assert ) {
	$policy = '<div class="ag-cookie-policy">DECLARATION</div>';

	// The emedemuro.es page, as Bricks serves it.
	$bricks = '<h1 id="brxe-eturfz" class="brxe-heading titulo-1">Política de cookies</h1>'
		. '<div id="brxe-yjwkil" class="brxe-text"><h3>¿Qué son las cookies?</h3><p>Una cookie es un fichero.<br><a class="cky-banner-element">Configurar consentimiento</a></p>'
		. '<h3>¿Qué tipos de cookies utiliza esta página web?</h3></div>';

	$result = Piensa_Cookie_Consent_Policy_Page::insert_policy( $bricks, $policy );
	$assert(
		false !== strpos( $result, 'Configurar consentimiento</a></p>' . $policy . '<h3>¿Qué tipos' ),
		'The declaration goes right after the CookieYes link, closing its paragraph'
	);
	$assert( substr_count( $result, 'DECLARATION' ) === 1, 'The declaration is inserted once' );

	$no_link = '<h1 class="title">Política de cookies</h1><div class="text"><p>Texto.</p></div>';
	$assert(
		Piensa_Cookie_Consent_Policy_Page::insert_policy( $no_link, $policy ) === '<h1 class="title">Política de cookies</h1>' . $policy . '<div class="text"><p>Texto.</p></div>',
		'Without the link, the declaration goes between the heading and the text'
	);

	$assert(
		Piensa_Cookie_Consent_Policy_Page::insert_policy( '<p>Sin título ni enlace.</p>', $policy ) === null,
		'With neither, the page is left alone'
	);
	$assert(
		Piensa_Cookie_Consent_Policy_Page::insert_policy( $bricks, '' ) === null,
		'An empty declaration inserts nothing'
	);

	$assert( Piensa_Cookie_Consent_Policy_Page::has_policy( $bricks . $policy ), 'A page with the rendered declaration has it' );
	$assert( Piensa_Cookie_Consent_Policy_Page::has_policy( '<p>[piensa_cookie_consent_policy]</p>' ), 'A page with the raw shortcode has it' );
	$assert( ! Piensa_Cookie_Consent_Policy_Page::has_policy( $bricks ), 'The hand-written page does not' );

	$assert(
		Piensa_Cookie_Consent_Policy_Page::is_policy_request( 'https://emedemuro.es/politica-cookies/', '/politica-cookies/' ),
		'The policy URL matches its request'
	);
	$assert(
		Piensa_Cookie_Consent_Policy_Page::is_policy_request( 'https://emedemuro.es/politica-cookies/', '/politica-cookies?pcc_certificate=1' ),
		'A query string and a missing slash still match'
	);
	$assert(
		! Piensa_Cookie_Consent_Policy_Page::is_policy_request( 'https://emedemuro.es/politica-cookies/', '/politica-privacidad/' ),
		'Another page does not match'
	);
	$assert(
		! Piensa_Cookie_Consent_Policy_Page::is_policy_request( 'https://emedemuro.es/', '/' ),
		'A policy URL pointing at the home page never matches'
	);
};
