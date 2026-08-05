<?php
/**
 * Lab HOUETOR Connect — Tests ops structurelles (plugin 2.7.0)
 * POST /blocks/move, /blocks/duplicate, /blocks/wrap, /blocks/unwrap
 * CAS, dry_run, révisions, audit, refs HWC.
 */
define('HWC_TEST_TOKEN', 'eHlibQROp3fU00hrR8EFJqJJ0cuM9pJy');

function hwc_test_req($method, $route, $params = [], $token = HWC_TEST_TOKEN) {
    $server = rest_get_server();
    $req = new WP_REST_Request($method, $route);
    if ($token !== null) {
        $req->set_header('X-Houetor-Token', $token);
    }
    foreach ($params as $k => $v) {
        $req->set_param($k, $v);
    }
    $resp = $server->dispatch($req);
    printf("[%s] %s %s -> %d | %s\n",
        date('H:i:s'), $method, $route, $resp->get_status(),
        wp_json_encode($resp->get_data(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    return $resp;
}

function hwc_blocks($page_id) {
    $r = hwc_test_req('GET', '/houetor/v1/page-blocks', ['page_id' => $page_id]);
    if ($r->get_status() !== 200) return null;
    $d = $r->get_data();
    echo "  [blocks] count=" . $d['count'] . " content_md5=" . substr($d['content_md5'], 0, 12) . "...\n";
    foreach ($d['blocks'] as $b) {
        echo "    index=" . $b['index'] . " ref=" . var_export($b['ref'], true) . " " . $b['blockName'] . " : " . substr($b['content'], 0, 40) . "\n";
    }
    return $d;
}

function hwc_refs_of($blocks) {
    $refs = [];
    foreach ($blocks['blocks'] as $b) {
        if (!empty($b['ref'])) $refs[] = $b['ref'];
    }
    return $refs;
}

$GLOBALS['ST_PASS'] = 0; $GLOBALS['ST_FAIL'] = 0;
function hwc_check($label, $ok, $detail = '') {
    if ($ok) { $GLOBALS['ST_PASS']++; echo "  PASS  $label $detail\n"; }
    else     { $GLOBALS['ST_FAIL']++; echo "  FAIL  $label $detail\n"; }
}

// Depuis 2.8.0, get_page_blocks expose des index GLOBAUX (blocs imbriqués
// inclus). Les ops structurelles (move/duplicate/wrap/delete) restent au
// niveau racine : elles attendent un index TOP-LEVEL. Conversion ici.
function hwc_toplevel_index($blocks, $global_index) {
    $n = 0;
    foreach ($blocks as $b) {
        if (($b['parent_ref'] ?? null) !== null) continue;
        if (($b['index'] ?? -1) === $global_index) return $n;
        $n++;
    }
    return null;
}

// ---- Préparation : état initial page 2 ----
delete_transient('hwc_ratelimit_2');
$post0 = get_post(2);
$md5_0 = md5($post0->post_content);
$content0 = $post0->post_content;
echo "===== STRUCTURAL: ETAT INITIAL page 2 =====\n";
echo "  md5=" . substr($md5_0, 0, 12) . "...\n\n";

// ---- Setup : 3 blocs avec ref (module=lab) ----
echo "===== ST-setup: 3 blocs (A, B, C) =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks', ['page_id' => 2, 'block_name' => 'core/paragraph', 'module' => 'lab', 'content' => 'Bloc A — structural', 'position' => 'end']);
$refA = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
$r = hwc_test_req('POST', '/houetor/v1/blocks', ['page_id' => 2, 'block_name' => 'core/paragraph', 'module' => 'lab', 'content' => 'Bloc B — structural', 'position' => 'end']);
$refB = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
$r = hwc_test_req('POST', '/houetor/v1/blocks', ['page_id' => 2, 'block_name' => 'core/heading', 'module' => 'lab', 'content' => 'Bloc C — structural', 'position' => 'end']);
$refC = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
hwc_check('setup: 3 refs générées', !empty($refA) && !empty($refB) && !empty($refC), "A=$refA B=$refB C=$refC");
delete_transient('hwc_ratelimit_2');
$blocks1 = hwc_blocks(2);
$n1 = $blocks1['count'];
$revs_after_setup = count(wp_get_post_revisions(2, ['numberposts' => -1]));
echo "\n";

// ---- T1 : move vers end par ref ----
echo "===== T1: move A -> end =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refA, 'position' => 'end', 'expected_hash' => $blocks1['content_md5']]);
$ok = $r->get_status() === 200 && empty($r->get_data()['dry_run']);
$blocks2 = hwc_blocks(2);
$last = $blocks2['blocks'][count($blocks2['blocks']) - 1];
hwc_check('move A->end: 200 + ref A en dernier', $ok && ($last['ref'] ?? null) === $refA, "dernier ref=" . var_export($last['ref'], true));
$revs_after_t1 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwc_check('move A->end: +1 révision', $revs_after_t1 === $revs_after_setup + 1, "revs $revs_after_setup -> $revs_after_t1");
echo "\n";

// ---- T2 : move vers start par index ----
echo "===== T2: move C -> start (par block_index) =====\n";
$idxC = null;
foreach ($blocks2['blocks'] as $b) if (($b['ref'] ?? null) === $refC) $idxC = $b['index'];
$idxC_top = hwc_toplevel_index($blocks2['blocks'], $idxC);
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'block_index' => $idxC_top, 'position' => 'start', 'expected_hash' => $blocks2['content_md5']]);
$blocks3 = hwc_blocks(2);
$first = $blocks3['blocks'][0];
hwc_check('move C->start: 200 + ref C en premier', $r->get_status() === 200 && ($first['ref'] ?? null) === $refC, "premier ref=" . var_export($first['ref'], true));
echo "\n";

// ---- T3 : move before/after avec ancre par ref ----
echo "===== T3: move B -> after A (par ref) =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refB, 'position' => 'after', 'anchor_ref' => $refA, 'expected_hash' => $blocks3['content_md5']]);
$blocks4 = hwc_blocks(2);
$order = hwc_refs_of($blocks4);
$idxA = array_search($refA, $order); $idxB = array_search($refB, $order);
hwc_check('move B after A: 200 + B juste après A', $r->get_status() === 200 && $idxB === $idxA + 1, "ordre=" . implode(',', $order));
echo "\n";

// ---- T4 : move avec ancre == source -> no-op ----
echo "===== T4: move A -> after A (no-op) =====\n";
global $wpdb;
$table = $wpdb->prefix . 'houetor_connect_actions_log';
$audit_before_t4 = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE action_type = 'move_block'");
$revs_before_t4 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refA, 'position' => 'after', 'anchor_ref' => $refA, 'expected_hash' => $blocks4['content_md5']]);
$msg = (string) ($r->get_data()['message'] ?? '');
$revs_after_t4 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
$audit_after_t4 = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE action_type = 'move_block'");
$post_t4 = get_post(2);
hwc_check('move no-op: 200 + message "déjà"', $r->get_status() === 200 && strpos($msg, 'déjà') !== false, $msg);
hwc_check('move no-op: md5 inchange', md5($post_t4->post_content) === $blocks4['content_md5']);
hwc_check('move no-op: aucune révision créée', $revs_after_t4 === $revs_before_t4, "revs $revs_before_t4 -> $revs_after_t4");
hwc_check('move no-op: aucune ligne audit', $audit_after_t4 === $audit_before_t4, "audit $audit_before_t4 -> $audit_after_t4");
echo "\n";

