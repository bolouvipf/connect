<?php
defined('ABSPATH') || exit;

class Houetor_SelfHare_Dispatch {

    const RATE_LIMIT_WRITES = 10;
    const RATE_LIMIT_WINDOW = 60;
    const PREVIEW_TOKEN_TTL = 600;

    const ALLOWED_ACTIONS = [
        'create_posts' => true,
        'update_posts' => true,
        'delete_posts' => true,
        'create_pages' => true,
        'update_pages' => true,
        'delete_pages' => true,
        'create_products' => true,
        'update_products' => true,
        'delete_products' => true,
        'get_wp_pages' => true,
        'inject_page' => true,
        'delete_block' => true,
        'revert_to_revision' => true,
        'get_page_blocks' => true,
        'update_block_content' => true,
        'get_page_history' => true,
    ];

    const REF_PREFIX = 'sh_blk_';

    private static function is_write_action($name) {
        $write_actions = [
            'revert_to_revision', 'create_content', 'update_content', 'delete_content',
            'inject_page', 'delete_block', 'update_block_content',
        ];
        if (in_array($name, $write_actions, true)) return true;
        $prefix = explode('_', $name, 2)[0] ?? '';
        return in_array($prefix, ['create', 'update', 'delete'], true);
    }

    public static function is_read_action($name) {
        return isset(self::ALLOWED_ACTIONS[$name]) && !self::is_write_action($name);
    }

    private static function preview_fingerprint($name, $params) {
        return md5(wp_json_encode(['name' => $name, 'params' => $params]));
    }

    private static function generate_inject_ref() {
        $seed = uniqid('sh_inject_ref_', true) . '|' . wp_rand() . '|' . microtime(true);
        return self::REF_PREFIX . substr(wp_hash($seed, 'auth'), 0, 9);
    }

    private static function get_latest_revision_id($post_id) {
        $revisions = wp_get_post_revisions($post_id, [
            'posts_per_page' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);
        return is_array($revisions) && !empty($revisions) ? (int) key($revisions) : 0;
    }

    private static function replace_injection_by_ref($content, $ref, $html) {
        $pattern = '/<!--\s*sh:ref:' . preg_quote($ref, '/') . '\s*-->.*?<!--\s*\/sh:ref:' . preg_quote($ref, '/') . '\s*-->/s';
        if (!preg_match($pattern, $content)) {
            return null;
        }
        return preg_replace($pattern, '<!-- sh:ref:' . $ref . ' -->' . $html . '<!-- /sh:ref:' . $ref . ' -->', $content);
    }

    private static function cas_write($post_id, $expected_content, $new_content, $before_revision_id) {
        global $wpdb;

        if (post_type_supports(get_post_type($post_id), 'revisions')) {
            wp_save_post_revision($post_id);
        }

        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
            $post_id
        ));

        if ((string) $current !== (string) $expected_content) {
            return ['success' => false, 'message' => 'Conflit d\'édition : contenu modifié entre temps.', 'error' => 'edit_conflict', 'page_id' => $post_id];
        }

        if ((string) $new_content === (string) $expected_content) {
            return ['success' => true, 'page_id' => $post_id, 'message' => 'Aucun changement détecté.', 'before_revision_id' => $before_revision_id, 'after_revision_id' => $before_revision_id, 'ref' => ''];
        }

        get_post($post_id);

        $swapped = $wpdb->update(
            $wpdb->posts,
            ['post_content' => $new_content],
            ['ID' => $post_id, 'post_content' => $expected_content],
            ['%s'],
            ['%d', '%s']
        );

        if ($swapped === false) {
            return ['success' => false, 'message' => 'Erreur base de données.'];
        }

        if ($swapped === 0) {
            return ['success' => false, 'message' => 'Conflit d\'édition : contenu modifié pendant l\'écriture.', 'error' => 'edit_conflict', 'page_id' => $post_id];
        }

        $updated = wp_update_post([
            'ID' => $post_id,
            'post_content' => wp_slash($new_content),
        ], true);

        if (is_wp_error($updated)) {
            $wpdb->update(
                $wpdb->posts,
                ['post_content' => $expected_content],
                ['ID' => $post_id, 'post_content' => $new_content],
                ['%s'],
                ['%d', '%s']
            );
            clean_post_cache($post_id);
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        $after_revision_id = self::get_latest_revision_id($post_id);

        return ['success' => true, 'page_id' => $post_id, 'before_revision_id' => $before_revision_id, 'after_revision_id' => $after_revision_id];
    }

