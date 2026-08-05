<?php
/**
 * Lab HOUETOR Connect — Test REST complet via WP_REST_Server::dispatch
 * Usage : wp --allow-root eval-file /mnt/c/Users/Kimsh/Desktop/lab/scripts/rest-test.php
 * Contexte : plugin houetor-connect activé dans l'env de test
 */
define('HWC_TEST_TOKEN', 'eHlibQROp3fU00hrR8EFJqJJ0cuM9pJy');

function hwc_test_req($method, $route, $params = [], $token = HWC_TEST_TOKEN, $headers = []) {
    $server = rest_get_server();
    $req = new WP_REST_Request($method, $route);
    if ($token !== null) {
        $req->set_header('X-Houetor-Token', $token);
    }
    foreach ($headers as $k => $v) {
        $req->set_header($k, $v);
    }
    foreach ($params as $k => $v) {
        $req->set_param($k, $v);
    }
    $resp = $server->dispatch($req);
    $body = $resp->get_data();
    printf("[%s] %s %s -> %d | %s\n",
        date('H:i:s'),
        $method,
        $route,
        $resp->get_status(),
        wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
    return $resp;
}

echo "===== TOKEN PRESENT (option hwc_token) =====\n";
$token_stored = get_option('hwc_token', 'ABSENT');
echo "hwc_token = " . $token_stored . "\n\n";

// État de départ de la page 2 — le cleanup final y reviendra (idempotence).
$GLOBALS['hwc_md5_init'] = md5(get_post(2)->post_content);

echo "===== T1: GET /pages (token valide) =====\n";
$r1 = hwc_test_req('GET', '/houetor/v1/pages');
echo "\n";

echo "===== T2: GET /pages (token INVALIDE -> 403 attendu) =====\n";
hwc_test_req('GET', '/houetor/v1/pages', [], 'mauvais-token-xyz');
echo "\n";

echo "===== T3: GET /pages (sans token -> 403 attendu) =====\n";
hwc_test_req('GET', '/houetor/v1/pages', [], null);
echo "\n";

echo "===== T4: GET /page-blocks?page_id=2 (page exemple) =====\n";
hwc_test_req('GET', '/houetor/v1/page-blocks', ['page_id' => 2]);
echo "\n";

echo "===== T5: GET /page-blocks sans page_id (400 attendu) =====\n";
hwc_test_req('GET', '/houetor/v1/page-blocks');
echo "\n";

echo "===== T6: GET /menus (token valide) =====\n";
hwc_test_req('GET', '/houetor/v1/menus');
echo "\n";

echo "===== T7: GET /media =====\n";
hwc_test_req('GET', '/houetor/v1/media');
echo "\n";

echo "===== T8: POST /inject (bloc HWC) =====\n";
$r8 = hwc_test_req('POST', '/houetor/v1/inject', [
    'page_id' => 2,
    'module'  => 'annonces',
    'content' => '<p>Bloc test lab injecte</p>',
    'position' => 'append',
]);
$block_id = null;
if ($r8->get_status() === 200) {
    $data = $r8->get_data();
    $block_id = isset($data['block_id']) ? $data['block_id'] : null;
    echo "block_id genere : " . var_export($block_id, true) . "\n";
}
echo "\n";

echo "===== T9: POST /inject meme block_id (REMPLACE attendu) =====\n";
if ($block_id) {
    hwc_test_req('POST', '/houetor/v1/inject', [
        'page_id' => 2,
        'module'  => 'annonces',
        'block_id' => $block_id,
        'content' => '<p>Bloc test lab MODIFIE</p>',
        'position' => 'append',
    ]);
}
echo "\n";

echo "===== T10: POST /blocks create (insert_after_index=0) =====\n";
$r10 = hwc_test_req('POST', '/houetor/v1/blocks', [
    'page_id'            => 2,
    'block_name'         => 'core/paragraph',
    'content'            => '<p>Paragraphe cree par le lab (after 0)</p>',
    'insert_after_index' => 0,
]);
echo "\n";

echo "===== T11: GET /page-blocks apres creation (verif position) =====\n";
$r11 = hwc_test_req('GET', '/houetor/v1/page-blocks', ['page_id' => 2]);
$created_visible = false;
if ($r11->get_status() === 200) {
    foreach ($r11->get_data()['blocks'] as $b) {
        if (strpos($b['content'], 'cree par le lab') !== false) {
            $created_visible = true;
            echo "FOUND: index=" . $b['index'] . " blockName=" . $b['blockName'] . " content=" . $b['content'] . "\n";
        }
    }
}
echo "Bloc cree visible via get_page_blocks : " . ($created_visible ? 'OUI' : 'NON') . "\n\n";

echo "===== T12: PATCH /block-content (update bloc 1) =====\n";
hwc_test_req('PATCH', '/houetor/v1/block-content', [
    'page_id'     => 2,
    'block_index' => 1,
    'new_content' => '<p>CONTENU MODIFIE par PATCH (index 1)</p>',
]);
echo "\n";

echo "===== T13: GET /page-blocks apres PATCH (verif) =====\n";
hwc_test_req('GET', '/houetor/v1/page-blocks', ['page_id' => 2]);
echo "\n";

echo "===== T14: POST /inject avec position=replace (danger test) =====\n";
hwc_test_req('POST', '/houetor/v1/inject', [
    'page_id'  => 2,
    'module'   => 'produits',
    'content'  => '<p>REPLACE TOTAL - risque ecrasement</p>',
    'position' => 'replace',
]);
echo "\n";

echo "===== T15: GET /page-blocks apres REPLACE (ecrasement?) =====\n";
$r15 = hwc_test_req('GET', '/houetor/v1/page-blocks', ['page_id' => 2]);
echo "\n";

echo "===== T16: Révisions de la page 2 (filet de securite) =====\n";
$revisions = wp_get_post_revisions(2, ['numberposts' => 10]);
echo "Nombre de révisions : " . count($revisions) . "\n";
foreach ($revisions as $rev) {
    echo "  - #" . $rev->ID . " " . $rev->post_date . " auteur=" . $rev->post_author . "\n";
}
echo "\n";

echo "===== T17: POST /uninject (retirer bloc HWC par block_id) =====\n";
if ($block_id) {
    hwc_test_req('POST', '/houetor/v1/uninject', [
        'page_id'  => 2,
        'module'   => 'annonces',
        'block_id' => $block_id,
    ]);
}
echo "\n";

echo "===== T18: DELETE /blocks (supprimer bloc 0) =====\n";
hwc_test_req('DELETE', '/houetor/v1/blocks', [
    'page_id'     => 2,
    'block_index' => 0,
]);
echo "\n";

echo "===== cleanup: restauration page 2 (la série 001 écrase volontairement via position=replace, T14) =====\n";
$md5_initial = $GLOBALS['hwc_md5_init'] ?? md5(get_post(2)->post_content);
$restored = false;
foreach (wp_get_post_revisions(2, ['numberposts' => -1]) as $rev) {
    if (md5($rev->post_content) === $md5_initial) {
        wp_restore_post_revision($rev->ID);
        echo "  page 2 restaurée depuis révision #" . $rev->ID . "\n";
        $restored = true;
        break;
    }
}
$md5_now = md5(get_post(2)->post_content);
echo $restored
    ? "  md5 final page 2 : " . $md5_now . " (initial " . $md5_initial . ") -> IDENTIQUE\n"
    : "  ATTENTION : aucune révision du md5 initial — restauration manuelle via scripts/restore-lab-pages.php\n";

echo "===== FIN =====\n";
