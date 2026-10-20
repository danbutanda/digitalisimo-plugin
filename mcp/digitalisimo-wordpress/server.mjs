#!/usr/bin/env node
// MCP de DIGITALÍSIMO para WordPress: guarda como borradores los artículos que redacta el cliente MCP, usando la API
// editorial de DIGITALÍSIMO IA Tools de cada sitio. Nunca publica ni llama a un proveedor de IA. Ver README.md.
import { readFile, realpath } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import { MAX_IMAGE_BYTES, createWordPressClient, imageFile, imageFromBase64, publicSites, sitesFromEnv } from './wordpress.mjs';

function wrap(fn) {
  return async (args) => {
    try { return { content: [{ type: 'text', text: JSON.stringify(await fn(args), null, 2) }] }; }
    catch (error) { return { isError: true, content: [{ type: 'text', text: error.message || 'Error no identificado.' }] }; }
  };
}

/** Lee una imagen local; con DIGITALISIMO_IMAGE_DIR sólo se admiten archivos dentro de esa carpeta. */
async function readLocalImage(file, imageDir) {
  const safeFile = await realpath(file);
  if (imageDir) {
    const safeRoot = await realpath(imageDir);
    if (!safeFile.startsWith(safeRoot + path.sep)) throw new Error('La imagen debe estar dentro de DIGITALISIMO_IMAGE_DIR.');
  }
  const bytes = await readFile(safeFile);
  if (bytes.length > MAX_IMAGE_BYTES) throw new Error('La imagen supera los 8 MB.');
  return { bytes, name: path.basename(safeFile) };
}

const site = z.string().min(1).optional().describe('Alias del sitio (listar_sitios). Opcional si sólo hay uno.');

export const articleChat = z.object({
  speakers: z.array(z.object({ id: z.string().min(1), name: z.string().min(1), icon: z.string().min(1), image_id: z.number().int().nonnegative(), align: z.enum(['start', 'center', 'end']) })).min(3),
  messages: z.array(z.object({ speaker: z.string().min(1), text: z.string().min(1) })).min(6),
}).describe('Conversación narrativa contextual de Empresario, MaryIA y Daniel. Debe aparecer una sola vez en la plantilla del artículo.');

export function makeMcpServer(sites, { fetcher, imageDir = process.env.DIGITALISIMO_IMAGE_DIR } = {}) {
  const wp = createWordPressClient(sites, fetcher);
  const server = new McpServer(
    { name: 'digitalisimo-wordpress', version: '2.0.0' },
    { instructions: 'Sólo crea borradores. Con varios sitios, usa listar_sitios y consultar_sitio antes de enviar contenido. Una imagen se sube al mismo sitio que el borrador; no inventes IDs de medios, categorías ni pilares, ni compartas credenciales.' },
  );

  server.registerTool('listar_sitios', {
    title: 'Listar sitios WordPress',
    description: 'Alias, nombre y URL de los sitios configurados, sin credenciales.',
    inputSchema: {},
  }, wrap(async () => ({ sites: publicSites(sites) })));

  server.registerTool('consultar_sitio', {
    title: 'Consultar sitio',
    description: 'Comprueba permisos, SEO activo, tipos de contenido permitidos y soporte del chat editorial antes de crear contenido.',
    inputSchema: { site },
  }, wrap(async (args) => wp.siteInfo(args.site)));

  server.registerTool('listar_clusters_seo', {
    title: 'Listar clusters SEO',
    description: 'Lista contenidos pilar y sus artículos relacionados antes de crear un nuevo artículo.',
    inputSchema: { site },
  }, wrap(async (args) => wp.clusters(args.site)));

  server.registerTool('crear_borrador_seo', {
    title: 'Crear borrador SEO',
    description: 'Guarda en WordPress el artículo redactado en esta sesión, con sus metadatos SEO y el chat editorial obligatorio. Nunca publica contenido ni llama a una API de IA.',
    inputSchema: {
      site,
      primary_keyword: z.string().min(2).describe('Keyword principal del artículo.'),
      title: z.string().min(5).describe('Título de la entrada.'),
      description: z.string().min(30).describe('Meta description.'),
      content: z.string().min(100).describe('Artículo HTML sin H1: WordPress ya muestra el título de la entrada.'),
      secondary_keywords: z.array(z.string()).max(4).optional().describe('Keywords secundarias relevantes.'),
      digitalisimo_article_chat: articleChat,
      pillar_id: z.number().int().positive().optional().describe('ID del contenido pilar existente (listar_clusters_seo).'),
      post_type: z.enum(['post', 'page']).optional().describe('Tipo de borrador; post por defecto.'),
      seo_title: z.string().optional().describe('Título SEO si debe ser distinto del título de la entrada.'),
      slug: z.string().optional(),
      excerpt: z.string().optional(),
      canonical: z.string().url().optional(),
      social_title: z.string().optional(),
      social_description: z.string().optional(),
      featured_media: z.number().int().positive().optional().describe('ID devuelto por subir_imagen en el MISMO sitio.'),
      image_alt: z.string().optional(),
      category_ids: z.array(z.number().int().positive()).optional(),
    },
  }, wrap(async ({ site: id, description, ...article }) => wp.createDraft(id, { ...article, meta_description: description })));

  server.registerTool('subir_imagen', {
    title: 'Subir imagen a la biblioteca de medios',
    description: 'Sube un PNG/JPG/WEBP/GIF (máx. 8 MB) desde una ruta local o en base64 y devuelve su ID para featured_media.',
    inputSchema: {
      site,
      image_path: z.string().min(1).optional().describe('Ruta local; con DIGITALISIMO_IMAGE_DIR, dentro de esa carpeta.'),
      image_base64: z.string().min(1).optional().describe('Bytes reales de la imagen; nunca inventados.'),
      mime_type: z.enum(['image/png', 'image/jpeg', 'image/webp', 'image/gif']).optional(),
      filename: z.string().min(1).optional(),
      image_alt: z.string().optional(),
    },
  }, wrap(async ({ site: id, image_path, image_base64, mime_type, filename, image_alt }) => {
    if (!image_path === !image_base64) throw new Error('Indica image_path o image_base64, uno de los dos.');
    let image;
    if (image_path) {
      const local = await readLocalImage(image_path, imageDir);
      image = imageFile(local.bytes, filename || local.name, mime_type);
    } else {
      if (!mime_type) throw new Error('Indica mime_type con image_base64.');
      image = imageFromBase64(image_base64, mime_type, filename);
    }
    return wp.uploadImage(id, image, image_alt);
  }));

  server.registerTool('subir_imagen_destacada', {
    title: 'Subir imagen destacada',
    description: 'Sube una imagen local y la asigna al borrador. Requiere activar la opción MCP de imágenes en IA Tools.',
    inputSchema: { site, post_id: z.number().int().positive(), image_path: z.string().min(1), image_title: z.string().min(3), image_alt: z.string().min(3) },
  }, wrap(async ({ site: id, post_id, image_path, image_title, image_alt }) => {
    const local = await readLocalImage(image_path, imageDir);
    return wp.featuredImage(id, post_id, imageFile(local.bytes, image_title.toLowerCase()), image_title, image_alt);
  }));

  return server;
}

const invoked = process.argv[1] ? await realpath(process.argv[1]).catch(() => '') : '';
if (invoked === await realpath(fileURLToPath(import.meta.url))) {
  try {
    await makeMcpServer(sitesFromEnv()).connect(new StdioServerTransport());
  } catch (error) {
    console.error('Error iniciando el MCP de DIGITALÍSIMO: ' + error.message);
    process.exitCode = 1;
  }
}
