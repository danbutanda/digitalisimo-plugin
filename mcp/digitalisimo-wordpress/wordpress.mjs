import { Buffer } from 'node:buffer';
import { readFileSync } from 'node:fs';

/**
 * Rutas REST que usa el MCP, relativas a /wp-json/. Las registra DIGITALÍSIMO IA Tools en cada sitio:
 * - digitalisimo-publisher/v1: includes/class-digitalisimo-ai-content-publisher.php (ficha del sitio y borradores).
 * - digitalisimo-mcp/v1: includes/class-digitalisimo-mcp-articles.php (clusters SEO).
 * - digitalisimo/v1/import-image: includes/class-content-automation.php (imagen destacada; opción MCP de imágenes).
 * La biblioteca de medios es la del núcleo de WordPress.
 */
export const ROUTES = Object.freeze({
  site: 'digitalisimo-publisher/v1/site',
  articles: 'digitalisimo-publisher/v1/articles',
  clusters: 'digitalisimo-mcp/v1/clusters',
  featuredImage: 'digitalisimo/v1/import-image',
  media: 'wp/v2/media',
});

const ALLOWED_MIME = new Map([
  ['image/png', 'png'],
  ['image/jpeg', 'jpg'],
  ['image/webp', 'webp'],
  ['image/gif', 'gif'],
]);
export const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

/** Valida la lista de sitios (JSON) y devuelve un Map por alias. Nunca expone las contraseñas fuera del cliente. */
export function readSites(source) {
  if (!source) throw new Error('Configura DIGITALISIMO_WP_URL, DIGITALISIMO_WP_USERNAME y DIGITALISIMO_WP_APP_PASSWORD, o DIGITALISIMO_SITES_FILE para varios sitios.');
  let raw;
  try { raw = JSON.parse(source); } catch { throw new Error('La lista de sitios no es JSON válido.'); }
  if (!Array.isArray(raw) || !raw.length) throw new Error('Configura al menos un sitio WordPress.');
  const sites = new Map();
  for (const site of raw) {
    if (!site || typeof site !== 'object' || !/^[a-z0-9_-]+$/.test(site.id ?? '')) throw new Error('ID de sitio inválido: usa minúsculas, números, guion o guion bajo.');
    if (sites.has(site.id)) throw new Error('ID de sitio repetido: ' + site.id);
    sites.set(site.id, normalizeSite(site));
  }
  return sites;
}

/**
 * Sitios del entorno: un solo sitio con DIGITALISIMO_WP_URL/USERNAME/APP_PASSWORD (la configuración de Codex), o
 * varios con DIGITALISIMO_SITES_FILE o DIGITALISIMO_SITES_JSON, guardados fuera del repositorio.
 */
export function sitesFromEnv(env = process.env) {
  if (env.DIGITALISIMO_SITES_JSON) return readSites(env.DIGITALISIMO_SITES_JSON);
  if (env.DIGITALISIMO_SITES_FILE) return readSites(readFileSync(env.DIGITALISIMO_SITES_FILE, 'utf8'));
  if (env.DIGITALISIMO_WP_URL || env.DIGITALISIMO_WP_USERNAME || env.DIGITALISIMO_WP_APP_PASSWORD) {
    return readSites(JSON.stringify([{
      id: 'principal',
      url: env.DIGITALISIMO_WP_URL,
      username: env.DIGITALISIMO_WP_USERNAME,
      applicationPassword: env.DIGITALISIMO_WP_APP_PASSWORD,
    }]));
  }
  return readSites('');
}

function normalizeSite(site) {
  let url;
  try { url = new URL(site.url); } catch { throw new Error('URL inválida para ' + site.id); }
  // Las contraseñas de aplicación viajan en cada petición: sólo por HTTPS, salvo un WordPress local de pruebas.
  const local = url.protocol === 'http:' && ['localhost', '127.0.0.1', '[::1]'].includes(url.hostname);
  if ((url.protocol !== 'https:' && !local) || url.username || url.password || url.search || url.hash) {
    throw new Error('Cada sitio debe utilizar una URL HTTPS sin credenciales ni parámetros.');
  }
  if (!site.username || !site.applicationPassword) throw new Error('Faltan usuario o contraseña de aplicación para ' + site.id);
  const base = url.toString().replace(/\/+$/, '');
  return { id: site.id, label: String(site.label || url.host + url.pathname.replace(/\/+$/, '')), base, username: site.username, password: site.applicationPassword };
}

export function publicSites(sites) {
  return [...sites.values()].map(({ id, label, base }) => ({ id, label, url: base }));
}

/** Tipo real de una imagen por sus primeros bytes, o null. */
export function sniffImage(bytes) {
  if (bytes.subarray(0, 8).equals(Buffer.from('89504e470d0a1a0a', 'hex'))) return 'image/png';
  if (bytes.subarray(0, 3).equals(Buffer.from('ffd8ff', 'hex'))) return 'image/jpeg';
  if (bytes.toString('ascii', 0, 4) === 'RIFF' && bytes.toString('ascii', 8, 12) === 'WEBP') return 'image/webp';
  if (bytes.toString('ascii', 0, 4) === 'GIF8') return 'image/gif';
  return null;
}

