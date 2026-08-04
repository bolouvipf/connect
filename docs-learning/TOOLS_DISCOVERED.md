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

## T-SERIE 004 — Plugin 2.5.0 : rétention audit + transform_block + MCP miroir — 2026-08-01

**Méthode :** trois batteries plugin `wp eval-file` (lab) + suite `mirror-suite.sh` (miroir MCP).

### Batterie rétention — `rest-test-retention.php` : **9/9 PASS**
| Test | Résultat |
|---|---|
| RET-A option par défaut = 90 jours | PASS |
| RET-B filtre `hwc_audit_retention_days` = 30 → limite 30 | PASS |
| RET-C option 0 → rétention désactivée (0 supprimé) | PASS |
| Purge par chunks (DELETE LIMIT 500, max 200 itérations), audit table intègre | PASS |

### Batterie transform — `rest-test-transform.php` : **21/21 PASS** (extraits)
- T1-T3 : transform paragraph → heading par ref avec CAS frais → 200 + `target_block_name`, ref HWC **conservée** ; relecture confirme `core/heading`.
- T4 : `level` conservé quand source = heading (heading → paragraph → heading).
- T5-T6 : ref inexistante → 404 « introuvable » ; `block_index` hors borne → 404.
- T7-T8 : source ciblée mais imbriquée (quote natif #4) → 400 « imbriqué » ; source hors whitelist (`core/image`) → 400.
- T9 : cible hors whitelist → 400 ; T10 : cible = `core/paragraph` depuis imbriqué → 400.
- T11 : dry_run → aucune écriture/révision/audit, `dry_run:true`.
- T12-T21 : params manquants → 400 ; rate limit 429 (sauf dry_run) ; audit `transform_block` dans `houetor_connect_actions_log`.

### Miroir MCP — suite `mirror-suite.sh` : **29 unitaires / 33 intégration / 26 scénarios PASS**
- Intégration : transform paragraph→heading (CAS chaîné après écritures), CAS périmé **en dry_run** → 409 traduit (le refus CAS précède le dry_run côté plugin), cible media en dry_run → 400 traduit, retour heading→paragraph.
- Scénario **S7** « Transforme le bloc avantage en titre » : `transform_block` → 200, relecture confirme `core/heading`, ref conservée → PASS ; SSE liste `transform_block`.
- Découverte : le rate limit compte toutes les tentatives (400/409) → reset `delete_transient('hwc_ratelimit_2')` entre batteries ; budget intégration = 10 écritures exactement.

## T-SERIE 005 — Plugin 2.6.0 : tier policy (refus blocs legacy + suggestion) — 2026-08-01

**Méthode :** batterie plugin `rest-test-tierpolicy.php` (11 tests) + régression V3 + suite `mirror-suite.sh` (miroir MCP).

### Batterie tier policy — `rest-test-tierpolicy.php` : **11/11 PASS**

**T-1 — `core/verse` (legacy) → 400 `block_legacy` + suggestion**
```json
{"code":"block_legacy","message":"Bloc core/verse obsolète ou non supporté à la création. Utilisez core/preformatted à la place (même contenu, bloc supporté).","data":{"status":400,"block_name":"core/verse","suggested_block":"core/preformatted"}}
```
→ aucune écriture, aucune révision, aucun audit `create_block`.

**T-2 — `core/cover-image` (renommé) → 400 `block_legacy` + `suggested_block=core/cover` → PASS**
**T-3 — `core/nimporte-quoi` (hors map) → 400 `create_failed` + message « Type de bloc non supporté », SANS suggestion → PASS** (refus générique conservé)
**T-4 — dry_run sur `core/html` → 400 `block_legacy` + `core/paragraph`, aucun effet → PASS**
**T-5 — filtre `hwc_legacy_blocks` : entrée custom `core/custom-legacy`→`core/list` testée, puis retrait du filtre → refus générique → PASS**
**T-6 — `core/list` (ALLOWED) → 201 + ref `tplab-…` → PASS** (régression positive)
**T-7 — audit `create_block` : aucune ligne pour les échecs (compteur = +1 seulement pour la création OK) → PASS**

### Régression — `rest-test-v3.php` : **32/32 PASS** (aucun endpoint existant modifié)

### Miroir MCP — suite `mirror-suite.sh` : **30 unitaires / 35 intégration / 29 scénarios PASS**
- Intégration : `create_block core/verse` **en dry_run** → 400 traduit « Recréez le bloc avec "core/preformatted" à la place… », `error.data.data.suggested_block` propagé (route-handler reflète les data REST) ; aucun bloc créé.
- Scénario **S8** « Ajoute un bloc poème (verse) » : refus `block_legacy` traduit avec suggestion → l'agent (simulé) applique la suggestion (`core/preformatted`, dry_run) → succès → aucun bloc créé → PASS (contrat « la demande s'exécute sans erreur » : l'erreur est actionnable).
- Découverte : en dry_run, le refus tier policy (400) ne consomme PAS le budget rate limit (check_rate_limit sauté en dry_run, refus whitelist avant écriture).

## T-SERIE 006 — Plugin 2.7.0 : ops structurelles move/duplicate/wrap/unwrap — 2026-08-02

**Méthode :** batterie plugin `rest-test-structural.php` (16 tests T1-T16, page 2) + régression V3 + suite `mirror-suite.sh` (miroir MCP, page 3 = Privacy Policy).

### Batterie structurelle — `rest-test-structural.php` : **42 PASS / 0 FAIL**

**T1-T3 — move nominal** : `POST /blocks/move` par ref A→end → 200 + ref A en dernier +1 révision ; par `block_index` C→start → ref C en premier ; B→after A par ref → ordre vérifié (B juste après A).
**T4 — move no-op (ancre == source)** : 200 + message « déjà », **md5 inchangé, AUCUNE révision, AUCUNE ligne audit**.
**T5 — validation** : sans position → 400 ; `before` sans ancre → 400 ; source introuvable → 404 ; ancre introuvable → 404 `anchor_not_found`.
**T6 — CAS faux → 409 `error_conflict`**, md5 inchangé.
**T7 — move dry_run → 200 `dry_run:true` + md5 inchangé + rate limit non consommé** (transient absent).
**T8-T9 — duplicate** : par ref → 200 + nouvelle ref ≠ source + copie juste après +1 bloc +1 révision + **toutes les refs uniques** ; bloc sans ref + module → ref `lab-…` générée.
**T10 — wrap simple (module)** : 200 + ref groupe + `count=1` ; `core/group` visible à la place du bloc ; ref A **préservée dans le sous-arbre** (extract_hwc_ref).
**T11 — wrap range 2 blocs + round-trip** : 200 + count = plage ; `serialize_blocks(parse_blocks())` → md5 identique ; plage inversée → 400 + md5 inchangé.
**T12 — wrap dry_run** : 200 `dry_run:true` + md5 inchangé.
**T13 — unwrap** : 200 + count=1 + ref A de nouveau à la racine +1 révision.
**T14 — cas limites** : unwrap non-groupe → 400 « n'est pas un groupe » ; CAS faux → 409 ; dry_run → 200 + md5 inchangé.
**T15 — rate limit** : duplicate = compteur 1 ; move dry_run = toujours 1 ; move réel = 2 (chaque op structurelle = 1 écriture).
**T16 — audit** : les 4 types (`move_block`, `duplicate_block`, `wrap_block`, `unwrap_block`) journalisés.
**Cleanup** : page 2 restaurée par révision, md5 final = md5 initial.

### Régression — `rest-test-v3.php` : **32/32 PASS** (aucun endpoint existant modifié)

### Miroir MCP — suite `mirror-suite.sh` : **42 unitaires / 52 intégration / 41 scénarios PASS**
- Intégration (page 3, budget rate limit indépendant, 6 écritures) : move réel → 200 + block_index 0 ; duplicate → copie heading juste après ; wrap plage [0..1] → groupe en position 0 ; unwrap → enfants de retour à la racine ; nettoyage (delete copie + move retour) → structure logique restaurée (count initial).
- Erreurs traduites : move source introuvable (dry_run) → 404 + `data.code=move_failed` ; move `before` sans ancre → 400 ; unwrap non-groupe (dry_run) → 400 traduit avec conseil « Ciblez un bloc core/group… ou créez le groupe via wrap_block ».
- Scénarios **S9-S12** : « Remonte ce paragraphe en haut » (move), « Duplique ce titre » (duplicate), « Regroupe ces deux blocs » (wrap), « Dégroupe ce groupe » (unwrap) — chacun : relecture → écriture (CAS) → relecture de confirmation → PASS. SSE liste les tools 2.4.0-2.7.0.
- Portage `portage-app-mcp/` : typecheck **0 erreur** (tsc Windows, types prod réels).

## T-SERIE 007 — Plugin 2.8.0 : édition d'un enfant imbriqué starter en réel (Fix Day) — 2026-08-04

**Méthode :** tests E2E via MCP prod (serveur Next local 3010, portage `mcp-block-crud-2.7.0`, chaîne Agent → app/mcp → Supabase → plugin 2.8.0), page 5 About de Fix Day (starter), preuves REST core (`context=edit`, nonce wp-admin) + analyse diff character-level. Scripts : `Temp/opencode/mcp-e2e-nested-starter.mjs`, `mcp-e2e-nested-batch-transform.mjs`, `mcp-e2e-nested-transform-core.mjs`.

### Suite imbriquée starter (patch 2.8.0) — page About : **7/7 PASS**

**E1-E5 — update enfant imbriqué starter (idx 2 = `atomic-wind/text` « About Us », depth 2, parent_ref 1, ref null)** :
- dry_run par index → 200, md5 inchangé, contenu inchangé (AVANT le patch : refus conteneur) ;
- update RÉEL → 200, « About Us — MODIF TEST IMBRIQUE » relu, md5 `d35956a7…`→`4661c256…` ;
- preuve REST raw → MODIF présent, ancien absent, parents (box max-w-4xl, section bg-dark) intacts ;
- restauration ORIGINAL → « About Us » relu ;
- preuve REST raw finale → len 22050, MODIF absent.

**E6-E7 — batch `update_blocks` sur enfant imbriqué (idx 2)** : modif → relu « MODIF BATCH », restauration → original relu. **Transform sur `atomic-wind/text` → REFUS par design** (« Bloc atomic-wind/text non transformable — blocs de texte uniquement : core/paragraph, core/heading, core/quote, core/list, core/code, core/preformatted, core/pullquote ») — garde-fou conservé, pas un défaut du patch.

### Suite transform imbriqué core — page jetable 147 : **7/8 PASS** (1 FAIL = hypothèse script sur `refG`)

**E8-E10 — transform enfant imbriqué core** : create ×2 (paragraph `lab-…`) + wrap [A..B] → `core/group` (child_count 2, ⚠️ `refG=null` renvoyé par wrap → ciblage du groupe par blockName) ; **transform ENFANT A paragraph→heading par ref → 200, relu `core/heading` (depth 1, parent_ref 1)** ; transform retour → `core/paragraph`, contenu conservé. **E11 — cleanup** : page 147 supprimée, aucun résidu.

### Delta md5 après restauration (analyse diff)

Après update+restauration via le plugin : raw 22052→22050 = **1 seul `\n` retiré** par `serialize_blocks` (`-->\n<span`→`--><span`, position 598). Structures strictement identiques (79 blocs), refs identiques (`agenttest-c4d8da4ddf28`), texte visible identique → **normalisation canonique WP** (famille `size-full/>`→`size-full />` Exp 019), aucun résidu de contenu. **Restauration au md5 EXACT** : réécriture du raw d'origine (rev 109) via REST core → **md5 final == md5 initial `d35956a796fb5b14c79cfda5c1065b82`**, 80 blocs exposés, idx 2 « About Us » intact.

### Découvertes

1. Le patch 2.8.0 lève les refus d'édition sur enfants imbriqués **starter** : update ET batch par index fonctionnent en réel ; transform sur enfant imbriqué **core** fonctionne (ref conservée, depth 1).
2. Limite conservée : transform = types **core texte uniquement** — `atomic-wind/text` (starter) refusé proprement (garde-fou design).
3. `ref=null` sur le groupe créé par wrap (page jetable) → ciblage de repli par blockName/index.
4. Restauration exacte possible : réécriture du raw d'origine via REST core (rev d'avant test) → md5 identique ; la restauration via `update_block_content` seule laisse le delta de normalisation `serialize_blocks` (bénin, visible au md5).
