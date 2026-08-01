<?php
defined('ABSPATH') || exit;

class HWC_REST_API {

    const RATE_LIMIT_WINDOW = 60;
    const RATE_LIMIT_MAX    = 10;

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

        register_rest_route('houetor/v1', '/blocks/batch-update', array(
            'methods'             => 'POST',
            'callback'            => array($this, 'batch_update_blocks'),
            'permission_callback' => array($this, 'check_token'),
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

    /**
     * Rate limiting des écritures : 10 écritures / 60s / page.
     * Lecture seule (get_page_blocks) exemptée.
     */
    private function check_rate_limit($page_id) {
        $key  = 'hwc_ratelimit_' . $page_id;
        $data = get_transient($key);
        $now  = time();

        if (!$data || !is_array($data)) {
            set_transient($key, array('count' => 1, 'first' => $now), self::RATE_LIMIT_WINDOW);
            return true;
        }

        if ($now - $data['first'] >= self::RATE_LIMIT_WINDOW) {
            set_transient($key, array('count' => 1, 'first' => $now), self::RATE_LIMIT_WINDOW);
            return true;
        }

        if ($data['count'] >= self::RATE_LIMIT_MAX) {
            return false;
        }

        $data['count']++;
        set_transient($key, $data, self::RATE_LIMIT_WINDOW);
        return true;
    }

    /**
     * Lecture du paramètre dry_run (booléen acceptant true/1/"1"/"true").
     * En dry_run : aucune écriture, aucune révision, aucun audit, aucun rate limit consommé.
     */
    private static function dry_run($params) {
        if (!isset($params['dry_run'])) {
            return false;
        }
        return filter_var($params['dry_run'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Journal d'audit : table {$wpdb->prefix}houetor_connect_actions_log.
     * Créée à l'activation du plugin (voir hwc_activate).
     */
    public static function audit_log($action_type, $before, $after) {
        global $wpdb;
        $table = $wpdb->prefix . 'houetor_connect_actions_log';
        // Table absente (plugin jamais ré-activé) : on la crée à la volée.
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            if (function_exists('hwc_create_audit_table')) {
                hwc_create_audit_table();
            }
        }
        $wpdb->insert($table, array(
            'action_type' => sanitize_text_field($action_type),
            'before_json' => wp_json_encode($before),
            'after_json'  => wp_json_encode($after),
            'created_at'  => current_time('mysql'),
        ));
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
        $expected_hash = isset($params['expected_hash']) ? sanitize_text_field($params['expected_hash']) : null;
        $dry_run = self::dry_run($params);

        if ($page_id === 0 || empty($content)) {
            return new WP_Error('bad_request', 'page_id et contenu requis.', array('status' => 400));
        }

        if (empty($module)) {
            return new WP_Error('bad_request', 'module requis.', array('status' => 400));
        }

        if (!$dry_run && !$this->check_rate_limit($page_id)) {
            return new WP_Error('rate_limited', 'Trop d\'écritures sur cette page (10/60s max). Réessayez plus tard.', array('status' => 429));
        }

        $post = get_post($page_id);
        if (!$post) {
            return new WP_Error('not_found', 'Page introuvable.', array('status' => 404));
        }

        $current_content = $post->post_content;

        // CAS : vérification que le contenu n'a pas changé depuis la lecture
        if ($expected_hash !== null && $expected_hash !== '' && md5($current_content) !== $expected_hash) {
            return new WP_Error('error_conflict', 'Conflit de concurrence : le contenu de la page a changé depuis la lecture. Relancez get_page_blocks / /pages et repassez le expected_hash à jour.', array('status' => 409));
        }

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

        // Filet de sécurité : révision AVANT toute écriture (sautée en dry_run)
        if (!$dry_run) {
            wp_save_post_revision($page_id);
        }

        if ($dry_run) {
            return new WP_REST_Response(array(
                'success'  => true,
                'dry_run'  => true,
                'page_id'  => $page_id,
                'module'   => $module,
                'block_id' => $block_id,
                'message'  => 'DRY RUN (aucune écriture) : contenu prêt à être injecté avec les marqueurs HWC.',
            ), 200);
        }

        $updated = wp_update_post(array(
            'ID'           => $page_id,
            'post_content' => $new_content,
        ), true);

        if (is_wp_error($updated)) {
            return new WP_Error('update_failed', 'Impossible de mettre à jour la page.', array('status' => 500));
        }

        self::audit_log('inject', array('page_id' => $page_id, 'content_md5' => md5($current_content)), array('page_id' => $page_id, 'content_md5' => md5($new_content)));

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
        $expected_hash = isset($params['expected_hash']) ? sanitize_text_field($params['expected_hash']) : null;
        $dry_run = self::dry_run($params);

        if ($page_id === 0 || empty($module) || empty($block_id)) {
            return new WP_Error('bad_request', 'page_id, module et block_id requis.', array('status' => 400));
        }

        if (!$dry_run && !$this->check_rate_limit($page_id)) {
            return new WP_Error('rate_limited', 'Trop d\'écritures sur cette page (10/60s max). Réessayez plus tard.', array('status' => 429));
        }

        $post = get_post($page_id);
        if (!$post) {
            return new WP_Error('not_found', 'Page introuvable.', array('status' => 404));
        }

        $current_content = $post->post_content;

        if ($expected_hash !== null && $expected_hash !== '' && md5($current_content) !== $expected_hash) {
            return new WP_Error('error_conflict', 'Conflit de concurrence : le contenu de la page a changé depuis la lecture. Relancez get_page_blocks et repassez le expected_hash à jour.', array('status' => 409));
        }

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

        if ($dry_run) {
            return new WP_REST_Response(array(
                'success'  => true,
                'dry_run'  => true,
                'page_id'  => $page_id,
                'module'   => $module,
                'block_id' => $block_id,
                'message'  => 'DRY RUN (aucune écriture) : bloc prêt à être retiré.',
            ), 200);
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post(array(
            'ID'           => $page_id,
            'post_content' => $new_content,
        ), true);

        if (is_wp_error($updated)) {
            return new WP_Error('update_failed', 'Impossible de mettre à jour la page.', array('status' => 500));
        }

        self::audit_log('uninject', array('page_id' => $page_id, 'module' => $module, 'block_id' => $block_id, 'content_md5' => md5($current_content)), array('page_id' => $page_id, 'content_md5' => md5($new_content)));

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
        $block_index = isset($params['block_index']) ? intval($params['block_index']) : null;
        $ref = isset($params['ref']) ? sanitize_text_field($params['ref']) : null;
        $new_content = isset($params['new_content']) ? wp_kses_post($params['new_content']) : '';
        $expected_hash = isset($params['expected_hash']) ? sanitize_text_field($params['expected_hash']) : null;
        $dry_run = self::dry_run($params);

        if (!$page_id || ($block_index === null && !$ref)) {
            return new WP_Error('bad_request', 'page_id et (block_index ou ref) requis.', array('status' => 400));
        }

        if (!$dry_run && !$this->check_rate_limit($page_id)) {
            return new WP_Error('rate_limited', 'Trop d\'écritures sur cette page (10/60s max). Réessayez plus tard.', array('status' => 429));
        }

        $before = get_post($page_id);
        $before_md5 = $before ? md5($before->post_content) : '';

        $result = HWC_Block_Editor::update_block_content($page_id, $block_index, $new_content, $ref, $expected_hash, $dry_run);
        if (!$result['success']) {
            if (isset($result['error']) && $result['error'] === 'conflict') {
                return new WP_Error('error_conflict', $result['message'], array('status' => 409));
            }
            $status = strpos($result['message'], 'introuvable') !== false ? 404 : 400;
            return new WP_Error('update_failed', $result['message'], array('status' => $status));
        }

        if (!$dry_run) {
            $after = get_post($page_id);
            self::audit_log('update_block', array('page_id' => $page_id, 'block_index' => $block_index, 'ref' => $ref, 'content_md5' => $before_md5), array('page_id' => $page_id, 'content_md5' => $after ? md5($after->post_content) : ''));
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
        $expected_hash = isset($params['expected_hash']) ? sanitize_text_field($params['expected_hash']) : null;

        // Spec 3.3 : position "start" | "end" | "before" | "after" + anchor_ref/anchor_index
        $position    = isset($params['position']) ? sanitize_text_field($params['position']) : '';
        $anchor_ref  = isset($params['anchor_ref']) ? sanitize_text_field($params['anchor_ref']) : null;
        $anchor_index = isset($params['anchor_index']) ? intval($params['anchor_index']) : null;
        $module      = isset($params['module']) ? sanitize_text_field($params['module']) : '';
        $dry_run = self::dry_run($params);

        if (!$page_id || empty($block_name)) {
            return new WP_Error('bad_request', 'page_id et block_name requis.', array('status' => 400));
        }

        // Résolution position/anchor -> insert_after_index / insert_before_index
        if (!empty($position)) {
            if (!in_array($position, array('start', 'end', 'before', 'after'), true)) {
                return new WP_Error('bad_request', 'position invalide (start|end|before|after).', array('status' => 400));
            }
            if (in_array($position, array('before', 'after'), true)) {
                if (!$anchor_ref && $anchor_index === null) {
                    return new WP_Error('bad_request', "position $position requiert anchor_ref ou anchor_index.", array('status' => 400));
                }
                if ($anchor_ref !== null) {
                    $anchor_index = HWC_Block_Editor::find_block_index_by_ref($page_id, $anchor_ref);
                    if ($anchor_index === null) {
                        // Erreur explicite — JAMAIS de fallback silencieux vers append
                        return new WP_Error('anchor_not_found', "Aucun bloc avec la ref \"$anchor_ref\" trouvé sur la page $page_id.", array('status' => 404));
                    }
                }
                if ($position === 'after') {
                    $insert_after_index = $anchor_index;
                } else {
                    $insert_before_index = $anchor_index;
                }
            } elseif ($position === 'start') {
                $insert_before_index = 0;
            }
            // "end" => aucun index => append (comportement par défaut)
        }

        if (!$dry_run && !$this->check_rate_limit($page_id)) {
            return new WP_Error('rate_limited', 'Trop d\'écritures sur cette page (10/60s max). Réessayez plus tard.', array('status' => 429));
        }

        $before = get_post($page_id);
        $before_md5 = $before ? md5($before->post_content) : '';

        $result = HWC_Block_Editor::create_block($page_id, $block_name, $content, $insert_after_index, $insert_before_index, $module, $expected_hash, $dry_run);
        if (!$result['success']) {
            if (isset($result['error']) && $result['error'] === 'conflict') {
                return new WP_Error('error_conflict', $result['message'], array('status' => 409));
            }
            return new WP_Error('create_failed', $result['message'], array('status' => 400));
        }

        if (!$dry_run) {
            $after = get_post($page_id);
            self::audit_log('create_block', array('page_id' => $page_id, 'block_name' => $block_name, 'position' => $position, 'anchor_ref' => $anchor_ref, 'content_md5' => $before_md5), array('page_id' => $page_id, 'ref' => $result['ref'], 'content_md5' => $after ? md5($after->post_content) : ''));
        }

        return new WP_REST_Response($result, 201);
    }

    public function delete_block($request) {
        $params = $request->get_params();

        $page_id = intval($params['page_id'] ?? 0);
        $block_index = isset($params['block_index']) ? intval($params['block_index']) : null;
        $ref = isset($params['ref']) ? sanitize_text_field($params['ref']) : null;
        $expected_hash = isset($params['expected_hash']) ? sanitize_text_field($params['expected_hash']) : null;
        $dry_run = self::dry_run($params);

        if (!$page_id || ($block_index === null && !$ref)) {
            return new WP_Error('bad_request', 'page_id et (block_index ou ref) requis.', array('status' => 400));
        }

        if (!$dry_run && !$this->check_rate_limit($page_id)) {
            return new WP_Error('rate_limited', 'Trop d\'écritures sur cette page (10/60s max). Réessayez plus tard.', array('status' => 429));
        }

        $before = get_post($page_id);
        $before_md5 = $before ? md5($before->post_content) : '';

        $result = HWC_Block_Editor::delete_block($page_id, $block_index, $ref, $expected_hash, $dry_run);
        if (!$result['success']) {
            if (isset($result['error']) && $result['error'] === 'conflict') {
                return new WP_Error('error_conflict', $result['message'], array('status' => 409));
            }
            return new WP_Error('delete_failed', $result['message'], array('status' => 404));
        }

        if (!$dry_run) {
            $after = get_post($page_id);
            self::audit_log('delete_block', array('page_id' => $page_id, 'block_index' => $block_index, 'ref' => $ref, 'content_md5' => $before_md5), array('page_id' => $page_id, 'content_md5' => $after ? md5($after->post_content) : ''));
        }

        return new WP_REST_Response($result, 200);
    }

    public function batch_update_blocks($request) {
        $params = $request->get_params();

        $page_id = intval($params['page_id'] ?? 0);
        $updates = isset($params['updates']) ? $params['updates'] : null;
        $expected_hash = isset($params['expected_hash']) ? sanitize_text_field($params['expected_hash']) : null;
        $dry_run = self::dry_run($params);

        if (!$page_id || !is_array($updates)) {
            return new WP_Error('bad_request', 'page_id et updates (tableau) requis.', array('status' => 400));
        }

        // Le batch = UNE écriture rate limit (all-or-nothing), jamais consommée en dry_run.
        if (!$dry_run && !$this->check_rate_limit($page_id)) {
            return new WP_Error('rate_limited', 'Trop d\'écritures sur cette page (10/60s max). Réessayez plus tard.', array('status' => 429));
        }

        $before = get_post($page_id);
        $before_md5 = $before ? md5($before->post_content) : '';

        $result = HWC_Block_Editor::batch_update_blocks($page_id, $updates, $expected_hash, $dry_run);
        if (!$result['success']) {
            if (isset($result['error']) && $result['error'] === 'conflict') {
                return new WP_Error('error_conflict', $result['message'], array('status' => 409));
            }
            $status = strpos($result['message'], 'introuvable') !== false ? 404 : 400;
            return new WP_Error('batch_failed', $result['message'], array('status' => $status));
        }

        if (!$dry_run) {
            $after = get_post($page_id);
            self::audit_log('batch_update_blocks', array('page_id' => $page_id, 'count' => count($updates), 'content_md5' => $before_md5), array('page_id' => $page_id, 'count' => $result['count'], 'content_md5' => $after ? md5($after->post_content) : ''));
        }

        return new WP_REST_Response($result, 200);
    }
}
