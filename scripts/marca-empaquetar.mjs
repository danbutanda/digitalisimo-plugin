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
//   4. `marca/reglas.php` en lugar de `marca/reglas.json`.
//   5. `marca/arbol.php`: huella del árbol transformado (mismo algoritmo que scripts/elementor-core-tree.py), que el sitio
//      puede comprobar.
// Uso: node scripts/marca-empaquetar.mjs <copia>/digitalisimo-elements [--json]
import { readFileSync, writeFileSync, readdirSync, statSync, renameSync, mkdirSync, rmSync, existsSync, openSync, writeSync, closeSync } from 'node:fs';
import { join, relative, dirname, posix } from 'node:path';
import { createHash } from 'node:crypto';
import { deflateRawSync } from 'node:zlib';
import { loadRules, transformCss, transformJs } from './marca-transform.mjs';

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
const php = (data) => `<?php\n// Generado por scripts/marca-empaquetar.mjs.\nreturn json_decode( base64_decode( '${Buffer.from(JSON.stringify(data)).toString('base64')}' ), true );\n`;

const SERVER_READ = [
  /^motor\/assets\/shapes\/.*\.svg$/, /\.twig$/, /^motor\/modules\/mcp\/static-resources\//,
  /^modules\/digitalisimo-sales\/(templates|data)\//, /^license\.txt$/, /^LICENSE/, /^COPYING$/,
];
const DROP = /\.(md|map|po|pot|mo|lock|scss)$|^motor\/readme\.txt$|^readme\.txt$/;
const TEXT = /\.(css|js|mjs|cjs|json|svg|html?|xml|txt)$/;
const rename = (rel) => rel.split('/').map((seg, i) => (i === 0 && seg === 'motor' ? seg : seg.replace(/elementor/gi, (m) => (m[0] === 'E' ? 'Dg' : P)))).join('/');
const relUrl = (css) => css.replace(/url\(\s*(['"]?)(?!data:|https?:|\/\/|#)([^'")]+)\1\s*\)/gi, (m, q, u) => (/elementor/i.test(u) ? `url(${q}${u.replace(/elementor/gi, P)}${q})` : m));

// El manifiesto del núcleo: PHP (lo lee Digitalisimo_Elements_Core::version()).
const manifest = join(dir, 'motor/digitalisimo-core.json');
if (existsSync(manifest)) { writeFileSync(join(dir, 'motor/digitalisimo-core.php'), php(JSON.parse(readFileSync(manifest, 'utf8')))); rmSync(manifest); }

const index = {};
const archive = [];
const report = { transformados: 0, renombrados: 0, sin_publicar: 0, servidor_lee: [], quedan: [] };
for (const file of walk(dir).sort()) {
  const rel = relative(dir, file).split('\\').join('/');
  if (rel.endsWith('.php') || rel.startsWith('marca/')) continue;
  const buf = readFileSync(file);
  const named = /elementor/i.test(rel.replace(/^motor\//, ''));
  const content = /elementor/i.test(buf.toString('latin1'));
  if (!named && !content) continue;
  if (SERVER_READ.some((r) => r.test(rel))) { report.servidor_lee.push(rel); continue; }
  archive.push([rel, buf]);
  if (DROP.test(rel)) { rmSync(file); index[rel] = null; report.sin_publicar++; continue; }
  let out = buf;
  if (TEXT.test(rel)) {
    let text = buf.toString('utf8');
    if (/\.css$/.test(rel)) text = relUrl(transformCss(text, rules));
    else if (/\.(js|mjs|cjs)$/.test(rel)) text = transformJs(text, rules);
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

// Huella del árbol transformado (sin arbol.php).
const list = walk(dir).map((f) => relative(dir, f).split('\\').join('/')).filter((r) => r !== 'marca/arbol.php').sort()
  .map((r) => `${createHash('sha256').update(readFileSync(join(dir, r))).digest('hex')}  ${r}\n`);
const tree = { files: list.length, tree_sha256: createHash('sha256').update(list.join('')).digest('hex') };
writeFileSync(join(dir, 'marca/arbol.php'), php(tree));

report.originales = { archivos: archive.length, bytes: statSync(join(dir, 'marca/originales.php')).size };
report.arbol = tree;
if (process.argv.includes('--json')) console.log(JSON.stringify(report, null, 1));
else console.log(`Transformados ${report.transformados}, renombrados ${report.renombrados}, sin publicar ${report.sin_publicar}; originales: ${report.originales.archivos} archivos en ${(report.originales.bytes / 1048576).toFixed(1)} MB; el servidor lee ${report.servidor_lee.length}; con «elementor» todavía: ${report.quedan.length}${report.quedan.length ? ' · ' + report.quedan.slice(0, 5).join(' ') : ''}; árbol ${tree.files} archivos ${tree.tree_sha256.slice(0, 12)}.`);
