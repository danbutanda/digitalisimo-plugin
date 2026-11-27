#!/usr/bin/env node
// Marca propia (docs/MARCA_PROPIA.md), fase 0: deriva las reglas de la capa de marca de los bundles REALES del front
// (núcleo incluido, derivado de PRO y módulos propios) y las compara con `digitalisimo-elements/marca/reglas.json`.
//
// Lee los CSS y JS que puede servir el front, quita comentarios y cadenas para separar identificadores de textos, y
// clasifica cada aparición de «elementor»:
//   - clases (`.elementor-…`, `elementor-…` en cadenas de selectores o de clases);
//   - atributos `data-elementor-*` y su forma `dataset.elementorX`;
//   - globales y propiedades JS (`elementorFrontendConfig`, `__elementorLoadChunk`…);
//   - eventos y ganchos JS (`elementor/frontend/init`, `elementor-pro/…`);
//   - rutas REST (`elementor/v1/…`, `elementor-pro/v1/…`) y acciones AJAX (`elementor_…`);
//   - acciones de URL (`#elementor-action:`), rutas de archivos y dominios de texto.
//
// Uso:
//   node scripts/marca-reglas.mjs              inventario en la salida y comprobación contra reglas.json (sale 1 si
//                                              algo de los bundles no lo cubre una regla)
//   node scripts/marca-reglas.mjs --escribir   regenera las listas derivadas de reglas.json (conserva las reglas
//                                              escritas a mano: prefijos, rutas, valores, fragmentos, excepciones)
//   node scripts/marca-reglas.mjs --json       el inventario completo en JSON
import { readFileSync, writeFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative } from 'node:path';
import { createHash } from 'node:crypto';

const ROOT = new URL('..', import.meta.url).pathname;
const EL = join(ROOT, 'digitalisimo-elements');
const RULES = join(EL, 'marca', 'reglas.json');
const args = new Set(process.argv.slice(2));

function walk(dir, out = []) {
  if (!existsSync(dir)) return out;
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    const st = statSync(p);
    if (st.isDirectory()) walk(p, out);
    else out.push(p);
  }
  return out;
}

