import { Buffer } from 'node:buffer';

const ALLOWED_MIME = new Map([
  ['image/png', 'png'],
  ['image/jpeg', 'jpg'],
  ['image/webp', 'webp'],
  ['image/gif', 'gif']
]);
const MAX_BYTES = 8 * 1024 * 1024;

export function readSites(source = process.env.DIGITALISIMO_SITES_JSON) {
  if (!source) throw new Error('Configura DIGITALISIMO_SITES_JSON antes de iniciar.');
  let raw;
  try { raw = JSON.parse(source); } catch { throw new Error('DIGITALISIMO_SITES_JSON no es JSON válido.'); }
  if (!Array.isArray(raw) || !raw.length) throw new Error('Configura al menos un sitio WordPress.');
  const sites = new Map();
  for (const site of raw) {
    if (!site || typeof site !== 'object' || !/^[a-z0-9_-]+$/.test(site.id ?? '')) throw new Error('ID de sitio inválido.');
    if (sites.has(site.id)) throw new Error('ID de sitio repetido.');
    const url = new URL(site.url);
    if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash) {
      throw new Error('Cada sitio debe utilizar una URL HTTPS sin credenciales ni parámetros.');
    }
    if (!site.username || !site.applicationPassword) throw new Error('Faltan credenciales de aplicación para ' + site.id);
    const base = url.toString().replace(/\/+$/, '');
    sites.set(site.id, { id: site.id, label: String(site.label || site.id), base, username: site.username, password: site.applicationPassword });
  }
  return sites;
}

export function publicSites(sites) {
  return [...sites.values()].map(({ id, label, base }) => ({ id, label, url: base }));
}

export function imageBytes(image_base64, mime_type, filename) {
  const ext = ALLOWED_MIME.get(mime_type);
  if (!ext) throw new Error('Tipo no admitido. Usa PNG, JPG, WEBP o GIF.');
  if (typeof image_base64 !== 'string' || image_base64.length > Math.ceil(MAX_BYTES * 4 / 3) + 8) throw new Error('Imagen inválida o mayor de 8 MB.');
  const data = image_base64.replace(/^data:image\/(?:png|jpeg|webp|gif);base64,/, '');
  if (!/^[A-Za-z0-9+/]+={0,2}$/.test(data) || data.length % 4 !== 0) throw new Error('Base64 inválido.');
  const bytes = Buffer.from(data, 'base64');
  if (!bytes.length || bytes.length > MAX_BYTES) throw new Error('Imagen vacía o mayor de 8 MB.');
  const magic = mime_type === 'image/png'
    ? bytes.subarray(0, 8).equals(Buffer.from('89504e470d0a1a0a', 'hex'))
    : mime_type === 'image/jpeg'
    ? bytes.subarray(0, 3).equals(Buffer.from('ffd8ff', 'hex'))
    : mime_type === 'image/webp'
    ? bytes.toString('ascii', 0, 4) === 'RIFF' && bytes.toString('ascii', 8, 12) === 'WEBP'
    : bytes.toString('ascii', 0, 4) === 'GIF8';
  if (!magic) throw new Error('Los bytes no corresponden al tipo de imagen declarado.');
  const safe = String(filename || 'digitalisimo-image').replace(/\.[^.]*$/, '').replace(/[^a-z0-9_-]/gi, '-').slice(0, 75) || 'digitalisimo-image';
  return { bytes, filename: safe + '.' + ext, mime_type };
}

export function createWordPressClient(sites, fetcher = fetch) {
  async function request(siteId, endpoint, { method = 'GET', body, headers = {} } = {}) {
    const site = sites.get(siteId);
    if (!site) throw new Error('Sitio desconocido. Usa list_sites antes.');
    const url = site.base + '/wp-json/' + endpoint;
    const auth = Buffer.from(site.username + ':' + site.password).toString('base64');
    const response = await fetcher(url, {
      method,
      redirect: 'manual',
      headers: { 'Authorization': 'Basic ' + auth, 'Accept': 'application/json', ...headers },
      ...(body === undefined ? {} : { body }),
      signal: AbortSignal.timeout(30_000)
    });
    if (response.status >= 300 && response.status < 400) throw new Error('WordPress respondió con redirección; comprueba la URL HTTPS canónica.');
    const text = await response.text();
    let result;
    try { result = JSON.parse(text); } catch { throw new Error('WordPress respondió con contenido no JSON (HTTP ' + response.status + ').'); }
    if (!response.ok) {
      const message = typeof result.message === 'string' ? result.message : 'Error de WordPress';
      throw new Error('WordPress HTTP ' + response.status + ': ' + message);
    }
    return result;
  }
  return {
    siteInfo(id) { return request(id, 'digitalisimo-publisher/v1/site'); },
    async uploadImage(id, values) {
      const image = imageBytes(values.image_base64, values.mime_type, values.filename);
      const attachment = await request(id, 'wp/v2/media', {
        method: 'POST',
        body: image.bytes,
        headers: {
          'Content-Type': image.mime_type,
          'Content-Disposition': 'attachment; filename="' + image.filename + '"'
        }
      });
      if (values.image_alt) {
        await request(id, 'wp/v2/media/' + attachment.id, {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ alt_text: values.image_alt })
        });
      }
      return { id: attachment.id, source_url: attachment.source_url, site: id };
    },
    createDraft(id, article) {
      if (article.status && article.status !== 'draft') throw new Error('El puente solo permite borradores.');
      return request(id, 'digitalisimo-publisher/v1/articles', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ...article, status: 'draft' })
      });
    }
  };
}