// ---- T5 : validation params ----
echo "===== T5: params invalides =====\n";
delete_transient('hwc_ratelimit_2');
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refA, 'expected_hash' => $blocks4['content_md5']]);
hwc_check('move sans position: 400', $r->get_status() === 400);
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refA, 'position' => 'before', 'expected_hash' => $blocks4['content_md5']]);
hwc_check('move before sans ancre: 400', $r->get_status() === 400);
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => 'lab-ref-inexistante', 'position' => 'start', 'expected_hash' => $blocks4['content_md5']]);
hwc_check('move source introuvable: 404', $r->get_status() === 404);
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refA, 'position' => 'after', 'anchor_ref' => 'lab-ref-inexistante', 'expected_hash' => $blocks4['content_md5']]);
hwc_check('move ancre introuvable: 404 anchor_not_found', $r->get_status() === 404 && ($r->get_data()['code'] ?? '') === 'anchor_not_found');
echo "\n";

// ---- T6 : move CAS KO ----
echo "===== T6: move CAS faux -> 409 =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refA, 'position' => 'end', 'expected_hash' => 'hash-faux']);
hwc_check('move CAS faux: 409', $r->get_status() === 409);
$post_t6 = get_post(2);
hwc_check('move CAS faux: md5 inchange', md5($post_t6->post_content) === $blocks4['content_md5']);
echo "\n";

