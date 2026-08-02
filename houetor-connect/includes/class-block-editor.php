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
     * Tier policy (inspirée de block-mcp, Exp 008) : blocs legacy/obsolètes
     * refusés à la création, avec le bloc ALLOWED suggéré à la place.
     * L'erreur devient actionnable : l'agent recrée le bloc avec la suggestion
     * au lieu de bloquer sur un refus muet. Filtrable via hwc_legacy_blocks.
     */
    const LEGACY_BLOCKS = [
        'core/cover-image'  => 'core/cover',
        'core/subheading'   => 'core/heading',
        'core/list-item'    => 'core/list',
        'core/verse'        => 'core/preformatted',
        'core/html'         => 'core/paragraph',
        'core/embed'        => 'core/video',
        'core/shortcode'    => 'core/paragraph',
        'core/spacer'       => 'core/group',
        'core/separator'    => 'core/group',
        'core/search'       => 'core/paragraph',
        'core/archives'     => 'core/list',
        'core/categories'   => 'core/list',
        'core/tag-cloud'    => 'core/list',
        'core/rss'          => 'core/list',
        'core/calendar'     => 'core/table',
        'core/social-links' => 'core/buttons',
        'core/post-title'   => 'core/heading',
        'core/post-content' => 'core/paragraph',
        'core/latest-posts' => 'core/list',
        'core/query'        => 'core/group',
        'core/nextpage'     => 'core/group',
    ];

    /**
     * Tier policy : retourne le bloc ALLOWED suggéré pour un bloc legacy
     * ($block_name), ou null si le bloc n'est pas dans la map (refus générique).
     */
    public static function legacy_suggestion($block_name) {
        $map = apply_filters('hwc_legacy_blocks', self::LEGACY_BLOCKS);
        if (!is_array($map)) {
            $map = self::LEGACY_BLOCKS;
        }
        return isset($map[$block_name]) ? $map[$block_name] : null;
    }

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
            $suggested = self::legacy_suggestion($block_name);
            if ($suggested !== null) {
                return [
                    'success'         => false,
                    'error'           => 'legacy',
                    'block_name'      => $block_name,
                    'suggested_block' => $suggested,
                    'message'         => "Bloc $block_name obsolète ou non supporté à la création. Utilisez $suggested à la place (même contenu, bloc supporté).",
                ];
            }
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

    /**
     * Index logique d'un bloc (position parmi les blocs nommés) à partir de son
     * index de tableau parse_blocks. Retourne null si le bloc est hors liste.
     */
    private static function logical_index_of($blocks, $array_idx) {
        $actual = 0;
        foreach ($blocks as $idx => $block) {
            if (empty($block['blockName'])) continue;
            if ($idx === $array_idx) return $actual;
            $actual++;
        }
        return null;
    }

    /**
     * Déplace un bloc (par ref ou index logique) vers une position
     * (start|end|before|after + ancre). Le bloc est retiré PUIS ré-inséré ;
     * l'ancre est résolue sur l'état AVANT retrait (les index logiques vus par
     * l'agent restent valides). CAS, dry_run, révision et audit identiques aux
     * autres écritures. Un déplacement sans effet (déjà en place) ne crée
     * aucune révision ni audit.
     */
    public static function move_block($page_id, $block_index = null, $ref = null, $position = '', $anchor_ref = null, $anchor_index = null, $expected_hash = null, $dry_run = false) {
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

        if (!in_array($position, array('start', 'end', 'before', 'after'), true)) {
            return ['success' => false, 'message' => 'position requis (start|end|before|after).'];
        }
        if (in_array($position, array('before', 'after'), true) && $anchor_ref === null && $anchor_index === null) {
            return ['success' => false, 'message' => "position $position requiert anchor_ref ou anchor_index."];
        }

        $blocks = parse_blocks($content);
        $src = self::locate_block($blocks, $ref, $block_index);
        if ($src === null) {
            $cible = $ref !== null ? "ref \"$ref\"" : "#$block_index";
            return ['success' => false, 'message' => "Bloc $cible introuvable sur la page $page_id."];
        }
        $src_idx = $src['idx'];
        $src_ref = $src['ref'];
        $src_block = $blocks[$src_idx];

        $pos = null;
        if ($position === 'start') {
            $pos = self::find_block_position($blocks, 0);
            if ($pos === null) {
                return ['success' => false, 'message' => 'Aucun bloc à déplacer (page vide de blocs).'];
            }
        } elseif ($position === 'end') {
            $last = null;
            foreach ($blocks as $idx => $b) {
                if (!empty($b['blockName'])) $last = $idx;
            }
            $pos = $last === null ? count($blocks) : $last + 1;
        } else {
            $anchor = self::locate_block($blocks, $anchor_ref, $anchor_index);
            if ($anchor === null) {
                $a = $anchor_ref !== null ? "ref \"$anchor_ref\"" : "bloc #$anchor_index";
                return ['success' => false, 'message' => "Ancre $a introuvable sur la page $page_id (position $position)."];
            }
            if ($anchor['idx'] === $src_idx) {
                return ['success' => true, 'post_id' => $page_id, 'message' => "Le bloc " . ($src_ref !== null ? "ref \"$src_ref\"" : "#$block_index") . " est déjà à la position demandée — aucun déplacement."];
            }
            $pos = $anchor['idx'] + ($position === 'after' ? 1 : 0);
        }

        // Retrait puis ré-insertion (l'insertion se fait dans le tableau retiré).
        array_splice($blocks, $src_idx, 1);
        if ($pos > $src_idx) {
            $pos--;
        }
        array_splice($blocks, $pos, 0, array($src_block));

        $new_post_content = serialize_blocks($blocks);
        if (md5($new_post_content) === md5($content)) {
            return ['success' => true, 'post_id' => $page_id, 'message' => "Le bloc " . ($src_ref !== null ? "ref \"$src_ref\"" : "#$block_index") . " est déjà à la position demandée — aucun déplacement."];
        }

        $new_index = self::logical_index_of($blocks, $pos);

        if ($dry_run) {
            $cible = $ref !== null ? "ref \"$ref\"" : "bloc #$block_index";
            return ['success' => true, 'dry_run' => true, 'post_id' => $page_id, 'ref' => $src_ref, 'block_index' => $new_index, 'blockName' => $src_block['blockName'], 'position' => $position, 'message' => "DRY RUN (aucune écriture) : déplacement du $cible ({$src_block['blockName']}) vers $position prêt dans « {$post->post_title} »."];
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        $cible = $ref !== null ? "ref \"$ref\"" : "#$block_index";
        return ['success' => true, 'post_id' => $updated, 'ref' => $src_ref, 'block_index' => $new_index, 'blockName' => $src_block['blockName'], 'position' => $position, 'message' => "Bloc $cible ({$src_block['blockName']}) déplacé vers $position dans « {$post->post_title} »."];
    }

    /**
     * Régénère les refs HWC de tout un sous-arbre (duplication) : chaque ref
     * existante est remplacée par une ref fraîche au même préfixe module, ce qui
     * garantit l'unicité des marqueurs après copie. $map mémorise ancien -> nouveau.
     */
    private static function regenerate_refs_deep(&$block, &$map) {
        $ref = self::extract_hwc_ref($block);
        if ($ref !== null) {
            if (!isset($map[$ref])) {
                $module = explode('-', $ref, 2)[0];
                $map[$ref] = $module . '-' . substr(md5($module . '|' . uniqid('', true)), 0, 12);
            }
            $new_ref = $map[$ref];
            $re = '/<!-- HWC ' . preg_quote($ref, '/') . ' (start|end) -->/';
            $block['innerHTML'] = preg_replace($re, '<!-- HWC ' . $new_ref . ' $1 -->', $block['innerHTML']);
            foreach ($block['innerContent'] as $i => $chunk) {
                if (is_string($chunk)) {
                    $block['innerContent'][$i] = preg_replace($re, '<!-- HWC ' . $new_ref . ' $1 -->', $chunk);
                }
            }
        }
        if (!empty($block['innerBlocks'])) {
            foreach ($block['innerBlocks'] as &$child) {
                self::regenerate_refs_deep($child, $map);
            }
            unset($child);
        }
    }

    /**
     * Duplique un bloc (par ref ou index logique) juste après sa position.
     * Les refs HWC de la copie sont régénérées (unicité préservée, préfixe
     * module conservé) ; si le source n'a pas de ref et que $module est fourni,
     * la copie reçoit une ref fraîche du module. La copie inclut les blocs
     * imbriqués éventuels (sous-arbre entier). CAS, dry_run, révision, audit.
     */
    public static function duplicate_block($page_id, $block_index = null, $ref = null, $module = '', $expected_hash = null, $dry_run = false) {
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
        $src = self::locate_block($blocks, $ref, $block_index);
        if ($src === null) {
            $cible = $ref !== null ? "ref \"$ref\"" : "#$block_index";
            return ['success' => false, 'message' => "Bloc $cible introuvable sur la page $page_id."];
        }
        $src_idx = $src['idx'];
        $src_ref = $src['ref'];
        $src_block = $blocks[$src_idx];

        $copy = $src_block;
        $map = [];
        self::regenerate_refs_deep($copy, $map);

        $new_ref = self::extract_hwc_ref($copy);
        if ($new_ref === null && !empty($module)) {
            $module = sanitize_title($module);
            $ref_id = substr(md5($page_id . '|' . $module . '|' . uniqid('', true)), 0, 12);
            $new_ref = $module . '-' . $ref_id;
            $copy = self::wrap_ref($copy, $new_ref);
        }

        array_splice($blocks, $src_idx + 1, 0, array($copy));

        $new_post_content = serialize_blocks($blocks);
        $new_index = self::logical_index_of($blocks, $src_idx + 1);

        if ($dry_run) {
            return ['success' => true, 'dry_run' => true, 'post_id' => $page_id, 'ref' => $new_ref, 'block_index' => $new_index, 'blockName' => $src_block['blockName'], 'message' => "DRY RUN (aucune écriture) : duplication du " . ($src_ref !== null ? "ref \"$src_ref\"" : "bloc #$block_index") . " ({$src_block['blockName']}) prête dans « {$post->post_title} »." . ($new_ref ? " Ref simulée de la copie : $new_ref" : '')];
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        return ['success' => true, 'post_id' => $updated, 'ref' => $new_ref, 'block_index' => $new_index, 'blockName' => $src_block['blockName'], 'message' => "Bloc " . ($src_ref !== null ? "ref \"$src_ref\"" : "#$block_index") . " ({$src_block['blockName']}) dupliqué dans « {$post->post_title} »." . ($new_ref ? " Ref de la copie : $new_ref" : '')];
    }

    /**
     * Enrobe un bloc (ou une plage contiguë start->end) dans un core/group.
     * Le groupe reçoit une ref HWC si $module est fourni. Les refs des blocs
     * enrobés sont conservées. CAS, dry_run, révision, audit.
     */
    public static function wrap_block($page_id, $block_index = null, $ref = null, $end_ref = null, $end_index = null, $module = '', $expected_hash = null, $dry_run = false) {
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
        $src = self::locate_block($blocks, $ref, $block_index);
        if ($src === null) {
            $cible = $ref !== null ? "ref \"$ref\"" : "#$block_index";
            return ['success' => false, 'message' => "Bloc $cible introuvable sur la page $page_id."];
        }
        $start_idx = $src['idx'];

        $end_idx = $start_idx;
        if ($end_ref !== null || $end_index !== null) {
            $end = self::locate_block($blocks, $end_ref, $end_index);
            if ($end === null) {
                $cible = $end_ref !== null ? "ref \"$end_ref\"" : "bloc #$end_index";
                return ['success' => false, 'message' => "Bloc de fin $cible introuvable sur la page $page_id."];
            }
            $end_idx = $end['idx'];
            if ($end_idx < $start_idx) {
                return ['success' => false, 'message' => 'Le bloc de fin précède le bloc de départ — plage invalide.'];
            }
        }

        $range = array_splice($blocks, $start_idx, $end_idx - $start_idx + 1);
        $count = count($range);

        $group_ref = null;
        if (!empty($module)) {
            $module = sanitize_title($module);
            $group_ref = $module . '-' . substr(md5($page_id . '|' . $module . '|' . uniqid('', true)), 0, 12);
        }
        $open = $group_ref !== null ? '<!-- HWC ' . $group_ref . ' start --><div class="wp-block-group">' : '<div class="wp-block-group">';
        $close = $group_ref !== null ? '</div><!-- HWC ' . $group_ref . ' end -->' : '</div>';

        $group = [
            'blockName'    => 'core/group',
            'attrs'        => [],
            'innerHTML'    => $open,
            'innerContent' => array_merge(array($open), array_fill(0, count($range), null), array($close)),
            'innerBlocks'  => $range,
        ];

        array_splice($blocks, $start_idx, 0, array($group));

        $new_post_content = serialize_blocks($blocks);
        $group_index = self::logical_index_of($blocks, $start_idx);

        if ($dry_run) {
            return ['success' => true, 'dry_run' => true, 'post_id' => $page_id, 'ref' => $group_ref, 'block_index' => $group_index, 'blockName' => 'core/group', 'count' => $count, 'message' => "DRY RUN (aucune écriture) : $count bloc(s) prêt(s) à être enrobé(s) dans un groupe dans « {$post->post_title} »." . ($group_ref ? " Ref simulée du groupe : $group_ref" : '')];
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        return ['success' => true, 'post_id' => $updated, 'ref' => $group_ref, 'block_index' => $group_index, 'blockName' => 'core/group', 'count' => $count, 'message' => "$count bloc(s) enrobé(s) dans un groupe dans « {$post->post_title} »." . ($group_ref ? " Ref du groupe : $group_ref" : '')];
    }

    /**
     * Dégroupe un core/group : ses enfants sont promus à sa place (au niveau
     * racine), le groupe disparaît. Les refs des enfants sont conservées.
     * CAS, dry_run, révision, audit.
     */
    public static function unwrap_block($page_id, $block_index = null, $ref = null, $expected_hash = null, $dry_run = false) {
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
        $src = self::locate_block($blocks, $ref, $block_index);
        if ($src === null) {
            $cible = $ref !== null ? "ref \"$ref\"" : "#$block_index";
            return ['success' => false, 'message' => "Bloc $cible introuvable sur la page $page_id."];
        }
        $src_idx = $src['idx'];

        if ($blocks[$src_idx]['blockName'] !== 'core/group') {
            return ['success' => false, 'message' => "Le bloc ciblé ({$blocks[$src_idx]['blockName']}) n'est pas un groupe — seul core/group peut être dégroupé."];
        }

        $children = $blocks[$src_idx]['innerBlocks'] ?? [];
        if (empty($children)) {
            return ['success' => false, 'message' => 'Le groupe ciblé est vide — rien à dégrouper.'];
        }
        $count = count($children);

        array_splice($blocks, $src_idx, 1, $children);

        $new_post_content = serialize_blocks($blocks);

        if ($dry_run) {
            return ['success' => true, 'dry_run' => true, 'post_id' => $page_id, 'count' => $count, 'message' => "DRY RUN (aucune écriture) : dégroupement de $count bloc(s) prêt dans « {$post->post_title} »."];
        }

        wp_save_post_revision($page_id);

        $updated = wp_update_post([
            'ID'           => $page_id,
            'post_content' => wp_slash($new_post_content),
        ], true);

        if (is_wp_error($updated)) {
            return ['success' => false, 'message' => $updated->get_error_message()];
        }

        return ['success' => true, 'post_id' => $updated, 'count' => $count, 'message' => "Groupe dégroupé — $count bloc(s) promu(s) à la racine de « {$post->post_title} »."];
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
