# Progression du lab — LEARNING STATE

Mise à jour à chaque session. Checklist globale du Script 2.

## État global

- [x] Dossier lab créé : `C:\Users\Kimsh\Desktop\lab\`
- [x] Repo `bolouvipf/connect` cloné dans `lab\connect`
- [x] Branche `opencode-learning` créée + pushée (origin/opencode-learning)
- [x] `docs-learning/` créé avec les 6 fichiers .md initialisés
- [x] `.env.learning` créé (non commité) + `.gitignore` (`.env.learning`, `wordpress-test-env/`)
- [x] WordPress test installé (localhost:8888) + plugin `houetor-connect` activé (token généré)
- [x] `SOURCE_OF_TRUTH_CHECK.md` rempli (dispatch.ts — Doc A vraie, copie locale 13/13 houetor/v1)
- [x] Endpoints testés manuellement et documentés (TOOLS_DISCOVERED.md — 18 tests REST)
- [x] `php -l` : 0 erreur sur les 14 fichiers .php
- [ ] Commit + push initial des docs et du code extrait sur `opencode-learning` (en cours)

## Découvertes structurantes (avant chantier Script 1)

1. **Le repo contient DÉJÀ un CRUD bloc** (commit ca1734e) : `/page-blocks`, `/block-content`, `/blocks` — index-based, avec révisions forcées. La spec Script 1 supposait leur absence.
2. **Écarts vs spec Script 1** (à trancher) :
   - Pas de ciblage par `ref` HWC (le nouveau bloc `/blocks` n'est pas enrobé de marqueurs)
   - Pas de CAS (`expected_hash`) sur aucune écriture
   - Pas de rate limit (10 écritures/60s)
   - Pas de journal d'audit (`houetor_connect_actions_log`)
   - `/inject` et `/uninject` n'appellent pas `wp_save_post_revision()` explicitement
3. **Bugs ouverts** : #1 versions incohérentes (2.1.0 vs 2.2.0) ; #2 `inject replace` destructeur sans garde-fou (confirmé en réel).

## Chantier Script 1 (à venir, après validation)

Options possibles (à décider avec l'utilisateur) :
- **A. Compléter l'existant** : ajouter ref HWC + CAS + rate limit + audit log sur les routes actuelles (additif).
- **B. Suivre la spec à la lettre** : créer class-block-manager.php + routes `/pages/{id}/blocks`, `/blocks/update|create|delete` (parallèles, sans toucher à l'existant).
- Les deux respectent : ne pas toucher `check_token()`, ne pas supprimer `/inject` `/uninject`.

## Rappels de procédure
- Jamais de commit sur `main`. Tout sur `opencode-learning`.
- Commits précis (`git add [fichiers]`), jamais `git add .` aveugle.
- Chaque write testé dans l'env isolé avant commit.
