# ONBOARDING — Kit de continuité pour agents IA (lab houetor-connect)

> **À lire intégralement avant toute action.** Ce fichier permet à un agent (ou une nouvelle session) de reprendre le travail dans le lab sans rien casser. Toute session qui travaille ici doit finir par mettre à jour `docs-learning/`.

---

## 1. Le but du projet

**L'objectif final du projet : garantir que toute action CRUD qu'un utilisateur demande à l'IA s'exécute sans erreur.** L'utilisateur parle en langage naturel (« corrige le texte du bloc Promo », « ajoute une offre avant le pied de page », « supprime l'ancienne bannière ») ; l'IA traduit la demande en appels sûrs (lecture → écriture → confirmation) et la demande **aboutit réellement**, sans échec, sans conflit non résolu, sans page cassée.

Ce contrat de qualité est assuré par les mécanismes du plugin et du MCP, **tous testés en preuve** :
1. **Relire avant d'écrire** — `get_page_blocks` fournit `content_md5` ; l'agent passe ce hash en `expected_hash` (CAS) → un conflit 409 (page modifiée par ailleurs) est détecté et l'agent relit pour repartir sur un état frais.
2. **`dry_run` sur toutes les écritures** — répétition générale sans rien écrire (aucune écriture/révision/audit/rate limit) : l'utilisateur peut valider l'effet avant publication.
3. **Batch atomique `update_blocks`** — N corrections demandées en une fois = 1 révision, all-or-nothing, max 50.
4. **Garde-fous** — rate limit (429), révision avant toute écriture, journal d'audit, refs HWC stables.
5. **Erreurs traduites en conseils actionnables** — le 409 dit « relisez la page », le 429 « attendez ~60 s » : l'agent sait quoi faire au lieu de bloquer.
6. **Relire pour confirmer** — après chaque écriture, l'agent vérifie que le résultat correspond exactement à la demande.

**Preuve de ce contrat** : les scénarios « exaucés exactement » (demandes utilisateur réalistes passées À TRAVERS le MCP miroir, consignées en brut dans `docs-learning/TOOLS_DISCOVERED.md` série 003) — 24/24 PASS.

Le lab est un **environnement d'apprentissage isolé** pour construire et prouver tout cela sur **houetor-connect** (plugin WordPress qui expose une API REST `houetor/v1` à des agents IA) : l'agent apprend la vérité terrain du plugin (sources, tests, preuves), l'améliore (bugs, robustesse, sécurité), et produit des livrables exploitables (code testé + zip de distribution) — **sans jamais toucher à la production ni au répertoire d'origine des plugins** (`C:\Users\Kimsh\Pictures\Screenshots\houetor`).

Principe : chaque découverte, test et bug est **documenté en preuve** (résultats bruts, pas de résumé) dans `docs-learning/`.

## 2. Repos & branche

- **Repo** : `https://github.com/bolouvipf/connect` (origin = `bolouvipf`)
- **Branche de travail (obligatoire)** : `opencode-learning` — jamais de commit sur `main`
- **Clone** : `git clone -b opencode-learning https://github.com/bolouvipf/connect.git`
- Le repo contient : `houetor-connect/` (source extraite du zip), `houetor-connect.zip` + `houetor-selfhare.zip` (distributions), `docs-learning/` (mémoire), `README.md`, `ONBOARDING.md`, `HOUETOR-selfhare-consolide-juillet2026.md`
- Identité git : `HOUETOR <bopiflo05@gmail.com>` (respecter pour les commits)

## 3. Environnement d'exécution (Windows + WSL)

| Composant | Détail |
|---|---|
| OS hôte | Windows 11 (PowerShell) |
| Sous-système | WSL Ubuntu (user `pierre`, exec via `wsl -u root -e bash -c '...'`) |
| PHP | 8.5.4 (WSL) |
| MySQL | 8.4.10 (WSL) — DB `houetor_connect_test`, user `houetor_lab` |
| WP-CLI | 2.12.0 (WSL) |
| WordPress test | `C:\Users\Kimsh\Desktop\lab\wordpress-test-env` — **pas dans le repo git** (gitignoré), tourne via `wp server --host=0.0.0.0 --port=8888` (WSL) |
| Chemin WSL du lab | `/mnt/c/Users/Kimsh/Desktop/lab/` |

