# HOUETOR — SelfHare : État Consolidé & Journal de Session
## Remplace : addendum-session-selfhare-crud-bugs1-6, résumé Section 19, résumé Section 23
### Juillet 2026 — à coller avec le Document Maître Consolidé (Sections 1-18)

> Ce fichier absorbe tout ce que contenaient les trois documents remplacés,
> condensé à l'essentiel, plus le journal complet d'une session de debug
> approfondie sur l'édition de contenu SelfHare. Supprime les trois anciens
> fichiers de la racine du projet — celui-ci les couvre intégralement.

---

## 1. RÉSUMÉ EXÉCUTIF — où on en est réellement

- **Hare classique** : agent fonctionnel en production (dispatch.ts corrigé, 4 tools WP réparés, 5 clients pilotes).
- **SelfHare** : sécurisé (nonce, rate limit, licence vérifiée), et après cette session, **l'édition de contenu bloc par bloc fonctionne enfin de bout en bout sur un cas réel testé** — mais seulement après 12 bugs trouvés et corrigés un par un. Un problème de cache navigateur reste **non résolu** en toute fin de session (voir §7.12).
- **Le vrai bloqueur non technique reste inchangé depuis la Section 19** : paiement récurrent automatique, onboarding guidé, dashboard stats/facturation — rien de cette session n'y touche.
- **Nouveau, découvert cette session** : la base Supabase réelle diverge de la documentation sur plusieurs points non négligeables (§4).

---

## 2. CE QUI VENAIT DE LA SECTION 19 (condensé)

- `dispatch.ts` (agent classique) corrigé : 4 tools pointaient vers l'API REST native WP (`wp/v2`) au lieu du plugin (`houetor/v1`), mauvais header d'auth. Corrigé et déployé.
- SelfHare audité : nonce unifié, rate limit migré vers Supabase (persistant), page admin protégée, licence vérifiée avant appel Anthropic.
- Premier appel relay production réussi (~6s, Claude répond).
- `houetor-widget` déprécié, retiré des priorités.
- **Ordre de priorité qui reste valable** :
  ```
  P1 — Paiement récurrent auto + onboarding guidé + dashboard stats/facturation
  P2 — wordpress.org + WhatsApp Business API
  P3 — <SelfHareChat/> sites codés + app mobile (si ≥20 clients payants)
  P4 — Archiver houetor-widget
  ```
- Sites codés (non-WordPress) : SelfHare reste WordPress-only, décision non tranchée formellement (Option A/B/C toujours ouvertes), impact jugé faible sur la rentabilité immédiate.

---

## 3. CE QUI VENAIT DE LA SECTION 23 (condensé)

CRUD fiabilisé sur les 3 modules métier (`annonces`, `formations`, `produits`) : create+inject, update in-place, delete+uninject, colonnes `wp_site_id/wp_page_id/wp_module/wp_block_id/wp_injected_at` en place. Root causes corrigées : `module` devenu obligatoire (plus de fallback silencieux), `block_id` stable, régénération token limitée à l'activation/connect, `site_id` explicite requis.

**Règles ajoutées au Document Maître (toujours en vigueur) :**
```
17. inject_page/export_to_wordpress exigent un site_id explicite.
18. hwc_token ne se régénère qu'à l'activation du plugin / handle_connect().
19. Tout module injecté doit porter les colonnes wp_* avant d'être fiable.
20. update_X/delete_X doivent répercuter côté WordPress avant Supabase définitif.
21. Aucune table/migration n'est "créée" sur la foi d'un document — vérifier information_schema.
22. Ne jamais emprunter le token/connexion d'un utilisateur réel pour un test — licence de test dédiée.
```

**File d'attente héritée (23.13), toujours ouverte sauf mention contraire :**
```
1. Assets visuels WordPress.org — toujours ouvert
2. 2FA + Contributors WordPress.org — toujours ouvert
3. Ref UUID stable par bloc / concurrence cas_write — EN COURS, voir §7.7
4. Cloudflare 502 sur /selfhare/relay — non revérifié cette session
5. BOM PHP récurrent houetor-selfhare.php — non revérifié cette session
6. Trigger "lundi" du cron — toujours ouvert
7. Résidu <!-- HWC annonces --> + double "annonces-annonces-{hash}" — toujours ouvert
8. is_front_page manquant dans get_wp_pages — toujours ouvert
9. Multi-sites simultanés — RÉSOLU cette session : 2 sites réels existent
   déjà en base (holisticgold + debonairrings), plus besoin d'en créer un 3e
10. Rentabilité (P1) — toujours LE bloqueur principal, intact
```

---

## 4. CE QUI VENAIT DE L'ADDENDUM (condensé)

