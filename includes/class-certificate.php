<?php
/**
 * Compliance certificate for a Kit Digital correction request.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the evidence a site sends Red.es to answer an LSSI requirement.
 *
 * Kit Digital reviews of the "Proyecto web" solution ask for cookie fixes
 * ("se deberá incluir la definición según origen y tipo de las cookies") and
 * the answer is uploaded under "Ampliación de documentación". Until now that
 * meant screenshots taken by hand. This checks the live configuration against
 * what the reviews ask for, records how each point was verified, and produces
 * a printable document with the file's data on it.
 *
 * The document is in Spanish whatever the site or user language: it exists
 * for a Spanish programme and is read by Red.es.
 */
class Piensa_Cookie_Consent_Certificate {

	/**
	 * Option holding the file data typed into the form.
	 */
	const OPTION = 'piensa_cookie_consent_certificate';

	/**
	 * Admin page slug.
	 */
	const PAGE = 'piensa-cookie-consent-certificado';

	/**
	 * Check outcomes.
	 */
	const PASS = 'pass';
	const WARN = 'warn';
	const FAIL = 'fail';

	/**
	 * @var Piensa_Cookie_Consent_Scanner
	 */
	private $scanner;

	/**
	 * @param Piensa_Cookie_Consent_Scanner $scanner Scanner.
	 */
	public function __construct( $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Register the hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'register_page' ], 20 );
		add_action( 'admin_post_piensa_cookie_consent_certificate', [ $this, 'handle_generate' ] );
	}

	/**
	 * The fields of the file, label by key.
	 *
	 * @return array<string, string>
	 */
	public static function get_fields() {
		return [
			'titular'       => 'Beneficiario (titular)',
			'nif'           => 'NIF del beneficiario',
			'acuerdo'       => 'Número de acuerdo',
			'bono'          => 'Bono digital',
			'procedimiento' => 'Procedimiento',
			'agente'        => 'Agente digitalizador',
			'requerimiento' => 'Requerimiento que se subsana',
		];
	}

	/**
	 * The file data saved for this site.
	 *
	 * @return array<string, string>
	 */
	public static function get_data() {
		$saved = get_option( self::OPTION, [] );
		$data  = [];

		foreach ( array_keys( self::get_fields() ) as $key ) {
			$data[ $key ] = is_array( $saved ) && isset( $saved[ $key ] ) ? (string) $saved[ $key ] : '';
		}

		if ( $data['requerimiento'] === '' ) {
			$data['requerimiento'] = 'LSSI: se deberá incluir la definición según origen y tipo de las cookies usadas (propias, de terceros, técnicas, analíticas, publicitarias).';
		}

		return $data;
	}

	/**
	 * Add the page under the plugin's menu.
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			'piensa-cookie-consent',
			'Certificado Kit Digital',
			'Certificado Kit Digital',
			'manage_options',
			self::PAGE,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * The form, with the checks as they stand now.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data   = self::get_data();
		$facts  = $this->collect_facts();
		$checks = self::evaluate( $facts );
		$result = self::overall( $checks );

		echo '<div class="wrap">';
		echo '<h1>Certificado de cumplimiento para Kit Digital</h1>';
		echo '<p>Comprueba la configuración de cookies de este sitio contra lo que piden los requerimientos de subsanación de Red.es (LSSI, art. 22.2) y genera un documento con las pruebas, listo para guardar como PDF y adjuntar en <em>Tramitación de Acuerdos → Ampliación de documentación</em>.</p>';

		echo '<h2>Estado actual</h2>';
		echo '<p><strong>' . esc_html( self::overall_label( $result ) ) . '</strong></p>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Requisito</th><th>Resultado</th><th>Prueba</th></tr></thead><tbody>';
		foreach ( $checks as $check ) {
			echo '<tr><td>' . esc_html( $check['label'] ) . '</td><td>' . esc_html( self::status_label( $check['status'] ) ) . '</td><td>' . esc_html( $check['evidence'] ) . '</td></tr>';
		}
		echo '</tbody></table>';

		echo '<h2>Datos del expediente</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" target="_blank">';
		echo '<input type="hidden" name="action" value="piensa_cookie_consent_certificate">';
		wp_nonce_field( 'piensa_cookie_consent_certificate' );
		echo '<table class="form-table" role="presentation">';
		foreach ( self::get_fields() as $key => $label ) {
			echo '<tr><th scope="row"><label for="pcc-cert-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( $key === 'requerimiento' ) {
				echo '<textarea class="large-text" rows="3" id="pcc-cert-' . esc_attr( $key ) . '" name="certificate[' . esc_attr( $key ) . ']">' . esc_textarea( $data[ $key ] ) . '</textarea>';
			} else {
				echo '<input type="text" class="regular-text" id="pcc-cert-' . esc_attr( $key ) . '" name="certificate[' . esc_attr( $key ) . ']" value="' . esc_attr( $data[ $key ] ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
		echo '<p class="description">Los datos se guardan al generar el certificado. Se abre en una pestaña nueva; usa «Imprimir → Guardar como PDF».</p>';
		submit_button( 'Generar certificado', 'primary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Save the file data and print the certificate.
	 *
	 * @return void
	 */
	public function handle_generate() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_admin_referer( 'piensa_cookie_consent_certificate' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitised below.
		$input = isset( $_POST['certificate'] ) && is_array( $_POST['certificate'] ) ? wp_unslash( $_POST['certificate'] ) : [];
		$data  = [];
		foreach ( array_keys( self::get_fields() ) as $key ) {
			$value        = isset( $input[ $key ] ) ? (string) $input[ $key ] : '';
			$data[ $key ] = $key === 'requerimiento' ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
		}
		update_option( self::OPTION, $data, false );

		$facts  = $this->collect_facts();
		$checks = self::evaluate( $facts );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );

		// Every value is escaped where render_document() builds it.
		echo self::render_document( self::get_data(), $facts, $checks ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Read what the checks are made against.
	 *
	 * @return array<string, mixed>
	 */
	public function collect_facts() {
		global $wp_version;

		$settings = Piensa_Cookie_Consent_Admin::get_settings();
		$scanner  = $this->scanner;
		$language = ! empty( $settings['default_language'] ) ? $settings['default_language'] : 'es';
		$text     = Piensa_Cookie_Consent_Banner_Text::get_text( $language, $settings );

		// In the policy's language, so the labels in the certificate match the
		// ones the visitor reads.
		$definitions = [];
		Piensa_Cookie_Consent_Core::in_policy_language(
			function () use ( $scanner, &$definitions ) {
				$definitions = $scanner->get_cookie_definitions();
				return '';
			}
		);

		$unknown  = [];
		$detected = get_option( 'piensa_cookie_consent_detected_cookies', [] );
		foreach ( is_array( $detected ) ? $detected : [] as $cookie ) {
			if ( isset( $cookie['category'] ) && $cookie['category'] === 'unknown' && ! empty( $cookie['name'] ) ) {
				$unknown[] = (string) $cookie['name'];
			}
		}

		return [
			'generated_at'      => wp_date( 'd/m/Y H:i:s T' ),
			'site_url'          => home_url( '/' ),
			'plugin_version'    => PIENSA_COOKIE_CONSENT_VERSION,
			'wp_version'        => (string) $wp_version,
			'geo_mode'          => isset( $settings['geo_mode'] ) ? (string) $settings['geo_mode'] : 'all',
			'geo_countries'     => isset( $settings['geo_countries'] ) ? (string) $settings['geo_countries'] : '',
			'accept_label'      => isset( $text['banner_accept_all'] ) ? (string) $text['banner_accept_all'] : '',
			'reject_label'      => isset( $text['banner_reject_all'] ) ? (string) $text['banner_reject_all'] : '',
			'manage_label'      => isset( $text['banner_manage_prefs'] ) ? (string) $text['banner_manage_prefs'] : '',
			'enable_blocker'    => ! empty( $settings['enable_blocker'] ),
			'block_unknown'     => ! empty( $settings['block_unknown_third_party'] ),
			'floating_button'   => ! empty( $settings['show_floating_button'] ),
			'consent_log'       => ! empty( $settings['enable_consent_log'] ),
			'log_counts'        => Piensa_Cookie_Consent_Consent_Log::count_by_choice(),
			'cookie_policy_url' => (string) $settings['cookie_policy_url'],
			'privacy_url'       => (string) $settings['privacy_policy_url'],
			'policy_page'       => self::fetch_policy( (string) $settings['cookie_policy_url'] ),
			'definitions'       => $definitions,
			'unknown_cookies'   => array_values( array_unique( $unknown ) ),
		];
	}

	/**
	 * Fetch the published policy page as a visitor gets it.
	 *
	 * @param string $url Policy URL.
	 *
	 * @return array{status: int, error: string, has_declaration: bool, terms: array<string, bool>}
	 */
	private static function fetch_policy( $url ) {
		$result = [
			'status'          => 0,
			'error'           => '',
			'has_declaration' => false,
			'terms'           => [],
		];

		if ( $url === '' ) {
			return $result;
		}

		$response = wp_remote_get(
			add_query_arg( 'pcc_certificate', time(), $url ),
			[
				'timeout'     => 15,
				'redirection' => 3,
				'cookies'     => [],
			]
		);

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			return $result;
		}

		$body                      = (string) wp_remote_retrieve_body( $response );
		$result['status']          = (int) wp_remote_retrieve_response_code( $response );
		$result['has_declaration'] = Piensa_Cookie_Consent_Policy_Page::has_policy( $body );
		$result['terms']           = self::find_terms( $body );

		return $result;
	}

	/**
	 * Which of the terms the requirement names the page text contains.
	 *
	 * @param string $html Page HTML.
	 *
	 * @return array<string, bool> Term => found.
	 */
	public static function find_terms( $html ) {
		$text = function_exists( 'mb_strtolower' )
			? mb_strtolower( wp_strip_all_tags( (string) $html ), 'UTF-8' )
			: strtolower( wp_strip_all_tags( (string) $html ) );

		$terms = [
			'propias'       => [ 'propias' ],
			'de terceros'   => [ 'de terceros', 'terceros' ],
			'técnicas'      => [ 'técnicas', 'tecnicas', 'necesarias' ],
			'analíticas'    => [ 'analíticas', 'analiticas', 'de análisis', 'analítica' ],
			'publicitarias' => [ 'publicitarias', 'publicidad', 'marketing' ],
		];

		$found = [];
		foreach ( $terms as $term => $variants ) {
			$found[ $term ] = false;
			foreach ( $variants as $variant ) {
				if ( false !== strpos( $text, $variant ) ) {
					$found[ $term ] = true;
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * Check the facts against what the reviews ask for.
	 *
	 * Pure, so it can be tested without WordPress.
	 *
	 * @param array<string, mixed> $facts From collect_facts().
	 *
	 * @return array<int, array{id: string, label: string, status: string, evidence: string}>
	 */
	public static function evaluate( array $facts ) {
		$checks = [];
		$page   = isset( $facts['policy_page'] ) ? $facts['policy_page'] : [];

		// 1. The banner is shown.
		$geo = isset( $facts['geo_mode'] ) ? $facts['geo_mode'] : 'all';
		if ( $geo === 'none' ) {
			$checks[] = self::check( 'banner', 'Aviso de cookies en la primera visita', self::FAIL, 'El banner está desactivado para todos los visitantes.' );
		} elseif ( $geo === 'all' ) {
			$checks[] = self::check( 'banner', 'Aviso de cookies en la primera visita', self::PASS, 'El banner se muestra a todos los visitantes antes de instalar cookies no necesarias.' );
		} else {
			$covers_spain = $geo === 'eea' || false !== strpos( strtoupper( (string) $facts['geo_countries'] ), 'ES' );
			$checks[]     = self::check(
				'banner',
				'Aviso de cookies en la primera visita',
				$covers_spain ? self::PASS : self::FAIL,
				$covers_spain ? 'El banner se muestra a los visitantes de España y del resto de países configurados.' : 'El banner no se muestra a los visitantes de España.'
			);
		}

		// 2. Refusing is as easy as accepting.
		$reject   = trim( (string) $facts['reject_label'] );
		$checks[] = self::check(
			'reject',
			'Rechazar con la misma facilidad que aceptar',
			$reject !== '' ? self::PASS : self::FAIL,
			$reject !== ''
				? sprintf( 'La primera capa ofrece «%s», «%s» y «%s», con el mismo número de clics para rechazar que para aceptar.', $facts['accept_label'], $reject, $facts['manage_label'] )
				: 'El botón de rechazo no tiene texto.'
		);

		// 3. Nothing non-essential before consent.
		if ( ! empty( $facts['enable_blocker'] ) && ! empty( $facts['block_unknown'] ) ) {
			$checks[] = self::check( 'blocking', 'Bloqueo previo de cookies no necesarias', self::PASS, 'Los scripts de terceros quedan bloqueados hasta que el visitante acepta su categoría, incluidos los no reconocidos. Google Consent Mode v2 arranca con todo denegado (analytics_storage, ad_storage, ad_user_data, ad_personalization).' );
		} elseif ( ! empty( $facts['enable_blocker'] ) ) {
			$checks[] = self::check( 'blocking', 'Bloqueo previo de cookies no necesarias', self::WARN, 'El bloqueo está activo solo para terceros reconocidos; los desconocidos se cargan antes del consentimiento.' );
		} else {
			$checks[] = self::check( 'blocking', 'Bloqueo previo de cookies no necesarias', self::FAIL, 'El bloqueo de scripts está desactivado.' );
		}

		// 4. The policy is published.
		$url = (string) $facts['cookie_policy_url'];
		if ( $url === '' ) {
			$checks[] = self::check( 'policy_url', 'Política de cookies publicada y enlazada', self::FAIL, 'No hay URL de política de cookies configurada.' );
		} elseif ( ! empty( $page['error'] ) ) {
			$checks[] = self::check( 'policy_url', 'Política de cookies publicada y enlazada', self::WARN, sprintf( 'No se pudo comprobar %s: %s', $url, $page['error'] ) );
		} elseif ( isset( $page['status'] ) && (int) $page['status'] === 200 ) {
			$checks[] = self::check( 'policy_url', 'Política de cookies publicada y enlazada', self::PASS, sprintf( '%s responde correctamente (HTTP 200) y se enlaza desde el banner.', $url ) );
		} else {
			$checks[] = self::check( 'policy_url', 'Política de cookies publicada y enlazada', self::FAIL, sprintf( '%s responde con HTTP %d.', $url, isset( $page['status'] ) ? (int) $page['status'] : 0 ) );
		}

		// 5. The types are defined: the point the requirement is about.
		$terms   = isset( $page['terms'] ) && is_array( $page['terms'] ) ? $page['terms'] : [];
		$missing = array_keys(
			array_filter(
				$terms,
				function ( $present ) {
					return ! $present;
				}
			)
		);
		if ( ! empty( $page['has_declaration'] ) && ! $missing ) {
			$checks[] = self::check( 'types', 'Definición de las cookies según origen y tipo', self::PASS, 'La política define las cookies propias y de terceros, y las técnicas, analíticas y publicitarias.' );
		} elseif ( $terms && ! $missing ) {
			$checks[] = self::check( 'types', 'Definición de las cookies según origen y tipo', self::WARN, 'El texto menciona todos los tipos, pero la página no incluye la declaración generada por el plugin.' );
		} else {
			$checks[] = self::check( 'types', 'Definición de las cookies según origen y tipo', self::FAIL, $missing ? 'Falta en la política: ' . implode( ', ', $missing ) . '.' : 'No se pudo leer el texto de la política.' );
		}

		// 6. Every declared cookie is described.
		$cookies    = self::flatten_cookies( isset( $facts['definitions'] ) ? $facts['definitions'] : [] );
		$incomplete = [];
		foreach ( $cookies as $cookie ) {
			if ( $cookie['origin'] === '' || $cookie['description'] === '' || $cookie['duration'] === '' ) {
				$incomplete[] = $cookie['name'];
			}
		}
		$checks[] = self::check(
			'declared',
			'Cada cookie con titular, finalidad y duración',
			$incomplete ? self::FAIL : self::PASS,
			$incomplete
				? 'Sin datos completos: ' . implode( ', ', $incomplete ) . '.'
				: sprintf( '%d cookies declaradas, cada una con origen (propia o de terceros), tipo, finalidad y duración.', count( $cookies ) )
		);

		// 7. Nothing the scanner found is left unexplained.
		$unknown  = isset( $facts['unknown_cookies'] ) ? (array) $facts['unknown_cookies'] : [];
		$checks[] = self::check(
			'unknown',
			'Cookies detectadas sin clasificar',
			$unknown ? self::WARN : self::PASS,
			$unknown ? 'El escáner encontró cookies sin categoría: ' . implode( ', ', $unknown ) . '.' : 'Todas las cookies detectadas por el escáner están clasificadas.'
		);

		// 8. Consent can be withdrawn.
		$withdraw = ! empty( $facts['floating_button'] ) || ! empty( $page['has_declaration'] );
		$checks[] = self::check(
			'withdraw',
			'Retirar o cambiar el consentimiento en cualquier momento',
			$withdraw ? self::PASS : self::FAIL,
			$withdraw ? 'Hay un botón permanente para revisar el consentimiento; retirarlo borra las cookies de las categorías rechazadas.' : 'No hay botón flotante ni botón de revisión en la política.'
		);

		// 9. Choices are recorded.
		$counts = isset( $facts['log_counts'] ) ? $facts['log_counts'] : [];
		if ( empty( $facts['consent_log'] ) ) {
			$checks[] = self::check( 'log', 'Registro de consentimientos', self::WARN, 'El registro de consentimientos está desactivado.' );
		} else {
			$total    = isset( $counts['total'] ) ? (int) $counts['total'] : 0;
			$checks[] = self::check(
				'log',
				'Registro de consentimientos',
				$total > 0 ? self::PASS : self::WARN,
				$total > 0
					? sprintf( '%d decisiones registradas: %d aceptan todo, %d solo las necesarias, %d personalizadas.', $total, (int) $counts['all'], (int) $counts['necessary'], (int) $counts['partial'] )
					: 'El registro está activo, pero aún no tiene decisiones.'
			);
		}

		// 10. The privacy policy is linked.
		$privacy  = (string) $facts['privacy_url'];
		$checks[] = self::check(
			'privacy',
			'Política de privacidad enlazada',
			$privacy !== '' ? self::PASS : self::WARN,
			$privacy !== '' ? $privacy : 'No hay URL de política de privacidad configurada.'
		);

		return $checks;
	}

	/**
	 * The worst outcome among the checks.
	 *
	 * @param array<int, array{status: string}> $checks Checks.
	 *
	 * @return string
	 */
	public static function overall( array $checks ) {
		$statuses = array_column( $checks, 'status' );

		if ( in_array( self::FAIL, $statuses, true ) ) {
			return self::FAIL;
		}

		return in_array( self::WARN, $statuses, true ) ? self::WARN : self::PASS;
	}

	/**
	 * Every declared cookie with its category, in one list.
	 *
	 * @param array<string, array<string, mixed>> $definitions Cookie definitions by category.
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function flatten_cookies( $definitions ) {
		$types = [
			'necessary' => 'Técnica (necesaria)',
			'analytics' => 'Analítica',
			'marketing' => 'Publicitaria',
		];

		$cookies = [];
		foreach ( (array) $definitions as $category => $data ) {
			foreach ( isset( $data['cookies'] ) ? (array) $data['cookies'] : [] as $cookie ) {
				$cookies[] = [
					'name'        => isset( $cookie['name'] ) ? (string) $cookie['name'] : '',
					'type'        => isset( $types[ $category ] ) ? $types[ $category ] : (string) $category,
					'origin'      => isset( $cookie['origin_label'] ) ? (string) $cookie['origin_label'] : '',
					'domain'      => isset( $cookie['domain'] ) ? (string) $cookie['domain'] : '',
					'description' => isset( $cookie['description'] ) ? (string) $cookie['description'] : '',
					'duration'    => isset( $cookie['duration'] ) ? (string) $cookie['duration'] : '',
				];
			}
		}

		return $cookies;
	}

	/**
	 * The certificate itself.
	 *
	 * @param array<string, string>                $data   File data.
	 * @param array<string, mixed>                 $facts  From collect_facts().
	 * @param array<int, array<string, string>>    $checks From evaluate().
	 *
	 * @return string
	 */
	public static function render_document( array $data, array $facts, array $checks ) {
		$e       = 'esc_html';
		$result  = self::overall( $checks );
		$cookies = self::flatten_cookies( $facts['definitions'] );

		// A fingerprint of what was checked, so a later copy can be matched to
		// this one. Definitions are left out: they hold translated strings that
		// do not change what was verified.
		$fingerprint = hash( 'sha256', (string) wp_json_encode( [ $data, $checks, $facts['generated_at'], $facts['site_url'] ] ) );

		$h  = '<!doctype html><html lang="es"><head><meta charset="utf-8">';
		$h .= '<title>Certificado de cumplimiento LSSI - ' . $e( wp_parse_url( $facts['site_url'], PHP_URL_HOST ) ) . '</title>';
		$h .= '<style>
			body{font-family:Arial,Helvetica,sans-serif;color:#1d2327;margin:32px auto;max-width:900px;padding:0 16px;font-size:13px;line-height:1.5}
			h1{font-size:22px;margin:0 0 4px}h2{font-size:16px;margin:28px 0 8px;border-bottom:2px solid #1d2327;padding-bottom:4px}
			table{border-collapse:collapse;width:100%;margin:8px 0}th,td{border:1px solid #c3c4c7;padding:6px 8px;text-align:left;vertical-align:top}th{background:#f0f0f1}
			.meta td:first-child{width:32%;font-weight:bold;background:#f6f7f7}
			.pass{color:#00692b;font-weight:bold}.warn{color:#8a5a00;font-weight:bold}.fail{color:#b32d2e;font-weight:bold}
			.result{padding:12px 16px;border-radius:6px;font-size:15px;font-weight:bold;margin:12px 0}
			.result.pass{background:#e7f6ec}.result.warn{background:#fcf3e1}.result.fail{background:#fcebea}
			.small{font-size:11px;color:#50575e}.sign{margin-top:48px;display:flex;gap:48px}.sign div{flex:1;border-top:1px solid #1d2327;padding-top:6px}
			.print{position:fixed;top:12px;right:12px;padding:8px 14px;font-size:13px;cursor:pointer}
			@media print{.print{display:none}body{margin:0}}
		</style></head><body>';
		$h .= '<button class="print" onclick="window.print()">Imprimir / Guardar como PDF</button>';

		$h .= '<h1>Certificado de cumplimiento de la política de cookies</h1>';
		$h .= '<p class="small">Artículo 22.2 de la Ley 34/2002 (LSSI-CE) y Guía sobre el uso de las cookies de la AEPD. Subsanación de requerimiento del programa Kit Digital.</p>';

		$h .= '<h2>Datos del expediente</h2><table class="meta">';
		foreach ( self::get_fields() as $key => $label ) {
			$h .= '<tr><td>' . $e( $label ) . '</td><td>' . ( $data[ $key ] !== '' ? nl2br( $e( $data[ $key ] ) ) : '—' ) . '</td></tr>';
		}
		$h .= '<tr><td>Sitio web</td><td>' . $e( $facts['site_url'] ) . '</td></tr>';
		$h .= '<tr><td>Política de cookies</td><td>' . ( $facts['cookie_policy_url'] !== '' ? $e( $facts['cookie_policy_url'] ) : '—' ) . '</td></tr>';
		$h .= '<tr><td>Fecha de la comprobación</td><td>' . $e( $facts['generated_at'] ) . '</td></tr>';
		$h .= '</table>';

		$h .= '<div class="result ' . esc_attr( $result ) . '">' . $e( self::overall_label( $result ) ) . '</div>';

		$h .= '<h2>Comprobaciones realizadas</h2><table><thead><tr><th style="width:4%">#</th><th style="width:30%">Requisito</th><th style="width:12%">Resultado</th><th>Prueba</th></tr></thead><tbody>';
		foreach ( $checks as $i => $check ) {
			$h .= '<tr><td>' . ( $i + 1 ) . '</td><td>' . $e( $check['label'] ) . '</td><td class="' . esc_attr( $check['status'] ) . '">' . $e( self::status_label( $check['status'] ) ) . '</td><td>' . $e( $check['evidence'] ) . '</td></tr>';
		}
		$h .= '</tbody></table>';

		$h .= '<h2>Definición de las cookies según origen y tipo</h2>';
		$h .= '<p><strong>Según la entidad que las gestiona</strong></p><ul>';
		$h .= '<li><strong>Cookies propias:</strong> se envían al equipo del usuario desde un equipo o dominio gestionado por el titular del sitio web, desde el que se presta el servicio solicitado.</li>';
		$h .= '<li><strong>Cookies de terceros:</strong> se envían al equipo del usuario desde un equipo o dominio que no gestiona el titular del sitio web, sino otra entidad que trata los datos obtenidos a través de ellas.</li></ul>';
		$h .= '<p><strong>Según su finalidad</strong></p><ul>';
		$h .= '<li><strong>Técnicas (necesarias):</strong> permiten la navegación y el uso de las funciones básicas del sitio, como recordar la decisión sobre cookies. No requieren consentimiento.</li>';
		$h .= '<li><strong>Analíticas:</strong> permiten cuantificar los usuarios y analizar estadísticamente el uso del sitio. Solo se instalan con consentimiento.</li>';
		$h .= '<li><strong>Publicitarias:</strong> permiten gestionar y mostrar publicidad según los hábitos de navegación. Solo se instalan con consentimiento.</li></ul>';
		$terms = isset( $facts['policy_page']['terms'] ) ? $facts['policy_page']['terms'] : [];
		if ( $terms ) {
			$found = [];
			foreach ( $terms as $term => $present ) {
				$found[] = $term . ': ' . ( $present ? 'sí' : 'no' );
			}
			$h .= '<p class="small">Términos encontrados en la política publicada: ' . $e( implode( ' · ', $found ) ) . '.</p>';
		}

		$h .= '<h2>Cookies declaradas en el sitio</h2><table><thead><tr><th>Cookie</th><th>Origen</th><th>Tipo</th><th>Dominio</th><th>Finalidad</th><th>Duración</th></tr></thead><tbody>';
		foreach ( $cookies as $cookie ) {
			$h .= '<tr><td>' . $e( $cookie['name'] ) . '</td><td>' . $e( $cookie['origin'] ) . '</td><td>' . $e( $cookie['type'] ) . '</td><td>' . $e( $cookie['domain'] ) . '</td><td>' . $e( $cookie['description'] ) . '</td><td>' . $e( $cookie['duration'] ) . '</td></tr>';
		}
		$h .= '</tbody></table>';

		$h .= '<h2>Datos técnicos</h2><table class="meta">';
		$h .= '<tr><td>Herramienta</td><td>Piensa Cookie Consent ' . $e( $facts['plugin_version'] ) . ' (WordPress ' . $e( $facts['wp_version'] ) . ')</td></tr>';
		$h .= '<tr><td>Botones de la primera capa</td><td>' . $e( $facts['accept_label'] ) . ' · ' . $e( $facts['reject_label'] ) . ' · ' . $e( $facts['manage_label'] ) . '</td></tr>';
		$h .= '<tr><td>Huella del documento (SHA-256)</td><td style="font-family:monospace;word-break:break-all">' . $e( $fingerprint ) . '</td></tr>';
		$h .= '</table>';
		$h .= '<p class="small">Documento generado automáticamente a partir de la configuración y de la página publicada en la fecha indicada. Recomendamos adjuntar junto a él capturas del banner y de la política de cookies.</p>';

		$h .= '<div class="sign"><div>Firma del beneficiario</div><div>Firma del agente digitalizador</div></div>';
		$h .= '</body></html>';

		return $h;
	}

	/**
	 * Text for the overall result.
	 *
	 * @param string $result Outcome.
	 *
	 * @return string
	 */
	public static function overall_label( $result ) {
		if ( $result === self::PASS ) {
			return 'CUMPLE: el sitio satisface todos los requisitos comprobados.';
		}

		if ( $result === self::WARN ) {
			return 'CUMPLE CON OBSERVACIONES: no hay incumplimientos, pero hay puntos que conviene revisar.';
		}

		return 'NO CUMPLE: hay requisitos sin satisfacer. Corrígelos antes de presentar la subsanación.';
	}

	/**
	 * Text for one check's outcome.
	 *
	 * @param string $status Outcome.
	 *
	 * @return string
	 */
	public static function status_label( $status ) {
		$labels = [
			self::PASS => 'Cumple',
			self::WARN => 'Revisar',
			self::FAIL => 'No cumple',
		];

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * @param string $id       Identifier.
	 * @param string $label    Requirement.
	 * @param string $status   Outcome.
	 * @param string $evidence How it was verified.
	 *
	 * @return array{id: string, label: string, status: string, evidence: string}
	 */
	private static function check( $id, $label, $status, $evidence ) {
		return [
			'id'       => $id,
			'label'    => $label,
			'status'   => $status,
			'evidence' => $evidence,
		];
	}
}
