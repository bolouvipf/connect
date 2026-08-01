# @houetor/connect-mcp — Miroir lab du serveur MCP HOUETOR

## Objectif

**Faire en sorte que toute action CRUD qu'un utilisateur demande à l'IA s'exécute sans erreur.**
L'utilisateur parle en langage naturel ; l'agent traduit la demande en un workflow sûr —
**relire avant d'écrire** (`get_page_blocks` → `content_md5` → `expected_hash` CAS), **`dry_run`**
pour la répétition générale, **batch atomique `update_blocks`** pour les demandes multiples,
et **relecture de confirmation** après écriture. Les erreurs restantes (409/429/404/401) sont
traduites en conseils actionnables pour que l'agent reparte du bon pied au lieu de bloquer.
La preuve de ce contrat : les scénarios utilisateur « exaucés exactement » (26/26 PASS, Phase 3).

Serveur MCP (Model Context Protocol) qui pilote le plugin WordPress **houetor-connect**
(v2.5.0 : CRUD de blocs avec refs HWC, CAS `expected_hash`, rate limit, audit log avec
rétention configurable, révisions, batch atomique `update_blocks`, `dry_run`,
transformation de blocs `transform_block`).

Il reproduit **à l'identique** le protocole du serveur MCP de production
(`app/mcp/` du projet Next HOUETOR : JSON-RPC 2.0 en HTTP + listing SSE, auth `X-HWT-Token`)
afin que les tools développés ici soient **portables 1:1 dans `app/mcp/`**.

## Protocole

| Élément | Valeur |
|---|---|
| Endpoint | `POST /mcp` — appels JSON-RPC 2.0 |
| Listing | `GET /mcp` — SSE, un événement `data:` listant les tools du profil |
| Auth | Header `X-HWT-Token` : `HWT-{ONG\|BOUTIQUE\|COACH\|CM\|MARKETING}-{uuid}` ou UUID nu |
| Erreurs JSON-RPC | `-32000` auth (401), `-32601` méthode inconnue (404), `-32600` requête invalide (400), `-32603` interne |
| Erreurs plugin | HTTP 409 (CAS), 429 (rate limit), 404 (ancre/bloc), 401 (jeton) → transmises en JSON-RPC `-32002` avec `data.status` + `data.code` et un message actionnable pour l'agent |

## Tools

WP + blocs (tous profils) :

- `get_wp_pages` — liste des pages
- `get_wp_menus` — liste des menus
- `get_page_blocks` — structure de blocs d'une page (`blockName`, `content`, `ref`, `content_md5`)
- `inject_page` — injection HTML (position, module, CAS)
- `uninject_page` — retrait par module + block_id
- `create_block` — création (position start/end/before/after, `anchor_ref`/`anchor_index`, module → ref, `dry_run`)
- `update_block_content` — modification par `ref` (prioritaire) ou `block_index`, CAS, `dry_run`
- `update_blocks` — **batch atomique** : plusieurs updates (par `ref` ou `block_index`) en UNE révision, all-or-nothing, max 50 par appel, compte 1 écriture rate limit, `dry_run`
- `delete_block` — suppression par `ref` ou `block_index`, CAS, `dry_run`
- `transform_block` — conversion d'un bloc de texte vers un autre type texte (paragraph/heading/quote/list/code/preformatted/pullquote), `ref` HWC conservée, CAS, `dry_run` (ex: « transforme ce paragraphe en titre »)
- `export_to_wordpress` — injection complète (module obligatoire)
- `list_connected_sites` — site configuré par env (équivalent lab de la table Supabase)

`dry_run: true` (sur toutes les écritures) valide sans rien écrire : aucune écriture, aucune
révision, aucun audit, aucun rate limit consommé — parfait pour une « répétition générale »
avant publication.

Les messages d'erreur 409/429/404/401 sont traduits en conseils concrets
(« relisez la page via `get_page_blocks` pour un `expected_hash` frais », « attendez ~60 s »…).

## Configuration

```bash
export WORDPRESS_URL=http://localhost:8888
export HOUETOR_TOKEN=<hwc_token du site>
export PORT=8890            # défaut
```

## Démarrage

```bash
npm install
npm run build
npm start                   # ou WORDPRESS_URL=... HOUETOR_TOKEN=... npm start
```

## Tests

```bash
npm test                    # 29 tests unitaires (Vitest, fetch mocké)
npm run test:integration    # 33 tests vs le WordPress lab (À EXÉCUTER DANS WSL, où :8888 est joignable)
node scripts/scenarios-test.mjs   # 26 scénarios utilisateur « exaucés exactement » via le MCP (Phase 3)
```

Prérequis scénarios : `wp option delete _transient_hwc_ratelimit_2` avant le run (budget
10 écritures/60s par page), puis restauration de la page via révision en post-run
(`scripts/restore-page2.php`).

## Exemple d'appel

```bash
curl -X POST http://localhost:8890/mcp \
  -H 'Content-Type: application/json' \
  -H 'X-HWT-Token: HWT-ONG-123e4567-e89b-12d3-a456-426614174000' \
  -d '{"jsonrpc":"2.0","id":1,"method":"get_page_blocks","params":{"page_id":"2"}}'
```

## Exemples de scénarios agent (testés en Phase 3)

Le pattern gagnant : **toujours relire avant d'écrire** (`get_page_blocks` fournit `content_md5`),
passer ce hash en `expected_hash`, puis **relire pour confirmer**.

1. « Ajoute un bloc avant le pied de page » → `create_block` (`position:before`, `anchor_index`).
2. « Corrige un texte » → `get_page_blocks` → `update_block_content` (`ref` ou `block_index` + `expected_hash`).
3. « Répétition générale avant de publier » → `dry_run:true`, vérifier `dry_run:true` + md5 inchangé, puis rejouer sans dry_run.
4. « Fais ces N corrections en une fois » → `update_blocks` (array d'updates, 1 révision, all-or-nothing).
5. « Supprime l'ancienne offre » → `delete_block` par `ref`.
6. « Conflit : un autre agent a modifié la page » → le 409 traduit dit de relire ; relire, repasser le hash frais, réécrire.
7. « Transforme ce bloc en titre » → `transform_block` (`target_block_name: core/heading`, `ref` conservée).

## Portage vers la prod (`app/mcp/`)

1. Copier les fichiers du dossier `src/` dans `app/mcp/` :
   - `route-handler.ts` → adapter à `NextRequest`/`NextResponse` (la logique de `handleRequest` est inchangée)
   - `tools.ts` → fusionner avec les tools existants (garder les profils de prod)
   - `dispatch.ts` → brancher les méthodes bloc sur Supabase `connected_sites` pour obtenir le token plugin
2. Ajouter les nouveaux tools aux clients d'agent (nommage `get_page_blocks`, `create_block`… déjà aligné sur les endpoints plugin).
3. La traduction d'erreurs (`error-translator.ts`) est réutilisable telle quelle.

## Versions

- 2.3.0 : miroir protocole + tools bloc v2.3.0 (en lockstep avec le plugin `houetor-connect`).
- 2.4.0 : batch atomique `update_blocks` + `dry_run` sur toutes les écritures (plugin et MCP en lockstep) ; mapping `inject_page` aligné sur la prod (`html` → `content`) ; scénarios utilisateur Phase 3 (24/24 PASS).
- 2.5.0 : `transform_block` (conversion entre blocs de texte, ref conservée) + rétention du journal d'audit (option `hwc_audit_retention_days`, CRON quotidien) ; unitaires 29/29, intégration 33/33, scénarios 26/26.
