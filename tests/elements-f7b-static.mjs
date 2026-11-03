// Revisión estática de la etapa F7b en DIGITALÍSIMO Elements: toda la carpeta modules/digitalisimo-f7b/ (PHP, JS y
// CSS, también sus assets/) y el módulo neutro de filtros modules/digitalisimo-filters/. Genérica: no falla porque un
// archivo de otro frente aún no exista, y lo que revisa lo saca de los archivos que haya.
// 1. Ningún dominio de tests/playground/brand-audit/vendors.json (tampoco en comentarios) y ninguna frase visible que
//    nombre el constructor original ni los productos de origen.
// 2. Textos: las llamadas de traducción usan el dominio de los módulos propios ('pro-elements') y no están en inglés.
// 3. Controles (V.12): los de la lista de V.12 que no son de estilo llevan 'style_transfer' => false y la presentación,
//    true; todo control nuevo sin selectors, prefix_class ni grupo declara 'style_transfer'. En los archivos que añaden
//    controles a widgets existentes (ganchos elementor/element/…), ningún control de estilo nace con un default que
//    cambie el CSS, y un default de un control que no es de estilo va detrás de una condición. Nunca common-optimized.
// 4. Scripts del front con defer y sin jQuery, y cada recurso en su tope de V.13 y §5; ningún url() salvo data:.
// 5. Clases propias con prefijo digi- (hojas, prefix_class, class="…" y classList del front).
// 6. Guardas: ningún símbolo es una clase que el núcleo declara en init (Element_Base, Widget_Base, Repeater…) ni una
//    que herede de ellas: su autocarga en plugins_loaded lanzaría «Class not found» (resuelto con las autocargas del
//    núcleo y de PRO).
// 7. Seguridad: AJAX del editor sólo por elementor/ajax/register_actions con current_user_can(); ningún wp_ajax_nopriv_,
//    ningún SELECT DISTINCT, ningún wp_die() en el bloqueo del borrado.
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative, dirname, basename } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = join(ROOT, 'digitalisimo-elements');
const F7B = join(BASE, 'modules/digitalisimo-f7b');
const FILTERS = join(BASE, 'modules/digitalisimo-filters');
let checks = 0;
// Se reúnen todas las incidencias (varios frentes escriben a la vez) y se falla al final con la lista completa.
const failures = [];
const ok = (value, message) => { checks++; if (!value) failures.push(message); };
const read = (path) => readFileSync(path, 'utf8');
const rel = (path) => relative(ROOT, path);

ok(existsSync(join(F7B, 'class-module.php')), 'Existe modules/digitalisimo-f7b/class-module.php.');
const files = [];
const walk = (dir) => {
  if (!existsSync(dir)) return;
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path);
    else if (/\.(php|js|css|json|svg)$/.test(name)) files.push(path);
  }
};
walk(F7B);
walk(FILTERS);
// Recursos de F7b que un frente pudiera dejar en assets/ de la raíz, y los dos de F4-Woo que reutilizan los filtros.
for (const name of ['actions.js', 'actions-editor.js', 'nested-slider.js', 'nested-slider-editor.js', 'forms-conditions.js', 'inline-tags-editor.js', 'apply-all-editor.js', 'queries-editor.js', 'overrides-editor.js', 'filters-form.js', 'filters.js']) {
  const path = join(BASE, 'assets/js', name);
  if (existsSync(path)) files.push(path);
}
for (const name of ['nested-slider.css', 'nested-presentation.css', 'f7b-filters.css']) {
  const path = join(BASE, 'assets/css', name);
  if (existsSync(path)) files.push(path);
}
const php = files.filter((p) => p.endsWith('.php'));
const js = files.filter((p) => p.endsWith('.js'));
const cssFiles = files.filter((p) => p.endsWith('.css'));
ok(php.length >= 5, `Archivos PHP revisados (${php.length}).`);

