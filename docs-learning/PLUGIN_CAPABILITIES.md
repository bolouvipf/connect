# Plugin houetor-connect — Capacités & API

**Version détectée (constante PHP) :** 2.2.0 — `HWC_VERSION` (houetor-connect.php:14)
**Version header plugin :** 2.1.0 (houetor-connect.php:6) — ⚠️ INCOHÉRENTE avec HWC_VERSION
**Stable tag readme.txt :** 2.1.0 (readme.txt:7) — ⚠️ idem
**Dernier scan :** 2026-07-31
**Source :** repo `bolouvipf/connect` branche `opencode-learning` (extrait de `houetor-connect.zip`, commit ca1734e)

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

### `/inject` (écriture persistante)
- params : `page_id`, `content` (wp_kses_post), `module` (obligatoire), `position` (`append`|`prepend`|`replace`, défaut `append`), `block_id` (auto-généré `uniqid(module-')` si absent)
- Si marqueurs `<!-- HWC {module}-{block_id} start/end -->` existants → remplace. Sinon positionne selon `position`.
- Écrit dans `post_content` via `wp_update_post` (persistant) — PAS de `wp_save_post_revision()` avant écriture ⚠️
- PAS de CAS (compare-and-swap) ⚠️

### `/uninject`
- params : `page_id`, `module`, `block_id` (tous requis)
- Regex sur marqueurs HWC uniquement — ne fonctionne PAS sur les blocs natifs

### `/page-blocks` (lecture, nouvelle depuis commit ca1734e)
- params : `page_id` (requis, query param)
- → `HWC_Block_Editor::get_page_blocks()` : `parse_blocks(post_content)`, ignore les blancs, renvoie `index` + `blockName` + `content` (texte extrait innerHTML→strip_tags, fallback attrs.content)
- ⚠️ Renvoie un WP_Error 404 si `post_content` vide (template) — message explicite

### `/block-content` (PATCH, nouvelle)
- params : `page_id`, `block_index` (>=0), `new_content` (wp_kses_post)
- → `update_block_content()` : localise par index (blocs nommés uniquement), refuse les blocs avec `innerBlocks` imbriqués, reconstruit innerHTML en préservant les attributs de la balise racine, synchronise `innerContent`
- `wp_save_post_revision()` AVANT écriture ✓
- ⚠️ PAS de CAS, PAS de ref, PAS de rate limit, PAS d'audit log

### `/blocks` (POST/DELETE, nouvelle)
- POST `create_block` : params `page_id`, `block_name` (liste blanche de 20 types core/*), `content`, `insert_after_index` (optionnel), `insert_before_index` (optionnel). Positionnel relatif par INDEX uniquement. Révision ✓ avant écriture.
- ⚠️ Le nouveau bloc n'est PAS enrobé de marqueurs HWC → aucune ref stable pour le retrouver ensuite
- DELETE `delete_block` : params `page_id`, `block_index`. Révision ✓ avant écriture.

---

## Fonctions PHP découvertes

| Fonction | Fichier:ligne | Rôle |
|---|---|---|
| `HWC_Block_Editor::get_page_blocks()` | class-block-editor.php:8 | Parse et indexe les blocs d'une page |
| `HWC_Block_Editor::update_block_content()` | class-block-editor.php:38 | Modifie le contenu d'un bloc par index |
| `HWC_Block_Editor::create_block()` | class-block-editor.php:117 | Crée un bloc (liste blanche 20 types), insertion after/before par index |
| `HWC_Block_Editor::delete_block()` | class-block-editor.php:235 | Supprime un bloc par index |
| `HWC_Block_Editor::find_block_position()` | class-block-editor.php:276 | Index logique → index tableau (ignore blancs) |
| `HWC_Block_Editor::extract_block_text()` | class-block-editor.php:286 | Texte lisible d'un bloc (innerHTML puis attrs.content) |
| `HWC_REST_API::check_token()` | class-rest-api.php:70 | Auth token (X-Houetor-Token / Bearer) + hash_equals |
| `HWC_Content_Injector::inject_content()` | class-content-injector.php:6 | Filtre `the_content` — injection DYNAMIQUE (option hwc_injections + fetch API HOUETOR) |
| `hwc_activate()` | houetor-connect.php:82 | Génère `hwc_token` si absent (uniquement à l'activation) |

## Mécanismes d'injection — DEUX coexistants (à ne pas confondre)

1. **Filtre dynamique `the_content`** (class-content-injector.php:6) : `add_filter('the_content', ...)` — affiche en front uniquement, lit l'option `hwc_injections` (page_id/module/position), fetch le HTML live depuis l'API HOUETOR, ajoute les marqueurs. Non persistant. Vérifie l'absence de conflit de marqueurs dans post_content avant d'injecter.
2. **Écriture REST persistante** (`/inject`) : écrit directement les marqueurs + HTML dans `post_content` via `wp_update_post`. Persistant en base.

## Constantes / Options

| Élément | Valeur | Lieu |
|---|---|---|
| `HWC_VERSION` | `2.2.0` | houetor-connect.php:14 |
| `HWC_API_BASE` | `https://houetor.com/api/public` | houetor-connect.php:16 |
| option `hwc_token` | 32 chars, `wp_generate_password` | houetor-connect.php:83-85 (activation only) |
| option `hwc_injections` | array de {page_id, module, position} | class-content-injector.php:11 |

## Écarts doc vs réalité (à garder en tête)

- Le Document Maître affirmait que l'injection se fait uniquement via filtre `the_content` (dynamique, non persistante) → **FAUX en partie** : `/inject` écrit désormais réellement dans `post_content` (commit ca1734e). Les deux coexistent.
- Règle « hwc_token ne se régénère qu'à l'activation » → **CONFIRMÉE** : `hwc_activate()` est le seul endroit (houetor-connect.php:82), et seulement si l'option manque.
