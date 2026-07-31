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

  it('reste fidèle au message brut sinon', () => {
    const t = translateError(400, { code: 'validation_error', message: 'champ invalide' }, 'fallback')
    expect(t.message).toBe('champ invalide')
  })
})
