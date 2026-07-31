<?php
defined('ABSPATH') || exit;

class HWC_Block_Editor {

    const REF_PREFIX = 'sh_blk_';

    public static function get_page_blocks($page_id) {
        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'message' => 'Page introuvable.', 'blocks' => [], 'count' => 0];
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
                'index'     => $index,
                'blockName' => $block_name,
                'content'   => $text,
                'ref'       => self::extract_hwc_ref($block),
            ];
            $index++;
        }

        return [
            'success'     => true,
            'blocks'      => $result,
            'count'       => count($result),
            'content_md5' => md5($content),
        ];
    }

    /**
     * Extrait la ref HWC ({module}-{block_id}) si le bloc est enrobé des marqueurs
     * <!-- HWC ... start/end -->. Sinon null — l'agent utilisera index.
     */
    public static function extract_hwc_ref($block) {
        $html = $block['innerHTML'] ?? '';
        if (preg_match('/^<!-- HWC ([A-Za-z0-9_]+-[A-Za-z0-9_-]+) start -->/', trim($html), $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Vérification CAS : expected_hash (md5 du post_content au moment de la lecture)
     * doit correspondre au contenu actuel. Null = vérification désactivée.
     */
    public static function cas_check($post, $expected_hash) {
        if ($expected_hash === null || $expected_hash === '') {
            return true;
        }
        return md5($post->post_content) === $expected_hash;
    }

    /**
     * Localise l'index logique d'un bloc par ref. Retourne l'index ou null.
     */
    public static function find_block_index_by_ref($page_id, $ref) {
        $post = get_post($page_id);
        if (!$post) {
            return null;
        }
        $blocks = parse_blocks($post->post_content);
        $actual = 0;
        foreach ($blocks as $block) {
            if (empty($block['blockName'])) continue;
            if (self::extract_hwc_ref($block) === $ref) {
                return $actual;
            }
            $actual++;
        }
        return null;
    }

    public static function update_block_content($page_id, $block_index = null, $new_content = '', $ref = null, $expected_hash = null) {
        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        if (!self::cas_check($post, $expected_hash)) {
            return ['success' => false, 'error' => 'conflict', 'message' => 'Conflit de concurrence : le contenu de la page a changé depuis la lecture. Relancez get_page_blocks et repassez le expected_hash à jour.'];
        }

        $content = $post->post_content;
        if (empty(trim($content))) {
            return ['success' => false, 'message' => 'Le contenu de cette page est vide ou utilise un template.'];
        }

        $blocks = parse_blocks($content);

        $actual_index = 0;
        $target_idx = null;
        $target_ref = null;
        foreach ($blocks as $idx => $block) {
            if (empty($block['blockName'])) continue;
            if ($ref !== null) {
                $r = self::extract_hwc_ref($block);
                if ($r === $ref) {
                    $target_idx = $idx;
                    $target_ref = $r;
                    break;
                }
            } else {
                if ($actual_index === $block_index) {
                    $target_idx = $idx;
                    $target_ref = self::extract_hwc_ref($block);
                    break;
                }
            }
            $actual_index++;
        }

        if ($target_idx === null) {
            if ($ref !== null) {
                return ['success' => false, 'message' => "Aucun bloc avec la ref \"$ref\" trouvé sur la page $page_id."];
            }
            $total = 0;
            foreach ($blocks as $b) { if (!empty($b['blockName'])) $total++; }
            return ['success' => false, 'message' => "Bloc #$block_index introuvable (0-" . ($total - 1) . " disponible). Utilise get_page_blocks pour voir les indices valides."];
        }

        $block_name = $blocks[$target_idx]['blockName'];

        if (!empty($blocks[$target_idx]['innerBlocks'])) {
            return ['success' => false, 'message' => "Le bloc #$actual_index ($block_name) contient des blocs imbriqués et ne peut pas être modifié directement."];
        }

        // Extraire les marqueurs HWC s'ils enrobent le bloc (à préserver)
        $marker_start = '';
        $marker_end = '';
        $old_html = trim($blocks[$target_idx]['innerHTML']);

        if ($target_ref !== null) {
            $marker_start = '<!-- HWC ' . $target_ref . ' start -->';
            $marker_end = '<!-- HWC ' . $target_ref . ' end -->';
            $old_html = preg_replace('/^<!-- HWC [A-Za-z0-9_]+-[A-Za-z0-9_-]+ start -->/', '', $old_html);
            $old_html = preg_replace('/<!-- HWC [A-Za-z0-9_]+-[A-Za-z0-9_-]+ end -->$/', '', $old_html);
            $old_html = trim($old_html);
        }

        if (preg_match('/^<(\w+)/', $old_html, $m)) {
            $tag = $m[1];
            if (preg_match('/^<' . $tag . '([^>]*)>/', $old_html, $attr_m)) {
                $attr_str = $attr_m[1];
                $new_html = "<{$tag}{$attr_str}>" . $new_content . "</{$tag}>";
            } else {
                $new_html = "<{$tag}>" . $new_content . "</{$tag}>";
            }
        } else {
            $new_html = $new_content;
        }

        $new_html = $marker_start . $new_html . $marker_end;

        $blocks[$target_idx]['innerHTML'] = $new_html;
        foreach ($blocks[$target_idx]['innerContent'] as $ic => $chunk) {
            if (is_string($chunk)) {
                $blocks[$target_idx]['innerContent'][$ic] = $new_html;
            }
        }

        if ($block_name === 'core/heading' && isset($blocks[$target_idx]['attrs']['content'])) {
            $blocks[$target_idx]['attrs']['content'] = wp_strip_all_tags($new_content);
        }

        $new_post_content = serialize_blocks($blocks);

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        $post_title = $post->post_title;
        $cible = $ref !== null ? "ref \"$ref\"" : "bloc #$block_index";
        return ['success' => true, 'post_id' => $updated, 'message' => "Bloc $cible ($block_name) mis à jour dans « $post_title »."];
    }

    /**
     * Crée un bloc. Si $module est fourni, le bloc est enrobé de marqueurs HWC
     * (ref auto-générée) pour être retrouvable par ref ensuite.
     */
    public static function create_block($page_id, $block_name, $content, $insert_after_index = null, $insert_before_index = null, $module = '', $expected_hash = null) {
        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        if (!self::cas_check($post, $expected_hash)) {
            return ['success' => false, 'error' => 'conflict', 'message' => 'Conflit de concurrence : le contenu de la page a changé depuis la lecture. Relancez get_page_blocks et repassez le expected_hash à jour.'];
        }

        $allowed_blocks = [
            'core/paragraph', 'core/heading', 'core/list', 'core/image',
            'core/button', 'core/buttons', 'core/group', 'core/columns',
            'core/column', 'core/quote', 'core/code', 'core/preformatted',
            'core/pullquote', 'core/table', 'core/cover', 'core/media-text',
            'core/video', 'core/file', 'core/gallery', 'core/audio',
        ];

        if (!in_array($block_name, $allowed_blocks, true)) {
            return ['success' => false, 'message' => "Type de bloc non supporté : $block_name."];
        }

        $blocks = parse_blocks($post->post_content);

        if ($block_name === 'core/heading') {
            $level = 2;
            $text = wp_strip_all_tags($content);
            $attrs = json_encode(['level' => $level, 'content' => $text], JSON_UNESCAPED_UNICODE);
            $new_block = [
                'blockName'    => 'core/heading',
                'attrs'        => ['level' => $level, 'content' => $text],
                'innerHTML'    => "<h$level>" . esc_html($text) . "</h$level>",
                'innerContent' => ["<h$level>" . esc_html($text) . "</h$level>"],
            ];
        } elseif ($block_name === 'core/paragraph') {
            $new_block = [
                'blockName'    => 'core/paragraph',
                'attrs'        => [],
                'innerHTML'    => '<p>' . wp_kses_post($content) . '</p>',
                'innerContent' => ['<p>' . wp_kses_post($content) . '</p>'],
            ];
        } elseif ($block_name === 'core/list') {
            $items = '';
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $items .= '<li>' . esc_html($line) . '</li>';
                }
            }
            $new_block = [
                'blockName'    => 'core/list',
                'attrs'        => [],
                'innerHTML'    => '<ul class="wp-block-list">' . $items . '</ul>',
                'innerContent' => ['<ul class="wp-block-list">' . $items . '</ul>'],
            ];
        } elseif ($block_name === 'core/image') {
            $attrs = ['url' => $content, 'alt' => ''];
            $new_block = [
                'blockName'    => 'core/image',
                'attrs'        => $attrs,
                'innerHTML'    => '<figure class="wp-block-image"><img src="' . esc_url($content) . '" alt=""/></figure>',
                'innerContent' => ['<figure class="wp-block-image"><img src="' . esc_url($content) . '" alt=""/></figure>'],
            ];
        } elseif ($block_name === 'core/button') {
            $attrs = ['url' => '#', 'text' => wp_strip_all_tags($content)];
            $new_block = [
                'blockName'    => 'core/button',
                'attrs'        => $attrs,
                'innerHTML'    => '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html(wp_strip_all_tags($content)) . '</a></div>',
                'innerContent' => ['<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html(wp_strip_all_tags($content)) . '</a></div>'],
            ];
        } elseif ($block_name === 'core/quote') {
            $new_block = [
                'blockName'    => 'core/quote',
                'attrs'        => [],
                'innerHTML'    => '<blockquote class="wp-block-quote"><p>' . wp_kses_post($content) . '</p></blockquote>',
                'innerContent' => ['<blockquote class="wp-block-quote"><p>' . wp_kses_post($content) . '</p></blockquote>'],
            ];
        } else {
            $new_block = [
                'blockName'    => $block_name,
                'attrs'        => [],
                'innerHTML'    => '<div>' . wp_kses_post($content) . '</div>',
                'innerContent' => ['<div>' . wp_kses_post($content) . '</div>'],
            ];
        }

        // Enrobage HWC automatique si module fourni (spec 3.3 : ref stable pour l'agent)
        $ref = null;
        if (!empty($module)) {
            $module = sanitize_title($module);
            $ref_id = substr(md5($page_id . '|' . $module . '|' . uniqid('', true)), 0, 12);
            $ref = $module . '-' . $ref_id;
            $marker_start = '<!-- HWC ' . $ref . ' start -->';
            $marker_end = '<!-- HWC ' . $ref . ' end -->';
            $new_block['innerHTML'] = $marker_start . $new_block['innerHTML'] . $marker_end;
            $new_block['innerContent'] = array_map(function ($chunk) use ($marker_start, $marker_end) {
                if (is_string($chunk)) {
                    return $marker_start . $chunk . $marker_end;
                }
                return $chunk;
            }, $new_block['innerContent']);
        }

        if ($insert_after_index !== null) {
            $insert_after_index = intval($insert_after_index);
            $pos = self::find_block_position($blocks, $insert_after_index);
            if ($pos === null) {
                return ['success' => false, 'message' => "Bloc #$insert_after_index introuvable pour l'insertion."];
            }
            array_splice($blocks, $pos + 1, 0, [$new_block]);
        } elseif ($insert_before_index !== null) {
            $insert_before_index = intval($insert_before_index);
            $pos = self::find_block_position($blocks, $insert_before_index);
            if ($pos === null) {
                return ['success' => false, 'message' => "Bloc #$insert_before_index introuvable pour l'insertion."];
            }
            array_splice($blocks, $pos, 0, [$new_block]);
        } else {
            $blocks[] = $new_block;
        }

        $new_post_content = serialize_blocks($blocks);

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        return [
            'success'  => true,
            'post_id'  => $updated,
            'ref'      => $ref,
            'message'  => "Bloc $block_name créé dans « {$post->post_title} »." . ($ref ? " Ref générée : $ref" : ''),
        ];
    }

    public static function delete_block($page_id, $block_index = null, $ref = null, $expected_hash = null) {
        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        if (!self::cas_check($post, $expected_hash)) {
            return ['success' => false, 'error' => 'conflict', 'message' => 'Conflit de concurrence : le contenu de la page a changé depuis la lecture. Relancez get_page_blocks et repassez le expected_hash à jour.'];
        }

        $content = $post->post_content;
        $blocks = parse_blocks($content);

        $actual_index = 0;
        $target_idx = null;
        foreach ($blocks as $idx => $block) {
            if (empty($block['blockName'])) continue;
            if ($ref !== null) {
                if (self::extract_hwc_ref($block) === $ref) {
                    $target_idx = $idx;
                    break;
                }
            } else {
                if ($actual_index === $block_index) {
                    $target_idx = $idx;
                    break;
                }
            }
            $actual_index++;
        }

        if ($target_idx === null) {
            if ($ref !== null) {
                return ['success' => false, 'message' => "Aucun bloc avec la ref \"$ref\" trouvé sur la page $page_id."];
            }
            return ['success' => false, 'message' => "Bloc #$block_index introuvable."];
        }

        array_splice($blocks, $target_idx, 1);
        $new_post_content = serialize_blocks($blocks);

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        $cible = $ref !== null ? "ref \"$ref\"" : "bloc #$block_index";
        return ['success' => true, 'post_id' => $updated, 'message' => "Bloc $cible supprimé de « {$post->post_title} »."];
    }

    private static function find_block_position($blocks, $target_index) {
        $actual = 0;
        foreach ($blocks as $idx => $block) {
            if (empty($block['blockName'])) continue;
            if ($actual === $target_index) return $idx;
            $actual++;
        }
        return null;
    }

    public static function extract_block_text($block) {
        $html = $block['innerHTML'] ?? '';
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($html)));

        if ($text === '' && !empty($block['attrs']['content']) && is_string($block['attrs']['content'])) {
            $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags($block['attrs']['content'])));
        }

        return $text;
    }
}
