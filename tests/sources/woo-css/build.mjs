// Fuentes legibles de las hojas de los widgets de WooCommerce de C7 (programa «sin widgets de PRO», docs/pro/c7.md).
//
// Las hojas de `digitalisimo-elements/modules/digitalisimo-woo/{single,store}/assets/*.css` se publican minificadas
// porque cada una tiene un tope de 4 KB (carrito, pago, cuenta, resumen y carrito del menú llevan dos). Aquí está su
// versión legible, una por hoja, con la misma ruta relativa (`single/…`, `store/…`). Se editan aquí y se construyen:
//
//   node tests/sources/woo-css/build.mjs           comprueba que cada hoja publicada es la minificación de su fuente
//   node tests/sources/woo-css/build.mjs --write   escribe las hojas publicadas desde las fuentes (y dice cuánto pesan)
//   node tests/sources/woo-css/build.mjs --init    vuelve a generar las fuentes desde las hojas publicadas (sólo para
//                                                   una hoja nueva: pisa los comentarios de las que ya existen)
//
// La minificación quita los comentarios y los saltos de línea con su sangría (sin tocar cadenas ni paréntesis), así que
// en una fuente se puede comentar libremente. No es parte del ZIP: `tests/` no se empaqueta. El tope de 4 KB lo
// comprueban tests/elements-woo-static.mjs y tests/elements-front-budget.php.
import { readFileSync, writeFileSync, readdirSync, mkdirSync, existsSync } from 'node:fs';
import { join, dirname, relative } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '..', '..', '..');
const ASSETS = join(ROOT, 'digitalisimo-elements/modules/digitalisimo-woo');
const DIRS = ['single/assets', 'store/assets'];
const mode = process.argv.includes('--write') ? 'write' : process.argv.includes('--init') ? 'init' : 'check';

/** Recorre el CSS carácter a carácter; `emit(kind, text)` recibe cadenas, comentarios, espacios y el resto. */
function scan(css, emit) {
  let i = 0;
  while (i < css.length) {
    const c = css[i];
    if (c === '"' || c === "'") {
      let j = i + 1;
      while (j < css.length && css[j] !== c) j += css[j] === '\\' ? 2 : 1;
      emit('string', css.slice(i, j + 1));
      i = j + 1;
    } else if (c === '/' && css[i + 1] === '*') {
      const j = css.indexOf('*/', i + 2);
      i = j < 0 ? css.length : j + 2;
      emit('comment', '');
    } else if (/\s/.test(c)) {
      let j = i;
      while (j < css.length && /\s/.test(css[j])) j++;
      emit('space', css.slice(i, j));
      i = j;
    } else {
      emit('char', c);
      i++;
    }
  }
}

/** Minifica: sin comentarios y sin espacios con salto de línea (nada junto a `{};,`, un espacio en otro caso). */
export function minify(css) {
  let out = '';
  let pending = null;
  scan(css, (kind, text) => {
    if (kind === 'comment') return;
    if (kind === 'space') {
      pending = pending === null ? text : pending + text;
      return;
    }
    if (pending !== null) {
      const prev = out.slice(-1);
      const next = text[0];
      if (!/\n/.test(pending)) out += pending;
      else if (prev !== '' && !'{};,'.includes(prev) && !'{};,'.includes(next)) out += ' ';
      pending = null;
    }
    out += text;
  });
  return out;
}

/** Versión legible: una declaración por línea, bloques sangrados (sólo fuera de cadenas y paréntesis). */
export function pretty(css) {
  const flat = minify(css);
  let out = '';
  let depth = 0;
  let parens = 0;
  const pad = () => '\t'.repeat(depth);
  scan(flat, (kind, text) => {
    if (kind !== 'char') { out += text; return; }
    if (text === '(') parens++;
    if (text === ')') parens--;
    if (parens > 0) { out += text; return; }
    if (text === '{') { depth++; out += '{\n' + pad(); return; }
    if (text === ';') { out += ';\n' + pad(); return; }
    if (text === '}') {
      depth = Math.max(0, depth - 1);
      out = out.replace(/[\t ]*$/, '');
      if (!out.endsWith('\n')) out += '\n';
      out += pad() + '}\n' + pad();
      return;
    }
    out += text;
  });
  return out.replace(/[\t ]+$/gm, '').replace(/\n{2,}/g, '\n').trim() + '\n';
}

let problems = 0;
let count = 0;
for (const dir of DIRS) {
  for (const name of readdirSync(join(ASSETS, dir)).filter((n) => n.endsWith('.css')).sort()) {
    const shipped = join(ASSETS, dir, name);
    const source = join(HERE, dir.replace(/\/assets$/, ''), name);
    count++;
    if (mode === 'init') {
      mkdirSync(dirname(source), { recursive: true });
      const text = pretty(readFileSync(shipped, 'utf8'));
      if (minify(text) !== minify(readFileSync(shipped, 'utf8'))) { console.log(`FALLA  ${name}: la fuente generada no vuelve a dar la hoja`); problems++; }
      writeFileSync(source, text);
      continue;
    }
    if (!existsSync(source)) { console.log(`FALLA  ${relative(ROOT, shipped)}: sin fuente en ${relative(ROOT, source)}`); problems++; continue; }
    const built = minify(readFileSync(source, 'utf8'));
    if (mode === 'write') {
      writeFileSync(shipped, built);
      console.log(`${relative(ROOT, shipped)}: ${Buffer.byteLength(built)} B`);
    } else if (built !== minify(readFileSync(shipped, 'utf8'))) {
      console.log(`FALLA  ${relative(ROOT, shipped)} no es la minificación de ${relative(ROOT, source)}: node tests/sources/woo-css/build.mjs --write`);
      problems++;
    }
  }
}
for (const dir of DIRS) {
  const base = join(HERE, dir.replace(/\/assets$/, ''));
  if (!existsSync(base)) continue;
  for (const name of readdirSync(base).filter((n) => n.endsWith('.css'))) {
    if (!existsSync(join(ASSETS, dir, name))) { console.log(`FALLA  fuente sin hoja publicada: ${relative(ROOT, join(base, name))}`); problems++; }
  }
}
if (problems) process.exit(1);
console.log(`Hojas de WooCommerce de C7: ${count} fuentes legibles ${mode === 'check' ? 'coinciden con las hojas publicadas' : mode === 'write' ? 'construidas' : 'generadas'}.`);
