<?php
/**
 * Self-hosted update channel for the build distributed outside wp.org.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Piensa_Cookie_Consent_Updater {
	private $plugin_file;
	private $plugin_slug;
	private $cache_key = 'piensa_cookie_consent_update';

	public function __construct( $plugin_file ) {
		$this->plugin_file = $plugin_file;
		$this->plugin_slug = plugin_basename( $plugin_file );
	}

	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_updates' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
		add_filter( 'upgrader_post_download', [ $this, 'verify_download' ], 10, 3 );
	}

	public function check_updates( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}

		$settings   = Piensa_Cookie_Consent_Admin::get_settings();
		$update_url = $this->get_update_url( $settings );
		if ( ! $update_url ) {
			return $transient;
		}

		$data = $this->get_update_data( $update_url, $settings );
		if ( ! $data ) {
			return $transient;
		}

		$current = defined( 'PIENSA_COOKIE_CONSENT_VERSION' ) ? PIENSA_COOKIE_CONSENT_VERSION : '0.0.0';
		if ( version_compare( $data['version'], $current, '<=' ) ) {
			return $transient;
		}

		$package = isset( $data['package'] ) ? $data['package'] : '';
		if ( ! $package ) {
			return $transient;
		}

		$require_signature = ! empty( $settings['update_require_signature'] );
		if ( $require_signature && ! $this->verify_signature( $data ) ) {
			return $transient;
		}

		$update                   = new stdClass();
		$update->slug             = 'piensa-cookie-consent';
		$update->plugin           = $this->plugin_slug;
		$update->new_version      = $data['version'];
		$update->url              = isset( $data['details_url'] ) ? $data['details_url'] : '';
		$update->package          = $package;
		$update->tested           = isset( $data['tested'] ) ? $data['tested'] : '';
		$update->requires         = isset( $data['requires'] ) ? $data['requires'] : '';
		$update->requires_php     = isset( $data['requires_php'] ) ? $data['requires_php'] : '';
		$update->ag_checksum      = isset( $data['checksum'] ) ? $data['checksum'] : '';
		$update->ag_checksum_alg  = isset( $data['checksum_alg'] ) ? $data['checksum_alg'] : 'sha256';
		$update->ag_signature     = isset( $data['signature'] ) ? $data['signature'] : '';
		$update->ag_signature_alg = isset( $data['signature_alg'] ) ? $data['signature_alg'] : 'sha256';

		if ( ! isset( $transient->response ) ) {
			$transient->response = [];
		}
		$transient->response[ $this->plugin_slug ] = $update;

		return $transient;
	}

	public function plugins_api( $result, $action, $args ) {
		if ( $action !== 'plugin_information' ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== 'piensa-cookie-consent' ) {
			return $result;
		}

		$settings   = Piensa_Cookie_Consent_Admin::get_settings();
		$update_url = $this->get_update_url( $settings );
		if ( ! $update_url ) {
			return $result;
		}

		$data = $this->get_update_data( $update_url, $settings );
		if ( ! $data ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = esc_html__( 'Piensa Cookie Consent', 'piensa-cookie-consent' );
		$info->slug          = 'piensa-cookie-consent';
		$info->version       = $data['version'];
		$info->author        = esc_html__( 'Piensa Cookie Consent', 'piensa-cookie-consent' );
		$info->homepage      = isset( $data['details_url'] ) ? $data['details_url'] : '';
		$info->requires      = isset( $data['requires'] ) ? $data['requires'] : '';
		$info->tested        = isset( $data['tested'] ) ? $data['tested'] : '';
		$info->requires_php  = isset( $data['requires_php'] ) ? $data['requires_php'] : '';
		$info->download_link = isset( $data['package'] ) ? $data['package'] : '';
		$info->sections      = isset( $data['sections'] ) && is_array( $data['sections'] )
			? $data['sections']
			: [
				'description' => __( 'Updates are managed from the central server.', 'piensa-cookie-consent' ),
			];

		return $info;
	}

	public function verify_download( $reply, $package, $upgrader ) {
		if ( is_wp_error( $reply ) ) {
			return $reply;
		}

		$skin   = isset( $upgrader->skin ) ? $upgrader->skin : null;
		$plugin = $skin && isset( $skin->plugin ) ? $skin->plugin : '';
		if ( $plugin !== $this->plugin_slug ) {
			return $reply;
		}

		$update = $this->get_cached_update();
		if ( ! $update || empty( $update->ag_checksum ) ) {
			return $reply;
		}

		$alg      = $update->ag_checksum_alg ?: 'sha256';
		$checksum = hash_file( $alg, $package );
		if ( ! $checksum || ! hash_equals( $update->ag_checksum, $checksum ) ) {
			return new WP_Error( 'piensa_cookie_consent_checksum_mismatch', esc_html__( 'Invalid update checksum. The update was blocked.', 'piensa-cookie-consent' ) );
		}

		return $reply;
	}

	private function get_update_url( $settings ) {
		if ( empty( $settings['update_server_url'] ) ) {
			return '';
		}

		$url = trim( (string) $settings['update_server_url'] );
		if ( $url === '' ) {
			return '';
		}

		$channel = ! empty( $settings['update_channel'] ) ? $settings['update_channel'] : 'stable';
		$url     = str_replace( '{slug}', 'piensa-cookie-consent', $url );
		$url     = str_replace( '{channel}', rawurlencode( $channel ), $url );
		if ( strpos( $url, '{site}' ) !== false ) {
			$url = str_replace( '{site}', rawurlencode( home_url() ), $url );
		} else {
			$url = add_query_arg(
				[
					'slug'    => 'piensa-cookie-consent',
					'channel' => $channel,
					'site'    => home_url(),
				],
				$url
			);
		}

		return $url;
	}

	private function get_update_data( $url, $settings ) {
		$cached = get_site_transient( $this->cache_key );
		if ( is_array( $cached ) && ! empty( $cached['version'] ) && ! empty( $cached['package'] ) ) {
			return $cached;
		}

		$headers = [
			'Accept'     => 'application/json',
			'User-Agent' => 'PiensaCookieConsent/' . ( defined( 'PIENSA_COOKIE_CONSENT_VERSION' ) ? PIENSA_COOKIE_CONSENT_VERSION : '0.0.0' ),
		];

		if ( ! empty( $settings['update_token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( (string) $settings['update_token'] );
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'     => 8,
				'redirection' => 2,
				'headers'     => $headers,
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['package'] ) ) {
			return null;
		}

		$normalized = [
			'version'       => (string) $data['version'],
			'package'       => esc_url_raw( (string) $data['package'] ),
			'details_url'   => isset( $data['details_url'] ) ? esc_url_raw( (string) $data['details_url'] ) : '',
			'tested'        => isset( $data['tested'] ) ? sanitize_text_field( $data['tested'] ) : '',
			'requires'      => isset( $data['requires'] ) ? sanitize_text_field( $data['requires'] ) : '',
			'requires_php'  => isset( $data['requires_php'] ) ? sanitize_text_field( $data['requires_php'] ) : '',
			'sections'      => isset( $data['sections'] ) && is_array( $data['sections'] ) ? $data['sections'] : [],
			'checksum'      => isset( $data['checksum'] ) ? sanitize_text_field( $data['checksum'] ) : '',
			'checksum_alg'  => isset( $data['checksum_alg'] ) ? sanitize_text_field( $data['checksum_alg'] ) : 'sha256',
			'signature'     => isset( $data['signature'] ) ? sanitize_text_field( $data['signature'] ) : '',
			'signature_alg' => isset( $data['signature_alg'] ) ? sanitize_text_field( $data['signature_alg'] ) : 'sha256',
		];

		set_site_transient( $this->cache_key, $normalized, 6 * HOUR_IN_SECONDS );

		return $normalized;
	}

	private function verify_signature( $data ) {
		$public_key = $this->get_public_key();
		if ( ! $public_key || empty( $data['signature'] ) ) {
			return false;
		}

		$payload   = $this->build_signature_payload( $data );
		$signature = base64_decode( $data['signature'], true );
		if ( $signature === false ) {
			return false;
		}

		$alg  = isset( $data['signature_alg'] ) ? $data['signature_alg'] : 'sha256';
		$algo = $alg === 'sha512' ? OPENSSL_ALGO_SHA512 : OPENSSL_ALGO_SHA256;

		return openssl_verify( $payload, $signature, $public_key, $algo ) === 1;
	}

	private function build_signature_payload( $data ) {
		$payload = (string) $data['version'] . '|' . (string) $data['package'] . '|' . (string) $data['checksum'];
		return apply_filters( 'piensa_cookie_consent_update_signature_payload', $payload, $data );
	}

	private function get_public_key() {
		$key = apply_filters( 'piensa_cookie_consent_update_public_key', '' );
		if ( $key ) {
			return $key;
		}

		$settings = Piensa_Cookie_Consent_Admin::get_settings();
		if ( ! empty( $settings['update_public_key'] ) ) {
			return $settings['update_public_key'];
		}

		return '';
	}

	private function get_cached_update() {
		$cached = get_site_transient( $this->cache_key );
		if ( ! is_array( $cached ) ) {
			return null;
		}

		$update                  = new stdClass();
		$update->ag_checksum     = isset( $cached['checksum'] ) ? $cached['checksum'] : '';
		$update->ag_checksum_alg = isset( $cached['checksum_alg'] ) ? $cached['checksum_alg'] : 'sha256';
		return $update;
	}
}
