// Utilidades compartidas por tests/migration-legacy-styles{,-chrome}.mjs y tests/playground/legacy-compare.mjs.
//
// - parsePhp(): lee el `return …;` de un `maps/*.styles.php` (datos generados por scripts/legacy-styles.py: cadenas
//   entre comillas simples, números, true/false/null y array( … ) con o sin claves) sin ejecutar PHP. Un array de PHP
//   cuyas claves son 0..n-1 en orden es una lista; cualquier otro, un objeto.
// - canonical()/hash(): JSON con las claves ordenadas y su sha256; es la huella de cada control en
//   tests/fixtures/f8-legacy-styles-baseline.json.
// - corpus(): las clases `digi-*`/`elementor-*`/`ep-*` que aparecen literalmente, con límite de clase, en el PHP y el JS de
//   DIGITALÍSIMO Elements y de Migración (sin los `.styles.php` ni el bloque `classes` de cada mapa). Es la misma regla que aplica scripts/legacy-styles.py
//   (`corpus_tokens()`): una clase de un selector que no está aquí ni casa con un patrón dinámico de
//   tests/fixtures/legacy-styles-dynamic.json no la pinta ningún widget, y el selector está muerto.
import { createHash } from 'node:crypto';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

export function parsePhp(text, file = 'php') {
  const start = text.search(/\breturn\s/);
  if (start < 0) throw new Error(`${file}: sin return`);
  let i = start + 6;
  const n = text.length;
  const fail = (what) => { throw new Error(`${file}: ${what} en ${i}: ${JSON.stringify(text.slice(i, i + 40))}`); };
  const ws = () => {
    for (;;) {
      while (i < n && /\s/.test(text[i])) i++;
      if (text.startsWith('//', i) || text[i] === '#') { while (i < n && text[i] !== '\n') i++; continue; }
      if (text.startsWith('/*', i)) { const end = text.indexOf('*/', i + 2); i = end < 0 ? n : end + 2; continue; }
      return;
    }
  };
  const value = () => {
    ws();
    const c = text[i];
    if (c === "'") {
      let out = '';
      i++;
      while (i < n && text[i] !== "'") {
        if (text[i] === '\\' && (text[i + 1] === "'" || text[i + 1] === '\\')) { out += text[i + 1]; i += 2; continue; }
        out += text[i++];
      }
      if (i >= n) fail('cadena sin cerrar');
      i++;
      return out;
    }
    const num = /^-?\d+(?:\.\d+)?/.exec(text.slice(i, i + 40));
    if (num) { i += num[0].length; return Number(num[0]); }
    const word = /^[A-Za-z_]\w*/.exec(text.slice(i, i + 40));
    if (!word) fail('valor desconocido');
    const low = word[0].toLowerCase();
    i += word[0].length;
    if (low === 'true' || low === 'false') return low === 'true';
    if (low === 'null') return null;
    if (low !== 'array') fail('identificador ' + word[0]);
    ws();
    if (text[i] !== '(') fail('array sin paréntesis');
    i++;
    const pairs = [];
    let next = 0;
    for (;;) {
      ws();
      if (text[i] === ')') { i++; break; }
      let item = value();
      ws();
      let key;
      if (text.startsWith('=>', i)) {
        i += 2;
        key = item;
        item = value();
        ws();
        if (typeof key === 'number' || /^(?:0|-?[1-9]\d*)$/.test(String(key))) key = Number(key);
        if (typeof key === 'number' && key >= next) next = key + 1;
      } else {
        key = next++;
      }
      pairs.push([key, item]);
      if (text[i] === ',') i++;
      else if (text[i] !== ')') fail('falta coma');
    }
    if (pairs.every(([k], index) => k === index)) return pairs.map(([, v]) => v);
    const out = {};
    for (const [k, v] of pairs) out[String(k)] = v;
    return out;
  };
  return value();
}

export function canonical(value) {
  if (Array.isArray(value)) return '[' + value.map(canonical).join(',') + ']';
  if (value && typeof value === 'object') {
    return '{' + Object.keys(value).sort().map((k) => JSON.stringify(k) + ':' + canonical(value[k])).join(',') + '}';
  }
  return JSON.stringify(value);
}

export const hash = (value) => createHash('sha256').update(canonical(value)).digest('hex');

export function stylesDir(root) {
  return join(root, 'digitalisimo-migration/legacy/maps');
}

export function loadStyles(root) {
  const dir = stylesDir(root);
  const out = {};
  for (const file of readdirSync(dir).filter((f) => f.endsWith('.styles.php')).sort()) {
    out[file.replace(/\.styles\.php$/, '')] = parsePhp(readFileSync(join(dir, file), 'utf8'), file);
  }
  return out;
}

