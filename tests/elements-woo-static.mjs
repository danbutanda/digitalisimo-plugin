// Revisión estática de la etapa F4-Woo (WooCommerce visual en DIGITALÍSIMO Elements): modules/digitalisimo-woo/, sus
// hojas y scripts de assets/ y la guarda hacia delante de modules/digitalisimo-widgets/.
// - Ningún dominio de tests/playground/brand-audit/vendors.json, tampoco en comentarios, y ninguna frase visible que
//   nombre el constructor del proveedor, los orígenes ni las extensiones de WooCommerce de terceros.
// - Controles de estilo añadidos a widgets existentes (Atc_Styles y Mini_Cart_Extras) sin `default` (V.12.1): el
//   núcleo guarda el `default` de todos los controles y emitiría CSS en cada instancia existente.
// - Ningún `<script>` ejecutable en el marcado (sí `type="text/template"`) y la configuración en línea sólo con
//   wp_add_inline_script() (V.12.3); ningún wp_localize_script() propio.
// - `rel="nofollow"` en los enlaces de filtro y de muestras (V.13); F4-Woo no imprime metas, canonical ni JSON-LD.
// - Lo que no se hará (V.18): ninguna clase `Automattic\WooCommerce\Internal\…`, ningún gancho en
//   `woocommerce_enable_post_clause_filtering`, ninguna escritura del tipo de un atributo (wc_update_attribute()).
// - Sin «venta falsa»: ningún aviso de compras o visitas ajenas.
// - Scripts con defer o sólo en el editor; topes de 8 KB por script y 4 KB por hoja; ningún url() salvo data:.
// - DIGITALISIMO_CHANGES.md anota los cambios de los derivados de loop-filter (V.8.2) y la línea de pro-elements.php.
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = join(ROOT, 'digitalisimo-elements');
const WOO = join(BASE, 'modules/digitalisimo-woo');
const ASSETS = ['wishlist', 'quick-view', 'swatches', 'swatches-admin', 'cart-extras', 'filters', 'filters-form', 'filters-editor'];

assert.ok(existsSync(join(WOO, 'class-module.php')), 'Existe modules/digitalisimo-woo/class-module.php.');
const files = [];
const walk = (dir) => {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path);
    else if (/\.(php|js|css|json|svg)$/.test(name)) files.push(path);
  }
};
walk(WOO);
for (const name of ASSETS) for (const ext of ['js', 'css']) { const path = join(BASE, 'assets', ext, `${name}.${ext}`); if (existsSync(path)) files.push(path); }
files.push(join(BASE, 'modules/digitalisimo-widgets/class-unknown-guard.php'));
for (const path of files) assert.ok(existsSync(path), `Existe ${relative(ROOT, path)}.`);
const php = files.filter((p) => p.endsWith('.php'));
const read = (path) => readFileSync(path, 'utf8');
const rel = (path) => relative(ROOT, path);

