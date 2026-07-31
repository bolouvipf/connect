import { describe, it, expect, vi, beforeEach } from 'vitest'
import { handleRequest } from '../src/route-handler.js'

const CONFIG = { baseUrl: 'http://localhost:8888', token: 'tok-lab' }
const HWT = 'HWT-ONG-123e4567-e89b-12d3-a456-426614174000'

function mockFetchOnce(status: number, body: unknown) {
  return vi.spyOn(globalThis, 'fetch').mockResolvedValueOnce(
    new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } }),
  )
}

function post(method: string, params: Record<string, unknown> = {}) {
  return handleRequest(
    new Request('http://localhost:8890/mcp', {
      method: 'POST',
      headers: { 'content-type': 'application/json', 'x-hwt-token': HWT },
      body: JSON.stringify({ jsonrpc: '2.0', id: 1, method, params }),
    }),
    CONFIG,
  )
}

describe('handleRequest', () => {
  beforeEach(() => vi.restoreAllMocks())

  it('rejette sans header X-HWT-Token', async () => {
    const res = await handleRequest(
      new Request('http://localhost:8890/mcp', {
        method: 'POST',
        headers: { 'content-type': 'application/json' },
        body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'get_wp_pages' }),
      }),
      CONFIG,
    )
    expect(res.status).toBe(401)
    const body = await res.json()
    expect(body.error.code).toBe(-32000)
  })

  it('rejette un corps invalide', async () => {
    const res = await post('x', {})
    vi.spyOn(globalThis, 'fetch').mockRestore()
    const bad = await handleRequest(
      new Request('http://localhost:8890/mcp', {
        method: 'POST',
        headers: { 'content-type': 'application/json', 'x-hwt-token': HWT },
        body: 'pas du json',
      }),
      CONFIG,
    )
    expect(bad.status).toBe(400)
    expect((await bad.json()).error.code).toBe(-32600)
  })

  it('méthode inconnue → -32601/404', async () => {
    const res = await post('get_inexistant')
    expect(res.status).toBe(404)
    const body = await res.json()
    expect(body.error.code).toBe(-32601)
  })

  it('profil non autorisé → -32000/401 (mécanisme de restriction)', async () => {
    const { HWT_TOOLS } = await import('../src/tools.js')
    HWT_TOOLS.push({ name: 'tool_restreint_test', description: 'test', profiles: ['ONG'], params: {} })
    try {
      const res = await handleRequest(
        new Request('http://localhost:8890/mcp', {
          method: 'POST',
          headers: { 'content-type': 'application/json', 'x-hwt-token': 'HWT-CM-abc' },
          body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'tool_restreint_test' }),
        }),
        CONFIG,
      )
      expect(res.status).toBe(401)
      expect((await res.json()).error.code).toBe(-32000)
    } finally {
      HWT_TOOLS.pop()
    }
  })

  it('get_wp_pages transmet la réponse du plugin', async () => {
    mockFetchOnce(200, { pages: [{ id: '2', title: 'Sample Page' }] })
    const res = await post('get_wp_pages')
    expect(res.status).toBe(200)
    const body = await res.json()
    expect(body.result.success).toBe(true)
    expect(body.result.data.pages[0].id).toBe('2')
    const call = vi.mocked(fetch).mock.calls[0]
    expect(String(call[0])).toBe('http://localhost:8888/wp-json/houetor/v1/pages')
    expect(call[1]?.headers).toMatchObject({ 'X-Houetor-Token': 'tok-lab' })
  })

  it('traduit le conflit CAS 409 du plugin', async () => {
    mockFetchOnce(409, { code: 'error_conflict', message: 'le contenu a change' })
    const res = await post('update_block_content', {
      page_id: '2',
      ref: 'HWC-abc',
      new_content: 'x',
      expected_hash: 'ancien',
    })
    expect(res.status).toBe(409)
    const body = await res.json()
    expect(body.error.code).toBe(-32002)
    expect(body.error.data.code).toBe('error_conflict')
    expect(body.error.message).toContain('get_page_blocks')
  })

  it('traduit le rate limit 429 du plugin', async () => {
    mockFetchOnce(429, { code: 'rate_limited', message: 'trop de requetes' })
    const res = await post('create_block', { page_id: '2', block_name: 'core/paragraph' })
    expect(res.status).toBe(429)
    const body = await res.json()
    expect(body.error.data.code).toBe('rate_limited')
  })

  it('paramètre requis manquant → 400', async () => {
    mockFetchOnce(200, {})
    const res = await post('create_block', {})
    expect(res.status).toBe(400)
  })

  it('SSE GET liste les tools filtrés par profil', async () => {
    const res = await handleRequest(
      new Request('http://localhost:8890/mcp', {
        method: 'GET',
        headers: { 'x-hwt-token': 'HWT-CM-abc' },
      }),
      CONFIG,
    )
    expect(res.status).toBe(200)
    expect(res.headers.get('content-type')).toContain('text/event-stream')
    const text = await res.text()
    const parsed = JSON.parse(text.replace('data: ', '').replace('\n\n', ''))
    expect(parsed.profil).toBe('CM')
    expect(parsed.tools.length).toBeGreaterThan(0)
    expect(parsed.tools.every((t: { profiles: string[] }) => t.profiles.includes('CM'))).toBe(true)
  })
})
