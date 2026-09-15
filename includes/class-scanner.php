<?php
/**
 * Crawls the site to discover external domains and the cookies they set.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Piensa_Cookie_Consent_Scanner {
	public function get_active_categories() {
		$categories = [
			'necessary' => true,
			'analytics' => false,
			'marketing' => false,
		];

		$settings = Piensa_Cookie_Consent_Admin::get_settings();

		if ( $settings['category_mode'] === 'manual' ) {
			$categories['analytics'] = ! empty( $settings['analytics_enabled'] );
			$categories['marketing'] = ! empty( $settings['marketing_enabled'] );
			return $categories;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( self::get_category_plugins() as $category => $plugins ) {
			foreach ( $plugins as $plugin ) {
				if ( is_plugin_active( $plugin ) ) {
					$categories[ $category ] = true;
					break;
				}
			}
		}

		$discovered = get_option( 'piensa_cookie_consent_discovered', [] );
		if ( is_array( $discovered ) ) {
			foreach ( $discovered as $data ) {
				if ( ! empty( $data['category'] ) && $data['category'] === 'analytics' ) {
					$categories['analytics'] = true;
				}
				if ( ! empty( $data['category'] ) && $data['category'] === 'marketing' ) {
					$categories['marketing'] = true;
				}
			}
		}

		return $categories;
	}

	/**
	 * Plugins whose presence means a category is in use.
	 *
	 * Shared with the diagnostics report: kept in one place so the report
	 * cannot describe a rule the scanner no longer follows.
	 *
	 * @return array<string, string[]> Category => plugin basenames.
	 */
	public static function get_category_plugins() {
		return [
			'analytics' => [
				'google-site-kit/google-site-kit.php',
				'google-analytics-for-wordpress/googleanalytics.php',
			],
			'marketing' => [
				'pixelyoursite/pixelyoursite.php',
				'facebook-for-woocommerce/facebook-for-woocommerce.php',
				'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager.php',
			],
		];
	}

	public static function get_domain_category_map() {
		$map = [
			'analytics' => [
				'google-analytics.com',
				'googletagmanager.com',
				'doubleclick.net',
				'stats.g.doubleclick.net',
				'hotjar.com',
				'hotjar.io',
				'hjcdn.com',
				'clarity.ms',
				'bing.com',
			],
			'marketing' => [
				'youtube.com',
				'youtu.be',
				'vimeo.com',
				'maps.google.com',
				'www.google.com',
				'google.com',
				'facebook.com',
				'connect.facebook.net',
				'facebook.net',
				'tiktok.com',
				'analytics.tiktok.com',
				'snapchat.com',
				'sc-static.net',
				'pinterest.com',
				'pinimg.com',
				'twitter.com',
				'platform.twitter.com',
				'ads.twitter.com',
				'googleadservices.com',
				'googlesyndication.com',
				'pinterest.com',
				'pinimg.com',
				'linkedin.com',
				'licdn.com',
			],
		];

		foreach ( self::get_services() as $service ) {
			$category = $service['category'];
			if ( ! isset( $map[ $category ] ) ) {
				$map[ $category ] = [];
			}
			foreach ( $service['domains'] as $domain ) {
				if ( ! in_array( $domain, $map[ $category ], true ) ) {
					$map[ $category ][] = $domain;
				}
			}
		}

		return $map;
	}

	public static function get_services() {
		return [
			'google_analytics'   => [
				'label'    => 'Google Analytics',
				'category' => 'analytics',
				'domains'  => [ 'google-analytics.com', 'googletagmanager.com', 'stats.g.doubleclick.net' ],
				'cookies'  => [ '_ga', '_gid', '_gat', '_ga*' ],
			],
			'google_analytics_4' => [
				'label'    => 'Google Analytics 4',
				'category' => 'analytics',
				'domains'  => [ 'google-analytics.com', 'googletagmanager.com' ],
				'cookies'  => [ '_ga', '_ga*', '_gid' ],
			],
			'google_ads'         => [
				'label'    => 'Google Ads',
				'category' => 'marketing',
				'domains'  => [ 'doubleclick.net', 'googleadservices.com', 'googlesyndication.com' ],
				'cookies'  => [ '_gcl_au', '_gcl_dc', '_gcl_aw', 'IDE', 'test_cookie' ],
			],
			'youtube'            => [
				'label'    => 'YouTube',
				'category' => 'marketing',
				'domains'  => [ 'youtube.com', 'youtu.be' ],
				'cookies'  => [ 'VISITOR_INFO1_LIVE', 'YSC', 'PREF' ],
			],
			'vimeo'              => [
				'label'    => 'Vimeo',
				'category' => 'marketing',
				'domains'  => [ 'vimeo.com' ],
				'cookies'  => [],
			],
			'google_maps'        => [
				'label'    => 'Google Maps',
				'category' => 'marketing',
				'domains'  => [ 'maps.google.com', 'www.google.com', 'google.com' ],
				'cookies'  => [],
			],
			'facebook'           => [
				'label'    => 'Meta / Facebook',
				'category' => 'marketing',
				'domains'  => [ 'facebook.com', 'connect.facebook.net', 'facebook.net' ],
				'cookies'  => [ '_fbp', 'fr' ],
			],
			'linkedin'           => [
				'label'    => 'LinkedIn Insight',
				'category' => 'marketing',
				'domains'  => [ 'linkedin.com', 'licdn.com' ],
				'cookies'  => [ 'li_fat_id', 'bcookie', 'bscookie', 'lidc' ],
			],
			'pinterest'          => [
				'label'    => 'Pinterest',
				'category' => 'marketing',
				'domains'  => [ 'pinterest.com', 'pinimg.com' ],
				'cookies'  => [ '_pinterest_ct', '_pinterest_sess', '_pin_unauth', '_pin_unauth_s' ],
			],
			'twitter'            => [
				'label'    => 'X (Twitter)',
				'category' => 'marketing',
				'domains'  => [ 'twitter.com', 'platform.twitter.com', 'ads.twitter.com' ],
				'cookies'  => [],
			],
			'tiktok'             => [
				'label'    => 'TikTok',
				'category' => 'marketing',
				'domains'  => [ 'tiktok.com', 'analytics.tiktok.com' ],
				'cookies'  => [ '_tt_enable_cookie', '_ttp', '_tt_' ],
			],
			'hotjar'             => [
				'label'    => 'Hotjar',
				'category' => 'analytics',
				'domains'  => [ 'hotjar.com', 'hotjar.io', 'hjcdn.com' ],
				'cookies'  => [ '_hjSessionUser*', '_hjSession*', '_hjAbsoluteSessionInProgress', '_hjIncludedInSessionSample', '_hjTLDTest' ],
			],
			'clarity'            => [
				'label'    => 'Microsoft Clarity',
				'category' => 'analytics',
				'domains'  => [ 'clarity.ms', 'bing.com' ],
				'cookies'  => [ '_clck', '_clsk', 'CLID', 'MUID', 'ANONCHK', 'SM' ],
			],
			'spotify'            => [
				'label'    => 'Spotify',
				'category' => 'marketing',
				'domains'  => [ 'spotify.com', 'open.spotify.com' ],
				'cookies'  => [],
			],
			'soundcloud'         => [
				'label'    => 'SoundCloud',
				'category' => 'marketing',
				'domains'  => [ 'soundcloud.com', 'w.soundcloud.com' ],
				'cookies'  => [],
			],
		];
	}

	public static function get_service_for_domain( $host ) {
		$services = self::get_services();
		foreach ( $services as $key => $service ) {
			foreach ( $service['domains'] as $domain ) {
				if ( $host === $domain || substr( $host, -strlen( $domain ) - 1 ) === '.' . $domain ) {
					return $key;
				}
			}
		}
		return '';
	}

	public function get_cookie_definitions() {
		$settings    = Piensa_Cookie_Consent_Admin::get_settings();
		$definitions = [
			'necessary' => [
				'label'       => $settings['necessary_label'],
				'description' => $settings['necessary_description'],
				'cookies'     => [
					[
						// The plugin's own consent record. Declaring it is not
						// optional: it is a cookie the site sets.
						'name'        => Piensa_Cookie_Consent_Consent::COOKIE,
						'domain'      => $this->get_cookie_domain(),
						'description' => __( 'Stores the cookie choices you have made on this site.', 'piensa-cookie-consent' ),
						'duration'    => __( '6 months', 'piensa-cookie-consent' ),
					],
					[
						'name'        => 'wordpress_*',
						'domain'      => $this->get_cookie_domain(),
						'description' => __( 'Keeps you signed in to WordPress.', 'piensa-cookie-consent' ),
						'duration'    => __( 'Session, or 14 days if you choose to be remembered', 'piensa-cookie-consent' ),
					],
					[
						'name'        => 'wp-settings-*',
						'domain'      => $this->get_cookie_domain(),
						'description' => __( 'Remembers your WordPress interface preferences.', 'piensa-cookie-consent' ),
						'duration'    => __( '1 year', 'piensa-cookie-consent' ),
					],
					[
						'name'        => 'wp-settings-time-*',
						'domain'      => $this->get_cookie_domain(),
						'description' => __( 'Records when your WordPress preferences were set.', 'piensa-cookie-consent' ),
						'duration'    => __( '1 year', 'piensa-cookie-consent' ),
					],
				],
			],
			'analytics' => [
				'label'       => $settings['analytics_label'],
				'description' => $settings['analytics_description'],
				'cookies'     => [],
			],
			'marketing' => [
				'label'       => $settings['marketing_label'],
				'description' => $settings['marketing_description'],
				'cookies'     => [],
			],
		];

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( is_plugin_active( 'google-site-kit/google-site-kit.php' ) || is_plugin_active( 'google-analytics-for-wordpress/googleanalytics.php' ) ) {
			$definitions['analytics']['cookies'][] = [
				'name'        => '_ga',
				'domain'      => $this->get_cookie_domain(),
				'description' => __( 'Google Analytics: user identifier.', 'piensa-cookie-consent' ),
				'duration'    => __( 'Not declared by the provider', 'piensa-cookie-consent' ),
			];
			$definitions['analytics']['cookies'][] = [
				'name'        => '_gid',
				'domain'      => $this->get_cookie_domain(),
				'description' => __( 'Google Analytics: session identifier.', 'piensa-cookie-consent' ),
				'duration'    => __( 'Not declared by the provider', 'piensa-cookie-consent' ),
			];
			$definitions['analytics']['cookies'][] = [
				'name'        => '_gat',
				'domain'      => $this->get_cookie_domain(),
				'description' => __( 'Google Analytics: request throttling.', 'piensa-cookie-consent' ),
				'duration'    => __( 'Not declared by the provider', 'piensa-cookie-consent' ),
			];
		}

		$marketing_plugins = [
			'pixelyoursite/pixelyoursite.php',
			'facebook-for-woocommerce/facebook-for-woocommerce.php',
		];

		foreach ( $marketing_plugins as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				$definitions['marketing']['cookies'][] = [
					'name'        => '_fbp',
					'domain'      => $this->get_cookie_domain(),
					'description' => __( 'Meta Pixel: browser identifier.', 'piensa-cookie-consent' ),
					'duration'    => __( 'Not declared by the provider', 'piensa-cookie-consent' ),
				];
				$definitions['marketing']['cookies'][] = [
					'name'        => 'fr',
					'domain'      => '.facebook.com',
					'description' => __( 'Meta Pixel: advertising and measurement.', 'piensa-cookie-consent' ),
					'duration'    => __( 'Not declared by the provider', 'piensa-cookie-consent' ),
				];
				break;
			}
		}

		if ( is_plugin_active( 'duracelltomi-google-tag-manager/duracelltomi-google-tag-manager.php' ) ) {
			$definitions['analytics']['cookies'][] = [
				'name'        => '_ga*',
				'domain'      => $this->get_cookie_domain(),
				'description' => __( 'Google Analytics (via GTM).', 'piensa-cookie-consent' ),
				'duration'    => __( 'Not declared by the provider', 'piensa-cookie-consent' ),
			];
		}

		$custom = $this->parse_custom_cookies( $settings['custom_cookies'] );
		foreach ( $custom as $category => $cookies ) {
			if ( ! isset( $definitions[ $category ] ) ) {
				continue;
			}
			$definitions[ $category ]['cookies'] = array_merge( $definitions[ $category ]['cookies'], $cookies );
		}

		return $definitions;
	}

	public function scan_site( $limit = 25 ) {
		$urls             = $this->get_scan_urls( $limit );
		$domains          = [];
		$visited          = [];
		$queue            = $urls;
		$site_host        = wp_parse_url( home_url(), PHP_URL_HOST );
		$detected_cookies = get_option( 'piensa_cookie_consent_detected_cookies', [] );
		if ( ! is_array( $detected_cookies ) ) {
			$detected_cookies = [];
		}

		// Tracked alongside the array rather than recounted every iteration,
		// which on a large site is a count() per page crawled.
		$visited_count = count( $visited );

		while ( $queue && $visited_count < $limit ) {
			$url = array_shift( $queue );
			if ( ! $url || isset( $visited[ $url ] ) ) {
				continue;
			}
			$visited[ $url ] = true;
			++$visited_count;

			$response = wp_remote_get(
				$url,
				[
					'timeout'     => 10,
					'redirection' => 3,
					'user-agent'  => 'PiensaCookieConsent/1.0',
				]
			);

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$html  = wp_remote_retrieve_body( $response );
			$found = $this->extract_hosts_from_html( $html );
			foreach ( $found as $host ) {
				$domains[ $host ] = true;
			}

			$cookies_found = $this->extract_cookies_from_response( $response, $url );
			if ( $cookies_found ) {
				$detected_cookies = self::merge_detected_cookies( $detected_cookies, $cookies_found );
			}

			$internal    = $this->extract_internal_links( $html, $site_host );
			$queue_count = count( $queue );

			foreach ( $internal as $link ) {
				if ( $visited_count + $queue_count >= $limit ) {
					break;
				}
				if ( ! isset( $visited[ $link ] ) ) {
					$queue[] = $link;
					++$queue_count;
				}
			}
		}

		$map        = self::get_domain_category_map();
		$discovered = get_option( 'piensa_cookie_consent_discovered', [] );
		if ( ! is_array( $discovered ) ) {
			$discovered = [];
		}

		foreach ( array_keys( $domains ) as $host ) {
			$category            = $this->categorize_domain( $host, $map );
			$service             = self::get_service_for_domain( $host );
			$discovered[ $host ] = [
				'category'  => $category,
				'service'   => $service,
				'last_seen' => time(),
			];
		}

		update_option( 'piensa_cookie_consent_discovered', $discovered, false );
		if ( $detected_cookies ) {
			update_option( 'piensa_cookie_consent_detected_cookies', $detected_cookies, false );
		}

		return [
			'urls'    => count( $visited ),
			'domains' => count( $domains ),
		];
	}

	private function get_cookie_domain() {
		$host = isset( $_SERVER['HTTP_HOST'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
			: wp_parse_url( home_url(), PHP_URL_HOST );
		if ( is_string( $host ) && strpos( $host, ':' ) !== false ) {
			$host = preg_replace( '/:\\d+$/', '', $host );
		}
		return $host ? $host : '';
	}

	private function parse_custom_cookies( $raw ) {
		$raw = is_string( $raw ) ? trim( $raw ) : '';
		if ( $raw === '' ) {
			return [];
		}

		$lines  = preg_split( '/\\r\\n|\\r|\\n/', $raw );
		$result = [];
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 5 ) {
				continue;
			}

			list($name, $category, $purpose, $duration, $domain) = $parts;
			$name     = sanitize_text_field( $name );
			$category = strtolower( sanitize_text_field( $category ) );
			$purpose  = sanitize_text_field( $purpose );
			$duration = sanitize_text_field( $duration );
			$domain   = sanitize_text_field( $domain );
			if ( ! in_array( $category, [ 'necessary', 'analytics', 'marketing' ], true ) ) {
				continue;
			}

			$result[ $category ][] = [
				'name'        => $name,
				'domain'      => $domain,
				'description' => $purpose,
				'duration'    => $duration,
			];
		}

		return $result;
	}

	private function extract_cookies_from_response( $response, $url ) {
		$headers = wp_remote_retrieve_headers( $response );
		if ( ! $headers ) {
			return [];
		}

		$set_cookie = '';
		if ( is_array( $headers ) && isset( $headers['set-cookie'] ) ) {
			$set_cookie = $headers['set-cookie'];
		} elseif ( is_object( $headers ) && isset( $headers['set-cookie'] ) ) {
			$set_cookie = $headers['set-cookie'];
		}

		if ( ! $set_cookie ) {
			return [];
		}

		$items        = is_array( $set_cookie ) ? $set_cookie : [ $set_cookie ];
		$found        = [];
		$request_host = wp_parse_url( $url, PHP_URL_HOST );
		foreach ( $items as $header ) {
			$cookie = $this->parse_set_cookie_header( $header, $request_host );
			if ( ! $cookie ) {
				continue;
			}
			$found[] = $cookie;
		}

		return $found;
	}

	private function parse_set_cookie_header( $header, $request_host ) {
		if ( ! is_string( $header ) || $header === '' ) {
			return null;
		}

		// explode() always returns at least one element, so array_shift() here
		// cannot come back empty.
		$parts      = explode( ';', $header );
		$name_value = array_shift( $parts );
		$name_value = trim( $name_value );
		if ( $name_value === '' || strpos( $name_value, '=' ) === false ) {
			return null;
		}

		$name_parts = array_map( 'trim', explode( '=', $name_value, 2 ) );
		$name       = $name_parts[0];
		$value      = isset( $name_parts[1] ) ? $name_parts[1] : '';
		if ( $name === '' ) {
			return null;
		}

		$domain = $request_host ? $request_host : '';
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( stripos( $part, 'domain=' ) === 0 ) {
				$domain = ltrim( trim( substr( $part, 7 ) ), '.' );
			}
		}

		$category = self::categorize_cookie_for_site( $name, $domain, $request_host );

		return [
			'name'      => sanitize_text_field( $name ),
			'domain'    => sanitize_text_field( $domain ),
			'category'  => $category,
			'last_seen' => time(),
		];
	}

	public static function categorize_cookie_for_site( $name, $domain, $request_host ) {
		$name_lc = strtolower( $name );

		$necessary = [
			'phpsessid',
			'wordpress_logged_in',
			'wordpress_sec',
			'wordpress_',
			'wp-settings',
			'wp-settings-time',
			'wp_lang',
		];

		foreach ( $necessary as $prefix ) {
			if ( strpos( $name_lc, $prefix ) === 0 ) {
				return 'necessary';
			}
		}

		$service_map = self::get_cookie_service_map();
		foreach ( $service_map as $pattern => $category ) {
			if ( preg_match( $pattern, $name_lc ) ) {
				return $category;
			}
		}

		if ( $request_host && $domain && $request_host !== $domain ) {
			$scanner  = new self();
			$category = $scanner->categorize_domain( $domain, self::get_domain_category_map() );
			if ( $category !== 'unknown' ) {
				return $category;
			}
		}

		return 'unknown';
	}

	public static function get_cookie_service_map() {
		$map = [
			'/^_ga/'                     => 'analytics',
			'/^_gid$/'                   => 'analytics',
			'/^_gat/'                    => 'analytics',
			'/^_gcl_/'                   => 'marketing',
			'/^_fbp$/'                   => 'marketing',
			'/^fr$/'                     => 'marketing',
			'/^_tt_/'                    => 'marketing',
			'/^_ttp$/'                   => 'marketing',
			'/^_pin_/'                   => 'marketing',
			'/^_clck$/'                  => 'analytics',
			'/^_clsk$/'                  => 'analytics',
			'/^_hj/'                     => 'analytics',
			'/^_hjSession/'              => 'analytics',
			'/^_hjSessionUser/'          => 'analytics',
			'/^CLID$/'                   => 'analytics',
			'/^MUID$/'                   => 'analytics',
			'/^ANONCHK$/'                => 'analytics',
			'/^SM$/'                     => 'analytics',
			'/^bcookie$/'                => 'marketing',
			'/^bscookie$/'               => 'marketing',
			'/^lidc$/'                   => 'marketing',
			'/^li_fat_id$/'              => 'marketing',
			'/^IDE$/'                    => 'marketing',
			'/^test_cookie$/'            => 'marketing',
			'/^NID$/'                    => 'marketing',
			'/^CONSENT$/'                => 'marketing',
			'/^cc_cookie$/'              => 'necessary',
			'/^cookieyes-consent$/'      => 'necessary',
			'/^cmplz_/'                  => 'necessary',
			'/^complianz_/'              => 'necessary',
			'/^cookie_notice_accepted$/' => 'necessary',
			'/^g_state$/'                => 'necessary',
		];

		foreach ( self::get_services() as $service ) {
			if ( empty( $service['cookies'] ) || empty( $service['category'] ) ) {
				continue;
			}
			foreach ( $service['cookies'] as $cookie ) {
				$cookie = strtolower( $cookie );
				if ( $cookie === '' ) {
					continue;
				}
				if ( substr( $cookie, -1 ) === '*' ) {
					$pattern = '/^' . preg_quote( rtrim( $cookie, '*' ), '/' ) . '/';
				} else {
					$pattern = '/^' . preg_quote( $cookie, '/' ) . '$/';
				}
				$map[ $pattern ] = $service['category'];
			}
		}

		return $map;
	}

	public static function merge_detected_cookies( $existing, $incoming ) {
		$indexed = [];
		foreach ( $existing as $cookie ) {
			if ( ! is_array( $cookie ) ) {
				continue;
			}
			$key = ( isset( $cookie['name'] ) ? $cookie['name'] : '' ) . '|' . ( isset( $cookie['domain'] ) ? $cookie['domain'] : '' );
			if ( $key === '|' ) {
				continue;
			}
			$indexed[ $key ] = $cookie;
		}

		foreach ( $incoming as $cookie ) {
			$key = ( isset( $cookie['name'] ) ? $cookie['name'] : '' ) . '|' . ( isset( $cookie['domain'] ) ? $cookie['domain'] : '' );
			if ( $key === '|' ) {
				continue;
			}
			$indexed[ $key ] = $cookie;
		}

		$result = array_values( $indexed );
		if ( count( $result ) > 200 ) {
			$result = array_slice( $result, 0, 200 );
		}

		return $result;
	}

	/**
	 * Whether a URL belongs to this site.
	 *
	 * The scanner only ever fetches the site's own pages. Anything else would
	 * let a crafted sitemap entry point the scan at an internal address, and
	 * the cookies that came back would be stored and shown in the admin.
	 *
	 * @param string $url URL to test.
	 *
	 * @return bool
	 */
	private function is_own_url( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		$site = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $host || ! $site ) {
			return false;
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( $scheme && ! in_array( strtolower( $scheme ), [ 'http', 'https' ], true ) ) {
			return false;
		}

		// A sitemap commonly lists the www form while home_url() has it
		// without, or the other way round. Treating those as different hosts
		// discards every URL and the scan silently finds nothing.
		$strip = static function ( $value ) {
			$value = strtolower( (string) $value );
			return 0 === strpos( $value, 'www.' ) ? substr( $value, 4 ) : $value;
		};

		return $strip( $host ) === $strip( $site );
	}

	/**
	 * Build the list of pages to crawl.
	 *
	 * Sitemaps come in two shapes and the difference matters: a sitemap index
	 * lists other sitemaps, not pages. Feeding those straight to the crawler
	 * means fetching XML documents and looking for script tags in them, which
	 * finds nothing — and because the list was not empty, the fall back to the
	 * home page never happened either, so the scan reported no third parties
	 * on a site full of them.
	 *
	 * The home page is always included: whatever the sitemap situation, it is
	 * the page most likely to carry the site's tags.
	 *
	 * @param int $limit Maximum number of URLs.
	 *
	 * @return string[]
	 */
	private function get_scan_urls( $limit ) {
		$urls = [ home_url( '/' ) ];

		// wp-sitemap.xml is WordPress's own, present since 5.5; the others come
		// from the SEO plugins that replace it.
		$candidates = [
			home_url( '/wp-sitemap.xml' ),
			home_url( '/sitemap.xml' ),
			home_url( '/sitemap_index.xml' ),
			home_url( '/sitemap-index.xml' ),
		];

		foreach ( $candidates as $sitemap ) {
			$locations = $this->read_sitemap( $sitemap );

			if ( ! $locations ) {
				continue;
			}

			foreach ( $locations as $url ) {
				$urls[] = $url;

				if ( count( $urls ) >= $limit ) {
					break 2;
				}
			}

			// One usable sitemap is enough; the rest would repeat it.
			break;
		}

		return array_slice( array_unique( $urls ), 0, $limit );
	}

	/**
	 * Read a sitemap, following one level of index if that is what it is.
	 *
	 * @param string $url   Sitemap URL.
	 * @param bool   $index Whether this call is already resolving an index.
	 *
	 * @return string[] Page URLs belonging to this site.
	 */
	private function read_sitemap( $url, $index = false ) {
		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 8,
				'redirection' => 3,
			]
		);

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return [];
		}

		$body = wp_remote_retrieve_body( $response );

		if ( ! $body || false === stripos( $body, '<loc' ) ) {
			return [];
		}

		preg_match_all( '/<loc>([^<]+)<\/loc>/i', $body, $matches );

		if ( empty( $matches[1] ) ) {
			return [];
		}

		$locations = [];
		foreach ( $matches[1] as $loc ) {
			// A sitemap can name any URL at all. Fetching one that is not ours
			// would turn an admin-triggered scan into a request to an arbitrary
			// address, so the host is checked before the crawler sees it.
			$clean = esc_url_raw( trim( html_entity_decode( $loc ) ) );

			if ( $clean && $this->is_own_url( $clean ) ) {
				$locations[] = $clean;
			}
		}

		// A sitemap index lists sitemaps. Only one level is followed: a deeper
		// nesting is not worth the requests, and the home page is in the list
		// regardless.
		if ( ! $index && false !== stripos( $body, '<sitemapindex' ) ) {
			$pages = [];

			foreach ( array_slice( $locations, 0, 5 ) as $child ) {
				$pages = array_merge( $pages, $this->read_sitemap( $child, true ) );

				if ( count( $pages ) >= 50 ) {
					break;
				}
			}

			return $pages;
		}

		return $locations;
	}

	private function extract_hosts_from_html( $html ) {
		$hosts     = [];
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$patterns  = [
			'/<script[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*>/i',
			'/<iframe[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*>/i',
			'/<img[^>]+src=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*>/i',
			'/<link[^>]+href=[\"\\\']([^\"\\\']+)[\"\\\'][^>]*>/i',
		];

		foreach ( $patterns as $pattern ) {
			if ( preg_match_all( $pattern, $html, $matches ) ) {
				foreach ( $matches[1] as $src ) {
					$host = $this->extract_host( $src );
					if ( $host && $host !== $site_host ) {
						$hosts[ $host ] = true;
					}
				}
			}
		}

		return array_keys( $hosts );
	}

	private function extract_internal_links( $html, $site_host ) {
		$links = [];
		if ( ! $site_host ) {
			return $links;
		}

		if ( preg_match_all( '/<a[^>]+href=[\"\\\']([^\"\\\']+)[\"\\\']/i', $html, $matches ) ) {
			foreach ( $matches[1] as $href ) {
				if ( strpos( $href, 'mailto:' ) === 0 || strpos( $href, 'tel:' ) === 0 ) {
					continue;
				}
				if ( strpos( $href, '//' ) === 0 ) {
					$href = 'https:' . $href;
				}
				if ( strpos( $href, '/' ) === 0 ) {
					$href = home_url( $href );
				}

				$host = wp_parse_url( $href, PHP_URL_HOST );
				// Same www normalisation as is_own_url(): otherwise the crawl
				// stops at the first page whose links use the other form.
				if ( $host && $this->is_own_url( $href ) ) {
					$clean = esc_url_raw( $href );
					if ( $clean ) {
						$links[ $clean ] = true;
					}
				}
			}
		}

		return array_keys( $links );
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

	private function categorize_domain( $host, $map ) {
		$settings = Piensa_Cookie_Consent_Admin::get_settings();
		if ( ! empty( $settings['domain_overrides'][ $host ] ) && $settings['domain_overrides'][ $host ] !== 'auto' ) {
			return $settings['domain_overrides'][ $host ];
		}

		foreach ( $map as $category => $domains ) {
			foreach ( $domains as $domain ) {
				if ( $host === $domain || substr( $host, -strlen( $domain ) - 1 ) === '.' . $domain ) {
					return $category;
				}
			}
		}

		return 'unknown';
	}

	public function get_report_data() {
		$definitions = $this->get_cookie_definitions();
		$categories  = [];
		foreach ( $definitions as $key => $data ) {
			$categories[] = [
				'key'           => $key,
				'label'         => $data['label'],
				'description'   => $data['description'],
				'cookies_count' => ! empty( $data['cookies'] ) ? count( $data['cookies'] ) : 0,
			];
		}

		$domains    = [];
		$discovered = get_option( 'piensa_cookie_consent_discovered', [] );
		if ( is_array( $discovered ) ) {
			foreach ( $discovered as $domain => $data ) {
				$domains[] = [
					'domain'    => $domain,
					'category'  => isset( $data['category'] ) ? $data['category'] : 'unknown',
					'service'   => isset( $data['service'] ) ? $data['service'] : '',
					'last_seen' => isset( $data['last_seen'] ) ? date_i18n( 'Y-m-d H:i', (int) $data['last_seen'] ) : '',
				];
			}
		}

		$cookies         = [];
		$detected_counts = [
			'necessary' => 0,
			'analytics' => 0,
			'marketing' => 0,
			'unknown'   => 0,
		];
		$detected        = get_option( 'piensa_cookie_consent_detected_cookies', [] );
		if ( is_array( $detected ) ) {
			foreach ( $detected as $cookie ) {
				$cookies[] = [
					'name'      => isset( $cookie['name'] ) ? $cookie['name'] : '',
					'domain'    => isset( $cookie['domain'] ) ? $cookie['domain'] : '',
					'category'  => isset( $cookie['category'] ) ? $cookie['category'] : 'unknown',
					'last_seen' => isset( $cookie['last_seen'] ) ? date_i18n( 'Y-m-d H:i', (int) $cookie['last_seen'] ) : '',
				];
				$cat       = isset( $cookie['category'] ) ? $cookie['category'] : 'unknown';
				if ( ! isset( $detected_counts[ $cat ] ) ) {
					$detected_counts[ $cat ] = 0;
				}
				++$detected_counts[ $cat ];
			}
		}

		foreach ( $categories as $index => $category ) {
			$key                                    = $category['key'];
			$categories[ $index ]['detected_count'] = isset( $detected_counts[ $key ] ) ? $detected_counts[ $key ] : 0;
		}

		return [
			'generated_at' => current_time( 'mysql' ),
			'site_url'     => home_url(),
			'categories'   => $categories,
			'domains'      => $domains,
			'cookies'      => $cookies,
		];
	}
}
