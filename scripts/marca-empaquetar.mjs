#!/usr/bin/env node
// Marca propia, fase 2 (docs/MARCA_PROPIA.md, opción A de DECISIONES): transforma al empaquetar una COPIA de
// digitalisimo-elements (nunca el repositorio). La huella del núcleo y sus sumas oficiales se verifican antes, sobre el
// árbol original (scripts/build-packages.sh); este script trabaja sobre la copia ya verificada.
//
//   1. `elementor/` → `motor/` (D-9). El manifiesto del núcleo pasa a `motor/digitalisimo-core.php`.
//   2. Cada archivo que NO es PHP y que tiene «elementor» en su ruta o en su contenido:
//      - su original entra al archivo `marca/originales.php` (empieza con `<?php exit;`: pedido por URL no devuelve nada);
//      - CSS, JS, JSON, SVG, HTML, XML y texto se transforman (reglas de marca/reglas.json) y se renombran
//        (`elementor` → `dg` en cada tramo de la ruta);
//      - binarios (fuentes, imágenes) sólo se renombran;
//      - documentación, mapas de fuentes y traducciones compiladas (.md, .map, .po, .pot, .mo, .lock, .scss, el readme
//        del núcleo) no se publican: sólo quedan en el archivo;
//      - lo que el servidor LEE para pintar (figuras de separadores, plantillas twig, guías del MCP, datos del kit de
//        ventas, la licencia) se deja como está y se informa (`servidor_lee`): necesita su propio arreglo en tiempo de
//        ejecución.
//   3. `marca/indice.php`: ruta original → ruta publicada (o null si no se publica), para el sitio con la marca apagada
//      (despliega los originales en una carpeta neutra) y para la auditoría.
//   4. `marca/reglas.php` y `marca/vocabulario.php` en lugar de los .json.
//   5. `marca/arbol.php`: huella del árbol transformado (mismo algoritmo que scripts/elementor-core-tree.py), que el sitio
//      puede comprobar.
// Uso: node scripts/marca-empaquetar.mjs <copia>/digitalisimo-elements [--json]
import { readFileSync, writeFileSync, readdirSync, statSync, renameSync, mkdirSync, rmSync, existsSync, openSync, writeSync, closeSync } from 'node:fs';
import { join, relative, dirname, posix } from 'node:path';
import { createHash } from 'node:crypto';
import { deflateRawSync } from 'node:zlib';
import { loadRules, transformCss, transformJs, transformStr } from './marca-transform.mjs';

const dir = process.argv[2];
if (!dir || !existsSync(join(dir, 'pro-elements.php'))) { console.error('uso: node scripts/marca-empaquetar.mjs <copia>/digitalisimo-elements'); process.exit(2); }
const rules = loadRules(join(dir, 'marca/reglas.json'));
const P = rules.prefijo || 'dg';

if (existsSync(join(dir, 'elementor'))) {
  if (existsSync(join(dir, 'motor'))) { console.error('la copia ya tiene motor/ y elementor/'); process.exit(1); }
  renameSync(join(dir, 'elementor'), join(dir, 'motor'));
}
rmSync(join(dir, 'marca/motor'), { recursive: true, force: true }); // el espejo del prototipo de la fase 0 no se usa
rmSync(join(dir, 'marca/assets'), { recursive: true, force: true });
rmSync(join(dir, 'marca/modules'), { recursive: true, force: true });

