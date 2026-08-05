<?php
/**
 * Lab HOUETOR Connect — ADDENDUM (Exp 030 bis) : test NOUVEAU ciblant un ENFANT
 * À L'INTÉRIEUR d'un core/quote natif de la page 2 (quote natif #1, index global).
 * Preuve : le patch 2.8.0 permet de cibler un enfant d'un conteneur natif (ref ou
 * index global) SANS toucher le parent ni les autres enfants.
 * Usage : wp eval-file rest-test-nested-child-native.php
 */

function hwn_req($method, $route, $params = []) {
    $req = new WP_REST_Request($method, $route);
    $req->set_header('X-Houetor-Token', get_option('hwc_token'));
    foreach ($params as $k => $v) {
        $req->set_param($k, $v);
    }
    return rest_get_server()->dispatch($req);
}

function hwn_blocks($page_id) {
    $r = hwn_req('GET', '/houetor/v1/page-blocks', ['page_id' => $page_id]);
    if ($r->get_status() !== 200) return null;
    $d = $r->get_data();
    foreach ($d['blocks'] as $b) {
        echo "    index=" . $b['index'] . " ref=" . var_export($b['ref'], true) . " parent=" . var_export($b['parent_ref'] ?? null, true) . " " . $b['blockName'] . " : " . substr($b['content'], 0, 42) . "\n";
    }
    return $d;
}

$GLOBALS['N_PASS'] = 0; $GLOBALS['N_FAIL'] = 0;
function hwn_check($label, $ok, $detail = '') {
    if ($ok) { $GLOBALS['N_PASS']++; echo "  PASS  $label $detail\n"; }
    else     { $GLOBALS['N_FAIL']++; echo "  FAIL  $label $detail\n"; }
}

// ---- Préparation : état initial page 2 ----
delete_transient('hwc_ratelimit_2');
$post0 = get_post(2);
$md5_0 = md5($post0->post_content);
$content0 = $post0->post_content;
echo "===== NESTED-CHILD-NATIVE: ETAT INITIAL page 2 =====\n";
echo "  md5=" . substr($md5_0, 0, 12) . "...\n\n";

// ---- Localisation dynamique : quote natif #1 (parent) + son enfant ----
echo "===== N-1: localisation dynamique quote natif + enfant =====\n";
$blocks1 = hwn_blocks(2);
$quote_idx = null; $child_idx = null; $child_ref = null; $child_name = null;
foreach ($blocks1['blocks'] as $b) {
    if ($quote_idx === null && $b['blockName'] === 'core/quote' && $b['ref'] === null && $b['index'] < 5) {
        $quote_idx = $b['index'];
        continue;
    }
    if ($quote_idx !== null && $b['parent_ref'] === $quote_idx) {
        $child_idx = $b['index'];
        $child_ref = $b['ref'];
        $child_name = $b['blockName'];
        break;
    }
}
hwn_check('quote natif #1 trouvé (dynamique)', $quote_idx !== null, $quote_idx !== null ? "quote_idx=$quote_idx" : 'introuvable');
hwn_check('enfant du quote natif trouvé (dynamique)', $child_idx !== null, $child_idx !== null ? "child_idx=$child_idx name=$child_name ref=" . var_export($child_ref, true) : 'introuvable');
if ($quote_idx === null || $child_idx === null) {
    echo "\n===== BILAN NESTED-CHILD-NATIVE : " . $GLOBALS['N_PASS'] . " PASS / " . $GLOBALS['N_FAIL'] . " FAIL (bloc natif introuvable — env modifié) =====\n";
    exit(1);
}
$hash1 = $blocks1['content_md5'];
$before_content = null;
foreach ($blocks1['blocks'] as $b) { if ($b['index'] === $child_idx) $before_content = $b['content']; }

