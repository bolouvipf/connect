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

    const BATCH_MAX_UPDATES = 50;

    /**
     * Types de blocs supportés à la création (create_block).
     */
    const ALLOWED_BLOCKS = [
        'core/paragraph', 'core/heading', 'core/list', 'core/image',
        'core/button', 'core/buttons', 'core/group', 'core/columns',
        'core/column', 'core/quote', 'core/code', 'core/preformatted',
        'core/pullquote', 'core/table', 'core/cover', 'core/media-text',
        'core/video', 'core/file', 'core/gallery', 'core/audio',
    ];

    /**
     * Types de blocs transformables (contenu purement textuel, aucun média).
     * Restriction volontaire : une conversion vers image/button/table/columns…
     * ne peut pas préserver le contenu de façon fiable.
     */
    const TEXT_BLOCKS = [
        'core/paragraph', 'core/heading', 'core/quote', 'core/list',
        'core/code', 'core/preformatted', 'core/pullquote',
    ];

    /**
     * Construit le bloc (structure parse_blocks) à partir du nom et du contenu
     * texte. Logique partagée par create_block et transform_block.
     * $attrs permet de préserver des attributs (ex. level du heading).
     */
    private static function build_block($block_name, $content, $attrs = []) {
        if ($block_name === 'core/heading') {
            $level = isset($attrs['level']) ? intval($attrs['level']) : 2;
            $text = wp_strip_all_tags($content);
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
            $new_block = [
                'blockName'    => 'core/image',
                'attrs'        => ['url' => $content, 'alt' => ''],
                'innerHTML'    => '<figure class="wp-block-image"><img src="' . esc_url($content) . '" alt=""/></figure>',
                'innerContent' => ['<figure class="wp-block-image"><img src="' . esc_url($content) . '" alt=""/></figure>'],
            ];
        } elseif ($block_name === 'core/button') {
            $new_block = [
                'blockName'    => 'core/button',
                'attrs'        => ['url' => '#', 'text' => wp_strip_all_tags($content)],
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
        return $new_block;
    }

    /**
     * Enrobe un bloc des marqueurs HWC (ref stable pour l'agent).
     * Retourne la ref utilisée (ou null si $ref vide).
     */
    private static function wrap_ref($new_block, $ref) {
        if (empty($ref)) {
            return $new_block;
        }
        $marker_start = '<!-- HWC ' . $ref . ' start -->';
        $marker_end = '<!-- HWC ' . $ref . ' end -->';
        $new_block['innerHTML'] = $marker_start . $new_block['innerHTML'] . $marker_end;
        $new_block['innerContent'] = array_map(function ($chunk) use ($marker_start, $marker_end) {
            if (is_string($chunk)) {
                return $marker_start . $chunk . $marker_end;
            }
            return $chunk;
        }, $new_block['innerContent']);
        return $new_block;
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
     * Construit le HTML de remplacement d'un bloc (attributs préservés, marqueurs
     * HWC conservés). Retourne le HTML complet prêt à être ré-sérialisé.
     */
    private static function build_replacement_html($block, $new_content) {
        $html = trim($block['innerHTML'] ?? '');
        $marker_start = '';
        $marker_end = '';
        $ref = self::extract_hwc_ref($block);

        if ($ref !== null) {
            $marker_start = '<!-- HWC ' . $ref . ' start -->';
            $marker_end = '<!-- HWC ' . $ref . ' end -->';
            $html = preg_replace('/^<!-- HWC [A-Za-z0-9_]+-[A-Za-z0-9_-]+ start -->/', '', $html);
            $html = preg_replace('/<!-- HWC [A-Za-z0-9_]+-[A-Za-z0-9_-]+ end -->$/', '', $html);
            $html = trim($html);
        }

        if (preg_match('/^<(\w+)/', $html, $m)) {
            $tag = $m[1];
            if (preg_match('/^<' . $tag . '([^>]*)>/', $html, $attr_m)) {
                $new_html = "<{$tag}{$attr_m[1]}>" . $new_content . "</{$tag}>";
            } else {
                $new_html = "<{$tag}>" . $new_content . "</{$tag}>";
            }
        } else {
            $new_html = $new_content;
        }

        return $marker_start . $new_html . $marker_end;
    }

    /**
     * Localise l'index (tableau parse_blocks) d'un bloc par ref ou index logique.
     * Retourne ['idx' => int, 'ref' => string|null] ou null.
     */
    private static function locate_block($blocks, $ref, $block_index) {
        $actual = 0;
        foreach ($blocks as $idx => $block) {
            if (empty($block['blockName'])) continue;
            if ($ref !== null) {
                if (self::extract_hwc_ref($block) === $ref) {
                    return array('idx' => $idx, 'ref' => $ref);
                }
            } elseif ($block_index !== null && $actual === intval($block_index)) {
                return array('idx' => $idx, 'ref' => self::extract_hwc_ref($block));
            }
            $actual++;
        }
        return null;
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

    public static function update_block_content($page_id, $block_index = null, $new_content = '', $ref = null, $expected_hash = null, $dry_run = false) {
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
        $located = self::locate_block($blocks, $ref, $block_index);

        if ($located === null) {
            if ($ref !== null) {
                return ['success' => false, 'message' => "Aucun bloc avec la ref \"$ref\" trouvé sur la page $page_id."];
            }
            $total = 0;
            foreach ($blocks as $b) { if (!empty($b['blockName'])) $total++; }
            return ['success' => false, 'message' => "Bloc #$block_index introuvable (0-" . ($total - 1) . " disponible). Utilise get_page_blocks pour voir les indices valides."];
        }

        $target_idx = $located['idx'];
        $target_ref = $located['ref'];
        $block_name = $blocks[$target_idx]['blockName'];

        if (!empty($blocks[$target_idx]['innerBlocks'])) {
            return ['success' => false, 'message' => "Le bloc #$block_index ($block_name) contient des blocs imbriqués et ne peut pas être modifié directement."];
        }

        $new_html = self::build_replacement_html($blocks[$target_idx], $new_content);
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

        $cible = $ref !== null ? "ref \"$ref\"" : "bloc #$block_index";

        if ($dry_run) {
            return ['success' => true, 'dry_run' => true, 'post_id' => $page_id, 'message' => "DRY RUN (aucune écriture) : le contenu du $cible ($block_name) dans « {$post->post_title} » est prêt à être mis à jour."];
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        $post_title = $post->post_title;
        return ['success' => true, 'post_id' => $updated, 'message' => "Bloc $cible ($block_name) mis à jour dans « $post_title »."];
    }

    /**
     * Mise à jour atomique de N blocs en UNE révision (all-or-nothing, max 50).
     * Toutes les cibles sont validées AVANT toute écriture ; si une seule échoue,
     * rien n'est écrit. Compte 1 écriture rate limit (géré côté REST).
     */
    public static function batch_update_blocks($page_id, $updates, $expected_hash = null, $dry_run = false) {
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

        if (!is_array($updates) || empty($updates)) {
            return ['success' => false, 'message' => 'updates requis (tableau non vide de {ref|block_index, new_content}).'];
        }

        if (count($updates) > self::BATCH_MAX_UPDATES) {
            return ['success' => false, 'message' => 'Trop d\'updates : maximum ' . self::BATCH_MAX_UPDATES . ' par appel batch.'];
        }

        $blocks = parse_blocks($content);
        $results = [];

        // Phase 1 : validation complète AVANT toute écriture (all-or-nothing).
        foreach ($updates as $update) {
            if (!is_array($update)) {
                return ['success' => false, 'message' => 'Chaque update doit être un objet {ref|block_index, new_content}.'];
            }

            $update_ref   = isset($update['ref']) ? sanitize_text_field($update['ref']) : null;
            $update_index = isset($update['block_index']) ? intval($update['block_index']) : null;
            $new_content  = isset($update['new_content']) ? $update['new_content'] : '';

            if ($update_ref === null && $update_index === null) {
                return ['success' => false, 'message' => 'Chaque update doit fournir ref ou block_index.'];
            }

            $located = self::locate_block($blocks, $update_ref, $update_index);
            if ($located === null) {
                $cible = $update_ref !== null ? "ref \"$update_ref\"" : "bloc #$update_index";
                return ['success' => false, 'message' => "Cible $cible introuvable sur la page $page_id — batch abandonné, aucune écriture effectuée."];
            }

            $target_idx = $located['idx'];
            $block_name = $blocks[$target_idx]['blockName'];

            if (!empty($blocks[$target_idx]['innerBlocks'])) {
                return ['success' => false, 'message' => "Le bloc $block_name ciblé contient des blocs imbriqués — batch abandonné, aucune écriture effectuée."];
            }

            // Application en mémoire : les updates successifs voient les précédents.
            $new_html = self::build_replacement_html($blocks[$target_idx], $new_content);
            $blocks[$target_idx]['innerHTML'] = $new_html;
            foreach ($blocks[$target_idx]['innerContent'] as $ic => $chunk) {
                if (is_string($chunk)) {
                    $blocks[$target_idx]['innerContent'][$ic] = $new_html;
                }
            }

            if ($block_name === 'core/heading' && isset($blocks[$target_idx]['attrs']['content'])) {
                $blocks[$target_idx]['attrs']['content'] = wp_strip_all_tags($new_content);
            }

            $results[] = [
                'ref'        => $located['ref'],
                'block_index'=> $update_index,
                'blockName'  => $block_name,
                'status'     => 'ok',
            ];
        }

        $new_post_content = serialize_blocks($blocks);
        $n = count($results);

        if ($dry_run) {
            return ['success' => true, 'dry_run' => true, 'post_id' => $page_id, 'count' => $n, 'updates' => $results, 'message' => "DRY RUN (aucune écriture) : $n bloc(s) valide(s), prêt(s) à être mis à jour en UNE révision dans « {$post->post_title} »."];
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        return ['success' => true, 'post_id' => $updated, 'count' => $n, 'updates' => $results, 'message' => "$n bloc(s) mis à jour en UNE seule révision dans « {$post->post_title} »."];
    }

    /**
     * Crée un bloc. Si $module est fourni, le bloc est enrobé de marqueurs HWC
     * (ref auto-générée) pour être retrouvable par ref ensuite.
     */
    public static function create_block($page_id, $block_name, $content, $insert_after_index = null, $insert_before_index = null, $module = '', $expected_hash = null, $dry_run = false) {
        $post = get_post($page_id);
        if (!$post) {
            return ['success' => false, 'message' => 'Page introuvable.'];
        }

        if (!self::cas_check($post, $expected_hash)) {
            return ['success' => false, 'error' => 'conflict', 'message' => 'Conflit de concurrence : le contenu de la page a changé depuis la lecture. Relancez get_page_blocks et repassez le expected_hash à jour.'];
        }

        if (!in_array($block_name, self::ALLOWED_BLOCKS, true)) {
            return ['success' => false, 'message' => "Type de bloc non supporté : $block_name."];
        }

        $blocks = parse_blocks($post->post_content);

        $new_block = self::build_block($block_name, $content);

        // Enrobage HWC automatique si module fourni (spec 3.3 : ref stable pour l'agent)
        $ref = null;
        if (!empty($module)) {
            $module = sanitize_title($module);
            $ref_id = substr(md5($page_id . '|' . $module . '|' . uniqid('', true)), 0, 12);
            $ref = $module . '-' . $ref_id;
            $new_block = self::wrap_ref($new_block, $ref);
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

        if ($dry_run) {
            $msg = "DRY RUN (aucune écriture) : bloc $block_name prêt à être créé dans « {$post->post_title} »." . ($ref ? " Ref simulée : $ref (sera différente à l'exécution réelle)" : '');
            return ['success' => true, 'dry_run' => true, 'post_id' => $page_id, 'ref' => $ref, 'message' => $msg];
        }

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

    /**
     * Transforme un bloc (contenu texte préservé, type changé) en place.
     * Uniquement entre types de la whitelist TEXT_BLOCKS ; la ref HWC du bloc
     * source est conservée pour que les écritures suivantes de l'agent restent
     * stables. CAS (expected_hash), dry_run, révision et audit identiques aux
     * autres écritures.
     */
    public static function transform_block($page_id, $block_index = null, $ref = null, $target_block_name = '', $expected_hash = null, $dry_run = false) {
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

        if (!in_array($target_block_name, self::TEXT_BLOCKS, true)) {
            return ['success' => false, 'message' => "Type de bloc non supporté pour la transformation : $target_block_name. Types de texte supportés : " . implode(', ', self::TEXT_BLOCKS) . '.'];
        }

        $blocks = parse_blocks($content);
        $located = self::locate_block($blocks, $ref, $block_index);

        if ($located === null) {
            if ($ref !== null) {
                return ['success' => false, 'message' => "Ref \"$ref\" introuvable sur la page $page_id (aucun bloc avec cette ref)."];
            }
            $total = 0;
            foreach ($blocks as $b) { if (!empty($b['blockName'])) $total++; }
            return ['success' => false, 'message' => "Bloc #$block_index introuvable (0-" . ($total - 1) . " disponible). Utilise get_page_blocks pour voir les indices valides."];
        }

        $target_idx = $located['idx'];
        $target_ref = $located['ref'];
        $source = $blocks[$target_idx];
        $source_name = $source['blockName'];

        if (!empty($source['innerBlocks'])) {
            return ['success' => false, 'message' => "Le bloc $source_name ciblé contient des blocs imbriqués et ne peut pas être transformé directement."];
        }

        if (!in_array($source_name, self::TEXT_BLOCKS, true)) {
            return ['success' => false, 'message' => "Bloc $source_name non transformable (blocs de texte uniquement : " . implode(', ', self::TEXT_BLOCKS) . ').'];
        }

        $text = self::extract_block_text($source);
        if ($text === '') {
            return ['success' => false, 'message' => "Le bloc source ($source_name) est vide — rien à transformer."];
        }

        $attrs = [];
        if ($target_block_name === 'core/heading' && $source_name === 'core/heading' && isset($source['attrs']['level'])) {
            $attrs['level'] = $source['attrs']['level'];
        }

        $new_block = self::build_block($target_block_name, $text, $attrs);
        $new_block = self::wrap_ref($new_block, $target_ref);
        $blocks[$target_idx] = $new_block;

        $new_post_content = serialize_blocks($blocks);
        $cible = $ref !== null ? "ref \"$ref\"" : "bloc #$block_index";

        if ($dry_run) {
            return [
                'success'          => true,
                'dry_run'          => true,
                'post_id'          => $page_id,
                'ref'              => $target_ref,
                'block_index'      => $block_index,
                'blockName'        => $source_name,
                'target_blockName' => $target_block_name,
                'message'          => "DRY RUN (aucune écriture) : transformation $source_name -> $target_block_name du $cible prête dans « {$post->post_title} ».",
            ];
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        return [
            'success'          => true,
            'post_id'          => $updated,
            'ref'              => $target_ref,
            'block_index'      => $block_index,
            'blockName'        => $source_name,
            'target_blockName' => $target_block_name,
            'message'          => "Bloc $cible transformé ($source_name -> $target_block_name) dans « {$post->post_title} ».",
        ];
    }

    public static function delete_block($page_id, $block_index = null, $ref = null, $expected_hash = null, $dry_run = false) {
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

        $cible = $ref !== null ? "ref \"$ref\"" : "bloc #$block_index";

        if ($dry_run) {
            return ['success' => true, 'dry_run' => true, 'post_id' => $page_id, 'message' => "DRY RUN (aucune écriture) : suppression du $cible prête dans « {$post->post_title} »."];
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

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
