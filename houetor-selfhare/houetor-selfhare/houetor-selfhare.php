<?php
/**
 * @wordpress-plugin
 * Plugin Name: Houetor SelfHare
 * Plugin URI:  https://houetor.com/selfhare
 * Description: Assistant IA WordPress propulsé par SelfHare / Houetor
 * Version:     1.0.3
 * Author:      Houetor
 * Author URI:  https://houetor.com
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: houetor-selfhare
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined('ABSPATH') || exit;

add_action('init', function () {
    if (!post_type_supports('page', 'revisions')) {
        add_post_type_support('page', 'revisions');
    }
}, 5);

define('HOUETOR_SELFHARE_VERSION', '1.0.3');
define('HOUETOR_SELFHARE_FILE', __FILE__);
define('HOUETOR_SELFHARE_PATH', plugin_dir_path(__FILE__));
define('HOUETOR_SELFHARE_URL', plugin_dir_url(__FILE__));
define('HOUETOR_SELFHARE_RELAY_URL', 'https://www.houetor.com/selfhare/relay');
define('HOUETOR_SELFHARE_VALIDATE_URL', 'https://www.houetor.com/selfhare/license/validate');

require_once HOUETOR_SELFHARE_PATH . 'includes/class-license.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-onboarding.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-agent-memory.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-agent-dispatch.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-error-translator.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-html-transformer.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-agent-chat.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-agent-routines.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-agent-role.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-rest-api.php';
require_once HOUETOR_SELFHARE_PATH . 'includes/class-page-cache.php';


add_action('plugins_loaded', function () {
    load_plugin_textdomain('houetor-selfhare', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

register_activation_hook(__FILE__, 'houetor_selfhare_activate');
register_deactivation_hook(__FILE__, 'houetor_selfhare_deactivate');

function houetor_selfhare_activate() {
    houetor_selfhare_create_tables();
    Houetor_SelfHare_Role::add_role();
    Houetor_SelfHare_Onboarding::run();
    Houetor_SelfHare_Page_Cache::refresh();
    update_option('houetor_selfhare_activated_at', time());
}

add_action('save_post_page', ['Houetor_SelfHare_Page_Cache', 'refresh']);
add_action('delete_post', ['Houetor_SelfHare_Page_Cache', 'refresh']);

function houetor_selfhare_deactivate() {
    Houetor_SelfHare_Routines::clear_schedule();
}

function houetor_selfhare_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();

    $tables = [
        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}houetor_selfhare_memory (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            context_json LONGTEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) $charset;",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}houetor_selfhare_routines (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            trigger_type VARCHAR(50),
            action_type VARCHAR(50),
            params LONGTEXT,
            active TINYINT(1) DEFAULT 1,
            last_run DATETIME NULL
        ) $charset;",

        "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}houetor_selfhare_actions_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action_type VARCHAR(50),
            before_json LONGTEXT,
            after_json LONGTEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) $charset;",
    ];

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ($tables as $sql) {
        dbDelta($sql);
    }
}

add_action('admin_menu', 'houetor_selfhare_admin_menu');
function houetor_selfhare_admin_menu() {
    add_menu_page(
        'SelfHare',
        'SelfHare',
        'manage_options',
        'houetor-selfhare',
        'houetor_selfhare_license_page',
        'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"><path d="M12 2C8 2 5 5 5 9c0 2.5 1.5 4.5 3 5.5V20c0 .6.4 1 1 1h6c.6 0 1-.4 1-1v-5.5c1.5-1 3-3 3-5.5 0-4-3-7-7-7zm0 2c1.5 0 3 .8 3.7 2H12v2h4.5c.3.6.5 1.3.5 2H12v2h4.8c-.2.8-.7 1.5-1.2 2H12v2h2.5c-.3.2-.6.4-.9.5-.4.2-.8.3-1.1.3-1.1 0-2-.6-2.5-1.2-.3-.4-.5-.9-.5-1.6 0-1.2.7-2.2 1.5-2.8.4-.3.7-.5.7-.8v-1.2c0-.4-.2-.6-.5-.8-.7-.5-1.2-1.2-1.2-2.2 0-1.2.8-2 2-2z" fill="currentColor"/></svg>'),
        30
    );

    add_submenu_page(
        'houetor-selfhare',
        'Activation',
        'Activation',
        'manage_options',
        'houetor-selfhare',
        'houetor_selfhare_license_page'
    );

    if (Houetor_SelfHare_License::is_active()) {
        add_submenu_page(
            'houetor-selfhare',
            'Assistant',
            'Assistant',
            'houetor_selfhare_agent',
            'houetor-selfhare-assistant',
            'houetor_selfhare_assistant_page'
        );

        add_submenu_page(
            'houetor-selfhare',
            'Routines',
            'Routines',
            'manage_options',
            'houetor-selfhare-routines',
            'houetor_selfhare_routines_page'
        );
    }
}

function houetor_selfhare_license_page() {
    Houetor_SelfHare_License::render_page();
}

function houetor_selfhare_assistant_page() {
    Houetor_SelfHare_Chat::render_page();
}

function houetor_selfhare_routines_page() {
    Houetor_SelfHare_Routines::render_page();
}

add_action('admin_enqueue_scripts', 'houetor_selfhare_admin_assets');
function houetor_selfhare_admin_assets($hook) {
    if (strpos($hook, 'houetor-selfhare') === false) return;
    wp_enqueue_style('houetor-selfhare-fonts', 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap', [], null);
    wp_enqueue_style('houetor-selfhare-admin', HOUETOR_SELFHARE_URL . 'assets/admin-chat.css', ['houetor-selfhare-fonts'], filemtime(plugin_dir_path(__FILE__) . 'assets/admin-chat.css'));
    wp_enqueue_script('houetor-selfhare-admin', HOUETOR_SELFHARE_URL . 'assets/admin-chat.js', ['jquery'], filemtime(plugin_dir_path(__FILE__) . 'assets/admin-chat.js'), true);
    wp_localize_script('houetor-selfhare-admin', 'HouetorSelfHare', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'rest_url' => rest_url('houetor-selfhare/v1/'),
        'nonce' => wp_create_nonce('houetor_selfhare_nonce'),
        'version' => HOUETOR_SELFHARE_VERSION,
    ]);
}

add_action('admin_footer', function () {
    if (strpos(get_current_screen()->base ?? '', 'houetor-selfhare') === false) return;
    echo '<p style="text-align:center;color:#8c8f94;font-size:12px;">SelfHare v' . esc_html(HOUETOR_SELFHARE_VERSION) . '</p>';
});

add_action('wp_ajax_houetor_selfhare_get_page_history', function () {
    check_ajax_referer('houetor_selfhare_nonce', 'nonce');
    if (!current_user_can('edit_pages')) wp_send_json_error('forbidden', 403);

    $post_id = absint($_POST['post_id'] ?? $_POST['page_id'] ?? 0);
    if (!$post_id || !get_post($post_id)) {
        wp_send_json_error('Page introuvable.');
        return;
    }
    $revisions = wp_get_post_revisions($post_id, ['order' => 'DESC']);
    $result = [];
    foreach ($revisions as $rev) {
        $result[] = [
            'revision_id' => $rev->ID,
            'author'      => get_the_author_meta('display_name', $rev->post_author),
            'date'        => get_the_modified_date('Y-m-d H:i:s', $rev->ID),
            'excerpt'     => wp_trim_words(wp_strip_all_tags($rev->post_content), 20),
        ];
    }
    wp_send_json_success(['post_id' => $post_id, 'revisions' => $result, 'count' => count($result)]);
});

add_action('wp_ajax_houetor_selfhare_restore_version', function () {
    check_ajax_referer('houetor_selfhare_nonce', 'nonce');
    if (!current_user_can('edit_pages')) wp_send_json_error('forbidden', 403);

    $post_id = absint($_POST['post_id'] ?? $_POST['page_id'] ?? 0);
    $revision_id = absint($_POST['revision_id'] ?? 0);
    if (!$revision_id || !$post_id) {
        wp_send_json_error('ID de révision et ID de publication requis.');
        return;
    }
    $revision = wp_get_post_revision($revision_id);
    if (!$revision) {
        wp_send_json_error('Révision introuvable.');
        return;
    }
    wp_send_json_success(wp_restore_post_revision($revision_id)
        ? ['post_id' => $post_id, 'revision_id' => $revision_id, 'message' => "Révision #$revision_id restaurée."]
        : ['success' => false, 'message' => 'Impossible de restaurer cette révision.']);
});

add_action('admin_menu', function () {
    add_management_page(
        'HOUETOR SelfHare — Journal',
        'SelfHare Journal',
        'manage_options',
        'houetor-selfhare-log',
        function () {
            if (!current_user_can('manage_options')) return;
            global $wpdb;
            $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
            $per_page = 10;
            $offset = ($paged - 1) * $per_page;
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}houetor_selfhare_actions_log");
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, action_type, before_json, after_json, created_at
                 FROM {$wpdb->prefix}houetor_selfhare_actions_log
                 ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page, $offset
            ));
            echo '<div class="wrap"><h1>Journal SelfHare (' . esc_html($total) . ' actions)</h1>';
            foreach ($rows as $r) {
                echo '<h3>#' . esc_html($r->id) . ' — ' . esc_html($r->action_type) . ' — ' . esc_html($r->created_at) . '</h3>';
                echo '<strong>Avant :</strong><pre style="white-space:pre-wrap;background:#f6f6f6;padding:10px;max-height:200px;overflow:auto;">' . esc_html($r->before_json) . '</pre>';
                echo '<strong>Après :</strong><pre style="white-space:pre-wrap;background:#f6f6f6;padding:10px;max-height:200px;overflow:auto;">' . esc_html($r->after_json) . '</pre>';
            }
            if ($total > $per_page) {
                $pages = ceil($total / $per_page);
                echo '<div class="tablenav"><div class="tablenav-pages">Pages : ';
                for ($i = 1; $i <= $pages; $i++) {
                    $cls = $i === $paged ? 'button button-primary' : 'button';
                    echo '<a class="' . esc_attr($cls) . '" href="' . esc_url(admin_url('tools.php?page=houetor-selfhare-log&paged=' . $i)) . '" style="margin-right:4px;">' . esc_html($i) . '</a> ';
                }
                echo '</div></div>';
            }
            echo '</div>';
        }
    );
});
