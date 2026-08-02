<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_Memory {

    public static function get() {
        global $wpdb;
        $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}houetor_selfhare_memory WHERE id = 1");
        return $row;
    }

    public static function update_context($context_json) {
        global $wpdb;
        $wpdb->replace("{$wpdb->prefix}houetor_selfhare_memory", [
            'id' => 1,
            'context_json' => $context_json,
            'updated_at' => current_time('mysql'),
        ]);
    }

    public static function get_context() {
        $row = self::get();
        if (!$row || empty($row->context_json)) return [];
        return json_decode($row->context_json, true) ?: [];
    }
}
