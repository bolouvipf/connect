<?php
/**
 * Lab HOUETOR Connect — Tests tier policy (plugin 2.6.0)
 * Refus des blocs legacy à la création avec map de remplacement suggérée :
 * POST /blocks avec block_name legacy -> 400 block_legacy + suggested_block.
 * Usage : wp eval-file rest-test-tierpolicy.php
 */

function hwctp_req($method, $route, $params = []) {
    $req = new WP_REST_Request($method, $route);
    $req->set_header('X-Houetor-Token', get_option('hwc_token'));
    foreach ($params as $k => $v) {
        $req->set_param($k, $v);
    }
    return rest_get_server()->dispatch($req);
}

function hwctp_blocks($page_id) {
    $r = hwctp_req('GET', '/houetor/v1/page-blocks', ['page_id' => $page_id]);
    if ($r->get_status() !== 200) return null;
    return $r->get_data();
}

$GLOBALS['TP_PASS'] = 0; $GLOBALS['TP_FAIL'] = 0;
function hwctp_check($label, $ok, $detail = '') {
    if ($ok) { $GLOBALS['TP_PASS']++; echo "  PASS  $label $detail\n"; }
    else     { $GLOBALS['TP_FAIL']++; echo "  FAIL  $label $detail\n"; }
}

// ---- Préparation : état initial page 2 ----
delete_transient('hwc_ratelimit_2');
$post0 = get_post(2);
$md5_0 = md5($post0->post_content);
$content0 = $post0->post_content;
$revs0 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
global $wpdb;
$table = $wpdb->prefix . 'houetor_connect_actions_log';
$audit0 = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE action_type = 'create_block'");
echo "===== TIER POLICY: ETAT INITIAL page 2 =====\n";
echo "  md5=" . substr($md5_0, 0, 12) . "... revisions=$revs0 audit_create=$audit0\n\n";

// ---- T1 : bloc legacy core/verse refusé avec suggestion ----
echo "===== T-1: core/verse -> 400 block_legacy + suggestion core/preformatted =====\n";
$r = hwctp_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/verse', 'module' => 'lab',
    'content' => 'Le vers reste un vers', 'position' => 'end',
]);
$d = $r->get_data();
$ok = $r->get_status() === 400
    && ($d['code'] ?? '') === 'block_legacy'
    && ($d['data']['suggested_block'] ?? '') === 'core/preformatted'
    && ($d['data']['block_name'] ?? '') === 'core/verse';
hwctp_check('400 + code block_legacy + suggested_block=core/preformatted', $ok, "status=" . $r->get_status() . " code=" . ($d['code'] ?? '') . " sugg=" . ($d['data']['suggested_block'] ?? ''));
$post1 = get_post(2);
$revs1 = count(wp_get_post_revisions(2, ['numberposts' => -1]));
hwctp_check('message contient la suggestion', strpos($d['message'] ?? '', 'core/preformatted') !== false, "msg=" . substr($d['message'] ?? '', 0, 80));
hwctp_check('aucune écriture ni révision', md5($post1->post_content) === $md5_0 && $revs1 === $revs0);

// ---- T2 : bloc legacy renommé core/cover-image -> core/cover ----
echo "\n===== T-2: core/cover-image -> 400 block_legacy + suggestion core/cover =====\n";
$r = hwctp_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/cover-image', 'content' => 'x', 'position' => 'end',
]);
$d = $r->get_data();
$ok = $r->get_status() === 400 && ($d['code'] ?? '') === 'block_legacy' && ($d['data']['suggested_block'] ?? '') === 'core/cover';
hwctp_check('cover-image -> block_legacy + cover', $ok, "code=" . ($d['code'] ?? '') . " sugg=" . ($d['data']['suggested_block'] ?? ''));

