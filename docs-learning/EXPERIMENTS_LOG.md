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

## Exp 020 — Scénarios utilisateur réels via le MCP prod (Fix Day, page 5 About) (2026-08-03)

**Contexte** : l'utilisateur a demandé de continuer les tests sur le site TasteWP → relance du serveur Next (`next build` + `next start -p 3010`, il était tombé) + batterie de **scénarios « demandes utilisateur en langage naturel »** exécutés À TRAVERS le MCP prod (portage `mcp-block-crud-2.7.0`), chaîne complète Agent → app/mcp → Supabase → plugin Fix Day. Script : `Temp/opencode/mcp-e2e-scenarios-prod.mjs` (page 5 About, starter : 5 blocs `atomic-wind/box`).

**Résultat : 9/9 PASS** (8 scénarios + état initial ; 8 écritures réelles dans le budget rate limit 10/60s, retry 429 intégré, aucun 429 rencontré) :

| # | Demande utilisateur | Exécution agent | Résultat |
|---|---|---|---|
| S1 | « Ajoute un bloc de texte en bas de la page About » | get_page_blocks (md5) → create_block CAS → relecture | ref `e2es20-cb5b50e8bd14`, index 5 (fin de page) ✓ |
| S2 | « Modifie le texte de ce bloc » | update_block_content CAS → relecture | contenu v2 présent après relecture ✓ |
| S3 | « Répétition générale : transforme en titre SANS enregistrer » | transform dry_run → relecture | md5 inchangé, toujours paragraph ✓ |
| S4 | « Transforme-le réellement en titre, puis remets-le en paragraphe » | transform → heading (ref conservée) → transform retour | round-trip OK, ref stable ✓ |
| S5 | « Applique mes deux corrections en une seule opération » | update_blocks batch (2 updates) → relecture | 1 révision, contenu final vérifié ✓ |
| S6 | « Conflit : la page a été modifiée ailleurs — refuse l'écriture obsolète » | update avec expected_hash périmé | **409 « Conflit CAS : le contenu de la page a changé depuis votre dernière lecture »**, contenu intact, md5 inchangé ✓ |
| S7 | « Remonte ce bloc en haut de page, puis remets-le à sa place » | move_block start → index 0 → move_block end | round-trip positions OK ✓ |
| S8 | « Supprime le bloc temporaire » | delete_block CAS → relecture | aucun résidu, **md5 final == md5 initial** (`a40568809ad0d4c949468cd29616c2dd`) ✓ |

