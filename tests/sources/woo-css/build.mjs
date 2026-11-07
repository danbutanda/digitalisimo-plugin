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
// Puntos de corte del sitio (corrección de fidelidad c7#2 de docs/pro/c7.md): las hojas con @media de ancho fijo (los
// puntos de corte por defecto, 767/768/1024/1025 px) tienen además una plantilla en `<frente>/breakpoints/<hoja>.css`
// con los marcadores `ELEMENTOR_SCREEN_*` del núcleo, como las `assets/css/templates/` de PRO. Con puntos de corte
// personalizados, `Woo\Assets::register_sheet()` sirve la copia que el núcleo regenera desde ella con los del kit. La
// plantilla se escribe con `--write` y se comprueba siempre; un ancho que no sea uno de esos cuatro falla.
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

// Ancho fijo de la hoja → marcador del núcleo (`Elementor\Core\Responsive\Files\Frontend`), los mismos que las
// plantillas de PRO de cada hoja: 1024 es el máximo de la tableta (también como mínimo), 1025 el siguiente, 768 el mínimo
// de la tableta y 767 el máximo del móvil (también como mínimo).
const MEDIA = {
  'max-width:1024px': 'max-width:ELEMENTOR_SCREEN_TABLET_MAX',
  'min-width:1024px': 'min-width:ELEMENTOR_SCREEN_TABLET_MAX',
  'min-width:1025px': 'min-width:ELEMENTOR_SCREEN_TABLET_NEXT',
  'max-width:768px': 'max-width:ELEMENTOR_SCREEN_TABLET_MIN',
  'max-width:767px': 'max-width:ELEMENTOR_SCREEN_MOBILE_MAX',
  'min-width:767px': 'min-width:ELEMENTOR_SCREEN_MOBILE_MAX',
};
/** Plantilla de una hoja publicada: `{ text, unknown }`; `text` es null si la hoja no tiene anchos fijos. */
export function breakpointTemplate(css) {
  const unknown = [];
  let found = 0;
  const text = css.replace(/\(\s*((?:min|max)-width)\s*:\s*(\d+(?:\.\d+)?)px\s*\)/g, (match, prop, value) => {
    const key = `${prop}:${value}px`;
    found++;
    if (!MEDIA[key]) { unknown.push(key); return match; }
    return `(${MEDIA[key]})`;
  });
  return { text: found ? text : null, unknown };
}

let problems = 0;
let count = 0;
let templates = 0;
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
      // Sólo las que cambian: una hoja que ya es la minificación de su fuente se deja tal cual.
      if (built !== minify(readFileSync(shipped, 'utf8'))) {
        writeFileSync(shipped, built);
        console.log(`${relative(ROOT, shipped)}: ${Buffer.byteLength(built)} B`);
      }
    } else if (built !== minify(readFileSync(shipped, 'utf8'))) {
      console.log(`FALLA  ${relative(ROOT, shipped)} no es la minificación de ${relative(ROOT, source)}: node tests/sources/woo-css/build.mjs --write`);
      problems++;
    }
    // Plantilla con los puntos de corte del sitio, desde la hoja publicada tal cual.
    const tpl = breakpointTemplate(readFileSync(shipped, 'utf8'));
    const tplPath = join(ASSETS, dir.replace(/\/assets$/, '/breakpoints'), name);
    if (tpl.unknown.length) { console.log(`FALLA  ${relative(ROOT, shipped)}: anchos sin marcador del núcleo (${tpl.unknown.join(', ')}): añádelos a MEDIA`); problems++; }
    if (mode === 'write' && tpl.text !== null) {
      mkdirSync(dirname(tplPath), { recursive: true });
      writeFileSync(tplPath, tpl.text);
    } else if (tpl.text === null && existsSync(tplPath)) {
      console.log(`FALLA  ${relative(ROOT, tplPath)}: la hoja ya no tiene anchos fijos; borra la plantilla`); problems++;
    } else if (tpl.text !== null && (!existsSync(tplPath) || readFileSync(tplPath, 'utf8') !== tpl.text)) {
      console.log(`FALLA  ${relative(ROOT, tplPath)} no es la plantilla de ${relative(ROOT, shipped)}: node tests/sources/woo-css/build.mjs --write`); problems++;
    } else if (tpl.text !== null) {
      templates++;
    }
  }
}
for (const dir of DIRS) {
  const tplDir = join(ASSETS, dir.replace(/\/assets$/, '/breakpoints'));
  if (existsSync(tplDir)) {
    for (const name of readdirSync(tplDir)) {
      if (!existsSync(join(ASSETS, dir, name))) { console.log(`FALLA  plantilla sin hoja publicada: ${relative(ROOT, join(tplDir, name))}`); problems++; }
    }
  }
  const base = join(HERE, dir.replace(/\/assets$/, ''));
  if (!existsSync(base)) continue;
  for (const name of readdirSync(base).filter((n) => n.endsWith('.css'))) {
    if (!existsSync(join(ASSETS, dir, name))) { console.log(`FALLA  fuente sin hoja publicada: ${relative(ROOT, join(base, name))}`); problems++; }
  }
}
if (problems) process.exit(1);
console.log(`Hojas de WooCommerce de C7: ${count} fuentes legibles ${mode === 'check' ? `coinciden con las hojas publicadas (y ${templates} plantillas de puntos de corte, con las suyas)` : mode === 'write' ? 'construidas' : 'generadas'}.`);
