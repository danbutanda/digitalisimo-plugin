// Kits (etapa K, §13.1 «fixtures» y §13.2): los kits sintéticos de tests/sources/kits/.
// - El generador es determinista: dos construcciones dan los mismos bytes y coinciden con fixtures.lock.json (sin
//   entradas de más ni de menos).
// - Los ZIP son STORED con fecha DOS de 1980; los IDs de elemento tienen 7 hex.
// - Nada apunta al proveedor (tests/playground/brand-audit/vendors.json), todo host es «.test» y todo correo es de
//   @example.com: son sintéticos, no copias de un kit real (el repositorio es público).
// - Cada spec declara «expect» por documento (CV-9) y lo que pide §13.2 para su caso.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { buildAll, lockOf, readSpecs } from './sources/kits/build.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const KITS = join(ROOT, 'tests/sources/kits');
const vendors = JSON.parse(readFileSync(join(ROOT, 'tests/playground/brand-audit/vendors.json'), 'utf8'));

// 1. Determinismo y candado.
const first = buildAll();
const second = buildAll();
for (const name of Object.keys(first)) assert.ok(first[name].zip.equals(second[name].zip), `${name}: dos construcciones dieron bytes distintos`);
const lock = JSON.parse(readFileSync(join(KITS, 'fixtures.lock.json'), 'utf8'));
delete lock._comentario;
assert.deepEqual(lockOf(first), lock, 'Los kits sintéticos no coinciden con fixtures.lock.json: si la spec cambió a propósito, node tests/sources/kits/build.mjs --write-lock');

// Lector mínimo de ZIP (directorio central) para revisar lo generado.
function entries(zip) {
  const end = zip.lastIndexOf(Buffer.from([0x50, 0x4b, 0x05, 0x06]));
  assert.ok(end >= 0, 'ZIP sin fin de directorio central');
  const count = zip.readUInt16LE(end + 10);
  let p = zip.readUInt32LE(end + 16);
  const out = [];
  for (let i = 0; i < count; i++) {
    assert.equal(zip.readUInt32LE(p), 0x02014b50, 'entrada del directorio central dañada');
    const method = zip.readUInt16LE(p + 10);
    const time = zip.readUInt16LE(p + 12);
    const date = zip.readUInt16LE(p + 14);
    const size = zip.readUInt32LE(p + 24);
    const nameLen = zip.readUInt16LE(p + 28);
    const extra = zip.readUInt16LE(p + 30);
    const comment = zip.readUInt16LE(p + 32);
    const local = zip.readUInt32LE(p + 42);
    const name = zip.subarray(p + 46, p + 46 + nameLen).toString('utf8');
    const dataStart = local + 30 + zip.readUInt16LE(local + 26) + zip.readUInt16LE(local + 28);
    out.push({ name, method, time, date, data: zip.subarray(dataStart, dataStart + size) });
    p += 46 + nameLen + extra + comment;
  }
  return out;
}
const flatten = (zip, prefix = '') => entries(zip).flatMap((e) => (/\.zip$/i.test(e.name) ? flatten(e.data, `${prefix}${e.name}!`) : [{ ...e, name: prefix + e.name }]));

const hosts = [...vendors.hosts, ...(vendors.future || [])];
const vendorRe = new RegExp(`(?<![a-z0-9-])(?:[a-z0-9-]+\\.)*(?:${hosts.map((h) => h.replace(/\./g, '\\.')).join('|')})(?![a-z0-9-])`, 'i');
const urlRe = /\bhttps?:\/\/([a-z0-9.-]+)/gi;
const mailRe = /[a-z0-9._%+-]+@([a-z0-9.-]+\.[a-z]{2,})/gi;
const problems = [];
const textual = (name) => /\.(json|xml|svg|html|txt|css|js)$/i.test(name);

