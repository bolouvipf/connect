<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_REST_API {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        register_rest_route('houetor-selfhare/v1', '/context', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_context'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);

        register_rest_route('houetor-selfhare/v1', '/manifest', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_manifest'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
    }

    public static function check_permission() {
        return current_user_can('houetor_selfhare_agent') || current_user_can('manage_options');
    }

    public static function get_context() {
        $context = Houetor_SelfHare_Memory::get_context();
        return new WP_REST_Response(['success' => true, 'data' => $context]);
    }

    public static function get_manifest() {
        $manifest = Houetor_SelfHare_Onboarding::build_manifest();
        return new WP_REST_Response(['success' => true, 'data' => $manifest]);
    }
}

Houetor_SelfHare_REST_API::init();
