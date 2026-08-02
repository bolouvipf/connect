import { supabase } from '@/lib/supabase-service'
import { translateError } from './error-translator'

export async function dispatch(method: string, params: Record<string, unknown>, userId: string) {
  switch (method) {
    case 'create_annonce':
      return createAnnonce(params, userId)
    case 'update_annonce':
      return updateAnnonce(params, userId)
    case 'delete_annonce':
      return deleteAnnonce(params, userId)
    case 'list_contenu':
      return listContenu(params, userId)
    case 'create_formation':
      return createFormation(params, userId)
    case 'update_formation':
      return updateFormation(params, userId)
    case 'delete_formation':
      return deleteFormation(params, userId)
    case 'create_produit':
      return createProduit(params, userId)
    case 'update_produit':
      return updateProduit(params, userId)
    case 'delete_produit':
      return deleteProduit(params, userId)
    case 'get_wp_pages':
      return getWpPages(userId)
    case 'inject_page':
      return injectPage(params, userId)
    case 'uninject_page':
      return uninjectPage(params, userId)
    case 'get_page_blocks':
      return getPageBlocks(params, userId)
    case 'create_block':
      return createBlock(params, userId)
    case 'update_block_content':
      return updateBlockContent(params, userId)
    case 'update_blocks':
      return updateBlocks(params, userId)
    case 'delete_block':
      return deleteBlock(params, userId)
    case 'transform_block':
      return transformBlock(params, userId)
    case 'move_block':
      return moveBlock(params, userId)
    case 'duplicate_block':
      return duplicateBlock(params, userId)
    case 'wrap_block':
      return wrapBlock(params, userId)
    case 'unwrap_block':
      return unwrapBlock(params, userId)
    case 'get_wp_menus':
      return getWpMenus(userId)
    case 'list_connected_sites':
      return listConnectedSites(userId)
    case 'export_to_wordpress':
      return exportToWordpress(params, userId)
    case 'get_profil':
      return getProfil(userId)
    case 'update_profil':
      return updateProfil(params, userId)
    case 'get_stats':
      return getStats(userId)
    case 'list_commandes':
      return listCommandes(userId)
    case 'update_commande':
      return updateCommande(params, userId)
    case 'send_notification':
      return sendNotification(params, userId)
    default:
      return { success: false, error: 'Méthode inconnue' }
  }
}

async function createAnnonce(params: Record<string, unknown>, userId: string) {
  const { titre, contenu, statut } = params
  if (!titre) return { success: false, error: 'titre requis' }

  const { data, error } = await supabase()
    .from('annonces')
    .insert({ user_id: userId, titre, contenu, statut: statut || 'brouillon' })
    .select()
    .single()

  if (error) return { success: false, error: error.message }
  return { success: true, data, message: 'Annonce créée ✓' }
}

async function updateAnnonce(params: Record<string, unknown>, userId: string) {
  const { id, titre, contenu, statut } = params
  if (!id) return { success: false, error: 'id requis' }

  const updates: Record<string, unknown> = {}
  if (titre !== undefined) updates.titre = titre
  if (contenu !== undefined) updates.contenu = contenu
  if (statut !== undefined) updates.statut = statut

  const { data, error } = await supabase()
    .from('annonces')
    .update(updates)
    .eq('id', id)
    .eq('user_id', userId)
    .select()
    .single()

  if (error) return { success: false, error: error.message }

  const hasContentUpdate = titre !== undefined || contenu !== undefined
  if (hasContentUpdate && data?.wp_block_id && data?.wp_page_id && data?.wp_module && data?.wp_site_id) {
    const { data: site } = await supabase()
      .from('connected_sites')
      .select('url, token')
      .eq('id', data.wp_site_id)
      .single()

    if (site) {
      const html = `<div style="background:#f0fdf4;padding:20px;border-radius:8px"><h3>${escHtml(data.titre ?? '')}</h3><p>${escHtml(data.contenu ?? '')}</p></div>`

      try {
        const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Houetor-Token': site.token,
          },
          body: JSON.stringify({
            page_id: data.wp_page_id,
            content: html,
            module: data.wp_module,
            block_id: data.wp_block_id,
          }),
        })

        if (!res.ok) {
          const err = await res.text()
          return { success: true, data, message: `Annonce mise à jour, mais le site n'a pas pu être synchronisé — contenu affiché potentiellement obsolète. Erreur: ${err}` }
        }
      } catch {
        return { success: true, data, message: 'Annonce mise à jour, mais le site n\'a pas pu être synchronisé — contenu affiché potentiellement obsolète.' }
      }
    }
  }

  return { success: true, data, message: 'Annonce mise à jour ✓' }
}