// ---- T7 : move dry_run ----
echo "===== T7: move dry_run=true =====\n";
delete_transient('hwc_ratelimit_2');
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refA, 'position' => 'start', 'expected_hash' => $blocks4['content_md5'], 'dry_run' => true]);
$ok = $r->get_status() === 200 && ($r->get_data()['dry_run'] ?? false) === true;
$post_t7 = get_post(2);
$rl_after_dry = get_transient('hwc_ratelimit_2');
hwc_check('move dry_run: 200 + dry_run=true + md5 inchange', $ok && md5($post_t7->post_content) === $blocks4['content_md5']);
hwc_check('move dry_run: rate limit non consommé', empty($rl_after_dry), "transient=" . var_export($rl_after_dry, true));
echo "\n";

// ---- T8 : duplicate par ref ----
echo "===== T8: duplicate A (ref régénérée, copie juste après) =====\n";
$revs_before_t8 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
$r = hwc_test_req('POST', '/houetor/v1/blocks/duplicate', ['page_id' => 2, 'ref' => $refA, 'expected_hash' => $blocks4['content_md5']]);
$newRef = $r->get_status() === 200 ? ($r->get_data()['ref'] ?? null) : null;
$blocks5 = hwc_blocks(2);
$idxA5 = null; $idxCopy = null;
foreach ($blocks5['blocks'] as $b) {
    if (($b['ref'] ?? null) === $refA) $idxA5 = $b['index'];
    if (($b['ref'] ?? null) === $newRef) $idxCopy = $b['index'];
}
hwc_check('duplicate: 200 + nouvelle ref != source', $r->get_status() === 200 && !empty($newRef) && $newRef !== $refA, "new=$newRef");
hwc_check('duplicate: copie juste après le source', $idxCopy === $idxA5 + 1, "A@$idxA5 copy@$idxCopy");
hwc_check('duplicate: +1 bloc', $blocks5['count'] === $n1 + 1, "count $n1 -> {$blocks5['count']}");
$revs_after_t8 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwc_check('duplicate: +1 révision', $revs_after_t8 === $revs_before_t8 + 1, "revs $revs_before_t8 -> $revs_after_t8");
$all_refs = hwc_refs_of($blocks5);
hwc_check('duplicate: toutes les refs uniques', count($all_refs) === count(array_unique($all_refs)), "refs=" . implode(',', $all_refs));
echo "\n";

