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

## Exp 009 — Le serveur MCP HOUETOR existe déjà : `app/mcp/` (2026-07-31)
- **Objet** : lecture complète de `C:\Users\Kimsh\Pictures\Screenshots\houetor\app\mcp\` (4 fichiers) + `node_modules\next\dist\esm\server\mcp\` (indiqué par l'utilisateur).
- **Résultat — le MCP HOUETOR est custom, PAS le SDK MCP** :
  - `route.ts` : endpoint HTTP — POST = **JSON-RPC 2.0** (`{jsonrpc,method,params,id}`), GET = **SSE** listant les tools (`data: {profil, uuid, tools:[{name,description,params}]}`). Auth : header **`X-HWT-Token`** ; erreurs JSON-RPC (-32000 auth, -32601 method, -32600 request, -32603 internal).
  - `tools.ts` : **23 tools** déclarés avec `{name, description, profiles[], params:{type,required,description}}` — CRUD annonces/formations/produits, list_contenu, 5 tools WordPress (`get_wp_pages`, `inject_page`, `get_wp_menus`, `list_connected_sites`, `export_to_wordpress`), profil/stats/commandes/notifications.
  - `parser.ts` : token HWT = `HWT-{ONG|BOUTIQUE|COACH|CM|MARKETING}-{uuid}` ou uuid nu ; profil géré côté token.
  - `dispatch.ts` (966 lignes) : 21 méthodes — Supabase (annonces, formations, produits, commandes, users, connected_sites) + WordPress via **le plugin houetor/v1** avec `X-Houetor-Token` (token stocké dans la table `connected_sites`) : `/pages`, `/menus`, `/inject`, `/uninject`, `/media` (upload images puis inject). Render HTML annonce/formation/produit en HTML inline. Notifications via Resend.
- **GAP critique** : le MCP utilise les routes `/inject` `/uninject` `/pages` `/menus` `/media` **mais PAS le CRUD bloc v2.3.0** (`/page-blocks`, `/block-content`, `/blocks`), ni `expected_hash` (CAS), ni gestion du rate limit, ni audit. Les garde-fous qu'on a durcis au lab ne sont pas encore exploités par le MCP.
- **`node_modules/next/dist/esm/server/mcp/`** : c'est le **MCP intégré de Next.js 16.2.6** (dev tooling : get-routes, get-errors, get-logs, get-page-metadata, get-project-metadata, get-server-action-by-id — `McpServer` du SDK compilé `next/dist/compiled/@modelcontextprotocol/sdk`). PAS le MCP HOUETOR. Référence de patterns officiels uniquement ; non activé dans `next.config.ts`.
- **Ce qu'on copie pour le lab** : la structure `route/tools/parser/dispatch` + la logique des appels WP (`fetch {url}/wp-json/houetor/v1/*` + `X-Houetor-Token`) comme base de `houetor-mcp/` — pour que les nouveaux tools testés au lab soient **portables tel quel** dans `app/mcp/` en production (même protocole JSON-RPC HTTP + SSE).

## Exp 010 — `houetor-mcp/` : miroir lab du MCP HOUETOR construit et testé (2026-07-31)
- **Objet** : Phase 1 de la mission � construire dans le repo `connect` un serveur MCP qui reproduit le protocole de `app/mcp/` (JSON-RPC 2.0 POST + SSE GET, auth `X-HWT-Token`, erreurs -32000/-32601/-32600/-32603) et expose le CRUD bloc v2.3.0 du plugin.
- **Livr�** : `houetor-mcp/` � z�ro d�pendance runtime (fetch natif Node 22) ; `src/` = `parser.ts` (copie fid�le prod), `tools.ts` (10 tools : 5 WP + get_page_blocks/create_block/update_block_content/delete_block/uninject_page), `client.ts` (X-Houetor-Token), `dispatch.ts`, `route-handler.ts` (handleRequest testable, portage facile vers NextRequest), `server.ts` (fabrique HTTP), `index.ts` (CLI, env WORDPRESS_URL/HOUETOR_TOKEN/PORT, d�faut 8890). Erreurs plugin traduites (`error-translator.ts`) : 409 CAS ? � relisez la page pour un expected_hash frais �, 429 ? � attendez ~60 s ou batch 2.4.0 �, 404 ancre/bloc ? relecture, 401 ? v�rifier token. JSON-RPC -32002 avec data.status/code.
- **Tests** : **18/18 unitaires** (Vitest, fetch mock� : auth, SSE filtrage profil, 409/429 traduits, 400 params) ; **16/16 int�gration** vs le WP lab r�el via WSL (pages ? page-blocks ? create avec ref ? update CAS OK ? update CAS KO 409 ? delete ? �tat restaur� ? 404 ? 401 ? SSE).
- **D�couvertes env** : (1) permaliens � plain � ? `/wp-json/` servait du HTML (301 canonical + page HTML) : corrig� `permalink_structure=/%postname%/` + rewrite flush � REST HTTP indispensable pour tout client externe ; (2) `/page-blocks` prend `page_id` en **query param** (pas de segment d'URL) ; (3) ref = `{module}-{hash}` (ex `test-7de7e65cf7d4`), module obligatoire pour avoir une ref ; (4) `/pages` renvoie `id` num�rique.
- **Prochaine �tape (Phase 2)** : endpoint plugin `POST /blocks/batch-update` (N updates = 1 r�vision, all-or-nothing, 1 �criture rate limit) + `dry_run`, puis tool MCP `update_blocks` correspondant.

## Exp 011 — Phase 2 mission : plugin+MCP 2.4.0 (batch update_blocks atomique + dry_run) (2026-08-01)
