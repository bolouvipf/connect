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
            html: String(params.html),
            module: params.module ? String(params.module) : undefined,
            block_id: params.block_id ? String(params.block_id) : undefined,
            position: params.position ? String(params.position) : undefined,
            expected_hash: params.expected_hash ? String(params.expected_hash) : undefined,
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
          })
        ).data,
      )
    }

    case 'export_to_wordpress': {
      requireParams(params, ['page_id', 'html', 'module'])
      const { data } = await wp.inject({
        page_id: String(params.page_id),
        html: String(params.html),
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
