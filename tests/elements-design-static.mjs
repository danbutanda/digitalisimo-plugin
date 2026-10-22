// Revisión estática de la etapa F7a (capacidades de Divi y Betheme en DIGITALÍSIMO Elements): el módulo
// modules/digitalisimo-design/ y los archivos nuevos de modules/digitalisimo-conditions/.
// - Ningún texto propio nombra el constructor del proveedor ni los productos de origen (Divi, Betheme, Bricks): se
//   revisan las cadenas entre comillas con un espacio (frases visibles), no los identificadores ni los ganchos.
// - Ningún dominio de tests/playground/brand-audit/vendors.json (hosts y futuros), tampoco en comentarios.
// - Todo script se registra con defer o sólo en el editor del constructor (ganchos elementor/editor/* o
//   elementor/preview/*); los dos scripts del editor no pasan de 8 KB y la hoja del sticky, de 0,5 KB.
// - Ningún CSS propio lleva url() salvo data: (máscaras y teselas incrustadas).
// - DIGITALISIMO_CHANGES.md anota la única línea nueva en un derivado de PRO (conditions-manager.php).
// - plugin.php y los CSS compilados que llegaron con PRO no cambian (tests/sources/design/pro-pinned.json); los
//   cuatro paquetes JS de PRO los vigila tests/elements-pro-inventory.mjs.
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import assert from 'node:assert/strict';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = join(ROOT, 'digitalisimo-elements');
const DESIGN = join(BASE, 'modules/digitalisimo-design');
const CONDITIONS = ['class-conditions-more.php', 'class-conditions-guard.php', 'class-page-visits.php']
  .map((name) => join(BASE, 'modules/digitalisimo-conditions', name));

assert.ok(existsSync(join(DESIGN, 'class-module.php')), 'Existe modules/digitalisimo-design/class-module.php.');
const files = [];
const walk = (dir) => {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path);
    else if (/\.(php|js|css|json|svg)$/.test(name)) files.push(path);
  }
};
walk(DESIGN);
for (const path of CONDITIONS) {
  assert.ok(existsSync(path), `Existe ${relative(ROOT, path)}.`);
  files.push(path);
}

// Dominios de los proveedores: en cualquier parte del archivo, comentarios incluidos.
const vendors = JSON.parse(readFileSync(join(ROOT, 'tests/playground/brand-audit/vendors.json'), 'utf8'));
const hosts = [...vendors.hosts, ...(vendors.future || [])];
const hostRe = new RegExp(`(?<![a-z0-9-])(?:[a-z0-9-]+\\.)*(?:${hosts.map((h) => h.replace(/\./g, '\\.')).join('|')})(?![a-z0-9-])`, 'i');
// Frases visibles: cadenas entre comillas (y plantillas de JS) con al menos un espacio, fuera de los comentarios,
// que no llegan al usuario.
const literals = (source, js) => {
  const out = [];
  let i = 0;
  while (i < source.length) {
    const c = source[i];
    const next = source[i + 1];
    if (c === '/' && next === '/') { const end = source.indexOf('\n', i); i = end < 0 ? source.length : end; continue; }
    if (c === '/' && next === '*') { const end = source.indexOf('*/', i + 2); i = end < 0 ? source.length : end + 2; continue; }
    if (c === "'" || c === '"' || (js && c === '`')) {
      let j = i + 1;
      while (j < source.length && source[j] !== c) j += source[j] === '\\' ? 2 : 1;
      out.push(source.slice(i, j + 1));
      i = j + 1;
      continue;
    }
    i++;
  }
  return out;
};
const brandRe = /\b(Elementor|Divi|Betheme|BeBuilder|Bricks|Elegant Themes|Muffin)\b/;
let strings = 0;
for (const path of files) {
  const rel = relative(ROOT, path);
  const source = readFileSync(path, 'utf8');
  assert.ok(!hostRe.test(source), `${rel} no nombra dominios de los proveedores (${source.match(hostRe)?.[0]}).`);
  if (!/\.(php|js)$/.test(path)) continue;
  for (const literal of literals(source, path.endsWith('.js'))) {
    if (!/\s/.test(literal.slice(1, -1))) continue;
    strings++;
    assert.ok(!brandRe.test(literal), `${rel}: una frase visible nombra un producto de origen: ${literal.slice(0, 120)}`);
  }
}
assert.ok(strings > 50, `Se revisaron las frases de los archivos (${strings}).`);

