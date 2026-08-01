// Client REST vers l'API de houetor-connect (v2.4.0). Même convention que
// dispatch.ts (prod) : header X-Houetor-Token + endpoints /pages /menus /inject
// /uninject /page-blocks /block-content /blocks /blocks/batch-update,
// erreurs traduites. Paramètre dry_run sur toutes les écritures.

import { translateError } from './error-translator.js'

export class WordPressClientError extends Error {
  status: number
  code: string
  data?: any

  constructor(status: number, code: string, message: string, data?: any) {
    super(message)
    this.status = status
    this.code = code
    this.data = data
  }
}

export interface RequestOptions {
  method: string
  path: string
  body?: Record<string, unknown>
  rawBody?: string
}

export class WordPressClient {
  baseUrl: string

  constructor(
    baseUrl: string,
    private token: string,
  ) {
    this.baseUrl = baseUrl
  }

  private async request<T>({ method, path, body, rawBody }: RequestOptions): Promise<{ status: number; data: T }> {
    const res = await fetch(`${this.baseUrl}/wp-json/houetor/v1${path}`, {
      method,
      headers: {
        'X-Houetor-Token': this.token,
        'Content-Type': 'application/json',
      },
      body: rawBody ?? (body ? JSON.stringify(body) : undefined),
    })

    let data: any = null
    try {
      data = await res.json()
    } catch {
      data = null
    }

    if (!res.ok) {
      const t = translateError(res.status, data, res.statusText)
      throw new WordPressClientError(t.status, t.code, t.message, data?.data ?? undefined)
    }

    return { status: res.status, data: data as T }
  }

  getPages() {
    return this.request({ method: 'GET', path: '/pages' })
  }

  getMenus() {
    return this.request({ method: 'GET', path: '/menus' })
  }

  getPageBlocks(pageId: string) {
    return this.request({ method: 'GET', path: `/page-blocks?page_id=${encodeURIComponent(pageId)}` })
  }

  inject(params: {
    page_id: string
    content: string
    module?: string
    block_id?: string
    position?: string
    expected_hash?: string
    dry_run?: boolean
  }) {
    return this.request({ method: 'POST', path: '/inject', body: params })
  }

  uninject(params: {
    page_id: string
    module: string
    block_id: string
    expected_hash?: string
    dry_run?: boolean
  }) {
    return this.request({ method: 'POST', path: '/uninject', body: params })
  }

  createBlock(params: {
    page_id: string
    block_name: string
    content?: string
    module?: string
    position?: string
    anchor_ref?: string
    anchor_index?: string
    expected_hash?: string
    dry_run?: boolean
  }) {
    return this.request({ method: 'POST', path: '/blocks', body: params })
  }

  updateBlockContent(params: {
    page_id: string
    ref?: string
    block_index?: string
    new_content: string
    expected_hash?: string
    dry_run?: boolean
  }) {
    return this.request({ method: 'PATCH', path: '/block-content', body: params })
  }

  deleteBlock(params: {
    page_id: string
    ref?: string
    block_index?: string
    expected_hash?: string
    dry_run?: boolean
  }) {
    return this.request({ method: 'DELETE', path: '/blocks', body: params })
  }

  batchUpdateBlocks(params: {
    page_id: string
    updates: Array<{ ref?: string; block_index?: string; new_content: string }>
    expected_hash?: string
    dry_run?: boolean
  }) {
    return this.request({ method: 'POST', path: '/blocks/batch-update', body: params })
  }

  transformBlock(params: {
    page_id: string
    ref?: string
    block_index?: string
    target_block_name: string
    expected_hash?: string
    dry_run?: boolean
  }) {
    return this.request({ method: 'POST', path: '/blocks/transform', body: params })
  }
}
