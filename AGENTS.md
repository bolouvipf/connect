# AGENTS.md — Point d'entrée mémoire (auto-chargé dans ce repo)

> Auto-chargé par opencode au démarrage. Complète `ONBOARDING.md` (kit complet) et `docs-learning/LEARNING_STATE.md` (état vivant).

## En bref

- **Projet** : repo `bolouvipf/connect` — distribution WordPress HOUETOR (zips `houetor-connect` + `houetor-selfhare`) + lab d'apprentissage.
- **Branche de travail** : `opencode-learning` (JAMAIS `main`).
- **Mémoire** : `ONBOARDING.md` (mode d'emploi agent), `docs-learning/` (6 fichiers : source de vérité, capacités, tests bruts, bugs, expériences, progression).
- **État actuel** : `houetor-connect` v2.4.0 livré et testé (série V3 32/32 + régression V2 14/14) ; `houetor-mcp/` v2.4.0 (24/24 unitaires, 28/28 intégration) ; zip 2.4.0 reconstruit ; derniers commits `599f388` (plugin), `a76318a` (zip), `3663900` (MCP), `5d25359` (docs).
- **MISSION (en cours — Phases 0-3 terminées, Phase 4 préparée)** : Plugin + MCP agent — **le serveur MCP HOUETOR existe déjà** (`Pictures\Screenshots\houetor\app\mcp\` : JSON-RPC HTTP + SSE, 23 tools, auth X-HWT-Token ; il appelle le plugin `houetor/v1` mais PAS encore le CRUD bloc). **Objectif ultime : que toute action CRUD demandée par un utilisateur à l'IA s'exécute sans erreur** (relire avant écrire + CAS, `dry_run`, batch atomique, erreurs traduites, confirmation). Mission = construire `houetor-mcp/` (miroir testé des patterns app/mcp) + montée 2.4.0 (batch `update_blocks` + `dry_run`) puis portage des tools dans `app/mcp/`. **Phase 4 : portage prêt dans `houetor-mcp/portage-app-mcp/` (typecheck 0 erreur) — déploiement dans le repo prod en attente de validation utilisateur.** Détails : `docs-learning/LEARNING_STATE.md` (point de reprise 2026-08-01) et `EXPERIMENTS_LOG.md` Exp 011.
- **Fils ouverts** : prioriser les évolutions inspirées de block-mcp (Exp 008) ; audit `houetor-selfhare` en attente de validation utilisateur.

## Procédure

1. Commencer par lire `ONBOARDING.md` puis `docs-learning/LEARNING_STATE.md` et `EXPERIMENTS_LOG.md`.
2. `php -l` avant tout commit ; tests dans l'env isolé (WordPress localhost:8888 via WSL) avant toute affirmation.
3. Commits ciblés, `.env.learning` jamais commité, secrets jamais exposés.
4. Terminer chaque session par la mise à jour de `docs-learning/LEARNING_STATE.md` + push.
