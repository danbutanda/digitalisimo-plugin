import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { ROUTES, createWordPressClient, imageFromBase64, publicSites, readSites, sitesFromEnv } from '../wordpress.mjs';

const secrets = JSON.stringify([
  { id: 'root', label: 'Principal', url: 'https://example.com/', username: 'editor', applicationPassword: 'not-a-real-password' },
  { id: 'blog', url: 'https://example.com/subsite/', username: 'editor2', applicationPassword: 'another-fake-password' },
]);
const png = Buffer.from('89504e470d0a1a0a00000000', 'hex').toString('base64');
const json = (value, status = 200) => new Response(JSON.stringify(value), { status });

test('las rutas existen en IA Tools: ninguna se antepone a otra', () => {
  const publisher = readFileSync(new URL('../../../digitalisimo-ia-tools/includes/class-digitalisimo-ai-content-publisher.php', import.meta.url), 'utf8');
  const mcp = readFileSync(new URL('../../../digitalisimo-ia-tools/includes/class-digitalisimo-mcp-articles.php', import.meta.url), 'utf8');
  const automation = readFileSync(new URL('../../../digitalisimo-ia-tools/includes/class-content-automation.php', import.meta.url), 'utf8');
  assert.ok(publisher.includes("const API_NAMESPACE = 'digitalisimo-publisher/v1';") && publisher.includes("self::API_NAMESPACE, '/site'") && publisher.includes("self::API_NAMESPACE, '/articles'"));
  assert.equal(ROUTES.site, 'digitalisimo-publisher/v1/site');
  assert.equal(ROUTES.articles, 'digitalisimo-publisher/v1/articles');
  assert.ok(mcp.includes("register_rest_route( 'digitalisimo-mcp/v1', '/clusters'"));
  assert.equal(ROUTES.clusters, 'digitalisimo-mcp/v1/clusters');
  assert.ok(automation.includes("register_rest_route( 'digitalisimo/v1', '/import-image'"));
  assert.equal(ROUTES.featuredImage, 'digitalisimo/v1/import-image');
});

test('lee sitios independientes y subdirectorios sin exponer contraseñas', () => {
  const visible = publicSites(readSites(secrets));
  assert.equal(visible.length, 2);
  assert.equal(visible[1].url, 'https://example.com/subsite');
  assert.ok(!JSON.stringify(visible).includes('not-a-real-password'));
});

test('el entorno de un solo sitio sigue funcionando como antes', () => {
  const sites = sitesFromEnv({ DIGITALISIMO_WP_URL: 'https://example.com/', DIGITALISIMO_WP_USERNAME: 'editor', DIGITALISIMO_WP_APP_PASSWORD: 'x' });
  assert.deepEqual(publicSites(sites), [{ id: 'principal', label: 'example.com', url: 'https://example.com' }]);
  assert.equal(sitesFromEnv({ DIGITALISIMO_SITES_JSON: secrets }).size, 2);
  assert.throws(() => sitesFromEnv({}), /DIGITALISIMO_WP_URL/);
  assert.throws(() => sitesFromEnv({ DIGITALISIMO_WP_URL: 'https://example.com' }), /contraseña/);
});

test('rechaza URL insegura y alias repetido; admite WordPress local', () => {
  assert.throws(() => readSites('[{"id":"site","url":"http://x.com","username":"e","applicationPassword":"p"}]'), /HTTPS/);
  assert.throws(() => readSites('[{"id":"site","url":"https://u:p@x.com","username":"e","applicationPassword":"p"}]'), /HTTPS/);
  const repeated = JSON.stringify([JSON.parse(secrets)[0], JSON.parse(secrets)[0]]);
  assert.throws(() => readSites(repeated), /repetido/);
  assert.equal(readSites('[{"id":"local","url":"http://localhost:8080","username":"e","applicationPassword":"p"}]').get('local').base, 'http://localhost:8080');
});

test('verifica imagen, tamaño y tipo', () => {
  const result = imageFromBase64(png, 'image/png', 'imagen ejemplo.png');
  assert.equal(result.filename, 'imagen-ejemplo.png');
  assert.equal(result.bytes.length, 12);
  assert.throws(() => imageFromBase64(png, 'image/jpeg', 'x.png'), /bytes/);
  assert.throws(() => imageFromBase64('no*base64', 'image/png', 'x.png'), /Base64/);
});

