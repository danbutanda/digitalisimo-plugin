import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';

const site = (process.env.DIGITALISIMO_WP_URL || '').replace(/\/$/, '');
const username = process.env.DIGITALISIMO_WP_USERNAME || '';
const password = process.env.DIGITALISIMO_WP_APP_PASSWORD || '';
if (!site || !username || !password) throw new Error('Configura DIGITALISIMO_WP_URL, DIGITALISIMO_WP_USERNAME y DIGITALISIMO_WP_APP_PASSWORD.');
const authorization = `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`;
async function request(path, init = {}) {
  const response = await fetch(`${site}/wp-json/digitalisimo-mcp/v1/${path}`, { ...init, headers: { Authorization: authorization, 'Content-Type': 'application/json', ...(init.headers || {}) } });
  const data = await response.json();
  if (!response.ok) throw new Error(data.message || `WordPress respondió HTTP ${response.status}`);
  return data;
}
const server = new McpServer({ name: 'digitalisimo-wordpress', version: '1.0.0' });
server.tool('listar_clusters_seo', 'Lista contenidos pilar y sus artículos relacionados antes de crear un nuevo artículo.', {}, async () => ({ content: [{ type: 'text', text: JSON.stringify(await request('clusters'), null, 2) }] }));
server.tool('crear_borrador_seo', 'Guarda en WordPress el artículo redactado por Codex en esta sesión. Nunca publica contenido ni llama a una API de IA.', {
  primary_keyword: z.string().min(2).describe('Keyword principal del artículo.'),
  title: z.string().min(5).describe('Título SEO redactado por Codex.'),
  description: z.string().min(30).describe('Meta description redactada por Codex.'),
  content: z.string().min(100).describe('Artículo HTML redactado por Codex en esta sesión, sin H1 porque WordPress muestra el título de la entrada.'),
  secondary_keywords: z.array(z.string()).max(4).optional().describe('Keywords secundarias relevantes.'),
  pillar_id: z.number().int().positive().optional().describe('ID del contenido pilar existente.'),
  post_type: z.enum(['post', 'page']).optional().describe('Tipo de borrador; post por defecto.')
}, async (input) => ({ content: [{ type: 'text', text: JSON.stringify(await request('create-draft', { method: 'POST', body: JSON.stringify(input) }), null, 2) }] }));
await server.connect(new StdioServerTransport());