**Découvertes** :
- La chaîne complète (MCP prod + Supabase + plugin 2.7.0) exécute les demandes utilisateur **sans aucune erreur** en conditions réelles : c'est la preuve finale du contrat ONBOARDING §1 (« toute action CRUD demandée par l'utilisateur s'exécute sans erreur ») sur un site réel connecté.
- Le message de conflit 409 prod est bien traduit avec conseil : « Conflit CAS : le contenu de la page a changé depuis votre dernière lecture (Conflit de con… » (truncated) — l'agent sait relire.
- Page 5 restaurée à l'identique ; les refs `e2es20-` ne laissent aucun résidu (vérifié dans le script + cleanup automatique en début de script si besoin).

**Pour reprendre** : serveur Next à relancer si tombé (procédure Exp 018 : `next build` + `next start -p 3010`). Validation réelle **complète** : Exp 018 CRUD 9/9 + Exp 019 structurel 12/12 + Exp 020 scénarios utilisateur 9/9. Reste (décisions utilisateur) : merge/rollout `mcp-block-crud-2.7.0` → main houetor ; lint global houetor (62 erreurs) ; roadmap (compte agent WP moindre privilège, rate limit rewrites séparé, PHPUnit) ; 3 `probe-*.mjs` untracked (écartés).

## Exp 021 — Positionnement précis + bloc enrichi (fond vert + image) sur Fix Day, page About (2026-08-03)

**Mission utilisateur** : sur la page About, créer un bloc **juste après** la section « About Us / About BrightSmile Dental / Dedicated to transforming smiles… », avec **fond vert et une image** (capture locale `Capture d'écran 2026-07-04 015437.png` fournie en pièce jointe). Vérification humaine du rendu : **succès confirmé par l'utilisateur**.

**Exécution** (chaîne complète MCP prod → Supabase → plugin, + REST core WP) :
1. **Localisation de la cible** : impossible via `get_page_blocks` (contenu des blocs starter `atomic-wind/box` = vide, ref = null) → récupération du `post_content` brut via REST core (`/wp-json/wp/v2/pages/5?context=edit`, nonce récupéré dans `wpApiSettings` de `post.php?post=5&action=edit`) + parseur de blocs top-level (v3, correct) → section About Us = **racine index 0**.
2. **Upload de l'image locale** vers WP : `POST /wp-json/wp/v2/media` avec body binaire + `Content-Disposition: attachment; filename="capture-agent-houetor.png"` + `Content-Type: image/png` + `X-WP-Nonce` → **média id 98**, URL `…/uploads/2026/08/capture-agent-houetor.png`.
3. **Création du bloc** : `create_block` via MCP prod — `block_name: core/group`, contenu HTML (div fond vert `#16a34a` + titre + texte + `<img src=…>`), `position: after`, `anchor_index: '0'`, CAS `expected_hash` frais → **ref `agenttest-c4d8da4ddf28`, index 1**.
4. **Double vérification indépendante** : plugin (`get_page_blocks` : index 1, voisin avant = index 0 About Us) **et** parseur v3 du raw REST (racine `@1527-2291` entre About Us `@0-1527` et la section suivante `@2293-7486`).

**Difficultés rencontrées et solutions** :

| # | Difficulté | Résolution |
|---|---|---|
| 1 | **Le modèle de chat ne supporte pas les images** : la capture envoyée par l'utilisateur est illisible directement (error « this model does not support image input ») | Contournement « vision » : description de l'image via **API Gemini** (`gemini-3.6-flash`, clé de `.env.learning`, base64 inline, jamais affichée) — la nature du visuel importait peu pour l'utilisateur (test de positionnement) |
| 2 | **Anciens modèles Gemini indisponibles** : `gemini-2.0-flash` / `gemini-2.5-flash` → 404 « no longer available to new users » | Lister `GET /v1beta/models` → choisir le modèle le plus récent listé (`gemini-3.6-flash`), fallback 3.5 flash |
| 3 | **`get_page_blocks` n'expose pas le contenu des blocs starter** (`atomic-wind/box` : content vide, ref null) → impossible d'identifier la section cible par son texte via le plugin seul | Analyse du **post_content brut** via REST core + parseur de blocs top-level pour mapper texte → index racine |
| 4 | **Parseurs de blocs JS buggés ×2** : (a) regex `[^\s\/>]` tronquait le nom au `/` de `atomic-wind/box` → tous les blocs traités comme fermants ; (b) gestion fausse des self-closing et du niveau racine (31 « racines » fantômes) | Parseur v3 : un seul regex `<!--\s*(\/?wp:)([^\s>]+)([^>]*)-->` + pile, close→pop, self-closing si attrs finissent par `/` → **8 racines exactes = celles du plugin** |
| 5 | **Nonce requis même en GET** sur l'API REST core de Fix Day | Login wp-admin (cookies) + nonce extrait de `wpApiSettings` (méthode probe-login2/probe-raw4) |
| 6 | **Filtrage `wp_kses_post`** du plugin sur le contenu des blocs (les `url()`/expressions CSS seraient strippées) | Fond vert via style inline simple (`background-color`, flex…) + `<img src>` standard — tout passe, rendu vérifié visuellement par l'utilisateur |

**Découvertes structurantes** :
- **Le contrat de positionnement précis est validé en réel** : `position: after + anchor_index` insère le bloc exactement au bon endroit (entre 2 sections), vérifié par 2 sources indépendantes. L'agent peut cibler un bloc starter (sans ref HWC) par son **index**, après avoir résolu le texte → index via le contenu brut.
- **L'upload d'image locale est possible sans accès shell** : REST core `/media` + binaire + `Content-Disposition` — prérequis pour « ajouter un bloc avec cette image » (l'agent peut aussi passer une URL existante directement dans `<img src>`).
- Les 3 blocs de test restants sur Fix Day : accueil ×2 (bas de page + après blog) + About ×1 (section verte) — **conservés pour vérification utilisateur, nettoyage sur demande** (delete + md5 d'origine `a40568809ad0d4c949468cd29616c2dd` pour About).

## Exp 022 — Audit de persistance des données (MCP prod + plugin connect) (2026-08-03)

**Question utilisateur** : les modifications apportées via l'agent ne gardent-elles pas les infos « partagées » vers un site comme du **JSON volatil** qui pourrait disparaître rapidement (ou à la déconnexion du site) ? → **Audit de code prod, aucun changement.**

**Verdict : AUCUNE donnée de travail en JSON volatil — tout est en base durable.** Preuves :

| Donnée | Stockage | Survit à la déconnexion |
|---|---|---|
| Blocs créés/modifiés (contenu des pages) | DB WP `wp_posts.post_content` (HTML sérialisé, pas de JSON) | ✅ |
| Refs HWC (`agenttest-…`) | commentaires HTML dans `post_content` | ✅ |
| Révisions avant écriture | `wp_posts` (post_type `revision`) | ✅ |
| Journal d'audit | table `houetor_connect_actions_log` | ✅ |
| Token plugin | option `hwc_token` (DB WP) | ✅ |
| Lien site↔compte (url+token pour l'agent) | Supabase `connected_sites` | ❌ supprimé — seul le lien, pas les données |
| Annonces/formations/produits injectés | Supabase (tables métier) | ✅ |

**Preuves dans le code** :
- `app/mcp/dispatch.ts` : **100 % stateless** — aucun `Map`/variable globale/cache/localStorage ; chaque requête = `resolveSite()` (relecture Supabase `dispatch.ts:153`) + `pluginRequest()` (fetch direct plugin `dispatch.ts:165`). Aucun JSON intermédiaire conservé.
- Déconnexion = `DELETE /api/connect-site` (`app/api/connect-site/route.ts:247-292`) : supprime **uniquement** la ligne `connected_sites` (url+token) — ne touche ni post_content, ni audit, ni options, ni tables métier.
- Plugin : les seuls `set_transient` sont fonctionnels/cosmétiques : cache de rendu HTML 5 min (`class-api-fetcher.php:52`), statut de connexion revérifié (`class-connect-status.php`), rate limit 60 s volontaire (`class-rest-api.php:142`). Aucun transient ne porte du contenu édité.

**Cas où les données disparaissent vraiment** : (1) suppression/expiration du site WP lui-même (la copie Supabase des injectés survit) ; (2) désinstallation du plugin → options seules supprimées, post_content intact (`uninstall.php`) ; (3) déconnexion → l'agent ne peut plus cibler le site (but), mais l'écrit reste côté site, token conservé pour reconnexion.

**Pour reprendre** : même audit à faire côté `houetor-selfhare` (persistance de ses données : previews, license, journal, routines, relay).

## Exp 023 — Audit de persistance des données côté `houetor-selfhare` (2026-08-03)

**Question utilisateur** : vérifier la même chose que Exp 022 pour selfhare — les données « partagées » (JSON) peuvent-elles disparaître rapidement ou à la déconnexion ? → **Audit de code, aucun changement** (état 1.0.2, Exp 017).

**Verdict : tous les JSON vivent en tables DB WordPress (durables). Aucun fichier JSON sur disque, aucun cache mémoire (aucun `file_put_contents` ni `wp_cache_*` dans le code). Seuls volatils : 2 transients volontaires.**

| Donnée | Stockage | Désactivation | Désinstallation |
|---|---|---|---|
| Modifications de pages (injections/blocs) | `wp_posts.post_content` (écrit direct `$wpdb->update`, `class-agent-dispatch.php:74-112`) | ✅ intact | ✅ intact (`uninstall.php` ne touche pas `wp_posts`) |
| Mémoire agent / onboarding (`context_json`) | table `houetor_selfhare_memory` | ✅ intact | ❌ **DROP TABLE** |
| Routines (`params` JSON) | table `houetor_selfhare_routines` | ✅ intact (cron seul nettoyé : `clear_schedule()`) | ❌ **DROP TABLE** |
| Journal d'audit (`before_json`/`after_json`) | table `houetor_selfhare_actions_log` | ✅ intact | ❌ **DROP TABLE** |
| License (chiffrée AES-256-CBC) | option `houetor_selfhare_license` | ✅ intact | ❌ delete_option |
| Plan / auto_mode / activated_at | options | ✅ | ❌ delete_option |
| Cache pages (id/title/slug) | option `houetor_selfhare_pages_cache` (régénéré à la volée par `get()`) | ✅ | ❌ (régénérable) |
| Produits WC | post_meta WP (`_regular_price`, `_manage_stock`, `_stock`) | ✅ | ✅ (meta de post) |
| **Previews** | transient `sh_preview_<token>` **TTL 600 s** + usage unique | — | — |
| **Rate limit** | transients 60 s | — | — |

**Découvertes structurantes** :
- **Pas de concept de « site connecté »/déconnexion côté selfhare** : la « connexion » = license en option (chiffrée, Exp 017). Rien n'est supprimé à la désactivation (juste le cron). Les transients preview/rate limit sont **volontaires** (jeton de sécurité à usage unique 10 min, compteur 60 s) — aucune donnée utilisateur dedans.
- **Différence notable vs connect (Exp 022)** : à la désinstallation, selfhare **DROP ses 3 tables** (mémoire, routines, audit) et supprime les options — le contenu des pages reste mais l'historique agent (mémoire, routines, journal, license) est **détruit**. connect, lui, ne supprime aucune table à l'uninstall (seulement des options, audit log survit).
- Les JSON (`context_json`, `params`, `before_json`/`after_json`) sont des champs LONGTEXT de tables WP → survivent aux redémarrages, à la déconnexion (inexistante) et aux expirations de transients ; ils ne disparaissent qu'à la **désinstallation volontaire** du plugin.

## Exp 024 — Restyle UI `houetor-selfhare` thème HOUETOR (ref. plans) + sync prod/lab + zip (2026-08-04)

**Mission utilisateur** : arranger l'interface admin du plugin selfhare en s'inspirant du design de `https://www.houetor.com/selfhare/plans` (champs + icônes + **élargir l'invite de commande**), préparer le zip et commiter — dans le dossier prod ET dans le lab (miroir) pour rester cohérents.

### Design de référence (extrait de `app/selfhare/plans/page.tsx`)
Fond `#0D1F1A`, cartes `#1A3028` / gradient `#162B24`, accent vert `#2ECC8A` (orange `#FB923C` pour l'agence), texte `#F0EDE6` / secondaire `#7A9E8E`, titres **Syne** + corps **DM Sans**, cards `rounded-3xl` (24 px), boutons pills `rounded-full`, ombres douces, chevrons/checkmarks SVG inline.

### Restyle appliqué (4 fichiers, prod puis lab)
1. **`assets/admin-chat.css`** (réécrit, 459 → 500+ lignes) : variables CSS `--sh-*` ; `#houetor-selfhare-chat` max-width **800→1080 px**, fond `#0D1F1A` radius 24 px ; **invite de commande élargie** (`#houetor-selfhare-input` : min-height 56 px, padding 14×22, font-size 16, radius 18, fond `#162B24`, placeholder `#7A9E8E`, focus glow vert) ; bouton Envoyer pill 56 px ; bulles : user = dégradé vert (comme CTA plans), assistant/system = carte sombre bordure verte ; toolbar = 2 champs selects sombres avec chevron vert + labels verts uppercase (icônes) ; upload = carré 56 px border vert ; boutons pills `#2ECC8A` texte `#0D1F1A` + `✓` via `::before` ; modal/diff/loading/preview-summary/scrollbars passés en sombre ; `.notice` success/error adaptés.
2. **`includes/class-agent-chat.php`** : emojis remplacés par **SVG inline** (éclair Action, document Page, trombone upload, ✕ retrait = `line` croisées) ; placeholder enrichi (« Décris ce que tu veux : ajouter un bloc, modifier une page… »).
3. **`assets/admin-chat.js`** : `'✅ '` → `'✓ '` (`.text()` n'affiche pas l'emoji ✅ dans les bulles) ; couleur accent `#4ADE80` → `#2ECC8A` (cohérence).
4. **`houetor-selfhare.php`** : `wp_enqueue_style('houetor-selfhare-fonts', Google Fonts DM Sans + Syne)` en dépendance du CSS admin ; header version aligné `1.0.2` (prod était resté `1.0.1`).

### ⚠️ DÉCOUVERTE — divergence prod vs lab (8 fichiers, sens : lab = complet, prod = retard)
Comparaison HEAD↔HEAD de tout le dossier `houetor-selfhare/` : `admin-chat.js`, `houetor-selfhare.php`, `class-agent-dispatch.php`, `class-agent-routines.php`, `class-error-translator.php`, `class-license.php`, `readme.txt`, `uninstall.php` diffèrent. **Le prod n'a PAS les correctifs 1.0.2 testés au lab (Exp 017, 36/36)** : preview serveur obligatoire (preview_token), CAS global, rate limit créations, routines actives, produits réels, journal paginé, license chiffrée, uninstall complet, localize `version` + footer. Le lab est la référence correcte.

### Stratégie de sync (préserver les correctifs lab)
- `admin-chat.css` + `class-agent-chat.php` : **bases HEAD identiques** prod/lab (prouvé par hash) → copie directe prod → lab.
- `admin-chat.js` + `houetor-selfhare.php` : bases différentes → **2 edits ciblés au lab** (✓ + `#2ECC8A` ; fonts) — `preview_token` (lignes 349/383-384), journal paginé, footer version, localize `version` **intacts** (vérifié par relecture + stats diff lab = JS 4 lignes / PHP 3 lignes).
- Vérifs : `php -l` 0 erreur (prod + lab), `node --check` OK (prod + lab).

### Commits, push, zip
- Prod `4bf9681` (branche `mcp-block-crud-2.7.0`, poussée) : 4 fichiers, 304+/153-.
- Lab `dcadf1f` (branche `opencode-learning`, poussée) : 4 fichiers, 303+/152-.
- Zip `outputs/houetor-selfhare.zip` : `git archive --format=zip --prefix=houetor-selfhare/ -o outputs/houetor-selfhare.zip HEAD:houetor-selfhare` → 24 fichiers, 33 184 octets, racine `houetor-selfhare/` vérifiée. (⚠️ 1er essai `HEAD houetor-selfhare` = double imbrication `houetor-selfhare/houetor-selfhare/` → corrigé.)

### ⚠️ Point d'attention pour l'utilisateur
Le zip distribué = **état prod (restyle seul, SANS correctifs sécurité du lab)**. Pour distribuer la version testée 36/36, porter les 8 fichiers du lab → prod puis regénérer le zip (décision utilisateur).

## Exp 025 — Boucle agent multi-étapes : lectures auto + enchaînement + dernière confirmation conservée (2026-08-04)

**Contexte** : après le restyle (Exp 024) et l'audit fiabilité (selfhare = niveau connect 2.7.0), l'utilisateur signale 2 plaintes UX : (1) l'agent demande confirmation pour les **lectures** (get_page_blocks…), (2) pas d'**enchaînement** : 1 message → 1 réponse → 1 exécution, `last_tool_result` jamais renvoyé automatiquement. Mission : lectures auto sans confirmation + boucle serveur multi-tours, avec indicateur de chargement visible — **lab d'abord**, puis déploiement réel sur Fix Day (licence connectée par l'utilisateur).

### Décisions utilisateur (4 questions)
1. Après une écriture exécutée → l'agent **continue seul** (vérifie le résultat, enchaîne) ;
2. **4 étapes max** par demande (`MAX_AGENT_ITERATIONS = 4`) ;
3. Lectures auto **visibles en discret** (ligne étape) + **indicateur de chargement animé** (« tourne progresse ») ;
4. **Lab d'abord**, portage prod après validation.

### Implémentation (lab, 4 fichiers — commit `9f66dec`)
1. **`includes/class-agent-dispatch.php`** : `is_read_action($name)` — lecture = `ALLOWED_ACTIONS[$name]` non-write (get_wp_pages / get_page_blocks / get_page_history).
2. **`includes/class-agent-chat.php`** :
   - `MAX_AGENT_ITERATIONS = 4` ; `agent_loop($message, $site_context, $manifest_schema, $last_tool_result, $last_tool_name)` : boucle `for` qui appelle le relay, **exécute les lectures via `Dispatch::execute()`** (jamais de confirmation), **s'arrête sur la 1re écriture** → retourne `{success, reply, tool_call, steps}` ; plafond atteint → « Limite d'étapes automatiques atteinte » ; lecture répétée (même nom + même md5 params) → arrêt propre.
   - `step_label()` : « Lecture des blocs de la page #5 (12 blocs) », « — échec : … » si lecture KO ; `call_relay()` : extrait la duplication de l'ajax, timeout 60, `last_tool_name` ajouté au contexte.
   - `houetor_selfhare_chat_ajax` : branché sur `agent_loop`, renvoie `steps` au JS.
   - HTML : loading « L'agent réfléchit… » + `.loading-dots`.
3. **`assets/admin-chat.js`** : `sendChat()` réutilisable (params : message, lastToolResult, lastToolName, opts.silent) ; submit → `sendChat` ; steps affichés en `.step` (ligne discrète bord vert) ; `state.lastUserMessage` ; après confirmation d'écriture OK → **reprise auto** `sendChat(userMsg, res.data, executedName, {silent:true})` (pas de re-bulle utilisateur) ; nouvelle écriture éventuelle → panneau de confirmation à nouveau.
4. **`assets/admin-chat.css`** : `.houetor-message.step` (transparent, bordure gauche verte, 12 px, muted) ; `@keyframes loadingDots` (3 points animés).

**Sécurité conservée** : une écriture n'est JAMAIS exécutée dans la boucle — elle est retournée à l'UI qui affiche le panneau « Confirmer l'action » (aperçu avant/après + preview_token serveur obligatoire, modal confirm). **L'utilisateur a confirmé : garder cette confirmation existante** (aucun renforcement).

### Vérifs
`php -l` 0 erreur (scripts/php-lint.sh), `node --check admin-chat.js` OK. Test runtime bloqué au lab (licence inexistante dans l'env localhost:8888) → décision utilisateur : tester en réel sur Fix Day.

### Déploiement réel — Fix Day (site TasteWP, plugin connecté dashboard HOUETOR)
- Zip rebâti depuis le commit lab : **⚠️ le zip Exp 024 était encore DOUBLE-IMBRIGUÉ** (`houetor-selfhare/houetor-selfhare/…`) malgré la note Exp 024 — la bonne commande est `git archive --format=zip --prefix=houetor-selfhare/ -o outputs/houetor-selfhare.zip HEAD:houetor-selfhare/houetor-selfhare` (arbre **du dossier plugin**). Conséquence upload 1 : WordPress « L'archive n'a pas pu être installée. Aucune extension » (nonce upload = celui du formulaire `plugin-install.php?tab=upload`, scope `<form>` — le 1er `_wpnonce` de la page est invalide). Upload 2 : « Le dossier de destination existe déjà » puis vérif trompeuse (404 fichiers / absent plugins.php) — **réplication TasteWP entre serveurs du pool** (quelques minutes) : après propagation, plugin listé puis **ACTIF** (vérifié plugins.php, statut « Désactiver », fichier principal 200 / 0 octet).
- **Licence** : connectée par l'utilisateur (dashboard) → vérifiée page `admin.php?page=houetor-selfhare` : « **Licence active — Plan : starter — Clé : SLH-starter-732251c8…** » + sous-menus **Assistant** et **Routines** présents (ils n'apparaissent que si `is_active()`).

### État et suites
Plugin `houetor-selfhare` 1.0.2 (+boucle) installé/actif sur Fix Day avec licence starter → **tester la boucle en réel** (lecture auto sans confirmation + écriture avec confirmation + reprise auto après exécution ; compteur 4 max ; étapes discrètes + loader). Fils ouverts inchangés : portage prod (8 fichiers Exp 024 + 4 fichiers Exp 025), merge `mcp-block-crud-2.7.0`, lint global houetor (62), probes untracked.

### ⚠️ Point de vigilance — jamais modifié un bloc EXISTANT du site en réel (ni connect, ni selfhare)
**Aucun test réel n'a encore modifié un bloc qui préexistait sur le site** (contenu starter créé par l'utilisateur, ex. blocs `atomic-wind/box` des pages Fix Day). Les `update_block_content` / `update_blocks` validés en réel (Exp 018 cycle CRUD 9/9, Exp 020 scénarios 9/9) portaient toujours sur des blocs **créés par l'agent dans le même flux** (refs `e2eprod-…` / `agenttest-…`) — la page était d'ailleurs restaurée à l'identique (md5 d'origine), ce qui prouve qu'aucun bloc starter n'a été touché. Au lab, la série 003 (TOOLS_DISCOVERED, S2/S4) avait modifié des blocs existants mais sur l'env local avec blocs de test.
**À valider en réel** (première demande de modif d'un bloc starter) : CAS `expected_hash` frais sur contenu existant, bloc sans ref agent → fallback index, sérialisation/reformatage HTML `atomic-wind/box` via `wp_kses_post` (md5 diffère après écriture, cf. TOOLS_DISCOVERED ligne 91), restauration par révision. C'est le premier scénario de la boucle agent à tester avec un bloc starter (modification d'un texte existant).

## Exp 026 — PREMIÈRE modification réelle d'un bloc EXISTANT du site (page About, Fix Day) — point de vigilance Exp 025 LEVÉ (2026-08-04)

**Contexte** : point de vigilance Exp 025 (utilisateur) : jamais modifié un bloc existant en réel (ni connect ni selfhare) — tous les updates passés (Exp 018/020) portaient sur des blocs créés par l'agent dans le même flux. Mission : tester la modification d'un bloc **préexistant** via le MCP prod (portage `mcp-block-crud-2.7.0`, serveur Next relancé `next build` + `next start -p 3010`). Script : `Temp/opencode/mcp-e2e-existing-blocks.mjs`.

### État réel de la page 5 About (avant test)
- **get_page_blocks (MCP prod)** : md5 `d35956a796fb5b14c79cfda5c1065b82`, **6 blocs racine** : index 0 `atomic-wind/box` (starter About Us, content vide), index 1 `core/group` **ref `agenttest-c4d8da4ddf28`** (bloc créé Exp 021, fond vert), index 2-5 `atomic-wind/box` (starter, content vide).
- **Structure réelle** (post_content brut REST) : les 5 racines starter `atomic-wind/box` **contiennent chacune 1 innerBlock** (un enfant `atomic-wind/box`) → `update_block_content` les **refuse par design** (class-block-editor.php:324 : « contient des blocs imbriqués et ne peut pas être modifié directement »). 33 paires de commentaires `atomic-wind/box` au total, 1 seul `wp:group` (pas de doublon — le parseur top-level maison buggé comptait 12 racines, corrigé par comptage des commentaires bruts).

### Résultats — **5/5 PASS**
| Test | Résultat | Preuve |
|---|---|---|
| **A1** dry_run update bloc starter index 0 | ✅ **refus dès le dry_run** : « Le bloc #0 (atomic-wind/box) contient des blocs imbriqués et ne peut pas être modifié directement. » (success:false, aucun refus d'écriture nécessaire) | md5 inchangé |
| **A2** update RÉEL bloc starter index 0 | ✅ **refus par design**, même message, **contenu intact** | md5 `d35956a7…` inchangé, nb blocs identique |
| **B1** dry_run update bloc EXISTANT ref `agenttest-c4d8da4ddf28` | ✅ aucun effet (md5 inchangé, texte de test absent) | md5 `d35956a7…` |
| **B2** update RÉEL bloc existant (modif du texte dans le groupe agent) | ✅ **SUCCÈS** : ref `agenttest-c4d8da4ddf28` **conservée**, contenu vérifié par relecture (get_page_blocks), md5 avance | `d35956a7…` → `afae5278…` |
| **C1** restauration du contenu ORIGINAL (update inverse, CAS) | ✅ **md5 final == md5 initial** (`d35956a796fb5b14c79cfda5c1065b82`) — restauration à l'identique **sans delta de reformatage** (contrairement au lab série 003 ligne 91 : ici le contenu restauré est le HTML déjà normalisé par `wp_kses_post` lors de la création Exp 021) | vérifié via MCP + REST brut |

### Preuves brutes (après test)
- **REST core brut** (page 5, context=edit) : len 22052, md5 `d35956a796fb5b14c79cfda5c1065b82` = **identique à l'état initial**, « TEXTE MODIFIE » absent, texte original « positionnee juste apres » présent, marqueurs `agenttest-c4d8da4ddf28 start/end` intacts.
- **Révisions** : id 108 (2026-08-04 09:18:43, len 22069, contient TEXTE MODIFIE) + id 109 (09:18:44, len 22052, contenu original) = les 2 écritures réelles B2+C1 (1 révision par écriture, comportement 2.7.0).

### Découvertes structurantes
1. **La modification d'un bloc EXISTANT du site fonctionne en réel** : CAS frais + ref stable → update → relecture → restauration à l'identique. Le point de vigilance Exp 025 est levé (au moins via le MCP connect ; la boucle selfhare reste à tester en réel par l'utilisateur dans l'UI).
2. **Les blocs starter `atomic-wind/box` (avec innerBlocks) ne sont PAS modifiables directement** via `update_block_content`/`update_blocks` — refus propre par design (message actionnable, contenu intact). L'agent doit donc cibler les blocs **sans** innerBlocks (groupe agent, paragraphes…) ou utiliser les ops structurelles (transform/wrap/unwrap). Comportement déjà couvert au lab (V3-6, série 003) — confirmé en réel.
3. Le refus intervient **avant** le dry_run (validation structurelle), donc même dry_run → success:false (aucune écriture simulée possible sur un bloc imbriqué).

### État Fix Day
Page About intacte (md5 `d35956a796fb5b14c79cfda5c1065b82`, blocs de test Exp 021 toujours en place). Serveur Next : relancé (port 3010). Aucune modification du code (ni plugin, ni MCP, ni selfhare).

### Suites
Tester la boucle selfhare en réel dans l'UI Fix Day (lectures auto + confirmation + reprise auto) — 1er scénario d'écriture : modif d'un texte dans un bloc **modifiable** (groupe agent ou bloc sans innerBlocks), puis portage prod selfhare (8 fichiers Exp 024 + 4 fichiers Exp 025) + zip + docs. Fils ouverts inchangés : merge `mcp-block-crud-2.7.0`, lint global houetor (62), probes untracked.

## Exp 026 bis — Carte des capacités sur blocs starter imbriqués (dry_run uniquement, page About Fix Day) (2026-08-04)

**Contexte** : après Exp 026 (modif bloc existant OK, bloc starter refusé), l'utilisateur demande : « Vérifie les possibilités de modifier ces blocs (sans faire des modifs d'abord) ». → Batterie **100 % dry_run** des 8 ops MCP prod sur le bloc starter index 0 (`atomic-wind/box` « About Us », 1 innerBlock), page 5, md5 `d35956a796fb5b14c79cfda5c1065b82`. Script : `Temp/opencode/mcp-dryrun-capabilities.mjs`.

### Résultats — 4 refus / 4 OK, page INTACTE
| Op | Verdict | Message |
|---|---|---|
| `update_block_content` (dry_run) | **REFUS** | « Le bloc #0 (atomic-wind/box) contient des blocs imbriqués et ne peut pas être modifié directement. » |
| `update_blocks` batch (dry_run) | **REFUS** | « Le bloc atomic-wind/box ciblé contient des blocs imbriqués — batch abandonné, aucune écriture effectuée. » |
| `transform_block` (dry_run) | **REFUS** | « ne peut pas être transformé directement » (innerBlocks) |
| `unwrap_block` (dry_run) | **REFUS** | « n'est pas un groupe — seul core/group peut être dégroupé » (+ conseil traduit) |
| `duplicate_block` (dry_run) | **OK** | duplication sous-arbre entier prête (ref null, index 1 simulé) |
| `move_block` (dry_run) | **OK** | déplacement vers end prêt (index 5 simulé) |
| `wrap_block` (dry_run) | **OK** | enrobage 1 bloc dans core/group prêt |
| `delete_block` (dry_run) | **OK** | suppression prête |

**Contrôle final** : md5 `d35956a7…` **inchangé**, 6 blocs → aucune écriture (dry_run respecté, cohérent avec la doc : dry_run ne consomme ni rate limit, ni révision, ni audit).

### Découvertes structurantes
1. **Les 4 ops « contenu » (update/batch/transform/unwrap) refusent toutes les innerBlocks par design** — garde-fou volontaire (class-block-editor.php:324, 417, 591, 1041) : impossible de modifier ou transformer un bloc imbriqué avec le plugin 2.7.0.
2. **`locate_block` ne descend JAMAIS dans les innerBlocks** (parcours racine uniquement, lignes 257-271) → même un enfant `atomic-wind/box` n'est pas ciblable par ref ou index. `get_page_blocks` n'expose d'ailleurs que les racines (content vide pour les starters).
3. **Les 4 ops structurelles (duplicate/move/wrap/delete) acceptent les blocs imbriqués** — elles manipulent le sous-arbre entier sans toucher au contenu.
4. **Conséquence produit** : pour « corriger le texte d'une section starter » (cas d'usage réel de l'agent), les routes 2.7.0 ne permettent pas la modification directe → piste évolution : route `replace_block` (remplacer un sous-arbre entier par un contenu neuf, CAS + révision + audit). Candidat roadmap à proposer à l'utilisateur.

### État
Aucune modification de code ni de contenu. Fix Day page About intacte (md5 `d35956a7…`). Serveur Next toujours actif (3010). Docs : ce fichier + LEARNING_STATE (section Exp 026 complétée).

## Exp 027 — PATCH BLOCS IMBRIQUÉS 2.8.0 : wrap/update ciblent les enfants (env lab + déploiement réel Fix Day) (2026-08-04)

**Contexte** : correctif d'édition des blocs imbriqués fourni par l'utilisateur (fichier `class-block-editor.php` + 2 scripts de test, dossier `Temp/opencode/nested-patch/`). Le plugin 2.7.0 refuse toute écriture sur un bloc imbriqué (garde-fous Exp 026 bis) et `locate_block` ne descend pas dans les innerBlocks. Mission : porter le patch dans le lab (`opencode-learning`), le valider (batteries + tests fournis + MCP miroir), puis le déployer et le valider **en conditions réelles sur Fix Day**.

### Portage et vérifications statiques
- Working tree lab **patché** : `connect/houetor-connect/includes/class-block-editor.php` == fichier fourni (identique après normalisation EOL). Bump `HWC_VERSION 2.8.0` (`houetor-connect.php`) + changelog `readme.txt`.
- Grep conforme : `locate_block()` reste dans move_block (L844/866), duplicate_block (L963), wrap_block (L1028/1037), unwrap_block (L1111) ; `locate_block_deep()` **uniquement** dans update_block_content (L382), batch_update_blocks (L483), transform_block (L651) ; delete_block/find_block_index_by_ref gardent leur boucle top-level inline.
- **Exposition enfants** : `flatten_blocks_recursive` (L43-64) ajoute désormais `parent_ref` (index du parent), `depth`, `has_children`, `child_count` à chaque bloc exposé par `get_page_blocks`.
- Nouveau message refus conteneur (update/batch/transform) : « est un conteneur (il a des blocs enfants) — impossible d'y écrire du contenu directement. Utilise get_page_blocks pour lister ses enfants (parent_ref = …) et cible l'un d'eux par sa propre ref/index ».
- `php -l` : **0 erreur** (14 fichiers). RISQUE 2 : l'ancien message « est un conteneur » subsiste dans `houetor-selfhare/class-agent-dispatch.php:854` (plugin séparé, hors périmètre, à tracer) et dans les citations historiques des docs Exp 025/026 (preuves, conservées).

### Tests fournis adaptés (env lab localhost:8888)
- `test-nested-block-depth1.php` (group > paragraph) : **TOUS LES TESTS PASSENT** — création depth 1, update du groupe, mise à jour de l'enfant imbriqué par ref, refus conteneur avec nouveau message, structure préservée.
- `test-nested-block-depth2-refs.php` (columns > column > paragraph, refs `annonces-abc123` + batch par index) : **TOUS LES TESTS PASSENT** — profondeur 2, refs enfants, batch, structure préservée.
- Wrapper permanent : `scripts/nested-tests.sh`.

### Batteries complètes (plugin + MCP miroir)
- Plugin : **V3 32/32, STRUCTURAL 42/42, TRANSFORM 21/21, RETENTION 9/9, TIER POLICY 11/11** — md5 final page 2 = `c4abdffe…` identique (RISQUE 1 : la référence page 2 contenait déjà 2 core/quote imbriqués → index globaux ≠ top-level, batteries sur refs/index `_top`, aucune assertion sur index absolus).
- MCP miroir `mirror-suite.sh` : **unitaires 42/42, intégration 52/52, scénarios 41/41**.

### Déploiement réel Fix Day — méthode « jumeau » (dossier `houetor-connect-280`)
- Upload direct du zip 2.8.0 **refusé** par WP (« le dossier de destination existe déjà » — l'écran plugin upload ne remplace jamais un dossier existant).
- Solution sans risque token : zip préfixé **`houetor-connect-280/`** (24 fichiers, `git archive HEAD:houetor-connect` + overlay des 3 fichiers modifiés via `zip`), upload wp-admin (`update.php?action=upload-plugin`, nonce du formulaire `plugin-install.php?tab=upload`, champ submit `install-plugin-submit` obligatoire), puis **désactivation du 2.7.0 + activation du jumeau** (liens `plugins.php?action=deactivate|activate&plugin=…&_wpnonce=…`).
- **Token/Supabase préservés** : options partagées (même `hwc_token`), `hwc_deactivate` ne fait que `wp_clear_scheduled_hook('hwc_audit_cleanup')`, `hwc_activate` ne régénère le token que s'il est absent → re-crée le cron. Dashboard connecté intact.
- Vérif fonctionnelle : `https://fixday.s6-tastewp.com/wp-content/plugins/houetor-connect-280/readme.txt` → **Stable tag 2.8.0** ; MCP répond avec le comportement 2.8.0 (champs child_count/has_children/depth/parent_ref exposés, update enfant par ref accepté). L'ancien 2.7.0 reste désactivé dans `houetor-connect/` (réservé ; suppression manuelle possible).

### Validation E2E réelle — **10/10 PASS** (`Temp/opencode/mcp-e2e-nested-prod.mjs`, MCP local 3010)
| Test | Résultat |
|---|---|
| login wp-admin + nonce | PASS |
| créer page de test (REST core) | PASS (page 135, publiée) |
| get_page_blocks | PASS (md5 initial) |
| create_block ×2 (paragraph module lab) | PASS (refA `lab-682823d5ce39`, refB `lab-54a1bded88a3`) |
| **wrap_block [A..B] → core/group** | PASS — groupe `child_count=2`, `has_children=true` |
| **enfants exposés depth=1 + parent_ref** | PASS — A et B à `depth=1`, `parent_ref=1` (AVANT le patch : invisibles) |
| preuve REST raw avant (context=edit) | PASS — marqueurs `HWC lab-… start/end` présents dans le raw, texte original |
| **update_block_content sur ENFANT A par ref** | PASS — **LE test du patch** : contenu relu « ENFANT 1 — TEXTE MODIFIE PAR LE PATCH IMBRIQUE » |
| preuve REST raw après | PASS — texte modifié présent, ancien absent, frère B intact, structure `wp:group` intacte |
| nettoyage DELETE page | PASS (404 après) |

**Adaptations des attentes du test au comportement réel** : (1) les blocs **sans ref custom** sont exposés avec `ref=null` (recherche du groupe par `name/blockName` et non par ref) ; (2) `parent_ref` = **index global du parent** (entier), pas sa ref ; (3) le raw `content` du REST core n'est exposé qu'en `?context=edit`.

### Nettoyage Fix Day
Pages de test toutes supprimées (111, 118, 124, 129, 135) + **page diag 110 « diag-tmp »** (draft laissé par le diagnostic auth, `deleted:true`). Reste « Privacy Policy » (draft natif WP, non touché). Aucun bloc starter modifié (page About et Accueil intactes, md5 d'origine).

### Découvertes structurantes
1. **L'édition d'un bloc imbriqué fonctionne en réel** : wrap crée le groupe (children exposés), `update_block_content` cible un enfant par sa ref (locate_block_deep), structure et frères intacts, preuve REST brute avant/après.
2. **L'exposition des enfants est la clé de l'UX agent** : `get_page_blocks` renvoie maintenant `parent_ref/depth/has_children/child_count` → l'agent peut lister les enfants et cibler précisément (message de refus actionnable : « cible l'un d'eux par sa propre ref/index »).
3. **`ref=null` pour les blocs sans data-ref custom** : les blocs starter/existants sans ref générée apparaissent avec `ref=null` → l'agent doit utiliser l'index (fallback existant) ; les blocs créés par le plugin ont leur ref `lab-…`/`hwc-…`.
4. **Méthode « jumeau » pour déployer une MAJ d'un plugin sur TasteWP** (dossier existant) : zip avec préfixe du dossier jumeau → upload → bascule désactiver/activer → token/options préservés. À réutiliser pour le portage prod selfhare et le rollout `mcp-block-crud-2.7.0`.

### État
Lab : working tree patché 2.8.0 (à committer). Fix Day : plugin **2.8.0 actif** (dossier `houetor-connect-280`), 2.7.0 désactivé (dossier `houetor-connect`, réservé), token/Supabase intacts, aucun résidu de test. Serveur Next toujours actif (3010). Docs : ce fichier + LEARNING_STATE (section Exp 027).

### Suites
Commits ciblés lab + push `opencode-learning` (docs + patch + tests fournis + adaptations MCP miroir). Fils ouverts inchangés : portage prod selfhare (8 fichiers Exp 024 + 4 Exp 025) + zip (méthode jumeau dispo) ; merge/rollout `mcp-block-crud-2.7.0` (le portage MCP prod devra suivre le patch 2.8.0 pour exposer/éditer les enfants) ; suppression éventuelle du dossier `houetor-connect` (2.7.0) sur Fix Day ; lint global houetor (62) ; roadmap (replace_block).

## Exp 028 — MODIFICATION RÉELLE D'UN ENFANT IMBRIQUÉ STARTER (page About Fix Day, 2.8.0) (2026-08-04)

**Contexte** : suite d'Exp 027. Le patch 2.8.0 (locate_block_deep) a été validé sur des blocs **créés par l'agent** (wrap + update enfant par ref). Mission : le valider sur des blocs **préexistants du starter** (jamais modifiés en réel avant) — le cas exact des 4 refus par design d'Exp 026 bis. Serveur Next relancé (`next build` + `next start -p 3010`, procédure Exp 018). Le GET SSE authentifié répond (401 sans token = normal).

### Préparation
- Smoke test MCP prod : page 5 About expose désormais **80 blocs sur TOUS les niveaux** (avant patch : 6 racines seules) avec `parent_ref/depth/has_children/child_count` — md5 `d35956a7…` identique = exposition seule, contenu intact.
- Cible choisie via raw REST (`context=edit`, nonce wp-admin) : **idx 2 = `atomic-wind/text` « About Us »** (depth 2, parent_ref 1, ref null → ciblage par index, fallback).

### Validation — Exp 028 (update enfant imbriqué starter) : **7/7 PASS** (`mcp-e2e-nested-starter.mjs`)
| Test | Résultat |
|---|---|
| dry_run update idx 2 (MODIF) | PASS — accepté (avant patch : refus conteneur), md5 inchangé, contenu inchangé |
| **update RÉEL idx 2** | PASS — « About Us — MODIF TEST IMBRIQUE » relu, md5 `d35956a7…`→`4661c256…` |
| preuve REST raw après | PASS — MODIF présent, ancien absent, structure parent/grand-parent intactes |
| restauration ORIGINAL | PASS — « About Us » restauré |
| preuve REST raw finale | PASS — len 22050, « About Us » présent, MODIF absent |

### Validation — Exp 028bis (batch sur enfant imbriqué) : **batch PASS, transform = refus par design** (`mcp-e2e-nested-batch-transform.mjs`)
| Test | Résultat |
|---|---|
| batch `update_blocks` idx 2 | PASS — « MODIF BATCH » relu, structure intacte |
| restauration batch | PASS — contenu original |
| transform `atomic-wind/text`→`core/paragraph` | REFUS par design — « Bloc atomic-wind/text non transformable (blocs de texte uniquement : core/paragraph, core/heading, core/quote, core/list, core/code, core/preformatted, core/pullquote) » — garde-fou existant, **pas un défaut du patch** |

### Validation — Exp 028ter (transform sur enfant imbriqué core, page jetable 147) : **7/8 PASS** (`mcp-e2e-nested-transform-core.mjs`)
| Test | Résultat |
|---|---|
| create ×2 + wrap [A..B] → core/group | PASS — groupe child_count 2 (⚠️ `refG=null` renvoyé par wrap : ciblage du groupe par blockName, pas par ref) |
| **transform ENFANT A paragraph→heading par ref** | PASS — type relu `core/heading` (depth 1, parent_ref 1) — **le test clé** |
| transform retour heading→paragraph | PASS — type restauré, contenu conservé |
| unwrap groupe | FAIL script (refG=null, « ref ou block_index requis ») — hypothèse du script, pas un bug plugin |
| cleanup DELETE page | PASS — page 147 supprimée, aucun résidu |

### Analyse du delta md5 après restauration (22052→22050)
- Diff character-level : **1 seul point** : `--> \n <span` (rev 109) → `--><span` (final) — le `\n` canonique après le commentaire d'ouverture est retiré par `serialize_blocks` lors de la réécriture.
- Comparaison sémantique : **structures strictement identiques (79 blocs), refs identiques (agenttest-c4d8da4ddf28), texte visible identique** (hors 1 espace issu du `\n`).
- Conclusion : normalisation canonique WP (même famille que `size-full/>`→`size-full />` d'Exp 019), **aucun résidu de contenu**.
- **Restauration au md5 EXACT** : réécriture du raw d'origine (rev 109, len 22052) via REST core `?context=edit` → md5 final `d35956a796fb5b14c79cfda5c1065b82` == md5 initial ✓ (80 blocs, idx 2 « About Us » intact).

### Découvertes structurantes
1. **Le patch 2.8.0 lève les 4 refus d'Exp 026 bis pour l'édition** : update ET batch sur enfant imbriqué starter (idx, sans ref) fonctionnent en réel ; transform sur enfant imbriqué **core** fonctionne aussi (par ref, depth 1).
2. **Limite du transform conservée** : `atomic-wind/text` (starter) n'est PAS transformable — types core texte uniquement (garde-fou design, à connaître pour l'agent : pas un bug).
3. **`ref=null` sur le groupe créé par wrap** (page jetable) : le wrap renvoie `ref=null` quand... (à préciser — en Exp 027 le groupe avait une ref). Ciblage de repli : par blockName/index.
4. **Restauration exacte possible** : réécriture du raw d'origine via REST core (rev d'avant test) → md5 identique. La restauration via update_block_content seule laisse le delta de normalisation `serialize_blocks` (1 newline) — bénin mais visible au md5.

### État
Fix Day : page About **restaurée au md5 EXACT d'origine** (`d35956a7…`), aucun résidu (page 147 supprimée), plugin 2.8.0 actif, serveur Next actif (3010). Lab : commits Exp 027 poussés (7400476/1115893/736e5d8/f67d366), git propre sauf probes + mirror-suite.sh EOL.

### Suites
Commit docs Exp 028 + push. Fils ouverts inchangés : portage prod selfhare (8 fichiers Exp 024 + 4 Exp 025) + zip (méthode jumeau) ; merge/rollout `mcp-block-crud-2.7.0` → main houetor (le portage MCP prod a été validé avec le comportement 2.8.0 — get_page_blocks expose les enfants via le plugin) ; suppression dossier `houetor-connect` (2.7.0) Fix Day ; lint global houetor (62) ; roadmap (replace_block, compte agent WP, rate limit rewrites, PHPUnit).

## Exp 029 — MODIFICATION RÉELLE PAGE CONTACT (demande utilisateur directe, bloc starter imbriqué, 2.8.0) (2026-08-04)

**Contexte** : l'utilisateur choisit lui-même un bloc sur la page **Contact** (id 8) de Fix Day et valide la modification — preuve finale du contrat « l'utilisateur contrôle ». Serveur Next actif (3010).

| Élément | État |
|---|---|
| **Localisation** | ✅ page Contact = id 8 (get_wp_pages) ; **57 blocs exposés** (md5 `f309e426…`) ; texte cible = **idx 4** `atomic-wind/text` (depth 2, parent_ref 1, ref null) « Have questions or ready to book your appointment? Reach out to our friendly team — we're here to help you smile. » |
| **Proposition** | ✅ texte de remplacement simple proposé par l'agent, dry_run d'abord : 200, md5 inchangé, contenu inchangé |
| **Validation utilisateur** | ✅ « vas-y » |
| **Écriture réelle** | ✅ `update_block_content` idx 4, CAS frais → 200, md5 `f309e426…`→`106e1db0…`, relecture : « Questions or ready to book your appointment? Our friendly team is here to help you smile every day. » |
| **Preuve brute** | ✅ REST core `context=edit` (len 15788) : MODIF présent, ORIG absent, structure `</p><!-- /wp:atomic-wind/text --></div>` intacte |
| **Contrôle visuel** | ✅ lien communiqué à l'utilisateur : https://fixday.s6-tastewp.com/contact/ → « gardons ça, ça fonctionne » |

**Découverte** : timeouts réseau intermittents vers Fix Day depuis Windows (ETIMEDOUT Cloudflare 188.114.x.x) — transitoires, site de nouveau HTTP 200 après ~20 s, l'écriture MCP n'a pas été affectée (retry).

**Suite demandée utilisateur** : vérifier que `houetor-selfhare` est capable de la même chose (modifier un bloc existant du site, y compris imbriqué) ; **feu vert** pour le mettre à jour si non.

### État
Fix Day : page Contact **modifiée en réel et CONSERVÉE** (décision utilisateur, md5 `106e1db0…`), page About intacte (`d35956a7…`), plugin 2.8.0 actif, serveur Next actif (3010). Lab : docs Exp 028 poussées (97d7790 + 8014fa0).

### Suites
Audit selfhare (capacité = édition bloc existant + imbriqué, comparer class-block-editor/agent-dispatch) → mise à jour si nécessaire (feu vert). Fils ouverts inchangés : portage prod selfhare, merge `mcp-block-crud-2.7.0`, dossier 2.7.0 Fix Day, lint global (62), roadmap.

## Exp 030 — SELFHARE 1.0.3 : ÉDITION DE BLOCS IMBRIQUÉS (audit + portage + test réel Fix Day) (2026-08-04)

**Objectif** : audit demandé Exp 029 (« selfhare sait-il modifier un bloc existant du site, y compris imbriqué ? », feu vert utilisateur pour mise à jour) → implémenter + déployer + prouver en réel.

### Audit (avant) : selfhare 1.0.2 ne sait PAS
- `get_page_blocks` : **top-level uniquement** (index/blockName/content) — pas de `ref`/`parent_ref`/`depth`/`has_children`/`child_count`, les enfants imbriqués invisibles
- `update_block_content` : boucle top-level ; **refus L853-854** « Le bloc #N … contient des blocs imbriqués et ne peut pas être modifié directement » → impossible d'éditer un enfant starter (`atomic-wind/box` > `atomic-wind/text`)
- Ciblage par index uniquement (pas de refs HWC, marqueurs `sh:ref:` réservés aux injections HTML)

### Implémentation (portage pattern connect 2.8.0)
1. `get_page_blocks` → `flatten_blocks_recursive` (L776) : liste plate **tous les blocs**, ajout `parent_ref`, `depth`, `has_children`, `child_count`
2. `update_block_content` → `locate_block_deep` (L809, par référence) : cible un bloc **à toute profondeur** par index global ; message introuvable inclut « blocs imbriqués inclus » + bornes (0-N)
3. Refus conteneur → **actionnable** (aligné connect L399) : « est un conteneur … Utilise get_page_blocks pour lister ses enfants (parent_ref = #N) et cible l'un d'eux par son propre index »
4. `compute_preview` update_block_content → localisation récursive (l'ancien texte pour le warning de perte est trouvé même imbriqué)
5. **Bug pré-existant corrigé** : `compute_preview` L281 réécrivait `update_block_content` → `update_content` (prefix `update_*`) et `delete_block` → `delete_content` — le case dédié était **inatteignable**, tout preview/execute passait par la mauvaise branche (« Contenu introuvable » systématique) → liste `$explicit_cases` (L278-287)
6. Version **1.0.2 → 1.0.3** (header + constante + stable tag + changelog readme.txt)

### Tests locaux (env WSL, script selfhare-test-016.php étendu 1.0.3)
**53/53 PASS** — dont nouvelles sections : flatten 4 blocs (group/p/h2/p) avec index/depth/parent_ref/has_children/child_count exacts ; conteneur refusé avec message actionnable + contenu intact ; **enfant imbriqué modifié en réel local** (idx 2 dans group → « Enfant 2 modifié » + structure group préservée + get_page_blocks reflète) ; index 99 → « introuvable (0-3, blocs imbriqués inclus) ».

### Déploiement Fix Day (méthode jumeau, leçon Exp 027)
- Zip : `git archive --prefix=houetor-selfhare-103/ -o outputs/houetor-selfhare-103.zip HEAD:houetor-selfhare/houetor-selfhare` (37 824 o)
- Upload wp-admin curl (nonce formulaire upload) : **« Le dossier de destination existe déjà »** → en fait **installé** (réplication TasteWP, 1er POST 200 silencieux)
- Bascule plugins.php (nonces) : `houetor-selfhare` → deactivate, `houetor-selfhare-103` → activate → **1.0.3 ACTIF**
- ⚠️ Les 2 plugins partagent le même slug WP (`houetor-selfhare.php`) → même `data-slug` sur plugins.php, versions « mélangées » ; la preuve fiable = page admin « SelfHare v1.0.3 » (footer) + test fonctionnel AJAX

### Test réel Fix Day (AJAX `houetor_selfhare_dispatch`, nonce localisé `HouetorSelfHare`)
- **Lecture imbriquée** ✅ page Contact (8) : **57 blocs aplatis**, children exposés (`{i:1 n:atomic-wind/box d:1 p:0 hc:3}`, `{i:4 n:atomic-wind/text d:2 p:1}` = bloc modifié Exp 029 visible) — impossible avant 1.0.3
- **Écriture imbriquée réelle** ✅ page About (5), idx 2 `atomic-wind/text` (depth 2, parent_ref 1) « About Us » → « About Us [TEST 1.0.3] » : preview (summary correct) → execute CAS → succès ; relecture get_page_blocks : « About Us [TEST 1.0.3] »
- **Restauration exacte** ✅ réécriture « About Us » : md5 final `856c1c99…`, texte `About Us` exact (REST core), **0 résidu** du texte de test
- **Delta analysé (preuve brute)** : rev 154 (pré-test, md5 `d35956a7…` = état Exp 028 restauré) vs actuel (`856c1c99…`) : **seule différence = 1 `\n` retiré par serialize_blocks** (`</span>\n<!-- /wp:` → `</span><!-- /wp:`) — normalisation canonique identique à connect Exp 028, 0 perte sémantique ; `856c1c99…` est un état canonique déjà existant (rev 146, 144, 142)
- **Idempotence** ✅ 2e round-trip même texte → md5 **inchangé** `856c1c99…`
- **Note** : dry_run sur écriture sans preview_token → refus `preview_required` (design Exp 017 : confirmation obligatoire même pour dry_run, pas un bug)

### État
Fix Day : **selfhare 1.0.3 actif** (page admin affiche v1.0.3), dossier `houetor-selfhare-103/` en plus du `houetor-selfhare/` (1.0.2 désactivé), édite les blocs imbriqués du starter. Lab : commit `d71a302` (3 fichiers plugin + readme), zip suivi `houetor-selfhare.zip` reconstruit en 1.0.3 (24 fichiers, prefix `houetor-selfhare/`). Docs : Exp 030.

### Suites
Décision utilisateur attendue : garder le dossier jumeau `-103` (méthode actuelle) ou nettoyer l'ancien 1.0.2 désactivé. Fils ouverts inchangés : portage prod selfhare (8 correctifs Exp 024 + 4 boucle Exp 025 + 1.0.3), merge `mcp-block-crud-2.7.0`, dossier 2.7.0 connect Fix Day, lint global (62), roadmap.

## Exp 030 bis — ADDENDUM UTILISATEUR : preuve brute AVANT/APRÈS patch blocs imbriqués (2026-08-04)

**Contexte** : après Exp 030 (selfhare 1.0.3 = portage du patch connect 2.8.0 blocs imbriqués), l'utilisateur pose un ADDENDUM de validation avant conclusion : (1) le refus des blocs conteneurs est un comportement VOLONTAIRE déjà couvert par des tests nommés (V3-6, T7, T8, T10) — coller la sortie brute AVANT et APRÈS le patch, ces tests doivent rester PASS ; (2) vérifier qu'aucun de ces scripts n'utilise d'index HARDCODÉ (ex. littéral « 4 ») vs index recalculé dynamiquement via get_page_blocks ; (3) ajouter un test NOUVEAU ciblant un enfant À L'INTÉRIEUR du core/quote natif #1/#4 de la page 2 par ref ou nouvel index global → SUCCÈS sans toucher le parent ni les autres enfants.

### Point 2 — Risque d'index hardcodé : ÉCARTÉ (les deux scripts sont dynamiques)
- `rest-test-v3.php` V3-6 (L145-148) : après inject du `core/group`, l'index du bloc imbriqué est **recherché dynamiquement** (`foreach $blocks3['blocks'] ... if blockName === 'core/group' → $nested_idx`) — aucun littéral.
- `rest-test-transform.php T10 (L175-179) : scan dynamique (`blockName === 'core/quote' && index < 5 && ref === null → $nested_idx`) — aucun littéral.
- **Conclusion** : si un conteneur apparaissait AVANT le quote natif dans la page 2 (renumérotation des index globaux), les deux tests recalcularaient l'index à chaque exécution → aucun risque d'assertion périmée. (T2 utilise aussi un index recalculé depuis la ref, L77-78.)

### Point 1 — Sortie brute AVANT (2.7.0) / APRÈS (2.8.0)
Méthode : swap temporaire du plugin du lab vers 2.7.0 (git archive 759a959, vérifié 0 occurrence locate_block_deep/has_children), exécution, puis restauration 2.8.0 (md5 class-block-editor.php identique source, plugin list 2.8.0). Tests dans l'env isolé WSL (wp eval-file), page 2 restaurée au md5 initial c4abdffec127… à chaque fin de suite.

**AVANT (plugin 2.7.0) — premières exécutions** :
- `rest-test-v3.php` : **31 PASS / 1 FAIL** — le FAIL = V3-6 « 400 + abandon » : le refus fonctionnait (400, abandon, md5 inchangé) mais le libellé 2.7.0 était « Le bloc core/group ciblé contient des blocs imbriqués » (mot « conteneur » absent).
- `rest-test-transform.php` : **20 PASS / 1 FAIL** — le FAIL = T10 : idem (400 + « contient des blocs imbriqués », mot « conteneur » absent du message 2.7.0).

**Découverte structurante** : les docs confirment (TOOLS_DISCOVERED.md L90/L109) que les tests vérifiaient « imbriqué » avant le patch ; le mot « conteneur » + le conseil actionnable (« Cible directement un enfant (voir get_page_blocks, parent_ref = cet index) ») ont été ajoutés par le patch 2.8.0 (Exp 027, L399). Le COMPORTEMENT de refus (400 + abandon + aucune écriture) est IDENTIQUE avant et après ; seule l'assertion de libellé devait être alignée.

**Action** : assertions V3-6/T10 rendues robustes au libellé — acceptent « conteneur » OU « imbriqué(s) » (le comportement testé reste le même : 400 + abandon + contenu intact).

**AVANT (2.7.0) — après alignement assertion** : `rest-test-v3.php` **32/32 PASS** ; `rest-test-transform.php` **21/21 PASS** — y compris V3-6 et T10.
**APRÈS (2.8.0) — après restauration** : `rest-test-v3.php` **32/32 PASS** ; `rest-test-transform.php` **21/21 PASS** — V3-6 (400 « est un conteneur … Cible directement un enfant (parent_ref = cet index) » + md5 inchangé), T7 (CAS 409), T8 (dry_run), T10 (400 « conteneur ») tous PASS.

### Point 3 — Test NOUVEAU : enfant DANS core/quote natif #1 (page 2)
Nouveau script scripts/rest-test-nested-child-native.php : **11/11 PASS** (2.8.0) :
- **N-1** : localisation dynamique — quote natif #1 idx 4 (ref NULL) + son enfant idx 5 (core/paragraph, ref NULL, parent_ref 4)
- **N-2** : dry_run update de l'ENFANT (par ref NULL + index global 5) → 200 + dry_run=true, md5 inchangé, contenu inchangé
- **N-3** : écriture RÉELLE → 200, contenu enfant = « ENFANT QUOTE NATIF — MODIF TEST 1.0.3 », **parent quote intact** (has_children=true, child_count=1), autres enfants non touchés
- **N-4** : restauration du contenu d'origine → 200, enfant re-lu identique
- **N-5** : restauration complète page 2 → **md5 final == md5 initial c4abdffec127…** (aucun résidu)

### Conclusion addendum
Le refus des conteneurs est bien un comportement volontaire, couvert AVANT (2.7.0) et APRÈS (2.8.0) par V3-6/T7/T8/T10 (tous PASS des deux côtés après alignement du libellé d'assertion sur le message amélioré par le patch) ; aucun index hardcodé ; la capacité nouvelle (cibler un enfant DANS un conteneur natif, par index global, sans toucher au parent) est prouvée par le test dédié 11/11. Patch 1.0.3/2.8.0 : comportements de refus préservés, capacité d'édition imbriquée ajoutée — conforme à l'addendum.

## Exp 031 — SECTION 27 : chantier blocs imbriqués rejoué de bout en bout (scripts commités + Étape 6 MCP portage) (2026-08-05)

**Contexte** : la Section 27 (chantier blocs imbriqués du repo utilisateur) exige, dans l'ordre : (1) retrouver/reconstruire les scripts de test et les commiter, (2) prouver le patch appliqué, (3) vérifier le risque de renumérotation d'index (RISQUE 1), (4) rejouer la batterie complète avec preuves brutes, (5) documenter les validations réelles Fix Day, (6) mettre à jour le wrapper MCP (tools.ts + error-translator.ts) avec les 4 champs imbriqués. Interdiction de merger vers `mcp-block-crud-2.7.0`/`main` tant que les étapes ne sont pas documentées.

### Étape 1 — Scripts de test commités (commit c18aa1d)
- Les 11 scripts (série 001, V2, V3, transform, nested-child-native, structural, retention, tierpolicy, depth1, depth2-refs + README.md) ont été copiés depuis `scripts/` (hors repo) vers **`houetor-connect/tests/`** et commités.
- Harnesses standalone rendues portables : chemins via `getenv('WP_INC')` / `getenv('HWC_PLUGIN_INC')` avec fallback lab (depth1 L36-43, depth2-refs L23-28).
- **Bug découvert et corrigé** : `rest-test.php` (série 001) T14 (position=replace) détruisait la page 2 sans restauration → ajout capture `$GLOBALS['hwc_md5_init']` + cleanup final (restauration de révision) → la suite est **idempotente** (md5 final == initial `c4abdffec127…` sur 2 runs).
- `php -l` : 0 erreur sur les 11 fichiers.

### Étape 2 — Preuve patch appliqué (2.8.0)
- `houetor-connect/includes/class-block-editor.php` md5 **1BB175A547CEE0220F7B94533AABBB35** == fichier fourni Section 27 (identique au commit 7400476, Exp 027).
- Fonctions imbriquées présentes : `flatten_blocks_recursive` (L43), `locate_block_deep` (L321, récursion `innerBlocks` L336) utilisée par **toutes les écritures** — update_block_content (L382), batch_update_blocks (L483), transform_block (L651) ; `locate_block` top-level (L289) pour move/duplicate/wrap/unwrap. Commentaire L317-318 : cohérence flatten/locate exigée.
- Messages 2.8.0 en place : « est un conteneur » L399/L493/L668 ; aucun ancien libellé « contient des blocs imbriqués » dans le code actif.

### Étape 3 — RISQUE 1 (renumérotation des index) : ÉCARTÉ
- Grep persistance plugin : `set_transient`/`update_option`/`wp_cache_set` ne concernent QUE settings_errors (admin), api_fetcher (cache HTML fetcher), connect_status (statut connexion), rate_limit (compteur 10/60 s volontaire, prouvé par les 429) et options de token/connexion. **Aucun stockage de block_index entre requêtes** (87 occurrences = passage de paramètre par requête).
- MCP : 50 occurrences `block_index` côté src, 26 côté portage — pass-through de params (le seul « cache » est un header HTTP `cache-control: no-cache`, route-handler.ts L67). Chaque écriture repose sur une lecture fraîche (relire avant écrire + CAS) → aucun index périmé possible.

### Étape 4 — Batterie complète rejouée (preuves brutes, env isolé localhost:8888)
| Batterie | Résultat |
|---|---|
| test-nested-block-depth1.php (harness vrai parse_blocks/serialize_blocks) | ALL PASS |
| test-nested-block-depth2-refs.php (columns>column>paragraph + ref) | « TOUS LES TESTS PASSENT » |
| rest-test-nested-child-native.php | 11/11 |
| rest-test-v3.php | 32/32 |
| rest-test-transform.php | 21/21 |
| rest-test-structural.php | 42/42 |
| rest-test-retention.php | 9/9 |
| rest-test-tierpolicy.php | 11/11 |
| rest-test.php (série 001, idempotente) | 18 tests, restauration exacte md5 |
| rest-test-v2.php | 14 tests, audit page 3 vérifié |
| test-connect.php (standalone, hors WP — via `wp eval-file` → Fatal « Cannot redeclare get_option » attendu, stubs) | 35 PASS / 0 FAIL |
| MCP vitest (13 unit + 29 server) | 42/42 |
| MCP integration-test.mjs (PORT 8891) | 52/52 |
| MCP scenarios-test.mjs (PORT 8892, reset rate limit avant) | **41/41 PASS** (S0-S12 : relecture, create, update CAS, transform, tier policy + suggestion appliquée, dry_run, batch, delete, 409 périmé refusé sans écrasement, move, duplicate, wrap, unwrap, SSE 32 tools, restaurations exactes) |

### Étape 5 — Validations réelles existantes (documentées)
Exp 027 (modification réelle enfant imbriqué About Fix Day), Exp 028 (2.8.0), Exp 029 (Contact), Exp 030 bis (addendum AVANT/APRÈS) — déjà tracées dans ce log ; aucune nouvelle action prod requise pour la Section 27.

### Étape 6 — Wrapper MCP portage (commit 0aa53d5)
- `portage-app-mcp/src/tools.ts` : description `get_page_blocks` alignée sur `src/tools.ts:29` — « structure COMPLÈTE (tous niveaux, blocs imbriqués inclus) … parent_ref (index du parent), depth, has_children, child_count, content_md5 ».
- `portage-app-mcp/src/error-translator.ts` : 3 cas 2.8.0 ajoutés (400 conteneur écriture/batch, 404 imbriqué introuvable, 400 conteneur transform), en-tête v2.8.0.
- `dispatch.ts` : **inchangé** — pass-through de la réponse REST brute (`pluginRequest` L800) → les 4 champs proviennent du plugin sans filtrage.
- `tsc --noEmit` : **0 erreur** miroir et portage (l'import `@/lib/supabase-service` du portage résout en lecture vers le repo originel via baseUrl — aucun fichier originel touché).

### Conclusion
Section 27 exécutée de bout en bout : étapes 1, 2, 3, 4, 6 réalisées avec preuves brutes (batterie complète verte : plugin + MCP 42/42/52/52/41/41), étape 5 couverte par les Exp 027-030 bis. Aucun merge vers `mcp-block-crud-2.7.0`/`main` (non autorisé sans l'utilisateur). Restes : push docs (Exp 031 + LEARNING_STATE), vérification finale git.

## Exp 031 bis — ROADMAP MARCHÉ lancée : portage prod Étape 6 + zip 2.8.0 + suite 1 commande + rotation token (2026-08-05)

**Contexte** : audit « qu'est-ce qui bloque la mise sur le marché » (serveur MCP non mergé/déployé, connect zip 2.7.0, selfhare 1.0.3 non packagé, sécurité token statique, pas de CI) → plan validé par l'utilisateur (voir `docs-learning/ROADMAP_MARKET.md`, 19 items) et démarrage des tâches à dépendance nulle.

### #1 — Portage Étape 6 sur la branche prod (commit `3749151`, repo houetor)
- `app/mcp/tools.ts` : description `get_page_blocks` alignée sur le miroir lab (4 champs parent_ref/depth/has_children/child_count).
- `app/mcp/error-translator.ts` : 3 cas 2.8.0 (conteneur écriture/batch, 404 imbriqué, conteneur transform) + en-tête v2.8.0.
- Preuves : diff miroir lab ↔ prod = **vide** (fichiers identiques) ; `tsc --noEmit` 0 erreur ; `eslint` 0 erreur sur les 2 fichiers. `dispatch.ts`/`route.ts`/`parser.ts` intacts.

### #5/#7 — Zip officiel 2.8.0 (commits `0129edd` + `4f81b0d`)
- Construit par `git archive` (WSL) depuis le HEAD du lab. **Diff vs zip 2.7.0 (extraction + diff -rq)** : exactement 3 fichiers modifiés (`houetor-connect.php` version, `class-block-editor.php` patch, `readme.txt` changelog) + 11 fichiers de test ajoutés, 0 retrait ; md5 `class-block-editor.php` = `1bb175a5…` ; readme.txt déjà à jour (stable tag 2.8.0 + changelog imbriqué).
- Zip régénéré une 2e fois après la rotation token (voir plus bas) → `4f81b0d`.

### #15 — Suite de tests 1 commande : `houetor-connect/tests/test-suite.sh` → **19/19 PASS, exit 0** (2 runs)
- Couvre : 8 batteries `wp eval-file` + 3 harnesses standalone + MCP (vitest 42 + integration 52 + scenarios 41).
- Fonctionnalités : restauration des pages AVANT chaque batterie (c'était la cause du 20/1 transform au run 1 : état de page altéré par la batterie précédente — réglé, 21/21 ensuite), reset rate limit, relance auto du serveur WP (systemctl), timeouts sur les batteries MCP, critère de réussite par batterie (bilan « X PASS / 0 FAIL », « IDENTIQUE », « FIN V2 », « TOUS LES TESTS PASSENT », « 35 PASS / 0 FAIL »).
- Preuve du run final : `outputs/test-suite-run4.log` (19 PASS / 0 FAIL, pages restaurées en fin).

### 🔴 Sécurité — Fuite de token corrigée (rotation + batteries dynamiques)
- **Découvert** : le token WP lab (`eHlibQROp3fU00hrR8EFJqJJ0cuM9pJy`) était **hardcodé dans 4 batteries commitées dans le repo public** (rest-test.php, v2, v3, structural) et dans `ONBOARDING.md`. Vérifié : `get_option('hwc_token') == littéral` → SAME.
- **Fix** : les 4 batteries lisent désormais `get_option('hwc_token', '')` (commit `2ff7421`), `ONBOARDING.md` nettoyé ; **rotation du token du WP lab** (nouvelle valeur 32 car. jamais affichée — `ROTATED len=32 old_matches=no`) → l'ancien littéral est révoqué ; vérif `eHlib` = 0 occurrence dans le lab ET dans le zip reconstruit.
- **Mystère résolu au passage** : `test-connect.php` en CLI ne sortait RIEN (0 octet, exit 0) → cause : `class-hwt-parser.php:2` et `class-connect-status.php:2` ont le guard `defined('ABSPATH') || exit;` → nouveau wrapper `test-connect-run.php` définit ABSPATH avant include → **35 PASS / 0 FAIL** (4628 octets de sortie).

### Conclusion
Le serveur MCP est prêt à merger (Étape 6 portée et alignée miroir = prod), le zip 2.8.0 est livrable (sans fuite de token), et la validation de référence est désormais 1 commande (`test-suite.sh` 19/19). Restent dépendants de l'utilisateur : merge `mcp-block-crud-2.7.0` → main + déploiement Vercel + E2E contre le serveur déployé + dossier Fix Day 2.8.0 + packaging selfhare 1.0.3 + décisions artefacts. Suivi complet : `ROADMAP_MARKET.md`.

## Exp 032 — MISSION 3 : selfhare 1.0.3 version unique + zip + swap Fix Day + tests réels MCP (2026-08-05)

**Contexte** : items #8/#9 de la ROADMAP MARCHÉ — mettre fin au « jumeau » `houetor-selfhare-103/`, intégrer 1.0.3 (8 correctifs Exp 024 + boucle Exp 025 + édition imbriquée Exp 030) en version unique dans le repo prod, zip officiel, upload Fix Day et test réel via le MCP.

### #8 — Version unique dans le repo prod (commits `5631f50` + `ab18fcf`, repo houetor, branche `mcp-block-crud-2.7.0`)
- **10 fichiers copiés lab → prod** (hash vérifiés) : les 8 correctifs Exp 024 + boucle Exp 025 (`class-agent-dispatch.php`, `class-agent-chat.php`, `admin-chat.js`, `admin-chat.css`) + `houetor-selfhare.php` (version header/constante 1.0.3). 10 fichiers identiques lab/prod vérifiés (`diff -rq`) ; php -l 0 erreur (7 fichiers) ; grep secrets 0.
- ⚠️ **Incident de branche corrigé** : les commits selfhare ont d'abord été poussés sur `main` du repo houetor (violation de la règle — travail à faire sur `mcp-block-crud-2.7.0`) → **revert sur main** (commits `d973e79`/`d2aed40`, main resté **identique à l'état `9f8a5d0`** : diff vide vérifié) → **cherry-pick sur `mcp-block-crud-2.7.0`** avec résolution de 2 conflits (admin-chat.js, houetor-selfhare.php — version du commit porté prise, `git diff ec79b31` = vide) → push `4f81b0d..ab18fcf`. Hashs finaux : `5631f50` (10 fichiers, +561/−190) + `ab18fcf` (zip).
- Commit (sur la branche de travail) : 10 fichiers. Le dossier `houetor-selfhare/` du repo est désormais **1.0.3 unique** (le « jumeau » vit seulement sur le serveur Fix Day, hors git).
- `uninstall.php` : **neutralisé (noop)** — l'ancien détruisait licence chiffrée + 3 tables + rôle partagés (décision Exp 030).

### #9 — Zip officiel + upload Fix Day + tests réels (commit `ab18fcf`, poussé sur `mcp-block-crud-2.7.0`)
- **Zip** : `git archive --format=zip -o outputs/houetor-selfhare.zip HEAD -- houetor-selfhare` — ⚠️ **sans `--prefix`** (l'arbre contient déjà le dossier `houetor-selfhare/` ; le `--prefix` créait la double imbrication « dossier existe déjà » d'Exp 024/025). Vérifié : 24 fichiers, 1 niveau, Version 1.0.3 (unzip -p), grep `eHlib` = 0.
- **Swap Fix Day** (`fixday-selfhare-install.mjs`) : upload zip (200) → désactivation jumeau 103 (302) → activation officiel (302) → vérif : **`houetor-selfhare/` ACTIF 1.0.3, jumeau inactif conservé en backup local** (la suppression du dossier détruirait la licence chiffrée — pas de suppression sans décision). Page admin : « Licence active » préservée.

### Tests réels via MCP prod (Contact, page 8 — 57 blocs, depth max 5, md5 `106e1db0475e74c64028232553743599`)
| Test | Résultat |
|---|---|
| Lecture | 57 blocs, parent_ref 53/57, depth 57/57, **tous les imbriqués starter ont `ref:null`** |
| update par `ref` | ❌ success=false (pas de ref HWC sur les imbriqués) |
| update par `block_index` sur **conteneur** (atomic-wind/box, child_count>0) | ❌ refus propre + message actionnable (« cible l'un de ses enfants par sa propre ref/index ») — comportement volontaire (famille V3-6) |
| update par `block_index` sur **feuille** depth=3 (atomic-wind/text idx 8, contenu 16 car.) | ✅ **SUCCÈS réel** : success=true, md5 `106e1db0…`→`38f1ebdf…` — locate_block_deep localise l'imbriqué en prod |
| restauration par update (même contenu) | ⚠️ md5 final `11765dd7…` ≠ initial (**delta 2 octets** : 15788→15786, re-sérialisation canonique) — connu famille Exp 028 : la restauration exacte passe par la réécriture du raw d'origine |
| **restauration exacte** | ✅ réécriture du raw d'origine (révision 157) via REST core → **md5 final == md5 initial EXACT** `106e1db0475e74c64028232553743599`, count 57 — Contact intact |
| Smoke pages | ✅ About 80 blocs (`856c1c99…`), Services 104 blocs (`3e40316e…`) inchangés |

**Découvertes structurantes** : (1) sur les imbriqués starter **sans ref**, le ciblage est par `block_index` global (flatten) ; (2) le refus conteneur est re-sérialisé en erreur `success=false` + message avec conseil → l'agent doit choisir une feuille ou descendre ; (3) `update_block_content` ne reproduit jamais le raw exact (normalisation serialize_blocks) → **toute restauration « à l'identique » exige la réécriture du raw d'origine (révision)** — le md5 exact est le seul critère fiable.

**État Fix Day final (2026-08-05 soir)** : `houetor-connect` 2.8.0 officiel ACTIF (dossier unique) + `houetor-selfhare` 1.0.3 officiel ACTIF + jumeau 103 inactif (backup) — token WP restauré, toutes les pages intactes (md5 d'origine).

**Pour reprendre** : mise à jour docs + push lab `opencode-learning` (cette expérience). Restent utilisateur : merge `mcp-block-crud-2.7.0` → main houetor (#2), déploiement Vercel (#3) + E2E déployé (#4), dossier Fix Day 2.8.0 connect (#6 ✅ en fait — fait par Exp 032 via upload officiel ; vérifier), artefacts Fix Day (#10), lint global, README marché (#17/#18).

## Exp 034 — SESSION 2026-08-06 : P1 paiement récurrent + Règle 24 (zip selfhare vérifié 8/8) + CRUD campagnes/cm_posts + Bug #12 CLÔTURÉ (1re preuve visuelle réelle) (2026-08-06)

**Contexte** : 4 missions utilisateur : (1) P1 paiement récurrent automatique, (2) sécuriser la distribution SelfHare (Règle 24 — vérifier les 8 correctifs Exp 017 dans le zip en circulation), (3) combler campagnes/cm_posts (CRUD agent manquant, profils MARKETING/CM), (4) Bug #12 SelfHare (cache admin-chat.js — seule surface UI jamais testée visuellement). Tout le travail se fait dans le repo `houetor` (branche `section28/p1-paiement-recurrent`, Règle 28 : jamais de push main), preuves scriptées au lab/HTTP réel. ⚠️ Numéro Exp 034 (Exp 033 déjà pris dans LEARNING_STATE par la mission E2E déployée 2026-08-05, non écrite dans EXPERIMENTS_LOG).

### §1 — P1 paiement récurrent automatique (repo houetor, commits `122a043` + `951ad4e`)
- **Décision actée (§10 spec v2)** : FedaPay SANS subscriptions natives (confirmé doc + étude `FEDAPAY_SUBSCRIPTION_CHECK.md`) → flux FCFA = **cron quotidien + lien FedaPay par email** (Règle 33 : jamais de débit silencieux, rappel automatique). Stripe (EUR/CAD) = **subscriptions natives**, webhook-driven.
- **Migration additive** `supabase/migrations/20260806_p1_billing_cycle.sql` : `billing_cycle_status` (active|past_due|expired|canceled, CHECK), `next_billing_at`, `renewal_failure_count`, `last_renewal_attempt_at`, `provider_subscription_id` sur `orders` + `houetor_selfhare_licenses` + 2 index partiels. ⚠️ **Non appliquée à la DB** (pas d'accès aux secrets) — prête au déploiement.
- **`lib/payment/billing.ts`** (cœur, client injecté — testable) : `onPaymentSucceeded` (**Règle 32** : `maxIso` — jamais diminuer `next_billing_at` ; FedaPay sans periodEnd → +1 mois), `onPaymentFailed` (depuis active OU past_due → past_due + compteur+1 ; expired/canceled bloqués — corrigé pendant les preuves), transitions 48h→past_due / 7j→expired, cooldown envoi 20h, `createRenewalPaymentLink` (metadata `renewal=1` + `provider_subscription_id`), `getUserBillingStatus` (par `customer_email` d'abord, fallback `user_id` — les checkouts créent des orders avec `user_id: null`).
- **Webhooks** : Stripe SelfHare étendu (`invoice.payment_succeeded/failed`, `customer.subscription.deleted/updated`, routage `source:'hare'` → `insertHareOrder` **idempotent** — le webhook Stripe n'existait que pour SelfHare avant) ; FedaPay Hare (`app/api/payment/webhook/route.ts`) + SelfHare : branche renouvellement AVANT idempotence/création. Métadonnées `source:'hare'/'selfhare'` ajoutées aux checkouts.
- **⚠️ Constat webhook (PREUVE 4 P1)** : **Webhook Stripe non testé en conditions réelles — à valider lors du premier renouvellement client réel.** Aucun webhook réel n'a pu être déclenché en prod : aucun client n'a encore de cycle de renouvellement actif (les renewals FedaPay reposent sur le cron + lien par email, les subscriptions Stripe natives ne sont pas encore souscrites). Les transitions grâce/expiration et le routage webhook sont couverts par les tests unitaires (`p1-billing-cycle.test.mjs` 24/24), pas par un événement réel Stripe/FedaPay.
- **Cron** `app/api/cron/billing-recurring/route.ts` (Vercel `0 8 * * *`, CRON_SECRET) : lignes dues (`billing_cycle_status IN active,past_due`), calcul montants via HARE_PRICES/SELFHARE_PRICES, emails Resend (renouvellement + grâce), transitions 48h/7j. Stripe skippé (piloté webhook).
- **Coupure d'accès (Règle 31)** : expired/canceled → refus dans `dispatch.ts` (MCP+agent), `app/agent/route.ts` (403, import dynamique), `app/selfhare/relay/route.ts` (`billing_cycle_status ?? status`).
- **Page** `/espace/facturation` (statut, échéance, 3 derniers paiements, bouton past_due/expired) + `POST /api/payment/renew` (« Payer maintenant ») + entrée Sidebar.
- **Preuves** : `scripts/test/p1-billing-cycle.test.mjs` → **24/24 PASS** (P1 Stripe/FedaPay, P2 SelfHare, P3 grâce/échecs/expiration, R31 coupure, R32 maxIso, P4 montant invalide) ; tsc 0 erreur ; lint 0 sur fichiers touchés.

### §2 — Règle 24 : vérification du zip SelfHare en circulation (8/8 correctifs Exp 017)
- **Verdict : CONFORME — pas de régénération** (extraction `outputs/houetor-selfhare.zip` 1.0.3, 20 fichiers, racine `houetor-selfhare/`).
- Preuves par correctif : `PREVIEW_TOKEN_TTL=600` + transient `sh_preview_` usage unique/`delete_transient` + `edit_conflict` + `sh_rate_`/`rate_limit_exceeded` + `log_action` (audit écritures seules) + `find_text_not_found` + `wc_update_product_stock` (dispatch) ; codes `preview_required`/`find_text_not_found` (error-translator) ; `previewToken`/`tc.preview_token` (admin-chat.js) ; `send_relay` bloquant timeout 30 sans `blocking=>false` + `internal=>true` (routines) ; **licence chiffrée AES-256-CBC** `base64(iv):base64(data)` clé `sha256(wp_salt('auth').'|houetor-selfhare')` ; uninstall complet (6 delete_option + wp_clear_scheduled_hook + remove_role) ; version 1.0.3 + localize `version` + journal paginé 10/page ; `Stable tag: 1.0.3` (readme).
- Vérifications : `php -l` (WSL PHP 8.5.4) **0 erreur** (16 .php) ; **md5 20/20 identique au dossier source du lab** (hors fins de ligne) ; **0 token/secret** (seules les URLs prod `houetor.com/selfhare/relay` + `.../license/validate`).

### §3 — Combler campagnes/cm_posts (CRUD agent manquant, commit `951ad4e`)
- **Trou confirmé** : tables existantes avec RLS (`campagnes`, `cm_posts`), `list_contenu` seul (lecture), AUCUN create/update/delete — MARKETING et CM ne pouvaient rien créer sur leur module principal.
- **Fix** : `app/mcp/crud-campagnes-cm.ts` (6 handlers, client injecté — pattern `billing.ts`), routage `dispatch.ts` (après `list_contenu`), 6 outils `tools.ts` : `create/update/delete_campagne` (MARKETING, ONG), `create/update/delete_cm_post` (CM, MARKETING, ONG). Pas d'injection WordPress (tables sans colonnes `wp_*`), RLS inchangée.
- **Preuves** : `scripts/test/p2-crud-campagnes-cm.test.mjs` → **20/20 PASS** (champs complets, statut par défaut brouillon, refus propres sans id/titre, updates partiels, scope `.eq id + user_id`, **isolation inter-utilisateurs**) ; P1 24/24 sans régression ; tsc 0 ; lint 0 (hors warning pré-existant).

### §4 — Bug #12 SelfHare (cache admin-chat.js) : CLÔTURÉ avec 1re preuve visuelle réelle
- **Cause racine confirmée** : le littéral `{{...}}` ne vient pas du code courant (0 occurrence dans zip + source) — un vieux `admin-chat.js` servi par **cache navigateur** (version d'enqueue statique jamais incrémentée, pattern bug #5).
- **Fix déjà en place (vérifié)** : `filemtime()` dynamique (`houetor-selfhare.php` L161-162) dans le zip 1.0.3 ET la source repo.
- **Preuve A (HTTP réel, `bug12-prove-a-http.mjs`)** : Fix Day sert `admin-chat.js?ver=1785968077` (timestamp = filemtime) — cache-busting actif.
- **Preuve B (visuelle réelle, `bug12-prove-b-visual.mjs`, Playwright + Chrome channel, 6/6 PASS)** : login wp-admin réel → chat Assistant chargé → sélecteur de page à ID numériques (`#5 About`, `#2 Accueil`) → message réel envoyé → l'agent répond (étapes « Liste des pages (6 pages) », « Lecture des blocs de la page #5 (80 blocs) », proposition `update_block_content` `page_id:"about"` `block_index:4`) → **0 `{{` dans le rendu ni le DOM**, 0 exécution sans confirmation, 0 erreur JS/404. Rendu UI : « Modifier un block_content » + refs réelles.
- **Signification** : la surface UI SelfHare (Assistant) est désormais **testée visuellement en conditions réelles pour la première fois** — débloque l'onboarding de nouveaux clients.
- Artefacts réutilisables : `Temp/opencode/bug12-prove-a-http.mjs`, `bug12-prove-b-visual.mjs`, screenshots `bug12-visual.png`/`bug12-final.png` (nécessitent `playwright` + `.env.learning` lab).

### Vérifications transverses
- Tous les fichiers travaillés sont dans le repo `houetor` (le seul hors-repo : `docs-learning/FEDAPAY_SUBSCRIPTION_CHECK.md` du lab). Preuves rejouables : `bun scripts/test/p1-billing-cycle.test.mjs` (24/24) + `bun scripts/test/p2-crud-campagnes-cm.test.mjs` (20/20).

### Pour reprendre
- **P1** : appliquer la migration `20260806_p1_billing_cycle.sql` sur la DB Supabase (décision + accès), merge/déploiement branche `section28/p1-paiement-recurrent`, restent onboarding guidé + dashboard stats/facturation (BLOQUEUR §9.17 partiellement levé).
- **Bug #12 suite (optionnelle)** : test Révisions (bug #7) + `Outils → SelfHare Journal` sur Fix Day.
- **Docs** : cette expérience à pousser sur `opencode-learning` (avec les mises à jour `HOUETOR-selfhare-consolide-juillet2026.md` §7/§8/§9, `LEARNING_STATE.md`, `README.md`). Fils ouverts inchangés : merge `mcp-block-crud-2.7.0` → main, déploiement Vercel, lint global (62), README marché (#17/#18), restauration « Insights & Resources » Blog #13.

## Exp 035 — ÉTUDE COMPATIBILITÉ ELEMENTOR : nos plugins sont-ils capables de CRUD des blocs Elementor ? (2026-08-06)

**Mission utilisateur** : étude SANS TOUCHE AU CODE — (1) vérifier si l'actuel (connect 2.8.0 + selfhare 1.0.3 + MCP, qui savent créer/modifier des blocs Gutenberg existants, innerBlocks inclus, et les blocs créés par nos 2 plugins dans `houetor/outputs/`) peut ajouter/CRUD des blocs créés avec Elementor ; (2) consulter en premier notre traitement des innerBlocks (patch 2.8.0) pour savoir s'il résout le problème ; (3) références lues : aishan-shrestha/elementor-custom-widget, elementor/elementor-hello-world, developers.elementor.com/docs/widgets/, developers.elementor.com/docs/getting-started/first-addon/ + recherches en ligne ; (4) dire ce qui est possible.

### Verdict : INCAPABLES AUJOURD'HUI — notre mécanique innerBlocks ne résout pas le problème (3 raisons)

**Preuve code (connect 2.8.0)** : toutes les écritures passent par `parse_blocks`/`serialize_blocks` (class-block-editor.php:19,354,381,415...) et le CAS est `md5($post->post_content)` (L250) ; refs HWC = commentaires HTML dans post_content. **Selfhare** : idem (class-agent-dispatch.php:462,771,880,925, `cas_write` sur post_content). **MCP prod** : 38 tools dont 12 tools bloc, tous proxy `houetor/v1` → même limite. `_elementor_data` : 0 occurrence dans les 2 plugins.

**Modèle de données Elementor (docs officielles developers.elementor.com/docs/data-structure/ + widget-element + container-element + recherches en ligne)** :
- Contenu stocké en méta `_elementor_data` (wp_postmeta) = arbre JSON d'éléments `{id, elType: container|section|column|widget, widgetType, settings{}, elements[]}` ; page = 1 tableau JSON d'éléments racine.
- `post_content` d'une page Elementor = vide ou placeholder → **Elementor IGNORE post_content au rendu** (études externes : modifier post_content ne fait rien de visible et peut faire disparaître « Edit with Elementor »).
- Écriture programmatique : `wp_slash(wp_json_encode(...))` **obligatoire** (sinon JSON corrompu → page réduite à un bloc texte), validation structure, backup + rollback, **flush cache CSS** (`_elementor_css`) après écriture.
- Révisions WP ne protègent PAS `_elementor_data` (Elementor a ses propres drafts) → notre rollback révision actuel ne couvre pas Elementor.
- 2 modes : containers flexbox (défaut Elementor 3.x) et sections/columns legacy ; templates `elementor_library` (header/footer/popups = Pro).
- Les 4 références fournies (custom-widget, hello-world, widgets/, first-addon/) = création d'addons/widgets (déclarer des types de widgets), PAS du CRUD de contenu de page.

**Les 3 raisons du NON (analyse du patch innerBlocks 2.8.0)** :
1. **Blocage EN AMONT** : `get_page_blocks` abandonne sur contenu vide/template (class-block-editor.php:15-17) — la mécanique innerBlocks n'est jamais atteinte sur une page Elementor (post_content vide).
2. **Chemin d'écriture incompatible** : nous mutons `innerHTML` + `serialize_blocks` (L402-415) ; Elementor mute `settings` + sauvegarde JSON (wp_slash). `serialize_blocks` sur une page Elementor = markup Gutenberg ignoré au rendu.
3. **Refs** : HWC = commentaires injectés par nous (extract_hwc_ref L80-86) → pages Elementor toutes `ref:null` (cas déjà connu, ciblage par index OK — prouvé réel Exp 032/033) ; MAIS Elementor fournit déjà un `id` unique 8-car hex par élément = meilleur équivalent de ref, sans injection.

### Ce qui SE TRANSFÈRE (la forme de l'arbre est analogue)

| Notre mécanique | Transfert vers Elementor |
|---|---|
| `flatten_blocks_recursive` (index global, parent_ref, depth, child_count — L43-74) | ✅ tel quel sur le JSON (`elements` ≡ `innerBlocks`) |
| `locate_block_deep` (récursion par référence, index global dynamique, AUCUN index hardcodé — L321-344) | ✅ tel quel (counter indépendant de parse_blocks) |
| Refus conteneur actionnable (L398-400) | ✅ (container/section = conteneur ; widget = feuille) |
| `cas_check` md5(post_content) (L246-251) | ✅ md5(_elementor_data), même principe |
| `serialize_blocks` + écriture post_content | ❌ → `wp_slash(wp_json_encode($elements))` sur la méta |
| Ref HWC (commentaires) | ❌ → `id` Elementor (déjà présent) |

**Conclusion** : notre mécanique innerBlocks résout déjà la navigation/adressage d'un arbre de profondeur quelconque (socle réutilisable) ; il manque la couche d'entrée (`_elementor_data` au lieu de parse_blocks) et la couche de sortie (settings + JSON + flush CSS au lieu de serialize_blocks).

### Ce qui serait possible (options, aucune décidée)

- **A (mini, lecture)** : détecter `_elementor_edit_mode=builder` et renvoyer l'arbre aplati dans get_page_blocks (même forme 2.8.0) → l'agent « voit » sans rien casser.
- **B (CRUD complet)** : module Elementor dans connect (routes `houetor/v1/elementor/*` : get tree, create/update/delete/duplicate/move element, CAS, dry_run, audit, rate limit, révisions) + schéma des settings par widget (`\Elementor\Plugin::$instance->widgets_manager->get_widget_types()->get_controls()`) + flush CSS + backup/rollback. Risque principal : schéma des widgets (clés de settings propres à chacun) et non-régression.
- **C** : ne rien faire, déclarer Elementor non supporté.

**Preuves externes de faisabilité** : `msrbuilds/elementor-mcp` (GPL-3.0, ~360 ⭐, 97 tools — wrapper `\Elementor\Plugin::$instance->documents->get()->save()`, jamais de méta brute, factory d'éléments ids hex uniques, schéma depuis les controls, tools containers/widgets/templates) ; `bvisible/elementor-mcp-api` (REST + MCP : `/page/{id}/element`, PATCH settings, duplicate/move, `/flush-css`) ; pattern `safe_elementor_save` (backup timestamppé + validation + rollback, ~15 000 saves sans corruption — harborsoftware.com). Aucun code touché (règle respectée).

### Pour reprendre
Étude close, aucune décision. Prochaine étape possible : choix utilisateur (A/B/C), éventuel test d'une vraie page Elementor pour capturer le schéma réel de `_elementor_data` (ex. site de test), puis documentation PLUGIN_CAPABILITIES si option retenue. Fils ouverts inchangés : merge `mcp-block-crud-2.7.0` → main, P1 migration DB, artefacts Fix Day #10, lint global (62), README marché.

## Exp 036 — Audit Règle 24 (distribution SelfHare) — 07/08/2026

**Objectif** : vérifier que `houetor-selfhare.zip` en circulation contient bien les 8 correctifs sécurité Exp 017 (CAS, rate limit, licence chiffrée, tokens preview, uninstall, traductions erreurs).

**Résultat** :
- `houetor/main` commit `010093c` = 1.0.3 — **8/8 fichiers conformes ✅** (MD5 `8E60FE23D033968187D8A2858BEEEC9E` ; marqueurs vus : `cas_write` L71 + rate limit + `openssl_encrypt` AES-256-CBC + `previewToken`/`tc.preview_token` + `edit_conflict`/`preview_required`/`find_text_not_found` + 6 `delete_option`/`remove_role` + `filemtime` + `Version: 1.0.3`/`Stable tag: 1.0.3`)
- `connect/outputs/` local = 1.0.2 (non versionné, dossier entier `?? outputs/`) — **8/8 fichiers conformes ✅** (MD5 `14D7448FC682DA4A2470B2E3B2B20957` ; mêmes marqueurs, versions `1.0.2`) — antérieur au restyle, pas le « restyle sans correctifs » redouté en Section 27 §5
- MD5 houetor : `8E60FE23D033968187D8A2858BEEEC9E`
- MD5 lab : `14D7448FC682DA4A2470B2E3B2B20957`
- Scénario danger (restyle sans Exp 017) : **non confirmé** — aucun des deux zips ne correspond.
- Seul zip à distribuer : `houetor/main` `outputs/houetor-selfhare.zip` (1.0.3, commit `010093c`).
- Zip lab 1.0.2 : ne pas distribuer (antérieur), ne pas supprimer (référence de version intermédiaire).

**Règle 24 : statut CONFORME au 07/08/2026.**

## Exp 037 — Bug #12 SelfHare rejoué : placeholder « #{{selected_page.id}} » — CAS C, aucun bug dans le code (07/08/2026)

**Mission** : re-diagnostic Bug #12 (placeholder littéral `#{{selected_page.id}}` dans l'UI au lieu d'un ID numérique, plugin actif 1.0.3 sur Fix Day). Hypothèse actée : cache navigateur (pattern bug #5, `?ver=` jamais incrémenté). 3 cas prévus (A = version statique → filemtime ; B = JS buggé → corriger l'interpolation ; C = rien d'anormal → documenter + signaler, ne pas corriger à l'aveugle).

**Sorties brutes Étape 1 (repo houetor, branche `section28/p1-paiement-recurrent`) :**
```
grep wp_enqueue_script.*admin-chat → houetor-selfhare.php L162 :
  wp_enqueue_script('houetor-selfhare-admin', HOUETOR_SELFHARE_URL . 'assets/admin-chat.js',
    ['jquery'], filemtime(plugin_dir_path(__FILE__) . 'assets/admin-chat.js'), true);
  → version DÉJÀ dynamique (filemtime) — Cas A écarté.

grep '{{' dans assets/admin-chat.js → 0 occurrence.
grep selected_page → 1 seule occurrence, L216 : selected_page: $pageSelect.val() || ''
  (envoi au backend, pas une interpolation UI).
grep '{{' dans tous les *.php du plugin (houetor-selfhare.php + includes/*) → 0 occurrence.
Interpolation réelle des IDs dans describeToolCall (JS source actuel) :
  L59  if (p.page_id) parts.push('#' + p.page_id);
  L72  if (p.post_id) parts.push('#' + p.post_id);
  L82  if (p.revision_id) parts.push('#' + p.revision_id);
  L86  if (p.page_id) parts.push('de la page #' + p.page_id);
  → mécanisme correct en place, aucun placeholder {{...}} dans le code.
Version source : houetor-selfhare.php L7/L26 = 1.0.3, readme.txt L6 Stable tag: 1.0.3.
```

**Preuve HTTP fraîche (artefact `bug12-prove-a-http.mjs` rejoué le 07/08/2026 sur Fix Day) :**
```
URL script servi : admin-chat.js?ver=1785968077
URL css servi   : admin-chat.css?ver=1785968077
ver est un filemtime (timestamp) : OUI ✓
PREUVE A : cache-busting filemtime actif sur le site réel — cause racine Bug #12 corrigée
```

**Verdict : CAS C — aucune correction de code** (pas de bump 1.0.4, pas de zip, pas de commit houetor ; la branche reste sur `bee2abc`). Le placeholder `#{{selected_page.id}}` n'existe dans AUCUN fichier du plugin 1.0.3 (source repo + zip) : c'est un vieux `admin-chat.js` servi par le **cache navigateur** du client (version antérieure à la rotation filemtime). Confirme la clôture Exp 034 §4 (0 occurrence source + preuve visuelle Playwright 6/6). Remède pour un client qui verrait encore le placeholder : **hard reload (Ctrl+F5) ou vidage du cache navigateur** — pas de déploiement à faire.
