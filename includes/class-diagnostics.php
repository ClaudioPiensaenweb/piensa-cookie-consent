<?php
/**
 * Reports the plugin's internal state.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Answers "why is it doing that?" with facts rather than guesses.
 *
 * When the banner offers the wrong categories or the consent log looks empty,
 * the question is always which of several inputs produced that result. Reading
 * the code cannot tell you; this can.
 */
class Piensa_Cookie_Consent_Diagnostics {

	/**
	 * Collect the state worth reporting.
	 *
	 * @return array<string, array<string, string>> Section title => label => value.
	 */
	public static function collect() {
		return [
			__( 'Environment', 'piensa-cookie-consent' ) => self::environment(),
			__( 'Banner categories', 'piensa-cookie-consent' ) => self::categories(),
			__( 'Consent log', 'piensa-cookie-consent' ) => self::consent_log(),
			__( 'Scanner', 'piensa-cookie-consent' )     => self::scanner(),
		];
	}

	/**
	 * Versions and the things that change how the plugin behaves.
	 *
	 * @return array<string, string>
	 */
	private static function environment() {
		global $wpdb, $wp_version;

		// SQLite answers some MySQL statements with nothing rather than an
		// error, which has already caused one silent failure here.
		$database = 'MySQL / MariaDB';
		if ( defined( 'DB_ENGINE' ) && 'sqlite' === constant( 'DB_ENGINE' ) ) {
			$database = 'SQLite';
		} elseif ( isset( $wpdb->dbh ) && is_object( $wpdb->dbh ) && false !== strpos( strtolower( get_class( $wpdb->dbh ) ), 'sqlite' ) ) {
			$database = 'SQLite';
		}

		return [
			__( 'Plugin version', 'piensa-cookie-consent' ) => PIENSA_COOKIE_CONSENT_VERSION,
			__( 'WordPress', 'piensa-cookie-consent' )     => $wp_version,
			__( 'PHP', 'piensa-cookie-consent' )           => PHP_VERSION,
			__( 'Database', 'piensa-cookie-consent' )      => $database,
			__( 'Build', 'piensa-cookie-consent' )         => Piensa_Cookie_Consent_Core::has_self_hosted_updater()
				? __( 'agency (self-hosted updates)', 'piensa-cookie-consent' )
				: __( 'WordPress.org', 'piensa-cookie-consent' ),
			__( 'Site language', 'piensa-cookie-consent' ) => get_locale(),
		];
	}

	/**
	 * Which categories the banner offers, and what decided that.
	 *
	 * @return array<string, string>
	 */
	private static function categories() {
		$settings = Piensa_Cookie_Consent_Admin::get_settings();
		$scanner  = new Piensa_Cookie_Consent_Scanner();
		$active   = $scanner->get_active_categories();

		$rows = [
			__( 'Category mode', 'piensa-cookie-consent' ) => $settings['category_mode'],
		];

		foreach ( [ 'necessary', 'analytics', 'marketing' ] as $category ) {
			$rows[ $category ] = ! empty( $active[ $category ] )
				? __( 'shown in the banner', 'piensa-cookie-consent' )
				: __( 'NOT shown in the banner', 'piensa-cookie-consent' );
		}

		if ( 'manual' === $settings['category_mode'] ) {
			$rows[ __( 'Decided by', 'piensa-cookie-consent' ) ] = __( 'the manual toggles under Categories', 'piensa-cookie-consent' );

			return $rows;
		}

		// Automatic mode has two independent routes to a category: a plugin
		// known to use it, and a domain the scanner classified. Reporting only
		// one of them made this report contradict itself — saying no domain
		// activates a category while the category was being shown.
		$rows[ __( 'Plugins that activate a category', 'piensa-cookie-consent' ) ] = self::activating_plugins();

		$discovered = get_option( 'piensa_cookie_consent_discovered', [] );
		$found      = [];

		if ( is_array( $discovered ) ) {
			foreach ( $discovered as $host => $data ) {
				if ( ! empty( $data['category'] ) && in_array( $data['category'], [ 'analytics', 'marketing' ], true ) ) {
					$found[] = $host . ' (' . $data['category'] . ')';
				}
			}
		}

		$rows[ __( 'Domains that activate a category', 'piensa-cookie-consent' ) ] = $found
			? implode( ', ', array_slice( $found, 0, 8 ) )
			: __( 'none found by the scanner', 'piensa-cookie-consent' );

		if ( empty( $active['analytics'] ) && empty( $active['marketing'] ) ) {
			$rows[ __( 'Why only necessary is shown', 'piensa-cookie-consent' ) ] = __( 'neither route found anything: run a scan, or switch Category mode to manual and turn the categories on yourself', 'piensa-cookie-consent' );
		}

		return $rows;
	}

