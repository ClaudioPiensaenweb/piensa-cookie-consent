<?php
/**
 * Data migrations between plugin versions.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs one-off data migrations when the stored schema version is behind the
 * version shipped with the code.
 */
class Piensa_Cookie_Consent_Migrator {

	/**
	 * Option holding the schema version the database is currently at.
	 */
	const VERSION_OPTION = 'piensa_cookie_consent_db_version';

	/**
	 * Schema version this release expects.
	 */
	const CURRENT_VERSION = 2;

	/**
	 * Options renamed in schema version 2, old key => new key.
	 *
	 * Releases up to 0.5.8 shipped under the `agency_shield_cmp_` prefix. Sites
	 * upgrading from those versions must keep their settings.
	 *
	 * @var array<string, string>
	 */
	private static $renamed_options = [
		'agency_shield_cmp_settings'         => 'piensa_cookie_consent_settings',
		'agency_shield_cmp_detected_cookies' => 'piensa_cookie_consent_detected_cookies',
		'agency_shield_cmp_discovered'       => 'piensa_cookie_consent_discovered',
	];

	/**
	 * Run any pending migrations.
	 *
	 * @return void
	 */
	public static function maybe_run() {
		$stored = (int) get_option( self::VERSION_OPTION, 0 );

		if ( $stored >= self::CURRENT_VERSION ) {
			return;
		}

		// Each step is guarded by the version it upgrades from, so a site
		// several versions behind runs them all in order.
		if ( $stored < 2 ) {
			self::migrate_to_2();
		}

		update_option( self::VERSION_OPTION, self::CURRENT_VERSION, false );
	}

	/**
	 * Schema version 2: move options off the pre-rename prefix.
	 *
	 * The old keys are deleted only once the new key is confirmed in place, so
	 * an interrupted migration can be retried without losing data.
	 *
	 * @return void
	 */
	private static function migrate_to_2() {
		foreach ( self::$renamed_options as $old_key => $new_key ) {
			$value = get_option( $old_key, null );

			if ( null === $value ) {
				continue;
			}

			// Never clobber a value the new install already wrote.
			if ( false === get_option( $new_key, false ) ) {
				update_option( $new_key, $value );
			}

			if ( false !== get_option( $new_key, false ) ) {
				delete_option( $old_key );
			}
		}

		self::rename_consent_log_table();

		delete_transient( 'agency_shield_cmp_scan_notice' );
	}

	/**
	 * Carry the consent log over to the renamed table.
	 *
	 * Recreating the table would silently orphan every consent record already
	 * collected, which is the evidence of compliance the log exists to provide.
	 *
	 * @return void
	 */
	private static function rename_consent_log_table() {
		global $wpdb;

		$old_table = $wpdb->prefix . 'agency_shield_cmp_consent';
		$new_table = $wpdb->prefix . Piensa_Cookie_Consent_Consent_Log::TABLE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );
		$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );

		if ( $old_exists && ! $new_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names cannot be parameterised; both are built from the trusted table prefix.
			$wpdb->query( "RENAME TABLE `{$old_table}` TO `{$new_table}`" );
		}
		// phpcs:enable
	}

	/**
	 * Option keys the plugin owns, for uninstall cleanup.
	 *
	 * @return string[]
	 */
	public static function get_owned_options() {
		return array_merge(
			array_values( self::$renamed_options ),
			array_keys( self::$renamed_options ),
			[ self::VERSION_OPTION ]
		);
	}
}
