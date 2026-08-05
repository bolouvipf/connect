<?php
/**
 * Lab HOUETOR Connect — Tests v3 (plugin 2.4.0)
 * POST /blocks/batch-update (atomique, 1 révision) + dry_run sur toutes les routes d'écriture
 */
define('HWC_TEST_TOKEN', get_option('hwc_token', ''));

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
        echo "    index=" . $b['index'] . " ref=" . var_export($b['ref'], true) . " " . $b['blockName'] . " : " . substr($b['content'], 0, 55) . "\n";
    }
    return $d;
}

$GLOBALS['V3_PASS'] = 0; $GLOBALS['V3_FAIL'] = 0;
function hwc_check($label, $ok, $detail = '') {
    if ($ok) { $GLOBALS['V3_PASS']++; echo "  PASS  $label $detail\n"; }
    else     { $GLOBALS['V3_FAIL']++; echo "  FAIL  $label $detail\n"; }
}

// ---- Préparation : état initial page 2 (md5 + révisions) ----
delete_transient('hwc_ratelimit_2'); // reset fenêtre rate limit (outil de test, pas l'API)
$post0 = get_post(2);
$md5_0 = md5($post0->post_content);
$content0 = $post0->post_content;
$revs0 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
echo "===== V3: ETAT INITIAL page 2 =====\n";
echo "  md5=" . substr($md5_0, 0, 12) . "... revisions=" . $revs0 . "\n\n";

// ---- Setup : 2 blocs avec module (refs stables) pour les tests batch ----
echo "===== V3-setup: creation de 2 blocs avec ref (module=lab) =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/paragraph', 'module' => 'lab',
    'content' => '<p>V3 bloc A — contenu initial</p>', 'position' => 'end',
]);
$refA = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
$r = hwc_test_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/paragraph', 'module' => 'lab',
    'content' => '<p>V3 bloc B — contenu initial</p>', 'position' => 'end',
]);
$refB = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
hwc_check('setup: 2 refs genérées', !empty($refA) && !empty($refB), "A=$refA B=$refB");
$blocks1 = hwc_blocks(2);
$revs_before_batch = count(wp_get_post_revisions(2, ['numberposts' => -1]));
$rl_before_batch = get_transient('hwc_ratelimit_2');
echo "\n";

// ---- Batch : cas nominal ----
echo "===== V3-1: batch-update 2 refs -> 1 révision, all-or-nothing =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/batch-update', [
    'page_id' => 2,
    'updates' => [
        ['ref' => $refA, 'new_content' => '<p>V3 A — modifie par BATCH</p>'],
        ['ref' => $refB, 'new_content' => '<p>V3 B — modifie par BATCH</p>'],
    ],
    'expected_hash' => $blocks1['content_md5'],
]);
$ok = $r->get_status() === 200 && ($r->get_data()['count'] ?? 0) === 2 && empty($r->get_data()['dry_run']);
hwc_check('batch nominal: 200 + count=2 + non dry_run', $ok, wp_json_encode($r->get_data()));
$revs_after_batch = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwc_check('batch: exactement +1 révision', $revs_after_batch === $revs_before_batch + 1, "revs $revs_before_batch -> $revs_after_batch");
$rl_after_batch = get_transient('hwc_ratelimit_2');
$rl_expected = $rl_before_batch ? $rl_before_batch['count'] + 1 : 2;
hwc_check('batch: compte exactement 1 écriture rate limit', ($rl_after_batch['count'] ?? 0) === $rl_expected, "count " . ($rl_before_batch['count'] ?? 'n/a') . " -> " . ($rl_after_batch['count'] ?? 'n/a'));
$blocks2 = hwc_blocks(2);
echo "\n";

// ---- Batch : ref invalide -> all-or-nothing ----
echo "===== V3-2: batch avec ref invalide -> echec, AUCUNE ecriture =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/batch-update', [
    'page_id' => 2,
    'updates' => [
        ['ref' => $refA, 'new_content' => '<p>V3 A — ne doit PAS etre applique</p>'],
        ['ref' => 'lab-ref-inexistante', 'new_content' => '<p>x</p>'],
    ],
    'expected_hash' => $blocks2['content_md5'],
]);
$ok = $r->get_status() === 404 && strpos((string) $r->get_data()['message'], 'abandonné') !== false;
hwc_check('batch ref invalide: 404 + abandon (introuvable -> 404, cohérent)', $ok);
$post_after_fail = get_post(2);
hwc_check('batch ref invalide: md5 inchange (rien ecrit)', md5($post_after_fail->post_content) === $blocks2['content_md5']);
$revs_after_fail = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwc_check('batch ref invalide: pas de nouvelle revision', $revs_after_fail === $revs_after_batch, "revs=$revs_after_fail");
echo "\n";

