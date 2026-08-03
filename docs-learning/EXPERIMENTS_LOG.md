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

## Exp 015 � Mission cl�ture lab + portage production (plugin 2.7.0 + MCP) (2026-08-02 soir)

**Contexte** : mission utilisateur structur�e en 9 �tapes � cl�turer le lab connect (merge opencode-learning ? main), porter le portage MCP vers le d�p�t prod houetor sur une branche d�di�e (jamais main), v�rifier nativement, tester sur un site WordPress neuf (TasteWP � Fix Day �), rapport final sans proposition de rollout.

**Ce qui a �t� fait** (preuves dans LEARNING_STATE � CL�TURE �) :
- Merge FF opencode-learning ? main (connect), push ; versions 2.7.0 reconfirm�es post-merge.
- Zip 2.7.0 copi� dans houetor/outputs/ (sha256 avant=apr�s AA7E89A8�, 42 542 octets).
- Branche mcp-block-crud-2.7.0 cr��e depuis main (houetor) ; portage 	ools.ts (+155), dispatch.ts (+435/-41), error-translator.ts (nouveau) ; route.ts/parser.ts v�rifi�s absents du diff.
- V�rification native : .next corrompu (artefact build gitignor�) supprim� ; **tsc 0 erreur** ; **lint app/mcp 0 erreur** ; lint global = 62 erreurs pr�-existantes hors app/mcp (�tat de main).
- Commit c91bd5 (4 fichiers) push� sur mcp-block-crud-2.7.0 (pas de merge main � comme demand�).
- Site TasteWP : plugin install�+activ� via wp-admin curl (nonce + upload multipart), Version 2.7.0 affich�e, token lu (32 chars, jamais affich�) ; E2E via MCP : **6 sc�narios TOUS PASS** (get_page_blocks, create+update CAS, 409 CAS p�rim�, 404 ancre, 400 wrap invers�, dry_run sans effet) + cleanup page restaur�e.

