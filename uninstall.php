<?php
/**
 * Removes every trace of the plugin when it is deleted from the site.
 *
 * @link https://developer.wordpress.org/plugins/plugin-basics/uninstall-methods/
 *
 * @package Piensa_Cookie_Consent
 */

// Only WordPress may run this file.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-migrator.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-consent-log.php';

/**
 * Delete the plugin's data from a single site.
 *
 * @return void
 */
function piensa_cookie_consent_uninstall_site() {
	global $wpdb;

	foreach ( Piensa_Cookie_Consent_Migrator::get_owned_options() as $option ) {
		delete_option( $option );
	}

	delete_transient( 'piensa_cookie_consent_scan_notice' );

	// The consent log is user data; deleting the plugin removes it.
	$table = $wpdb->prefix . Piensa_Cookie_Consent_Consent_Log::TABLE;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterised and the name is built from a trusted prefix.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

if ( is_multisite() ) {
	$piensa_cookie_consent_sites = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	);

	foreach ( $piensa_cookie_consent_sites as $piensa_cookie_consent_site_id ) {
		switch_to_blog( $piensa_cookie_consent_site_id );
		piensa_cookie_consent_uninstall_site();
		restore_current_blog();
	}

	foreach ( Piensa_Cookie_Consent_Migrator::get_owned_options() as $piensa_cookie_consent_option ) {
		delete_site_option( $piensa_cookie_consent_option );
	}
} else {
	piensa_cookie_consent_uninstall_site();
}
