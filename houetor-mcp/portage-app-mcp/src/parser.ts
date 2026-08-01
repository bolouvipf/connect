export function parseHWT(token: string): { profil: string | null; uuid: string } | null {
  if (!token || typeof token !== 'string') return null

  const hwtMatch = token.match(/^HWT-(ONG|BOUTIQUE|COACH|CM|MARKETING)-(.+)$/)
  if (hwtMatch) return { profil: hwtMatch[1], uuid: hwtMatch[2] }

  if (/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(token)) {
    return { profil: null, uuid: token }
  }

  return null
}