// Scripts: con defer, o sólo en el editor del constructor.
for (const path of files.filter((p) => p.endsWith('.php'))) {
  const rel = relative(ROOT, path);
  const source = readFileSync(path, 'utf8');
  const calls = source.match(/wp_(register|enqueue)_script\s*\([^;]*;/g) || [];
  if (!calls.length) continue;
  const editorOnly = /['"]elementor\/(editor|preview)\/[a-z_\/]+['"]/.test(source);
  for (const call of calls) {
    assert.ok(/'strategy'\s*=>\s*'defer'/.test(call) || editorOnly, `${rel}: script sin defer fuera del editor: ${call.slice(0, 100)}`);
  }
  if (editorOnly) assert.ok(!/add_action\(\s*'wp_enqueue_scripts'/.test(source), `${rel}: el script del editor no se encola en el front.`);
}
for (const [path, max] of [
  ['presets/assets/presets-editor.js', 8192],
  ['backgrounds/assets/design-editor.js', 8192],
  ['sticky/assets/sticky-state.css', 512],
]) {
  const bytes = statSync(join(DESIGN, path)).size;
  assert.ok(bytes <= max, `${path}: ${bytes} B (tope ${max} B).`);
}

// CSS propio sin url() salvo data:: en las hojas, todo url(); en PHP y JS, el url() de las cadenas (fuera de los
// comentarios), salvo el que cierra la cadena, que es una lista de rechazo ('url(').
const urlOk = (target) => /^\s*(['"]|\\['"])?data:/.test(target);
for (const path of files.filter((p) => /\.(css|php|js)$/.test(p))) {
  const rel = relative(ROOT, path);
  const source = readFileSync(path, 'utf8');
  const chunks = path.endsWith('.css') ? [source] : literals(source, path.endsWith('.js')).map((l) => l.slice(1, -1));
  for (const chunk of chunks) {
    for (const match of chunk.matchAll(/(?<![A-Za-z0-9_-])url\(/g)) {
      const rest = chunk.slice(match.index + 4);
      if (rest === '' && !path.endsWith('.css')) continue;
      assert.ok(urlOk(rest), `${rel}: url() que no es data: (${chunk.slice(match.index, match.index + 60)}).`);
    }
  }
}

// La línea nueva del derivado de PRO, anotada.
const changes = readFileSync(join(BASE, 'DIGITALISIMO_CHANGES.md'), 'utf8');
assert.ok(changes.includes('modules/theme-builder/classes/conditions-manager.php') && /digitalisimo_elements_theme_builder_priorities/.test(changes),
  'DIGITALISIMO_CHANGES.md anota el filtro nuevo de conditions-manager.php.');
const manager = readFileSync(join(BASE, 'modules/theme-builder/classes/conditions-manager.php'), 'utf8');
assert.equal((manager.match(/digitalisimo_elements_theme_builder_priorities/g) || []).length, 1, 'conditions-manager.php tiene el filtro una sola vez.');

// plugin.php y los CSS compilados de PRO, sin cambios.
const pinned = JSON.parse(readFileSync(join(ROOT, 'tests/sources/design/pro-pinned.json'), 'utf8')).files;
const changed = [];
for (const [rel, sum] of Object.entries(pinned)) {
  const path = join(BASE, rel);
  if (!existsSync(path)) { changed.push(`${rel} (falta)`); continue; }
  if (createHash('sha256').update(readFileSync(path)).digest('hex') !== sum) changed.push(rel);
}
assert.ok(Object.keys(pinned).length > 200 && 'plugin.php' in pinned, 'La lista fijada cubre plugin.php y los CSS de PRO.');
assert.deepEqual(changed, [], `plugin.php y los CSS compilados de PRO no cambian: ${changed.join(', ')}`);

// Guardas (V.9): ningún símbolo comprobado es una clase que el núcleo no autocarga y declara al crear su gestor de
// elementos en `init` (`Elements_Manager::require_files()`). Las guardas corren en `plugins_loaded`, así que esa
// clase faltaría siempre y el submódulo no registraría nada en un sitio real (las unitarias declaran sus dobles antes).
const elementsManager = readFileSync(join(BASE, 'elementor/includes/managers/elements.php'), 'utf8');
const requireFiles = (elementsManager.match(/function require_files\(\)\s*\{([\s\S]*?)\n\t\}/) || [])[1] || '';
const lateClasses = [...requireFiles.matchAll(/'(includes\/[^']+\.php)'/g)]
  .flatMap(([, rel]) => [...readFileSync(join(BASE, 'elementor', rel), 'utf8').matchAll(/^(?:abstract |final )?class (\w+)/gm)].map((m) => m[1]));
assert.ok(lateClasses.includes('Element_Base') && lateClasses.includes('Repeater'), `Clases declaradas en init leídas del núcleo: ${lateClasses.join(', ')}`);
const lateInGuards = [];
for (const path of files.filter((f) => f.endsWith('.php'))) {
  const source = readFileSync(path, 'utf8');
  for (const [, list] of source.matchAll(/(?:const SYMBOLS|\$symbols)\s*=\s*array\(([\s\S]*?)\);/g)) {
    for (const [, symbol] of list.matchAll(/'([^']+)'/g)) {
      const name = symbol.replace(/\\\\/g, '\\').replace(/^\\/, '');
      const [cls] = name.split('::');
      if (lateClasses.some((late) => cls === `Elementor\\${late}`)) lateInGuards.push(`${relative(ROOT, path)}: ${name}`);
    }
  }
}
assert.deepEqual(lateInGuards, [], `Guardas con clases que el núcleo declara en init: ${lateInGuards.join(', ')}`);

console.log(`DIGITALÍSIMO Elements: revisión estática de F7a en verde (${files.length} archivos, ${strings} frases, ${Object.keys(pinned).length} archivos de PRO fijados).`);
