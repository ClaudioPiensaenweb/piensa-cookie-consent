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
	const CURRENT_VERSION = 3;

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

		// Run every step the site is behind on, oldest first, so an install
		// several versions old ends up in the same state as a recent one.
		foreach ( self::get_steps() as $version => $step ) {
			if ( $stored < $version ) {
				$step();
			}
		}

		update_option( self::VERSION_OPTION, self::CURRENT_VERSION, false );
	}

	/**
	 * Migration steps, keyed by the schema version they bring the site up to.
	 *
	 * Closures rather than callable arrays, so static analysis can see which
	 * methods are reachable.
	 *
	 * @return array<int, callable(): void> Version => step.
	 */
	private static function get_steps() {
		return [
			2 => static function () {
				self::migrate_to_2();
			},
			3 => static function () {
				self::migrate_to_3();
			},
		];
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
	 * Schema version 3: banner text moves under one key, per language.
	 *
	 * Up to 1.5.0 the Spanish text lived in flat keys (`banner_title`) and the
	 * English in the same keys with an `_en` suffix, edited on two different
	 * screens. Adding a third language would have meant a third suffix, so the
	 * text now lives in `banner_text[<code>]`. Anything a site had customised
	 * is carried across; anything left at its default is dropped, so the site
	 * picks up the shipped translations from then on.
	 *
	 * @return void
	 */
	private static function migrate_to_3() {
		$settings = get_option( 'piensa_cookie_consent_settings', [] );

		if ( ! is_array( $settings ) || isset( $settings['banner_text'] ) ) {
			return;
		}

		$fields = array_keys( Piensa_Cookie_Consent_Banner_Text::get_fields() );
		$text   = [];

		foreach ( [ 'es' => '', 'en' => '_en' ] as $code => $suffix ) {
			$shipped = Piensa_Cookie_Consent_Banner_Text::get_defaults_for( $code );

			foreach ( $fields as $field ) {
				$key = $field . $suffix;

				if ( ! isset( $settings[ $key ] ) ) {
					continue;
				}

				$value = trim( (string) $settings[ $key ] );

				// Only carry across what the site actually changed. Copying a
				// value identical to the old default would freeze the site on
				// wording this release improves.
				if ( '' === $value || ( isset( $shipped[ $field ] ) && $value === $shipped[ $field ] ) ) {
					continue;
				}

				$text[ $code ][ $field ] = $value;
			}
		}

		$settings['banner_text'] = $text;
		update_option( 'piensa_cookie_consent_settings', $settings, false );
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

		// SHOW TABLES is MySQL syntax and simply returns nothing under the
		// SQLite integration. Treating "no answer" as "no table" is the safe
		// reading: the rename is skipped and the new table is created empty,
		// rather than a rename being attempted against a table that is not
		// there.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching
		$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );
		$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );

		if ( $old_exists && ! $new_exists ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names cannot be parameterised; both are built from the trusted table prefix.
			$wpdb->query( "RENAME TABLE `{$old_table}` TO `{$new_table}`" );
			update_option( Piensa_Cookie_Consent_Consent_Log::INSTALLED_OPTION, 1, true );
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
			[ self::VERSION_OPTION, Piensa_Cookie_Consent_Consent_Log::INSTALLED_OPTION ]
		);
	}
}
