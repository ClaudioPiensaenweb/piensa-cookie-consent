<?php
// includes/class-admin.php

if (!defined('ABSPATH')) {
    exit;
}

class Agency_Shield_Admin {
    private $option_name = 'agency_shield_cmp_settings';
    private $menu_hook = '';

    public function init() {
        if (is_admin()) {
            add_action('admin_menu', [$this, 'register_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('admin_post_agency_shield_cmp_scan', [$this, 'handle_scan_request']);
            add_action('admin_post_agency_shield_cmp_export_logs', [$this, 'handle_export_logs']);
            add_action('admin_post_agency_shield_cmp_export_settings', [$this, 'handle_export_settings']);
            add_action('admin_post_agency_shield_cmp_import_settings', [$this, 'handle_import_settings']);
            add_action('admin_post_agency_shield_cmp_report_html', [$this, 'handle_report_html']);
            add_action('admin_post_agency_shield_cmp_report_json', [$this, 'handle_report_json']);
            add_action('wp_ajax_agency_shield_cmp_preview', [$this, 'render_preview']);
            add_action('wp_ajax_agency_shield_cmp_collect_cookies', [$this, 'handle_collect_cookies']);
        }
    }

    public function register_menu() {
        // Menu principal independiente con icono
        $this->menu_hook = add_menu_page(
            'PW Cookie Monster',
            'PW Cookie Monster',
            'manage_options',
            'agency-shield-cmp',
            [$this, 'render_settings_page'],
            'dashicons-admin-generic',
            80
        );

        // Submenus
        add_submenu_page(
            'agency-shield-cmp',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'agency-shield-cmp'
        );

        add_submenu_page(
            'agency-shield-cmp',
            'Apariencia',
            'Apariencia',
            'manage_options',
            'agency-shield-cmp&tab=apariencia',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'agency-shield-cmp',
            'Scanner',
            'Scanner',
            'manage_options',
            'agency-shield-cmp&tab=scanner',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'agency-shield-cmp',
            'Logs',
            'Logs',
            'manage_options',
            'agency-shield-cmp&tab=logs',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        register_setting('agency_shield_cmp_settings', $this->option_name, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);

        add_settings_section(
            'agency_shield_cmp_main',
            'Configuracion general',
            function () {
                echo '<p>Configura el funcionamiento general del CMP.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_blocker',
            'Bloqueo de contenido',
            [$this, 'render_blocker_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_main'
        );

        add_settings_field(
            'agency_shield_cmp_consent_log',
            'Registro de consentimiento',
            [$this, 'render_consent_log_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_main'
        );

        add_settings_field(
            'agency_shield_cmp_policy_revision',
            'Revision de consentimiento',
            [$this, 'render_policy_revision_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_main'
        );

        add_settings_section(
            'agency_shield_cmp_banner',
            'Textos del banner',
            function () {
                echo '<p>Personaliza el banner y el modal de preferencias.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_banner_icon',
            'Icono del banner',
            [$this, 'render_banner_icon_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_banner'
        );

        add_settings_field(
            'agency_shield_cmp_banner_title',
            'Titulo del banner',
            [$this, 'render_banner_title_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_banner'
        );

        add_settings_field(
            'agency_shield_cmp_banner_description',
            'Descripcion del banner',
            [$this, 'render_banner_description_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_banner'
        );

        add_settings_field(
            'agency_shield_cmp_banner_accept_all',
            'Texto aceptar todas',
            [$this, 'render_banner_accept_all_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_banner'
        );

        add_settings_field(
            'agency_shield_cmp_banner_reject_all',
            'Texto rechazar no necesarias',
            [$this, 'render_banner_reject_all_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_banner'
        );

        add_settings_field(
            'agency_shield_cmp_banner_manage',
            'Texto gestionar preferencias',
            [$this, 'render_banner_manage_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_banner'
        );

        add_settings_field(
            'agency_shield_cmp_banner_save',
            'Texto guardar preferencias',
            [$this, 'render_banner_save_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_banner'
        );

        add_settings_field(
            'agency_shield_cmp_banner_preferences_title',
            'Titulo del modal',
            [$this, 'render_banner_preferences_title_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_banner'
        );

        add_settings_section(
            'agency_shield_cmp_categories',
            'Categorias y comportamiento',
            function () {
                echo '<p>Define etiquetas y comportamiento de categorias.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_category_mode',
            'Modo de categorias',
            [$this, 'render_category_mode_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_categories'
        );

        add_settings_field(
            'agency_shield_cmp_necessary_toggle',
            'Cookies necesarias',
            [$this, 'render_necessary_toggle_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_categories'
        );

        add_settings_field(
            'agency_shield_cmp_category_toggles',
            'Categorias activas',
            [$this, 'render_category_toggles_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_categories'
        );

        add_settings_field(
            'agency_shield_cmp_category_labels',
            'Etiquetas y textos',
            [$this, 'render_category_labels_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_categories'
        );

        add_settings_section(
            'agency_shield_cmp_appearance',
            'Apariencia y posicion',
            function () {
                echo '<p>Controla la posicion del banner y los estilos principales.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_layout_position',
            'Posicion y layout',
            [$this, 'render_layout_position_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_appearance'
        );

        add_settings_field(
            'agency_shield_cmp_theme_colors',
            'Colores y radios',
            [$this, 'render_theme_colors_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_appearance'
        );

        add_settings_field(
            'agency_shield_cmp_theme_presets',
            'Plantillas rapidas',
            [$this, 'render_theme_presets_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_appearance'
        );

        add_settings_field(
            'agency_shield_cmp_theme_preview',
            'Preview',
            [$this, 'render_theme_preview_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_appearance'
        );

        add_settings_section(
            'agency_shield_cmp_compliance',
            'Cumplimiento y privacidad',
            function () {
                echo '<p>Define geolocalizacion, idioma y requisitos legales.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_geo_targeting',
            'Geo-targeting',
            [$this, 'render_geo_targeting_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_compliance'
        );

        add_settings_field(
            'agency_shield_cmp_language',
            'Idioma y deteccion',
            [$this, 'render_language_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_compliance'
        );

        add_settings_field(
            'agency_shield_cmp_report',
            'Informe de cumplimiento',
            [$this, 'render_report_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_compliance'
        );

        add_settings_section(
            'agency_shield_cmp_languages',
            'Textos multilenguaje',
            function () {
                echo '<p>Define textos en ES y EN.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_texts_en',
            'Textos EN',
            [$this, 'render_texts_en_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_languages'
        );

        add_settings_section(
            'agency_shield_cmp_branding',
            'Branding y white-label',
            function () {
                echo '<p>Personaliza el nombre y oculta marcas.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_branding_fields',
            'Branding',
            [$this, 'render_branding_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_branding'
        );

        add_settings_section(
            'agency_shield_cmp_tools',
            'Importar / Exportar',
            function () {
                echo '<p>Exporta o importa la configuracion completa.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_export',
            'Exportar',
            [$this, 'render_export_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_tools'
        );

        add_settings_field(
            'agency_shield_cmp_import',
            'Importar',
            [$this, 'render_import_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_tools'
        );

        add_settings_section(
            'agency_shield_cmp_health',
            'Health check',
            function () {
                echo '<p>Revisa posibles riesgos de cumplimiento.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_health_view',
            'Estado',
            [$this, 'render_health_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_health'
        );

        add_settings_section(
            'agency_shield_cmp_content',
            'Contenido bloqueado',
            function () {
                echo '<p>Dominios que deben neutralizarse hasta que haya consentimiento.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_domains',
            'Dominios bloqueados',
            [$this, 'render_domains_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_content'
        );

        add_settings_section(
            'agency_shield_cmp_texts',
            'Textos del placeholder',
            function () {
                echo '<p>Personaliza el mensaje mostrado sobre el contenido bloqueado.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_placeholder_title',
            'Mensaje principal',
            [$this, 'render_placeholder_title_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_texts'
        );

        add_settings_field(
            'agency_shield_cmp_placeholder_button',
            'Texto del boton',
            [$this, 'render_placeholder_button_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_texts'
        );

        add_settings_section(
            'agency_shield_cmp_ui',
            'Interfaz de consentimiento',
            function () {
                echo '<p>Controla el boton flotante para revisar el consentimiento.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_floating_button',
            'Boton flotante',
            [$this, 'render_floating_button_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_ui'
        );

        add_settings_field(
            'agency_shield_cmp_floating_button_text',
            'Texto del boton',
            [$this, 'render_floating_button_text_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_ui'
        );

        add_settings_field(
            'agency_shield_cmp_floating_button_style',
            'Estilo del boton',
            [$this, 'render_floating_button_style_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_ui'
        );

        add_settings_section(
            'agency_shield_cmp_discovery',
            'Descubrimiento automatico',
            function () {
                echo '<p>Se detectan recursos externos (scripts, iframes, imagenes) para sugerir categorias.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_discovered',
            'Dominios detectados',
            [$this, 'render_discovered_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_discovery'
        );

        add_settings_field(
            'agency_shield_cmp_cookie_audit',
            'Auditoria en navegador',
            [$this, 'render_cookie_audit_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_discovery'
        );

        add_settings_field(
            'agency_shield_cmp_detected_cookies',
            'Cookies detectadas',
            [$this, 'render_detected_cookies_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_discovery'
        );

        add_settings_section(
            'agency_shield_cmp_policy',
            'Politica de cookies',
            function () {
                echo '<p>Enlaza tu politica y define cookies personalizadas.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_cookie_policy_url',
            'URL politica de cookies',
            [$this, 'render_cookie_policy_url_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_policy'
        );

        add_settings_field(
            'agency_shield_cmp_privacy_policy_url',
            'URL politica de privacidad',
            [$this, 'render_privacy_policy_url_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_policy'
        );

        add_settings_field(
            'agency_shield_cmp_custom_cookies',
            'Cookies personalizadas',
            [$this, 'render_custom_cookies_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_policy'
        );

        add_settings_section(
            'agency_shield_cmp_logs',
            'Registro de consentimientos',
            function () {
                echo '<p>Ultimos consentimientos registrados.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_logs_table',
            'Logs',
            [$this, 'render_logs_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_logs'
        );

        add_settings_section(
            'agency_shield_cmp_updates',
            'Actualizaciones seguras',
            function () {
                echo '<p>Configura el servidor central de actualizaciones y la firma.</p>';
            },
            'agency-shield-cmp'
        );

        add_settings_field(
            'agency_shield_cmp_update_server',
            'Servidor de actualizaciones',
            [$this, 'render_update_server_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_updates'
        );

        add_settings_field(
            'agency_shield_cmp_update_channel',
            'Canal',
            [$this, 'render_update_channel_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_updates'
        );

        add_settings_field(
            'agency_shield_cmp_update_token',
            'Token de acceso',
            [$this, 'render_update_token_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_updates'
        );

        add_settings_field(
            'agency_shield_cmp_update_public_key',
            'Clave publica',
            [$this, 'render_update_public_key_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_updates'
        );

        add_settings_field(
            'agency_shield_cmp_update_signature',
            'Verificacion de firma',
            [$this, 'render_update_signature_field'],
            'agency-shield-cmp',
            'agency_shield_cmp_updates'
        );
    }

    public function render_blocker_field() {
        $settings = self::get_settings();
        $checked = $settings['enable_blocker'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[enable_blocker]" value="1" ' . $checked . '> Activar bloqueo de iframes externos</label>';
    }

    public function render_consent_log_field() {
        $settings = self::get_settings();
        $checked = $settings['enable_consent_log'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[enable_consent_log]" value="1" ' . $checked . '> Guardar registro de consentimiento</label>';
    }

    public function render_policy_revision_field() {
        $settings = self::get_settings();
        $value = (int) $settings['policy_revision'];
        echo '<input class="small-text" type="number" min="0" name="' . esc_attr($this->option_name) . '[policy_revision]" value="' . $value . '" />';
        echo '<p class="description">Incrementa este valor cuando cambies el texto o la politica para solicitar nuevo consentimiento.</p>';
    }

    public function render_banner_icon_field() {
        $settings = self::get_settings();
        $show_icon = !empty($settings['banner_show_icon']);
        $icon_style = isset($settings['banner_icon_style']) ? $settings['banner_icon_style'] : 'cookie';

        echo '<div class="ag-field-group">';
        echo '<label style="display:block;margin-bottom:12px;"><input type="checkbox" name="' . esc_attr($this->option_name) . '[banner_show_icon]" value="1" ' . ($show_icon ? 'checked' : '') . '> Mostrar icono en el banner</label>';
        echo '<div class="ag-icon-preview" style="display:flex;gap:12px;align-items:stretch;flex-wrap:wrap;">';

        $icons = [
            'cookie' => ['label' => 'Cookie', 'class' => 'fa-solid fa-cookie'],
            'cookie-bite' => ['label' => 'Cookie mordida', 'class' => 'fa-solid fa-cookie-bite'],
            'shield' => ['label' => 'Escudo', 'class' => 'fa-solid fa-shield-halved'],
            'lock' => ['label' => 'Candado', 'class' => 'fa-solid fa-lock'],
            'fingerprint' => ['label' => 'Huella', 'class' => 'fa-solid fa-fingerprint'],
        ];

        foreach ($icons as $key => $data) {
            $checked = $icon_style === $key ? 'checked' : '';
            $border_color = $icon_style === $key ? '#2271b1' : '#dcdcde';
            $bg_color = $icon_style === $key ? '#f0f6fc' : '#fff';
            echo '<label style="display:flex;flex-direction:column;align-items:center;gap:8px;cursor:pointer;padding:14px 16px;border:2px solid ' . $border_color . ';border-radius:10px;min-width:90px;background:' . $bg_color . ';transition:all 0.15s ease;">';
            echo '<input type="radio" name="' . esc_attr($this->option_name) . '[banner_icon_style]" value="' . esc_attr($key) . '" ' . $checked . ' style="display:none;">';
            echo '<i class="' . esc_attr($data['class']) . '" style="font-size:28px;color:#1d2327;"></i>';
            echo '<span style="font-size:11px;color:#50575e;font-weight:500;">' . esc_html($data['label']) . '</span>';
            echo '</label>';
        }

        echo '</div>';
        echo '<p class="description" style="margin-top:12px;">Iconos via Font Awesome 6. Aparece junto al titulo del banner.</p>';
        echo '</div>';
    }

    public function render_banner_title_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['banner_title']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_title]" value="' . $value . '" />';
    }

    public function render_banner_description_field() {
        $settings = self::get_settings();
        $value = esc_textarea($settings['banner_description']);
        echo '<textarea class="large-text" rows="3" name="' . esc_attr($this->option_name) . '[banner_description]">' . $value . '</textarea>';
    }

    public function render_banner_accept_all_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['banner_accept_all']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_accept_all]" value="' . $value . '" />';
    }

    public function render_banner_reject_all_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['banner_reject_all']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_reject_all]" value="' . $value . '" />';
    }

    public function render_banner_manage_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['banner_manage_prefs']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_manage_prefs]" value="' . $value . '" />';
    }

    public function render_banner_save_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['banner_save_prefs']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_save_prefs]" value="' . $value . '" />';
    }

    public function render_banner_preferences_title_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['banner_preferences_title']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_preferences_title]" value="' . $value . '" />';
    }

    public function render_category_mode_field() {
        $settings = self::get_settings();
        $value = $settings['category_mode'];
        echo '<select name="' . esc_attr($this->option_name) . '[category_mode]">';
        echo '<option value="auto"' . selected($value, 'auto', false) . '>Auto (detectar plugins)</option>';
        echo '<option value="manual"' . selected($value, 'manual', false) . '>Manual</option>';
        echo '</select>';
        echo '<p class="description">En manual puedes forzar si se muestran categorias.</p>';
    }

    public function render_necessary_toggle_field() {
        $settings = self::get_settings();
        $checked = !empty($settings['allow_necessary_toggle']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[allow_necessary_toggle]" value="1" ' . $checked . '> Permitir desactivar cookies necesarias</label>';
        echo '<p class="description">No recomendado: puede afectar el funcionamiento del sitio.</p>';
    }

    public function render_category_toggles_field() {
        $settings = self::get_settings();
        $analytics = !empty($settings['analytics_enabled']) ? 'checked' : '';
        $marketing = !empty($settings['marketing_enabled']) ? 'checked' : '';
        echo '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="' . esc_attr($this->option_name) . '[analytics_enabled]" value="1" ' . $analytics . '> Analytics</label>';
        echo '<label style="display:block;"><input type="checkbox" name="' . esc_attr($this->option_name) . '[marketing_enabled]" value="1" ' . $marketing . '> Marketing</label>';
    }

    public function render_category_labels_field() {
        $settings = self::get_settings();
        echo '<div class="ag-field-group">';
        echo '<strong>Necesarias</strong>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[necessary_label]" value="' . esc_attr($settings['necessary_label']) . '" />';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[necessary_description]">' . esc_textarea($settings['necessary_description']) . '</textarea>';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[necessary_legal_note]" placeholder="Nota legal (opcional)">' . esc_textarea($settings['necessary_legal_note']) . '</textarea>';
        echo '</div>';
        echo '<div class="ag-field-group">';
        echo '<strong>Analitica</strong>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[analytics_label]" value="' . esc_attr($settings['analytics_label']) . '" />';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[analytics_description]">' . esc_textarea($settings['analytics_description']) . '</textarea>';
        echo '</div>';
        echo '<div class="ag-field-group">';
        echo '<strong>Marketing</strong>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[marketing_label]" value="' . esc_attr($settings['marketing_label']) . '" />';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[marketing_description]">' . esc_textarea($settings['marketing_description']) . '</textarea>';
        echo '</div>';
    }

    public function render_layout_position_field() {
        $settings = self::get_settings();
        $consent_layout = $settings['consent_layout'];
        $consent_position = $settings['consent_position'];
        $preferences_layout = $settings['preferences_layout'];
        $preferences_position = $settings['preferences_position'];

        echo '<div class="ag-field-group">';
        echo '<strong>Banner</strong>';
        echo '<select name="' . esc_attr($this->option_name) . '[consent_layout]">';
        echo '<option value="box"' . selected($consent_layout, 'box', false) . '>Box</option>';
        echo '<option value="cloud"' . selected($consent_layout, 'cloud', false) . '>Cloud</option>';
        echo '<option value="bar"' . selected($consent_layout, 'bar', false) . '>Bar</option>';
        echo '</select> ';
        echo '<select name="' . esc_attr($this->option_name) . '[consent_position]">';
        echo '<option value="bottom right"' . selected($consent_position, 'bottom right', false) . '>Abajo derecha</option>';
        echo '<option value="bottom left"' . selected($consent_position, 'bottom left', false) . '>Abajo izquierda</option>';
        echo '<option value="bottom center"' . selected($consent_position, 'bottom center', false) . '>Abajo centro</option>';
        echo '<option value="top right"' . selected($consent_position, 'top right', false) . '>Arriba derecha</option>';
        echo '<option value="top left"' . selected($consent_position, 'top left', false) . '>Arriba izquierda</option>';
        echo '<option value="top center"' . selected($consent_position, 'top center', false) . '>Arriba centro</option>';
        echo '</select>';
        echo '</div>';

        echo '<div class="ag-field-group">';
        echo '<strong>Preferencias</strong>';
        echo '<select name="' . esc_attr($this->option_name) . '[preferences_layout]">';
        echo '<option value="box"' . selected($preferences_layout, 'box', false) . '>Box</option>';
        echo '<option value="bar"' . selected($preferences_layout, 'bar', false) . '>Bar</option>';
        echo '</select> ';
        echo '<select name="' . esc_attr($this->option_name) . '[preferences_position]">';
        echo '<option value="right"' . selected($preferences_position, 'right', false) . '>Derecha</option>';
        echo '<option value="left"' . selected($preferences_position, 'left', false) . '>Izquierda</option>';
        echo '<option value="center"' . selected($preferences_position, 'center', false) . '>Centro</option>';
        echo '</select>';
        echo '</div>';
    }

    public function render_theme_colors_field() {
        $settings = self::get_settings();
        $fields = [
            ['key' => 'theme_bg', 'label' => 'Fondo modal', 'var' => '--cc-bg'],
            ['key' => 'theme_primary_color', 'label' => 'Texto principal', 'var' => '--cc-primary-color'],
            ['key' => 'theme_secondary_color', 'label' => 'Texto secundario', 'var' => '--cc-secondary-color'],
            ['key' => 'theme_btn_primary_bg', 'label' => 'Boton primario', 'var' => '--cc-btn-primary-bg'],
            ['key' => 'theme_btn_primary_color', 'label' => 'Texto boton primario', 'var' => '--cc-btn-primary-color'],
            ['key' => 'theme_btn_secondary_bg', 'label' => 'Boton secundario', 'var' => '--cc-btn-secondary-bg'],
            ['key' => 'theme_btn_secondary_color', 'label' => 'Texto boton secundario', 'var' => '--cc-btn-secondary-color'],
        ];

        echo '<div class="ag-color-editor">';
        foreach ($fields as $field) {
            $key = $field['key'];
            $value = esc_attr($settings[$key]);
            echo '<div class="ag-color-item">';
            echo '<div class="ag-color-meta">';
            echo '<strong>' . esc_html($field['label']) . '</strong>';
            echo '<code>' . esc_html($field['var']) . '</code>';
            echo '</div>';
            echo '<div class="ag-color-controls">';
            echo '<input type="color" data-ag-color="' . esc_attr($key) . '" name="' . esc_attr($this->option_name) . '[' . esc_attr($key) . ']" value="' . $value . '" />';
            echo '<input type="text" class="regular-text ag-color-text" data-ag-color-text="' . esc_attr($key) . '" value="' . $value . '" placeholder="#ffffff" />';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
        echo '<div class="ag-field-grid" style="margin-top:12px;">';
        echo '<label>Radio modal (px) <input class="small-text" type="number" min="0" name="' . esc_attr($this->option_name) . '[theme_modal_radius]" value="' . esc_attr($settings['theme_modal_radius']) . '" /></label>';
        echo '<label>Radio botones (px) <input class="small-text" type="number" min="0" name="' . esc_attr($this->option_name) . '[theme_button_radius]" value="' . esc_attr($settings['theme_button_radius']) . '" /></label>';
        echo '</div>';
    }

    public function render_theme_presets_field() {
        $presets = self::get_theme_presets();
        echo '<div class="ag-field-row">';
        echo '<select class="ag-preset-select" name="agency_shield_cmp_preset_select">';
        foreach ($presets as $key => $preset) {
            echo '<option value="' . esc_attr($key) . '">' . esc_html($preset['label']) . '</option>';
        }
        echo '</select>';
        echo '<button type="button" class="button ag-apply-preset" data-ag-apply-preset="1">Aplicar plantilla</button>';
        echo '<span class="description">Aplica colores, radios y layouts.</span>';
        echo '</div>';
    }

    public function render_theme_preview_field() {
        $preview_url = admin_url('admin-ajax.php?action=agency_shield_cmp_preview');
        $nonce = wp_create_nonce('agency_shield_cmp_preview');
        echo '<div class="ag-preview-frame-wrap">';
        echo '<iframe class="ag-preview-frame" data-ag-preview-frame src="' . esc_url($preview_url . '&nonce=' . $nonce) . '" loading="lazy"></iframe>';
        echo '</div>';
        echo '<p class="description">Vista previa real del banner. Se actualiza al cambiar los campos.</p>';
    }

    public function render_geo_targeting_field() {
        $settings = self::get_settings();
        $mode = $settings['geo_mode'];
        $countries = esc_textarea($settings['geo_countries']);
        $header = $settings['geo_header'];

        echo '<div class="ag-field-group">';
        echo '<select name="' . esc_attr($this->option_name) . '[geo_mode]">';
        echo '<option value="all"' . selected($mode, 'all', false) . '>Mostrar siempre</option>';
        echo '<option value="eea"' . selected($mode, 'eea', false) . '>Solo EEE + UK/CH</option>';
        echo '<option value="custom"' . selected($mode, 'custom', false) . '>Solo paises definidos</option>';
        echo '<option value="none"' . selected($mode, 'none', false) . '>No mostrar (desactivar CMP)</option>';
        echo '</select>';
        echo '<p class="description">Si no hay cabecera de geo, se mostrara siempre.</p>';
        echo '</div>';
        echo '<div class="ag-field-group">';
        echo '<textarea class="large-text" rows="3" name="' . esc_attr($this->option_name) . '[geo_countries]">' . $countries . '</textarea>';
        echo '<p class="description">Codigos ISO2 separados por coma (ej: ES,FR,DE). Solo aplica en modo custom.</p>';
        echo '</div>';
        echo '<div class="ag-field-group">';
        echo '<select name="' . esc_attr($this->option_name) . '[geo_header]">';
        echo '<option value="auto"' . selected($header, 'auto', false) . '>Auto detectar (CF/IP/Geo)</option>';
        echo '<option value="CF-IPCountry"' . selected($header, 'CF-IPCountry', false) . '>CF-IPCountry</option>';
        echo '<option value="X-GeoIP-Country"' . selected($header, 'X-GeoIP-Country', false) . '>X-GeoIP-Country</option>';
        echo '<option value="X-Country-Code"' . selected($header, 'X-Country-Code', false) . '>X-Country-Code</option>';
        echo '<option value="X-Geo-Country"' . selected($header, 'X-Geo-Country', false) . '>X-Geo-Country</option>';
        echo '</select>';
        echo '<p class="description">Selecciona la cabecera que tu hosting/CDN expone.</p>';
        echo '</div>';
    }

    public function render_language_field() {
        $settings = self::get_settings();
        $mode = $settings['language_mode'];
        $default = $settings['default_language'];

        echo '<div class="ag-field-group">';
        echo '<select name="' . esc_attr($this->option_name) . '[language_mode]">';
        echo '<option value="auto"' . selected($mode, 'auto', false) . '>Auto (documento o navegador)</option>';
        echo '<option value="site"' . selected($mode, 'site', false) . '>Idioma del sitio (WP)</option>';
        echo '<option value="browser"' . selected($mode, 'browser', false) . '>Idioma del navegador</option>';
        echo '<option value="custom"' . selected($mode, 'custom', false) . '>Forzar idioma</option>';
        echo '</select>';
        echo '</div>';
        echo '<div class="ag-field-group">';
        echo '<select name="' . esc_attr($this->option_name) . '[default_language]">';
        echo '<option value="es"' . selected($default, 'es', false) . '>Espanol</option>';
        echo '<option value="en"' . selected($default, 'en', false) . '>English</option>';
        echo '</select>';
        echo '<p class="description">Se usa si no se puede detectar.</p>';
        echo '</div>';
    }

    public function render_report_field() {
        $html_url = wp_nonce_url(admin_url('admin-post.php?action=agency_shield_cmp_report_html'), 'agency_shield_cmp_report_html');
        $json_url = wp_nonce_url(admin_url('admin-post.php?action=agency_shield_cmp_report_json'), 'agency_shield_cmp_report_json');
        echo '<a class="button" href="' . esc_url($html_url) . '">Descargar informe HTML</a> ';
        echo '<a class="button" href="' . esc_url($json_url) . '">Descargar informe JSON</a>';
    }

    public function render_texts_en_field() {
        $settings = self::get_settings();
        echo '<div class="ag-field-group">';
        echo '<strong>Banner</strong>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_title_en]" value="' . esc_attr($settings['banner_title_en']) . '" placeholder="Title" />';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[banner_description_en]">' . esc_textarea($settings['banner_description_en']) . '</textarea>';
        echo '<div class="ag-field-row">';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_accept_all_en]" value="' . esc_attr($settings['banner_accept_all_en']) . '" placeholder="Accept all" />';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_reject_all_en]" value="' . esc_attr($settings['banner_reject_all_en']) . '" placeholder="Reject" />';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_manage_prefs_en]" value="' . esc_attr($settings['banner_manage_prefs_en']) . '" placeholder="Manage preferences" />';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_save_prefs_en]" value="' . esc_attr($settings['banner_save_prefs_en']) . '" placeholder="Save preferences" />';
        echo '</div>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[banner_preferences_title_en]" value="' . esc_attr($settings['banner_preferences_title_en']) . '" placeholder="Preferences title" />';
        echo '</div>';
        echo '<div class="ag-field-group">';
        echo '<strong>Categories</strong>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[necessary_label_en]" value="' . esc_attr($settings['necessary_label_en']) . '" placeholder="Necessary" />';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[necessary_description_en]">' . esc_textarea($settings['necessary_description_en']) . '</textarea>';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[necessary_legal_note_en]" placeholder="Legal note (optional)">' . esc_textarea($settings['necessary_legal_note_en']) . '</textarea>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[analytics_label_en]" value="' . esc_attr($settings['analytics_label_en']) . '" placeholder="Analytics" />';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[analytics_description_en]">' . esc_textarea($settings['analytics_description_en']) . '</textarea>';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[marketing_label_en]" value="' . esc_attr($settings['marketing_label_en']) . '" placeholder="Marketing" />';
        echo '<textarea class="large-text" rows="2" name="' . esc_attr($this->option_name) . '[marketing_description_en]">' . esc_textarea($settings['marketing_description_en']) . '</textarea>';
        echo '</div>';
    }

    public function render_branding_field() {
        $settings = self::get_settings();
        $checked = $settings['hide_branding'] ? 'checked' : '';
        echo '<div class="ag-field-group">';
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[brand_name]" value="' . esc_attr($settings['brand_name']) . '" placeholder="Nombre de marca (opcional)" />';
        echo '<input class="regular-text" type="url" name="' . esc_attr($this->option_name) . '[brand_logo_url]" value="' . esc_attr($settings['brand_logo_url']) . '" placeholder="URL del logo (opcional)" />';
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[hide_branding]" value="1" ' . $checked . '> Ocultar branding en el banner</label>';
        echo '</div>';
    }

    public function render_export_field() {
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=agency_shield_cmp_export_settings'), 'agency_shield_cmp_export_settings');
        echo '<a class="button" href="' . esc_url($export_url) . '">Descargar configuracion (JSON)</a>';
    }

    public function render_import_field() {
        echo '<input type="file" name="agency_shield_cmp_settings_file" form="ag-import-form" accept="application/json" />';
        echo ' ';
        echo '<button type="submit" class="button" form="ag-import-form">Importar configuracion</button>';
    }

    public function render_health_field() {
        $issues = $this->get_health_issues();
        if (!$issues) {
            echo '<p class="description">Todo correcto. No se detectaron riesgos.</p>';
            return;
        }
        echo '<ul class="ag-health">';
        foreach ($issues as $issue) {
            echo '<li>' . esc_html($issue) . '</li>';
        }
        echo '</ul>';
    }

    public function render_domains_field() {
        $settings = self::get_settings();
        $value = esc_textarea($settings['blocked_domains']);
        echo '<textarea class="large-text code" rows="7" name="' . esc_attr($this->option_name) . '[blocked_domains]">' . $value . '</textarea>';
        echo '<p class="description">Uno por linea. Ejemplo: youtube.com</p>';
    }

    public function render_placeholder_title_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['placeholder_title']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[placeholder_title]" value="' . $value . '" />';
    }

    public function render_placeholder_button_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['placeholder_button']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[placeholder_button]" value="' . $value . '" />';
    }

    public function render_floating_button_field() {
        $settings = self::get_settings();
        $checked = $settings['show_floating_button'] ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[show_floating_button]" value="1" ' . $checked . '> Mostrar boton flotante de revision</label>';
    }

    public function render_floating_button_text_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['floating_button_text']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[floating_button_text]" value="' . $value . '" />';
    }

    public function render_floating_button_style_field() {
        $settings = self::get_settings();
        $value = $settings['floating_button_style'];
        echo '<select name="' . esc_attr($this->option_name) . '[floating_button_style]">';
        echo '<option value="icon"' . selected($value, 'icon', false) . '>Icono</option>';
        echo '<option value="text"' . selected($value, 'text', false) . '>Texto</option>';
        echo '</select>';
        echo '<p class="description">Recomendado: icono para no molestar al usuario.</p>';
    }

    public function render_discovered_field() {
        $discovered = get_option('agency_shield_cmp_discovered', []);
        $settings = self::get_settings();
        $overrides = isset($settings['domain_overrides']) && is_array($settings['domain_overrides']) ? $settings['domain_overrides'] : [];
        $scan_url = wp_nonce_url(admin_url('admin-post.php?action=agency_shield_cmp_scan'), 'agency_shield_cmp_scan');

        echo '<p><a class="button" href="' . esc_url($scan_url) . '">Escanear ahora</a></p>';

        if (!is_array($discovered) || !$discovered) {
            echo '<p class="description">Aun no se han detectado dominios externos.</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Dominio</th><th>Servicio</th><th>Categoria sugerida</th><th>Categoria final</th><th>Ultima deteccion</th></tr></thead>';
        echo '<tbody>';
        foreach ($discovered as $domain => $data) {
            $category = isset($data['category']) ? esc_html($data['category']) : 'unknown';
            $service = isset($data['service']) ? esc_html($data['service']) : '-';
            $last_seen = isset($data['last_seen']) ? date_i18n('Y-m-d H:i', (int) $data['last_seen']) : '-';
            $selected = isset($overrides[$domain]) ? $overrides[$domain] : 'auto';
            echo '<tr>';
            echo '<td>' . esc_html($domain) . '</td>';
            echo '<td>' . $service . '</td>';
            echo '<td>' . $category . '</td>';
            echo '<td>' . $this->render_domain_select($domain, $selected) . '</td>';
            echo '<td>' . esc_html($last_seen) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function render_cookie_audit_field() {
        $nonce = wp_create_nonce('agency_shield_cmp_audit');
        $audit_url = add_query_arg(
            [
                'ag_cookie_audit' => '1',
                'ag_nonce' => $nonce,
            ],
            home_url('/')
        );

        echo '<p><a class="button" href="' . esc_url($audit_url) . '" target="_blank" rel="noopener">Escanear cookies en el navegador</a></p>';
        echo '<p class="description">Abre tu sitio en modo auditoria para detectar cookies reales (JS). Solo admins. Se guardan nombres, no valores.</p>';
    }

    public function render_detected_cookies_field() {
        $cookies = get_option('agency_shield_cmp_detected_cookies', []);
        if (!is_array($cookies) || !$cookies) {
            echo '<p class="description">Aun no se han detectado cookies durante el escaneo.</p>';
            return;
        }

        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Cookie</th><th>Dominio</th><th>Categoria</th><th>Ultima deteccion</th></tr></thead><tbody>';
        foreach ($cookies as $cookie) {
            $name = isset($cookie['name']) ? $cookie['name'] : '';
            $domain = isset($cookie['domain']) ? $cookie['domain'] : '';
            $category = isset($cookie['category']) ? $cookie['category'] : 'unknown';
            $last_seen = isset($cookie['last_seen']) ? date_i18n('Y-m-d H:i', (int) $cookie['last_seen']) : '-';
            echo '<tr>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td>' . esc_html($domain) . '</td>';
            echo '<td>' . esc_html($category) . '</td>';
            echo '<td>' . esc_html($last_seen) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function render_cookie_policy_url_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['cookie_policy_url']);
        echo '<input class="regular-text" type="url" name="' . esc_attr($this->option_name) . '[cookie_policy_url]" value="' . $value . '" placeholder="https://tusitio.com/politica-de-cookies" />';
        echo '<p class="description">Usa el shortcode [agency_shield_cookie_policy] en una pagina si no tienes URL propia.</p>';
    }

    public function render_privacy_policy_url_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['privacy_policy_url']);
        echo '<input class="regular-text" type="url" name="' . esc_attr($this->option_name) . '[privacy_policy_url]" value="' . $value . '" placeholder="https://tusitio.com/privacidad" />';
    }

    public function render_custom_cookies_field() {
        $settings = self::get_settings();
        $value = esc_textarea($settings['custom_cookies']);
        echo '<textarea class="large-text code" rows="6" name="' . esc_attr($this->option_name) . '[custom_cookies]">' . $value . '</textarea>';
        echo '<p class="description">Formato: nombre|categoria|finalidad|duracion|dominio. Ejemplo: my_cookie|analytics|Medicion basica|variable|tusitio.com</p>';
    }

    public function render_logs_field() {
        $logs = Agency_Shield_Consent_Log::get_logs(50, 0);
        if (!$logs) {
            echo '<p class="description">Aun no hay registros.</p>';
            return;
        }

        $export_url = wp_nonce_url(admin_url('admin-post.php?action=agency_shield_cmp_export_logs'), 'agency_shield_cmp_export_logs');

        echo '<p><a class="button" href="' . esc_url($export_url) . '">Exportar CSV</a></p>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Fecha</th><th>Consent ID</th><th>Accion</th><th>Categorias</th><th>Revision</th><th>Idioma</th><th>URL</th></tr></thead><tbody>';
        foreach ($logs as $log) {
            echo '<tr>';
            echo '<td>' . esc_html($log['created_at']) . '</td>';
            echo '<td>' . esc_html($log['consent_id']) . '</td>';
            echo '<td>' . esc_html($log['action']) . '</td>';
            echo '<td>' . esc_html($log['categories']) . '</td>';
            echo '<td>' . esc_html($log['revision']) . '</td>';
            echo '<td>' . esc_html($log['language']) . '</td>';
            echo '<td>' . esc_html($log['url']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }

    public function render_update_server_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['update_server_url']);
        echo '<input class="regular-text" type="url" name="' . esc_attr($this->option_name) . '[update_server_url]" value="' . $value . '" placeholder="https://updates.tu-dominio.com/cmp.json" />';
        echo '<p class="description">Soporta variables: {slug}, {channel}, {site}. Si no las usas se agregan via query.</p>';
    }

    public function render_update_channel_field() {
        $settings = self::get_settings();
        $value = $settings['update_channel'];
        echo '<select name="' . esc_attr($this->option_name) . '[update_channel]">';
        echo '<option value="stable"' . selected($value, 'stable', false) . '>Stable</option>';
        echo '<option value="beta"' . selected($value, 'beta', false) . '>Beta</option>';
        echo '</select>';
    }

    public function render_update_token_field() {
        $settings = self::get_settings();
        $value = esc_attr($settings['update_token']);
        echo '<input class="regular-text" type="text" name="' . esc_attr($this->option_name) . '[update_token]" value="' . $value . '" placeholder="Bearer token (opcional)" />';
        echo '<p class="description">Se envia en la cabecera Authorization.</p>';
    }

    public function render_update_public_key_field() {
        $settings = self::get_settings();
        $value = esc_textarea($settings['update_public_key']);
        echo '<textarea class="large-text code" rows="4" name="' . esc_attr($this->option_name) . '[update_public_key]" placeholder="-----BEGIN PUBLIC KEY-----">' . $value . '</textarea>';
        echo '<p class="description">Clave publica para verificar firmas del servidor central.</p>';
    }

    public function render_update_signature_field() {
        $settings = self::get_settings();
        $checked = !empty($settings['update_require_signature']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[update_require_signature]" value="1" ' . $checked . '> Requerir firma valida</label>';
        echo '<p class="description">Si esta activo, el update se bloquea sin firma valida.</p>';
    }

    public function render_settings_page() {
        $settings = self::get_settings();
        $discovered = get_option('agency_shield_cmp_discovered', []);
        $domains_count = is_array($discovered) ? count($discovered) : 0;
        $scan_url = wp_nonce_url(admin_url('admin-post.php?action=agency_shield_cmp_scan'), 'agency_shield_cmp_scan');
        $export_url = wp_nonce_url(admin_url('admin-post.php?action=agency_shield_cmp_export_logs'), 'agency_shield_cmp_export_logs');
        $cookie_policy_url = $settings['cookie_policy_url'];

        echo '<div class="wrap ag-admin">';
        echo '<div class="ag-admin-hero">';
        echo '<div class="ag-admin-hero__content">';
        echo '<span class="ag-admin-eyebrow">PW Cookie Monster</span>';
        echo '<h1>Panel central de consentimiento</h1>';
        echo '<p class="ag-admin-subtitle">Gestion profesional con un guino al monstruo de las cookies: cumplimiento, control y datos limpios.</p>';
        echo '</div>';
        echo '<div class="ag-admin-hero__actions">';
        echo '<a class="button button-primary" href="' . esc_url($scan_url) . '">Escanear ahora</a>';
        echo '<a class="button" href="' . esc_url($export_url) . '">Exportar CSV</a>';
        if (!empty($cookie_policy_url)) {
            echo '<a class="button" href="' . esc_url($cookie_policy_url) . '" target="_blank" rel="noopener">Ver politica</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-admin-kpis">';
        echo '<div class="ag-kpi">';
        echo '<span class="ag-kpi__label">Revision legal</span>';
        echo '<span class="ag-kpi__value">' . esc_html($settings['policy_revision']) . '</span>';
        echo '</div>';
        echo '<div class="ag-kpi">';
        echo '<span class="ag-kpi__label">Logs</span>';
        echo '<span class="ag-kpi__value">' . ($settings['enable_consent_log'] ? 'Activo' : 'Inactivo') . '</span>';
        echo '</div>';
        echo '<div class="ag-kpi">';
        echo '<span class="ag-kpi__label">Dominios detectados</span>';
        echo '<span class="ag-kpi__value">' . esc_html($domains_count) . '</span>';
        echo '</div>';
        echo '<div class="ag-kpi">';
        echo '<span class="ag-kpi__label">Modo categorias</span>';
        echo '<span class="ag-kpi__value">' . esc_html(ucfirst($settings['category_mode'])) . '</span>';
        echo '</div>';
        echo '</div>';

        $notice = get_transient('agency_shield_cmp_scan_notice');
        if ($notice) {
            echo '<div class="notice notice-success inline"><p>' . esc_html($notice) . '</p></div>';
            delete_transient('agency_shield_cmp_scan_notice');
        }

        echo '<nav class="ag-admin-tabs" data-ag-tabs>';
        echo '<button type="button" class="ag-tab-btn is-active" data-tab="general">General</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="compliance">Compliance</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="idiomas">Idiomas</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="categorias">Categorias</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="apariencia">Apariencia</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="banner">Banner</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="bloqueo">Bloqueo</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="scanner">Scanner</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="politica">Politica</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="branding">Branding</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="tools">Tools</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="health">Health</button>';
        echo '<button type="button" class="ag-tab-btn" data-tab="logs">Logs</button>';
        echo '</nav>';

        echo '<div class="agency-shield-admin-card">';
        echo '<form method="post" action="options.php">';
        settings_fields('agency_shield_cmp_settings');

        echo '<div class="ag-tab is-active" data-tab="general">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Configuracion general</h2>';
        echo '<p class="ag-section-intro">Datos principales, revision legal y registro.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_main');
        echo '</table>';
        echo '</div>';

        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h3>Accesos rapidos</h3>';
        echo '<p class="ag-section-intro">Boton flotante y revision de consentimiento.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_ui');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="compliance">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Cumplimiento y privacidad</h2>';
        echo '<p class="ag-section-intro">Define geolocalizacion e idioma.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_compliance');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="idiomas">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Textos multilenguaje</h2>';
        echo '<p class="ag-section-intro">Configura EN (ES se gestiona en Banner/Categorias).</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_languages');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="categorias">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Categorias y comportamiento</h2>';
        echo '<p class="ag-section-intro">Define etiquetas y activa o desactiva categorias.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_categories');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="apariencia">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Apariencia y posicion</h2>';
        echo '<p class="ag-section-intro">Ajusta el layout, posicion y colores.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_appearance');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="banner">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Textos del banner</h2>';
        echo '<p class="ag-section-intro">Personaliza el banner y el modal de preferencias.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_banner');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="bloqueo">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Bloqueo de contenido</h2>';
        echo '<p class="ag-section-intro">Controla los dominios bloqueados y el texto de placeholders.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_content');
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_texts');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="scanner">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Descubrimiento automatico</h2>';
        echo '<p class="ag-section-intro">Escanea tu sitio y clasifica dominios externos.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_discovery');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="politica">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Politica de cookies</h2>';
        echo '<p class="ag-section-intro">Enlaza la politica y define cookies personalizadas.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_policy');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="branding">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Branding y white-label</h2>';
        echo '<p class="ag-section-intro">Personaliza la marca visible.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_branding');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="tools">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Herramientas</h2>';
        echo '<p class="ag-section-intro">Importa o exporta configuraciones.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_tools');
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_updates');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="health">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Health check</h2>';
        echo '<p class="ag-section-intro">Revision rapida de riesgos.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_health');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="ag-tab" data-tab="logs">';
        echo '<div class="ag-panel">';
        echo '<div class="ag-panel-header">';
        echo '<h2>Registro de consentimientos</h2>';
        echo '<p class="ag-section-intro">Consulta y exporta el historial de consentimientos.</p>';
        echo '</div>';
        echo '<table class="form-table">';
        do_settings_fields('agency-shield-cmp', 'agency_shield_cmp_logs');
        echo '</table>';
        echo '</div>';
        echo '</div>';

        submit_button('Guardar cambios');
        echo '</form>';
        echo '<form id="ag-import-form" method="post" action="' . esc_url(admin_url('admin-post.php?action=agency_shield_cmp_import_settings')) . '" enctype="multipart/form-data">';
        wp_nonce_field('agency_shield_cmp_import_settings');
        echo '</form>';
        echo '</div>';
        echo '</div>';
    }

    public function render_preview() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_ajax_referer('agency_shield_cmp_preview', 'nonce');

        $settings = [];
        if (!empty($_POST['agency_shield_cmp_settings']) && is_array($_POST['agency_shield_cmp_settings'])) {
            $raw = wp_unslash($_POST['agency_shield_cmp_settings']);
            $settings = $this->sanitize_settings($raw);
        } else {
            $settings = self::get_settings();
        }

        $config = $this->build_preview_config($settings);

        $css_cc = AGENCY_SHIELD_CMP_URL . 'assets/css/cookieconsent.css';
        $css_main = AGENCY_SHIELD_CMP_URL . 'assets/css/agency-shield.css';
        $js_cc = AGENCY_SHIELD_CMP_URL . 'assets/js/cookieconsent.js';
        $js_main = AGENCY_SHIELD_CMP_URL . 'assets/js/agency-shield.js';

        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html><head><meta charset="utf-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<link rel="stylesheet" href="' . esc_url($css_cc) . '">';
        echo '<link rel="stylesheet" href="' . esc_url($css_main) . '">';
        echo '<style>html,body{margin:0;padding:0;background:#f1f5f9;}#preview-root{min-height:360px;position:relative;padding:20px;}#cc-main{position:absolute !important;}</style>';
        echo '</head><body>';
        echo '<div id="preview-root"></div>';
        echo '<script>window.AgencyShieldConfig=' . wp_json_encode($config) . ';</script>';
        echo '<script src="' . esc_url($js_cc) . '"></script>';
        echo '<script src="' . esc_url($js_main) . '"></script>';
        echo '<script>setTimeout(function(){if(window.CookieConsent&&CookieConsent.show){CookieConsent.show();}},60);</script>';
        echo '</body></html>';
        exit;
    }

    private function build_preview_config($settings) {
        $definitions = [
            'necessary' => [
                'label' => $settings['necessary_label'],
                'description' => $settings['necessary_description'],
                'cookies' => [],
            ],
            'analytics' => [
                'label' => $settings['analytics_label'],
                'description' => $settings['analytics_description'],
                'cookies' => [],
            ],
            'marketing' => [
                'label' => $settings['marketing_label'],
                'description' => $settings['marketing_description'],
                'cookies' => [],
            ],
        ];

        $categories = [
            'necessary' => true,
            'analytics' => !empty($settings['analytics_enabled']),
            'marketing' => !empty($settings['marketing_enabled']),
        ];

        $site_lang = $settings['default_language'] ?: 'es';

        return [
            'categories' => $categories,
            'cookieDefinitions' => $definitions,
            'ui' => [
                'floatingButton' => false,
                'floatingButtonText' => $settings['floating_button_text'],
                'floatingButtonStyle' => $settings['floating_button_style'],
                'consentLayout' => $settings['consent_layout'],
                'consentPosition' => $settings['consent_position'],
                'preferencesLayout' => $settings['preferences_layout'],
                'preferencesPosition' => $settings['preferences_position'],
                'bannerShowIcon' => !empty($settings['banner_show_icon']),
                'bannerIconStyle' => $settings['banner_icon_style'],
                'allowNecessaryToggle' => !empty($settings['allow_necessary_toggle']),
            ],
            'theme' => [
                'bg' => $settings['theme_bg'],
                'primaryColor' => $settings['theme_primary_color'],
                'secondaryColor' => $settings['theme_secondary_color'],
                'primaryBtnBg' => $settings['theme_btn_primary_bg'],
                'primaryBtnColor' => $settings['theme_btn_primary_color'],
                'secondaryBtnBg' => $settings['theme_btn_secondary_bg'],
                'secondaryBtnColor' => $settings['theme_btn_secondary_color'],
                'modalRadius' => $settings['theme_modal_radius'],
                'buttonRadius' => $settings['theme_button_radius'],
            ],
            'language' => [
                'mode' => 'custom',
                'default' => $site_lang,
                'site' => $site_lang,
                'texts' => [
                    'es' => [
                        'banner_title' => $settings['banner_title'],
                        'banner_description' => $settings['banner_description'],
                        'banner_accept_all' => $settings['banner_accept_all'],
                        'banner_reject_all' => $settings['banner_reject_all'],
                        'banner_manage_prefs' => $settings['banner_manage_prefs'],
                        'banner_save_prefs' => $settings['banner_save_prefs'],
                        'banner_preferences_title' => $settings['banner_preferences_title'],
                        'necessary_label' => $settings['necessary_label'],
                        'necessary_description' => $settings['necessary_description'],
                        'necessary_legal_note' => $settings['necessary_legal_note'],
                        'analytics_label' => $settings['analytics_label'],
                        'analytics_description' => $settings['analytics_description'],
                        'marketing_label' => $settings['marketing_label'],
                        'marketing_description' => $settings['marketing_description'],
                    ],
                    'en' => [
                        'banner_title' => $settings['banner_title_en'],
                        'banner_description' => $settings['banner_description_en'],
                        'banner_accept_all' => $settings['banner_accept_all_en'],
                        'banner_reject_all' => $settings['banner_reject_all_en'],
                        'banner_manage_prefs' => $settings['banner_manage_prefs_en'],
                        'banner_save_prefs' => $settings['banner_save_prefs_en'],
                        'banner_preferences_title' => $settings['banner_preferences_title_en'],
                        'necessary_label' => $settings['necessary_label_en'],
                        'necessary_description' => $settings['necessary_description_en'],
                        'necessary_legal_note' => $settings['necessary_legal_note_en'],
                        'analytics_label' => $settings['analytics_label_en'],
                        'analytics_description' => $settings['analytics_description_en'],
                        'marketing_label' => $settings['marketing_label_en'],
                        'marketing_description' => $settings['marketing_description_en'],
                    ],
                ],
            ],
            'brand' => [
                'name' => $settings['brand_name'],
                'logo' => $settings['brand_logo_url'],
                'hide' => !empty($settings['hide_branding']),
            ],
            'policy' => [
                'revision' => (int) $settings['policy_revision'],
                'cookiePolicyUrl' => $settings['cookie_policy_url'],
                'privacyPolicyUrl' => $settings['privacy_policy_url'],
                'logConsent' => false,
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('agency_shield_consent_log'),
                'banner' => [
                    'title' => $settings['banner_title'],
                    'description' => $settings['banner_description'],
                    'acceptAll' => $settings['banner_accept_all'],
                    'rejectAll' => $settings['banner_reject_all'],
                    'managePrefs' => $settings['banner_manage_prefs'],
                    'savePrefs' => $settings['banner_save_prefs'],
                    'preferencesTitle' => $settings['banner_preferences_title'],
                ],
            ],
        ];
    }

    public function enqueue_assets($hook) {
        // Cargar en todas las paginas de PW Cookie Monster
        if (strpos($hook, 'agency-shield') === false && $hook !== $this->menu_hook) {
            return;
        }

        // Font Awesome para iconos
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');

        wp_enqueue_style('agency-shield-admin', AGENCY_SHIELD_CMP_URL . 'assets/css/agency-shield-admin.css', ['font-awesome'], AGENCY_SHIELD_CMP_VERSION);
        wp_enqueue_script('agency-shield-admin', AGENCY_SHIELD_CMP_URL . 'assets/js/agency-shield-admin.js', [], AGENCY_SHIELD_CMP_VERSION, true);
        wp_localize_script('agency-shield-admin', 'AgencyShieldAdminConfig', [
            'presets' => self::get_theme_presets(),
            'previewUrl' => admin_url('admin-ajax.php?action=agency_shield_cmp_preview'),
            'previewNonce' => wp_create_nonce('agency_shield_cmp_preview'),
            'previewAssets' => [
                'cssCc' => AGENCY_SHIELD_CMP_URL . 'assets/css/cookieconsent.css',
                'cssMain' => AGENCY_SHIELD_CMP_URL . 'assets/css/agency-shield.css',
                'jsCc' => AGENCY_SHIELD_CMP_URL . 'assets/js/cookieconsent.js',
                'jsMain' => AGENCY_SHIELD_CMP_URL . 'assets/js/agency-shield.js',
            ],
        ]);
    }

    public function sanitize_settings($value) {
        $defaults = self::get_default_settings();
        $value = is_array($value) ? $value : [];

        $enable_blocker = !empty($value['enable_blocker']) ? true : false;

        $blocked_domains = isset($value['blocked_domains']) ? (string) $value['blocked_domains'] : '';
        $lines = preg_split('/\\r\\n|\\r|\\n/', $blocked_domains);
        $lines = array_filter(array_map('trim', $lines));
        $blocked_domains = implode("\n", $lines);

        $placeholder_title = isset($value['placeholder_title']) ? wp_strip_all_tags((string) $value['placeholder_title']) : '';
        $placeholder_button = isset($value['placeholder_button']) ? wp_strip_all_tags((string) $value['placeholder_button']) : '';
        $show_floating_button = !empty($value['show_floating_button']) ? true : false;
        $floating_button_text = isset($value['floating_button_text']) ? wp_strip_all_tags((string) $value['floating_button_text']) : '';
        $floating_button_style = isset($value['floating_button_style']) && in_array($value['floating_button_style'], ['icon', 'text'], true)
            ? $value['floating_button_style']
            : $defaults['floating_button_style'];

        $enable_consent_log = !empty($value['enable_consent_log']) ? true : false;
        $policy_revision = isset($value['policy_revision']) ? (int) $value['policy_revision'] : $defaults['policy_revision'];

        $cookie_policy_url = isset($value['cookie_policy_url']) ? esc_url_raw((string) $value['cookie_policy_url']) : '';
        $privacy_policy_url = isset($value['privacy_policy_url']) ? esc_url_raw((string) $value['privacy_policy_url']) : '';

        $update_server_url = isset($value['update_server_url']) ? esc_url_raw((string) $value['update_server_url']) : '';
        $update_channel = isset($value['update_channel']) && in_array($value['update_channel'], ['stable', 'beta'], true)
            ? $value['update_channel']
            : $defaults['update_channel'];
        $update_token = isset($value['update_token']) ? sanitize_text_field((string) $value['update_token']) : '';
        $update_public_key = isset($value['update_public_key']) ? trim((string) $value['update_public_key']) : '';
        $update_require_signature = !empty($value['update_require_signature']) ? true : false;

        $banner_title = isset($value['banner_title']) ? wp_strip_all_tags((string) $value['banner_title']) : '';
        $banner_description = isset($value['banner_description']) ? sanitize_textarea_field((string) $value['banner_description']) : '';
        $banner_accept_all = isset($value['banner_accept_all']) ? wp_strip_all_tags((string) $value['banner_accept_all']) : '';
        $banner_reject_all = isset($value['banner_reject_all']) ? wp_strip_all_tags((string) $value['banner_reject_all']) : '';
        $banner_manage_prefs = isset($value['banner_manage_prefs']) ? wp_strip_all_tags((string) $value['banner_manage_prefs']) : '';
        $banner_save_prefs = isset($value['banner_save_prefs']) ? wp_strip_all_tags((string) $value['banner_save_prefs']) : '';
        $banner_preferences_title = isset($value['banner_preferences_title']) ? wp_strip_all_tags((string) $value['banner_preferences_title']) : '';

        $banner_show_icon = !empty($value['banner_show_icon']) ? true : false;
        $banner_icon_style = isset($value['banner_icon_style']) && in_array($value['banner_icon_style'], ['cookie', 'cookie-bite', 'shield', 'lock', 'fingerprint'], true)
            ? $value['banner_icon_style']
            : $defaults['banner_icon_style'];

        $custom_cookies = isset($value['custom_cookies']) ? (string) $value['custom_cookies'] : '';
        $custom_cookies = $this->sanitize_custom_cookies($custom_cookies);

        $category_mode = isset($value['category_mode']) && in_array($value['category_mode'], ['auto', 'manual'], true)
            ? $value['category_mode']
            : $defaults['category_mode'];
        $allow_necessary_toggle = !empty($value['allow_necessary_toggle']) ? true : false;
        $analytics_enabled = !empty($value['analytics_enabled']) ? true : false;
        $marketing_enabled = !empty($value['marketing_enabled']) ? true : false;
        $necessary_label = isset($value['necessary_label']) ? wp_strip_all_tags((string) $value['necessary_label']) : '';
        $necessary_description = isset($value['necessary_description']) ? wp_strip_all_tags((string) $value['necessary_description']) : '';
        $analytics_label = isset($value['analytics_label']) ? wp_strip_all_tags((string) $value['analytics_label']) : '';
        $analytics_description = isset($value['analytics_description']) ? wp_strip_all_tags((string) $value['analytics_description']) : '';
        $marketing_label = isset($value['marketing_label']) ? wp_strip_all_tags((string) $value['marketing_label']) : '';
        $marketing_description = isset($value['marketing_description']) ? wp_strip_all_tags((string) $value['marketing_description']) : '';

        $consent_layout = isset($value['consent_layout']) ? sanitize_text_field((string) $value['consent_layout']) : $defaults['consent_layout'];
        $consent_position = isset($value['consent_position']) ? sanitize_text_field((string) $value['consent_position']) : $defaults['consent_position'];
        $preferences_layout = isset($value['preferences_layout']) ? sanitize_text_field((string) $value['preferences_layout']) : $defaults['preferences_layout'];
        $preferences_position = isset($value['preferences_position']) ? sanitize_text_field((string) $value['preferences_position']) : $defaults['preferences_position'];

        $theme_bg = isset($value['theme_bg']) ? sanitize_hex_color((string) $value['theme_bg']) : $defaults['theme_bg'];
        $theme_primary_color = isset($value['theme_primary_color']) ? sanitize_hex_color((string) $value['theme_primary_color']) : $defaults['theme_primary_color'];
        $theme_secondary_color = isset($value['theme_secondary_color']) ? sanitize_hex_color((string) $value['theme_secondary_color']) : $defaults['theme_secondary_color'];
        $theme_btn_primary_bg = isset($value['theme_btn_primary_bg']) ? sanitize_hex_color((string) $value['theme_btn_primary_bg']) : $defaults['theme_btn_primary_bg'];
        $theme_btn_primary_color = isset($value['theme_btn_primary_color']) ? sanitize_hex_color((string) $value['theme_btn_primary_color']) : $defaults['theme_btn_primary_color'];
        $theme_btn_secondary_bg = isset($value['theme_btn_secondary_bg']) ? sanitize_hex_color((string) $value['theme_btn_secondary_bg']) : $defaults['theme_btn_secondary_bg'];
        $theme_btn_secondary_color = isset($value['theme_btn_secondary_color']) ? sanitize_hex_color((string) $value['theme_btn_secondary_color']) : $defaults['theme_btn_secondary_color'];
        $theme_modal_radius = isset($value['theme_modal_radius']) ? (int) $value['theme_modal_radius'] : $defaults['theme_modal_radius'];
        $theme_button_radius = isset($value['theme_button_radius']) ? (int) $value['theme_button_radius'] : $defaults['theme_button_radius'];

        $geo_mode = isset($value['geo_mode']) && in_array($value['geo_mode'], ['all', 'eea', 'custom', 'none'], true)
            ? $value['geo_mode']
            : $defaults['geo_mode'];
        $geo_countries = isset($value['geo_countries']) ? sanitize_text_field((string) $value['geo_countries']) : $defaults['geo_countries'];
        $geo_header = isset($value['geo_header']) ? sanitize_text_field((string) $value['geo_header']) : $defaults['geo_header'];

        $language_mode = isset($value['language_mode']) && in_array($value['language_mode'], ['auto', 'site', 'browser', 'custom'], true)
            ? $value['language_mode']
            : $defaults['language_mode'];
        $default_language = isset($value['default_language']) && in_array($value['default_language'], ['es', 'en'], true)
            ? $value['default_language']
            : $defaults['default_language'];

        $banner_title_en = isset($value['banner_title_en']) ? wp_strip_all_tags((string) $value['banner_title_en']) : '';
        $banner_description_en = isset($value['banner_description_en']) ? sanitize_textarea_field((string) $value['banner_description_en']) : '';
        $banner_accept_all_en = isset($value['banner_accept_all_en']) ? wp_strip_all_tags((string) $value['banner_accept_all_en']) : '';
        $banner_reject_all_en = isset($value['banner_reject_all_en']) ? wp_strip_all_tags((string) $value['banner_reject_all_en']) : '';
        $banner_manage_prefs_en = isset($value['banner_manage_prefs_en']) ? wp_strip_all_tags((string) $value['banner_manage_prefs_en']) : '';
        $banner_save_prefs_en = isset($value['banner_save_prefs_en']) ? wp_strip_all_tags((string) $value['banner_save_prefs_en']) : '';
        $banner_preferences_title_en = isset($value['banner_preferences_title_en']) ? wp_strip_all_tags((string) $value['banner_preferences_title_en']) : '';

        $necessary_label_en = isset($value['necessary_label_en']) ? wp_strip_all_tags((string) $value['necessary_label_en']) : '';
        $necessary_description_en = isset($value['necessary_description_en']) ? wp_strip_all_tags((string) $value['necessary_description_en']) : '';
        $necessary_legal_note = isset($value['necessary_legal_note']) ? sanitize_textarea_field((string) $value['necessary_legal_note']) : '';
        $necessary_legal_note_en = isset($value['necessary_legal_note_en']) ? sanitize_textarea_field((string) $value['necessary_legal_note_en']) : '';
        $analytics_label_en = isset($value['analytics_label_en']) ? wp_strip_all_tags((string) $value['analytics_label_en']) : '';
        $analytics_description_en = isset($value['analytics_description_en']) ? wp_strip_all_tags((string) $value['analytics_description_en']) : '';
        $marketing_label_en = isset($value['marketing_label_en']) ? wp_strip_all_tags((string) $value['marketing_label_en']) : '';
        $marketing_description_en = isset($value['marketing_description_en']) ? wp_strip_all_tags((string) $value['marketing_description_en']) : '';

        $brand_name = isset($value['brand_name']) ? wp_strip_all_tags((string) $value['brand_name']) : '';
        $brand_logo_url = isset($value['brand_logo_url']) ? esc_url_raw((string) $value['brand_logo_url']) : '';
        $hide_branding = !empty($value['hide_branding']) ? true : false;

        $domain_overrides = [];
        if (!empty($value['domain_overrides']) && is_array($value['domain_overrides'])) {
            $allowed = ['auto', 'necessary', 'analytics', 'marketing', 'unknown'];
            foreach ($value['domain_overrides'] as $domain => $cat) {
                $domain = sanitize_text_field($domain);
                $cat = sanitize_text_field($cat);
                if ($domain && in_array($cat, $allowed, true)) {
                    $domain_overrides[$domain] = $cat;
                }
            }
        }

        return [
            'enable_blocker' => $enable_blocker,
            'blocked_domains' => $blocked_domains !== '' ? $blocked_domains : $defaults['blocked_domains'],
            'placeholder_title' => $placeholder_title !== '' ? $placeholder_title : $defaults['placeholder_title'],
            'placeholder_button' => $placeholder_button !== '' ? $placeholder_button : $defaults['placeholder_button'],
            'show_floating_button' => $show_floating_button,
            'floating_button_text' => $floating_button_text !== '' ? $floating_button_text : $defaults['floating_button_text'],
            'floating_button_style' => $floating_button_style,
            'enable_consent_log' => $enable_consent_log,
            'policy_revision' => $policy_revision,
            'cookie_policy_url' => $cookie_policy_url !== '' ? $cookie_policy_url : $defaults['cookie_policy_url'],
            'privacy_policy_url' => $privacy_policy_url !== '' ? $privacy_policy_url : $defaults['privacy_policy_url'],
            'update_server_url' => $update_server_url !== '' ? $update_server_url : $defaults['update_server_url'],
            'update_channel' => $update_channel,
            'update_token' => $update_token !== '' ? $update_token : $defaults['update_token'],
            'update_public_key' => $update_public_key !== '' ? $update_public_key : $defaults['update_public_key'],
            'update_require_signature' => $update_require_signature,
            'banner_title' => $banner_title !== '' ? $banner_title : $defaults['banner_title'],
            'banner_description' => $banner_description !== '' ? $banner_description : $defaults['banner_description'],
            'banner_accept_all' => $banner_accept_all !== '' ? $banner_accept_all : $defaults['banner_accept_all'],
            'banner_reject_all' => $banner_reject_all !== '' ? $banner_reject_all : $defaults['banner_reject_all'],
            'banner_manage_prefs' => $banner_manage_prefs !== '' ? $banner_manage_prefs : $defaults['banner_manage_prefs'],
            'banner_save_prefs' => $banner_save_prefs !== '' ? $banner_save_prefs : $defaults['banner_save_prefs'],
            'banner_preferences_title' => $banner_preferences_title !== '' ? $banner_preferences_title : $defaults['banner_preferences_title'],
            'banner_show_icon' => $banner_show_icon,
            'banner_icon_style' => $banner_icon_style,
            'custom_cookies' => $custom_cookies !== '' ? $custom_cookies : $defaults['custom_cookies'],
            'domain_overrides' => $domain_overrides,
            'category_mode' => $category_mode,
            'allow_necessary_toggle' => $allow_necessary_toggle,
            'analytics_enabled' => $analytics_enabled,
            'marketing_enabled' => $marketing_enabled,
            'necessary_label' => $necessary_label !== '' ? $necessary_label : $defaults['necessary_label'],
            'necessary_description' => $necessary_description !== '' ? $necessary_description : $defaults['necessary_description'],
            'necessary_legal_note' => $necessary_legal_note !== '' ? $necessary_legal_note : $defaults['necessary_legal_note'],
            'analytics_label' => $analytics_label !== '' ? $analytics_label : $defaults['analytics_label'],
            'analytics_description' => $analytics_description !== '' ? $analytics_description : $defaults['analytics_description'],
            'marketing_label' => $marketing_label !== '' ? $marketing_label : $defaults['marketing_label'],
            'marketing_description' => $marketing_description !== '' ? $marketing_description : $defaults['marketing_description'],
            'consent_layout' => $consent_layout ?: $defaults['consent_layout'],
            'consent_position' => $consent_position ?: $defaults['consent_position'],
            'preferences_layout' => $preferences_layout ?: $defaults['preferences_layout'],
            'preferences_position' => $preferences_position ?: $defaults['preferences_position'],
            'theme_bg' => $theme_bg ?: $defaults['theme_bg'],
            'theme_primary_color' => $theme_primary_color ?: $defaults['theme_primary_color'],
            'theme_secondary_color' => $theme_secondary_color ?: $defaults['theme_secondary_color'],
            'theme_btn_primary_bg' => $theme_btn_primary_bg ?: $defaults['theme_btn_primary_bg'],
            'theme_btn_primary_color' => $theme_btn_primary_color ?: $defaults['theme_btn_primary_color'],
            'theme_btn_secondary_bg' => $theme_btn_secondary_bg ?: $defaults['theme_btn_secondary_bg'],
            'theme_btn_secondary_color' => $theme_btn_secondary_color ?: $defaults['theme_btn_secondary_color'],
            'theme_modal_radius' => $theme_modal_radius,
            'theme_button_radius' => $theme_button_radius,
            'geo_mode' => $geo_mode,
            'geo_countries' => $geo_countries,
            'geo_header' => $geo_header,
            'language_mode' => $language_mode,
            'default_language' => $default_language,
            'banner_title_en' => $banner_title_en !== '' ? $banner_title_en : $defaults['banner_title_en'],
            'banner_description_en' => $banner_description_en !== '' ? $banner_description_en : $defaults['banner_description_en'],
            'banner_accept_all_en' => $banner_accept_all_en !== '' ? $banner_accept_all_en : $defaults['banner_accept_all_en'],
            'banner_reject_all_en' => $banner_reject_all_en !== '' ? $banner_reject_all_en : $defaults['banner_reject_all_en'],
            'banner_manage_prefs_en' => $banner_manage_prefs_en !== '' ? $banner_manage_prefs_en : $defaults['banner_manage_prefs_en'],
            'banner_save_prefs_en' => $banner_save_prefs_en !== '' ? $banner_save_prefs_en : $defaults['banner_save_prefs_en'],
            'banner_preferences_title_en' => $banner_preferences_title_en !== '' ? $banner_preferences_title_en : $defaults['banner_preferences_title_en'],
            'necessary_label_en' => $necessary_label_en !== '' ? $necessary_label_en : $defaults['necessary_label_en'],
            'necessary_description_en' => $necessary_description_en !== '' ? $necessary_description_en : $defaults['necessary_description_en'],
            'necessary_legal_note_en' => $necessary_legal_note_en !== '' ? $necessary_legal_note_en : $defaults['necessary_legal_note_en'],
            'analytics_label_en' => $analytics_label_en !== '' ? $analytics_label_en : $defaults['analytics_label_en'],
            'analytics_description_en' => $analytics_description_en !== '' ? $analytics_description_en : $defaults['analytics_description_en'],
            'marketing_label_en' => $marketing_label_en !== '' ? $marketing_label_en : $defaults['marketing_label_en'],
            'marketing_description_en' => $marketing_description_en !== '' ? $marketing_description_en : $defaults['marketing_description_en'],
            'brand_name' => $brand_name !== '' ? $brand_name : $defaults['brand_name'],
            'brand_logo_url' => $brand_logo_url !== '' ? $brand_logo_url : $defaults['brand_logo_url'],
            'hide_branding' => $hide_branding,
        ];
    }

    private function sanitize_custom_cookies($raw) {
        $raw = is_string($raw) ? $raw : '';
        $lines = preg_split('/\\r\\n|\\r|\\n/', $raw);
        $clean = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 5) {
                continue;
            }

            $parts = array_slice($parts, 0, 5);
            $parts = array_map('sanitize_text_field', $parts);
            $clean[] = implode('|', $parts);
        }

        return implode("\n", $clean);
    }

    public static function get_settings() {
        $defaults = self::get_default_settings();
        $settings = get_option('agency_shield_cmp_settings', []);

        if (!is_array($settings)) {
            $settings = [];
        }

        return array_merge($defaults, $settings);
    }

    private static function get_default_settings() {
        return [
            'enable_blocker' => true,
            'blocked_domains' => implode("\n", [
                'youtube.com',
                'youtu.be',
                'vimeo.com',
                'google.com/maps',
                'maps.google.com',
                'soundcloud.com',
                'spotify.com',
                'facebook.com/plugins',
                'platform.twitter.com',
            ]),
            'placeholder_title' => 'Contenido externo bloqueado (Privacidad)',
            'placeholder_button' => 'Aceptar Cookies para ver',
            'show_floating_button' => true,
            'floating_button_text' => 'Revisar consentimiento',
            'floating_button_style' => 'icon',
            'enable_consent_log' => true,
            'policy_revision' => 1,
            'cookie_policy_url' => '',
            'privacy_policy_url' => '',
            'update_server_url' => '',
            'update_channel' => 'stable',
            'update_token' => '',
            'update_public_key' => '',
            'update_require_signature' => true,
            'banner_title' => 'Preferencias de cookies',
            'banner_description' => 'Usamos cookies para mejorar la experiencia y medir el rendimiento.',
            'banner_accept_all' => 'Aceptar todas',
            'banner_reject_all' => 'Rechazar no necesarias',
            'banner_manage_prefs' => 'Gestionar preferencias',
            'banner_save_prefs' => 'Guardar preferencias',
            'banner_preferences_title' => 'Preferencias de cookies',
            'banner_show_icon' => true,
            'banner_icon_style' => 'cookie',
            'custom_cookies' => '',
            'domain_overrides' => [],
            'category_mode' => 'auto',
            'allow_necessary_toggle' => false,
            'analytics_enabled' => true,
            'marketing_enabled' => true,
            'necessary_label' => 'Cookies necesarias',
            'necessary_description' => 'Requeridas para el funcionamiento basico del sitio.',
            'necessary_legal_note' => 'Puedes desactivarlas, pero algunas funciones esenciales pueden dejar de estar disponibles.',
            'analytics_label' => 'Cookies de analitica',
            'analytics_description' => 'Nos ayudan a mejorar midiendo el uso del sitio.',
            'marketing_label' => 'Cookies de marketing',
            'marketing_description' => 'Permiten contenido externo y publicidad personalizada.',
            'consent_layout' => 'box',
            'consent_position' => 'bottom right',
            'preferences_layout' => 'box',
            'preferences_position' => 'right',
            'theme_bg' => '#ffffff',
            'theme_primary_color' => '#2c2f31',
            'theme_secondary_color' => '#5e6266',
            'theme_btn_primary_bg' => '#30363c',
            'theme_btn_primary_color' => '#ffffff',
            'theme_btn_secondary_bg' => '#eaeff2',
            'theme_btn_secondary_color' => '#2c2f31',
            'theme_modal_radius' => 8,
            'theme_button_radius' => 6,
            'geo_mode' => 'all',
            'geo_countries' => 'ES,FR,DE,IT,PT,NL,BE,LU,IE,AT,PL,SE,NO,FI,DK,GR,CZ,SK,HU,RO,BG,HR,SI,LV,LT,EE,IS,LI,CH,GB',
            'geo_header' => 'auto',
            'language_mode' => 'auto',
            'default_language' => 'es',
            'banner_title_en' => 'Cookie preferences',
            'banner_description_en' => 'We use cookies to improve the experience and measure performance.',
            'banner_accept_all_en' => 'Accept all',
            'banner_reject_all_en' => 'Reject non-essential',
            'banner_manage_prefs_en' => 'Manage preferences',
            'banner_save_prefs_en' => 'Save preferences',
            'banner_preferences_title_en' => 'Cookie preferences',
            'necessary_label_en' => 'Necessary cookies',
            'necessary_description_en' => 'Required for the basic functioning of the site.',
            'necessary_legal_note_en' => 'You can disable them, but some essential features may stop working.',
            'analytics_label_en' => 'Analytics cookies',
            'analytics_description_en' => 'Help us improve by measuring site usage.',
            'marketing_label_en' => 'Marketing cookies',
            'marketing_description_en' => 'Enable external content and personalized ads.',
            'brand_name' => '',
            'brand_logo_url' => '',
            'hide_branding' => false,
        ];
    }

    private static function get_theme_presets() {
        return [
            'classic' => [
                'label' => 'Classic Light',
                'theme_bg' => '#ffffff',
                'theme_primary_color' => '#1d2327',
                'theme_secondary_color' => '#5e6266',
                'theme_btn_primary_bg' => '#1d2327',
                'theme_btn_primary_color' => '#ffffff',
                'theme_btn_secondary_bg' => '#eaeff2',
                'theme_btn_secondary_color' => '#1d2327',
                'theme_modal_radius' => 8,
                'theme_button_radius' => 6,
                'consent_layout' => 'box',
                'consent_position' => 'bottom right',
                'preferences_layout' => 'box',
                'preferences_position' => 'right',
            ],
            'sunrise' => [
                'label' => 'Sunrise',
                'theme_bg' => '#fff7ed',
                'theme_primary_color' => '#7c2d12',
                'theme_secondary_color' => '#9a3412',
                'theme_btn_primary_bg' => '#ea580c',
                'theme_btn_primary_color' => '#ffffff',
                'theme_btn_secondary_bg' => '#fed7aa',
                'theme_btn_secondary_color' => '#7c2d12',
                'theme_modal_radius' => 12,
                'theme_button_radius' => 10,
                'consent_layout' => 'box',
                'consent_position' => 'bottom left',
                'preferences_layout' => 'box',
                'preferences_position' => 'right',
            ],
            'graphite' => [
                'label' => 'Graphite',
                'theme_bg' => '#111827',
                'theme_primary_color' => '#f9fafb',
                'theme_secondary_color' => '#cbd5e1',
                'theme_btn_primary_bg' => '#0f766e',
                'theme_btn_primary_color' => '#ffffff',
                'theme_btn_secondary_bg' => '#1f2937',
                'theme_btn_secondary_color' => '#f9fafb',
                'theme_modal_radius' => 10,
                'theme_button_radius' => 8,
                'consent_layout' => 'cloud',
                'consent_position' => 'bottom center',
                'preferences_layout' => 'bar',
                'preferences_position' => 'right',
            ],
        ];
    }

    private function render_domain_select($domain, $selected) {
        $options = [
            'auto' => 'Auto',
            'necessary' => 'Necesarias',
            'analytics' => 'Analitica',
            'marketing' => 'Marketing',
            'unknown' => 'Desconocido',
        ];

        $html = '<select name="' . esc_attr($this->option_name) . '[domain_overrides][' . esc_attr($domain) . ']">';
        foreach ($options as $value => $label) {
            $is_selected = selected($selected, $value, false);
            $html .= '<option value="' . esc_attr($value) . '" ' . $is_selected . '>' . esc_html($label) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }

    public function handle_scan_request() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('agency_shield_cmp_scan');

        $scanner = new Agency_Shield_Scanner();
        $result = $scanner->scan_site(25);

        $message = sprintf('Escaneo completado. URLs: %d, dominios: %d', $result['urls'], $result['domains']);
        set_transient('agency_shield_cmp_scan_notice', $message, 60);

        wp_safe_redirect(admin_url('options-general.php?page=agency-shield-cmp'));
        exit;
    }

    public function handle_export_logs() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('agency_shield_cmp_export_logs');
        Agency_Shield_Consent_Log::export_logs();
    }

    public function handle_export_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('agency_shield_cmp_export_settings');
        $settings = self::get_settings();

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=agency-shield-cmp-settings.json');
        echo wp_json_encode($settings);
        exit;
    }

    public function handle_import_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('agency_shield_cmp_import_settings');

        if (empty($_FILES['agency_shield_cmp_settings_file']['tmp_name'])) {
            wp_safe_redirect(admin_url('options-general.php?page=agency-shield-cmp#ag-tab=tools'));
            exit;
        }

        $contents = file_get_contents($_FILES['agency_shield_cmp_settings_file']['tmp_name']);
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            wp_safe_redirect(admin_url('options-general.php?page=agency-shield-cmp#ag-tab=tools'));
            exit;
        }

        $sanitized = $this->sanitize_settings($decoded);
        update_option('agency_shield_cmp_settings', $sanitized, false);

        wp_safe_redirect(admin_url('options-general.php?page=agency-shield-cmp#ag-tab=tools'));
        exit;
    }

    public function handle_report_html() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('agency_shield_cmp_report_html');
        $scanner = new Agency_Shield_Scanner();
        $report = $scanner->get_report_data();

        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename=agency-shield-report.html');

        echo '<!doctype html><html><head><meta charset="utf-8"><title>PW Cookie Monster Report</title>';
        echo '<style>body{font-family:Arial,sans-serif;margin:20px;color:#111;}h1{margin-bottom:6px;}table{border-collapse:collapse;width:100%;margin-top:12px;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}th{background:#f3f4f6;}</style>';
        echo '</head><body>';
        echo '<h1>PW Cookie Monster - Informe</h1>';
        echo '<p><strong>Fecha:</strong> ' . esc_html($report['generated_at']) . '</p>';
        echo '<p><strong>Sitio:</strong> ' . esc_html($report['site_url']) . '</p>';
        echo '<h2>Categorias</h2>';
        echo '<table><thead><tr><th>Categoria</th><th>Descripcion</th><th>Cookies definidas</th><th>Cookies detectadas</th></tr></thead><tbody>';
        foreach ($report['categories'] as $category) {
            $detected = isset($category['detected_count']) ? $category['detected_count'] : 0;
            echo '<tr><td>' . esc_html($category['label']) . '</td><td>' . esc_html($category['description']) . '</td><td>' . esc_html($category['cookies_count']) . '</td><td>' . esc_html($detected) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h2>Dominios detectados</h2>';
        echo '<table><thead><tr><th>Dominio</th><th>Categoria</th><th>Servicio</th><th>Ultima deteccion</th></tr></thead><tbody>';
        foreach ($report['domains'] as $domain) {
            echo '<tr><td>' . esc_html($domain['domain']) . '</td><td>' . esc_html($domain['category']) . '</td><td>' . esc_html($domain['service']) . '</td><td>' . esc_html($domain['last_seen']) . '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h2>Cookies detectadas</h2>';
        echo '<table><thead><tr><th>Cookie</th><th>Dominio</th><th>Categoria</th><th>Ultima deteccion</th></tr></thead><tbody>';
        if (!empty($report['cookies'])) {
            foreach ($report['cookies'] as $cookie) {
                echo '<tr><td>' . esc_html($cookie['name']) . '</td><td>' . esc_html($cookie['domain']) . '</td><td>' . esc_html($cookie['category']) . '</td><td>' . esc_html($cookie['last_seen']) . '</td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '</body></html>';
        exit;
    }

    public function handle_report_json() {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado.');
        }

        check_admin_referer('agency_shield_cmp_report_json');
        $scanner = new Agency_Shield_Scanner();
        $report = $scanner->get_report_data();

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename=agency-shield-report.json');
        echo wp_json_encode($report);
        exit;
    }

    public function handle_collect_cookies() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'No autorizado'], 403);
        }

        check_ajax_referer('agency_shield_cmp_collect_cookies', 'nonce');

        $raw = isset($_POST['cookies']) ? wp_unslash($_POST['cookies']) : '';
        $cookies = json_decode($raw, true);
        if (!is_array($cookies)) {
            $cookies = [];
        }

        $domain = isset($_POST['domain']) ? sanitize_text_field(wp_unslash($_POST['domain'])) : '';
        if ($domain === '') {
            $domain = parse_url(home_url(), PHP_URL_HOST);
        }

        $host = parse_url(home_url(), PHP_URL_HOST);
        $normalized = [];
        foreach ($cookies as $cookie) {
            $name = sanitize_text_field($cookie);
            if ($name === '') {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'domain' => $domain,
                'category' => Agency_Shield_Scanner::categorize_cookie_for_site($name, $domain, $host),
                'last_seen' => time(),
            ];
        }

        if (!$normalized) {
            wp_send_json_success(['count' => 0]);
        }

        $existing = get_option('agency_shield_cmp_detected_cookies', []);
        if (!is_array($existing)) {
            $existing = [];
        }
        $merged = Agency_Shield_Scanner::merge_detected_cookies($existing, $normalized);
        update_option('agency_shield_cmp_detected_cookies', $merged, false);

        wp_send_json_success(['count' => count($normalized)]);
    }

    private function get_health_issues() {
        $issues = [];
        $settings = self::get_settings();

        if ($settings['geo_mode'] === 'none') {
            $issues[] = 'El CMP esta desactivado por geolocalizacion. Verifica cumplimiento.';
        }
        if ($settings['geo_mode'] === 'custom' && trim($settings['geo_countries']) === '') {
            $issues[] = 'Geo-targeting en modo custom sin paises definidos.';
        }

        if ($settings['enable_consent_log'] === false) {
            $issues[] = 'Registro de consentimiento desactivado.';
        }

        $discovered = get_option('agency_shield_cmp_discovered', []);
        if (is_array($discovered)) {
            $unknown = 0;
            foreach ($discovered as $data) {
                if (!empty($data['category']) && $data['category'] === 'unknown') {
                    $unknown++;
                }
            }
            if ($unknown > 0) {
                $issues[] = sprintf('Hay %d dominios sin clasificar.', $unknown);
            }
        }

        if (empty($settings['cookie_policy_url'])) {
            $issues[] = 'No hay URL de politica de cookies.';
        }

        if (empty($settings['privacy_policy_url'])) {
            $issues[] = 'No hay URL de politica de privacidad.';
        }

        return $issues;
    }
}
