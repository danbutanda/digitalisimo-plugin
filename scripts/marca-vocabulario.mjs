#!/usr/bin/env node
// Marca propia, fase 2 (D-12 de docs/MARCA_PROPIA.md): el vocabulario que comparten el JS del paquete transformado y el
// servidor. Con la marca encendida, el JS del editor manda al servidor los nombres ya transformados (acciones AJAX
// `digitalisimo_ajax`, rutas `digitalisimo/v1/…`, tipos `digitalisimo_library`, claves `digitalisimo_post_lock`,
// plantillas `digitalisimo_canvas`…) y el servidor tiene que entenderlos. Este script lee TODAS las cadenas del JS del
// núcleo, de PRO y de los módulos (con el mismo analizador léxico del empaquetado) y escribe en
// `digitalisimo-elements/marca/vocabulario.json`, para cada cadena de tipo identificador con «elementor», su forma
// transformada → la original. El servidor deshace sólo esas formas exactas (Transform::str_in), nunca el texto libre.
//
// Una forma transformada que ya es un nombre propio nuestro (aparece en el código fuera del núcleo) no entra: se
// informa como choque.
//
// Uso: node scripts/marca-vocabulario.mjs [--escribir]   (sin --escribir, falla si el archivo no está al día)
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';
import { createHash } from 'node:crypto';
import { loadRules, lexJs, transformStr, transformIdent } from './marca-transform.mjs';

const ROOT = new URL('..', import.meta.url).pathname;
const EL = join(ROOT, 'digitalisimo-elements');
const FILE = join(EL, 'marca/vocabulario.json');
const rules = loadRules();
const walk = (d, o = []) => { if (!existsSync(d)) return o; for (const n of readdirSync(d)) { const p = join(d, n); if (statSync(p).isDirectory()) { if (!['node_modules', 'vendor', 'marca'].includes(n)) walk(p, o); } else o.push(p); } return o; };

// El JS que se publica transformado (sin los .js sin minificar del núcleo cuando hay .min.js: son el mismo código).
const all = walk(EL).filter((f) => /\.m?js$/.test(f));
const files = all.filter((f) => !(/\.js$/.test(f) && !/\.min\.js$/.test(f) && all.includes(f.replace(/\.js$/, '.min.js'))));
const IDENT = /^[A-Za-z0-9_\-/.:#=\[\]@$]+$/;
const vocab = new Map();
const hash = createHash('sha256');
for (const f of files.sort()) {
  const src = readFileSync(f, 'utf8');
  if (!/elementor/i.test(src)) continue;
  hash.update(relative(EL, f) + '\0' + src);
  lexJs(src, (type, text) => {
    // Claves que el JS manda como identificadores (`{elementor_post_lock: …}` en el heartbeat): también llegan al servidor.
    if (type === 'id' && /^_?elementor_[a-z0-9_]+$/.test(text)) {
      const to = transformIdent(text, rules);
      if (to !== text) vocab.set(to, vocab.has(to) && vocab.get(to) !== text ? [].concat(vocab.get(to), text) : text);
    }
    if ((type === 'str' || type === 'tpl') && text.length <= 120 && IDENT.test(text) && /elementor/i.test(text)) {
      const to = transformStr(text, rules);
      if (to !== text) vocab.set(to, vocab.has(to) && vocab.get(to) !== text ? [].concat(vocab.get(to), text) : text);
    }
    return text;
  });
}

// Choques: formas que también son nombres propios nuestros o que vienen de dos originales distintos.
const ownCode = walk(EL).filter((f) => !f.includes('/elementor/') && !/modules\/digitalisimo-(marca|mcp)\//.test(f) && /\.(php|js)$/.test(f) && !/\.min\.js$/.test(f)).map((f) => readFileSync(f, 'utf8')).join('\n');
const choques = [];
const out = {};
for (const [to, from] of [...vocab.entries()].sort((a, b) => a[0].localeCompare(b[0]))) {
  if (Array.isArray(from)) { choques.push(`${to} ← ${[...new Set(from)].join(', ')}`); continue; }
  if (to === rules.prefijo || to.length < 4) continue; // `dg`: demasiado corto para deshacerlo sin contexto
  if (/^digitalisimo[_-]/.test(to) && new RegExp(`['"\`]${to.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}['"\`]`).test(ownCode)) { choques.push(`${to} ← ${from} (nombre propio)`); continue; }
  out[to] = from;
}
const data = { descripcion: 'Generado por scripts/marca-vocabulario.mjs: forma transformada → original de las cadenas de tipo identificador con «elementor» del JS del paquete (D-12). No se edita a mano.', huella: hash.digest('hex'), choques, vocabulario: out };
const text = JSON.stringify(data, null, '\t') + '\n';
if (process.argv.includes('--escribir')) {
  writeFileSync(FILE, text);
  console.log(`vocabulario.json escrito: ${Object.keys(out).length} formas, ${choques.length} choques, ${files.length} archivos JS.`);
} else {
  const now = existsSync(FILE) ? readFileSync(FILE, 'utf8') : '';
  if (now !== text) { console.log('FALLA vocabulario.json no está al día con el JS: node scripts/marca-vocabulario.mjs --escribir'); process.exit(1); }
  console.log(`Vocabulario del editor al día: ${Object.keys(out).length} formas, ${choques.length} choques.`);
}
