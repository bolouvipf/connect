<?php
/**
 * @wordpress-plugin
 * Plugin Name:       Houetor Connect
 * Plugin URI:        https://houetor.com
 * Description:       Connecte votre site WordPress a Houetor Hare. Affiche automatiquement vos annonces, produits ou formations selon votre profil HWT.
 * Version:           2.7.0
 * Author:            Houetor
 * Author URI:        https://houetor.com
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       houetor-connect
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

defined('ABSPATH') || exit;

define('HWC_VERSION', '2.7.0');
define('HWC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HWC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('HWC_API_BASE', 'https://houetor.com/api/public');

require_once HWC_PLUGIN_DIR . 'includes/class-hwt-parser.php';
require_once HWC_PLUGIN_DIR . 'includes/class-connect-status.php';
require_once HWC_PLUGIN_DIR . 'includes/class-admin-settings.php';
require_once HWC_PLUGIN_DIR . 'includes/class-api-fetcher.php';
require_once HWC_PLUGIN_DIR . 'includes/class-content-injector.php';
require_once HWC_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once HWC_PLUGIN_DIR . 'includes/class-block-editor.php';

function hwc_init() {
    load_plugin_textdomain('houetor-connect', false, dirname(plugin_basename(__FILE__)) . '/languages');
    $admin = new HWC_Admin_Settings();
    $injector = new HWC_Content_Injector();
    $rest = new HWC_REST_API();

    add_action('admin_menu', array($admin, 'add_admin_menu'));
    add_action('admin_init', array($admin, 'register_settings'));
    add_action('admin_init', array($admin, 'redirect_legacy_url'));
    add_action('admin_post_hwc_connect', array($admin, 'handle_connect'));
    add_action('admin_post_hwc_disconnect', array($admin, 'handle_disconnect'));
    add_action('admin_post_hwc_reset_desync', array($admin, 'handle_reset_desync'));
    add_action('admin_enqueue_scripts', array($admin, 'enqueue_admin_assets'));
    add_filter('the_content', array($injector, 'inject_content'));
    add_action('wp_enqueue_scripts', array($injector, 'enqueue_frontend_assets'));
    add_action('rest_api_init', array($rest, 'register_routes'));
    add_action('wp_ajax_hwc_submit_order', 'hwc_handle_ajax_order');
    add_action('wp_ajax_nopriv_hwc_submit_order', 'hwc_handle_ajax_order');
}
add_action('plugins_loaded', 'hwc_init');

function hwc_handle_ajax_order() {
    check_ajax_referer('hwc_order_nonce', 'nonce');

    if (!Houetor_Connect::is_connected()) {
        wp_send_json_error(array('message' => 'Connexion HOUETOR requise pour soumettre une commande.'));
    }

    $name    = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
    $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
    $phone   = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
    $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
    $item_id = isset($_POST['item_id']) ? sanitize_text_field(wp_unslash($_POST['item_id'])) : '';

    if (empty($name) || empty($email)) {
        wp_send_json_error(array('message' => 'Veuillez remplir tous les champs obligatoires.'));
    }

    $to = get_option('admin_email');
    $subject = sprintf('[Houetor Connect] Nouvelle commande de %s', $name);
    $body = sprintf(
        "Nom: %s\nEmail: %s\nTéléphone: %s\nID Article: %s\nMessage: %s",
        $name, $email, $phone, $item_id, $message
    );
    wp_mail($to, $subject, $body);

    wp_send_json_success(array('message' => 'Votre commande a été envoyée avec succès.'));
}

function hwc_create_audit_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'houetor_connect_actions_log';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        action_type VARCHAR(50) NOT NULL,
        before_json LONGTEXT NULL,
        after_json LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY action_type (action_type),
        KEY created_at (created_at)
    ) $charset_collate;";
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function hwc_activate() {
    if (!get_option('hwc_token')) {
        update_option('hwc_token', wp_generate_password(32, false));
    }
    hwc_create_audit_table();
    if (get_option('hwc_audit_retention_days') === false) {
        update_option('hwc_audit_retention_days', 90);
    }
    if (!wp_next_scheduled('hwc_audit_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'hwc_audit_cleanup');
    }
}
register_activation_hook(__FILE__, 'hwc_activate');

function hwc_deactivate() {
    wp_clear_scheduled_hook('hwc_audit_cleanup');
}
register_deactivation_hook(__FILE__, 'hwc_deactivate');

/**
 * Runner du CRON quotidien de rétention du journal d'audit.
 */
function hwc_audit_cleanup_runner() {
    HWC_REST_API::audit_cleanup();
}
add_action('hwc_audit_cleanup', 'hwc_audit_cleanup_runner');
