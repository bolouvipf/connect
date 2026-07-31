// Test d'intégration : démarre le serveur MCP lab puis enchaîne des appels JSON-RPC
// contre le WordPress de test (localhost:8888). À exécuter DANS WSL.
// Usage : WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN=<hwc_token> node scripts/integration-test.mjs

import { createRequire } from 'node:module'
import { createServer } from 'node:http'

const require = createRequire(import.meta.url)
const { createMcpServer } = require('../dist/index.cjs')

const PORT = 8891
const HWT = 'HWT-ONG-123e4567-e89b-12d3-a456-426614174000'
const baseUrl = process.env.WORDPRESS_URL ?? 'http://localhost:8888'
const token = process.env.HOUETOR_TOKEN ?? ''

const results = []
const rpc = (method, params = {}) =>
  fetch(`http://localhost:${PORT}/mcp`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', 'x-hwt-token': HWT },
    body: JSON.stringify({ jsonrpc: '2.0', id: Date.now(), method, params }),
  })

function check(label, cond, detail = '') {
  results.push({ label, ok: !!cond, detail })
  console.log(`${cond ? 'PASS' : 'FAIL'} ${label}${detail ? ' — ' + detail : ''}`)
}

if (!token) {
  console.error('HOUETOR_TOKEN requis (hwc_token du site lab)')
  process.exit(1)
}

const server = createMcpServer({ baseUrl, token })

server.listen(PORT, async () => {
  try {
    const pagesRes = await rpc('get_wp_pages')
    const pagesBody = await pagesRes.json()
    check('get_wp_pages → 200', pagesRes.status === 200)
    const pages = pagesBody.result?.data?.pages ?? []
    check('page 2 présente', pages.some((p) => String(p.id) === '2'))

    const blocksRes = await rpc('get_page_blocks', { page_id: '2' })
    const blocksBody = await blocksRes.json()
    check('get_page_blocks page 2 → 200', blocksRes.status === 200)
    const blocks = blocksBody.result?.data?.blocks ?? []
    const countBefore = blocks.length
    check('page 2 a des blocs', countBefore > 0, `count=${countBefore}`)
    const md5Before = blocksBody.result?.data?.content_md5
    check('content_md5 présent', typeof md5Before === 'string' && md5Before.length > 0)

    const createRes = await rpc('create_block', {
      page_id: '2',
      block_name: 'core/paragraph',
      content: '<p>MCP lab — bloc temporaire</p>',
      module: 'test',
    })
    const createBody = await createRes.json()
    check('create_block → 200', createRes.status === 200, createBody.error?.message ?? '')
    const ref = createBody.result?.data?.ref
    check('ref générée (préfixe module)', typeof ref === 'string' && ref.startsWith('test-'), `ref=${ref}`)

    const blocks2Body = await (await rpc('get_page_blocks', { page_id: '2' })).json()
    const blocks2 = blocks2Body.result?.data?.blocks ?? []
    check('bloc créé lisible par ref', blocks2.some((b) => b.ref === ref))
    check('nombre de blocs = avant + 1', blocks2.length === countBefore + 1, `before=${countBefore} after=${blocks2.length}`)
    const md5After = blocks2Body.result?.data?.content_md5

    const updRes = await rpc('update_block_content', {
      page_id: '2',
      ref,
      new_content: '<p>MCP lab — modifié</p>',
      expected_hash: md5After,
    })
    const updBody = await updRes.json()
    check('update_block_content → 200', updRes.status === 200, updBody.error?.message ?? '')

    const updBad = await rpc('update_block_content', {
      page_id: '2',
      ref,
      new_content: '<p>MCP lab — CAS KO</p>',
      expected_hash: 'hash-inexistant',
    })
    const badBody = await updBad.json()
    check(
      'update avec mauvais hash → 409 traduit',
      updBad.status === 409 && badBody.error?.data?.code === 'error_conflict',
    )

    const delRes = await rpc('delete_block', { page_id: '2', ref })
    const delBody = await delRes.json()
    check('delete_block → 200', delRes.status === 200, delBody.error?.message ?? '')

    const blocks3 = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data?.blocks ?? []
    check('page 2 restaurée (count initial)', blocks3.length === countBefore, `before=${countBefore} after=${blocks3.length}`)

    const unknown = await rpc('methode_inexistante')
    check('méthode inconnue → 404/-32601', unknown.status === 404)

    const noAuth = await fetch(`http://localhost:${PORT}/mcp`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({ jsonrpc: '2.0', id: 1, method: 'get_wp_pages' }),
    })
    check('sans X-HWT-Token → 401', noAuth.status === 401)

    const sse = await fetch(`http://localhost:${PORT}/mcp`, {
      method: 'GET',
      headers: { 'x-hwt-token': 'HWT-CM-abc' },
    })
    const sseText = await sse.text()
    const sseBody = JSON.parse(sseText.replace('data: ', '').replace('\n\n', ''))
    check('SSE GET liste tools profil CM', sse.status === 200 && sseBody.tools.length > 0)
  } catch (err) {
    results.push({ label: 'exception', ok: false, detail: String(err) })
    console.log('FAIL exception —', err)
  } finally {
    const failures = results.filter((r) => !r.ok).length
    console.log(`\n${results.length - failures}/${results.length} PASS`)
    server.close()
    process.exit(failures === 0 ? 0 : 1)
  }
})
