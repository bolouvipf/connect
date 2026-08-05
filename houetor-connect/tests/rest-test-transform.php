<?php
/**
 * Lab HOUETOR Connect — Tests transform de blocs (plugin 2.5.0)
 * POST /blocks/transform : conversion entre blocs de texte (whitelist),
 * ref HWC conservée, CAS/dry_run/révision/audit identiques aux autres écritures.
 * Usage : wp eval-file rest-test-transform.php
 */

function hwct_req($method, $route, $params = []) {
    $req = new WP_REST_Request($method, $route);
    $req->set_header('X-Houetor-Token', get_option('hwc_token'));
    foreach ($params as $k => $v) {
        $req->set_param($k, $v);
    }
    return rest_get_server()->dispatch($req);
}

function hwct_blocks($page_id) {
    $r = hwct_req('GET', '/houetor/v1/page-blocks', ['page_id' => $page_id]);
    if ($r->get_status() !== 200) return null;
    $d = $r->get_data();
    foreach ($d['blocks'] as $b) {
        echo "    index=" . $b['index'] . " ref=" . var_export($b['ref'], true) . " " . $b['blockName'] . " : " . substr($b['content'], 0, 45) . "\n";
    }
    return $d;
}

$GLOBALS['T_PASS'] = 0; $GLOBALS['T_FAIL'] = 0;
function hwct_check($label, $ok, $detail = '') {
    if ($ok) { $GLOBALS['T_PASS']++; echo "  PASS  $label $detail\n"; }
    else     { $GLOBALS['T_FAIL']++; echo "  FAIL  $label $detail\n"; }
}

// ---- Préparation : état initial page 2 ----
delete_transient('hwc_ratelimit_2');
$post0 = get_post(2);
$md5_0 = md5($post0->post_content);
$content0 = $post0->post_content;
$revs0 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
global $wpdb;
$table = $wpdb->prefix . 'houetor_connect_actions_log';
echo "===== TRANSFORM: ETAT INITIAL page 2 =====\n";
echo "  md5=" . substr($md5_0, 0, 12) . "... revisions=$revs0\n\n";

// ---- Setup : 2 blocs paragraph avec ref (module=lab) ----
echo "===== T-setup: 2 blocs paragraph avec ref =====\n";
$r = hwct_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/paragraph', 'module' => 'lab',
    'content' => '<p>T bloc A — à transformer</p>', 'position' => 'end',
]);
$refA = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
$r = hwct_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/paragraph', 'module' => 'lab',
    'content' => '<p>T bloc B — à transformer par index</p>', 'position' => 'end',
]);
$refB = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
hwct_check('setup: 2 refs générées', !empty($refA) && !empty($refB), "A=$refA B=$refB");
$blocks1 = hwct_blocks(2);
$hash1 = $blocks1['content_md5'];

// ---- T1 : paragraph -> heading par ref, ref préservée ----
echo "\n===== T-1: transform paragraph -> core/heading (par ref) =====\n";
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'ref' => $refA, 'target_block_name' => 'core/heading', 'expected_hash' => $hash1,
]);
$d = $r->get_data();
$ok = $r->get_status() === 200 && ($d['blockName'] ?? '') === 'core/paragraph' && ($d['target_blockName'] ?? '') === 'core/heading' && ($d['ref'] ?? '') === $refA;
hwct_check('transform 200 + blockName/target/ref', $ok, "blockName={$d['blockName']} target={$d['target_blockName']} ref={$d['ref']}");
$blocks2 = hwct_blocks(2);
$blkA = null;
foreach ($blocks2['blocks'] as $b) { if ($b['ref'] === $refA) $blkA = $b; }
hwct_check('bloc A est maintenant core/heading (ref conservée)', $blkA && $blkA['blockName'] === 'core/heading', "name=" . ($blkA['blockName'] ?? 'null') . " content={$blkA['content']}");
hwct_check('contenu texte préservé (sans tags)', $blkA && strpos($blkA['content'], 'à transformer') !== false, "content={$blkA['content']}");

