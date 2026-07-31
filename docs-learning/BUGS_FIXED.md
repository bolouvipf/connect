# Bugs trouvés & corrigés

Format : symptôme observé / cause racine (preuve code) / fix appliqué / commit hash / statut.

## Bug #1 — Versions incohérentes (header 2.1.0 vs HWC_VERSION 2.2.0) — SIGNALÉ, PAS CORRIGÉ

- **Symptôme** : `houetor-connect.php` header `Version: 2.1.0` (ligne 6), constante `HWC_VERSION = '2.2.0'` (ligne 14), readme.txt `Stable tag: 2.1.0` (ligne 7). Confirmé en pratique : `wp plugin list` affiche `2.1.0`.
- **Cause racine probable** : le bump 2.2.0 (commit ca1734e « block-aware CRUD endpoints ») a mis à jour la constante mais pas le header ni le stable tag.
- **Impact** : version affichée wp-admin (2.1.0) ≠ code réel (2.2.0).
- **Fix** : aligner header + readme.txt sur 2.2.0 (recommandé) ou baisser la constante.
- **Statut** : non corrigé — en attente de décision utilisateur.

## Bug #2 — `/inject` position=replace DÉTRUIT tout le contenu de la page (confirmé en réel) — SIGNALÉ, PAS CORRIGÉ

- **Symptôme** (test T14/T15, 2026-07-31) : `POST /houetor/v1/inject {position:"replace"}` → 200 ; le `get_page_blocks` suivant renvoie `"blocks": [], "count": 0`. Toute la page existante est remplacée par le seul bloc injecté.
- **Cause racine** : class-rest-api.php:198-200 — `case 'replace': $new_content = $injected;` remplace l'intégralité de `post_content`. Aucune vérification, aucun CAS, aucun garde-fou côté `/inject` (pas d'appel explicite `wp_save_post_revision()` avant écriture, contrairement aux routes `/blocks` qui le font).
- **Impact** : une erreur de l'agent (position mal comprise) peut effacer une page entière. La restauration n'est possible que via les révisions WP (auto).
- **Correspond à la spec Script 1** : insuffisance #5 « Pas de update_block_content ciblé — risque d'écraser le reste » + #7 « Pas de CAS ».
- **Fix proposé** (à valider) : ajouter `wp_save_post_revision()` + CAS sur `/inject` aussi, ou documenter explicitement `replace` comme dangereux côté agent.
- **Statut** : non corrigé — documenté pour le chantier Script 1.

## (Aucun bug corrigé pour l'instant — session d'installation)
