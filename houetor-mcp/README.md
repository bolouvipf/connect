# @houetor/connect-mcp — Miroir lab du serveur MCP HOUETOR

Serveur MCP (Model Context Protocol) qui pilote le plugin WordPress **houetor-connect**
(v2.3.0 : CRUD de blocs avec refs HWC, CAS `expected_hash`, rate limit, audit log, révisions).

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
- `create_block` — création (position start/end/before/after, `anchor_ref`/`anchor_index`, module → ref)
- `update_block_content` — modification par `ref` (prioritaire) ou `block_index`, CAS
- `delete_block` — suppression par `ref` ou `block_index`, CAS
- `export_to_wordpress` — injection complète (module obligatoire)
- `list_connected_sites` — site configuré par env (équivalent lab de la table Supabase)

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
npm test                    # 18 tests unitaires (Vitest, fetch mocké)
npm run test:integration    # 16 tests vs le WordPress lab (À EXÉCUTER DANS WSL, où :8888 est joignable)
```

## Exemple d'appel

```bash
curl -X POST http://localhost:8890/mcp \
  -H 'Content-Type: application/json' \
  -H 'X-HWT-Token: HWT-ONG-123e4567-e89b-12d3-a456-426614174000' \
  -d '{"jsonrpc":"2.0","id":1,"method":"get_page_blocks","params":{"page_id":"2"}}'
```

## Portage vers la prod (`app/mcp/`)

1. Copier les fichiers du dossier `src/` dans `app/mcp/` :
   - `route-handler.ts` → adapter à `NextRequest`/`NextResponse` (la logique de `handleRequest` est inchangée)
   - `tools.ts` → fusionner avec les tools existants (garder les profils de prod)
   - `dispatch.ts` → brancher les méthodes bloc sur Supabase `connected_sites` pour obtenir le token plugin
2. Ajouter les nouveaux tools aux clients d'agent (nommage `get_page_blocks`, `create_block`… déjà aligné sur les endpoints plugin).
3. La traduction d'erreurs (`error-translator.ts`) est réutilisable telle quelle.

## Versions

- 2.3.0 : miroir protocole + tools bloc v2.3.0 (en lockstep avec le plugin `houetor-connect`).
- 2.4.0 (à venir) : batch atomique `update_blocks` + `dry_run` côté plugin, exposés ici.