function escHtml(s: string) {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

// --- Helpers partagés pour les appels plugin (portage CRUD bloc v2.4.0) ---

async function resolveSite(siteId: unknown, userId: string) {
  const { data: site, error } = await supabase()
    .from('connected_sites')
    .select('url, token')
    .eq('id', String(siteId))
    .eq('user_id', userId)
    .single()

  if (error || !site) return null
  return site
}

async function pluginRequest(
  site: { url: string; token: string },
  path: string,
  opts: { method: string; body?: Record<string, unknown> },
) {
  const res = await fetch(`${site.url}/wp-json/houetor/v1${path}`, {
    method: opts.method,
    headers: {
      'Content-Type': 'application/json',
      'X-Houetor-Token': site.token,
    },
    body: opts.body !== undefined ? JSON.stringify(opts.body) : undefined,
  })

  let data: any = null
  try {
    data = await res.json()
  } catch {
    data = null
  }

  if (!res.ok) {
    const t = translateError(res.status, data, res.statusText)
    return { success: false, error: `${t.message}` }
  }

  return { success: true, data }
}

function boolParam(params: Record<string, unknown>, key: string): boolean | undefined {
  const v = params[key]
  if (v === undefined) return undefined
  if (v === true || v === 'true' || v === 1 || v === '1') return true
  return false
}

function requireOneOf(params: Record<string, unknown>, keys: string[]): string | null {
  for (const key of keys) {
    if (params[key] !== undefined && params[key] !== null && params[key] !== '') return key
  }
  return null
}

function renderFormationHtml(formation: { titre: string; description: string; prix: number | null; image_url: string | null }) {
  const imageHtml = formation.image_url ? `<img src="${escHtml(formation.image_url)}" alt="${escHtml(formation.titre)}" class="formation-image" style="max-width:100%;border-radius:8px;margin-bottom:12px"/>` : ''
  const prixHtml = formation.prix != null ? `<p class="formation-prix" style="font-weight:700;color:#16a34a">${escHtml(String(formation.prix))} FCFA</p>` : ''
  return `<div class="wp-block-group houetor-formation" style="background:#f0fdf4;padding:20px;border-radius:8px">${imageHtml}<h3 class="wp-block-heading">${escHtml(formation.titre)}</h3><div class="formation-description">${escHtml(formation.description)}</div>${prixHtml}</div>`
}

async function deleteAnnonce(params: Record<string, unknown>, userId: string) {
  const { id } = params
  if (!id) return { success: false, error: 'id requis' }

  const { data: annonce, error: fetchError } = await supabase()
    .from('annonces')
    .select('wp_site_id, wp_page_id, wp_module, wp_block_id')
    .eq('id', id)
    .eq('user_id', userId)
    .single()

  if (fetchError || !annonce) return { success: false, error: 'Annonce introuvable' }

  if (annonce.wp_block_id && annonce.wp_site_id && annonce.wp_page_id) {
    const { data: site } = await supabase()
      .from('connected_sites')
      .select('url, token')
      .eq('id', annonce.wp_site_id)
      .single()

    if (site) {
      try {
        const res = await fetch(`${site.url}/wp-json/houetor/v1/uninject`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Houetor-Token': site.token,
          },
          body: JSON.stringify({
            page_id: annonce.wp_page_id,
            module: annonce.wp_module,
            block_id: annonce.wp_block_id,
          }),
        })

        if (!res.ok) {
          const err = await res.text()
          return { success: false, error: `Bloc WordPress non retiré, annonce conservée. Erreur: ${err}` }
        }
      } catch (err) {
        return { success: false, error: `Bloc WordPress non retiré, annonce conservée. Erreur réseau: ${String(err)}` }
      }
    }
  }

  const { error } = await supabase()
    .from('annonces')
    .delete()
    .eq('id', id)
    .eq('user_id', userId)

  if (error) return { success: false, error: error.message }
  return { success: true, data: null, message: 'Annonce supprimée ✓ (bloc WordPress retiré)' }
}

async function listContenu(params: Record<string, unknown>, userId: string) {
  const type = params.type as string

  let table: string
  switch (type) {
    case 'annonces':
      table = 'annonces'
      break
    case 'formations':
      table = 'formations'
      break
    case 'posts':
      table = 'cm_posts'
      break
    case 'produits':
      table = 'produits'
      break
    case 'campagnes':
      table = 'campagnes'
      break
    default:
      return { success: false, error: `Type inconnu : ${type}` }
  }

  const { data, error } = await supabase()
    .from(table)
    .select('*')
    .eq('user_id', userId)
    .order('created_at', { ascending: false })

  if (error) return { success: false, error: error.message }
  return { success: true, data }
}

