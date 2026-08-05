<?php
/**
 * Lab HOUETOR Connect — Tests v2 des nouvelles fonctionnalités (chantier Script 1)
 * ref HWC / CAS / rate limit / audit log / positionnement par anchor
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
        echo "    index=" . $b['index'] . " ref=" . var_export($b['ref'], true) . " " . $b['blockName'] . " : " . substr($b['content'], 0, 50) . "\n";
    }
    return $d;
}

echo "===== V2: ETAT INITIAL (page 2 restaurée) =====\n";
$blocks0 = hwc_blocks(2);
echo "\n";

echo "===== V2-1: create_block avec module + position=after + anchor_ref (spec 3.3) =====\n";
// anchor = index 0 (ref null pour bloc natif) -> on cible par index via position after
$r = hwc_test_req('POST', '/houetor/v1/blocks', [
    'page_id'     => 2,
    'block_name'  => 'core/paragraph',
    'content'     => '<p>V2: cree avec ref HWC (after index 0)</p>',
    'position'    => 'after',
    'anchor_index' => 0,
    'module'      => 'lab',
]);
$created_ref = null;
if ($r->get_status() === 201) {
    $d = $r->get_data();
    $created_ref = $d['ref'] ?? null;
    echo "  REF GENERE : " . var_export($created_ref, true) . "\n";
}
echo "\n";

echo "===== V2-2: get_page_blocks — le bloc doit avoir la ref + être entre 0 et le quote =====\n";
$blocks1 = hwc_blocks(2);
$ok_pos = false; $ok_ref = false;
foreach ($blocks1['blocks'] as $i => $b) {
    if ($b['ref'] === $created_ref) {
        $ok_ref = true;
        if ($i === 1 && $blocks1['blocks'][0]['index'] === 0) $ok_pos = true;
    }
}
echo "  ref visible : " . ($ok_ref ? 'OUI' : 'NON') . " | position after index 0 : " . ($ok_pos ? 'OUI' : 'NON') . "\n\n";

echo "===== V2-3: update_block_content par REF (marqueurs préservés attendus) =====\n";
$r = hwc_test_req('PATCH', '/houetor/v1/block-content', [
    'page_id'     => 2,
    'ref'         => $created_ref,
    'new_content' => '<p>V2: modifie par REF — marqueurs HWC preserves</p>',
    'expected_hash' => $blocks1['content_md5'],
]);
echo "\n";

echo "===== V2-4: verification contenu + ref toujours presente =====\n";
$blocks2 = hwc_blocks(2);
echo "\n";

echo "===== V2-5: CAS NEGATIF — expected_hash volontairement faux -> 409 attendu =====\n";
$r = hwc_test_req('PATCH', '/houetor/v1/block-content', [
    'page_id'     => 2,
    'block_index' => 0,
    'new_content' => '<p>NE DOIT JAMAIS ECRIRE</p>',
    'expected_hash' => 'hash-faux-volontairement',
]);
echo "  CAS bloque l'ecriture : " . ($r->get_status() === 409 ? 'OUI (409 error_conflict)' : 'NON (BUG!)') . "\n\n";

echo "===== V2-6: anchor_not_found — ref inconnue -> 404 explicite (jamais de fallback) =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks', [
    'page_id'    => 2,
    'block_name' => 'core/paragraph',
    'content'    => '<p>ne doit pas etre ajoute</p>',
    'position'   => 'after',
    'anchor_ref' => 'lab-ref-inexistante',
]);
echo "  anchor_not_found : " . ($r->get_status() === 404 ? 'OUI' : 'NON (BUG!)') . "\n\n";

echo "===== V2-7: create_block position=before avec anchor_ref (le bloc cree precedemment) =====\n";
$r = hwc_test_req('POST', '/houetor/v1/blocks', [
    'page_id'    => 2,
    'block_name' => 'core/paragraph',
    'content'    => '<p>V2: insere BEFORE le bloc ref</p>',
    'position'   => 'before',
    'anchor_ref' => $created_ref,
    'module'     => 'lab',
]);
echo "\n";

echo "===== V2-8: get_page_blocks — verification ordre (before = avant la ref) =====\n";
$blocks3 = hwc_blocks(2);
$order_ok = false;
foreach ($blocks3['blocks'] as $i => $b) {
    if ($b['ref'] === $created_ref) {
        $prev = $blocks3['blocks'][$i - 1] ?? null;
        if ($prev && strpos($prev['content'], 'insere BEFORE') !== false) $order_ok = true;
    }
}
echo "  bloc before positionne avant la ref : " . ($order_ok ? 'OUI' : 'NON') . "\n\n";

echo "===== V2-9: delete_block par REF =====\n";
$r = hwc_test_req('DELETE', '/houetor/v1/blocks', [
    'page_id' => 2,
    'ref'     => $created_ref,
    'expected_hash' => $blocks3['content_md5'],
]);
echo "\n";

echo "===== V2-10: get_page_blocks — bloc ref supprime =====\n";
$blocks4 = hwc_blocks(2);
$ref_gone = true;
foreach ($blocks4['blocks'] as $b) {
    if ($b['ref'] === $created_ref) $ref_gone = false;
}
echo "  ref supprimee : " . ($ref_gone ? 'OUI' : 'NON') . "\n\n";

echo "===== V2-11: inject avec CAS conflit -> 409 attendu =====\n";
$r = hwc_test_req('POST', '/houetor/v1/inject', [
    'page_id'       => 2,
    'module'        => 'annonces',
    'content'       => '<p>CAS test</p>',
    'position'      => 'append',
    'expected_hash' => 'hash-faux-volontairement',
]);
echo "  inject bloque par CAS : " . ($r->get_status() === 409 ? 'OUI' : 'NON (BUG!)') . "\n\n";

echo "===== V2-12: RATE LIMIT — 10 écritures successives sur la page 3, la 11e doit échouer =====\n";
// page 3 = page "Privacy Policy" (existe sur WP par défaut)
$r = hwc_test_req('GET', '/houetor/v1/pages');
$page3 = 3;
$ok429 = false; $writes = 0; $denied = 0;
for ($i = 0; $i < 12; $i++) {
    $r = hwc_test_req('POST', '/houetor/v1/inject', [
        'page_id'  => $page3,
        'module'   => 'ratelimit',
        'content'  => '<p>rate limit write #' . $i . '</p>',
        'position' => 'append',
    ]);
    if ($r->get_status() === 429) { $ok429 = true; $denied++; break; }
    if ($r->get_status() === 200) $writes++;
}
echo "  écritures acceptées : $writes | 429 rencontré : " . ($ok429 ? 'OUI' : 'NON (BUG!)') . " (seuil 10/60s)\n\n";

echo "===== V2-13: JOURNAL D'AUDIT — contenu de la table =====\n";
global $wpdb;
$table = $wpdb->prefix . 'houetor_connect_actions_log';
$rows = $wpdb->get_results("SELECT action_type, before_json, after_json, created_at FROM $table ORDER BY id DESC LIMIT 8");
echo "  Lignes d'audit : " . count($rows) . "\n";
foreach ($rows as $row) {
    echo "    - " . $row->created_at . " [" . $row->action_type . "] before=" . substr($row->before_json, 0, 80) . " | after=" . substr($row->after_json, 0, 80) . "\n";
}
echo "\n";

echo "===== V2-14: REVISIONS de la page 2 =====\n";
$revisions = wp_get_post_revisions(2, ['numberposts' => 10]);
echo "  Révisions : " . count($revisions) . "\n";

echo "\n===== FIN V2 =====\n";