// Lo que puede pedir el front. Del núcleo: hojas del front, de widgets y condicionales, scripts del front, trozos y
// manejadores, y las librerías del front. Del derivado de PRO y de los módulos propios: todo lo que no es del
// escritorio ni del editor (por nombre). Se prefiere la versión .min cuando existe (es la que se sirve).
const EDITOR = /(^|[/-])(admin|editor|app|common|onboarding|ai|kit-library|kit-elements|notes|packages|web-cli|new-template|beta-tester|dev-tools|import-export|element-manager|checklist|announcements|styleguide|cloud|container-converter|responsive-bar|e-select2|pickr|nprogress|perfect-scrollbar|html2canvas|dom-to-image|color-thief|backbone|inline-editor|tipsy|flatpickr|nouislider|preview|screenshot|custom-code|display-conditions|atomic-form-editor|editor-interactions|form-submission-admin|swatches-admin|theme-light|theme-dark|editor-one|mcp|role-manager|wp-one)([./-]|$)/;
function served(files) {
  const set = new Set(files);
  return files.filter((f) => {
    if (!/\.(css|js)$/.test(f) || /\.map$/.test(f)) return false;
    if (/\.min\.(css|js)$/.test(f)) return true;
    return !set.has(f.replace(/\.(css|js)$/, '.min.$1'));
  });
}
function group(f) {
  const rel = relative(EL, f);
  if (rel.startsWith('elementor/')) return 'motor';
  if (rel.startsWith('modules/digitalisimo-') || rel.startsWith('assets/digitalisimo/')) return 'propio';
  return 'pro';
}
function frontFiles() {
  const engine = [
    ...walk(join(EL, 'elementor/assets/css')).filter((f) => /\/(frontend[^/]*|widget-[^/]*|e-swiper[^/]*|apple-webkit[^/]*|lightbox[^/]*|dialog[^/]*|shapes[^/]*)\.css$|\/(conditionals|templates|modules)\//.test(f)),
    ...walk(join(EL, 'elementor/assets/js')).filter((f) => /\/(frontend[^/]*|webpack\.runtime[^/]*|[^/]*handler[^/]*)\.js$|\/chunks\//.test(f)),
    ...walk(join(EL, 'elementor/assets/lib')),
  ];
  const ours = [
    ...walk(join(EL, 'assets/css')), ...walk(join(EL, 'assets/js')), ...walk(join(EL, 'assets/lib')),
    ...walk(join(EL, 'modules')).filter((f) => /\/assets\//.test(f)),
  ];
  return served([...engine, ...ours]).filter((f) => !EDITOR.test(relative(EL, f).replace(/^elementor\//, ''))).sort();
}

// Separa código de cadenas y comentarios (un analizador mínimo: sirve para clasificar, no para ejecutar).
function lexJs(src) {
  const code = [];
  const strings = [];
  let i = 0;
  const n = src.length;
  let buf = '';
  let prev = '';
  while (i < n) {
    const c = src[i];
    const d = src[i + 1];
    if (c === '/' && d === '/') { while (i < n && src[i] !== '\n') i++; continue; }
    if (c === '/' && d === '*') { const e = src.indexOf('*/', i + 2); i = e < 0 ? n : e + 2; continue; }
    if (c === '"' || c === "'" || c === '`') {
      let s = '';
      i++;
      while (i < n && src[i] !== c) {
        if (src[i] === '\\') { s += src[i] + (src[i + 1] || ''); i += 2; continue; }
        if (c === '`' && src[i] === '$' && src[i + 1] === '{') { // ${…}: el código vuelve al lexer
          let depth = 1; i += 2; let inner = '';
          while (i < n && depth) { if (src[i] === '{') depth++; else if (src[i] === '}') depth--; if (depth) inner += src[i]; i++; }
          const sub = lexJs(inner); buf += ' ' + sub.code + ' '; strings.push(...sub.strings); s += '\u0000';
          continue;
        }
        s += src[i]; i++;
      }
      i++;
      strings.push(s);
      buf += ' "" ';
      prev = '"';
      continue;
    }
    // Expresión regular literal: tras un operador o un paréntesis/coma abierta.
    if (c === '/' && /[(,=:[!&|?{};]$/.test(buf.trimEnd().slice(-1) || '(')) {
      let j = i + 1; let cls = false;
      while (j < n && (src[j] !== '/' || cls) && src[j] !== '\n') { if (src[j] === '\\') j++; else if (src[j] === '[') cls = true; else if (src[j] === ']') cls = false; j++; }
      if (src[j] === '/') { strings.push(src.slice(i + 1, j)); i = j + 1; while (/[a-z]/.test(src[i] || '')) i++; buf += ' /r/ '; continue; }
    }
    buf += c; i++; prev = c;
  }
  code.push(buf);
  return { code: code.join(''), strings };
}

const inv = {
  archivos: { motor: 0, pro: 0, propio: 0 },
  con_elementor: 0,
  apariciones: 0,
  clases: new Map(),
  atributos: new Map(),
  dataset: new Map(),
  globales: new Map(),
  propiedades: new Map(),
  eventos: new Map(),
  rest: new Map(),
  ajax: new Map(),
  acciones_url: new Map(),
  rutas: new Map(),
  dominios: new Map(),
  css_otros: new Map(),
  textos: new Map(),
  por_archivo: [],
};
const bump = (m, k, by = 1) => m.set(k, (m.get(k) || 0) + by);

function classifyString(s, file) {
  if (!/elementor/i.test(s)) return;
  for (const m of s.matchAll(/data-elementor-[a-z0-9-]+/g)) bump(inv.atributos, m[0]);
  for (const m of s.matchAll(/#elementor-action(?::[a-z0-9_-]+)?/gi)) bump(inv.acciones_url, m[0].replace(/:.*/, ':'));
  for (const m of s.matchAll(/\b(elementor(?:-pro)?(?:-one)?\/v\d+(?:\/[\w{}-]+)*)/g)) bump(inv.rest, m[1].split('/').slice(0, 2).join('/'));
  for (const m of s.matchAll(/(?:^|[^\w./-])((?:elementor|elementor-pro)\/[a-z0-9_-]+(?:\/[\w:-]+)*)/g)) {
    if (!/\/v\d+(\/|$)/.test(m[1])) bump(inv.eventos, m[1]);
  }
  for (const m of s.matchAll(/(?:^|[^\w/-])(elementor_[a-z0-9_]+)/g)) bump(inv.ajax, m[1]);
  for (const m of s.matchAll(/\/(?:plugins\/[\w-]+\/)?(?:elementor|uploads\/elementor)\/[\w/.-]*/g)) bump(inv.rutas, m[0]);
  for (const m of s.matchAll(/(?:^|[\s.#"'(>~+,:\[\]])((?:elementor|e)?-?elementor-[a-z0-9_-]+|elementor)(?=$|[\s.#"'):\[\],>~+{])/g)) {
    const t = m[1];
    if (t === 'elementor') { if (/^(elementor|elementor-pro)$/.test(s.trim())) bump(inv.dominios, s.trim()); else bump(inv.clases, 'elementor'); continue; }
    if (!t.startsWith('data-')) bump(inv.clases, t);
  }
  if (/\bElementor\b/.test(s) && /\s/.test(s)) bump(inv.textos, s.slice(0, 80));
}

for (const f of frontFiles()) {
  const g = group(f);
  inv.archivos[g]++;
  const src = readFileSync(f, 'utf8');
  const count = (src.match(/elementor/gi) || []).length;
  if (!count) continue;
  inv.con_elementor++;
  inv.apariciones += count;
  inv.por_archivo.push([relative(EL, f), count]);
  if (f.endsWith('.css')) {
    const css = src.replace(/\/\*[\s\S]*?\*\//g, (c) => { if (/elementor/i.test(c)) bump(inv.css_otros, 'comentario'); return ''; });
    for (const m of css.matchAll(/\.((?:e-)?elementor[a-zA-Z0-9_-]*)/g)) bump(inv.clases, m[1]);
    for (const m of css.matchAll(/\[(data-elementor-[a-z0-9-]+)/g)) bump(inv.atributos, m[1]);
    for (const m of css.matchAll(/url\(\s*["']?([^)"']*elementor[^)"']*)/gi)) bump(inv.rutas, m[1]);
    for (const m of css.matchAll(/@(?:-webkit-)?keyframes\s+([\w-]*elementor[\w-]*)/gi)) bump(inv.css_otros, 'keyframes:' + m[1]);
    for (const m of css.matchAll(/(--[\w-]*elementor[\w-]*)/gi)) bump(inv.css_otros, 'variable:' + m[1]);
    for (const m of css.matchAll(/#(elementor[\w-]*)/g)) bump(inv.css_otros, 'id:' + m[1]);
    continue;
  }
  const { code, strings } = lexJs(src);
  for (const s of strings) classifyString(s, f);
  for (const m of code.matchAll(/(\.?)(\bdataset\.)?\b([A-Za-z_$][\w$]*[Ee]lementor[\w$]*|[Ee]lementor[\w$]*)\b/g)) {
    const [, dot, ds, id] = m;
    if (ds) bump(inv.dataset, id);
    else if (dot) bump(inv.propiedades, id);
    else bump(inv.globales, id);
  }
}

const sorted = (m) => Object.fromEntries([...m.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0])));
const summary = {
  archivos_del_front: inv.archivos,
  archivos_con_elementor: inv.con_elementor,
  apariciones: inv.apariciones,
  clases_distintas: inv.clases.size,
  atributos: sorted(inv.atributos),
  dataset: sorted(inv.dataset),
  globales: sorted(inv.globales),
  propiedades: sorted(inv.propiedades),
  eventos: sorted(inv.eventos),
  rest: sorted(inv.rest),
  ajax: sorted(inv.ajax),
  acciones_url: sorted(inv.acciones_url),
  rutas: sorted(inv.rutas),
  dominios: sorted(inv.dominios),
  css_otros: sorted(inv.css_otros),
  textos: sorted(inv.textos),
};

// Huella de lo leído: si cambian los bundles (versión nueva del núcleo o de PRO), cambia y la prueba pide repasar.
const hash = createHash('sha256');
for (const [file] of inv.por_archivo.sort()) hash.update(file + '\0' + readFileSync(join(EL, file)) + '\0');
const fingerprint = hash.digest('hex');

if (args.has('--json')) { console.log(JSON.stringify({ ...summary, clases: sorted(inv.clases), por_archivo: inv.por_archivo }, null, 1)); process.exit(0); }

// ---------------------------------------------------------------------------------------------------------------------
// Reglas: lo derivado (listas cerradas) más lo escrito a mano (prefijos, rutas, valores, fragmentos y excepciones).
const camel = (s) => s.replace(/-([a-z])/g, (_, c) => c.toUpperCase());
const renameId = (id) => {
  if (id === 'ElementorProFrontendConfig') return 'digitalisimoProFrontendConfig';
  return id.replace(/Elementor/g, 'Digitalisimo').replace(/elementor/g, 'digitalisimo').replace(/^Digitalisimo/, 'digitalisimo');
};
const derived = {
  huella: fingerprint,
  motor: JSON.parse(readFileSync(join(EL, 'elementor/digitalisimo-core.json'), 'utf8')).version,
  archivos_del_front: inv.archivos,
  archivos_con_elementor: inv.con_elementor,
  apariciones: inv.apariciones,
  clases_distintas: inv.clases.size,
};
const rules = existsSync(RULES) ? JSON.parse(readFileSync(RULES, 'utf8')) : {};
const want = {
  atributos: [...inv.atributos.keys()].sort(),
  dataset: Object.fromEntries([...inv.dataset.keys()].sort().map((k) => [k, k.replace(/^elementor/, 'dg').replace(/Elementor/g, 'Dg')])),
  js_globales: Object.fromEntries([...inv.globales.keys()].sort().map((k) => [k, renameId(k)])),
  js_propiedades: Object.fromEntries([...inv.propiedades.keys()].sort().map((k) => [k, renameId(k)])),
  js_eventos: [...inv.eventos.keys()].sort(),
  rest_del_front: [...inv.rest.keys()].sort(),
  ajax_del_front: [...inv.ajax.keys()].sort(),
};

if (args.has('--escribir')) {
  rules.derivado = derived;
  rules.atributos = { ...(rules.atributos || {}), lista: want.atributos };
  rules.js = { ...(rules.js || {}), dataset: want.dataset, globales: want.js_globales, propiedades: want.js_propiedades, eventos: want.js_eventos };
  rules.rest = { ...(rules.rest || {}), usados_por_el_front: want.rest_del_front };
  rules.ajax = { ...(rules.ajax || {}), usados_por_el_front: want.ajax_del_front };
  writeFileSync(RULES, JSON.stringify(rules, null, '\t') + '\n');
  console.log(`reglas.json escrito (huella ${fingerprint.slice(0, 12)}).`);
  process.exit(0);
}

console.log(`Front: ${inv.archivos.motor} archivos del núcleo, ${inv.archivos.pro} de PRO, ${inv.archivos.propio} propios; ${inv.con_elementor} con «elementor» (${inv.apariciones} apariciones, ${inv.clases.size} clases distintas).`);
for (const k of ['atributos', 'dataset', 'globales', 'propiedades', 'eventos', 'rest', 'ajax', 'acciones_url', 'dominios', 'css_otros']) {
  const m = summary[k];
  console.log(`  ${k}: ${Object.keys(m).length} · ${Object.entries(m).slice(0, 8).map(([a, b]) => `${a}(${b})`).join(' ')}`);
}

// Comprobación: todo lo hallado tiene regla.
const fail = [];
if (!rules.derivado) fail.push('reglas.json no existe o no tiene «derivado»: node scripts/marca-reglas.mjs --escribir');
else if (rules.derivado.huella !== fingerprint) fail.push(`los bundles cambiaron desde la derivación (huella ${String(rules.derivado.huella).slice(0, 12)} → ${fingerprint.slice(0, 12)}): repasar y regenerar con --escribir`);
const missing = (what, list, have) => { for (const x of list) if (!have(x)) fail.push(`${what} sin regla: ${x}`); };
const attrPrefix = rules.atributos?.de || 'data-elementor-';
missing('atributo', want.atributos, (a) => a.startsWith(attrPrefix));
missing('dataset', Object.keys(want.dataset), (k) => rules.js?.dataset?.[k]);
missing('global JS', Object.keys(want.js_globales), (k) => rules.js?.globales?.[k]);
missing('propiedad JS', Object.keys(want.js_propiedades), (k) => rules.js?.propiedades?.[k]);
missing('evento JS', want.js_eventos, (e) => (rules.js?.eventos || []).includes(e));
missing('ruta REST del front', want.rest_del_front, (r) => (rules.rest?.usados_por_el_front || []).includes(r) && (rules.rest?.alias || {})[r]);
missing('acción AJAX del front', want.ajax_del_front, (a) => (rules.ajax?.usados_por_el_front || []).includes(a));
missing('acción de URL', Object.keys(summary.acciones_url), (a) => (rules.acciones_url || []).some((r) => a.startsWith(r.de.replace(/:$/, ''))));
for (const [id, to] of Object.entries(rules.js?.globales || {})) if (/elementor/i.test(to)) fail.push(`regla de global JS que deja «elementor»: ${id} → ${to}`);
for (const r of rules.nunca_tocar || []) if (!/^(src|srcset|href|texto|poster|action)/.test(r)) fail.push(`regla «nunca_tocar» desconocida: ${r}`);
if (fail.length) { for (const f of fail) console.log('FALLA ' + f); process.exit(1); }
console.log(`reglas.json cubre todo lo hallado en los bundles (huella ${fingerprint.slice(0, 12)}).`);
