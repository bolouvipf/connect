# ROADMAP MARCHÉ — Mise sur le marché des 3 livrables (connect, selfhare, serveur MCP)

> Créé 2026-08-05 après l'audit « qu'est-ce qui bloque la mise sur le marché ? ».
> Statut mis à jour au fil des sessions. Branche de travail lab : `opencode-learning` (connect) ; prod : repo `houetor`, branche `mcp-block-crud-2.7.0` (jamais main sans l'utilisateur).

## Contexte (blocages identifiés 2026-08-05)

- Serveur MCP : jamais mergé/déployé (testé en local seulement) ; Étape 6 Section 27 absente de la branche prod.
- Connect : zip officiel en 2.7.0 ; 2.8.0 seulement en upload manuel Fix Day.
- Selfhare : 1.0.3 actif via dossier « jumeau » non packagé ; zip = restyle 1.0.2.
- Sécurité : token statique partagé (pas de rôle WP), rate limit par page seulement, lint 62 erreurs, pas de CI.

## P0 — Serveur MCP en prod

| # | Tâche | Qui | Statut |
|---|---|---|---|
| 1 | Portage Étape 6 Section 27 sur branche prod (tools.ts get_page_blocks 4 champs + error-translator 3 cas 2.8.0), tsc+eslint 0 | 🤝 | ✅ FAIT — commit `3749151` sur `mcp-block-crud-2.7.0` (diff miroir lab = vide, tsc 0, eslint 0) |
| 2 | Merge `mcp-block-crud-2.7.0` → `main` (revue diff complète, aucun secret) | 👤 | ⬜ À FAIRE |
| 3 | Déploiement dashboard (Vercel) + vérif GET SSE 32 tools | 👤 + 🤖 | ⬜ À FAIRE |
| 4 | E2E contre le serveur DÉPLOYÉ (CRUD page About + CAS 409 + dry_run + restauration md5) | 🤖 | ⬜ À FAIRE (réf. 9/9 Exp 018) |

## P0 — Connect 2.8.0 livrable

| # | Tâche | Qui | Statut |
|---|---|---|---|
| 5 | Zip officiel `houetor-connect.zip` 2.8.0 (git archive, chemins /) | 🤖 | ✅ FAIT — `0129edd` puis régénéré `4f81b0d` (rotation token) ; diff vs 2.7.0 = houetor-connect.php + class-block-editor.php + readme.txt + 11 tests ajoutés ; md5 class-block-editor `1bb175a5…` ; 0 occurrence ancien token |
| 6 | Dossier de déploiement Fix Day → 2.8.0 + test réel (écriture + restauration exacte) | 🤝 | ⬜ À FAIRE |
| 7 | readme.txt à jour (changelog 2.7.0/2.8.0, exigences) | 🤖 | ✅ FAIT — stable tag 2.8.0 + changelog imbriqué déjà dans le zip |

## P0 — Selfhare 1.0.3 packagé

| # | Tâche | Qui | Statut |
|---|---|---|---|
| 8 | Intégrer 1.0.3 en version unique dans le repo prod (fin du « jumeau » `houetor-selfhare-103/`) : correctifs Exp 024 (8) + boucle Exp 025 (4) + imbriqué, uninstall sécurisé | 🤝 | ⬜ À FAIRE |
| 9 | Zip `houetor-selfhare.zip` 1.0.3 + upload Fix Day + test réel (Contact 57 blocs, écriture imbriquée, restauration) | 🤝 | ⬜ À FAIRE |
| 10 | Décision artefacts de test Fix Day (bloc Services + « TEST HOUETOR 1.0.3 ») : retirer ou assumer | 👤 | ⬜ À FAIRE |

## P1 — Sécurité

| # | Tâche | Qui | Statut |
|---|---|---|---|
| 11 | Compte agent WP moindre privilège (lier le token à un rôle/capability) : spec lab + tests puis portage | 🤖 → 🤝 | ⬜ À FAIRE |
| 12 | Rate limit global par site (en plus du per-page 10/60s) + rewrites séparé | 🤖 → 🤝 | ⬜ À FAIRE |
| 13 | Rotation/révocation de token documentée (outil admin) | 🤖 | 🔶 PARTIEL — rotation manuelle faite 2026-08-05 (fuite `eHlib…` dans le repo public révoquée, batteries passées en token dynamique) ; outil admin à faire |
| 14 | ⚠️ Bonus : aucun token en clair dans le repo public | 🤖 | ✅ FAIT — batteries 100 % dynamiques (`get_option('hwc_token')`), ONBOARDING.md nettoyé, vérif `eHlib` = 0 occurrence |

## P2 — Qualité / CI

| # | Tâche | Qui | Statut |
|---|---|---|---|
| 15 | Suite de tests exécutable en 1 commande | 🤖 | ✅ FAIT — `houetor-connect/tests/test-suite.sh` : 11 batteries PHP + MCP 42/52/41 → **19/19 PASS, exit 0** (2 runs) ; restauration pages avant chaque suite, relance serveur, timeouts |
| 16 | Corriger les 62 erreurs de lint (repo houetor, par module) | 🤝 | ⬜ À FAIRE |
| 17 | (Roadmap) PHPUnit pour les tests unitaires purs | 🤖 | ⬜ À FAIRE |

## P3 — Docs marché

| # | Tâche | Qui | Statut |
|---|---|---|---|
| 18 | README marché : installation, génération du token, sécurité, limites, support | 🤖 | ⬜ À FAIRE |
| 19 | Politique licence selfhare (starter → pro, activation, mises à jour) | 👤 | ⬜ À FAIRE |

## Rappel des règles applicables

- Jamais de commit sur `main` (connect : `opencode-learning` ; houetor : `mcp-block-crud-2.7.0` tant que le merge #2 n'est pas validé).
- `git add` ciblé, secrets jamais commités (le `.env.local` houetor et `.env.learning` restent hors git).
- Preuve brute avant conclusion : `test-suite.sh` = la référence de validation.
