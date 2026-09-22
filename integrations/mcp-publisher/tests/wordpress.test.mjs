import test from 'node:test';
import assert from 'node:assert/strict';
import { createWordPressClient, imageBytes, publicSites, readSites } from '../wordpress.mjs';

const secrets = JSON.stringify([
  { id: 'root', label: 'Principal', url: 'https://example.com/', username: 'editor', applicationPassword: 'not-a-real-password' },
  { id: 'blog', url: 'https://example.com/subsite/', username: 'editor2', applicationPassword: 'another-fake-password' }
]);
test('lee sitios independientes y subdirectorios sin exponer contraseñas', () => {
  const sites = readSites(secrets);
  const visible = publicSites(sites);
  assert.equal(visible.length, 2);
  assert.equal(visible[1].url, 'https://example.com/subsite');
  assert.ok(!JSON.stringify(visible).includes('not-a-real-password'));
});
test('rechaza URL insegura y alias repetido', () => {
  assert.throws(() => readSites('[{"id":"site","url":"http://x.com","username":"e","applicationPassword":"p"}]'), /HTTPS/);
  const repeated = JSON.stringify([JSON.parse(secrets)[0], JSON.parse(secrets)[0]]);
  assert.throws(() => readSites(repeated), /repetido/);
});
test('verifica imagen PNG, tamaño y MIME', () => {
  const png = Buffer.from('89504e470d0a1a0a00000000', 'hex').toString('base64');
  const result = imageBytes(png, 'image/png', 'imagen ejemplo.png');
  assert.equal(result.filename, 'imagen-ejemplo.png');
  assert.equal(result.bytes.length, 12);
  assert.throws(() => imageBytes(png, 'image/jpeg', 'x.png'), /bytes/);
});
test('get_site autentica por instalación sin incluir credenciales en el resultado', async () => {
  const requests = [];
  const fetcher = async (url, options) => {
    requests.push({ url, options });
    return new Response(JSON.stringify({ url: 'https://example.com/subsite/', seo_enabled: true }), { status: 200 });
  };
  const wp = createWordPressClient(readSites(secrets), fetcher);
  const site = await wp.siteInfo('blog');
  assert.equal(site.seo_enabled, true);
  assert.equal(requests[0].url, 'https://example.com/subsite/wp-json/digitalisimo-publisher/v1/site');
  assert.equal(requests[0].options.headers.Authorization, 'Basic ' + Buffer.from('editor2:another-fake-password').toString('base64'));
  assert.equal(requests[0].options.redirect, 'manual');
});
test('imagen usa WordPress media y configura alt', async () => {
  const requests = [];
  const fetcher = async (url, options) => {
    requests.push({ url, options });
    return new Response(JSON.stringify(requests.length === 1 ? { id: 24, source_url: 'https://example.com/image.png' } : { id: 24 }), { status: 201 });
  };
  const png = Buffer.from('89504e470d0a1a0a00000000', 'hex').toString('base64');
  const wp = createWordPressClient(readSites(secrets), fetcher);
  const result = await wp.uploadImage('root', { image_base64: png, mime_type: 'image/png', filename: 'imagen.png', image_alt: 'Texto alternativo' });
  assert.equal(result.id, 24);
  assert.equal(requests[0].options.headers['Content-Type'], 'image/png');
  assert.equal(requests[1].url, 'https://example.com/wp-json/wp/v2/media/24');
  assert.deepEqual(JSON.parse(requests[1].options.body), { alt_text: 'Texto alternativo' });
});
test('crea únicamente borradores SEO en el sitio elegido', async () => {
  let body;
  const fetcher = async (url, options) => {
    assert.equal(url, 'https://example.com/wp-json/digitalisimo-publisher/v1/articles');
    body = JSON.parse(options.body);
    return new Response(JSON.stringify({ id: 1591, status: 'draft' }), { status: 200 });
  };
  const wp = createWordPressClient(readSites(secrets), fetcher);
  const article = await wp.createDraft('root', {
    title: 'Entrada', content: '<p>Texto</p>', meta_description: 'Resumen',
    primary_keyword: 'principal', featured_media: 24
  });
  assert.equal(body.status, 'draft');
  assert.equal(body.featured_media, 24);
  assert.equal(article.id, 1591);
  assert.throws(() => wp.createDraft('root', { status: 'publish' }), /borradores/);
});
test('rechaza redirecciones y errores WordPress sin revelar la contraseña', async () => {
  const sites = readSites(secrets);
  const wpRedirect = createWordPressClient(sites, async () => new Response('', { status: 302 }));
  await assert.rejects(wpRedirect.siteInfo('root'), /redirección/);
  const wp401 = createWordPressClient(sites, async () => new Response(JSON.stringify({ message: 'Sin permiso' }), { status: 401 }));
  await assert.rejects(wp401.siteInfo('root'), /HTTP 401/);
});
