<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

delete_option('houetor_selfhare_activated_at');
delete_option('houetor_selfhare_license_key');
delete_option('houetor_selfhare_license_plan');
delete_option('houetor_selfhare_license');
delete_option('houetor_selfhare_pages_cache');
delete_option('houetor_selfhare_auto_mode');

wp_clear_scheduled_hook('houetor_selfhare_cron');
remove_role('houetor_selfhare_agent');

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}houetor_selfhare_memory");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}houetor_selfhare_routines");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}houetor_selfhare_actions_log");
