// Comparaciones de kit-roundtrip.sh (etapa K, §13.3). Funciones puras que usa scenarios.cjs y que también se pueden
// correr a mano sobre los JSON de $OUT:
//   node compare.mjs firma <sig-a.json> <sig-b.json> [escenario]      A1: firma de datos A frente a B (con known.json)
//   node compare.mjs expect <sig-b.json> <kit.expect.json> <add|fresh> A3: un kit de terceros frente a su «expect»
//   node compare.mjs inventario <controls.json> <references.json>     §9.2: controles con aspecto de referencia
//   node compare.mjs instantanea <antes.json> <despues.json> <modo>   A6/A8/A9/A10: tablas, opciones y archivos
import { readFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '../../..');
const readJson = (file) => JSON.parse(readFileSync(file, 'utf8'));

// ── Utilidades ────────────────────────────────────────────────────────────────────────────────────────────────
const isPlain = (v) => v && typeof v === 'object' && !Array.isArray(v);
/** Igualdad estructural con claves en cualquier orden; `[]` y `{}` vacíos son lo mismo (PHP no distingue). */
/** ¿Contiene `big` todo lo de `small`? Objetos por clave, listas por elemento (sin orden), escalares iguales. */
export function contains(big, small) {
  if (small === undefined || small === null) return true;
  if (Array.isArray(small)) return Array.isArray(big) && small.every((x) => big.some((y) => same(x, y)));
  if (isPlain(small)) return isPlain(big) && Object.keys(small).every((k) => contains(big[k], small[k]));
  return same(big, small);
}

export function same(a, b) {
  if (Array.isArray(a) && !a.length && isPlain(b) && !Object.keys(b).length) return true;
  if (Array.isArray(b) && !b.length && isPlain(a) && !Object.keys(a).length) return true;
  if (typeof a !== typeof b || Array.isArray(a) !== Array.isArray(b)) return a == b && typeof a !== 'object'; // eslint-disable-line eqeqeq
  if (Array.isArray(a)) return a.length === b.length && a.every((x, i) => same(x, b[i]));
  if (isPlain(a)) {
    const ka = Object.keys(a);
    const kb = Object.keys(b);
    return ka.length === kb.length && ka.every((k) => Object.prototype.hasOwnProperty.call(b, k) && same(a[k], b[k]));
  }
  return a === b;
}

/** Normaliza un árbol de elementos de la firma: sin `isInner: false` y con ajustes vacíos como `{}`. */
export function normalizeTree(value) {
  if (Array.isArray(value)) return value.map(normalizeTree);
  if (!isPlain(value)) return value;
  const out = {};
  for (const [k, v] of Object.entries(value)) {
    if (k === 'isInner' && v === false) continue;
    out[k] = k === 'settings' && Array.isArray(v) && !v.length ? {} : normalizeTree(v);
  }
  return out;
}

/** Diferencias entre dos valores como rutas `a.b.0.c`. */
export function diff(a, b, path = '', out = []) {
  if (same(a, b)) return out;
  if (isPlain(a) && isPlain(b)) {
    for (const k of new Set([...Object.keys(a), ...Object.keys(b)])) diff(a[k], b[k], path ? `${path}.${k}` : k, out);
    return out;
  }
  if (Array.isArray(a) && Array.isArray(b) && a.length === b.length) {
    a.forEach((x, i) => diff(x, b[i], `${path}.${i}`, out));
    return out;
  }
  out.push({ path, a: a === undefined ? '(no existe)' : a, b: b === undefined ? '(no existe)' : b });
  return out;
}

const at = (value, path) => String(path).split('.').reduce((node, key) => (node === undefined || node === null ? undefined : node[key]), value);
const short = (v) => { const s = JSON.stringify(v); return s && s.length > 160 ? s.slice(0, 157) + '…' : s; };

