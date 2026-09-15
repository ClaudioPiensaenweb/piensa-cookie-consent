<?php
// includes/class-core.php

if (!defined('ABSPATH')) {
    exit;
}

require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-scanner.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent-mode.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-admin.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-blocker.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent-log.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-geo.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-icons.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent.php';
require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-consent-api.php';

class Piensa_Cookie_Consent_Core {
    private $scanner;
    private $consent_mode;
    private $blocker;
    private $admin;
    private $consent_log;
    private $updater;
    private $consent_api;

    public function __construct() {
        $this->scanner = new Piensa_Cookie_Consent_Scanner();
        $this->consent_mode = new Piensa_Cookie_Consent_Consent_Mode($this->scanner);
        $this->blocker = new Piensa_Cookie_Consent_Blocker();
        $this->admin = new Piensa_Cookie_Consent_Admin();
        $this->consent_log = new Piensa_Cookie_Consent_Consent_Log();
        $this->consent_api = new Piensa_Cookie_Consent_Consent_API();
    }

    public function init() {
        $this->consent_mode->init();
        $this->blocker->init();
        $this->admin->init();
        $this->consent_log->init();
        $this->consent_api->init();
        $this->maybe_init_updater();

        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('piensa_cookie_consent_review', [$this, 'render_consent_review_shortcode']);
        add_shortcode('piensa_cookie_consent_policy', [$this, 'render_cookie_policy_shortcode']);
        add_action('wp_footer', [$this, 'maybe_inject_cookie_audit'], 99);
    }

    /**
     * Load the self-hosted updater, when this build ships one.
     *
     * WordPress.org guideline #8 forbids a plugin in the directory from
     * serving its own updates, so `includes/class-updater.php` is stripped
     * from the wp.org package. The agency build keeps it, and a site can still
     * opt out by defining PIENSA_COOKIE_CONSENT_DISABLE_UPDATER.
     *
     * @return void
     */

    /**
     * Whether this build ships the self-hosted updater.
     *
     * The wp.org package strips it, so anything that configures it must be
     * hidden there rather than shown and left broken.
     *
     * @return bool
     */
    public static function has_self_hosted_updater() {
        if (defined('PIENSA_COOKIE_CONSENT_DISABLE_UPDATER') && PIENSA_COOKIE_CONSENT_DISABLE_UPDATER) {
            return false;
        }

        return file_exists(PIENSA_COOKIE_CONSENT_PATH . 'includes/class-updater.php');
    }

    private function maybe_init_updater() {
        if (!self::has_self_hosted_updater()) {
            return;
        }

        require_once PIENSA_COOKIE_CONSENT_PATH . 'includes/class-updater.php';

        $this->updater = new Piensa_Cookie_Consent_Updater(PIENSA_COOKIE_CONSENT_FILE);
        $this->updater->init();
    }

    public function enqueue_assets() {
        $settings = Piensa_Cookie_Consent_Admin::get_settings();
        if (!Piensa_Cookie_Consent_Geo::should_show_cmp($settings)) {
            return;
        }

        wp_enqueue_style('piensa-cookie-consent-cookieconsent', PIENSA_COOKIE_CONSENT_URL . 'assets/css/cookieconsent.css', [], PIENSA_COOKIE_CONSENT_VERSION);
        wp_enqueue_style('piensa-cookie-consent-main', PIENSA_COOKIE_CONSENT_URL . 'assets/css/piensa-cookie-consent.css', [], PIENSA_COOKIE_CONSENT_VERSION);

        wp_enqueue_script('piensa-cookie-consent-cookieconsent', PIENSA_COOKIE_CONSENT_URL . 'assets/js/cookieconsent.js', [], PIENSA_COOKIE_CONSENT_VERSION, true);
        wp_enqueue_script('piensa-cookie-consent-main', PIENSA_COOKIE_CONSENT_URL . 'assets/js/piensa-cookie-consent.js', ['piensa-cookie-consent-cookieconsent'], PIENSA_COOKIE_CONSENT_VERSION, true);

        if (is_user_logged_in() && current_user_can('manage_options') && !empty($_GET['ag_cookie_audit']) && !empty($_GET['ag_nonce'])) {
            $nonce = sanitize_text_field(wp_unslash($_GET['ag_nonce']));
            if (wp_verify_nonce($nonce, 'piensa_cookie_consent_audit')) {
                wp_enqueue_script('piensa-cookie-consent-audit', PIENSA_COOKIE_CONSENT_URL . 'assets/js/piensa-cookie-consent-audit.js', [], PIENSA_COOKIE_CONSENT_VERSION, true);
                wp_localize_script('piensa-cookie-consent-audit', 'PiensaCookieConsentAudit', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('piensa_cookie_consent_collect_cookies'),
                    'domain' => parse_url(home_url(), PHP_URL_HOST),
                ]);
            }
        }

        $categories = $this->scanner->get_active_categories();
        $cookie_definitions = $this->scanner->get_cookie_definitions();
        $services = Piensa_Cookie_Consent_Scanner::get_services();
        $discovered = get_option('piensa_cookie_consent_discovered', []);
        $detected_services = [];
        if (is_array($discovered)) {
            foreach ($discovered as $data) {
                if (!empty($data['service'])) {
                    $detected_services[$data['service']] = true;
                }
            }
        }
        $site_lang = substr(get_locale(), 0, 2);
        wp_localize_script('piensa-cookie-consent-main', 'PiensaCookieConsentConfig', [
            'icons' => Piensa_Cookie_Consent_Icons::get_all_paths(),
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
                'nonce' => wp_create_nonce('piensa_cookie_consent_log'),
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
        $settings = Piensa_Cookie_Consent_Admin::get_settings();
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
        if (!wp_verify_nonce($nonce, 'piensa_cookie_consent_audit')) {
            return;
        }

        echo '<div id="pw-cookie-audit-toast" style="position:fixed;bottom:16px;right:16px;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;font-size:12px;z-index:99999;box-shadow:0 10px 30px rgba(0,0,0,0.2);">Auditoria de cookies en curso...</div>';
    }
}