**D�couvertes** :
- git status houetor pr�-existant : M dispatch.ts/tools.ts = 100% EOL CRLF/LF (diff --ignore-space-at-eol vide), D ghjk.py (hors p�rim�tre, laiss�).
- Le lab n'a jamais lint� portage-app-mcp (pas de config eslint) ? 2 erreurs 
o-explicit-any port�es silencieusement ; corrig�es � la source (interface RestErrorData + data: unknown + cast s RestErrorData � l'appel, + cast s {block_id?: string} dans injectPage pour tsc) � comportement inchang�, typecheck 0 erreur lab + prod.
- Token plugin lisible sur dmin.php?page=houetor-connect ; g�n�r� � l'activation.
- TasteWP : pas de shell ? tout par wp-admin curl.
- L'E2E a utilisé le MCP miroir lab (patterns identiques au portage) — l'app Next de la branche exige Supabase connected_sites (site non connecté au dashboard) : limite documentée dans le rapport, pas masquée.

## Exp 016 — Audit complet de `houetor-selfhare` (2026-08-02, validation utilisateur obtenue)

**Contexte** : fil ouvert de la mission (« audit `houetor-selfhare`, NE PAS y toucher sans validation »). Validation obtenue : « fais l'audit pour selfhare (bien sûr en restant dans le lab) ». Objet : `connect\houetor-selfhare.zip` (2e plugin HOUETOR, assistant IA WP autonome via relay `houetor.com/selfhare/relay`).

**Méthode** :
- Extraction du zip → `Temp\opencode\selfhare-audit\houetor-selfhare\` ; identité prouvée avec la source prod (`Pictures\Screenshots\houetor\houetor-selfhare`) : Compare-Object des hashes = **100% identiques**.
- `php -l` WSL : **0 erreur** sur les 16 fichiers PHP.
- Lecture intégrale des 21 fichiers (9 classes + plugin principal + uninstall + JS/CSS + readme + index.php).

**Architecture** : 16 actions (create/update/delete posts|pages|products, get_wp_pages, inject_page, delete_block, revert_to_revision, get_page_blocks, update_block_content, get_page_history) ; flux chat → relay IA (license_key dans le body) → tool_call → preview (diff avant/après) → confirmation UI → dispatch ; rôle dédié `houetor_selfhare_agent` ; 3 tables (memory, routines, actions_log) ; CAS SQL pur sur inject_page/delete_block ; rate limit transients 10/60s ; révisions forcées ; journal d'audit.

**Constats — sécurité (points forts)** :
1. Tous les endpoints AJAX : nonce `houetor_selfhare_nonce` + caps `edit_posts` + rôle agent ; REST : 2 routes GET lecture seule avec permission double.
2. `wp_kses_post` systématique sur tout HTML entrant ; upload whitelist MIME (jpeg/png/webp) + 5 Mo + `media_handle_sideload` ; échappement sorties admin ; JS XSS-safe (tout texte via `$('<span>').text().html()`).
3. Créations en brouillon uniquement ; suppression → corbeille (réversible) ; `revert_to_revision` ; CAS conditionnel + rollback sur échec.

**Constats — faiblesses (par ordre de gravité)** :
1. **Version incohérente (bug #1 connect répliqué)** : header `1.0.1` + stable tag `1.0.1` vs `HOUETOR_SELFHARE_VERSION = '1.0.2'` — et la constante n'est utilisée **nulle part** (définition orpheline, grep 1 occurrence).
2. **Aperçu contournable** : le endpoint `houetor_selfhare_dispatch` n'exécute AUCUN contrôle de preview préalable ; le mode auto (`can_skip_preview`, chat.php:9-15) n'est implémenté nulle part côté serveur et le JS ne l'utilise pas (`$confirmBtn` jamais affiché, admin-chat.js:302-304). Un appel AJAX direct avec nonce+cap exécute sans aperçu.
3. **CAS partiel** : seuls `inject_page`/`delete_block` passent par `cas_write` (ligne 52). `update_content` (routes `update_posts/pages`, les plus utilisées), `update_block_content`, `delete_content`, `create_content`, `revert_to_revision` écrivent DIRECTEMENT sans CAS ni expected_hash → conflits non détectés (≠ connect 2.7.0 : CAS global + dry_run partout).
4. **Rate limit inopérant sur les créations** : `check_rate_limit` renvoie `true` si `$post_id == 0` (ligne 520-521) — `create_content` n'a jamais de post_id → créations illimitées.
5. **`update_content` : str_replace silencieux** : à l'exécution, si `find_text` absent → écriture d'un contenu identique sans erreur (ligne 451-458) ; le preview, lui, bloque (« texte introuvable », ligne 247-249). Divergence preview/exécution.
6. **Routines inertes** : cron hebdo (`execute_routine`, `send_audit_message`) avec `blocking => false` (lignes 71, 93) → les tool_calls renvoyés par le relay sont IGNORÉS, rien n'est exécuté. Feature cosmétique.
7. **Manifest produits fantôme** : `build_manifest()` annonce `products` (name/price/stock_quantity) si WooCommerce mais le dispatch n'écrit que post_title/post_content/post_status → `create_products` crée un produit vide, price/stock jamais modifiables.
8. **Nettoyage incomplet** : uninstall.php ne supprime NI le rôle `houetor_selfhare_agent` NI le cron (`clear_schedule` seulement à la désactivation) ; journal admin LIMIT 10 sans pagination ; `log_action` journalise aussi les lectures → inflation table.
9. **Hack suspect** : `UPDATE ... SET post_modified = post_modified` (cas_write ligne 59-62) — auto-affectation sans effet, résidu probable.
10. **License stockée en clair** dans l'option `houetor_selfhare_license` et transmise en clair (HTTPS) au relay à chaque chat — stockage non protégé si DB compromise (usage courant, à connaître).

**Recommandations (priorité)** : (1) aligner la version + utiliser la constante ; (2) étendre CAS + expected_hash à update_content/update_block_content (modèle connect 2.7.0) ; (3) rate limit créations (compteur global) ; (4) enforce preview côté serveur (flag transient) ou retirer le mode auto ambigu ; (5) corriger la divergence str_replace ; (6) nettoyages : hack post_modified, routines (exécuter les tool_calls ou retirer), produits fantôme, uninstall (rôle+cron), journal paginé.

**Décision** : rapport seul — **aucune modification** du plugin (hors périmètre sans nouvelle validation). Rapport consigné ici + LEARNING_STATE. Hors-périmètre : tout chantier correctif sur selfhare.

## Exp 017 — Grosse correction `houetor-selfhare` 1.0.2, patterns connect 2.7.0 appliqués (2026-08-02, validation utilisateur obtenue)

**Contexte** : après l'audit (Exp 016), validation utilisateur : « je te lance pour une grosse correction et n'oublie pas d'appliquer ce que nous avons compris et modifier sur connect ». Objet : le plugin `houetor-selfhare` (source = extraction du zip versionné, chantier dans `connect\houetor-selfhare\houetor-selfhare\`, copie d'audit Temp laissée intacte).

**Correctifs appliqués (modèle connect 2.7.0 : CAS global + expected_hash, dry_run, rate limit partout, révisions avant écriture, audit écritures seules, version lockstep, erreurs traduites)** :

1. **`includes/class-agent-dispatch.php`** — le cœur du chantier :
   - Const `PREVIEW_TOKEN_TTL = 600` ; helpers `is_write_action()` + `preview_fingerprint()` (md5 json_encode des params).
   - `execute()` réécrit : écritures → **preview token obligatoire côté serveur** (transient `sh_preview_<token>` = fingerprint, usage unique, `delete_transient` après) ; les écritures internes (routines) ne contournent que pour `create_content` (brouillon) ; **gate `expected_hash`** (md5 post_content vs expected_hash → `edit_conflict` 409) ; `dry_run` ; **rate limit** ; capture before/after ; **journal d'audit écritures seules** (lectures non loggées).
   - `preview()` renvoie `preview_token` + `expected_hash` (md5 contenu courant).
   - `check_rate_limit()` : écritures seules, par post `sh_rate_<id>` (10/60 s) OU par user `sh_rate_u_<uid>` pour les créations (post_id=0, fallback CLI `sh_rate_u_cli`) — créations enfin limitées.
   - `update_content()` : via `cas_write` + `find_text` strict (`strpos` → `find_text_not_found`, fin du str_replace silencieux) + meta produits.
   - `update_product_meta()` : WC réel (`_regular_price` via `wc_format_decimal`, `_manage_stock=yes`, `_stock` + `wc_update_product_stock`) ; `create_content()` applique aussi les meta produits (fin du produit fantôme).
   - `delete_content()` : `wp_save_post_revision()` avant `wp_trash_post`.
   - `update_block_content()` : via `cas_write` au lieu de `wp_update_post` direct.
   - **Retrait du hack** `UPDATE ... SET post_modified = post_modified` de `cas_write`.
2. **`includes/class-error-translator.php`** : codes `preview_required` + `find_text_not_found` traduits en conseils.
3. **`assets/admin-chat.js`** : variable `previewToken` récupérée de `res.data.preview_token`, envoyée au dispatch (`tc.preview_token`), réinitialisée à `closeModal()`.
4. **`includes/class-agent-routines.php`** : `send_relay()` désormais **bloquant** (fini `blocking => false`), parse `tool_call` du relay, marque `internal => true`, exécute via Dispatch — les routines planifiées exécutent ENFIN les tool_calls ; `execute_routine` + `send_audit_message` passent par `send_relay`.
5. **`includes/class-license.php`** : licence chiffrée au repos — AES-256-CBC, format `base64(iv):base64(data)`, clé = `sha256(wp_salt('auth') . '|houetor-selfhare')` tronquée 32 octets ; `get_license()` déchiffre, `save_license()` chiffre.
6. **`uninstall.php`** : `delete_option` (license/pages_cache/auto_mode) + `wp_clear_scheduled_hook('houetor_selfhare_cron')` + `remove_role('houetor_selfhare_agent')` — nettoyage complet.
7. **`houetor-selfhare.php`** : header Version **1.0.2** ; **la constante `HOUETOR_SELFHARE_VERSION` est ENFIN utilisée** (localize `version` du JS + footer admin) ; journal admin **paginé** (10/page, `paged=N`, total + liens).
8. **`readme.txt`** : Stable tag 1.0.2 + changelog 1.0.2.

**Vérifications** : `php -l` 0 erreur (16 fichiers) ; aucun BOM dans les .php du zip ; **zip reconstruit** (WSL `zip`, racine `houetor-selfhare/`, 20 fichiers) — `sha256 155e1d99…`, contenu `diff -r` identique au chantier, **install/activation testées** dans WP lab via `wp plugin install <zip>` (Version 1.0.2 affichée).

**Batterie de tests** `scripts/selfhare-test-016.php` (WP lab, 12 sections) : **36 PASS / 0 FAIL (exit 0)** :
1. Version lockstep (constante/header/stable tag 1.0.2) — 3 PASS
2. Preview : token + expected_hash présents — 4 PASS
3. Preview obligatoire serveur : sans token → `preview_required`, mauvais token → `preview_required`, bon token → écriture + révision créée, token à usage unique — 6 PASS
4. CAS : expected_hash périmé → `edit_conflict` 409, contenu non écrasé — 2 PASS
5. `find_text` introuvable → `find_text_not_found` (preview ET exécution), contenu restauré — 3 PASS
6. `dry_run` : succès sans création de post — 2 PASS
7. Rate limit créations : 10 OK puis 11e → `rate_limit_exceeded` — 2 PASS
8. Audit : +1 après écriture, lectures non loggées — 1 PASS
9. Produits (stub WooCommerce) : manifest, preview prix/stock, `_regular_price`/`_stock`/`_manage_stock` écrits — 6 PASS
10. Routines : tool_call du relay exécuté (lecture), écriture via routine refusée (`preview_required`) — 2 PASS
11. License : option chiffrée (pas de clair, format `iv:data`), `get_license()` déchiffre, `is_active()` OK — 4 PASS
12. Preview de lecture (get_page_blocks) — 1 PASS

(Note batterie : les compteurs rate limit créations partagent le budget avec les tests produits → `delete_transient('sh_rate_u_cli')` avant la section 9, artefact de séquencement du script, pas du plugin.)

**Nettoyage post-tests** : 6 pages « SelfHare Test 016 » supprimées, transients `sh_rate_*`/`sh_preview_*` purgeés (les restants expirent en 10 min de toute façon), table `actions_log` conservée (preuve before/after visibles).

**Livrable** : `connect\houetor-selfhare.zip` reconstruit + dossier chantier `connect\houetor-selfhare\` à committer (pattern repo : dossier source + zip suivis). Hors périmètre inchangé : 3 `probe-*.mjs` untracked, copie d'audit Temp, prod jamais touchée.

## Exp 018 — Validation du portage MCP prod en conditions réelles (Fix Day connecté) (2026-08-03)

**Contexte** : l'utilisateur a connecté le site TasteWP « Fix Day » au dashboard HOUETOR (token profil ONG `HWT-ONG-2566161c-…`, ligne Supabase `connected_sites` id `f166ef68-8816-45b0-97f9-d618360a84d6`) et uploadé un starter site (contenu réel : pages About/Accueil/Blog/Contact/Home/Services, blocs `atomic-wind/box` du thème starter). Objectif : tester enfin le **portage MCP prod** (branche `mcp-block-crud-2.7.0` du repo houetor) en conditions réelles — l'E2E de l'Exp 015 passait par le miroir lab faute de site connecté.

**Identifiants fournis par l'utilisateur (stockés dans `.env.learning`, gitignoré, jamais affichés/commités)** : wp-admin Fix Day (`pierre11bolouvi` + mot de passe), token HWT ONG, clés Supabase (`sb_publishable_*` pour anon, JWT `service_role`).

**Ce qui a été fait** :
1. **Infra** : app Next du repo houetor lancée localement — d'abord `next dev` (Turbopack), puis `next build` + `next start` sur le port 3010 (le runtime edge du MCP en dev Turbopack n'inline pas correctement les env ; en prod build c'est OK).
2. **Blocage Supabase résolu** : le `.env.local` du repo houetor (snapshot Vercel CLI) avait les 3 variables Supabase vidées (`""`). `vercel env pull` (dev et production) renvoie des valeurs vides : le compte CLI (`bopiflo05-9197`) n'a pas le droit de décrypter les secrets (seules les variables système TURBO_*/VERCEL_* reviennent, plus `VERCEL_OIDC_TOKEN`). L'URL du projet (`https://jseikgsdfjarozzshnxj.supabase.co`) a été retrouvée par grep dans le repo ; les clés ont été fournies par l'utilisateur. → `.env.local` réécrit avec les 3 variables (gitignoré, jamais commité).
3. **Tests MCP prod — première validation réelle du portage, 9/9 PASS** :
   - `GET /mcp` SSE avec `X-HWT-Token` ONG : profil ONG, uuid correct, **32 tools** dont les 12 tools bloc 2.7.0 (get_page_blocks, create_block, update_block_content, update_blocks, delete_block, transform_block, move_block, duplicate_block, wrap_block, unwrap_block, inject/uninject_page).
   - `POST list_connected_sites` : HTTP 200, Fix Day présent (id, url, token plugin cohérent avec la page admin).
   - Cycle CRUD 2.7.0 complet sur la **page 5 (About, contenu starter)** : `create_block` dry_run (md5 intact, bloc absent, message « DRY RUN (aucune écriture) ») → create réel (ref `e2eprod-…` générée, bloc présent, md5 avancé) → `update_block_content` CAS OK (appliqué) → **CAS périmé → refusé « Conflit CAS » + contenu intact** → `update_blocks` batch atomique (appliqué) → `move_block` vers start (index 0) → `delete_block` (bloc disparu) → **page restaurée à l'identique** (md5 `a40568809ad0d4c949468cd29616c2dd` = état d'origine avant toute écriture de la session).
