#!/usr/bin/env node
// Auditoría de cero rastro de «elementor» (docs/MARCA_PROPIA.md, prueba de aceptación de DECISIONES), parte SIN
// Playground: lo que se decide al empaquetar. La parte que necesita un sitio (front, escritorio, editor, MCP, /wp-json/ y
// URL directas en un servidor) es tests/playground/brand-zero-trace.sh, que escribe su propio informe con las mismas
// capas; `--sitio <informe.json>` los junta.
//
// Capas (cada una cuenta apariciones de «elementor» sin distinguir mayúsculas, fuera de `excepciones_internas`):
//   zip_carpeta       carpeta del motor con el nombre en el ZIP (D-9)
//   zip_nombres       nombres de archivo del ZIP que lo contienen (URL que se pueden pedir; D-8)
//   zip_servibles     contenido de los archivos estáticos del ZIP que un navegador puede pedir por URL
//                     (.css .js .json .txt .svg .html .md .xml): el original sigue en el paquete aunque haya espejo
//   espejo            contenido del espejo de assets del front (`marca/` del ZIP), si lo trae
//   excepciones       excepciones internas de reglas.json sin justificar (deben ser 0)
//   dominio_ajeno     hosts `dg.com`, `*.dg.red`… en lo servible: el transformador nunca debe cambiar el dominio del
//                     proveedor por uno ajeno (B-4 de la revisión F2; deben ser 0)
//   enlaces_proveedor (informativa, no falla) URL y hosts del proveedor que siguen tal cual a propósito (los oculta
//                     digitalisimo-vendor-free.php; quitarlos o mandarlos a la ayuda propia es decisión del dueño), URL
//                     absolutas de otros sitios y nombres de campos que relee el servidor (B-5)
// Uso:
//   node tests/brand-zero-trace.mjs                     ZIP simulado con los archivos versionados del plugin
//   node tests/brand-zero-trace.mjs --zip <archivo>     un ZIP construido (p. ej. scripts/marca-prototipo/zip-motor.sh)
//   node tests/brand-zero-trace.mjs --sitio <json>      suma las capas del Playground
//   --solo-sitio                                        sólo las capas del Playground (sin las del ZIP)
//   --json                                              informe en JSON
// Sale 1 si alguna capa tiene una aparición (en la fase 0 falla en lo que cubre la fase 2: el conteo dice cuánto falta).
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { splitVendor, FIELD_RE } from '../scripts/marca-transform.mjs';

const ROOT = fileURLToPath(new URL('..', import.meta.url));
const argv = process.argv.slice(2);
const arg = (k) => { const i = argv.indexOf(k); return i >= 0 ? argv[i + 1] : ''; };
const ZIP = arg('--zip');
const SITE = arg('--sitio');
const SERVABLE = /\.(css|js|json|txt|svg|html?|md|xml|map)$/i;
const rules = JSON.parse(readFileSync(`${ROOT}/digitalisimo-elements/marca/reglas.json`, 'utf8'));
const all = (t) => (String(t).match(/elementor/gi) || []).length;
// Las apariciones dentro de URL y hosts del proveedor van aparte (enlaces_proveedor).
// Lo que se conserva a propósito y va aparte (enlaces_proveedor): URL y hosts del proveedor, una URL absoluta de otro
// sitio dentro de una cadena del JS (`https://patchstack.com/…?utm_campaign=elementor-…`: el transformador no toca URL
// absolutas, B-4) y los nombres de campos que relee el servidor (`name="elementor_x"`, B-5).
const KEEP = new RegExp(`(?:https?:)?\\/\\/[^\\s"'\`<>]*elementor[^\\s"'\`<>]*|${FIELD_RE.source}`, 'gi');
const plainOf = (t) => splitVendor(t).filter((x) => !x.vendor).map((x) => x.text).join(' ').replace(KEEP, ' ');
const count = (t) => { t = String(t); return all(t) ? all(plainOf(t)) : 0; };
const vendorCount = (t) => { t = String(t); return all(t) ? all(t) - all(plainOf(t)) : 0; };
const ALIEN = /(?<![\w-])(?:[a-z0-9-]+\.)*dg\.(?:com|cloud|red|io|net)(?![\w-])/gi;