// ---- T9 : duplicate d'un bloc sans ref avec module ----
echo "===== T9: duplicate d'un bloc sans ref + module -> ref générée =====\n";
delete_transient('hwc_ratelimit_2');
$r = hwc_test_req('POST', '/houetor/v1/blocks', ['page_id' => 2, 'block_name' => 'core/quote', 'content' => 'Citation sans ref', 'position' => 'end']);
$idxNoRef = null;
$blocks6 = hwc_blocks(2);
foreach ($blocks6['blocks'] as $b) if ($b['blockName'] === 'core/quote' && empty($b['ref'])) $idxNoRef = $b['index'];
$idxNoRef_top = hwc_toplevel_index($blocks6['blocks'], $idxNoRef);
$r = hwc_test_req('POST', '/houetor/v1/blocks/duplicate', ['page_id' => 2, 'block_index' => $idxNoRef_top, 'module' => 'lab', 'expected_hash' => $blocks6['content_md5']]);
$newRef2 = $r->get_status() === 200 ? ($r->get_data()['ref'] ?? null) : null;
hwc_check('duplicate sans ref + module: ref générée', !empty($newRef2) && strpos($newRef2, 'lab-') === 0, "new=$newRef2");
$blocks7 = hwc_blocks(2);
echo "\n";

// ---- T10 : wrap simple par ref avec module ----
echo "===== T10: wrap A (module=lab -> groupe avec ref) =====\n";
delete_transient('hwc_ratelimit_2');
$idxA7 = null;
foreach ($blocks7['blocks'] as $b) if (($b['ref'] ?? null) === $refA) $idxA7 = $b['index'];
$r = hwc_test_req('POST', '/houetor/v1/blocks/wrap', ['page_id' => 2, 'ref' => $refA, 'module' => 'lab', 'expected_hash' => $blocks7['content_md5']]);
$groupRef = $r->get_status() === 200 ? ($r->get_data()['ref'] ?? null) : null;
$ok = $r->get_status() === 200 && !empty($groupRef) && ($r->get_data()['count'] ?? 0) === 1;
hwc_check('wrap: 200 + ref groupe + count=1', $ok, "group=$groupRef");
$blocks8 = hwc_blocks(2);
$group_idx = null;
foreach ($blocks8['blocks'] as $b) if (($b['ref'] ?? null) === $groupRef) $group_idx = $b['index'];
hwc_check('wrap: groupe core/group visible à la place du bloc', $group_idx !== null && $blocks8['blocks'][$group_idx]['blockName'] === 'core/group', "idx=$group_idx");
hwc_check('wrap: bloc A plus visible au niveau racine (dans le groupe)', count(array_filter($blocks8['blocks'], function ($b) use ($refA) { return ($b['parent_ref'] ?? null) === null && ($b['ref'] ?? null) === $refA; })) === 0);
$post_t10 = get_post(2);
$parsed10 = parse_blocks($post_t10->post_content);
$found_nested = false;
foreach ($parsed10 as $pb) {
    if (($pb['blockName'] ?? '') === 'core/group') {
        foreach ($pb['innerBlocks'] ?? [] as $cb) {
            if (HWC_Block_Editor::extract_hwc_ref($cb) === $refA) $found_nested = true;
        }
    }
}
hwc_check('wrap: ref A préservée dans le groupe (sous-arbre)', $found_nested);
echo "\n";

