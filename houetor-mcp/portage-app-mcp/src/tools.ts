export interface HWToolParam {
  type: string
  required: boolean
  description: string
}

export interface HWTool {
  name: string
  description: string
  profiles: string[]
  params: Record<string, HWToolParam>
}

export const HWT_TOOLS: HWTool[] = [
  {
    name: 'create_annonce',
    description: 'Créer une nouvelle annonce',
    profiles: ['ONG', 'BOUTIQUE', 'CM', 'MARKETING'],
    params: {
      titre: { type: 'string', required: true, description: 'Titre de l\'annonce' },
      contenu: { type: 'string', required: true, description: 'Contenu de l\'annonce' },
      statut: { type: 'string', required: false, description: 'brouillon | publiee' },
    },
  },
  {
    name: 'update_annonce',
    description: 'Modifier une annonce existante',
    profiles: ['ONG', 'BOUTIQUE', 'CM', 'MARKETING'],
    params: {
      id: { type: 'string', required: true, description: 'ID de l\'annonce' },
      titre: { type: 'string', required: false, description: 'Nouveau titre' },
      contenu: { type: 'string', required: false, description: 'Nouveau contenu' },
      statut: { type: 'string', required: false, description: 'brouillon | publiee' },
    },
  },
  {
    name: 'delete_annonce',
    description: 'Supprimer une annonce',
    profiles: ['ONG', 'BOUTIQUE', 'CM', 'MARKETING'],
    params: {
      id: { type: 'string', required: true, description: 'ID de l\'annonce' },
    },
  },
  {
    name: 'list_contenu',
    description: 'Lister le contenu du client (annonces, formations, posts, produits)',
    profiles: ['ONG', 'BOUTIQUE', 'CM', 'MARKETING', 'COACH'],
    params: {
      type: { type: 'string', required: true, description: 'Type de contenu : annonces | formations | posts | produits | campagnes' },
    },
  },
  {
    name: 'create_formation',
    description: 'Créer une nouvelle formation et l\'injecter dans WordPress',
    profiles: ['COACH', 'MARKETING', 'ONG'],
    params: {
      titre: { type: 'string', required: true, description: 'Titre de la formation' },
      description: { type: 'string', required: false, description: 'Description' },
      prix: { type: 'number', required: false, description: 'Prix en FCFA' },
      image_url: { type: 'string', required: false, description: 'URL de l\'image' },
      site_id: { type: 'string', required: true, description: 'ID du site WordPress connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress cible' },
      position: { type: 'string', required: false, description: 'prepend | append (défaut: append)' },
    },
  },
  {
    name: 'update_formation',
    description: 'Modifier une formation existante (resynchronise WordPress si le contenu change)',
    profiles: ['COACH', 'MARKETING', 'ONG'],
    params: {
      id: { type: 'string', required: true, description: 'ID de la formation' },
      titre: { type: 'string', required: false, description: 'Nouveau titre' },
      description: { type: 'string', required: false, description: 'Nouvelle description' },
      prix: { type: 'number', required: false, description: 'Nouveau prix' },
      image_url: { type: 'string', required: false, description: 'Nouvelle URL d\'image' },
    },
  },
  {
    name: 'delete_formation',
    description: 'Supprimer une formation (retire d\'abord le bloc WordPress, puis supprime la ligne Supabase)',
    profiles: ['COACH', 'MARKETING', 'ONG'],
    params: {
      id: { type: 'string', required: true, description: 'ID de la formation' },
    },
  },
  {
    name: 'create_produit',
    description: 'Créer un nouveau produit et l\'injecter dans WordPress',
    profiles: ['BOUTIQUE', 'MARKETING', 'ONG'],
    params: {
      nom: { type: 'string', required: true, description: 'Nom du produit' },
      description: { type: 'string', required: false, description: 'Description du produit' },
      prix: { type: 'number', required: false, description: 'Prix en FCFA' },
      image_url: { type: 'string', required: false, description: 'URL de l\'image' },
      site_id: { type: 'string', required: true, description: 'ID du site WordPress connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress cible' },
      position: { type: 'string', required: false, description: 'prepend | append (défaut: append)' },
    },
  },
  {
    name: 'update_produit',
    description: 'Modifier un produit existant (resynchronise WordPress si le contenu change)',
    profiles: ['BOUTIQUE', 'MARKETING', 'ONG'],
    params: {
      produit_id: { type: 'string', required: true, description: 'ID du produit' },
      nom: { type: 'string', required: false, description: 'Nouveau nom' },
      description: { type: 'string', required: false, description: 'Nouvelle description' },
      prix: { type: 'number', required: false, description: 'Nouveau prix' },
      image_url: { type: 'string', required: false, description: 'Nouvelle URL d\'image' },
    },
  },
  {
    name: 'delete_produit',
    description: 'Supprimer un produit (retire d\'abord le bloc WordPress, puis supprime la ligne Supabase)',
    profiles: ['BOUTIQUE', 'MARKETING', 'ONG'],
    params: {
      produit_id: { type: 'string', required: true, description: 'ID du produit' },
    },
  },
  {
    name: 'get_wp_pages',
    description: 'Récupérer la liste des pages WordPress du site connecté',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
  {
    name: 'inject_page',
    description: 'Injecter du contenu HTML dans une page WordPress',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      html: { type: 'string', required: true, description: 'Contenu HTML Gutenberg à injecter' },
      module: { type: 'string', required: false, description: 'Module HOUETOR (annonces, produits, formations, custom)' },
      annonce_id: { type: 'string', required: false, description: 'ID de l\'annonce (stocke la traçabilité WordPress sur l\'annonce)' },
      position: { type: 'string', required: false, description: 'prepend | append | replace (défaut: append)' },
      block_id: { type: 'string', required: false, description: 'ID du bloc à remplacer (re-injection d\'un bloc existant)' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'uninject_page',
    description: 'Retirer un bloc HOUETOR d\'une page (par module + block_id)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      module: { type: 'string', required: true, description: 'Module HOUETOR du bloc à retirer' },
      block_id: { type: 'string', required: true, description: 'ID du bloc à retirer' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'get_wp_menus',
    description: 'Récupérer la liste des menus WordPress',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
  {
    name: 'list_connected_sites',
    description: 'Lister les sites WordPress connectés au compte',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
  {
    name: 'get_page_blocks',
    description: 'Lister la structure de blocs d\'une page (blockName, content, ref HWC, content_md5) — À RELIRE AVANT CHAQUE ÉCRITURE',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
    },
  },
  {
    name: 'create_block',
    description: 'Créer un bloc sur une page (position start/end/before/after, anchor_ref/anchor_index, module → ref HWC)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      block_name: { type: 'string', required: true, description: 'Nom du bloc WordPress (ex: core/paragraph)' },
      content: { type: 'string', required: false, description: 'Contenu HTML du bloc' },
      module: { type: 'string', required: false, description: 'Module HOUETOR (génère la ref HWC {module}-{hash})' },
      position: { type: 'string', required: false, description: 'start | end | before | after (défaut: end)' },
      anchor_ref: { type: 'string', required: false, description: 'ref HWC du bloc ancrage (pour before/after)' },
      anchor_index: { type: 'string', required: false, description: 'index du bloc ancrage (pour before/after)' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'update_block_content',
    description: 'Modifier le contenu d\'un bloc (par ref HWC prioritaire ou block_index, CAS expected_hash)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'ref HWC du bloc (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'index du bloc (si pas de ref)' },
      new_content: { type: 'string', required: true, description: 'Nouveau contenu HTML du bloc' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'update_blocks',
    description: 'Batch atomique : plusieurs corrections de blocs en UNE révision (all-or-nothing, max 50, compte 1 écriture rate limit)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      updates: { type: 'array', required: true, description: 'Tableau d\'objets { ref|block_index, new_content }' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'delete_block',
    description: 'Supprimer un bloc (par ref HWC ou block_index, CAS expected_hash)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'ref HWC du bloc (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'index du bloc (si pas de ref)' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'transform_block',
    description: 'Convertir un bloc de texte en un autre type de bloc texte (paragraph/heading/quote/list/code/preformatted/pullquote) — la ref HWC est conservée, CAS expected_hash',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'ref HWC du bloc (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'index du bloc (si pas de ref)' },
      target_block_name: { type: 'string', required: true, description: 'Type de bloc cible (ex: core/heading)' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'move_block',
    description: 'Déplacer un bloc existant (ref HWC prioritaire ou block_index) vers start | end | before | after (ancre par anchor_ref/anchor_index). Sans effet si déjà en place. CAS + dry_run',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'ref HWC du bloc à déplacer (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'index du bloc à déplacer (si pas de ref)' },
      position: { type: 'string', required: true, description: 'start | end | before | after' },
      anchor_ref: { type: 'string', required: false, description: 'ref HWC de l\'ancre (obligatoire pour before/after)' },
      anchor_index: { type: 'string', required: false, description: 'index de l\'ancre alternatif à anchor_ref' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'duplicate_block',
    description: 'Dupliquer un bloc existant (ref HWC prioritaire ou block_index) juste après lui ; refs HWC de la copie régénérées en profondeur (préfixe module conservé). CAS + dry_run',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'ref HWC du bloc à dupliquer (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'index du bloc à dupliquer (si pas de ref)' },
      module: { type: 'string', required: false, description: 'module HOUETOR — attribue une ref stable à la copie si la source n\'en a pas' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture (ref simulée)' },
    },
  },
  {
    name: 'wrap_block',
    description: 'Enrober un bloc (ou une plage contiguë start→end par ref|block_index + end_ref|end_index) dans un core/group ; ref de groupe si module fourni. Plage inversée refusée. CAS + dry_run',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'ref HWC du premier bloc de la plage (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'index du premier bloc de la plage (si pas de ref)' },
      end_ref: { type: 'string', required: false, description: 'ref HWC du dernier bloc de la plage (plage > 1 bloc)' },
      end_index: { type: 'string', required: false, description: 'index du dernier bloc de la plage (plage > 1 bloc)' },
      module: { type: 'string', required: false, description: 'module HOUETOR — attribue une ref stable au groupe' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'unwrap_block',
    description: 'Dégrouper un bloc core/group (ref HWC prioritaire ou block_index) : ses enfants sont promus à la racine à sa place. Seul un core/group peut être dégroupé. CAS + dry_run',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté (obtenu via list_connected_sites)' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'ref HWC du groupe (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'index du groupe (si pas de ref)' },
      expected_hash: { type: 'string', required: false, description: 'content_md5 lu via get_page_blocks (CAS : 409 si la page a changé)' },
      dry_run: { type: 'boolean', required: false, description: 'true = répétition générale sans aucune écriture' },
    },
  },
  {
    name: 'export_to_wordpress',
    description: 'Exporter du contenu vers WordPress (avec upload d\'images)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      site_id: { type: 'string', required: true, description: 'ID du site connecté' },
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress cible' },
      html: { type: 'string', required: true, description: 'Contenu HTML Gutenberg complet' },
      images: { type: 'array', required: false, description: 'URLs d\'images à uploader vers la médiathèque WP' },
      annonce_id: { type: 'string', required: false, description: 'ID de l\'annonce (stocke la traçabilité WordPress sur l\'annonce)' },
    },
  },
  {
    name: 'get_profil',
    description: 'Obtenir les informations du profil client',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
  {
    name: 'update_profil',
    description: 'Mettre à jour les informations du profil',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      full_name: { type: 'string', required: false, description: 'Nom complet' },
      company: { type: 'string', required: false, description: 'Organisation / Entreprise' },
      phone: { type: 'string', required: false, description: 'Téléphone' },
    },
  },
  {
    name: 'get_stats',
    description: 'Obtenir les statistiques du compte',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
  {
    name: 'list_commandes',
    description: 'Lister les commandes',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
  {
    name: 'update_commande',
    description: 'Mettre à jour le statut d\'une commande',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      id: { type: 'string', required: true, description: 'ID de la commande' },
      statut: { type: 'string', required: true, description: 'Nouveau statut : en_attente | confirmee | expediee | livree | annulee' },
    },
  },
  {
    name: 'send_notification',
    description: 'Envoyer une notification au client (email)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      sujet: { type: 'string', required: true, description: 'Sujet de la notification' },
      message: { type: 'string', required: true, description: 'Corps du message' },
    },
  },
]