// Cadenas entre comillas (y plantillas de JS), fuera de los comentarios.
const literals = (source, isJs) => {
  const out = [];
  let i = 0;
  while (i < source.length) {
    const c = source[i];
    const next = source[i + 1];
    if (c === '/' && next === '/') { const end = source.indexOf('\n', i); i = end < 0 ? source.length : end; continue; }
    if (c === '#' && !isJs && source[i - 1] !== '\\') { const end = source.indexOf('\n', i); i = end < 0 ? source.length : end; continue; }
    if (c === '/' && next === '*') { const end = source.indexOf('*/', i + 2); i = end < 0 ? source.length : end + 2; continue; }
    if (c === "'" || c === '"' || (isJs && c === '`')) {
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
// El código sin comentarios (las cadenas se conservan).
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
// Llamadas `->metodo( … )` con sus argumentos completos y su receptor.
const calls = (source, names) => {
  const out = [];
  const re = new RegExp(`(\\$[A-Za-z_]\\w*|self|static|parent)\\s*(->|::)\\s*(${names.join('|')})\\s*\\(`, 'g');
  for (const m of source.matchAll(re)) {
    let depth = 0;
    let j = m.index + m[0].length - 1;
    for (; j < source.length; j++) {
      const c = source[j];
      if (c === "'" || c === '"') { let k = j + 1; while (k < source.length && source[k] !== c) k += source[k] === '\\' ? 2 : 1; j = k; continue; }
      if (c === '(') depth++;
      else if (c === ')' && --depth === 0) break;
    }
    out.push({ receiver: m[1], kind: m[3], args: source.slice(m.index + m[0].length, j), at: m.index });
  }
  return out;
};
// Cuerpo de un método `function nombre(` del mismo archivo.
const method = (source, name) => {
  const start = source.search(new RegExp(`function\\s+${name}\\s*\\(`));
  if (start < 0) return '';
  const open = source.indexOf('{', start);
  let depth = 0;
  for (let i = open; i < source.length; i++) {
    if (source[i] === '{') depth++;
    else if (source[i] === '}' && --depth === 0) return source.slice(open, i + 1);
  }
  return '';
};
const constants = (source) => Object.fromEntries([...source.matchAll(/const\s+([A-Z_][A-Z0-9_]*)\s*=\s*'([^']*)'/g)].map((m) => [m[1], m[2]]));

// ── 1. Proveedores ───────────────────────────────────────────────────────────────────────────────────────────────
const vendors = JSON.parse(read(join(ROOT, 'tests/playground/brand-audit/vendors.json')));
const hosts = [...vendors.hosts, ...(vendors.future || [])];
const hostRe = new RegExp(`(?<![a-z0-9-])(?:[a-z0-9-]+\\.)*(?:${hosts.map((h) => h.replace(/\./g, '\\.')).join('|')})(?![a-z0-9-])`, 'i');
const brandRe = /\b(Elementor|Divi|Betheme|BeBuilder|Bricks|Elegant Themes|Muffin|Element Pack|Prime Slider|BdThemes)\b/;
let phrases = 0;
for (const path of files) {
  const source = read(path);
  ok(!hostRe.test(source), `${rel(path)} no nombra dominios de los proveedores (${source.match(hostRe)?.[0]}).`);
  if (!/\.(php|js)$/.test(path)) continue;
  for (const literal of literals(source, path.endsWith('.js'))) {
    if (!/\s/.test(literal.slice(1, -1))) continue;
    phrases++;
    ok(!brandRe.test(literal), `${rel(path)}: una frase visible nombra un producto ajeno: ${literal.slice(0, 120)}`);
  }
}
ok(phrases > 50, `Frases revisadas (${phrases}).`);

// ── 2. Textos traducibles ────────────────────────────────────────────────────────────────────────────────────────
const english = /\b(the|and|with|without|please|click|delete|save|settings|loading|enable|disable|select|show|hide|this|your|you|are|is|of|to|for)\b/i;
let gettext = 0;
for (const path of php) {
  const source = code(read(path));
  for (const m of source.matchAll(/\b(__|_e|_x|_n|_nx|esc_html__|esc_html_e|esc_attr__|esc_attr_e|esc_html_x|esc_attr_x)\s*\(/g)) {
    let depth = 0;
    let j = m.index + m[0].length - 1;
    for (; j < source.length; j++) {
      const c = source[j];
      if (c === "'" || c === '"') { let k = j + 1; while (k < source.length && source[k] !== c) k += source[k] === '\\' ? 2 : 1; j = k; continue; }
      if (c === '(') depth++;
      else if (c === ')' && --depth === 0) break;
    }
    const args = source.slice(m.index + m[0].length, j);
    const strings = literals(args, false);
    if (!strings.length) continue;
    gettext++;
    ok(strings[strings.length - 1] === "'pro-elements'", `${rel(path)}: ${m[1]}() con el dominio 'pro-elements': ${args.slice(0, 100)}`);
    const text = strings[0].slice(1, -1);
    ok(!english.test(text), `${rel(path)}: texto en inglés: ${text.slice(0, 100)}`);
  }
}
for (const path of js) {
  for (const literal of literals(read(path), true)) {
    const text = literal.slice(1, -1);
    if (/^[A-ZÁÉÍÓÚÑ¿¡][^<>{}=;]*\s\S+\s\S+/.test(text) && !/^[A-Z_]+$/.test(text)) ok(!english.test(text), `${rel(path)}: mensaje en inglés: ${text.slice(0, 100)}`);
  }
}
ok(gettext > 10, `Llamadas de traducción revisadas (${gettext}).`);

// ── 3. Controles ─────────────────────────────────────────────────────────────────────────────────────────────────
// V.12: controles que no son de estilo (más destino y clave de los filtros) y los que sí lo son.
// F8 (§3.E.3): el contador opcional del slider anidado (`counter`) tampoco es de estilo.
const NON_STYLE = new Set(['_digitalisimo_actions_on', '_digitalisimo_actions', '_digitalisimo_hidden_initial', 'digitalisimo_saved_query', '_digitalisimo_slot', 'digitalisimo_overrides', 'digitalisimo_inline_tags', 'digitalisimo_conditional', 'digitalisimo_rules', 'digitalisimo_logic', 'destination', 'selected_element', 'counter']);
const STYLE = new Set(['digitalisimo_presentation']);
const UI_ONLY = /::(RAW_HTML|HEADING|DIVIDER|NOTICE|ALERT|BUTTON|DEPRECATED_NOTICE)\b|'type'\s*=>\s*'(raw_html|heading|divider|notice|alert|button|deprecated_notice)'/;
let controls = 0;
let existing = 0;
const seenNames = new Set();
for (const path of php) {
  const source = code(read(path));
  const consts = constants(source);
  const hooksExisting = /['"]elementor\/element\/(?!parse_css|before_parse_css|is_dynamic_content|after_add_attributes)[^'"]*['"]|"elementor\/element\/\{/.test(source) && /add_action\s*\(/.test(source);
  const newWidget = /class\s+\w+\s+extends\s+\\?[\w\\]*(Widget_Base|Base_Widget|Widget_Nested_Base|Filter_Widget|Filter_Form)\b/.test(source);
  ok(!/elementor\/element\/common-optimized\//.test(source), `${rel(path)}: nunca common-optimized (el núcleo repite ahí los ganchos de common).`);
  for (const call of calls(source, ['add_control', 'add_responsive_control', 'add_group_control'])) {
    if (/^\$repeater/i.test(call.receiver) || /^\$fields?$/.test(call.receiver)) continue;
    controls++;
    let args = call.args;
    // Argumentos de un método del mismo archivo (`self::control()`).
    const fromMethod = /(?:self|static)::(\w+)\(\s*\)/.exec(args);
    if (fromMethod && !/array\s*\(/.test(args.slice(0, fromMethod.index))) args += method(source, fromMethod[1]);
    const first = literals(args, false)[0];
    const constName = /^\s*(?:self|static)::([A-Z_][A-Z0-9_]*)\s*,/.exec(args);
    const name = constName ? consts[constName[1]] : (first && args.trim().startsWith(first) ? first.slice(1, -1) : '');
    if (name) seenNames.add(name);
    const group = call.kind === 'add_group_control';
    const style = group || /'selectors?'\s*=>|'prefix_class'\s*=>/.test(args);
    const transfer = /'style_transfer'\s*=>\s*(true|false)/.exec(args);
    if (NON_STYLE.has(name)) ok(transfer && transfer[1] === 'false', `${rel(path)}: ${name} no es de estilo y lleva 'style_transfer' => false (V.12).`);
    if (STYLE.has(name)) ok(transfer && transfer[1] === 'true', `${rel(path)}: ${name} es de estilo ('style_transfer' => true).`);
    if (!style && !group && !UI_ONLY.test(args)) ok(!!transfer, `${rel(path)}: control ${name || '(dinámico)'} sin selectors declara 'style_transfer': ${args.slice(0, 120)}`);
    if (hooksExisting && !newWidget) {
      existing++;
      const def = /'default'\s*=>\s*([^,\n)]+)/.exec(args);
      if (style && def) ok(/^(''|""|array\(\s*\))$/.test(def[1].trim()), `${rel(path)}: control de estilo ${name} con default en un widget existente: ${def[0]}`);
      if (!style && def && !/^(''|""|array\(\s*\))$/.test(def[1].trim())) ok(/'conditions?'\s*=>/.test(args), `${rel(path)}: ${name} con default en un widget existente sin condición que lo active: ${def[0]}`);
    }
  }
}
ok(controls > 10 && existing >= 1, `Controles revisados (${controls}; ${existing} en widgets existentes).`);
ok(seenNames.has('digitalisimo_presentation'), 'Se revisó el control de la presentación.');
ok(seenNames.has('counter'), 'Se revisó el control del contador del slider anidado (F8).');

// ── 4. Scripts y topes ───────────────────────────────────────────────────────────────────────────────────────────
const CAPS = {
  'actions.js': 6144, 'nested-slider.css': 4096, 'nested-slider.js': 6144, 'forms-conditions.js': 3072,
  'nested-presentation.css': 4096, 'f7b-filters.css': 2048, 'filters-form.js': 6144, 'filters.js': 8192,
  // F8 (§5): el contador del slider anidado.
  'nested-slider-counter.css': 256, 'nested-slider-counter.js': 512,
};
for (const path of [...js, ...cssFiles]) {
  const name = basename(path);
  const cap = CAPS[name] ?? (/-editor\.js$/.test(name) ? 8192 : name.endsWith('.css') ? 4096 : 8192);
  const bytes = statSync(path).size;
  ok(bytes <= cap, `${rel(path)}: ${bytes} B (tope ${cap} B).`);
}
for (const path of js.filter((p) => !/-editor\.js$/.test(p))) {
  const source = code(read(path));
  ok(!/\bjQuery\b|(^|[^\w$.])\$\(\s*['"`a-z]/.test(source), `${rel(path)}: script del front sin jQuery.`);
}
for (const path of php) {
  const source = code(read(path));
  const editorOnly = /['"]elementor\/(editor|preview)\/[a-z_/]+['"]/.test(source);
  for (const call of source.match(/wp_(register|enqueue)_script\s*\([^;]*;/g) || []) {
    if (/wp_enqueue_script\s*\(\s*(\$[\w>-]+|self::\w+(\(\))?|static::\w+|'[^']+')\s*\)\s*;/.test(call)) continue; // encola un handle ya registrado
    const handle = (/\(\s*([^,]+),/.exec(call) || [])[1] || '';
    const isEditor = editorOnly || /editor/i.test(handle) || /-editor\.js/.test(call);
    ok(/'strategy'\s*=>\s*'defer'/.test(call) || isEditor, `${rel(path)}: script del front sin defer: ${call.slice(0, 140)}`);
    if (!isEditor) ok(!/'jquery'/.test(call), `${rel(path)}: script del front que depende de jQuery: ${call.slice(0, 140)}`);
  }
}
const urlOk = (target) => /^\s*(['"]|\\['"])?data:/.test(target);
for (const path of [...cssFiles, ...php, ...js]) {
  const source = read(path);
  const chunks = path.endsWith('.css') ? [source] : literals(source, path.endsWith('.js')).map((l) => l.slice(1, -1));
  for (const chunk of chunks) {
    for (const match of chunk.matchAll(/(?<![A-Za-z0-9_-])url\(/g)) {
      const rest = chunk.slice(match.index + 4);
      if (rest === '' && !path.endsWith('.css')) continue;
      ok(urlOk(rest), `${rel(path)}: url() que no es data: (${chunk.slice(match.index, match.index + 60)}).`);
    }
  }
}

// ── 5. Clases propias digi- ──────────────────────────────────────────────────────────────────────────────────────
const classOk = (token) => /^(digi-|elementor-|e-|screen-reader-text$)/.test(token) || /[{}$%<>]/.test(token);
for (const path of cssFiles) {
  const source = read(path).replace(/\/\*[\s\S]*?\*\//g, '').replace(/\{[^{}]*\}/g, '{}');
  for (const m of source.matchAll(/\.(-?[_a-zA-Z][\w-]*)/g)) ok(classOk(m[1]), `${rel(path)}: clase propia sin prefijo digi-: .${m[1]}`);
}
for (const path of php) {
  const source = code(read(path));
  for (const m of source.matchAll(/'prefix_class'\s*=>\s*'([^']*)'/g)) ok(m[1].startsWith('digi-'), `${rel(path)}: prefix_class con prefijo digi- (${m[1]}).`);
}
const ADMIN = /^(wrap|widefat|striped|fixed|notice|notice-[a-z-]*|is-dismissible|is-active|is-inactive|button|button-[a-z-]+|dashicons|dashicons-[a-z-]+|description|regular-text|small-text|large-text|ul-disc|code|spinner|card|form-table|page-title-action|hidden|wp-heading-inline|alignleft|alignright|subsubsub|count|current|tablenav|actions|row-actions|column-[a-z_-]+)$/;
// En el marcado PHP (panel del editor y pantallas), además de digi-, el prefijo de digitalisimo-ui: digitalisimo-.
const markupOk = (token) => classOk(token) || /^digitalisimo-/.test(token);
for (const path of [...php, ...js]) {
  const source = read(path);
  const admin = /admin_menu|add_submenu_page|admin_notices|manage_[a-z_]+_columns|row_actions/.test(source);
  for (const literal of literals(source, path.endsWith('.js'))) {
    for (const m of literal.matchAll(/class=\\?["']([^"'\\]*)/g)) {
      for (const token of m[1].split(/\s+/).filter(Boolean)) ok((path.endsWith('.php') ? markupOk(token) : classOk(token)) || (admin && ADMIN.test(token)), `${rel(path)}: clase sin prefijo digi-: ${token}`);
    }
  }
  if (path.endsWith('.js')) {
    for (const m of source.matchAll(/classList\.(?:add|remove|toggle|contains)\(\s*['"]([^'"]+)['"]/g)) ok(classOk(m[1]), `${rel(path)}: classList con una clase sin prefijo digi-: ${m[1]}`);
  }
}

// ── 6. Guardas sin clases del núcleo que llegan en init ─────────────────────────────────────────────────────────
const core = join(BASE, 'elementor');
const lateFiles = [];
for (const [file, fn] of [['includes/managers/elements.php', 'require_files'], ['includes/managers/widgets.php', 'require_files']]) {
  const body = method(read(join(core, file)), fn);
  for (const m of body.matchAll(/'(includes\/[^']+\.php)'/g)) lateFiles.push(m[1]);
}
const late = new Set(lateFiles.flatMap((f) => [...read(join(core, f)).matchAll(/^(?:abstract |final )?class (\w+)/gm)].map((m) => `Elementor\\${m[1]}`)));
ok(late.has('Elementor\\Element_Base') && late.has('Elementor\\Widget_Base') && late.has('Elementor\\Repeater'), `Clases del núcleo declaradas en init: ${[...late].join(', ')}`);
const coreMap = Object.fromEntries([...read(join(core, 'includes/autoloader.php')).matchAll(/'([A-Za-z_\\]+)'\s*=>\s*'(includes\/[^']+\.php)'/g)].map((m) => [`Elementor\\${m[1].replace(/\\\\/g, '\\')}`, m[2]]));
const kebab = (name) => name.replace(/([a-z])([A-Z])/g, '$1-$2').replace(/_/g, '-').replace(/\\/g, '/').toLowerCase();
const fileOf = (cls) => {
  if (coreMap[cls]) return join(core, coreMap[cls]);
  if (cls.startsWith('Elementor\\')) return join(core, kebab(cls.slice('Elementor\\'.length)) + '.php');
  if (cls.startsWith('ElementorPro\\')) return join(BASE, kebab(cls.slice('ElementorPro\\'.length)) + '.php');
  return '';
};
const chain = (cls, depth = 0) => {
  if (late.has(cls)) return [cls];
  if (depth > 12) return [];
  const file = fileOf(cls);
  if (!file || !existsSync(file)) return [];
  const source = read(file);
  const short = cls.split('\\').pop();
  const ext = new RegExp(`(?:abstract\\s+|final\\s+)?class\\s+${short}\\s+extends\\s+([\\\\\\w]+)`).exec(source);
  if (!ext) return [];
  let parent = ext[1];
  if (parent.startsWith('\\')) parent = parent.slice(1);
  else {
    const use = new RegExp(`^use\\s+([\\\\\\w]+?)(?:\\s+as\\s+${parent})?;`, 'm');
    const aliased = [...source.matchAll(/^use\s+([\\\w]+?)(?:\s+as\s+(\w+))?;/gm)].find((m) => (m[2] || m[1].split('\\').pop()) === parent.split('\\')[0]);
    const ns = (/^namespace\s+([\\\w]+);/m.exec(source) || [])[1] || '';
    parent = aliased ? aliased[1] + parent.slice(parent.split('\\')[0].length) : `${ns}\\${parent}`;
    void use;
  }
  const up = chain(parent, depth + 1);
  return up.length ? [cls, ...up] : [];
};
ok(chain('Elementor\\Modules\\NestedElements\\Base\\Widget_Nested_Base').includes('Elementor\\Widget_Base'), 'La resolución de herencia conoce el caso Widget_Nested_Base → Widget_Base.');
ok(chain('Elementor\\Controls_Manager').length === 0, 'Controls_Manager se puede comprobar en plugins_loaded.');
let guards = 0;
const lateGuards = [];
for (const path of php) {
  const source = code(read(path));
  const consts = constants(source);
  // Una guarda en `init()` corre en plugins_loaded; en otro método (p. ej. el de `elementor/widgets/register`), ya en init.
  const enclosing = (index) => [...source.slice(0, index).matchAll(/function\s+(\w+)\s*\(/g)].pop()?.[1] ?? '';
  const lists = [
    ...[...source.matchAll(/requires\(\s*array\(([\s\S]*?)\)\s*,/g)].filter((m) => enclosing(m.index) === 'init').map((m) => m[1]),
    ...[...source.matchAll(/(?:const SYMBOLS|\$symbols)\s*=\s*array\(([\s\S]*?)\);/g)].map((m) => m[1]),
  ];
  for (const list of lists) {
    for (const m of list.matchAll(/'([^']+)'|self::([A-Z_][A-Z0-9_]*)/g)) {
      const symbol = (m[1] ?? consts[m[2]] ?? '').replace(/\\\\/g, '\\').replace(/^\\/, '');
      if (!symbol || /\(\)$|^const:/.test(symbol)) continue;
      guards++;
      const cls = symbol.split('::')[0];
      const found = chain(cls);
      if (found.length) lateGuards.push(`${rel(path)}: ${symbol} (${found.join(' → ')})`);
    }
  }
}
ok(guards >= 3, `Símbolos de guardas revisados (${guards}).`);
ok(lateGuards.length === 0, `Guardas con clases que el núcleo declara en init (lanzan «Class not found» en plugins_loaded): ${lateGuards.join('; ')}`);

// ── 7. Seguridad ─────────────────────────────────────────────────────────────────────────────────────────────────
for (const path of php) {
  const source = code(read(path));
  ok(!/wp_ajax_nopriv_/.test(source), `${rel(path)}: ninguna acción AJAX sin sesión.`);
  // SELECT DISTINCT sólo acotado a una lista de entradas (`post_id IN (…)`), nunca sobre la tabla entera (C2-18).
  for (const m of source.matchAll(/SELECT\s+DISTINCT[^;]*/gi)) ok(/\b(post_id|ID)\s+IN\s*\(/.test(m[0]), `${rel(path)}: SELECT DISTINCT sobre una tabla entera: ${m[0].slice(0, 120)}`);
  const capability = /current_user_can\s*\(|::can_\w+\s*\(|::can\s*\(/;
  if (/register_ajax_action\s*\(/.test(source)) ok(/elementor\/ajax\/register_actions/.test(source) && capability.test(source), `${rel(path)}: AJAX del editor por elementor/ajax/register_actions y con current_user_can().`);
  if (/add_action\(\s*'wp_ajax_/.test(source)) ok(/check_ajax_referer|wp_verify_nonce/.test(source) && capability.test(source), `${rel(path)}: wp_ajax_ con nonce y capacidad.`);
}
const deletion = join(F7B, 'class-deletion.php');
if (existsSync(deletion)) ok(!/\bwp_die\s*\(/.test(code(read(deletion))), 'El bloqueo del borrado nunca llama a wp_die().');

assert.ok(failures.length === 0, `Revisión estática de F7b: ${failures.length} incidencias:\n- ${failures.join('\n- ')}`);
console.log(`DIGITALÍSIMO Elements: revisión estática de F7b en verde (${checks} comprobaciones; ${files.length} archivos, ${phrases} frases, ${controls} controles, ${guards} símbolos de guardas).`);