// Cadenas entre comillas (y plantillas de JS), fuera de los comentarios.
const literals = (source, js) => {
  const out = [];
  let i = 0;
  while (i < source.length) {
    const c = source[i];
    const next = source[i + 1];
    if (c === '/' && next === '/') { const end = source.indexOf('\n', i); i = end < 0 ? source.length : end; continue; }
    if (c === '#' && !js && source[i - 1] !== '\\') { const end = source.indexOf('\n', i); i = end < 0 ? source.length : end; continue; }
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
// El código sin comentarios (las cadenas se conservan: un `//` dentro de una cadena no es un comentario).
const code = (source) => {
  let out = '';
  let i = 0;
  while (i < source.length) {
    const c = source[i];
    const next = source[i + 1];
    if (c === '/' && next === '/') { const end = source.indexOf('\n', i); i = end < 0 ? source.length : end; continue; }
    if (c === '/' && next === '*') { const end = source.indexOf('*/', i + 2); i = end < 0 ? source.length : end + 2; continue; }
    if (c === "'" || c === '"') {
      let j = i + 1;
      while (j < source.length && source[j] !== c) j += source[j] === '\\' ? 2 : 1;
      out += source.slice(i, j + 1);
      i = j + 1;
      continue;
    }
    out += c;
    i++;
  }
  return out;
};

// 1. Proveedores y orígenes.
const vendors = JSON.parse(read(join(ROOT, 'tests/playground/brand-audit/vendors.json')));
const hosts = [...vendors.hosts, ...(vendors.future || [])];
const hostRe = new RegExp(`(?<![a-z0-9-])(?:[a-z0-9-]+\\.)*(?:${hosts.map((h) => h.replace(/\./g, '\\.')).join('|')})(?![a-z0-9-])`, 'i');
const brandRe = /\b(Elementor|Divi|Betheme|BeBuilder|Bricks|Elegant Themes|Muffin|YITH|TI WooCommerce|Element Pack)\b/;
let strings = 0;
for (const path of files) {
  const source = read(path);
  assert.ok(!hostRe.test(source), `${rel(path)} no nombra dominios de los proveedores (${source.match(hostRe)?.[0]}).`);
  if (!/\.(php|js)$/.test(path)) continue;
  for (const literal of literals(source, path.endsWith('.js'))) {
    if (!/\s/.test(literal.slice(1, -1))) continue;
    strings++;
    assert.ok(!brandRe.test(literal), `${rel(path)}: una frase visible nombra un producto ajeno: ${literal.slice(0, 120)}`);
  }
}
assert.ok(strings > 200, `Se revisaron las frases de los archivos (${strings}).`);

// 2. Controles de estilo en widgets existentes, sin `default` (V.12.1).
const calls = (source, names) => {
  const out = [];
  const re = new RegExp(`->(${names.join('|')})\\s*\\(`, 'g');
  for (const m of source.matchAll(re)) {
    let depth = 0;
    let j = m.index + m[0].length - 1;
    for (; j < source.length; j++) {
      const c = source[j];
      if (c === "'" || c === '"') { let k = j + 1; while (k < source.length && source[k] !== c) k += source[k] === '\\' ? 2 : 1; j = k; continue; }
      if (c === '(') depth++;
      else if (c === ')' && --depth === 0) break;
    }
    out.push({ kind: m[1], args: source.slice(m.index, j + 1) });
  }
  return out;
};
let styleControls = 0;
for (const name of ['swatches/class-atc-styles.php', 'cart/class-mini-cart-extras.php']) {
  const path = join(WOO, name);
  const found = calls(code(read(path)), ['add_control', 'add_responsive_control', 'add_group_control']);
  assert.ok(found.length >= 5, `${name}: se leyeron sus controles (${found.length}).`);
  for (const { kind, args } of found) {
    const style = kind === 'add_group_control' || /'selectors?'\s*=>/.test(args);
    if (!style) continue;
    styleControls++;
    assert.ok(!/'default'\s*=>/.test(args), `${name}: control de estilo con default en un widget existente: ${args.slice(0, 140)}`);
  }
}
assert.ok(styleControls >= 8, `Se revisaron los controles de estilo añadidos a widgets existentes (${styleControls}).`);

// 3. Marcado sin <script> ejecutable; configuración en línea sólo con wp_add_inline_script().
for (const path of php) {
  const source = read(path);
  for (const literal of literals(source, false)) {
    if (!/<script/i.test(literal)) continue;
    assert.ok(/<script[^>]*type=\\?["']text\/template/i.test(literal), `${rel(path)}: <script> ejecutable en el marcado: ${literal.slice(0, 120)}`);
  }
  assert.ok(!/\bwp_localize_script\s*\(/.test(code(source)), `${rel(path)}: configuración con wp_localize_script() (usar wp_add_inline_script()).`);
}
const inline = php.filter((p) => /\bwp_add_inline_script\s*\(/.test(code(read(p))));
assert.ok(inline.length >= 2, `La configuración del pie va con wp_add_inline_script() (${inline.map(rel).join(', ')}).`);

// 4. SEO: nofollow en los enlaces de filtro y de muestras; ni metas, ni canonical, ni JSON-LD.
let anchors = 0;
for (const path of [...readdirSync(join(WOO, 'filters')).map((f) => join(WOO, 'filters', f)), join(WOO, 'swatches/class-loop.php')].filter((p) => p.endsWith('.php'))) {
  // El enlace se arma concatenando cadenas: se mira la sentencia entera, desde `<a ` hasta el `;` que la cierra.
  const source = code(read(path));
  for (const match of source.matchAll(/<a\s/g)) {
    const end = source.indexOf(';', match.index);
    const statement = source.slice(match.index, end < 0 ? source.length : end);
    if (!/href=/.test(statement)) continue;
    anchors++;
    assert.ok(/rel=\\?["'][^"']*nofollow/.test(statement), `${rel(path)}: enlace de filtro o de muestra sin rel="nofollow": ${statement.slice(0, 160)}`);
  }
}
assert.ok(anchors >= 5, `Se revisaron los enlaces de filtros y muestras (${anchors}).`);
for (const path of php) {
  const source = code(read(path));
  assert.ok(!/application\/ld\+json|rel=\\?["']canonical|<meta\s/i.test(source), `${rel(path)}: F4-Woo no imprime metas, canonical ni JSON-LD.`);
}

// 5. Lo que no se hará (V.18).
for (const path of files.filter((p) => /\.(php|js)$/.test(p))) {
  const source = code(read(path));
  assert.ok(!/Automattic\\{1,2}WooCommerce\\{1,2}Internal\\{1,2}/.test(source), `${rel(path)}: llama a una clase interna de WooCommerce.`);
  assert.ok(!/woocommerce_enable_post_clause_filtering/.test(source), `${rel(path)}: engancha woocommerce_enable_post_clause_filtering.`);
  assert.ok(!/\bwc_update_attribute\s*\(/.test(source), `${rel(path)}: escribe el tipo de un atributo de WooCommerce.`);
}

// 6. Sin «venta falsa».
const fakeRe = /(acaba de comprar|compró hace|personas? (están|está) viendo|vendidos? en las últimas|just (bought|purchased)|people are (viewing|watching)|recent(ly)? (sold|purchase))/i;
for (const path of files.filter((p) => /\.(php|js)$/.test(p))) {
  for (const literal of literals(read(path), path.endsWith('.js'))) assert.ok(!fakeRe.test(literal), `${rel(path)}: aviso de «venta falsa»: ${literal.slice(0, 120)}`);
}

// 7. Scripts con defer (o sólo en el editor), topes y url() sólo data:.
for (const path of php) {
  const source = code(read(path));
  const registered = source.match(/wp_(register|enqueue)_script\s*\([^;]*;/g) || [];
  const editorOnly = /['"]elementor\/(editor|preview)\/[a-z_\/]+['"]/.test(source);
  for (const call of registered) {
    if (/wp_enqueue_script\s*\(\s*(\$[\w>-]+|self::\w+(\(\))?|'[^']+')\s*\)\s*;/.test(call)) continue; // encola un handle ya registrado
    assert.ok(/'strategy'\s*=>\s*'defer'/.test(call) || editorOnly, `${rel(path)}: script sin defer fuera del editor: ${call.slice(0, 120)}`);
  }
}
for (const name of ASSETS) {
  for (const [ext, max] of [['js', 8192], ['css', 4096]]) {
    const path = join(BASE, 'assets', ext, `${name}.${ext}`);
    if (!existsSync(path)) continue;
    const bytes = statSync(path).size;
    assert.ok(bytes <= max, `assets/${ext}/${name}.${ext}: ${bytes} B (tope ${max} B).`);
  }
}
const urlOk = (target) => /^\s*(['"]|\\['"])?data:/.test(target);
for (const path of files.filter((p) => /\.(css|php|js)$/.test(p))) {
  const source = read(path);
  const chunks = path.endsWith('.css') ? [source] : literals(source, path.endsWith('.js')).map((l) => l.slice(1, -1));
  for (const chunk of chunks) {
    for (const match of chunk.matchAll(/(?<![A-Za-z0-9_-])url\(/g)) {
      const rest = chunk.slice(match.index + 4);
      if (rest === '' && !path.endsWith('.css')) continue;
      assert.ok(urlOk(rest), `${rel(path)}: url() que no es data: (${chunk.slice(match.index, match.index + 60)}).`);
    }
  }
}

// 8. Derivados de PRO tocados, anotados.
const changes = read(join(BASE, 'DIGITALISIMO_CHANGES.md'));
for (const file of ['modules/loop-filter/module.php', 'modules/loop-filter/query/query-types/hierarchy-or-query.php']) {
  assert.ok(changes.includes(file), `DIGITALISIMO_CHANGES.md anota el cambio de ${file} (V.8.2).`);
}
assert.ok(/digitalisimo-woo/.test(changes), 'DIGITALISIMO_CHANGES.md anota la línea de pro-elements.php que arranca modules/digitalisimo-woo/.');

console.log(`DIGITALÍSIMO Elements: revisión estática de F4-Woo en verde (${files.length} archivos, ${strings} frases, ${styleControls} controles de estilo sin default, ${anchors} enlaces con nofollow).`);
