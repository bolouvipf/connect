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

## Exp 007 — Série de tests v2.3.0 (2026-07-31)
- **Commande** : `wp eval-file rest-test-v2.php` (14 tests, V2-1 → V2-14).
- **Résultat** : **14/14 PASS**. Ref HWC auto-générée + ciblage par ref (update/delete, marqueurs préservés) ; `position: before/after` + `anchor_ref` ordre vérifié ; `anchor_not_found` → 404 explicite ; CAS négatif → 409 `error_conflict` (aucun écrasement) ; inject CAS → 409 ; rate limit : 10 écritures OK puis 429 ; audit log rempli (before/after md5) ; 10 révisions.
- **Preuve** : résultats bruts consignés dans `TOOLS_DISCOVERED.md` (série 002).

## Exp 008 — Analyse de block-mcp (GravityKit) — extraction de bonnes pratiques (2026-07-31)
- **Objet** : `C:\Users\Kimsh\Downloads\block-mcp` — MCP WordPress (serveur TS `@gravitykit/block-mcp` + plugin WP `gk-block-mcp`, v2.1.0, 326 tests PHP + 249 TS).
- **Approche retenue** : ne rien copier, ne pas le brancher — **tirer le meilleur** et faire évoluer `houetor-connect` dans la même direction.
- **Ce qu'il fait mieux / ce qu'on peut en tirer** :

| Idée (block-mcp) | Commentaire / équivalent houetor-connect |
|---|---|
| Refs stables stockées dans `attrs.metadata.gk_ref` (persistées en DB direct, sans révision) | Nous : marqueurs `<!-- HWC {module}-{ref} -->` en commentaires HTML. Équivalent fonctionnel ; les leurs survivent au parsing agent sans HTML comments. À comparer |
| Batch atomique `update_blocks` (N updates en UNE révision, all-or-nothing, max 50, compte 1 écriture rate limit) | **Manque chez nous** → évolution candidate (notre rate limit est par appel) |
| Opérations structurelles par chemin (`edit_block_tree` : move, duplicate, wrap-in-group, unwrap-group, insert-child, replace-block…) | **Manque chez nous** → évolution candidate |
| Compte agent dédié à moindre privilège (rôle custom, Application Passwords, pas de `delete_*`, pas de `unfiltered_html`, sign-in interactif bloqué) | Nous : token statique `hwc_token` (32 chars) avec `check_token()`. Le modèle à mot de passe d'application WP est plus auditable → piste forte pour le futur |
| Connect/onboarding one-click (browser-Approve handshake, `.mcpb`, code single-use chiffré AES-256-GCM) | À transposer dans le modèle HOUETOR si compte agent retenu |
| ETag/If-Match (412 `stale_revision`) | Nous avons l'équivalent en mieux : CAS `expected_hash` md5 (409 `error_conflict`) |
| Rate limit par post (10 écritures/min + 2 rewrites/min séparés) | Nous : 10/60s par page global. Idée : ajouter un budget séparé pour les réécritures complètes |
| Tier policy (blocs legacy rejetés à l'insertion + map de remplacement suggérée) | **Manque chez nous** → évolution candidate (liste blanche 20 types existe, pas de tiers) |
| `dry_run` (valider une mutation sans écrire) | **Manque chez nous** → évolution candidate |
| Détection blocs dual-storage (attrs + innerHTML) + refus si champs manquants | Partiellement chez nous (refus innerBlocks imbriqués) |
| Auto-transforms (level d'un heading → tag h2/h3 resynchronisé) | **Manque chez nous** → évolution candidate |
| Erreurs REST `{code, message, data:{status}}` avec « how to recover » | Nous avons le format WP_Error standard ; on peut enrichir les messages de récupération |
| PHPUnit sur stub WP (SQLite drop-in, pas d'instance complète) + phpcs/PHPStan à chaque commit | Nous : tests manuels `wp eval-file`. Évolution : suite PHPUnit automatisée |
| Version lockstep (une version = plugin + serveur + zip + tag) | Nous venons de corriger bug #1 sur ce principe |
| `AGENTS.md`/`CLAUDE.md` documentés pour les agents | C'est ce que fait `ONBOARDING.md` |
| Kills-witch, SSRF guard sur URL sideload, caps précis | Idées pour le durcissement futur |

- **Décision** : ne PAS intégrer block-mcp au lab pour l'instant (utilisateur : « tirer le meilleur de lui »). Les évolutions candidates ci-dessus seront priorisées avec l'utilisateur en séance (roadmap §9 d'ONBOARDING.md).
