# HOUETOR Connect

Agent IA WordPress pour la gestion et l'édition de contenu — propulsé par Claude (Anthropic).

> **Statut :** Production active — Agent classique fonctionnel, SelfHare en debug avancé

---

## Aperçu

**HOUETOR Connect** est une plateforme d'agents IA qui s'intègre à WordPress pour :
- Créer, modifier et supprimer du contenu (annonces, formations, produits)
- Éditer le contenu des pages bloc par bloc via SelfHare
- Piloter votre site WordPress en langage naturel

Deux agents sont disponibles :

| Agent | Description | Statut |
|---|---|---|
| **Hare** | Agent classique — dispatch via API REST WP | ✅ Production (5 clients pilotes) |
| **SelfHare** | Agent avancé — édition bloc par bloc via plugin WordPress + OpenAI/Anthropic | ⚠️ Beta — édition contenu fonctionnelle, cache navigateur non résolu |

---

## Stack technique

| Composant | Technologie |
|---|---|
| Backend agent | TypeScript — Fastify + Vercel |
| Base de données | Supabase (PostgreSQL) |
| Plugin WordPress | PHP — hooks, REST API, parse_blocks/serialize_blocks |
| Fournisseur IA | Anthropic Claude (relay via /selfhare/relay) |
| Paiements | CinetPay, FedaPay, Kkiapay, Stripe, PayPal |

---

## Déploiement

1. **Plugin WordPress** — télécharger `houetor-selfhare.zip` et l'installer sur votre site WP
2. **Activer** le plugin → génération automatique du token
3. **Connecter** le site via le dashboard HOUETOR
4. **Licence** — une licence de test est requise (starter ou supérieur)

---

## État consolidé — Juillet 2026

Un document complet est disponible dans ce dépôt :

➡️ [`HOUETOR-selfhare-consolide-juillet2026.md`](./HOUETOR-selfhare-consolide-juillet2026.md)

Il contient :
- L'historique complet des 12 bugs SelfHare corrigés
- L'audit Supabase/Vercel en direct
- La file d'attente des priorités consolidée
- Le point de reprise immédiat pour la prochaine session

---

## Learning Lab (branche `opencode-learning`)

Le dépôt sert aussi de **terrain d'apprentissage isolé** pour faire évoluer le plugin `houetor-connect` sans risquer la production : source extraite du zip, environnement WordPress de test local, docs de vérité terrain et tests consignés en preuve.

- ➡️ [`ONBOARDING.md`](./ONBOARDING.md) — kit de continuité pour les agents IA (env, règles, état, roadmap)
- ➡️ [`docs-learning/`](./docs-learning/) — mémoire du lab (6 fichiers : source de vérité, capacités, tests, bugs, expériences, progression)

**État actuel de `houetor-connect` : v2.3.0** — ref HWC, CAS (`expected_hash`), rate limit, journal d'audit, révisions forcées. Bugs #1 (versions) et #2 (`inject replace` destructeur) corrigés, 14/14 tests validés.

---

## Priorités actuelles

```
IMMÉDIAT
1. Résoudre le bug cache navigateur admin-chat.js (#12)
2. Restaurer le contenu perdu "Insights & Resources" (page Blog #13)
3. Tester les blocs riches (cover, columns, image, button)
4. Nettoyer les lignes de test orphelines (Supabase)

COURT TERME
5. Paiement récurrent automatique + onboarding guidé
6. Dashboard stats et facturation
7. wordpress.org + WhatsApp Business API

LONG TERME
8. Application mobile
9. Sites codés (non-WordPress)
```

---

## Licence

Propriétaire — HOUETOR. Usage autorisé sous licence active uniquement.

---

*Documenté à partir de l'état consolidé Juillet 2026*
