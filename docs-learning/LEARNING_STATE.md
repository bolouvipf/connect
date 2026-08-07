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

**Pour reprendre** : validation réelle du CRUD bloc 2.7.0 **terminée** (Exp 018 CRUD 9/9 + Exp 019 structurel 12/12 + Exp 020 scénarios utilisateur 9/9 + Exp 021 positionnement précis/bloc enrichi). **Audits persistance** : Exp 022 connect (aucun JSON volatil, tout en DB, déconnexion = suppression ligne Supabase seule) + Exp 023 selfhare (tables DB WP durables ; à la désinstallation DROP 3 tables + options — contenu des pages intact). Reste (décisions utilisateur) : merge/rollout `mcp-block-crud-2.7.0` → main houetor ; lint global houetor (62 erreurs) ; roadmap (compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit) ; 3 `probe-*.mjs` untracked (écartés) ; serveur Next à relancer si besoin (procédure Exp 018). Docs : EXPERIMENTS_LOG Exp 023.

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

## RESTYLE UI SELFHARE — Session 2026-08-04 (Exp 024 : thème HOUETOR ref plans, prod + lab synchronisés)

**Mission utilisateur** : arranger l'interface admin du plugin `houetor-selfhare` (dossier prod `houetor-selfhare/`) en s'inspirant du design de `houetor.com/selfhare/plans` — revoir les champs et icônes + élargir la forme/taille de l'« invite de commande », puis « préparer le zip et commit », et faire pareil dans le lab.

