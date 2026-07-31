=== Houetor Connect ===
Contributors: houetor
Donate link: https://houetor.com
Tags: houetor, hare, annonces, produits, formations, api
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 2.1.0
Requires PHP: 7.4
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Connecte votre site WordPress a Houetor Hare. Affiche automatiquement vos annonces, produits ou formations selon votre profil HWT.

== Description ==

Houetor Connect est le pont entre votre site WordPress et la plateforme Houetor Hare. En saisissant votre code HWT, le plugin détecte automatiquement votre profil (ONG, Boutique, Coach, CM ou Marketing) et affiche le contenu approprié : annonces, produits, formations ou commandes.

= Fonctionnalités =

* Détection automatique du profil HWT
* Affichage en grille ou liste
* Injection de contenu dans les pages de votre choix
* API REST sécurisée pour la communication bidirectionnelle
* Cache intégré pour des performances optimales
* Formulaire de commande AJAX
* Design responsive et personnalisable

== Installation ==

1. Téléchargez le fichier ZIP du plugin.
1. Dans votre administration WordPress, allez dans Extensions > Ajouter > Télécharger.
1. Sélectionnez le fichier ZIP et cliquez sur Installer.
1. Activez le plugin.
1. Allez dans Réglages > Houetor Connect.
1. Saisissez votre code HWT et configurez l\'affichage.

== FAQ ==

= Où trouver mon code HWT ? =

Votre code HWT vous est fourni par Houetor lors de votre inscription à la plateforme Houetor Hare. Il se présente sous la forme HWT-{PROFIL}-{identifiant}.

= Que faire si mon code est invalide ? =

Vérifiez que le code respecte bien le format HWT-{PROFIL}-{uuid}. Les profils valides sont : ONG, BOUTIQUE, COACH, CM, MARKETING.

= Puis-je afficher le contenu sur plusieurs pages ? =

Oui, vous pouvez configurer autant d\'injections que nécessaire dans la section "Injections de contenu" des réglages.

= Le contenu ne s\'affiche pas ? =

Vérifiez que votre code HWT est valide et que la page sélectionnée existe. Le cache est rafraîchi toutes les 5 minutes.

== Changelog ==

= 2.1.0 =
* Ajout de l\'API REST pour la communication bidirectionnelle
* Amélioration de l\'interface d\'administration
* Ajout des tokens de sécurité
* Support des formations pour le profil Coach

= 2.0.0 =
* Refonte complète du plugin
* Nouveau système de profils HWT
* Injection de contenu configurable
* Design responsive

= 1.0.0 =
* Version initiale
* Affichage des annonces pour les profils ONG
