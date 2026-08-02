import { NextRequest, NextResponse } from 'next/server'
import { parseHWT } from './parser'
import { HWT_TOOLS } from './tools'
import { dispatch, ALLOWED_METHODS } from './dispatch'

export const runtime = 'edge'

function jsonRpcError(code: number, message: string, id: unknown) {
  return NextResponse.json(
    { jsonrpc: '2.0', error: { code, message }, id },
    { status: code === -32000 ? 401 : code === -32601 ? 404 : 400 }
  )
}

function jsonRpcSuccess(result: unknown, id: unknown) {
  return NextResponse.json({ jsonrpc: '2.0', result, id })
}

export async function POST(req: NextRequest) {
  try {
    const token = req.headers.get('X-HWT-Token')
    if (!token) return jsonRpcError(-32000, 'unauthorized: X-HWT-Token manquant', null)

    const parsed = parseHWT(token)
    if (!parsed) return jsonRpcError(-32000, 'unauthorized: token invalide', null)

    const body = await req.json()
    if (!body || body.jsonrpc !== '2.0' || !body.method) {
      return jsonRpcError(-32600, 'invalid request: jsonrpc 2.0 requis avec method', body?.id ?? null)
    }

    const { method, params, id } = body

    if (!ALLOWED_METHODS.includes(method)) {
      return jsonRpcError(-32601, `method not found: ${method}`, id)
    }

    const tool = HWT_TOOLS.find(t => t.name === method)
    if (tool && parsed.profil && !tool.profiles.includes(parsed.profil)) {
      return jsonRpcError(-32000, `unauthorized: ${method} non autorisé pour le profil ${parsed.profil}`, id)
    }

    const result = await dispatch(method, params ?? {}, parsed.uuid)
    return jsonRpcSuccess(result, id)
  } catch (err) {
    return jsonRpcError(-32603, `internal error: ${String(err)}`, null)
  }
}

export async function GET(req: NextRequest) {
  try {
    const token = req.headers.get('X-HWT-Token')
    if (!token) return jsonRpcError(-32000, 'unauthorized: X-HWT-Token manquant', null)

    const parsed = parseHWT(token)
    if (!parsed) return jsonRpcError(-32000, 'unauthorized: token invalide', null)

    const availableTools = HWT_TOOLS.filter(t => !parsed.profil || t.profiles.includes(parsed.profil))

    const encoder = new TextEncoder()
    const stream = new ReadableStream({
      start(controller) {
        controller.enqueue(encoder.encode(`data: ${JSON.stringify({ profil: parsed.profil, uuid: parsed.uuid, tools: availableTools.map(t => ({ name: t.name, description: t.description, params: t.params })) })}\n\n`))
        controller.close()
      },
    })

    return new NextResponse(stream, {
      headers: {
        'Content-Type': 'text/event-stream',
        'Cache-Control': 'no-cache',
        Connection: 'keep-alive',
      },
    })
  } catch (err) {
    return jsonRpcError(-32603, `internal error: ${String(err)}`, null)
  }
}
