# AGENTS.md — Point d'entrée mémoire (auto-chargé dans ce repo)

> Auto-chargé par opencode au démarrage. Complète `ONBOARDING.md` (kit complet) et `docs-learning/LEARNING_STATE.md` (état vivant).

## En bref

- **Projet** : repo `bolouvipf/connect` — distribution WordPress HOUETOR (zips `houetor-connect` + `houetor-selfhare`) + lab d'apprentissage.
- **Branche de travail** : `opencode-learning` (JAMAIS `main`).
- **Mémoire** : `ONBOARDING.md` (mode d'emploi agent), `docs-learning/` (6 fichiers : source de vérité, capacités, tests bruts, bugs, expériences, progression).
- **État actuel** : `houetor-connect` v2.3.0 livré et testé (14/14) ; bugs #1-#4 corrigés ; zip 2.3.0 reconstruit ; dernier commit `4040700`.
- **MISSION validée (à lancer)** : Plugin + MCP agent — **le serveur MCP HOUETOR existe déjà** (`Pictures\Screenshots\houetor\app\mcp\` : JSON-RPC HTTP + SSE, 23 tools, auth X-HWT-Token ; il appelle le plugin `houetor/v1` mais PAS encore le CRUD bloc v2.3.0). Mission = construire `houetor-mcp/` (miroir testé des patterns app/mcp) + montée 2.4.0 (batch `update_blocks` + `dry_run`) puis portage des tools dans `app/mcp/`. Détails : `docs-learning/LEARNING_STATE.md` (section MISSION, révisée Exp 009) et `EXPERIMENTS_LOG.md` Exp 009.
- **Fils ouverts** : prioriser les évolutions inspirées de block-mcp (Exp 008) ; audit `houetor-selfhare` en attente de validation utilisateur.

## Procédure

1. Commencer par lire `ONBOARDING.md` puis `docs-learning/LEARNING_STATE.md` et `EXPERIMENTS_LOG.md`.
2. `php -l` avant tout commit ; tests dans l'env isolé (WordPress localhost:8888 via WSL) avant toute affirmation.
3. Commits ciblés, `.env.learning` jamais commité, secrets jamais exposés.
4. Terminer chaque session par la mise à jour de `docs-learning/LEARNING_STATE.md` + push.
