#!/bin/bash
# Suite de vérification du miroir MCP contre le WP lab (exécuter dans WSL).
# Lecture du token depuis la base WP lab (jamais affiché), reset rate limit,
# restauration des pages de référence, puis 3 batteries.
set -e
WP_DIR=/mnt/c/Users/Kimsh/Desktop/lab/wordpress-test-env
MCP_DIR=/mnt/c/Users/Kimsh/Desktop/lab/connect/houetor-mcp
SCRIPTS_DIR=/mnt/c/Users/Kimsh/Desktop/lab/scripts

if ! curl -s -o /dev/null --max-time 40 http://127.0.0.1:8888/wp-json/; then
  echo "ERREUR: WP lab injoignable sur 127.0.0.1:8888 (service wp-dev-server?)"
  exit 1
fi

TOKEN=$(cd "$WP_DIR" && wp --allow-root option get hwc_token)
if [ -z "$TOKEN" ]; then
  echo "ERREUR: hwc_token vide"
  exit 1
fi

wp_cmd() {
  (cd "$WP_DIR" && wp --allow-root "$@")
}

reset_rl() {
  for p in 2 3; do
    wp_cmd option delete _transient_hwc_ratelimit_$p >/dev/null 2>&1 || true
  done
}

restore_pages() {
  wp_cmd eval-file "$SCRIPTS_DIR/restore-lab-pages.php"
}

reset_rl
restore_pages
echo "== 1. Unitaires =="
(cd "$MCP_DIR" && npm test 2>&1 | tail -6)
echo
echo "== 2. Integration vs WP lab =="
reset_rl
restore_pages
(cd "$MCP_DIR" && WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN="$TOKEN" npm run test:integration 2>&1 | tail -12)
echo
echo "== 3. Scenarios Phase 3 via MCP miroir =="
reset_rl
restore_pages
(cd "$MCP_DIR" && WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN="$TOKEN" node scripts/scenarios-test.mjs 2>&1 | tail -30)
echo
echo "== 4. Restauration finale des pages de référence =="
restore_pages
