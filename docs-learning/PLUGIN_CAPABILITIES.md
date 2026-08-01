# Plugin houetor-connect — Capacités & API

**Version (constante PHP + header + stable tag) :** 2.6.0 — cohérent partout
**Dernier scan :** 2026-08-01
**Source :** repo `bolouvipf/connect` branche `opencode-learning` (2.6.0 testé en lab : tier policy 11/11, V3 32/32, rétention 9/9, transform 21/21)

---

## Endpoints REST exposés (namespace `houetor/v1`, fichier:ligne)

| Route | Méthode | Callback | Auth | Fichier:ligne |
|---|---|---|---|---|
| `/pages` | GET | `get_pages` | token | class-rest-api.php |
| `/menus` | GET | `get_menus` | token | class-rest-api.php |
| `/media` | GET/POST | `get_media` / `upload_media` | token | class-rest-api.php |
| `/inject` | POST | `inject_content` | token | class-rest-api.php |
| `/uninject` | POST | `uninject_content` | token | class-rest-api.php |
| `/page-blocks` | GET | `get_page_blocks` | token | class-rest-api.php |
| `/block-content` | PATCH | `update_block_content` | token | class-rest-api.php |
| `/blocks` | POST/DELETE | `create_block` / `delete_block` | token | class-rest-api.php |
| `/blocks/batch-update` | POST | `batch_update_blocks` | token | class-rest-api.php |
| `/blocks/transform` | POST | `transform_block` | token | class-rest-api.php |

**Auth :** header `X-Houetor-Token` ou `Authorization: Bearer <token>`, comparé avec `hash_equals()` à l'option `hwc_token`.

## Écritures — garde-fous (toutes les routes d'écriture)

- **CAS (compare-and-swap)** : `expected_hash` (md5 `post_content` lu via `get_page_blocks`) → mismatch = **409 `error_conflict`**, jamais d'écrasement silencieux. `null` = vérification désactivée.
- **`dry_run`** (toutes les écritures, bool) : répétition générale — aucune écriture, aucune révision, aucun audit, **aucun rate limit consommé**. Les refus (CAS 409, whitelist, legacy) précèdent le dry_run → un dry_run avec mauvais hash renvoie quand même l'erreur.
- **Rate limit** : transient `hwc_ratelimit_{page_id}`, **10 écritures/60s max** → **429 `rate_limited`** ; le batch compte pour 1 écriture ; TOUTES les tentatives comptent (400/409 inclus, pas en dry_run).
- **Journal d'audit** : table `{prefix}houetor_connect_actions_log`, before/after `content_md5` ; **rétention** option `hwc_audit_retention_days` (défaut 90, filtre `hwc_audit_retention_days`) + CRON quotidien `hwc_audit_cleanup` (purge chunkée 500×200 max).
- **Révisions** : `wp_save_post_revision()` AVANT toute écriture réelle.

### `/inject` (écriture persistante)
- params : `page_id`, `content` (wp_kses_post), `module` (obligatoire), `position` (`append`|`prepend`|`replace`, défaut `append`), `block_id`, `expected_hash`, `dry_run`
- Marqueurs `<!-- HWC {module}-{block_id} start/end -->` : existe → remplace ; sinon positionne.

### `/uninject`
- params : `page_id`, `module`, `block_id`, `expected_hash`, `dry_run` — regex sur marqueurs HWC uniquement.

### `/page-blocks` (lecture)
- params : `page_id` → `index`, `blockName`, `content`, `ref` (HWC ou null), `content_md5` (CAS).
- ⚠️ 404 si `post_content` vide (template).

### `/block-content` (PATCH)
- params : `page_id`, `ref` (prioritaire) OU `block_index`, `new_content`, `expected_hash`, `dry_run`
- Refuse les blocs avec `innerBlocks` imbriqués ; marqueurs HWC préservés.

