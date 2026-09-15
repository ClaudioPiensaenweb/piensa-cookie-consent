<?php
// includes/class-consent-log.php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Piensa_Cookie_Consent_Consent_Log {
	const TABLE = 'piensa_cookie_consent_log';

	public function init() {
		add_action( 'wp_ajax_piensa_cookie_consent_log_consent', [ $this, 'handle_log_request' ] );
		add_action( 'wp_ajax_nopriv_piensa_cookie_consent_log_consent', [ $this, 'handle_log_request' ] );
	}

	public static function install_table() {
		global $wpdb;

		$table   = $wpdb->prefix . self::TABLE;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            consent_id varchar(64) NOT NULL,
            action varchar(32) NOT NULL,
            categories text NOT NULL,
            services text NULL,
            revision int(11) NOT NULL DEFAULT 0,
            consent_timestamp datetime NULL,
            created_at datetime NOT NULL,
            ip_hash char(64) NULL,
            user_agent text NULL,
            language varchar(12) NULL,
            gpc tinyint(1) NOT NULL DEFAULT 0,
            url text NULL,
            PRIMARY KEY  (id),
            KEY consent_id (consent_id)
        ) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public function handle_log_request() {
		check_ajax_referer( 'piensa_cookie_consent_log', 'nonce' );

		$settings = Piensa_Cookie_Consent_Admin::get_settings();
		if ( empty( $settings['enable_consent_log'] ) ) {
			wp_send_json_success( [ 'disabled' => true ] );
		}

		$payload = [
			'consent_id'        => isset( $_POST['consent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_id'] ) ) : '',
			'action'            => isset( $_POST['consent_action'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_action'] ) ) : '',
			'categories'        => isset( $_POST['categories'] ) ? wp_unslash( $_POST['categories'] ) : '[]',
			'services'          => isset( $_POST['services'] ) ? wp_unslash( $_POST['services'] ) : '[]',
			'revision'          => isset( $_POST['revision'] ) ? (int) $_POST['revision'] : 0,
			'consent_timestamp' => isset( $_POST['consent_timestamp'] ) ? sanitize_text_field( wp_unslash( $_POST['consent_timestamp'] ) ) : '',
			'language'          => isset( $_POST['language'] ) ? sanitize_text_field( wp_unslash( $_POST['language'] ) ) : '',
			'gpc'               => isset( $_POST['gpc'] ) ? (int) $_POST['gpc'] : 0,
			'url'               => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
		];

		$payload['categories'] = $this->normalize_json( $payload['categories'] );
		$payload['services']   = $this->normalize_json( $payload['services'] );

		$ip                    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$payload['ip_hash']    = $ip !== '' ? hash( 'sha256', $ip . wp_salt( 'auth' ) ) : null;
		$payload['user_agent'] = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : null;

		$this->insert_log( $payload );

		wp_send_json_success( [ 'ok' => true ] );
	}

	public static function get_logs( $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table owned by this plugin; a cached consent record would not be evidence of anything.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return [];
		}

		$limit  = max( 1, (int) $limit );
		$offset = max( 0, (int) $offset );

		return $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names cannot be parameterised; $table is built from the trusted prefix.
			$wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset ),
			ARRAY_A
		);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function export_logs() {
		$logs = self::get_logs( 1000, 0 );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=piensa-cookie-consent-log.csv' );

        // phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Streaming a download to php://output; WP_Filesystem writes files, it does not stream responses.
		$output = fopen( 'php://output', 'w' );

		// A CSV cell is not HTML: escaping here would put entities in the file.
		fputcsv(
			$output,
			[
				__( 'Date', 'piensa-cookie-consent' ),
				__( 'Consent ID', 'piensa-cookie-consent' ),
				__( 'Action', 'piensa-cookie-consent' ),
				__( 'Categories', 'piensa-cookie-consent' ),
				__( 'Revision', 'piensa-cookie-consent' ),
				__( 'Language', 'piensa-cookie-consent' ),
				__( 'GPC', 'piensa-cookie-consent' ),
				__( 'URL', 'piensa-cookie-consent' ),
			]
		);

		foreach ( $logs as $log ) {
			fputcsv(
				$output,
				[
					$log['created_at'],
					$log['consent_id'],
					$log['action'],
					$log['categories'],
					$log['revision'],
					$log['language'],
					$log['gpc'],
					$log['url'],
				]
			);
		}

		fclose( $output );
        // phpcs:enable WordPress.WP.AlternativeFunctions.file_system_operations_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	private function insert_log( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table owned by this plugin.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			self::install_table();
		}

		$created_at   = current_time( 'mysql' );
		$consent_time = $data['consent_timestamp'] ? $this->to_mysql_datetime( $data['consent_timestamp'] ) : null;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Writing a consent record to this plugin's own table.
		$wpdb->insert(
			$table,
			[
				'consent_id'        => $data['consent_id'],
				'action'            => $data['action'],
				'categories'        => $data['categories'],
				'services'          => $data['services'],
				'revision'          => $data['revision'],
				'consent_timestamp' => $consent_time,
				'created_at'        => $created_at,
				'ip_hash'           => $data['ip_hash'],
				'user_agent'        => $data['user_agent'],
				'language'          => $data['language'],
				'gpc'               => $data['gpc'],
				'url'               => $data['url'],
			],
			[
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
			]
		);
	}

	private function normalize_json( $value ) {
		$decoded = json_decode( $value, true );
		if ( json_last_error() === JSON_ERROR_NONE ) {
			return wp_json_encode( $decoded );
		}

		return wp_json_encode( [] );
	}

	private function to_mysql_datetime( $iso ) {
		$timestamp = strtotime( $iso );
		if ( $timestamp === false ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
