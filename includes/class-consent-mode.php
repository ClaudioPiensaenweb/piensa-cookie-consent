<?php
// includes/class-consent-mode.php

if (!defined('ABSPATH')) {
    exit;
}

class Piensa_Cookie_Consent_Consent_Mode {
    private $scanner;

    public function __construct($scanner) {
        $this->scanner = $scanner;
    }

    public function init() {
        add_action('wp_head', [$this, 'inject_consent_mode'], 1);
    }

    public function inject_consent_mode() {
        $categories = $this->scanner->get_active_categories();
        $has_analytics = !empty($categories['analytics']);
        $has_marketing = !empty($categories['marketing']);

        $settings = Piensa_Cookie_Consent_Admin::get_settings();
        if (!Piensa_Cookie_Consent_Geo::should_show_cmp($settings)) {
            $analytics_granted = $has_analytics;
            $marketing_granted = $has_marketing;
        } else {
            $consent = $this->get_current_consent();
            $analytics_granted = $has_analytics && $consent['analytics'];
            $marketing_granted = $has_marketing && $consent['marketing'];
        }

        $analytics_value = $analytics_granted ? 'granted' : 'denied';
        $marketing_value = $marketing_granted ? 'granted' : 'denied';

        echo "\n";
        echo '<script>'; 
        echo 'window.dataLayer = window.dataLayer || [];';
        echo 'function gtag(){dataLayer.push(arguments);}';
        echo "gtag('consent', 'default', {";
        echo "'analytics_storage': '$analytics_value',";
        echo "'ad_storage': '$marketing_value',";
        echo "'ad_user_data': '$marketing_value',";
        echo "'ad_personalization': '$marketing_value'";
        echo "});";
        echo '</script>'; 
        echo "\n";
    }

    private function get_current_consent() {
        $consent = [
            'analytics' => false,
            'marketing' => false,
        ];

        $consent['analytics'] = Piensa_Cookie_Consent_Consent::has_consent('analytics');
        $consent['marketing'] = Piensa_Cookie_Consent_Consent::has_consent('marketing');

        return $consent;
    }

}
