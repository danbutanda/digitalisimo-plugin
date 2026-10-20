import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { Client } from '@modelcontextprotocol/sdk/client/index.js';
import { InMemoryTransport } from '@modelcontextprotocol/sdk/inMemory.js';
import { makeMcpServer } from '../server.mjs';
import { readSites } from '../wordpress.mjs';

const sites = readSites(JSON.stringify([{ id: 'demo', url: 'https://example.com', username: 'editor', applicationPassword: 'fake-test-password' }]));
const chat = {
  speakers: [
    { id: 'empresario', name: 'Empresario', icon: '🧑', image_id: 0, align: 'start' },
    { id: 'maryia', name: 'MaryIA', icon: '🤖', image_id: 0, align: 'center' },
    { id: 'daniel', name: 'Daniel', icon: '👨‍💼', image_id: 0, align: 'end' },
  ],
  messages: ['empresario', 'daniel', 'maryia', 'daniel', 'empresario', 'daniel'].map((speaker, i) => ({ speaker, text: 'Mensaje ' + i })),
};

async function connect(fetcher, options = {}) {
  const server = makeMcpServer(sites, { fetcher, ...options });
  const [clientTransport, serverTransport] = InMemoryTransport.createLinkedPair();
  const client = new Client({ name: 'prueba', version: '1.0.0' });
  await Promise.all([server.connect(serverTransport), client.connect(clientTransport)]);
  return client;
}

test('registra las herramientas con el SDK real', async () => {
  const client = await connect(async () => new Response('{}'));
  const names = (await client.listTools()).tools.map((tool) => tool.name).sort();
  assert.deepEqual(names, ['consultar_sitio', 'crear_borrador_seo', 'listar_clusters_seo', 'listar_sitios', 'subir_imagen', 'subir_imagen_destacada']);
  const listed = JSON.parse((await client.callTool({ name: 'listar_sitios', arguments: {} })).content[0].text);
  assert.deepEqual(listed, { sites: [{ id: 'demo', label: 'example.com', url: 'https://example.com' }] });
});

test('listar_clusters_seo y crear_borrador_seo llaman a las rutas reales de IA Tools', async () => {
  const calls = [];
  const client = await connect(async (url, options) => { calls.push({ url, options }); return new Response(JSON.stringify({ id: 5, status: 'draft' })); });
  await client.callTool({ name: 'listar_clusters_seo', arguments: {} });
  const result = await client.callTool({ name: 'crear_borrador_seo', arguments: {
    primary_keyword: 'seo local', title: 'SEO local para empresas', description: 'Cómo ordenar el SEO local de una empresa sin promesas vacías.',
    content: '<p>' + 'Contenido del artículo. '.repeat(8) + '</p>', digitalisimo_article_chat: chat, pillar_id: 3,
  } });
  assert.ok(!result.isError, result.content[0].text);
  assert.equal(calls[0].url, 'https://example.com/wp-json/digitalisimo-mcp/v1/clusters');
  assert.equal(calls[1].url, 'https://example.com/wp-json/digitalisimo-publisher/v1/articles');
  const body = JSON.parse(calls[1].options.body);
  assert.equal(body.meta_description, 'Cómo ordenar el SEO local de una empresa sin promesas vacías.');
  assert.equal(body.description, undefined);
  assert.equal(body.status, 'draft');
  assert.deepEqual(body.digitalisimo_article_chat, chat);
});

test('crear_borrador_seo exige el chat editorial', async () => {
  const client = await connect(async () => new Response('{}'));
  const result = await client.callTool({ name: 'crear_borrador_seo', arguments: { primary_keyword: 'seo local', title: 'SEO local', description: 'x'.repeat(40), content: 'y'.repeat(120) } });
  assert.equal(result.isError, true);
});

test('subir_imagen respeta DIGITALISIMO_IMAGE_DIR y devuelve el ID del medio', async () => {
  const dir = mkdtempSync(join(tmpdir(), 'digi-mcp-'));
  const outside = mkdtempSync(join(tmpdir(), 'digi-mcp-fuera-'));
  try {
    const png = Buffer.from('89504e470d0a1a0a00000000', 'hex');
    writeFileSync(join(dir, 'portada.png'), png);
    writeFileSync(join(outside, 'otra.png'), png);
    const client = await connect(async () => new Response(JSON.stringify({ id: 31, source_url: 'https://example.com/portada.png' }), { status: 201 }), { imageDir: dir });
    const ok = await client.callTool({ name: 'subir_imagen', arguments: { image_path: join(dir, 'portada.png') } });
    assert.equal(JSON.parse(ok.content[0].text).id, 31);
    const denied = await client.callTool({ name: 'subir_imagen', arguments: { image_path: join(outside, 'otra.png') } });
    assert.equal(denied.isError, true);
    assert.match(denied.content[0].text, /DIGITALISIMO_IMAGE_DIR/);
  } finally {
    rmSync(dir, { recursive: true, force: true });
    rmSync(outside, { recursive: true, force: true });
  }
});
