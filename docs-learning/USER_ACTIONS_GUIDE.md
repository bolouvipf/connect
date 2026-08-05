# GUIDE DES ACTIONS UTILISATEUR — Mise sur le marché (connect + selfhare + MCP)

> Créé 2026-08-05 (Exp 031 bis). Complète `ROADMAP_MARKET.md` : ici, pour CHAQUE tâche restante à la main de l'utilisateur, la procédure exacte, les commandes, les vérifications et les pièges connus (tous déjà éprouvés dans les Exp 015/018/025/027).
> Règle d'or : ne jamais afficher/commiter les secrets (token WP, clés Supabase, `.env.local`, `.env.learning`).

## Vue d'ensemble

| # | Tâche | Qui | Statut |
|---|---|---|---|
| 2 | Merge `mcp-block-crud-2.7.0` → `main` (houetor) | 👤 | ⬜ |
| 3 | Déploiement Vercel + vérif GET SSE 32 tools | 👤 | ⬜ |
| 4 | E2E contre le serveur DÉPLOYÉ (agent, après #3) | 🤖 | ⬜ |
| 6 | Dossier Fix Day → connect 2.8.0 officiel + ménage 2.7.0 | 👤 + 🤖 | ⬜ |
| 8/9 | selfhare 1.0.3 version unique prod + zip | 🤖 (validation 👤) | ⬜ |
| 10 | Artefacts de test Fix Day (Services/Contact/About) : garder ou nettoyer | 👤 | ⬜ |
| 17 | README marché (agent) | 🤖 | ⬜ |
| 18 | Politique licence selfhare (décisions) | 👤 | ⬜ |

---

## #2 — Merge `mcp-block-crud-2.7.0` → `main` (repo houetor)

**Contexte** : la branche contient EXACTEMENT 2 commits prêts :
- `3749151` — portage MCP Étape 6 (tools.ts + error-translator.ts, tsc 0, eslint 0, diff miroir lab = vide)
- `4f81b0d` — zip `outputs/houetor-connect.zip` 2.8.0 (0 occurrence ancien token, 74246 octets)

**Diff attendu vs `main`** : 3 fichiers seulement — `app/mcp/tools.ts`, `app/mcp/error-translator.ts`, `outputs/houetor-connect.zip`. Rien d'autre.

**Procédure locale (PowerShell)** :

```powershell
cd C:\Users\Kimsh\Pictures\Screenshots\houetor
git fetch origin
git checkout main
git pull origin main
git diff origin/main origin/mcp-block-crud-2.7.0 --stat   # AVANT de merger : 3 fichiers attendus
git diff origin/main origin/mcp-block-crud-2.7.0 -- app/mcp/ | Select-Object -First 80  # revue rapide
git merge --no-ff origin/mcp-block-crud-2.7.0 -m "feat(mcp): portage etape 6 (blocs imbriques 2.8.0) + zip connect 2.8.0"
git push origin main
```

**Alternative recommandée — PR GitHub** : ouvrir https://github.com/bolouvipf/houetor → « Pull requests » → New pull request → base `main` ← compare `mcp-block-crud-2.7.0` → vérifier le diff (3 fichiers) → Merge. (Le merge local marche aussi et est plus direct.)

**Vérifications** :
- Le diff ne contient AUCUN fichier `.env*`, aucun token en clair, aucun `probe-*.mjs`.
- `outputs/houetor-selfhare.zip` est un `M` LOCAL non commité → **ne JAMAIS le `git add`** (c'est la tâche #9).

**Pièges connus** :
- Ne pas utiliser `git add .` (le zip selfhare non commité serait embarqué).
- Après merge, ne pas supprimer la branche avant le déploiement Vercel (utile pour rollback).

---

## #3 — Déploiement Vercel

**Contexte** : le MCP prod tourne aujourd'hui en local (`next build` + `next start -p 3010`, Exp 018). L'app a besoin des 3 variables d'environnement Supabase qui sont dans `.env.local` (gitignoré) — **jamais affichées/committées**.

**Procédure** :
1. Dashboard https://vercel.com → projet du repo `houetor` (compte `bopiflo05-9197`).
2. **Environment Variables** (Settings → Environment Variables) : recopier les 3 valeurs du `.env.local` local (URL Supabase + 2 clés) — les coller à la main.
   ⚠️ **Ne PAS utiliser `vercel env pull`** : le compte n'a pas le droit de décryptage (testé 2026-08-03, Exp 018).
3. **Deploy** : branche `main` (après #2) — `npx vercel --prod` depuis le repo, ou Deployments → Redeploy dans le dashboard.
4. Framework : Next.js (build automatique), Node ≥ 20.

**Vérification** :
- `GET https://<projet>.vercel.app/mcp?event=session` avec header `X-HWT-Token: <token profil ONG>` → 32 tools déclarés dont les 12 bloc (comparer avec la liste du lab/3010).
- Signaler l'URL à l'agent → #4 E2E déployé.

---

## #4 — E2E contre le serveur DÉPLOYÉ (agent, après #3)

Réf. Exp 018 (9/9 PASS en local sur Fix Day connecté) : GET SSE tools, `list_connected_sites` (id `f166ef68-…`), cycle CRUD complet page About — dry_run sans effet → create → update CAS → **409 périmé refusé + contenu intact** → batch → move → delete → **page restaurée md5 identique**.
À lancer par l'agent avec l'URL Vercel réelle. Aucune action utilisateur requise ici (validation des résultats seulement).

---

## #6 — Fix Day : connect 2.8.0 officiel + ménage

**État actuel de Fix Day (https://fixday.s6-tastewp.com)** :
- `houetor-connect-280/` (jumeau 2.8.0) **ACTIF** — c'est déjà le patch blocs imbriqués (Exp 027).
- `houetor-connect/` (2.7.0) encore présent, **désactivé**.
- selfhare 1.0.3 actif (dossier unique `houetor-selfhare-103/`).

**À faire** (upload du zip officiel + ménage) — procédure wp-admin éprouvée (Exp 015/025/027) :
1. **Uploader le zip officiel** `houetor/outputs/houetor-connect.zip` (2.8.0) via wp-admin (Extensions → Ajouter → Téléverser). Pattern curl (détail complet dans Exp 015) : login cookies → `plugin-install.php?tab=upload` (récupérer `_wpnonce`) → `update.php?action=upload-plugin` multipart.
   - Si « dossier existe déjà » → méthode jumeau (Exp 027) : renommer le dossier dans le zip avant upload, puis désactiver l'ancien.
2. **Ménage** : dans plugins.php, supprimer le dossier `houetor-connect` (2.7.0) désactivé.
3. **Vérifier** : plugins.php affiche « Version 2.8.0 » ; le token WP est **préservé** (option `hwc_token` partagée, l'activation ne le régénère que s'il est absent — Exp 027).
4. **Piège TasteWP** : réplication du pool de serveurs → les vérifs 404/absent sont trompeuses pendant ~minutes (Exp 025). Vérifier 2-3 fois espacées.
5. Test rapide facultatif : un dry_run via le MCP lab pointé sur Fix Day.

---

## #8/#9 — selfhare 1.0.3 : version unique dans le repo prod + zip (agent, validation 👤)

**Contexte** : le lab contient la source complète 1.0.3 (`lab\connect\houetor-selfhare\`) — correctifs sécurité Exp 024 (8 fichiers) + boucle agent Exp 025 (4 fichiers) + édition blocs imbriqués 1.0.3 (Exp 030). Le prod `houetor\houetor-selfhare\` est resté en 1.0.2 partiel (restyle seul). Fix Day fonctionne avec un dossier « jumeau » qui doit devenir la version unique.

**Procédure (à exécuter par l'agent, à VALIDER par l'utilisateur)** :
1. Copier les fichiers 1.0.3 du lab → prod (diff attendu : admin-chat.css/js, class-agent-chat.php, class-agent-dispatch.php, class-agent-routines.php, class-error-translator.php, class-license.php, houetor-selfhare.php, readme.txt, uninstall.php).
2. `php -l` 0 erreur + `node --check` OK.
3. Commit ciblé sur `mcp-block-crud-2.7.0` (ou `main` si déjà mergé) + push.
4. Zip : `git archive --format=zip --prefix=houetor-selfhare/ -o outputs/houetor-selfhare.zip HEAD:houetor-selfhare/houetor-selfhare` (⚠️ arbre du dossier PLUGIN — la commande du Exp 024 avec double imbrication rendait le zip invalide, Exp 025 a trouvé la bonne).
5. Upload Fix Day (méthode jumeau si nécessaire, Exp 027/030) + test réel (lecture Contact 57 blocs + écriture + restauration, réf. Exp 030).

**Validation utilisateur demandée** : OUI pour porter le 1.0.3 testé dans le repo prod (au lieu de distribuer le 1.0.2 restylé sans correctifs).

---

## #10 — Artefacts de test Fix Day : garder ou nettoyer

**Décision à prendre** (état actuel, tous créés par l'agent en réel) :
- **Services (43)** : bloc injecté footer (ref `sh_blk_4fb3a7e2e`, h2 + p « Test réel selfhare 1.0.3 ») + bloc imbriqué #3 modifié « Our Dental Services [TEST HOUETOR 1.0.3] » — **GARDÉS par décision du 2026-08-04** (à reconfirmer).
- **Contact (8)** : texte modifié Exp 029 « gardons ça, ça fonctionne » — conservé.
- **About (5)** : état canonique `856c1c99…` (blocs starter + groupe agent Exp 021), page restaurée.
- **Blog (7)** : post_page → contenu ignoré au front, nettoyé (aucun artefact).

**Si nettoyage demandé** : procédure par l'agent via MCP connect (delete_block par ref `sh_blk_4fb3a7e2e` + restauration texte par update CAS) ou wp-admin classique — chaque écriture = révision + audit + restauration vérifiable (md5 avant/après). Rien à faire manuellement : il suffit de dire « garder » ou « nettoyer ».

---

## #17 — README marché (agent)

Rédaction par l'agent, sans action utilisateur : installation du zip, génération du token (page admin `admin.php?page=houetor-connect`), sécurité (CAS, dry_run, rate limit, révisions, audit), connexion dashboard/Supabase, limites connues (10 requêtes/60 s par page, pas de compte agent dédié), support.

---

## #18 — Politique licence selfhare : décisions à fournir

Questions à trancher (le plugin gère déjà licence + plan starter/pro, chiffrement AES-256-CBC, verrouillage relay) :
1. **Tarifs/plans** : starter gratuit ? plans pro payants ? prix ?
2. **Activation** : à la première utilisation (clé générée côté HOUETOR comme aujourd'hui) ou clé vendue ?
3. **Mises à jour** : distribution des zips via Fix Day comme maintenant, ou futur endpoint de mise à jour ?
4. **Support** : canal (dashboard, email) + SLA éventuel.
Réponses à donner à l'agent → il documente (README marché + admin).

---

## Checklist « prêt pour le marché »

- [ ] #2 merge main poussé (3 fichiers vérifiés)
- [ ] #3 Vercel déployé, GET SSE 32 tools OK
- [ ] #4 E2E déployé vert (9/9 réf. Exp 018)
- [ ] #6 Fix Day : 2.8.0 officiel seul actif, dossier 2.7.0 supprimé
- [ ] #8/#9 selfhare 1.0.3 unique + zip + upload OK
- [ ] #10 artefacts tranchés (garder/nettoyer)
- [ ] #17 README marché publié
- [ ] #18 politique licence rédigée
- (hors blocage) #11 compte agent WP, #12 rate limit global, #14 lint 62, #16 PHPUnit — chantiers lab à lancer