// 2 y 3. Formato de los ZIP y contenido sintético.
const checkText = (where, raw) => {
  // Los espacios de nombres XML (xmlns="http://www.w3.org/…", WXR) son identificadores, no direcciones que se pidan.
  const text = raw.replace(/\sxmlns(?::[a-z]+)?=\\?"[^"\\]*\\?"/gi, '');
  if (vendorRe.test(text)) problems.push(`${where}: nombra un dominio del proveedor`);
  for (const m of text.matchAll(urlRe)) if (!/\.test$/i.test(m[1])) problems.push(`${where}: host ${m[1]} no es .test`);
  for (const m of text.matchAll(mailRe)) if (m[1].toLowerCase() !== 'example.com') problems.push(`${where}: correo de ${m[1]} (sólo @example.com)`);
};
const checkIds = (where, value) => {
  if (Array.isArray(value)) return value.forEach((v) => checkIds(where, v));
  if (!value || typeof value !== 'object') return;
  if (typeof value.elType === 'string' && !/^[0-9a-f]{7}$/.test(String(value.id))) problems.push(`${where}: ID de elemento «${value.id}» no tiene 7 hex`);
  Object.values(value).forEach((v) => checkIds(where, v));
};
for (const [name, built] of Object.entries(first)) {
  for (const e of entries(built.zip)) {
    if (e.method !== 0) problems.push(`${name}: ${e.name} no es STORED`);
    if (e.time !== 0 || e.date !== 0x21) problems.push(`${name}: ${e.name} no tiene la fecha DOS de 1980`);
  }
  if (built.zip.length > 4 * 1024 * 1024) problems.push(`${name}: ${built.zip.length} bytes; un kit sintético no pasa de 4 MB`);
  for (const e of flatten(built.zip)) {
    if (!textual(e.name)) continue;
    const text = e.data.toString('utf8');
    checkText(`${name}:${e.name}`, text);
    if (/\.json$/i.test(e.name)) {
      try { checkIds(`${name}:${e.name}`, JSON.parse(text)); } catch (err) { if (!built.spec.hostil) problems.push(`${name}:${e.name}: JSON inválido`); }
    }
  }
  for (const [p, data] of Object.entries(built.remote)) if (/\.(svg|json|txt)$/i.test(p)) checkText(`${name}:remote/${p}`, data.toString('utf8'));
}
for (const { file } of readSpecs()) checkText(`spec/${file}`, readFileSync(join(KITS, 'spec', file), 'utf8'));
for (const file of ['no-reference.json', 'fixtures.lock.json']) checkText(file, readFileSync(join(KITS, file), 'utf8'));

// 4. «expect» por documento (CV-9) y lo que §13.2 pide a cada spec.
const docsOf = (built) => {
  const names = built.files.map((f) => f.replace(/^[^/]+\/(?=manifest\.json$|templates\/|content\/|wp-content\/|site-settings\.json$|taxonomies\/|digitalisimo\/)/, ''));
  return names.filter((f) => /^(templates\/[^/]+\.json|content\/[^/]+\/[^/]+\.json)$/.test(f)).map((f) => f.replace(/\.json$/, ''));
};
const required = {
  'tercero-envato.zip': ['estilos', 'pagina', 'cabecera', 'pie', 'desconocida'],
  'tercero-pro3.zip': ['theme', 'experiments', 'plugins', 'colores', 'conflicto', 'popup', 'loop', 'loop-carousel', 'taxonomy-filter', 'global-widget', 'formulario', 'dinamicas', 'desconocido', 'e-component', 'fuente', 'iconos', 'metadata', 'wxr'],
  'tercero-1x.zip': ['version'],
  'woo.zip': ['producto', 'variaciones', 'categoria', 'portada'],
  'woo-sin-portada.zip': ['producto', 'categoria'],
  'propio-portada.zip': ['portada', 'digitalisimo'],
  'propio-sin-portada.zip': ['digitalisimo'],
};
for (const [name, built] of Object.entries(first)) {
  const expect = built.spec.expect;
  if (!expect || typeof expect !== 'object') { problems.push(`${name}: sin «expect»`); continue; }
  if (!built.spec.caso) problems.push(`${name}: sin «caso» (qué escenario de §13.3 lo usa)`);
  const docs = built.spec.format === 'envato' ? Object.keys(expect.documents || {}) : docsOf(built);
  for (const doc of docs) if (!(expect.documents || {})[doc]) problems.push(`${name}: el documento ${doc} no tiene «expect»`);
  for (const [doc, e] of Object.entries(expect.documents || {})) {
    for (const key of ['status', 'doc_type']) if (!(key in e)) problems.push(`${name}: «expect» de ${doc} sin ${key}`);
  }
  for (const key of required[name] || []) if (!(built.spec.cubre || []).includes(key)) problems.push(`${name}: §13.2 pide que cubra «${key}»`);
}
for (const name of Object.keys(required)) if (!first[name]) problems.push(`Falta el kit sintético ${name} (§13.2)`);

assert.deepEqual(problems, [], `Kits sintéticos con problemas:\n  ${problems.slice(0, 40).join('\n  ')}`);
console.log(`Kits sintéticos: ${Object.keys(first).length} ZIP deterministas iguales a fixtures.lock.json, STORED con fecha de 1980, IDs de 7 hex, sólo hosts .test y correos @example.com, sin el proveedor y con «expect» por documento.`);