let files;
let read;
if (ZIP) {
  files = execFileSync('unzip', ['-Z1', ZIP], { maxBuffer: 1 << 28 }).toString().split('\n').filter((f) => f && !f.endsWith('/'));
  read = (f) => execFileSync('unzip', ['-p', ZIP, f], { maxBuffer: 1 << 28 });
} else if (argv.includes('--solo-sitio')) {
  files = [];
  read = () => '';
} else {
  files = execFileSync('git', ['-C', ROOT, 'ls-files', 'digitalisimo-elements'], { maxBuffer: 1 << 28 }).toString().split('\n')
    .filter((f) => f && !f.startsWith('digitalisimo-elements/bdthemes-element-pack/') && existsSync(`${ROOT}/${f}`));
  read = (f) => readFileSync(`${ROOT}/${f}`);
}

const layers = {
  zip_carpeta: { n: 0, ejemplos: [] },
  zip_nombres: { n: 0, ejemplos: [] },
  zip_servibles: { n: 0, archivos: 0, ejemplos: [] },
  espejo: { n: 0, archivos: 0, ejemplos: [] },
  excepciones: { n: 0, ejemplos: [] },
  dominio_ajeno: { n: 0, ejemplos: [] },
};
const vendor = { n: 0, archivos: 0, ejemplos: [] };
const add = (layer, n, ejemplo) => { layers[layer].n += n; if (ejemplo && layers[layer].ejemplos.length < 12) layers[layer].ejemplos.push(ejemplo); };

if (argv.includes('--solo-sitio')) for (const k of Object.keys(layers)) if (k !== 'excepciones') delete layers[k];
if (files.some((f) => f.startsWith('digitalisimo-elements/elementor/'))) add('zip_carpeta', 1, 'digitalisimo-elements/elementor/');
const byDir = {};
for (const f of files) {
  if (/elementor/i.test(f.replace(/^digitalisimo-elements\/elementor\//, 'digitalisimo-elements/motor/'))) {
    const dir = f.split('/').slice(0, 4).join('/');
    byDir[dir] = (byDir[dir] || 0) + 1;
    if (layers.zip_nombres) layers.zip_nombres.n++;
  }
}
if (layers.zip_nombres) layers.zip_nombres.ejemplos = Object.entries(byDir).sort((a, b) => b[1] - a[1]).slice(0, 12).map(([d, n]) => `${d} (${n})`);
for (const f of files) {
  if (!SERVABLE.test(f)) continue;
  const text = String(read(f));
  const alien = (text.match(ALIEN) || []).length;
  if (alien) add('dominio_ajeno', alien, `${f} (${alien}: ${[...new Set(text.match(ALIEN))].slice(0, 3).join(', ')})`);
  const v = vendorCount(text);
  if (v) { vendor.n += v; vendor.archivos++; if (vendor.ejemplos.length < 6) vendor.ejemplos.push(`${f} (${v})`); }
  const n = count(text);
  if (!n) continue;
  const mirror = f.startsWith('digitalisimo-elements/marca/') && !/\/(reglas|vocabulario)\.json$/.test(f);
  const layer = mirror ? 'espejo' : 'zip_servibles';
  layers[layer].archivos++;
  add(layer, n, `${f} (${n})`);
}
for (const e of rules.excepciones_internas || []) if (!e.que || !e.por_que) add('excepciones', 1, JSON.stringify(e));

let site = {};
if (SITE) site = JSON.parse(readFileSync(SITE, 'utf8')).capas || {};
const layersAll = { ...layers, ...site };
const total = Object.values(layersAll).reduce((a, l) => a + (l.n || 0), 0);
if (argv.includes('--json')) console.log(JSON.stringify({ origen: ZIP || 'repositorio', total, capas: layersAll, enlaces_proveedor: vendor }, null, 1));
else {
  console.log(`Auditoría de cero rastro (${ZIP ? 'ZIP ' + ZIP : 'archivos versionados del plugin'}): ${total} apariciones de «elementor» fuera de las excepciones internas.`);
  for (const [name, l] of Object.entries(layersAll)) console.log(`  ${l.n ? 'FALLA' : 'ok   '} ${name}: ${l.n}${l.archivos ? ` en ${l.archivos} archivos` : ''}${l.ejemplos?.length ? ' · ' + l.ejemplos.slice(0, 4).join(' · ') : ''}`);
  console.log(`  info  enlaces_proveedor: ${vendor.n}${vendor.archivos ? ` en ${vendor.archivos} archivos` : ''} (URL y hosts del proveedor, a propósito: pendiente de decisión del dueño)`);
}
process.exit(total ? 1 : 0);
