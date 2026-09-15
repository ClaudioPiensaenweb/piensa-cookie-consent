<?php
/**
 * Plugin Name:       Piensa Cookie Consent
 * Plugin URI:        https://github.com/ClaudioPiensaenweb/piensa-cookie-consent
 * Description:       GDPR and ePrivacy cookie consent banner with Google Consent Mode v2, automatic script blocking, cookie scanning, geo-targeting and a consent log.
 * Version:           1.4.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Piensaenweb
 * Author URI:        https://piensaenweb.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       piensa-cookie-consent
 * Domain Path:       /languages
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Refuse to load twice.
 *
 * Two copies of the plugin in the plugins directory — a leftover folder from a
 * previous install, or the same plugin under two names — both declare the same
 * classes, and the second one to load kills the request with a fatal error
 * that names a class rather than the actual problem. Bowing out with a notice
 * says what is wrong and leaves the site up.
 */
if ( defined( 'PIENSA_COOKIE_CONSENT_FILE' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p><p><code>%s</code><br><code>%s</code></p></div>',
				esc_html__( 'Piensa Cookie Consent is installed twice. Only the first copy is running; deactivate and delete the other one.', 'piensa-cookie-consent' ),
				esc_html( PIENSA_COOKIE_CONSENT_FILE ),
				esc_html( __FILE__ )
			);
		}
	);
	return;
}

define( 'PIENSA_COOKIE_CONSENT_VERSION', '1.4.0' );
define( 'PIENSA_COOKIE_CONSENT_FILE', __FILE__ );
define( 'PIENSA_COOKIE_CONSENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PIENSA_COOKIE_CONSENT_URL', plugin_dir_url( __FILE__ ) );
define( 'PIENSA_COOKIE_CONSENT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Minimum PHP version the plugin runs on.
 *
 * WordPress checks the `Requires PHP` header before activating, but a site
 * that copies the directory in by hand bypasses that check, so guard here too.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'Piensa Cookie Consent requires PHP %1$s or higher. This site runs PHP %2$s.', 'piensa-cookie-consent' ),
						'7.4',
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-core.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent-log.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-migrator.php';

/**
 * Boot the plugin.
 *
 * @return void
 */
function piensa_cookie_consent_bootstrap() {
	$core = new Piensa_Cookie_Consent_Core();
	$core->init();
}
add_action( 'plugins_loaded', 'piensa_cookie_consent_bootstrap' );

/**
 * Run data migrations after the plugin is updated.
 *
 * @return void
 */
function piensa_cookie_consent_maybe_migrate() {
	Piensa_Cookie_Consent_Migrator::maybe_run();
}
add_action( 'plugins_loaded', 'piensa_cookie_consent_maybe_migrate', 5 );

/**
 * Create the consent log table and seed options on activation.
 *
 * @return void
 */
function piensa_cookie_consent_activate() {
	Piensa_Cookie_Consent_Consent_Log::install_table();
	Piensa_Cookie_Consent_Migrator::maybe_run();
}
register_activation_hook( __FILE__, 'piensa_cookie_consent_activate' );

/**
 * Clear the scheduled log purge when the plugin is switched off.
 *
 * A schedule left behind fires against code that is no longer loaded.
 *
 * @return void
 */
function piensa_cookie_consent_deactivate() {
	$timestamp = wp_next_scheduled( Piensa_Cookie_Consent_Consent_Log::PURGE_HOOK );

	if ( $timestamp ) {
		wp_unschedule_event( $timestamp, Piensa_Cookie_Consent_Consent_Log::PURGE_HOOK );
	}
}
register_deactivation_hook( __FILE__, 'piensa_cookie_consent_deactivate' );