test('con un solo sitio no hace falta indicarlo; con varios sí', async () => {
  const requests = [];
  const fetcher = async (url, options) => { requests.push({ url, options }); return json({ pillars: [] }); };
  const single = createWordPressClient(readSites(JSON.stringify([JSON.parse(secrets)[1]])), fetcher);
  await single.clusters();
  assert.equal(requests[0].url, 'https://example.com/subsite/wp-json/digitalisimo-mcp/v1/clusters');
  assert.equal(requests[0].options.headers.Authorization, 'Basic ' + Buffer.from('editor2:another-fake-password').toString('base64'));
  assert.equal(requests[0].options.redirect, 'manual');
  const multi = createWordPressClient(readSites(secrets), fetcher);
  await assert.rejects(multi.clusters(), /varios sitios/);
  await multi.siteInfo('blog');
  assert.equal(requests[1].url, 'https://example.com/subsite/wp-json/digitalisimo-publisher/v1/site');
  await assert.rejects(multi.siteInfo('otro'), /desconocido/);
});

test('imagen usa la biblioteca de WordPress y configura alt', async () => {
  const requests = [];
  const fetcher = async (url, options) => { requests.push({ url, options }); return json(requests.length === 1 ? { id: 24, source_url: 'https://example.com/image.png' } : { id: 24 }, 201); };
  const wp = createWordPressClient(readSites(secrets), fetcher);
  const result = await wp.uploadImage('root', imageFromBase64(png, 'image/png', 'imagen.png'), 'Texto alternativo');
  assert.deepEqual(result, { id: 24, source_url: 'https://example.com/image.png', site: 'root' });
  assert.equal(requests[0].url, 'https://example.com/wp-json/wp/v2/media');
  assert.equal(requests[0].options.headers['Content-Type'], 'image/png');
  assert.equal(requests[1].url, 'https://example.com/wp-json/wp/v2/media/24');
  assert.deepEqual(JSON.parse(requests[1].options.body), { alt_text: 'Texto alternativo' });
});

test('imagen destacada va al endpoint de IA Tools como formulario', async () => {
  let request;
  const fetcher = async (url, options) => { request = { url, options }; return json({ success: true, id: 7, post_id: 9 }); };
  const wp = createWordPressClient(readSites(secrets), fetcher);
  await wp.featuredImage('root', 9, imageFromBase64(png, 'image/png', 'portada'), 'Portada', 'Alt');
  assert.equal(request.url, 'https://example.com/wp-json/digitalisimo/v1/import-image');
  assert.ok(request.options.body instanceof FormData);
  assert.equal(request.options.body.get('post_id'), '9');
  assert.equal(request.options.body.get('file').name, 'portada.png');
});

test('crea únicamente borradores en el publisher de IA Tools', async () => {
  let body;
  const fetcher = async (url, options) => {
    assert.equal(url, 'https://example.com/wp-json/digitalisimo-publisher/v1/articles');
    body = JSON.parse(options.body);
    return json({ id: 1591, status: 'draft' });
  };
  const wp = createWordPressClient(readSites(secrets), fetcher);
  const article = await wp.createDraft('root', { title: 'Entrada', content: '<p>Texto</p>', meta_description: 'Resumen', primary_keyword: 'principal', featured_media: 24 });
  assert.equal(body.status, 'draft');
  assert.equal(body.featured_media, 24);
  assert.equal(article.id, 1591);
  assert.throws(() => wp.createDraft('root', { status: 'publish' }), /borradores/);
});

test('rechaza redirecciones y errores de WordPress sin revelar la contraseña', async () => {
  const sites = readSites(secrets);
  await assert.rejects(createWordPressClient(sites, async () => new Response('', { status: 302 })).siteInfo('root'), /redirección/);
  await assert.rejects(createWordPressClient(sites, async () => json({ message: 'Sin permiso' }, 401)).siteInfo('root'), (error) => /HTTP 401: Sin permiso/.test(error.message) && !error.message.includes('not-a-real-password'));
  await assert.rejects(createWordPressClient(sites, async () => new Response('<html>', { status: 500 })).siteInfo('root'), /no JSON/);
});
