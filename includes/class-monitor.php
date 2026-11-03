<?php
/**
 * Keeps the cookie declaration true over time, without anyone checking.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notices new cookies and tells the agency.
 *
 * The scanner fetches pages from the server and never runs JavaScript, so it
 * cannot see `_ga`, `_fbp` or anything else a script writes; a cookie policy
 * built from it lists what the server sets and little more. And nothing ran
 * it again: a client adding a chat widget or a pixel left the declaration
 * silently out of date until a review caught it.
 *
 * This closes both gaps:
 *
 * - Visitors who have consented report, at most once a week per browser, the
 *   names of the cookies and storage keys their browser holds for the site.
 *   Names only, never values. That is what the site really sets, from real
 *   pages, with whatever scripts the visitor accepted.
 * - A weekly check runs the server scan, looks for anything not classified
 *   and re-runs the compliance checks. Anything new is emailed once, and a
 *   regression in the checks is emailed too.
 * - The agency can read every site's state from a token-protected REST
 *   endpoint, or receive it on a webhook after each check.
 */
class Piensa_Cookie_Consent_Monitor {

	/**
	 * Option holding this feature's settings.
	 */
	const OPTION = 'piensa_cookie_consent_monitor';

	/**
	 * Option holding what has already been alerted, and the last results.
	 */
	const STATE_OPTION = 'piensa_cookie_consent_monitor_state';

	/**
	 * Weekly check.
	 */
	const CRON_HOOK = 'piensa_cookie_consent_weekly_check';

	/**
	 * Early check after a visitor reports something new.
	 */
	const SOON_HOOK = 'piensa_cookie_consent_soon_check';

	/**
	 * Admin page slug.
	 */
	const PAGE = 'piensa-cookie-consent-monitor';

	/**
	 * Reports accepted from one address per hour.
	 */
	const REPORT_RATE_LIMIT = 10;

	/**
	 * Names accepted in one report.
	 */
	const MAX_NAMES = 60;

	/**
	 * @var Piensa_Cookie_Consent_Scanner
	 */
	private $scanner;