// Divide un selector compuesto por sus comas de primer nivel (no las de :is(), :not() ni atributos).
export function splitSelector(selector) {
  const parts = [];
  let depth = 0;
  let cur = '';
  for (const ch of String(selector)) {
    if (ch === '(' || ch === '[') depth++;
    else if (ch === ')' || ch === ']') depth--;
    if (ch === ',' && depth === 0) { parts.push(cur.trim()); cur = ''; continue; }
    cur += ch;
  }
  if (cur.trim()) parts.push(cur.trim());
  return parts;
}

// Clases de un selector (sin las plantillas {{…}} de Elementor).
export function classesOf(selector) {
  const clean = String(selector).replace(/\{\{[^}]*\}\}/g, ' ');
  return [...clean.matchAll(/\.(-?[_a-zA-Z][_a-zA-Z0-9-]*)/g)].map((m) => m[1]);
}

// Los propios, los de Elementor y los de Element Pack que no son `bdt-` (`ep-*`), que ningún widget propio imprime.
export const checked = (cls) => /^(?:digi|elementor|ep)-/.test(cls);

// Todas las partes de selector de un control: [{ key, part }] (key = la clave de selectors o '' en un grupo).
export function selectorParts(control) {
  if ('group' in control) return splitSelector(control.selector).map((part) => ({ key: '', part }));
  return Object.keys(control.selectors || {}).flatMap((key) => splitSelector(key).map((part) => ({ key, part })));
}

const TOKEN = /(?<![A-Za-z0-9_-])((?:digi|elementor|ep)-[A-Za-z0-9_-]+)/g;
const SKIP_DIRS = new Set(['node_modules', '.git']);
export const CLASSES_BLOCK = /^\t'classes'\s*=>[^\n]*?array\((?:[^\n]*\),[ \t]*$|\n[\s\S]*?\n\t\),[^\n]*$)/gm;

export function corpusFiles(root) {
  const out = [];
  const walk = (dir) => {
    for (const entry of readdirSync(dir, { withFileTypes: true })) {
      if (SKIP_DIRS.has(entry.name) || entry.isSymbolicLink()) continue;
      const path = join(dir, entry.name);
      if (entry.isDirectory()) walk(path);
      else if (/\.(?:php|js)$/.test(entry.name) && !entry.name.endsWith('.styles.php')) out.push(path);
    }
  };
  walk(join(root, 'digitalisimo-elements'));
  walk(join(root, 'digitalisimo-migration'));
  return out.sort();
}

export function corpus(root) {
  const tokens = new Set();
  for (const file of corpusFiles(root)) {
    if (statSync(file).size > 32 * 1024 * 1024) continue;
    let text = readFileSync(file, 'latin1');
    // Un mapa puede imprimir marcado propio en su filtro, pero su `classes` no prueba nada: es lo que se valida.
    if (relative(root, file).startsWith('digitalisimo-migration/legacy/maps/')) text = text.replace(CLASSES_BLOCK, '');
    for (const m of text.matchAll(TOKEN)) tokens.add(m[1]);
  }
  return tokens;
}

export function loadDynamic(root) {
  const data = JSON.parse(readFileSync(join(root, 'tests/fixtures/legacy-styles-dynamic.json'), 'utf8'));
  return data.patterns.map((p) => ({ ...p, re: new RegExp(p.pattern) }));
}

export function aliveClass(cls, tokens, dynamic) {
  return !checked(cls) || tokens.has(cls) || dynamic.some((p) => p.re.test(cls));
}

export function deadClasses(part, tokens, dynamic) {
  return classesOf(part).filter((cls) => !aliveClass(cls, tokens, dynamic));
}

// El mismo recorte que hace `legacy-styles.py --prune`: quita las partes de selector con una clase muerta; una clave
// sin partes vivas desaparece, y un control sin selectores vivos también (devuelve null).
export function prune(control, tokens, dynamic) {
  const live = (part) => deadClasses(part, tokens, dynamic).length === 0;
  if ('group' in control) {
    const parts = splitSelector(control.selector).filter(live);
    return parts.length ? { ...control, selector: parts.join(', ') } : null;
  }
  const selectors = {};
  for (const [key, css] of Object.entries(control.selectors || {})) {
    const parts = splitSelector(key).filter(live);
    if (parts.length) selectors[parts.join(', ')] = css;
  }
  return Object.keys(selectors).length ? { ...control, selectors } : null;
}
