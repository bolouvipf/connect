<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_Onboarding {

    public static function run() {
        global $wpdb;

        $site_type = 'blog';
        $features = [];
        $has_woocommerce = class_exists('WooCommerce');

        if ($has_woocommerce) {
            $site_type = 'boutique';
            $features[] = 'woocommerce';
        }

        $page_counts = wp_count_posts('page');
        $post_counts = wp_count_posts('post');

        $nav_menus = wp_get_nav_menus();
        $menu_structure = [];
        foreach ($nav_menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            if ($items) {
                $menu_structure[] = [
                    'name' => $menu->name,
                    'count' => count($items),
                ];
            }
        }

        $plugins = get_option('active_plugins', []);
        $has_elementor = in_array('elementor/elementor.php', $plugins);
        $has_gutenberg = function_exists('register_block_type');

        $context = [
            'type' => $site_type,
            'lang' => get_locale(),
            'site_name' => get_bloginfo('name'),
            'site_description' => get_bloginfo('description'),
            'has_woocommerce' => $has_woocommerce,
            'has_elementor' => $has_elementor,
            'has_gutenberg' => $has_gutenberg,
            'page_count' => $page_counts->publish,
            'post_count' => $post_counts->publish,
            'active_plugins_count' => count($plugins),
            'menu_structure' => $menu_structure,
            'features' => $features,
            'timezone' => wp_timezone_string(),
            'onboarded_at' => current_time('mysql'),
        ];

        $wpdb->replace("{$wpdb->prefix}houetor_selfhare_memory", [
            'id' => 1,
            'context_json' => wp_json_encode($context),
            'updated_at' => current_time('mysql'),
        ]);
    }

    public static function get_context() {
        global $wpdb;
        $row = $wpdb->get_row("SELECT context_json FROM {$wpdb->prefix}houetor_selfhare_memory WHERE id = 1");
        if (!$row) return null;
        return json_decode($row->context_json, true);
    }

    public static function build_manifest() {
        $manifest = [
            'posts' => ['editable_fields' => ['post_title', 'post_content', 'post_status']],
            'pages' => ['editable_fields' => ['post_title', 'post_content']],
        ];

        $context = self::get_context();
        if ($context && !empty($context['has_woocommerce'])) {
            $manifest['products'] = ['editable_fields' => ['name', 'price', 'stock_quantity']];
        }

        return $manifest;
    }
}
