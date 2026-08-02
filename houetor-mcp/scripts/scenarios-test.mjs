// Test Phase 3 : scénarios utilisateur « exaucés exactement » À TRAVERS le MCP miroir.
// Chaque scénario simule une demande utilisateur réaliste : relecture (get_page_blocks),
// écriture via le MCP, vérification d'état. À exécuter DANS WSL.
// Usage : WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN=<hwc_token> node scripts/scenarios-test.mjs
// Prérequis : reset du rate limit avant le run (wp option delete _transient_hwc_ratelimit_2)

import { createRequire } from 'node:module'
import { createServer } from 'node:http'

const require = createRequire(import.meta.url)
const { createMcpServer } = require('../dist/index.cjs')

const PORT = 8892
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

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
const getPage = async (pageId) => (await (await rpc('get_page_blocks', { page_id: pageId })).json()).result?.data
const last = (arr) => arr[arr.length - 1]

if (!token) {
  console.error('HOUETOR_TOKEN requis (hwc_token du site lab)')
  process.exit(1)
}

const server = createMcpServer({ baseUrl, token })

server.listen(PORT, async () => {
  try {
    // État de référence
    const page2 = await getPage('2')
    const blocks0 = page2.blocks
    const md5Before = page2.content_md5
    check('S0 relecture initiale : page 2 lisible via MCP', blocks0.length >= 4, `count=${blocks0.length}`)

    // ===== S1 — « Ajoute un bloc avantage juste avant le pied de page » =====
    const s1 = await rpc('create_block', {
      page_id: '2',
      block_name: 'core/heading',
      content: 'Avantage MCP',
      module: 'client',
      position: 'before',
      anchor_index: String(blocks0.length - 1),
    })
    const s1Body = await s1.json()
    const refA = s1Body.result?.data?.ref
    check('S1 create avant le pied de page → 200 + ref', s1.status === 200 && typeof refA === 'string', s1Body.error?.message ?? '')
    const after1 = await getPage('2')
    check('S1 bloc visible en avant-dernière position', after1.blocks[after1.blocks.length - 2]?.ref === refA)

    // ===== S2 — « Corrige le prix dans le bloc annonce (bloc natif n°0) » =====
    const targetIdx = '0'
    const target = blocks0.find((b) => b.index === 0)
    check('S2 précondition : bloc 0 éditable (paragraph)', target?.blockName === 'core/paragraph', target?.blockName ?? '')
    const s2 = await rpc('update_block_content', {
      page_id: '2',
      block_index: targetIdx,
      new_content: 'This is an example page. Offre spéciale : 99 €',
      expected_hash: after1.content_md5,
    })
    const s2Body = await s2.json()
    check('S2 update par index avec expected_hash frais → 200', s2.status === 200, s2Body.error?.message ?? '')
    const after2 = await getPage('2')
    check('S2 relecture : contenu mis à jour', after2.blocks[0].content.includes('99'))

    // ===== S7 — « Transforme le bloc avantage en titre » =====
    const s7 = await rpc('transform_block', {
      page_id: '2',
      ref: refA,
      target_block_name: 'core/heading',
      expected_hash: after2.content_md5,
    })
    const s7Body = await s7.json()
    check('S7 transform paragraph→heading → 200 + target', s7.status === 200 && s7Body.result?.data?.target_blockName === 'core/heading', s7Body.error?.message ?? '')
    const afterS7 = await getPage('2')
    check('S7 relecture : bloc A est core/heading (ref conservée)', afterS7.blocks.find((b) => b.ref === refA)?.blockName === 'core/heading')

    // ===== S8 — « Ajoute un bloc poème (verse) » : tier policy → l'agent corrige avec la suggestion =====
    const s8 = await rpc('create_block', {
      page_id: '2',
      block_name: 'core/verse',
      content: 'Rose, un vers',
      module: 'client',
      dry_run: true,
    })
    const s8Body = await s8.json()
    check(
      'S8 bloc legacy demandé → 400 block_legacy traduit avec suggestion',
      s8.status === 400 &&
        s8Body.error?.data?.code === 'block_legacy' &&
        s8Body.error?.data?.data?.suggested_block === 'core/preformatted' &&
        s8Body.error?.message?.includes('core/preformatted'),
      s8Body.error?.message ?? '',
    )
    const s8Fix = await rpc('create_block', {
      page_id: '2',
      block_name: 'core/preformatted',
      content: 'Rose, un vers',
      module: 'client',
      dry_run: true,
    })
    const s8FixBody = await s8Fix.json()
    check('S8 l\u2019agent applique la suggestion (dry_run) → succès', s8Fix.status === 200 && s8FixBody.result?.data?.dry_run === true, s8FixBody.error?.message ?? '')
    const afterS8 = await getPage('2')
    check('S8 aucun bloc créé (les deux appels en dry_run)', afterS8.content_md5 === afterS7.content_md5)

    // ===== S3 — « Fais une répétition générale avant de publier (dry_run) » =====
    const s3 = await rpc('update_block_content', {
      page_id: '2',
      ref: refA,
      new_content: 'Ne pas appliquer',
      expected_hash: afterS7.content_md5,
      dry_run: true,
    })
    const s3Body = await s3.json()
    check('S3 dry_run → dry_run:true', s3Body.result?.data?.dry_run === true, s3Body.error?.message ?? '')
    const after3 = await getPage('2')
    check('S3 dry_run : contenu inchangé (md5 identique)', after3.content_md5 === after2.content_md5)
    check('S3 dry_run : bloc A inchangé', after3.blocks.find((b) => b.ref === refA)?.content.includes('Ne pas appliquer') === false)

    // ===== S4 — « Fais ces deux corrections en une seule fois » =====
    const s4 = await rpc('update_blocks', {
      page_id: '2',
      updates: [
        { ref: refA, new_content: 'Avantage MCP v2' },
        { block_index: targetIdx, new_content: 'This is an example page. Offre spéciale : 89 €' },
      ],
      expected_hash: after3.content_md5,
    })
    const s4Body = await s4.json()
    check('S4 batch update_blocks → count=2', s4.status === 200 && s4Body.result?.data?.count === 2, s4Body.error?.message ?? '')
    const after4 = await getPage('2')
    check('S4 relecture : les 2 corrections appliquées', after4.blocks.find((b) => b.ref === refA)?.content.includes('v2') && after4.blocks[0].content.includes('89'))

    // ===== S5 — « Supprime l'ancienne offre » =====
    const s5 = await rpc('delete_block', { page_id: '2', ref: refA })
    check('S5 delete par ref → 200', s5.status === 200, (await s5.json()).error?.message ?? '')
    const after5 = await getPage('2')
    check('S5 relecture : bloc A disparu', after5.blocks.some((b) => b.ref === refA) === false)

    // ===== S6 — Conflit concurrent : un autre agent a modifié la page =====
    const md5Concurrent = after5.content_md5
    const s6Create = await rpc('create_block', {
      page_id: '2',
      block_name: 'core/paragraph',
      content: '<p>Modification concurrente</p>',
      module: 'autre-agent',
    })
    const s6CreateBody = await s6Create.json()
    check('S6 écriture concurrente (autre agent) → 200', s6Create.status === 200, s6CreateBody.error?.message ?? '')
    const refB = s6CreateBody.result?.data?.ref
    const s6Stale = await rpc('update_block_content', {
      page_id: '2',
      block_index: targetIdx,
      new_content: 'Écrase le concurrent',
      expected_hash: md5Concurrent,
    })
    const s6StaleBody = await s6Stale.json()
    check(
      'S6 update avec hash périmé → 409 traduit (conseil relecture)',
      s6Stale.status === 409 && s6StaleBody.error?.data?.code === 'error_conflict',
      s6StaleBody.error?.message ?? '',
    )
    const after6 = await getPage('2')
    check('S6 relecture : l’écriture périmée n’a PAS écrasé (bloc 0 inchangé)', after6.blocks[0].content.includes('89') && !after6.blocks[0].content.includes('Écrase le concurrent'))
    check('S6 relecture : le bloc du concurrent est présent', after6.blocks.some((b) => b.ref === refB))
    const s6Fresh = await rpc('update_block_content', {
      page_id: '2',
      block_index: targetIdx,
      new_content: 'This is an example page. Offre spéciale : 79 €',
      expected_hash: after6.content_md5,
    })
    const s6FreshBody = await s6Fresh.json()
    check('S6 relecture + hash frais → 200', s6Fresh.status === 200, s6FreshBody.error?.message ?? '')
    const after7 = await getPage('2')
    check('S6 relecture : correction appliquée après relecture', after7.blocks[0].content.includes('79'))

    // ===== S9-S12 : ops structurelles (2.7.0) sur la page 3 « Privacy Policy » (budget indépendant) =====
    const p3init = await getPage('3')
    const p3blocks0 = p3init.blocks
    check('S9 précondition : page 3 lisible (Privacy Policy)', p3blocks0.length >= 5, `count=${p3blocks0.length}`)
    const headingWho = p3blocks0.find((b) => b.content?.includes('Who we are'))

    // ===== S9 — « Remonte le paragraphe en haut de la page » =====
    const s9 = await rpc('move_block', {
      page_id: '3',
      block_index: '2',
      position: 'start',
      expected_hash: p3init.content_md5,
    })
    const s9Body = await s9.json()
    check('S9 move paragraphe → start : 200 + block_index 0', s9.status === 200 && s9Body.result?.data?.block_index === 0, s9Body.error?.message ?? '')
    const after9 = await getPage('3')
    check('S9 relecture : paragraphe en première position', after9.blocks[0]?.content?.includes('website address'))

    // ===== S10 — « Duplique le titre de section » =====
    const s10 = await rpc('duplicate_block', {
      page_id: '3',
      block_index: '1',
      expected_hash: after9.content_md5,
    })
    const s10Body = await s10.json()
    check('S10 duplicate heading → 200', s10.status === 200, s10Body.error?.message ?? '')
    const after10 = await getPage('3')
    check('S10 relecture : copie du heading juste après la source', after10.blocks[1]?.blockName === 'core/heading' && after10.blocks[2]?.blockName === 'core/heading')

    // ===== S11 — « Regroupe le paragraphe et le titre dans une section » =====
    const s11 = await rpc('wrap_block', {
      page_id: '3',
      block_index: '0',
      end_index: '1',
      expected_hash: after10.content_md5,
    })
    const s11Body = await s11.json()
    check('S11 wrap plage [0..1] → 200 + groupe', s11.status === 200 && s11Body.result?.data?.blockName === 'core/group', s11Body.error?.message ?? '')
    const after11 = await getPage('3')
    check('S11 relecture : groupe en position 0, blocs enrobés plus à la racine', after11.blocks[0]?.blockName === 'core/group' && !after11.blocks.some((b) => b.content?.includes('website address')))

    // ===== S12 — « Dégroupe la section, je veux retrouver mes blocs » =====
    const s12 = await rpc('unwrap_block', {
      page_id: '3',
      block_index: '0',
      expected_hash: after11.content_md5,
    })
    const s12Body = await s12.json()
    check('S12 unwrap du groupe → 200 + count>=2', s12.status === 200 && s12Body.result?.data?.count >= 2, s12Body.error?.message ?? '')
    const after12 = await getPage('3')
    check('S12 relecture : paragraphe + titre de retour à la racine', after12.blocks[0]?.blockName === 'core/paragraph' && after12.blocks[1]?.blockName === 'core/heading')

    // Nettoyage page 3 : delete de la copie + move retour (ancre relue après nettoyage)
    const delCopy = await rpc('delete_block', { page_id: '3', block_index: '2', expected_hash: after12.content_md5 })
    check('S12 cleanup delete copie → 200', delCopy.status === 200)
    const afterDel = await getPage('3')
    const whoNow = afterDel.blocks.find((b) => b.content?.includes('Who we are'))
    const mvBack = await rpc('move_block', {
      page_id: '3',
      block_index: '0',
      position: 'after',
      anchor_index: String(whoNow?.index ?? 1),
      expected_hash: afterDel.content_md5,
    })
    check('S12 cleanup move retour → 200', mvBack.status === 200, (await mvBack.json()).error?.message ?? '')
    const p3final = await getPage('3')
    check('page 3 structure logique restaurée (count initial)', p3final.blocks.length === p3blocks0.length, `before=${p3blocks0.length} after=${p3final.blocks.length}`)

    // ===== Nettoyage : restauration de la page 2 =====
    const delB = await rpc('delete_block', { page_id: '2', ref: refB })
    const delBBody = await delB.json()
    check('nettoyage delete bloc concurrent → 200', delB.status === 200, delBBody.error?.message ?? '')
    const md5Clean = (await getPage('2')).content_md5
    const restore = await rpc('update_blocks', {
      page_id: '2',
      updates: [{ block_index: targetIdx, new_content: blocks0.find((b) => b.index === 0).content }],
      expected_hash: md5Clean,
    })
    const restoreBody = await restore.json()
    check('nettoyage restauration bloc n°0 (batch) → count=1', restore.status === 200 && restoreBody.result?.data?.count === 1, restoreBody.error?.message ?? '')
    const finalPage = await getPage('2')
    check('page 2 restaurée (contenu visible du bloc 0 identique)', finalPage.blocks[0].content === blocks0.find((b) => b.index === 0).content)
    check('page 2 restaurée (count d’origine)', finalPage.blocks.length === blocks0.length, `before=${blocks0.length} after=${finalPage.blocks.length}`)
    console.log('NOTE: la restauration exacte (md5 d’origine) est faite par restauration de révision en post-run (wp eval-file), la sérialisation wp_kses_post pouvant reformater l’innerHTML.')

    const sse = await fetch(`http://localhost:${PORT}/mcp`, { headers: { 'x-hwt-token': HWT } })
    const sseText = await sse.text()
    const sseBody = JSON.parse(sseText.replace('data: ', '').replace('\n\n', ''))
    check(
      'SSE : tools 2.4.0-2.7.0 listés',
      sse.status === 200 &&
        ['update_blocks', 'transform_block', 'move_block', 'duplicate_block', 'wrap_block', 'unwrap_block'].every((n) => sseBody.tools.some((t) => t.name === n)),
    )
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