// ---- T11 : wrap range (2 blocs contigus) + round-trip md5 ----
echo "===== T11: wrap range B..(bloc quote) dans un groupe =====\n";
$idxB8 = null; $idxQ8 = null; $idxG8 = null;
foreach ($blocks8['blocks'] as $b) {
    if (($b['ref'] ?? null) === $refB) $idxB8 = $b['index'];
    if (($b['ref'] ?? null) === $groupRef) $idxG8 = $b['index'];
}
foreach ($blocks8['blocks'] as $b) if ($b['blockName'] === 'core/quote' && empty($b['ref']) && $b['index'] > $idxB8) $idxQ8 = $b['index'];
$idxB8_top = hwc_toplevel_index($blocks8['blocks'], $idxB8);
$idxQ8_top = hwc_toplevel_index($blocks8['blocks'], $idxQ8);
$r = hwc_test_req('POST', '/houetor/v1/blocks/wrap', ['page_id' => 2, 'block_index' => $idxB8_top, 'end_index' => $idxQ8_top, 'expected_hash' => $blocks8['content_md5']]);
$ok = $r->get_status() === 200 && ($r->get_data()['count'] ?? 0) === ($idxQ8_top - $idxB8_top + 1);
hwc_check('wrap range: 200 + count = nb de blocs de la plage', $ok, "range $idxB8_top..$idxQ8_top count=" . ($r->get_data()['count'] ?? 'n/a'));
$blocks9 = hwc_blocks(2);
$reparsed9 = parse_blocks(get_post(2)->post_content);
$roundtrip_ok = md5(serialize_blocks($reparsed9)) === md5(get_post(2)->post_content);
hwc_check('wrap range: round-trip parse/serialize identique', $roundtrip_ok);
$idxG8_top = hwc_toplevel_index($blocks8['blocks'], $idxG8);
$r = hwc_test_req('POST', '/houetor/v1/blocks/wrap', ['page_id' => 2, 'block_index' => $idxB8_top, 'end_index' => $idxG8_top, 'expected_hash' => $blocks9['content_md5']]);
hwc_check('wrap plage inversée: 400 (fin avant début)', $r->get_status() === 400, (string) ($r->get_data()['message'] ?? ''));
$post_t11b = get_post(2);
hwc_check('wrap plage inversée: md5 inchange', md5($post_t11b->post_content) === $blocks9['content_md5']);
echo "\n";

// ---- T12 : wrap dry_run ----
echo "===== T12: wrap dry_run=true =====\n";
delete_transient('hwc_ratelimit_2');
$blocks9b = hwc_blocks(2);
$r = hwc_test_req('POST', '/houetor/v1/blocks/wrap', ['page_id' => 2, 'ref' => $refC, 'module' => 'lab', 'expected_hash' => $blocks9b['content_md5'], 'dry_run' => true]);
$post_t12 = get_post(2);
hwc_check('wrap dry_run: 200 + dry_run=true + md5 inchange', $r->get_status() === 200 && ($r->get_data()['dry_run'] ?? false) === true && md5($post_t12->post_content) === $blocks9b['content_md5']);
echo "\n";

// ---- T13 : unwrap du groupe de A (ref groupe) ----
echo "===== T13: unwrap du groupe (ref) -> A promu =====\n";
delete_transient('hwc_ratelimit_2');
$revs_before_t13 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
$r = hwc_test_req('POST', '/houetor/v1/blocks/unwrap', ['page_id' => 2, 'ref' => $groupRef, 'expected_hash' => $blocks9b['content_md5']]);
$ok = $r->get_status() === 200 && ($r->get_data()['count'] ?? 0) === 1;
hwc_check('unwrap: 200 + count=1', $ok, (string) ($r->get_data()['message'] ?? ''));
$blocks10 = hwc_blocks(2);
$refA_back = false;
foreach ($blocks10['blocks'] as $b) if (($b['ref'] ?? null) === $refA) $refA_back = true;
hwc_check('unwrap: ref A de nouveau visible au niveau racine', $refA_back);
$revs_after_t13 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwc_check('unwrap: +1 révision', $revs_after_t13 === $revs_before_t13 + 1, "revs $revs_before_t13 -> $revs_after_t13");
echo "\n";

// ---- T14 : unwrap cas limites ----
echo "===== T14: unwrap non-groupe + CAS KO + dry_run =====\n";
delete_transient('hwc_ratelimit_2');
$idx_group2 = null;
foreach ($blocks10['blocks'] as $b) if ($b['blockName'] === 'core/group' && empty($b['ref'])) $idx_group2 = $b['index'];
$idx_group2_top = $idx_group2 !== null ? hwc_toplevel_index($blocks10['blocks'], $idx_group2) : null;
$r = hwc_test_req('POST', '/houetor/v1/blocks/unwrap', ['page_id' => 2, 'ref' => $refC, 'expected_hash' => $blocks10['content_md5']]);
hwc_check("unwrap non-groupe: 400 (\"n'est pas un groupe\")", $r->get_status() === 400 && strpos((string) ($r->get_data()['message'] ?? ''), "n'est pas un groupe") !== false);
$r = hwc_test_req('POST', '/houetor/v1/blocks/unwrap', ['page_id' => 2, 'ref' => $groupRef, 'expected_hash' => 'hash-faux']);
hwc_check('unwrap CAS faux: 409', $r->get_status() === 409);
$r = hwc_test_req('POST', '/houetor/v1/blocks/unwrap', ['page_id' => 2, 'block_index' => $idx_group2_top, 'expected_hash' => $blocks10['content_md5'], 'dry_run' => true]);
$post_t14 = get_post(2);
hwc_check('unwrap dry_run: 200 + dry_run=true + md5 inchange', $r->get_status() === 200 && ($r->get_data()['dry_run'] ?? false) === true && md5($post_t14->post_content) === $blocks10['content_md5']);
echo "\n";