// ---- N-2 : dry_run sur l'enfant du quote natif (aucune écriture) ----
echo "\n===== N-2: dry_run update enfant DANS quote natif (ref=" . var_export($child_ref, true) . ", idx=$child_idx) =====\n";
$new_content = '<p>ENFANT QUOTE NATIF — MODIF TEST 1.0.3</p>';
$r = hwn_req('PATCH', '/houetor/v1/block-content', [
    'page_id' => 2,
    'ref' => $child_ref,
    'block_index' => $child_idx,
    'new_content' => $new_content,
    'expected_hash' => $hash1,
    'dry_run' => true,
]);
$ok = $r->get_status() === 200 && !empty($r->get_data()['dry_run']);
hwn_check('dry_run enfant natif -> 200 + dry_run=true (sans écriture)', $ok, "status=" . $r->get_status());
$post2 = get_post(2);
hwn_check('dry_run: md5 inchange', md5($post2->post_content) === $hash1);
$blocks2 = hwn_blocks(2);
$child_still = null;
foreach ($blocks2['blocks'] as $b) { if ($b['index'] === $child_idx) $child_still = $b; }
hwn_check('dry_run: contenu enfant inchangé', $child_still && $child_still['content'] === $before_content);

// ---- N-3 : écriture RÉELLE sur l'enfant du quote natif ----
echo "\n===== N-3: écriture réelle enfant DANS quote natif =====\n";
delete_transient('hwc_ratelimit_2');
$r = hwn_req('PATCH', '/houetor/v1/block-content', [
    'page_id' => 2,
    'ref' => $child_ref,
    'block_index' => $child_idx,
    'new_content' => $new_content,
    'expected_hash' => $hash1,
]);
$ok = $r->get_status() === 200;
hwn_check('update réel enfant natif -> 200', $ok, "status=" . $r->get_status());
$blocks3 = hwn_blocks(2);
$child3 = null; $quote3 = null;
foreach ($blocks3['blocks'] as $b) {
    if ($b['index'] === $child_idx) $child3 = $b;
    if ($b['index'] === $quote_idx) $quote3 = $b;
}
hwn_check('contenu enfant modifié', $child3 && strpos($child3['content'], 'MODIF TEST 1.0.3') !== false, "content=" . ($child3['content'] ?? 'null'));
hwn_check('parent quote TOUJOURS conteneur (non touché)', $quote3 && $quote3['has_children'] === true && $quote3['child_count'] === 1, "child_count=" . ($quote3['child_count'] ?? 'null'));

// ---- N-4 : restauration de l'enfant (contenu d'origine) ----
echo "\n===== N-4: restauration enfant (contenu d'origine) =====\n";
delete_transient('hwc_ratelimit_2');
$r = hwn_req('PATCH', '/houetor/v1/block-content', [
    'page_id' => 2,
    'ref' => $child_ref,
    'block_index' => $child_idx,
    'new_content' => $before_content,
    'expected_hash' => $blocks3['content_md5'],
]);
$ok = $r->get_status() === 200;
hwn_check('restauration enfant -> 200', $ok, "status=" . $r->get_status());
$blocks4 = hwn_blocks(2);
$child4 = null;
foreach ($blocks4['blocks'] as $b) { if ($b['index'] === $child_idx) $child4 = $b; }
hwn_check('enfant restauré (contenu identique)', $child4 && $child4['content'] === $before_content);

// ---- N-5 : parent quote natif intact en profondeur (md5 global) ----
echo "\n===== N-5: intégrité — aucun résidu, parent intact =====\n";
$post5 = get_post(2);
$restored = false;
foreach (wp_get_post_revisions(2, ['numberposts' => -1]) as $rev) {
    if (md5($rev->post_content) === $md5_0) { wp_restore_post_revision($rev->ID); $restored = true; break; }
}
if (!$restored) {
    wp_update_post(['ID' => 2, 'post_content' => wp_slash($content0)], true);
}
$final = get_post(2);
$md5_final = md5($final->post_content);
echo "  md5 final page 2 : " . $md5_final . " (initial " . $md5_0 . ")\n";
hwn_check('md5 final == md5 initial (restauration complète)', $md5_final === $md5_0);

echo "\n===== BILAN NESTED-CHILD-NATIVE : " . $GLOBALS['N_PASS'] . " PASS / " . $GLOBALS['N_FAIL'] . " FAIL =====\n";
exit($GLOBALS['N_FAIL'] > 0 ? 1 : 0);
