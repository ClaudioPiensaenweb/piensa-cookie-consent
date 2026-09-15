<?php
/**
 * Plugin Name: PW Cookie Monster
 * Description: CMP profesional con un guino divertido: el monstruo de las cookies protege tu cumplimiento y deja migas de datos limpios.
 * Version: 0.5.8
 * Author: PW Cookie Monster
 * Plugin URI: https://pwcookie.monster
 * Author URI: https://pwcookie.monster
 * License: GPLv2 or later
 * Text Domain: agency-shield-cmp
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('AGENCY_SHIELD_CMP_PATH', plugin_dir_path(__FILE__));
define('AGENCY_SHIELD_CMP_URL', plugin_dir_url(__FILE__));
define('AGENCY_SHIELD_CMP_FILE', __FILE__);

define('AGENCY_SHIELD_CMP_VERSION', '0.5.8');

require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-core.php';
require_once AGENCY_SHIELD_CMP_PATH . 'includes/class-consent-log.php';

function agency_shield_cmp_bootstrap() {
    $core = new Agency_Shield_Core();
    $core->init();
}

add_action('plugins_loaded', 'agency_shield_cmp_bootstrap');

register_activation_hook(__FILE__, ['Agency_Shield_Consent_Log', 'install_table']);
