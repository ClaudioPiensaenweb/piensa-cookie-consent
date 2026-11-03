<?php
/**
 * Serves Google Fonts from the site itself.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replaces Google Fonts requests with local copies.
 *
 * Loading a stylesheet from fonts.googleapis.com sends every visitor's IP
 * address to Google before they have chosen anything; a German court fined a
 * site for exactly that in 2022, and emedemuro.es did it through Bricks.
 * Blocking the stylesheet until consent would leave the site in the wrong
 * typeface for everyone who refuses, so the fonts are downloaded once and
 * served from the uploads folder instead: the page looks the same and nothing
 * reaches Google.
 *
 * A stylesheet not yet cached is removed from the page and fetched in the
 * background; the page falls back to the theme's next font until then.
 */
class Piensa_Cookie_Consent_Fonts {

	/**
	 * Option mapping a Google Fonts URL hash to its local stylesheet URL.
	 */
	const OPTION = 'piensa_cookie_consent_fonts';

	/**
	 * Cron hook that downloads one stylesheet.
	 */
	const CRON = 'piensa_cookie_consent_cache_font';

	/**
	 * Hosts whose requests are replaced.
	 */
	const HOSTS = [ 'fonts.googleapis.com', 'fonts.gstatic.com' ];

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( self::CRON, [ __CLASS__, 'cache' ] );
	}

	/**
	 * Rewrite Google Fonts references in a page.
	 *
	 * @param string $html Page HTML.
	 *
	 * @return string
	 */
	public static function localize( $html ) {
		if ( false === strpos( $html, 'fonts.g' ) ) {
			return $html;
		}

		$cached = get_option( self::OPTION, [] );
		$cached = is_array( $cached ) ? $cached : [];
		$queue  = [];

		$result = self::rewrite(
			$html,
			function ( $url ) use ( $cached, &$queue ) {
				$key = md5( $url );
				if ( isset( $cached[ $key ] ) ) {
					return (string) $cached[ $key ];
				}
				$queue[ $key ] = $url;
				return null;
			}
		);

		foreach ( $queue as $url ) {
			if ( ! wp_next_scheduled( self::CRON, [ $url ] ) ) {
				wp_schedule_single_event( time(), self::CRON, [ $url ] );
			}
		}

		return $result;
	}

	/**
	 * Replace or remove every reference to Google Fonts.
	 *
	 * Pure, so it can be tested without WordPress.
	 *
	 * @param string   $html    Page HTML.
	 * @param callable $resolve Google Fonts URL => local URL, or null when
	 *                          there is no local copy yet.
	 *
	 * @return string
	 */
	public static function rewrite( $html, $resolve ) {
		$hosts = implode( '|', array_map( 'preg_quote', self::HOSTS ) );

		// Hints that open a connection to Google on their own.
		$html = (string) preg_replace( '#<link\b[^>]*\brel=["\']?(?:dns-prefetch|preconnect)["\']?[^>]*(?:' . $hosts . ')[^>]*>\s*#i', '', $html );
		$html = (string) preg_replace( '#<link\b[^>]*(?:' . $hosts . ')[^>]*\brel=["\']?(?:dns-prefetch|preconnect)["\']?[^>]*>\s*#i', '', $html );

		// Stylesheets, including ones preloaded.
		$html = (string) preg_replace_callback(
			'#<link\b[^>]*\bhref=(["\'])(https?:)?//fonts\.googleapis\.com/([^"\']+)\1[^>]*>\s*#i',
			function ( $found ) use ( $resolve ) {
				$url   = 'https://fonts.googleapis.com/' . html_entity_decode( $found[3], ENT_QUOTES );
				$local = call_user_func( $resolve, $url );
				if ( ! is_string( $local ) || $local === '' ) {
					return '';
				}
				return (string) preg_replace( '#\bhref=(["\'])[^"\']*\1#i', 'href="' . esc_url( $local ) . '"', $found[0], 1 );
			},
			$html
		);

		// @import inside inline styles.
		$html = (string) preg_replace_callback(
			'#@import\s+(?:url\()?\s*["\']?(?:https?:)?//fonts\.googleapis\.com/([^"\')\s;]+)["\']?\s*\)?\s*;?#i',
			function ( $found ) use ( $resolve ) {
				$local = call_user_func( $resolve, 'https://fonts.googleapis.com/' . html_entity_decode( $found[1], ENT_QUOTES ) );
				return is_string( $local ) && $local !== '' ? '@import url("' . $local . '");' : '';
			},
			$html
		);

		return $html;
	}

	/**
	 * Download one stylesheet and its font files.
	 *
	 * @param string $url Google Fonts stylesheet URL.
	 *
	 * @return bool
	 */
	public static function cache( $url ) {
		$url = (string) $url;
		if ( 0 !== strpos( $url, 'https://fonts.googleapis.com/' ) ) {
			return false;
		}

		// A current browser's user agent, or Google answers with TTF files
		// for browsers of ten years ago.
		$args = [
			'timeout'    => 20,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
		];

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$css     = (string) wp_remote_retrieve_body( $response );
		$uploads = wp_upload_dir();
		$key     = md5( $url );
		$dir     = trailingslashit( $uploads['basedir'] ) . 'piensa-cookie-consent/fonts/' . $key;
		$base    = trailingslashit( $uploads['baseurl'] ) . 'piensa-cookie-consent/fonts/' . $key;

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$failed = false;
		$css    = (string) preg_replace_callback(
			'#url\((["\']?)(https://fonts\.gstatic\.com/[^"\')]+)\1\)#i',
			function ( $found ) use ( $dir, $base, $args, &$failed ) {
				$ext  = pathinfo( (string) wp_parse_url( $found[2], PHP_URL_PATH ), PATHINFO_EXTENSION );
				$name = md5( $found[2] ) . '.' . ( $ext !== '' ? $ext : 'woff2' );
				$file = $dir . '/' . $name;

				if ( ! file_exists( $file ) ) {
					$font = wp_remote_get( $found[2], $args );
					if ( is_wp_error( $font ) || 200 !== (int) wp_remote_retrieve_response_code( $font ) ) {
						$failed = true;
						return $found[0];
					}
					file_put_contents( $file, wp_remote_retrieve_body( $font ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}

				return 'url(' . $base . '/' . $name . ')';
			},
			$css
		);

		// A stylesheet that still points at Google would defeat the purpose.
		if ( $failed ) {
			return false;
		}

		file_put_contents( $dir . '/fonts.css', $css ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$cached         = get_option( self::OPTION, [] );
		$cached         = is_array( $cached ) ? $cached : [];
		$cached[ $key ] = set_url_scheme( $base . '/fonts.css' );
		update_option( self::OPTION, $cached, false );

		return true;
	}
}