4. **Site Fix Day** : starter uploadé par l'utilisateur ; plugin `houetor-connect` **toujours actif en 2.7.0** (vérifié via wp-admin cookies) ; token plugin (32 chars) lu sur la page admin et ajouté à `.env.learning` (masqué). Session wp-admin utilisable pour de futurs re-uploads (méthode curl cookies, voir Exp 015).

**Découvertes** :
- Shape des réponses MCP prod : les écritures renvoient `result.data {success, post_id, ref, message}` — **pas de liste de blocs ni md5** → toujours relire `get_page_blocks` après une écriture pour vérifier.
- Les erreurs plugin arrivent en `success:false + error` (HTTP 200, JSON-RPC pas d'`error`), message **traduit avec conseil actionnable** (« Re-lisez la page (get_page_blocks) pour obtenir un content_md5/expected_hash à jour, puis réessayez ») — le error-translator du portage fonctionne.
- `get_wp_pages` prod : `result.data[0].pages.pages[]` (id numériques, title/slug/url) — différent du miroir (même protocole, shape dispatch prod).
- Le script d'E2E lab (Temp/opencode) doit gérer : pageId dans `data[0].pages.pages`, md5 dans `data.content_md5`, erreurs via `success:false`.
- `next dev` (Turbopack) ne suffit pas pour le MCP edge avec env : utiliser `next build` + `next start`.

**Reste à faire (point de reprise)** :
1. Ops structurelles en réel (transform/wrap/duplicate/unwrap) sur la page 2 (Accueil, contenu riche) — script `mcp-e2e-prod.mjs` (Temp/opencode) à étendre ; budget rate limit 10/60s par page.
2. Consigner cette session dans LEARNING_STATE.md + AGENTS/ONBOARDING (fait en fin de session) + commit + push `opencode-learning`.
3. Décisions utilisateur en attente (inchangées) : merge/rollout `mcp-block-crud-2.7.0` → main (houetor) ; lint global houetor (62 erreurs pré-existantes) ; évolutions roadmap (compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit) ; 3 `probe-*.mjs` untracked (écartés).
4. Serveur Next : relançable via `next build` + `next start -p 3010` (procédure ci-dessous) ; les tests passent par `POST http://localhost:3010/mcp` avec header `X-HWT-Token` (token dans `.env.learning`).

**Procédure de relance du serveur MCP prod (si relancé un jour)** :
```powershell
cd C:\Users\Kimsh\Pictures\Screenshots\houetor
node_modules\.bin\next build      # REQUIS si .env.local a changé (inlining NEXT_PUBLIC_*)
node node_modules/next/dist/bin/next start -p 3010
# Test rapide : POST /mcp {"jsonrpc":"2.0","method":"list_connected_sites","params":{},"id":1} + header X-HWT-Token
```

## Exp 019 — Ops structurelles 2.7.0 validées en conditions réelles (Fix Day, page 2 Accueil) (2026-08-03)

**Contexte** : point de reprise Exp 018 — il restait à tester en réel les 4 ops structurelles (transform/wrap/duplicate/unwrap) sur la page 2 (Accueil, contenu starter riche : 2 core/html, media-text, paragraph). Serveur MCP prod relancé (`next build` + `next start -p 3010` — il était tombé). Scripts dans Temp/opencode : `mcp-e2e-struct-prod.mjs` (batterie 1, imparfaite) puis `mcp-e2e-struct2.mjs` (batterie corrigée, **12/12 PASS**).

**Batterie 1 (mcp-e2e-struct-prod.mjs) — 3 anomalies apparentes, toutes expliquées** :
- Le wrap a créé le groupe avec une **nouvelle ref** (`e2eprod-398905c2d5e3`) et les refs A/B ont disparu du niveau racine → `get_page_blocks` **n'expose pas les innerBlocks** (découverte majeure). L'unwrap ciblé sur la ref interne A → « Bloc ref introuvable » (le refus est silencieux dans le JSON, pas une erreur JSON-RPC).
- `duplicate_block` : dry_run sans effet, réel → 2 copies avec **refs régénérées uniques** (e2eprod-e9ee0b0fb3da + e2eprod-7d922ce1a130).
- Le step() du script ne comptait les FAIL que sur exception → les `success:false` passaient en PASS silencieux (défaut de script, corrigé en batterie 2).
- Le md5 final différait de l'état d'origine (`56f889f1…` vs `bdd89ac2…`) → **diff prouvé = 1 seul octet** : `<img … size-full/>` → `<img … size-full />` — normalisation standard de `serialize_blocks` WP (preuve : diff de la révision 19 vs contenu courant via API REST core, nonce récupéré sur post.php). Pas un résidu.

**Batterie corrigée (mcp-e2e-struct2.mjs) — 12/12 PASS** (page 2, budget rate limit 10/60s respecté, retry 429 intégré) :
1. create A (paragraph, ref e2eprod-655e53ff821d) — PASS
2. transform A→heading **dry_run** : md5 inchangé, toujours paragraph — PASS
3. transform A→heading **réel** : `core/heading`, **ref conservée**, md5 avancé — PASS
4. transform A→paragraph (restauration) : `core/paragraph`, **ref conservée** — PASS
5. create B (contigu après A) — PASS
6. **wrap B..A (plage inversée) → 400 refusé** avec message traduit actionnable (« Plage de wrap invalide : le bloc de fin précède le bloc de départ … index croissants d'après get_page_blocks ») — PASS
7. **wrap A..B réel** : groupe `core/group` créé avec **nouvelle ref** (e2eprod-4059980611fa) — PASS
8. **unwrap par ref interne A → refusé** (« introuvable »), groupe intact — PASS
9. **unwrap par ref du GROUPE réel** : groupe disparu, **refs originales A+B restaurées** — PASS
10. delete A+B : aucun résidu, count=4 — PASS
11. **md5 final == md5 initial** (`56f889f1…` = `56f889f1…`) — PASS

**Découvertes structurantes** :
- **Le contrat d'utilisation des ops structurelles est confirmé en réel** : après un wrap, l'agent doit utiliser la **ref du groupe** (renvoyée par le wrap) pour unwrap ; les refs internes restent valides après unwrap (préservées dans le sous-arbre, comme au lab).
- `get_page_blocks` ne liste que les blocs racine (pas d'innerBlocks) → après wrap, seules la ref du groupe et les refs racine sont visibles. L'agent doit lire la réponse du wrap pour obtenir la ref du groupe.
- Les refus (400) arrivent en `result.success=false + error` (HTTP 200, pas d'`error` JSON-RPC) — le error-translator prod traduit bien avec conseil.
- L'API REST core de Fix Day exige un nonce même en GET : le récupérer dans `wpApiSettings` de `post.php?post=2&action=edit` (login via cookies wp-admin, méthode probe-login2/probe-raw4).

**État du site après tests** : page 2 restaurée à l'identique (md5 final = md5 de début de session `56f889f1…`), aucune écriture résiduelle, révisions conservées (normales). Toutes les ops structurelles 2.7.0 sont désormais validées en conditions réelles : transform (dry_run+réel+restauration), wrap (création+refus plage inversée), unwrap (ref groupe), duplicate (refs uniques) — en plus du CRUD complet de l'Exp 018 (9/9).
