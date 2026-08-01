#!/bin/bash
# Suite de vérification du miroir MCP contre le WP lab (exécuter dans WSL).
# Lecture du token depuis la base WP lab (jamais affiché), reset rate limit, puis 3 batteries.
set -e
WP_DIR=/mnt/c/Users/Kimsh/Desktop/lab/wordpress-test-env
MCP_DIR=/mnt/c/Users/Kimsh/Desktop/lab/connect/houetor-mcp

if ! curl -s -o /dev/null --max-time 40 http://127.0.0.1:8888/wp-json/; then
  echo "ERREUR: WP lab injoignable sur 127.0.0.1:8888 (service wp-dev-server?)"
  exit 1
fi

TOKEN=$(cd "$WP_DIR" && wp --allow-root option get hwc_token)
if [ -z "$TOKEN" ]; then
  echo "ERREUR: hwc_token vide"
  exit 1
fi

cd "$WP_DIR" && wp --allow-root option delete _transient_hwc_ratelimit_2 >/dev/null 2>&1 || true

cd "$MCP_DIR"
echo "== 1. Unitaires (attendu 24/24) =="
npm test 2>&1 | tail -8
echo
echo "== 2. Integration vs WP lab (attendu 28/28) =="
WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN="$TOKEN" npm run test:integration 2>&1 | tail -10
echo
echo "== 3. Scenarios Phase 3 via MCP miroir (attendu 24/24) =="
WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN="$TOKEN" node scripts/scenarios-test.mjs 2>&1 | tail -35