	/**
	 * @param Piensa_Cookie_Consent_Scanner $scanner Scanner.
	 */
	public function __construct( $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( self::CRON_HOOK, [ $this, 'run_check' ] );
		add_action( self::SOON_HOOK, [ $this, 'run_check' ] );
		add_action( 'wp_ajax_piensa_cookie_consent_report_cookies', [ $this, 'handle_report' ] );
		add_action( 'wp_ajax_nopriv_piensa_cookie_consent_report_cookies', [ $this, 'handle_report' ] );
		add_action( 'rest_api_init', [ $this, 'register_route' ] );

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_page' ], 21 );
			add_action( 'admin_post_piensa_cookie_consent_monitor', [ $this, 'handle_admin_post' ] );
			add_action( 'admin_init', [ __CLASS__, 'maybe_schedule' ] );
		}
	}

	/**
	 * Schedule the weekly check.
	 *
	 * @return void
	 */
	public static function maybe_schedule() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON_HOOK );
		}
	}

	/**
	 * The settings, with defaults.
	 *
	 * @return array{report_cookies: bool, alert_email: string, webhook_url: string, token: string}
	 */
	public static function get_settings() {
		$saved = get_option( self::OPTION, [] );
		$saved = is_array( $saved ) ? $saved : [];

		return [
			'report_cookies' => isset( $saved['report_cookies'] ) ? (bool) $saved['report_cookies'] : true,
			'alert_email'    => isset( $saved['alert_email'] ) ? (string) $saved['alert_email'] : (string) get_option( 'admin_email' ),
			'webhook_url'    => isset( $saved['webhook_url'] ) ? (string) $saved['webhook_url'] : '',
			'token'          => isset( $saved['token'] ) ? (string) $saved['token'] : '',
		];
	}

	/**
	 * The REST token, created on first use.
	 *
	 * @return string
	 */
	public static function get_token() {
		$settings = self::get_settings();

		if ( $settings['token'] === '' ) {
			$settings['token'] = wp_generate_password( 40, false );
			update_option( self::OPTION, $settings, false );
		}

		return $settings['token'];
	}

	/**
	 * Clean the names a visitor's browser reported.
	 *
	 * Pure, so it can be tested without WordPress.
	 *
	 * @param mixed $names Decoded report.
	 *
	 * @return string[]
	 */
	public static function clean_names( $names ) {
		if ( ! is_array( $names ) ) {
			return [];
		}

		$clean = [];
		foreach ( $names as $name ) {
			if ( ! is_string( $name ) ) {
				continue;
			}
			$name = trim( $name );
			// Cookie names, not values: anything with a value's shape is
			// refused rather than stored.
			if ( preg_match( '/^[A-Za-z0-9_.:\-]{1,100}$/', $name ) ) {
				$clean[ $name ] = true;
			}
			if ( count( $clean ) >= self::MAX_NAMES ) {
				break;
			}
		}

		return array_keys( $clean );
	}

	/**
	 * The category a name is already declared under, if any.
	 *
	 * The site's own cookies (the consent record, the ones declared by hand or
	 * by an integration) are known by definition; without this they would be
	 * reported as unclassified every week.
	 *
	 * @param string                              $name        Cookie or storage key name.
	 * @param array<string, array<string, mixed>> $definitions Cookie definitions by category.
	 *
	 * @return string|null
	 */
	public static function declared_category( $name, $definitions ) {
		foreach ( (array) $definitions as $category => $data ) {
			foreach ( isset( $data['cookies'] ) ? (array) $data['cookies'] : [] as $cookie ) {
				$declared = isset( $cookie['name'] ) ? (string) $cookie['name'] : '';
				if ( $declared === '' ) {
					continue;
				}
				$pattern = '/^' . str_replace( '\\*', '.*', preg_quote( $declared, '/' ) ) . '$/i';
				if ( preg_match( $pattern, $name ) ) {
					return (string) $category;
				}
			}
		}

		return null;
	}

	/**
	 * Receive a visitor's report of the cookies their browser holds.
	 *
	 * @return void
	 */
	public function handle_report() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Anonymous endpoint on cacheable pages; rate-limited and validated below, as the consent log is.
		$settings = self::get_settings();
		if ( ! $settings['report_cookies'] ) {
			wp_send_json_success( [ 'disabled' => true ] );
		}

		$ip  = Piensa_Cookie_Consent_Consent_Log::client_ip();
		$key = 'pcc_rr_' . substr( hash( 'sha256', $ip . wp_salt( 'auth' ) ), 0, 32 );
		if ( $ip !== '' && (int) get_transient( $key ) >= self::REPORT_RATE_LIMIT ) {
			wp_send_json_error( [ 'message' => 'Too many requests' ], 429 );
		}
		set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; each name is validated by clean_names().
		$cookies = self::clean_names( json_decode( isset( $_POST['cookies'] ) ? (string) wp_unslash( $_POST['cookies'] ) : '[]', true ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON; each name is validated by clean_names().
		$storage = self::clean_names( json_decode( isset( $_POST['storage'] ) ? (string) wp_unslash( $_POST['storage'] ) : '[]', true ) );
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$added = $this->record( $cookies, $storage );

		// Something new and unclassified: check within the hour rather than
		// waiting for the weekly run.
		if ( $added && ! wp_next_scheduled( self::SOON_HOOK ) ) {
			wp_schedule_single_event( time() + HOUR_IN_SECONDS, self::SOON_HOOK );
		}

		wp_send_json_success( [ 'count' => count( $cookies ) + count( $storage ) ] );
	}

	/**
	 * Merge reported names into the detected cookies.
	 *
	 * @param string[] $cookies Cookie names.
	 * @param string[] $storage localStorage / sessionStorage keys.
	 *
	 * @return int Unclassified names not seen before.
	 */
	private function record( array $cookies, array $storage ) {
		$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$declared = $this->scanner->get_cookie_definitions();
		$existing = get_option( 'piensa_cookie_consent_detected_cookies', [] );
		$existing = is_array( $existing ) ? $existing : [];
		$known    = [];
		foreach ( $existing as $cookie ) {
			if ( isset( $cookie['name'] ) ) {
				$known[ $cookie['name'] ] = true;
			}
		}

		$incoming = [];
		$new      = 0;
		foreach ( [ 'cookie' => $cookies, 'storage' => $storage ] as $type => $names ) {
			foreach ( $names as $name ) {
				$category   = self::declared_category( $name, $declared );
				$category   = $category !== null ? $category : Piensa_Cookie_Consent_Scanner::categorize_cookie_for_site( $name, $host, $host );
				$incoming[] = [
					'name'      => $name,
					'domain'    => $host,
					'category'  => $category,
					'type'      => $type,
					'source'    => 'visitor',
					'last_seen' => time(),
				];
				if ( $category === 'unknown' && ! isset( $known[ $name ] ) ) {
					++$new;
				}
			}
		}

		if ( $incoming ) {
			update_option( 'piensa_cookie_consent_detected_cookies', Piensa_Cookie_Consent_Scanner::merge_detected_cookies( $existing, $incoming ), false );
		}

		return $new;
	}

	/**
	 * The weekly check: scan, compare, alert.
	 *
	 * @return array<string, mixed> The status, as the REST endpoint returns it.
	 */
	public function run_check() {
		$this->scanner->scan_site( 25 );

		$status = $this->get_status();
		$state  = get_option( self::STATE_OPTION, [] );
		$state  = is_array( $state ) ? $state : [];

		$alerted = isset( $state['alerted'] ) && is_array( $state['alerted'] ) ? $state['alerted'] : [];
		$new     = array_values( array_diff( array_merge( $status['unknown_cookies'], $status['unknown_hosts'] ), $alerted ) );

		$previous   = isset( $state['result'] ) ? (string) $state['result'] : '';
		$regression = $status['result'] === Piensa_Cookie_Consent_Certificate::FAIL && $previous !== Piensa_Cookie_Consent_Certificate::FAIL;

		if ( $new || $regression ) {
			self::send_alert( $status, $new, $regression );
		}

		update_option(
			self::STATE_OPTION,
			[
				'alerted'    => array_values( array_unique( array_merge( $alerted, $new ) ) ),
				'result'     => $status['result'],
				'checked_at' => time(),
			],
			false
		);

		self::send_webhook( $status );

		return $status;
	}

	/**
	 * The site's state, for the agency.
	 *
	 * @return array<string, mixed>
	 */
	public function get_status() {
		global $wp_version;

		$certificate = new Piensa_Cookie_Consent_Certificate( $this->scanner );
		$checks      = Piensa_Cookie_Consent_Certificate::evaluate( $certificate->collect_facts() );

		$unknown_cookies = [];
		$detected        = get_option( 'piensa_cookie_consent_detected_cookies', [] );
		foreach ( is_array( $detected ) ? $detected : [] as $cookie ) {
			if ( isset( $cookie['category'], $cookie['name'] ) && $cookie['category'] === 'unknown' ) {
				$unknown_cookies[] = (string) $cookie['name'];
			}
		}

		$unknown_hosts = [];
		$discovered    = get_option( 'piensa_cookie_consent_discovered', [] );
		foreach ( is_array( $discovered ) ? $discovered : [] as $host => $data ) {
			if ( isset( $data['category'] ) && $data['category'] === 'unknown' ) {
				$unknown_hosts[] = (string) $host;
			}
		}

		$state = get_option( self::STATE_OPTION, [] );

		return [
			'site'            => home_url( '/' ),
			'plugin_version'  => PIENSA_COOKIE_CONSENT_VERSION,
			'wp_version'      => (string) $wp_version,
			'result'          => Piensa_Cookie_Consent_Certificate::overall( $checks ),
			'checks'          => array_map(
				function ( $check ) {
					return [
						'id'       => $check['id'],
						'status'   => $check['status'],
						'evidence' => $check['evidence'],
					];
				},
				$checks
			),
			'unknown_cookies' => array_values( array_unique( $unknown_cookies ) ),
			'unknown_hosts'   => array_values( array_unique( $unknown_hosts ) ),
			'consents'        => Piensa_Cookie_Consent_Consent_Log::count_by_choice(),
			'checked_at'      => is_array( $state ) && isset( $state['checked_at'] ) ? gmdate( 'c', (int) $state['checked_at'] ) : null,
			'generated_at'    => gmdate( 'c' ),
		];
	}

	/**
	 * Email what is new.
	 *
	 * @param array<string, mixed> $status     Status.
	 * @param string[]             $new        Names and hosts not alerted before.
	 * @param bool                 $regression Whether the checks newly fail.
	 *
	 * @return void
	 */
	private static function send_alert( array $status, array $new, $regression ) {
		$to = self::get_settings()['alert_email'];
		if ( ! is_email( $to ) ) {
			return;
		}

		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$subject = $regression
			/* translators: %s: site host. */
			? sprintf( __( '[%s] The cookie compliance check now fails', 'piensa-cookie-consent' ), $host )
			/* translators: %s: site host. */
			: sprintf( __( '[%s] New unclassified cookies or third parties', 'piensa-cookie-consent' ), $host );

		$lines = [];
		if ( $new ) {
			$lines[] = __( 'Found on the site and not yet in the cookie declaration:', 'piensa-cookie-consent' );
			foreach ( $new as $item ) {
				$lines[] = '  - ' . $item;
			}
			$lines[] = '';
			$lines[] = __( 'Classify them in the scanner so the policy and the banner declare them, or block them if they should not be there.', 'piensa-cookie-consent' );
			$lines[] = '';
		}
		if ( $regression ) {
			$lines[] = __( 'Failing checks:', 'piensa-cookie-consent' );
			foreach ( $status['checks'] as $check ) {
				if ( $check['status'] === Piensa_Cookie_Consent_Certificate::FAIL ) {
					$lines[] = '  - ' . $check['evidence'];
				}
			}
			$lines[] = '';
		}
		$lines[] = admin_url( 'admin.php?page=' . self::PAGE );

		wp_mail( $to, $subject, implode( "\n", $lines ) );
	}

	/**
	 * Send the status to the agency's webhook, signed with the token.
	 *
	 * @param array<string, mixed> $status Status.
	 *
	 * @return void
	 */
	private static function send_webhook( array $status ) {
		$url = self::get_settings()['webhook_url'];
		if ( $url === '' || ! wp_http_validate_url( $url ) ) {
			return;
		}

		$body = (string) wp_json_encode( $status );

		wp_remote_post(
			$url,
			[
				'timeout'  => 10,
				'blocking' => false,
				'headers'  => [
					'Content-Type'    => 'application/json',
					'X-PCC-Signature' => 'sha256=' . hash_hmac( 'sha256', $body, self::get_token() ),
				],
				'body'     => $body,
			]
		);
	}

	/**
	 * GET /wp-json/piensa-cookie-consent/v1/status
	 *
	 * @return void
	 */
	public function register_route() {
		register_rest_route(
			'piensa-cookie-consent/v1',
			'/status',
			[
				'methods'             => 'GET',
				'callback'            => function () {
					return rest_ensure_response( $this->get_status() );
				},
				'permission_callback' => function ( $request ) {
					return self::token_matches( (string) $request->get_header( 'x_pcc_token' ), self::get_settings()['token'] );
				},
			]
		);
	}

	/**
	 * Whether a presented token is the site's.
	 *
	 * @param string $presented Token sent.
	 * @param string $expected  Token stored.
	 *
	 * @return bool
	 */
	public static function token_matches( $presented, $expected ) {
		return $expected !== '' && $presented !== '' && hash_equals( $expected, $presented );
	}

	/**
	 * Add the page under the plugin's menu.
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			'piensa-cookie-consent',
			__( 'Monitoring', 'piensa-cookie-consent' ),
			__( 'Monitoring', 'piensa-cookie-consent' ),
			'manage_options',
			self::PAGE,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Settings, last results and the run-now button.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_settings();
		$token    = self::get_token();
		$state    = get_option( self::STATE_OPTION, [] );
		$next     = wp_next_scheduled( self::CRON_HOOK );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['pcc_checked'] ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Check completed.', 'piensa-cookie-consent' ) . '</p></div>';
		}

		echo '<div class="wrap"><h1>' . esc_html__( 'Monitoring', 'piensa-cookie-consent' ) . '</h1>';
		echo '<p>' . esc_html__( 'Keeps the cookie declaration up to date by itself: real visits report which cookies the site sets, a weekly check scans for anything new, and the agency is told.', 'piensa-cookie-consent' ) . '</p>';

		echo '<table class="widefat striped" style="max-width:900px"><tbody>';
		echo '<tr><td>' . esc_html__( 'Last check', 'piensa-cookie-consent' ) . '</td><td>' . esc_html( is_array( $state ) && ! empty( $state['checked_at'] ) ? wp_date( 'd/m/Y H:i', (int) $state['checked_at'] ) : '—' ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Result', 'piensa-cookie-consent' ) . '</td><td>' . esc_html( is_array( $state ) && ! empty( $state['result'] ) ? Piensa_Cookie_Consent_Certificate::status_label( $state['result'] ) : '—' ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Next check', 'piensa-cookie-consent' ) . '</td><td>' . esc_html( $next ? wp_date( 'd/m/Y H:i', $next ) : '—' ) . '</td></tr>';
		echo '<tr><td>' . esc_html__( 'Status endpoint', 'piensa-cookie-consent' ) . '</td><td><code>' . esc_html( rest_url( 'piensa-cookie-consent/v1/status' ) ) . '</code><br>' . esc_html__( 'Header', 'piensa-cookie-consent' ) . ' <code>X-PCC-Token: ' . esc_html( $token ) . '</code></td></tr>';
		echo '</tbody></table>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="piensa_cookie_consent_monitor">';
		wp_nonce_field( 'piensa_cookie_consent_monitor' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row">' . esc_html__( 'Learn from real visits', 'piensa-cookie-consent' ) . '</th><td><label><input type="checkbox" name="monitor[report_cookies]" value="1" ' . checked( $settings['report_cookies'], true, false ) . '> ' . esc_html__( 'Visitors who have consented report, once a week, the names (never the values) of the cookies and storage keys their browser holds for this site.', 'piensa-cookie-consent' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="pcc-alert-email">' . esc_html__( 'Alert email', 'piensa-cookie-consent' ) . '</label></th><td><input type="email" class="regular-text" id="pcc-alert-email" name="monitor[alert_email]" value="' . esc_attr( $settings['alert_email'] ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="pcc-webhook">' . esc_html__( 'Agency webhook', 'piensa-cookie-consent' ) . '</label></th><td><input type="url" class="regular-text" id="pcc-webhook" name="monitor[webhook_url]" value="' . esc_attr( $settings['webhook_url'] ) . '"><p class="description">' . esc_html__( 'Receives the status as JSON after every check, signed in the X-PCC-Signature header with HMAC-SHA256 and the token.', 'piensa-cookie-consent' ) . '</p></td></tr>';
		echo '</table>';
		echo '<p>';
		submit_button( __( 'Save', 'piensa-cookie-consent' ), 'primary', 'save', false );
		echo ' ';
		submit_button( __( 'Run the check now', 'piensa-cookie-consent' ), 'secondary', 'run', false );
		echo ' ';
		submit_button( __( 'New token', 'piensa-cookie-consent' ), 'delete', 'rotate', false );
		echo '</p></form></div>';
	}

	/**
	 * Save the settings, run the check or rotate the token.
	 *
	 * @return void
	 */
	public function handle_admin_post() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_admin_referer( 'piensa_cookie_consent_monitor' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitised below.
		$input    = isset( $_POST['monitor'] ) && is_array( $_POST['monitor'] ) ? wp_unslash( $_POST['monitor'] ) : [];
		$settings = self::get_settings();

		$settings['report_cookies'] = ! empty( $input['report_cookies'] );
		$settings['alert_email']    = isset( $input['alert_email'] ) ? sanitize_email( (string) $input['alert_email'] ) : '';
		$settings['webhook_url']    = isset( $input['webhook_url'] ) ? esc_url_raw( (string) $input['webhook_url'] ) : '';
		if ( isset( $_POST['rotate'] ) || $settings['token'] === '' ) {
			$settings['token'] = wp_generate_password( 40, false );
		}
		update_option( self::OPTION, $settings, false );

		$args = [ 'page' => self::PAGE ];
		if ( isset( $_POST['run'] ) ) {
			$this->run_check();
			$args['pcc_checked'] = 1;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}
