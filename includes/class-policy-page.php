<?php
/**
 * Puts the cookie declaration on the cookie policy page by itself.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inserts the `[piensa_cookie_consent_policy]` output where it is missing.
 *
 * A Kit Digital review rejected a site whose policy page had been written by
 * hand: it described the cookies in general terms and never classified them
 * by origin. The plugin already renders the declaration the review asks for,
 * but only where someone pastes the shortcode, and on most sites nobody had.
 *
 * Page builders do not all run their text through `the_content` (Bricks does
 * not), so this works on the finished HTML instead. It places the declaration
 * right after a leftover CookieYes "Configurar consentimiento" link when there
 * is one, otherwise right after the page heading, and leaves the page alone
 * when it cannot find either.
 */
class Piensa_Cookie_Consent_Policy_Page {

	/**
	 * Marker the rendered declaration carries.
	 */
	const MARKER = 'ag-cookie-policy';

	/**
	 * Renders the declaration.
	 *
	 * @var callable
	 */
	private $render;

	/**
	 * @param callable $render Returns the declaration's HTML.
	 */
	public function __construct( $render ) {
		$this->render = $render;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() ) {
			add_action( 'template_redirect', [ $this, 'maybe_start_buffer' ], 1 );
		}
	}

	/**
	 * Buffer the response when it is the configured cookie policy page.
	 *
	 * @return void
	 */
	public function maybe_start_buffer() {
		$settings = Piensa_Cookie_Consent_Admin::get_settings();

		if ( empty( $settings['cookie_policy_url'] ) || is_feed() || is_customize_preview() ) {
			return;
		}

		// Builder canvases render the page for editing; inserting there would
		// let the editor save the generated markup into the page.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of which screen this is.
		if ( isset( $_GET['bricks'] ) || isset( $_GET['elementor-preview'] ) || isset( $_GET['et_fb'] ) || isset( $_GET['fl_builder'] ) ) {
			return;
		}

		$request = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( ! self::is_policy_request( $settings['cookie_policy_url'], $request ) ) {
			return;
		}

		ob_start( [ $this, 'process_html' ] );
	}

	/**
	 * Insert the declaration into a buffered response.
	 *
	 * @param string $html Buffered response.
	 *
	 * @return string
	 */
	public function process_html( $html ) {
		if ( ! is_string( $html ) || $html === '' || self::has_policy( $html ) ) {
			return $html;
		}

		$inserted = self::insert_policy( $html, (string) call_user_func( $this->render ) );

		return $inserted === null ? $html : $inserted;
	}

	/**
	 * Whether a request is for the page at the policy URL.
	 *
	 * Compared by path, so a query string or a missing trailing slash does not
	 * stop it from matching.
	 *
	 * @param string $policy_url  Configured cookie policy URL.
	 * @param string $request_uri Request URI.
	 *
	 * @return bool
	 */
	public static function is_policy_request( $policy_url, $request_uri ) {
		$policy_path  = wp_parse_url( (string) $policy_url, PHP_URL_PATH );
		$request_path = wp_parse_url( (string) $request_uri, PHP_URL_PATH );

		if ( ! is_string( $policy_path ) || ! is_string( $request_path ) ) {
			return false;
		}

		$policy_path  = '/' . trim( $policy_path, '/' );
		$request_path = '/' . trim( $request_path, '/' );

		return $policy_path !== '/' && strtolower( rawurldecode( $policy_path ) ) === strtolower( rawurldecode( $request_path ) );
	}

	/**
	 * Whether the page already carries the declaration.
	 *
	 * @param string $html Page HTML.
	 *
	 * @return bool
	 */
	public static function has_policy( $html ) {
		return false !== strpos( $html, 'class="' . self::MARKER . '"' )
			|| false !== strpos( $html, '[piensa_cookie_consent_policy]' );
	}

	/**
	 * Place the declaration in the page.
	 *
	 * After a CookieYes "Configurar consentimiento" link first, closing the
	 * paragraph that holds it, because that is where the old plugin's own
	 * declaration used to appear. Otherwise after the first `<h1>`, which on a
	 * policy page is its title, so the declaration sits between the heading
	 * and the text.
	 *
	 * @param string $html        Page HTML.
	 * @param string $policy_html Declaration HTML.
	 *
	 * @return string|null The page with the declaration, or null when there is
	 *                     nowhere sensible to put it.
	 */
	public static function insert_policy( $html, $policy_html ) {
		if ( $policy_html === '' ) {
			return null;
		}

		if ( preg_match( '#<a\b[^>]*class="[^"]*\bcky-banner-element\b[^"]*"[^>]*>.*?</a>(\s*</p>)?#is', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$end = $match[0][1] + strlen( $match[0][0] );

			return substr( $html, 0, $end ) . $policy_html . substr( $html, $end );
		}

		if ( preg_match( '#<h1\b[^>]*>.*?</h1>#is', $html, $match, PREG_OFFSET_CAPTURE ) ) {
			$end = $match[0][1] + strlen( $match[0][0] );

			return substr( $html, 0, $end ) . $policy_html . substr( $html, $end );
		}

		return null;
	}
}
