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
            ];
            $index++;
        }

        return ['success' => true, 'blocks' => $result, 'count' => count($result)];
    }

    public static function update_block_content($page_id, $block_index, $new_content) {
        $post = get_post($page_id);
        if (!$post) {
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
                $new_html = "<{$tag}{$attr_str}>" . $new_content . "</{$tag}>";
            } else {
                $new_html = "<{$tag}>" . $new_content . "</{$tag}>";
            }
        } else {
            $new_html = $new_content;
        }

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
        return ['success' => true, 'post_id' => $updated, 'message' => "Bloc #$block_index ($block_name) mis à jour dans « $post_title »."];
    }

    public static function create_block($page_id, $block_name, $content, $insert_after_index = null, $insert_before_index = null) {
        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'message' => 'Page introuvable.'];
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

        return ['success' => true, 'post_id' => $updated, 'message' => "Bloc $block_name créé dans « {$post->post_title} »."];
    }

    public static function delete_block($page_id, $block_index) {
        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        $content = $post->post_content;
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

        return ['success' => true, 'post_id' => $updated, 'message' => "Bloc #$block_index supprimé de « {$post->post_title} »."];
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
