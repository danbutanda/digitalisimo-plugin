#!/usr/bin/env node
// Sincroniza Prism en DIGITALÍSIMO Elements (digitalisimo-elements/assets/lib/prism/), que el widget de resaltado de
// código pedía a un CDN. Uso:
//
//   node scripts/sync-prism.mjs [--from carpeta] [--cdnjs]
//
// Descarga de npm los paquetes oficiales fijados abajo (o los lee de --from: <paquete>-<versión>.tgz y el JSON del
// registro <paquete>-<versión>.json), comprueba la `integrity` (sha512) de cada tarball contra la fijada aquí y contra
// la del registro, y copia sólo lo que el widget usa, con la estructura del paquete para que el autoloader resuelva las
// gramáticas desde su propio `src`:
//
// - de prismjs: el core, el autoloader, los cinco plugins que registra modules/code-highlight/module.php y, en
//   components/, la gramática de cada valor del control `language` del widget (con los alias del autoloader) más el
//   cierre de sus `require` según components.json de la misma versión, contrastado con el mapa del autoloader;
// - de clipboard: `dist/clipboard.min.js`, porque el plugin copy-to-clipboard de Prism 1.23.0 lo pide a un CDN si
//   `window.ClipboardJS` no existe. Servido antes que ese plugin, ya no sale ninguna petición.
//
// Sin CSS (va en la hoja del widget) ni ningún otro archivo. Escribe MANIFEST.json con versiones, integridad y el
// sha256 de cada archivo. Con --cdnjs compara una vez las sumas SRI que publica cdnjs para esas versiones (o las lee de
// --from: cdnjs-<biblioteca>-<versión>.json) y lo anota; sin él, conserva la comparación anterior si nada cambió.
//
// No ejecuta nada de los paquetes: cada tarball se lee en memoria y components.json y los mapas del autoloader se leen
// como datos. Sólo reemplaza una carpeta creada por este script (con su MANIFEST.json).
import { createHash } from 'node:crypto';
import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { gunzipSync } from 'node:zlib';

const PRISM = { name: 'Prism', package: 'prismjs', version: '1.23.0', integrity: 'sha512-c29LVsqOaLbBHuIbsTxaKENh1N2EQBOHaWv7gkHN4dgRbxSREqDnDbtFJYdpPauS4YCplMSNCABQ6Eeor69bAA==', cdnjs: 'prism' };
const CLIPBOARD = { name: 'clipboard.js', package: 'clipboard', version: '2.0.0', integrity: 'sha512-gXzHBlzEVqCk2b8Wpkil89S0WSMAX7eZho2zANX+EEEa9LMutGe9ICU+wHRzsH7cCHaCbUzj900P+AXOM0FE3A==', cdnjs: 'clipboard.js', file: 'dist/clipboard.min.js', target: 'clipboard/clipboard.min.js', cdnjsPath: 'clipboard.min.js' };
// El paquete clipboard 2.0.0 declara MIT en package.json y en la cabecera de clipboard.min.js, pero no trae LICENSE:
// éste es el texto del repositorio del proyecto.
const CLIPBOARD_LICENSE = `MIT License

Copyright (c) Zeno Rocha

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
`;

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const ELEMENTS = join(ROOT, 'digitalisimo-elements');
const TARGET = join(ELEMENTS, 'assets/lib/prism');
const MODULE = join(ELEMENTS, 'modules/code-highlight/module.php');
const WIDGET = join(ELEMENTS, 'modules/code-highlight/widgets/code-highlight.php');
// La misma guarda que scripts/build-packages.sh aplica al ZIP de Elements: un archivo que la dispare no se distribuye.
const GUARD = /bdthemes|element[ _-]?pack|elementpack|prime[ _-]?slider|primeslider|(^|[^a-z0-9_-])bdt[-_][a-z]|(^|[^a-z0-9_])ep_[a-z]/i;

const fail = (message) => { console.error(`sync-prism: ${message}`); process.exit(1); };
const sha256 = (buffer) => createHash('sha256').update(buffer).digest('hex');
const sri = (buffer) => `sha512-${createHash('sha512').update(buffer).digest('base64')}`;
const args = process.argv.slice(2);
const from = args.includes('--from') ? args[args.indexOf('--from') + 1] : null;
if (args.includes('--from') && !from) fail('--from necesita una carpeta');