// ---- Batch : CAS KO ----
echo "===== V3-3: batch CAS faux -> 409, rien ecrit =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/batch-update', [
    'page_id' => 2,
    'updates' => [['ref' => $refA, 'new_content' => '<p>CAS KO</p>']],
    'expected_hash' => 'hash-faux-volontairement',
]);
hwc_check('batch CAS faux: 409 error_conflict', $r->get_status() === 409);
$post_after_cas = get_post(2);
hwc_check('batch CAS faux: md5 inchange', md5($post_after_cas->post_content) === $blocks2['content_md5']);
echo "\n";

// ---- Batch : validation params ----
echo "===== V3-4: batch sans ref ni index -> 400 =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/batch-update', [
    'page_id' => 2,
    'updates' => [['new_content' => '<p>rien</p>']],
]);
hwc_check('batch update sans cible: 400', $r->get_status() === 400);

echo "===== V3-5: batch > 50 updates -> 400 =====\n";
$updates51 = [];
for ($i = 0; $i < 51; $i++) $updates51[] = ['ref' => $refA, 'new_content' => "<p>$i</p>"];
$r = hwc_test_req('POST', '/houetor/v1/blocks/batch-update', [
    'page_id' => 2, 'updates' => $updates51,
]);
$ok = $r->get_status() === 400 && strpos((string) $r->get_data()['message'], 'maximum') !== false;
hwc_check('batch 51 updates: 400 (max 50)', $ok);
echo "\n";

// ---- Batch : bloc imbriqué refusé ----
echo "===== V3-setup: bloc group imbrique (via inject) =====\n";
$r = hwc_test_req('POST', '/houetor/v1/inject', [
    'page_id' => 2, 'module' => 'lab',
    'content' => '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>inner p</p><!-- /wp:paragraph --></div><!-- /wp:group -->',
    'position' => 'append',
]);
$blocks3 = hwc_blocks(2);
$nested_idx = null;
foreach ($blocks3['blocks'] as $b) {
    if ($b['blockName'] === 'core/group') $nested_idx = $b['index'];
}
echo "\n===== V3-6: batch ciblant un bloc imbrique -> refuse, rien ecrit =====\n";
if ($nested_idx !== null) {
    $r = hwc_test_req('POST', '/houetor/v1/blocks/batch-update', [
        'page_id' => 2,
        'updates' => [
            ['ref' => $refA, 'new_content' => '<p>V3 A — ne doit PAS etre applique</p>'],
            ['block_index' => $nested_idx, 'new_content' => '<p>imbrique</p>'],
        ],
        'expected_hash' => $blocks3['content_md5'],
    ]);
    $msg = (string) $r->get_data()['message'];
    $ok = $r->get_status() === 400 && (strpos($msg, 'conteneur') !== false || strpos($msg, 'imbriqué') !== false || strpos($msg, 'imbriqués') !== false);
    hwc_check('batch bloc imbriqué: 400 + abandon (message conteneur/imbriqué)', $ok);
    $post_after_nested = get_post(2);
    hwc_check('batch bloc imbriqué: md5 inchange', md5($post_after_nested->post_content) === $blocks3['content_md5']);
} else {
    hwc_check('batch bloc imbriqué: bloc group introuvable', false);
}
echo "\n";

// ---- dry_run : inject ----
echo "===== V3-7: inject dry_run=true -> succès sans ecriture =====\n";
$blocks4 = hwc_blocks(2);
$r = hwc_test_req('POST', '/houetor/v1/inject', [
    'page_id' => 2, 'module' => 'lab', 'position' => 'append',
    'content' => '<p>V3 dry_run — ne doit jamais apparaitre</p>', 'dry_run' => true,
    'expected_hash' => $blocks4['content_md5'],
]);
$ok = $r->get_status() === 200 && ($r->get_data()['dry_run'] ?? false) === true;
hwc_check('inject dry_run: 200 + dry_run=true', $ok);
$post5 = get_post(2);
hwc_check('inject dry_run: md5 inchange (rien ecrit)', md5($post5->post_content) === $blocks4['content_md5']);