    public static function execute($tool_call, $manifest_schema) {
        if (!$tool_call || empty($tool_call['name'])) {
            return ['success' => false, 'message' => 'Aucune action à exécuter.'];
        }

        $name = sanitize_text_field($tool_call['name']);
        $params = isset($tool_call['params']) && is_array($tool_call['params']) ? $tool_call['params'] : [];

        if (!isset(self::ALLOWED_ACTIONS[$name])) {
            return ['success' => false, 'message' => "Action '$name' non autorisée."];
        }

        if (!self::is_in_manifest($name, $manifest_schema)) {
            return ['success' => false, 'message' => "Action '$name' non listée dans le manifest_schema actuel."];
        }

        $internal = !empty($tool_call['internal']);
        $is_write = self::is_write_action($name);

        if ($is_write && !$internal) {
            $token = isset($tool_call['preview_token']) ? sanitize_text_field($tool_call['preview_token']) : (isset($params['preview_token']) ? sanitize_text_field($params['preview_token']) : '');
            unset($params['preview_token']);
            $expected = self::preview_fingerprint($name, $params);
            if (empty($token) || get_transient('sh_preview_' . $token) !== $expected) {
                return ['success' => false, 'message' => 'Aperçu requis avant exécution de cette action.', 'error' => 'preview_required'];
            }
            delete_transient('sh_preview_' . $token);
        } elseif ($is_write && $internal) {
            if ($name !== 'create_content') {
                return ['success' => false, 'message' => 'Cette écriture nécessite un aperçu et une confirmation humaine.', 'error' => 'preview_required'];
            }
        }

        if (isset($params['expected_hash'])) {
            $expected_hash = sanitize_text_field($params['expected_hash']);
            unset($params['expected_hash']);
            $post_id = isset($params['post_id']) ? intval($params['post_id']) : (isset($params['id']) ? intval($params['id']) : (isset($params['page_id']) ? intval($params['page_id']) : 0));
            $post = $post_id ? get_post($post_id) : null;
            if ($post && md5($post->post_content) !== $expected_hash) {
                return ['success' => false, 'message' => "Conflit d'édition : le contenu a été modifié entre temps.", 'error' => 'edit_conflict', 'page_id' => $post_id];
            }
        }

        if (!empty($params['dry_run'])) {
            unset($params['dry_run']);
            $dry = self::compute_preview($name, $params);
            $dry['dry_run'] = true;
            return $dry;
        }

        $rate_check = self::check_rate_limit($name, $params);
        if ($rate_check !== true) {
            return $rate_check;
        }

        $before = self::capture_state($name, $params);
        $result = self::route($name, $params);
        $after = self::capture_state($name, $params);

        $result = Houetor_SelfHare_Error_Translator::enrich_result($result);

        if ($is_write) {
            self::log_action($name, $before, $after);
        }

        return $result;
    }

    private static function route($name, $params) {
        if (method_exists(__CLASS__, $name)) {
            return self::$name($params);
        }

        $prefix = explode('_', $name, 2)[0] ?? '';
        $type = explode('_', $name, 2)[1] ?? '';

        if (in_array($prefix, ['create', 'update', 'delete'], true) && !empty($type)) {
            $method = $prefix . '_content';
            if (method_exists(__CLASS__, $method)) {
                $merged = array_merge($params, ['post_type' => $type]);
                return self::$method($merged);
            }
        }

        return ['success' => false, 'message' => "Action '$name' non implémentée."];
    }

    public static function preview($tool_call, $manifest_schema) {
        if (!$tool_call || empty($tool_call['name'])) {
            return ['success' => false, 'message' => 'Aucune action à prévisualiser.'];
        }

        $name = sanitize_text_field($tool_call['name']);
        $params = isset($tool_call['params']) && is_array($tool_call['params']) ? $tool_call['params'] : [];

        if (!isset(self::ALLOWED_ACTIONS[$name])) {
            return ['success' => false, 'message' => "Action '$name' non autorisée."];
        }

        if (!self::is_in_manifest($name, $manifest_schema)) {
            return ['success' => false, 'message' => "Action '$name' non listée dans le manifest_schema actuel."];
        }

        $result = self::compute_preview($name, $params);
        if (!$result['success']) {
            return $result;
        }

        $result['preview_token'] = wp_generate_password(20, false);
        set_transient('sh_preview_' . $result['preview_token'], self::preview_fingerprint($name, $params), self::PREVIEW_TOKEN_TTL);

        $post_id = isset($params['post_id']) ? intval($params['post_id']) : (isset($params['id']) ? intval($params['id']) : (isset($params['page_id']) ? intval($params['page_id']) : 0));
        if ($post_id && ($post = get_post($post_id))) {
            $result['expected_hash'] = md5($post->post_content);
        }

        return $result;
    }

