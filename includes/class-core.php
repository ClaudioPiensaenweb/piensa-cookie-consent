<?php
/**
 * Wires the plugin's pieces together and registers the front-end assets.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-scanner.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent-mode.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-admin.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-blocker.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent-log.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-geo.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-icons.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-banner-text.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-theme-colors.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-diagnostics.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent-api.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-retention-sync.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-woocommerce.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-policy-page.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-certificate.php';

class Piensa_Cookie_Consent_Core {
	private $scanner;
	private $consent_mode;
	private $blocker;
	private $admin;
	private $consent_log;
	private $updater;
	private $consent_api;
	private $retention_sync;
	private $woocommerce;
	private $policy_page;
	private $certificate;

	public function __construct() {
		$this->scanner        = new Piensa_Cookie_Consent_Scanner();
		$this->consent_mode   = new Piensa_Cookie_Consent_Consent_Mode();
		$this->blocker        = new Piensa_Cookie_Consent_Blocker();
		$this->admin          = new Piensa_Cookie_Consent_Admin();
		$this->consent_log    = new Piensa_Cookie_Consent_Consent_Log();
		$this->consent_api    = new Piensa_Cookie_Consent_Consent_API();
		$this->retention_sync = new Piensa_Cookie_Consent_Retention_Sync();
		$this->woocommerce    = new Piensa_Cookie_Consent_WooCommerce();
		$this->policy_page    = new Piensa_Cookie_Consent_Policy_Page( [ $this, 'render_cookie_policy_shortcode' ] );
		$this->certificate    = new Piensa_Cookie_Consent_Certificate( $this->scanner );
	}

	public function init() {
		add_action( 'init', [ $this, 'load_textdomain' ] );

		$this->consent_mode->init();
		$this->blocker->init();
		$this->admin->init();
		$this->consent_log->init();
		$this->consent_api->init();
		$this->retention_sync->init();
		$this->woocommerce->init();
		$this->policy_page->init();
		$this->certificate->init();
		$this->maybe_init_updater();

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_filter( 'script_loader_tag', [ $this, 'defer_own_scripts' ], 10, 2 );
		add_shortcode( 'piensa_cookie_consent_review', [ $this, 'render_consent_review_shortcode' ] );
		add_shortcode( 'piensa_cookie_consent_policy', [ $this, 'render_cookie_policy_shortcode' ] );
		add_action( 'wp_footer', [ $this, 'maybe_inject_cookie_audit' ], 99 );
	}

	/**
	 * Load the translations.
	 *
	 * WordPress.org serves language packs on its own, but the agency build is
	 * installed by hand and still needs the bundled .mo files.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		// WordPress.org installs get their translations from the language packs
		// it serves, and calling this there is flagged as redundant. The agency
		// build is installed by hand and has to load the bundled .mo itself.
		if ( ! self::has_self_hosted_updater() ) {
			return;
		}

		load_plugin_textdomain(
			'piensa-cookie-consent',
			false,
			dirname( PIENSA_COOKIE_CONSENT_BASENAME ) . '/languages'
		);
	}

	/**
	 * Whether this build ships the self-hosted updater.
	 *
	 * The wp.org package strips it, so anything that configures it must be
	 * hidden there rather than shown and left broken.
	 *
	 * @return bool
	 */
	/**
	 * URL of an asset, minified where there is a minified copy.
	 *
	 * The readable source stays in the package — it is what the plugin is
	 * reviewed on — and is what gets served when SCRIPT_DEBUG is on, so a site
	 * debugging a problem reads the same code this repository holds.
	 *
	 * @param string $relative Path under the plugin directory.
	 *
	 * @return string
	 */
	public static function asset_url( $relative ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			return PIENSA_COOKIE_CONSENT_URL . $relative;
		}

		$minified = preg_replace( '/\\.(js|css)$/', '.min.$1', $relative );

		if ( $minified !== $relative && file_exists( PIENSA_COOKIE_CONSENT_PATH . $minified ) ) {
			return PIENSA_COOKIE_CONSENT_URL . $minified;
		}

		return PIENSA_COOKIE_CONSENT_URL . $relative;
	}

	public static function has_self_hosted_updater() {
		if ( defined( 'PIENSA_COOKIE_CONSENT_DISABLE_UPDATER' ) && PIENSA_COOKIE_CONSENT_DISABLE_UPDATER ) {
			return false;
		}

		return file_exists( PIENSA_COOKIE_CONSENT_PATH . 'includes/class-updater.php' );
	}

	/**
	 * Load the self-hosted updater, when this build ships one.
	 *
	 * WordPress.org guideline #8 forbids a plugin in the directory from serving
	 * its own updates, so `includes/class-updater.php` is stripped from the
	 * wp.org package. The agency build keeps it, and a site can opt out by
	 * defining PIENSA_COOKIE_CONSENT_DISABLE_UPDATER.
	 *
	 * @return void
	 */
	private function maybe_init_updater() {
		if ( ! self::has_self_hosted_updater() ) {
			return;
		}

		require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-updater.php';

		$this->updater = new Piensa_Cookie_Consent_Updater( PIENSA_COOKIE_CONSENT_FILE );
		$this->updater->init();
	}

	/**
	 * Add defer to the plugin's own scripts.
	 *
	 * Written as a tag filter rather than with the 'strategy' argument of
	 * wp_enqueue_script(), which only arrived in WordPress 6.3 while this
	 * plugin supports 6.0.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle Script handle.
	 *
	 * @return string
	 */
	public function defer_own_scripts( $tag, $handle ) {
		if ( ! wp_scripts()->get_data( $handle, 'piensa_defer' ) ) {
			return $tag;
		}

		if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
			return $tag;
		}

		return str_replace( ' src=', ' defer src=', $tag );
	}

	public function enqueue_assets() {
		$settings = Piensa_Cookie_Consent_Admin::get_settings();
		if ( ! Piensa_Cookie_Consent_Geo::should_show_cmp( $settings ) ) {
			return;
		}

		wp_enqueue_style( 'piensa-cookie-consent-cookieconsent', self::asset_url( 'assets/css/cookieconsent.css' ), [], PIENSA_COOKIE_CONSENT_VERSION );
		wp_enqueue_style( 'piensa-cookie-consent-main', self::asset_url( 'assets/css/piensa-cookie-consent.css' ), [], PIENSA_COOKIE_CONSENT_VERSION );

		wp_enqueue_script( 'piensa-cookie-consent-cookieconsent', self::asset_url( 'assets/js/cookieconsent.js' ), [], PIENSA_COOKIE_CONSENT_VERSION, true );
		wp_enqueue_script( 'piensa-cookie-consent-main', self::asset_url( 'assets/js/piensa-cookie-consent.js' ), [ 'piensa-cookie-consent-cookieconsent' ], PIENSA_COOKIE_CONSENT_VERSION, true );

		// Deferred rather than merely placed in the footer: a classic script
		// still blocks the parser where it sits, and these two are the heaviest
		// thing the plugin puts on the page. Deferred scripts keep their
		// relative order and still run before DOMContentLoaded, which is what
		// the front-end script waits for.
		foreach ( [ 'piensa-cookie-consent-cookieconsent', 'piensa-cookie-consent-main' ] as $handle ) {
			wp_script_add_data( $handle, 'piensa_defer', true );
		}

		if ( is_user_logged_in() && current_user_can( 'manage_options' ) && ! empty( $_GET['ag_cookie_audit'] ) && ! empty( $_GET['ag_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_GET['ag_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'piensa_cookie_consent_audit' ) ) {
				wp_enqueue_script( 'piensa-cookie-consent-audit', self::asset_url( 'assets/js/piensa-cookie-consent-audit.js' ), [], PIENSA_COOKIE_CONSENT_VERSION, true );
				wp_localize_script(
					'piensa-cookie-consent-audit',
					'PiensaCookieConsentAudit',
					[
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce'   => wp_create_nonce( 'piensa_cookie_consent_collect_cookies' ),
						'domain'  => wp_parse_url( home_url(), PHP_URL_HOST ),
						'i18n'    => [
							'running' => __( 'Cookie audit running...', 'piensa-cookie-consent' ),
							'done'    => __( 'Audit complete. Cookies found:', 'piensa-cookie-consent' ),
							'none'    => __( 'Audit complete. No cookies were found.', 'piensa-cookie-consent' ),
							'failed'  => __( 'Audit failed. Check the browser console.', 'piensa-cookie-consent' ),
						],
					]
				);
			}
		}

		$categories         = $this->scanner->get_active_categories();
		$cookie_definitions = $this->scanner->get_cookie_definitions();
		$services           = Piensa_Cookie_Consent_Scanner::get_services();
		$discovered         = get_option( 'piensa_cookie_consent_discovered', [] );
		$detected_services  = [];
		if ( is_array( $discovered ) ) {
			foreach ( $discovered as $data ) {
				if ( ! empty( $data['service'] ) ) {
					$detected_services[ $data['service'] ] = true;
				}
			}
		}
		$site_lang = substr( get_locale(), 0, 2 );
		wp_localize_script(
			'piensa-cookie-consent-main',
			'PiensaCookieConsentConfig',
			[
				'icons'             => Piensa_Cookie_Consent_Icons::get_all_paths(),
				'i18n'              => [
					'consentStatus'   => __( 'Consent:', 'piensa-cookie-consent' ),
					// The banner is bilingual regardless of the dashboard
					// locale, so both footers use fixed labels rather than
					// whatever language the admin happens to be in.
					'cookiePolicyEs'  => 'Política de cookies',
					'privacyPolicyEs' => 'Política de privacidad',
					'cookiePolicyEn'  => 'Cookie policy',
					'privacyPolicyEn' => 'Privacy policy',
				],
				'categories'        => $categories,
				'cookieDefinitions' => $cookie_definitions,
				'ui'                => [
					'floatingButton'       => ! empty( $settings['show_floating_button'] ),
					'floatingButtonText'   => $settings['floating_button_text'],
					'floatingButtonStyle'  => $settings['floating_button_style'],
					'consentLayout'        => $settings['consent_layout'],
					'consentPosition'      => $settings['consent_position'],
					'preferencesLayout'    => $settings['preferences_layout'],
					'preferencesPosition'  => $settings['preferences_position'],
					'bannerShowIcon'       => ! empty( $settings['banner_show_icon'] ),
					'bannerIconStyle'      => $settings['banner_icon_style'],
					'allowNecessaryToggle' => ! empty( $settings['allow_necessary_toggle'] ),
				],
				'theme'             => [
					'bg'                => $settings['theme_bg'],
					'primaryColor'      => $settings['theme_primary_color'],
					'secondaryColor'    => $settings['theme_secondary_color'],
					'primaryBtnBg'      => $settings['theme_btn_primary_bg'],
					'primaryBtnColor'   => $settings['theme_btn_primary_color'],
					'secondaryBtnBg'    => $settings['theme_btn_secondary_bg'],
					'secondaryBtnColor' => $settings['theme_btn_secondary_color'],
					'modalRadius'       => $settings['theme_modal_radius'],
					'buttonRadius'      => $settings['theme_button_radius'],
				],
				'language'          => [
					'mode'    => $settings['language_mode'],
					'default' => $settings['default_language'],
					'site'    => $site_lang,
					// Every shipped language, defaults merged with the site's
					// overrides. Previously only Spanish and English were sent,
					// each assembled from its own flat set of option keys.
					'texts'   => Piensa_Cookie_Consent_Banner_Text::get_all( $settings ),
				],
				'services'          => [
					'registry' => $services,
					'detected' => array_keys( $detected_services ),
				],
				'brand'             => [
					'name' => $settings['brand_name'],
					'logo' => $settings['brand_logo_url'],
					'hide' => ! empty( $settings['hide_branding'] ),
				],
				'policy'            => [
					'revision'         => (int) $settings['policy_revision'],
					'cookiePolicyUrl'  => $settings['cookie_policy_url'],
					'privacyPolicyUrl' => $settings['privacy_policy_url'],
					'logConsent'       => ! empty( $settings['enable_consent_log'] ),
					'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
					'nonce'            => wp_create_nonce( 'piensa_cookie_consent_log' ),
					'banner'           => [
						'title'            => $settings['banner_title'],
						'description'      => $settings['banner_description'],
						'acceptAll'        => $settings['banner_accept_all'],
						'rejectAll'        => $settings['banner_reject_all'],
						'managePrefs'      => $settings['banner_manage_prefs'],
						'savePrefs'        => $settings['banner_save_prefs'],
						'preferencesTitle' => $settings['banner_preferences_title'],
					],
				],
			]
		);
	}

	public function render_consent_review_shortcode() {
		$settings     = Piensa_Cookie_Consent_Admin::get_settings();
		$button_text  = esc_html( $settings['floating_button_text'] );
		$button_label = esc_attr( $settings['floating_button_text'] );
		$style        = $settings['floating_button_style'];
		$icon         = $this->get_review_icon_svg();

		$button = $style === 'icon'
			? '<button type="button" class="ag-btn-consent-review ag-btn-icon" data-ag-consent-review="1" aria-label="' . $button_label . '">' . $icon . '<span class="ag-btn-label">' . $button_text . '</span></button>'
			: '<button type="button" class="ag-btn-consent-review" data-ag-consent-review="1">' . $button_text . '</button>';

		return '<div class="ag-consent-review">' .
			$button .
			'<span class="ag-consent-status" data-ag-consent-status="1"></span>' .
			'</div>';
	}

	private function get_review_icon_svg() {
		return '<svg class="ag-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2l7 3v6c0 5-3.5 9-7 11-3.5-2-7-6-7-11V5l7-3zm0 5a1 1 0 00-1 1v4c0 .6.4 1 1 1h4a1 1 0 100-2h-3V8a1 1 0 00-1-1z"/></svg>';
	}

	/**
	 * The `[piensa_cookie_consent_policy]` shortcode.
	 *
	 * @return string
	 */
	public function render_cookie_policy_shortcode() {
		return self::in_policy_language( [ $this, 'render_cookie_policy' ] );
	}

	/**
	 * Run a renderer in the language the policy is published in.
	 *
	 * Strings follow the locale of whoever is looking, so an administrator
	 * whose profile is in English saw the declaration in English inside the
	 * page builder, on a Spanish site whose visitors read it in Spanish. The
	 * policy is a published legal text with one language: the banner's
	 * default one.
	 *
	 * @param callable $render Returns HTML.
	 *
	 * @return string
	 */
	public static function in_policy_language( $render ) {
		$settings = Piensa_Cookie_Consent_Admin::get_settings();
		$locale   = self::locale_for_language( ! empty( $settings['default_language'] ) ? $settings['default_language'] : 'es' );
		$switched = function_exists( 'switch_to_locale' ) && determine_locale() !== $locale && switch_to_locale( $locale );

		if ( $switched ) {
			self::reload_translations( $locale );
		}

		try {
			return (string) call_user_func( $render );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
				self::reload_translations( determine_locale() );
			}
		}
	}

	/**
	 * The WordPress locale for one of the banner's language codes.
	 *
	 * @param string $code Language code, such as `es`.
	 *
	 * @return string
	 */
	public static function locale_for_language( $code ) {
		$map = [
			'es' => 'es_ES',
			'en' => 'en_US',
			'de' => 'de_DE',
			'fr' => 'fr_FR',
			'it' => 'it_IT',
			'pt' => 'pt_PT',
			'ca' => 'ca',
		];

		return isset( $map[ $code ] ) ? $map[ $code ] : (string) $code;
	}

	/**
	 * Load this plugin's strings for a locale.
	 *
	 * Switching locale does not reliably reload a plugin's own translations:
	 * the bundled .mo of the agency build is not in WordPress's registry.
	 *
	 * @param string $locale Locale.
	 *
	 * @return void
	 */
	private static function reload_translations( $locale ) {
		unload_textdomain( 'piensa-cookie-consent' );

		$file = PIENSA_COOKIE_CONSENT_PATH . 'languages/piensa-cookie-consent-' . $locale . '.mo';
		if ( is_readable( $file ) ) {
			load_textdomain( 'piensa-cookie-consent', $file, $locale );
		}
	}

	/**
	 * Render the cookie policy table.
	 *
	 * The columns are the ones a cookie policy is required to carry: which
	 * cookie, whose it is, what it is for and how long it lasts. The closing
	 * paragraph covers how to accept, refuse or withdraw consent, which has to
	 * be stated rather than merely possible.
	 *
	 * @return string
	 */
	public function render_cookie_policy() {
		$definitions = $this->scanner->get_cookie_definitions();

		$output  = '<div class="ag-cookie-policy">';
		$output .= '<h3>' . esc_html__( 'Types of cookies by origin', 'piensa-cookie-consent' ) . '</h3>';
		$output .= '<ul>';
		$output .= '<li><strong>' . esc_html__( 'First-party cookies:', 'piensa-cookie-consent' ) . '</strong> ' . esc_html__( 'sent to your device from a device or domain managed by the owner of this website, from which the service you request is provided.', 'piensa-cookie-consent' ) . '</li>';
		$output .= '<li><strong>' . esc_html__( 'Third-party cookies:', 'piensa-cookie-consent' ) . '</strong> ' . esc_html__( 'sent to your device from a device or domain not managed by the owner of this website, but by another entity that processes the data obtained through them.', 'piensa-cookie-consent' ) . '</li>';
		$output .= '</ul>';
		$output .= '<h3>' . esc_html__( 'Types of cookies by purpose', 'piensa-cookie-consent' ) . '</h3>';
		$output .= '<ul>';
		$output .= '<li><strong>' . esc_html__( 'Technical (necessary) cookies:', 'piensa-cookie-consent' ) . '</strong> ' . esc_html__( 'allow you to browse the website and use its basic functions, such as remembering your cookie choices. They do not require consent.', 'piensa-cookie-consent' ) . '</li>';
		$output .= '<li><strong>' . esc_html__( 'Analytics cookies:', 'piensa-cookie-consent' ) . '</strong> ' . esc_html__( 'allow the number of visitors to be counted and how they use the website to be analysed statistically. They are only installed if you accept them.', 'piensa-cookie-consent' ) . '</li>';
		$output .= '<li><strong>' . esc_html__( 'Advertising (marketing) cookies:', 'piensa-cookie-consent' ) . '</strong> ' . esc_html__( 'allow advertising to be managed and shown according to your browsing habits. They are only installed if you accept them.', 'piensa-cookie-consent' ) . '</li>';
		$output .= '</ul>';
		$output .= '<h3>' . esc_html__( 'Cookies used on this website', 'piensa-cookie-consent' ) . '</h3>';

		foreach ( $definitions as $data ) {
			$output .= '<h3>' . esc_html( $data['label'] ) . '</h3>';
			$output .= '<p>' . esc_html( $data['description'] ) . '</p>';

			if ( empty( $data['cookies'] ) ) {
				$output .= '<p>' . esc_html__( 'No cookies have been declared in this category.', 'piensa-cookie-consent' ) . '</p>';
				continue;
			}

			$output .= '<table class="ag-cookie-table">';
			$output .= '<thead><tr>';
			$output .= '<th>' . esc_html__( 'Cookie', 'piensa-cookie-consent' ) . '</th>';
			$output .= '<th>' . esc_html__( 'Origin', 'piensa-cookie-consent' ) . '</th>';
			$output .= '<th>' . esc_html__( 'Domain', 'piensa-cookie-consent' ) . '</th>';
			$output .= '<th>' . esc_html__( 'Purpose', 'piensa-cookie-consent' ) . '</th>';
			$output .= '<th>' . esc_html__( 'Retention', 'piensa-cookie-consent' ) . '</th>';
			$output .= '</tr></thead><tbody>';

			foreach ( $data['cookies'] as $cookie ) {
				$domain = isset( $cookie['domain'] ) ? (string) $cookie['domain'] : '';
				$origin = isset( $cookie['origin_label'] ) ? (string) $cookie['origin_label'] : '';

				$output .= '<tr>';
				$output .= '<td>' . esc_html( $cookie['name'] ) . '</td>';
				$output .= '<td>' . esc_html( $origin ) . '</td>';
				$output .= '<td>' . esc_html( $domain ) . '</td>';
				$output .= '<td>' . esc_html( $cookie['description'] ) . '</td>';
				$output .= '<td>' . esc_html( $cookie['duration'] ) . '</td>';
				$output .= '</tr>';
			}

			$output .= '</tbody></table>';
		}

		$output .= '<h3>' . esc_html__( 'How to accept, refuse or withdraw your consent', 'piensa-cookie-consent' ) . '</h3>';
		$output .= '<p>' . esc_html__( 'The banner shown on your first visit lets you accept all cookies, refuse every cookie that is not strictly necessary, or choose category by category. Refusing takes the same single click as accepting.', 'piensa-cookie-consent' ) . '</p>';
		$output .= '<p>' . esc_html__( 'You can change or withdraw your choice at any time, with the same ease, using the cookie settings button on this site. Withdrawing consent deletes the cookies in the categories you no longer accept. Your browser settings also let you block or delete cookies for any site.', 'piensa-cookie-consent' ) . '</p>';
		$output .= '<p>' . $this->render_consent_review_shortcode() . '</p>';
		$output .= '</div>';

		return $output;
	}

	public function maybe_inject_cookie_audit() {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_GET['ag_cookie_audit'] ) || empty( $_GET['ag_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_GET['ag_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'piensa_cookie_consent_audit' ) ) {
			return;
		}

		echo '<div id="pw-cookie-audit-toast" style="position:fixed;bottom:16px;right:16px;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;font-size:12px;z-index:99999;box-shadow:0 10px 30px rgba(0,0,0,0.2);">Auditoria de cookies en curso...</div>';
	}
}
