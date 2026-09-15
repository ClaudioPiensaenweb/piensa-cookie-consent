<?php
// includes/class-consent-log.php

if (!defined('ABSPATH')) {
    exit;
}

class Piensa_Cookie_Consent_Consent_Log {
    const TABLE = 'piensa_cookie_consent_log';

    public function init() {
        add_action('wp_ajax_piensa_cookie_consent_log_consent', [$this, 'handle_log_request']);
        add_action('wp_ajax_nopriv_piensa_cookie_consent_log_consent', [$this, 'handle_log_request']);
    }

    public static function install_table() {
        global $wpdb;

        $table = $wpdb->prefix . self::TABLE;
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
        dbDelta($sql);
    }

    public function handle_log_request() {
        check_ajax_referer('piensa_cookie_consent_log', 'nonce');

        $settings = Piensa_Cookie_Consent_Admin::get_settings();
        if (empty($settings['enable_consent_log'])) {
            wp_send_json_success(['disabled' => true]);
        }

        $payload = [
            'consent_id' => isset($_POST['consent_id']) ? sanitize_text_field(wp_unslash($_POST['consent_id'])) : '',
            'action' => isset($_POST['consent_action']) ? sanitize_text_field(wp_unslash($_POST['consent_action'])) : '',
            'categories' => isset($_POST['categories']) ? wp_unslash($_POST['categories']) : '[]',
            'services' => isset($_POST['services']) ? wp_unslash($_POST['services']) : '[]',
            'revision' => isset($_POST['revision']) ? (int) $_POST['revision'] : 0,
            'consent_timestamp' => isset($_POST['consent_timestamp']) ? sanitize_text_field(wp_unslash($_POST['consent_timestamp'])) : '',
            'language' => isset($_POST['language']) ? sanitize_text_field(wp_unslash($_POST['language'])) : '',
            'gpc' => isset($_POST['gpc']) ? (int) $_POST['gpc'] : 0,
            'url' => isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '',
        ];

        $payload['categories'] = $this->normalize_json($payload['categories']);
        $payload['services'] = $this->normalize_json($payload['services']);

        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $payload['ip_hash'] = $ip !== '' ? hash('sha256', $ip . wp_salt('auth')) : null;
        $payload['user_agent'] = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : null;

        $this->insert_log($payload);

        wp_send_json_success(['ok' => true]);
    }

    public static function get_logs($limit = 50, $offset = 0) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            return [];
        }

        $limit = max(1, (int) $limit);
        $offset = max(0, (int) $offset);

        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $limit, $offset),
            ARRAY_A
        );
    }

    public static function export_logs() {
        $logs = self::get_logs(1000, 0);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=piensa-cookie-consent-consent-log.csv');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Date', 'Consent ID', 'Action', 'Categories', 'Revision', 'Language', 'GPC', 'URL']);

        foreach ($logs as $log) {
            fputcsv($output, [
                $log['created_at'],
                $log['consent_id'],
                $log['action'],
                $log['categories'],
                $log['revision'],
                $log['language'],
                $log['gpc'],
                $log['url'],
            ]);
        }

        fclose($output);
        exit;
    }

    private function insert_log($data) {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE;

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if ($exists !== $table) {
            self::install_table();
        }

        $created_at = current_time('mysql');
        $consent_time = $data['consent_timestamp'] ? $this->to_mysql_datetime($data['consent_timestamp']) : null;

        $wpdb->insert(
            $table,
            [
                'consent_id' => $data['consent_id'],
                'action' => $data['action'],
                'categories' => $data['categories'],
                'services' => $data['services'],
                'revision' => $data['revision'],
                'consent_timestamp' => $consent_time,
                'created_at' => $created_at,
                'ip_hash' => $data['ip_hash'],
                'user_agent' => $data['user_agent'],
                'language' => $data['language'],
                'gpc' => $data['gpc'],
                'url' => $data['url'],
            ],
            [
                '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s',
            ]
        );
    }

    private function normalize_json($value) {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return wp_json_encode($decoded);
        }

        return wp_json_encode([]);
    }

    private function to_mysql_datetime($iso) {
        $timestamp = strtotime($iso);
        if ($timestamp === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $timestamp);
    }
}
