<?php
defined('ABSPATH') || exit;

class HWC_REST_API {

    public function register_routes() {
        register_rest_route('houetor/v1', '/pages', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_pages'),
            'permission_callback' => array($this, 'check_token'),
        ));

        register_rest_route('houetor/v1', '/menus', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_menus'),
            'permission_callback' => array($this, 'check_token'),
        ));

        register_rest_route('houetor/v1', '/media', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_media'),
                'permission_callback' => array($this, 'check_token'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'upload_media'),
                'permission_callback' => array($this, 'check_token'),
            ),
        ));

        register_rest_route('houetor/v1', '/inject', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'inject_content'),
            'permission_callback' => array($this, 'check_token'),
        ));

        register_rest_route('houetor/v1', '/uninject', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'uninject_content'),
            'permission_callback' => array($this, 'check_token'),
        ));

        register_rest_route('houetor/v1', '/page-blocks', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'get_page_blocks'),
            'permission_callback' => array($this, 'check_token'),
        ));

        register_rest_route('houetor/v1', '/block-content', array(
            'methods'             => 'PATCH',
            'callback'            => array($this, 'update_block_content'),
            'permission_callback' => array($this, 'check_token'),
        ));

        register_rest_route('houetor/v1', '/blocks', array(
            array(
                'methods'             => 'POST',
                'callback'            => array($this, 'create_block'),
                'permission_callback' => array($this, 'check_token'),
            ),
            array(
                'methods'             => 'DELETE',
                'callback'            => array($this, 'delete_block'),
                'permission_callback' => array($this, 'check_token'),
            ),
        ));
    }

    public function check_token($request) {
        $token = $request->get_header('X-Houetor-Token');

        if (empty($token)) {
            $auth_header = $request->get_header('Authorization');
            if (!empty($auth_header) && preg_match('/^Bearer\s+(.+)$/i', $auth_header, $matches)) {
                $token = $matches[1];
            }
        }

        $stored = get_option('hwc_token', '');

        if (empty($token) || empty($stored)) {
            return new WP_Error('forbidden', 'Token manquant.', array('status' => 403));
        }

        if (!hash_equals($stored, $token)) {
            return new WP_Error('forbidden', 'Token invalide.', array('status' => 403));
        }

        return true;
    }

    public function get_pages() {
        $pages = get_pages(array('number' => 100));

        $data = array_map(function ($page) {
            return array(
                'id'    => $page->ID,
                'title' => $page->post_title,
                'slug'  => $page->post_name,
                'url'   => get_permalink($page->ID),
            );
        }, $pages);

        return new WP_REST_Response(array('pages' => $data), 200);
    }

    public function get_menus() {
        $menus = wp_get_nav_menus();
        $data = array();

        foreach ($menus as $menu) {
            $items = wp_get_nav_menu_items($menu->term_id);
            $menu_items = array();

            if (!empty($items) && is_array($items)) {
                foreach ($items as $item) {
                    $menu_items[] = array(
                        'id'   => $item->ID,
                        'title' => $item->title,
                        'url'   => $item->url,
                    );
                }
            }

            $data[] = array(
                'id'    => $menu->term_id,
                'name'  => $menu->name,
                'slug'  => $menu->slug,
                'items' => $menu_items,
            );
        }

        return new WP_REST_Response(array('menus' => $data), 200);
    }

    public function get_media() {
        $attachments = get_posts(array(
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $data = array_map(function ($attachment) {
            return array(
                'id'       => $attachment->ID,
                'url'      => wp_get_attachment_url($attachment->ID),
                'filename' => basename(get_attached_file($attachment->ID)),
            );
        }, $attachments);

        return new WP_REST_Response(array('media' => $data), 200);
    }

    public function inject_content($request) {
        $params = $request->get_params();

        $page_id  = isset($params['page_id']) ? intval($params['page_id']) : 0;
        $content  = isset($params['content']) ? wp_kses_post($params['content']) : '';
        $module   = isset($params['module']) ? sanitize_text_field($params['module']) : '';
        $position = isset($params['position']) ? sanitize_text_field($params['position']) : 'append';
        $block_id = isset($params['block_id']) ? sanitize_text_field($params['block_id']) : '';

        if ($page_id === 0 || empty($content)) {
            return new WP_Error('bad_request', 'page_id et contenu requis.', array('status' => 400));
        }

        if (empty($module)) {
            return new WP_Error('bad_request', 'module requis.', array('status' => 400));
        }

        $post = get_post($page_id);
        if (!$post) {
            return new WP_Error('not_found', 'Page introuvable.', array('status' => 404));
        }

        $current_content = $post->post_content;

        if (empty($block_id)) {
            $block_id = uniqid($module . '-');
        }

        $marker_start = '<!-- HWC ' . $module . '-' . $block_id . ' start -->';
        $marker_end   = '<!-- HWC ' . $module . '-' . $block_id . ' end -->';
        $injected = $marker_start . $content . $marker_end;

        $pattern = '/<!-- HWC ' . preg_quote($module . '-' . $block_id, '/') . ' start -->.*?<!-- HWC ' . preg_quote($module . '-' . $block_id, '/') . ' end -->/s';

        if (preg_match($pattern, $current_content)) {
            $new_content = preg_replace($pattern, $injected, $current_content);
        } else {
            switch ($position) {
                case 'prepend':
                    $new_content = $injected . "\n" . $current_content;
                    break;
                case 'replace':
                    $new_content = $injected;
                    break;
                case 'append':
                default:
                    $new_content = $current_content . "\n" . $injected;
                    break;
            }
        }

        $updated = wp_update_post(array(
            'ID'           => $page_id,
            'post_content' => $new_content,
        ), true);

        if (is_wp_error($updated)) {
            return new WP_Error('update_failed', 'Impossible de mettre à jour la page.', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'success'  => true,
            'page_id'  => $page_id,
            'module'   => $module,
            'block_id' => $block_id,
        ), 200);
    }

    public function uninject_content($request) {
        $params = $request->get_params();

        $page_id = isset($params['page_id']) ? intval($params['page_id']) : 0;
        $module  = isset($params['module']) ? sanitize_text_field($params['module']) : '';
        $block_id = isset($params['block_id']) ? sanitize_text_field($params['block_id']) : '';

        if ($page_id === 0 || empty($module) || empty($block_id)) {
            return new WP_Error('bad_request', 'page_id, module et block_id requis.', array('status' => 400));
        }

        $post = get_post($page_id);
        if (!$post) {
            return new WP_Error('not_found', 'Page introuvable.', array('status' => 404));
        }

        $current_content = $post->post_content;
        $pattern = '/<!-- HWC ' . preg_quote($module . '-' . $block_id, '/') . ' start -->.*?<!-- HWC ' . preg_quote($module . '-' . $block_id, '/') . ' end -->/s';

        if (!preg_match($pattern, $current_content)) {
            return new WP_REST_Response(array(
                'success'  => true,
                'message' => 'Aucun bloc trouvé à retirer (déjà supprimé ou block_id inexistant).',
                'page_id'  => $page_id,
                'module'   => $module,
                'block_id' => $block_id,
            ), 200);
        }

        $new_content = preg_replace($pattern, '', $current_content);

        $updated = wp_update_post(array(
            'ID'           => $page_id,
            'post_content' => $new_content,
        ), true);

        if (is_wp_error($updated)) {
            return new WP_Error('update_failed', 'Impossible de mettre à jour la page.', array('status' => 500));
        }

        return new WP_REST_Response(array(
            'success'  => true,
            'message'  => 'Bloc HWC retiré avec succès.',
            'page_id'  => $page_id,
            'module'   => $module,
            'block_id' => $block_id,
        ), 200);
    }

    public function upload_media($request) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $files = $request->get_file_params();
        if (!isset($files['file']) || empty($files['file']['size'])) {
            return new WP_Error('no_file', 'NO_FILE', array('status' => 400));
        }

        $attachment_id = media_handle_upload('file', 0);
        if (is_wp_error($attachment_id)) {
            return new WP_Error('upload_failed', $attachment_id->get_error_message(), array('status' => 500));
        }

        return new WP_REST_Response(array(
            'id'  => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
        ), 201);
    }

    public function get_page_blocks($request) {
        $page_id = intval($request->get_param('page_id'));
        if (!$page_id) {
            return new WP_Error('bad_request', 'page_id requis.', array('status' => 400));
        }

        $result = HWC_Block_Editor::get_page_blocks($page_id);
        if (!$result['success']) {
            return new WP_Error('not_found', $result['message'], array('status' => 404));
        }

        return new WP_REST_Response($result, 200);
    }

    public function update_block_content($request) {
        $params = $request->get_params();

        $page_id = intval($params['page_id'] ?? 0);
        $block_index = intval($params['block_index'] ?? -1);
        $new_content = isset($params['new_content']) ? wp_kses_post($params['new_content']) : '';

        if (!$page_id || $block_index < 0) {
            return new WP_Error('bad_request', 'page_id et block_index requis.', array('status' => 400));
        }

        $result = HWC_Block_Editor::update_block_content($page_id, $block_index, $new_content);
        if (!$result['success']) {
            $status = strpos($result['message'], 'introuvable') !== false ? 404 : 400;
            return new WP_Error('update_failed', $result['message'], array('status' => $status));
        }

        return new WP_REST_Response($result, 200);
    }

    public function create_block($request) {
        $params = $request->get_params();

        $page_id = intval($params['page_id'] ?? 0);
        $block_name = isset($params['block_name']) ? sanitize_text_field($params['block_name']) : '';
        $content = isset($params['content']) ? $params['content'] : '';
        $insert_after_index = isset($params['insert_after_index']) ? intval($params['insert_after_index']) : null;
        $insert_before_index = isset($params['insert_before_index']) ? intval($params['insert_before_index']) : null;

        if (!$page_id || empty($block_name)) {
            return new WP_Error('bad_request', 'page_id et block_name requis.', array('status' => 400));
        }

        $result = HWC_Block_Editor::create_block($page_id, $block_name, $content, $insert_after_index, $insert_before_index);
        if (!$result['success']) {
            return new WP_Error('create_failed', $result['message'], array('status' => 400));
        }

        return new WP_REST_Response($result, 201);
    }

    public function delete_block($request) {
        $params = $request->get_params();

        $page_id = intval($params['page_id'] ?? 0);
        $block_index = intval($params['block_index'] ?? -1);

        if (!$page_id || $block_index < 0) {
            return new WP_Error('bad_request', 'page_id et block_index requis.', array('status' => 400));
        }

        $result = HWC_Block_Editor::delete_block($page_id, $block_index);
        if (!$result['success']) {
            return new WP_Error('delete_failed', $result['message'], array('status' => 404));
        }

        return new WP_REST_Response($result, 200);
    }
}
