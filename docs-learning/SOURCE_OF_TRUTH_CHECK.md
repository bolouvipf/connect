# Vérification État Réel — dispatch.ts

**Date de vérification :** 2026-07-31
**Méthode :** grep sur le repo cloné (`bolouvipf/connect`, branche `opencode-learning`) + comparaison copie locale du projet Next.js

## Résultat

### 1. Le repo `connect` ne contient AUCUN fichier `dispatch.ts`

```
$ grep -rn "wp/v2\|houetor/v1" . --include="*.ts" --include="*.php"
# → 0 résultat .ts. Le repo ne contient que : README.md,
#   HOUETOR-selfhare-consolide-juillet2026.md, houetor-connect.zip, houetor-selfhare.zip
```

Extraction `houetor-connect.zip` + grep : uniquement du PHP (8 routes REST `houetor/v1` dans
class-rest-api.php — voir PLUGIN_CAPABILITIES.md). **Aucun TypeScript nulle part.**

### 2. La copie LOCALE du projet Next.js (hors repo) pointe bien vers `houetor/v1`

Chemin : `C:\Users\Kimsh\Pictures\Screenshots\houetor\app\mcp\dispatch.ts`
Grep `wp/v2|houetor/v1` → **13/13 fetch vers `/wp-json/houetor/v1/...`** (inject×6, uninject×3,
pages×1, menus×1, media×1, + 1 restant), **0 occurrence de `wp/v2`** :

```
dispatch.ts:97:   const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, ...
dispatch.ts:156:  const res = await fetch(`${site.url}/wp-json/houetor/v1/uninject`, ...
dispatch.ts:249:  const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, ...
dispatch.ts:334:  const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, ...
dispatch.ts:388:  const res = await fetch(`${site.url}/wp-json/houetor/v1/uninject`, ...
dispatch.ts:453:  const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, ...
dispatch.ts:538:  const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, ...
dispatch.ts:592:  const res = await fetch(`${site.url}/wp-json/houetor/v1/uninject`, ...
dispatch.ts:636:  const res = await fetch(`${site.url}/wp-json/houetor/v1/pages`, ...
dispatch.ts:665:  const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, ...
dispatch.ts:717:  const res = await fetch(`${site.url}/wp-json/houetor/v1/menus`, ...
dispatch.ts:766:  const res = await fetch(`${site.url}/wp-json/houetor/v1/media`, ...
dispatch.ts:795:  const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, ...
```

## Conclusion

**Doc A (corrigé) est vraie** sur la copie locale actuelle : les 13 appels de `dispatch.ts`
utilisent `houetor/v1`, aucun `wp/v2`, pas d'erreur de header visible. **Le bug documenté
par Doc B (« à vérifier/corriger, session dédiée requise ») ne se retrouve pas dans cette
copie.**

⚠️ Limites de la vérification :
- La copie locale peut être en retard/en avance par rapport au code réellement déployé
  (le repo `connect` n'héberge pas le code Next.js — il faut le repo principal HOUETOR).
- Le contenu de `app/mcp/tools.ts` (définition des tools) n'a pas été audité ici : le bug
  des « 4 tools mal routés » peut survivre dans la définition des tools même si dispatch
  est corrigé.

## Documents en contradiction

- **HOUETOR-selfhare-consolide-juillet2026.md:23** (dans ce repo) : « dispatch.ts (agent
  classique) corrigé : 4 tools pointaient vers l'API REST native WP (wp/v2) au lieu du
  plugin (houetor/v1) ... Corrigé et déployé. » → **Doc A**
- **HOUETOR-master-consolide.md P9** : « bug dispatch.ts encore à vérifier/corriger, session
  dédiée requise » → **Doc B** (ce document n'existe pas dans ce repo ; référence externe)

**Qui avait raison :** Doc A, sur la base de la copie locale actuelle (13/13 endpoints
`houetor/v1`). Doc B est soit obsolète (écrite avant le correctif), soit reflète l'état
d'une autre copie. À confirmer sur le repo Next.js officiel (hors scope de ce lab) via
`app/mcp/tools.ts` — signalé, pas touché.