    private static function is_in_manifest($action, $manifest) {
        $map = [
            'get_wp_pages'         => ['pages'],
            'inject_page'          => ['pages'],
            'delete_block'         => ['pages'],
            'revert_to_revision'   => ['posts', 'pages'],
            'get_page_blocks'      => ['pages'],
            'update_block_content' => ['pages'],
            'get_page_history'     => ['posts', 'pages'],
        ];

        if (isset($map[$action])) {
            foreach ($map[$action] as $table) {
                if (isset($manifest[$table])) return true;
            }
            return false;
        }

        $prefix = explode('_', $action, 2)[0] ?? '';
        $type = explode('_', $action, 2)[1] ?? '';

        if (in_array($prefix, ['create', 'update', 'delete'], true) && !empty($type)) {
            return isset($manifest[$type]);
        }

        return false;
    }

    private static function compute_preview($action, $params) {
        $prefix = explode('_', $action, 2)[0] ?? '';
        $type   = explode('_', $action, 2)[1] ?? '';
        if (in_array($prefix, ['create', 'update', 'delete'], true) && !empty($type)) {
            $params['post_type'] = $type;
            $action = $prefix . '_content';
        }

        switch ($action) {
            case 'create_content':
                $title = isset($params['post_title']) ? sanitize_text_field($params['post_title']) : '(sans titre)';
                $type = isset($params['post_type']) ? sanitize_text_field($params['post_type']) : 'post';
                $extra = [];
                if (isset($params['price']) && $params['price'] !== '') $extra[] = 'prix ' . sanitize_text_field($params['price']);
                if (isset($params['stock_quantity']) && $params['stock_quantity'] !== '') $extra[] = 'stock ' . intval($params['stock_quantity']);
                $summary = "Créer un $type intitulé « $title » (statut : brouillon).";
                if ($extra) $summary .= ' (' . implode(', ', $extra) . ')';
                return [
                    'success' => true,
                    'summary' => $summary,
                    'before' => null,
                    'after' => [
                        'post_title' => $title,
                        'post_type' => $type,
                        'post_status' => 'draft',
                    ],
                ];

            case 'update_content':
                $post_id = isset($params['post_id']) ? intval($params['post_id']) : (isset($params['id']) ? intval($params['id']) : 0);
                $post = get_post($post_id, ARRAY_A);
                if (!$post) {
                    return ['success' => false, 'message' => 'Contenu introuvable.'];
                }

                if (isset($params['find_text']) && isset($params['replace_text']) && strlen($params['find_text']) > 0) {
                    $new_content = str_replace($params['find_text'], $params['replace_text'], $post['post_content']);
                    if ($new_content === $post['post_content']) {
                        return ['success' => false, 'message' => 'Le texte "' . $params['find_text'] . '" est introuvable dans le contenu actuel de cette page.'];
                    }
                    $params['post_content'] = $new_content;
                }

                $changed = [];
                if (isset($params['post_title'])) $changed[] = 'titre';
                if (isset($params['post_content'])) $changed[] = 'contenu';
                if (isset($params['post_status'])) $changed[] = 'statut';
                $summary = 'Modifier la ' . ($post['post_type'] === 'page' ? 'page' : 'publication') . " « {$post['post_title']} » : " . implode(', ', $changed) . '.';
                $after = $post;
                if (isset($params['post_title'])) $after['post_title'] = sanitize_text_field($params['post_title']);
                if (isset($params['post_content'])) $after['post_content'] = wp_kses_post($params['post_content']);
                if (isset($params['post_status'])) $after['post_status'] = sanitize_text_field($params['post_status']);

                $old_len = strlen(wp_strip_all_tags($post['post_content']));
                $new_len = strlen(wp_strip_all_tags($after['post_content']));
                if ($old_len > 100 && $new_len > 0 && $new_len < $old_len * 0.3) {
                    $summary .= " ⚠️ Action risquée : le contenu passe de $old_len à $new_len caractères (perte de " . round((1 - $new_len / $old_len) * 100) . "%). Vérifiez avant de confirmer.";
                }

                return [
                    'success' => true,
                    'summary' => $summary,
                    'before' => [
                        'post_title' => $post['post_title'],
                        'post_content' => mb_substr(wp_strip_all_tags($post['post_content']), 0, 200),
                        'post_status' => $post['post_status'],
                    ],
                    'after' => [
                        'post_title' => $after['post_title'],
                        'post_content' => mb_substr(wp_strip_all_tags($after['post_content']), 0, 200),
                        'post_status' => $after['post_status'],
                    ],
                ];

            case 'delete_content':
                $post_id = isset($params['post_id']) ? intval($params['post_id']) : (isset($params['id']) ? intval($params['id']) : 0);
                $post = get_post($post_id, ARRAY_A);
                if (!$post) {
                    return ['success' => false, 'message' => 'Contenu introuvable.'];
                }
                return [
                    'success' => true,
                    'summary' => "Déplacer « {$post['post_title']} » dans la corbeille (action réversible).",
                    'before' => [
                        'post_title' => $post['post_title'],
                        'post_status' => $post['post_status'],
                    ],
                    'after' => ['post_status' => 'trash'],
                ];

            case 'get_wp_pages':
                $count = count(get_pages());
                return [
                    'success' => true,
                    'summary' => "Lister les $count pages du site (lecture seule, aucune modification).",
                    'before' => null,
                    'after' => null,
                ];

            case 'inject_page':
                $page_id = intval($params['page_id'] ?? 0);
                $html = wp_kses_post($params['html'] ?? '');
                $block_html = Houetor_SelfHare_HTML_Transformer::to_blocks($html);
                $position = sanitize_text_field($params['position'] ?? 'remplacement');
                $ref = isset($params['ref']) ? sanitize_text_field($params['ref']) : '';
                $post = get_post($page_id, ARRAY_A);
                if (!$post) {
                    return ['success' => false, 'message' => 'Page introuvable.'];
                }
                $position_label = ['header' => 'en haut', 'footer' => 'en bas', 'remplacement' => 'en remplacement complet'];
                $label = $position_label[$position] ?? $position;
                $ref_note = $ref ? " (ref: `$ref`, remplace l'injection existante)" : ' (nouvelle ref générée)';
                $has_transform = $html !== $block_html;
                return [
                    'success' => true,
                    'summary' => "Injecter du contenu $label dans la page « {$post['post_title']} »$ref_note" . ($has_transform ? ' (converti en blocs Gutenberg).' : '.'),
                    'before' => [
                        'post_title' => $post['post_title'],
                        'post_content' => mb_substr(wp_strip_all_tags($post['post_content']), 0, 200),
                    ],
                    'after' => [
                        'post_title' => $post['post_title'],
                        'post_content' => mb_substr(wp_strip_all_tags($block_html), 0, 200) . ($position !== 'remplacement' ? ' (précédé/suivi du contenu existant)' : ''),
                    ],
                ];

            case 'delete_block':
                $page_id = intval($params['page_id'] ?? 0);
                $ref = isset($params['ref']) ? sanitize_text_field($params['ref']) : '';
                $post = get_post($page_id, ARRAY_A);
                if (!$post) {
                    return ['success' => false, 'message' => 'Page introuvable.'];
                }
                return [
                    'success' => true,
                    'summary' => "Supprimer le bloc `$ref` de la page « {$post['post_title']} ».",
                    'before' => [
                        'post_title' => $post['post_title'],
                        'post_content' => '(contient le bloc)',
                    ],
                    'after' => [
                        'post_title' => $post['post_title'],
                        'post_content' => '(bloc supprimé)',
                    ],
                ];

            case 'revert_to_revision':
                $revision_id = isset($params['revision_id']) ? intval($params['revision_id']) : 0;
                $post_id = isset($params['post_id']) ? intval($params['post_id']) : 0;
                $revision = get_post($revision_id);
                if (!$revision || !$post_id) {
                    return ['success' => false, 'message' => 'Révision introuvable.'];
                }
                return [
                    'success' => true,
                    'summary' => "Restaurer la révision #$revision_id. Action réversible.",
                    'before' => null,
                    'after' => null,
                ];

            case 'get_page_blocks':
                $page_id = intval($params['page_id'] ?? 0);
                $post = get_post($page_id, ARRAY_A);
                if (!$post) return ['success' => false, 'message' => 'Page introuvable.'];
                return [
                    'success' => true,
                    'summary' => "Lire les blocs de « {$post['post_title']} » (lecture seule).",
                    'before' => null,
                    'after' => null,
                ];

            case 'update_block_content':
                $page_id = intval($params['page_id'] ?? 0);
                $block_index = intval($params['block_index'] ?? -1);
                $new_content = isset($params['new_content']) ? $params['new_content'] : '';
                $post = get_post($page_id, ARRAY_A);
                if (!$post) return ['success' => false, 'message' => 'Page introuvable.'];
                $parsed = parse_blocks($post['post_content']);
                $old_block_text = '';
                $actual = 0;
                foreach ($parsed as $b) {
                    if (!empty($b['blockName'])) {
                        if ($actual === $block_index) {
                            $old_block_text = self::extract_block_text($b);
                            break;
                        }
                        $actual++;
                    }
                }
                $old_len = strlen($old_block_text);
                $new_len = strlen(wp_strip_all_tags($new_content));
                $summary = "Modifier le bloc #$block_index de la page « {$post['post_title']} ».";
                if ($old_len > 50 && $new_len > 0 && $new_len < $old_len * 0.3) {
                    $summary .= " ⚠️ Action risquée : le bloc passe de $old_len à $new_len caractères (perte de " . round((1 - $new_len / $old_len) * 100) . "%). Vérifiez avant de confirmer.";
                }
                return [
                    'success' => true,
                    'summary' => $summary,
                    'before' => ['post_title' => $post['post_title'], 'block_index' => $block_index],
                    'after' => ['post_title' => $post['post_title'], 'block_index' => $block_index],
                ];

            default:
                return ['success' => false, 'message' => "Action '$action' inconnue."];
        }
    }

