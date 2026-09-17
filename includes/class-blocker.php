<?php
/**
 * Neutralises third-party scripts and embeds until consent is given.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Piensa_Cookie_Consent_Blocker {

	/**
	 * Transient that rate-limits writes from the front-end discovery pass.
	 */
	const DISCOVERY_THROTTLE = 'piensa_cookie_consent_discovery_throttle';

	/**
	 * Most hosts kept in the discovered-domains option.
	 */
	const MAX_DISCOVERED = 500;

	/*
	 * The patterns each pass runs.
	 *
	 * Two things they have in common, both learnt the hard way. They accept
	 * either quote style: the earlier ones accepted double quotes only, so a
	 * tag written with single quotes — which plenty of builders and plugins
	 * emit — went through unblocked. And the ones that span an element body use
	 * an unrolled loop instead of a lazy `.*?`, which on a long page exhausts
	 * PCRE's backtrack limit; see process_html() for what that used to cost.
	 */

	const SCRIPT_PATTERN = '/<script\\s+(?![^>]*\\bdata-category\\b)[^>]*\\bsrc\\s*=\\s*["\']([^"\']+)["\'][^>]*>\\s*<\\/script>/is';

	const INLINE_SCRIPT_PATTERN = '/<script\\b([^>]*)>([^<]*+(?:<(?!\\/script\\b)[^<]*+)*+)<\\/script>/is';

	const IMG_PATTERN = '/<img\\s+(?![^>]*\\bdata-cookie-category\\b)[^>]*\\bsrc\\s*=\\s*["\']([^"\']+)["\'][^>]*>/is';

	const LINK_PATTERN = '/<link\\s+(?![^>]*\\bdata-cookie-category\\b)[^>]*\\bhref\\s*=\\s*["\']([^"\']+)["\'][^>]*>/is';

	const IFRAME_PATTERN = '/<iframe\\s+[^>]*\\bsrc\\s*=\\s*["\']([^"\']+)["\'][^>]*>[^<]*+(?:<(?!\\/iframe\\b)[^<]*+)*+<\\/iframe>/is';

	private $blocked_domains    = [];
	private $placeholder_title  = '';
	private $placeholder_button = '';
	private $enabled            = true;
	private $allowed_categories = [];
	private $domain_overrides   = [];
	private $site_host          = '';
	private $block_unknown      = true;
	private $allowed_domains    = [];

	public function init() {
		if ( ! is_admin() ) {
			add_action( 'template_redirect', [ $this, 'start_buffer' ], 0 );
		}
	}

	public function start_buffer() {
		$settings                 = Piensa_Cookie_Consent_Admin::get_settings();
		$this->enabled            = ! empty( $settings['enable_blocker'] );
		$this->blocked_domains    = $this->parse_domains( $settings['blocked_domains'] );
		$this->placeholder_title  = $settings['placeholder_title'];
		$this->placeholder_button = $settings['placeholder_button'];
		$this->domain_overrides   = isset( $settings['domain_overrides'] ) && is_array( $settings['domain_overrides'] ) ? $settings['domain_overrides'] : [];
		$this->site_host          = wp_parse_url( home_url(), PHP_URL_HOST );
		$this->block_unknown      = ! empty( $settings['block_unknown_third_party'] );
		$this->allowed_domains    = $this->parse_domains( isset( $settings['allowed_domains'] ) ? $settings['allowed_domains'] : '' );

		if ( ! Piensa_Cookie_Consent_Geo::should_show_cmp( $settings ) ) {
			$this->enabled = false;
			return;
		}

		if ( ! $this->enabled || ! $this->should_process() ) {
			return;
		}

		ob_start( [ $this, 'process_html' ] );
	}

	/**
	 * Request contexts the buffer stays out of.
	 *
	 * Visual builders render the site inside their own editor on the front end,
	 * where is_admin() is false, so the buffer rewrote the editor's own scripts
	 * and took the builder down with it. What the site owner sees then is a
	 * builder that will not load, with nothing to suggest the cookie plugin is
	 * responsible. Feeds, REST responses and the customizer preview are not
	 * pages anyone consents on either.
	 *
	 * @return bool
	 */
	private function should_process() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return false;
		}

		if ( function_exists( 'wp_is_json_request' ) && wp_is_json_request() ) {
			return false;
		}

		if ( is_feed() || is_embed() || is_customize_preview() ) {
			return false;
		}

		if ( self::is_builder_request() ) {
			return false;
		}

		/**
		 * Filters whether the blocker rewrites this response.
		 *
		 * A site running a builder or a template engine this plugin has not
		 * heard of can switch the buffer off for that request, rather than
		 * having to disable blocking everywhere.
		 *
		 * @param bool $should_process Whether to rewrite the response.
		 */
		return (bool) apply_filters( 'piensa_cookie_consent_should_block', true );
	}

	/**
	 * Whether this request belongs to a visual builder's editing screen.
	 *
	 * Each builder is recognised by the parameter it puts on the URL when it
	 * opens its editor over the front end. Only the presence of the parameter
	 * is checked: the values differ between builders and between versions of
	 * the same one, and guessing at them is how a guard like this goes quietly
	 * out of date.
	 *
	 * @return bool
	 */
	private static function is_builder_request() {
		$flags = [
			'bricks',                        // Bricks.
			'elementor-preview',             // Elementor.
			'et_fb',                         // Divi.
			'et_bfb',                        // Divi, block layout.
			'ct_builder',                    // Oxygen.
			'fl_builder',                    // Beaver Builder.
			'brizy-edit',                    // Brizy.
			'brizy-edit-iframe',
			'breakdance',                    // Breakdance.
			'vc_editable',                   // WPBakery.
			'vcv-editable',                  // Visual Composer.
			'tve',                           // Thrive Architect.
			'zion_builder_active',           // Zion Builder.
			'siteorigin_panels_live_editor', // SiteOrigin.
		];

		foreach ( $flags as $flag ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading the URL only to decide whether to stay out of the way.
			if ( isset( $_GET[ $flag ] ) ) {
				return true;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- As above.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		return in_array( $action, [ 'elementor', 'elementor_ajax', 'et_fb_retrieve_builder_data' ], true );
	}

	/**
	 * Rewrite the response, or hand back exactly what came in.
	 *
	 * preg_replace_callback() returns null when PCRE gives up, and it gives up
	 * on long pages: roughly a megabyte of markup after an unclosed iframe is
	 * enough to reach the backtrack limit. That null was returned straight out
	 * of here as the page body, so the site rendered blank — a cookie plugin
	 * taking a site down being far worse than one script going unblocked. Every
	 * pass is checked now, and a failure keeps the HTML as it stands.
	 *
	 * @param string $html Buffered response.
	 *
	 * @return string
	 */
	public function process_html( $html ) {
		$this->discover_third_party_sources( $html );
		$this->allowed_categories = $this->get_allowed_categories();

		$passes = [
			[ self::SCRIPT_PATTERN, 'replace_script_callback' ],
			[ self::INLINE_SCRIPT_PATTERN, 'replace_inline_script_callback' ],
			[ self::IMG_PATTERN, 'replace_img_callback' ],
			[ self::LINK_PATTERN, 'replace_link_callback' ],
			[ self::IFRAME_PATTERN, 'replace_iframe_callback' ],
		];

		foreach ( $passes as $pass ) {
			$result = preg_replace_callback( $pass[0], [ $this, $pass[1] ], $html );

			if ( null === $result ) {
				// Whatever defeated this pass is still in the document, so the
				// remaining ones would meet it too. Stop here and serve what we
				// have, which is valid HTML either way.
				return $html;
			}

			$html = $result;
		}

		return $html;
	}

	public function replace_iframe_callback( $matches ) {
		$full_tag = $matches[0];
		$src_url  = $matches[1];

		$host     = $this->extract_host( $src_url );
		$category = $this->categorize_domain( $host );
		$service  = Piensa_Cookie_Consent_Scanner::get_service_for_domain( $host );

		if ( ! $this->should_block_category( $category, $host ) && ! $this->is_domain_blocked( $src_url ) ) {
			return $full_tag;
		}

		$title  = esc_html( $this->placeholder_title );
		$button = esc_html( $this->placeholder_button );

		$target_category = $category && $category !== 'unknown' ? $category : 'marketing';
		$neutralized_tag = str_replace( 'src=', 'data-src=', $full_tag );
		$extra           = ' class="ag-blocked-content" data-cookie-category="' . esc_attr( $target_category ) . '"';
		if ( $service ) {
			$extra .= ' data-cookie-service="' . esc_attr( $service ) . '"';
		}
		$neutralized_tag = str_replace( '<iframe', '<iframe' . $extra, $neutralized_tag );

		// Without an explicit type, a placeholder rendered inside a form
		// submits it instead of granting consent.
		$button_attrs = 'type="button" class="ag-btn-accept-marketing"';
		if ( $service ) {
			$button_attrs .= ' data-ag-service="' . esc_attr( $service ) . '" data-ag-category="' . esc_attr( $target_category ) . '"';
		}

		// The overlay is announced as a named group, so a screen reader
		// explains why the embed is missing rather than skipping over it.
		$placeholder = '
        <div class="ag-placeholder-wrapper">
            ' . $neutralized_tag . '
            <div class="ag-placeholder-overlay" role="group" aria-label="' . esc_attr( $title ) . '">
                <div class="ag-placeholder-content">
                    <p>' . $title . '</p>
                    <button ' . $button_attrs . '>' . $button . '</button>
                </div>
            </div>
        </div>';

		return $placeholder;
	}

	private function parse_domains( $raw ) {
		$raw   = is_string( $raw ) ? $raw : '';
		$lines = preg_split( '/\\r\\n|\\r|\\n/', $raw );
		$lines = array_filter( array_map( 'trim', $lines ) );
		return $lines;
	}

	/**
	 * Categories the server leaves untouched.
	 *
	 * Always just the necessary ones, deliberately. Varying the HTML by the
	 * visitor's consent cookie makes every page uncacheable in practice: a page
	 * cache stores whatever the first visitor generated and serves it to
	 * everyone, so one person accepting would release the scripts to visitors
	 * who never did. Blocking unconditionally keeps the markup identical for
	 * all visitors, and the front-end script releases what the visitor has
	 * accepted once it runs.
	 *
	 * @return string[]
	 */
	private function get_allowed_categories() {
		return [ 'necessary' ];
	}

	private function replace_script_callback( $matches ) {
		$full_tag = $matches[0];
		$src_url  = $matches[1];
		$host     = $this->extract_host( $src_url );
		$category = $this->categorize_domain( $host );
		$service  = Piensa_Cookie_Consent_Scanner::get_service_for_domain( $host );

		if ( ! $this->should_block_category( $category, $host ) && ! $this->is_domain_blocked( $src_url ) ) {
			return $full_tag;
		}

		$tag          = preg_replace( '/\\s+type=([\"\\\']).*?\\1/i', '', $full_tag );
		$tag          = preg_replace( '/\\s+data-category=([\"\\\']).*?\\1/i', '', $tag );
		$service_attr = $service ? ' data-service="' . esc_attr( $service ) . '"' : '';
		$tag          = str_replace( '<script', '<script type="text/plain" data-category="' . esc_attr( $category ) . '"' . $service_attr, $tag );

		return $tag;
	}

	private function replace_inline_script_callback( $matches ) {
		$attrs    = isset( $matches[1] ) ? $matches[1] : '';
		$content  = isset( $matches[2] ) ? $matches[2] : '';
		$full_tag = $matches[0];

		if ( $content === '' || trim( $content ) === '' ) {
			return $full_tag;
		}

		if ( stripos( $attrs, 'data-category=' ) !== false ) {
			return $full_tag;
		}

		if ( stripos( $attrs, 'src=' ) !== false ) {
			return $full_tag;
		}

		// Never block this plugin's own configuration. It declares the cookie
		// names and domains of the services it knows about — Hotjar's _hj*,
		// googletagmanager.com — which is exactly what the rules below match
		// on, so the plugin was neutralising the script that tells the banner
		// which categories to offer. The visible result was a preferences
		// dialog with only the necessary category and none of the site's own
		// text or colours.
		if ( $this->is_own_script( $content ) ) {
			return $full_tag;
		}

		if ( preg_match( '/\\btype=([\"\\\'])([^\"\\\']+)\\1/i', $attrs, $type_match ) ) {
			$type = strtolower( $type_match[2] );
			if ( ! in_array( $type, [ 'text/javascript', 'application/javascript', 'module' ], true ) ) {
				return $full_tag;
			}
		}

		$rules = $this->get_inline_script_rules();
		foreach ( $rules as $rule ) {
			if ( preg_match( $rule['pattern'], $content ) ) {
				$category = $rule['category'];
				$service  = $rule['service'];
				$tag      = '<script type="text/plain" data-category="' . esc_attr( $category ) . '"';
				if ( $service ) {
					$tag .= ' data-service="' . esc_attr( $service ) . '"';
				}
				$tag .= '>' . $content . '</script>';
				return $tag;
			}
		}

		return $full_tag;
	}

	/**
	 * Whether an inline script belongs to this plugin.
	 *
	 * @param string $content Script body.
	 *
	 * @return bool
	 */
	private function is_own_script( $content ) {
		foreach ( [ 'PiensaCookieConsentConfig', 'PiensaCookieConsentAdminConfig', 'PiensaCookieConsentAudit', 'piensaCookieConsentMode' ] as $marker ) {
			if ( strpos( $content, $marker ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function get_inline_script_rules() {
		return [
			[
				'pattern'  => '/hotjar|hj\\s*\\(|_hj/i',
				'category' => 'analytics',
				'service'  => 'hotjar',
			],
			[
				'pattern'  => '/clarity\\s*\\(|clarity\\.ms/i',
				'category' => 'analytics',
				'service'  => 'clarity',
			],
			[
				'pattern'  => '/gtag\\s*\\(|google-analytics\\.com|googletagmanager\\.com/i',
				'category' => 'analytics',
				'service'  => 'google_analytics',
			],
			[
				'pattern'  => '/fbq\\s*\\(|facebook\\.net|connect\\.facebook\\.net/i',
				'category' => 'marketing',
				'service'  => 'facebook',
			],
			[
				'pattern'  => '/ttq\\s*\\(|tiktok\\.com|analytics\\.tiktok\\.com/i',
				'category' => 'marketing',
				'service'  => 'tiktok',
			],
			[
				'pattern'  => '/pintrk\\s*\\(|pinterest\\.com/i',
				'category' => 'marketing',
				'service'  => 'pinterest',
			],
			[
				'pattern'  => '/lintrk\\s*\\(|linkedin\\.com|licdn\\.com/i',
				'category' => 'marketing',
				'service'  => 'linkedin',
			],
			[
				'pattern'  => '/twq\\s*\\(|twitter\\.com|platform\\.twitter\\.com/i',
				'category' => 'marketing',
				'service'  => 'twitter',
			],
		];
	}

	private function replace_img_callback( $matches ) {
		$full_tag = $matches[0];
		$src_url  = $matches[1];
		$host     = $this->extract_host( $src_url );
		$category = $this->categorize_domain( $host );
		$service  = Piensa_Cookie_Consent_Scanner::get_service_for_domain( $host );

		if ( ! $this->should_block_category( $category, $host ) && ! $this->is_domain_blocked( $src_url ) ) {
			return $full_tag;
		}

		$target_category = $category && $category !== 'unknown' ? $category : 'marketing';
		$tag             = str_replace( 'src=', 'data-src=', $full_tag );
		$tag             = preg_replace( '/\\s+srcset=([\"\\\']).*?\\1/i', '', $tag );
		$tag             = preg_replace( '/\\s+loading=([\"\\\']).*?\\1/i', '', $tag );
		$tag             = preg_replace( '/\\s+decoding=([\"\\\']).*?\\1/i', '', $tag );
		$tag             = preg_replace( '/\\s+class=([\"\\\'])([^\"\\\']*)\\1/i', ' class="$2 ag-blocked-content"', $tag, 1, $count );
		if ( $count === 0 ) {
			$tag = str_replace( '<img', '<img class="ag-blocked-content"', $tag );
		}
		$extra = ' data-cookie-category="' . esc_attr( $target_category ) . '"';
		if ( $service ) {
			$extra .= ' data-cookie-service="' . esc_attr( $service ) . '"';
		}
		$tag = str_replace( '<img', '<img' . $extra, $tag );
		$tag = preg_replace( '/\\s+data-src=/i', ' data-src=', $tag );
		$tag = str_replace( '<img', '<img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=="', $tag );

		return $tag;
	}

	private function replace_link_callback( $matches ) {
		$full_tag = $matches[0];
		$href_url = $matches[1];
		$host     = $this->extract_host( $href_url );
		$category = $this->categorize_domain( $host );
		$service  = Piensa_Cookie_Consent_Scanner::get_service_for_domain( $host );

		if ( ! $this->should_block_category( $category, $host ) && ! $this->is_domain_blocked( $href_url ) ) {
			return $full_tag;
		}

		$target_category = $category && $category !== 'unknown' ? $category : 'marketing';
		$tag             = str_replace( 'href=', 'data-href=', $full_tag );
		if ( preg_match( '/\\srel=([\"\\\'])([^\"\\\']*)\\1/i', $full_tag, $rel_match ) ) {
			$rel_value = $rel_match[2];
			$tag       = str_replace( '<link', '<link data-rel="' . esc_attr( $rel_value ) . '"', $tag );
		}
		$tag = preg_replace( '/\\s+class=([\"\\\'])([^\"\\\']*)\\1/i', ' class="$2 ag-blocked-content"', $tag, 1, $count );
		if ( $count === 0 ) {
			$tag = str_replace( '<link', '<link class="ag-blocked-content"', $tag );
		}
		$extra = ' data-cookie-category="' . esc_attr( $target_category ) . '"';
		if ( $service ) {
			$extra .= ' data-cookie-service="' . esc_attr( $service ) . '"';
		}
		$tag = str_replace( '<link', '<link' . $extra, $tag );
		$tag = str_replace( 'data-href=', 'data-href=', $tag );
		$tag = preg_replace( '/\\s+rel=([\"\\\']).*?\\1/i', ' rel="preload"', $tag );
		$tag = str_replace( '<link', '<link href=""', $tag );

		return $tag;
	}

	private function is_domain_blocked( $src_url ) {
		foreach ( $this->blocked_domains as $domain ) {
			if ( strpos( $src_url, $domain ) !== false ) {
				return true;
			}
		}

		return false;
	}

	private function discover_third_party_sources( $html ) {
		if ( ! $this->enabled ) {
			return;
		}

		$found    = [];
		$patterns = [
			'/<script[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*>/i',
			'/<iframe[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*>/i',
			'/<img[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*>/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match_all( $pattern, $html, $matches ) ) {
				foreach ( $matches[1] as $src ) {
					$host = $this->extract_host( $src );

					// Same www normalisation the scanner uses, so the site does
					// not record itself as a third party.
					if ( $host && ! Piensa_Cookie_Consent_Scanner::is_same_site( $host ) ) {
						$found[ $host ] = true;
					}
				}
			}
		}

		if ( ! $found ) {
			return;
		}

		$discovered = get_option( 'piensa_cookie_consent_discovered', [] );
		if ( ! is_array( $discovered ) ) {
			$discovered = [];
		}

		$new_hosts = array_diff( array_keys( $found ), array_keys( $discovered ) );

		// Refreshing last_seen on every hit would mean an UPDATE against
		// wp_options for every page view on the site. The timestamp is only
		// worth a write once an hour, and a host nobody has seen before is
		// worth one immediately.
		if ( empty( $new_hosts ) && get_transient( self::DISCOVERY_THROTTLE ) ) {
			return;
		}

		foreach ( array_keys( $found ) as $host ) {
			$discovered[ $host ] = [
				'category'  => $this->categorize_domain( $host ),
				'service'   => Piensa_Cookie_Consent_Scanner::get_service_for_domain( $host ),
				'last_seen' => time(),
			];
		}

		// A page that pulls in hundreds of third-party hosts must not grow the
		// option without bound.
		if ( count( $discovered ) > self::MAX_DISCOVERED ) {
			uasort(
				$discovered,
				static function ( $a, $b ) {
					$left  = isset( $a['last_seen'] ) ? (int) $a['last_seen'] : 0;
					$right = isset( $b['last_seen'] ) ? (int) $b['last_seen'] : 0;
					return $right <=> $left;
				}
			);
			$discovered = array_slice( $discovered, 0, self::MAX_DISCOVERED, true );
		}

		update_option( 'piensa_cookie_consent_discovered', $discovered, false );
		set_transient( self::DISCOVERY_THROTTLE, 1, HOUR_IN_SECONDS );
	}

	private function extract_host( $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return '';
		}

		if ( strpos( $url, '//' ) === 0 ) {
			$url = 'https:' . $url;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return '';
		}

		return strtolower( $host );
	}

	private function categorize_domain( $host ) {
		if ( ! $host ) {
			return 'unknown';
		}

		if ( ! empty( $this->domain_overrides[ $host ] ) && $this->domain_overrides[ $host ] !== 'auto' ) {
			return $this->domain_overrides[ $host ];
		}

		$map = Piensa_Cookie_Consent_Scanner::get_domain_category_map();
		foreach ( $map as $category => $domains ) {
			foreach ( $domains as $domain ) {
				if ( $host === $domain || substr( $host, -strlen( $domain ) - 1 ) === '.' . $domain ) {
					return $category;
				}
			}
		}

		return 'unknown';
	}

	/**
	 * Whether a resource must be held back until consent is given.
	 *
	 * A domain the plugin does not recognise used to be let through, which
	 * meant any third party it had never seen loaded before the visitor chose.
	 * Unrecognised third parties are now blocked by default; first-party
	 * resources and the technical exceptions list are not.
	 *
	 * @param string $category Category resolved for the host.
	 * @param string $host     Host the resource is loaded from.
	 *
	 * @return bool
	 */
	private function should_block_category( $category, $host = '' ) {
		if ( $category === 'necessary' ) {
			return false;
		}

		if ( $category !== 'unknown' ) {
			return ! in_array( $category, $this->allowed_categories, true );
		}

		if ( ! $this->block_unknown ) {
			return false;
		}

		if ( ! $this->is_third_party( $host ) || $this->is_domain_allowed( $host ) ) {
			return false;
		}

		// Unclassified third parties are treated as marketing, the category
		// that requires the most explicit consent. Once marketing is granted
		// they load normally.
		return ! in_array( 'marketing', $this->allowed_categories, true );
	}

	/**
	 * Whether a host belongs to someone other than this site.
	 *
	 * @param string $host Host to test.
	 *
	 * @return bool
	 */
	private function is_third_party( $host ) {
		if ( ! $host || ! $this->site_host ) {
			return false;
		}

		$host = strtolower( $host );
		$site = strtolower( $this->site_host );

		if ( $host === $site ) {
			return false;
		}

		// A subdomain of the site is still the site.
		return substr( $host, - strlen( $site ) - 1 ) !== '.' . $site;
	}

	/**
	 * Whether a host is on the technical exceptions list.
	 *
	 * @param string $host Host to test.
	 *
	 * @return bool
	 */
	private function is_domain_allowed( $host ) {
		if ( ! $host ) {
			return false;
		}

		$host = strtolower( $host );

		foreach ( $this->allowed_domains as $allowed ) {
			$allowed = strtolower( trim( $allowed ) );
			if ( $allowed === '' ) {
				continue;
			}
			if ( $host === $allowed || substr( $host, - strlen( $allowed ) - 1 ) === '.' . $allowed ) {
				return true;
			}
		}

		return false;
	}
}
