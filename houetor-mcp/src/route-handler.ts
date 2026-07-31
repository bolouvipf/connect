// Handler HTTP JSON-RPC 2.0 + SSE — même logique que app/mcp/route.ts (prod) :
// POST = appels de méthodes, GET = listing SSE des tools filtrés par profil,
// auth par header X-HWT-Token, codes d'erreur JSON-RPC -32000/-32601/-32600/-32603.

import { parseHWT } from './parser.js'
import { HWT_TOOLS } from './tools.js'
import { dispatch, DispatchContext, MethodError } from './dispatch.js'
import { WordPressClient, WordPressClientError } from './client.js'

export const HWT_PROFILES = ['ONG', 'BOUTIQUE', 'COACH', 'CM', 'MARKETING']

export interface ServerConfig {
  baseUrl: string
  token: string
}

function jsonRpcError(code: number, message: string, status: number): Response {
  return new Response(JSON.stringify({ jsonrpc: '2.0', error: { code, message } }), {
    status,
    headers: { 'content-type': 'application/json' },
  })
}

function httpStatusForJsonRpc(code: number): number {
  switch (code) {
    case -32000:
      return 401
    case -32601:
      return 404
    default:
      return 400
  }
}

function toolsForProfil(profil: string | null): typeof HWT_TOOLS {
  return HWT_TOOLS.filter((tool) => profil === null || tool.profiles.includes(profil))
}

export async function handleRequest(
  req: Request,
  config: ServerConfig,
): Promise<Response> {
  const hwtToken = req.headers.get('x-hwt-token') ?? ''
  const parsed = parseHWT(hwtToken)

  if (!parsed) {
    return new Response(
      JSON.stringify({ jsonrpc: '2.0', error: { code: -32000, message: 'Token HWT invalide ou manquant (header X-HWT-Token)' } }),
      { status: 401, headers: { 'content-type': 'application/json' } },
    )
  }

  const { profil, uuid } = parsed

  if (req.method === 'GET') {
    const encoder = new TextEncoder()
    const body = new ReadableStream<Uint8Array>({
      start(controller) {
        controller.enqueue(encoder.encode(`data: ${JSON.stringify({ profil, uuid, tools: toolsForProfil(profil) })}\n\n`))
        controller.close()
      },
    })
    return new Response(body, {
      status: 200,
      headers: {
        'content-type': 'text/event-stream; charset=utf-8',
        'cache-control': 'no-cache',
        connection: 'keep-alive',
      },
    })
  }

  if (req.method !== 'POST') {
    return new Response(JSON.stringify({ error: 'méthode HTTP non supportée' }), {
      status: 405,
      headers: { 'content-type': 'application/json' },
    })
  }

  let payload: any
  try {
    payload = await req.json()
  } catch {
    return jsonRpcError(-32600, 'Corps JSON invalide (format JSON-RPC 2.0 attendu)', httpStatusForJsonRpc(-32600))
  }

  const { jsonrpc, method, params = {}, id = null } = payload ?? {}

  if (jsonrpc !== '2.0' || typeof method !== 'string' || method.length === 0) {
    return jsonRpcError(-32600, 'Requête invalide : jsonrpc "2.0" et method non vide requis', httpStatusForJsonRpc(-32600))
  }

  const tool = HWT_TOOLS.find((t) => t.name === method)
  if (!tool) {
    return jsonRpcError(-32601, `Méthode inconnue: ${method}`, httpStatusForJsonRpc(-32601))
  }

  if (profil !== null && !tool.profiles.includes(profil)) {
    return jsonRpcError(-32000, `Le profil ${profil} n'est pas autorisé pour ${method}`, httpStatusForJsonRpc(-32000))
  }

  const ctx: DispatchContext = {
    userId: uuid,
    profil,
    wp: new WordPressClient(config.baseUrl, config.token),
  }

  try {
    const result = await dispatch(method, params, ctx)
    return new Response(JSON.stringify({ jsonrpc: '2.0', id, result }), {
      status: 200,
      headers: { 'content-type': 'application/json' },
    })
  } catch (err) {
    if (err instanceof MethodError) {
      return jsonRpcError(-32003, err.message, httpStatusForJsonRpc(-32003))
    }
    if (err instanceof WordPressClientError) {
      return new Response(
        JSON.stringify({
          jsonrpc: '2.0',
          id,
          error: {
            code: err.status >= 500 ? -32003 : -32002,
            message: err.message,
            data: { status: err.status, code: err.code },
          },
        }),
        { status: err.status >= 500 ? 502 : err.status, headers: { 'content-type': 'application/json' } },
      )
    }
    return jsonRpcError(-32603, `Erreur interne: ${(err as Error).message}`, httpStatusForJsonRpc(-32603))
  }
}
