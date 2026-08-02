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

  it('update_blocks appelle /blocks/batch-update avec updates + dry_run', async () => {
    mockFetchOnce(200, { success: true, count: 2, dry_run: false })
    const res = await post('update_blocks', {
      page_id: '2',
      updates: [
        { ref: 'lab-aaa', new_content: '<p>1</p>' },
        { block_index: '3', new_content: '<p>2</p>' },
      ],
      expected_hash: 'md5-x',
      dry_run: 'true',
    })
    expect(res.status).toBe(200)
    const call = vi.mocked(fetch).mock.calls[0]
    expect(String(call[0])).toBe('http://localhost:8888/wp-json/houetor/v1/blocks/batch-update')
    expect(call[1]?.method).toBe('POST')
    const sent = JSON.parse(String(call[1]?.body))
    expect(sent.updates).toEqual([
      { ref: 'lab-aaa', block_index: undefined, new_content: '<p>1</p>' },
      { ref: undefined, block_index: '3', new_content: '<p>2</p>' },
    ])
    expect(sent.dry_run).toBe(true)
    expect(sent.expected_hash).toBe('md5-x')
    const body = await res.json()
    expect(body.result.data.count).toBe(2)
  })

  it('update_blocks sans updates → 400', async () => {
    const res = await post('update_blocks', { page_id: '2' })
    expect(res.status).toBe(400)
  })

  it('update_blocks avec update sans cible → 400', async () => {
    const res = await post('update_blocks', {
      page_id: '2',
      updates: [{ new_content: '<p>sans cible</p>' }],
    })
    expect(res.status).toBe(400)
    const body = await res.json()
    expect(body.error.message).toContain('ref ou block_index')
  })

  it('inject_page transmet dry_run en booléen', async () => {
    mockFetchOnce(200, { success: true, dry_run: true })
    const res = await post('inject_page', {
      page_id: '2',
      html: '<p>x</p>',
      module: 'annonces',
      dry_run: 'true',
    })
    expect(res.status).toBe(200)
    const sent = JSON.parse(String(vi.mocked(fetch).mock.calls[0][1]?.body))
    expect(sent.dry_run).toBe(true)
  })

  it('inject_page avec dry_run=false transmet false', async () => {
    mockFetchOnce(200, { success: true })
    await post('inject_page', { page_id: '2', html: '<p>x</p>', module: 'annonces', dry_run: false })
    const sent = JSON.parse(String(vi.mocked(fetch).mock.calls[0][1]?.body))
    expect(sent.dry_run).toBe(false)
  })

  it('SSE GET liste le tool update_blocks', async () => {
    const res = await handleRequest(
      new Request('http://localhost:8890/mcp', {
        method: 'GET',
        headers: { 'x-hwt-token': 'HWT-ONG-abc' },
      }),
      CONFIG,
    )
    const text = await res.text()
    const parsed = JSON.parse(text.replace('data: ', '').replace('\n\n', ''))
    expect(parsed.tools.some((t: { name: string }) => t.name === 'update_blocks')).toBe(true)
  })

  it('transform_block appelle /blocks/transform avec target_block_name + dry_run', async () => {
    mockFetchOnce(200, { success: true, blockName: 'core/paragraph', target_blockName: 'core/heading', dry_run: false })
    const res = await post('transform_block', {
      page_id: '2',
      ref: 'lab-aaa',
      target_block_name: 'core/heading',
      expected_hash: 'md5-x',
      dry_run: 'true',
    })
    expect(res.status).toBe(200)
    const call = vi.mocked(fetch).mock.calls[0]
    expect(String(call[0])).toBe('http://localhost:8888/wp-json/houetor/v1/blocks/transform')
    expect(call[1]?.method).toBe('POST')
    const sent = JSON.parse(String(call[1]?.body))
    expect(sent.ref).toBe('lab-aaa')
    expect(sent.target_block_name).toBe('core/heading')
    expect(sent.dry_run).toBe(true)
    expect(sent.expected_hash).toBe('md5-x')
    const body = await res.json()
    expect(body.result.data.target_blockName).toBe('core/heading')
  })

  it('transform_block sans cible → 400', async () => {
    const res = await post('transform_block', { page_id: '2', target_block_name: 'core/heading' })
    expect(res.status).toBe(400)
    const body = await res.json()
    expect(body.error.message).toContain('ref ou block_index')
  })

  it('transform_block sans target_block_name → 400', async () => {
    const res = await post('transform_block', { page_id: '2', ref: 'lab-aaa' })
    expect(res.status).toBe(400)
  })

  it('move_block appelle /blocks/move avec position + ancre + dry_run', async () => {
    mockFetchOnce(200, { success: true, dry_run: false, block_index: 0, ref: 'lab-aaa' })
    const res = await post('move_block', {
      page_id: '2',
      ref: 'lab-aaa',
      position: 'before',
      anchor_ref: 'lab-zzz',
      expected_hash: 'md5-x',
      dry_run: 'true',
    })
    expect(res.status).toBe(200)
    const call = vi.mocked(fetch).mock.calls[0]
    expect(String(call[0])).toBe('http://localhost:8888/wp-json/houetor/v1/blocks/move')
    expect(call[1]?.method).toBe('POST')
    const sent = JSON.parse(String(call[1]?.body))
    expect(sent.ref).toBe('lab-aaa')
    expect(sent.position).toBe('before')
    expect(sent.anchor_ref).toBe('lab-zzz')
    expect(sent.dry_run).toBe(true)
    const body = await res.json()
    expect(body.result.data.block_index).toBe(0)
  })

  it('move_block sans cible → 400', async () => {
    const res = await post('move_block', { page_id: '2', position: 'end' })
    expect(res.status).toBe(400)
    const body = await res.json()
    expect(body.error.message).toContain('ref ou block_index')
  })

  it('move_block sans position → 400', async () => {
    const res = await post('move_block', { page_id: '2', ref: 'lab-aaa' })
    expect(res.status).toBe(400)
  })

  it('duplicate_block appelle /blocks/duplicate avec module', async () => {
    mockFetchOnce(200, { success: true, dry_run: false, ref: 'test-copy', block_index: 3 })
    const res = await post('duplicate_block', {
      page_id: '2',
      ref: 'lab-aaa',
      module: 'test',
      dry_run: 'true',
    })
    expect(res.status).toBe(200)
    const call = vi.mocked(fetch).mock.calls[0]
    expect(String(call[0])).toBe('http://localhost:8888/wp-json/houetor/v1/blocks/duplicate')
    const sent = JSON.parse(String(call[1]?.body))
    expect(sent.ref).toBe('lab-aaa')
    expect(sent.module).toBe('test')
    expect(sent.dry_run).toBe(true)
    const body = await res.json()
    expect(body.result.data.ref).toBe('test-copy')
  })

  it('wrap_block appelle /blocks/wrap avec plage + module', async () => {
    mockFetchOnce(200, { success: true, dry_run: false, ref: 'lab-group', count: 2 })
    const res = await post('wrap_block', {
      page_id: '2',
      block_index: '1',
      end_index: '2',
      module: 'test',
      expected_hash: 'md5-x',
    })
    expect(res.status).toBe(200)
    const call = vi.mocked(fetch).mock.calls[0]
    expect(String(call[0])).toBe('http://localhost:8888/wp-json/houetor/v1/blocks/wrap')
    const sent = JSON.parse(String(call[1]?.body))
    expect(sent.block_index).toBe('1')
    expect(sent.end_index).toBe('2')
    expect(sent.module).toBe('test')
    const body = await res.json()
    expect(body.result.data.count).toBe(2)
  })

  it('wrap_block traduit la plage inversée 400 du plugin', async () => {
    mockFetchOnce(400, {
      code: 'wrap_failed',
      message: 'Le bloc de fin précède le bloc de départ — plage invalide.',
    })
    const res = await post('wrap_block', { page_id: '2', block_index: '5', end_index: '2' })
    expect(res.status).toBe(400)
    const body = await res.json()
    expect(body.error.data.code).toBe('wrap_failed')
    expect(body.error.message).toContain('index croissants')
  })

  it('unwrap_block appelle /blocks/unwrap', async () => {
    mockFetchOnce(200, { success: true, dry_run: false, count: 2, ref: 'lab-group' })
    const res = await post('unwrap_block', { page_id: '2', ref: 'lab-group', dry_run: 'true' })
    expect(res.status).toBe(200)
    const call = vi.mocked(fetch).mock.calls[0]
    expect(String(call[0])).toBe('http://localhost:8888/wp-json/houetor/v1/blocks/unwrap')
    const sent = JSON.parse(String(call[1]?.body))
    expect(sent.ref).toBe('lab-group')
    expect(sent.dry_run).toBe(true)
    const body = await res.json()
    expect(body.result.data.count).toBe(2)
  })

  it('unwrap_block non-groupe → 400 traduit avec conseil', async () => {
    mockFetchOnce(400, {
      code: 'unwrap_failed',
      message: "Le bloc ciblé (core/paragraph) n'est pas un groupe — seul core/group peut être dégroupé.",
    })
    const res = await post('unwrap_block', { page_id: '2', ref: 'lab-aaa' })
    expect(res.status).toBe(400)
    const body = await res.json()
    expect(body.error.data.code).toBe('unwrap_failed')
    expect(body.error.message).toContain('core/group')
    expect(body.error.message).toContain('wrap_block')
  })

  it('SSE GET liste les tools structurels 2.7.0', async () => {
    const res = await handleRequest(
      new Request('http://localhost:8890/mcp', {
        method: 'GET',
        headers: { 'x-hwt-token': 'HWT-ONG-abc' },
      }),
      CONFIG,
    )
    const text = await res.text()
    const parsed = JSON.parse(text.replace('data: ', '').replace('\n\n', ''))
    const names = parsed.tools.map((t: { name: string }) => t.name)
    expect(names).toEqual(expect.arrayContaining(['move_block', 'duplicate_block', 'wrap_block', 'unwrap_block']))
  })

  it('transform_block traduit le 409 CAS du plugin', async () => {
    mockFetchOnce(409, { code: 'error_conflict', message: 'le contenu a change' })
    const res = await post('transform_block', {
      page_id: '2',
      ref: 'lab-aaa',
      target_block_name: 'core/heading',
      expected_hash: 'ancien',
    })
    expect(res.status).toBe(409)
    const body = await res.json()
    expect(body.error.data.code).toBe('error_conflict')
    expect(body.error.message).toContain('get_page_blocks')
  })

  it('SSE GET liste le tool transform_block', async () => {
    const res = await handleRequest(
      new Request('http://localhost:8890/mcp', {
        method: 'GET',
        headers: { 'x-hwt-token': 'HWT-ONG-abc' },
      }),
      CONFIG,
    )
    const text = await res.text()
    const parsed = JSON.parse(text.replace('data: ', '').replace('\n\n', ''))
    expect(parsed.tools.some((t: { name: string }) => t.name === 'transform_block')).toBe(true)
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
