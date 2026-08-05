<?php
define('ABSPATH', '/tmp/');

// --- Stubs WP minimaux, juste ce dont blocks.php / class-block-editor.php ont besoin ---
function wp_strip_all_tags($s) { return trim(strip_tags((string)$s)); }
function esc_html($s) { return htmlspecialchars((string)$s, ENT_QUOTES); }
function esc_url($s) { return (string)$s; }
function wp_kses_post($s) { return (string)$s; }
function sanitize_title($s) { return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', (string)$s)); }
function sanitize_text_field($s) { return trim((string)$s); }
function apply_filters($tag, $value) { return $value; }
function wp_slash($v) { return $v; }
function is_wp_error($v) { return false; }

class FakePost {
    public $ID;
    public $post_content;
    public $post_title = 'Page de test';
}

$GLOBALS['__fake_posts'] = [];

function get_post($id) {
    return $GLOBALS['__fake_posts'][$id] ?? null;
}

function wp_update_post($args, $wp_error = false) {
    $id = $args['ID'];
    $GLOBALS['__fake_posts'][$id]->post_content = stripslashes($args['post_content']);
    return $id;
}

function wp_save_post_revision($id) { return true; }

// --- Vrai code WordPress (source officielle, non modifié) ---
// Chemins portables : env WP_INC / HWC_PLUGIN_INC, fallback = poste du lab.
$wp_inc = getenv('WP_INC') ?: '/mnt/c/Users/Kimsh/Desktop/lab/wordpress-test-env/wp-includes';
require "$wp_inc/class-wp-block-parser-block.php";
require "$wp_inc/class-wp-block-parser-frame.php";
require "$wp_inc/class-wp-block-parser.php";
require "$wp_inc/blocks.php";

// --- Le fichier patché de Connect ---
require (getenv('HWC_PLUGIN_INC') ?: '/mnt/c/Users/Kimsh/Desktop/lab/connect/houetor-connect/includes') . '/class-block-editor.php';

// --- Contenu de test : un core/group avec un core/paragraph niché à
//     l'intérieur (exactement le cas signalé : bloc existant en inner_block) ---
$content = <<<HTML
<!-- wp:group -->
<div class="wp-block-group">
<!-- wp:heading -->
<h2>Titre du groupe</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Ancien contenu du paragraphe niché</p>
<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
HTML;

$GLOBALS['__fake_posts'][42] = new FakePost();
$GLOBALS['__fake_posts'][42]->ID = 42;
$GLOBALS['__fake_posts'][42]->post_content = $content;

echo "=== TEST 1 : get_page_blocks() voit-il le paragraphe niché ? ===\n";
$listing = HWC_Block_Editor::get_page_blocks(42);
echo json_encode($listing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

$nested_ref_or_index = null;
foreach ($listing['blocks'] as $b) {
    if ($b['blockName'] === 'core/paragraph' && $b['depth'] > 0) {
        $nested_ref_or_index = $b['index'];
    }
}

if ($nested_ref_or_index === null) {
    echo "ECHEC : le paragraphe niché n'apparait pas dans la liste.\n";
    exit(1);
}
echo ">>> Paragraphe niché trouvé à l'index global $nested_ref_or_index (depth > 0)\n\n";

echo "=== TEST 2 : update_block_content() sur ce bloc niché (AVANT le patch, cela renvoyait 'contient des blocs imbriqués') ===\n";
$result = HWC_Block_Editor::update_block_content(42, $nested_ref_or_index, 'NOUVEAU contenu injecte dans le bloc nichE');
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if (!$result['success']) {
    echo "ECHEC : l'update a été refusé.\n";
    exit(1);
}

echo "=== TEST 3 : le contenu réellement écrit dans post_content (preuve d'écriture en place, structure du groupe préservée) ===\n";
echo $GLOBALS['__fake_posts'][42]->post_content . "\n\n";

$final = $GLOBALS['__fake_posts'][42]->post_content;
$ok_new_text   = strpos($final, 'NOUVEAU contenu injecte dans le bloc nichE') !== false;
$ok_group_kept = strpos($final, 'wp:group') !== false;
$ok_heading_kept = strpos($final, 'Titre du groupe') !== false;

echo "Nouveau texte présent : " . ($ok_new_text ? 'OUI' : 'NON') . "\n";
echo "Structure wp:group préservée : " . ($ok_group_kept ? 'OUI' : 'NON') . "\n";
echo "Le heading frère (non ciblé) est intact : " . ($ok_heading_kept ? 'OUI' : 'NON') . "\n";

echo "\n=== TEST 4 : refus toujours actif si on cible le CONTENEUR lui-même (le core/group) ===\n";
$result2 = HWC_Block_Editor::update_block_content(42, 0, 'essai sur le conteneur');
echo json_encode($result2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

if ($result2['success'] !== false) {
    echo "ECHEC : le conteneur aurait dû être refusé.\n";
    exit(1);
}

if ($ok_new_text && $ok_group_kept && $ok_heading_kept) {
    echo "\n>>> TOUS LES TESTS PASSENT.\n";
    exit(0);
} else {
    echo "\n>>> ECHEC.\n";
    exit(1);
}