// ---- T2 : heading -> paragraph par index ----
echo "\n===== T-2: transform heading -> core/paragraph (par index) =====\n";
$idxA = null;
foreach ($blocks2['blocks'] as $b) { if ($b['ref'] === $refA) $idxA = $b['index']; }
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'block_index' => $idxA, 'target_block_name' => 'core/paragraph', 'expected_hash' => $blocks2['content_md5'],
]);
$d = $r->get_data();
hwct_check('transform par index 200 + paragraph', $r->get_status() === 200 && ($d['target_blockName'] ?? '') === 'core/paragraph', "target={$d['target_blockName']}");
$blocks3 = hwct_blocks(2);
$blkA2 = null;
foreach ($blocks3['blocks'] as $b) { if ($b['ref'] === $refA) $blkA2 = $b; }
hwct_check('bloc A redevenu core/paragraph', $blkA2 && $blkA2['blockName'] === 'core/paragraph', "name=" . ($blkA2['blockName'] ?? 'null'));

// ---- T3 : paragraph -> quote ----
echo "\n===== T-3: transform paragraph -> core/quote =====\n";
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'ref' => $refB, 'target_block_name' => 'core/quote', 'expected_hash' => $blocks3['content_md5'],
]);
$d = $r->get_data();
hwct_check('paragraph -> quote 200', $r->get_status() === 200 && ($d['target_blockName'] ?? '') === 'core/quote');
$blocks4 = hwct_blocks(2);
$blkB = null;
foreach ($blocks4['blocks'] as $b) { if ($b['ref'] === $refB) $blkB = $b; }
hwct_check('bloc B est core/quote (ref conservée)', $blkB && $blkB['blockName'] === 'core/quote', "name=" . ($blkB['blockName'] ?? 'null'));

// ---- T4 : refus cible media (core/image) ----
echo "\n===== T-4: refus cible non texte (core/image) =====\n";
$revs_before4 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'ref' => $refB, 'target_block_name' => 'core/image', 'expected_hash' => $blocks4['content_md5'],
]);
$ok = $r->get_status() === 400 && strpos($r->get_data()['message'], 'non supporté pour la transformation') !== false;
hwct_check('core/image refusé (400 + message)', $ok, "status=" . $r->get_status());
$post_after4 = get_post(2);
$revs_after4 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwct_check('aucune écriture ni révision', md5($post_after4->post_content) === $blocks4['content_md5'] && $revs_after4 === $revs_before4);

// ---- T5 : refus source non texte (core/button) ----
echo "\n===== T-5: refus source non texte (core/button) =====\n";
$r = hwct_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/button', 'module' => 'labbtn',
    'content' => 'Bouton test', 'position' => 'end', 'expected_hash' => $blocks4['content_md5'],
]);
$refBtn = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
hwct_check('setup: bouton créé avec ref', !empty($refBtn));
$blocks5 = hwct_blocks(2);
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'ref' => $refBtn, 'target_block_name' => 'core/heading', 'expected_hash' => $blocks5['content_md5'],
]);
$ok = $r->get_status() === 400 && strpos($r->get_data()['message'], 'non transformable') !== false;
hwct_check('source core/button refusée (400 + message)', $ok, "status=" . $r->get_status());
$post_after5 = get_post(2);
hwct_check('aucune écriture', md5($post_after5->post_content) === $blocks5['content_md5']);

// ---- T6 : ref introuvable -> 404 ----
echo "\n===== T-6: ref introuvable -> 404 =====\n";
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'ref' => 'lab-inexistante00', 'target_block_name' => 'core/paragraph',
]);
hwct_check('ref inconnue -> 404', $r->get_status() === 404, "status=" . $r->get_status());

// ---- T7 : CAS périmé -> 409 ----
echo "\n===== T-7: expected_hash périmé -> 409 =====\n";
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'ref' => $refB, 'target_block_name' => 'core/paragraph', 'expected_hash' => 'aaaa0000000000000000000000000000',
]);
$ok = $r->get_status() === 409 && !empty($r->get_data()['message']);
hwct_check('CAS faux -> 409 + message relecture', $ok, "status=" . $r->get_status());
$post_after7 = get_post(2);
$revs_after7 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwct_check('rien écrit ni révisé', md5($post_after7->post_content) === md5($post_after5->post_content));

