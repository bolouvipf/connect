# AGENTS.md — Point d'entrée mémoire (auto-chargé dans ce repo)

> Auto-chargé par opencode au démarrage. Complète `ONBOARDING.md` (kit complet) et `docs-learning/LEARNING_STATE.md` (état vivant).

## En bref

- **Projet** : repo `bolouvipf/connect` — distribution WordPress HOUETOR (zips `houetor-connect` + `houetor-selfhare`) + lab d'apprentissage.
- **Branche de travail** : `opencode-learning` (JAMAIS `main`).
- **Mémoire** : `ONBOARDING.md` (mode d'emploi agent), `docs-learning/` (6 fichiers : source de vérité, capacités, tests bruts, bugs, expériences, progression).
- **État actuel** : `houetor-connect` v2.7.0 (ops structurelles move/duplicate/wrap/unwrap ; structural 42/42, V3 32/32, rétention 9/9, transform 21/21, tier policy 11/11) ; `houetor-mcp/` v2.7.0 (42/42 unitaires, 52/52 intégration, 41/41 scénarios) ; zip 2.7.0 reconstruit (niveau unique) ; stable tag readme.txt 2.7.0 ; commits 2.7.0 + docs (Exp 014, série 006) — vérifier le push en début de session.
- **MISSION (en cours — Phases 0-3 terminées, Phase 4 préparée)** : Plugin + MCP agent — **le serveur MCP HOUETOR existe déjà** (`Pictures\Screenshots\houetor\app\mcp\` : JSON-RPC HTTP + SSE, 23 tools, auth X-HWT-Token ; il appelle le plugin `houetor/v1` mais PAS encore le CRUD bloc). **Objectif ultime : que toute action CRUD demandée par un utilisateur à l'IA s'exécute sans erreur** (relire avant écrire + CAS, `dry_run`, batch atomique, erreurs traduites, confirmation). Mission = construire `houetor-mcp/` (miroir testé des patterns app/mcp) + montées 2.4.0 (batch `update_blocks` + `dry_run`), 2.5.0 (rétention audit + `transform_block`), 2.6.0 (tier policy) et 2.7.0 (ops structurelles) puis portage des tools dans `app/mcp/`. **Phase 4 : portage prêt dans `houetor-mcp/portage-app-mcp/` (11 tools bloc, typecheck 0 erreur — tsc depuis Windows) — déploiement dans le repo prod en attente de validation utilisateur (prérequis plugin clients ≥ 2.7.0).** Détails : `docs-learning/LEARNING_STATE.md` (point de reprise 2026-08-02) et `EXPERIMENTS_LOG.md` Exp 014.
- **Fils ouverts** : évolutions block-mcp restantes (Exp 008 : compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit) ; audit `houetor-selfhare` en attente de validation utilisateur ; déploiement Phase 4 en attente.

## Procédure

1. Commencer par lire `ONBOARDING.md` puis `docs-learning/LEARNING_STATE.md` et `EXPERIMENTS_LOG.md`.
2. `php -l` avant tout commit ; tests dans l'env isolé (WordPress localhost:8888 via WSL) avant toute affirmation.
3. Commits ciblés, `.env.learning` jamais commité, secrets jamais exposés.
4. Terminer chaque session par la mise à jour de `docs-learning/LEARNING_STATE.md` + push.
