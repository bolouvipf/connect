=== Houetor Connect ===
Contributors: houetor
Donate link: https://houetor.com
Tags: houetor, hare, annonces, produits, formations, api
Requires at least: 5.8
Tested up to: 6.8
Stable tag: 2.7.0
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

= 2.7.0 =
* Opérations structurelles par bloc (toutes avec CAS expected_hash, dry_run,
  révision avant écriture, audit, refs HWC stables) :
  - POST /blocks/move : déplacer un bloc (start | end | before | after + ancre)
  - POST /blocks/duplicate : dupliquer un bloc juste après lui (refs régénérées en profondeur)
  - POST /blocks/wrap : enrober un bloc ou une plage contiguë dans un core/group
  - POST /blocks/unwrap : dégrouper un core/group (enfants promus à la racine)
* Déplacement sans effet (déjà en place) : aucune révision, aucun audit
* Plage de wrap inversée refusée (400 explicite)

= 2.6.0 =
* Tier policy : refus des blocs legacy à la création (400 block_legacy)
  avec bloc de remplacement suggéré (suggested_block) — map filtrable hwc_legacy_blocks

= 2.5.0 =
* Rétention du journal d'audit : option hwc_audit_retention_days (défaut 90)
  + CRON quotidien de purge
* POST /blocks/transform : conversion d'un bloc de texte (paragraph/heading/quote/
  list/code/preformatted/pullquote) — ref HWC conservée, CAS + dry_run

= 2.4.0 =
* POST /blocks/batch-update : mise à jour atomique de plusieurs blocs (max 50)
  en UNE seule révision — all-or-nothing, compte 1 écriture rate limit
* Paramètre dry_run (true/1) sur toutes les routes d'écriture
  (/inject, /uninject, /block-content, /blocks, /blocks/batch-update) :
  validation complète (CAS, cibles, contenu) sans aucune écriture,
  sans révision, sans audit, sans consommation du rate limit
* Versions alignées (header + constante + readme)

= 2.3.0 =
* Ciblage des blocs par ref HWC ({module}-{block_id}) en plus de l'index
* Ref HWC auto-générée sur les blocs créés via POST /blocks (paramètre module)
* Positionnement "start" | "end" | "before" | "after" avec anchor_ref/anchor_index
  sur POST /blocks — erreur explicite anchor_not_found (jamais de fallback silencieux)
* CAS (compare-and-swap) sur toutes les écritures via expected_hash (md5 du post_content) —
  conflit => 409 error_conflict, jamais d'écrasement silencieux
* Rate limiting des écritures : 10/60s par page (429 rate_limited)
* Journal d'audit : table {prefix}houetor_connect_actions_log (before/after par action)
* wp_save_post_revision() avant écriture sur /inject et /uninject (filet de sécurité)
* GET /page-blocks renvoie content_md5 + ref par bloc
* Versions alignées (header + constante + readme)

= 2.2.0 =
* Édition bloc par bloc : GET /page-blocks, PATCH /block-content, POST/DELETE /blocks
* wp_save_post_revision() avant écriture des routes blocs

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
