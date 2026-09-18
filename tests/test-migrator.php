<?php
/**
 * Tests for the upgrade path.
 *
 * Migrations run once, on somebody else's live site, and a mistake is found
 * after the old data is gone. This covers the two that have already gone wrong:
 * the rename from the plugin's previous name, where recreating the consent log
 * instead of renaming it would have orphaned every record collected, and the
 * move to per-language banner text, which filled the Spanish fields with the
 * English defaults that releases 1.0 to 1.5 shipped.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

if ( ! defined( 'PIENSA_COOKIE_CONSENT_PATH' ) ) {
	define( 'PIENSA_COOKIE_CONSENT_PATH', dirname( __DIR__ ) . '/' );
}

$GLOBALS['piensa_test_options'] = [];

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @param string $key     Option.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) { // phpcs:ignore
		return array_key_exists( $key, $GLOBALS['piensa_test_options'] )
			? $GLOBALS['piensa_test_options'][ $key ]
			: $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * @param string $key      Option.
	 * @param mixed  $value    Value.
	 * @param mixed  $autoload Ignored.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = null ) { // phpcs:ignore
		unset( $autoload );
		$GLOBALS['piensa_test_options'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * @param string $key Option.
	 * @return bool
	 */
	function delete_option( $key ) { // phpcs:ignore
		unset( $GLOBALS['piensa_test_options'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * @param string $key Transient.
	 * @return bool
	 */
	function delete_transient( $key ) { // phpcs:ignore
		unset( $key );
		return true;
	}
}

foreach ( [ 'sanitize_text_field', 'sanitize_textarea_field' ] as $piensa_stub ) {
	if ( ! function_exists( $piensa_stub ) ) {
		eval( 'function ' . $piensa_stub . '( $value ) { return trim( (string) $value ); }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text   Text.
	 * @param string $domain Ignored.
	 * @return string
	 */
	function __( $text, $domain = null ) { // phpcs:ignore
		unset( $domain );
		return $text;
	}
}

/**
 * Stub of the consent log, for the names the migration needs.
 */
class Piensa_Cookie_Consent_Consent_Log {
	const TABLE             = 'piensa_cookie_consent';
	const INSTALLED_OPTION  = 'piensa_cookie_consent_log_installed';
}

/**
 * A database that records what it was asked to do.
 */
class Piensa_Test_Wpdb {

	public $prefix  = 'wp_';
	public $tables  = [];
	public $queries = [];

	/**
	 * @param string $sql  Query.
	 * @param mixed  ...$a Arguments.
	 * @return string
	 */
	public function prepare( $sql, ...$a ) {
		foreach ( $a as $value ) {
			$sql = preg_replace( '/%s/', (string) $value, $sql, 1 );
		}

		return $sql;
	}

	/**
	 * @param string $sql Query.
	 * @return string|null
	 */
	public function get_var( $sql ) {
		if ( preg_match( '/SHOW TABLES LIKE (.+)$/', $sql, $m ) ) {
			$name = trim( $m[1] );
			return in_array( $name, $this->tables, true ) ? $name : null;
		}

		return null;
	}

	/**
	 * @param string $sql Query.
	 * @return int
	 */
	public function query( $sql ) {
		$this->queries[] = $sql;

		if ( preg_match( '/RENAME TABLE `(.+?)` TO `(.+?)`/', $sql, $m ) ) {
			$this->tables = array_map(
				static function ( $t ) use ( $m ) {
					return $t === $m[1] ? $m[2] : $t;
				},
				$this->tables
			);
		}

		return 1;
	}
}

require_once __DIR__ . '/../includes/class-banner-text.php';
require_once __DIR__ . '/../includes/class-migrator.php';

return function ( $assert ) {
	global $wpdb;

	// ---------------------------------------------------------------- 0.5.x
	// A site coming from the plugin's previous name, with settings and a log.
	$GLOBALS['piensa_test_options'] = [
		'agency_shield_cmp_settings'   => [
			'enable_blocker' => 1,
			'banner_title'   => 'Mis cookies',
		],
		'agency_shield_cmp_discovered' => [ 'example.test' => [] ],
	];

	$wpdb         = new Piensa_Test_Wpdb();
	$wpdb->tables = [ 'wp_agency_shield_cmp_consent' ];

	Piensa_Cookie_Consent_Migrator::maybe_run();

	$settings = get_option( 'piensa_cookie_consent_settings' );

	$assert( is_array( $settings ), 'the settings survive the rename' );
	$assert( isset( $settings['enable_blocker'] ), 'a setting keeps its value' );
	$assert( false === get_option( 'agency_shield_cmp_settings', false ), 'the old key is cleaned up' );
	$assert(
		[ 'example.test' => [] ] === get_option( 'piensa_cookie_consent_discovered' ),
		'discovered hosts survive too'
	);

	// The consent log is the evidence of compliance. Recreating the table would
	// leave every record collected orphaned under the old name.
	$assert(
		in_array( 'wp_piensa_cookie_consent', $wpdb->tables, true ),
		'the consent log table is renamed, not recreated'
	);
	$assert( 1 === get_option( Piensa_Cookie_Consent_Consent_Log::INSTALLED_OPTION ), 'the log is marked as installed' );

	// Wording the site actually chose is carried across.
	$assert(
		isset( $settings['banner_text']['es']['banner_title'] )
			&& 'Mis cookies' === $settings['banner_text']['es']['banner_title'],
		'text the site wrote is kept'
	);

	$assert(
		4 === (int) get_option( 'piensa_cookie_consent_db_version' ),
		'the schema version is recorded'
	);

	// ------------------------------------------------------- 1.0 to 1.5
	// Those releases shipped English defaults sitting in the Spanish fields.
	// Read as wording the site had chosen, they were carried into the Spanish
	// block and a Spanish site showed an English banner.
	$GLOBALS['piensa_test_options'] = [
		'piensa_cookie_consent_settings' => [
			'banner_title'       => 'Cookie preferences',
			'banner_accept_all'  => 'Accept all',
			'banner_description' => 'Un texto que hemos escrito nosotros.',
			'banner_title_en'    => 'Our own English title',
		],
	];

	$wpdb = new Piensa_Test_Wpdb();

	Piensa_Cookie_Consent_Migrator::maybe_run();

	$text = get_option( 'piensa_cookie_consent_settings' )['banner_text'];

	$assert( ! isset( $text['es']['banner_title'] ), 'an English default is not kept as Spanish' );
	$assert( ! isset( $text['es']['banner_accept_all'] ), 'nor is an English button label' );
	$assert(
		isset( $text['es']['banner_description'] ) && 'Un texto que hemos escrito nosotros.' === $text['es']['banner_description'],
		'genuinely custom Spanish text is kept'
	);
	$assert(
		isset( $text['en']['banner_title'] ) && 'Our own English title' === $text['en']['banner_title'],
		'custom English text moves to the English block'
	);

	// ------------------------------------------------------------ already done
	// Running twice must not undo anything: upgrades fire on every request until
	// the version is written, and a half-applied migration would be worse than
	// none.
	$before = get_option( 'piensa_cookie_consent_settings' );
	Piensa_Cookie_Consent_Migrator::maybe_run();
	$assert( $before === get_option( 'piensa_cookie_consent_settings' ), 'running again changes nothing' );

	// ------------------------------------------------------------ fresh install
	$GLOBALS['piensa_test_options'] = [];
	$wpdb                           = new Piensa_Test_Wpdb();

	Piensa_Cookie_Consent_Migrator::maybe_run();

	$assert( [] === $wpdb->queries, 'a fresh install runs no table surgery' );
	$assert(
		4 === (int) get_option( 'piensa_cookie_consent_db_version' ),
		'a fresh install is marked as current'
	);
};