### `/blocks` (POST/DELETE)
- POST `create_block` : params `page_id`, `block_name`, `content`, `module` (ref HWC auto `{module}-{md5:12}` si fourni), `position` (`start`|`end`|`before`|`after`), `anchor_ref`/`anchor_index` (before/after ; inconnu → **404 `anchor_not_found`**, jamais de fallback), `expected_hash`, `dry_run`.
- **Tier policy (2.6.0)** : `block_name` dans `LEGACY_BLOCKS` (21 blocs obsolètes/renommés/retirés) → **400 `block_legacy`** avec `data.block_name` + `data.suggested_block` (remplacement suggéré dans ALLOWED_BLOCKS, ex `core/verse`→`core/preformatted`) ; hors map → 400 `create_failed` générique. Map filtrable `hwc_legacy_blocks`.
- DELETE `delete_block` : params `page_id`, `ref` OU `block_index`, `expected_hash`, `dry_run`.

### `/blocks/batch-update` (2.4.0)
- params : `page_id`, `updates` (max 50 × `{ref|block_index, new_content}`), `expected_hash`, `dry_run`
- **Atomique** : validation complète AVANT écriture (all-or-nothing), UNE révision, 1 écriture rate limit.

### `/blocks/transform` (2.5.0)
- params : `page_id`, `ref` OU `block_index`, `target_block_name`, `expected_hash`, `dry_run`
- Conversion entre les 7 blocs `TEXT_BLOCKS` (paragraph, heading, quote, list, code, preformatted, pullquote) ; **ref HWC conservée** ; `level` du heading préservé ; refuse imbriqués/hors whitelist (source ET cible) ; révision + CAS + audit `transform_block`.

---

## Fonctions PHP découvertes

| Fonction | Rôle |
|---|---|
| `HWC_Block_Editor::get_page_blocks()` | Parse + indexe les blocs, renvoie ref + content_md5 |
| `HWC_Block_Editor::extract_hwc_ref()` | Extrait la ref HWC (regex marqueurs) |
| `HWC_Block_Editor::legacy_suggestion()` | Tier policy : suggestion de remplacement d'un bloc legacy (filtre `hwc_legacy_blocks`) |
| `HWC_Block_Editor::cas_check()` | Vérifie md5 attendu vs réel |
| `HWC_Block_Editor::update_block_content()` | Update par ref (prioritaire) ou index, marqueurs préservés |
| `HWC_Block_Editor::create_block()` | Création + enrobage ref HWC si module, anchors before/after, tier policy |
| `HWC_Block_Editor::batch_update_blocks()` | Batch atomique all-or-nothing (max 50, 1 révision) |
| `HWC_Block_Editor::transform_block()` | Conversion texte→texte, ref conservée, whitelist TEXT_BLOCKS |
| `HWC_Block_Editor::delete_block()` | Suppression par ref ou index |
| `HWC_REST_API::check_rate_limit()` | Transient 10/60s par page |
| `HWC_REST_API::audit_log()` | Insertion journal (before/after md5) |
| `HWC_REST_API::audit_cleanup()` | Purge rétention (CRON `hwc_audit_cleanup`) |
| `HWC_REST_API::check_token()` | Auth token (NON MODIFIÉE, règle respectée) |
| `hwc_create_audit_table()` | dbDelta table `houetor_connect_actions_log` (activation) |
| `hwc_activate()` / `hwc_deactivate()` | Génère `hwc_token` si absent + table audit + CRON retention (idempotent) |

## Mécanismes d'injection — DEUX coexistants (à ne pas confondre)

1. **Filtre dynamique `the_content`** (class-content-injector.php) : non persistant, fetch HTML live depuis l'API HOUETOR (option `hwc_injections`).
2. **Écriture REST persistante** (`/inject`) : écrit marqueurs + HTML dans `post_content`. Persistant.

## Constantes / Options

| Élément | Valeur |
|---|---|
| `HWC_VERSION` | `2.6.0` |
| `HWC_API_BASE` | `https://houetor.com/api/public` |
| `RATE_LIMIT_WINDOW` / `RATE_LIMIT_MAX` | `60` / `10` |
| `BATCH_MAX_UPDATES` | `50` |
| `ALLOWED_BLOCKS` | 20 types `core/*` (create) |
| `TEXT_BLOCKS` | 7 types texte (transform) |
| `LEGACY_BLOCKS` | 21 entrées legacy → suggestion (tier policy) |
| option `hwc_token` | 32 chars, généré à l'activation seulement |
| option `hwc_audit_retention_days` | défaut 90 (filtre `hwc_audit_retention_days`) |
| option `hwc_injections` | array {page_id, module, position} |
| table `houetor_connect_actions_log` | (id, action_type, before_json, after_json, created_at) |
