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

	/**
	 * Where this build looks for its manifest when nothing else is set.
	 *
	 * The setting used to default to an empty string, with this address shown
	 * only as the field's placeholder — grey text that reads exactly like a
	 * filled-in value. Nobody typed it, so get_update_url() returned nothing
	 * and no site ever checked for an update. Living here rather than in the
	 * shared defaults keeps it out of the wordpress.org package, which strips
	 * this file and must not look like it serves its own updates.
	 */
	const DEFAULT_MANIFEST = 'https://claudiopiensaenweb.github.io/piensa-cookie-consent/update.json';

	/**
	 * Verifies a signed manifest without any per-site configuration.
	 *
	 * Pairs with the private half held as the UPDATE_SIGNING_KEY repository
	 * secret, which scripts/build-update-manifest.sh signs each release with.
	 * "Require a valid signature" defaults to on, and until this constant
	 * existed that meant every site silently rejected every update: the
	 * setting asked for a signature, no site had a public key configured, and
	 * verify_signature() has nothing to check a signature against without one.
	 * A key with nobody able to sign against it protects nothing — rotate it
	 * by generating a new pair, updating the UPDATE_SIGNING_KEY secret, and
	 * replacing the value here in the same release.
	 */
	const DEFAULT_PUBLIC_KEY = "-----BEGIN PUBLIC KEY-----\nMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAuzsXEB5kXRvx1DKKfKSL\nemiW+Rtm3PTNYRGLqVKZy9Fpr8bJ88hpaXSpDTCa5uJBSiruYZWpz3MkZRjGx8Na\n65CsE7fgiEJS8CqrJIZ2JtddgXXLxR4hvdMZOF2gzkZnUOPHEW0VgAEHSI8hrH5x\nvvWtM46Gm6rSlskN6Tqhhho9PhXTX9yZoY6qmkQ6azsz25Ny2LUjm7ABM2DTEqF1\nRBj1nLYa5EmGz//TebcbktoZMLvsY0CgNmrgZ81OzO9gLO35QtAda/KxPBPUnxsD\nTZ9DTiLhZLSFV/R8Cl3OQ08SJATp93lJBn02a083T+ONjOvuZvy5jQk6Fw+N3rr4\ngoYmm3DM/wrxVMZ4siBXDsxAE2ifN5nuIVwSWvsQO3sNuOemlENdEWeoaXwWmW1w\nzHCtxX6O3cvCdEUty9wTqoY0HTSXtpcYSGqhuMEiUFxa6wuW7B7Q5yjJGiymHxCm\n8cMkgLxhHvZ7MjF32r8YB306CggD7Ge98HiCHkzONV69fo2J3bF1EdsAaa/OBQtf\n1QGWY0QGZiXvr+O96NAE67nlpJNXYvJjCkiqnoCnjNLcYj1Kachzxi5cGPbYdgOk\nv9dT/q2VWSRtEjOpMkBddOxnwuHEAdrVbDWchM1b+Vq5UC3OFIoEHUwBjp2sphke\n1MIIg/kM/9gNJ+g57oEmHc0CAwEAAQ==\n-----END PUBLIC KEY-----";

	/**
	 * Site transient holding the last manifest read.
	 */
	const CACHE_KEY = 'piensa_cookie_consent_update';

	private $plugin_slug;
	private $cache_key = self::CACHE_KEY;

	public function __construct( $plugin_file ) {
		$this->plugin_slug = plugin_basename( $plugin_file );
	}

	public function init() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_updates' ] );
		add_filter( 'plugins_api', [ $this, 'plugins_api' ], 10, 3 );
		add_filter( 'upgrader_post_download', [ $this, 'verify_download' ], 10, 3 );
		add_filter( 'auto_update_plugin', [ $this, 'maybe_auto_update' ], 10, 2 );
	}

	/**
	 * Answer WordPress's own auto-update gate for this plugin specifically.
	 *
	 * $update already carries the site's own stored choice — whether an admin
	 * ticked "Enable auto-updates" for this plugin in the Plugins list — which
	 * is virtually always false, since nobody visits that screen. Every check
	 * this update passed to get here already confirmed the manifest checksum,
	 * and the signature too when one is required, so saying yes here is not
	 * skipping a check WordPress would otherwise have made — only setting the
	 * plugin's own default answer, the same as clicking that toggle would.
	 *
	 * @param bool|null $update Whether WordPress would auto-update this item.
	 * @param object    $item   The update object, as this plugin built it.
	 *
	 * @return bool|null
	 */
	public function maybe_auto_update( $update, $item ) {
		if ( ! isset( $item->plugin ) || $item->plugin !== $this->plugin_slug ) {
			return $update;
		}

		$settings = Piensa_Cookie_Consent_Admin::get_settings();

		return ! empty( $settings['enable_auto_update'] );
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

	/**
	 * Whether the site owner explicitly asked for a fresh check.
	 *
	 * WordPress's own "Check Again" link, on Dashboard → Updates, points at
	 * update-core.php?force-check=1 — meant to bypass every cache in the
	 * chain and show what is really available right now. Our own six-hour
	 * cache sat in front of that regardless of how check_updates() got
	 * invoked, so clicking the button showed the same stale verdict until the
	 * cache aged out on its own: indistinguishable, to the person clicking it,
	 * from the button doing nothing at all.
	 *
	 * @return bool
	 */
	private static function is_forced_check() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only: decides whether to bypass a cache, changes nothing itself.
		return isset( $_GET['force-check'] );
	}

	private function get_update_url( $settings ) {
		$url = isset( $settings['update_server_url'] ) ? trim( (string) $settings['update_server_url'] ) : '';

		if ( $url === '' ) {
			$url = self::DEFAULT_MANIFEST;
		}

		/**
		 * Filters the manifest this site reads its updates from.
		 *
		 * @param string $url The manifest address.
		 */
		$url = (string) apply_filters( 'piensa_cookie_consent_update_url', $url );

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
		if ( ! self::is_forced_check() ) {
			$cached = get_site_transient( $this->cache_key );
			if ( is_array( $cached ) && ! empty( $cached['version'] ) && ! empty( $cached['package'] ) ) {
				return $cached;
			}
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

		$payload = $this->build_signature_payload( $data );

		// A detached signature, which travels base64-encoded. Decoding it is
		// how the update is verified, not a way of hiding code.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
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

		return self::DEFAULT_PUBLIC_KEY;
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