    private static function create_content($params) {
        $type = isset($params['post_type']) ? sanitize_text_field($params['post_type']) : 'post';
        $title = isset($params['post_title']) ? sanitize_text_field($params['post_title']) : '';
        $content = isset($params['post_content']) ? wp_kses_post($params['post_content']) : '';

        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => $type,
        ]);

        if (is_wp_error($post_id)) {
            return ['success' => false, 'message' => $post_id->get_error_message()];
        }

        if ($type === 'products' && (isset($params['price']) || isset($params['stock_quantity']))) {
            self::update_product_meta($post_id, $params);
        }

        return [
            'success' => true,
            'post_id' => $post_id,
            'edit_link' => get_edit_post_link($post_id, 'raw'),
            'message' => "« $title » créé (brouillon).",
        ];
    }

    private static function update_content($params) {
        $post_id = isset($params['post_id']) ? intval($params['post_id']) : (isset($params['id']) ? intval($params['id']) : 0);
        $post = $post_id ? get_post($post_id, ARRAY_A) : null;
        if (!$post) {
            return ['success' => false, 'message' => 'Contenu introuvable.'];
        }

        $find_text = isset($params['find_text']) ? strval($params['find_text']) : null;
        $replace_text = isset($params['replace_text']) ? strval($params['replace_text']) : null;
        unset($params['find_text'], $params['replace_text']);

        $new_content = $post['post_content'];
        if ($find_text !== null && $replace_text !== null && strlen($find_text) > 0) {
            if (strpos($new_content, $find_text) === false) {
                return ['success' => false, 'message' => 'Le texte "' . $find_text . '" est introuvable dans le contenu actuel.', 'error' => 'find_text_not_found', 'page_id' => $post_id];
            }
            $new_content = str_replace($find_text, $replace_text, $new_content);
        }

        if (isset($params['post_content'])) {
            $new_content = wp_kses_post($params['post_content']);
        }

        if ($new_content !== $post['post_content']) {
            $before_revision_id = self::get_latest_revision_id($post_id);
            $result = self::cas_write($post_id, $post['post_content'], $new_content, $before_revision_id);
            if (!$result['success']) {
                return $result;
            }
        }

        $update = ['ID' => $post_id];
        if (isset($params['post_title'])) $update['post_title'] = sanitize_text_field($params['post_title']);
        if (isset($params['post_status'])) $update['post_status'] = sanitize_text_field($params['post_status']);

        if (isset($update['post_title']) && $update['post_title'] === $post['post_title']) {
            unset($update['post_title']);
        }
        if (isset($update['post_status']) && $update['post_status'] === $post['post_status']) {
            unset($update['post_status']);
        }
        if (count($update) > 1) {
            $res = wp_update_post($update, true);
            if (is_wp_error($res)) {
                return ['success' => false, 'message' => $res->get_error_message()];
            }
        }

        if (isset($params['price']) || isset($params['stock_quantity'])) {
            self::update_product_meta($post_id, $params);
        }

        return ['success' => true, 'post_id' => $post_id, 'message' => 'Article mis à jour.'];
    }

    private static function update_product_meta($post_id, $params) {
        if (!class_exists('WooCommerce')) return;
        if (isset($params['price']) && $params['price'] !== '') {
            $price = sanitize_text_field($params['price']);
            update_post_meta($post_id, '_regular_price', function_exists('wc_format_decimal') ? wc_format_decimal($price) : $price);
        }
        if (isset($params['stock_quantity']) && $params['stock_quantity'] !== '') {
            $stock = intval($params['stock_quantity']);
            update_post_meta($post_id, '_manage_stock', 'yes');
            update_post_meta($post_id, '_stock', $stock);
            if (function_exists('wc_update_product_stock')) {
                wc_update_product_stock($post_id, $stock);
            }
        }
    }

    private static function delete_content($params) {
        $post_id = isset($params['post_id']) ? intval($params['post_id']) : (isset($params['id']) ? intval($params['id']) : 0);
        if (!$post_id || !get_post($post_id)) {
            return ['success' => false, 'message' => 'Contenu introuvable.'];
        }

        if (post_type_supports(get_post_type($post_id), 'revisions')) {
            wp_save_post_revision($post_id);
        }

        $trashed = wp_trash_post($post_id);
        if (!$trashed) {
            return ['success' => false, 'message' => 'Impossible de supprimer ce contenu.'];
        }

        return ['success' => true, 'post_id' => $post_id, 'message' => 'Contenu déplacé dans la corbeille.'];
    }

    private static function revert_to_revision($params) {
        $revision_id = isset($params['revision_id']) ? intval($params['revision_id']) : 0;
        $post_id = isset($params['post_id']) ? intval($params['post_id']) : 0;

        if (!$revision_id || !$post_id) {
            return ['success' => false, 'message' => 'ID de révision et ID de publication requis.'];
        }

        $revision = wp_get_post_revision($revision_id);
        if (!$revision) {
            return ['success' => false, 'message' => 'Révision introuvable.'];
        }

        $restored = wp_restore_post_revision($revision_id);
        if (!$restored) {
            return ['success' => false, 'message' => 'Impossible de restaurer cette révision.'];
        }

        return [
            'success' => true,
            'post_id' => $post_id,
            'revision_id' => $revision_id,
            'message' => "Révision #$revision_id restaurée.",
        ];
    }

    private static function check_rate_limit($name, $params) {
        if (!self::is_write_action($name)) {
            return true;
        }

        $post_id = isset($params['post_id']) ? intval($params['post_id']) : (isset($params['page_id']) ? intval($params['page_id']) : 0);
        if (!$post_id) {
            $uid = get_current_user_id();
            $transient_key = 'sh_rate_u_' . ($uid ? $uid : 'cli');
        } else {
            $transient_key = 'sh_rate_' . $post_id;
        }

        $window = get_transient($transient_key);
        if ($window === false) {
            set_transient($transient_key, 1, self::RATE_LIMIT_WINDOW);
            return true;
        }

        $count = (int) $window;
        if ($count >= self::RATE_LIMIT_WRITES) {
            $retry_after = get_option('_transient_timeout_' . $transient_key, time()) - time();
            return [
                'success' => false,
                'message' => 'Limite de taux atteinte. Réessaie dans ' . max(1, $retry_after) . ' secondes.',
                'error' => 'rate_limit_exceeded',
                'retry_after' => max(1, $retry_after),
                'page_id' => $post_id,
            ];
        }

        set_transient($transient_key, $count + 1, self::RATE_LIMIT_WINDOW);
        return true;
    }

    private static function get_wp_pages($params = []) {
        $pages = get_pages([
            'sort_column' => 'post_title',
            'sort_order' => 'ASC',
        ]);

        $result = [];
        foreach ($pages as $page) {
            $result[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'slug' => $page->post_name,
                'status' => $page->post_status,
                'edit_link' => get_edit_post_link($page->ID, 'raw'),
            ];
        }

        return ['success' => true, 'pages' => $result, 'count' => count($result)];
    }

    private static function inject_page($params) {
        $page_id = intval($params['page_id'] ?? 0);
        $html = wp_kses_post($params['html'] ?? '');
        $html = Houetor_SelfHare_HTML_Transformer::to_blocks($html);
        $position = sanitize_text_field($params['position'] ?? 'remplacement');
        $ref = isset($params['ref']) ? sanitize_text_field($params['ref']) : '';

        if (!$page_id || !($post = get_post($page_id))) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        $original_content = $post->post_content;
        $before_revision_id = self::get_latest_revision_id($page_id);

        if (!empty($ref)) {
            $new_content = self::replace_injection_by_ref($original_content, $ref, $html);
            if ($new_content !== null) {
                $result = self::cas_write($page_id, $original_content, $new_content, $before_revision_id);
                if ($result['success']) {
                    $result['ref'] = $ref;
                    $result['message'] = "Contenu injecté mis à jour via ref `$ref` dans « {$post->post_title} ».";
                }
                return $result;
            }
        }

        $new_ref = self::generate_inject_ref();
        $wrapped = '<!-- sh:ref:' . $new_ref . ' -->' . $html . '<!-- /sh:ref:' . $new_ref . ' -->';

        if ($position === 'header') {
            $new_content = $wrapped . $original_content;
        } elseif ($position === 'footer') {
            $new_content = $original_content . $wrapped;
        } else {
            $new_content = $wrapped;
        }

        $result = self::cas_write($page_id, $original_content, $new_content, $before_revision_id);
        if ($result['success']) {
            $result['ref'] = $new_ref;
            $action = $position === 'remplacement' ? 'remplacé' : "injecté en $position";
            $result['message'] = "Contenu $action (ref: `$new_ref`) dans « {$post->post_title} ».";
        }
        return $result;
    }

    private static function delete_block($params) {
        $page_id = intval($params['page_id'] ?? 0);
        $ref = isset($params['ref']) ? sanitize_text_field($params['ref']) : '';

        if (!$page_id || !($post = get_post($page_id))) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        if (empty($ref)) {
            return ['success' => false, 'message' => 'Référence de bloc manquante.'];
        }

        $original_content = $post->post_content;
        $before_revision_id = self::get_latest_revision_id($page_id);

        $pattern = '/<!--\s*sh:ref:' . preg_quote($ref, '/') . '\s*-->.*?<!--\s*\/sh:ref:' . preg_quote($ref, '/') . '\s*-->/s';
        if (!preg_match($pattern, $original_content)) {
            return ['success' => false, 'message' => "Bloc avec la référence `$ref` introuvable dans cette page."];
        }

        $new_content = preg_replace($pattern, '', $original_content);
        $result = self::cas_write($page_id, $original_content, $new_content, $before_revision_id);
        if ($result['success']) {
            $post_title = $post->post_title;
            $result['message'] = "Bloc `$ref` supprimé de « $post_title ».";
        }
        return $result;
    }

    private static function get_page_blocks($params) {
        $page_id = intval($params['page_id'] ?? 0);
        if (!$page_id || !($post = get_post($page_id))) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        $content = $post->post_content;
        if (empty(trim($content))) {
            return ['success' => false, 'message' => 'Le contenu de cette page est vide ou utilise un template. Les blocs ne sont pas stockés directement dans le champ contenu.', 'blocks' => [], 'count' => 0];
        }

        $blocks = parse_blocks($content);
        $result = [];
        $index = 0;
        foreach ($blocks as $block) {
            $block_name = $block['blockName'] ?? '';
            if (empty($block_name)) continue;

            $text = self::extract_block_text($block);
            $result[] = [
                'index' => $index,
                'blockName' => $block_name,
                'content' => $text,
            ];
            $index++;
        }

        return ['success' => true, 'blocks' => $result, 'count' => count($result)];
    }

    private static function get_page_history($params) {
        $post_id = intval($params['post_id'] ?? $params['page_id'] ?? 0);
        if (!$post_id || !get_post($post_id)) {
            return ['success' => false, 'message' => 'Page introuvable.'];
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
        return ['success' => true, 'post_id' => $post_id, 'revisions' => $result, 'count' => count($result)];
    }

    private static function extract_block_text($block) {
        $html = $block['innerHTML'] ?? '';
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($html)));

        if ($text === '' && !empty($block['attrs']['content']) && is_string($block['attrs']['content'])) {
            $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($block['attrs']['content'])));
        }

        return $text;
    }

    private static function update_block_content($params) {
        $page_id = intval($params['page_id'] ?? 0);
        $block_index = intval($params['block_index'] ?? -1);
        $new_text = isset($params['new_content']) ? wp_kses_post($params['new_content']) : '';

        if (!$page_id || !($post = get_post($page_id))) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        $content = $post->post_content;
        if (empty(trim($content))) {
            return ['success' => false, 'message' => 'Le contenu de cette page est vide ou utilise un template.'];
        }

        $blocks = parse_blocks($content);

        $actual_index = 0;
        $target_idx = null;
        foreach ($blocks as $idx => $block) {
            if (empty($block['blockName'])) continue;
            if ($actual_index === $block_index) {
                $target_idx = $idx;
                break;
            }
            $actual_index++;
        }

        if ($target_idx === null) {
            $total = 0;
            foreach ($blocks as $b) { if (!empty($b['blockName'])) $total++; }
            return ['success' => false, 'message' => "Bloc #$block_index introuvable (0-" . ($total - 1) . " disponible). Utilise get_page_blocks pour voir les indices valides."];
        }

        $block_name = $blocks[$target_idx]['blockName'];
        $old_html = $blocks[$target_idx]['innerHTML'];

        if (!empty($blocks[$target_idx]['innerBlocks'])) {
            return ['success' => false, 'message' => "Le bloc #$block_index ($block_name) contient des blocs imbriqués et ne peut pas être modifié directement."];
        }

        $old_html = trim($old_html);

        if (preg_match('/^<(\w+)/', $old_html, $m)) {
            $tag = $m[1];
            if (preg_match('/^<' . $tag . '([^>]*)>/', $old_html, $attr_m)) {
                $attr_str = $attr_m[1];
                $new_html = "<{$tag}{$attr_str}>" . $new_text . "</{$tag}>";
            } else {
                $new_html = "<{$tag}>" . $new_text . "</{$tag}>";
            }
        } else {
            $new_html = $new_text;
        }

        $blocks[$target_idx]['innerHTML'] = $new_html;
        foreach ($blocks[$target_idx]['innerContent'] as $ic => $chunk) {
            if (is_string($chunk)) {
                $blocks[$target_idx]['innerContent'][$ic] = $new_html;
            }
        }

        if ($block_name === 'core/heading' && isset($blocks[$target_idx]['attrs']['content'])) {
            $blocks[$target_idx]['attrs']['content'] = wp_strip_all_tags($new_text);
        }

        $new_post_content = serialize_blocks($blocks);

        $before_revision_id = self::get_latest_revision_id($page_id);
        $cas = self::cas_write($page_id, $post->post_content, $new_post_content, $before_revision_id);
        if (!$cas['success']) {
            return $cas;
        }

        $post_title = $post->post_title;
        return ['success' => true, 'post_id' => $page_id, 'message' => "Bloc #$block_index ($block_name) mis à jour dans « $post_title »."];
    }

    private static function capture_state($action, $params) {
        if ($action === 'get_wp_pages') return ['pages_count' => count(get_pages())];

        $post_id = isset($params['post_id']) ? intval($params['post_id']) : (isset($params['id']) ? intval($params['id']) : (isset($params['page_id']) ? intval($params['page_id']) : 0));
        if (!$post_id) return ['note' => 'Aucun ID fourni pour l\'état avant'];

        $post = get_post($post_id, ARRAY_A);
        return $post ? $post : ['note' => 'Impossible de capturer l\'état avant'];
    }

    private static function log_action($action_type, $before, $after) {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}houetor_selfhare_actions_log", [
            'action_type' => $action_type,
            'before_json' => wp_json_encode($before),
            'after_json' => wp_json_encode($after),
            'created_at' => current_time('mysql'),
        ]);
    }
}
