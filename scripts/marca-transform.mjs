// Marca propia (docs/MARCA_PROPIA.md): las transformaciones de CSS y JS que aplican las reglas de
// `digitalisimo-elements/marca/reglas.json`. Las usan el espejo (scripts/marca-espejo.mjs) y la auditoría de cero rastro
// (tests/brand-zero-trace.mjs). El HTML lo transforma la capa PHP (prototipo en scripts/marca-prototipo/buffer.php).
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

export function loadRules(path = fileURLToPath(new URL('../digitalisimo-elements/marca/reglas.json', import.meta.url))) {
  return JSON.parse(readFileSync(path, 'utf8'));
}

const esc = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

/** Identificadores JS (globales, propiedades y dataset) de la regla, los más largos primero. */
function idMap(rules) {
  const map = { ...(rules.js?.propiedades || {}), ...(rules.js?.globales || {}), ...(rules.js?.dataset || {}) };
  return Object.entries(map).sort((a, b) => b[0].length - a[0].length);
}

/** CSS: clases, atributos, keyframes, variables e ids; sin comentarios ni sourcemaps. */
export function transformCss(css, rules) {
  const p = rules.prefijo || 'dg';
  return css
    .replace(/\/\*[\s\S]*?\*\//g, (c) => (/elementor/i.test(c) || /sourceMappingURL/.test(c) ? '' : c))
    .replace(/data-elementor-/g, `data-${p}-`)
    .replace(/--elementor-/g, `--${p}-`)
    .replace(/(^|[^\w-])elementor(?=[\w-]|[^\w-]|$)/g, (m, pre) => `${pre}${p}`)
    .replace(/(^|[^\w])e-elementor-/g, `$1e-${p}-`)
    // Lo que queda dentro de un nombre de clase (`eicon-elementor`, `social-icon-elementor`): el HTML cambia igual.
    .replace(/elementor/gi, p);
}

/** Nombre de archivo en el espejo: `elementor-icons.min.css` → `dg-icons.min.css` (la capa cambia la URL igual). */
export function mirrorName(rel, rules) {
  return rel.replace(/elementor/gi, rules.prefijo || 'dg');
}

/** JS: identificadores, eventos, atributos, acciones de URL y clases en cadenas. */
export function transformJs(js, rules) {
  const p = rules.prefijo || 'dg';
  let out = js.replace(/\n\/\/# sourceMappingURL=.*$/m, '');
  for (const [from, to] of idMap(rules)) out = out.replace(new RegExp(`(?<![\\w$])${esc(from)}(?![\\w$])`, 'g'), to);
  for (const r of rules.js?.eventos_regla || []) out = out.replace(new RegExp(`(?<![\\w./-])${esc(r.de)}`, 'g'), r.a);
  for (const r of rules.acciones_url || []) out = out.split(r.de.replace(/:$/, '')).join(r.a.replace(/:$/, ''));
  out = out.replace(/%23elementor-action/g, `%23${p}-action`).replace(/data-elementor-/g, `data-${p}-`);
  // Clases y el resto: `elementor-x` → `dg-x`, `elementor` sola → `dg` (también el texto de los mensajes de consola).
  out = out.replace(/(?<![\w$])elementor(?=-|\b)/g, p);
  // Identificadores locales de los archivos sin minificar (`_elementorFrontendCon`, `elementorBreakpoints`): el mismo
  // cambio en todos los archivos, así que siguen casando entre sí.
  out = out.replace(/elementor/g, 'digitalisimo').replace(/ELEMENTOR/g, 'DIGITALISIMO');
  out = out.replace(/(?<![\w$])Elementor(?![\w$])/g, 'DIGITALÍSIMO').replace(/Elementor/g, 'Digitalisimo');
  // Coincidencias sin distinguir mayúsculas dentro de nombres locales de librerías (`elementOrSelector` de motion.js).
  out = out.replace(/elementor/gi, (m) => (m[0] === 'E' ? 'Digitalisimo' : 'digitalisimo'));
  return out;
}

/** Apariciones de «elementor» que quedan (sin distinguir mayúsculas). */
export function count(text) {
  return (text.match(/elementor/gi) || []).length;
}
