// Generador de los kits sintéticos de la etapa K (§13.2). Cada spec/*.json describe un kit (manifiesto, archivos,
// WXR y medios) y lo que debe quedar en B tras importarlo («expect», CV-9). El generador es determinista: IDs de
// 7 hex escritos en la spec, fechas fijas, claves de JSON ordenadas, ZIP sin compresión (STORED) con fecha DOS de
// 1980 y PNG con bloques deflate sin comprimir, así que el sha256 de cada ZIP no depende de la versión de Node ni de
// zlib. Ningún kit real entra en Git: los ZIP no se versionan (.gitignore) y fixtures.lock.json guarda su sha256.
//
// Uso:
//   node tests/sources/kits/build.mjs --check                 reconstruye en memoria y compara con fixtures.lock.json
//   node tests/sources/kits/build.mjs --write-lock            reescribe fixtures.lock.json (tras cambiar una spec)
//   node tests/sources/kits/build.mjs --out DIR [--remote DIR] escribe los ZIP y los medios remotos de demostración
//
// Fichas dentro de los textos de una spec: {{sha256:<medio>}}, {{sha1:<medio>}}, {{file:<medio>}} (<sha256>.<ext>),
// {{bytes:<medio>}} y {{mime:<medio>}}; si la ficha es todo el texto y es un número, queda como número.
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, writeFileSync, mkdirSync, existsSync } from 'node:fs';
import { join, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const SPEC_DIR = join(HERE, 'spec');
const LOCK = join(HERE, 'fixtures.lock.json');

// ── CRC32 (sin depender de zlib.crc32, que no existe en todas las versiones de Node 20) ───────────────────────
const CRC_TABLE = (() => {
  const t = new Uint32Array(256);
  for (let n = 0; n < 256; n++) {
    let c = n;
    for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1;
    t[n] = c >>> 0;
  }
  return t;
})();
export function crc32(buf) {
  let c = 0xffffffff;
  for (let i = 0; i < buf.length; i++) c = CRC_TABLE[(c ^ buf[i]) & 0xff] ^ (c >>> 8);
  return (c ^ 0xffffffff) >>> 0;
}
function adler32(buf) {
  let a = 1;
  let b = 0;
  for (let i = 0; i < buf.length; i++) { a = (a + buf[i]) % 65521; b = (b + a) % 65521; }
  return ((b << 16) | a) >>> 0;
}
const sha256 = (buf) => createHash('sha256').update(buf).digest('hex');

// ── ZIP STORED determinista ───────────────────────────────────────────────────────────────────────────────────
// entries: [{ name, data: Buffer, symlink?: true, size?: número declarado (para un tamaño falso), flags? }]
// Las entradas se escriben en el orden recibido: las pruebas de rutas hostiles necesitan nombres repetidos y orden fijo.
export function zipStore(entries) {
  const DOS_TIME = 0;
  const DOS_DATE = (0 << 9) | (1 << 5) | 1; // 1980-01-01
  const locals = [];
  const centrals = [];
  let offset = 0;
  for (const entry of entries) {
    const name = Buffer.from(entry.name, 'utf8');
    const data = Buffer.isBuffer(entry.data) ? entry.data : Buffer.from(String(entry.data ?? ''), 'utf8');
    const crc = crc32(data);
    const size = entry.size ?? data.length;
    const local = Buffer.alloc(30);
    local.writeUInt32LE(0x04034b50, 0);
    local.writeUInt16LE(10, 4);
    local.writeUInt16LE(0x0800, 6); // nombres en UTF-8
    local.writeUInt16LE(0, 8); // STORED
    local.writeUInt16LE(DOS_TIME, 10);
    local.writeUInt16LE(DOS_DATE, 12);
    local.writeUInt32LE(crc, 14);
    local.writeUInt32LE(size, 18);
    local.writeUInt32LE(size, 22);
    local.writeUInt16LE(name.length, 26);
    local.writeUInt16LE(0, 28);
    locals.push(local, name, data);
    const central = Buffer.alloc(46);
    central.writeUInt32LE(0x02014b50, 0);
    central.writeUInt16LE(entry.symlink ? 0x0314 : 0x0014, 4); // versión hecha por: Unix si es enlace simbólico
    central.writeUInt16LE(10, 6);
    central.writeUInt16LE(0x0800, 8);
    central.writeUInt16LE(0, 10);
    central.writeUInt16LE(DOS_TIME, 12);
    central.writeUInt16LE(DOS_DATE, 14);
    central.writeUInt32LE(crc, 16);
    central.writeUInt32LE(size, 20);
    central.writeUInt32LE(size, 24);
    central.writeUInt16LE(name.length, 28);
    central.writeUInt16LE(0, 30);
    central.writeUInt16LE(0, 32);
    central.writeUInt16LE(0, 34);
    central.writeUInt16LE(0, 36);
    central.writeUInt32LE(entry.symlink ? (0o120777 << 16) >>> 0 : 0, 38);
    central.writeUInt32LE(offset, 42);
    centrals.push(central, name);
    offset += local.length + name.length + data.length;
  }
  const cd = Buffer.concat(centrals);
  const end = Buffer.alloc(22);
  end.writeUInt32LE(0x06054b50, 0);
  end.writeUInt16LE(0, 4);
  end.writeUInt16LE(0, 6);
  end.writeUInt16LE(entries.length, 8);
  end.writeUInt16LE(entries.length, 10);
  end.writeUInt32LE(cd.length, 12);
  end.writeUInt32LE(offset, 16);
  end.writeUInt16LE(0, 20);
  return Buffer.concat([...locals, cd, end]);
}

// ── Medios sintéticos ─────────────────────────────────────────────────────────────────────────────────────────
// PNG de un color con IDAT en bloques deflate sin comprimir: bytes idénticos en cualquier Node.
export function png(w, h, [r, g, b]) {
  const row = Buffer.alloc(1 + w * 3);
  for (let x = 0; x < w; x++) { row[1 + x * 3] = r; row[2 + x * 3] = g; row[3 + x * 3] = b; }
  const raw = Buffer.concat(Array.from({ length: h }, () => row));
  const blocks = [];
  for (let i = 0; i < raw.length || i === 0; i += 65535) {
    const chunk = raw.subarray(i, i + 65535);
    const head = Buffer.alloc(5);
    head[0] = i + 65535 >= raw.length ? 1 : 0;
    head.writeUInt16LE(chunk.length, 1);
    head.writeUInt16LE(~chunk.length & 0xffff, 3);
    blocks.push(head, chunk);
    if (!raw.length) break;
  }
  const adler = Buffer.alloc(4);
  adler.writeUInt32BE(adler32(raw));
  const zdata = Buffer.concat([Buffer.from([0x78, 0x01]), ...blocks, adler]);
  const chunk = (type, data) => {
    const len = Buffer.alloc(4);
    len.writeUInt32BE(data.length);
    const crc = Buffer.alloc(4);
    crc.writeUInt32BE(crc32(Buffer.concat([Buffer.from(type), data])));
    return Buffer.concat([len, Buffer.from(type), data, crc]);
  };
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(w, 0);
  ihdr.writeUInt32BE(h, 4);
  ihdr[8] = 8; ihdr[9] = 2; ihdr[10] = 0; ihdr[11] = 0; ihdr[12] = 0;
  return Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', ihdr), chunk('IDAT', zdata), chunk('IEND', Buffer.alloc(0))]);
}

// WOFF2 mínimo (firma wOF2 y cabecera de 48 bytes con el resto a cero): basta para el tipo real; la semilla lo distingue.
export function woff2(seed = '') {
  const head = Buffer.alloc(48);
  head.write('wOF2', 0, 'latin1');
  head.writeUInt32BE(0x00010000, 4);
  head.writeUInt32BE(48 + 32, 8);
  head.writeUInt16BE(1, 12);
  return Buffer.concat([head, createHash('sha256').update(String(seed)).digest()]);
}

const MIME = { png: 'image/png', jpg: 'image/jpeg', svg: 'image/svg+xml', woff2: 'font/woff2', json: 'application/json', csv: 'text/csv', pdf: 'application/pdf', txt: 'text/plain', html: 'text/html', js: 'text/javascript', xml: 'application/xml', php: 'text/x-php' };

function asset(spec) {
  if (spec.png) return { data: png(spec.w || 8, spec.h || 8, spec.png), ext: 'png' };
  if (spec.svg !== undefined) return { data: Buffer.from(spec.svg, 'utf8'), ext: 'svg' };
  if (spec.woff2 !== undefined) return { data: woff2(spec.woff2), ext: 'woff2' };
  if (spec.text !== undefined) return { data: Buffer.from(spec.text, 'utf8'), ext: spec.ext || 'txt' };
  throw new Error(`Medio sin tipo conocido: ${JSON.stringify(spec).slice(0, 80)}`);
}

// ── JSON con claves ordenadas ─────────────────────────────────────────────────────────────────────────────────
// Una condición del Theme Builder conserva el orden en que la escribe el constructor ({type, name, sub_name, sub_id}):
// el documento de tema las une con implode() en ese orden y los kits reales llegan así. Todo lo demás, por clave.
const CONDITION_KEYS = ['type', 'name', 'sub_name', 'sub_id'];
const isCondition = (value) => 'type' in value && 'name' in value && Object.keys(value).every((k) => CONDITION_KEYS.includes(k));
export function sortKeys(value) {
  if (Array.isArray(value)) return value.map(sortKeys);
  if (value && typeof value === 'object') {
    const keys = isCondition(value) ? CONDITION_KEYS.filter((k) => k in value) : Object.keys(value).sort();
    return Object.fromEntries(keys.map((k) => [k, sortKeys(value[k])]));
  }
  return value;
}
export const stableStringify = (value) => JSON.stringify(sortKeys(value));

// ── WXR mínimo (WordPress eXtended RSS 1.2) ───────────────────────────────────────────────────────────────────
const xmlEscape = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const cdata = (s) => `<![CDATA[${String(s ?? '').replace(/]]>/g, ']]]]><![CDATA[>')}]]>`;
export function wxr(doc) {
  const lines = ['<?xml version="1.0" encoding="UTF-8" ?>'];
  if (doc.doctype) lines.push(doc.doctype);
  lines.push('<rss version="2.0" xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:wp="http://wordpress.org/export/1.2/">');
  lines.push('<channel>');
  lines.push(`<title>${xmlEscape(doc.title || 'Kit')}</title>`, `<link>${xmlEscape(doc.site)}</link>`, '<language>es-MX</language>', '<wp:wxr_version>1.2</wp:wxr_version>');
  lines.push(`<wp:base_site_url>${xmlEscape(doc.site)}</wp:base_site_url>`, `<wp:base_blog_url>${xmlEscape(doc.site)}</wp:base_blog_url>`);
  for (const a of doc.authors || []) {
    lines.push(`<wp:author><wp:author_id>${a.id}</wp:author_id><wp:author_login>${cdata(a.login)}</wp:author_login><wp:author_email>${cdata(a.email)}</wp:author_email><wp:author_display_name>${cdata(a.display || a.login)}</wp:author_display_name><wp:author_first_name>${cdata(a.first || '')}</wp:author_first_name><wp:author_last_name>${cdata(a.last || '')}</wp:author_last_name></wp:author>`);
  }
  for (const t of doc.terms || []) {
    lines.push(`<wp:term><wp:term_id>${t.id}</wp:term_id><wp:term_taxonomy>${cdata(t.taxonomy)}</wp:term_taxonomy><wp:term_slug>${cdata(t.slug)}</wp:term_slug><wp:term_parent>${cdata(t.parent || '')}</wp:term_parent><wp:term_name>${cdata(t.name)}</wp:term_name></wp:term>`);
  }
  for (const it of doc.items || []) {
    const meta = Object.entries(it.meta || {}).map(([k, v]) => `<wp:postmeta><wp:meta_key>${cdata(k)}</wp:meta_key><wp:meta_value>${cdata(typeof v === 'string' ? v : JSON.stringify(v))}</wp:meta_value></wp:postmeta>`).join('');
    const cats = (it.terms || []).map((t) => `<category domain="${xmlEscape(t.taxonomy)}" nicename="${xmlEscape(t.slug)}">${cdata(t.name || t.slug)}</category>`).join('');
    const comments = (it.comments || []).map((c) => `<wp:comment><wp:comment_id>${c.id}</wp:comment_id><wp:comment_author>${cdata(c.author)}</wp:comment_author><wp:comment_author_email>${cdata(c.email)}</wp:comment_author_email><wp:comment_author_IP>${cdata(c.ip || '')}</wp:comment_author_IP><wp:comment_date>${cdata('2026-01-15 10:00:00')}</wp:comment_date><wp:comment_date_gmt>${cdata('2026-01-15 10:00:00')}</wp:comment_date_gmt><wp:comment_content>${cdata(c.content)}</wp:comment_content><wp:comment_approved>${cdata('1')}</wp:comment_approved><wp:comment_type>${cdata('comment')}</wp:comment_type><wp:comment_parent>0</wp:comment_parent><wp:comment_user_id>0</wp:comment_user_id>${Object.entries(c.meta || {}).map(([k, v]) => `<wp:commentmeta><wp:meta_key>${cdata(k)}</wp:meta_key><wp:meta_value>${cdata(v)}</wp:meta_value></wp:commentmeta>`).join('')}</wp:comment>`).join('');
    lines.push(`<item><title>${cdata(it.title)}</title><link>${xmlEscape(`${doc.site}/${it.slug}/`)}</link><pubDate>Thu, 15 Jan 2026 10:00:00 +0000</pubDate><dc:creator>${cdata(it.author || 'autor')}</dc:creator><guid isPermaLink="false">${xmlEscape(it.guid || `${doc.site}/?p=${it.id}`)}</guid><description></description><content:encoded>${cdata(it.content || '')}</content:encoded><excerpt:encoded>${cdata(it.excerpt || '')}</excerpt:encoded><wp:post_id>${it.id}</wp:post_id><wp:post_date>${cdata('2026-01-15 10:00:00')}</wp:post_date><wp:post_date_gmt>${cdata('2026-01-15 10:00:00')}</wp:post_date_gmt><wp:post_modified>${cdata('2026-01-15 10:00:00')}</wp:post_modified><wp:post_modified_gmt>${cdata('2026-01-15 10:00:00')}</wp:post_modified_gmt><wp:comment_status>${cdata('closed')}</wp:comment_status><wp:ping_status>${cdata('closed')}</wp:ping_status><wp:post_name>${cdata(it.slug)}</wp:post_name><wp:status>${cdata(it.status || 'publish')}</wp:status><wp:post_parent>${it.parent || 0}</wp:post_parent><wp:menu_order>${it.menu_order || 0}</wp:menu_order><wp:post_type>${cdata(it.type || 'post')}</wp:post_type><wp:post_password>${cdata(it.password || '')}</wp:post_password><wp:is_sticky>0</wp:is_sticky>${it.attachment_url ? `<wp:attachment_url>${cdata(it.attachment_url)}</wp:attachment_url>` : ''}${cats}${meta}${comments}</item>`);
  }
  lines.push('</channel>', '</rss>', '');
  return lines.join('\n');
}

// ── Construcción de una spec ──────────────────────────────────────────────────────────────────────────────────
function substitute(value, assets) {
  if (Array.isArray(value)) return value.map((v) => substitute(v, assets));
  if (value && typeof value === 'object') {
    return Object.fromEntries(Object.entries(value).map(([k, v]) => [substitute(k, assets), substitute(v, assets)]));
  }
  if (typeof value !== 'string' || !value.includes('{{')) return value;
  const one = (kind, name) => {
    const a = assets[name];
    if (!a) throw new Error(`Ficha {{${kind}:${name}}} sin medio`);
    return { sha256: a.sha256, sha1: createHash('sha1').update(a.data).digest('hex'), file: `${a.sha256}.${a.ext}`, bytes: a.data.length, mime: MIME[a.ext] || 'application/octet-stream' }[kind];
  };
  const whole = value.match(/^\{\{(sha256|sha1|file|bytes|mime):([a-z0-9-]+)\}\}$/);
  if (whole) return one(whole[1], whole[2]);
  return value.replace(/\{\{(sha256|sha1|file|bytes|mime):([a-z0-9-]+)\}\}/g, (_, kind, name) => String(one(kind, name)));
}

function entryData(entry, assets, specName) {
  if (entry && typeof entry === 'object' && !Array.isArray(entry)) {
    if (entry.$asset) {
      const a = assets[entry.$asset];
      if (!a) throw new Error(`${specName}: medio ${entry.$asset} sin definir`);
      return a.data;
    }
    if (entry.$text !== undefined) return Buffer.from(substitute(entry.$text, assets), 'utf8');
    if (entry.$wxr) return Buffer.from(wxr(substitute(entry.$wxr, assets)), 'utf8');
    if (entry.$json !== undefined) return Buffer.from(stableStringify(substitute(entry.$json, assets)), 'utf8');
  }
  return Buffer.from(stableStringify(substitute(entry, assets)), 'utf8');
}

// Repeticiones («repeat»): una plantilla de entradas y medios con {{n}} que se expande N veces (kit de 100 imágenes).
function expand(spec) {
  if (!spec.repeat) return spec;
  const { count, assets = {}, remote = {}, list } = spec.repeat;
  const out = JSON.parse(JSON.stringify(spec));
  const items = [];
  for (let n = 1; n <= count; n++) {
    const pad = String(n).padStart(3, '0');
    const fill = (v) => JSON.parse(JSON.stringify(v).replace(/\{\{n\}\}/g, pad).replace(/"\{\{shade\}\}"/g, String(n % 256)));
    Object.assign(out.assets, fill(assets));
    Object.assign(out.remote, fill(remote));
    if (list) items.push(fill(list.item));
  }
  if (list) {
    // La ruta es una lista de claves (los nombres de archivo llevan puntos).
    const target = list.path.reduce((node, key) => node[key], out.entries);
    target.push(...items);
  }
  delete out.repeat;
  return out;
}

export function buildSpec(raw, specName = raw.zip) {
  const spec = expand({ assets: {}, remote: {}, entries: {}, ...raw });
  const assets = {};
  for (const [name, def] of Object.entries(spec.assets)) {
    const a = asset(def);
    assets[name] = { ...a, sha256: sha256(a.data) };
  }
  const prefix = spec.prefix || '';
  const files = Object.keys(spec.entries).sort().map((p) => ({ name: prefix + substitute(p, assets), data: entryData(spec.entries[p], assets, specName) }));
  let zip = zipStore(files);
  if (spec.outer) {
    const outer = [{ name: spec.outer.kit, data: zip }];
    for (const p of Object.keys(spec.outer.docs || {}).sort()) outer.push({ name: p, data: Buffer.from(spec.outer.docs[p], 'utf8') });
    zip = zipStore(outer);
  }
  const remote = {};
  for (const [url, def] of Object.entries(spec.remote)) {
    if (def === null) continue; // 404 a propósito
    const u = new URL(url);
    remote[`${u.host}${u.pathname}`] = def.$asset ? assets[def.$asset].data : entryData(def, assets, specName);
  }
  // «expect» con las mismas fichas resueltas ({{sha1:…}} sirve para el nombre estable de un medio en la firma).
  spec.expect = substitute(spec.expect || {}, assets);
  return { name: spec.zip, zip, remote, files: files.map((f) => f.name), spec };
}

// «extends»: una spec puede partir de otra (variantes como «sin portada»): los objetos se mezclan en profundidad, las
// listas, los valores y los objetos vacíos reemplazan, y «remove_entries» quita archivos del kit base.
const isPlain = (v) => v && typeof v === 'object' && !Array.isArray(v);
function merge(base, over) {
  if (!isPlain(base) || !isPlain(over)) return over === undefined ? base : over;
  const out = { ...base };
  // Un objeto vacío reemplaza (así una variante puede vaciar, por ejemplo, las páginas del manifiesto).
  for (const [k, v] of Object.entries(over)) out[k] = isPlain(v) && Object.keys(v).length && isPlain(base[k]) ? merge(base[k], v) : v;
  return out;
}
function resolveSpec(file, seen = []) {
  if (seen.includes(file)) throw new Error(`«extends» circular: ${[...seen, file].join(' → ')}`);
  const spec = JSON.parse(readFileSync(join(SPEC_DIR, file), 'utf8'));
  if (!spec.extends) return spec;
  const base = resolveSpec(spec.extends, [...seen, file]);
  const { extends: _parent, remove_entries: remove = [], ...over } = spec;
  const merged = merge({ ...base, expect: undefined, cubre: undefined }, over);
  for (const path of remove) delete merged.entries[path];
  return merged;
}

export function readSpecs() {
  return readdirSync(SPEC_DIR).filter((f) => f.endsWith('.json')).sort().map((f) => ({ file: f, spec: resolveSpec(f) }));
}

export function buildAll() {
  const out = {};
  for (const { file, spec } of readSpecs()) {
    const built = buildSpec(spec, file);
    if (!/^[a-z0-9-]+\.zip$/.test(built.name || '')) throw new Error(`${file}: «zip» debe ser <nombre>.zip`);
    if (out[built.name]) throw new Error(`${file}: ${built.name} repetido`);
    out[built.name] = built;
  }
  return out;
}

export function lockOf(built) {
  const lock = {};
  for (const [name, b] of Object.entries(built)) {
    lock[name] = sha256(b.zip);
    for (const [p, data] of Object.entries(b.remote)) lock[`remote/${p}`] = sha256(data);
  }
  return sortKeys(lock);
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const args = process.argv.slice(2);
  const opt = (name) => { const i = args.indexOf(name); return i >= 0 ? args[i + 1] : null; };
  const built = buildAll();
  const lock = lockOf(built);
  if (args.includes('--write-lock')) {
    writeFileSync(LOCK, JSON.stringify({ _comentario: 'sha256 de los ZIP y de los medios remotos que genera build.mjs. Se reescribe con --write-lock tras cambiar una spec; tests/elements-kit-fixtures.mjs lo compara.', ...lock }, null, 1) + '\n');
    console.log(`fixtures.lock.json: ${Object.keys(lock).length} entradas.`);
  }
  if (args.includes('--check')) {
    const saved = existsSync(LOCK) ? JSON.parse(readFileSync(LOCK, 'utf8')) : {};
    delete saved._comentario;
    const bad = [...new Set([...Object.keys(lock), ...Object.keys(saved)])].filter((k) => lock[k] !== saved[k]);
    if (bad.length) {
      console.error(`Los kits sintéticos no coinciden con fixtures.lock.json (${bad.slice(0, 6).join(', ')}). Si la spec cambió a propósito: node tests/sources/kits/build.mjs --write-lock`);
      process.exit(1);
    }
    console.log(`Kits sintéticos: ${Object.keys(built).length} ZIP iguales a fixtures.lock.json.`);
  }
  const outDir = opt('--out');
  if (outDir) {
    mkdirSync(outDir, { recursive: true });
    for (const [name, b] of Object.entries(built)) writeFileSync(join(outDir, name), b.zip);
    for (const [name, b] of Object.entries(built)) writeFileSync(join(outDir, basename(name, '.zip') + '.expect.json'), JSON.stringify(b.spec.expect || {}, null, 1));
  }
  const remoteDir = opt('--remote');
  if (remoteDir) {
    for (const b of Object.values(built)) {
      for (const [p, data] of Object.entries(b.remote)) {
        mkdirSync(dirname(join(remoteDir, p)), { recursive: true });
        writeFileSync(join(remoteDir, p), data);
      }
    }
  }
}
