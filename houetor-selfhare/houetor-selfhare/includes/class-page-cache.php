<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_Page_Cache {
    const OPTION_KEY = 'houetor_selfhare_pages_cache';

    public static function refresh() {
        $pages = get_pages(['sort_column' => 'post_title', 'sort_order' => 'ASC']);
        $cache = [];
        foreach ($pages as $p) {
            $cache[] = ['id' => $p->ID, 'title' => $p->post_title, 'slug' => $p->post_name];
        }
        update_option(self::OPTION_KEY, $cache, false);
        return $cache;
    }

    public static function get() {
        $cache = get_option(self::OPTION_KEY, null);
        return is_array($cache) ? $cache : self::refresh();
    }
}
