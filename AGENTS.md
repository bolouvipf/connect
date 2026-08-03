# AGENTS.md — Point d'entrée mémoire (auto-chargé dans ce repo)

> Auto-chargé par opencode au démarrage. Complète `ONBOARDING.md` (kit complet) et `docs-learning/LEARNING_STATE.md` (état vivant).

## En bref

- **Projet** : repo `bolouvipf/connect` — distribution WordPress HOUETOR (zips `houetor-connect` + `houetor-selfhare`) + lab d'apprentissage.
- **Branche de travail** : `opencode-learning` (JAMAIS `main`).
- **Mémoire** : `ONBOARDING.md` (mode d'emploi agent), `docs-learning/` (6 fichiers : source de vérité, capacités, tests bruts, bugs, expériences, progression).
- **État actuel** : `houetor-connect` v2.7.0 (ops structurelles move/duplicate/wrap/unwrap ; structural 42/42, V3 32/32, rétention 9/9, transform 21/21, tier policy 11/11) ; `houetor-mcp/` v2.7.0 (42/42 unitaires, 52/52 intégration, 41/41 scénarios) ; zip 2.7.0 reconstruit (niveau unique) ; stable tag readme.txt 2.7.0 ; `houetor-selfhare` **1.0.2** (Exp 017) ; **branche `opencode-learning` = `4066a42` (Exp 017 poussé, git propre)**.
- **MISSION EXÉCUTÉE (Exp 015, 2026-08-02 soir)** : Plugin + MCP agent — **le serveur MCP HOUETOR existe** (`Pictures\Screenshots\houetor\app\mcp\` : JSON-RPC HTTP + SSE, auth X-HWT-Token). **Objectif ultime : que toute action CRUD demandée par un utilisateur à l'IA s'exécute sans erreur** (relire avant écrire + CAS, `dry_run`, batch atomique, erreurs traduites, confirmation). **Clôture + portage PROD FAITS** : merge `opencode-learning` → `main` (connect, poussé) ; zip 2.7.0 → `houetor/outputs/` ; **portage déployé sur branche `mcp-block-crud-2.7.0` du repo `houetor` (commit `fc91bd5` : tools.ts +155, dispatch.ts +435/-41, error-translator.ts nouveau ; route.ts/parser.ts intacts ; tsc 0 erreur ; lint app/mcp 0 erreur) — PAS de merge dans main houetor** ; E2E vert sur site TasteWP neuf « Fix Day » (plugin 2.7.0 actif, 6 scénarios PASS). Détails : `docs-learning/LEARNING_STATE.md` (section CLÔTURE) et `EXPERIMENTS_LOG.md` Exp 015.
- **VALIDATION MCP PROD (Exp 018, 2026-08-03)** : Fix Day **connecté au dashboard HOUETOR** (token profil ONG, Supabase connected_sites) + **starter site uploadé** (contenu réel) → **portage MCP testé en conditions réelles 9/9 PASS** (GET SSE 32 tools, list_connected_sites, cycle CRUD 2.7.0 complet sur page About : dry_run sans effet, create, CAS OK, **409 périmé refusé + contenu intact**, batch update_blocks, move→start, delete → **page restaurée à l'identique**). Blocage Supabase résolu : `.env.local` du repo houetor ré-écrit (URL retrouvée + clés fournies par l'utilisateur ; pull Vercel CLI impossible — compte sans droit de décryptage). Serveur Next local : `next build` + `next start -p 3010`. Détails : Exp 018.
- **Fils ouverts (actions utilisateur)** : ops structurelles en réel à poursuivre (transform/wrap/duplicate/unwrap, page Accueil id 2 — script `mcp-e2e-prod.mjs` prêt dans Temp/opencode) ; merge/rollout `mcp-block-crud-2.7.0` ; lint global houetor (62 erreurs pré-existantes) ; évolutions roadmap restantes (compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit) ; 3 `probe-*.mjs` lab untracked (écartés). Secrets en stock (jamais commités) : `.env.learning` (lab) + `.env.local` (houetor).

## Procédure

1. Commencer par lire `ONBOARDING.md` puis `docs-learning/LEARNING_STATE.md` et `EXPERIMENTS_LOG.md`.
2. `php -l` avant tout commit ; tests dans l'env isolé (WordPress localhost:8888 via WSL) avant toute affirmation.
3. Commits ciblés, `.env.learning` jamais commité, secrets jamais exposés.
4. Terminer chaque session par la mise à jour de `docs-learning/LEARNING_STATE.md` + push.
