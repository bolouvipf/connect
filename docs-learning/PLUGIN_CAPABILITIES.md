# Plugin houetor-connect — Capacités & API

**Version (constante PHP + header + stable tag) :** 2.3.0 — cohérent partout (bug #1 corrigé)
**Dernier scan :** 2026-07-31
**Source :** repo `bolouvipf/connect` branche `opencode-learning` (chantier v2.3.0 testé en lab)

---

## Endpoints REST exposés (namespace `houetor/v1`, fichier:ligne)

| Route | Méthode | Callback | Auth | Fichier:ligne |
|---|---|---|---|---|
| `/pages` | GET | `get_pages` | token | class-rest-api.php:7 |
| `/menus` | GET | `get_menus` | token | class-rest-api.php:13 |
| `/media` | GET/POST | `get_media` / `upload_media` | token | class-rest-api.php:19-30 |
| `/inject` | POST | `inject_content` | token | class-rest-api.php:32 |
| `/uninject` | POST | `uninject_content` | token | class-rest-api.php:38 |
| `/page-blocks` | GET | `get_page_blocks` | token | class-rest-api.php:44 |
| `/block-content` | PATCH | `update_block_content` | token | class-rest-api.php:50 |
| `/blocks` | POST/DELETE | `create_block` / `delete_block` | token | class-rest-api.php:56-67 |

**Auth :** header `X-Houetor-Token` ou `Authorization: Bearer <token>`, comparé avec `hash_equals()` à l'option `hwc_token` (class-rest-api.php:70-91).

## Écritures — garde-fous v2.3.0 (toutes les routes d'écriture)

- **CAS (compare-and-swap)** : `expected_hash` obligatoire si présent dans la réponse lecture (`content_md5` de `get_page_blocks` / `/pages`). Mismatch → **409 `error_conflict`**, jamais d'écrasement silencieux.
- **Rate limit** : transient `hwc_ratelimit_{page_id}`, **10 écritures/60s max** → **429 `rate_limited`** (class-rest-api.php `check_rate_limit()`).
- **Journal d'audit** : table `{prefix}houetor_connect_actions_log` créée à l'activation (`hwc_create_audit_table()`, dbDelta) ; chaque update/create/delete/inject/uninject journalisé avec before/after `content_md5` (`audit_log()`).
- **Révisions** : `wp_save_post_revision()` AVANT toute écriture (inject, uninject, block-content, blocks).

### `/inject` (écriture persistante)
- params : `page_id`, `content` (wp_kses_post), `module` (obligatoire), `position` (`append`|`prepend`|`replace`, défaut `append`), `block_id` (auto-généré `uniqid(module-)` si absent), `expected_hash` (CAS)
- Si marqueurs `<!-- HWC {module}-{block_id} start/end -->` existants → remplace. Sinon positionne selon `position`.
- Révision ✓ avant écriture ; CAS ✓ ; rate limit ✓ ; audit ✓.

### `/uninject`
- params : `page_id`, `module`, `block_id` (requis), `expected_hash` (CAS)
- Regex sur marqueurs HWC uniquement — ne fonctionne PAS sur les blocs natifs.

### `/page-blocks` (lecture)
- params : `page_id` (requis)
- → `HWC_Block_Editor::get_page_blocks()` : renvoie `index`, `blockName`, `content`, **`ref`** (ref HWC si bloc enrobé, sinon null), **`content_md5`** (pour CAS).
- ⚠️ Renvoie WP_Error 404 si `post_content` vide (template).

### `/block-content` (PATCH)
- params : `page_id`, `ref` (prioritaire) OU `block_index`, `new_content`, `expected_hash` (CAS)
- → `update_block_content()` : localise par ref (marqueurs HWC) ou index ; refuse les blocs avec `innerBlocks` imbriqués ; reconstruit innerHTML en préservant les attributs ; **préserve les marqueurs HWC lors de l'update par ref**.
- Révision ✓ ; CAS ✓ ; rate limit ✓ ; audit ✓.

### `/blocks` (POST/DELETE)
- POST `create_block` : params `page_id`, `block_name` (liste blanche 20 types core/*), `content`, `module` (optionnel — si fourni, le bloc est **enrobé d'une ref HWC auto-générée** `{module}-{md5:12}`), `position` (`start`|`end`|`before`|`after`), `anchor_ref` (obligatoire pour before/after, sinon **404 `anchor_not_found` — jamais de fallback silencieux**), `anchor_index` (alternative), `expected_hash` (CAS).
- DELETE `delete_block` : params `page_id`, `ref` OU `block_index`, `expected_hash` (CAS).
- Révision ✓ avant écriture ; CAS ✓ ; rate limit ✓ ; audit ✓.

---

## Fonctions PHP découvertes (v2.3.0)

| Fonction | Fichier:ligne | Rôle |
|---|---|---|
| `HWC_Block_Editor::get_page_blocks()` | class-block-editor.php | Parse + indexe les blocs, renvoie ref + content_md5 |
| `HWC_Block_Editor::extract_hwc_ref()` | class-block-editor.php | Extrait la ref HWC d'un bloc (regex marqueurs) |
| `HWC_Block_Editor::find_block_index_by_ref()` | class-block-editor.php | Index logique → tableau via ref |
| `HWC_Block_Editor::cas_check()` | class-block-editor.php | Vérifie md5 attendu vs réel (objet de conflit) |
| `HWC_Block_Editor::update_block_content()` | class-block-editor.php | Update par ref (prioritaire) ou index, marqueurs préservés |
| `HWC_Block_Editor::create_block()` | class-block-editor.php | Création + enrobage ref HWC si module, anchors before/after |
| `HWC_Block_Editor::delete_block()` | class-block-editor.php | Suppression par ref ou index |
| `HWC_Block_Editor::find_block_position()` | class-block-editor.php | Index logique → index tableau |
| `HWC_REST_API::check_rate_limit()` | class-rest-api.php | Transient 10/60s par page |
| `HWC_REST_API::audit_log()` | class-rest-api.php | Insertion journal (before/after md5) |
| `HWC_REST_API::check_token()` | class-rest-api.php | Auth token (NON MODIFIÉE, règle respectée) |
| `hwc_create_audit_table()` | houetor-connect.php | dbDelta table `houetor_connect_actions_log` (activation) |
| `hwc_activate()` | houetor-connect.php | Génère `hwc_token` si absent + crée table audit |

## Mécanismes d'injection — DEUX coexistants (à ne pas confondre)

1. **Filtre dynamique `the_content`** (class-content-injector.php:6) : non persistant, fetch HTML live depuis l'API HOUETOR (option `hwc_injections`).
2. **Écriture REST persistante** (`/inject`) : écrit marqueurs + HTML dans `post_content`. Persistant.

## Constantes / Options

| Élément | Valeur | Lieu |
|---|---|---|
| `HWC_VERSION` | `2.3.0` | houetor-connect.php |
| `HWC_API_BASE` | `https://houetor.com/api/public` | houetor-connect.php:16 |
| `RATE_LIMIT_WINDOW` / `RATE_LIMIT_MAX` | `60` / `10` | class-rest-api.php |
| option `hwc_token` | 32 chars, généré à l'activation seulement | houetor-connect.php |
| option `hwc_injections` | array {page_id, module, position} | class-content-injector.php:11 |
| table `houetor_connect_actions_log` | `(id, action, page_id, module, block_id, ref, before_md5, after_md5, created_at)` | houetor-connect.php |

## Écarts doc vs réalité (à garder en tête)

- Document Maître affirmait injection uniquement via filtre `the_content` → **FAUX en partie** : `/inject` écrit réellement dans `post_content`. Les deux coexistent.
- Règle « hwc_token ne se régénère qu'à l'activation » → **CONFIRMÉE** : `hwc_activate()` est le seul endroit, seulement si l'option manque.
- Spec Script 1 → traduction en Approche A (additive) : spec lue littéralement (routes `/pages/{id}/blocks`, manager séparé) non implémentée ; ref/CAS/rate limit/audit intégrés à l'existant.
