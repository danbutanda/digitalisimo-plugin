#!/usr/bin/env node
import { readFile, realpath } from 'node:fs/promises';
import path from 'node:path';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import { createWordPressClient, publicSites, readSites } from './wordpress.mjs';

function toolResult(value) {
  return { content: [{ type: 'text', text: JSON.stringify(value) }] };
}
function wrap(fn) {
  return async args => {
    try { return toolResult(await fn(args)); }
    catch (error) { return { isError: true, content: [{ type: 'text', text: error.message || 'Error no identificado.' }] }; }
  };
}
async function readApprovedImage(file) {
  const root = process.env.DIGITALISIMO_IMAGE_DIR;
  if (!root) throw new Error('Configura DIGITALISIMO_IMAGE_DIR para leer imágenes locales.');
  const safeRoot = await realpath(root);
  const safeFile = await realpath(file);
  if (!safeFile.startsWith(safeRoot + path.sep)) throw new Error('La imagen debe estar dentro de DIGITALISIMO_IMAGE_DIR.');
  const bytes = await readFile(safeFile);
  if (bytes.length > 8 * 1024 * 1024) throw new Error('La imagen supera los 8 MB.');
  return bytes;
}

export function makeMcpServer(sites) {
  const wp = createWordPressClient(sites);
  const server = new McpServer(
    { name: 'digitalisimo-wp', version: '0.1.0' },
    { instructions: 'Solo crear borradores. Usar list_sites y get_site antes de enviar contenido. La imagen debe subirse primero al WordPress correcto; usar el ID devuelto en create_draft. No inventar IDs de medios ni compartir credenciales.' }
  );

  server.registerTool('list_sites', {
    title: 'Listar WordPress autorizados',
    description: 'Muestra únicamente alias, nombre y URL de instalaciones WordPress configuradas, sin credenciales.',
    inputSchema: {}
  }, wrap(async () => ({ sites: publicSites(sites) })));

  server.registerTool('get_site', {
    title: 'Consultar sitio y módulo SEO',
    description: 'Valida permisos, instalación DIGITALÍSIMO SEO y rutas de un sitio. Úsalo antes de crear contenido.',
    inputSchema: { site: z.string().min(1) }
  }, wrap(async ({ site }) => wp.siteInfo(site)));

  server.registerTool('upload_image', {
    title: 'Enviar imagen a Biblioteca de Medios',
    description: 'Envía PNG/JPG/WEBP/GIF en base64 (máx. 8MB). Requiere bytes accesibles para el cliente MCP. Devuelve ID para featured_media.',
    inputSchema: {
      site: z.string().min(1),
      image_base64: z.string().min(1),
      mime_type: z.enum(['image/png', 'image/jpeg', 'image/webp', 'image/gif']),
      filename: z.string().min(1),
      image_alt: z.string().optional()
    }
  }, wrap(async ({ site, ...image }) => wp.uploadImage(site, image)));

  server.registerTool('upload_local_image', {
    title: 'Enviar imagen local autorizada',
    description: 'Uso desde VS Code/cliente local. Solo permite leer archivos dentro del directorio DIGITALISIMO_IMAGE_DIR configurado.',
    inputSchema: {
      site: z.string().min(1),
      image_path: z.string().min(1),
      mime_type: z.enum(['image/png', 'image/jpeg', 'image/webp', 'image/gif']),
      image_alt: z.string().optional()
    }
  }, wrap(async ({ site, image_path, mime_type, image_alt }) => {
    const bytes = await readApprovedImage(image_path);
    return wp.uploadImage(site, {
      image_base64: bytes.toString('base64'), mime_type,
      filename: path.basename(image_path), image_alt
    });
  }));

  server.registerTool('create_draft', {
    title: 'Crear borrador con SEO DIGITALÍSIMO',
    description: 'Crea un borrador; nunca publica. El sitio necesita DIGITALÍSIMO SEO habilitado. featured_media debe ser ID del MISMO sitio.',
    inputSchema: {
      site: z.string().min(1),
      title: z.string().min(1),
      content: z.string().min(1),
      seo_title: z.string().optional(),
      slug: z.string().optional(),
      meta_description: z.string().min(1),
      primary_keyword: z.string().min(1),
      secondary_keywords: z.array(z.string()).optional(),
      excerpt: z.string().optional(),
      canonical: z.string().url().optional(),
      social_title: z.string().optional(),
      social_description: z.string().optional(),
      featured_media: z.number().int().positive().optional(),
      image_alt: z.string().optional(),
      category_ids: z.array(z.number().int().positive()).optional(),
      pillar_id: z.number().int().positive().optional(),
      post_type: z.enum(['post', 'page']).optional()
    }
  }, wrap(async ({ site, ...article }) => wp.createDraft(site, article)));
  return server;
}

if (process.argv[1] && path.resolve(process.argv[1]) === path.resolve(new URL(import.meta.url).pathname)) {
  try {
    const server = makeMcpServer(readSites());
    await server.connect(new StdioServerTransport());
    console.error('DIGITALÍSIMO MCP listo (stdio).');
  } catch (error) {
    console.error('Error iniciando DIGITALÍSIMO MCP: ' + error.message);
    process.exitCode = 1;
  }
}
