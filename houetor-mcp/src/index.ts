// Point d'entrée CLI du serveur MCP lab.
// Usage : WORDPRESS_URL=http://localhost:8888 HOUETOR_TOKEN=<hwc_token> npm start
// Exporte aussi createMcpServer pour les tests d'intégration.

import { createMcpServer } from './server.js'

export { createMcpServer }
export type { ServerConfig } from './route-handler.js'
export { handleRequest } from './route-handler.js'
export { HWT_TOOLS } from './tools.js'
export { parseHWT } from './parser.js'

function main() {
  const PORT = Number(process.env.PORT ?? 8890)
  const baseUrl = process.env.WORDPRESS_URL ?? ''
  const token = process.env.HOUETOR_TOKEN ?? ''

  if (!baseUrl || !token) {
    console.error(
      'Env manquante: WORDPRESS_URL (ex: http://localhost:8888) et HOUETOR_TOKEN (hwc_token du plugin) sont obligatoires.',
    )
    process.exit(1)
  }

  const server = createMcpServer({ baseUrl, token })

  server.listen(PORT, () => {
    console.log(`HOUETOR MCP lab — http://localhost:${PORT}/mcp (JSON-RPC 2.0 POST + SSE GET)`)
    console.log(`Site cible : ${baseUrl} — token: ${token.slice(0, 4)}…${token.slice(-4)}`)
  })
}

main()
