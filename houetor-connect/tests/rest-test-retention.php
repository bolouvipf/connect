<?php
/**
 * Lab HOUETOR Connect — Tests rétention du journal d'audit (plugin 2.5.0)
 * Option hwc_audit_retention_days (défaut 90) + CRON quotidien hwc_audit_cleanup
 * + purge chunkée HWC_REST_API::audit_cleanup().
 * Usage : wp eval-file rest-test-retention.php
 */

$GLOBALS['RET_PASS'] = 0; $GLOBALS['RET_FAIL'] = 0;
function ret_check($label, $ok, $detail = '') {
    if ($ok) { $GLOBALS['RET_PASS']++; echo "  PASS  $label $detail\n"; }
    else     { $GLOBALS['RET_FAIL']++; echo "  FAIL  $label $detail\n"; }
}

function ret_insert_row($age_days, $type = 'test_retention') {
    global $wpdb;
    $table = $wpdb->prefix . 'houetor_connect_actions_log';
    $wpdb->insert($table, [
        'action_type' => $type,
        'before_json' => '{}',
        'after_json'  => wp_json_encode(['age_days' => $age_days]),
        'created_at'  => date('Y-m-d H:i:s', time() - $age_days * DAY_IN_SECONDS),
    ]);
    return (int) $wpdb->insert_id;
}

function ret_count_rows($type = 'test_retention') {
    global $wpdb;
    $table = $wpdb->prefix . 'houetor_connect_actions_log';
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE action_type = %s", $type));
}

function ret_rows_aged() {
    global $wpdb;
    $table = $wpdb->prefix . 'houetor_connect_actions_log';
    return $wpdb->get_col($wpdb->prepare(
        "SELECT DATEDIFF(NOW(), created_at) FROM $table WHERE action_type = %s", 'test_retention'
    ));
}

// ---- Préparation ----
echo "===== RETENTION: état initial =====\n";
delete_transient('hwc_ratelimit_2');
// Nettoyage des éventuels résidus de runs précédents (le test est idempotent).
global $wpdb;
$wpdb->delete($wpdb->prefix . 'houetor_connect_actions_log', ['action_type' => 'test_retention']);
// Activation simulée (idempotente) : programme le CRON + initialise l'option.
if (get_option('hwc_audit_retention_days') === false) {
    hwc_activate();
    echo "  (hwc_activate() exécutée)\n";
}
$opt = get_option('hwc_audit_retention_days');
echo "  option hwc_audit_retention_days = " . var_export($opt, true) . "\n";
$sched = wp_next_scheduled('hwc_audit_cleanup');
echo "  CRON hwc_audit_cleanup programmé = " . var_export($sched !== false, true) . "\n\n";

ret_check('option rétention initialisée à 90', (string) $opt === '90', "valeur=$opt");
ret_check('CRON quotidien hwc_audit_cleanup programmé', $sched !== false);

// ---- Test A : défaut 90 jours -> purge les lignes > 90j, garde les fraîches ----
echo "\n===== RET-A: rétention par défaut (90j) =====\n";
$old_id  = ret_insert_row(100);   // 100 jours -> à purger
$fresh_id = ret_insert_row(10);   // 10 jours -> à garder
ret_check('insert lignes 100j + 10j', ret_count_rows() === 2, "count=" . ret_count_rows());
$n = HWC_REST_API::audit_cleanup();
ret_check('cleanup: 1 ligne supprimée (la 100j)', $n === 1, "n=$n");
$remaining = ret_rows_aged();
ret_check('la ligne 100j est purgée, la 10j reste', count($remaining) === 1 && (int) $remaining[0] <= 10, "ages_restants=" . implode(',', $remaining));

// ---- Test B : filtre hwc_audit_retention_days = 30j -> purge aussi les 40j ----
echo "\n===== RET-B: filtre rétention 30j =====\n";
$med_id = ret_insert_row(40);
add_filter('hwc_audit_retention_days', function () { return 30; });
$n = HWC_REST_API::audit_cleanup();
ret_check('cleanup (30j): 1 ligne supprimée (la 40j)', $n === 1, "n=$n");
$remaining = ret_rows_aged();
ret_check('la 10j reste, la 40j purgée', count($remaining) === 1 && (int) $remaining[0] <= 10, "ages_restants=" . implode(',', $remaining));

// ---- Test C : rétention désactivée (<= 0) -> aucune suppression ----
echo "\n===== RET-C: rétention désactivée (0) =====\n";
$keep_id = ret_insert_row(400);
add_filter('hwc_audit_retention_days', function () { return 0; });
$n = HWC_REST_API::audit_cleanup();
ret_check('cleanup désactivé: 0 suppression', $n === 0, "n=$n");
ret_check('la ligne 400j reste (rétention off)', ret_count_rows() === 2, "count=" . ret_count_rows());

// ---- Nettoyage : purger les lignes de test ----
global $wpdb;
$wpdb->delete($wpdb->prefix . 'houetor_connect_actions_log', ['action_type' => 'test_retention']);
echo "\n===== RÉSULTAT =====\n";
echo "RETENTION: {$GLOBALS['RET_PASS']} PASS, {$GLOBALS['RET_FAIL']} FAIL\n";
exit($GLOBALS['RET_FAIL'] > 0 ? 1 : 0);