| Élément | État |
|---|---|
| **Design de référence** | ✅ extrait de `app/selfhare/plans/page.tsx` : fond `#0D1F1A`, cartes `#1A3028`/`#162B24`, vert `#2ECC8A`, orange agence `#FB923C`, texte `#F0EDE6`/`#7A9E8E`, titres Syne + corps DM Sans, cards radius 24px, boutons pills |
| **Restyle prod (4 fichiers)** | ✅ `admin-chat.css` réécrit (variables CSS, chat max-width 800→1080, **invite de commande élargie** : min-height 56px, padding 14×22, font-size 16, radius 18, glow focus vert ; bulles user = dégradé vert ; boutons pills + ✓ ::before ; modal/diff/loading/scrollbars sombres) ; `class-agent-chat.php` (emojis 📎/✕ → **SVG inline**, icônes SVG labels Action/Page, placeholder enrichi) ; `admin-chat.js` (✅ → ✓ car `.text()` n'affiche pas les emojis ; accent `#4ADE80`→`#2ECC8A`) ; `houetor-selfhare.php` (Google Fonts Syne+DM Sans, header version aligné `1.0.2`) |
| **⚠️ DIVERGENCE prod/lab découverte** | ✅ 8 fichiers diffèrent entre HEAD prod et HEAD lab : le lab (testé 36/36, Exp 017) a les correctifs sécurité **absents du prod** — preview_token serveur obligatoire, CAS global, rate limit créations, routines actives, produits réels, journal paginé, license chiffrée, uninstall complet, `'version'` localize + footer. Le prod n'a que le restyle UI (état 1.0.2 partiel) |
| **Sync lab (4 fichiers)** | ✅ CSS + class-agent-chat.php copiés (bases HEAD identiques), JS et houetor-selfhare.php **édités ciblés** pour PRÉSERVER les correctifs lab (preview_token lignes 349/383-384, journal paginé, footer, localize version intacts) — mêmes stats diff que prod (JS 4 lignes, PHP 3 lignes) |
| **Vérifs** | ✅ `php -l` 0 erreur ×2 (prod + lab, `class-agent-chat.php` + `houetor-selfhare.php`) ; `node --check` OK ×2 (JS prod + lab) |
| **Commits + push** | ✅ prod `4bf9681` (`mcp-block-crud-2.7.0`, poussé) ; lab `dcadf1f` (`opencode-learning`, poussé) — 4 fichiers chacun, probes-*.mjs toujours écartés |
| **Zip** | ✅ `outputs/houetor-selfhare.zip` régénéré par `git archive --prefix=houetor-selfhare/ HEAD:houetor-selfhare` (chemins `/`, jamais Compress-Archive) — 24 fichiers, 33 184 octets, structure racine `houetor-selfhare/` vérifiée (⚠️ 1er essai `HEAD houetor-selfhare` = double imbrication → corrigé avec `HEAD:houetor-selfhare`) |

**⚠️ IMPORTANT — zip = état prod, SANS les correctifs du lab** : le zip régénéré (comme l'actuel avant lui) distribue la version prod qui n'a PAS preview_token obligatoire/CAS/rate limit créations. Pour distribuer la version testée 36/36, il faut porter les 8 fichiers du lab vers le prod (ou générer le zip depuis le lab). **Décision utilisateur demandée.**

**Pour reprendre** : décision utilisateur : (a) porter les correctifs 1.0.2 du lab → prod (8 fichiers : admin-chat.js, houetor-selfhare.php, class-agent-dispatch.php, class-agent-routines.php, class-error-translator.php, class-license.php, readme.txt, uninstall.php) puis regénérer le zip ; (b) ou distribuer le zip prod actuel tel quel. Fils ouverts inchangés (merge `mcp-block-crud-2.7.0`, lint global 62 erreurs, roadmap, probes untracked). Docs : EXPERIMENTS_LOG Exp 024.

## BOUCLE AGENT SELFHARE + DÉPLOIEMENT FIX DAY — Session 2026-08-04 (Exp 025 : lectures auto, enchaînement, dernière confirmation conservée)

**Mission utilisateur** : améliorer l'UX agent selfhare — (1) les lectures ne doivent plus demander confirmation, (2) l'agent doit enchaîner les actions, (3) MAIS **une dernière confirmation doit toujours rester avant toute modification/création** (décision finale : garder le panneau existant tel quel).

| Élément | État |
|---|---|
| **Décisions utilisateur** | ✅ Continue seul après écriture exécutée ; **4 étapes max** par demande ; lectures auto visibles en discret + indicateur de chargement animé ; **lab d'abord** ; **confirmation finale conservée** (panneau aperçu avant/après + preview_token serveur) |
| **Implémentation lab (4 fichiers, commit `9f66dec` poussé)** | ✅ `class-agent-dispatch.php` (`is_read_action()`) ; `class-agent-chat.php` (`MAX_AGENT_ITERATIONS=4`, `agent_loop()` = boucle relay : exécute les lectures via Dispatch::execute, **s'arrête sur la 1re écriture sans jamais l'exécuter**, stop si lecture répétée md5, `step_label()`, `call_relay()` ; ajax branché sur agent_loop + renvoie `steps`) ; `admin-chat.js` (`sendChat()` réutilisable + silent, affichage `.step`, `state.lastUserMessage`, **reprise auto après écriture confirmée**) ; `admin-chat.css` (`.step` discret bord vert, `@keyframes loadingDots`) |
| **Vérifs** | ✅ `php -l` 0 erreur (14 fichiers) ; `node --check` OK |
| **⚠️ Zip Exp 024 invalide (double imbrication)** | ✅ Découvert à l'upload : le zip Exp 024 contenait `houetor-selfhare/houetor-selfhare/…` → WP « Aucune extension trouvée ». Bonne commande : `git archive --format=zip --prefix=houetor-selfhare/ -o outputs/houetor-selfhare.zip HEAD:houetor-selfhare/houetor-selfhare` (arbre du dossier plugin). Zip rebâti : 24 fichiers, 37 870 octets, racine `houetor-selfhare/houetor-selfhare.php` ✓ |
| **Déploiement Fix Day** | ✅ Upload wp-admin curl (login + nonce **du formulaire upload** + multipart) → 1er essai KO (zip imbriqué), 2e essai « dossier existe déjà » + **réplication TasteWP entre serveurs du pool** (vérifs 404/absent trompeuses pendant ~minutes) → après propagation : **installé + ACTIF** (plugins.php, statut Désactiver) |
| **Licence selfhare** | ✅ Connectée par l'utilisateur → vérifiée `admin.php?page=houetor-selfhare` : « **Licence active — Plan : starter — Clé : SLH-starter-732251c8…** » + sous-menus **Assistant** et **Routines** présents (is_active()=true) |

**État Fix Day (2026-08-04)** : `houetor-selfhare` 1.0.2 + boucle Exp 025 **installé/actif, licence starter active** — prêt à tester la boucle en conditions réelles (lectures auto, enchaînement, confirmation avant écriture, compteur 4, loader).

**⚠️ Point de vigilance (souligné par l'utilisateur)** : **jamais modifié un bloc EXISTANT du site en réel** (ni connect ni selfhare) — tous les updates validés sur Fix Day (Exp 018/020) portaient sur des blocs créés par l'agent dans le même flux (refs `e2eprod-…`/`agenttest-…`), pages restaurées à l'identique (md5 d'origine). À valider en premier avec la boucle : modif d'un bloc starter (CAS frais, fallback index sans ref, reformatage `wp_kses_post`, restauration révision).

**Pour reprendre** : tester la boucle en réel sur Fix Day (message → lectures auto en `.step` sans confirmation → écriture proposée avec panneau de confirmation → exécuter → reprise auto de vérification) — **le 1er scénario d'écriture doit être la modification d'un bloc starter existant** (texte existant, cf. point de vigilance) ; puis portage prod (8 fichiers correctifs Exp 024 + 4 fichiers boucle Exp 025) + zip + docs Exp 026. Fils ouverts inchangés (merge `mcp-block-crud-2.7.0`, lint global 62 erreurs, roadmap, probes untracked). Docs : EXPERIMENTS_LOG Exp 025.

## PREMIÈRE MODIFICATION D'UN BLOC EXISTANT EN RÉEL — Session 2026-08-04 (Exp 026 : point de vigilance Exp 025 LEVÉ, 5/5 PASS)

**Mission utilisateur** : « le test de modifications sur les blocs existants » — modifier un bloc **préexistant du site** (jamais fait en réel, point de vigilance Exp 025). Via le MCP prod (portage `mcp-block-crud-2.7.0`), serveur Next relancé (`next build` + `next start -p 3010`), page 5 About de Fix Day.

| Élément | État |
|---|---|
| **État réel page About** | ✅ md5 `d35956a796fb5b14c79cfda5c1065b82`, 6 blocs racine : index 0+2-5 starter `atomic-wind/box` (content vide, **chacun 1 innerBlock**), index 1 groupe agent ref `agenttest-c4d8da4ddf28` (bloc Exp 021) |
| **A — bloc starter imbriqué** | ✅ dry_run ET réel → **refus par design** : « contient des blocs imbriqués et ne peut pas être modifié directement » (class-block-editor.php:324), contenu intact, md5 inchangé |
| **B — bloc EXISTANT modifiable** | ✅ dry_run sans effet → update RÉEL (texte modifié dans le groupe agent) → **SUCCÈS, ref conservée**, md5 `d35956a7…`→`afae5278…`, relecture vérifiée |
| **C — restauration** | ✅ update inverse → **md5 final == md5 initial identique** (`d35956a7…`), pas de delta de reformatage (HTML déjà normalisé wp_kses_post) |
| **Preuves brutes** | ✅ REST core final (len 22052, texte original présent, TEXTE MODIFIE absent, marqueurs HWC intacts) ; **révisions 108** (état modifié) + **109** (état restauré) = 1 révision/écriture |

**Découverte structurante** : les blocs starter `atomic-wind/box` (avec innerBlocks) ne sont **jamais** modifiables via update_block_content (refus propre avant dry_run) — l'agent doit cibler les blocs sans innerBlocks (groupe agent) ou passer par les ops structurelles. Comportement design (V3-6), désormais **confirmé en réel**.

**Pour reprendre** : (1) **tester la boucle selfhare en réel dans l'UI Fix Day** (lectures auto + confirmation + reprise auto) — 1er scénario d'écriture : modif d'un texte dans un bloc **modifiable** (groupe agent ref `agenttest-c4d8da4ddf28` — uniquement si l'utilisateur valide ; sinon bloc créé par la boucle) ; (2) portage prod selfhare (8 fichiers correctifs Exp 024 + 4 fichiers boucle Exp 025) + zip ; (3) serveur Next relancé si besoin (procédure Exp 018). Fils ouverts inchangés : merge `mcp-block-crud-2.7.0` → main houetor, lint global (62 erreurs), roadmap (compte agent WP, rate limit rewrites, PHPUnit), 3 `probe-*.mjs` untracked (écartés). Secrets en stock (jamais commités) : `.env.learning` (lab) + `.env.local` (houetor). Docs : EXPERIMENTS_LOG Exp 026.

**Complément (Exp 026 bis — carte des capacités blocs starters, dry_run seul)** : 8 ops MCP prod testées en dry_run sur le bloc starter index 0 (atomic-wind/box avec innerBlocks) : **4 refus par design** (update_block_content, update_blocks batch, transform_block, unwrap_block — garde-fous class-block-editor.php:324/417/591/1041) / **4 OK structurelles** (duplicate, move, wrap, delete — sous-arbre entier, contenu non touché). `locate_block` ne descend jamais dans les innerBlocks (racines seules, lignes 257-271). Page intacte (md5 `d35956a7…` inchangé). **Piste évolution pour « corriger une section starter » : route `replace_block` (remplacement sous-arbre entier, CAS+révision+audit) — candidat roadmap à valider.**

## PATCH BLOCS IMBRIQUÉS 2.8.0 — Session 2026-08-04 (Exp 027 : correctif fourni porté + validé lab + DÉPLOYÉ EN RÉEL Fix Day, 10/10 PASS)

**Mission utilisateur** : correctif d'édition des blocs imbriqués (fichier `class-block-editor.php` fourni + 2 scripts de test) → porter dans le lab, valider, déployer et valider en conditions réelles sur Fix Day. Le 2.7.0 refusait toute écriture sur un bloc imbriqué et `locate_block` ne descendait pas dans les innerBlocks.

| Élément | État |
|---|---|
| **Portage lab** | ✔ working tree patché : `houetor-connect.php` (HWC_VERSION **2.8.0**), `includes/class-block-editor.php` (== fichier fourni), `readme.txt` (changelog) — `php -l` 0 erreur (14 fichiers) |
| **Architecture du patch** | ✔ `locate_block()` inchangé (move/duplicate/wrap/unwrap, racines) ; **`locate_block_deep()`** nouveau (update L382, batch L483, transform L651) ; **exposition enfants** : `flatten_blocks_recursive` ajoute `parent_ref` (index du parent), `depth`, `has_children`, `child_count` ; nouveau message refus conteneur « est un conteneur (il a des blocs enfants)… cible l'un d'eux par sa propre ref/index » |
| **Tests fournis adaptés (lab)** | ✔ depth1 (group>paragraph) + depth2 (columns>column>paragraph, refs + batch) : **TOUS PASS** ; wrapper permanent `scripts/nested-tests.sh` |
| **Batteries plugin** | ✔ V3 32/32, STRUCTURAL 42/42, TRANSFORM 21/21, RETENTION 9/9, TIER POLICY 11/11 — md5 page 2 `c4abdffe…` restauré identique (RISQUE 1 : référence avec 2 core/quote imbriqués → index globaux ≠ top-level, batteries sur refs/index `_top`) |
| **MCP miroir** | ✔ mirror-suite : unitaires 42/42, intégration 52/52, scénarios 41/41 |
| **RISQUE 2** | ✔ ancien message « est un conteneur » : reste dans selfhare `class-agent-dispatch.php:854` (hors périmètre) + docs Exp 025/026 (historique conservé) — aucune assertion de test dessus |
| **Déploiement Fix Day — méthode « jumeau »** | ✔ upload direct refusé (« dossier existe déjà ») → zip préfixé **`houetor-connect-280/`** + upload + **désactivation 2.7.0 / activation jumeau** (nonces wp-admin) → **2.8.0 ACTIF**, token/Supabase préservés (options partagées, hwc_deactivate ne touche pas le token, hwc_activate ne le régénère que s'il est absent) |
| **Validation E2E réelle** | ✔ **10/10 PASS** (page de test 135 supprimée après) : wrap [A..B] → core/group `child_count=2`/`has_children=true` ; **enfants exposés depth=1 + parent_ref=1** ; preuve REST raw avant (marqueurs `HWC lab-…`) ; **update_block_content sur ENFANT par ref → SUCCÈS (LE test du patch)** ; raw après : modifié présent / ancien absent / frère B + structure `wp:group` intactes |
| **Nettoyage** | ✔ pages de test supprimées (111/118/124/129/135) + page diag 110 « diag-tmp » (`deleted:true`) ; « Privacy Policy » draft natif laissé ; blocs starter intacts |

**Comportements réels à connaître (adaptations des attentes)** : blocs **sans ref custom → `ref=null`** (cibler par index) ; `parent_ref` = **index global du parent** (entier, pas la ref) ; raw REST exposé seulement en `?context=edit`.

**Pour reprendre** : commits ciblés lab + push `opencode-learning` (docs Exp 027 + patch + tests fournis + adaptations MCP miroir). Fils ouverts : portage prod selfhare (8 fichiers Exp 024 + 4 Exp 025) + zip (**méthode jumeau disponible**) ; merge/rollout `mcp-block-crud-2.7.0` (**le portage MCP prod devra suivre le patch 2.8.0** pour exposer/éditer les enfants imbriqués) ; suppression éventuelle du dossier `houetor-connect` (2.7.0) sur Fix Day ; lint global houetor (62 erreurs) ; roadmap (compte agent WP, rate limit rewrites, PHPUnit, **replace_block**) ; 3 `probe-*.mjs` untracked (écartés). Secrets en stock (jamais commités) : `.env.learning` (lab) + `.env.local` (houetor). Docs : EXPERIMENTS_LOG Exp 027.

## MODIFICATION D'UN ENFANT IMBRIQUÉ STARTER EN RÉEL — Session 2026-08-04 (Exp 028 : patch 2.8.0 validé sur blocs préexistants du starter, page About restaurée au md5 EXACT)

**Mission** : après le patch 2.8.0 validé sur des blocs créés par l'agent (Exp 027), valider l'édition des blocs **préexistants du starter** — le cas des 4 refus par design d'Exp 026 bis. Serveur Next relancé (`next build` + `next start -p 3010`). Cible : idx 2 = `atomic-wind/text` « About Us » (depth 2, parent_ref 1, ref null → index).

| Élément | État |
|---|---|
| **Smoke test** | ✅ GET SSE 200 auth + get_page_blocks : **80 blocs sur tous les niveaux** (avant : 6 racines) avec parent_ref/depth/has_children/child_count, md5 `d35956a7…` intact |
| **update enfant imbriqué starter (idx 2)** | ✅ **7/7 PASS** : dry_run accepté (avant : refus) → update RÉEL « MODIF TEST » → preuve REST raw (modif présent, parents intacts) → restauration → « About Us » d'origine |
| **batch update_blocks idx 2** | ✅ PASS (modif + restauration) |
| **transform enfant imbriqué** | ✅ `atomic-wind/text` → refus par design (types core texte uniquement, garde-fou) ; sur enfant **core/paragraph** (page jetable, wrap) : **paragraph→heading→paragraph PASS** (ref conservée, depth 1) |
| **Delta md5** | ✅ après restauration : raw 22052→22050 = **1 seul `\n` retiré** par `serialize_blocks` (`-->\n<span`→`--><span`) ; structures 79 blocs + refs + texte visibles identiques → normalisation canonique (famille Exp 019), aucun résidu |
| **Restauration exacte** | ✅ réécriture du raw d'origine (rev 109) via REST core → **md5 final == md5 initial `d35956a7…`**, 80 blocs, idx 2 « About Us » intact |
| **Cleanup** | ✅ page jetable 147 supprimée, aucun résidu |

**Découvertes** : (1) le patch 2.8.0 lève les refus d'édition sur enfants imbriqués **starter** (update + batch par index) ; (2) limite conservée : transform = types core texte uniquement (`atomic-wind/text` refusé proprement) ; (3) `ref=null` sur groupe créé par wrap (page jetable) → ciblage par blockName/index ; (4) la restauration via update laisse le delta de normalisation, la réécriture du raw d'origine restaure le md5 exact.

**Pour reprendre** : commit docs Exp 028 + push. Fils ouverts : portage prod selfhare (8 fichiers Exp 024 + 4 Exp 025) + zip (**méthode jumeau**) ; merge/rollout `mcp-block-crud-2.7.0` → main houetor (portage MCP prod validé avec comportement 2.8.0 via le plugin) ; suppression dossier `houetor-connect` (2.7.0) Fix Day ; lint global houetor (62) ; roadmap (replace_block, compte agent WP, rate limit rewrites, PHPUnit) ; 3 `probe-*.mjs` untracked (écartés). Serveur Next actif (3010), relance : `next build` + `next start -p 3010`. Secrets en stock (jamais commités) : `.env.learning` (lab) + `.env.local` (houetor). Docs : EXPERIMENTS_LOG Exp 028.

## MODIFICATION RÉELLE PAGE CONTACT — Session 2026-08-04 (Exp 029 : bloc starter imbriqué choisi par l'utilisateur, « gardons ça », feu vert audit selfhare)

**Mission** : l'utilisateur choisit un bloc sur la page **Contact** (id 8) de Fix Day et demande la modification d'un texte — puis valide et garde le résultat. Enchaînement : doc Exp 029 → **audit selfhare** (même capacité d'édition) → mise à jour si nécessaire (**feu vert donné**).

| Élément | État |
|---|---|
| **Cible** | ✅ idx 4 `atomic-wind/text` (depth 2, ref null) « Have questions or ready to book your appointment? Reach out to our friendly team — we're here to help you smile. » (page Contact, 57 blocs exposés, md5 `f309e426…`) |
| **Process** | ✅ dry_run (200, rien écrit) → validation utilisateur « vas-y » → écriture réelle CAS frais → md5 `f309e426…`→`106e1db0…` → relecture + preuve REST brute (MODIF présent / ORIG absent / structure intacte) |
| **Décision utilisateur** | ✅ « gardons ça, ça fonctionne » — **la modification est CONSERVÉE en réel** |
| **Suite demandée** | ✅ vérifier si `houetor-selfhare` sait faire la même chose (modification bloc existant, y compris imbriqué) ; **feu vert de le mettre à jour** |

**Pour reprendre** : audit selfhare → comparer sa capacité d'édition de blocs (existant + imbriqué) vs connect 2.8.0 (locate_block_deep, exposition parent_ref/depth/has_children/child_count, refus conteneur actionnable) → porter les écarts si nécessaire (feu vert). Fils ouverts inchangés : portage prod selfhare (8 fichiers Exp 024 + 4 Exp 025) + zip ; merge/rollout `mcp-block-crud-2.7.0` ; dossier 2.7.0 Fix Day ; lint global (62) ; roadmap. État Fix Day : Contact md5 `106e1db0…` (modifié, conservé), About `d35956a7…` intact, plugin 2.8.0 actif, serveur Next actif (3010). Docs : EXPERIMENTS_LOG Exp 029.

## SELFHARE 1.0.3 ÉDITION BLOCS IMBRIQUÉS — Session 2026-08-04 (Exp 030 : audit → portage → déploiement Fix Day → test réel complet)

**Mission** : audit demandé Exp 029 → selfhare 1.0.2 **incapable** (get_page_blocks top-level, refus imbriqués L853-854) → portage pattern connect 2.8.0 + **bug pré-existant corrigé** (compute_preview réécrivait `update_block_content`/`delete_block` en `*_content` — case inatteignable) → **1.0.3** (commit `d71a302`).

| Élément | État |
|---|---|
| **get_page_blocks** | ✅ `flatten_blocks_recursive` : tous les blocs, `parent_ref`/`depth`/`has_children`/`child_count` |
| **update_block_content** | ✅ `locate_block_deep` (profondeur quelconque, par index global) ; refus conteneur **actionnable** (« parent_ref = #N, cible un enfant par son index ») |
| **Tests locaux** | ✅ **53/53 PASS** (selfhare-test-016.php étendu : flatten, conteneur refusé, enfant imbriqué modifié, index introuvable bornes 0-3) |
| **Déploiement Fix Day** | ✅ méthode jumeau (zip `houetor-selfhare-103/` 37 824 o, upload wp-admin, bascule désactiver/activer) → **1.0.3 ACTIF** (page admin « SelfHare v1.0.3 ») ; ⚠️ mêmes slugs WP → data-slug partagés, vérif par footer admin + test AJAX |
| **Test réel (AJAX dispatch)** | ✅ lecture Contact : **57 blocs aplatis** (enfants exposés, bloc Exp 029 visible) ; **écriture réelle About idx 2 (depth 2)** « About Us » → « About Us [TEST 1.0.3] » CAS OK ; **restauration exacte** « About Us » 0 résidu ; **delta = 1 `\n`** serialize_blocks (canonical, rev 154 `d35956a7…` vs actuel `856c1c99…`, état canonique déjà vu rev 146/144/142) ; **idempotent** (2e round-trip md5 identique) |
| **Note dry_run** | ✅ preview_token obligatoire avant dry_run sur écriture (design Exp 017, pas un bug) |
| **TEST RÉEL 2 — CRÉATION DE BLOC PAGE BLOG (suite de session, 2026-08-04)** | ✅ **inject_page réussi** : page Blog id 7 (slug `blog`, « Page Placeholder » starter = 2 blocs : atomic-wind/box → enfant atomic-wind/text) ; preview obligatoire OK (token `lcfU5iqkgVWYaUhK7OOh`, summary before/after correct) ; **exécution** : révision `168`, ref `sh_blk_9789976e9`, message « Contenu injecté en footer » ; **2 → 4 blocs** (ajoutés en fin d'arbre : index 2 `core/heading` « Test réel selfhare 1.0.3 » + index 3 `core/paragraph` « Bloc créé par l'agent lab HOUETOR le 2026-08-04 sur la page Blog (injection footer). ») ; **contenu starter intact** ; **preuve REST indépendante** : `<!-- sh:ref:sh_blk_9789976e9 --><h2 class="wp-block-heading">…</h2><p class="wp-block-paragraph">…</p><!-- /sh:ref:… -->` visible dans `content.rendered` de `/wp-json/wp/v2/pages/7`. **⚠️ MAIS invisible sur le front : page 7 = page d'index des articles (posts page) → post_content ignoré** (comportement standard WP). → **nettoyé + refait sur Services** : |
| **TEST RÉEL 3 — CRÉATION DE BLOC SUR UNE VRAIE PAGE DE CONTENU : SERVICES (suite de session, 2026-08-04)** | ✅ nettoyage Blog : delete_block ref `sh_blk_9789976e9` (rév 169→170), page 7 restaurée 2 blocs ; **inject_page footer sur Services (43)** : 102 blocs avant (starter riche) → preview OK → exec OK (rév `172`, ref `sh_blk_4fb3a7e2e`) → **104 blocs** (index 102 `core/heading` + 103 `core/paragraph` en fin d'arbre) ; **VISIBLE SUR LE FRONT** : `https://fixday.s6-tastewp.com/services/` contient « Test réel selfhare 1.0.3 » + paragraphe (preuve HTML GET) |
| **TEST RÉEL 4 — MODIFICATION D'UN BLOC IMBRIQUÉ EXISTANT SUR SERVICES (suite de session, 2026-08-04)** | ✅ **update_block_content réussi sur bloc imbriqué starter** : page Services (43), **index 3** `atomic-wind/text` (**depth 2, parent_ref 1**, contenu « Our Dental Services ») → « Our Dental Services [TEST HOUETOR 1.0.3] » ; preview obligatoire OK (token `jaFXSWOIyz6UDskCoyWm`, expected_hash `356d145e…`) ; exec OK (CAS) ; **relecture : 1 SEUL bloc modifié (index 3), les 103 autres intacts** (diff vérifiée) ; **preuve front** : `/services/` contient le nouveau texte, ancien seul absent. ✅ **Bloc #3 GARDÉ par décision utilisateur (2026-08-04)** — le texte « Our Dental Services [TEST HOUETOR 1.0.3] » reste en place, plus de restauration prévue |

**Pour reprendre** : ✅ **nettoyage fait (suite de session, 2026-08-04)** : `uninstall.php` du 1.0.2 **neutralisé via plugin-editor** (noop — il détruisait licence/tables/rôle partagés) puis dossier `houetor-selfhare/` **supprimé** via plugins.php delete-selected (302 `deleted=1`) ; vérifié : seul `houetor-selfhare-103` reste (actif, « SelfHare v1.0.3 ») + **preuve fonctionnelle AJAX** : dispatch `_wpnonce` + `tool_call={name:'get_page_blocks', params:{page_id:8}}` → success, **57 blocs**, imbrications exposées (⚠️ rappel format AJAX selfhare : champ `_wpnonce` — pas `nonce` — et `tool_call.params` — pas `arguments`). Portage prod selfhare à enrichir : 8 correctifs Exp 024 + 4 boucle Exp 025 + **1.0.3 imbriqué** (class-agent-dispatch.php) → repo `houetor` (copie du fichier, php -l, zip, upload TasteWP jumeau si dossier existant). Fils ouverts inchangés : merge/rollout `mcp-block-crud-2.7.0` ; dossier 2.7.0 connect Fix Day ; lint global (62) ; roadmap. État Fix Day : selfhare 1.0.3 actif (dossier unique `houetor-selfhare-103`), connect 2.8.0 actif, About md5 `856c1c99…` (canonique, sémantiquement identique à `d35956a7…`), Contact `106e1db0…`, **Services : 2 artefacts de test GARDÉS par décision utilisateur — bloc injecté footer (ref `sh_blk_4fb3a7e2e`, h2+p, rév 172) + bloc imbriqué #3 modifié (« Our Dental Services [TEST HOUETOR 1.0.3] », rév 172+1)** ; le bloc Blog `sh_blk_9789976e9` a été supprimé après découverte posts page ; serveur Next actif (3010). Docs : EXPERIMENTS_LOG Exp 030.

## ADDENDUM UTILISATEUR — PREUVE BRUTE AVANT/APRÈS PATCH BLOCS IMBRIQUÉS — Session 2026-08-04 (Exp 030 bis : validation complète, ✅ FAIT)

**Mission** : l'utilisateur exige avant conclusion la preuve brute que le refus des blocs conteneurs est un comportement VOLONTAIRE couvert par des tests nommés (V3-6, T7, T8, T10), qu'aucun de ces scripts n'utilise d'index hardcodé, et qu'un test NOUVEAU cible un enfant DANS un conteneur natif avec succès sans toucher au parent.

| Élément | État |
|---|---|
| **Risque index hardcodé** | ✅ **ÉCARTÉ** : V3-6 (rest-test-v3.php L145-148) et T10 (rest-test-transform.php L175-179) localisent le bloc imbriqué **dynamiquement** via get_page_blocks (scan par blockName, aucun littéral d'index) ; T2 recalculé depuis ref (L77-78) |
| **AVANT (2.7.0)** | ✅ refus fonctionnel présent (400 + abandon + rien écrit) mais libellé 2.7.0 « contient des blocs imbriqués » → V3-6/T10 FAIL d'assertion de libellé ; **alignement assertion** (accepte « conteneur » OU « imbriqué(s) ») → **V3 32/32 + TRANSFORM 21/21 PASS** |
| **APRÈS (2.8.0)** | ✅ après restauration (md5 identique source) : **V3 32/32 + TRANSFORM 21/21 PASS** — V3-6 (400 « est un conteneur … Cible directement un enfant (parent_ref = cet index) » + md5 inchangé), T7 (CAS 409), T8 (dry_run), T10 (400 « conteneur ») |
| **Test NOUVEAU** | ✅ `scripts/rest-test-nested-child-native.php` **11/11 PASS** (2.8.0) : enfant DANS core/quote natif #1 (idx 4, enfant idx 5, ref NULL) → dry_run sans effet → **écriture réelle OK, parent intact (child_count=1)** → restauration → **md5 final == initial `c4abdffec127…`** |
| **Conclusion** | ✅ refus conteneur = comportement volontaire couvert AVANT et APRÈS (tous PASS) ; capacité nouvelle (édition enfant DANS conteneur natif par index global) prouvée ; patch 1.0.3/2.8.0 conforme à l'addendum |

**Pour reprendre** : addendum clos (Exp 030 bis documenté dans EXPERIMENTS_LOG). Scripts de test mis à jour : `rest-test-v3.php` (V3-6) + `rest-test-transform.php` (T10) — assertions robustes au libellé ; nouveau `rest-test-nested-child-native.php` (11/11). Lab : connect 2.8.0 restauré (md5 `class-block-editor.php` identique source `1bb175a5…`), page 2 md5 `c4abdffec127…`. Commit/docs : EXPERIMENTS_LOG Exp 030 bis + LEARNING_STATE + AGENTS.md (poussés). ⚠️ **Vérifié 2026-08-04 (session pause)** : `Downloads\files.zip` re-analyse = matériau source Exp 027 (class-block-editor.patch + class-block-editor.php **md5 identique** `1bb175a5…` = commit `7400476` + 2 tests stub `/home/claude/wp-stub` hors lab) — **rien de nouveau, aucune action**. Fils ouverts inchangés : portage prod selfhare 1.0.3, merge/rollout `mcp-block-crud-2.7.0`, dossier 2.7.0 connect Fix Day, lint global (62), roadmap.

## SECTION 27 — CHANTIER BLOCS IMBRIQUÉS REJOUÉ — Session 2026-08-05 (Exp 031 : étapes 1-6 exécutées, preuves brutes, commits ciblés)

**Mission** : la Section 27 du repo utilisateur exige, dans l'ordre : (1) retrouver/reconstruire les scripts de test et les commiter, (2) prouver le patch appliqué, (3) vérifier RISQUE 1 (renumérotation d'index), (4) rejouer la batterie complète (preuves brutes), (5) documenter les validations réelles Fix Day, (6) mettre à jour le wrapper MCP (tools.ts + error-translator.ts, 4 champs imbriqués). Pas de merge `mcp-block-crud-2.7.0`/`main` sans l'utilisateur.

| Étape | État |
|---|---|
| **1 — Scripts commités** | ✅ `houetor-connect/tests/` (11 fichiers + README, commit `c18aa1d`) ; harnesses portables (`getenv WP_INC/HWC_PLUGIN_INC`) ; **bug corrigé** : série 001 non-idempotente (T14 détruisait page 2) → cleanup restauration révision → **md5 final == initial sur 2 runs** ; `php -l` 0 erreur |
| **2 — Patch prouvé** | ✅ md5 `class-block-editor.php` = **1BB175A547CEE0220F7B94533AABBB35** (== fourni) ; `flatten_blocks_recursive` L43, `locate_block_deep` L321 (récursion innerBlocks L336) sur toutes les écritures (update L382, batch L483, transform L651) ; messages « est un conteneur » L399/493/668 |
| **3 — RISQUE 1 écarté** | ✅ aucun stockage d'index inter-requêtes (plugin : transients = settings/status/rate-limit uniquement, 87 occurrences `block_index` = paramètre par requête ; MCP : 50+26 pass-through, seul « cache » = header HTTP) → lecture fraîche + CAS obligatoires |
| **4 — Batterie complète** | ✅ depth1 ALL PASS ; depth2 « TOUS LES TESTS PASSENT » ; nested-child-native 11/11 ; V3 **32/32** ; transform **21/21** ; structural **42/42** ; retention **9/9** ; tierpolicy **11/11** ; série 001 (18) + V2 (14) + test-connect standalone **35 PASS/0 FAIL** ; MCP vitest **42/42** + integration **52/52** + **scénarios 41/41** (S0-S12) |
| **5 — Validations réelles** | ✅ couvertes par Exp 027/028/029/030 bis (déjà documentées, aucune action prod nouvelle) |
| **6 — Wrapper MCP portage** | ✅ commit `0aa53d5` : `portage-app-mcp/src/tools.ts` (description get_page_blocks alignée `src/tools.ts:29`, 4 champs) + `error-translator.ts` (3 cas 2.8.0 : conteneur écriture/batch, 404 imbriqué, conteneur transform) ; `dispatch.ts` inchangé (pass-through REST → champs du plugin) ; **tsc 0 erreur miroir + portage** |

**Pour reprendre** : Section 27 exécutée de bout en bout (Exp 031 documenté dans EXPERIMENTS_LOG, commits `c18aa1d` + `0aa53d5` + docs poussés). Note run MCP : wrapper `run-integration.sh` supprime le helper token → **recopier `hwc-get-token.php` dans `/tmp` avant chaque run** ; scenarios = reset rate limit (`wp option delete _transient_hwc_ratelimit_2`) ; `/tmp` WSL ne survit pas entre invocations (`wsl` s'arrête). Fils ouverts inchangés : portage prod selfhare 1.0.3, merge/rollout `mcp-block-crud-2.7.0`, dossier 2.7.0 connect Fix Day, lint global (62), roadmap. Probes `probe-*.mjs` + `outputs/` restent untracked/écartés.

## ROADMAP MARCHÉ LANCÉE — Session 2026-08-05 (Exp 031 bis : portage prod Étape 6, zip 2.8.0, suite 1 commande, rotation token)

**Mission** : l'audit « qu'est-ce qui bloque la mise sur le marché » (serveur MCP non mergé/déployé, connect zip 2.7.0, selfhare 1.0.3 non packagé, token statique, pas de CI) a donné lieu à un **plan validé par l'utilisateur** : `docs-learning/ROADMAP_MARKET.md` (19 items P0→P3). Démarrage des tâches à dépendance nulle.

| Élément | État |
|---|---|
| **#1 Portage Étape 6 sur branche prod** | ✅ commit `3749151` (repo houetor, `mcp-block-crud-2.7.0`, poussé) : `app/mcp/tools.ts` (get_page_blocks 4 champs) + `app/mcp/error-translator.ts` (3 cas 2.8.0) ; diff miroir lab↔prod = **vide** ; tsc 0 erreur ; eslint 0 |
| **#5 Zip officiel 2.8.0** | ✅ `git archive` depuis HEAD lab ; diff vs zip 2.7.0 = 3 fichiers modifiés (houetor-connect.php, class-block-editor.php, readme.txt) + 11 tests ajoutés, 0 retrait ; md5 class-block-editor `1bb175a5…` ; commits `0129edd` puis `4f81b0d` (régénéré après rotation) dans `houetor/outputs/` |
| **#7 readme.txt** | ✅ déjà à jour (stable tag 2.8.0 + changelog 2.7.0/2.8.0 imbriqué) |
| **#15 Suite 1 commande** | ✅ `houetor-connect/tests/test-suite.sh` → **19/19 PASS, exit 0** (2 runs ; preuve `outputs/test-suite-run4.log`) : 8 batteries wp eval + 3 harnesses + MCP (vitest 42, integration 52, scenarios 41) ; restauration pages AVANT chaque batterie (causait le 20/1 transform au run 1) ; relance serveur auto ; timeouts ; critère par batterie (bilans + marqueurs IDENTIQUE/FIN V2/RETENTION) |
| **🔴 Rotation token** | ✅ `eHlibQROp3fU00hrR8EFJqJJ0cuM9pJy` hardcodé dans 4 batteries commitées + ONBOARDING.md et ÉGAL au vrai token WP (SAME vérifié) → **token WP roté (32 car., jamais affiché, l'ancien est révoqué)** ; batteries en `get_option('hwc_token','')` ; ONBOARDING.md nettoyé ; commit lab `2ff7421` (non poussé) ; vérif `eHlib` = 0 occurrence (lab + zip extrait) |
| **🐛 test-connect silencieux résolu** | ✅ guard `defined('ABSPATH') || exit;` (class-hwt-parser.php:2, class-connect-status.php:2) tuait la CLI → wrapper `test-connect-run.php` (ABSPATH stub) → **35 PASS / 0 FAIL** (4628 octets) |
| **Commits prod poussés** | ✅ `3749151` (portage) + `4f81b0d` (zip 2.8.0 propre) sur `mcp-block-crud-2.7.0` ; main houetor intact |

**⚠️ IMPORTANT — fuite corrigée dans le repo PUBLIC** : le littéral `eHlib…` ne doit JAMAIS réapparaître (il identifiait le token WP lab révoqué). Vérification de non-régression = `grep eHlib` → 0 partout.

**Pour reprendre** : (1) **push lab en attente** : commit `2ff7421` (token dynamique + test-connect-run.php + test-suite.sh + ONBOARDING.md) + docs Exp 031 bis (EXPERIMENTS_LOG, LEARNING_STATE, AGENTS.md, ROADMAP_MARKET.md) → `opencode-learning` ; (2) puis décisions utilisateur : merge `mcp-block-crud-2.7.0` → main houetor (#2), déploiement Vercel (#3), E2E contre serveur déployé (#4), dossier Fix Day 2.8.0 (#6), packaging selfhare 1.0.3 version unique (#8/#9), artefacts Fix Day (#10) ; (3) chantiers lab proposables : #11 compte agent moindre privilège, #12 rate limit global, #14 lint 62, #16 PHPUnit. Probes `probe-*.mjs` + `outputs/` untracked (écartés). Secrets en stock (jamais commités) : `.env.learning` (lab) + `.env.local` (houetor).

## MISSION 2 + 3 TERMINÉES — Session 2026-08-05 soir (Exp 032 : Fix Day 2.8.0 officiel + selfhare 1.0.3 version unique, tests réels)

**Mission** : la session a exécuté les missions qui restaient sur Fix Day : (2) remplacer l'installation 2.7.0 par le zip officiel 2.8.0 (avec rotation de token déjà faite) ; (3) porter selfhare 1.0.3 en version unique dans le repo prod + zip + swap + tests réels via MCP. **Les deux sont terminées et vérifiées.**

| Mission | État |
|---|---|
| **2 — Connect 2.8.0 officiel Fix Day** | ✅ dossier `houetor-connect/` 2.8.0 officiel **ACTIF, unique** (suppression 2.7.0 en 2 étapes : confirmation `verify-delete=1` + POST — piège uninstall.php qui supprime `hwc_token` ; suppression jumeau 280 ; upload zip officiel ; activation ; **token WP restauré** `3QOCQ0…` = `TASTEWP_FIXDAY_PLUGIN_TOKEN` après activation (régénéré car option vide) puis réécrit via `options.php` — nonce `hwc_settings_group`, whitelist après activation). Vérif : plugins.php Version 2.8.0 + `class="active"` ; MCP : 32 tools, Fix Day listé, About 80 blocs md5 `856c1c99…` |
| **3 — Selfhare 1.0.3 version unique** | ✅ 10 fichiers lab→prod (diff lab↔prod vide, php -l 0, secrets 0) ; **zip sans `--prefix`** (`git archive -o outputs/houetor-selfhare.zip HEAD -- houetor-selfhare` — 24 fichiers, Version 1.0.3, 0 `eHlib`) ; **swap Fix Day** : upload officiel → désactivation jumeau 103 (conservé inactif en backup — uninstall détruirait la licence) → activation → « Licence active » préservée |
| **⚠️ Incident de branche (prod) corrigé** | ✅ commits selfhare poussés d'abord sur `main` houetor (violation règle) → **revert sur main** (`d973e79`/`d2aed40`, main == `9f8a5d0` diff vide) → **cherry-pick sur `mcp-block-crud-2.7.0`** (`5631f50` + `ab18fcf`, 2 conflits résolus version portée, diff vs commit d'origine vide) → push `4f81b0d..ab18fcf`. **Règle à respecter : jamais de commit/push sur `main` du repo houetor sans l'utilisateur** |
| **Tests réels MCP selfhare (Contact 8)** | ✅ lecture 57 blocs (parent_ref 53/57, depth 5, **imbriqués starter = `ref:null`**) ; update par `ref` échoue (pas de ref) ; **`block_index` sur conteneur → refus design** (message actionnable) ; **`block_index` sur feuille depth 3 → SUCCÈS** (md5 changé) ; **restauration update ≠ exact** (delta 2 octets, famille Exp 028) → **réécriture raw d'origine via révision REST → md5 EXACT** `106e1db0475e74c64028232553743599`, count 57 |
| **Smoke pages** | ✅ About 80 blocs (`856c1c99…`), Services 104 blocs (`3e40316e…`) inchangés ; token connect `3QOCQ0…` présent |

**Découverte structurante (confirmée) : `update_block_content` ne reproduit jamais le raw exact (normalisation `serialize_blocks`) → le md5 exact est le seul critère fiable ; restauration à l'identique = réécriture du raw d'origine (révision).**

**Pour reprendre** : docs Exp 032 + ROADMAP (#8/#9 FAIT) commitées avec ce point de reprise → push `opencode-learning` (avec `2ff7421` en attente). Restent utilisateur : merge #2, Vercel #3, E2E déployé #4, artefacts Fix Day #10, lint #16, README marché #17/#18, licence #19. Fix Day : connect 2.8.0 + selfhare 1.0.3 officiels ACTIFS, jumeau 103 inactif (suppression = décision 👤). Scripts wp-admin éprouvés dans Temp/opencode : `fixday-install-280.mjs`, `fixday-verify-final.mjs`, `fixday-selfhare-install.mjs`, `fixday-restore-contact.mjs`, `fixday-final-check3.mjs` (réutilisables).

## MISSION 4 TERMINÉE — Session 2026-08-05 soir (Exp 033 : E2E serveur déployé 1a→1e + merge selfhare 1.0.3 → main, push OK)

**Mission** : (4) E2E complet contre le serveur MCP **déployé** puis merge selfhare 1.0.3 (`5631f50` + `ab18fcf`) vers `main` du repo houetor, vérif déploiement Vercel, ROADMAP à jour. **Terminée et vérifiée (preuves brutes).**

| Élément | État |
|---|---|
| **1a GET SSE déployé** | ✅ HTTP 200, **32 tools** ; 10/10 tools bloc présents ; **écart consigne** : `get_page_history`/`revert_to_revision`/`batch_update_blocks` n'existent pas dans le code MCP (grep app/mcp prod = 0 occ, tool réel = `update_blocks`) → liste réelle collée |
| **1b list_connected_sites** | ✅ brut : Fix Day `f166ef68-8816-45b0-97f9-d618360a84d6` (user `2566161c-…`, created 2026-08-02T22:34:11, url/token redactés) |
| **1c get_page_blocks About** | ✅ 80 blocs, md5 `856c1c99…` ; 4 champs 2.8.0 présents (parent_ref/depth/has_children/child_count) ; 3 premiers blocs collés (depth 0/1/2, child_count 1/3/0) |
| **1d CRUD complet About** | ✅ dry_run sans effet (md5 inchangé) ; create réel OK (ref null, localisé par contenu, global idx 80 / top 6) ; update CAS OK ; **CAS périmé → 409 `result.success:false` + `result.error`** (md5 intact) ; batch `update_blocks` (updates + expected_hash racine) ; move (top-level) ; delete (top-level) → **count 80==80 et md5 final `856c1c99…` identiques** |
| **1e Contact imbriqué** | ✅ 57 blocs md5 `106e1db0…` ; update feuille depth 2 (idx 2, parent 1) OK ; frère idx 1 intact ; **restauration révision REST → md5 exact `106e1db0475e74c6…`** |
| **⚠️ Sémantique déployée** | `create_block` renvoie `ref:null` (localiser par scan contenu) ; `update_block_content` = **index global flatten** ; `delete`/`move` = **index top-level uniquement** (« Bloc #80 introuvable ») ; erreurs = `result.success:false` + `result.error` (pas `result.data.success`) ; rate limit 10/60s/page |
| **Merge selfhare → main** | ✅ **cherry-pick ciblé** (la revue `git diff origin/main..origin/mcp-block-crud-2.7.0` contenait 3 fichiers hors périmètre — Navbar/PillarsSection/ghjk.py des commits anciens `603849c`/`dae06a8` — exclus volontairement, documenté) : `5631f50` → `2bb4167` (2 conflits admin-chat.js/houetor-selfhare.php résolus `--theirs` + **2 auto-mergés forcés `git restore --source=5631f50`** admin-chat.css/class-agent-chat.php car l'auto-merge gardait du contenu de main ; diff vs 5631f50 = vide) ; `ab18fcf` → `010093c` (zip) ; **push `d2aed40..010093c`** |
| **Vérifs post-merge** | ✅ 10/10 fichiers lab==commit (md5, dont admin-chat.css `03924456ee` et class-agent-chat.php `e2b741b891`) ; zip identique au commit (md5 `8e60fe23…`, 24 entrées=20 fichiers+4 dossiers, préfixe `houetor-selfhare/`, Version 1.0.3, 0 `eHlib`, refs `sh_blk_` présentes) ; **`git diff origin/main~2 origin/main --stat` = uniquement houetor-selfhare/ (10 fichiers) + zip, aucun secret** |
| **Vérif déploiement Vercel** | ✅ E2E complet rejoué APRÈS push : 1a→1e TOUS PASS (32 tools) → le merge n'a rien cassé (selfhare ne touche pas app/mcp/) ; confirmation visuelle dashboard = 👤 |

**Pour reprendre** : E2E déployé rejouable via `Temp/opencode/mcp-e2e-deployed.mjs` (1a→1e, propre, restaure About/Contact aux md5 canoniques) ; merge selfhare fait (main == `010093c`) ; reste sur ROADMAP : #6 dossier Fix Day 2.8.0, #10 artefacts, #11/#12/#14/#16/#17/#18/#19 (détails : ROADMAP_MARKET.md, items #2/#3/#4 marqués FAIT) ; ⚠️ tout commit/push sur `main` houetor reste interdit sauf mission explicite.

## SESSION 2026-08-06 — P1 paiement récurrent + Règle 24 zip vérifié + CRUD campagnes/cm_posts + Bug #12 clos (Exp 034, docs EXPERIMENTS_LOG)

| Sujet | État |
|---|---|
| **P1 paiement récurrent automatique** | ✅ code complet sur `section28/p1-paiement-recurrent` (commits `122a043` + `951ad4e`, 2e commit = CRUD campagnes/cm_posts) : migration `20260806_p1_billing_cycle.sql` (additive, `billing_cycle_status`/`next_billing_at`/compteurs sur orders + houetor_selfhare_licenses), `lib/payment/billing.ts` (Règle 32 maxIso, Règle 31 coupure, transitions 48h/7j), webhooks Stripe étendu (source `hare` → insertHareOrder idempotent) + FedaPay (branche renouvellement), cron `0 8 * * *`, page `/espace/facturation` + `POST /api/payment/renew`, coupure d'accès dispatch/agent/relay. **Preuves 24/24 PASS** (script bun), tsc 0, lint 0. ⚠️ **Migration NON appliquée à la DB** (pas d'accès secrets) ; pas de push (Règle 28) |
| **Règle 24 — zip selfhare en circulation** | ✅ **CONFORME** : 8/8 correctifs Exp 017 présents (preuve grep par correctif), `php -l` 0 erreur (WSL 8.5.4), md5 20/20 identique au lab (hors fins de ligne), 0 token. Pas de régénération nécessaire |
| **CRUD campagnes/cm_posts (MARKETING/CM)** | ✅ trou comblé : `app/mcp/crud-campagnes-cm.ts` (6 handlers, client injecté), routage dispatch, 6 outils tools.ts (create/update/delete_campagne + create/update/delete_cm_post ; profils MARKETING/ONG et CM/MARKETING/ONG). **Preuves 20/20 PASS**, P1 sans régression, tsc 0, lint 0 |
| **Bug #12 (cache admin-chat.js)** | ✅ **CLÔTURÉ — 1re preuve visuelle réelle de l'UI SelfHare** : cause = version d'enqueue statique (cache navigateur, vieux admin-chat.js) ; fix `filemtime()` déjà en place (zip + source) ; Preuve A HTTP : `admin-chat.js?ver=1785968077` servi sur Fix Day ; **Preuve B Playwright + Chrome 6/6** : chat Assistant chargé, sélecteur `#5 About`, agent réel → `page #5`/`index 4`/`page_id:"about"`, 0 `{{` dans le rendu, 0 exécution sans confirmation, 0 erreur JS/404. Scripts réutilisables : `Temp/opencode/bug12-prove-a-http.mjs`, `bug12-prove-b-visual.mjs` |

**Pour reprendre** : P1 → appliquer la migration sur la DB (décision + accès) + merge/déploiement branche `section28/p1-paiement-recurrent` ; Bug #12 suite optionnelle → test Révisions (bug #7) + `Outils → SelfHare Journal` sur Fix Day ; docs de cette session + mises à jour `HOUETOR-selfhare-consolide-juillet2026.md` (§7/§8/§9) + README.md + EXPERIMENTS_LOG Exp 034 → push `opencode-learning`. Fils ouverts inchangés : merge `mcp-block-crud-2.7.0` → main, Vercel E2E déployé, artefacts Fix Day #10, lint global (62), README marché, restauration « Insights & Resources » Blog #13.

## ÉTUDE COMPATIBILITÉ ELEMENTOR — Session 2026-08-06 (Exp 035 : verdict documenté, AUCUN code touché)

**Mission utilisateur** : étudier (sans toucher au code) si l'actuel peut CRUD des blocs créés avec Elementor — d'abord en consultant notre traitement des innerBlocks (patch 2.8.0) pour savoir s'il résout le problème. Références lues : aishan-shrestha/elementor-custom-widget, elementor/elementor-hello-world, developers.elementor.com/docs/widgets/ + first-addon/ + recherches en ligne.

| Élément | État |
|---|---|
| **Verdict** | ❌ **INCAPABLES aujourd'hui** : connect 2.8.0 + selfhare 1.0.3 + MCP (38 tools) travaillent à 100 % sur `post_content` (`parse_blocks`/`serialize_blocks`, CAS `md5($post->post_content)` — class-block-editor.php:19/250/354/381/415 ; selfhare class-agent-dispatch.php:462/771/880/925) ; `_elementor_data` : 0 occurrence dans les 2 plugins |
| **Pourquoi (modèle Elementor)** | Contenu = méta `_elementor_data` (arbre JSON `{id, elType: container/section/column/widget, widgetType, settings{}, elements[]}`), `post_content` vide/placeholder IGNORÉ au rendu ; écriture = `wp_slash(wp_json_encode())` obligatoire + validation + backup/rollback + **flush cache CSS** ; révisions WP ne protègent PAS `_elementor_data` ; 2 modes (containers flexbox défaut 3.x / sections-columns legacy) ; templates `elementor_library` (Pro) |
| **La mécanique innerBlocks résout-elle ?** | ❌ NON — 3 raisons : (1) blocage EN AMONT : `get_page_blocks` abandonne « contenu vide ou utilise un template » (class-block-editor.php:15-17) avant toute logique bloc ; (2) écriture incompatible : mutation `innerHTML` + `serialize_blocks` (L402-415) vs mutation `settings` + JSON ; (3) refs HWC absentes chez Elementor (mais `id` 8-car hex unique déjà présent = meilleur équivalent, sans injection) |
| **Ce qui SE transfère** | ✅ `flatten_blocks_recursive` (L43-74), `locate_block_deep` (L321-344, aucun index hardcodé), refus conteneur actionnable (L398-400), CAS — tous réutilisables sur l'arbre JSON (`elements` ≡ `innerBlocks`) |
| **Options (aucune décidée)** | A = lecture seule (détecter `_elementor_edit_mode=builder`, renvoyer l'arbre aplati dans get_page_blocks) ; B = module Elementor CRUD complet (routes `houetor/v1/elementor/*`, schéma widgets via `\Elementor\Plugin::$instance->widgets_manager->get_widget_types()->get_controls()`, flush CSS, backup) ; C = déclarer non supporté |
| **Preuves externes** | `msrbuilds/elementor-mcp` (GPL-3.0, ~360 ⭐, 97 tools, wrapper `documents->get()->save()`) ; `bvisible/elementor-mcp-api` (REST `/page/{id}/element` PATCH/duplicate/move + `/flush-css`) ; pattern `safe_elementor_save` (~15 000 saves sans corruption) |

**Pour reprendre** : étude close (EXPERIMENTS_LOG Exp 035), **aucun code touché**, décision utilisateur en attente (A/B/C). Fils ouverts inchangés : merge `mcp-block-crud-2.7.0` → main, P1 migration DB, artefacts Fix Day #10, lint global (62), README marché (#17/#18), restauration « Insights & Resources » Blog #13.

## SESSION 2026-08-07 (suite) — merge P1 → main, SEO robots.txt + sitemap.ts, audit LCP landing (Exp 038, docs EXPERIMENTS_LOG)

| Sujet | État |
|---|---|
| **Merge P1 → main** | ✅ décision explicite (Règle 28 levée) : revue 19 fichiers +1791/−7 sans fichier sensible, merge --no-ff ort → `73dd25b`, push `d2a587f..73dd25b` ; tsc 0 ; curl 307→200 (`www.houetor.com`) ; branche distante supprimée, locale supprimée ensuite (13/08) |
| **SEO robots.txt + sitemap.ts** | ✅ branche `section28/seo-robots-sitemap`, commit `0b37e98` (fusion validée : config IA conservée + 8 Disallow /espace /admin /mcp /api /selfhare/relay /obtenir /commander /bureau ; sitemap 5 URLs : + /selfhare/plans + /commencer, − /obtenir ×4 − /commander) ; tsc/eslint 0 ; curl dev : robots.txt + sitemap.xml conformes ; merge autorisé → `36f2108`, push `73dd25b..36f2108`, branche supprimée ; déploiement Vercel + logs = utilisateur |
| **Audit LCP landing** | ✅ écart non confirmé : visuels grille = images statiques WebP ×4 (16–37 KB, total ≈ 109 KB) via next/image lazy (PillarCard.tsx:49-57) ; LCP de `/` = h1 texte (HeroSection.tsx:48) ; aucun mockup/SVG généré en code ; aucun code touché ; vigilance : mockup en hero/priority = LCP mobile sensible (marché cible) |
| **Migration P1 en prod** | ✅ appliquée en prod Supabase `jseikgsdfjarozzshnxj` (10 colonnes confirmées sur orders + houetor_selfhare_licenses) — initialement listée « NON appliquée » (Exp 034), désormais FAIT |

**Pour reprendre** : main houetor = `36f2108` (SEO déployé via Vercel, logs côté utilisateur) ; branches locales section28 supprimées ; restent `agents/code-explanation-request` + `mcp-block-crud-2.7.0` (référence locale zip 1.0.3, distante partie — zip officiel = main `010093c`). Lab : Exp 038 commité. Fils ouverts inchangés : merge `mcp-block-crud-2.7.0` → main, artefacts Fix Day #10, lint global (62), README marché (#17/#18), restauration « Insights & Resources » Blog #13, décision Elementor A/B/C (Exp 035).
