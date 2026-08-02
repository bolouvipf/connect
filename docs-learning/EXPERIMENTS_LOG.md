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

## Exp 012 — Évolutions roadmap 1+5 : rétention audit + auto-transform, plugin+MCP 2.5.0 (2026-08-01)

**Contexte** : utilisateur choisit « rester dans le lab » (pas de déploiement prod) puis valide le mix des options 1 (audit retention) et 5 (auto-transforms), à risque minimisé, en 4 étapes A→D (A rétention → B endpoint transform → C MCP miroir → D lockstep 2.5.0). Cloisonnement strict : un NOUVEL endpoint `/blocks/transform` uniquement, aucun endpoint existant modifié.

**Ce qui a été fait** :
- **Étape A — Rétention** : `HWC_REST_API::audit_cleanup()` — option `hwc_audit_retention_days` (défaut 90, filtrable `hwc_audit_retention_days`), purge par chunks `DELETE … LIMIT 500` (max 200 itérations) ; CRON quotidien `hwc_audit_cleanup` posé dans `hwc_activate()` (idempotent), nettoyé dans `hwc_deactivate()`. Test `rest-test-retention.php` : **9/9 PASS** (défaut 90 / filtre 30 / désactivé 0).
- **Étape B — Transform** : refactor `class-block-editor.php` — const `ALLOWED_BLOCKS` (liste existante) + `TEXT_BLOCKS` (paragraph, heading, quote, list, code, preformatted, pullquote) ; helpers privés `build_block()` (heading garde `level` si source heading, sinon 2) et `wrap_ref()` (enrobage marqueur HWC) partagés avec `create_block` (bug corrigé : `wrap_ref` retournait `null` au lieu du bloc modifié) ; `transform_block()` — localisation par ref/index, **refus des blocs imbriqués** (`innerBlocks`), refus source/cible hors whitelist, ref HWC **conservée**, dry_run sans écriture/révision/audit, `wp_save_post_revision()` avant écriture, CAS 409. Route `POST /houetor/v1/blocks/transform` + handler (400 params, 429 sauf dry_run, 404 « introuvable », audit `transform_block`). Test `rest-test-transform.php` : **21/21 PASS** — dont T10 refus du bloc quote natif imbriqué, T9 refus cible media.
- **Étape C — MCP miroir** : tool `transform_block` (client.ts + tools.ts + dispatch.ts : ref OU block_index requis, dry_run bool) ; 5 tests unitaires ajoutés (URL/body POST, 400 ref/index manquant, 400 target manquant, 409 traduit, SSE) → **29/29** ; intégration **33/33** (transform paragraph→heading→paragraph avec CAS chaîné, 409 CAS périmé en dry_run, 400 cible media en dry_run) ; scénario **S7** « transforme ce bloc en titre » → **26/26**.
- **Étape D — Lockstep 2.5.0** : versions (header ligne 7 + `HWC_VERSION` + package.json MCP) ; **zip reconstruit** `houetor-connect.zip` via `git archive` SANS prefix (correction d'une double imbrication `houetor-connect/houetor-connect/` dans le zip 2.4.0) ; portage `portage-app-mcp/src/` enrichi (`transform_block` : tools.ts + dispatch.ts + ALLOWED_METHODS) — **typecheck 0 erreur vs types prod** (tsc lancé depuis Windows : la baseUrl `C:/…` du tsconfig ne se résout pas sous WSL) ; README miroir + portage à jour.

**Découvertes** :
- Le rate limit compte TOUTES les tentatives (400/409 inclus) → en test : `delete_transient('hwc_ratelimit_2')` entre les batteries ; budget intégration = 10 écritures exactement.
- Le refus CAS précède le dry_run dans le plugin → un `dry_run` avec mauvais hash renvoie quand même 409 (utile : teste la traduction 409 sans consommer de budget).
- `git archive --prefix=houetor-connect/` sur le dossier repo `houetor-connect/` double l'imbrication — le zip 2.4.0 committé était donc mal structuré ; corrigé en archive sans prefix.
- Windows→WSL : les junctions/reparse points ne se résolvent pas pour `tsc` sous WSL (baseUrl Windows) — lancer le typecheck du portage depuis Windows.

**Scores finaux** : plugin — V3 32/32, rétention 9/9, transform 21/21 ; miroir — unitaires 29/29, intégration 33/33, scénarios 26/26 (suite `mirror-suite.sh` entièrement verte).

## Exp 013 — Évolution roadmap : tier policy (refus blocs legacy + suggestion), plugin+MCP 2.6.0 (2026-08-01)

**Contexte** : utilisateur choisit « Évolutions roadmap block-mcp » puis l'option « Tier policy (refus blocs legacy) » — montée 2.6.0 à risque minimisé, calquée sur l'Exp 012 (étapes A→D, cloisonnement strict). Idée source (Exp 008) : « blocs legacy rejetés à l'insertion + map de remplacement suggérée » — l'erreur devient actionnable : l'agent recrée le bloc avec la suggestion au lieu de bloquer sur un refus muet.

**Ce qui a été fait** :
- **Étape A — Plugin** : `HWC_Block_Editor::LEGACY_BLOCKS` (21 entrées : blocs obsolètes/renommés/retirés → suggestion dans ALLOWED_BLOCKS, ex `core/cover-image`→`core/cover`, `core/verse`→`core/preformatted`, `core/html`→`core/paragraph`, `core/social-links`→`core/buttons`) + `legacy_suggestion()` (filtre `hwc_legacy_blocks` personnalisable) ; `create_block` refusé legacy → `['error' => 'legacy', 'suggested_block' => …]` ; handler REST `create_block` → **WP_Error `block_legacy` (400) avec data `block_name` + `suggested_block`**. Aucun endpoint existant modifié (refus générique `create_failed` conservé pour les blocs hors map).
- **Étape B — Tests** : `rest-test-tierpolicy.php` **11/11 PASS** (T1 verse→preformatted, T2 cover-image→cover, T3 inconnu→create_failed générique, T4 dry_run legacy→400 sans écriture, T5 filtre custom + retrait, T6 ALLOWED→201, T7 aucun audit sur échecs, T8 cleanup) ; **régression V3 32/32 PASS**.
- **Étape C — Miroir MCP** : `error-translator.ts` cas `400 block_legacy` → message « Recréez le bloc avec "X" à la place… » ; `WordPressClientError` propage désormais `data` REST et `route-handler.ts` les reflète dans `error.data.data` (utile au portage prod) ; test unitaire + test intégration (create legacy **en dry_run** → 400 traduit, budget rate limit intact : le refus tier policy précède le dry_run mais le rate limit n'est pas consommé en dry_run) + scénario **S8** (« Ajoute un bloc poème » → refus traduit → l'agent applique la suggestion → succès en dry_run).
- **Étape D — Lockstep 2.6.0** : header plugin + `HWC_VERSION` + **stable tag readme.txt 2.4.0 → 2.6.0 (dérive corrigée de la montée 2.5.0)** + package.json MCP ; portage `portage-app-mcp/src/error-translator.ts` enrichi (typecheck **0 erreur** via tsc Windows) ; zip reconstruit (git archive sans prefix) ; docs (Exp 013, série 005, LEARNING_STATE, ONBOARDING, AGENTS, README MCP).

**Scores finaux** : plugin — tier policy 11/11, V3 32/32 ; miroir — unitaires **30/30**, intégration **35/35**, scénarios **29/29** (S8 inclus) ; portage — typecheck 0 erreur.

**Découvertes** :
- En dry_run, le refus tier policy (400) est renvoyé SANS consommer le budget rate limit (le `check_rate_limit` est sauté en dry_run ; le refus whitelist précède l'écriture) — même patron que la découverte Exp 011 (refus CAS avant dry_run).
- Le stable tag readme.txt était resté en 2.4.0 lors de la montée 2.5.0 (dérive silencieuse du bug #1) — corrigé en 2.6.0.
- `sed` multi-guillemets sous PowerShell→WSL échoue : passer par l'édition de fichier directe.

## Exp 014 — Évolution roadmap : ops structurelles move/duplicate/wrap/unwrap, plugin+MCP 2.7.0 (2026-08-02)

**Contexte** : reprise de session — un chantier « ops structurelles » (la roadmap restante issue de block-mcp, Exp 008) était commencé la veille (19:46→20:16) mais **non commité et non documenté**. Découverte à l'ouverture : `git status` montrait ~1100 lignes d'ajouts (plugin + MCP + portage) sans aucun commit. Session = finaliser et livrer la montée 2.7.0 à risque minimisé (calquée sur Exp 012/013, cloisonnement strict : 4 NOUVEAUX endpoints, aucun endpoint existant modifié).

**Ce qui a été fait** :
- **Reprise du chantier** : vérification de l'existant (plugin synchro env test, php -l 0 erreur, serveur :8888 actif via service systemd) ; batteries de preuve lancées : **structural 42/42 PASS** (rest-test-structural.php, T1-T16) ; **régression V3 32/32** ; **unitaires 42/42, intégration 52/52, scénarios 41/41** (mirror-suite, avec S9-S12 nouveaux).
- **Diagnostic latence** : le WP lab répondait en ~10-18 s par requête HTTP → les batteries mirror-suite dépassaient le timeout de la tool. Cause : `opcache.enable_cli=Off` + disque DrvFS `/mnt/c` — pas un bug du plugin. Contournement : exécution des batteries par étapes avec timeouts larges (900 s).
- **Portage complété** : `portage-app-mcp/src/tools.ts` +4 tools (move_block, duplicate_block, wrap_block, unwrap_block, avec `site_id` comme les tools prod) ; `dispatch.ts` +4 cases + 4 fonctions `moveBlock/duplicateBlock/wrapBlock/unwrapBlock` (validations ref|block_index, ancre requise pour before/after, endpoint `/blocks/move|duplicate|wrap|unwrap`) + `ALLOWED_METHODS` ; `error-translator.ts` +2 cas (`wrap_failed` plage invalide → conseil index croissants, `unwrap_failed` non-groupe → conseil core/group) — **typecheck 0 erreur** (tsc Windows).
- **Lockstep 2.7.0** : header plugin + `HWC_VERSION` + stable tag readme.txt + package.json MCP ; changelog readme.txt complété (sections 2.5.0/2.6.0 manquantes + 2.7.0) ; zip reconstruit ; docs (Exp 014, série 006, PLUGIN_CAPABILITIES 2.7.0, LEARNING_STATE, ONBOARDING, AGENTS, README MCP, README portage).
- **Correction incident portage** : le `git status` montrait un diff EOL massif (2400 lignes) sur `portage-app-mcp/` — en réalité 100% CRLF/LF (0 changement réel avec `--ignore-space-at-eol`) : `git checkout --` pour repartir de l'arbre HEAD propre avant portage.

**Scores finaux** : plugin — structural **42/42**, V3 **32/32** ; miroir — unitaires **42/42**, intégration **52/52**, scénarios **41/41** (S9-S12) ; portage — typecheck **0 erreur**.

**Découvertes** :
- Chaque op structurelle = exactement 1 écriture rate limit (T15 : duplicate → compteur 1, move réel → 2, dry_run → inchangé).
- Move no-op (ancre == source) : 200 + « déjà en place », **aucune révision, aucun audit** (T4) — les écritures sans effet ne polluent pas l'historique.
- Wrap régénère les refs en profondeur pour duplicate (réf. uniques garanties, T8) ; wrap préserve la ref du bloc dans le sous-arbre du groupe (T10, extract_hwc_ref).
- Le mirror-suite.sh a été enrichi : restauration des pages de référence (`restore-lab-pages.php`) avant CHAQUE batterie (la page 3 est désormais utilisée par les tests structurels — budget rate limit indépendant).
