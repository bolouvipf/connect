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
- [ ] Push final des commits locaux 2.5.0 sur `opencode-learning` (vérifier `git status` propre après push)
- [ ] (En attente utilisateur) Audit de `houetor-selfhare`

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

## Point de reprise — Session 2026-08-01 (2.5.0 : rétention audit + transform_block livrés, push final)

**État** : évolutions roadmap (mix options 1+5) terminées — plugin+MCP **2.5.0**, portage enrichi, docs à jour. Reste : push des commits locaux (voir `git status` en début de session) puis tout est synchro.

| Élément | État |
|---|---|
| **Étape A — Rétention audit** (commit `7ad5659`) | ✅ `HWC_REST_API::audit_cleanup()` : option `hwc_audit_retention_days` (défaut 90, filtre `hwc_audit_retention_days`), purge chunkée 500×200 max, CRON quotidien `hwc_audit_cleanup` (activate/deactivate) — test lab **9/9 PASS** |
| **Étape B — Transform** (commit `9b550ad`) | ✅ Refactor `class-block-editor.php` : const `ALLOWED_BLOCKS`/`TEXT_BLOCKS` (7 blocs texte), helpers partagés `build_block()`/`wrap_ref()` (bug `wrap_ref` → null corrigé), `transform_block()` (refus imbriqué/hors whitelist, ref HWC conservée, level heading préservé, dry_run, révision, CAS) ; route `POST /houetor/v1/blocks/transform` — test lab **21/21 PASS** |
| **Étape C — MCP miroir** (commit `bd99f61`) | ✅ Tool `transform_block` (client+tools+dispatch, ref OU block_index, dry_run) — unitaires **29/29**, intégration **33/33**, scénario S7 → **26/26** (`mirror-suite.sh` vert) |
| **Étape D — Lockstep 2.5.0** (commits `e8f9eef` + `744e268`) | ✅ Versions (header `Version:` + `HWC_VERSION` + package.json MCP) ; **zip reconstruit** avec niveau unique corrigé (le zip 2.4.0 avait une double imbrication `houetor-connect/houetor-connect/`) ; portage enrichi (`transform_block` : tools.ts + dispatch.ts + ALLOWED_METHODS) — **typecheck 0 erreur vs types prod** ; README miroir + portage à jour ; docs (Exp 012, T-SERIE 004, LEARNING_STATE) |
| **Phase 4 mission — portage `app/mcp/` (dans le lab)** | ✅ `houetor-mcp/portage-app-mcp/` : `original/` + `src/` (error-translator.ts + **7 tools** + dispatch **7 méthodes** + helpers resolveSite/pluginRequest + ALLOWED_METHODS) — **typecheck 0 erreur** (tsc depuis WINDOWS : baseUrl `C:/…` du tsconfig non résolue sous WSL) ; **déploiement prod EN ATTENTE de validation utilisateur** (prérequis plugin clients ≥ 2.3.0/2.4.0/2.5.0) |
| **Agents opencode (globaux)** | ✅ `analyste` + `relecteur` (Gemini 3.6 flash, provider google `{env:GEMINI_API_KEY}`) — **redémarrage opencode requis** pour activer |
| **Sécurité clés** | ✅ Gemini + OpenRouter absentes de tout historique git, `.env.learning` jamais commité |
| Env de test | ✅ Serveur :8888 via service systemd `wp-dev-server` ; plugin lab **2.5.0** actif ; page 2 md5 d'origine `ce833acf933c17dd97eef071665b6269` (187 révisions) |

**Découvertes session** :
- Le rate limit compte TOUTES les tentatives (400/409 inclus) → reset `delete_transient('hwc_ratelimit_2')` entre batteries ; budget intégration = 10 écritures exactement.
- Le refus CAS précède le dry_run → un `dry_run` + mauvais hash renvoie 409 (teste la traduction sans consommer de budget).
- `git archive` SANS `--prefix` (le dossier repo `houetor-connect/` fournit déjà le niveau plugin) ; le zip 2.4.0 committé était mal imbriqué.
- tsc du portage : lancer depuis Windows (junction node_modules + baseUrl Windows), pas sous WSL.
- Page 2 : bloc natif #4 = quote imbriqué → sert au test de refus imbriqué (T10).

**Pour reprendre** : AGENTS.md auto-chargé. Lire `ONBOARDING.md` (§1-8) puis `LEARNING_STATE.md` puis `EXPERIMENTS_LOG.md` Exp 012. **Rappel d'objectif** : chaque demande CRUD d'un utilisateur doit s'exécuter sans erreur (ONBOARDING §1). **Prochaine action** : `git status` → si commits locaux non poussés (7ad5659, 9b550ad, bd99f61, e8f9eef, 744e268, docs), `git push origin opencode-learning` ; puis au choix utilisateur : déploiement Phase 4 (copie `portage-app-mcp/src/*.ts` vers `app/mcp/` prod, tsc + lint, commit dédié) ou nouvelles évolutions roadmap block-mcp (ops structurelles, compte agent WP moindre privilège, tier policy, PHPUnit).

**Commandes MCP utiles** (dans WSL, depuis `houetor-mcp/`) :
```bash
bash scripts/mirror-suite.sh        # suite complète: 29 unitaires + 33 intégration + 26 scénarios (lit le token seul, reset rate limit)
npm test                            # 29 unitaires seuls
```
Relance du serveur WP lab si tombé : `wsl -u root -e bash -c "systemctl restart wp-dev-server"` (service systemd créé le 2026-08-01 car `setsid nohup wp server` meurt avec la session WSL).

**Fils ouverts à retenir** :
1. Tests HTTP externes depuis Windows bloqués (pare-feu Hyper-V, pas admin) — non bloquant, équivalent interne OK.
2. `houetor-selfhare` : ne pas y toucher sans validation explicite de l'utilisateur.
3. Portage `app/mcp/` : nécessite accès prod + décision utilisateur (respecter la règle « ne pas toucher le répertoire d'origine sans validation »).
