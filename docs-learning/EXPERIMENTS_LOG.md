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
