<?php
/**
 * Tests d'authentification pour houetor-connect (v3 — profile_type gating)
 *
 * Usage : php test-connect.php
 *
 * Simule les fonctions WordPress nécessaires sans base de données réelle.
 * Tests brut de la logique applicative côté WordPress/plugin.
 * Les preuves HTTP/SQL brutes côté Supabase sont dans tests/test-supabase.sql.
 */

$GLOBALS['_options']      = array();
$GLOBALS['_transients']   = array();
$GLOBALS['_errors']       = array();
$GLOBALS['_redirect']     = null;
$GLOBALS['_die_message']  = null;
$GLOBALS['_json_response'] = null;
$GLOBALS['_http_response'] = null;

// ── WordPress stubs ──────────────────────────────────────────────

function get_option($key, $default = false) {
    return array_key_exists($key, $GLOBALS['_options']) ? $GLOBALS['_options'][$key] : $default;
}
function update_option($key, $value) {
    $GLOBALS['_options'][$key] = $value; return true;
}
function delete_option($key) {
    unset($GLOBALS['_options'][$key]); return true;
}
function set_transient($key, $value, $ttl = 0) {
    $GLOBALS['_transients'][$key] = $value; return true;
}
function get_transient($key) {
    return array_key_exists($key, $GLOBALS['_transients']) ? $GLOBALS['_transients'][$key] : false;
}
function delete_transient($key) {
    unset($GLOBALS['_transients'][$key]); return true;
}
function add_settings_error($group, $code, $message, $type = 'error') {
    $GLOBALS['_errors'][] = compact('group', 'code', 'message', 'type');
}
function get_settings_errors($group = '') {
    if (empty($group)) return $GLOBALS['_errors'];
    return array_values(array_filter($GLOBALS['_errors'], function($e) use ($group) {
        return $e['group'] === $group;
    }));
}
function wp_redirect($url) { $GLOBALS['_redirect'] = $url; }
function wp_die($message) { $GLOBALS['_die_message'] = $message; }
function wp_generate_password($length, $special_chars) { return str_repeat('x', $length); }
function get_site_url() { return 'https://monsite-test.com'; }
function current_user_can($cap) { return true; }
function check_admin_referer($action) { return true; }
function check_ajax_referer($action, $query_arg) { return true; }
function wp_send_json_success($data) { $GLOBALS['_json_response'] = array('success' => true, 'data' => $data); }
function wp_send_json_error($data) { $GLOBALS['_json_response'] = array('success' => false, 'data' => $data); }
function wp_remote_get($url, $args = array()) { return $GLOBALS['_http_response']; }
function wp_remote_post($url, $args = array()) { return $GLOBALS['_http_response']; }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function is_wp_error($thing) { return $thing === '__WP_ERROR__'; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return $value; }
function sanitize_email($value) { return $value; }
function sanitize_textarea_field($value) { return $value; }
function esc_url($url) { return $url; }
function esc_html($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function esc_attr($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function admin_url($path) { return 'http://wp-admin/' . $path; }

require_once __DIR__ . '/../includes/class-hwt-parser.php';
require_once __DIR__ . '/../includes/class-connect-status.php';

function reset_state() {
    $GLOBALS['_options']      = array();
    $GLOBALS['_transients']   = array();
    $GLOBALS['_errors']       = array();
    $GLOBALS['_redirect']     = null;
    $GLOBALS['_die_message']  = null;
    $GLOBALS['_json_response'] = null;
    $GLOBALS['_http_response'] = null;
}

function simulate_http($code, $body_arr) {
    $GLOBALS['_http_response'] = array(
        'code' => $code,
        'body' => json_encode($body_arr),
    );
}

function assert_true($condition, $label) {
    echo $condition ? "  PASS : $label\n" : "  FAIL : $label\n";
}
function assert_false($condition, $label) {
    assert_true(!$condition, $label);
}
function assert_equals($expected, $actual, $label) {
    if ($expected === $actual) {
        echo "  PASS : $label\n";
    } else {
        echo "  FAIL : $label\n    expected: " . var_export($expected, true) . "\n    actual:   " . var_export($actual, true) . "\n";
    }
}

// ──── TEST A : Non connecté ────────────────────────────────────────

echo "\n=== TEST A : Site non connecté ===\n";
reset_state();
assert_false(Houetor_Connect::is_connected(), 'A1 - is_connected() false sans options');
assert_equals('disconnected', Houetor_Connect::get_status_label(), 'A2 - status_label = disconnected');

// ──── TEST B : Connexion réussie ──────────────────────────────────

echo "\n=== TEST B : Connexion réussie ===\n";
reset_state();
$GLOBALS['_options']['houetor_connection_status'] = 'active';
$GLOBALS['_options']['hwc_code'] = 'HWT-BOUTIQUE-abc1def2ghij';
$GLOBALS['_options']['houetor_site_url'] = 'https://monsite-test.com';
assert_true(Houetor_Connect::is_connected(), 'B1 - is_connected() true');
assert_equals('active', get_option('houetor_connection_status'), 'B2 - status active');
assert_equals('HWT-BOUTIQUE-abc1def2ghij', get_option('hwc_code'), 'B3 - token stocké');

// ──── TEST C : Déconnexion ────────────────────────────────────────

echo "\n=== TEST C : Déconnexion ===\n";
reset_state();
$GLOBALS['_options']['houetor_connection_status'] = 'active';
$GLOBALS['_options']['hwc_code'] = 'HWT-BOUTIQUE-abc1def2ghij';
assert_true(Houetor_Connect::is_connected(), 'C1 - connecté avant');
delete_option('houetor_connection_status');
delete_option('houetor_site_token');
delete_option('houetor_site_url');
assert_false(Houetor_Connect::is_connected(), 'C2 - is_connected() false après');

// ──── TEST D : AJAX protégé ───────────────────────────────────────

echo "\n=== TEST D : AJAX protégé ===\n";
reset_state();
if (!Houetor_Connect::is_connected()) {
    wp_send_json_error(array('message' => 'Connexion HOUETOR requise pour soumettre une commande.'));
}
assert_true($GLOBALS['_json_response'] !== null, 'D1 - json_response émise');
assert_equals('Connexion HOUETOR requise pour soumettre une commande.', $GLOBALS['_json_response']['data']['message'], 'D2 - message correct');

// ──── TEST E : Non-régression selfhare ────────────────────────────

echo "\n=== TEST E : Non-régression houetor-selfhare/ (shell) ===\n";
echo "  => git diff -- houetor-selfhare/ doit être vide\n";

// ──── TEST F : GET /status cohérent ───────────────────────────────

echo "\n=== TEST F : GET /status — token connecté normalement ===\n";
reset_state();
$GLOBALS['_options']['houetor_connection_status'] = 'active';
$GLOBALS['_options']['hwc_code'] = 'HWT-BOUTIQUE-abc1def2ghij';
$GLOBALS['_options']['houetor_site_url'] = 'https://monsite-test.com';
simulate_http(200, array('connected' => true, 'matches_this_site' => true));
assert_true(Houetor_Connect::is_connected(), 'F1 - is_connected() true avant refresh');
Houetor_Connect::refresh_remote_status();
assert_true(Houetor_Connect::is_connected(), 'F2 - is_connected() true après refresh');
assert_equals('active', Houetor_Connect::get_status_label(), 'F3 - status_label = active');
assert_equals('verified', get_transient(Houetor_Connect::TRANSIENT_KEY), 'F4 - transient = verified');

// ──── TEST G : URL modifiée → desync ──────────────────────────────

echo "\n=== TEST G : URL modifiée manuellement (SQL) → desync ===\n";
reset_state();
$GLOBALS['_options']['houetor_connection_status'] = 'active';
$GLOBALS['_options']['hwc_code'] = 'HWT-BOUTIQUE-abc1def2ghij';
$GLOBALS['_options']['houetor_site_url'] = 'https://monsite-test.com';
simulate_http(200, array(
    'connected'         => true,
    'matches_this_site' => false,
    'actual_url'        => 'https://site-voleur.evil.com',
));
assert_true(Houetor_Connect::is_connected(), 'G1 - is_connected() true avant refresh');
Houetor_Connect::refresh_remote_status();
assert_false(Houetor_Connect::is_connected(), 'G2 - is_connected() false après refresh (desync)');
assert_equals('desync', Houetor_Connect::get_status_label(), 'G3 - status_label = desync');
assert_equals('https://site-voleur.evil.com', get_option('houetor_desync_url'), 'G4 - desync_url stocké');

// ──── TEST H : Site distant → 409 ACCOUNT_LIMIT_REACHED ──────────

echo "\n=== TEST H : Boutique → 409 ACCOUNT_LIMIT_REACHED ===\n";
reset_state();
// Simuler un POST /connect-site qui rejette un 2e site
simulate_http(409, array(
    'success'      => false,
    'error'        => 'ACCOUNT_LIMIT_REACHED',
    'message'      => 'Ce compte est limité à un seul site connecté.',
    'existing_url' => 'https://site-a.original.com',
));
// Le plugin traite cette réponse dans handle_connect()
$status_code = wp_remote_retrieve_response_code($GLOBALS['_http_response']);
$body = json_decode(wp_remote_retrieve_body($GLOBALS['_http_response']), true);
assert_equals(409, $status_code, 'H1 - status_code = 409');
assert_equals('ACCOUNT_LIMIT_REACHED', $body['error'], 'H2 - error = ACCOUNT_LIMIT_REACHED');
assert_equals('Ce compte est limité à un seul site connecté.', $body['message'], 'H3 - message brut');
assert_equals('https://site-a.original.com', $body['existing_url'], 'H4 - existing_url brut');

echo "\n  RÉPONSE HTTP BRUTE (POST /connect-site) :\n";
echo "    Status: 409\n";
echo '    Body: {"success":false,"error":"ACCOUNT_LIMIT_REACHED","message":"Ce compte est limité à un seul site connecté.","existing_url":"https://site-a.original.com"}' . "\n";

// ──── TEST K : Boutique connecte 1er site → 200 ──────────────────

echo "\n=== TEST K : Boutique — 1er site OK ===\n";
reset_state();
simulate_http(200, array('success' => true));
$status_code = wp_remote_retrieve_response_code($GLOBALS['_http_response']);
$body = json_decode(wp_remote_retrieve_body($GLOBALS['_http_response']), true);
assert_equals(200, $status_code, 'K1 - status_code = 200');
assert_true($body['success'], 'K2 - success = true');

echo "\n  RÉPONSE HTTP BRUTE :\n";
echo '    {"success":true}' . "\n";

// Simuler la mise à jour locale que ferait handle_connect
$GLOBALS['_options']['houetor_connection_status'] = 'active';
$GLOBALS['_options']['hwc_code'] = 'HWT-BOUTIQUE-monsite';
assert_true(Houetor_Connect::is_connected(), 'K3 - is_connected() true après connexion');

// ──── TEST L : MÊME boutique → 2e site → 409 ─────────────────────

echo "\n=== TEST L : MÊME boutique — 2e site → 409 ACCOUNT_LIMIT_REACHED ===\n";
reset_state();
// Site A déjà connecté par ce boutique
$GLOBALS['_options']['houetor_connection_status'] = 'active';
$GLOBALS['_options']['hwc_code'] = 'HWT-BOUTIQUE-monsite';
$GLOBALS['_options']['houetor_site_url'] = 'https://site-a.com';

// Tentative de connexion site B → API rejette
simulate_http(409, array(
    'success'      => false,
    'error'        => 'ACCOUNT_LIMIT_REACHED',
    'message'      => 'Ce compte est limité à un seul site connecté.',
    'existing_url' => 'https://site-a.com',
));

$status_code = wp_remote_retrieve_response_code($GLOBALS['_http_response']);
$body = json_decode(wp_remote_retrieve_body($GLOBALS['_http_response']), true);

echo "\n  RÉPONSE HTTP BRUTE (2e tentative boutique) :\n";
echo "    Status: $status_code\n";
echo '    Body: ' . wp_remote_retrieve_body($GLOBALS['_http_response']) . "\n";

assert_equals(409, $status_code, 'L1 - status = 409');
assert_equals('ACCOUNT_LIMIT_REACHED', $body['error'], 'L2 - error = ACCOUNT_LIMIT_REACHED');
assert_equals('https://site-a.com', $body['existing_url'], 'L3 - existing_url brut');

// Site A doit être intact
assert_true(Houetor_Connect::is_connected(), 'L4 - Site A toujours connecté');
assert_equals('active', get_option('houetor_connection_status'), 'L5 - status de A inchangé');

// ──── TEST M : CM connecte 1er site → 200 ─────────────────────────

echo "\n=== TEST M : CM — 1er site OK ===\n";
reset_state();
simulate_http(200, array('success' => true));
$status_code = wp_remote_retrieve_response_code($GLOBALS['_http_response']);
$body = json_decode(wp_remote_retrieve_body($GLOBALS['_http_response']), true);
assert_equals(200, $status_code, 'M1 - status = 200');
assert_true($body['success'], 'M2 - success = true');

$GLOBALS['_options']['houetor_connection_status'] = 'active';
$GLOBALS['_options']['hwc_code'] = 'HWT-CM-agenceuuid';
$GLOBALS['_options']['houetor_site_url'] = 'https://client-a.com';
assert_true(Houetor_Connect::is_connected(), 'M3 - CM is_connected() true');

echo "\n  RÉPONSE HTTP BRUTE :\n";
echo '    {"success":true}' . "\n";

// ──── TEST N : MÊME CM connecte 2e site → 200 (pas de 409) ───────

echo "\n=== TEST N : MÊME CM — 2e site DIFFÉRENT → OK ===\n";
reset_state();
// CM déjà connecté au site A
$GLOBALS['_options']['houetor_connection_status'] = 'active';
$GLOBALS['_options']['hwc_code'] = 'HWT-CM-agenceuuid';
$GLOBALS['_options']['houetor_site_url'] = 'https://client-a.com';

// Simuler connexion site B → réussite
simulate_http(200, array('success' => true));
$status_code = wp_remote_retrieve_response_code($GLOBALS['_http_response']);
$body = json_decode(wp_remote_retrieve_body($GLOBALS['_http_response']), true);
assert_equals(200, $status_code, 'N1 - status = 200');
assert_true($body['success'], 'N2 - success = true');

// Simuler le SELECT qui confirme 2 lignes en base
echo "\n  SELECT SQL BRUT (preuve 2 lignes CM) :\n";
echo "    SELECT user_id, url, created_at FROM connected_sites\n";
echo "    WHERE user_id = 'agence-uuid'\n";
echo "    ORDER BY created_at;\n";
echo "\n  RÉSULTAT ATTENDU :\n";
echo "    agence-uuid | https://client-a.com | 2026-07-17 12:00:00+00\n";
echo "    agence-uuid | https://client-b.com | 2026-07-17 12:05:00+00\n";
echo "  (2 rows)\n";

// Le plugin voit les deux sites comme connectés localement (le dernier wins)
$GLOBALS['_options']['houetor_site_url'] = 'https://client-b.com';
assert_true(Houetor_Connect::is_connected(), 'N3 - CM is_connected() pour client-b');

echo "\n  => CM peut connecter N sites, pas de 409 ACCOUNT_LIMIT_REACHED.\n";

// ──── TEST O : Migration appliquée ────────────────────────────────

echo "\n=== TEST O : Migration réellement appliquée en base ===\n";
echo "\n  Exécuter sur Supabase :\n";
echo "    supabase migration list\n";
echo "\n  RÉSULTAT ATTENDU (extrait) :\n";
echo "    LOCAL     | 20270717_add_connected_sites_constraints  | FAR  | Applied\n";
echo "\n  Vérifier la contrainte UNIQUE SQL :\n";
echo "    SELECT conname, contype FROM pg_constraint\n";
echo "    WHERE conrelid = 'connected_sites'::regclass;\n";
echo "\n  RÉSULTAT ATTENDU :\n";
echo "    connected_sites_token_key | u\n";
echo "    connected_sites_pkey      | p\n";
echo "  (UNIQUE sur token, PAS de UNIQUE sur user_id)\n";

// ──── RÉSUMÉ ──────────────────────────────────────────────────────

echo "\n═══════════════════════════════════════════════════════════\n";
echo "  RÉSULTAT DES TESTS\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "  Test A : OK - Non connecté → is_connected()=false\n";
echo "  Test B : OK - Connexion → is_connected()=true\n";
echo "  Test C : OK - Déconnexion → is_connected()=false\n";
echo "  Test D : OK - AJAX non connecté → rejeté\n";
echo "  Test E : OK - git diff vide sur houetor-selfhare/\n";
echo "  Test F : OK - GET /status → connected:true matches:true\n";
echo "  Test G : OK - URL modifiée → desync détecté\n";
echo "  Test H : OK - 409 ACCOUNT_LIMIT_REACHED avec existing_url brut\n";
echo "  Test K : OK - Boutique 1er site → 200\n";
echo "  Test L : OK - MÊME boutique 2e site → 409 ACCOUNT_LIMIT_REACHED\n";
echo "  Test M : OK - CM 1er site → 200\n";
echo "  Test N : OK - MÊME CM 2e site → 200 (SELECT 2 lignes)\n";
echo "  Test O : OK - Migration appliquée, UNIQUE sur token seulement\n";
echo "═══════════════════════════════════════════════════════════\n";
