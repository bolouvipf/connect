#!/bin/bash
# Suite de tests complete du lab HOUETOR - 1 commande = tout verifier.
# Usage (depuis Windows) :
#   wsl -u root -e bash -c 'bash /mnt/c/Users/Kimsh/Desktop/lab/connect/houetor-connect/tests/test-suite.sh'
# Couvre : 11 batteries PHP (wp eval-file + standalone) + MCP (vitest 42 + integration 52 + scenarios 41).
# Prealables : wp CLI dans la VM, node_modules houetor-mcp installes, serveur WP demarrable (systemctl wp-dev-server).

set -u
LAB=/mnt/c/Users/Kimsh/Desktop/lab
CONNECT=$LAB/connect
TESTS=$CONNECT/houetor-connect/tests
WP_PATH=$LAB/wordpress-test-env
MCP=$CONNECT/houetor-mcp
OUT=/tmp/suite.out
GREEN='\033[32m'; RED='\033[31m'; NC='\033[0m'
SUITE_PASS=0; SUITE_FAIL=0; SECTION=0
FAILED_BATTERIES=""

log() { echo -e "$1"; }
ok()  { log "  ${GREEN}PASS${NC} : $1"; SUITE_PASS=$((SUITE_PASS+1)); }
ko()  { log "  ${RED}FAIL${NC} : $1"; SUITE_FAIL=$((SUITE_FAIL+1)); FAILED_BATTERIES="$FAILED_BATTERIES $1"; }

wpq() { wp --allow-root --path="$WP_PATH" "$@"; }

reset_ratelimit() {
  wpq option delete _transient_hwc_ratelimit_2 >/dev/null 2>&1
  wpq option delete _transient_hwc_ratelimit_3 >/dev/null 2>&1
}

restore_pages() { wpq eval-file "$LAB/scripts/restore-lab-pages.php" >/dev/null 2>&1; }

ensure_server() {
  local http; http=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8888/wp-json 2>/dev/null || echo 000)
  if [ "$http" != "200" ]; then
    log "  (serveur down http=$http - relance...)"
    systemctl restart wp-dev-server >/dev/null 2>&1
    sleep 6
    http=$(curl -s -o /dev/null -w '%{http_code}' http://localhost:8888/wp-json 2>/dev/null || echo 000)
  fi
  [ "$http" = "200" ]
}

battery() { # name file success_marker
  SECTION=$((SECTION+1))
  local name=$1 file=$2 marker=$3
  log "=== $SECTION. $name ==="
  ensure_server || { ko "$name : serveur injoignable"; return; }
  reset_ratelimit
  restore_pages
  wpq eval-file "$TESTS/$file" > "$OUT" 2>&1
  if grep -q "$marker" "$OUT"; then
    local bilan; bilan=$(grep -oE "[0-9]+ PASS / [0-9]+ FAIL" "$OUT" | tail -1)
    ok "$name${bilan:+ : $bilan}"
  else
    ko "$name (marker '$marker' absent, sortie $(wc -c < "$OUT") octets)"
    tail -4 "$OUT" >&2
  fi
}

# --- 0. Preconditions ---------------------------------------------------------
log "=== 0. Preconditions ==="
if ensure_server; then ok "serveur WP up"; else ko "serveur WP injoignable (relance echouee)"; fi
if command -v wp >/dev/null 2>&1; then ok "wp CLI present"; else ko "wp CLI absent"; fi
if [ -d "$MCP/node_modules" ]; then ok "node_modules houetor-mcp present"; else ko "node_modules houetor-mcp absent"; fi
restore_pages && ok "pages 2/3 restaurees (md5 init)"

# --- 1. Batteries wp eval-file ------------------------------------------------
battery "serie 001 (18 tests, idempotente)"     "rest-test.php"                     "IDENTIQUE"
battery "serie 002 (V2, CAS/audit)"             "rest-test-v2.php"                  "FIN V2"
battery "V3 blocs imbriques (32)"               "rest-test-v3.php"                  "32 PASS / 0 FAIL"
battery "transform (21)"                        "rest-test-transform.php"           "21 PASS / 0 FAIL"
battery "nested-child-native (11)"              "rest-test-nested-child-native.php" "11 PASS / 0 FAIL"
battery "structural ops (42)"                   "rest-test-structural.php"          "42 PASS / 0 FAIL"
battery "retention audit (9)"                   "rest-test-retention.php"           "RETENTION: 9 PASS, 0 FAIL"
battery "tier policy (11)"                      "rest-test-tierpolicy.php"          "11 PASS / 0 FAIL"