// ---- T3 : bloc inconnu (hors map) -> refus générique create_failed ----
echo "\n===== T-3: bloc inconnu -> 400 create_failed message générique =====\n";
$r = hwctp_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/nimporte-quoi', 'content' => 'x', 'position' => 'end',
]);
$d = $r->get_data();
$ok = $r->get_status() === 400 && ($d['code'] ?? '') === 'create_failed' && strpos($d['message'] ?? '', 'non supporté') !== false && empty($d['data']['suggested_block'] ?? null);
hwctp_check('inconnu -> create_failed + message générique, sans suggestion', $ok, "code=" . ($d['code'] ?? '') . " msg=" . substr($d['message'] ?? '', 0, 60));

// ---- T4 : dry_run sur legacy -> toujours 400, aucun effet ----
echo "\n===== T-4: dry_run legacy (core/html) -> 400 block_legacy =====\n";
$r = hwctp_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/html', 'content' => '<div>x</div>', 'position' => 'end', 'dry_run' => true,
]);
$d = $r->get_data();
$ok = $r->get_status() === 400 && ($d['code'] ?? '') === 'block_legacy' && ($d['data']['suggested_block'] ?? '') === 'core/paragraph';
hwctp_check('dry_run core/html -> block_legacy + paragraph', $ok, "code=" . ($d['code'] ?? '') . " sugg=" . ($d['data']['suggested_block'] ?? ''));
$post4 = get_post(2);
hwctp_check('aucune écriture (dry_run + refus)', md5($post4->post_content) === $md5_0);

// ---- T5 : filtre hwc_legacy_blocks personnalisé ----
echo "\n===== T-5: filtre hwc_legacy_blocks (map custom) =====\n";
add_filter('hwc_legacy_blocks', function ($map) {
    $map['core/custom-legacy'] = 'core/list';
    return $map;
});
$r = hwctp_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/custom-legacy', 'content' => 'x', 'position' => 'end',
]);
$d = $r->get_data();
$ok = $r->get_status() === 400 && ($d['code'] ?? '') === 'block_legacy' && ($d['data']['suggested_block'] ?? '') === 'core/list';
hwctp_check('entrée custom -> block_legacy + core/list', $ok, "code=" . ($d['code'] ?? '') . " sugg=" . ($d['data']['suggested_block'] ?? ''));
remove_all_filters('hwc_legacy_blocks');
$r = hwctp_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/custom-legacy', 'content' => 'x', 'position' => 'end',
]);
$d = $r->get_data();
hwctp_check('filtre retiré -> refus générique de nouveau', ($d['code'] ?? '') === 'create_failed', "code=" . ($d['code'] ?? ''));

// ---- T6 : bloc ALLOWED créé normalement (régression positive) ----
echo "\n===== T-6: core/list (ALLOWED) -> 201 + ref =====\n";
delete_transient('hwc_ratelimit_2');
$r = hwctp_req('POST', '/houetor/v1/blocks', [
    'page_id' => 2, 'block_name' => 'core/list', 'module' => 'tplab',
    'content' => "item 1\nitem 2", 'position' => 'end',
]);
$ref = $r->get_status() === 201 ? ($r->get_data()['ref'] ?? null) : null;
hwctp_check('list créé 201 avec ref', $r->get_status() === 201 && !empty($ref), "ref=$ref");
$blocks6 = hwctp_blocks(2);

// ---- T7 : aucun audit create_block sur les refus ----
echo "\n===== T-7: audit create_block intact après les refus =====\n";
$audit7 = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE action_type = 'create_block'");
hwctp_check('aucun audit create_block pour les échecs', $audit7 === $audit0 + 1, "audit=$audit7 (initial=$audit0 +1 création OK)");

// ---- T8 : suppression du bloc de test + restauration ----
echo "\n===== T-8: cleanup =====\n";
if (!empty($ref)) {
    hwctp_req('DELETE', '/houetor/v1/blocks', ['page_id' => 2, 'ref' => $ref, 'expected_hash' => $blocks6['content_md5']]);
    echo "  (bloc list supprimé)\n";
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

echo "\n===== BILAN TIER POLICY : " . $GLOBALS['TP_PASS'] . " PASS / " . $GLOBALS['TP_FAIL'] . " FAIL =====\n";
exit($GLOBALS['TP_FAIL'] > 0 ? 1 : 0);