**Contrainte réseau importante** : Windows ne peut PAS joindre `localhost:8888` (pare-feu Hyper-V inbound bloqué, pas de droits admin). **Tous les tests passent par wp-cli depuis WSL** : `wp --allow-root eval-file <script>.php` dans le répertoire `wordpress-test-env`. (Relancer le serveur si besoin : `setsid nohup wp --allow-root server --host=0.0.0.0 --port=8888` dans `wordpress-test-env`.)

## 4. Identifiants — `.env.learning` (NE JAMAIS COMMITER)

`C:\Users\Kimsh\Desktop\lab\.env.learning` contient :
- WP : `http://localhost:8888`, admin `admin` / `pierre11bolouvi`, email `test@houetor.local`
- MySQL : `houetor_connect_test` / `houetor_lab` / `pierre11bolouvi`
- **Token API plugin** : `hwc_token` = `eHlibQROp3fU00hrR8EFJqJJ0cuM9pJy` (réponse de la lecture de l'option, à récupérer via `get_option('hwc_token')` si besoin)
- Clés AI (pour expériences futures) : `GEMINI_API_KEY`, `OPENROUTER_API_KEY` — **confidentielles, jamais affichées en clair dans un chat, jamais commitées**

Règle : tout identifiant ajouté à `.env.learning` reste local ; les docs n'en contiennent que des références, jamais les valeurs.

## 5. Mémoire du projet — `docs-learning/` (les 6 fichiers)

| Fichier | Rôle | Quand le mettre à jour |
|---|---|---|
| `SOURCE_OF_TRUTH_CHECK.md` | Vérification docs vs code réel (ex : dispatch.ts 13/13 `houetor/v1`) | Après chaque nouveau scan de source |
| `PLUGIN_CAPABILITIES.md` | Capacités & API du plugin (routes, params, garde-fous) | Après chaque modification du plugin |
| `TOOLS_DISCOVERED.md` | Résultats bruts des tests (séries T, V2…) | Après chaque série de tests |
| `BUGS_FIXED.md` | Bugs trouvés/corrigés (symptôme → cause → fix → preuve) | Après chaque bug |
| `EXPERIMENTS_LOG.md` | Expériences menées + ce qu'on en apprend | Après chaque expérience |
| `LEARNING_STATE.md` | Checklist globale + état + prochaines étapes | À la fin de chaque session |

## 6. Scripts utiles — `C:\Users\Kimsh\Desktop\lab\scripts\`

| Script | Usage |
|---|---|
| `php-lint.sh` | `php -l` sur tous les fichiers .php (obligatoire avant commit) |
| `check-setup.php` | Vérifie table audit, token, version constante |
| `rest-test.php` | Série 001 (routes d'origine, 18 tests) |
| `rest-test-v2.php` | Série 002 (fonctionnalités v2.3.0, 14 tests) |
| `cleanup.php` | Restaure pages 2 et 3 après tests |
| `restore-page.php` / `check-page.php` | Aide restauration / vérification page |
| `rebuild-zip.sh` | Reconstruit `houetor-connect.zip` (via `git archive`, chemins `/`) |

Exécution type : `wsl -u root -e bash -c 'cd /mnt/c/Users/Kimsh/Desktop/lab/wordpress-test-env && wp --allow-root eval-file /mnt/c/Users/Kimsh/Desktop/lab/scripts/rest-test-v2.php 2>&1'`

## 7. Règles absolues (violation = session invalide)

1. **Jamais de commit sur `main`** — tout sur `opencode-learning`.
2. **`git add` ciblé** (fichiers nommés), jamais `git add .` / `git add -A`.
3. **`.env.learning` jamais commité** (gitignoré — vérifier `git status` avant commit).
4. **`php -l` avant tout commit** ; tests d'abord dans l'env isolé avant d'affirmer quoi que ce soit.
5. **Zip de distribution reconstruit avec des chemins `/`** (WSL `git archive`/`zip`), jamais Compress-Archive PowerShell.
6. **Ne pas modifier `HWC_REST_API::check_token()`** ; ne pas supprimer les routes `/inject` `/uninject`.
7. **Ne jamais écraser silencieusement** : toute écriture passe par CAS (`expected_hash`), révision avant écriture, audit log.
8. **Ne pas exposer de secrets** (clés, token, mots de passe) dans les docs ni dans les sorties de chat.
9. **Preuve avant conclusion** : un comportement n'est vrai que s'il est testé et consigné en brut.

## 8. État actuel (2026-08-01)

- **Version** : **2.6.0** (header + `HWC_VERSION` + stable tag + package.json MCP cohérents)
- **Fonctionnalités livrées et testées** : ref HWC (marqueurs `<!-- HWC {module}-{ref} -->`), `expected_hash` CAS (409 `error_conflict`), rate limit 10 écritures/60s par page (429 `rate_limited`), table d'audit `{prefix}houetor_connect_actions_log` (before/after md5) **+ rétention** (option `hwc_audit_retention_days` défaut 90, CRON quotidien), `wp_save_post_revision()` avant toute écriture, `anchor_ref`/`anchor_index` (404 `anchor_not_found`), **batch atomique `update_blocks`** (N updates = 1 révision, all-or-nothing, max 50, 1 écriture rate limit), **`dry_run`** sur toutes les écritures (aucun effet ni budget consommé), **`transform_block`** (7 blocs texte, ref conservée), **tier policy** (blocs legacy refusés à la création → 400 `block_legacy` + `suggested_block`, map filtrable `hwc_legacy_blocks`)
- **Scores de preuve (2.6.0)** : plugin — V3 32/32, rétention 9/9, transform 21/21, tier policy 11/11 ; MCP miroir — 30/30 unitaires, 35/35 intégration, 29/29 scénarios ; portage `app/mcp/` — typecheck 0 erreur (déploiement prod en attente de validation utilisateur)
- **Env propre** : page 2 restaurée (md5 d'origine `ce833acf933c17dd97eef071665b6269`)
- **Git** : `opencode-learning` (commits 2.6.0 + docs — vérifier le push en début de session)

## 9. Prochaines étapes possibles (roadmap)

1. Tests HTTP externes (depuis Windows) — bloqué pare-feu Hyper-V, non prioritaire
2. Évolutions inspirées de l'analyse de `block-mcp` (GravityKit) — voir `EXPERIMENTS_LOG.md` : ✅ batch atomique `update_blocks`, ✅ `dry_run`, ✅ auto-transforms (`transform_block`), ✅ rétention audit, ✅ **tier policy** (2.6.0) ; restants : opérations structurelles par chemin (move/duplicate/wrap), compte agent dédié à moindre privilège (Application Passwords), rate limit rewrites séparé, tests PHPUnit automatisés
3. Audit de `houetor-selfhare` (2e plugin) — **en attente : ne pas y toucher sans validation utilisateur explicite**
4. Scénarios utilisateur réels (demandes typiques → routes exactes)
5. **Déploiement Phase 4** : copie `houetor-mcp/portage-app-mcp/src/*.ts` vers `app/mcp/` prod (7 tools bloc, typecheck 0 erreur) — en attente de validation utilisateur

## 10. Dépannage

- `git` dans WSL : erreur `dubious ownership` → `git config --global --add safe.directory /mnt/c/Users/Kimsh/Desktop/lab/connect`
- Push WSL qui "hange" : faire le commit dans WSL, le push depuis PowerShell (ou script fichier + `wsl -e bash script.sh`)
- Commit avec identité `root` : `git commit --amend --reset-author --no-edit` depuis PowerShell (identité HOUETOR)
- Page détruite par un test : `cleanup.php` ou `wp post list` + `wp_restore_post_revision(<id>)`
- Serveur WP arrêté : relancer `wp server` (voir §3)