// ---- T8 : dry_run ----
echo "\n===== T-8: dry_run (quote -> paragraph) =====\n";
$blocks6 = hwct_blocks(2);
$audit_before = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE action_type = 'transform_block'");
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'ref' => $refB, 'target_block_name' => 'core/paragraph', 'expected_hash' => $blocks6['content_md5'], 'dry_run' => true,
]);
$d = $r->get_data();
hwct_check('dry_run -> 200 + dry_run=true', $r->get_status() === 200 && !empty($d['dry_run']), "dry_run=" . var_export($d['dry_run'], true));
$post_after8 = get_post(2);
$revs_after8 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
$audit_after = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE action_type = 'transform_block'");
hwct_check('dry_run: aucune écriture ni révision ni audit', md5($post_after8->post_content) === $blocks6['content_md5'] && $revs_after8 === $revs_after7 && $audit_after === $audit_before);

// ---- T9 : exécution réelle quote -> paragraph + audit ----
echo "\n===== T-9: exécution réelle + audit =====\n";
delete_transient('hwc_ratelimit_2'); // fenêtre rate limit (toutes les tentatives comptent)
$r = hwct_req('POST', '/houetor/v1/blocks/transform', [
    'page_id' => 2, 'ref' => $refB, 'target_block_name' => 'core/paragraph', 'expected_hash' => $blocks6['content_md5'],
]);
hwct_check('quote -> paragraph 200', $r->get_status() === 200);
$audit_rows = $wpdb->get_results($wpdb->prepare("SELECT action_type, before_json, after_json FROM $table WHERE action_type = 'transform_block' AND before_json LIKE %s ORDER BY id DESC LIMIT 1", '%' . $wpdb->esc_like($refB) . '%'));
hwct_check('audit transform_block présent (avec ref B)', count($audit_rows) > 0, count($audit_rows) > 0 ? "before=" . substr($audit_rows[0]->before_json, 0, 90) : '');

// ---- T10 : imbriqué refusé (bloc natif #1 quote imbriqué) ----
echo "\n===== T-10: bloc imbriqué refusé =====\n";
delete_transient('hwc_ratelimit_2'); // fenêtre rate limit (toutes les tentatives comptent)
$blocks7 = hwct_blocks(2);
$nested_idx = null;
foreach ($blocks7['blocks'] as $b) {
    if ($b['blockName'] === 'core/quote' && $b['index'] < 5 && $b['ref'] === null) { $nested_idx = $b['index']; break; }
}
if ($nested_idx !== null) {
    $r = hwct_req('POST', '/houetor/v1/blocks/transform', [
        'page_id' => 2, 'block_index' => $nested_idx, 'target_block_name' => 'core/paragraph', 'expected_hash' => $blocks7['content_md5'],
    ]);
    $msg = (string) $r->get_data()['message'];
    $ok = $r->get_status() === 400 && (strpos($msg, 'conteneur') !== false || strpos($msg, 'imbriqué') !== false || strpos($msg, 'imbriqués') !== false);
    hwct_check('bloc imbriqué refusé (400 + message conteneur/imbriqué)', $ok, "status=" . $r->get_status());
} else {
    hwct_check('bloc imbriqué refusé (bloc natif introuvable)', false, 'aucun bloc quote natif imbriqué — env modifié');
}

// ---- Nettoyage : suppression bouton + restauration page 2 ----
echo "\n===== T-cleanup =====\n";
if (!empty($refBtn)) {
    hwct_req('DELETE', '/houetor/v1/blocks', ['page_id' => 2, 'ref' => $refBtn, 'expected_hash' => $blocks7['content_md5']]);
    echo "  (bloc bouton supprimé)\n";
}
$restored = false;
foreach (wp_get_post_revisions(2, ['numberposts' => -1]) as $rev) {
    if (md5($rev->post_content) === $md5_0) {
        wp_restore_post_revision($rev->ID);
        $restored = true;
        break;
    }
}
if (!$restored) {
    wp_update_post(['ID' => 2, 'post_content' => wp_slash($content0)], true);
}
$final = get_post(2);
echo "  md5 final page 2 : " . md5($final->post_content) . " (initial " . $md5_0 . ") -> " . (md5($final->post_content) === $md5_0 ? 'IDENTIQUE' : 'DIFFERENT!') . "\n";

echo "\n===== BILAN TRANSFORM : " . $GLOBALS['T_PASS'] . " PASS / " . $GLOBALS['T_FAIL'] . " FAIL =====\n";
exit($GLOBALS['T_FAIL'] > 0 ? 1 : 0);
