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

    // ---- v2.5.0 : transform_block (2 écritures réelles — budget rate limit : 10) ----
    const tfRes = await rpc('transform_block', {
      page_id: '2',
      ref,
      target_block_name: 'core/heading',
      expected_hash: md5After,
    })
    const tfBody = await tfRes.json()
    check(
      'transform_block paragraph→heading → 200 + target_blockName',
      tfRes.status === 200 && tfBody.result?.data?.target_blockName === 'core/heading',
      tfBody.error?.message ?? '',
    )
    const blocksTf = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data?.blocks ?? []
    check('transform: ref conservée + blockName heading', blocksTf.find((b) => b.ref === ref)?.blockName === 'core/heading')

    const tfBad = await rpc('transform_block', {
      page_id: '2',
      ref,
      target_block_name: 'core/quote',
      expected_hash: 'hash-inexistant',
      dry_run: true,
    })
    const tfBadBody = await tfBad.json()
    check(
      'transform CAS périmé (dry_run) → 409 traduit',
      tfBad.status === 409 && tfBadBody.error?.data?.code === 'error_conflict',
      tfBadBody.error?.message ?? '',
    )

    const tfMedia = await rpc('transform_block', {
      page_id: '2',
      ref,
      target_block_name: 'core/image',
      dry_run: true,
    })
    const tfMediaBody = await tfMedia.json()
    check('transform cible media (dry_run) → 400 traduit', tfMedia.status === 400, tfMediaBody.error?.message ?? '')

    // ---- v2.6.0 : tier policy — bloc legacy refusé à la création avec suggestion (dry_run, budget intact) ----
    const legacyRes = await rpc('create_block', {
      page_id: '2',
      block_name: 'core/verse',
      content: 'Le vers reste un vers',
      module: 'test',
      dry_run: true,
    })
    const legacyBody = await legacyRes.json()
    check(
      'create_block core/verse (dry_run) → 400 block_legacy traduit avec suggestion',
      legacyRes.status === 400 &&
        legacyBody.error?.data?.code === 'block_legacy' &&
        legacyBody.error?.data?.data?.suggested_block === 'core/preformatted' &&
        legacyBody.error?.message?.includes('core/preformatted'),
      legacyBody.error?.message ?? '',
    )
    const blocksLegacy = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data?.blocks ?? []
    check('tier policy: aucun bloc créé (page inchangée)', blocksLegacy.length === blocksTf.length)

    const tfBack = await rpc('transform_block', {
      page_id: '2',
      ref,
      target_block_name: 'core/paragraph',
    })
    check('transform heading→paragraph (retour) → 200', tfBack.status === 200)
    const md5Tf = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data?.content_md5

    const updRes = await rpc('update_block_content', {
      page_id: '2',
      ref,
      new_content: '<p>MCP lab — modifié</p>',
      expected_hash: md5Tf,
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

    // ---- v2.4.0 : dry_run + batch ----
    const blocksDryBefore = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data
    const updDry = await rpc('update_block_content', {
      page_id: '2',
      ref,
      new_content: '<p>dry — ne doit pas etre applique</p>',
      dry_run: true,
    })
    const updDryBody = await updDry.json()
    check('update_block_content dry_run → dry_run:true', updDryBody.result?.data?.dry_run === true, updDryBody.error?.message ?? '')
    const blocksDryAfter = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data
    check('dry_run: contenu inchangé (md5 identique)', blocksDryAfter.content_md5 === blocksDryBefore.content_md5)

    const delDry = await rpc('delete_block', { page_id: '2', ref, dry_run: true })
    const delDryBody = await delDry.json()
    check('delete_block dry_run → dry_run:true', delDryBody.result?.data?.dry_run === true)
    const blocksAfterDelDry = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data?.blocks ?? []
    check('delete_block dry_run: ref toujours présente', blocksAfterDelDry.some((b) => b.ref === ref))

    const createDry = await rpc('create_block', {
      page_id: '2',
      block_name: 'core/paragraph',
      content: '<p>dry — pas de creation</p>',
      module: 'test',
      dry_run: true,
    })
    const createDryBody = await createDry.json()
    check('create_block dry_run → dry_run:true', createDryBody.result?.data?.dry_run === true)

    const injectDry = await rpc('inject_page', {
      page_id: '2',
      html: '<p>dry inject</p>',
      module: 'test',
      dry_run: true,
    })
    const injectDryBody = await injectDry.json()
    check('inject_page dry_run → dry_run:true', injectDryBody.result?.data?.dry_run === true)

    const create2Res = await rpc('create_block', {
      page_id: '2',
      block_name: 'core/paragraph',
      content: '<p>MCP lab — bloc B pour batch</p>',
      module: 'test',
    })
    const create2Body = await create2Res.json()
    const ref2 = create2Body.result?.data?.ref
    check('create_block #2 → ref générée', typeof ref2 === 'string' && ref2.startsWith('test-'), `ref2=${ref2}`)

    const batchMd5 = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data?.content_md5
    const batchRes = await rpc('update_blocks', {
      page_id: '2',
      updates: [
        { ref, new_content: '<p>MCP lab — batch A</p>' },
        { ref: ref2, new_content: '<p>MCP lab — batch B</p>' },
      ],
      expected_hash: batchMd5,
    })
    const batchBody = await batchRes.json()
    check('update_blocks batch nominal → count=2', batchRes.status === 200 && batchBody.result?.data?.count === 2, batchBody.error?.message ?? '')

    const batchFail = await rpc('update_blocks', {
      page_id: '2',
      updates: [
        { ref, new_content: '<p>NE DOIT PAS ETRE APPLIQUE</p>' },
        { ref: 'test-ref-inexistante', new_content: '<p>x</p>' },
      ],
    })
    const batchFailBody = await batchFail.json()
    check(
      'update_blocks all-or-nothing: ref invalide → échec traduit (400/404)',
      batchFail.status === 400 || batchFail.status === 404,
      batchFailBody.error?.message ?? '',
    )
    const blocksAfterBatchFail = (await (await rpc('get_page_blocks', { page_id: '2' })).json()).result?.data?.blocks ?? []
    check('batch all-or-nothing: rien appliqué (contenu A inchangé)', blocksAfterBatchFail.find((b) => b.ref === ref)?.content.includes('NE DOIT PAS') === false)

    const batchDry = await rpc('update_blocks', {
      page_id: '2',
      updates: [{ ref, new_content: '<p>dry batch</p>' }],
      dry_run: true,
    })
    const batchDryBody = await batchDry.json()
    check('update_blocks dry_run → dry_run:true + count=1', batchDryBody.result?.data?.dry_run === true && batchDryBody.result?.data?.count === 1)

    // ---- v2.7.0 : ops structurelles sur PAGE 3 (budget rate limit indépendant ; 6 écritures) ----
    const p3init = (await (await rpc('get_page_blocks', { page_id: '3' })).json()).result?.data
    check('page 3 lisible via MCP (Privacy Policy, draft)', p3init && p3init.blocks?.length >= 5, `count=${p3init?.blocks?.length ?? 0}`)
    const headingWho = p3init.blocks.find((b) => b.content?.includes('Who we are'))
    check('page 3: heading "Who we are" présent', typeof headingWho?.index === 'number')

    // erreurs en dry_run / validation (0 écriture)
    const mvBad = await rpc('move_block', {
      page_id: '3',
      ref: 'test-ref-inexistante',
      position: 'start',
      dry_run: true,
    })
    const mvBadBody = await mvBad.json()
    check('move_block source introuvable (dry_run) → 404 traduit', mvBad.status === 404 && mvBadBody.error?.data?.code === 'move_failed', mvBadBody.error?.message ?? '')

    const mvNoAnchor = await rpc('move_block', { page_id: '3', ref: 'test-ref-inexistante', position: 'before' })
    check('move_block before sans ancre → 400', mvNoAnchor.status === 400)

    const unBad = await rpc('unwrap_block', { page_id: '3', block_index: '2', dry_run: true })
    const unBadBody = await unBad.json()
    check(
      'unwrap_block non-groupe (dry_run) → 400 traduit avec conseil core/group',
      unBad.status === 400 &&
        unBadBody.error?.data?.code === 'unwrap_failed' &&
        unBadBody.error?.message?.includes('core/group'),
      unBadBody.error?.message ?? '',
    )

    // move réel : bloc 2 → start (relire avant écrire : CAS)
    const mvRes = await rpc('move_block', {
      page_id: '3',
      block_index: '2',
      position: 'start',
      expected_hash: p3init.content_md5,
    })
    const mvBody = await mvRes.json()
    check('move_block réel → 200 + block_index 0', mvRes.status === 200 && mvBody.result?.data?.block_index === 0, mvBody.error?.message ?? '')
    let stBlocks = (await (await rpc('get_page_blocks', { page_id: '3' })).json()).result?.data
    check('move_block: paragraphe ciblé premier', stBlocks.blocks[0]?.content?.includes('website address'))

    // duplicate réel : bloc 1 (heading) → copie juste après (pas de ref sans module)
    const dupRes = await rpc('duplicate_block', {
      page_id: '3',
      block_index: '1',
      expected_hash: stBlocks.content_md5,
    })
    const dupBody = await dupRes.json()
    check('duplicate_block réel → 200', dupRes.status === 200, dupBody.error?.message ?? '')
    stBlocks = (await (await rpc('get_page_blocks', { page_id: '3' })).json()).result?.data
    check('duplicate_block: copie heading juste après la source', stBlocks.blocks[1]?.blockName === 'core/heading' && stBlocks.blocks[2]?.blockName === 'core/heading')

    // wrap réel : plage [0..1] (paragraphe + heading) → groupe en position 0
    const wrapRes = await rpc('wrap_block', {
      page_id: '3',
      block_index: '0',
      end_index: '1',
      expected_hash: stBlocks.content_md5,
    })
    const wrapBody = await wrapRes.json()
    check('wrap_block réel plage → 200 + groupe', wrapRes.status === 200 && wrapBody.result?.data?.blockName === 'core/group', wrapBody.error?.message ?? '')
    stBlocks = (await (await rpc('get_page_blocks', { page_id: '3' })).json()).result?.data
    check(
      'wrap_block: groupe en position 0, enfants plus à la racine',
      stBlocks.blocks[0]?.blockName === 'core/group' && !stBlocks.blocks.some((b) => b.content?.includes('website address')),
    )

    // unwrap réel du groupe
    const unwRes = await rpc('unwrap_block', {
      page_id: '3',
      block_index: '0',
      expected_hash: stBlocks.content_md5,
    })
    const unwBody = await unwRes.json()
    check('unwrap_block réel → 200 + count>=2', unwRes.status === 200 && unwBody.result?.data?.count >= 2, unwBody.error?.message ?? '')
    stBlocks = (await (await rpc('get_page_blocks', { page_id: '3' })).json()).result?.data
    check('unwrap_block: enfants de retour à la racine', stBlocks.blocks[0]?.blockName === 'core/paragraph' && stBlocks.blocks[1]?.blockName === 'core/heading')

    // nettoyage page 3 : delete de la copie + move retour du paragraphe
    const del3 = await rpc('delete_block', { page_id: '3', block_index: '2', expected_hash: stBlocks.content_md5 })
    check('nettoyage delete copie → 200', del3.status === 200)
    stBlocks = (await (await rpc('get_page_blocks', { page_id: '3' })).json()).result?.data
    check('nettoyage: copie supprimée (1 seul heading "Who we are")', stBlocks.blocks.filter((b) => b.blockName === 'core/heading' && b.content?.includes('Who we are')).length === 1)
    const mvBack = await rpc('move_block', {
      page_id: '3',
      block_index: '0',
      position: 'after',
      anchor_index: String(stBlocks.blocks.find((b) => b.content?.includes('Who we are'))?.index ?? 1),
      expected_hash: stBlocks.content_md5,
    })
    check('nettoyage move retour → 200', mvBack.status === 200, (await mvBack.json()).error?.message ?? '')
    const p3final = (await (await rpc('get_page_blocks', { page_id: '3' })).json()).result?.data
    check('page 3 structure logique restaurée (count initial)', p3final.blocks.length === p3init.blocks.length, `before=${p3init.blocks.length} after=${p3final.blocks.length}`)

    const delRes = await rpc('delete_block', { page_id: '2', ref })
    const delBody = await delRes.json()
    check('delete_block → 200', delRes.status === 200, delBody.error?.message ?? '')

    const delRes2 = await rpc('delete_block', { page_id: '2', ref: ref2 })
    check('delete_block #2 → 200', delRes2.status === 200)

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