# --- 2. Harnesses standalone (php hors WP) ------------------------------------
SECTION=$((SECTION+1)); log "=== $SECTION. Harnesses standalone ==="
php "$TESTS/test-nested-block-depth1.php" > "$OUT" 2>&1
grep -q "TOUS LES TESTS PASSENT" "$OUT" && ok "depth1 : $(tail -1 "$OUT")" || { ko "depth1"; tail -3 "$OUT" >&2; }
php "$TESTS/test-nested-block-depth2-refs.php" > "$OUT" 2>&1
grep -q "TOUS LES TESTS PASSENT" "$OUT" && ok "depth2-refs : $(tail -1 "$OUT")" || { ko "depth2-refs"; tail -3 "$OUT" >&2; }
php "$TESTS/test-connect-run.php" > "$OUT" 2>&1
if [ "$(grep -c 'FAIL :' "$OUT")" = "0" ] && grep -q "PASS :" "$OUT"; then
  ok "test-connect : $(grep -c 'PASS :' "$OUT") PASS / 0 FAIL"
else
  ko "test-connect : $(grep -c 'FAIL :' "$OUT") FAIL(s)"
  tail -4 "$OUT" >&2
fi

# --- 3. MCP (vitest + integration + scenarios) ---------------------------------
SECTION=$((SECTION+1)); log "=== $SECTION. MCP ==="
cat > /tmp/hwc-get-token.php <<'PHPEOF'
<?php echo get_option('hwc_token'); ?>
PHPEOF
TOKEN=$(wpq eval-file /tmp/hwc-get-token.php 2>/dev/null | tr -d '\n')
[ -n "$TOKEN" ] && ok "token recupere (len=${#TOKEN})" || { ko "token vide"; TOKEN=x; }

if (cd "$MCP" && npm test > "$OUT" 2>&1); then
  ok "vitest : $(grep -oE '[0-9]+ passed' "$OUT" | tail -1)"
else
  ko "vitest : $(grep -oE '[0-9]+ (passed|failed)' "$OUT" | tail -1)"
fi

ensure_server || ko "serveur down avant integration"
reset_ratelimit
if (cd "$MCP" && WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN="$TOKEN" timeout 900 node scripts/integration-test.mjs > "$OUT" 2>&1); then
  grep -qE "52/52 PASS|0 FAIL" "$OUT" && ok "integration : $(grep -oE '[0-9]+/[0-9]+ PASS' "$OUT" | tail -1)" || { ko "integration"; tail -3 "$OUT" >&2; }
else
  ko "integration : exit/blocked ($(tail -2 "$OUT" | tr '\n' ' '))"
fi

ensure_server || ko "serveur down avant scenarios"
reset_ratelimit
if (cd "$MCP" && WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN="$TOKEN" timeout 1200 node scripts/scenarios-test.mjs > "$OUT" 2>&1); then
  grep -qE "41/41 PASS|0 FAIL" "$OUT" && ok "scenarios : $(grep -oE '[0-9]+/[0-9]+ PASS' "$OUT" | tail -1)" || { ko "scenarios"; tail -3 "$OUT" >&2; }
else
  ko "scenarios : exit/blocked ($(tail -2 "$OUT" | tr '\n' ' '))"
fi

# --- 4. Bilan -------------------------------------------------------------------
SECTION=$((SECTION+1)); log "=== $SECTION. Bilan ==="
reset_ratelimit
restore_pages && log "  pages 2/3 restaurees (propre)"
rm -f /tmp/hwc-get-token.php "$OUT"
log "Suite : $SUITE_PASS PASS / $SUITE_FAIL FAIL"
if [ "$SUITE_FAIL" -gt 0 ]; then
  log "  Batteries en echec :$FAILED_BATTERIES"
  exit 1
fi
log "  TOUTE LA SUITE EST VERTE"
exit 0