async function createFormation(params: Record<string, unknown>, userId: string) {
  const { titre, description, prix, image_url, site_id, page_id, position } = params
  if (!titre) return { success: false, error: 'titre requis' }
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }

  const { data: site, error: siteError } = await supabase()
    .from('connected_sites')
    .select('url, token')
    .eq('id', site_id)
    .eq('user_id', userId)
    .single()

  if (siteError || !site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  const { data: formation, error } = await supabase()
    .from('formations')
    .insert({ user_id: userId, titre, description, prix: prix ?? null, image_url: image_url ?? null })
    .select()
    .single()

  if (error) return { success: false, error: error.message }

  const html = renderFormationHtml({ titre: titre as string, description: (description as string) ?? '', prix: prix as number | null, image_url: image_url as string | null })

  try {
    const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Houetor-Token': site.token,
      },
      body: JSON.stringify({
        page_id: parseInt(page_id as string),
        content: html,
        module: 'formations',
        position: (position as string) || 'append',
      }),
    })

    if (!res.ok) {
      const err = await res.text()
      return { success: true, data: formation, message: `Formation créée, mais injection WordPress échouée: ${err}` }
    }

    const wpData = await res.json()

    await supabase()
      .from('formations')
      .update({
        wp_site_id: site_id,
        wp_page_id: parseInt(page_id as string),
        wp_module: 'formations',
        wp_block_id: wpData.block_id,
        wp_injected_at: new Date().toISOString(),
      })
      .eq('id', formation.id)
      .eq('user_id', userId)

    return { success: true, data: formation, message: 'Formation créée et injectée dans WordPress ✓' }
  } catch (err) {
    return { success: true, data: formation, message: `Formation créée, mais site WordPress injoignable: ${String(err)}` }
  }
}

async function updateFormation(params: Record<string, unknown>, userId: string) {
  const { id, titre, description, prix, image_url } = params
  if (!id) return { success: false, error: 'id requis' }

  const { data: existing, error: fetchError } = await supabase()
    .from('formations')
    .select('wp_site_id, wp_page_id, wp_module, wp_block_id')
    .eq('id', id)
    .eq('user_id', userId)
    .single()

  if (fetchError || !existing) return { success: false, error: 'Formation introuvable' }

  const updates: Record<string, unknown> = {}
  if (titre !== undefined) updates.titre = titre
  if (description !== undefined) updates.description = description
  if (prix !== undefined) updates.prix = prix
  if (image_url !== undefined) updates.image_url = image_url

  const { data, error } = await supabase()
    .from('formations')
    .update(updates)
    .eq('id', id)
    .eq('user_id', userId)
    .select()
    .single()

  if (error) return { success: false, error: error.message }

  const contentChanged = titre !== undefined || description !== undefined || prix !== undefined || image_url !== undefined
  if (contentChanged && existing.wp_block_id && existing.wp_site_id && existing.wp_page_id) {
    const { data: site } = await supabase()
      .from('connected_sites')
      .select('url, token')
      .eq('id', existing.wp_site_id)
      .single()

    if (site) {
      const html = renderFormationHtml({
        titre: (data.titre ?? '') as string,
        description: (data.description ?? '') as string,
        prix: data.prix as number | null,
        image_url: data.image_url as string | null,
      })

      try {
        const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Houetor-Token': site.token,
          },
          body: JSON.stringify({
            page_id: existing.wp_page_id,
            content: html,
            module: 'formations',
            block_id: existing.wp_block_id,
          }),
        })

        if (!res.ok) {
          const err = await res.text()
          return { success: true, data, message: `Formation mise à jour, mais synchronisation WordPress échouée — contenu affiché potentiellement obsolète. Erreur: ${err}` }
        }

        await supabase()
          .from('formations')
          .update({ wp_injected_at: new Date().toISOString() })
          .eq('id', id)
      } catch {
        return { success: true, data, message: 'Formation mise à jour, mais site WordPress injoignable — contenu affiché potentiellement obsolète.' }
      }
    }
  }

  return { success: true, data, message: 'Formation mise à jour ✓' }
}

async function deleteFormation(params: Record<string, unknown>, userId: string) {
  const { id } = params
  if (!id) return { success: false, error: 'id requis' }

  const { data: formation, error: fetchError } = await supabase()
    .from('formations')
    .select('wp_site_id, wp_page_id, wp_module, wp_block_id')
    .eq('id', id)
    .eq('user_id', userId)
    .single()

  if (fetchError || !formation) return { success: false, error: 'Formation introuvable' }

  if (formation.wp_block_id && formation.wp_site_id && formation.wp_page_id) {
    const { data: site } = await supabase()
      .from('connected_sites')
      .select('url, token')
      .eq('id', formation.wp_site_id)
      .single()

    if (site) {
      try {
        const res = await fetch(`${site.url}/wp-json/houetor/v1/uninject`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Houetor-Token': site.token,
          },
          body: JSON.stringify({
            page_id: formation.wp_page_id,
            module: formation.wp_module,
            block_id: formation.wp_block_id,
          }),
        })

        if (!res.ok) {
          const err = await res.text()
          return { success: false, error: `Bloc WordPress non retiré, formation conservée. Erreur: ${err}` }
        }
      } catch (err) {
        return { success: false, error: `Bloc WordPress non retiré, formation conservée. Erreur réseau: ${String(err)}` }
      }
    }
  }

  const { error } = await supabase()
    .from('formations')
    .delete()
    .eq('id', id)
    .eq('user_id', userId)

  if (error) return { success: false, error: error.message }
  return { success: true, data: null, message: 'Formation supprimée ✓ (bloc WordPress retiré)' }
}

