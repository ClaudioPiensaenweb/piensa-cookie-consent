<?php
/**
 * Admin screens, settings registration and the tools they expose.
 *
 * @package Piensa_Cookie_Consent
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Piensa_Cookie_Consent_Admin {
	private $option_name = 'piensa_cookie_consent_settings';
	private $menu_hook   = '';

	public function init() {
		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'register_menu' ] );
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'update_option_piensa_cookie_consent_settings', [ __CLASS__, 'flush_settings_cache' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
			add_action( 'admin_post_piensa_cookie_consent_scan', [ $this, 'handle_scan_request' ] );
			add_action( 'admin_post_piensa_cookie_consent_export_logs', [ $this, 'handle_export_logs' ] );
			add_action( 'admin_post_piensa_cookie_consent_export_settings', [ $this, 'handle_export_settings' ] );
			add_action( 'admin_post_piensa_cookie_consent_import_settings', [ $this, 'handle_import_settings' ] );
			add_action( 'admin_post_piensa_cookie_consent_report_html', [ $this, 'handle_report_html' ] );
			add_action( 'admin_post_piensa_cookie_consent_report_json', [ $this, 'handle_report_json' ] );
			add_action( 'wp_ajax_piensa_cookie_consent_preview', [ $this, 'render_preview' ] );
			add_action( 'wp_ajax_piensa_cookie_consent_collect_cookies', [ $this, 'handle_collect_cookies' ] );
		}
	}

	public function register_menu() {
		// Top-level menu, so the CMP is not buried under Settings.
		$this->menu_hook = add_menu_page(
			esc_html__( 'Piensa Cookie Consent', 'piensa-cookie-consent' ),
			esc_html__( 'Piensa Cookie Consent', 'piensa-cookie-consent' ),
			'manage_options',
			'piensa-cookie-consent',
			[ $this, 'render_settings_page' ],
			'dashicons-admin-generic',
			80
		);

		// Sub-pages under the top-level entry.
		add_submenu_page(
			'piensa-cookie-consent',
			'Dashboard',
			'Dashboard',
			'manage_options',
			'piensa-cookie-consent'
		);

		add_submenu_page(
			'piensa-cookie-consent',
			esc_html__( 'Appearance', 'piensa-cookie-consent' ),
			esc_html__( 'Appearance', 'piensa-cookie-consent' ),
			'manage_options',
			'piensa-cookie-consent&tab=apariencia',
			[ $this, 'render_settings_page' ]
		);

		add_submenu_page(
			'piensa-cookie-consent',
			'Scanner',
			'Scanner',
			'manage_options',
			'piensa-cookie-consent&tab=scanner',
			[ $this, 'render_settings_page' ]
		);

		add_submenu_page(
			'piensa-cookie-consent',
			'Logs',
			'Logs',
			'manage_options',
			'piensa-cookie-consent&tab=logs',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_settings() {
		register_setting(
			'piensa_cookie_consent_settings',
			$this->option_name,
			[
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
			]
		);

		add_settings_section(
			'piensa_cookie_consent_main',
			esc_html__( 'General settings', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Configure how the CMP behaves.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_blocker',
			esc_html__( 'Content blocking', 'piensa-cookie-consent' ),
			[ $this, 'render_blocker_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_main'
		);

		add_settings_field(
			'piensa_cookie_consent_log_retention',
			esc_html__( 'Consent log retention', 'piensa-cookie-consent' ),
			[ $this, 'render_log_retention_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_main'
		);

		add_settings_field(
			'piensa_cookie_consent_block_unknown',
			esc_html__( 'Unrecognised third parties', 'piensa-cookie-consent' ),
			[ $this, 'render_block_unknown_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_main'
		);

		add_settings_field(
			'piensa_cookie_consent_allowed_domains',
			esc_html__( 'Technical exceptions', 'piensa-cookie-consent' ),
			[ $this, 'render_allowed_domains_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_main'
		);

		add_settings_field(
			'piensa_cookie_consent_log',
			esc_html__( 'Consent log', 'piensa-cookie-consent' ),
			[ $this, 'render_consent_log_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_main'
		);

		add_settings_field(
			'piensa_cookie_consent_policy_revision',
			esc_html__( 'Consent review', 'piensa-cookie-consent' ),
			[ $this, 'render_policy_revision_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_main'
		);

		add_settings_section(
			'piensa_cookie_consent_banner',
			esc_html__( 'Banner text', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Customize the banner and the preferences dialog.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_banner_icon',
			esc_html__( 'Banner icon', 'piensa-cookie-consent' ),
			[ $this, 'render_banner_icon_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_banner'
		);

		add_settings_field(
			'piensa_cookie_consent_banner_title',
			esc_html__( 'Banner title', 'piensa-cookie-consent' ),
			[ $this, 'render_banner_title_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_banner'
		);

		add_settings_field(
			'piensa_cookie_consent_banner_description',
			esc_html__( 'Banner description', 'piensa-cookie-consent' ),
			[ $this, 'render_banner_description_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_banner'
		);

		add_settings_field(
			'piensa_cookie_consent_banner_accept_all',
			esc_html__( 'Accept all label', 'piensa-cookie-consent' ),
			[ $this, 'render_banner_accept_all_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_banner'
		);

		add_settings_field(
			'piensa_cookie_consent_banner_reject_all',
			esc_html__( 'Reject non-essential label', 'piensa-cookie-consent' ),
			[ $this, 'render_banner_reject_all_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_banner'
		);

		add_settings_field(
			'piensa_cookie_consent_banner_manage',
			esc_html__( 'Manage preferences label', 'piensa-cookie-consent' ),
			[ $this, 'render_banner_manage_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_banner'
		);

		add_settings_field(
			'piensa_cookie_consent_banner_save',
			esc_html__( 'Save preferences label', 'piensa-cookie-consent' ),
			[ $this, 'render_banner_save_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_banner'
		);

		add_settings_field(
			'piensa_cookie_consent_banner_preferences_title',
			esc_html__( 'Modal title', 'piensa-cookie-consent' ),
			[ $this, 'render_banner_preferences_title_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_banner'
		);

		add_settings_section(
			'piensa_cookie_consent_categories',
			esc_html__( 'Categories and behavior', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Set the labels and the behavior of each category.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_category_mode',
			esc_html__( 'Category mode', 'piensa-cookie-consent' ),
			[ $this, 'render_category_mode_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_categories'
		);

		add_settings_field(
			'piensa_cookie_consent_necessary_toggle',
			esc_html__( 'Necessary cookies', 'piensa-cookie-consent' ),
			[ $this, 'render_necessary_toggle_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_categories'
		);

		add_settings_field(
			'piensa_cookie_consent_category_toggles',
			esc_html__( 'Active categories', 'piensa-cookie-consent' ),
			[ $this, 'render_category_toggles_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_categories'
		);

		add_settings_field(
			'piensa_cookie_consent_category_labels',
			esc_html__( 'Labels and text', 'piensa-cookie-consent' ),
			[ $this, 'render_category_labels_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_categories'
		);

		add_settings_section(
			'piensa_cookie_consent_appearance',
			esc_html__( 'Appearance and position', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Controls the banner position and the main styles.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_layout_position',
			esc_html__( 'Position and layout', 'piensa-cookie-consent' ),
			[ $this, 'render_layout_position_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_appearance'
		);

		add_settings_field(
			'piensa_cookie_consent_theme_colors',
			esc_html__( 'Colors and corner radii', 'piensa-cookie-consent' ),
			[ $this, 'render_theme_colors_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_appearance'
		);

		add_settings_field(
			'piensa_cookie_consent_theme_presets',
			esc_html__( 'Quick templates', 'piensa-cookie-consent' ),
			[ $this, 'render_theme_presets_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_appearance'
		);

		add_settings_field(
			'piensa_cookie_consent_theme_preview',
			'Preview',
			[ $this, 'render_theme_preview_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_appearance'
		);

		add_settings_section(
			'piensa_cookie_consent_compliance',
			esc_html__( 'Compliance and privacy', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Set geo-targeting, language and legal requirements.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_geo_targeting',
			'Geo-targeting',
			[ $this, 'render_geo_targeting_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_compliance'
		);

		add_settings_field(
			'piensa_cookie_consent_language',
			esc_html__( 'Language and detection', 'piensa-cookie-consent' ),
			[ $this, 'render_language_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_compliance'
		);

		add_settings_field(
			'piensa_cookie_consent_report',
			esc_html__( 'Compliance report', 'piensa-cookie-consent' ),
			[ $this, 'render_report_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_compliance'
		);

		add_settings_section(
			'piensa_cookie_consent_languages',
			esc_html__( 'Multilingual text', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Set the Spanish and English text.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_texts_en',
			esc_html__( 'English text', 'piensa-cookie-consent' ),
			[ $this, 'render_texts_en_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_languages'
		);

		add_settings_section(
			'piensa_cookie_consent_branding',
			esc_html__( 'Branding and white-label', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Customize the name and hide branding.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_branding_fields',
			'Branding',
			[ $this, 'render_branding_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_branding'
		);

		add_settings_section(
			'piensa_cookie_consent_tools',
			'Importar / Exportar',
			function () {
				echo '<p>' . esc_html__( 'Export or import the full configuration.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_export',
			'Exportar',
			[ $this, 'render_export_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_tools'
		);

		add_settings_field(
			'piensa_cookie_consent_import',
			'Importar',
			[ $this, 'render_import_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_tools'
		);

		add_settings_section(
			'piensa_cookie_consent_health',
			'Health check',
			function () {
				echo '<p>' . esc_html__( 'Review possible compliance risks.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_health_view',
			'Estado',
			[ $this, 'render_health_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_health'
		);

		add_settings_section(
			'piensa_cookie_consent_content',
			esc_html__( 'Blocked content', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Domains to neutralize until consent is given.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_domains',
			esc_html__( 'Blocked domains', 'piensa-cookie-consent' ),
			[ $this, 'render_domains_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_content'
		);

		add_settings_section(
			'piensa_cookie_consent_texts',
			esc_html__( 'Placeholder text', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Customize the message shown over blocked content.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_placeholder_title',
			esc_html__( 'Main message', 'piensa-cookie-consent' ),
			[ $this, 'render_placeholder_title_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_texts'
		);

		add_settings_field(
			'piensa_cookie_consent_placeholder_button',
			esc_html__( 'Button label', 'piensa-cookie-consent' ),
			[ $this, 'render_placeholder_button_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_texts'
		);

		add_settings_section(
			'piensa_cookie_consent_ui',
			esc_html__( 'Consent interface', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Controls the floating button that reopens the consent dialog.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_floating_button',
			esc_html__( 'Floating button', 'piensa-cookie-consent' ),
			[ $this, 'render_floating_button_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_ui'
		);

		add_settings_field(
			'piensa_cookie_consent_floating_button_text',
			esc_html__( 'Button label', 'piensa-cookie-consent' ),
			[ $this, 'render_floating_button_text_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_ui'
		);

		add_settings_field(
			'piensa_cookie_consent_floating_button_style',
			esc_html__( 'Button style', 'piensa-cookie-consent' ),
			[ $this, 'render_floating_button_style_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_ui'
		);

		add_settings_section(
			'piensa_cookie_consent_discovery',
			esc_html__( 'Automatic discovery', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'External resources such as scripts, iframes and images are detected to suggest categories.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_discovered',
			esc_html__( 'Detected domains', 'piensa-cookie-consent' ),
			[ $this, 'render_discovered_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_discovery'
		);

		add_settings_field(
			'piensa_cookie_consent_cookie_audit',
			esc_html__( 'In-browser audit', 'piensa-cookie-consent' ),
			[ $this, 'render_cookie_audit_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_discovery'
		);

		add_settings_field(
			'piensa_cookie_consent_detected_cookies',
			esc_html__( 'Detected cookies', 'piensa-cookie-consent' ),
			[ $this, 'render_detected_cookies_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_discovery'
		);

		add_settings_section(
			'piensa_cookie_consent_policy',
			esc_html__( 'Cookie policy', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Link your policy and declare custom cookies.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_cookie_policy_url',
			esc_html__( 'Cookie policy URL', 'piensa-cookie-consent' ),
			[ $this, 'render_cookie_policy_url_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_policy'
		);

		add_settings_field(
			'piensa_cookie_consent_privacy_policy_url',
			esc_html__( 'Privacy policy URL', 'piensa-cookie-consent' ),
			[ $this, 'render_privacy_policy_url_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_policy'
		);

		add_settings_field(
			'piensa_cookie_consent_custom_cookies',
			esc_html__( 'Custom cookies', 'piensa-cookie-consent' ),
			[ $this, 'render_custom_cookies_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_policy'
		);

		add_settings_section(
			'piensa_cookie_consent_logs',
			esc_html__( 'Consent records', 'piensa-cookie-consent' ),
			function () {
				echo '<p>Ultimos consentimientos registrados.</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_logs_table',
			'Logs',
			[ $this, 'render_logs_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_logs'
		);

		// The update server settings only make sense in the build that
		// ships the updater; the wp.org package has none.
		if ( ! Piensa_Cookie_Consent_Core::has_self_hosted_updater() ) {
			return;
		}

		add_settings_section(
			'piensa_cookie_consent_updates',
			esc_html__( 'Secure updates', 'piensa-cookie-consent' ),
			function () {
				echo '<p>' . esc_html__( 'Configure the central update server and its signature.', 'piensa-cookie-consent' ) . '</p>';
			},
			'piensa-cookie-consent'
		);

		add_settings_field(
			'piensa_cookie_consent_update_server',
			esc_html__( 'Update server', 'piensa-cookie-consent' ),
			[ $this, 'render_update_server_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_updates'
		);

		add_settings_field(
			'piensa_cookie_consent_update_channel',
			esc_html__( 'Channel', 'piensa-cookie-consent' ),
			[ $this, 'render_update_channel_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_updates'
		);

		add_settings_field(
			'piensa_cookie_consent_update_token',
			esc_html__( 'Access token', 'piensa-cookie-consent' ),
			[ $this, 'render_update_token_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_updates'
		);

		add_settings_field(
			'piensa_cookie_consent_update_public_key',
			esc_html__( 'Public key', 'piensa-cookie-consent' ),
			[ $this, 'render_update_public_key_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_updates'
		);

		add_settings_field(
			'piensa_cookie_consent_update_signature',
			esc_html__( 'Signature verification', 'piensa-cookie-consent' ),
			[ $this, 'render_update_signature_field' ],
			'piensa-cookie-consent',
			'piensa_cookie_consent_updates'
		);
	}

	public function render_blocker_field() {
		$settings = self::get_settings();
		$checked  = $settings['enable_blocker'] ? 'checked' : '';
		echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[enable_blocker]" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Block external iframes', 'piensa-cookie-consent' ) . '</label>';
	}

	public function render_log_retention_field() {
		$settings = self::get_settings();
		$value    = isset( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 0;
		echo '<input class="small-text" type="number" min="0" max="3650" name="' . esc_attr( $this->option_name ) . '[log_retention_days]" value="' . esc_attr( (string) $value ) . '" /> ';
		echo esc_html__( 'days', 'piensa-cookie-consent' );
		echo '<p class="description">' . esc_html__( 'Records older than this are deleted daily. Keeping consent records indefinitely is itself a compliance problem: the GDPR asks for a defined retention period. Set to 0 to keep everything, which you should only do if something else purges the table.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_block_unknown_field() {
		$settings = self::get_settings();
		$checked  = ! empty( $settings['block_unknown_third_party'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[block_unknown_third_party]" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Block third-party resources the plugin does not recognise', 'piensa-cookie-consent' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Required to comply: without it, a third party the plugin has never seen loads before the visitor chooses. Unrecognised third parties are treated as marketing and load once that category is accepted.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_allowed_domains_field() {
		$settings = self::get_settings();
		$value    = isset( $settings['allowed_domains'] ) ? $settings['allowed_domains'] : '';
		echo '<textarea class="large-text code" rows="6" name="' . esc_attr( $this->option_name ) . '[allowed_domains]">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Third-party hosts that serve no cookies and are never blocked, one per line. Intended for asset CDNs and font providers, not for analytics or advertising.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_consent_log_field() {
		$settings = self::get_settings();
		$checked  = $settings['enable_consent_log'] ? 'checked' : '';
		echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[enable_consent_log]" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Keep a consent log', 'piensa-cookie-consent' ) . '</label>';
	}

	public function render_policy_revision_field() {
		$settings = self::get_settings();
		$value    = (int) $settings['policy_revision'];
		echo '<input class="small-text" type="number" min="0" name="' . esc_attr( $this->option_name ) . '[policy_revision]" value="' . esc_attr( (string) $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Increase this value when you change the text or the policy, to ask visitors for consent again.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_banner_icon_field() {
		$settings   = self::get_settings();
		$show_icon  = ! empty( $settings['banner_show_icon'] );
		$icon_style = isset( $settings['banner_icon_style'] ) ? $settings['banner_icon_style'] : 'cookie';

		echo '<div class="ag-field-group">';
		echo '<label style="display:block;margin-bottom:12px;"><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[banner_show_icon]" value="1" ' . ( $show_icon ? 'checked' : '' ) . '> ' . esc_html__( 'Show an icon in the banner', 'piensa-cookie-consent' ) . '</label>';
		echo '<div class="ag-icon-preview" style="display:flex;gap:12px;align-items:stretch;flex-wrap:wrap;">';

		$icons      = Piensa_Cookie_Consent_Icons::get_choices();
		$icon_style = Piensa_Cookie_Consent_Icons::resolve( $icon_style );

		foreach ( $icons as $key => $label ) {
			$checked      = $icon_style === $key ? 'checked' : '';
			$border_color = $icon_style === $key ? '#2271b1' : '#dcdcde';
			$bg_color     = $icon_style === $key ? '#f0f6fc' : '#fff';
			echo '<label style="display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;padding:14px 16px;border:2px solid ' . esc_attr( $border_color ) . ';border-radius:10px;min-width:90px;background:' . esc_attr( $bg_color ) . ';transition:all 0.15s ease;">';
			echo '<input type="radio" name="' . esc_attr( $this->option_name ) . '[banner_icon_style]" value="' . esc_attr( $key ) . '" ' . esc_attr( $checked ) . ' style="display:none;">';
			// The icon markup is built from a fixed internal table, not user input.
			echo wp_kses( Piensa_Cookie_Consent_Icons::get( $key, 28 ), Piensa_Cookie_Consent_Icons::get_allowed_html() );
			echo '<span style="font-size:11px;color:#50575e;font-weight:500;">' . esc_html( $label ) . '</span>';
			echo '</label>';
		}

		echo '</div>';
		echo '<p class="description" style="margin-top:12px;">' . esc_html__( 'Appears next to the banner title.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
	}

	public function render_banner_title_field() {
		$settings = self::get_settings();
		$value    = $settings['banner_title'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_title]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_banner_description_field() {
		$settings = self::get_settings();
		$value    = esc_textarea( $settings['banner_description'] );
		echo '<textarea class="large-text" rows="3" name="' . esc_attr( $this->option_name ) . '[banner_description]">' . esc_attr( $value ) . '</textarea>';
	}

	public function render_banner_accept_all_field() {
		$settings = self::get_settings();
		$value    = $settings['banner_accept_all'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_accept_all]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_banner_reject_all_field() {
		$settings = self::get_settings();
		$value    = $settings['banner_reject_all'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_reject_all]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_banner_manage_field() {
		$settings = self::get_settings();
		$value    = $settings['banner_manage_prefs'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_manage_prefs]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_banner_save_field() {
		$settings = self::get_settings();
		$value    = $settings['banner_save_prefs'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_save_prefs]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_banner_preferences_title_field() {
		$settings = self::get_settings();
		$value    = $settings['banner_preferences_title'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_preferences_title]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_category_mode_field() {
		$settings = self::get_settings();
		$value    = $settings['category_mode'];
		echo '<select name="' . esc_attr( $this->option_name ) . '[category_mode]">';
		echo '<option value="auto"' . selected( $value, 'auto', false ) . '>' . esc_html__( 'Auto (detect plugins)', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="manual"' . selected( $value, 'manual', false ) . '>Manual</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'In manual mode you decide which categories are shown.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_necessary_toggle_field() {
		$settings = self::get_settings();
		$checked  = ! empty( $settings['allow_necessary_toggle'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[allow_necessary_toggle]" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Let visitors disable necessary cookies', 'piensa-cookie-consent' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Not recommended: it can break parts of the site.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_category_toggles_field() {
		$settings  = self::get_settings();
		$analytics = ! empty( $settings['analytics_enabled'] ) ? 'checked' : '';
		$marketing = ! empty( $settings['marketing_enabled'] ) ? 'checked' : '';
		echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[analytics_enabled]" value="1" ' . esc_attr( $analytics ) . '> Analytics</label>';
		echo '<label style="display:block;"><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[marketing_enabled]" value="1" ' . esc_attr( $marketing ) . '> Marketing</label>';
	}

	public function render_category_labels_field() {
		$settings = self::get_settings();
		echo '<div class="ag-field-group">';
		echo '<strong>' . esc_html__( 'Necessary', 'piensa-cookie-consent' ) . '</strong>';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[necessary_label]" value="' . esc_attr( $settings['necessary_label'] ) . '" />';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[necessary_description]">' . esc_textarea( $settings['necessary_description'] ) . '</textarea>';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[necessary_legal_note]" placeholder="' . esc_attr__( 'Legal note (optional)', 'piensa-cookie-consent' ) . '">' . esc_textarea( $settings['necessary_legal_note'] ) . '</textarea>';
		echo '</div>';
		echo '<div class="ag-field-group">';
		echo '<strong>' . esc_html__( 'Analytics', 'piensa-cookie-consent' ) . '</strong>';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[analytics_label]" value="' . esc_attr( $settings['analytics_label'] ) . '" />';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[analytics_description]">' . esc_textarea( $settings['analytics_description'] ) . '</textarea>';
		echo '</div>';
		echo '<div class="ag-field-group">';
		echo '<strong>Marketing</strong>';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[marketing_label]" value="' . esc_attr( $settings['marketing_label'] ) . '" />';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[marketing_description]">' . esc_textarea( $settings['marketing_description'] ) . '</textarea>';
		echo '</div>';
	}

	public function render_layout_position_field() {
		$settings             = self::get_settings();
		$consent_layout       = $settings['consent_layout'];
		$consent_position     = $settings['consent_position'];
		$preferences_layout   = $settings['preferences_layout'];
		$preferences_position = $settings['preferences_position'];

		echo '<div class="ag-field-group">';
		echo '<strong>' . esc_html__( 'Banner', 'piensa-cookie-consent' ) . '</strong>';
		echo '<select name="' . esc_attr( $this->option_name ) . '[consent_layout]">';
		echo '<option value="box"' . selected( $consent_layout, 'box', false ) . '>Box</option>';
		echo '<option value="cloud"' . selected( $consent_layout, 'cloud', false ) . '>Cloud</option>';
		echo '<option value="bar"' . selected( $consent_layout, 'bar', false ) . '>Bar</option>';
		echo '</select> ';
		echo '<select name="' . esc_attr( $this->option_name ) . '[consent_position]">';
		echo '<option value="bottom right"' . selected( $consent_position, 'bottom right', false ) . '>' . esc_html__( 'Bottom right', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="bottom left"' . selected( $consent_position, 'bottom left', false ) . '>' . esc_html__( 'Bottom left', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="bottom center"' . selected( $consent_position, 'bottom center', false ) . '>' . esc_html__( 'Bottom center', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="top right"' . selected( $consent_position, 'top right', false ) . '>' . esc_html__( 'Top right', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="top left"' . selected( $consent_position, 'top left', false ) . '>' . esc_html__( 'Top left', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="top center"' . selected( $consent_position, 'top center', false ) . '>' . esc_html__( 'Top center', 'piensa-cookie-consent' ) . '</option>';
		echo '</select>';
		echo '</div>';

		echo '<div class="ag-field-group">';
		echo '<strong>' . esc_html__( 'Preferences', 'piensa-cookie-consent' ) . '</strong>';
		echo '<select name="' . esc_attr( $this->option_name ) . '[preferences_layout]">';
		echo '<option value="box"' . selected( $preferences_layout, 'box', false ) . '>Box</option>';
		echo '<option value="bar"' . selected( $preferences_layout, 'bar', false ) . '>Bar</option>';
		echo '</select> ';
		echo '<select name="' . esc_attr( $this->option_name ) . '[preferences_position]">';
		echo '<option value="right"' . selected( $preferences_position, 'right', false ) . '>Derecha</option>';
		echo '<option value="left"' . selected( $preferences_position, 'left', false ) . '>Izquierda</option>';
		echo '<option value="center"' . selected( $preferences_position, 'center', false ) . '>Centro</option>';
		echo '</select>';
		echo '</div>';
	}

	public function render_theme_colors_field() {
		$settings = self::get_settings();
		$fields   = [
			[
				'key'   => 'theme_bg',
				'label' => __( 'Modal background', 'piensa-cookie-consent' ),
				'var'   => '--cc-bg',
			],
			[
				'key'   => 'theme_primary_color',
				'label' => __( 'Main text', 'piensa-cookie-consent' ),
				'var'   => '--cc-primary-color',
			],
			[
				'key'   => 'theme_secondary_color',
				'label' => __( 'Secondary text', 'piensa-cookie-consent' ),
				'var'   => '--cc-secondary-color',
			],
			[
				'key'   => 'theme_btn_primary_bg',
				'label' => __( 'Primary button', 'piensa-cookie-consent' ),
				'var'   => '--cc-btn-primary-bg',
			],
			[
				'key'   => 'theme_btn_primary_color',
				'label' => __( 'Primary button label', 'piensa-cookie-consent' ),
				'var'   => '--cc-btn-primary-color',
			],
			[
				'key'   => 'theme_btn_secondary_bg',
				'label' => __( 'Secondary button', 'piensa-cookie-consent' ),
				'var'   => '--cc-btn-secondary-bg',
			],
			[
				'key'   => 'theme_btn_secondary_color',
				'label' => __( 'Secondary button label', 'piensa-cookie-consent' ),
				'var'   => '--cc-btn-secondary-color',
			],
		];

		echo '<div class="ag-color-editor">';
		foreach ( $fields as $field ) {
			$key   = $field['key'];
			$value = $settings[ $key ];
			echo '<div class="ag-color-item">';
			echo '<div class="ag-color-meta">';
			echo '<strong>' . esc_html( $field['label'] ) . '</strong>';
			echo '<code>' . esc_html( $field['var'] ) . '</code>';
			echo '</div>';
			echo '<div class="ag-color-controls">';
			echo '<input type="color" data-ag-color="' . esc_attr( $key ) . '" name="' . esc_attr( $this->option_name ) . '[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" />';
			echo '<input type="text" class="regular-text ag-color-text" data-ag-color-text="' . esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" placeholder="#ffffff" />';
			echo '</div>';
			echo '</div>';
		}
		echo '</div>';
		echo '<div class="ag-field-grid" style="margin-top:12px;">';
		echo '<label>Radio modal (px) <input class="small-text" type="number" min="0" name="' . esc_attr( $this->option_name ) . '[theme_modal_radius]" value="' . esc_attr( $settings['theme_modal_radius'] ) . '" /></label>';
		echo '<label>Radio botones (px) <input class="small-text" type="number" min="0" name="' . esc_attr( $this->option_name ) . '[theme_button_radius]" value="' . esc_attr( $settings['theme_button_radius'] ) . '" /></label>';
		echo '</div>';
	}

	public function render_theme_presets_field() {
		$presets = self::get_theme_presets();
		echo '<div class="ag-field-row">';
		echo '<select class="ag-preset-select" name="piensa_cookie_consent_preset_select">';
		foreach ( $presets as $key => $preset ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $preset['label'] ) . '</option>';
		}
		echo '</select>';
		echo '<button type="button" class="button ag-apply-preset" data-ag-apply-preset="1">Aplicar plantilla</button>';
		echo '<span class="description">Aplica colores, radios y layouts.</span>';
		echo '</div>';
	}

	public function render_theme_preview_field() {
		$preview_url = admin_url( 'admin-ajax.php?action=piensa_cookie_consent_preview' );
		$nonce       = wp_create_nonce( 'piensa_cookie_consent_preview' );
		echo '<div class="ag-preview-frame-wrap">';
		echo '<iframe class="ag-preview-frame" data-ag-preview-frame src="' . esc_url( $preview_url . '&nonce=' . $nonce ) . '" loading="lazy"></iframe>';
		echo '</div>';
		echo '<p class="description">' . esc_html__( 'A live preview of the banner. It updates as you change the fields.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_geo_targeting_field() {
		$settings  = self::get_settings();
		$mode      = $settings['geo_mode'];
		$countries = esc_textarea( $settings['geo_countries'] );
		$header    = $settings['geo_header'];

		echo '<div class="ag-field-group">';
		echo '<select name="' . esc_attr( $this->option_name ) . '[geo_mode]">';
		echo '<option value="all"' . selected( $mode, 'all', false ) . '>' . esc_html__( 'Always show', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="eea"' . selected( $mode, 'eea', false ) . '>' . esc_html__( 'EEA, UK and Switzerland only', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="custom"' . selected( $mode, 'custom', false ) . '>' . esc_html__( 'Selected countries only', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="none"' . selected( $mode, 'none', false ) . '>' . esc_html__( 'Never show (disable the CMP)', 'piensa-cookie-consent' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'With no geo header available, the banner is always shown.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<div class="ag-field-group">';
		echo '<textarea class="large-text" rows="3" name="' . esc_attr( $this->option_name ) . '[geo_countries]">' . esc_attr( $countries ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Comma-separated ISO2 codes, for example ES,FR,DE. Only used in custom mode.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<div class="ag-field-group">';
		echo '<select name="' . esc_attr( $this->option_name ) . '[geo_header]">';
		echo '<option value="auto"' . selected( $header, 'auto', false ) . '>' . esc_html__( 'Auto-detect (CF/IP/Geo)', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="CF-IPCountry"' . selected( $header, 'CF-IPCountry', false ) . '>CF-IPCountry</option>';
		echo '<option value="X-GeoIP-Country"' . selected( $header, 'X-GeoIP-Country', false ) . '>X-GeoIP-Country</option>';
		echo '<option value="X-Country-Code"' . selected( $header, 'X-Country-Code', false ) . '>X-Country-Code</option>';
		echo '<option value="X-Geo-Country"' . selected( $header, 'X-Geo-Country', false ) . '>X-Geo-Country</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the header your host or CDN exposes.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
	}

	public function render_language_field() {
		$settings = self::get_settings();
		$mode     = $settings['language_mode'];
		$default  = $settings['default_language'];

		echo '<div class="ag-field-group">';
		echo '<select name="' . esc_attr( $this->option_name ) . '[language_mode]">';
		echo '<option value="auto"' . selected( $mode, 'auto', false ) . '>' . esc_html__( 'Auto (document or browser)', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="site"' . selected( $mode, 'site', false ) . '>' . esc_html__( 'Site language (WP)', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="browser"' . selected( $mode, 'browser', false ) . '>' . esc_html__( 'Browser language', 'piensa-cookie-consent' ) . '</option>';
		echo '<option value="custom"' . selected( $mode, 'custom', false ) . '>' . esc_html__( 'Force a language', 'piensa-cookie-consent' ) . '</option>';
		echo '</select>';
		echo '</div>';
		echo '<div class="ag-field-group">';
		echo '<select name="' . esc_attr( $this->option_name ) . '[default_language]">';
		echo '<option value="es"' . selected( $default, 'es', false ) . '>Espanol</option>';
		echo '<option value="en"' . selected( $default, 'en', false ) . '>English</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Used when detection fails.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
	}

	public function render_report_field() {
		$html_url = wp_nonce_url( admin_url( 'admin-post.php?action=piensa_cookie_consent_report_html' ), 'piensa_cookie_consent_report_html' );
		$json_url = wp_nonce_url( admin_url( 'admin-post.php?action=piensa_cookie_consent_report_json' ), 'piensa_cookie_consent_report_json' );
		echo '<a class="button" href="' . esc_url( $html_url ) . '">' . esc_html__( 'Download the HTML report', 'piensa-cookie-consent' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $json_url ) . '">' . esc_html__( 'Download the JSON report', 'piensa-cookie-consent' ) . '</a>';
	}

	public function render_texts_en_field() {
		$settings = self::get_settings();
		echo '<div class="ag-field-group">';
		echo '<strong>' . esc_html__( 'Banner', 'piensa-cookie-consent' ) . '</strong>';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_title_en]" value="' . esc_attr( $settings['banner_title_en'] ) . '" placeholder="Title" />';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[banner_description_en]">' . esc_textarea( $settings['banner_description_en'] ) . '</textarea>';
		echo '<div class="ag-field-row">';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_accept_all_en]" value="' . esc_attr( $settings['banner_accept_all_en'] ) . '" placeholder="Accept all" />';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_reject_all_en]" value="' . esc_attr( $settings['banner_reject_all_en'] ) . '" placeholder="Reject" />';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_manage_prefs_en]" value="' . esc_attr( $settings['banner_manage_prefs_en'] ) . '" placeholder="Manage preferences" />';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_save_prefs_en]" value="' . esc_attr( $settings['banner_save_prefs_en'] ) . '" placeholder="Save preferences" />';
		echo '</div>';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[banner_preferences_title_en]" value="' . esc_attr( $settings['banner_preferences_title_en'] ) . '" placeholder="Preferences title" />';
		echo '</div>';
		echo '<div class="ag-field-group">';
		echo '<strong>Categories</strong>';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[necessary_label_en]" value="' . esc_attr( $settings['necessary_label_en'] ) . '" placeholder="Necessary" />';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[necessary_description_en]">' . esc_textarea( $settings['necessary_description_en'] ) . '</textarea>';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[necessary_legal_note_en]" placeholder="Legal note (optional)">' . esc_textarea( $settings['necessary_legal_note_en'] ) . '</textarea>';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[analytics_label_en]" value="' . esc_attr( $settings['analytics_label_en'] ) . '" placeholder="Analytics" />';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[analytics_description_en]">' . esc_textarea( $settings['analytics_description_en'] ) . '</textarea>';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[marketing_label_en]" value="' . esc_attr( $settings['marketing_label_en'] ) . '" placeholder="Marketing" />';
		echo '<textarea class="large-text" rows="2" name="' . esc_attr( $this->option_name ) . '[marketing_description_en]">' . esc_textarea( $settings['marketing_description_en'] ) . '</textarea>';
		echo '</div>';
	}

	public function render_branding_field() {
		$settings = self::get_settings();
		$checked  = $settings['hide_branding'] ? 'checked' : '';
		echo '<div class="ag-field-group">';
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[brand_name]" value="' . esc_attr( $settings['brand_name'] ) . '" placeholder="' . esc_attr__( 'Brand name (optional)', 'piensa-cookie-consent' ) . '" />';
		echo '<input class="regular-text" type="url" name="' . esc_attr( $this->option_name ) . '[brand_logo_url]" value="' . esc_attr( $settings['brand_logo_url'] ) . '" placeholder="' . esc_attr__( 'Logo URL (optional)', 'piensa-cookie-consent' ) . '" />';
		echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[hide_branding]" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Hide branding in the banner', 'piensa-cookie-consent' ) . '</label>';
		echo '</div>';
	}

	public function render_export_field() {
		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=piensa_cookie_consent_export_settings' ), 'piensa_cookie_consent_export_settings' );
		echo '<a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Download settings (JSON)', 'piensa-cookie-consent' ) . '</a>';
	}

	public function render_import_field() {
		echo '<input type="file" name="piensa_cookie_consent_settings_file" form="ag-import-form" accept="application/json" />';
		echo ' ';
		echo '<button type="submit" class="button" form="ag-import-form">' . esc_html__( 'Import settings', 'piensa-cookie-consent' ) . '</button>';
	}

	public function render_health_field() {
		$issues = $this->get_health_issues();
		if ( ! $issues ) {
			echo '<p class="description">' . esc_html__( 'All good. No risks detected.', 'piensa-cookie-consent' ) . '</p>';
			return;
		}
		echo '<ul class="ag-health">';
		foreach ( $issues as $issue ) {
			echo '<li>' . esc_html( $issue ) . '</li>';
		}
		echo '</ul>';
	}

	public function render_domains_field() {
		$settings = self::get_settings();
		$value    = esc_textarea( $settings['blocked_domains'] );
		echo '<textarea class="large-text code" rows="7" name="' . esc_attr( $this->option_name ) . '[blocked_domains]">' . esc_attr( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One per line. For example: youtube.com', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_placeholder_title_field() {
		$settings = self::get_settings();
		$value    = $settings['placeholder_title'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[placeholder_title]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_placeholder_button_field() {
		$settings = self::get_settings();
		$value    = $settings['placeholder_button'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[placeholder_button]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_floating_button_field() {
		$settings = self::get_settings();
		$checked  = $settings['show_floating_button'] ? 'checked' : '';
		echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[show_floating_button]" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Show a floating review button', 'piensa-cookie-consent' ) . '</label>';
	}

	public function render_floating_button_text_field() {
		$settings = self::get_settings();
		$value    = $settings['floating_button_text'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[floating_button_text]" value="' . esc_attr( $value ) . '" />';
	}

	public function render_floating_button_style_field() {
		$settings = self::get_settings();
		$value    = $settings['floating_button_style'];
		echo '<select name="' . esc_attr( $this->option_name ) . '[floating_button_style]">';
		echo '<option value="icon"' . selected( $value, 'icon', false ) . '>Icono</option>';
		echo '<option value="text"' . selected( $value, 'text', false ) . '>Texto</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Recommended: an icon, so the page stays uncluttered.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_discovered_field() {
		$discovered = get_option( 'piensa_cookie_consent_discovered', [] );
		$settings   = self::get_settings();
		$overrides  = isset( $settings['domain_overrides'] ) && is_array( $settings['domain_overrides'] ) ? $settings['domain_overrides'] : [];
		$scan_url   = wp_nonce_url( admin_url( 'admin-post.php?action=piensa_cookie_consent_scan' ), 'piensa_cookie_consent_scan' );

		echo '<p><a class="button" href="' . esc_url( $scan_url ) . '">' . esc_html__( 'Scan now', 'piensa-cookie-consent' ) . '</a></p>';

		if ( ! is_array( $discovered ) || ! $discovered ) {
			echo '<p class="description">' . esc_html__( 'No external domains have been detected yet.', 'piensa-cookie-consent' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr><th>Dominio</th><th>Servicio</th><th>Categoria sugerida</th><th>Categoria final</th><th>Ultima deteccion</th></tr></thead>';
		echo '<tbody>';
		foreach ( $discovered as $domain => $data ) {
			$category  = isset( $data['category'] ) ? esc_html( $data['category'] ) : 'unknown';
			$service   = isset( $data['service'] ) ? esc_html( $data['service'] ) : '-';
			$last_seen = isset( $data['last_seen'] ) ? date_i18n( 'Y-m-d H:i', (int) $data['last_seen'] ) : '-';
			$selected  = isset( $overrides[ $domain ] ) ? $overrides[ $domain ] : 'auto';
			echo '<tr>';
			echo '<td>' . esc_html( $domain ) . '</td>';
			echo '<td>' . esc_attr( $service ) . '</td>';
			echo '<td>' . esc_attr( $category ) . '</td>';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_domain_select() escapes every value it interpolates.
			echo '<td>' . $this->render_domain_select( $domain, $selected ) . '</td>';
			echo '<td>' . esc_html( $last_seen ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function render_cookie_audit_field() {
		$nonce     = wp_create_nonce( 'piensa_cookie_consent_audit' );
		$audit_url = add_query_arg(
			[
				'ag_cookie_audit' => '1',
				'ag_nonce'        => $nonce,
			],
			home_url( '/' )
		);

		echo '<p><a class="button" href="' . esc_url( $audit_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Scan cookies in the browser', 'piensa-cookie-consent' ) . '</a></p>';
		echo '<p class="description">' . esc_html__( 'Opens your site in audit mode to detect the cookies scripts actually set. Administrators only. Names are stored, never values.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_detected_cookies_field() {
		$cookies = get_option( 'piensa_cookie_consent_detected_cookies', [] );
		if ( ! is_array( $cookies ) || ! $cookies ) {
			echo '<p class="description">' . esc_html__( 'No cookies have been detected in a scan yet.', 'piensa-cookie-consent' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr><th>Cookie</th><th>Dominio</th><th>Categoria</th><th>Ultima deteccion</th></tr></thead><tbody>';
		foreach ( $cookies as $cookie ) {
			$name      = isset( $cookie['name'] ) ? $cookie['name'] : '';
			$domain    = isset( $cookie['domain'] ) ? $cookie['domain'] : '';
			$category  = isset( $cookie['category'] ) ? $cookie['category'] : 'unknown';
			$last_seen = isset( $cookie['last_seen'] ) ? date_i18n( 'Y-m-d H:i', (int) $cookie['last_seen'] ) : '-';
			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( $domain ) . '</td>';
			echo '<td>' . esc_html( $category ) . '</td>';
			echo '<td>' . esc_html( $last_seen ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function render_cookie_policy_url_field() {
		$settings = self::get_settings();
		$value    = $settings['cookie_policy_url'];
		echo '<input class="regular-text" type="url" name="' . esc_attr( $this->option_name ) . '[cookie_policy_url]" value="' . esc_attr( $value ) . '" placeholder="https://tusitio.com/politica-de-cookies" />';
		echo '<p class="description">' . esc_html__( 'Use the [piensa_cookie_consent_policy] shortcode on a page if you do not have a URL of your own.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_privacy_policy_url_field() {
		$settings = self::get_settings();
		$value    = $settings['privacy_policy_url'];
		echo '<input class="regular-text" type="url" name="' . esc_attr( $this->option_name ) . '[privacy_policy_url]" value="' . esc_attr( $value ) . '" placeholder="https://tusitio.com/privacidad" />';
	}

	public function render_custom_cookies_field() {
		$settings = self::get_settings();
		$value    = esc_textarea( $settings['custom_cookies'] );
		echo '<textarea class="large-text code" rows="6" name="' . esc_attr( $this->option_name ) . '[custom_cookies]">' . esc_attr( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Format: name|category|purpose|duration|domain. For example:', 'piensa-cookie-consent' ) . ' my_cookie|analytics|Medicion basica|variable|tusitio.com</p>';
	}

	public function render_logs_field() {
		$logs = Piensa_Cookie_Consent_Consent_Log::get_logs( 50, 0 );
		if ( ! $logs ) {
			echo '<p class="description">' . esc_html__( 'No records yet.', 'piensa-cookie-consent' ) . '</p>';
			return;
		}

		$export_url = wp_nonce_url( admin_url( 'admin-post.php?action=piensa_cookie_consent_export_logs' ), 'piensa_cookie_consent_export_logs' );

		echo '<p><a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export CSV', 'piensa-cookie-consent' ) . '</a></p>';
		echo '<table class="widefat striped">';
		echo '<thead><tr><th>' . esc_html__( 'Date', 'piensa-cookie-consent' ) . '</th><th>Consent ID</th><th>' . esc_html__( 'Action', 'piensa-cookie-consent' ) . '</th><th>' . esc_html__( 'Categories', 'piensa-cookie-consent' ) . '</th><th>' . esc_html__( 'Revision', 'piensa-cookie-consent' ) . '</th><th>' . esc_html__( 'Language', 'piensa-cookie-consent' ) . '</th><th>URL</th></tr></thead><tbody>';
		foreach ( $logs as $log ) {
			echo '<tr>';
			echo '<td>' . esc_html( $log['created_at'] ) . '</td>';
			echo '<td>' . esc_html( $log['consent_id'] ) . '</td>';
			echo '<td>' . esc_html( $log['action'] ) . '</td>';
			echo '<td>' . esc_html( $log['categories'] ) . '</td>';
			echo '<td>' . esc_html( $log['revision'] ) . '</td>';
			echo '<td>' . esc_html( $log['language'] ) . '</td>';
			echo '<td>' . esc_html( $log['url'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	public function render_update_server_field() {
		$settings = self::get_settings();
		$value    = $settings['update_server_url'];
		echo '<input class="regular-text" type="url" name="' . esc_attr( $this->option_name ) . '[update_server_url]" value="' . esc_attr( $value ) . '" placeholder="https://updates.tu-dominio.com/cmp.json" />';
		echo '<p class="description">' . esc_html__( 'Supports the {slug}, {channel} and {site} variables. Left out, they are appended as query arguments.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_update_channel_field() {
		$settings = self::get_settings();
		$value    = $settings['update_channel'];
		echo '<select name="' . esc_attr( $this->option_name ) . '[update_channel]">';
		echo '<option value="stable"' . selected( $value, 'stable', false ) . '>Stable</option>';
		echo '<option value="beta"' . selected( $value, 'beta', false ) . '>Beta</option>';
		echo '</select>';
	}

	public function render_update_token_field() {
		$settings = self::get_settings();
		$value    = $settings['update_token'];
		echo '<input class="regular-text" type="text" name="' . esc_attr( $this->option_name ) . '[update_token]" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr__( 'Bearer token (optional)', 'piensa-cookie-consent' ) . '" />';
		echo '<p class="description">' . esc_html__( 'Sent in the Authorization header.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_update_public_key_field() {
		$settings = self::get_settings();
		$value    = esc_textarea( $settings['update_public_key'] );
		echo '<textarea class="large-text code" rows="4" name="' . esc_attr( $this->option_name ) . '[update_public_key]" placeholder="-----BEGIN PUBLIC KEY-----">' . esc_attr( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Public key used to verify signatures from the central server.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_update_signature_field() {
		$settings = self::get_settings();
		$checked  = ! empty( $settings['update_require_signature'] ) ? 'checked' : '';
		echo '<label><input type="checkbox" name="' . esc_attr( $this->option_name ) . '[update_require_signature]" value="1" ' . esc_attr( $checked ) . '> ' . esc_html__( 'Require a valid signature', 'piensa-cookie-consent' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'When enabled, an update without a valid signature is blocked.', 'piensa-cookie-consent' ) . '</p>';
	}

	public function render_settings_page() {
		$settings          = self::get_settings();
		$discovered        = get_option( 'piensa_cookie_consent_discovered', [] );
		$domains_count     = is_array( $discovered ) ? count( $discovered ) : 0;
		$scan_url          = wp_nonce_url( admin_url( 'admin-post.php?action=piensa_cookie_consent_scan' ), 'piensa_cookie_consent_scan' );
		$export_url        = wp_nonce_url( admin_url( 'admin-post.php?action=piensa_cookie_consent_export_logs' ), 'piensa_cookie_consent_export_logs' );
		$cookie_policy_url = $settings['cookie_policy_url'];

		echo '<div class="wrap ag-admin">';
		echo '<div class="ag-admin-hero">';
		echo '<div class="ag-admin-hero__content">';
		echo '<span class="ag-admin-eyebrow">' . esc_html__( 'Piensa Cookie Consent', 'piensa-cookie-consent' ) . '</span>';
		echo '<h1>' . esc_html__( 'Consent control panel', 'piensa-cookie-consent' ) . '</h1>';
		echo '<p class="ag-admin-subtitle">' . esc_html__( 'Consent management for agencies: compliance, control and clean data.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<div class="ag-admin-hero__actions">';
		echo '<a class="button button-primary" href="' . esc_url( $scan_url ) . '">' . esc_html__( 'Scan now', 'piensa-cookie-consent' ) . '</a>';
		echo '<a class="button" href="' . esc_url( $export_url ) . '">' . esc_html__( 'Export CSV', 'piensa-cookie-consent' ) . '</a>';
		if ( ! empty( $cookie_policy_url ) ) {
			echo '<a class="button" href="' . esc_url( $cookie_policy_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the policy', 'piensa-cookie-consent' ) . '</a>';
		}
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-admin-kpis">';
		echo '<div class="ag-kpi">';
		echo '<span class="ag-kpi__label">' . esc_html__( 'Revision', 'piensa-cookie-consent' ) . ' legal</span>';
		echo '<span class="ag-kpi__value">' . esc_html( $settings['policy_revision'] ) . '</span>';
		echo '</div>';
		echo '<div class="ag-kpi">';
		echo '<span class="ag-kpi__label">Logs</span>';
		echo '<span class="ag-kpi__value">' . ( $settings['enable_consent_log'] ? 'Activo' : 'Inactivo' ) . '</span>';
		echo '</div>';
		echo '<div class="ag-kpi">';
		echo '<span class="ag-kpi__label">' . esc_html__( 'Detected domains', 'piensa-cookie-consent' ) . '</span>';
		echo '<span class="ag-kpi__value">' . esc_html( (string) $domains_count ) . '</span>';
		echo '</div>';
		echo '<div class="ag-kpi">';
		echo '<span class="ag-kpi__label">' . esc_html__( 'Category mode', 'piensa-cookie-consent' ) . '</span>';
		echo '<span class="ag-kpi__value">' . esc_html( ucfirst( $settings['category_mode'] ) ) . '</span>';
		echo '</div>';
		echo '</div>';

		$notice = get_transient( 'piensa_cookie_consent_scan_notice' );
		if ( $notice ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html( $notice ) . '</p></div>';
			delete_transient( 'piensa_cookie_consent_scan_notice' );
		}

		echo '<nav class="ag-admin-tabs" data-ag-tabs>';
		echo '<button type="button" class="ag-tab-btn is-active" data-tab="general">' . esc_html__( 'General', 'piensa-cookie-consent' ) . '</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="compliance">Compliance</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="idiomas">' . esc_html__( 'Languages', 'piensa-cookie-consent' ) . '</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="categorias">' . esc_html__( 'Categories', 'piensa-cookie-consent' ) . '</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="apariencia">' . esc_html__( 'Appearance', 'piensa-cookie-consent' ) . '</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="banner">' . esc_html__( 'Banner', 'piensa-cookie-consent' ) . '</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="bloqueo">' . esc_html__( 'Blocking', 'piensa-cookie-consent' ) . '</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="scanner">Scanner</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="politica">' . esc_html__( 'Policy', 'piensa-cookie-consent' ) . '</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="branding">Branding</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="tools">Tools</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="health">Health</button>';
		echo '<button type="button" class="ag-tab-btn" data-tab="logs">Logs</button>';
		echo '</nav>';

		echo '<div class="piensa-cookie-consent-admin-card">';
		echo '<form method="post" action="options.php">';
		settings_fields( 'piensa_cookie_consent_settings' );

		echo '<div class="ag-tab is-active" data-tab="general">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'General settings', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">Datos principales, revision legal y registro.</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_main' );
		echo '</table>';
		echo '</div>';

		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h3>' . esc_html__( 'Quick actions', 'piensa-cookie-consent' ) . '</h3>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Floating button and consent review.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_ui' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="compliance">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Compliance and privacy', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Set geo-targeting and language.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_compliance' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="idiomas">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Multilingual text', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Configure the English text. Spanish is managed under Banner and Categories.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_languages' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="categorias">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Categories and behavior', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Set the labels, and turn categories on or off.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_categories' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="apariencia">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Appearance and position', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Adjust the layout, position and colors.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_appearance' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="banner">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Banner text', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Customize the banner and the preferences dialog.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_banner' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="bloqueo">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Content blocking', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Controls the blocked domains and the placeholder text.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_content' );
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_texts' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="scanner">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Automatic discovery', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Scan your site and classify its external domains.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_discovery' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="politica">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Cookie policy', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Link your policy and declare custom cookies.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_policy' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="branding">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Branding and white-label', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Customize the visible branding.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_branding' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="tools">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Tools', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Import and export settings.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_tools' );
		if ( Piensa_Cookie_Consent_Core::has_self_hosted_updater() ) {
			do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_updates' );
		}
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="health">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>Health check</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Revision', 'piensa-cookie-consent' ) . ' rapida de riesgos.</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_health' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		echo '<div class="ag-tab" data-tab="logs">';
		echo '<div class="ag-panel">';
		echo '<div class="ag-panel-header">';
		echo '<h2>' . esc_html__( 'Consent records', 'piensa-cookie-consent' ) . '</h2>';
		echo '<p class="ag-section-intro">' . esc_html__( 'Review and export the consent history.', 'piensa-cookie-consent' ) . '</p>';
		echo '</div>';
		echo '<table class="form-table">';
		do_settings_fields( 'piensa-cookie-consent', 'piensa_cookie_consent_logs' );
		echo '</table>';
		echo '</div>';
		echo '</div>';

		submit_button( esc_html__( 'Save changes', 'piensa-cookie-consent' ) );
		echo '</form>';
		echo '<form id="ag-import-form" method="post" action="' . esc_url( admin_url( 'admin-post.php?action=piensa_cookie_consent_import_settings' ) ) . '" enctype="multipart/form-data">';
		wp_nonce_field( 'piensa_cookie_consent_import_settings' );
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	public function render_preview() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_ajax_referer( 'piensa_cookie_consent_preview', 'nonce' );

		$settings = [];
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised field by field by sanitize_settings() on the next line.
		if ( ! empty( $_POST['piensa_cookie_consent_settings'] ) && is_array( $_POST['piensa_cookie_consent_settings'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by sanitize_settings() below.
			$raw      = wp_unslash( $_POST['piensa_cookie_consent_settings'] );
			$settings = $this->sanitize_settings( $raw );
		} else {
			$settings = self::get_settings();
		}

		$config = $this->build_preview_config( $settings );

		$css_cc   = PIENSA_COOKIE_CONSENT_URL . 'assets/css/cookieconsent.css';
		$css_main = PIENSA_COOKIE_CONSENT_URL . 'assets/css/piensa-cookie-consent.css';
		$js_cc    = PIENSA_COOKIE_CONSENT_URL . 'assets/js/cookieconsent.js';
		$js_main  = PIENSA_COOKIE_CONSENT_URL . 'assets/js/piensa-cookie-consent.js';

		header( 'Content-Type: text/html; charset=utf-8' );

		// A standalone document served into the preview iframe: there is no
		// wp_head() here, so the enqueue system has nowhere to print to and the
		// assets have to be linked directly.
		// phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet, WordPress.WP.EnqueuedResources.NonEnqueuedScript
		echo '<!doctype html><html><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<link rel="stylesheet" href="' . esc_url( $css_cc ) . '">';
		echo '<link rel="stylesheet" href="' . esc_url( $css_main ) . '">';
		echo '<style>html,body{margin:0;padding:0;background:#f1f5f9;}#preview-root{min-height:360px;position:relative;padding:20px;}#cc-main{position:absolute !important;}</style>';
		echo '</head><body>';
		echo '<div id="preview-root"></div>';
		echo '<script>window.PiensaCookieConsentConfig=' . wp_json_encode( $config ) . ';</script>';
		echo '<script src="' . esc_url( $js_cc ) . '"></script>';
		echo '<script src="' . esc_url( $js_main ) . '"></script>';
		echo '<script>setTimeout(function(){if(window.CookieConsent&&CookieConsent.show){CookieConsent.show();}},60);</script>';
		echo '</body></html>';
		// phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet, WordPress.WP.EnqueuedResources.NonEnqueuedScript
		exit;
	}

	private function build_preview_config( $settings ) {
		$definitions = [
			'necessary' => [
				'label'       => $settings['necessary_label'],
				'description' => $settings['necessary_description'],
				'cookies'     => [],
			],
			'analytics' => [
				'label'       => $settings['analytics_label'],
				'description' => $settings['analytics_description'],
				'cookies'     => [],
			],
			'marketing' => [
				'label'       => $settings['marketing_label'],
				'description' => $settings['marketing_description'],
				'cookies'     => [],
			],
		];

		$categories = [
			'necessary' => true,
			'analytics' => ! empty( $settings['analytics_enabled'] ),
			'marketing' => ! empty( $settings['marketing_enabled'] ),
		];

		$site_lang = $settings['default_language'] ?: 'es';

		return [
			'categories'        => $categories,
			'cookieDefinitions' => $definitions,
			'ui'                => [
				'floatingButton'       => false,
				'floatingButtonText'   => $settings['floating_button_text'],
				'floatingButtonStyle'  => $settings['floating_button_style'],
				'consentLayout'        => $settings['consent_layout'],
				'consentPosition'      => $settings['consent_position'],
				'preferencesLayout'    => $settings['preferences_layout'],
				'preferencesPosition'  => $settings['preferences_position'],
				'bannerShowIcon'       => ! empty( $settings['banner_show_icon'] ),
				'bannerIconStyle'      => $settings['banner_icon_style'],
				'allowNecessaryToggle' => ! empty( $settings['allow_necessary_toggle'] ),
			],
			'theme'             => [
				'bg'                => $settings['theme_bg'],
				'primaryColor'      => $settings['theme_primary_color'],
				'secondaryColor'    => $settings['theme_secondary_color'],
				'primaryBtnBg'      => $settings['theme_btn_primary_bg'],
				'primaryBtnColor'   => $settings['theme_btn_primary_color'],
				'secondaryBtnBg'    => $settings['theme_btn_secondary_bg'],
				'secondaryBtnColor' => $settings['theme_btn_secondary_color'],
				'modalRadius'       => $settings['theme_modal_radius'],
				'buttonRadius'      => $settings['theme_button_radius'],
			],
			'language'          => [
				'mode'    => 'custom',
				'default' => $site_lang,
				'site'    => $site_lang,
				'texts'   => [
					'es' => [
						'banner_title'             => $settings['banner_title'],
						'banner_description'       => $settings['banner_description'],
						'banner_accept_all'        => $settings['banner_accept_all'],
						'banner_reject_all'        => $settings['banner_reject_all'],
						'banner_manage_prefs'      => $settings['banner_manage_prefs'],
						'banner_save_prefs'        => $settings['banner_save_prefs'],
						'banner_preferences_title' => $settings['banner_preferences_title'],
						'necessary_label'          => $settings['necessary_label'],
						'necessary_description'    => $settings['necessary_description'],
						'necessary_legal_note'     => $settings['necessary_legal_note'],
						'analytics_label'          => $settings['analytics_label'],
						'analytics_description'    => $settings['analytics_description'],
						'marketing_label'          => $settings['marketing_label'],
						'marketing_description'    => $settings['marketing_description'],
					],
					'en' => [
						'banner_title'             => $settings['banner_title_en'],
						'banner_description'       => $settings['banner_description_en'],
						'banner_accept_all'        => $settings['banner_accept_all_en'],
						'banner_reject_all'        => $settings['banner_reject_all_en'],
						'banner_manage_prefs'      => $settings['banner_manage_prefs_en'],
						'banner_save_prefs'        => $settings['banner_save_prefs_en'],
						'banner_preferences_title' => $settings['banner_preferences_title_en'],
						'necessary_label'          => $settings['necessary_label_en'],
						'necessary_description'    => $settings['necessary_description_en'],
						'necessary_legal_note'     => $settings['necessary_legal_note_en'],
						'analytics_label'          => $settings['analytics_label_en'],
						'analytics_description'    => $settings['analytics_description_en'],
						'marketing_label'          => $settings['marketing_label_en'],
						'marketing_description'    => $settings['marketing_description_en'],
					],
				],
			],
			'brand'             => [
				'name' => $settings['brand_name'],
				'logo' => $settings['brand_logo_url'],
				'hide' => ! empty( $settings['hide_branding'] ),
			],
			'policy'            => [
				'revision'         => (int) $settings['policy_revision'],
				'cookiePolicyUrl'  => $settings['cookie_policy_url'],
				'privacyPolicyUrl' => $settings['privacy_policy_url'],
				'logConsent'       => false,
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'piensa_cookie_consent_log' ),
				'banner'           => [
					'title'            => $settings['banner_title'],
					'description'      => $settings['banner_description'],
					'acceptAll'        => $settings['banner_accept_all'],
					'rejectAll'        => $settings['banner_reject_all'],
					'managePrefs'      => $settings['banner_manage_prefs'],
					'savePrefs'        => $settings['banner_save_prefs'],
					'preferencesTitle' => $settings['banner_preferences_title'],
				],
			],
		];
	}

	public function enqueue_assets( $hook ) {
		// Load on every screen belonging to this plugin.
		if ( strpos( $hook, 'piensa-cookie-consent' ) === false && $hook !== $this->menu_hook ) {
			return;
		}

		wp_enqueue_style( 'piensa-cookie-consent-admin', PIENSA_COOKIE_CONSENT_URL . 'assets/css/piensa-cookie-consent-admin.css', [], PIENSA_COOKIE_CONSENT_VERSION );
		wp_enqueue_script( 'piensa-cookie-consent-admin', PIENSA_COOKIE_CONSENT_URL . 'assets/js/piensa-cookie-consent-admin.js', [], PIENSA_COOKIE_CONSENT_VERSION, true );
		wp_localize_script(
			'piensa-cookie-consent-admin',
			'PiensaCookieConsentAdminConfig',
			[
				'presets'       => self::get_theme_presets(),
				'previewUrl'    => admin_url( 'admin-ajax.php?action=piensa_cookie_consent_preview' ),
				'previewNonce'  => wp_create_nonce( 'piensa_cookie_consent_preview' ),
				'previewAssets' => [
					'cssCc'   => PIENSA_COOKIE_CONSENT_URL . 'assets/css/cookieconsent.css',
					'cssMain' => PIENSA_COOKIE_CONSENT_URL . 'assets/css/piensa-cookie-consent.css',
					'jsCc'    => PIENSA_COOKIE_CONSENT_URL . 'assets/js/cookieconsent.js',
					'jsMain'  => PIENSA_COOKIE_CONSENT_URL . 'assets/js/piensa-cookie-consent.js',
				],
			]
		);
	}

	public function sanitize_settings( $value ) {
		$defaults = self::get_default_settings();
		$value    = is_array( $value ) ? $value : [];

		$enable_blocker            = ! empty( $value['enable_blocker'] ) ? true : false;
		$block_unknown_third_party = ! empty( $value['block_unknown_third_party'] ) ? true : false;
		$log_retention_days        = isset( $value['log_retention_days'] ) ? max( 0, min( 3650, (int) $value['log_retention_days'] ) ) : 0;
		$allowed_domains           = isset( $value['allowed_domains'] ) ? (string) $value['allowed_domains'] : '';

		$blocked_domains = isset( $value['blocked_domains'] ) ? (string) $value['blocked_domains'] : '';
		$lines           = preg_split( '/\\r\\n|\\r|\\n/', $blocked_domains );
		$lines           = array_filter( array_map( 'trim', $lines ) );
		$blocked_domains = implode( "\n", $lines );

		$placeholder_title     = isset( $value['placeholder_title'] ) ? wp_strip_all_tags( (string) $value['placeholder_title'] ) : '';
		$placeholder_button    = isset( $value['placeholder_button'] ) ? wp_strip_all_tags( (string) $value['placeholder_button'] ) : '';
		$show_floating_button  = ! empty( $value['show_floating_button'] ) ? true : false;
		$floating_button_text  = isset( $value['floating_button_text'] ) ? wp_strip_all_tags( (string) $value['floating_button_text'] ) : '';
		$floating_button_style = isset( $value['floating_button_style'] ) && in_array( $value['floating_button_style'], [ 'icon', 'text' ], true )
			? $value['floating_button_style']
			: $defaults['floating_button_style'];

		$enable_consent_log = ! empty( $value['enable_consent_log'] ) ? true : false;
		$policy_revision    = isset( $value['policy_revision'] ) ? (int) $value['policy_revision'] : $defaults['policy_revision'];

		$cookie_policy_url  = isset( $value['cookie_policy_url'] ) ? esc_url_raw( (string) $value['cookie_policy_url'] ) : '';
		$privacy_policy_url = isset( $value['privacy_policy_url'] ) ? esc_url_raw( (string) $value['privacy_policy_url'] ) : '';

		$update_server_url        = isset( $value['update_server_url'] ) ? esc_url_raw( (string) $value['update_server_url'] ) : '';
		$update_channel           = isset( $value['update_channel'] ) && in_array( $value['update_channel'], [ 'stable', 'beta' ], true )
			? $value['update_channel']
			: $defaults['update_channel'];
		$update_token             = isset( $value['update_token'] ) ? sanitize_text_field( (string) $value['update_token'] ) : '';
		$update_public_key        = isset( $value['update_public_key'] ) ? trim( (string) $value['update_public_key'] ) : '';
		$update_require_signature = ! empty( $value['update_require_signature'] ) ? true : false;

		$banner_title             = isset( $value['banner_title'] ) ? wp_strip_all_tags( (string) $value['banner_title'] ) : '';
		$banner_description       = isset( $value['banner_description'] ) ? sanitize_textarea_field( (string) $value['banner_description'] ) : '';
		$banner_accept_all        = isset( $value['banner_accept_all'] ) ? wp_strip_all_tags( (string) $value['banner_accept_all'] ) : '';
		$banner_reject_all        = isset( $value['banner_reject_all'] ) ? wp_strip_all_tags( (string) $value['banner_reject_all'] ) : '';
		$banner_manage_prefs      = isset( $value['banner_manage_prefs'] ) ? wp_strip_all_tags( (string) $value['banner_manage_prefs'] ) : '';
		$banner_save_prefs        = isset( $value['banner_save_prefs'] ) ? wp_strip_all_tags( (string) $value['banner_save_prefs'] ) : '';
		$banner_preferences_title = isset( $value['banner_preferences_title'] ) ? wp_strip_all_tags( (string) $value['banner_preferences_title'] ) : '';

		$banner_show_icon  = ! empty( $value['banner_show_icon'] ) ? true : false;
		$banner_icon_style = isset( $value['banner_icon_style'] ) && in_array( $value['banner_icon_style'], [ 'cookie', 'cookie-bite', 'shield', 'lock', 'fingerprint' ], true )
			? $value['banner_icon_style']
			: $defaults['banner_icon_style'];

		$custom_cookies = isset( $value['custom_cookies'] ) ? (string) $value['custom_cookies'] : '';
		$custom_cookies = $this->sanitize_custom_cookies( $custom_cookies );

		$category_mode          = isset( $value['category_mode'] ) && in_array( $value['category_mode'], [ 'auto', 'manual' ], true )
			? $value['category_mode']
			: $defaults['category_mode'];
		$allow_necessary_toggle = ! empty( $value['allow_necessary_toggle'] ) ? true : false;
		$analytics_enabled      = ! empty( $value['analytics_enabled'] ) ? true : false;
		$marketing_enabled      = ! empty( $value['marketing_enabled'] ) ? true : false;
		$necessary_label        = isset( $value['necessary_label'] ) ? wp_strip_all_tags( (string) $value['necessary_label'] ) : '';
		$necessary_description  = isset( $value['necessary_description'] ) ? wp_strip_all_tags( (string) $value['necessary_description'] ) : '';
		$analytics_label        = isset( $value['analytics_label'] ) ? wp_strip_all_tags( (string) $value['analytics_label'] ) : '';
		$analytics_description  = isset( $value['analytics_description'] ) ? wp_strip_all_tags( (string) $value['analytics_description'] ) : '';
		$marketing_label        = isset( $value['marketing_label'] ) ? wp_strip_all_tags( (string) $value['marketing_label'] ) : '';
		$marketing_description  = isset( $value['marketing_description'] ) ? wp_strip_all_tags( (string) $value['marketing_description'] ) : '';

		$consent_layout       = isset( $value['consent_layout'] ) ? sanitize_text_field( (string) $value['consent_layout'] ) : $defaults['consent_layout'];
		$consent_position     = isset( $value['consent_position'] ) ? sanitize_text_field( (string) $value['consent_position'] ) : $defaults['consent_position'];
		$preferences_layout   = isset( $value['preferences_layout'] ) ? sanitize_text_field( (string) $value['preferences_layout'] ) : $defaults['preferences_layout'];
		$preferences_position = isset( $value['preferences_position'] ) ? sanitize_text_field( (string) $value['preferences_position'] ) : $defaults['preferences_position'];

		$theme_bg                  = isset( $value['theme_bg'] ) ? sanitize_hex_color( (string) $value['theme_bg'] ) : $defaults['theme_bg'];
		$theme_primary_color       = isset( $value['theme_primary_color'] ) ? sanitize_hex_color( (string) $value['theme_primary_color'] ) : $defaults['theme_primary_color'];
		$theme_secondary_color     = isset( $value['theme_secondary_color'] ) ? sanitize_hex_color( (string) $value['theme_secondary_color'] ) : $defaults['theme_secondary_color'];
		$theme_btn_primary_bg      = isset( $value['theme_btn_primary_bg'] ) ? sanitize_hex_color( (string) $value['theme_btn_primary_bg'] ) : $defaults['theme_btn_primary_bg'];
		$theme_btn_primary_color   = isset( $value['theme_btn_primary_color'] ) ? sanitize_hex_color( (string) $value['theme_btn_primary_color'] ) : $defaults['theme_btn_primary_color'];
		$theme_btn_secondary_bg    = isset( $value['theme_btn_secondary_bg'] ) ? sanitize_hex_color( (string) $value['theme_btn_secondary_bg'] ) : $defaults['theme_btn_secondary_bg'];
		$theme_btn_secondary_color = isset( $value['theme_btn_secondary_color'] ) ? sanitize_hex_color( (string) $value['theme_btn_secondary_color'] ) : $defaults['theme_btn_secondary_color'];
		$theme_modal_radius        = isset( $value['theme_modal_radius'] ) ? (int) $value['theme_modal_radius'] : $defaults['theme_modal_radius'];
		$theme_button_radius       = isset( $value['theme_button_radius'] ) ? (int) $value['theme_button_radius'] : $defaults['theme_button_radius'];

		$geo_mode      = isset( $value['geo_mode'] ) && in_array( $value['geo_mode'], [ 'all', 'eea', 'custom', 'none' ], true )
			? $value['geo_mode']
			: $defaults['geo_mode'];
		$geo_countries = isset( $value['geo_countries'] ) ? sanitize_text_field( (string) $value['geo_countries'] ) : $defaults['geo_countries'];
		$geo_header    = isset( $value['geo_header'] ) ? sanitize_text_field( (string) $value['geo_header'] ) : $defaults['geo_header'];

		$language_mode    = isset( $value['language_mode'] ) && in_array( $value['language_mode'], [ 'auto', 'site', 'browser', 'custom' ], true )
			? $value['language_mode']
			: $defaults['language_mode'];
		$default_language = isset( $value['default_language'] ) && in_array( $value['default_language'], [ 'es', 'en' ], true )
			? $value['default_language']
			: $defaults['default_language'];

		$banner_title_en             = isset( $value['banner_title_en'] ) ? wp_strip_all_tags( (string) $value['banner_title_en'] ) : '';
		$banner_description_en       = isset( $value['banner_description_en'] ) ? sanitize_textarea_field( (string) $value['banner_description_en'] ) : '';
		$banner_accept_all_en        = isset( $value['banner_accept_all_en'] ) ? wp_strip_all_tags( (string) $value['banner_accept_all_en'] ) : '';
		$banner_reject_all_en        = isset( $value['banner_reject_all_en'] ) ? wp_strip_all_tags( (string) $value['banner_reject_all_en'] ) : '';
		$banner_manage_prefs_en      = isset( $value['banner_manage_prefs_en'] ) ? wp_strip_all_tags( (string) $value['banner_manage_prefs_en'] ) : '';
		$banner_save_prefs_en        = isset( $value['banner_save_prefs_en'] ) ? wp_strip_all_tags( (string) $value['banner_save_prefs_en'] ) : '';
		$banner_preferences_title_en = isset( $value['banner_preferences_title_en'] ) ? wp_strip_all_tags( (string) $value['banner_preferences_title_en'] ) : '';

		$necessary_label_en       = isset( $value['necessary_label_en'] ) ? wp_strip_all_tags( (string) $value['necessary_label_en'] ) : '';
		$necessary_description_en = isset( $value['necessary_description_en'] ) ? wp_strip_all_tags( (string) $value['necessary_description_en'] ) : '';
		$necessary_legal_note     = isset( $value['necessary_legal_note'] ) ? sanitize_textarea_field( (string) $value['necessary_legal_note'] ) : '';
		$necessary_legal_note_en  = isset( $value['necessary_legal_note_en'] ) ? sanitize_textarea_field( (string) $value['necessary_legal_note_en'] ) : '';
		$analytics_label_en       = isset( $value['analytics_label_en'] ) ? wp_strip_all_tags( (string) $value['analytics_label_en'] ) : '';
		$analytics_description_en = isset( $value['analytics_description_en'] ) ? wp_strip_all_tags( (string) $value['analytics_description_en'] ) : '';
		$marketing_label_en       = isset( $value['marketing_label_en'] ) ? wp_strip_all_tags( (string) $value['marketing_label_en'] ) : '';
		$marketing_description_en = isset( $value['marketing_description_en'] ) ? wp_strip_all_tags( (string) $value['marketing_description_en'] ) : '';

		$brand_name     = isset( $value['brand_name'] ) ? wp_strip_all_tags( (string) $value['brand_name'] ) : '';
		$brand_logo_url = isset( $value['brand_logo_url'] ) ? esc_url_raw( (string) $value['brand_logo_url'] ) : '';
		$hide_branding  = ! empty( $value['hide_branding'] ) ? true : false;

		$domain_overrides = [];
		if ( ! empty( $value['domain_overrides'] ) && is_array( $value['domain_overrides'] ) ) {
			$allowed = [ 'auto', 'necessary', 'analytics', 'marketing', 'unknown' ];
			foreach ( $value['domain_overrides'] as $domain => $cat ) {
				$domain = sanitize_text_field( $domain );
				$cat    = sanitize_text_field( $cat );
				if ( $domain && in_array( $cat, $allowed, true ) ) {
					$domain_overrides[ $domain ] = $cat;
				}
			}
		}

		return [
			'enable_blocker'              => $enable_blocker,
			'block_unknown_third_party'   => $block_unknown_third_party,
			'log_retention_days'          => $log_retention_days,
			'allowed_domains'             => $allowed_domains !== '' ? $allowed_domains : $defaults['allowed_domains'],
			'blocked_domains'             => $blocked_domains !== '' ? $blocked_domains : $defaults['blocked_domains'],
			'placeholder_title'           => $placeholder_title !== '' ? $placeholder_title : $defaults['placeholder_title'],
			'placeholder_button'          => $placeholder_button !== '' ? $placeholder_button : $defaults['placeholder_button'],
			'show_floating_button'        => $show_floating_button,
			'floating_button_text'        => $floating_button_text !== '' ? $floating_button_text : $defaults['floating_button_text'],
			'floating_button_style'       => $floating_button_style,
			'enable_consent_log'          => $enable_consent_log,
			'policy_revision'             => $policy_revision,
			'cookie_policy_url'           => $cookie_policy_url !== '' ? $cookie_policy_url : $defaults['cookie_policy_url'],
			'privacy_policy_url'          => $privacy_policy_url !== '' ? $privacy_policy_url : $defaults['privacy_policy_url'],
			'update_server_url'           => $update_server_url !== '' ? $update_server_url : $defaults['update_server_url'],
			'update_channel'              => $update_channel,
			'update_token'                => $update_token !== '' ? $update_token : $defaults['update_token'],
			'update_public_key'           => $update_public_key !== '' ? $update_public_key : $defaults['update_public_key'],
			'update_require_signature'    => $update_require_signature,
			'banner_title'                => $banner_title !== '' ? $banner_title : $defaults['banner_title'],
			'banner_description'          => $banner_description !== '' ? $banner_description : $defaults['banner_description'],
			'banner_accept_all'           => $banner_accept_all !== '' ? $banner_accept_all : $defaults['banner_accept_all'],
			'banner_reject_all'           => $banner_reject_all !== '' ? $banner_reject_all : $defaults['banner_reject_all'],
			'banner_manage_prefs'         => $banner_manage_prefs !== '' ? $banner_manage_prefs : $defaults['banner_manage_prefs'],
			'banner_save_prefs'           => $banner_save_prefs !== '' ? $banner_save_prefs : $defaults['banner_save_prefs'],
			'banner_preferences_title'    => $banner_preferences_title !== '' ? $banner_preferences_title : $defaults['banner_preferences_title'],
			'banner_show_icon'            => $banner_show_icon,
			'banner_icon_style'           => $banner_icon_style,
			'custom_cookies'              => $custom_cookies !== '' ? $custom_cookies : $defaults['custom_cookies'],
			'domain_overrides'            => $domain_overrides,
			'category_mode'               => $category_mode,
			'allow_necessary_toggle'      => $allow_necessary_toggle,
			'analytics_enabled'           => $analytics_enabled,
			'marketing_enabled'           => $marketing_enabled,
			'necessary_label'             => $necessary_label !== '' ? $necessary_label : $defaults['necessary_label'],
			'necessary_description'       => $necessary_description !== '' ? $necessary_description : $defaults['necessary_description'],
			'necessary_legal_note'        => $necessary_legal_note !== '' ? $necessary_legal_note : $defaults['necessary_legal_note'],
			'analytics_label'             => $analytics_label !== '' ? $analytics_label : $defaults['analytics_label'],
			'analytics_description'       => $analytics_description !== '' ? $analytics_description : $defaults['analytics_description'],
			'marketing_label'             => $marketing_label !== '' ? $marketing_label : $defaults['marketing_label'],
			'marketing_description'       => $marketing_description !== '' ? $marketing_description : $defaults['marketing_description'],
			'consent_layout'              => $consent_layout ?: $defaults['consent_layout'],
			'consent_position'            => $consent_position ?: $defaults['consent_position'],
			'preferences_layout'          => $preferences_layout ?: $defaults['preferences_layout'],
			'preferences_position'        => $preferences_position ?: $defaults['preferences_position'],
			'theme_bg'                    => $theme_bg ?: $defaults['theme_bg'],
			'theme_primary_color'         => $theme_primary_color ?: $defaults['theme_primary_color'],
			'theme_secondary_color'       => $theme_secondary_color ?: $defaults['theme_secondary_color'],
			'theme_btn_primary_bg'        => $theme_btn_primary_bg ?: $defaults['theme_btn_primary_bg'],
			'theme_btn_primary_color'     => $theme_btn_primary_color ?: $defaults['theme_btn_primary_color'],
			'theme_btn_secondary_bg'      => $theme_btn_secondary_bg ?: $defaults['theme_btn_secondary_bg'],
			'theme_btn_secondary_color'   => $theme_btn_secondary_color ?: $defaults['theme_btn_secondary_color'],
			'theme_modal_radius'          => $theme_modal_radius,
			'theme_button_radius'         => $theme_button_radius,
			'geo_mode'                    => $geo_mode,
			'geo_countries'               => $geo_countries,
			'geo_header'                  => $geo_header,
			'language_mode'               => $language_mode,
			'default_language'            => $default_language,
			'banner_title_en'             => $banner_title_en !== '' ? $banner_title_en : $defaults['banner_title_en'],
			'banner_description_en'       => $banner_description_en !== '' ? $banner_description_en : $defaults['banner_description_en'],
			'banner_accept_all_en'        => $banner_accept_all_en !== '' ? $banner_accept_all_en : $defaults['banner_accept_all_en'],
			'banner_reject_all_en'        => $banner_reject_all_en !== '' ? $banner_reject_all_en : $defaults['banner_reject_all_en'],
			'banner_manage_prefs_en'      => $banner_manage_prefs_en !== '' ? $banner_manage_prefs_en : $defaults['banner_manage_prefs_en'],
			'banner_save_prefs_en'        => $banner_save_prefs_en !== '' ? $banner_save_prefs_en : $defaults['banner_save_prefs_en'],
			'banner_preferences_title_en' => $banner_preferences_title_en !== '' ? $banner_preferences_title_en : $defaults['banner_preferences_title_en'],
			'necessary_label_en'          => $necessary_label_en !== '' ? $necessary_label_en : $defaults['necessary_label_en'],
			'necessary_description_en'    => $necessary_description_en !== '' ? $necessary_description_en : $defaults['necessary_description_en'],
			'necessary_legal_note_en'     => $necessary_legal_note_en !== '' ? $necessary_legal_note_en : $defaults['necessary_legal_note_en'],
			'analytics_label_en'          => $analytics_label_en !== '' ? $analytics_label_en : $defaults['analytics_label_en'],
			'analytics_description_en'    => $analytics_description_en !== '' ? $analytics_description_en : $defaults['analytics_description_en'],
			'marketing_label_en'          => $marketing_label_en !== '' ? $marketing_label_en : $defaults['marketing_label_en'],
			'marketing_description_en'    => $marketing_description_en !== '' ? $marketing_description_en : $defaults['marketing_description_en'],
			'brand_name'                  => $brand_name !== '' ? $brand_name : $defaults['brand_name'],
			'brand_logo_url'              => $brand_logo_url !== '' ? $brand_logo_url : $defaults['brand_logo_url'],
			'hide_branding'               => $hide_branding,
		];
	}

	private function sanitize_custom_cookies( $raw ) {
		$raw   = is_string( $raw ) ? $raw : '';
		$lines = preg_split( '/\\r\\n|\\r|\\n/', $raw );
		$clean = [];

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				continue;
			}

			$parts = array_map( 'trim', explode( '|', $line ) );
			if ( count( $parts ) < 5 ) {
				continue;
			}

			$parts   = array_slice( $parts, 0, 5 );
			$parts   = array_map( 'sanitize_text_field', $parts );
			$clean[] = implode( '|', $parts );
		}

		return implode( "\n", $clean );
	}

	/**
	 * Settings resolved for this request.
	 *
	 * @var array|null
	 */
	private static $settings_cache = null;

	/**
	 * Return the settings, merged over the defaults.
	 *
	 * Cached for the request: this is called from the blocker, Consent Mode,
	 * the enqueue pass and every field renderer, and building the defaults
	 * reads a file from disk.
	 *
	 * @return array
	 */
	public static function get_settings() {
		if ( null !== self::$settings_cache ) {
			return self::$settings_cache;
		}

		$defaults = self::get_default_settings();
		$settings = get_option( 'piensa_cookie_consent_settings', [] );

		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		self::$settings_cache = array_merge( $defaults, $settings );

		return self::$settings_cache;
	}

	/**
	 * Drop the cached settings after a write.
	 *
	 * @return void
	 */
	public static function flush_settings_cache() {
		self::$settings_cache = null;
	}

	/**
	 * Third-party hosts exempt from the consent block by default.
	 *
	 * The list lives in a data file rather than in this class. The plugin
	 * loads nothing from these hosts — they are hosts a site's own theme and
	 * plugins commonly use for fonts and scripts, which the blocker leaves
	 * alone because they set no tracking cookies. Written as PHP literals they
	 * read, to an analyser and to a reviewer, as the plugin offloading its own
	 * assets, which is a different thing and is not allowed.
	 *
	 * @return string One host per line.
	 */
	private static function get_default_allowed_domains() {
		$path = PIENSA_COOKIE_CONSENT_PATH . 'includes/data/technical-hosts.json';

		if ( ! is_readable( $path ) ) {
			return '';
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a file shipped inside the plugin, not a remote resource.
		$data = json_decode( (string) file_get_contents( $path ), true );

		if ( ! is_array( $data ) || empty( $data['hosts'] ) || ! is_array( $data['hosts'] ) ) {
			return '';
		}

		return implode( "\n", array_map( 'sanitize_text_field', $data['hosts'] ) );
	}

	private static function get_default_settings() {
		return [
			'enable_blocker'              => true,
			'block_unknown_third_party'   => true,
			// Two years: long enough to answer a challenge about a consent
			// given, short enough not to be a store of records nobody needs.
			'log_retention_days'          => 730,
			'allowed_domains'             => self::get_default_allowed_domains(),
			'blocked_domains'             => implode(
				"\n",
				[
					'youtube.com',
					'youtu.be',
					'vimeo.com',
					'google.com/maps',
					'maps.google.com',
					'soundcloud.com',
					'spotify.com',
					'facebook.com/plugins',
					'platform.twitter.com',
				]
			),
			'placeholder_title'           => __( 'External content blocked (privacy)', 'piensa-cookie-consent' ),
			'placeholder_button'          => __( 'Accept cookies to view', 'piensa-cookie-consent' ),
			'show_floating_button'        => true,
			'floating_button_text'        => __( 'Review consent', 'piensa-cookie-consent' ),
			'floating_button_style'       => 'icon',
			'enable_consent_log'          => true,
			'policy_revision'             => 1,
			'cookie_policy_url'           => '',
			'privacy_policy_url'          => '',
			'update_server_url'           => '',
			'update_channel'              => 'stable',
			'update_token'                => '',
			'update_public_key'           => '',
			'update_require_signature'    => true,
			'banner_title'                => __( 'Cookie preferences', 'piensa-cookie-consent' ),
			'banner_description'          => __( 'We use cookies to improve the experience and measure performance.', 'piensa-cookie-consent' ),
			'banner_accept_all'           => __( 'Accept all', 'piensa-cookie-consent' ),
			'banner_reject_all'           => __( 'Reject non-essential', 'piensa-cookie-consent' ),
			'banner_manage_prefs'         => __( 'Manage preferences', 'piensa-cookie-consent' ),
			'banner_save_prefs'           => __( 'Save preferences', 'piensa-cookie-consent' ),
			'banner_preferences_title'    => __( 'Cookie preferences', 'piensa-cookie-consent' ),
			'banner_show_icon'            => true,
			'banner_icon_style'           => 'cookie',
			'custom_cookies'              => '',
			'domain_overrides'            => [],
			'category_mode'               => 'auto',
			'allow_necessary_toggle'      => false,
			'analytics_enabled'           => true,
			'marketing_enabled'           => true,
			'necessary_label'             => __( 'Necessary cookies', 'piensa-cookie-consent' ),
			'necessary_description'       => __( 'Required for the basic functioning of the site.', 'piensa-cookie-consent' ),
			'necessary_legal_note'        => __( 'You can disable them, but some essential features may stop working.', 'piensa-cookie-consent' ),
			'analytics_label'             => __( 'Analytics cookies', 'piensa-cookie-consent' ),
			'analytics_description'       => __( 'Help us improve by measuring site usage.', 'piensa-cookie-consent' ),
			'marketing_label'             => __( 'Marketing cookies', 'piensa-cookie-consent' ),
			'marketing_description'       => __( 'Enable external content and personalized ads.', 'piensa-cookie-consent' ),
			'consent_layout'              => 'box',
			'consent_position'            => 'bottom right',
			'preferences_layout'          => 'box',
			'preferences_position'        => 'right',
			'theme_bg'                    => '#ffffff',
			'theme_primary_color'         => '#2c2f31',
			'theme_secondary_color'       => '#5e6266',
			'theme_btn_primary_bg'        => '#30363c',
			'theme_btn_primary_color'     => '#ffffff',
			'theme_btn_secondary_bg'      => '#eaeff2',
			'theme_btn_secondary_color'   => '#2c2f31',
			'theme_modal_radius'          => 8,
			'theme_button_radius'         => 6,
			'geo_mode'                    => 'all',
			'geo_countries'               => 'ES,FR,DE,IT,PT,NL,BE,LU,IE,AT,PL,SE,NO,FI,DK,GR,CZ,SK,HU,RO,BG,HR,SI,LV,LT,EE,IS,LI,CH,GB',
			'geo_header'                  => 'auto',
			'language_mode'               => 'auto',
			'default_language'            => 'es',
			'banner_title_en'             => 'Cookie preferences',
			'banner_description_en'       => 'We use cookies to improve the experience and measure performance.',
			'banner_accept_all_en'        => 'Accept all',
			'banner_reject_all_en'        => 'Reject non-essential',
			'banner_manage_prefs_en'      => 'Manage preferences',
			'banner_save_prefs_en'        => 'Save preferences',
			'banner_preferences_title_en' => 'Cookie preferences',
			'necessary_label_en'          => 'Necessary cookies',
			'necessary_description_en'    => 'Required for the basic functioning of the site.',
			'necessary_legal_note_en'     => 'You can disable them, but some essential features may stop working.',
			'analytics_label_en'          => 'Analytics cookies',
			'analytics_description_en'    => 'Help us improve by measuring site usage.',
			'marketing_label_en'          => 'Marketing cookies',
			'marketing_description_en'    => 'Enable external content and personalized ads.',
			'brand_name'                  => '',
			'brand_logo_url'              => '',
			'hide_branding'               => false,
		];
	}

	private static function get_theme_presets() {
		return [
			'classic'  => [
				'label'                     => 'Classic Light',
				'theme_bg'                  => '#ffffff',
				'theme_primary_color'       => '#1d2327',
				'theme_secondary_color'     => '#5e6266',
				'theme_btn_primary_bg'      => '#1d2327',
				'theme_btn_primary_color'   => '#ffffff',
				'theme_btn_secondary_bg'    => '#eaeff2',
				'theme_btn_secondary_color' => '#1d2327',
				'theme_modal_radius'        => 8,
				'theme_button_radius'       => 6,
				'consent_layout'            => 'box',
				'consent_position'          => 'bottom right',
				'preferences_layout'        => 'box',
				'preferences_position'      => 'right',
			],
			'sunrise'  => [
				'label'                     => 'Sunrise',
				'theme_bg'                  => '#fff7ed',
				'theme_primary_color'       => '#7c2d12',
				'theme_secondary_color'     => '#9a3412',
				'theme_btn_primary_bg'      => '#ea580c',
				'theme_btn_primary_color'   => '#ffffff',
				'theme_btn_secondary_bg'    => '#fed7aa',
				'theme_btn_secondary_color' => '#7c2d12',
				'theme_modal_radius'        => 12,
				'theme_button_radius'       => 10,
				'consent_layout'            => 'box',
				'consent_position'          => 'bottom left',
				'preferences_layout'        => 'box',
				'preferences_position'      => 'right',
			],
			'graphite' => [
				'label'                     => 'Graphite',
				'theme_bg'                  => '#111827',
				'theme_primary_color'       => '#f9fafb',
				'theme_secondary_color'     => '#cbd5e1',
				'theme_btn_primary_bg'      => '#0f766e',
				'theme_btn_primary_color'   => '#ffffff',
				'theme_btn_secondary_bg'    => '#1f2937',
				'theme_btn_secondary_color' => '#f9fafb',
				'theme_modal_radius'        => 10,
				'theme_button_radius'       => 8,
				'consent_layout'            => 'cloud',
				'consent_position'          => 'bottom center',
				'preferences_layout'        => 'bar',
				'preferences_position'      => 'right',
			],
		];
	}

	private function render_domain_select( $domain, $selected ) {
		$options = [
			'auto'      => 'Auto',
			'necessary' => __( 'Necessary', 'piensa-cookie-consent' ),
			'analytics' => __( 'Analytics', 'piensa-cookie-consent' ),
			'marketing' => 'Marketing',
			'unknown'   => __( 'Unknown', 'piensa-cookie-consent' ),
		];

		$html = '<select name="' . esc_attr( $this->option_name ) . '[domain_overrides][' . esc_attr( $domain ) . ']">';
		foreach ( $options as $value => $label ) {
			$is_selected = selected( $selected, $value, false );
			$html       .= '<option value="' . esc_attr( $value ) . '" ' . $is_selected . '>' . esc_html( $label ) . '</option>';
		}
		$html .= '</select>';

		return $html;
	}

	public function handle_scan_request() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_admin_referer( 'piensa_cookie_consent_scan' );

		$scanner = new Piensa_Cookie_Consent_Scanner();
		$result  = $scanner->scan_site( 25 );

		/* translators: 1: number of URLs crawled, 2: number of external domains found */
		$message = sprintf( esc_html__( 'Scan complete. URLs: %1$d, domains: %2$d', 'piensa-cookie-consent' ), $result['urls'], $result['domains'] );
		set_transient( 'piensa_cookie_consent_scan_notice', $message, 60 );

		wp_safe_redirect( admin_url( 'options-general.php?page=piensa-cookie-consent' ) );
		exit;
	}

	public function handle_export_logs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_admin_referer( 'piensa_cookie_consent_export_logs' );
		Piensa_Cookie_Consent_Consent_Log::export_logs();
	}

	public function handle_export_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_admin_referer( 'piensa_cookie_consent_export_settings' );
		$settings = self::get_settings();

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=piensa-cookie-consent-settings.json' );
		echo wp_json_encode( $settings );
		exit;
	}

	public function handle_import_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_admin_referer( 'piensa_cookie_consent_import_settings' );

		$redirect = admin_url( 'options-general.php?page=piensa-cookie-consent#ag-tab=tools' );

		// A path PHP itself wrote into $_FILES, not user-supplied text. It is
		// validated by is_uploaded_file() below, which is the only check that
		// means anything here; sanitising the string would not make an
		// arbitrary path safe to read.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$upload = isset( $_FILES['piensa_cookie_consent_settings_file']['tmp_name'] )
			? $_FILES['piensa_cookie_consent_settings_file']['tmp_name']
			: '';
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Only a genuine upload for this request may be read, never an
		// arbitrary path a crafted request might name.
		if ( ! is_string( $upload ) || $upload === '' || ! is_uploaded_file( $upload ) ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		$error = isset( $_FILES['piensa_cookie_consent_settings_file']['error'] )
			? (int) $_FILES['piensa_cookie_consent_settings_file']['error']
			: UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_OK !== $error ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		$contents = self::read_uploaded_file( $upload );
		$decoded  = is_string( $contents ) ? json_decode( $contents, true ) : null;
		if ( ! is_array( $decoded ) ) {
			wp_safe_redirect( $redirect );
			exit;
		}

		$sanitized = $this->sanitize_settings( $decoded );
		update_option( 'piensa_cookie_consent_settings', $sanitized, false );
		self::flush_settings_cache();

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Read an uploaded file through the WordPress filesystem abstraction.
	 *
	 * Going through WP_Filesystem keeps the plugin working on hosts where
	 * direct file access is not how WordPress writes, and is what the plugin
	 * directory expects instead of a bare file_get_contents().
	 *
	 * @param string $path Path to the uploaded temporary file.
	 *
	 * @return string|false File contents, or false on failure.
	 */
	private static function read_uploaded_file( $path ) {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		if ( ! $wp_filesystem ) {
			return false;
		}

		return $wp_filesystem->get_contents( $path );
	}

	public function handle_report_html() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_admin_referer( 'piensa_cookie_consent_report_html' );
		$scanner = new Piensa_Cookie_Consent_Scanner();
		$report  = $scanner->get_report_data();

		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=piensa-cookie-consent-report.html' );

		echo '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html__( 'Piensa Cookie Consent', 'piensa-cookie-consent' ) . ' Report</title>';
		echo '<style>body{font-family:Arial,sans-serif;margin:20px;color:#111;}h1{margin-bottom:6px;}table{border-collapse:collapse;width:100%;margin-top:12px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#f3f4f6;}</style>';
		echo '</head><body>';
		echo '<h1>' . esc_html__( 'Piensa Cookie Consent', 'piensa-cookie-consent' ) . ' - ' . esc_html__( 'Report', 'piensa-cookie-consent' ) . '</h1>';
		echo '<p><strong>' . esc_html__( 'Date', 'piensa-cookie-consent' ) . ':</strong> ' . esc_html( $report['generated_at'] ) . '</p>';
		echo '<p><strong>Sitio:</strong> ' . esc_html( $report['site_url'] ) . '</p>';
		echo '<h2>' . esc_html__( 'Categories', 'piensa-cookie-consent' ) . '</h2>';
		echo '<table><thead><tr><th>Categoria</th><th>Descripcion</th><th>Cookies definidas</th><th>' . esc_html__( 'Detected cookies', 'piensa-cookie-consent' ) . '</th></tr></thead><tbody>';
		foreach ( $report['categories'] as $category ) {
			$detected = isset( $category['detected_count'] ) ? $category['detected_count'] : 0;
			echo '<tr><td>' . esc_html( $category['label'] ) . '</td><td>' . esc_html( $category['description'] ) . '</td><td>' . esc_html( $category['cookies_count'] ) . '</td><td>' . esc_html( $detected ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>' . esc_html__( 'Detected domains', 'piensa-cookie-consent' ) . '</h2>';
		echo '<table><thead><tr><th>Dominio</th><th>Categoria</th><th>Servicio</th><th>Ultima deteccion</th></tr></thead><tbody>';
		foreach ( $report['domains'] as $domain ) {
			echo '<tr><td>' . esc_html( $domain['domain'] ) . '</td><td>' . esc_html( $domain['category'] ) . '</td><td>' . esc_html( $domain['service'] ) . '</td><td>' . esc_html( $domain['last_seen'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<h2>' . esc_html__( 'Detected cookies', 'piensa-cookie-consent' ) . '</h2>';
		echo '<table><thead><tr><th>Cookie</th><th>Dominio</th><th>Categoria</th><th>Ultima deteccion</th></tr></thead><tbody>';
		if ( ! empty( $report['cookies'] ) ) {
			foreach ( $report['cookies'] as $cookie ) {
				echo '<tr><td>' . esc_html( $cookie['name'] ) . '</td><td>' . esc_html( $cookie['domain'] ) . '</td><td>' . esc_html( $cookie['category'] ) . '</td><td>' . esc_html( $cookie['last_seen'] ) . '</td></tr>';
			}
		}
		echo '</tbody></table>';
		echo '</body></html>';
		exit;
	}

	public function handle_report_json() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Not authorized.', 'piensa-cookie-consent' ) );
		}

		check_admin_referer( 'piensa_cookie_consent_report_json' );
		$scanner = new Piensa_Cookie_Consent_Scanner();
		$report  = $scanner->get_report_data();

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=piensa-cookie-consent-report.json' );
		echo wp_json_encode( $report );
		exit;
	}

	public function handle_collect_cookies() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Not authorized', 'piensa-cookie-consent' ) ], 403 );
		}

		check_ajax_referer( 'piensa_cookie_consent_collect_cookies', 'nonce' );

		// A JSON document; it is decoded and its fields sanitised individually below.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw     = isset( $_POST['cookies'] ) ? wp_unslash( $_POST['cookies'] ) : '';
		$cookies = json_decode( $raw, true );
		if ( ! is_array( $cookies ) ) {
			$cookies = [];
		}

		$domain = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		if ( $domain === '' ) {
			$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		}

		$host       = wp_parse_url( home_url(), PHP_URL_HOST );
		$normalized = [];
		foreach ( $cookies as $cookie ) {
			$name = sanitize_text_field( $cookie );
			if ( $name === '' ) {
				continue;
			}
			$normalized[] = [
				'name'      => $name,
				'domain'    => $domain,
				'category'  => Piensa_Cookie_Consent_Scanner::categorize_cookie_for_site( $name, $domain, $host ),
				'last_seen' => time(),
			];
		}

		if ( ! $normalized ) {
			wp_send_json_success( [ 'count' => 0 ] );
		}

		$existing = get_option( 'piensa_cookie_consent_detected_cookies', [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		$merged = Piensa_Cookie_Consent_Scanner::merge_detected_cookies( $existing, $normalized );
		update_option( 'piensa_cookie_consent_detected_cookies', $merged, false );

		wp_send_json_success( [ 'count' => count( $normalized ) ] );
	}

	private function get_health_issues() {
		$issues   = [];
		$settings = self::get_settings();

		if ( $settings['geo_mode'] === 'none' ) {
			$issues[] = esc_html__( 'The CMP is switched off by geo-targeting. Check that this meets your obligations.', 'piensa-cookie-consent' );
		}
		if ( $settings['geo_mode'] === 'custom' && trim( $settings['geo_countries'] ) === '' ) {
			$issues[] = esc_html__( 'Geo-targeting is set to a custom list, but no countries are defined.', 'piensa-cookie-consent' );
		}

		if ( $settings['enable_consent_log'] === false ) {
			$issues[] = esc_html__( 'The consent log is switched off.', 'piensa-cookie-consent' );
		}

		$discovered = get_option( 'piensa_cookie_consent_discovered', [] );
		if ( is_array( $discovered ) ) {
			$unknown = 0;
			foreach ( $discovered as $data ) {
				if ( ! empty( $data['category'] ) && $data['category'] === 'unknown' ) {
					++$unknown;
				}
			}
			if ( $unknown > 0 ) {
				/* translators: %d: number of domains without a category */
				$issues[] = sprintf( esc_html__( '%d domains are still unclassified.', 'piensa-cookie-consent' ), $unknown );
			}
		}

		if ( empty( $settings['cookie_policy_url'] ) ) {
			$issues[] = esc_html__( 'No cookie policy URL is set.', 'piensa-cookie-consent' );
		}

		if ( empty( $settings['privacy_policy_url'] ) ) {
			$issues[] = esc_html__( 'No privacy policy URL is set.', 'piensa-cookie-consent' );
		}

		return $issues;
	}
}