	/**
	 * Installed plugins that cause a category to be offered.
	 *
	 * @return string
	 */
	private static function activating_plugins() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$found = [];

		foreach ( Piensa_Cookie_Consent_Scanner::get_category_plugins() as $category => $plugins ) {
			foreach ( $plugins as $plugin ) {
				if ( is_plugin_active( $plugin ) ) {
					$found[] = dirname( $plugin ) . ' (' . $category . ')';
				}
			}
		}

		return $found ? implode( ', ', $found ) : __( 'none active', 'piensa-cookie-consent' );
	}

	/**
	 * Whether the log is recording, and how much.
	 *
	 * @return array<string, string>
	 */
	private static function consent_log() {
		global $wpdb;

		$settings = Piensa_Cookie_Consent_Admin::get_settings();
		$table    = $wpdb->prefix . Piensa_Cookie_Consent_Consent_Log::TABLE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reporting on this plugin's own table; a cached count would defeat the purpose.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name built from the trusted prefix.
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
		$last  = null;

		if ( null !== $count ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- As above.
			$last = $wpdb->get_var( "SELECT created_at FROM `{$table}` ORDER BY id DESC LIMIT 1" );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$rows = [
			__( 'Logging enabled', 'piensa-cookie-consent' ) => ! empty( $settings['enable_consent_log'] )
				? __( 'yes', 'piensa-cookie-consent' )
				: __( 'no', 'piensa-cookie-consent' ),
			__( 'Table', 'piensa-cookie-consent' ) => $table,
			__( 'Marked as installed', 'piensa-cookie-consent' ) => get_option( Piensa_Cookie_Consent_Consent_Log::INSTALLED_OPTION )
				? __( 'yes', 'piensa-cookie-consent' )
				: __( 'no', 'piensa-cookie-consent' ),
			__( 'Records stored', 'piensa-cookie-consent' ) => null === $count
				? __( 'the table could not be read — it may not exist', 'piensa-cookie-consent' )
				: (string) (int) $count,
		];

		if ( $last ) {
			$rows[ __( 'Most recent record', 'piensa-cookie-consent' ) ] = $last;
		}

		$rows[ __( 'Retention', 'piensa-cookie-consent' ) ] = ( (int) $settings['log_retention_days'] > 0 )
			? sprintf( '%d days', (int) $settings['log_retention_days'] )
			: __( 'kept indefinitely', 'piensa-cookie-consent' );

		return $rows;
	}

	/**
	 * What the scanner has found so far.
	 *
	 * @return array<string, string>
	 */
	private static function scanner() {
		$discovered = get_option( 'piensa_cookie_consent_discovered', [] );
		$cookies    = get_option( 'piensa_cookie_consent_detected_cookies', [] );

		$by_category = [];
		if ( is_array( $discovered ) ) {
			foreach ( $discovered as $data ) {
				$category                 = ! empty( $data['category'] ) ? $data['category'] : 'unknown';
				$by_category[ $category ] = ( isset( $by_category[ $category ] ) ? $by_category[ $category ] : 0 ) + 1;
			}
		}

		$summary = [];
		foreach ( $by_category as $category => $total ) {
			$summary[] = $category . ': ' . $total;
		}

		return [
			__( 'Domains discovered', 'piensa-cookie-consent' ) => is_array( $discovered ) ? (string) count( $discovered ) : '0',
			__( 'By category', 'piensa-cookie-consent' ) => $summary ? implode( ', ', $summary ) : '—',
			__( 'Cookies recorded', 'piensa-cookie-consent' ) => is_array( $cookies ) ? (string) count( $cookies ) : '0',
		];
	}

	/**
	 * Render the report as a table.
	 *
	 * @return void
	 */
	public static function render() {
		echo '<div class="ag-diagnostics">';

		foreach ( self::collect() as $section => $rows ) {
			echo '<h4 style="margin:16px 0 6px;">' . esc_html( $section ) . '</h4>';
			echo '<table class="widefat striped" style="max-width:720px;"><tbody>';

			foreach ( $rows as $label => $value ) {
				echo '<tr><td style="width:240px;"><strong>' . esc_html( $label ) . '</strong></td>';
				echo '<td><code>' . esc_html( $value ) . '</code></td></tr>';
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}

	/**
	 * The same report as plain text, for pasting into a support thread.
	 *
	 * @return string
	 */
	public static function as_text() {
		$lines = [];

		foreach ( self::collect() as $section => $rows ) {
			$lines[] = '## ' . $section;
			foreach ( $rows as $label => $value ) {
				$lines[] = $label . ': ' . $value;
			}
			$lines[] = '';
		}

		return implode( "\n", $lines );
	}
}