function renderProduitHtml(produit: { nom: string; description: string; prix: number | null; image_url: string | null }) {
  const imageHtml = produit.image_url ? `<img src="${escHtml(produit.image_url)}" alt="${escHtml(produit.nom)}" class="produit-image" style="max-width:100%;border-radius:8px;margin-bottom:12px"/>` : ''
  const prixHtml = produit.prix != null ? `<p class="produit-prix" style="font-weight:700;color:#16a34a">${escHtml(String(produit.prix))} FCFA</p>` : ''
  return `<div class="wp-block-group houetor-produit" style="background:#f0fdf4;padding:20px;border-radius:8px">${imageHtml}<h3 class="wp-block-heading">${escHtml(produit.nom)}</h3><div class="produit-description">${escHtml(produit.description)}</div>${prixHtml}</div>`
}

async function createProduit(params: Record<string, unknown>, userId: string) {
  const { nom, description, prix, image_url, site_id, page_id, position } = params
  if (!nom) return { success: false, error: 'nom requis' }
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }

  const { data: site, error: siteError } = await supabase()
    .from('connected_sites')
    .select('url, token')
    .eq('id', site_id)
    .eq('user_id', userId)
    .single()

  if (siteError || !site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  const { data: produit, error } = await supabase()
    .from('produits')
    .insert({ user_id: userId, nom, description, prix: prix ?? null, image_url: image_url ?? null, stock: null, actif: true, categorie: null })
    .select()
    .single()

  if (error) return { success: false, error: error.message }

  const html = renderProduitHtml({ nom: nom as string, description: (description as string) ?? '', prix: prix as number | null, image_url: image_url as string | null })

  try {
    const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Houetor-Token': site.token,
      },
      body: JSON.stringify({
        page_id: parseInt(page_id as string),
        content: html,
        module: 'produits',
        position: (position as string) || 'append',
      }),
    })

    if (!res.ok) {
      const err = await res.text()
      return { success: true, data: produit, message: `Produit créé, mais injection WordPress échouée: ${err}` }
    }

    const wpData = await res.json()

    await supabase()
      .from('produits')
      .update({
        wp_site_id: site_id,
        wp_page_id: parseInt(page_id as string),
        wp_module: 'produits',
        wp_block_id: wpData.block_id,
        wp_injected_at: new Date().toISOString(),
      })
      .eq('id', produit.id)
      .eq('user_id', userId)

    return { success: true, data: produit, message: 'Produit créé et injecté dans WordPress ✓' }
  } catch (err) {
    return { success: true, data: produit, message: `Produit créé, mais site WordPress injoignable: ${String(err)}` }
  }
}

async function updateProduit(params: Record<string, unknown>, userId: string) {
  const { produit_id, nom, description, prix, image_url } = params
  if (!produit_id) return { success: false, error: 'produit_id requis' }

  const { data: existing, error: fetchError } = await supabase()
    .from('produits')
    .select('wp_site_id, wp_page_id, wp_module, wp_block_id')
    .eq('id', produit_id)
    .eq('user_id', userId)
    .single()

  if (fetchError || !existing) return { success: false, error: 'Produit introuvable' }

  const updates: Record<string, unknown> = {}
  if (nom !== undefined) updates.nom = nom
  if (description !== undefined) updates.description = description
  if (prix !== undefined) updates.prix = prix
  if (image_url !== undefined) updates.image_url = image_url

  const { data, error } = await supabase()
    .from('produits')
    .update(updates)
    .eq('id', produit_id)
    .eq('user_id', userId)
    .select()
    .single()

  if (error) return { success: false, error: error.message }

  const contentChanged = nom !== undefined || description !== undefined || prix !== undefined || image_url !== undefined
  if (contentChanged && existing.wp_block_id && existing.wp_site_id && existing.wp_page_id) {
    const { data: site } = await supabase()
      .from('connected_sites')
      .select('url, token')
      .eq('id', existing.wp_site_id)
      .single()

    if (site) {
      const html = renderProduitHtml({
        nom: (data.nom ?? '') as string,
        description: (data.description ?? '') as string,
        prix: data.prix as number | null,
        image_url: data.image_url as string | null,
      })

      try {
        const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Houetor-Token': site.token,
          },
          body: JSON.stringify({
            page_id: existing.wp_page_id,
            content: html,
            module: 'produits',
            block_id: existing.wp_block_id,
          }),
        })

        if (!res.ok) {
          const err = await res.text()
          return { success: true, data, message: `Produit mis à jour, mais synchronisation WordPress échouée — contenu affiché potentiellement obsolète. Erreur: ${err}` }
        }

        await supabase()
          .from('produits')
          .update({ wp_injected_at: new Date().toISOString() })
          .eq('id', produit_id)
      } catch {
        return { success: true, data, message: 'Produit mis à jour, mais site WordPress injoignable — contenu affiché potentiellement obsolète.' }
      }
    }
  }

  return { success: true, data, message: 'Produit mis à jour ✓' }
}

