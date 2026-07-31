<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('hwc_code');
delete_option('houetor_site_token');
delete_option('hwc_layout');
delete_option('hwc_items_count');
delete_option('hwc_injections');
delete_option('hwc_token');
delete_option('houetor_connection_status');
delete_option('houetor_site_url');
delete_option('houetor_desync_url');
delete_transient('houetor_connect_remote_status');
