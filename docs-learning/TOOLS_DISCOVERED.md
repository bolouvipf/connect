# Tests manuels — Résultats bruts (Règle 14 : preuve, pas de résumé)

## T-SERIE 001 — Test REST complet (routes d'origine) — 2026-07-31
Voir historique : 18 tests (auth 403, parsing, create positionnel, PATCH, révisions).
**BUG confirmé à l'époque** : `inject replace` vidait la page → corrigé (v2.3.0).

## T-SERIE 002 — Nouvelles fonctionnalités v2.3.0 (chantier Script 1) — 2026-07-31

**Méthode :** `wp --allow-root eval-file rest-test-v2.php` (14 tests).

### Résultats (tous PASS)

**V2-1 — create_block module + position=after + anchor_index=0 → 201**
```json
{"success":true,"post_id":2,"ref":"lab-d9a42013888b","message":"Bloc core/paragraph créé dans « Sample Page ». Ref générée : lab-d9a42013888b"}
```

**V2-2 — get_page_blocks : ref visible + position correcte (index 1, entre 0 et le quote) → OUI**

**V2-3 — update par REF → 200** : « Bloc ref "lab-d9a42013888b" (core/paragraph) mis à jour »
**V2-4 — marqueurs HWC préservés : ref toujours visible après update → OUI**

**V2-5 — CAS négatif → 409**
```json
{"code":"error_conflict","message":"Conflit de concurrence : le contenu de la page a changé depuis la lecture...","data":{"status":409}}
```
→ **l'écriture a été bloquée, aucun écrasement silencieux.**

**V2-6 — anchor_ref inconnu → 404 explicite**
```json
{"code":"anchor_not_found","message":"Aucun bloc avec la ref \"lab-ref-inexistante\" trouvé sur la page 2.","data":{"status":404}}
```
→ **aucun fallback silencieux vers append.**

**V2-7 — create position=before + anchor_ref → 201** (ref `lab-87c46f2d064f`)
**V2-8 — ordre vérifié : le nouveau bloc précède bien l'ancre → OUI**

**V2-9 — delete par REF → 200** ; **V2-10 — ref supprimée → OUI**

**V2-11 — inject avec expected_hash faux → 409 error_conflict → OUI**

**V2-12 — rate limit : 10 écritures acceptées sur la page 3, la 11e → 429**
```json
{"code":"rate_limited","message":"Trop d'écritures sur cette page (10/60s max). Réessayez plus tard.","data":{"status":429}}
```

**V2-13 — journal d'audit : lignes présentes avec before/after (md5)**
```
2026-07-31 17:09:35 [inject] before={"page_id":3,"content_md5":"c47a0e..."} | after={"page_id":3,"content_md5":"762c2e..."}
```

**V2-14 — révisions page 2 : 10 révisions présentes**

### Nettoyage post-test
- Page 2 restaurée (5 blocs d'origine, md5 `592dfd9742814297172c5f516bcd40e3` retrouvé)
- Page 3 restaurée (révision #15, avant les injects)
- `php -l` : 0 erreur sur 14 fichiers

## Tests à venir
- Tests HTTP complets (curl depuis Windows → en attente du pare-feu Hyper-V, non bloquant : tests via dispatch internes équivalents)
- Non-régression /inject /uninject (routes intactes)

## T-SERIE 003 — Scénarios « exaucés exactement » via le MCP miroir (Phase 3) — 2026-08-01

**Méthode :** `node scripts/scenarios-test.mjs` (JSON-RPC HTTP vers le MCP lab :8892, qui appelle le WP lab :8888). Chaque scénario = demande utilisateur réaliste : relecture → écriture → vérification.

**Résultat : 24/24 PASS** (preuve brute dans le run, extraits ci-dessous).

**S1 « Ajoute un bloc avantage juste avant le pied de page »** : `create_block` position=before anchor_index → 200 + ref, bloc visible en avant-dernière position → PASS
**S2 « Corrige le prix dans le bloc annonce »** : `update_block_content` par index avec expected_hash frais → 200, relecture confirme « 99 € » → PASS
**S3 « Fais une répétition générale avant de publier »** : `dry_run:true` → `dry_run:true`, md5 inchangé, bloc A intact → PASS
**S4 « Fais ces deux corrections en une seule fois »** : `update_blocks` batch count=2, relecture confirme les 2 corrections → PASS
**S5 « Supprime l'ancienne offre »** : `delete_block` par ref → 200, relecture confirme disparition → PASS
**S6 « Un autre agent a modifié la page »** : écriture concurrente puis update avec hash périmé → **409 traduit** (« Re-lisez la page (get_page_blocks…) pour obtenir un expected_hash à jour »), l'écriture périmée n'a PAS écrasé, relecture + hash frais → 200 → PASS
**SSE** : `update_blocks` listé dans les tools → PASS

**Preuves plugin (audit + révisions)** — 6 dernières lignes du journal `houetor_connect_actions_log` :
```
162 [batch_update_blocks] 07:55:09 before={"page_id":2,"count":1,"content_md5":"5756ab…"} | after={"page_id":2,"count":1,"content_md5":"00b3f0…"}
161 [delete_block] 07:54:55 before={"page_id":2,"block_index":null,"ref":"autre-agent-1ac982381501",…} | after={"page_id":2,"content_md5":"5756ab…"}
160 [update_block] 07:54:45 before={"page_id":2,"block_index":0,"ref":null,"content_md5":"136d4d…"} | after={"page_id":2,"content_md5":"95ec01…"}
159 [create_block] 07:54:28 before={"page_id":2,"block_name":"core\/paragraph",…} | after={"page_id":2,"ref":"autre-agent-1ac982381501",…}
```
- Révisions page 2 présentes (chaque écriture réelle → révision ; dry_run n'en crée aucune).
- Total cumulé table : inject 100x, create_block 28x, update_block 10x, delete_block 14x, batch_update_blocks 10x (historique lab complet).

**Nettoyage post-test** : page 2 restaurée (md5 d'origine `592dfd9742814297172c5f516bcd40e3` via révision), rate limit reset.

**Découvertes** :
- Le bloc natif #1 de la page 2 est un `core/quote` avec blocs imbriqués → refusé par design (« blocs imbriqués ») : le scénario cible le bloc #0 (paragraph). Comportement attendu et déjà couvert par V3-6.
- La restauration d'un bloc via `update_block_content`/batch repasse par `wp_kses_post` → sérialisation reformatée (md5 différent du contenu d'origine) ; la restauration EXACTE se fait par restauration de révision (wp eval-file).
