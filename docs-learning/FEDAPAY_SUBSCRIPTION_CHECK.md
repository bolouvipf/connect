# FedaPay — Existence de subscriptions natives (ÉTAPE A.1, chantier P1)

Date : 2026-08-06 · Statut : **CONFIRMÉ — PAS de subscriptions natives**

## Question

FedaPay propose-t-il un objet "subscription" natif avec webhook de
renouvellement automatique, ou seulement des paiements uniques ?

## Réponse (preuve)

**FedaPay ne propose PAS d'objet subscription ni d'événement de
renouvellement.** Le modèle est 100 % transactionnel :

- Documentation officielle (https://docs-v1.fedapay.com/development/events) :
  cycle de vie = `customer.created` → `transaction.created` →
  `transaction.canceled` / `transaction.declined` / `transaction.approved`
  → `transaction.transferred`, chaque transition déclenchant
  `transaction.updated`. **Aucun événement de récurrence.**
- API Reference (https://docs.fedapay.com/api-reference/introduction-en) :
  sections = Customers, Collect, Payouts, Balances, Logs, Webhooks.
  **Pas de section Subscriptions.**
- Webhooks (https://docs.fedapay.com/integration-api/en/webhooks-en) :
  notifications d'événements de transaction ; retry jusqu'à 9 fois
  (intervalle exponentiel ≤ 2 min) ; désactivation auto du webhook après
  10 échecs.
- Lib CLI officielle (fedapay/fedapay-cli) : commandes webhooks
  create/delete/list/retrieve/update — aucune commande subscription.

## Conséquence pour P1

L'hypothèse §4.2 du brief est **confirmée** : le renouvellement FCFA doit
être **simulé côté HOUETOR** (cron quotidien de rappel + fenêtre de grâce),
pas un prélèvement automatique silencieux — FedaPay ne le permet pas
techniquement sans objet subscription.

Le flux Stripe (EUR/CAD) reste le modèle natif (subscriptions + invoices),
comme décrit §4.1 du brief.

## Références

- https://docs-v1.fedapay.com/development/events
- https://docs.fedapay.com/integration-api/en/webhooks-en
- https://docs.fedapay.com/api-reference/introduction-en
- https://github.com/fedapay/fedapay-cli/blob/master/docs/webhooks.md
