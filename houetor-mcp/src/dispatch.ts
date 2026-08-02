// Dispatcher des méthodes JSON-RPC — même contrat que app/mcp/dispatch.ts (prod) :
// chaque handler retourne un objet ou lève une erreur; route.ts convertit en JSON-RPC.

import { WordPressClient } from './client.js'

export interface DispatchContext {
  userId: string
  profil: string | null
  wp: WordPressClient
}

export class MethodError extends Error {
  status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

function ok(data: unknown) {
  return { success: true, data }
}

function requireParams(params: Record<string, unknown>, keys: string[]): void {
  for (const key of keys) {
    if (params[key] === undefined || params[key] === null || params[key] === '') {
      throw new MethodError(400, `parametre manquant ou vide: ${key}`)
    }
  }
}

function boolParam(params: Record<string, unknown>, key: string): boolean | undefined {
  if (params[key] === undefined) return undefined
  if (params[key] === true || params[key] === 'true' || params[key] === 1 || params[key] === '1') return true
  return false
}

export async function dispatch(method: string, params: Record<string, unknown>, ctx: DispatchContext) {
  const { wp } = ctx

  switch (method) {
    case 'get_wp_pages':
      return ok((await wp.getPages()).data)

    case 'get_wp_menus':
      return ok((await wp.getMenus()).data)

    case 'get_page_blocks': {
      requireParams(params, ['page_id'])
      return ok((await wp.getPageBlocks(String(params.page_id))).data)
    }

    case 'inject_page': {
      requireParams(params, ['page_id', 'html'])
      return ok(
        (
          await wp.inject({
            page_id: String(params.page_id),
            content: String(params.html),
            module: params.module ? String(params.module) : undefined,
            block_id: params.block_id ? String(params.block_id) : undefined,
            position: params.position ? String(params.position) : undefined,
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'uninject_page': {
      requireParams(params, ['page_id', 'module', 'block_id'])
      return ok(
        (
          await wp.uninject({
            page_id: String(params.page_id),
            module: String(params.module),
            block_id: String(params.block_id),
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'create_block': {
      requireParams(params, ['page_id', 'block_name'])
      return ok(
        (
          await wp.createBlock({
            page_id: String(params.page_id),
            block_name: String(params.block_name),
            content: params.content ? String(params.content) : undefined,
            module: params.module ? String(params.module) : undefined,
            position: params.position ? String(params.position) : undefined,
            anchor_ref: params.anchor_ref ? String(params.anchor_ref) : undefined,
            anchor_index: params.anchor_index ? String(params.anchor_index) : undefined,
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'update_block_content': {
      requireParams(params, ['page_id', 'new_content'])
      if (!params.ref && !params.block_index) {
        throw new MethodError(400, 'parametre manquant: ref ou block_index obligatoire')
      }
      return ok(
        (
          await wp.updateBlockContent({
            page_id: String(params.page_id),
            ref: params.ref ? String(params.ref) : undefined,
            block_index: params.block_index ? String(params.block_index) : undefined,
            new_content: String(params.new_content),
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'delete_block': {
      requireParams(params, ['page_id'])
      if (!params.ref && !params.block_index) {
        throw new MethodError(400, 'parametre manquant: ref ou block_index obligatoire')
      }
      return ok(
        (
          await wp.deleteBlock({
            page_id: String(params.page_id),
            ref: params.ref ? String(params.ref) : undefined,
            block_index: params.block_index ? String(params.block_index) : undefined,
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'update_blocks': {
      requireParams(params, ['page_id', 'updates'])
      const updates = params.updates
      if (!Array.isArray(updates) || updates.length === 0) {
        throw new MethodError(400, 'parametre invalide: updates doit etre un tableau non vide')
      }
      for (const u of updates) {
        const entry = u as Record<string, unknown>
        if (!entry || typeof entry !== 'object') {
          throw new MethodError(400, 'parametre invalide: chaque update doit etre un objet {ref|block_index, new_content}')
        }
        if (entry.new_content === undefined || entry.new_content === '') {
          throw new MethodError(400, 'parametre manquant: new_content requis dans chaque update')
        }
        if (!entry.ref && (entry.block_index === undefined || entry.block_index === '')) {
          throw new MethodError(400, 'parametre manquant: ref ou block_index obligatoire dans chaque update')
        }
      }
      return ok(
        (
          await wp.batchUpdateBlocks({
            page_id: String(params.page_id),
            updates: updates.map((u) => {
              const entry = u as Record<string, unknown>
              return {
                ref: entry.ref ? String(entry.ref) : undefined,
                block_index: entry.block_index !== undefined ? String(entry.block_index) : undefined,
                new_content: String(entry.new_content),
              }
            }),
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'transform_block': {
      requireParams(params, ['page_id', 'target_block_name'])
      if (!params.ref && !params.block_index) {
        throw new MethodError(400, 'parametre manquant: ref ou block_index obligatoire')
      }
      return ok(
        (
          await wp.transformBlock({
            page_id: String(params.page_id),
            ref: params.ref ? String(params.ref) : undefined,
            block_index: params.block_index ? String(params.block_index) : undefined,
            target_block_name: String(params.target_block_name),
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'move_block': {
      requireParams(params, ['page_id', 'position'])
      if (!params.ref && !params.block_index) {
        throw new MethodError(400, 'parametre manquant: ref ou block_index obligatoire')
      }
      return ok(
        (
          await wp.moveBlock({
            page_id: String(params.page_id),
            ref: params.ref ? String(params.ref) : undefined,
            block_index: params.block_index ? String(params.block_index) : undefined,
            position: String(params.position),
            anchor_ref: params.anchor_ref ? String(params.anchor_ref) : undefined,
            anchor_index: params.anchor_index ? String(params.anchor_index) : undefined,
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'duplicate_block': {
      requireParams(params, ['page_id'])
      if (!params.ref && !params.block_index) {
        throw new MethodError(400, 'parametre manquant: ref ou block_index obligatoire')
      }
      return ok(
        (
          await wp.duplicateBlock({
            page_id: String(params.page_id),
            ref: params.ref ? String(params.ref) : undefined,
            block_index: params.block_index ? String(params.block_index) : undefined,
            module: params.module ? String(params.module) : undefined,
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'wrap_block': {
      requireParams(params, ['page_id'])
      if (!params.ref && !params.block_index) {
        throw new MethodError(400, 'parametre manquant: ref ou block_index obligatoire')
      }
      return ok(
        (
          await wp.wrapBlock({
            page_id: String(params.page_id),
            ref: params.ref ? String(params.ref) : undefined,
            block_index: params.block_index ? String(params.block_index) : undefined,
            end_ref: params.end_ref ? String(params.end_ref) : undefined,
            end_index: params.end_index ? String(params.end_index) : undefined,
            module: params.module ? String(params.module) : undefined,
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'unwrap_block': {
      requireParams(params, ['page_id'])
      if (!params.ref && !params.block_index) {
        throw new MethodError(400, 'parametre manquant: ref ou block_index obligatoire')
      }
      return ok(
        (
          await wp.unwrapBlock({
            page_id: String(params.page_id),
            ref: params.ref ? String(params.ref) : undefined,
            block_index: params.block_index ? String(params.block_index) : undefined,
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
            dry_run: boolParam(params, 'dry_run'),
          })
        ).data,
      )
    }

    case 'export_to_wordpress': {
      requireParams(params, ['page_id', 'html', 'module'])
      const { data } = await wp.inject({
        page_id: String(params.page_id),
        content: String(params.html),
        module: String(params.module),
      })
      return ok(data)
    }

    case 'list_connected_sites': {
      return ok({
        sites: [{ id: 'local', url: ctx.wp.baseUrl, label: 'Site lab (env WORDPRESS_URL)' }],
      })
    }

    default:
      throw new MethodError(404, `methode inconnue: ${method}`)
  }
}
