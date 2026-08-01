# AGENTS.md — Point d'entrée mémoire (auto-chargé dans ce repo)

> Auto-chargé par opencode au démarrage. Complète `ONBOARDING.md` (kit complet) et `docs-learning/LEARNING_STATE.md` (état vivant).

## En bref

- **Projet** : repo `bolouvipf/connect` — distribution WordPress HOUETOR (zips `houetor-connect` + `houetor-selfhare`) + lab d'apprentissage.
- **Branche de travail** : `opencode-learning` (JAMAIS `main`).
- **Mémoire** : `ONBOARDING.md` (mode d'emploi agent), `docs-learning/` (6 fichiers : source de vérité, capacités, tests bruts, bugs, expériences, progression).
- **État actuel** : `houetor-connect` v2.5.0 (rétention audit + transform_block ; série V3 32/32, rétention 9/9, transform 21/21) ; `houetor-mcp/` v2.5.0 (29/29 unitaires, 33/33 intégration, 26/26 scénarios) ; zip 2.5.0 reconstruit (niveau unique corrigé) ; commits 2.5.0 `7ad5659`/`9b550ad`/`bd99f61`/`e8f9eef`/`744e268` + docs (Exp 012, série 004) — vérifier le push en début de session.
- **MISSION (en cours — Phases 0-3 terminées, Phase 4 préparée)** : Plugin + MCP agent — **le serveur MCP HOUETOR existe déjà** (`Pictures\Screenshots\houetor\app\mcp\` : JSON-RPC HTTP + SSE, 23 tools, auth X-HWT-Token ; il appelle le plugin `houetor/v1` mais PAS encore le CRUD bloc). **Objectif ultime : que toute action CRUD demandée par un utilisateur à l'IA s'exécute sans erreur** (relire avant écrire + CAS, `dry_run`, batch atomique, erreurs traduites, confirmation). Mission = construire `houetor-mcp/` (miroir testé des patterns app/mcp) + montées 2.4.0 (batch `update_blocks` + `dry_run`) et 2.5.0 (rétention audit + `transform_block`) puis portage des tools dans `app/mcp/`. **Phase 4 : portage prêt dans `houetor-mcp/portage-app-mcp/` (7 tools bloc, typecheck 0 erreur — tsc depuis Windows) — déploiement dans le repo prod en attente de validation utilisateur.** Détails : `docs-learning/LEARNING_STATE.md` (point de reprise 2026-08-01) et `EXPERIMENTS_LOG.md` Exp 012.
- **Fils ouverts** : prioriser les évolutions inspirées de block-mcp (Exp 008) ; audit `houetor-selfhare` en attente de validation utilisateur.

## Procédure

1. Commencer par lire `ONBOARDING.md` puis `docs-learning/LEARNING_STATE.md` et `EXPERIMENTS_LOG.md`.
2. `php -l` avant tout commit ; tests dans l'env isolé (WordPress localhost:8888 via WSL) avant toute affirmation.
3. Commits ciblés, `.env.learning` jamais commité, secrets jamais exposés.
4. Terminer chaque session par la mise à jour de `docs-learning/LEARNING_STATE.md` + push.