// ---- dry_run : update_block_content ----
echo "===== V3-8: update_block_content dry_run=true =====\n";
$r = hwc_test_req('PATCH', '/houetor/v1/block-content', [
    'page_id' => 2, 'ref' => $refA,
    'new_content' => '<p>V3 dry_run — ne doit pas remplacer</p>', 'dry_run' => true,
    'expected_hash' => $blocks4['content_md5'],
]);
$ok = $r->get_status() === 200 && ($r->get_data()['dry_run'] ?? false) === true;
hwc_check('update dry_run: 200 + dry_run=true', $ok);
$post6 = get_post(2);
hwc_check('update dry_run: md5 inchange', md5($post6->post_content) === $blocks4['content_md5']);

// ---- dry_run : create_block ----
echo "===== V3-9: create_block dry_run=true =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/paragraph', 'module' => 'lab',
    'content' => '<p>V3 dry_run — ne doit pas etre cree</p>', 'dry_run' => true,
]);
$ok = $r->get_status() === 201 && ($r->get_data()['dry_run'] ?? false) === true;
hwc_check('create dry_run: 201 + dry_run=true', $ok);
$blocks7 = hwc_blocks(2);
hwc_check('create dry_run: count de blocs inchange', $blocks7['count'] === $blocks4['count'], "count {$blocks4['count']} -> {$blocks7['count']}");
hwc_check('create dry_run: md5 inchange', $blocks7['content_md5'] === $blocks4['content_md5']);
echo "\n";

// ---- dry_run : delete_block ----
echo "===== V3-10: delete_block dry_run=true =====\n";
$r = hwc_test_req('DELETE', '/houetor/v1/blocks', [
    'page_id' => 2, 'ref' => $refB, 'dry_run' => true,
    'expected_hash' => $blocks4['content_md5'],
]);
$ok = $r->get_status() === 200 && ($r->get_data()['dry_run'] ?? false) === true;
hwc_check('delete dry_run: 200 + dry_run=true', $ok);
$blocks8 = hwc_blocks(2);
$refB_still = false;
foreach ($blocks8['blocks'] as $b) if ($b['ref'] === $refB) $refB_still = true;
hwc_check('delete dry_run: ref B toujours presente', $refB_still);
echo "\n";

// ---- dry_run : batch ----
echo "===== V3-11: batch-update dry_run=true (validation complète sans écriture) =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/batch-update', [
    'page_id' => 2,
    'updates' => [
        ['ref' => $refA, 'new_content' => '<p>V3 A — dry run batch</p>'],
        ['ref' => $refB, 'new_content' => '<p>V3 B — dry run batch</p>'],
    ],
    'expected_hash' => $blocks4['content_md5'],
    'dry_run' => true,
]);
$d = $r->get_data();
$ok = $r->get_status() === 200 && ($d['dry_run'] ?? false) === true && ($d['count'] ?? 0) === 2;
hwc_check('batch dry_run: 200 + dry_run=true + count=2', $ok, wp_json_encode($d));
$post9 = get_post(2);
hwc_check('batch dry_run: md5 inchange', md5($post9->post_content) === $blocks4['content_md5']);

// ---- dry_run : CAS vérifié quand même ----
echo "===== V3-12: dry_run avec CAS faux -> 409 (validation incluse) =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks/batch-update', [
    'page_id' => 2,
    'updates' => [['ref' => $refA, 'new_content' => '<p>x</p>']],
    'expected_hash' => 'hash-faux-volontairement',
    'dry_run' => true,
]);
hwc_check('dry_run CAS faux: 409', $r->get_status() === 409);
$post10 = get_post(2);
hwc_check('dry_run CAS faux: md5 inchange', md5($post10->post_content) === $blocks4['content_md5']);

// ---- dry_run : ne consomme PAS le rate limit ----
echo "===== V3-13: 12 dry_run successifs (au-dela du seuil 10/60s) -> tous acceptés =====\n";
$rl_before_dry = get_transient('hwc_ratelimit_2');
$all_ok = true; $n_dry = 12;
for ($i = 0; $i < $n_dry; $i++) {
    $r = hwc_test_req('POST', '/houetor/v1/inject', [
        'page_id' => 2, 'module' => 'lab', 'content' => '<p>dry ' . $i . '</p>',
        'dry_run' => true,
    ]);
    if ($r->get_status() !== 200) { $all_ok = false; break; }
}
$rl_after_dry = get_transient('hwc_ratelimit_2');
hwc_check("$n_dry dry_run acceptés (rate limit non consommé)", $all_ok);
hwc_check('dry_run: compteur rate limit inchange', ($rl_after_dry['count'] ?? 0) === ($rl_before_dry['count'] ?? 0), "count " . ($rl_before_dry['count'] ?? 'n/a') . " -> " . ($rl_after_dry['count'] ?? 'n/a'));
echo "\n";