/** Comprueba bytes, tamaño y tipo declarado, y devuelve un nombre de archivo seguro con la extensión real. */
export function imageFile(bytes, filename, declared) {
  if (!bytes.length || bytes.length > MAX_IMAGE_BYTES) throw new Error('Imagen vacía o mayor de 8 MB.');
  const mime = sniffImage(bytes);
  if (!mime) throw new Error('Tipo no admitido. Usa PNG, JPG, WEBP o GIF.');
  if (declared && declared !== mime) throw new Error('Los bytes no corresponden al tipo de imagen declarado.');
  const safe = String(filename || 'digitalisimo-image').replace(/\.[^.]*$/, '').replace(/[^a-z0-9_-]/gi, '-').slice(0, 75) || 'digitalisimo-image';
  return { bytes, mime_type: mime, filename: safe + '.' + ALLOWED_MIME.get(mime) };
}

export function imageFromBase64(image_base64, mime_type, filename) {
  if (!ALLOWED_MIME.has(mime_type)) throw new Error('Tipo no admitido. Usa PNG, JPG, WEBP o GIF.');
  if (typeof image_base64 !== 'string' || image_base64.length > Math.ceil(MAX_IMAGE_BYTES * 4 / 3) + 8) throw new Error('Imagen inválida o mayor de 8 MB.');
  const data = image_base64.replace(/^data:image\/(?:png|jpeg|webp|gif);base64,/, '');
  if (!/^[A-Za-z0-9+/]+={0,2}$/.test(data) || data.length % 4 !== 0) throw new Error('Base64 inválido.');
  return imageFile(Buffer.from(data, 'base64'), filename, mime_type);
}

export function createWordPressClient(sites, fetcher = fetch) {
  function site(id) {
    if (id === undefined || id === null || id === '') {
      if (sites.size === 1) return [...sites.values()][0];
      throw new Error('Hay varios sitios configurados: indica site (usa listar_sitios).');
    }
    const found = sites.get(id);
    if (!found) throw new Error('Sitio desconocido. Usa listar_sitios antes.');
    return found;
  }
  async function request(id, endpoint, { method = 'GET', body, headers = {} } = {}) {
    const target = site(id);
    const auth = Buffer.from(target.username + ':' + target.password).toString('base64');
    const response = await fetcher(target.base + '/wp-json/' + endpoint, {
      method,
      redirect: 'manual',
      headers: { Authorization: 'Basic ' + auth, Accept: 'application/json', ...headers },
      ...(body === undefined ? {} : { body }),
      signal: AbortSignal.timeout(60_000),
    });
    if (response.status >= 300 && response.status < 400) throw new Error('WordPress respondió con redirección; comprueba la URL HTTPS canónica.');
    const text = await response.text();
    let result;
    try { result = JSON.parse(text); } catch { throw new Error('WordPress respondió con contenido no JSON (HTTP ' + response.status + ').'); }
    if (!response.ok) {
      const message = result && typeof result.message === 'string' ? result.message : 'Error de WordPress';
      throw new Error('WordPress HTTP ' + response.status + ': ' + message);
    }
    return result;
  }
  return {
    siteInfo(id) { return request(id, ROUTES.site); },
    clusters(id) { return request(id, ROUTES.clusters); },
    /** Sube la imagen a la biblioteca de medios y devuelve su ID para featured_media. */
    async uploadImage(id, image, alt) {
      const attachment = await request(id, ROUTES.media, {
        method: 'POST',
        body: image.bytes,
        headers: { 'Content-Type': image.mime_type, 'Content-Disposition': 'attachment; filename="' + image.filename + '"' },
      });
      if (alt) {
        await request(id, ROUTES.media + '/' + attachment.id, {
          method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ alt_text: alt }),
        });
      }
      return { id: attachment.id, source_url: attachment.source_url, site: site(id).id };
    },
    /** Sube la imagen y la asigna como destacada de un borrador (requiere la opción MCP de imágenes en IA Tools). */
    featuredImage(id, postId, image, title, alt) {
      const form = new FormData();
      form.append('post_id', String(postId));
      form.append('image_title', title);
      form.append('image_alt', alt);
      form.append('file', new Blob([image.bytes], { type: image.mime_type }), image.filename);
      return request(id, ROUTES.featuredImage, { method: 'POST', body: form });
    },
    /** Crea un borrador con los metadatos SEO; nunca publica. */
    createDraft(id, article) {
      if (article.status && article.status !== 'draft') throw new Error('El MCP sólo crea borradores.');
      return request(id, ROUTES.articles, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ...article, status: 'draft' }),
      });
    },
  };
}
