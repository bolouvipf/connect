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
    name: 'get_wp_pages',
    description: 'Récupérer la liste des pages WordPress du site connecté',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
  {
    name: 'get_wp_menus',
    description: 'Récupérer la liste des menus WordPress',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
  {
    name: 'get_page_blocks',
    description: "Lire la structure COMPLÈTE de blocs d'une page WordPress (tous niveaux, blocs imbriqués inclus). Retourne : index (global), blockName, content, ref, parent_ref (index du parent), depth, has_children, child_count, content_md5",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
    },
  },
  {
    name: 'inject_page',
    description: "Injecter du contenu HTML dans une page WordPress (avec marqueurs HWC, CAS expected_hash si fourni)",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      html: { type: 'string', required: true, description: 'Contenu HTML Gutenberg à injecter' },
      module: { type: 'string', required: false, description: 'Module HOUETOR (annonces, produits, formations, custom)' },
      block_id: { type: 'string', required: false, description: 'Identifiant de bloc (généré si absent)' },
      position: { type: 'string', required: false, description: 'append | prepend | replace (défaut: append)' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS, issu de get_page_blocks / /pages)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire (aucune révision, aucun audit)' },
    },
  },
  {
    name: 'uninject_page',
    description: 'Retirer un bloc HWC d\'une page WordPress (marqueurs HWC uniquement)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      module: { type: 'string', required: true, description: 'Module HOUETOR du bloc' },
      block_id: { type: 'string', required: true, description: 'Identifiant du bloc à retirer' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire' },
    },
  },
  {
    name: 'create_block',
    description: "Créer un bloc Gutenberg dans une page (enrobé d'une ref HWC si module fourni ; position by anchor_ref/anchor_index)",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      block_name: { type: 'string', required: true, description: 'Nom du bloc (liste blanche core/*)' },
      content: { type: 'string', required: false, description: 'Contenu du bloc' },
      module: { type: 'string', required: false, description: 'Module HOUETOR — enrobe le bloc d\'une ref stable (ex: annonces)' },
      position: { type: 'string', required: false, description: 'start | end | before | after (défaut: end)' },
      anchor_ref: { type: 'string', required: false, description: 'Ref HWC d\'un bloc existant (obligatoire pour before/after)' },
      anchor_index: { type: 'string', required: false, description: 'Index de bloc alternatif à anchor_ref' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire (ref simulée)' },
    },
  },
  {
    name: 'update_block_content',
    description: "Modifier le contenu d'un bloc existant (par ref HWC prioritaire ou par index) — CAS attendu",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'Ref HWC du bloc (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'Index du bloc (si pas de ref)' },
      new_content: { type: 'string', required: true, description: 'Nouveau contenu du bloc' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire' },
    },
  },
  {
    name: 'delete_block',
    description: "Supprimer un bloc (par ref HWC prioritaire ou par index)",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'Ref HWC du bloc (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'Index du bloc (si pas de ref)' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire' },
    },
  },
  {
    name: 'update_blocks',
    description: "Mettre à jour plusieurs blocs d'une page en UNE révision atomique (all-or-nothing, max 50, compte 1 écriture rate limit) — CAS expected_hash global",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      updates: { type: 'array', required: true, description: 'Tableau [{ref|block_index, new_content}, ...] — chaque entrée cible un bloc par ref HWC (prioritaire) ou index' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS, issu de get_page_blocks)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider toutes les cibles sans rien écrire' },
    },
  },
  {
    name: 'transform_block',
    description: "Transformer un bloc existant vers un autre type (contenu texte préservé, ref HWC conservée). Types supportés : core/paragraph, core/heading, core/quote, core/list, core/code, core/preformatted, core/pullquote",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'Ref HWC du bloc (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'Index du bloc (si pas de ref)' },
      target_block_name: { type: 'string', required: true, description: 'Type cible (blocs de texte uniquement, ex: core/heading)' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS, issu de get_page_blocks)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire (aucune révision, aucun audit)' },
    },
  },
  {
    name: 'move_block',
    description: "Déplacer un bloc existant (par ref HWC prioritaire ou par index) vers start | end | before | after (ancre par anchor_ref/anchor_index). Sans effet si déjà en place. CAS + dry_run",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'Ref HWC du bloc à déplacer (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'Index du bloc à déplacer (si pas de ref)' },
      position: { type: 'string', required: true, description: 'start | end | before | after' },
      anchor_ref: { type: 'string', required: false, description: 'Ref HWC de l\'ancre (obligatoire pour before/after)' },
      anchor_index: { type: 'string', required: false, description: 'Index de l\'ancre alternatif à anchor_ref' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire (aucune révision, aucun audit)' },
    },
  },
  {
    name: 'duplicate_block',
    description: "Dupliquer un bloc existant (par ref HWC prioritaire ou par index) juste après lui ; refs HWC de la copie régénérées en profondeur (préfixe module conservé). CAS + dry_run",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'Ref HWC du bloc à dupliquer (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'Index du bloc à dupliquer (si pas de ref)' },
      module: { type: 'string', required: false, description: 'Module HOUETOR — attribue une ref stable à la copie si la source n\'en a pas' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire (ref simulée)' },
    },
  },
  {
    name: 'wrap_block',
    description: "Enrober un bloc (ou une plage contiguë start→end par ref|block_index + end_ref|end_index) dans un core/group ; ref de groupe si module fourni. Plage inversée refusée. CAS + dry_run",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'Ref HWC du premier bloc de la plage (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'Index du premier bloc de la plage (si pas de ref)' },
      end_ref: { type: 'string', required: false, description: 'Ref HWC du dernier bloc de la plage (plage > 1 bloc)' },
      end_index: { type: 'string', required: false, description: 'Index du dernier bloc de la plage (plage > 1 bloc)' },
      module: { type: 'string', required: false, description: 'Module HOUETOR — attribue une ref stable au groupe' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire (aucune révision, aucun audit)' },
    },
  },
  {
    name: 'unwrap_block',
    description: "Dégrouper un bloc core/group (par ref HWC prioritaire ou par index) : ses enfants sont promus à la racine à sa place. Seul un core/group peut être dégroupé. CAS + dry_run",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress' },
      ref: { type: 'string', required: false, description: 'Ref HWC du groupe (prioritaire sur block_index)' },
      block_index: { type: 'string', required: false, description: 'Index du groupe (si pas de ref)' },
      expected_hash: { type: 'string', required: false, description: 'md5 du contenu attendu (CAS)' },
      dry_run: { type: 'boolean', required: false, description: 'true = valider sans rien écrire (aucune révision, aucun audit)' },
    },
  },
  {
    name: 'export_to_wordpress',
    description: "Exporter du contenu vers WordPress (upload d'images puis injection)",
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {
      page_id: { type: 'string', required: true, description: 'ID de la page WordPress cible' },
      html: { type: 'string', required: true, description: 'Contenu HTML Gutenberg complet' },
      images: { type: 'array', required: false, description: "URLs d'images à uploader vers la médiathèque WP" },
      module: { type: 'string', required: true, description: 'Module HOUETOR (annonces, produits, formations, custom)' },
    },
  },
  {
    name: 'list_connected_sites',
    description: 'Lister les sites WordPress connectés (lab : le site configuré via env WORDPRESS_URL)',
    profiles: ['ONG', 'BOUTIQUE', 'COACH', 'MARKETING', 'CM'],
    params: {},
  },
]
