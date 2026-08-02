=== Houetor SelfHare ===
Contributors: houetor
Tags: ai, assistant, wordpress, automation
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPL v2 or later

Assistant IA WordPress hébergé par Houetor. Gérez votre contenu en
langage naturel via SelfHare.

== Description ==

SelfHare est l'assistant IA de Houetor pour WordPress. Discutez avec
l'agent et il exécute des actions sur votre site (articles, pages,
produits WooCommerce, formations, annonces, commandes, etc.) via
Claude d'Anthropic, ou programmez des routines automatisées.

Connectez votre site à votre compte houetor.com (Stripe, FedaPay).
Plans : Starter, Pro, Agency. Console multi-sites pour les agences.
API REST /houetor-selfhare/v1/ pour intégration tierce.

== Installation ==

1. Téléchargez le plugin et extrayez-le dans /wp-content/plugins/houetor-selfhare/
2. Activez le plugin dans le menu Extensions
3. Rendez-vous dans SelfHare > Activation pour coller votre clé de licence
4. Une fois activée, l'Assistant est accessible dans SelfHare > Assistant

== Changelog ==

= 1.0.2 =
* Sécurité : aperçu obligatoire côté serveur avant toute exécution (token à usage unique), conflit d'édition détecté (CAS + expected_hash), limite de taux étendue aux créations, clé de licence chiffrée au stockage
* Fiabilité : révision forcée avant toute écriture (contenu, corbeille), remplacement de texte vérifié (erreur explicite si introuvable), routines planifiées réellement exécutées, prix/stock WooCommerce gérés
* Correction : versions alignées (1.0.2), journal d'audit paginé et limité aux écritures, nettoyage complet à la désinstallation (rôle + cron)

= 1.0.1 =
* Correction : validation HTML5 du format de clé de licence

= 1.0.0 =
* Première version