async function deleteProduit(params: Record<string, unknown>, userId: string) {
  const { produit_id } = params
  if (!produit_id) return { success: false, error: 'produit_id requis' }

  const { data: produit, error: fetchError } = await supabase()
    .from('produits')
    .select('wp_site_id, wp_page_id, wp_module, wp_block_id')
    .eq('id', produit_id)
    .eq('user_id', userId)
    .single()

  if (fetchError || !produit) return { success: false, error: 'Produit introuvable' }

  if (produit.wp_block_id && produit.wp_site_id && produit.wp_page_id) {
    const { data: site } = await supabase()
      .from('connected_sites')
      .select('url, token')
      .eq('id', produit.wp_site_id)
      .single()

    if (site) {
      try {
        const res = await fetch(`${site.url}/wp-json/houetor/v1/uninject`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-Houetor-Token': site.token,
          },
          body: JSON.stringify({
            page_id: produit.wp_page_id,
            module: produit.wp_module,
            block_id: produit.wp_block_id,
          }),
        })

        if (!res.ok) {
          const err = await res.text()
          return { success: false, error: `Bloc WordPress non retiré, produit conservé. Erreur: ${err}` }
        }
      } catch (err) {
        return { success: false, error: `Bloc WordPress non retiré, produit conservé. Erreur réseau: ${String(err)}` }
      }
    }
  }

  const { error } = await supabase()
    .from('produits')
    .delete()
    .eq('id', produit_id)
    .eq('user_id', userId)

  if (error) return { success: false, error: error.message }
  return { success: true, data: null, message: 'Produit supprimé ✓ (bloc WordPress retiré)' }
}

async function getWpPages(userId: string) {
  const { data: sites, error: sitesError } = await supabase()
    .from('connected_sites')
    .select('id, nom, url, token')
    .eq('user_id', userId)

  if (sitesError) return { success: false, error: sitesError.message }

  const results = []
  for (const site of sites) {
    try {
      const res = await fetch(`${site.url}/wp-json/houetor/v1/pages`, {
        headers: { 'X-Houetor-Token': site.token },
      })
      if (!res.ok) continue
      const pages = await res.json()
      results.push({ site_id: site.id, site_nom: site.nom, pages })
    } catch {
      continue
    }
  }

  return { success: true, data: results }
}

async function injectPage(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id, html, module, annonce_id } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id || !html) return { success: false, error: 'page_id et html requis' }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  const result = await pluginRequest(site, '/inject', {
    method: 'POST',
    body: {
      page_id,
      content: html,
      module: module || 'annonces',
      position: params.position ? String(params.position) : 'append',
      block_id: params.block_id ? String(params.block_id) : undefined,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })

  if (!result.success) return result

  const data = result.data

  if (annonce_id) {
    await supabase()
      .from('annonces')
      .update({
        wp_site_id: String(site_id),
        wp_page_id: parseInt(page_id as string),
        wp_module: (module as string) || 'annonces',
        wp_block_id: data.block_id,
        wp_injected_at: new Date().toISOString(),
      })
      .eq('id', annonce_id)
      .eq('user_id', userId)
  }

  return { success: true, data, message: `Contenu injecté dans la page ✓` }
}

async function uninjectPage(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id, module, block_id } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id || !module || !block_id) return { success: false, error: 'page_id, module et block_id requis' }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/uninject', {
    method: 'POST',
    body: {
      page_id,
      module,
      block_id,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function getPageBlocks(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, `/page-blocks?page_id=${encodeURIComponent(String(page_id))}`, { method: 'GET' })
}

