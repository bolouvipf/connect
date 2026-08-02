import { describe, it, expect } from 'vitest'
import { parseHWT } from '../src/parser.js'
import { translateError } from '../src/error-translator.js'

describe('parseHWT', () => {
  it('parse un token HWT-{profil}-{uuid}', () => {
    expect(parseHWT('HWT-ONG-123e4567-e89b-12d3-a456-426614174000')).toEqual({
      profil: 'ONG',
      uuid: '123e4567-e89b-12d3-a456-426614174000',
    })
  })

  it('parse chaque profil', () => {
    for (const profil of ['ONG', 'BOUTIQUE', 'COACH', 'CM', 'MARKETING']) {
      expect(parseHWT(`HWT-${profil}-abc`)).toEqual({ profil, uuid: 'abc' })
    }
  })

  it('accepte un UUID nu (profil null)', () => {
    expect(parseHWT('123e4567-e89b-12d3-a456-426614174000')).toEqual({
      profil: null,
      uuid: '123e4567-e89b-12d3-a456-426614174000',
    })
  })

  it('rejette les tokens invalides', () => {
    expect(parseHWT('')).toBeNull()
    expect(parseHWT('HWT-XXX-toto')).toBeNull()
    expect(parseHWT('pas-un-uuid')).toBeNull()
  })
})

describe('translateError', () => {
  it('conflit CAS 409 → conseil de relecture', () => {
    const t = translateError(409, { code: 'error_conflict', message: 'le contenu a change' }, 'fallback')
    expect(t.code).toBe('error_conflict')
    expect(t.message).toContain('get_page_blocks')
  })

  it('rate limit 429 → conseil d\u2019attente', () => {
    const t = translateError(429, { code: 'rate_limited', message: 'trop de requetes' }, 'fallback')
    expect(t.message).toContain('60 s')
    expect(t.message).toContain('update_blocks')
  })

  it('ancre introuvable 404 → conseil de relecture', () => {
    const t = translateError(404, { code: 'anchor_not_found', message: 'ancre inconnue' }, 'fallback')
    expect(t.message).toContain('get_page_blocks')
  })

  it('401 → conseil token', () => {
    const t = translateError(401, { code: 'unauthorized', message: 'token invalide' }, 'fallback')
    expect(t.message).toContain('HOUETOR_TOKEN')
  })

  it('tier policy 400 block_legacy → conseil de remplacement', () => {
    const t = translateError(400, {
      code: 'block_legacy',
      message: 'Bloc core/verse obsolète ou non supporté à la création. Utilisez core/preformatted à la place.',
      data: { status: 400, block_name: 'core/verse', suggested_block: 'core/preformatted' },
    }, 'fallback')
    expect(t.code).toBe('block_legacy')
    expect(t.message).toContain('core/verse')
    expect(t.message).toContain('core/preformatted')
  })

  it('wrap_failed plage inversée → conseil ordre start/end', () => {
    const t = translateError(400, {
      code: 'wrap_failed',
      message: 'Le bloc de fin précède le bloc de départ — plage invalide.',
    }, 'fallback')
    expect(t.code).toBe('wrap_failed')
    expect(t.message).toContain('index croissants')
    expect(t.message).toContain('get_page_blocks')
  })

  it('unwrap_failed non-groupe → conseil core/group + wrap_block', () => {
    const t = translateError(400, {
      code: 'unwrap_failed',
      message: "Le bloc ciblé (core/paragraph) n'est pas un groupe — seul core/group peut être dégroupé.",
    }, 'fallback')
    expect(t.code).toBe('unwrap_failed')
    expect(t.message).toContain('core/group')
    expect(t.message).toContain('wrap_block')
  })

  it('move_failed 404 générique → message brut préservé', () => {
    const t = translateError(404, {
      code: 'move_failed',
      message: 'Bloc #99 introuvable sur la page 2.',
    }, 'fallback')
    expect(t.status).toBe(404)
    expect(t.message).toBe('Bloc #99 introuvable sur la page 2.')
  })

  it('reste fidèle au message brut sinon', () => {
    const t = translateError(400, { code: 'validation_error', message: 'champ invalide' }, 'fallback')
    expect(t.message).toBe('champ invalide')
  })
})
