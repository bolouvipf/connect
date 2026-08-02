# Portage `app/mcp/` — CRUD bloc v2.7.0 (Phase 4 mission)

Ce dossier contient le **portage prêt à déployer** des tools CRUD bloc dans le serveur MCP
de production (`Pictures\Screenshots\houetor\app\mcp\`). Tout le travail est fait **dans le lab** ;
rien du repo de production n'a été modifié.

## Contenu

| Fichier | Rôle |
|---|---|
| `original/` | Copie exacte des 4 fichiers prod (`route.ts`, `parser.ts`, `tools.ts`, `dispatch.ts`) — référence brute au moment du portage |
| `src/` | Les 4 fichiers **tels qu'ils doivent être en prod** + `error-translator.ts` (nouveau) |
| `tsconfig.json` | Typecheck local : résout `@/*` vers le repo prod et `node_modules` via junction → vérifie l'intégration réelle |
| `node_modules/` | Junction (non commitée) vers `node_modules` du repo prod — résout les types Next/Supabase |

## Ce que le portage ajoute (vs prod actuelle)

1. **`error-translator.ts`** (nouveau, identique au miroir 2.7.0) — 401/403, 409 `error_conflict`, 429 `rate_limited`, 404 `anchor_not_found`/`block_not_found`, **400 `block_legacy`** (tier policy : conseil de remplacement du bloc), **400 `wrap_failed`** (plage inversée : conseil index croissants), **400 `unwrap_failed`** (non-groupe : conseil core/group) traduits en **conseils actionnables** pour l'agent.
2. **`tools.ts`** — 11 nouveaux tools (profils tous clients) :
   - `get_page_blocks` (blockName, content, ref HWC, content_md5)
   - `create_block` (position start/end/before/after, anchor_ref/anchor_index, module → ref)
   - `update_block_content` (ref prioritaire ou block_index, CAS)
   - `update_blocks` (batch atomique, max 50, all-or-nothing, 1 écriture rate limit)
   - `delete_block` (ref ou block_index, CAS)
   - `transform_block` (conversion entre blocs de texte paragraph/heading/quote/list/code/preformatted/pullquote — ref HWC conservée, CAS, dry_run)
   - `move_block` (start/end/before/after + ancre, no-op sans effet, CAS, dry_run)
   - `duplicate_block` (copie juste après, refs régénérées en profondeur, CAS, dry_run)
   - `wrap_block` (bloc ou plage contiguë → core/group, ref de groupe si module, CAS, dry_run)
   - `unwrap_block` (dégroupage core/group, enfants promus, CAS, dry_run)
   - `uninject_page` (module + block_id)
   - + `inject_page` étendu : `position` (prepend|append|replace), `block_id`, `expected_hash`, `dry_run`
3. **`dispatch.ts`** — helpers `resolveSite` + `pluginRequest` (appels `houetor/v1` avec token `connected_sites` et erreurs traduites), 11 nouvelles méthodes, `dry_run`/`expected_hash` sur inject/uninject.
4. **`route.ts` / `parser.ts`** — **inchangés** (aucune modification nécessaire).

## Déploiement (quand l'utilisateur validera)

1. Copier `src/*.ts` vers `Pictures\Screenshots\houetor\app\mcp\` :
   - `route.ts`, `parser.ts` : inchangés (remplacement optionnel, identiques)
   - `tools.ts`, `dispatch.ts` : remplacés par les versions portées
   - `error-translator.ts` : ajouté
2. Vérifier : `npx tsc --noEmit` (0 erreur au 2026-08-01) puis `npm run lint`.
3. Committer sur une branche dédiée du repo prod, jamais directement sur main.

## Prérequis de déploiement (IMPORTANT)

Les nouveaux endpoints exigent **houetor-connect ≥ 2.3.0** sur les sites clients
(refs HWC, CAS, `/page-blocks`, `/block-content`, `/blocks`) et **≥ 2.4.0**
pour `update_blocks` (`/blocks/batch-update`) et `dry_run`, **≥ 2.5.0**
pour `transform_block` (`/blocks/transform`) et la rétention du journal d'audit,
**≥ 2.6.0** pour la tier policy (`block_legacy` traduit avec suggestion),
**≥ 2.7.0** pour `move_block`/`duplicate_block`/`wrap_block`/`unwrap_block`
(`/blocks/move|duplicate|wrap|unwrap`).
**Tant que les sites clients n'ont pas le plugin à jour, ces tools renverront 404.**
Les 21 tools existants restent fonctionnels avec l'ancien plugin.

## Vérifications faites

- `npx tsc --noEmit` (tsconfig ci-contre, types réels du repo prod) : **0 erreur** (2.7.0)
- Miroir `houetor-mcp/` v2.7.0 : unitaires 42/42, intégration 52/52, scénarios 41/41 (preuve du protocole, tier policy + ops structurelles incluses)
- Diff `original/` → `src/` : dispatch.ts +4 méthodes structurelles, tools.ts +4 tools, error-translator.ts +2 cas

## Rejouer le typecheck

```powershell
node_modules\.bin\tsc.cmd --noEmit -p tsconfig.json
```
