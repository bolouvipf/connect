# Tests manuels — Résultats bruts (Règle 14 : preuve, pas de résumé)

Chaque entrée : commande exacte + sortie brute. 

## T-SERIE 001 — Test REST complet via WP_REST_Server::dispatch (2026-07-31)

**Environnement :** localhost:8888 (env test isolé), plugin activé, token `hwc_token` généré à l'activation.
**Méthode :** `wp --allow-root eval-file /mnt/c/Users/Kimsh/Desktop/lab/scripts/rest-test.php`
(script exerçant chaque route via `rest_get_server()->dispatch()` avec headers simulés).

### Résultats bruts (extraits significatifs)

**T1 — GET /pages (token valide) → 200**
```json
{"pages":[{"id":2,"title":"Sample Page","slug":"sample-page","url":"http://localhost:8888/?page_id=2"}]}
```

**T2 — GET /pages (token invalide) → 403**
```json
{"code":"forbidden","message":"Token invalide.","data":{"status":403}}
```

**T3 — GET /pages (sans token) → 403**
```json
{"code":"forbidden","message":"Token manquant.","data":{"status":403}}
```

**T4 — GET /page-blocks?page_id=2 → 200, 5 blocs parsés**
```json
{"index":0,"blockName":"core/paragraph","content":"This is an example page. It's different from a blog post..."}
{"index":1,"blockName":"core/quote","content":""}
...
"count":5
```

**T8 — POST /inject → 200, block_id auto-généré** : `annonces-6a6cd48139ce0`
**T9 — POST /inject même block_id → 200 (remplacement confirmé)**
**T10 — POST /blocks create (insert_after_index=0) → 201** : « Bloc core/paragraph créé dans Sample Page. »
**T11 — get_page_blocks après création** : le bloc apparaît à l'index 1, entre le bloc 0 et le bloc quote — **positionnement relatif OK**
**T12 — PATCH /block-content (bloc 1) → 200** : « Bloc #1 (core/paragraph) mis à jour dans Sample Page. »
**T13 — contenu modifié visible au get_page_blocks ✓**
**T14 — POST /inject position=replace → 200 MAIS détruit tout le contenu** (voir BUGS_FIXED.md #2)
**T15 — après replace : get_page_blocks → `"blocks": [], "count": 0`** (page vidée)
**T16 — révisions : 5 révisions présentes** (#5 à #9, dates 16:59:45→16:59:54) — filet OK via wp_update_post auto
**T17 — POST /uninject → 200 « Aucun bloc trouvé à retirer »** (bloc avait été détruit par le replace — cohérent)
**T18 — DELETE /blocks sur page vidée → 404 « Bloc #0 introuvable »** (cohérent)

### Actions post-tests
- Restauration page 2 via `wp_restore_post_revision(5)` → 5 blocs d'origine rétablis (vérifié).
- `php -l` sur les 14 fichiers .php du plugin → **0 erreur de syntaxe**.

## Tests à venir (chantier Script 1)
- create_block avec ref HWC + position before/after par anchor
- CAS expected_hash (faux hash → conflit attendu)
- Rate limit (10 écritures/60s)
- Journal d'audit
