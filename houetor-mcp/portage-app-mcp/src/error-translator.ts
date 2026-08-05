// Traduction des erreurs REST houetor-connect en messages actionnables pour l'agent.
// Portage direct de houetor-mcp/src/error-translator.ts (v2.8.0, testé dans le lab).
// Codes observés dans le lab (série V2) : 401 unauthorized, 404 anchor_not_found/block_not_found,
// 409 error_conflict (CAS), 429 rate_limited (10 écritures/60s par page), 400 validation,
// 400 block_legacy (tier policy : bloc obsolète refusé à la création + suggestion).
// 2.8.0 : blocs imbriqués (conteneur refusé en écriture/transform, bloc imbriqué introuvable).

export interface TranslatedError {
  status: number
  code: string
  message: string
}

export interface RestErrorData {
  code?: string
  message?: string
  data?: { block_name?: string; suggested_block?: string }
}

export function translateError(status: number, data: RestErrorData, fallback: string): TranslatedError {
  const code = typeof data?.code === 'string' ? data.code : 'error'
  const raw = typeof data?.message === 'string' ? data.message : fallback

  if (status === 401 || status === 403) {
    return {
      status,
      code: 'unauthorized',
      message:
        `Jeton plugin invalide (${raw}). Vérifiez le token du site connecté : ` +
        `il doit être celui retourné par /pages avec le header X-Houetor-Token attendu.`,
    }
  }

  if (status === 409 && code === 'error_conflict') {
    return {
      status,
      code,
      message:
        `Conflit CAS : le contenu de la page a changé depuis votre dernière lecture ` +
        `(${raw}). Re-lisez la page (get_page_blocks) pour obtenir un content_md5/expected_hash à jour, puis réessayez.`,
    }
  }

  if (status === 429 && code === 'rate_limited') {
    return {
      status,
      code,
      message:
        `Limite d'écriture atteinte : 10 écritures max / 60 s sur cette page ` +
        `(${raw}). Patientez ~60 s ou regroupez vos écritures avec update_blocks (batch atomique).`,
    }
  }

  if (status === 404 && (code === 'anchor_not_found' || code === 'block_not_found')) {
    return {
      status,
      code,
      message:
        `Cible introuvable (${raw}). Relisez la page (get_page_blocks) : les refs HWC et index ` +
        `de la page ont peut-être changé depuis votre dernière lecture.`,
    }
  }

  if (status === 400 && code === 'block_legacy') {
    const blockName = typeof data?.data?.block_name === 'string' ? data.data.block_name : ''
    const suggested = typeof data?.data?.suggested_block === 'string' ? data.data.suggested_block : ''
    return {
      status,
      code,
      message:
        `Bloc "${blockName}" refusé par la tier policy (obsolète ou non supporté à la création) : ` +
        `${raw} Recréez le bloc avec "${suggested}" à la place (même contenu, type supporté) ` +
        `pour exécuter la demande sans erreur.`,
    }
  }

  if (status === 400 && code === 'wrap_failed' && raw.includes('plage invalide')) {
    return {
      status,
      code,
      message:
        `Plage de wrap invalide : le bloc de fin précède le bloc de départ (${raw}). ` +
        `Passez start (ref|block_index) puis end (end_ref|end_index) dans l'ordre d'affichage ` +
        `(index croissants, d'après get_page_blocks) et réessayez.`,
    }
  }

  if (status === 400 && code === 'unwrap_failed' && raw.includes("n'est pas un groupe")) {
    return {
      status,
      code,
      message:
        `Dégroupage refusé : le bloc ciblé n'est pas un core/group (${raw}). ` +
        `Ciblez un bloc core/group (get_page_blocks) ou créez le groupe d'abord via wrap_block, puis réessayez.`,
    }
  }

  // 2.8.0 — blocs imbriqués
  if (status === 400 && raw.includes('conteneur') && raw.includes('blocs enfants')) {
    return {
      status,
      code,
      message:
        `Le bloc ciblé est un conteneur (il a des blocs enfants) — impossible d'y écrire du contenu directement (${raw}). ` +
        `Utilisez get_page_blocks pour lister ses enfants (champ parent_ref) et ciblez l'un d'eux par sa propre ref/index.`,
    }
  }

  if (status === 404 && raw.includes('introuvable') && raw.includes('imbriqu')) {
    return {
      status,
      code,
      message:
        `Bloc imbriqué introuvable (${raw}). Relisez la page (get_page_blocks) — elle expose maintenant tous les blocs à tous les niveaux avec parent_ref, depth, has_children. ` +
        `Ciblez le bloc par sa ref ou son index global.`,
    }
  }

  if (status === 400 && raw.includes('conteneur') && raw.includes('ne peut pas être transformé')) {
    return {
      status,
      code,
      message:
        `Le bloc ciblé est un conteneur (blocs enfants) et ne peut pas être transformé directement (${raw}). ` +
        `Ciblez l'un de ses enfants via get_page_blocks (parent_ref) pour le transformer.`,
    }
  }

  return { status, code, message: `${raw}` }
}
