=== HOUETOR Connect ===
Contributors: BOLOUVI Pierre Florent, houetor
Tags: automation, ai, content, blocks, gutenberg
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.9.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Crée du contenu WordPress en langage naturel avec un agent IA.

== Description ==

HOUETOR Connect est un plugin WordPress qui permet de gérer ton site
entièrement depuis un agent IA. Plus besoin d'utiliser l'éditeur WordPress
— simplement écris ce que tu veux créer, et l'agent le fait pour toi.

= Fonctionnalités =
* Crée annonces, formations, produits, articles en texte simple
* Modifie contenu existant via commandes naturelles
* Intégration Gutenberg native — compatible tous les thèmes
* Aucune dépendance externe — fonctionne immédiatement
* Sécurisé — authentification par token unique

= Cas d'usage =
* E-commerce : Gère ta boutique sans toucher au code
* ONG/Associations : Publie rapidement tes actualités
* Coachs/Formateurs : Crée contenu pédagogique facilement
* Marketing/CM : Automatise contenu et publications

= Comment ça fonctionne =
1. Installe le plugin (zip depuis WordPress.org)
2. Copie ton token depuis HOUETOR > Paramètres
3. Colle-le dans ton tableau de bord HOUETOR.com
4. Commence à demander : "Crée une annonce immobilier" → c'est fait!

= Prérequis =
* WordPress 5.9+ (Gutenberg natif)
* PHP 7.4+
* Aucun plugin supplémentaire requis

= Plan payant optionnel =
Le plugin gratuit fonctionne seul. Un abonnement HOUETOR
(à partir de 23 000 FCFA/mois) déverrouille l'agent IA propulsé par
Claude + accès au tableau de bord avancé.

Version gratuite = visualisation contenu uniquement.
Version payante = création + modification complète.

== Installation ==

1. Télécharge houetor-connect.zip depuis WordPress.org
2. Va dans Extensions > Ajouter une extension
3. Clique Télécharger une extension, sélectionne le fichier
4. Clique Installer, puis Activer
5. Va dans HOUETOR > Paramètres pour générer ton token

C'est tout. Aucune configuration supplémentaire.

== FAQ ==

= Mes données sont-elles en sécurité ? =
Oui. Le plugin ne stocke rien localement sauf un token de
connexion (identifiant unique, non secret). Tout passe par HOUETOR.com
en HTTPS avec chiffrage TLS.

= Puis-je utiliser sur plusieurs sites ? =
Oui, avec un abonnement Hare ou SelfHare — chacun supporte plusieurs
domaines selon le plan.

= Ça fonctionne avec Elementor / Divi ? =
Non pour l'instant. Le plugin fonctionne avec Gutenberg natif.
Soumis sur la feuille de route pour l'avenir.

= Je peux désactiver sans perdre mon contenu ? =
Oui, le plugin ne modifie aucun contenu de WordPress. Simplement
désactive et tout reste intact.

= Quand créer du contenu coûte-t-il ? =
La création via l'agent IA demande un abonnement payant (~23k–50k FCFA/mois
selon ton profil). Les essais gratuits sont disponibles.

== Changelog ==

= 2.9.0 =
* Détection Elementor (refus explicite si page builder détecté)
* Optimisation performance MCP
* Support blocs imbriqués (depth 1-3)
* Améliorations UI admin

= 2.8.0 =
* Support blocs imbriqués complets
* Batch update atomique
* Preview before confirmation

= 2.7.0 =
* Opérations structurelles (move, duplicate, wrap, unwrap)

= 2.6.0 =
* Tier policy — suggestion de solutions au lieu d'erreurs

= 2.5.0 =
* Transform block (paragraph ↔ heading)
* Rétention audit configurable

= 2.4.0 =
* Batch update_blocks avec dry_run

= 2.3.0 =
* CAS (Change Awareness System)
* Rate limit 10/60s
* Journal d'audit complet

= 2.1.0 =
* Version initiale : basic CRUD, Gutenberg integration

== Support ==

Questions ou bugs ? Contacte support@houetor.com
Forum communauté : Facebook "HOUETOR Utilisateurs"
Documentation : https://houetor.com/aide

== Liens ==

Site : https://houetor.com
Tableau de bord : https://houetor.com/espace
En savoir plus : https://houetor.com/commencer
