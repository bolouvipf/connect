// Traduction des erreurs REST houetor-connect en messages actionnables pour l'agent.
// Portage direct de houetor-mcp/src/error-translator.ts (v2.6.0, testé dans le lab).
// Codes observés dans le lab (série V2) : 401 unauthorized, 404 anchor_not_found/block_not_found,
// 409 error_conflict (CAS), 429 rate_limited (10 écritures/60s par page), 400 validation,
// 400 block_legacy (tier policy : bloc obsolète refusé à la création + suggestion).

export interface TranslatedError {
  status: number
  code: string
  message: string
}

export function translateError(status: number, data: any, fallback: string): TranslatedError {
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

  return { status, code, message: `${raw}` }
}
