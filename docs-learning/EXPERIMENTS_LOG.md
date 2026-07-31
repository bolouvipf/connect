# Journal des expériences

Format : objectif / commandes exécutées / résultat brut.

## Exp 001 — Clone + état du repo (2026-07-31)
- **Commandes** : `git clone https://github.com/bolouvipf/connect.git`, `git log --oneline -20`, `git branch -a`, `git remote -v`.
- **Résultat** : 3 commits (HEAD ca1734e « feat(connect): add block-aware CRUD endpoints »), une seule branche `main`, remote origin OK. Repo = distribution (README.md + 2 zips + 1 doc consolidée).

## Exp 002 — Extraction du zip et état du code (2026-07-31)
- **Commande** : `Expand-Archive houetor-connect.zip → connect\houetor-connect\`.
- **Résultat** : 8 fichiers dans `includes/` dont `class-block-editor.php` (296 lignes) et `class-rest-api.php` (367 lignes). CRUD bloc DÉJÀ présent (index-based) : `/page-blocks`, `/block-content` (PATCH), `/blocks` (POST/DELETE). SANS ref HWC, CAS, rate limit, audit log.

## Exp 003 — Réseau WSL (2026-07-31)
- **Problème** : aucun TCP sortant WSL (HTTP 000), DNS OK, MTU 1500, pare-feu Hyper-V outbound=Allow.
- **Fix** : `.wslconfig` → `networkingMode=mirrored` + `wsl --shutdown` → `HTTPS_archive:200` / `HTTPS_gh:200`.
- **Note** : le mode miroir ne s'est finalement PAS appliqué (IP NAT 192.168.228.88 persistante, inbound Windows→WSL bloqué par le pare-feu Hyper-V, pas admin). Tests REST effectués en interne via `wp eval-file` (fonctionnel, preuve équivalente).

## Exp 004 — Installation env de test + tests REST (2026-07-31)
- **Commandes** : wp core download/install (localhost:8888), plugin activé, `wp eval-file rest-test.php`.
- **Résultat** : 18 tests exécutés. Auth OK (403 token manquant/invalide), lecture/parsing OK, create positionnel OK, PATCH OK, révisions OK. BUG CONFIRMÉ : `inject replace` vide la page (bug #2). Page restaurée via révision #5.

## Exp 005 — php -l (2026-07-31)
- **Commande** : script `php-lint.sh` sur `houetor-connect/`.
- **Résultat** : 0 erreur de syntaxe sur les 14 fichiers .php.

## Exp 006 — Version incohérente (2026-07-31)
- **Commande** : `wp plugin list` + lecture header.
- **Résultat** : affiché `2.1.0`, constante `2.2.0`, stable tag `2.1.0` → bug #1.