const walk = (d, out = []) => { for (const n of readdirSync(d)) { const p = join(d, n); statSync(p).isDirectory() ? walk(p, out) : out.push(p); } return out; };
// Datos como arreglo PHP literal: opcache lo guarda ya armado (sin decodificar nada en cada petición).
const phpValue = (v) => {
  if (v === null || v === undefined) return 'null';
  if (typeof v === 'boolean') return v ? 'true' : 'false';
  if (typeof v === 'number') return Number.isInteger(v) ? String(v) : JSON.stringify(v);
  if (typeof v === 'string') return `'${v.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
  if (Array.isArray(v)) return `array(${v.map(phpValue).join(',')})`;
  return `array(${Object.entries(v).map(([k, x]) => `${phpValue(k)}=>${phpValue(x)}`).join(',')})`;
};
const php = (data) => `<?php\n// Generado por scripts/marca-empaquetar.mjs.\nreturn ${phpValue(data)};\n`;

// Lo que el servidor LEE por su ruta para pintar (no se puede renombrar ni transformar: con la marca apagada tiene que
// dar lo de siempre). Se publica con el contenido ORIGINAL, pero la palabra va enmascarada con una forma equivalente que
// el lector decodifica igual (`mask()`): así sirve con la marca encendida y apagada y pedido por URL no dice «elementor».
const SERVER_READ = [
  /^motor\/assets\/shapes\/.*\.svg$/, /\.twig$/, /^motor\/modules\/mcp\/static-resources\//,
  /^modules\/digitalisimo-sales\/(templates|data)\//,
];
// Licencias: el texto se conserva entero (la GPL pide no quitar los avisos), pero en un .php que pedido por URL no
// devuelve nada.
const LICENSE = /(^|\/)(license\.txt|LICENSE(\.txt|\.md)?|COPYING)$/i;
const ZWJ = '\u200d';
/** El contenido original con la palabra enmascarada según el tipo de archivo (ver SERVER_READ). */
function mask(rel, text) {
  const ent = (t) => t.replace(/elementor/gi, (m) => `&#${m.charCodeAt(0)};${m.slice(1)}`);
  if (/\.json$/.test(rel)) return text.replace(/elementor/gi, (m) => `\\u${m.charCodeAt(0).toString(16).padStart(4, '0')}${m.slice(1)}`);
  if (/\.(svg|html?|xml)$/.test(rel)) return ent(text);
  if (/\.twig$/.test(rel)) {
    // En el código de Twig, las cadenas se parten (`('elemen' ~ 'tor…')`, Twig y Twing las unen igual); los comentarios
    // con la palabra se quitan; en el HTML, la entidad.
    return text.replace(/(\{[{%#])([\s\S]*?)([}%#]\})|([^{]+|\{)/g, (m, open, code, close, raw) => {
      if (raw !== undefined) return ent(raw);
      if (open === '{#') return /elementor/i.test(code) ? '' : m;
      return open + code.replace(/'([^'\\]*)'|"([^"\\]*)"/g, (q, a, b) => {
        const v = a ?? b;
        if (!/elementor/i.test(v)) return q;
        const i = v.search(/elementor/i) + 6;
        return `('${v.slice(0, i)}' ~ '${v.slice(i)}')`;
      }) + close;
    });
  }
  // Markdown y texto que lee el MCP: un separador invisible dentro de la palabra (el MCP lo quita al responder).
  return text.replace(/(e)(lementor)/gi, (m, a, b) => `${m.slice(0, 6)}${ZWJ}${m.slice(6)}`);
}
/**
 * Fuentes TrueType (y la TTF sin comprimir de un .eot): «elementor» sólo está en nombres de glifos (tabla `post`), que no
 * cambian cómo se ve la fuente. Se cambia por una palabra del mismo largo (`digitalis`) y se recalculan las sumas de
 * comprobación de las tablas y `head.checkSumAdjustment`, para que los navegadores (OTS) la acepten igual.
 */
function fixFont(buf, rel) {
  let base = 0;
  if (/\.eot$/i.test(rel)) {
    const eotSize = buf.readUInt32LE(0);
    const dataSize = buf.readUInt32LE(4);
    if (buf.readUInt32LE(12) & 0x4) return null; // comprimida (MTX): no se toca
    base = eotSize - dataSize;
  }
  const out = Buffer.from(buf);
  const ttf = out.subarray(base);
  const word = Buffer.from('digitalis');
  for (let i = ttf.indexOf('elementor'); i >= 0; i = ttf.indexOf('elementor', i + 9)) word.copy(ttf, i);
  for (let i = ttf.indexOf('Elementor'); i >= 0; i = ttf.indexOf('Elementor', i + 9)) Buffer.from('Digitalis').copy(ttf, i);
  const sum = (b, off, len) => { let s = 0; for (let k = 0; k < len; k += 4) { const w = [0, 1, 2, 3].reduce((a, j) => a * 256 + (off + k + j < b.length && k + j < len ? b[off + k + j] : 0), 0); s = (s + w) >>> 0; } return s; };
  const n = ttf.readUInt16BE(4);
  let head = -1;
  for (let t = 0; t < n; t++) {
    const rec = 12 + t * 16;
    const tag = ttf.toString('latin1', rec, rec + 4);
    const off = ttf.readUInt32BE(rec + 8);
    const len = ttf.readUInt32BE(rec + 12);
    if (tag === 'head') { head = off; ttf.writeUInt32BE(0, off + 8); }
    ttf.writeUInt32BE(sum(ttf, off, len), rec + 4);
  }
  if (head >= 0) ttf.writeUInt32BE((0xb1b0afba - sum(ttf, 0, ttf.length)) >>> 0, head + 8);
  return out;
}
/** JSON con las mismas reglas que el JS transformado (claves y valores), para que coincidan (traducciones, datos). */
function jsonStr(text) {
  let data;
  try { data = JSON.parse(text); } catch { return null; }
  const walk = (v) => (typeof v === 'string' ? transformStr(v, rules) : Array.isArray(v) ? v.map(walk) : v && typeof v === 'object' ? Object.fromEntries(Object.entries(v).map(([k, x]) => [transformStr(k, rules), walk(x)])) : v);
  return JSON.stringify(walk(data));
}
const DROP = /\.(md|map|po|pot|mo|lock|scss)$|^motor\/readme\.txt$|^readme\.txt$/;
const TEXT = /\.(css|js|mjs|cjs|json|svg|html?|xml|txt)$/;
const rename = (rel) => rel.split('/').map((seg, i) => (i === 0 && seg === 'motor' ? seg : seg.replace(/elementor/gi, (m) => (m[0] === 'E' ? 'Dg' : P)))).join('/');
const relUrl = (css) => css.replace(/url\(\s*(['"]?)(?!data:|https?:|\/\/|#)([^'")]+)\1\s*\)/gi, (m, q, u) => (/elementor/i.test(u) ? `url(${q}${u.replace(/elementor/gi, P)}${q})` : m));

// El manifiesto del núcleo: PHP (lo lee Digitalisimo_Elements_Core::version()).
const manifest = join(dir, 'motor/digitalisimo-core.json');
if (existsSync(manifest)) { writeFileSync(join(dir, 'motor/digitalisimo-core.php'), php(JSON.parse(readFileSync(manifest, 'utf8')))); rmSync(manifest); }

const index = {};
const archive = [];
// El JS y el CSS sin minificar del núcleo que tienen su `.min` al lado sólo se cargan con SCRIPT_DEBUG: no se publican
// ni se guardan sus originales (el sitio sirve el `.min`, Package::map_url()). Es más de la mitad del archivo de
// originales y del peso que el paquete transformado agrega al ZIP.
let unminified = 0;
for (const file of walk(join(dir, 'motor'))) {
  const m = file.match(/^(.*)\.(js|css)$/);
  if (!m || /\.min\.(js|css)$/.test(file) || !existsSync(`${m[1]}.min.${m[2]}`)) continue;
  rmSync(file);
  index[relative(dir, file).split('\\').join('/')] = null;
  unminified++;
}
const report = { transformados: 0, renombrados: 0, sin_publicar: 0, sin_minificar: unminified, servidor_lee: [], quedan: [] };
for (const file of walk(dir).sort()) {
  const rel = relative(dir, file).split('\\').join('/');
  // Idiomas del núcleo (`elementor-es_MX.l10n.php`): PHP, el contenido no se sirve, pero el nombre se puede pedir por
  // URL (D-8). Se renombran (`dg-es_MX.l10n.php`) y el sitio los encuentra con el nombre nuevo (Package).
  if (/^motor\/languages\/elementor[^/]*\.l10n\.php$/.test(rel)) {
    const dest = rename(rel);
    renameSync(file, join(dir, dest));
    index[rel] = dest;
    report.idiomas_php = (report.idiomas_php || 0) + 1;
    continue;
  }
  if (rel.endsWith('.php') || rel.startsWith('marca/')) continue;
  const buf = readFileSync(file);
  const named = /elementor/i.test(rel.replace(/^motor\//, ''));
  const content = /elementor/i.test(buf.toString('latin1'));
  if (!named && !content) continue;
  if (SERVER_READ.some((r) => r.test(rel))) {
    const masked = mask(rel, buf.toString('utf8'));
    writeFileSync(file, masked);
    report.servidor_lee.push(rel);
    if (/elementor/i.test(masked)) report.quedan.push(rel);
    continue;
  }
  if (LICENSE.test(rel)) {
    rmSync(file);
    writeFileSync(`${file}.php`, `<?php exit; ?>\n${buf.toString('utf8')}`);
    index[rel] = `${rel}.php`;
    report.licencias = (report.licencias || 0) + 1;
    continue;
  }
  // Fuentes con la palabra sólo en nombres de glifos y sin ella en la ruta: se corrigen en su lugar (sirven igual con la
  // marca apagada, no hace falta el original).
  if (!named && /\.(ttf|otf|eot)$/i.test(rel)) {
    const fixed = fixFont(buf, rel);
    if (fixed && !/elementor/i.test(fixed.toString('latin1'))) { writeFileSync(file, fixed); report.fuentes = (report.fuentes || 0) + 1; continue; }
  }
  archive.push([rel, buf]);
  if (DROP.test(rel)) { rmSync(file); index[rel] = null; report.sin_publicar++; continue; }
  let out = buf;
  if (TEXT.test(rel)) {
    let text = buf.toString('utf8');
    if (/\.css$/.test(rel)) text = relUrl(transformCss(text, rules));
    else if (/\.(js|mjs|cjs)$/.test(rel)) text = transformJs(text, rules);
    else if (/\.json$/.test(rel) && jsonStr(text) !== null) text = jsonStr(text);
    else text = text.replace(/elementor/gi, (m) => (m[0] === 'E' ? 'DIGITALÍSIMO' : P));
    out = Buffer.from(text);
    report.transformados++;
  } else report.renombrados++;
  const dest = rename(rel);
  rmSync(file);
  mkdirSync(dirname(join(dir, dest)), { recursive: true });
  writeFileSync(join(dir, dest), out);
  index[rel] = dest;
  if (/elementor/i.test(out.toString('latin1')) || /elementor/i.test(dest.replace(/^motor\//, ''))) report.quedan.push(dest);
}

// Archivo de originales: cabecera PHP que no imprime nada, una línea de índice (ruta, desplazamiento, largo) y los
// contenidos comprimidos (deflate) uno tras otro.
const header = '<?php exit; // DIGITALÍSIMO: archivo de datos, no se ejecuta ni se sirve.\n';
let offset = 0;
const entries = archive.map(([rel, buf]) => { const z = deflateRawSync(buf, { level: 9 }); const e = [rel, offset, z.length, buf.length, createHash('sha256').update(buf).digest('hex')]; offset += z.length; return [e, z]; });
const fd = openSync(join(dir, 'marca/originales.php'), 'w');
writeSync(fd, header);
writeSync(fd, Buffer.from(JSON.stringify(entries.map(([e]) => e)) + '\n'));
for (const [, z] of entries) writeSync(fd, z);
closeSync(fd);
writeFileSync(join(dir, 'marca/indice.php'), php(index));
writeFileSync(join(dir, 'marca/reglas.php'), php(rules));
rmSync(join(dir, 'marca/reglas.json'), { force: true });
// El vocabulario del editor (scripts/marca-vocabulario.mjs): también como PHP, que pedido por URL no se sirve.
if (existsSync(join(dir, 'marca/vocabulario.json'))) {
  writeFileSync(join(dir, 'marca/vocabulario.php'), php(JSON.parse(readFileSync(join(dir, 'marca/vocabulario.json'), 'utf8'))));
  rmSync(join(dir, 'marca/vocabulario.json'));
}

// Huella del árbol transformado (sin arbol.php).
const list = walk(dir).map((f) => relative(dir, f).split('\\').join('/')).filter((r) => r !== 'marca/arbol.php').sort()
  .map((r) => `${createHash('sha256').update(readFileSync(join(dir, r))).digest('hex')}  ${r}\n`);
const tree = { files: list.length, tree_sha256: createHash('sha256').update(list.join('')).digest('hex') };
writeFileSync(join(dir, 'marca/arbol.php'), php(tree));

report.originales = { archivos: archive.length, bytes: statSync(join(dir, 'marca/originales.php')).size };
report.arbol = tree;
if (process.argv.includes('--json')) console.log(JSON.stringify(report, null, 1));
else console.log(`Transformados ${report.transformados}, renombrados ${report.renombrados}, sin publicar ${report.sin_publicar}, fuentes corregidas ${report.fuentes || 0}, licencias en .php ${report.licencias || 0}, sin minificar quitados ${report.sin_minificar}; originales: ${report.originales.archivos} archivos en ${(report.originales.bytes / 1048576).toFixed(1)} MB; el servidor lee ${report.servidor_lee.length}; con «elementor» todavía: ${report.quedan.length}${report.quedan.length ? ' · ' + report.quedan.slice(0, 5).join(' ') : ''}; árbol ${tree.files} archivos ${tree.tree_sha256.slice(0, 12)}.`);