const download = async (url, json) => {
  const response = await fetch(url);
  if (!response.ok) fail(`${url} respondió ${response.status}`);
  return json ? response.json() : Buffer.from(await response.arrayBuffer());
};
const input = (name, url, json) => (from ? (json ? JSON.parse(readFileSync(join(from, name), 'utf8')) : readFileSync(join(from, name))) : download(url, json));

/** Entradas de un tar (ustar, con cabeceras pax y nombres largos de GNU), en memoria: ruta → contenido. */
const untar = (buffer) => {
  const entries = new Map();
  const text = (block, start, length) => block.subarray(start, start + length).toString('utf8').replace(/\0.*$/s, '');
  let pending = null;
  for (let offset = 0; offset + 512 <= buffer.length;) {
    const header = buffer.subarray(offset, offset + 512);
    if (header.every((byte) => byte === 0)) break;
    const size = parseInt(text(header, 124, 12).trim() || '0', 8);
    const type = String.fromCharCode(header[156] || 48);
    const prefix = text(header, 345, 155);
    let name = (prefix ? `${prefix}/` : '') + text(header, 0, 100);
    const data = buffer.subarray(offset + 512, offset + 512 + size);
    offset += 512 + Math.ceil(size / 512) * 512;
    if (type === 'x') { const m = /\d+ path=([^\n]*)\n/.exec(data.toString('utf8')); pending = m ? m[1] : null; continue; }
    if (type === 'L') { pending = data.toString('utf8').replace(/\0.*$/s, ''); continue; }
    if (type === 'g') continue;
    if (pending) { name = pending; pending = null; }
    if (type === '0' || type === '\0') entries.set(name.replace(/^\.\//, ''), Buffer.from(data));
  }
  return entries;
};

/** Paquete oficial de npm verificado: lector de sus archivos por ruta relativa a package/. */
const fetchPackage = async (spec) => {
  const base = `${spec.package}-${spec.version}`;
  const tarball = `https://registry.npmjs.org/${spec.package}/-/${base}.tgz`;
  const metadata = await input(`${base}.json`, `https://registry.npmjs.org/${spec.package}/${spec.version}`, true);
  if (metadata.version !== spec.version) fail(`el registro describe ${spec.package} ${metadata.version}, no ${spec.version}`);
  if (metadata.dist?.integrity !== spec.integrity) fail(`la integridad de ${base} en el registro (${metadata.dist?.integrity}) no es la fijada`);
  if (metadata.dist?.tarball !== tarball) fail(`el registro sirve ${base} desde ${metadata.dist?.tarball}`);
  const archive = await input(`${base}.tgz`, tarball);
  if (sri(archive) !== spec.integrity) fail(`el tarball de ${base} no cuadra con ${spec.integrity}`);
  const files = untar(gunzipSync(archive));
  const read = (path) => files.get(`package/${path}`) ?? fail(`${base} no contiene ${path}`);
  const pkg = JSON.parse(read('package.json').toString('utf8'));
  if (pkg.name !== spec.package || pkg.version !== spec.version || pkg.license !== 'MIT') fail(`package.json inesperado: ${pkg.name} ${pkg.version} ${pkg.license}`);
  return { ...spec, source: tarball, read };
};

/** Objeto JSON entre los marcadores que el autoloader deja en su fuente sin minificar. */
const placeholder = (source, marker) => {
  const m = new RegExp(`/\\*${marker}_placeholder\\[\\*/([\\s\\S]*?)/\\*\\]\\*/`).exec(source);
  if (!m) fail(`el autoloader no trae el mapa ${marker}`);
  return JSON.parse(m[1]);
};
const list = (value) => (value === undefined ? [] : Array.isArray(value) ? value : [value]);

// 1. Paquetes oficiales verificados.
const prism = await fetchPackage(PRISM);
const clipboard = await fetchPackage(CLIPBOARD);
const clipboardFile = clipboard.read(CLIPBOARD.file);
if (!clipboardFile.toString('utf8').includes('Licensed MIT © Zeno Rocha')) fail(`${CLIPBOARD.file} no trae su aviso de licencia`);

// 2. Lo que pide el widget: los handles de module.php y los valores del control `language`.
const handles = {};
for (const m of readFileSync(MODULE, 'utf8').matchAll(/wp_register_script\(\s*'([a-z_]+)',\s*\$base_url\s*\.\s*'\/([^']+)'/g)) handles[m[1]] = m[2];
const control = /\$language_option\s*=\s*\[([\s\S]*?)\];/.exec(readFileSync(WIDGET, 'utf8'));
if (!control) fail('no se encontró $language_option en el widget');
const values = [...control[1].matchAll(/'([^']+)'\s*=>/g)].map((m) => m[1]);
if (!values.length) fail('el control language no tiene valores');

// 3. Gramáticas: alias del autoloader y cierre de `require` de components.json, contrastado con el mapa del autoloader.
const components = JSON.parse(prism.read('components.json').toString('utf8')).languages;
const autoloader = prism.read('plugins/autoloader/prism-autoloader.js').toString('utf8');
const dependencies = placeholder(autoloader, 'dependencies');
const aliases = placeholder(autoloader, 'aliases');
const closure = (lang, out = []) => {
  if (out.includes(lang)) return out;
  if (!components[lang] || lang === 'meta') fail(`components.json no declara la gramática ${lang}`);
  const required = list(components[lang].require);
  const loader = list(dependencies[lang]);
  if (required.slice().sort().join() !== loader.slice().sort().join()) fail(`${lang}: components.json pide [${required}] y el autoloader [${loader}]`);
  for (const dep of required) closure(dep, out);
  out.push(lang);
  return out;
};
const languages = {};
for (const value of values) {
  // Prism toma el idioma de la clase `language-<valor>` con /\blang(?:uage)?-([\w-]+)\b/i (así «visual-basic,» es
  // visual-basic) y el autoloader resuelve sus alias antes de pedir el archivo.
  const m = /^[\w-]+/.exec(value);
  if (!m) fail(`valor de language sin nombre de gramática: ${value}`);
  const name = m[0].toLowerCase();
  languages[value] = closure(aliases[name] || name);
}

// 4. Subconjunto (ruta publicada → contenido), sin lo que dispararía la guarda del empaquetado.
const wanted = new Map();
const kinds = new Map();
for (const [handle, path] of Object.entries(handles)) {
  if (path === CLIPBOARD.target) continue;
  wanted.set(path, prism.read(path));
  kinds.set(path, `handle ${handle}`);
}
for (const grammar of new Set(Object.values(languages).flat())) {
  const path = `components/prism-${grammar}.min.js`;
  if (!wanted.has(path)) { wanted.set(path, prism.read(path)); kinds.set(path, 'grammar'); }
}
wanted.set(CLIPBOARD.target, clipboardFile);
kinds.set(CLIPBOARD.target, 'clipboard');
const excluded = [];
for (const [path, content] of wanted) {
  if (!/^[a-z0-9/._-]+$/.test(path)) fail(`ruta inesperada ${path}`);
  if (GUARD.test(content.toString('utf8'))) {
    if (kinds.get(path) !== 'grammar') fail(`${path} dispara la guarda del empaquetado y no es una gramática`);
    excluded.push({ file: path, reason: 'dispara la guarda de scripts/build-packages.sh' });
    wanted.delete(path);
  }
}
for (const value of Object.keys(languages)) languages[value] = languages[value].filter((g) => wanted.has(`components/prism-${g}.min.js`));
const missing = Object.entries(handles).filter(([, path]) => !wanted.has(path));
if (missing.length) fail(`module.php registra rutas que no se copian: ${missing.map(([h, p]) => `${h} → ${p}`).join(', ')}`);
for (const handle of ['prismjs_core', 'prismjs_loader', 'prismjs_normalize', 'prismjs_line_numbers', 'prismjs_line_highlight', 'prismjs_toolbar', 'prismjs_copy_to_clipboard']) {
  if (!handles[handle]) fail(`module.php no registra ${handle}`);
}
if (!Object.values(handles).includes(CLIPBOARD.target)) fail(`module.php no registra ${CLIPBOARD.target} antes de copy-to-clipboard`);
const paths = [...wanted.keys()].sort();

// 5. Comparación con las sumas que publica cdnjs (una vez, con --cdnjs).
const manifestPath = join(TARGET, 'MANIFEST.json');
const previous = existsSync(manifestPath) ? JSON.parse(readFileSync(manifestPath, 'utf8')) : null;
if (existsSync(TARGET) && !previous) fail(`${TARGET} existe y no la creó este script: no se toca`);
const record = {};
const licenses = { LICENSE: prism.read('LICENSE'), 'clipboard/LICENSE': Buffer.from(CLIPBOARD_LICENSE) };
for (const [path, content] of [...Object.entries(licenses), ...wanted]) record[path] = { sha256: sha256(content), bytes: content.length };
let cdnjs = null;
if (args.includes('--cdnjs')) {
  const published = async (spec) => (await input(`cdnjs-${spec.cdnjs}-${spec.version}.json`, `https://api.cdnjs.com/libraries/${spec.cdnjs}/${spec.version}?fields=sri`, true)).sri || {};
  const prismSri = await published(PRISM);
  const clipboardSri = await published(CLIPBOARD);
  const different = paths.filter((path) => (path === CLIPBOARD.target ? clipboardSri[CLIPBOARD.cdnjsPath] : prismSri[path]) !== sri(wanted.get(path)));
  if (different.length) fail(`cdnjs publica otras sumas para: ${different.join(', ')}`);
  cdnjs = {
    checked: new Date().toISOString().slice(0, 10),
    sources: [PRISM, CLIPBOARD].map((spec) => `api.cdnjs.com/libraries/${spec.cdnjs}/${spec.version}?fields=sri`),
    files: paths.length,
    identical: paths.length,
  };
} else if (previous?.cdnjs && previous.cdnjs.files === paths.length && paths.every((path) => previous.files?.[path]?.sha256 === record[path].sha256)) {
  cdnjs = previous.cdnjs;
}

// 6. Copia y manifiesto.
rmSync(TARGET, { recursive: true, force: true });
for (const [path, content] of [...Object.entries(licenses), ...wanted]) {
  mkdirSync(dirname(join(TARGET, path)), { recursive: true });
  writeFileSync(join(TARGET, path), content);
}
const copyright = (text) => (/^Copyright.*$/m.exec(text) || [''])[0];
const manifest = {
  name: PRISM.name,
  package: PRISM.package,
  version: PRISM.version,
  source: prism.source,
  integrity: PRISM.integrity,
  license: 'MIT',
  copyright: copyright(licenses.LICENSE.toString('utf8')),
  synced: new Date().toISOString().slice(0, 10),
  generator: 'scripts/sync-prism.mjs',
  handles,
  languages,
  excluded,
  companions: {
    clipboard: {
      name: CLIPBOARD.name,
      package: CLIPBOARD.package,
      version: CLIPBOARD.version,
      source: clipboard.source,
      integrity: CLIPBOARD.integrity,
      license: 'MIT',
      copyright: copyright(CLIPBOARD_LICENSE),
      files: [CLIPBOARD.target, 'clipboard/LICENSE'],
      reason: 'El plugin copy-to-clipboard de Prism lo pide a un CDN si window.ClipboardJS no existe.',
    },
  },
  files: Object.fromEntries(Object.keys(record).sort().map((path) => [path, record[path]])),
  ...(cdnjs ? { cdnjs } : {}),
};
writeFileSync(manifestPath, `${JSON.stringify(manifest, null, 2)}\n`);
const bytes = paths.reduce((total, path) => total + record[path].bytes, 0);
console.log(`Prism ${PRISM.version} y clipboard.js ${CLIPBOARD.version} en digitalisimo-elements/assets/lib/prism: ${paths.length} archivos (${bytes} B), ${new Set(Object.values(languages).flat()).size} gramáticas para ${values.length} valores del control${excluded.length ? `, ${excluded.length} excluidos` : ''}${cdnjs ? `; idénticos a cdnjs (${cdnjs.checked})` : ''}.`);