// ── known.json: diferencias aceptadas, cada una con su motivo; una entrada sin usar falla ───────────────────────
export function loadKnown(file = join(HERE, 'known.json')) {
  const data = existsSync(file) ? readJson(file) : { diferencias: [] };
  return (data.diferencias || []).map((k, i) => {
    if (!k.ruta || !k.motivo) throw new Error(`known.json[${i}]: falta «ruta» o «motivo»`);
    // «valor_a» y «valor_b» (opcionales) limitan la entrada a esos valores: expresión regular sobre el JSON del valor,
    // o «(no existe)» si falta.
    return { ...k, re: new RegExp(k.ruta), reA: k.valor_a ? new RegExp(k.valor_a) : null, reB: k.valor_b ? new RegExp(k.valor_b) : null, used: 0 };
  });
}
const shown = (v) => (v === undefined || v === '(no existe)' ? '(no existe)' : JSON.stringify(v));
export function applyKnown(diffs, known, scenario) {
  const left = [];
  for (const d of diffs) {
    const entry = known.find((k) => (!k.escenarios || k.escenarios.includes(scenario)) && k.re.test(d.path)
      && (!k.reA || k.reA.test(shown(d.a))) && (!k.reB || k.reB.test(shown(d.b))));
    if (entry) entry.used++;
    else left.push(d);
  }
  return left;
}
export const unusedKnown = (known, scenarios) => known.filter((k) => !k.used && (!k.escenarios || k.escenarios.some((s) => scenarios.includes(s))));

// ── A1: firma de A frente a firma de B ───────────────────────────────────────────────────────────────────────
export function compareSignatures(sigA, sigB, { known = [], scenario = 'R1' } = {}) {
  // La ruta y el slug no se comparan (el importador crea desde el título; «la ruta cambió» va al informe), ni los
  // comentarios ni las contraseñas, que nunca viajan (not-exported.json lo comprueba en el kit).
  const pick = (sig) => ({
    documents: Object.fromEntries(Object.entries(sig.documents || {}).map(([k, d]) => { const { path, slug, ...rest } = d; return [k, { ...rest, data: normalizeTree(d.data) }]; })),
    posts: Object.fromEntries(Object.entries(sig.posts || {}).map(([k, p]) => { const { comments, password, ...rest } = p; return [k, rest]; })),
    snippets: sig.snippets, attachments: sig.attachments, terms: sig.terms, menus: sig.menus, kit: sig.kit, fonts: sig.fonts, icons: sig.icons,
    options: sig.options, files: sig.files, counts: sig.counts, presets: sig.presets, indexes: sig.indexes,
  });
  const problems = applyKnown(diff(pick(sigA), pick(sigB)), known, scenario).map((d) => `${d.path}: A ${short(d.a)} · B ${short(d.b)}`);
  for (const id of sigB.unresolved || []) problems.push(`unresolved: ${id} (un ID del origen que no se tradujo, CV-8)`);
  for (const issue of sigB.integrity || []) problems.push(`integridad: ${issue} (CC-8)`);
  // «Sólo quedan las URL de A que se declaran» (A1): cada una pasa por known.json como «url-de-A:<dónde>».
  for (const d of applyKnown((sigB.origin_urls || []).map((where) => ({ path: `url-de-A:${where}` })), known, scenario)) problems.push(`URL del host de A en B: ${d.path.slice(9)}`);
  return problems;
}

