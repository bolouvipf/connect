# Bugs trouvés & corrigés

Format : symptôme observé / cause racine (preuve code) / fix appliqué / commit hash / statut.

## Bug #1 — Versions incohérentes (header 2.1.0 vs HWC_VERSION 2.2.0) — ✅ CORRIGÉ (v2.3.0)

- **Symptôme** : header `Version: 2.1.0` (houetor-connect.php:6), constante `2.2.0` (ligne 14), stable tag `2.1.0` (readme.txt:7). Confirmé en pratique : `wp plugin list` affichait 2.1.0.
- **Cause racine** : bump 2.2.0 (commit ca1734e) incomplet — constante seule mise à jour.
- **Fix** : bump complet vers **2.3.0** (header + constante + stable tag + changelog 2.2.0/2.3.0 documentés).
- **Commit** : à venir (chantier v2.3.0).
- **Statut** : testé — `check-setup.php` confirme `HWC_VERSION = 2.3.0`.

## Bug #2 — `/inject` position=replace DÉTRUIT tout le contenu — ✅ CORRIGÉ (v2.3.0)

- **Symptôme** (test T14/T15) : `inject {position:"replace"}` → 200, la page se retrouve à `count: 0`. Aucun filet de sécurité.
- **Cause racine** : class-rest-api.php `case 'replace'` remplace tout `post_content` ; pas de `wp_save_post_revision()` avant écriture, pas de CAS.
- **Fix** :
  1. `wp_save_post_revision($page_id)` AVANT toute écriture dans `/inject` ET `/uninject`
  2. CAS (`expected_hash`, md5 du post_content) sur `/inject`, `/uninject`, `/block-content`, `/blocks` → conflit = 409 `error_conflict`, jamais d'écrasement silencieux
- **Vérifié** : V2-5 (CAS bloque → 409), V2-11 (inject CAS → 409), V2-14 (révisions présentes).
- **Statut** : testé.

## Bug #3 — Pas de ref stable pour les blocs créés — ✅ CORRIGÉ (v2.3.0)

- **Symptôme** : les blocs créés via `/blocks` n'étaient pas enrobés de marqueurs HWC → invisibles au ciblage par ref, contrairement aux blocs `/inject`.
- **Fix** : `create_block()` enrobe automatiquement le nouveau bloc (`module` param) d'une ref auto-générée `{module}-{md5 12 chars}` ; `get_page_blocks` renvoie `ref` par bloc + `content_md5` ; update/delete acceptent `ref` OU `index` (ref prioritaire) en préservant les marqueurs.
- **Vérifié** : V2-1..V2-4, V2-7..V2-10.
- **Statut** : testé.

## Bug #4 — Pas de rate limiting ni journal d'audit — ✅ CORRIGÉ (v2.3.0)

- **Symptôme** : un bug agent pouvait spammer les écritures sans frein ni traçabilité (contraire règle 14).
- **Fix** : transient `hwc_ratelimit_{page_id}` (10 écritures/60s, 429) sur toutes les routes d'écriture ; table `{prefix}houetor_connect_actions_log` créée à l'activation, chaque update/create/delete/inject/uninject journalisé (before/after md5).
- **Vérifié** : V2-12 (10 OK puis 429), V2-13 (lignes d'audit).
- **Statut** : testé.

## Bug #5 — Suite de test série 001 NON idempotente (rest-test.php T14 détruisait la page sans restauration) — ✅ CORRIGÉ (Exp 031, 2026-08-05)

- **Symptôme** : `rest-test.php` (série 001) T14 (`inject position=replace`) remplaçait tout `post_content` puis la suite se terminait SANS restaurer → page 2 du lab laissée vide (`count: 0`), ce qui cassait les suites suivantes (intégration MCP : « page 2 a des blocs count=0 »).
- **Cause racine** : bug du SCRIPT de test (le plugin, lui, est couvert par le Bug #2 : `wp_save_post_revision()` + CAS) — le script ne restaurait pas la révision en fin de suite.
- **Fix** : capture `$GLOBALS['hwc_md5_init']` en tête de script + bloc cleanup final qui retrouve la révision ayant ce md5 et `wp_restore_post_revision()` → suite idempotente.
- **Vérifié** : 2 runs successifs → md5 final == initial `c4abdffec12763597022af2da35cd47c` (aucun résidu).
- **Commit** : `c18aa1d` (batteries SECTION 27 dans `houetor-connect/tests/`).
- **Statut** : testé.
