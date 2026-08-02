<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_Role {

    const ROLE_ID = 'houetor_selfhare_agent';
    const ROLE_NAME = 'Agent SelfHare';

    public static function add_role() {
        $role = get_role('administrator');
        if ($role) {
            $role->add_cap(self::ROLE_ID, true);
        }

        $role = get_role(self::ROLE_ID);
        if ($role) return;

        $capabilities = [
            'read' => true,
            'edit_posts' => true,
            'edit_published_posts' => true,
            'publish_posts' => true,
            'delete_posts' => true,
            'delete_published_posts' => true,
            'upload_files' => true,
            'edit_pages' => true,
            'edit_published_pages' => true,
            'publish_pages' => true,
            'delete_pages' => true,
            'delete_published_pages' => true,
        ];

        add_role(self::ROLE_ID, self::ROLE_NAME, $capabilities);
    }

    public static function remove_role() {
        remove_role(self::ROLE_ID);
    }

    public static function ensure_admin_cap() {
        if (!function_exists('get_role')) return;
        $role = get_role('administrator');
        if ($role && !$role->has_cap(self::ROLE_ID)) {
            $role->add_cap(self::ROLE_ID, true);
        }
    }

    public static function has_capability($cap) {
        return current_user_can(self::ROLE_ID) || current_user_can('manage_options');
    }
}

add_action('admin_init', [Houetor_SelfHare_Role::class, 'ensure_admin_cap']);
