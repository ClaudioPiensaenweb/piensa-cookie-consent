<?php
// includes/class-core.php

if (!defined('ABSPATH')) {
    exit;
}

require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-scanner.php';
require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-consent-mode.php';
require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-admin.php';
require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-blocker.php';
require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-consent-log.php';
require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-geo.php';
require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-updater.php';

class Agency_Shield_Core {
    private $scanner;
    private $consent_mode;
    private $blocker;
    private $admin;
    private $consent_log;
    private $updater;

    public function __construct() {
        $this->scanner = new Agency_Shield_Scanner();
        $this->consent_mode = new Agency_Shield_Consent_Mode($this->scanner);
        $this->blocker = new Agency_Shield_Blocker();
        $this->admin = new Agency_Shield_Admin();
        $this->consent_log = new Agency_Shield_Consent_Log();
        $this->updater = new Agency_Shield_Updater(AGENCY_SHIELD_CMP_FILE);
    }

    public function init() {
        $this->consent_mode->init();
        $this->blocker->init();
        $this->admin->init();
        $this->consent_log->init();
        $this->updater->init();

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('agency_shield_consent_review', [$this, 'render_consent_review_shortcode']);
        add_shortcode('agency_shield_cookie_policy', [$this, 'render_cookie_policy_shortcode']);
        add_action('wp_footer', [$this, 'maybe_inject_cookie_audit'], 99);
    }

    public function enqueue_assets() {
        $settings = Agency_Shield_Admin::get_settings();
        if (!Agency_Shield_Geo::should_show_cmp($settings)) {
            return;
        }

        // Font Awesome para iconos
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', [], '6.5.1');

        wp_enqueue_style('agency-shield-cookieconsent', AGENCY_SHIELD_CMP_URL . 'assets/css/cookieconsent.css', [], AGENCY_SHIELD_CMP_VERSION);
        wp_enqueue_style('agency-shield-main', AGENCY_SHIELD_CMP_URL . 'assets/css/agency-shield.css', ['font-awesome'], AGENCY_SHIELD_CMP_VERSION);

        wp_enqueue_script('agency-shield-cookieconsent', AGENCY_SHIELD_CMP_URL . 'assets/js/cookieconsent.js', [], AGENCY_SHIELD_CMP_VERSION, true);
        wp_enqueue_script('agency-shield-main', AGENCY_SHIELD_CMP_URL . 'assets/js/agency-shield.js', ['agency-shield-cookieconsent'], AGENCY_SHIELD_CMP_VERSION, true);

        if (is_user_logged_in() && current_user_can('manage_options') && !empty($_GET['ag_cookie_audit']) && !empty($_GET['ag_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['ag_nonce']));
            if (wp_verify_nonce($nonce, 'agency_shield_cmp_audit')) {
                wp_enqueue_script('agency-shield-audit', AGENCY_SHIELD_CMP_URL . 'assets/js/agency-shield-audit.js', [], AGENCY_SHIELD_CMP_VERSION, true);
                wp_localize_script('agency-shield-audit', 'PWCookieAuditCfg', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('agency_shield_cmp_collect_cookies'),
                    'domain' => parse_url(home_url(), PHP_URL_HOST),
                ]);
            }
        }

        $categories = $this->scanner->get_active_categories();
        $cookie_definitions = $this->scanner->get_cookie_definitions();
        $services = Agency_Shield_Scanner::get_services();
        $discovered = get_option('agency_shield_cmp_discovered', []);
        $detected_services = [];
        if (is_array($discovered)) {
            foreach ($discovered as $data) {
                if (!empty($data['service'])) {
                    $detected_services[$data['service']] = true;
                }
            }
        }
        $site_lang = substr(get_locale(), 0, 2);
        wp_localize_script('agency-shield-main', 'AgencyShieldConfig', [
            'categories' => $categories,
            'cookieDefinitions' => $cookie_definitions,
            'ui' => [
                'floatingButton' => !empty($settings['show_floating_button']),
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
                'mode' => $settings['language_mode'],
                'default' => $settings['default_language'],
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
            'services' => [
                'registry' => $services,
                'detected' => array_keys($detected_services),
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
                'logConsent' => !empty($settings['enable_consent_log']),
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
        ]);
    }

    public function render_consent_review_shortcode() {
        $settings = Agency_Shield_Admin::get_settings();
        $button_text = esc_html($settings['floating_button_text']);
        $button_label = esc_attr($settings['floating_button_text']);
        $style = $settings['floating_button_style'];
        $icon = $this->get_review_icon_svg();

        $button = $style === 'icon'
            ? '<button type="button" class="ag-btn-consent-review ag-btn-icon" data-ag-consent-review="1" aria-label="' . $button_label . '">' . $icon . '<span class="ag-btn-label">' . $button_text . '</span></button>'
            : '<button type="button" class="ag-btn-consent-review" data-ag-consent-review="1">' . $button_text . '</button>';

        return '<div class="ag-consent-review">' .
            $button .
            '<span class="ag-consent-status" data-ag-consent-status="1"></span>' .
            '</div>';
    }

    private function get_review_icon_svg() {
        return '<svg class="ag-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2l7 3v6c0 5-3.5 9-7 11-3.5-2-7-6-7-11V5l7-3zm0 5a1 1 0 00-1 1v4c0 .6.4 1 1 1h4a1 1 0 100-2h-3V8a1 1 0 00-1-1z"/></svg>';
    }

    public function render_cookie_policy_shortcode() {
        $definitions = $this->scanner->get_cookie_definitions();

        $output = '<div class="ag-cookie-policy">';
        foreach ($definitions as $category => $data) {
            $label = esc_html($data['label']);
            $description = esc_html($data['description']);
            $output .= '<h3>' . $label . '</h3>';
            $output .= '<p>' . $description . '</p>';

            if (!empty($data['cookies'])) {
                $output .= '<table class="ag-cookie-table">';
                $output .= '<thead><tr><th>Cookie</th><th>Dominio</th><th>Finalidad</th><th>Duracion</th></tr></thead><tbody>';
                foreach ($data['cookies'] as $cookie) {
                    $name = esc_html($cookie['name']);
                    $domain = esc_html($cookie['domain']);
                    $purpose = esc_html($cookie['description']);
                    $duration = esc_html($cookie['duration']);
                    $output .= '<tr><td>' . $name . '</td><td>' . $domain . '</td><td>' . $purpose . '</td><td>' . $duration . '</td></tr>';
                }
                $output .= '</tbody></table>';
            } else {
                $output .= '<p>No se han declarado cookies en esta categoria.</p>';
            }
        }
        $output .= '</div>';

        return $output;
    }

    public function maybe_inject_cookie_audit() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }

        if (empty($_GET['ag_cookie_audit']) || empty($_GET['ag_nonce'])) {
            return;
        }

        $nonce = sanitize_text_field(wp_unslash($_GET['ag_nonce']));
        if (!wp_verify_nonce($nonce, 'agency_shield_cmp_audit')) {
            return;
        }

        echo '<div id="pw-cookie-audit-toast" style="position:fixed;bottom:16px;right:16px;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;font-size:12px;z-index:99999;box-shadow:0 10px 30px rgba(0,0,0,0.2);">Auditoria de cookies en curso...</div>';
    }
}