// ---- T15 : rate limit — chaque op structurelle = 1 écriture ----
echo "===== T15: rate limit — chaque op structurelle = 1 écriture =====\n";
delete_transient('hwc_ratelimit_2');
$r = hwc_test_req('POST', '/houetor/v1/blocks/duplicate', ['page_id' => 2, 'ref' => $refC, 'expected_hash' => $blocks10['content_md5']]);
$rl = get_transient('hwc_ratelimit_2');
hwc_check('duplicate: compteur rate limit = 1', ($rl['count'] ?? 0) === 1, "count=" . ($rl['count'] ?? 'n/a'));
$blocks11 = hwc_blocks(2);
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refC, 'position' => 'end', 'expected_hash' => $blocks11['content_md5'], 'dry_run' => true]);
$rl2 = get_transient('hwc_ratelimit_2');
hwc_check('move dry_run: compteur toujours 1', ($rl2['count'] ?? 0) === 1, "count=" . ($rl2['count'] ?? 'n/a'));
$r = hwc_test_req('POST', '/houetor/v1/blocks/move', ['page_id' => 2, 'ref' => $refC, 'position' => 'end', 'expected_hash' => $blocks11['content_md5']]);
$rl3 = get_transient('hwc_ratelimit_2');
hwc_check('move réel: compteur = 2', ($rl3['count'] ?? 0) === 2, "count=" . ($rl3['count'] ?? 'n/a'));
echo "\n";

// ---- T16 : audit des ops structurelles ----
echo "===== T16: JOURNAL D'AUDIT =====\n";
$types = ['move_block', 'duplicate_block', 'wrap_block', 'unwrap_block'];
$all_ok = true;
foreach ($types as $t) {
    $n = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE action_type = '$t' AND created_at >= '" . date('Y-m-d H:i:s', time() - 600) . "'");
    echo "    [$t] lignes récentes: $n\n";
    if ((int) $n === 0) $all_ok = false;
}
hwc_check("audit: 4 types d'ops structurelles journalisés", $all_ok);
echo "\n";

// ---- RESTAURATION page 2 ----
echo "===== ST-cleanup: restauration page 2 =====\n";
$restored = false;
$revisions = wp_get_post_revisions(2, ['numberposts' => -1]);
foreach ($revisions as $rev) {
    if (md5($rev->post_content) === $md5_0) {
        wp_restore_post_revision($rev->ID);
        echo "  page 2 restaurée depuis révision (md5 initial retrouvé)\n";
        $restored = true;
        break;
    }
}
if (!$restored) {
    $upd = wp_update_post(['ID' => 2, 'post_content' => wp_slash($content0)], true);
    echo "  page 2 restaurée par écriture directe : " . (is_wp_error($upd) ? 'ECHEC' : 'OK') . "\n";
}
$final = get_post(2);
echo "  md5 final page 2 : " . md5($final->post_content) . " (initial " . $md5_0 . ") -> " . (md5($final->post_content) === $md5_0 ? 'IDENTIQUE' : 'DIFFERENT!') . "\n";

echo "\n===== BILAN STRUCTURAL : " . $GLOBALS['ST_PASS'] . " PASS / " . $GLOBALS['ST_FAIL'] . " FAIL =====\n";
