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
const articleChat = z.object({
  speakers: z.array(z.object({ id: z.string().min(1), name: z.string().min(1), icon: z.string().min(1), image_id: z.number().int().nonnegative(), align: z.enum(['start', 'center', 'end']) })).min(3),
  messages: z.array(z.object({ speaker: z.string().min(1), text: z.string().min(1) })).min(6)
}).describe('Conversación narrativa contextual de Empresario, MaryIA y Daniel. Debe aparecer una sola vez en la plantilla del artículo.');
server.tool('crear_borrador_seo', 'Guarda en WordPress el artículo redactado por Codex en esta sesión. El chat editorial es obligatorio; nunca publica contenido ni llama a una API de IA.', {
  primary_keyword: z.string().min(2).describe('Keyword principal del artículo.'),
  title: z.string().min(5).describe('Título SEO redactado por Codex.'),
  description: z.string().min(30).describe('Meta description redactada por Codex.'),
  content: z.string().min(100).describe('Artículo HTML redactado por Codex en esta sesión, sin H1 porque WordPress muestra el título de la entrada.'),
  secondary_keywords: z.array(z.string()).max(4).optional().describe('Keywords secundarias relevantes.'),
  digitalisimo_article_chat: articleChat,
  pillar_id: z.number().int().positive().optional().describe('ID del contenido pilar existente.'),
  post_type: z.enum(['post', 'page']).optional().describe('Tipo de borrador; post por defecto.')
}, async (input) => { const article = { ...input, meta_description: input.description }; delete article.description; return { content: [{ type: 'text', text: JSON.stringify(await request('digitalisimo-publisher/v1/articles', { method: 'POST', body: JSON.stringify(article) }), null, 2) }] }; });
server.tool('subir_imagen_destacada', 'Sube una imagen creada por Codex y la asigna al borrador. Requiere activar la opción MCP de imágenes en IA Tools.', {
  post_id: z.number().int().positive(), image_path: z.string().min(1), image_title: z.string().min(3), image_alt: z.string().min(3)
}, async ({ post_id, image_path, image_title, image_alt }) => {
  const file = await import('node:fs').then(({ readFileSync }) => new Blob([readFileSync(image_path)]));
  const form = new FormData(); form.append('post_id', String(post_id)); form.append('image_title', image_title); form.append('image_alt', image_alt); form.append('file', file, image_title.toLowerCase().replace(/[^a-z0-9]+/gi, '-') + '.png');
  const response = await fetch(`${site}/wp-json/digitalisimo/v1/import-image`, { method: 'POST', headers: { Authorization: authorization }, body: form }); const data = await response.json(); if (!response.ok) throw new Error(data.message || `WordPress respondió HTTP ${response.status}`); return { content: [{ type: 'text', text: JSON.stringify(data, null, 2) }] };
});

await server.connect(new StdioServerTransport());
