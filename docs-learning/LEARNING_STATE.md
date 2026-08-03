# Progression du lab — LEARNING STATE

Mise à jour à chaque session. Checklist globale du Script 2.

## État global

- [x] Dossier lab créé : `C:\Users\Kimsh\Desktop\lab\`
- [x] Repo `bolouvipf/connect` cloné dans `lab\connect`
- [x] Branche `opencode-learning` créée + pushée (origin/opencode-learning)
- [x] `docs-learning/` créé avec les 6 fichiers .md initialisés
- [x] `.env.learning` créé (non commité) + `.gitignore` (`.env.learning`, `wordpress-test-env/`)
- [x] WordPress test installé (localhost:8888) + plugin `houetor-connect` activé (token généré)
- [x] `SOURCE_OF_TRUTH_CHECK.md` rempli (dispatch.ts — Doc A vraie, copie locale 13/13 houetor/v1)
- [x] Endpoints testés manuellement et documentés (TOOLS_DISCOVERED.md — série 001 : 18 tests ; série 002 : 14 tests v2.3.0)
- [x] `php -l` : 0 erreur sur les 14 fichiers .php (avant et après chantier)
- [x] Chantier Script 1 — Approche A implémentée et TESTÉE (V2-1 → V2-14 tous PASS)

## Découvertes structurantes (avant chantier Script 1)

1. **Le repo contenait DÉJÀ un CRUD bloc** (commit ca1734e) : `/page-blocks`, `/block-content`, `/blocks` — index-based. La spec Script 1 supposait leur absence.
2. **Écarts vs spec Script 1** → corrigés en v2.3.0 (Approche A) :
   - [x] Ciblage par `ref` HWC (blocs enrobés de marqueurs, ref auto-générée)
   - [x] CAS (`expected_hash`) sur toutes les écritures → 409
   - [x] Rate limit 10/60s par page → 429
   - [x] Journal d'audit `houetor_connect_actions_log` (before/after md5)
   - [x] `wp_save_post_revision()` avant toute écriture (inject/uninject inclus)
3. **Bugs corrigés** : #1 versions incohérentes (→ 2.3.0 partout) ; #2 `inject replace` destructeur (révision + CAS) ; #3 pas de ref pour les blocs créés ; #4 pas de rate limit ni audit.

## Décisions actées

- **Approche A** retenue (compléter l'existant, additif) plutôt que spec à la lettre (Approche B).
- Corriger les 2 bugs ouverts en même temps.
- Version cible : **2.3.0**.
- Non modifiés : `check_token()`, routes `/inject` `/uninject` (routes conservées, garde-fous ajoutés seulement).

## Prochaines étapes

- [x] Commit + push du chantier v2.3.0 sur `opencode-learning` (a94b623 + 3f17c06)
- [x] Reconstruction de `houetor-connect.zip` (chemins `/`, source synchro)
- [x] Kit de continuité : `ONBOARDING.md` + section Learning Lab dans `README.md`
- [x] Clés Gemini + OpenRouter ajoutées à `.env.learning` (jamais commitées)
- [x] Analyse de `block-mcp` (GravityKit) consignée — évolutions candidates identifiées (EXPERIMENTS_LOG Exp 008)
- [x] Phase 1 mission MCP : miroir `houetor-mcp/` v2.3.0 construit + testé (18/18 unitaires, 16/16 intégration)
- [x] Phase 2 mission : plugin+MCP 2.4.0 (batch `update_blocks` + `dry_run`) livré et testé (V3 32/32, régression 14/14, unitaires 24/24, intégration 28/28), commits `599f388`/`a76318a`/`3663900`
- [x] Phase 3 mission : scénarios « exaucés exactement » via le MCP miroir (24/24 PASS, TOOLS_DISCOVERED série 003, README MCP à jour) — commit `1a4252a`
- [x] Phase 4 mission : portage `app/mcp/` **préparé dans le lab** (`houetor-mcp/portage-app-mcp/` : error-translator + 6 tools + dispatch, typecheck 0 erreur vs types prod) — **déploiement en attente de validation utilisateur** (copie dans le repo prod) ; sinon évolutions roadmap block-mcp
- [x] Objectif explicité dans les docs : « toute action CRUD demandée par l'utilisateur s'exécute sans erreur » (ONBOARDING §1, README racine, README MCP, AGENTS.md, LEARNING_STATE) — 2026-08-01
- [x] Agents opencode configurés (globaux) : `analyste` + `relecteur` sur Gemini 3.6 flash gratuit (provider google, `{env:GEMINI_API_KEY}`) + clé enregistrée en variable utilisateur — redémarrage opencode requis
- [x] Vérif sécurité clés : GEMINI/OPENROUTER absentes de tout historique git (connect public + houetor privé), recherche GitHub 0 résultat, `.env.learning` jamais commité — 2026-08-01
- [ ] Prioriser avec l'utilisateur les évolutions inspirées de block-mcp (ops structurelles, compte agent WP, tier policy, PHPUnit)
- [x] Évolutions roadmap validées (mix options 1+5, « vas-y ») : **rétention audit + auto-transform** livrées — plugin+MCP **2.5.0** (V3 32/32, rétention 9/9, transform 21/21, unitaires 29/29, intégration 33/33, scénarios 26/26), commits `7ad5659`/`9b550ad`/`bd99f61`/`e8f9eef`/`744e268` + docs (Exp 012, série 004)
- [x] Push final des commits 2.5.0 sur `opencode-learning` (git propre, synchro confirmée en reprise)
- [x] Évolution roadmap validée (tier policy) : **refus blocs legacy + suggestion** livrée — plugin+MCP **2.6.0** (tier policy 11/11, V3 32/32, unitaires 30/30, intégration 35/35, scénarios 29/29, portage typecheck 0 erreur), commits (voir point de reprise) + docs (Exp 013, série 005)
- [x] Évolution roadmap validée (ops structurelles) : **move/duplicate/wrap/unwrap** livrée — plugin+MCP **2.7.0** (structural 42/42, V3 32/32, unitaires 42/42, intégration 52/52, scénarios 41/41, portage typecheck 0 erreur), commits + docs (Exp 014, série 006)
- [x] (Fait — Exp 016) Audit de `houetor-selfhare` : rapport complet consigné (aucune modification du plugin, hors périmètre)
- [ ] (En attente utilisateur) Déploiement Phase 4 : copie `portage-app-mcp/src/*.ts` vers `app/mcp/` prod (prérequis plugin clients ≥ 2.7.0)

## Rappels de procédure

- Jamais de commit sur `main`. Tout sur `opencode-learning`.
- Commits précis (`git add [fichiers]`), jamais `git add .` aveugle.
- `.env.learning` jamais commité.
- Chaque write testé dans l'env isolé avant commit.

## MISSION validée — Plugin + MCP agent (en attente d'exécution, session suivante)

**Décision utilisateur (2026-07-31)** : commencer la mission = faire évoluer le plugin **ET** construire la brique MCP agent côté HOUETOR (style block-mcp) pour que les agents exaucent les demandes utilisateur exactement sur les sites.

**Objectif ultime (rappel) : toute action CRUD qu'un utilisateur demande à l'IA doit s'exécuter sans erreur** — relire avant d'écrire (CAS `expected_hash`), `dry_run` (répétition générale), batch atomique `update_blocks`, garde-fous (rate limit, révision, audit), erreurs traduites en conseils actionnables, relecture de confirmation. Preuve = scénarios « exaucés exactement » (TOOLS_DISCOVERED série 003, 24/24).

**Décisions actées** :
- Le serveur MCP vit **dans le repo connect** : `houetor-mcp/` (version lockstep avec le plugin, comme block-mcp)
- Première montée : **Batch atomique `update_blocks` + `dry_run`** d'abord (puis : compte agent WP moindre privilège, ops structurelles, tier policy — en séances suivantes)
- Clients cibles : **stdio universel** (Claude Desktop, Claude Code, Cursor, opencode…) — pas de packaging .mcpb pour l'instant

**DÉCOUVERTE MAJEURE (Exp 009, 2026-07-31) — le serveur MCP HOUETOR EXISTE DÉJÀ** :
`C:\Users\Kimsh\Pictures\Screenshots\houetor\app\mcp\` = `route.ts` + `tools.ts` + `parser.ts` + `dispatch.ts`. Protocole **HTTP JSON-RPC 2.0 (POST) + listing SSE (GET)**, auth header `X-HWT-Token` (token HWT par profil), 23 tools déclarés, 21 méthodes dispatchées. Le MCP appelle le plugin via `houetor/v1` (`/pages`, `/menus`, `/inject`, `/uninject`, `/media`) avec `X-Houetor-Token` stocké dans la table Supabase `connected_sites`.
**→ Le MCP ne connaît PAS encore le CRUD bloc v2.3.0** (`/page-blocks`, `/block-content`, `/blocks`), ni le CAS `expected_hash`, ni batch/dry_run. C'est LA mission : étendre le MCP existant (pas en créer un autre).
`node_modules/next/dist/esm/server/mcp/` = MCP intégré de Next 16.2.6 (dev tooling, non activé) — référence de patterns seulement.

**Architecture cible** :
```
Agent IA ──(JSON-RPC HTTP + SSE, header X-HWT-Token)──▶ app/mcp/ (prod) ≡ houetor-mcp/ (lab, miroir testé)
                                                     ──HTTPS + X-Houetor-Token──▶ houetor-connect (plugin WP, API houetor/v1)
```
Le lab `houetor-mcp/` est un **miroir testé** du MCP : mêmes patterns (route/tools/parser/dispatch, protocole identique) pour que les nouveaux tools soient **portables tel quel** dans `app/mcp/` en production.

**Plan d'exécution (révisé)** :
- **Phase 0 — Reprise** : lecture mémoire ; smoke tests lab (php -l, check-setup, serveur :8888, git synchro) ; **installer Node ≥20 dans WSL** (actuel 11.12.1 — insuffisant)
- **Phase 1 — MCP miroir `houetor-mcp/`** : copier les patterns de `app/mcp/` (route JSON-RPC + SSE, tools, parser, dispatch) ; outils WP existants (get_wp_pages, inject_page, get_wp_menus, list_connected_sites, export_to_wordpress) + **NOUVEAUX outils bloc** : `get_page_blocks`, `create_block`, `update_block_content`, `delete_block` (avec ref/expected_hash/anchor) ; gestion des erreurs 409/429/404 traduites en messages agents
- **Phase 2 — Montée plugin+MCP 2.4.0** : (1) endpoint `POST /blocks/batch-update` (N updates = 1 révision, all-or-nothing, compte 1 écriture rate limit) + tool MCP `update_blocks` ; (2) paramètre `dry_run` sur les routes d'écriture + tool MCP
- **Phase 3 — Scénarios « exaucés exactement »** : demandes utilisateur réalistes testées À TRAVERS le MCP miroir (relecture = demande, audit + révision OK), consignées dans TOOLS_DISCOVERED + `houetor-mcp/README.md` (incluant le mode d'emploi de portage vers `app/mcp/`)
- **Phase 4 — Livraison 2.4.0** : version lockstep (plugin header + constante + stable tag + package.json MCP + changelog), docs à jour, zip reconstruit en `/`, commits ciblés + push

**Règles rappel** : jamais `main` ; `.env.learning` + token jamais commités (env vars du MCP : `WORDPRESS_URL`, `HOUETOR_TOKEN`) ; tests isolés avant affirmation ; `php -l` avant commit ; zip en `/`.

## Point de reprise — Session 2026-08-02 (2.7.0 : ops structurelles livrées, push final)

**⚠️ LAB CLÔTURÉ — Session 2026-08-02 (soir) : mission portage production exécutée.** `opencode-learning` fusionnée dans `main` (connect), portage MCP déployé sur branche `mcp-block-crud-2.7.0` du repo `houetor` (commit `fc91bd5`, NON mergé dans `main` houetor — décision utilisateur), test E2E vert sur site TasteWP neuf (Fix Day). Détails : Exp 015. Point de reprise ci-dessous = état AVANT clôture (historique).

**État** : évolution roadmap « ops structurelles » (move/duplicate/wrap/unwrap, dernière grande piste block-mcp restante) terminée — plugin+MCP **2.7.0**, portage enrichi, docs à jour. Chantier trouvé NON commité à l'ouverture de session (démarré la veille 19:46-20:16, sans commit ni docs) → finalisé entièrement ce jour. Reste : push des commits locaux (voir `git status` en début de session) puis tout est synchro.

| Élément | État |
|---|---|
| **Étape A — Ops structurelles plugin** | ✅ 4 nouveaux endpoints, aucun existant modifié : `POST /blocks/move` (start/end/before/after + ancre, no-op sans effet → 0 révision 0 audit), `/blocks/duplicate` (refs régénérées en profondeur), `/blocks/wrap` (bloc/plage → core/group, plage inversée 400), `/blocks/unwrap` (dégroupage, non-groupe 400) — tous CAS + dry_run + révision + audit + 1 écriture rate limit — **test lab 42/42 PASS**, régression V3 **32/32** |
| **Étape B — Miroir MCP** | ✅ 4 tools (`move_block`/`duplicate_block`/`wrap_block`/`unwrap_block`) + `error-translator.ts` cas `wrap_failed`/`unwrap_failed` traduits en conseils ; tests unitaires + intégration (page 3, budget rate limit indépendant) + scénarios **S9-S12** — unitaires **42/42**, intégration **52/52**, scénarios **41/41** |
| **Étape C — Lockstep 2.7.0** | ✅ Versions (header + `HWC_VERSION` + **stable tag** + package.json MCP) ; changelog readme.txt complété (2.5.0/2.6.0 manquants ajoutés + 2.7.0) ; portage `portage-app-mcp/` enrichi (+4 tools, +4 méthodes, +2 cas traduction) — **typecheck 0 erreur** (tsc Windows) ; zip reconstruit ; docs (Exp 014, série 006, PLUGIN_CAPABILITIES 2.7.0, LEARNING_STATE, ONBOARDING, AGENTS, README MCP, README portage) |
| **Phase 4 mission — portage `app/mcp/` (dans le lab)** | ✅ `houetor-mcp/portage-app-mcp/` : 11 tools bloc 2.7.0 inclus — **typecheck 0 erreur** ; **déploiement prod EN ATTENTE de validation utilisateur** (prérequis plugin clients ≥ 2.7.0) |
| **Sécurité clés** | ✅ Gemini + OpenRouter absentes de tout historique git, `.env.learning` jamais commité |
| Env de test | ✅ Serveur :8888 via service systemd `wp-dev-server` ; plugin lab **2.7.0** actif (php -l 0 erreur) ; page 2 md5 d'origine `c4abdffec12763597022af2da35cd47c` restauré en post-test |

**Découvertes session** :
- Chaque op structurelle = exactement 1 écriture rate limit ; dry_run ne consomme rien ; move no-op (déjà en place) ne crée NI révision NI audit.
- Le diff `git status` sur `portage-app-mcp/` montrait ~2400 lignes modifiées = 100% CRLF/LF (vérifié `--ignore-space-at-eol` = 0) → `git checkout --` avant portage propre.
- Latence WP lab ~5-18 s/requête HTTP (opcache CLI off + disque DrvFS) : les batteries mirror-suite dépassent les timeouts par défaut → exécuter par étapes avec timeouts larges (900 s).
- Le mirror-suite.sh restaure maintenant les pages de référence (`restore-lab-pages.php`) avant CHAQUE batterie.

**Pour reprendre** : AGENTS.md auto-chargé. Lire `ONBOARDING.md` (§1-8) puis `LEARNING_STATE.md` puis `EXPERIMENTS_LOG.md` Exp 014. **Rappel d'objectif** : chaque demande CRUD d'un utilisateur doit s'exécuter sans erreur (ONBOARDING §1). **Prochaine action** : `git status` → si commits locaux non poussés (2.7.0 plugin + MCP + portage + zip + docs), `git push origin opencode-learning` ; puis au choix utilisateur : (a) déploiement Phase 4 (copie `portage-app-mcp/src/*.ts` vers `app/mcp/` prod, tsc + lint, commit dédié — prérequis plugin clients ≥ 2.7.0), (b) roadmap restante (compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit), (c) audit `houetor-selfhare` (attente validation explicite).

**Commandes MCP utiles** (dans WSL, depuis `houetor-mcp/`) :
```bash
bash scripts/mirror-suite.sh        # suite complète: 42 unitaires + 52 intégration + 41 scénarios (restaure les pages, reset rate limit)
npm test                            # 42 unitaires seuls
```
Relance du serveur WP lab si tombé : `wsl -u root -e bash -c "systemctl restart wp-dev-server"` (service systemd créé le 2026-08-01 car `setsid nohup wp server` meurt avec la session WSL).

**Fils ouverts à retenir** :
1. Tests HTTP externes depuis Windows bloqués (pare-feu Hyper-V, pas admin) — non bloquant, équivalent interne OK.
2. `houetor-selfhare` : ne pas y toucher sans validation explicite de l'utilisateur.
3. Portage `app/mcp/` : nécessite accès prod + décision utilisateur (respecter la règle « ne pas toucher le répertoire d'origine sans validation »).
4. Déploiement des évolutions 2.4.0→2.7.0 côté clients (plugin + MCP) : le portage inclut update_blocks, transform_block, dry_run, tier policy, ops structurelles — les sites connectés doivent avoir le plugin ≥ 2.7.0.

## CLÔTURE — Session 2026-08-02 soir : mission portage production (Exp 015)

**Mission utilisateur** : clôturer le lab connect + porter le travail validé (plugin 2.7.0 + portage MCP) vers le dépôt `houetor` de production, sans toucher la prod réelle ni les clients. **Exécutée en totalité, rapport remis.**

| Étape | État | Preuve |
|---|---|---|
| 1. Merge `opencode-learning` → `main` (connect) | ✅ | FF `ca1734e..5dbc863` (puis `cde9fe9` fix lint) ; versions 2.7.0 vérifiées (header, HWC_VERSION, stable tag, package.json MCP) ; push main |
| 2. Zip 2.7.0 → `houetor/outputs/` | ✅ | sha256 avant = après = `AA7E89A8…` ; 34 082 → 42 542 octets |
| 3. Branche `mcp-block-crud-2.7.0` (houetor) | ✅ | créée depuis main, jamais de travail direct sur main |
| 4. Portage `app/mcp/` | ✅ | `tools.ts` (+155), `dispatch.ts` (+435/-41), `error-translator.ts` (nouveau) ; route.ts/parser.ts absents du diff (prouvé) |
| 5. Vérification native | ✅ | incident `.next/dev/types/routes.d.ts` corrompu (artefact généré gitignoré daté 31/07) → supprimé (validé) → **tsc 0 erreur** ; **lint app/mcp 0 erreur** (2 `no-explicit-any` portés du lab corrigés à la source + re-copie, commit `cde9fe9`) ; lint global = 62 erreurs pré-existantes hors app/mcp (état de main, non touchées) |
| 6. Commit ciblé + push | ✅ | `fc91bd5` (4 fichiers, 647+/41-) poussé sur `origin/mcp-block-crud-2.7.0` — PAS de merge dans main houetor (comme demandé) |
| 7. Site TasteWP « Fix Day » | ✅ | `https://fixday.s6-tastewp.com` — plugin installé + activé (upload zip via wp-admin curl), **Version 2.7.0** affichée dans plugins.php ; token généré à l'activation (32 chars, jamais affiché, stockage temp supprimé). ⚠️ Connexion dashboard HOUETOR (connected_sites Supabase) NON faite : nécessite accès dashboard utilisateur |
| 8. Test E2E via MCP | ✅ | 6 scénarios + cleanup **TOUS PASS** sur le site neuf (serveur MCP lab = mêmes patterns que le portage, pointé sur Fix Day) : get_page_blocks (5 blocs + md5), create+update CAS chaîné, **409** CAS périmé, **404** anchor_not_found, **400** wrap_failed plage inversée, **dry_run** md5 inchangé + bloc absent ; script `e2e-fixday.mjs` dans Temp/opencode |
| 9. Rapport | ✅ | remis ci-dessous / dans le chat de session |

**Découvertes session** :
- Le repo houetor avait des modifs non commitées pré-existantes : `M app/mcp/dispatch.ts` + `tools.ts` = **100% EOL CRLF/LF** (diff `--ignore-space-at-eol` vide), `D ghjk.py` (laissé, hors périmètre, décision utilisateur).
- Les 2 `no-explicit-any` portés venaient du lab (jamais linté : pas de config eslint dans portage-app-mcp) — détectés seulement par lint natif prod. Correction = interface `RestErrorData` typée + `data: unknown` (cast à l'appel) — comportement inchangé, typecheck 0 erreur des deux côtés.
- Le token plugin est lisible sur la page admin `admin.php?page=houetor-connect` (`id="hwc-token-display"`) ; généré à l'activation (`hwc_activate` → `wp_generate_password(32,false)`).
- TasteWP : pas d'accès shell → installer/activer via wp-admin curl (cookies + nonce `_wpnonce` + upload multipart) ; version vérifiable dans plugins.php.

**Reste ouvert (décisions utilisateur, hors mission)** :
- Connexion dashboard HOUETOR du site Fix Day (action utilisateur, accès dashboard requis) — prérequis pour cibler le site via le MCP prod (site_id → Supabase connected_sites)
- Merge (ou PR) de `mcp-block-crud-2.7.0` vers `main` houetor + rollout clients — **décision utilisateur, PAS proposé dans le rapport**
- `ghjk.py` supprimé non commité dans houetor ; 3 `probe-*.mjs` untracked dans le lab (questions posées, réponse écartée)
- Lint global houetor : 62 erreurs pré-existantes (chantier séparé)

## AUDIT SELFHARE — Session 2026-08-02 (Exp 016, rapport seul, AUCUNE modification)

**Objet** : audit complet du 2e plugin `houetor-selfhare` (zip lab == source prod prouvé, php -l 0 erreur, 21 fichiers lus intégralement). Rapport détaillé : `EXPERIMENTS_LOG.md` Exp 016.

**En bref** : architecture saine (nonces + caps + rôle dédié + kses + brouillon/corbeille + CAS SQL sur inject/delete + rate limit + révisions + audit + erreurs traduites) MAIS 10 faiblesses :

1. Version incohérente : header/stable tag `1.0.1` vs constante orpheline `1.0.2` (jamais utilisée)
2. Aperçu contournable : `dispatch` sans contrôle de preview serveur ; mode auto (`can_skip_preview`) non implémenté côté serveur
3. CAS partiel : `update_content`/`update_block_content`/`delete_content`/`create_content`/`revert_to_revision` écrivent DIRECTEMENT (pas de CAS ni expected_hash) — seul inject_page/delete_block protégé
4. Rate limit inopérant sur les créations (`$post_id == 0` → sortie anticipée)
5. `update_content` str_replace silencieux à l'exécution (divergence avec le preview qui bloque)
6. Routines planifiées inertes (`blocking => false` → tool_calls du relay jamais exécutés)
7. Manifest produits fantôme (create_products crée un produit vide, price/stock jamais modifiables)
8. Nettoyage incomplet : uninstall ne supprime ni le rôle ni le cron ; journal LIMIT 10 non paginé ; log aussi les lectures
9. Hack suspect `SET post_modified = post_modified` (cas_write) — résidu probable
10. License en clair en option + transmise au relay à chaque chat

**Prochaine action (décision utilisateur)** : corriger ou non selfhare (priorités : version, CAS global modèle connect 2.7.0, rate limit créations, preview serveur) — chantier correctif hors périmètre sans nouvelle validation. Fils ouverts inchangés (Fix Day dashboard, merge mcp-block-crud-2.7.0, probe-*.mjs, lint global).

## CORRECTION SELFHARE — Session 2026-08-02 (Exp 017, validation utilisateur « grosse correction »)

**Livré** : `houetor-selfhare` **1.0.2** — patterns connect 2.7.0 appliqués au 2e plugin. Chantier `connect\houetor-selfhare\` (dossier source) + zip reconstruit (sha256 `155e1d99…`, 20 fichiers, install/activation testées WP lab).

**Ce qui a été fait (10 faiblesses de l'audit → 8 traités)** :
1. ✅ Version lockstep 1.0.2 (header + stable tag + **constante enfin utilisée** : localize `version` + footer admin) + changelog
2. ✅ **Preview obligatoire côté serveur** : `execute()` exige un `preview_token` (transient fingerprint, usage unique) pour toute écriture ; retourne `preview_token` + `expected_hash` ; seule l'interne `create_content` (brouillon) contourne
3. ✅ **CAS global** : `update_content`/`update_block_content` via `cas_write` + `expected_hash` (`edit_conflict` 409) — fin des écritures directes ; **hack `SET post_modified=post_modified` supprimé**
4. ✅ **Rate limit créations** : compteur par user (`sh_rate_u_<uid>`, fallback CLI `sh_rate_u_cli`) — 10/60 s ; écritures par post conservées
5. ✅ `find_text` strict (`strpos` → `find_text_not_found`) — fin du str_replace silencieux
6. ✅ **Routines actives** : `send_relay()` bloquant, parse `tool_call`, exécution via Dispatch (`internal => true`)
7. ✅ **Produits réels** : `update_product_meta()` (WC price/stock/manage_stock) + création — fin du produit fantôme
8. ✅ Nettoyage : uninstall (option+rôle+cron), **journal paginé**, **audit écritures seules**
9. ✅ **License chiffrée au repos** (AES-256-CBC, clé dérivée de `wp_salt('auth')`)
10. ⚠️ License en clair transmise au relay : conservé (HTTPS, hors périmètre sans demande)

**Tests** : batterie `scripts/selfhare-test-016.php` (12 sections) **36 PASS / 0 FAIL** — preview obligatoire (sans/mauvais/bon token, usage unique), CAS 409, find_text introuvable, dry_run sans écriture, rate limit 10+1, audit écritures seules, produits (stub WC), routines (tool_call exécuté + écriture refusée), license chiffrée. `php -l` 0 erreur. Voir Exp 017.

**Point de reprise — prochaine session** :
- État git : commit de clôture à pousser (ou déjà poussé) : zip 1.0.2 + dossier `houetor-selfhare/` + docs Exp 017 + LEARNING_STATE → `git push origin opencode-learning`
- **Reste décision utilisateur** (inchangé) : merge `mcp-block-crud-2.7.0` (houetor), connexion dashboard Fix Day, 3 `probe-*.mjs` untracked (écartés), lint global houetor, évolutions roadmap (compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit)
- Prochaine étape candidate : relecture `relecteur` du chantier + tests E2E réels via WP-CLI HTTP si souhaité ; sinon mise à jour de `HOUETOR-selfhare-consolide-juillet2026.md` avec l'état 1.0.2

## VALIDATION MCP PROD — Session 2026-08-03 (Exp 018 : portage testé en conditions réelles sur Fix Day connecté)

**Contexte** : l'utilisateur a (1) fourni les identifiants TasteWP Fix Day (stockés `.env.learning`, gitignoré), (2) **connecté Fix Day au dashboard HOUETOR** (token profil ONG `HWT-ONG-…`, Supabase `connected_sites` id `f166ef68-8816-45b0-97f9-d618360a84d6`), (3) uploadé un **starter site** (contenu réel). → Première occasion de tester le **portage MCP prod** (branche `mcp-block-crud-2.7.0`) avec la vraie chaîne Supabase → plugin.

| Élément | État |
|---|---|
| **Infra** | ✅ App Next du repo houetor buildée + lancée (`next build` + `next start -p 3010`) ; `next dev` Turbopack insuffisant pour le MCP edge (env non inlinées) |
| **Blocage Supabase résolu** | ✅ `.env.local` du repo avait les 3 variables vidées (`""`) ; pull Vercel impossible (compte sans droit de décryptage) ; URL retrouvée dans le repo + clés fournies par l'utilisateur → `.env.local` configuré (gitignoré, jamais commité) |
| **GET SSE auth** | ✅ profil ONG, 32 tools déclarés, dont les 12 tools bloc 2.7.0 |
| **list_connected_sites** | ✅ Fix Day trouvé (id/url/token plugin cohérents) |
| **Cycle CRUD 2.7.0 (page 5 About, contenu starter)** | ✅ **9/9 PASS** : dry_run sans effet (md5 intact) → create réel (ref `e2eprod-…`) → update CAS OK → **409 CAS périmé refusé + contenu intact** → batch `update_blocks` → `move_block` start (index 0) → delete → **page restaurée à l'identique** (md5 `a4056880…` = état d'origine) |
| **Plugin site** | ✅ 2.7.0 toujours actif après starter ; token plugin (32 chars) ajouté à `.env.learning` (masqué) ; session wp-admin OK (re-upload possible, méthode Exp 015) |

**Découvertes** : écritures MCP prod → `result.data {success, post_id, ref, message}` (relire `get_page_blocks` pour vérifier) ; erreurs plugin → `success:false + error` HTTP 200 avec conseil traduit (« Re-lisez la page… ») ; `get_wp_pages` → `data[0].pages.pages[]`. Détails : `EXPERIMENTS_LOG.md` Exp 018.

**Pour reprendre** : (a) ops structurelles en réel (transform/wrap/duplicate/unwrap) sur la page 2 Accueil — étendre `mcp-e2e-prod.mjs` (Temp/opencode) ; (b) décisions utilisateur en attente : merge `mcp-block-crud-2.7.0` → main houetor, lint global (62 erreurs), roadmap (compte agent WP, rate limit rewrites, PHPUnit) ; (c) si serveur à relancer : `next build` + `next start -p 3010` puis POST `/mcp` avec `X-HWT-Token` (voir Exp 018). Secrets en stock (jamais commités) : `.env.learning` (lab) + `.env.local` (houetor).

## VALIDATION OPS STRUCTURELLES PROD — Session 2026-08-03 (Exp 019 : transform/wrap/unwrap/duplicate en réel, 12/12 PASS)

**Mission** : finir la validation réelle du portage MCP (branche `mcp-block-crud-2.7.0`) — les 4 ops structurelles sur la page 2 Accueil de Fix Day (contenu starter). Serveur Next relancé (`next build` + `next start -p 3010`). Scripts Temp/opencode : `mcp-e2e-struct-prod.mjs` (batterie 1) puis `mcp-e2e-struct2.mjs` (batterie corrigée **12/12 PASS**).

| Élément | État |
|---|---|
| **transform_block** | ✅ dry_run sans effet → réel paragraph→heading (**ref conservée**) → restauration heading→paragraph (**ref conservée**), md5 avancé à chaque écriture |
| **wrap_block** | ✅ plage inversée B..A → **400 refusé** avec conseil traduit actionnable ; wrap A..B réel → groupe `core/group` avec **nouvelle ref** |
| **unwrap_block** | ✅ ref interne → **refusé** (« introuvable ») ; **ref du groupe** → groupe disparu, **refs originales restaurées** |
| **duplicate_block** | ✅ (batterie 1) dry_run sans effet → réel : 2 copies, **refs régénérées uniques** |
| **Cleanup** | ✅ delete A+B, aucun résidu e2eprod, **md5 final == md5 de début de session** (page restaurée à l'identique) |

**Découvertes structurantes** :
1. **`get_page_blocks` n'expose PAS les innerBlocks** (que les blocs racine) → après wrap, l'agent doit utiliser la **ref du groupe renvoyée par le wrap** pour unwrap ; les refs internes sont préservées dans le sous-arbre et redeviennent visibles après unwrap.
2. Refus (400) = `result.success:false + error` HTTP 200 (pas d'`error` JSON-RPC) — traduits avec conseil (« Re-lisez la page… index croissants »).
3. Diff md5 « anormal » constaté en batterie 1 = **1 seul octet** : `size-full/>` → `size-full />` — normalisation standard de `serialize_blocks` WP (prouvé par diff révision 19 vs contenu courant) — pas un résidu.
4. API REST core Fix Day : nonce requis même en GET → récupéré dans `wpApiSettings` de `post.php` (login cookies wp-admin, probes probe-login2/probe-raw4).

**Pour reprendre** : validation réelle du CRUD bloc 2.7.0 **terminée** (Exp 018 CRUD 9/9 + Exp 019 structurel 12/12 + Exp 020 scénarios utilisateur 9/9 + Exp 021 positionnement précis/bloc enrichi). Reste (décisions utilisateur) : merge/rollout `mcp-block-crud-2.7.0` → main houetor ; lint global houetor (62 erreurs) ; roadmap (compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit) ; 3 `probe-*.mjs` untracked (écartés) ; serveur Next à relancer si besoin (procédure Exp 018). Docs : EXPERIMENTS_LOG Exp 021.

## POSITIONNEMENT PRÉCIS + BLOC ENRICHI — Session 2026-08-03 (Exp 021 : fond vert + image après « About Us », succès utilisateur)

**Mission** : sur la page About de Fix Day, créer un bloc **juste après** la section « About Us / About BrightSmile Dental / Dedicated to transforming smiles… », avec **fond vert + image** (capture locale fournie en pièce jointe). **Rendu vérifié par l'utilisateur : SUCCÈS.**

| Étape | État | Preuve |
|---|---|---|
| **Localisation cible** | ✅ impossible via `get_page_blocks` seul (contenu des blocs starter vide, ref null) → post_content brut via REST core (nonce) + parseur top-level v3 → About Us = **racine index 0** | racine `@0-1527` |
| **Upload image locale** | ✅ `POST /wp-json/wp/v2/media` (binaire + Content-Disposition + nonce) → **média id 98** `…/2026/08/capture-agent-houetor.png` | — |
| **Création bloc** | ✅ `create_block` MCP prod : `core/group`, div fond vert `#16a34a` + titre + texte + `<img>`, `position: after anchor_index '0'`, CAS frais → **ref `agenttest-c4d8da4ddf28`, index 1** | — |
| **Double vérif** | ✅ plugin (index 1, voisin = About Us index 0) + raw REST (racine `@1527-2291` entre `@0-1527` et `@2293-7486`) | — |
| **Vision image** | ✅ modèle chat sans vision → description via API **Gemini 3.6 flash** (base64, clé jamais affichée) ; anciens modèles 404 → liste `/v1beta/models` | — |

**Difficultés notables** : modèle chat sans entrée image ; `gemini-2.0/2.5-flash` retirés (404) ; `get_page_blocks` ne montre pas le contenu des blocs starter (résolution texte→index via raw REST) ; 2 parseurs JS buggés avant v3 (slash dans `atomic-wind/box`, self-closing) ; nonce même en GET ; `wp_kses_post` filtre les styles (fond vert = style inline simple, OK). Détails : `EXPERIMENTS_LOG.md` Exp 021.

**État Fix Day (blocs de test en place, nettoyage sur demande)** : accueil ×2 (bas de page + après blog index 3) + About ×1 (section verte index 1). About md5 d'origine `a40568809ad0d4c949468cd29616c2dd`.

## SCÉNARIOS UTILISATEUR PROD — Session 2026-08-03 (Exp 020 : demandes en langage naturel via MCP prod, 9/9 PASS)

**Mission** : continuer les tests sur le site TasteWP Fix Day — exécuter des scénarios « demandes utilisateur réalistes » À TRAVERS le portage MCP prod (branche `mcp-block-crud-2.7.0`, chaîne Agent → app/mcp → Supabase → plugin) sur la page 5 About (starter : 5 blocs `atomic-wind/box`). Serveur Next relancé (il était tombé : `next build` + `next start -p 3010`). Script : `Temp/opencode/mcp-e2e-scenarios-prod.mjs`.

| Scénario | État |
|---|---|
| **S1 « Ajoute un bloc en bas de la page »** | ✅ create_block CAS → ref `e2es20-cb5b50e8bd14`, index 5 (fin) |
| **S2 « Modifie le texte du bloc »** | ✅ update CAS → contenu vérifié par relecture |
| **S3 « Répétition générale SANS enregistrer »** | ✅ transform dry_run : md5 inchangé, toujours paragraph |
| **S4 « Transforme en titre puis remets en paragraphe »** | ✅ round-trip heading↔paragraph, ref conservée |
| **S5 « Deux corrections en une seule opération »** | ✅ batch update_blocks : 1 révision, contenu final vérifié |
| **S6 « Conflit : refuse l'écriture obsolète »** | ✅ **409 « Conflit CAS : le contenu de la page a changé… »**, contenu intact, md5 inchangé |
| **S7 « Remonte le bloc en haut puis remets-le »** | ✅ move start (index 0) → move end, round-trip OK |
| **S8 « Supprime le bloc temporaire »** | ✅ aucun résidu, **md5 final == md5 initial** (`a40568809ad0d4c949468cd29616c2dd`) |

**Preuve finale du contrat ONBOARDING §1** : la chaîne prod complète exécute les demandes utilisateur **sans aucune erreur** en conditions réelles. 8 écritures réelles ≤ budget rate limit 10/60s (aucun 429 rencontré, retry intégré).

**Pour reprendre** : validation réelle **complète** (Exp 018 + 019 + 020). Reste (décisions utilisateur) : merge/rollout `mcp-block-crud-2.7.0` → main houetor ; lint global houetor (62 erreurs) ; roadmap (compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit) ; 3 `probe-*.mjs` untracked (écartés) ; serveur Next à relancer si besoin (procédure Exp 018). Docs : EXPERIMENTS_LOG Exp 020.