async function createBlock(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id, block_name } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id || !block_name) return { success: false, error: 'page_id et block_name requis' }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/blocks', {
    method: 'POST',
    body: {
      page_id,
      block_name,
      content: params.content ? String(params.content) : undefined,
      module: params.module ? String(params.module) : undefined,
      position: params.position ? String(params.position) : undefined,
      anchor_ref: params.anchor_ref ? String(params.anchor_ref) : undefined,
      anchor_index: params.anchor_index ? String(params.anchor_index) : undefined,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function updateBlockContent(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id, new_content } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id || !new_content) return { success: false, error: 'page_id et new_content requis' }
  if (!requireOneOf(params, ['ref', 'block_index'])) {
    return { success: false, error: 'ref ou block_index requis' }
  }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/block-content', {
    method: 'PATCH',
    body: {
      page_id,
      ref: params.ref ? String(params.ref) : undefined,
      block_index: params.block_index !== undefined && params.block_index !== '' ? String(params.block_index) : undefined,
      new_content,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function updateBlocks(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }

  const updates = params.updates
  if (!Array.isArray(updates) || updates.length === 0) {
    return { success: false, error: 'updates requis : tableau non vide de { ref|block_index, new_content }' }
  }
  for (const u of updates) {
    const entry = u as Record<string, unknown>
    if (!entry || typeof entry !== 'object') {
      return { success: false, error: 'updates invalide : chaque élément doit être un objet { ref|block_index, new_content }' }
    }
    if (entry.new_content === undefined || entry.new_content === '') {
      return { success: false, error: 'new_content requis dans chaque update' }
    }
    if (!entry.ref && (entry.block_index === undefined || entry.block_index === '')) {
      return { success: false, error: 'ref ou block_index requis dans chaque update' }
    }
  }
  if (updates.length > 50) {
    return { success: false, error: 'updates trop grand : 50 max par appel' }
  }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/blocks/batch-update', {
    method: 'POST',
    body: {
      page_id,
      updates: updates.map((u) => {
        const entry = u as Record<string, unknown>
        return {
          ref: entry.ref ? String(entry.ref) : undefined,
          block_index: entry.block_index !== undefined && entry.block_index !== '' ? String(entry.block_index) : undefined,
          new_content: String(entry.new_content),
        }
      }),
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function deleteBlock(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }
  if (!requireOneOf(params, ['ref', 'block_index'])) {
    return { success: false, error: 'ref ou block_index requis' }
  }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/blocks', {
    method: 'DELETE',
    body: {
      page_id,
      ref: params.ref ? String(params.ref) : undefined,
      block_index: params.block_index !== undefined && params.block_index !== '' ? String(params.block_index) : undefined,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function transformBlock(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id, target_block_name } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }
  if (!target_block_name) return { success: false, error: 'target_block_name requis (ex: core/heading)' }
  if (!requireOneOf(params, ['ref', 'block_index'])) {
    return { success: false, error: 'ref ou block_index requis' }
  }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/blocks/transform', {
    method: 'POST',
    body: {
      page_id,
      ref: params.ref ? String(params.ref) : undefined,
      block_index: params.block_index !== undefined && params.block_index !== '' ? String(params.block_index) : undefined,
      target_block_name: String(target_block_name),
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function moveBlock(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id, position } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }
  if (!position) return { success: false, error: 'position requis (start | end | before | after)' }
  if (!requireOneOf(params, ['ref', 'block_index'])) {
    return { success: false, error: 'ref ou block_index requis' }
  }
  if ((position === 'before' || position === 'after') && !requireOneOf(params, ['anchor_ref', 'anchor_index'])) {
    return { success: false, error: 'anchor_ref ou anchor_index requis pour before/after' }
  }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/blocks/move', {
    method: 'POST',
    body: {
      page_id,
      ref: params.ref ? String(params.ref) : undefined,
      block_index: params.block_index !== undefined && params.block_index !== '' ? String(params.block_index) : undefined,
      position: String(position),
      anchor_ref: params.anchor_ref ? String(params.anchor_ref) : undefined,
      anchor_index: params.anchor_index !== undefined && params.anchor_index !== '' ? String(params.anchor_index) : undefined,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function duplicateBlock(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }
  if (!requireOneOf(params, ['ref', 'block_index'])) {
    return { success: false, error: 'ref ou block_index requis' }
  }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/blocks/duplicate', {
    method: 'POST',
    body: {
      page_id,
      ref: params.ref ? String(params.ref) : undefined,
      block_index: params.block_index !== undefined && params.block_index !== '' ? String(params.block_index) : undefined,
      module: params.module ? String(params.module) : undefined,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function wrapBlock(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }
  if (!requireOneOf(params, ['ref', 'block_index'])) {
    return { success: false, error: 'ref ou block_index requis (premier bloc de la plage)' }
  }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/blocks/wrap', {
    method: 'POST',
    body: {
      page_id,
      ref: params.ref ? String(params.ref) : undefined,
      block_index: params.block_index !== undefined && params.block_index !== '' ? String(params.block_index) : undefined,
      end_ref: params.end_ref ? String(params.end_ref) : undefined,
      end_index: params.end_index !== undefined && params.end_index !== '' ? String(params.end_index) : undefined,
      module: params.module ? String(params.module) : undefined,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function unwrapBlock(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id } = params
  if (!site_id) return { success: false, error: 'site_id requis (obtenez-le via list_connected_sites)' }
  if (!page_id) return { success: false, error: 'page_id requis' }
  if (!requireOneOf(params, ['ref', 'block_index'])) {
    return { success: false, error: 'ref ou block_index requis' }
  }

  const site = await resolveSite(site_id, userId)
  if (!site) return { success: false, error: 'site_id invalide ou site non autorisé' }

  return pluginRequest(site, '/blocks/unwrap', {
    method: 'POST',
    body: {
      page_id,
      ref: params.ref ? String(params.ref) : undefined,
      block_index: params.block_index !== undefined && params.block_index !== '' ? String(params.block_index) : undefined,
      expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
      dry_run: boolParam(params, 'dry_run'),
    },
  })
}

async function getWpMenus(userId: string) {
  const { data: sites } = await supabase()
    .from('connected_sites')
    .select('id, nom, url, token')
    .eq('user_id', userId)

  if (!sites) return { success: true, data: [] }

  const results = []
  for (const site of sites) {
    try {
      const res = await fetch(`${site.url}/wp-json/houetor/v1/menus`, {
        headers: { 'X-Houetor-Token': site.token },
      })
      if (!res.ok) continue
      const menus = await res.json()
      results.push({ site_id: site.id, site_nom: site.nom, menus })
    } catch {
      continue
    }
  }

  return { success: true, data: results }
}

async function listConnectedSites(userId: string) {
  const { data, error } = await supabase()
    .from('connected_sites')
    .select('*')
    .eq('user_id', userId)

  if (error) return { success: false, error: error.message }
  return { success: true, data }
}

async function exportToWordpress(params: Record<string, unknown>, userId: string) {
  const { site_id, page_id, html, images, module, annonce_id } = params
  if (!site_id || !page_id || !html) return { success: false, error: 'site_id, page_id et html requis' }
  if (!module) return { success: false, error: 'module requis (ex: annonces, produits, formations, custom)' }

  const { data: site } = await supabase()
    .from('connected_sites')
    .select('url, token')
    .eq('id', site_id)
    .eq('user_id', userId)
    .single()

  if (!site) return { success: false, error: 'Site non trouvé' }

  const uploadedUrls: string[] = []
  const uploadErrors: string[] = []

  if (Array.isArray(images)) {
    for (const [i, imgUrl] of images.entries()) {
      try {
        const imgRes = await fetch(imgUrl as string)
        const blob = await imgRes.blob()
        const formData = new FormData()
        formData.append('file', blob, `image-${i}.jpg`)

        const wpRes = await fetch(`${site.url}/wp-json/houetor/v1/media`, {
          method: 'POST',
          headers: { 'X-Houetor-Token': site.token },
          body: formData,
        })

        if (wpRes.ok) {
          const wpMedia = await wpRes.json()
          uploadedUrls.push(wpMedia.url)
        } else {
          const errText = await wpRes.text()
          uploadErrors.push(`image ${i + 1}: HTTP ${wpRes.status} ${errText}`)
        }
      } catch (err) {
        uploadErrors.push(`image ${i + 1}: ${String(err)}`)
      }
    }
  }

  let finalHtml = html as string
  if (Array.isArray(images) && uploadedUrls.length > 0) {
    for (let i = 0; i < (images as string[]).length; i++) {
      if (uploadedUrls[i]) {
        finalHtml = finalHtml.replace(images[i] as string, uploadedUrls[i])
      }
    }
  }

  try {
    const res = await fetch(`${site.url}/wp-json/houetor/v1/inject`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Houetor-Token': site.token,
      },
      body: JSON.stringify({
        page_id: page_id as string,
        content: finalHtml,
        images: uploadedUrls,
        module: module as string,
      }),
    })

    if (!res.ok) {
      const err = await res.text()
      return { success: false, error: `Erreur injection WP: ${err}` }
    }

    const data = await res.json()

    if (annonce_id) {
      await supabase()
        .from('annonces')
        .update({
          wp_site_id: site_id,
          wp_page_id: parseInt(page_id as string),
          wp_module: module as string,
          wp_block_id: data.block_id,
          wp_injected_at: new Date().toISOString(),
        })
        .eq('id', annonce_id)
        .eq('user_id', userId)
    }

    let uploadMsg = `Images uploadées: ${uploadedUrls.length}`
    if (uploadErrors.length > 0) uploadMsg += `\nÉchecs: ${uploadErrors.join('; ')}`
    return { success: true, data, message: `Exporté vers WordPress ✓\n${uploadMsg}` }
  } catch (err) {
    return { success: false, error: String(err) }
  }
}

async function getProfil(userId: string) {
  const { data, error } = await supabase()
    .from('users')
    .select('*')
    .eq('id', userId)
    .single()

  if (error) return { success: false, error: error.message }
  return { success: true, data }
}

async function updateProfil(params: Record<string, unknown>, userId: string) {
  const updates: Record<string, unknown> = {}
  if (params.full_name !== undefined) updates.full_name = params.full_name
  if (params.company !== undefined) updates.company = params.company
  if (params.phone !== undefined) updates.phone = params.phone

  const { data, error } = await supabase()
    .from('users')
    .update(updates)
    .eq('id', userId)
    .select()
    .single()

  if (error) return { success: false, error: error.message }
  return { success: true, data, message: 'Profil mis à jour ✓' }
}

async function getStats(userId: string) {
  const { data: user } = await supabase().from('users').select('profile_type').eq('id', userId).single()
  const profileType = user?.profile_type

  const stats: Record<string, unknown> = {}

  const { count: annoncesCount } = await supabase().from('annonces').select('*', { count: 'exact', head: true }).eq('user_id', userId)
  stats.total_annonces = annoncesCount ?? 0

  const { count: formationsCount } = await supabase().from('formations').select('*', { count: 'exact', head: true }).eq('user_id', userId)
  stats.total_formations = formationsCount ?? 0

  const { count: produitsCount } = await supabase().from('produits').select('*', { count: 'exact', head: true }).eq('user_id', userId)
  stats.total_produits = produitsCount ?? 0

  const { count: commandesCount } = await supabase().from('commandes').select('*', { count: 'exact', head: true }).eq('user_id', userId)
  stats.total_commandes = commandesCount ?? 0

  const { data: sites } = await supabase().from('connected_sites').select('id').eq('user_id', userId)
  stats.sites_connectes = (sites ?? []).length

  return { success: true, data: stats }
}

async function listCommandes(userId: string) {
  const { data, error } = await supabase()
    .from('commandes')
    .select('*, produits(nom, prix)')
    .eq('user_id', userId)
    .order('created_at', { ascending: false })

  if (error) return { success: false, error: error.message }
  return { success: true, data }
}

async function updateCommande(params: Record<string, unknown>, userId: string) {
  const { id, statut } = params
  if (!id || !statut) return { success: false, error: 'id et statut requis' }

  const { data, error } = await supabase()
    .from('commandes')
    .update({ statut: statut as string })
    .eq('id', id)
    .eq('user_id', userId)
    .select()
    .single()

  if (error) return { success: false, error: error.message }
  return { success: true, data, message: `Commande mise à jour ✓ (${statut})` }
}

async function sendNotification(params: Record<string, unknown>, userId: string) {
  const { sujet, message } = params
  if (!sujet || !message) return { success: false, error: 'sujet et message requis' }

  const { data: user } = await supabase()
    .from('users')
    .select('email')
    .eq('id', userId)
    .single()

  if (!user?.email) return { success: false, error: 'Aucun email trouvé pour ce client' }

  try {
    const { Resend } = await import('resend')
    const resend = new Resend(process.env.RESEND_API_KEY!)
    await resend.emails.send({
      from: 'HOUETOR <contact@houetor.com>',
      to: user.email,
      subject: sujet as string,
      html: `<div style="font-family: DM Sans, sans-serif; max-width: 560px; background: #060D09; color: #E8F5EE; padding: 40px; border-radius: 12px;"><p style="color: #4ADE80; font-weight: 700;">HOUETOR</p><p>${message as string}</p></div>`,
    })
    return { success: true, data: null, message: `Notification envoyée ✓` }
  } catch (err) {
    return { success: false, error: String(err) }
  }
}

export const ALLOWED_METHODS = [
  'create_annonce',
  'update_annonce',
  'delete_annonce',
  'list_contenu',
  'create_formation',
  'update_formation',
  'delete_formation',
  'create_produit',
  'update_produit',
  'delete_produit',
  'get_wp_pages',
  'inject_page',
  'uninject_page',
  'get_page_blocks',
  'create_block',
  'update_block_content',
  'update_blocks',
  'delete_block',
  'transform_block',
  'move_block',
  'duplicate_block',
  'wrap_block',
  'unwrap_block',
  'get_wp_menus',
  'list_connected_sites',
  'export_to_wordpress',
  'get_profil',
  'update_profil',
  'get_stats',
  'list_commandes',
  'update_commande',
  'send_notification',
]