// ── A3: un kit de terceros frente a su «expect» (CV-9) ────────────────────────────────────────────────────────
function want(value, mode) {
  return isPlain(value) && ('add' in value || 'fresh' in value) ? value[mode] : value;
}
function matches(actual, expected) {
  if (isPlain(expected) && 're' in expected) return typeof actual === 'string' && new RegExp(expected.re).test(actual);
  if (isPlain(expected) && 'never_empty' in expected) {
    const list = Array.isArray(actual) ? actual : [actual];
    return list.length > 0 && list.every((v) => v !== '' && v !== null && v !== undefined && !/^user:/.test(String(v)) && !/^unresolved:/.test(String(v)));
  }
  return same(actual, expected);
}
export function checkExpect(sigB, expect, { mode = 'add', titles = {} } = {}) {
  const problems = [];
  for (const [doc, e] of Object.entries(expect.documents || {})) {
    const key = e.key || titles[doc];
    const d = key ? (sigB.documents || {})[key] : undefined;
    if (!d) { problems.push(`${doc}: no está en B (esperado ${key})`); continue; }
    const status = want(e.status, mode);
    if (status !== undefined && d.status !== status) problems.push(`${doc}: estado ${d.status}, esperado ${status} (D8)`);
    if (e.doc_type && d.doc_type !== e.doc_type) problems.push(`${doc}: doc_type ${d.doc_type}, esperado ${e.doc_type}`);
    if (e.conditions && !same(d.conditions || [], e.conditions)) problems.push(`${doc}: condiciones ${short(d.conditions || [])}, esperado ${short(e.conditions)} (§9.5)`);
    if (e.data && !same(normalizeTree(d.data), normalizeTree(e.data))) problems.push(`${doc}: _elementor_data distinto: ${diff(normalizeTree(e.data), normalizeTree(d.data)).slice(0, 3).map((x) => `${x.path} ${short(x.a)}→${short(x.b)}`).join('; ')}`);
    for (const [path, value] of Object.entries(e.checks || {})) {
      const actual = at(normalizeTree(d.data), path) ?? at(d, path);
      if (!matches(actual, value)) problems.push(`${doc}: ${path} = ${short(actual)}, esperado ${short(value)}`);
    }
    for (const [type, n] of Object.entries(e.types || {})) if ((d.counts || {})[type] !== n) problems.push(`${doc}: ${n} elemento(s) ${type} esperados, hay ${(d.counts || {})[type] || 0} (A4)`);
    for (const [key2, value] of Object.entries(e.meta || {})) if (!same((d.meta || {})[key2], value)) problems.push(`${doc}: meta ${key2} = ${short((d.meta || {})[key2])}, esperado ${short(value)}`);
    for (const key2 of e.meta_absent || []) if ((d.meta || {})[key2] !== undefined || (d.digitalisimo || {})[key2] !== undefined) problems.push(`${doc}: la meta ${key2} no debía llegar`);
    if (e.original_type && (d.digitalisimo || {})._digitalisimo_original_type !== e.original_type) problems.push(`${doc}: _digitalisimo_original_type ${short((d.digitalisimo || {})._digitalisimo_original_type)}, esperado ${e.original_type} (CC-4)`);
    if (e.review && !(sigB.review || []).includes(key)) problems.push(`${doc}: debía quedar en «Revisar antes de publicar» (A14)`);
  }
  for (const [key, e] of Object.entries(expect.posts || {})) {
    const p = (sigB.posts || {})[key];
    if (!p) { problems.push(`${key}: la entrada del WXR no está en B`); continue; }
    const status = want(e.status, mode);
    if (status !== undefined && p.status !== status) problems.push(`${key}: estado ${p.status}, esperado ${status} (D8)`);
    for (const text of e.content_has || []) if (!String(p.content).includes(text)) problems.push(`${key}: post_content sin ${text} (CC-6)`);
    for (const k of e.meta_absent || []) if (p.meta[k] !== undefined) problems.push(`${key}: la meta ${k} no debía llegar (objeto serializado)`);
    for (const k of e.elementor_meta_absent || []) if ((p.elementor_meta || []).includes(k)) problems.push(`${key}: ${k} llegó por el WXR (V-20)`);
    if (e.thumbnail && p.thumbnail !== e.thumbnail) problems.push(`${key}: imagen destacada ${p.thumbnail}, esperado ${e.thumbnail}`);
    if (e.comments_without_contact && (p.comments || []).some((c) => c.email || c.ip)) problems.push(`${key}: un comentario conserva correo o IP (CR-14)`);
    if (e.password !== undefined && (p.password || '') !== e.password) problems.push(`${key}: contraseña ${short(p.password || '')}, esperada ${short(e.password)} (una entrada protegida sigue protegida: A14)`);
  }
  for (const [slug, e] of Object.entries(expect.menus || {})) {
    const m = (sigB.menus || {})[slug];
    if (!m) { problems.push(`menú ${slug}: no está en B`); continue; }
    if (e.items !== undefined && m.items.length !== e.items) problems.push(`menú ${slug}: ${m.items.length} elementos, esperado ${e.items}`);
    for (const url of e.urls || []) if (!m.items.some((i) => i.url === url)) problems.push(`menú ${slug}: falta la URL ${url} (CC-6)`);
    if (e.object_classes !== undefined && m.items.some((i) => i.classes_object)) problems.push(`menú ${slug}: un _menu_item_classes con objeto llegó (V-21)`);
  }
  for (const [title, e] of Object.entries(expect.fonts || {})) {
    const f = (sigB.fonts || {})[title];
    if (!f) { problems.push(`fuente ${title}: no está en B`); continue; }
    if (e.local && !f.faces.every((face) => Object.entries(face).every(([k, v]) => !['woff', 'woff2', 'ttf', 'svg', 'eot'].includes(k) || /^media:/.test(String(v))))) problems.push(`fuente ${title}: un archivo no es un adjunto local (§7.8 #2)`);
    for (const bad of e.face_lacks || []) if (String(f.face_css).includes(bad)) problems.push(`fuente ${title}: font_face contiene ${bad} (§7.8 #1)`);
  }
  for (const [name, e] of Object.entries(expect.icons || {})) {
    const s = (sigB.icons || {})[name];
    if (s && e.external === false && /https?:\/\//.test(JSON.stringify(s.config))) problems.push(`iconos ${name}: la configuración guarda una URL externa (CR-8)`);
  }
  for (const [title, e] of Object.entries(expect.snippets || {})) {
    const s = (sigB.snippets || {})[title];
    if (!s) { problems.push(`fragmento ${title}: no está en B`); continue; }
    for (const k of ['status', 'location', 'priority']) if (e[k] !== undefined && String(s[k]) !== String(e[k])) problems.push(`fragmento ${title}: ${k} ${s[k]}, esperado ${e[k]} (§7.8 #5)`);
    if (e.conditions && !same(s.conditions || [], e.conditions)) problems.push(`fragmento ${title}: condiciones ${short(s.conditions)}, esperado ${short(e.conditions)}`);
  }
  for (const [key, e] of Object.entries(expect.terms || {})) {
    const t = (sigB.terms || {})[key];
    if (!t) { problems.push(`término ${key}: no está en B`); continue; }
    for (const [k, v] of Object.entries(e.meta || {})) if (!same(t.meta[k], v)) problems.push(`término ${key}: meta ${k} = ${short(t.meta[k])}, esperado ${short(v)} (B18)`);
  }
  const kit = want(expect.kit || {}, mode) || {};
  const settings = ((sigB.kit || {}).settings) || {};
  for (const item of kit.custom_colors_include || []) {
    if (!(settings.custom_colors || []).some((c) => c._id === item._id && String(c.color).toLowerCase() === String(item.color).toLowerCase())) problems.push(`kit: falta el color ${item._id} ${item.color} (§9.4)`);
  }
  if (kit.custom_colors_from_system && (settings.custom_colors || []).filter((c) => / \(kit\)$/.test(c.title || '')).length < kit.custom_colors_from_system) problems.push('kit: los colores del sistema que usa el contenido no entraron como «(kit)» (§9.4)');
  if (kit.custom_colors && !same(settings.custom_colors, kit.custom_colors)) problems.push(`kit: colores ${short(settings.custom_colors)}, esperado ${short(kit.custom_colors)}`);
  const opts = want(expect.options || {}, mode) || {};
  for (const [k, v] of Object.entries(opts)) if (k !== 'unchanged' && (sigB.options || {})[k] !== v) problems.push(`opción ${k} = ${(sigB.options || {})[k]}, esperado ${v} (A6)`);
  for (const [path, value] of Object.entries(expect.files || {})) if ((sigB.files || {})[path] !== value) problems.push(`archivo ${path}: ${(sigB.files || {})[path] || 'falta'} (B4)`);
  return problems;
}

// ── §9.2: inventario en tiempo de ejecución ───────────────────────────────────────────────────────────────────
const LOOKS_LIKE = /(_id|_ids|template|menu|navbar|user|_value)$/;
// Lo que ya traduce el núcleo y K no duplica (§9.2): los widgets con método propio sólo traducen lo suyo
// (Loop Grid, template_id: E:modules/loop-builder/widgets/loop-grid.php:596-602; Plantilla; Nav Menu), y los que
// usan el rasgo On_Import de PRO, los controles Query con post_ids o term_ids en el nombre, o post_id, template_id y
// popup (E:base/on-import-trait.php:86-108). Un widget sin el rasgo (`import` de controls.json) no tiene nada cubierto.
const CORE_COVERED = new Set(['loop-grid|template_id', 'template|template_id', 'nav-menu|menu']);
const coreCovered = (type, path, control, widget = {}) => CORE_COVERED.has(`${type}|${path}`)
  || ((widget.import === undefined || widget.import === 'rasgo') && control.type === 'query' && !path.includes('.') && (/post_ids|term_ids/.test(path) || ['post_id', 'template_id', 'popup'].includes(path)));
export function checkInventory(controls, references, noReference) {
  const declared = new Set();
  const add = (type, path) => declared.add(`${type}|${String(path).replace(/\.\*\./g, '.').replace(/\.\*$/, '')}`);
  const all = (references || {}).all || {};
  for (const [scope, byType] of Object.entries(all)) {
    if (scope !== 'element') continue;
    for (const [type, refs] of Object.entries(byType)) for (const ref of refs) add(type, ref.path);
  }
  for (const [type, refs] of Object.entries((references || {}).derived || {})) for (const ref of Array.isArray(refs) ? refs : []) add(type, ref.path);
  const exempt = new Set((noReference.controles || []).map((e) => `${e.widget}|${e.control}`));
  const problems = [];
  const visit = (type, name, control, prefix = '', widget = {}) => {
    const path = prefix ? `${prefix}.${name}` : name;
    if (control.fields) for (const [field, sub] of Object.entries(control.fields)) visit(type, field, sub, path, widget);
    const looks = ['query', 'template_query'].includes(control.type) || (['select2', 'select', 'number'].includes(control.type) && LOOKS_LIKE.test(name));
    if (!looks || control.type === 'url') return;
    const ok = declared.has(`${type}|${path}`) || declared.has(`*|${path}`) || exempt.has(`${type}|${path}`) || exempt.has(`*|${path}`) || coreCovered(type, path, control, widget);
    if (!ok) problems.push(`${type}: control ${path} (${control.type}) parece una referencia y no está en el registro de K ni en no-reference.json`);
  };
  for (const [type, widget] of Object.entries((controls || {}).widgets || {})) {
    if (widget.error) { problems.push(`${type}: get_controls() falló (${widget.error})`); continue; }
    for (const [name, control] of Object.entries(widget.controls || {})) visit(type, name, control, '', widget);
  }
  for (const [type, element] of Object.entries((controls || {}).elements || {})) {
    for (const [name, control] of Object.entries(element.controls || {})) visit('*', name, control);
  }
  // Un control no puede ser a la vez referencia (registro de K) y «no referencia» (no-reference.json).
  for (const key of exempt) if (declared.has(key)) problems.push(`no-reference.json: ${key.replace('|', ': ')} también está en el registro de K como referencia; sobra una de las dos`);
  return [...new Set(problems)];
}

// ── A6, A8, A9, A10: instantáneas ─────────────────────────────────────────────────────────────────────────────
/**
 * @param {object} before  Instantánea anterior (mu-kit.php snapshot).
 * @param {object} after   Instantánea posterior.
 * @param {object} opts    { blogs: [ids] a comparar, allowOptions: [regex], allowTables: { tabla: true } (filas nuevas
 *                         permitidas), uploads: 'igual'|'sin-nuevos'|'libre', trees: true }
 */
export function compareSnapshots(before, after, opts = {}) {
  const problems = [];
  const blogs = opts.blogs || Object.keys(before.sites || {});
  const allow = (opts.allowOptions || []).map((r) => (r instanceof RegExp ? r : new RegExp(r)));
  for (const blog of blogs) {
    const a = (before.sites || {})[blog];
    const b = (after.sites || {})[blog];
    if (!a || !b) { problems.push(`sitio ${blog}: falta en una instantánea`); continue; }
    for (const name of new Set([...Object.keys(a.options), ...Object.keys(b.options)])) {
      if (a.options[name] === b.options[name] || allow.some((r) => r.test(name))) continue;
      problems.push(`sitio ${blog}: la opción ${name} cambió (${short(a.options[name])} → ${short(b.options[name])})`);
    }
    for (const table of new Set([...Object.keys(a.tables), ...Object.keys(b.tables)])) {
      const ta = a.tables[table] || { rows: 0, digest: '', hashes: {} };
      const tb = b.tables[table] || { rows: 0, digest: '', hashes: {} };
      if (ta.digest === tb.digest) continue;
      const allowed = (opts.allowTables || {})[table];
      if (allowed === 'libre') continue;
      const removed = Object.keys(ta.hashes).filter((k) => !(k in tb.hashes));
      const added = Object.keys(tb.hashes).filter((k) => !(k in ta.hashes));
      const changed = Object.keys(ta.hashes).filter((k) => k in tb.hashes && ta.hashes[k] !== tb.hashes[k]);
      if (allowed === 'nuevas' && !removed.length && !changed.length) continue;
      problems.push(`sitio ${blog}: tabla ${table} cambió (${ta.rows} → ${tb.rows} filas; ${added.length} nuevas, ${removed.length} quitadas, ${changed.length} cambiadas${changed.length ? ': ' + changed.slice(0, 3).join(', ') : ''})`);
    }
    // Cachés derivadas (mu-kit.php, DIGI_K_CACHES), por contenido: «igual» (por defecto), «nuevas» (sólo entradas
    // añadidas: «agregar» suma plantillas, nunca toca las del sitio) o «libre». La de iconos puede quedar borrada:
    // el constructor la regenera bajo demanda.
    const cacheMode = opts.caches || 'igual';
    for (const name of new Set([...Object.keys(a.caches || {}), ...Object.keys(b.caches || {})])) {
      const ca = (a.caches || {})[name];
      const cb = (b.caches || {})[name];
      if (cacheMode === 'libre' || same(ca, cb)) continue;
      if (cacheMode === 'nuevas' && contains(cb, ca)) continue;
      if (name === 'elementor_custom_icon_sets_config' && cb === undefined) continue;
      problems.push(`sitio ${blog}: la caché ${name} cambió (${cacheMode === 'nuevas' ? 'perdió o cambió entradas del sitio' : 'otro contenido'}: ${short(ca === undefined ? null : ca)} → ${short(cb === undefined ? null : cb)})`);
    }
    // Metas del kit activo: iguales salvo que el escenario las compare campo a campo (opts.kit = 'libre').
    if (opts.kit !== 'libre') {
      for (const key of new Set([...Object.keys(a.kit_meta || {}), ...Object.keys(b.kit_meta || {})])) {
        if ((a.kit_meta || {})[key] !== (b.kit_meta || {})[key]) problems.push(`sitio ${blog}: la meta del kit ${key} cambió`);
      }
    }
    const mode = opts.uploads || 'igual';
    if (mode !== 'libre') {
      // Temporales de K (§5.3): uploads/digitalisimo-elements/tmp/ con sus guardas, y cada tmp/<token>/ con las suyas
      // mientras haya una limpieza programada (la copia preparada espera a «Aplicar los ajustes del kit» o a 24 h).
      // La copia extraída del núcleo (uploads/elementor/tmp/) no tiene excepción: se borra al terminar.
      const tmpOk = (file) => {
        if (/^digitalisimo-elements\/tmp\/(\.htaccess|index\.php)$/.test(file)) return true;
        const m = file.match(/^digitalisimo-elements\/tmp\/([0-9a-f]{64})\//);
        return !!m && !!b.uploads[`digitalisimo-elements/tmp/${m[1]}/.htaccess`] && !!b.uploads[`digitalisimo-elements/tmp/${m[1]}/index.php`] && (b.kit_cleanup || []).length > 0;
      };
      for (const file of new Set([...Object.keys(a.uploads), ...Object.keys(b.uploads)])) {
        if (a.uploads[file] === b.uploads[file]) continue;
        if (!a.uploads[file] && tmpOk(file)) continue;
        if (mode === 'sin-nuevos' && a.uploads[file] && !b.uploads[file]) continue;
        problems.push(`sitio ${blog}: uploads/${file} ${a.uploads[file] ? (b.uploads[file] ? 'cambió' : 'desapareció') : 'es nuevo'}`);
      }
    }
  }
  if (opts.trees !== false) {
    for (const tree of ['plugins', 'themes', 'mu']) {
      for (const name of new Set([...Object.keys(before[tree] || {}), ...Object.keys(after[tree] || {})])) {
        if ((before[tree] || {})[name] !== (after[tree] || {})[name]) problems.push(`${tree}/${name} cambió: nunca se instala ni se cambia nada (A6, D5)`);
      }
    }
  }
  if (before.sitemeta && after.sitemeta) {
    for (const key of new Set([...Object.keys(before.sitemeta), ...Object.keys(after.sitemeta)])) {
      if (before.sitemeta[key] !== after.sitemeta[key] && !allow.some((r) => r.test(key))) problems.push(`sitemeta ${key} cambió (A9, CV-24)`);
    }
  }
  return problems;
}

// ── not-exported.json: lo que nunca viaja en un kit propio ───────────────────────────────────────────────────
export function loadNotExported(file = join(HERE, 'not-exported.json')) {
  const data = readJson(file);
  return (data.entradas || []).map((e, i) => {
    if (!e.clave || !e.buscar || !e.motivo) throw new Error(`not-exported.json[${i}]: falta «clave», «buscar» o «motivo»`);
    return e;
  });
}

// ── CLI ───────────────────────────────────────────────────────────────────────────────────────────────────────
if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const [cmd, ...args] = process.argv.slice(2);
  let problems = [];
  if (cmd === 'firma') problems = compareSignatures(readJson(args[0]), readJson(args[1]), { known: loadKnown(), scenario: args[2] || 'R1' });
  else if (cmd === 'expect') problems = checkExpect(readJson(args[0]), readJson(args[1]), { mode: args[2] || 'add' });
  else if (cmd === 'inventario') problems = checkInventory(readJson(args[0]), readJson(args[1]), readJson(join(ROOT, 'tests/sources/kits/no-reference.json')));
  else if (cmd === 'instantanea') problems = compareSnapshots(readJson(args[0]), readJson(args[1]), { allowOptions: args[2] === 'fresh' ? ['^elementor_active_kit$', '^page_on_front$', '^show_on_front$'] : [] });
  else { console.error('Uso: node compare.mjs firma|expect|inventario|instantanea …'); process.exit(2); }
  for (const p of problems) console.log(`- ${p}`);
  console.log(problems.length ? `${problems.length} diferencia(s).` : 'Sin diferencias.');
  process.exit(problems.length ? 1 : 0);
}
