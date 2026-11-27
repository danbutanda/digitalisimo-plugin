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
  // Los url() se apartan (el espejo los reescribe después hacia los binarios originales).
  const urls = [];
  css = css.replace(/url\(\s*(['"]?)([^'")]*)\1\s*\)/gi, (m) => { urls.push(m); return `\u0001${urls.length - 1}\u0001`; });
  return css
    .replace(/\/\*[\s\S]*?\*\//g, (c) => (/elementor/i.test(c) || /sourceMappingURL/.test(c) ? '' : c))
    .replace(/data-elementor-/g, `data-${p}-`)
    .replace(/--elementor-/g, `--${p}-`)
    .replace(/(^|[^\w-])elementor(?=[\w-]|[^\w-]|$)/g, (m, pre) => `${pre}${p}`)
    .replace(/(^|[^\w])e-elementor-/g, `$1e-${p}-`)
    // Lo que queda dentro de un nombre de clase (`eicon-elementor`, `social-icon-elementor`): el HTML cambia igual.
    .replace(/elementor/gi, p)
    .replace(/\u0001(\d+)\u0001/g, (m, i) => urls[Number(i)]);
}

/** Nombre de archivo en el espejo: `elementor-icons.min.css` → `dg-icons.min.css` (la capa cambia la URL igual). */
export function mirrorName(rel, rules) {
  return rel.replace(/elementor/gi, rules.prefijo || 'dg');
}

const KEYWORDS_BEFORE_REGEX = new Set(['return', 'typeof', 'instanceof', 'in', 'of', 'new', 'delete', 'void', 'throw', 'case', 'do', 'else', 'yield', 'await']);

/**
 * Analizador léxico mínimo de JS: parte el código en identificadores, cadenas ('…', "…"), trozos de plantilla (`…${`),
 * expresiones regulares, comentarios y el resto. `visit(tipo, texto)` devuelve el texto nuevo de cada pieza (tipos:
 * `id`, `str` —sin comillas—, `tpl` —texto de una plantilla—, `re` —cuerpo de la expresión—, `comment`, `code`).
 */
export function lexJs(src, visit) {
  const out = [];
  const n = src.length;
  let i = 0;
  let prev = ''; // última pieza significativa: 'id:<x>', 'num', 'p:<c>'
  let prev2 = ''; // la anterior a prev (para `.dataset.x`)
  const braces = []; // 'b' (llave de código) o 't' (abre ${ de una plantilla)
  const regexOk = () => {
    if (!prev) return true;
    if (prev === 'num') return false;
    if (prev.startsWith('id:')) return KEYWORDS_BEFORE_REGEX.has(prev.slice(3));
    return !/^p:[)\]}]$/.test(prev);
  };
  const template = () => {
    // Desde después de ` (o de la } que cierra un ${): hasta ` o ${.
    let j = i;
    let chunk = '';
    while (j < n) {
      const c = src[j];
      if (c === '\\') { chunk += src.slice(j, j + 2); j += 2; continue; }
      if (c === '`') { out.push(visit('tpl', chunk), '`'); i = j + 1; prev = 'p:`'; return; }
      if (c === '$' && src[j + 1] === '{') { out.push(visit('tpl', chunk), '${'); i = j + 2; braces.push('t'); prev = 'p:{'; return; }
      chunk += c;
      j++;
    }
    out.push(visit('tpl', chunk));
    i = n;
  };
  while (i < n) {
    const c = src[i];
    if (c === '/' && src[i + 1] === '/') {
      let j = src.indexOf('\n', i);
      if (j < 0) j = n;
      out.push(visit('comment', src.slice(i, j)));
      i = j;
      continue;
    }
    if (c === '/' && src[i + 1] === '*') {
      let j = src.indexOf('*/', i + 2);
      j = j < 0 ? n : j + 2;
      out.push(visit('comment', src.slice(i, j)));
      i = j;
      continue;
    }
    if (c === '"' || c === "'") {
      let j = i + 1;
      while (j < n && src[j] !== c && src[j] !== '\n') j += src[j] === '\\' ? 2 : 1;
      out.push(c, visit('str', src.slice(i + 1, j)), j < n ? src[j] : '');
      i = j + 1;
      prev = 'str';
      continue;
    }
    if (c === '`') { out.push('`'); i++; template(); continue; }
    if (c === '/' && regexOk()) {
      let j = i + 1;
      let cls = false;
      while (j < n && src[j] !== '\n') {
        const d = src[j];
        if (d === '\\') { j += 2; continue; }
        if (d === '[') cls = true;
        else if (d === ']') cls = false;
        else if (d === '/' && !cls) break;
        j++;
      }
      if (j < n && src[j] === '/') {
        let k = j + 1;
        while (k < n && /[a-z]/i.test(src[k])) k++;
        out.push('/', visit('re', src.slice(i + 1, j)), src.slice(j, k));
        i = k;
        prev = 'num';
        continue;
      }
    }
    if (/[A-Za-z_$]/.test(c) || c > '\x7f') {
      let j = i + 1;
      while (j < n && (/[\w$]/.test(src[j]) || src[j] > '\x7f')) j++;
      const id = src.slice(i, j);
      out.push(visit('id', id, prev === 'p:.' && prev2 === 'id:dataset'));
      prev2 = prev;
      prev = 'id:' + id;
      i = j;
      continue;
    }
    if (/[0-9]/.test(c)) {
      let j = i + 1;
      while (j < n && /[\w.]/.test(src[j])) j++;
      out.push(src.slice(i, j));
      prev = 'num';
      i = j;
      continue;
    }
    if (c === '{') braces.push('b');
    if (c === '}') {
      if (braces.pop() === 't') { out.push('}'); i++; template(); continue; }
    }
    out.push(c);
    if (!/\s/.test(c)) { prev2 = prev; prev = 'p:' + c; }
    i++;
  }
  return out.join('');
}

/** Un identificador de código: la lista de reglas (`js.globales`, `propiedades`, `dataset`) y, si no, el cambio genérico. */
export function transformIdent(id, rules, dataset = false) {
  if (!/elementor/i.test(id)) return id;
  // `el.dataset.elementorOpenLightbox` es el atributo `data-elementor-open-lightbox`: como el HTML, `data-dg-*`.
  if (dataset) return id.replace(/elementor/g, rules.prefijo || 'dg').replace(/Elementor/g, 'Dg');
  const map = { ...(rules.js?.propiedades || {}), ...(rules.js?.globales || {}), ...(rules.js?.dataset || {}) };
  if (Object.prototype.hasOwnProperty.call(map, id)) return map[id];
  return id.replace(/ELEMENTOR/g, 'DIGITALISIMO').replace(/elementor/gi, (m) => (m[0] === 'E' ? 'Digitalisimo' : 'digitalisimo'));
}

/**
 * Una cadena del JS (también el texto de las plantillas y de las expresiones regulares). Las mismas formas que el HTML y
 * el CSS: clases `elementor-x` → `dg-x`, `data-elementor-` → `data-dg-`, `#elementor-action` → `#dg-action`; ganchos,
 * eventos y espacios REST `elementor/…`, `elementor-pro/…` → `digitalisimo/…`, `digitalisimo-pro/…`; acciones AJAX,
 * tipos y plantillas `elementor_x` → `digitalisimo_x`; dominios de texto `elementor`, `elementor-pro` → `dg`, `dg-pro`;
 * nombres de JS conocidos en cadenas (`'elementorFrontendConfig'`) como en el código. El texto («Elementor») →
 * «DIGITALÍSIMO».
 */
export function transformStr(s, rules) {
  if (!/elementor/i.test(s)) return s;
  // Las URL y los nombres de host del proveedor nunca cambian (B-4 de la revisión F2): `go.elementor.com` no puede
  // pasar a `go.dg.com`, un dominio ajeno al que el editor mandaría datos o del que aceptaría mensajes. Lo que los
  // oculta es `digitalisimo-vendor-free.php`, que los reconoce por su host. El resto de la cadena sí se transforma.
  const parts = splitVendor(s);
  if (parts.length === 1 && parts[0].vendor) return s;
  // Una URL absoluta entera (de medios, de otra página): sólo su consulta y el ancla de acción, como
  // `Transform::url_str()` en PHP (el servidor además lleva las rutas de assets a sus copias; en los bundles no hay URL
  // del sitio). Un medio `https://…/elementor-banner.jpg` sigue igual. Las rutas relativas (`/elementor/v1/…`) siguen
  // las reglas de siempre: el JS arma con ellas las rutas REST que el servidor acepta con los dos nombres.
  if (isAbsUrl(s)) return urlStr(s, rules);
  if (parts.length === 1) return transformPlain(s, rules);
  return parts.map((x) => (x.vendor ? x.text : transformPlain(x.text, rules))).join('');
}

/** Host del proveedor (los de `digitalisimo-vendor-free.php`): `elementor.com`, `go.elementor.com`, `x.elementor.red`… */
const VENDOR_HOST = String.raw`(?:[a-z0-9-]+\.)*elementor(?:\.[a-z0-9-]+)*\.(?:com|cloud|red|io|net)`;

/**
 * Hosts del proveedor en una URL (`https://go.elementor.com/x`, `//x.elementor.red`, también con `\/`) o sueltos
 * (`"elementor.com"`, `".elementor.com"`), con su ruta. La misma expresión que `Transform::VENDOR` en PHP.
 */
export const VENDOR_RE = new RegExp(String.raw`(?:(?:https?:)?(?:\\?\/){2})?(?<![\w$-])${VENDOR_HOST}(?![\w-])(?::\d+)?(?:(?:\\?\/|[?#])(?:[^\s"'` + '`' + String.raw`<>\\]|\\\/)*)?`, 'gi');

/** La cadena en tramos: los del proveedor (`vendor`), que no se tocan, y los demás. */
export function splitVendor(s) {
  const out = [];
  let last = 0;
  for (const m of s.matchAll(VENDOR_RE)) {
    if (m.index > last) out.push({ text: s.slice(last, m.index), vendor: false });
    out.push({ text: m[0], vendor: true });
    last = m.index + m[0].length;
  }
  if (last < s.length || !out.length) out.push({ text: s.slice(last), vendor: false });
  return out;
}

/** ¿Es una URL absoluta (con esquema o `//`)? Como la primera forma de `Transform::is_url()`. */
export function isAbsUrl(s) {
  return /^(?:https?:)?(?:\\?\/){2}[^\s]+$/i.test(s);
}

/** La consulta de una URL (los alias del escritorio y de la API, D-3), como `Transform::query()`. */
function query(s, rules) {
  const p = rules.prefijo || 'dg';
  return s.replace(/([?&;]page=)elementor/g, '$1digitalisimo-motor')
    .replace(/([?&;]action=)elementor(?![\w-])/g, '$1digitalisimo')
    .replace(/([?&;])elementor-preview=/g, `$1${p}-preview=`)
    .replace(/([?&;=]|%3D)elementor_/g, '$1digitalisimo_')
    .replace(/(\/wp-json(?:\\)?\/|rest_route=(?:\/|%2F))elementor(-[a-z]+|_[a-z0-9_]+)?(?=\\?\/|%2F|$|[?&#])/gi, '$1digitalisimo$2');
}

function urlStr(s, rules) {
  return query(s, rules).replace(/(#|%23)elementor-action/, `$1${rules.prefijo || 'dg'}-action`);
}

function transformPlain(s, rules) {
  if (!/elementor/i.test(s)) return s;
  const p = rules.prefijo || 'dg';
  s = s.replace(/(#|%23)elementor-action/g, `$1${p}-action`)
    .replace(/data-elementor-/g, `data-${p}-`)
    // URL del escritorio (los alias de Admin_Urls, D-3): page=elementor… → page=digitalisimo-motor…, action=elementor.
    .replace(/([?&;]page=)elementor/g, '$1digitalisimo-motor')
    .replace(/([?&;]action=)elementor(?![\w-])/g, '$1digitalisimo')
    .replace(/(?<![\w-])elementor(-[a-z]+)?(?=\\?\/)/g, 'digitalisimo$1')
    .replace(/elementor_/g, 'digitalisimo_');
  for (const [from, to] of idMap(rules)) {
    if (from === 'elementor' || from === 'elementor_') continue;
    if (s.includes(from)) s = s.replace(new RegExp(`(?<![\\w$])${esc(from)}(?![\\w$])`, 'g'), to);
  }
  return s.replace(/(?<![\w$])elementor(?![a-zA-Z0-9_$])/g, p)
    .replace(/(?<![\w$])Elementor(?![\w$])/g, 'DIGITALÍSIMO')
    .replace(/ELEMENTOR/g, 'DIGITALISIMO')
    .replace(/elementor(?=[A-Z])/g, 'digitalisimo')
    .replace(/Elementor/g, 'Digitalisimo')
    .replace(/elementor/gi, p);
}

/** JS: por contexto (analizador léxico). Los comentarios con «elementor» y los sourcemaps se quitan. */
export function transformJs(js, rules) {
  if (!/elementor/i.test(js)) return js.replace(/\n\/\/# sourceMappingURL=.*$/m, '');
  return lexJs(js.replace(/\n\/\/# sourceMappingURL=.*$/m, ''), (type, text, ctx) => {
    if (type === 'id') return transformIdent(text, rules, ctx);
    if (type === 'comment') return /elementor/i.test(text) ? (text.startsWith('//') ? '' : ' ') : text;
    if (type === 'code') return text;
    return transformStr(text, rules);
  });
}

/** Apariciones de «elementor» que quedan (sin distinguir mayúsculas). */
export function count(text) {
  return (text.match(/elementor/gi) || []).length;
}
