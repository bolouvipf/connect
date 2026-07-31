// Fabrique du serveur HTTP /mcp (JSON-RPC POST + SSE GET). Séparée du CLI pour
// être réutilisable par les tests d'intégration.

import { createServer, Server } from 'node:http'
import { handleRequest, ServerConfig } from './route-handler.js'

export function createMcpServer(config: ServerConfig): Server {
  return createServer((nodeReq, nodeRes) => {
    const url = new URL(nodeReq.url ?? '/', `http://${nodeReq.headers.host ?? 'localhost'}`)

    if (url.pathname !== '/mcp') {
      nodeRes.writeHead(404, { 'content-type': 'application/json' })
      nodeRes.end(JSON.stringify({ error: 'not_found' }))
      return
    }

    const headers: Record<string, string> = {}
    for (const [key, value] of Object.entries(nodeReq.headers)) {
      if (typeof value === 'string') headers[key] = value
    }

    const chunks: Buffer[] = []
    nodeReq.on('data', (chunk) => chunks.push(chunk))
    nodeReq.on('end', async () => {
      const body = Buffer.concat(chunks).toString('utf8')
      const req = new Request(url.href, {
        method: nodeReq.method ?? 'GET',
        headers,
        body: body.length > 0 ? body : undefined,
      })

      try {
        const res = await handleRequest(req, config)
        nodeRes.writeHead(res.status, Object.fromEntries(res.headers.entries()))
        nodeRes.end(Buffer.from(await res.arrayBuffer()))
      } catch (err) {
        nodeRes.writeHead(500, { 'content-type': 'application/json' })
        nodeRes.end(JSON.stringify({ jsonrpc: '2.0', error: { code: -32603, message: `erreur interne: ${(err as Error).message}` } }))
      }
    })
  })
}