// ---- dry_run : aucune révision ni audit créés ----
echo "===== V3-14: dry_run ne crée ni révision =====\n";
$revs_before_dry = count(wp_get_post_revisions(2, ['numberposts' => -1]));
$r = hwc_test_req('POST', '/houetor/v1/inject', [
    'page_id' => 2, 'module' => 'lab', 'content' => '<p>dry rev check</p>', 'dry_run' => true,
]);
$revs_after_dry = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwc_check('1 dry_run supplementaire: révisions inchangées', $revs_after_dry === $revs_before_dry, "revs $revs_before_dry -> $revs_after_dry");
global $wpdb;
$table = $wpdb->prefix . 'houetor_connect_actions_log';
$audit_dry = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE action_type = 'dry_run'");
$rows_recent = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE created_at >= '" . date('Y-m-d H:i:s', time() - 120) . "'");
echo "  audit (2 dernières minutes) : $rows_recent lignes\n";
hwc_check('aucune ligne audit dry_run fantome', (int) $audit_dry === 0, "(lignes dry_run: $audit_dry)");
echo "\n";

// ---- Rate limit : fenêtre réinitialisée pour la suite (le test des écritures) ----
echo "===== V3-15: rate limit — la 11e écriture dans la fenêtre doit échouer =====\n";
delete_transient('hwc_ratelimit_2'); // reset fenêtre pour un test propre (outil de test, pas l'API)
$writes = 0; $got429 = false;
for ($i = 0; $i < 12; $i++) {
    $r = hwc_test_req('POST', '/houetor/v1/inject', [
        'page_id' => 2, 'module' => 'lab', 'content' => "<p>rl $i</p>", 'position' => 'append',
    ]);
    if ($r->get_status() === 429) { $got429 = true; break; }
    if ($r->get_status() === 200) $writes++;
}
echo "  écritures acceptées dans la fenêtre : $writes | 429 : " . ($got429 ? 'OUI' : 'NON') . "\n";
hwc_check('rate limit 10/60s: 11e écriture bloquée', $got429 && $writes >= 10, "writes=$writes");
echo "\n";

// ---- Bilan audit (preuve brute) ----
echo "===== V3-16: JOURNAL D'AUDIT (lignes batch + 5 dernières) =====\n";
$batch_rows = $wpdb->get_results("SELECT action_type, before_json, after_json, created_at FROM $table WHERE action_type = 'batch_update_blocks' ORDER BY id DESC LIMIT 3");
foreach ($batch_rows as $row) {
    echo "    [BATCH] " . $row->created_at . " before=" . substr($row->before_json, 0, 80) . " | after=" . substr($row->after_json, 0, 80) . "\n";
}
$has_batch_audit = count($batch_rows) > 0;
hwc_check('audit: ligne batch_update_blocks présente', $has_batch_audit);
$rows = $wpdb->get_results("SELECT action_type, created_at FROM $table ORDER BY id DESC LIMIT 5");
foreach ($rows as $row) {
    echo "    - " . $row->created_at . " [" . $row->action_type . "]\n";
}
echo "\n";

// ---- RESTAURATION page 2 (mécanisme cleanup, hors API) ----
echo "===== V3-cleanup: restauration page 2 =====\n";
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
    echo "  page 2 restaurée par écriture directe (aucune révision du md5 initial) : " . (is_wp_error($upd) ? 'ECHEC' : 'OK') . "\n";
}
$final = get_post(2);
echo "  md5 final page 2 : " . md5($final->post_content) . " (initial " . $md5_0 . ") -> " . (md5($final->post_content) === $md5_0 ? 'IDENTIQUE' : 'DIFFERENT!') . "\n";

echo "\n===== BILAN V3 : " . $GLOBALS['V3_PASS'] . " PASS / " . $GLOBALS['V3_FAIL'] . " FAIL =====\n";
