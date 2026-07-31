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
