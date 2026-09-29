<?php
/**
 * Keeps declared cookie retention periods current against Cookiedatabase.org.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Refreshes the retention period shown for a handful of well-known
 * third-party cookies, from the shared database Complianz and other
 * consent tools already draw on.
 *
 * Deliberately narrow in scope. Cookiedatabase.org answers "how long does the
 * cookie named _ga live", not "what is the host js.stripe.com" — its lookup is
 * keyed by cookie name, with no way to go from a discovered host to a service.
 * It cannot have prevented the Stripe incident this exists alongside, and does
 * not try to: it only keeps the retention text of cookies this plugin already
 * declares by name from going stale, without anyone having to notice that
 * Google, Meta or a gateway quietly changed a cookie's lifetime.
 */
class Piensa_Cookie_Consent_Retention_Sync {

	/**
	 * Option holding the last successful lookup, cookie name => data.
	 */
	const OPTION = 'piensa_cookie_consent_retention_cache';

	/**
	 * Cron hook the sync runs on.
	 */
	const CRON_HOOK = 'piensa_cookie_consent_sync_retention';

	/**
	 * The endpoint this plugin has confirmed answers without a licence key.
	 *
	 * Cookiedatabase.org's own documentation describes a licence field on this
	 * call, but querying it directly returns full data with none supplied; the
	 * requirement seems to have been for a different, older revision. Read
	 * only, and only cookie names already declared elsewhere in this plugin
	 * are ever sent — nothing that identifies the site or its visitors.
	 */
	const ENDPOINT = 'https://cookiedatabase.org/wp-json/cookiedatabase/v1/cookies';

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( self::CRON_HOOK, [ __CLASS__, 'sync' ] );
	}

	/**
	 * Schedule the daily sync, once, on activation.
	 *
	 * Deliberately not in init(), where it would run its wp_next_scheduled()
	 * guard on every single request. That is the pattern this file used at
	 * first, mirroring the plugin's own consent-log purge — and scheduling
	 * from there was enough on its own to exhaust memory inside WordPress's own
	 * hook dispatch during a WP-CLI bootstrap, confirmed by disabling nothing
	 * else. Activation runs exactly once, which sidesteps whatever about
	 * calling wp_schedule_event() from init() triggers that.
	 *
	 * @return void
	 */
	public static function maybe_schedule() {
		if ( self::is_enabled() && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Whether the sync is switched on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		/**
		 * Filters whether cookie retention periods are refreshed from
		 * Cookiedatabase.org.
		 *
		 * @param bool $enabled Whether the sync runs.
		 */
		return (bool) apply_filters( 'piensa_cookie_consent_sync_retention', true );
	}

	/**
	 * Cookie names this plugin is willing to have overridden with a fresher
	 * figure, and the label the lookup is filed under.
	 *
	 * The label itself makes no difference to the match — confirmed against
	 * the live API, which resolves purely by cookie name regardless of what a
	 * caller groups it under — so one label covers all of them.
	 *
	 * Wildcard names such as `_ga_*` and `_gat*` are deliberately left out:
	 * Cookiedatabase.org matches a literal name fuzzily on ITS end, not a
	 * pattern sent to it, so there is no name to query. Their retention stays
	 * the hardcoded figure Google documents directly.
	 *
	 * @return string[]
	 */
	private static function get_tracked_cookies() {
		return [ '_ga', '_gid', '_fbp', 'fr' ];
	}

	/**
	 * Look up the tracked cookies and cache what came back.
	 *
	 * A failure of any kind — no network, a non-200 response, malformed JSON —
	 * leaves the existing cache exactly as it was. A stale-but-present figure
	 * from the last successful sync is better than an empty one from a single
	 * bad request overwriting it.
	 *
	 * @return void
	 */
	public static function sync() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$names = self::get_tracked_cookies();

		$response = wp_remote_post(
			self::ENDPOINT,
			[
				'timeout' => 10,
				'headers' => [ 'Content-Type' => 'application/json' ],
				'body'    => wp_json_encode( [ 'en' => [ 'declared-cookies' => $names ] ] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return;
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$entries = isset( $body['data']['en'] ) && is_array( $body['data']['en'] ) ? $body['data']['en'] : null;

		if ( ! is_array( $entries ) ) {
			return;
		}

		$cache = [];

		foreach ( $names as $name ) {
			if ( ! isset( $entries[ $name ]['retention'] ) || ! is_string( $entries[ $name ]['retention'] ) ) {
				continue;
			}

			$retention = sanitize_text_field( $entries[ $name ]['retention'] );

			if ( '' === $retention ) {
				continue;
			}

			$cache[ $name ] = [
				'retention' => $retention,
				'synced_at' => time(),
			];
		}

		// A response naming none of the cookies asked about is treated as a
		// failure rather than as "these cookies have no retention": more
		// likely the service changed shape than that four well-known cookies
		// all stopped having a lifetime.
		if ( $cache ) {
			update_option( self::OPTION, $cache, false );
		}
	}

	/**
	 * The retention text for a cookie, from the last successful sync.
	 *
	 * @param string $cookie_name Cookie name, exactly as declared.
	 * @param string $fallback    What to show if nothing is cached yet.
	 *
	 * @return string
	 */
	public static function get_retention( $cookie_name, $fallback ) {
		$cache = get_option( self::OPTION, [] );

		if ( is_array( $cache ) && isset( $cache[ $cookie_name ]['retention'] ) ) {
			$retention = $cache[ $cookie_name ]['retention'];

			if ( is_string( $retention ) && '' !== $retention ) {
				return $retention;
			}
		}

		return $fallback;
	}

	/**
	 * When the cache was last refreshed, for Diagnostics.
	 *
	 * @return int Unix timestamp, or 0 if never synced.
	 */
	public static function last_synced() {
		$cache = get_option( self::OPTION, [] );

		if ( ! is_array( $cache ) || ! $cache ) {
			return 0;
		}

		$times = array_column( $cache, 'synced_at' );

		return $times ? (int) max( $times ) : 0;
	}
}