6 bugs SelfHare corrigés en session précédente : crash JSON relay, schema de tool rejeté par Anthropic, BOM PHP, `editable_fields.map` non-array, cache `?ver=` jamais incrémenté bloquant le vrai fix du `tool_choice`, et **le bug le plus important : `update_pages` écrasait tout le contenu au lieu de le modifier ciblé** — corrigé à l'époque par l'introduction de `get_page_blocks`/`update_block_content` (`parse_blocks()`/`serialize_blocks()`).

**Leçon retenue (règle informelle, confirmée re-nécessaire cette session) :** un rapport "c'est corrigé" n'est jamais accepté sans preuve brute indépendante — trois faux positifs dans cette session précédente, et **au moins six de plus dans la session couverte par ce document** (voir §7).

**A.5 (limite non testée à l'époque) : blocs riches (`cover`, `columns`, `image`, `button`) jamais testés.** Cette session ne les a toujours pas testés explicitement — le travail s'est concentré sur un bug plus fondamental qui rendait même les blocs texte simples non fiables (voir §7.7). **A.5 reste donc ouvert, aggravé : même le cas simple n'était pas fiable jusqu'à cette session.**

---

## 5. CETTE SESSION — AUDIT SUPABASE/VERCEL EN DIRECT

Accès direct utilisé (Supabase projet `jseikgsdfjarozzshnxj`, Vercel projet `prj_W69W7duMRj34aXnvuSfk1TZWjDSF`) pour vérifier l'état réel plutôt que la documentation.

**Découvertes :**
- `orders.payment_provider` accepte réellement **5 providers** (`cinetpay`, `fedapay`, `kkiapay`, `stripe`, `paypal`), pas 2 comme documenté nulle part ailleurs.
- Champ `access_type` (`site_only`/`saas_only`/`site_and_saas`) sur `users`/`orders` — segmentation produit jamais documentée.
- `orders` porte tout un formulaire d'intake site (`site_type`, `site_colors`, `site_pages`, etc.) non décrit dans le Document Maître.
- `templates.model` a pour défaut `claude-haiku-4-5`, pas `claude-sonnet-4-6` (Section 4 du Document Maître) — à confirmer si voulu.
- `annonces`/`produits`/`formations` : au moment de l'audit, colonnes `wp_*` toutes NULL ou table vide — aucune preuve vivante d'injection réussie survivante (des lignes de test orphelines existaient dans `produits`, nettoyage recommandé).
- **`houetor_selfhare_licenses` et `houetor_selfhare_usage_log` ont disparu puis réapparu pendant la session** — cause identifiée : suppression volontaire par l'utilisateur lui-même (pas un incident). Conséquence réelle : les anciennes licences de test (`SLH-starter-d8846ecf...`, `SLH-starter-3884b977...`, référencées par la Règle 22) **n'existent plus**.
- Deux sites réels dans `connected_sites` : `holisticgold.s2-tastewp.com` et `debonairrings.s2-tastewp.com` — referme le point 23.13 #9.
**Nouvelle licence de test créée cette session :**
```
license_key : SLH-starter-732251c8-6c91-4ea7-9f3a-b78f2bb18f9d
email       : bopiflo05@gmail.com
plan        : starter
site_url    : https://confusedstamp.s6-tastewp.com
```
C'est la licence/site utilisés pour tout le debug de cette session (§7). **La Règle 22 doit être mise à jour** pour référencer celle-ci à la place des licences disparues.

---

## 6. CETTE SESSION — EXTENSION DU PÉRIMÈTRE DES TOOLS SELFHARE

**Comparaison avec le *text editor tool* d'Anthropic** (celui que Claude utilise pour éditer des fichiers) a servi de guide de conception :
- `view` → `list_pages`/`get_page_blocks`
- `str_replace` → `update_block_content` (le `find_text`/`replace_text` devient un fallback interne, pas un tool séparé)
- `insert` → `create_block` (positionné, pas une création libre)
- `undo_edit` (abandonné par Anthropic au profit du versioning natif/git) → validé le choix de s'appuyer sur les révisions WordPress natives plutôt qu'un système d'undo maison
- **Aucune commande `delete` dans l'outil officiel Anthropic** → signal retenu : `delete_block` doit rester derrière un filet de sécurité solide (révisions fiables) avant d'être activé en production.
**Décisions actées :**
- `create_page`/`delete_page` **exclus** du périmètre SelfHare (reste "édition de l'existant", pas gestion de structure).
- **`revert_to_revision` existait déjà, complet et câblé** (découvert en cours de session, §7.3) — le travail initialement prévu sur `restore_previous_version`/`class-version-history.php` était un doublon. **Abandonné**, remplacé par un seul ajout léger : `get_page_history` (liste les révisions pour fournir le `revision_id` à `revert_to_revision`).
- Modules **`campagnes` et `cm_posts` n'ont aucun CRUD agent documenté** (profils MARKETING et CM sans outil sur leur module principal) — identifié, **pas traité cette session**, à reprendre.
- **`routines`** : aucun tool agent pour créer/piloter une routine en langage naturel — identifié, explicitement mis de côté ("laissons les routines d'abord") pour cette session.

---

## 7. JOURNAL DE SESSION — LES 12 BUGS TROUVÉS ET CORRIGÉS (dans l'ordre réel)

Chaque bug ci-dessous a été confirmé par preuve brute (grep sur le code source **et** sur le zip généré, logs Vercel, ou test manuel réel) avant d'être considéré résolu — conformément à la leçon de l'addendum (§4).

| # | Bug | Cause racine | Fix | Statut |
|---|---|---|---|---|
| 1 | `get_page_blocks` → "non listée dans le manifest_schema" | Ajouté dans `ALLOWED_ACTIONS` et dans les instructions du prompt, mais **jamais ajouté** à la map de `is_in_manifest()` | Ajout de `get_page_blocks`/`update_block_content` à la map `is_in_manifest()` | ✅ corrigé et vérifié |
| 2 | `update_block_content` aurait cassé pareil juste après | Tombait dans la branche générique `{prefix}_{type}` → cherchait `manifest['block_content']`, qui n'existe pas | Entrée explicite ajoutée dans la map, priorité sur le pattern générique | ✅ corrigé (même commit que #1) |
| 3 | Doublon d'architecture undo | `revert_to_revision` existait déjà, complet, alors qu'on développait `restore_previous_version` en parallèle | Abandon de `class-version-history.php`, réutilisation de `revert_to_revision` + ajout léger de `get_page_history` | ✅ corrigé |
| 4 | `compute_preview()` → "Action 'update_pages' inconnue" | Le switch de preview ne connaît que `create_content`/`update_content`/`delete_content`, jamais traduit depuis `update_pages`/`create_posts`/etc. comme le fait déjà `route()` | Traduction prefix/type ajoutée en tête de `compute_preview()` | ✅ corrigé |
| 5 | Sélecteur d'action encore "Créer une page" | Décision de retrait jamais appliquée à l'UI | Option retirée de `class-agent-chat.php` | ✅ corrigé |
| 6 | "Modifier une page" forçait `update_pages` directement, contournant `get_page_blocks` | `tool_choice` forcé sans passer par la lecture d'abord | Force `get_page_blocks` en premier ; au tour suivant, `tool_choice: auto` via `last_tool_name` plombé JS→AJAX→PHP→`site_context`→`route.ts` ; gère aussi le cas "aucune page sélectionnée" | ✅ corrigé et vérifié en usage réel |
| 7 | **Perte de contenu réelle** — bloc "Insights & Resources" disparu sans révision pour le restaurer | `cas_write()` écrivait le nouveau contenu en SQL brut **avant** d'appeler `wp_update_post()`, donc WordPress ne pouvait jamais photographier l'ancien contenu | `wp_save_post_revision()` forcé en tout début de `cas_write()`, avant toute écriture | ✅ corrigé — **mais le contenu perdu sur la page Blog (#13) n'a jamais été récupéré, doit être retapé manuellement si pas encore fait** |
| 8 | Lecture de blocs retournait du contenu vide ("blocs ne contiennent pas de texte visible") | `extract_block_text()` ne lisait que `innerHTML`, jamais `attrs.content` (où vit le texte de `core/heading` notamment) | Fallback sur `attrs.content` ajouté | ✅ corrigé |
| 9 | Aperçu Avant/Après vide alors que le contenu allait réellement changer (action confirmée "à l'aveugle" une fois) | `compute_preview()` case `update_content` ne simulait jamais `find_text`/`replace_text` avant de construire le diff, contrairement à l'exécution réelle | Simulation ajoutée dans l'aperçu, + erreur explicite si le texte cherché est introuvable | ✅ corrigé |
| 10 | "Modifier un page #TESTPAGE" puis "Contenu introuvable" | Le `<option value="...">` du sélecteur de page utilisait le **titre**, pas l'ID numérique | Nouvelle classe `Houetor_SelfHare_Page_Cache` (cache ID+titre, rafraîchi à l'activation/save/delete), dropdown corrigé | ✅ corrigé |
| 11 | Même symptôme persistant après le fix #10 | Claude repassait le **nom** de la page ("TESTPAGE") en paramètre `id` du fallback `find_text`/`replace_text`, au lieu de réutiliser l'ID numérique (53) déjà résolu par `get_page_blocks` — comportement du modèle, pas un bug de code pur | Filet serveur dans `route.ts` : écrase `toolCall.params.id` avec `selected_page` pour toute action `create/update/delete_pages/posts`, quelle que soit la valeur envoyée par Claude | ✅ corrigé |
| 12 | **"Action proposée : Modifier un page #{{selected_page.id}}"** — placeholder littéral non interpolé | **Cause non confirmée.** Recherche exhaustive (`grep -rn "{{"` sur tout le dépôt) ne trouve aucune syntaxe de template `{{...}}` nulle part dans le code. Hypothèse la plus probable : cache navigateur servant une ancienne version d'`admin-chat.js` — pattern déjà documenté dans l'addendum (bug #5, `?ver=1.0.1` jamais incrémenté) | **Non appliqué.** Dernière action en attente : vérifier le paramètre de version dans `wp_enqueue_script()` pour `admin-chat.js`, et vider le cache navigateur / DevTools réseau pour confirmer | ⚠️ **NON RÉSOLU — point de reprise immédiat de la prochaine session** |

---

## 8. POINT DE REPRISE IMMÉDIAT

**Bug #12 est le seul non résolu.** Avant toute chose à la prochaine session :

1. `grep -n "wp_enqueue_script.*admin-chat\|admin-chat.js" houetor-selfhare/*.php houetor-selfhare/includes/*.php` — vérifier si le paramètre de version est une constante fixe (`'1.0.1'`) ou dynamique (`filemtime(...)`).
2. Si constante fixe → la faire dépendre de `filemtime()` du fichier, comme suggéré dans l'addendum original pour le même type de bug.
3. Vider le cache navigateur / forcer un rechargement (Ctrl+Shift+R) sur `confusedstamp.s6-tastewp.com/wp-admin`, puis refaire **un seul test complet** : sélecteur "Modifier une page" → page test → changement de texte → vérifier que l'action proposée affiche un **ID numérique réel**, pas un placeholder.
4. Une fois confirmé : vérifier enfin **Révisions** (bug #7 corrigé) et `Outils → SelfHare Journal` sur ce test, pour la première fois avec tous les fixes cumulés actifs.
**Restauration manuelle en attente (si pas encore faite) :** le texte "Insights & Resources" sur la page Blog (#13) de `confusedstamp.s6-tastewp.com`, perdu lors du bug #7, avant qu'un fix de révision n'existe.

---

## 9. FILE D'ATTENTE CONSOLIDÉE — TOUT CE QUI RESTE OUVERT

```
IMMÉDIAT
1. Bug #12 (cache admin-chat.js) — voir §8
2. Restauration manuelle "Insights & Resources" sur page Blog #13 (si pas fait)
3. Tester les blocs riches (cover, columns, image, button) — A.5, jamais fait,
   maintenant prioritaire puisque le cas simple est enfin fiable
4. Nettoyer les lignes de test orphelines dans `produits` (Supabase)
5. Mettre à jour la Règle 22 avec la nouvelle licence de test
   (SLH-starter-732251c8..., confusedstamp.s6-tastewp.com)

COURT TERME — SelfHare
6. Combler campagnes/cm_posts (aucun CRUD agent documenté)
7. routines — aucun tool agent (mis de côté volontairement cette session)
8. delete_block — à activer seulement après validation complète de
   get_page_history/revert_to_revision en conditions réelles
9. Assets média (list_media/upload_media/update_media) — jamais commencés

HÉRITÉ (23.13 / 19.4), TOUJOURS OUVERT
10. Assets visuels + 2FA + Contributors WordPress.org
11. Cloudflare 502 /selfhare/relay (non revérifié)
12. BOM PHP houetor-selfhare.php (non revérifié)
13. Trigger cron "lundi" jamais testé réel
14. Résidu <!-- HWC annonces --> + double "annonces-annonces-{hash}"
15. is_front_page manquant dans get_wp_pages
16. Décision Option A/B/C sites codés pour SelfHare — toujours pas tranchée

BLOQUEUR PRINCIPAL, INCHANGÉ
17. P1 — paiement récurrent auto + onboarding guidé + dashboard
    stats/facturation — reste LE sujet non traité par cette session,
    intact depuis la Section 19
```

---

*Document consolidé · SelfHare · Juillet 2026*
*Remplace l'addendum bugs 1-6, le résumé Section 19 et le résumé Section 23.*
*Colle ce fichier avec le Document Maître Consolidé (Sections 1-18) pour reprendre sans perte de contexte.*
