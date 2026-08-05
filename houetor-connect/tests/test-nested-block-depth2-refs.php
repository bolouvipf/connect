<?php
define('ABSPATH', '/tmp/');
function wp_strip_all_tags($s) { return trim(strip_tags((string)$s)); }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
function esc_url($s) { return (string)$s; }
function wp_kses_post($s) { return (string)$s; }
function sanitize_title($s) { return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$s)); }
function sanitize_text_field($s) { return trim((string)$s); }
function apply_filters($tag, $value) { return $value; }
function wp_slash($v) { return $v; }
function is_wp_error($v) { return false; }

class FakePost { public $ID; public $post_content; public $post_title = 'Page columns'; }
$GLOBALS['__fake_posts'] = [];
function get_post($id) { return $GLOBALS['__fake_posts'][$id] ?? null; }
function wp_update_post($args, $wp_error = false) {
    $id = $args['ID'];
    $GLOBALS['__fake_posts'][$id]->post_content = stripslashes($args['post_content']);
    return $id;
}
function wp_save_post_revision($id) { return true; }

$wp_inc = getenv('WP_INC') ?: '/mnt/c/Users/Kimsh/Desktop/lab/wordpress-test-env/wp-includes';
require "$wp_inc/class-wp-block-parser-block.php";
require "$wp_inc/class-wp-block-parser-frame.php";
require "$wp_inc/class-wp-block-parser.php";
require "$wp_inc/blocks.php";
require (getenv('HWC_PLUGIN_INC') ?: '/mnt/c/Users/Kimsh/Desktop/lab/connect/houetor-connect/includes') . '/class-block-editor.php';

// core/columns > core/column > core/paragraph (avec ref HWC), profondeur 2
// -- exactement le pattern produit par wrap_block + create_block(module=...)
$content = <<<HTML
<!-- wp:columns -->
<div class="wp-block-columns">
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:paragraph -->
<!-- HWC annonces-abc123 start --><p>Texte original colonne 1</p><!-- HWC annonces-abc123 end -->
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column">
<!-- wp:paragraph -->
<p>Texte colonne 2 (sans ref)</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:column -->
</div>
<!-- /wp:columns -->
HTML;

$GLOBALS['__fake_posts'][7] = new FakePost();
$GLOBALS['__fake_posts'][7]->ID = 7;
$GLOBALS['__fake_posts'][7]->post_content = $content;

echo "=== get_page_blocks (profondeur 2) ===\n";
$listing = HWC_Block_Editor::get_page_blocks(7);
foreach ($listing['blocks'] as $b) {
    echo str_repeat('  ', $b['depth']) . "#{$b['index']} {$b['blockName']} ref=" . var_export($b['ref'], true) . " parent_ref=" . var_export($b['parent_ref'], true) . " content=\"{$b['content']}\"\n";
}

echo "\n=== update_block_content PAR REF (annonces-abc123), à profondeur 2 ===\n";
$r1 = HWC_Block_Editor::update_block_content(7, null, 'Texte modifié via ref', 'annonces-abc123');
echo json_encode($r1, JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== batch_update_blocks sur la colonne 2 (par index, sans ref), profondeur 2 ===\n";
// retrouver l'index de "Texte colonne 2"
$idx2 = null;
foreach (HWC_Block_Editor::get_page_blocks(7)['blocks'] as $b) {
    if (strpos($b['content'], 'colonne 2') !== false) $idx2 = $b['index'];
}
$r2 = HWC_Block_Editor::batch_update_blocks(7, [
    ['block_index' => $idx2, 'new_content' => 'Colonne 2 modifiée en batch'],
]);
echo json_encode($r2, JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== Contenu final ===\n";
echo $GLOBALS['__fake_posts'][7]->post_content . "\n";

$final = $GLOBALS['__fake_posts'][7]->post_content;
$checks = [
    'ref utilisée -> texte modifié'      => strpos($final, 'Texte modifié via ref') !== false,
    'ref HWC conservée après update'     => strpos($final, 'HWC annonces-abc123') !== false,
    'batch sur profondeur 2 -> modifié'  => strpos($final, 'Colonne 2 modifiée en batch') !== false,
    'structure columns/column intacte'   => substr_count($final, 'wp:column') >= 4, // 1x columns-open+close (2) + 2x column (2 each open/close = 4) approx
];
foreach ($checks as $label => $ok) {
    echo ($ok ? 'OK  ' : 'FAIL ') . $label . "\n";
}
$all_ok = !in_array(false, $checks, true);
echo $all_ok ? "\n>>> TOUS LES TESTS PASSENT (profondeur 2 + refs).\n" : "\n>>> ECHEC.\n";
exit($all_ok ? 0 : 1);
