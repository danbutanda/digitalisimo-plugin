// F7b, frente D: los cuatro formularios de los filtros ampliados con `assets/js/filters-form.js` y el coordinador de
// F4-Woo (`assets/js/filters.js`), V.5, C2-13 y C2-30 de la especificación. Mismo DOM mínimo que
// `tests/elements-woo-filters.mjs`, relojes falsos y dobles de `fetch`, del núcleo y del módulo de PRO:
// - Cada formulario se registra en el coordinador con el tipo de su `data-type` y sus claves `{prefijo}{widget}`
//   (`-max` en el rango); los de F4-Woo siguen con sus tipos; `element_ready` de los cuatro widgets.
// - Buscador: no busca al teclear, sólo al enviar (botón visible con el coordinador); «Limpiar» vacía y refresca.
// - Campo: rango numérico y de fechas por botón; lista de casillas con «O»; orden por desplegable; por página con
//   botones de una sola selección; todo en una sola petición junto al precio de F4-Woo.
// - `digi_ctx`: sólo si el envoltorio del bucle lleva `data-digi-ctx` con un número (C2-13).
// - `popstate` vuelve a escribir los cuatro formularios; destino «Archivo»: GET con `digi_f` y listas con comas.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const JS = (name) => new URL(`../digitalisimo-elements/assets/js/${name}`, import.meta.url);
const source = { coordinator: readFileSync(JS('filters.js'), 'utf8'), form: readFileSync(JS('filters-form.js'), 'utf8') };
const CSS = readFileSync(new URL('../digitalisimo-elements/modules/digitalisimo-filters/assets/f7b-filters.css', import.meta.url), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const same = (a, b, message) => { assert.deepEqual(a, b, message); checks++; };

// ---------------------------------------------------------------------------------------------------------------
// DOM mínimo: árbol, atributos, dataset, classList, eventos con captura y burbuja, selectores y un analizador de HTML.
const VOID = new Set(['input', 'br', 'img', 'meta', 'link', 'hr']);
const decode = (s) => s.replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&#038;|&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>');
const camel = (s) => s.replace(/-([a-z])/g, (m, c) => c.toUpperCase());
const kebab = (s) => s.replace(/[A-Z]/g, (c) => '-' + c.toLowerCase());
function splitTop(s, sep) {
  const out = []; let depth = 0, quote = '', cur = '';
  for (const ch of s) {
    if (quote) { if (ch === quote) quote = ''; cur += ch; continue; }
    if (ch === '"' || ch === "'") { quote = ch; cur += ch; continue; }
    if (ch === '[' || ch === '(') depth++;
    if (ch === ']' || ch === ')') depth--;
    if (ch === sep && !depth) { out.push(cur); cur = ''; continue; }
    cur += ch;
  }
  out.push(cur);
  return out.map((x) => x.trim()).filter(Boolean);
}
function parseCompound(str) {
  const c = { tag: '', classes: [], attrs: [], nots: [], checked: false, scope: false };
  const re = /^([a-zA-Z][\w-]*)|\.([\w-]+)|\[([\w-]+)(?:([~^]?=)("[^"]*"|'[^']*'|[^\]]+))?\]|:not\(([^()]*(?:\([^()]*\))?[^()]*)\)|:checked|:scope/g;
  let m; let rest = str;
  while ((m = re.exec(str))) {
    rest = rest.replace(m[0], '');
    if (m[1]) c.tag = m[1].toUpperCase();
    else if (m[2]) c.classes.push(m[2]);
    else if (m[3]) c.attrs.push({ name: m[3], op: m[4] || '', value: m[5] ? m[5].replace(/^["']|["']$/g, '') : '' });
    else if (m[6] !== undefined) c.nots.push(parseList(m[6]));
    else if (m[0] === ':checked') c.checked = true;
    else if (m[0] === ':scope') c.scope = true;
  }
  if (rest.trim()) throw new Error('Selector no admitido por el DOM de prueba: ' + str);
  return c;
}
function parseComplex(str) {
  const parts = []; let comb = ' ';
  for (const tok of str.replace(/\s*>\s*/g, ' > ').split(/\s+/).filter(Boolean)) {
    if (tok === '>') { comb = '>'; continue; }
    parts.push({ c: parseCompound(tok), comb }); comb = ' ';
  }
  return parts;
}
const parseList = (s) => splitTop(s, ',').map(parseComplex);
function matchCompound(el, c, scope) {
  if (el.nodeType !== 1) return false;
  if (c.scope && el !== scope) return false;
  if (c.tag && el.tagName !== c.tag) return false;
  for (const k of c.classes) if (!el.classList.contains(k)) return false;
  for (const a of c.attrs) {
    if (!el.hasAttribute(a.name)) return false;
    if (a.op === '=' && el.getAttribute(a.name) !== a.value) return false;
  }
  if (c.checked && !el.checked) return false;
  for (const list of c.nots) if (matchList(el, list, scope)) return false;
  return true;
}
function matchFrom(el, parts, i, scope) {
  if (!matchCompound(el, parts[i].c, scope)) return false;
  if (i === 0) return true;
  if (parts[i].comb === '>') return !!el.parentNode && matchFrom(el.parentNode, parts, i - 1, scope);
  for (let p = el.parentNode; p && p.nodeType === 1; p = p.parentNode) if (matchFrom(p, parts, i - 1, scope)) return true;
  return false;
}
const matchList = (el, list, scope) => list.some((parts) => matchFrom(el, parts, parts.length - 1, scope));

class Ev {
  constructor(type, init = {}) { this.type = type; this.bubbles = init.bubbles !== false; this.defaultPrevented = false; Object.assign(this, init); }
  preventDefault() { this.defaultPrevented = true; }
  stopPropagation() {}
}
class Text { constructor(data) { this.nodeType = 3; this.data = data; this.parentNode = null; } get textContent() { return this.data; } }
class Node {
  constructor(tag, doc) {
    this.nodeType = 1; this.tagName = tag.toUpperCase(); this.attributes = new Map(); this.childNodes = []; this.parentNode = null; this.listeners = []; this.ownerDocument = doc;
    this.checked = false; this._value = undefined;
    this.dataset = new Proxy({}, {
      get: (t, k) => (typeof k === 'string' && this.hasAttribute('data-' + kebab(k)) ? this.getAttribute('data-' + kebab(k)) : undefined),
      set: (t, k, v) => { this.setAttribute('data-' + kebab(k), String(v)); return true; },
    });
    const self = this;
    this.classList = {
      contains: (c) => (self.getAttribute('class') || '').split(/\s+/).includes(c),
      add: (...cs) => cs.forEach((c) => { if (!self.classList.contains(c)) self.setAttribute('class', ((self.getAttribute('class') || '') + ' ' + c).trim()); }),
      remove: (...cs) => cs.forEach((c) => self.setAttribute('class', (self.getAttribute('class') || '').split(/\s+/).filter((x) => x && x !== c).join(' '))),
      toggle: (c, on) => { const want = on === undefined ? !self.classList.contains(c) : !!on; if (want) self.classList.add(c); else self.classList.remove(c); return want; },
    };
  }
  getAttribute(n) { return this.attributes.has(n) ? this.attributes.get(n) : null; }
  setAttribute(n, v) { this.attributes.set(n, String(v)); }
  removeAttribute(n) { this.attributes.delete(n); }
  hasAttribute(n) { return this.attributes.has(n); }
  get className() { return this.getAttribute('class') || ''; }
  set className(v) { this.setAttribute('class', v); }
  get type() { return (this.getAttribute('type') || (this.tagName === 'INPUT' ? 'text' : '')).toLowerCase(); }
  get name() { return this.getAttribute('name') || ''; }
  get value() {
    if (this.tagName === 'SELECT') { const opts = this.querySelectorAll('option'); const sel = opts.find((o) => o.selected) || opts[0]; return sel ? sel.value : ''; }
    if (this.tagName === 'OPTION') return this.getAttribute('value') ?? this.textContent;
    return this._value ?? this.getAttribute('value') ?? '';
  }
  set value(v) {
    if (this.tagName === 'SELECT') { this.querySelectorAll('option').forEach((o) => { o.selected = o.value === String(v); }); return; }
    this._value = String(v);
  }
  get children() { return this.childNodes.filter((n) => n.nodeType === 1); }
  get firstElementChild() { return this.children[0] || null; }
  get nextSibling() { const s = this.parentNode ? this.parentNode.childNodes : []; return s[s.indexOf(this) + 1] || null; }
  get textContent() { return this.childNodes.map((n) => n.textContent).join(''); }
  set textContent(v) { this.childNodes.forEach((n) => { n.parentNode = null; }); this.childNodes = v ? [Object.assign(new Text(String(v)), { parentNode: this })] : []; }
  set innerHTML(html) { this.textContent = ''; parseInto(this, html, this.ownerDocument); }
  get innerHTML() { return this.childNodes.map((n) => (n.nodeType === 3 ? n.data.replace(/&/g, '&amp;').replace(/</g, '&lt;') : `<${n.tagName.toLowerCase()}${[...n.attributes].map(([k, v]) => ` ${k}="${v.replace(/&/g, '&amp;').replace(/"/g, '&quot;')}"`).join('')}>${VOID.has(n.tagName.toLowerCase()) ? '' : n.innerHTML + `</${n.tagName.toLowerCase()}>`}`)).join(''); }
  _detach(n) { if (n.parentNode) { const s = n.parentNode.childNodes; s.splice(s.indexOf(n), 1); } n.parentNode = this; }
  appendChild(n) { this._detach(n); this.childNodes.push(n); return n; }
  insertBefore(n, ref) { this._detach(n); const i = ref ? this.childNodes.indexOf(ref) : -1; if (i < 0) this.childNodes.push(n); else this.childNodes.splice(i, 0, n); return n; }
  replaceChild(n, old) { const i = this.childNodes.indexOf(old); if (i < 0) throw new Error('replaceChild: no es hijo'); this._detach(n); this.childNodes.splice(this.childNodes.indexOf(old), 1, n); old.parentNode = null; return old; }
  contains(n) { for (let p = n; p; p = p.parentNode) if (p === this) return true; return false; }
  descendants() { const out = []; const walk = (n) => n.children.forEach((c) => { out.push(c); walk(c); }); walk(this); return out; }
  querySelectorAll(sel) { const list = parseList(sel); return this.descendants().filter((el) => matchList(el, list, this)); }
  querySelector(sel) { return this.querySelectorAll(sel)[0] || null; }
  matches(sel) { return matchList(this, parseList(sel), null); }
  closest(sel) { const list = parseList(sel); for (let p = this; p && p.nodeType === 1; p = p.parentNode) if (matchList(p, list, null)) return p; return null; }
  addEventListener(type, fn, capture) { this.listeners.push({ type, fn, capture: capture === true || !!(capture && capture.capture) }); }
  dispatchEvent(ev) {
    const path = []; for (let p = this; p; p = p.parentNode || (p === this.ownerDocument.documentElement ? this.ownerDocument : null)) path.push(p);
    ev.target = this;
    for (const node of [...path].reverse()) node.listeners.filter((l) => l.type === ev.type && l.capture).forEach((l) => l.fn.call(node, ev));
    for (const node of ev.bubbles ? path : [this]) node.listeners.filter((l) => l.type === ev.type && !l.capture).forEach((l) => l.fn.call(node, ev));
    return !ev.defaultPrevented;
  }
  click() { return this.dispatchEvent(new Ev('click')); }
  focus() { this.ownerDocument.activeElement = this; }
}
function parseInto(parent, html, doc) {
  const re = /<!--[\s\S]*?-->|<\/([a-zA-Z][\w-]*)\s*>|<([a-zA-Z][\w-]*)((?:\s+[^\s=>/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*\/?>|([^<]+)/g;
  const stack = [parent]; let m;
  while ((m = re.exec(html))) {
    const top = stack[stack.length - 1];
    if (m[1]) { while (stack.length > 1 && stack.pop().tagName !== m[1].toUpperCase()); continue; }
    if (m[2]) {
      const el = new Node(m[2], doc);
      for (const a of (m[3] || '').matchAll(/([^\s=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?/g)) el.setAttribute(a[1], decode(a[2] ?? a[3] ?? a[4] ?? ''));
      if (el.hasAttribute('checked')) el.checked = true;
      if (el.tagName === 'OPTION' && el.hasAttribute('selected')) el.selected = true;
      top.appendChild(el);
      if (!VOID.has(m[2].toLowerCase())) stack.push(el);
      continue;
    }
    if (m[4] && m[4].trim()) top.appendChild(new Text(decode(m[4])));
  }
}
function makeDocument(html) {
  const doc = { listeners: [], activeElement: null, readyState: 'complete', nodeType: 9 };
  const root = new Node('html', doc);
  doc.documentElement = root;
  doc.body = root;
  doc.createElement = (t) => new Node(t, doc);
  doc.querySelectorAll = (s) => root.querySelectorAll(s);
  doc.querySelector = (s) => root.querySelector(s);
  doc.addEventListener = (type, fn, capture) => doc.listeners.push({ type, fn, capture: capture === true });
  doc.dispatchEvent = (ev) => { doc.events = (doc.events || []).concat(ev.type); return true; };
  doc.contains = (n) => root.contains(n);
  root.parentNode = null;
  root.innerHTML = html;
  doc.activeElement = root;
  return doc;
}
class FormDataDouble {
  constructor(form) {
    this.entries = form.querySelectorAll('input,select').filter((i) => i.name && !i.hasAttribute('disabled') && (!['checkbox', 'radio'].includes(i.type) || i.checked)).map((i) => [i.name, i.value]);
  }
  forEach(cb) { this.entries.forEach(([k, v]) => cb(v, k)); }
}

// ---------------------------------------------------------------------------------------------------------------
// Página: documento 10 con el bucle `loop123` (los cuatro filtros y el precio de F4-Woo), el bucle `loopctx` con
// contexto (C2-13) y su rango de fechas, y tres formularios con destino «Archivo».
const loopHtml = (id, items, ctx = '') => `<div class="elementor-element elementor-element-${id} elementor-widget-loop-grid" data-id="${id}" data-element_type="widget" data-widget_type="loop-grid.post"${ctx}><div class="elementor-widget-container"><div class="elementor-loop-container">${items}</div></div></div>`;
const item = (n) => `<div class="e-loop-item"><div class="elementor-element elementor-element-it${n}" data-element_type="widget" data-widget_type="heading.default"><a href="#p${n}">P${n}</a></div></div>`;
const helper = '<span class="e-filter" hidden data-base-url="https://example.test/catalogo/" data-page-num="1"></span>';
const status = 'data-status="{&quot;updated&quot;:&quot;Resultados actualizados&quot;,&quot;none&quot;:&quot;Ningún resultado con estos filtros&quot;,&quot;one&quot;:&quot;1 resultado&quot;,&quot;many&quot;:&quot;%d resultados&quot;}"';
const wrap = (id, name, form) => `<div class="elementor-element elementor-element-${id} elementor-widget-${name}" data-id="${id}" data-element_type="widget" data-widget_type="${name}.default">${form}</div>`;
const open = (cls, label, kind, loop, extra) => `<form class="digi-filter ${cls}" method="get" action="https://example.test/catalogo/" aria-label="${label}" data-digi-filter="${kind}" data-target="${loop ? 'loop' : 'archive'}" data-loop="${loop}" ${status}${extra}>${loop ? helper : ''}`;
const clear = (label, text) => `<a class="digi-filter__clear" rel="nofollow" href="https://example.test/catalogo/" aria-label="${label}">${text}</a>`;
const searchForm = (loop) => wrap('fsearch', 'digitalisimo-loop-search', open('digi-filter--search', 'Buscar', 'search', loop, ` role="search" data-type="digitalisimo_search" data-key="digi_s_fsearch" data-multiple="0"`)
  + `<div class="digi-filter__search"><label class="digi-filter__label" for="digi-s-fsearch">Buscar</label><input class="digi-filter__input" type="search" id="digi-s-fsearch" name="e-filter-${loop}-digi_s_fsearch" value="" minlength="2" maxlength="100"><button type="submit" class="digi-filter__submit">Buscar</button></div>`
  + `<div class="digi-filter__actions">${clear('Limpiar la búsqueda', 'Limpiar')}</div></form>`);
const rangeForm = (id, loop, type) => {
  const input = (edge, name) => `<label class="digi-filter__field"><span class="digi-filter__label">${edge === 'min' ? 'Desde' : 'Hasta'}</span><input class="digi-filter__input" type="${type}" name="${name}" data-digi-edge="${edge}" value=""${type === 'number' ? ' min="0" max="1000" step="0.5"' : ''}></label>`;
  const pre = loop ? `e-filter-${loop}-` : '';
  return wrap(id, 'digitalisimo-loop-meta-filter', open('digi-filter--range', 'Filtrar por precio', 'range', loop, ` data-type="digitalisimo_meta" data-key-min="digi_m_${id}" data-key-max="digi_m_${id}-max" data-apply="button" data-min="${type === 'number' ? '0' : ''}" data-max="${type === 'number' ? '1000' : ''}"`)
    + `<fieldset class="digi-filter__set"><legend class="digi-filter__legend">Precio</legend><div class="digi-filter__fields">${input('min', `${pre}digi_m_${id}`)}${input('max', `${pre}digi_m_${id}-max`)}</div></fieldset>`
    + (loop ? '' : `<input type="hidden" name="digi_f" value="10-${id}">`)
    + `<div class="digi-filter__actions"><button type="submit" class="digi-filter__submit">Aplicar</button>${clear('Quitar el filtro por precio', 'Limpiar')}</div></form>`);
};
const colorForm = (id, loop) => {
  const name = loop ? `e-filter-${loop}-digi_m_${id}` : `digi_m_${id}`;
  return wrap(id, 'digitalisimo-loop-meta-filter', open('digi-filter--meta digi-filter--checkboxes', 'Filtrar por color', 'meta', loop, ` data-type="digitalisimo_meta" data-key="digi_m_${id}" data-logic="OR" data-multiple="1" data-display="checkboxes"`)
    + `<fieldset class="digi-filter__set"><legend class="digi-filter__legend">Color</legend><ul class="digi-filter__list" role="list"><li><label class="digi-filter__option"><input type="checkbox" name="${name}" value="rojo"><span>Rojo</span></label></li><li><label class="digi-filter__option"><input type="checkbox" name="${name}" value="nueva-york"><span>NY</span></label></li></ul></fieldset>`
    + (loop ? '' : `<input type="hidden" name="utm_source" value="x"><input type="hidden" name="digi_f" value="10-${id}">`)
    + `<div class="digi-filter__actions"><button type="submit" class="digi-filter__submit">Aplicar</button>${clear('Quitar el filtro por color', 'Limpiar')}</div></form>`);
};
const orderForm = (id, loop) => wrap(id, 'digitalisimo-loop-orderby', open('digi-filter--orderby digi-filter--dropdown', 'Ordenar por', 'orderby', loop, ` data-type="digitalisimo_orderby" data-key="digi_o_${id}" data-logic="OR" data-multiple="0" data-display="dropdown"`)
  + `<fieldset class="digi-filter__set"><legend class="digi-filter__legend">Ordenar por</legend><select class="digi-filter__select" name="${loop ? `e-filter-${loop}-` : ''}digi_o_${id}" aria-label="Ordenar por"><option value="">Orden predeterminado</option><option value="title_asc">Título (A-Z)</option><option value="date_desc">Más recientes</option></select></fieldset>`
  + (loop ? '' : `<input type="hidden" name="digi_pp_farch02" value="24"><input type="hidden" name="digi_f" value="10-${id}">`)
  + `<div class="digi-filter__actions"><button type="submit" class="digi-filter__submit">Aplicar</button>${clear('Volver al orden predeterminado', 'Restablecer')}</div></form>`);
const pageForm = (id, loop) => wrap(id, 'digitalisimo-loop-per-page', open('digi-filter--per-page digi-filter--buttons', 'Por página', 'per-page', loop, ` data-type="digitalisimo_per_page" data-key="digi_pp_${id}" data-logic="OR" data-multiple="0" data-display="buttons"`)
  + `<fieldset class="digi-filter__set"><legend class="digi-filter__legend">Por página</legend><ul class="digi-filter__list" role="list">${['12', '24'].map((n) => `<li><a class="digi-filter__term" rel="nofollow" role="button" aria-pressed="false" data-value="${n}" href="https://example.test/catalogo/?e-filter-${loop}-digi_pp_${id}=${n}"><span>${n}</span></a></li>`).join('')}</ul></fieldset>`
  + `<div class="digi-filter__actions">${clear('Volver a la cantidad predeterminada', 'Restablecer')}</div></form>`);
const priceForm = (id, loop) => wrap(id, 'digitalisimo-woo-price-filter', `<form class="digi-filter digi-filter--price" method="get" action="https://example.test/catalogo/" aria-label="Filtrar por precio" data-digi-filter="price" data-target="loop" data-loop="${loop}" ${status} data-apply="button" data-display="fields" data-min="0" data-max="200" data-key-min="digi_price_min" data-key-max="digi_price_max" data-currency="{}">${helper}<fieldset class="digi-filter__set"><legend class="digi-filter__legend">Precio</legend><input class="digi-filter__input" type="number" name="e-filter-${loop}-digi_price_min" data-digi-edge="min" value=""><input class="digi-filter__input" type="number" name="e-filter-${loop}-digi_price_max" data-digi-edge="max" value=""></fieldset><div class="digi-filter__actions"><button type="submit" class="digi-filter__submit">Aplicar</button><a class="digi-filter__clear" rel="nofollow" href="https://example.test/catalogo/">Quitar filtro</a></div></form>`);
const archiveSearch = wrap('fsarch1', 'digitalisimo-loop-search', `<form class="digi-filter digi-filter--search" method="get" action="https://example.test/" aria-label="Buscar" data-digi-filter="search" data-target="archive" data-loop="" ${status} role="search" data-type="digitalisimo_search" data-key="digi_s_fsarch1" data-multiple="0"><div class="digi-filter__search"><label class="digi-filter__label" for="digi-s-fsarch1">Buscar</label><input class="digi-filter__input" type="search" id="digi-s-fsarch1" name="s" value="" minlength="2" maxlength="100"><button type="submit" class="digi-filter__submit">Buscar</button></div></form>`);
const PAGE = `<div data-elementor-id="10" class="elementor elementor-10">${loopHtml('loop123', item(1) + item(2))}${searchForm('loop123')}${rangeForm('frange1', 'loop123', 'number')}${colorForm('fcolor1', 'loop123')}${orderForm('forder1', 'loop123')}${pageForm('fpage01', 'loop123')}${priceForm('fprice1', 'loop123')}`
  + `${loopHtml('loopctx', item(3), ' data-digi-ctx="77"')}${rangeForm('fdate01', 'loopctx', 'date')}${pageForm('fpage02', 'loopctx')}`
  + `${loopHtml('loopbad', item(4), ' data-digi-ctx="7a"')}${pageForm('fpage03', 'loopbad')}`
  + `${orderForm('farch01', '')}${colorForm('farch03', '')}${rangeForm('farch04', '', 'number')}${archiveSearch}</div>`;

// ---------------------------------------------------------------------------------------------------------------
// Entorno: el de `elements-woo-filters.mjs` (relojes falsos, fetch controlable, historia, núcleo y módulo de PRO).
function env({ html = PAGE, search = '', pro = true } = {}) {
  const doc = makeDocument(html);
  let now = 0; const timers = [];
  const setTimeout = (fn, ms = 0) => { const t = { fn, at: now + ms, id: timers.length + 1 }; timers.push(t); return t.id; };
  const clearTimeout = (id) => { const t = timers.find((x) => x.id === id); if (t) t.done = true; };
  const tick = async (ms = 0) => { now += ms; for (let t; (t = timers.filter((x) => !x.done && x.at <= now).sort((a, b) => a.at - b.at)[0]);) { t.done = true; t.fn(); } for (let i = 0; i < 5; i++) await Promise.resolve(); };
  const fetches = [];
  const fetch = (url, init) => new Promise((resolve, reject) => {
    const call = { url, init, body: init.body ? JSON.parse(init.body) : null, resolve: (data, status = 200) => resolve({ ok: status < 400, status, json: async () => data, text: async () => data }) };
    init.signal.addEventListener('abort', () => { call.aborted = true; reject(Object.assign(new Error('abort'), { name: 'AbortError' })); });
    fetches.push(call);
  });
  const location = { origin: 'https://example.test', pathname: '/catalogo/', search, hash: '', reloads: 0, assigned: [], get href() { return this.origin + this.pathname + this.search + this.hash; }, reload() { this.reloads++; }, assign(u) { this.assigned.push(u); } };
  const pushes = [];
  const history = { pushState(state, title, url) { pushes.push(url); const u = new URL(url, location.href); location.pathname = u.pathname; location.search = u.search; location.hash = u.hash; } };
  const filters = {}; const actions = {};
  const hooks = {
    addFilter(name, fn, priority = 10) { (filters[name] = filters[name] || []).push({ fn, priority }); },
    applyFilters(name, value) { return (filters[name] || []).slice().sort((a, b) => a.priority - b.priority).reduce((v, f) => f.fn(v), value); },
    addAction(name, fn) { (actions[name] = actions[name] || []).push(fn); },
    doAction(name, ...args) { (actions[name] || []).forEach((fn) => fn(...args)); },
  };
  const elementorFrontend = { hooks, isEditMode: () => false, config: { post: { id: 99 } }, elementsHandler: { runReadyTrigger: () => {} } };
  const winListeners = {};
  const ctx = {
    document: doc, location, history, fetch, AbortController, URLSearchParams, URL, setTimeout, clearTimeout, Event: Ev, FormData: FormDataDouble, JSON, console,
    elementorFrontend, ElementorProFrontendConfig: pro ? { urls: { rest: 'https://example.test/wp-json/' }, settings: {} } : undefined,
    DOMParser: class { parseFromString(text) { return makeDocument(text); } },
    addEventListener: (type, fn) => { (winListeners[type] = winListeners[type] || []).push(fn); },
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  class LoopFilter {
    refreshLoopWidget() {}
    getQueryStringInObjectForm() { return {}; }
    setFilterDataForLoopWidget() {}
    removeFilterFromLoopWidget() {}
  }
  vm.runInContext(source.coordinator, ctx);
  vm.runInContext(source.form, ctx);
  if (pro) {
    hooks.addFilter('elementor-pro/frontend/handlers', (h) => Object.assign({}, h, { taxonomyFilter: LoopFilter }), 10);
    const handlers = hooks.applyFilters('elementor-pro/frontend/handlers', {});
    ctx.elementorProFrontend = { modules: { taxonomyFilter: new handlers.taxonomyFilter() } };
  }
  const ready = Object.keys(actions);
  doc.querySelectorAll('[data-widget_type]').forEach((el) => hooks.doAction('frontend/element_ready/' + el.getAttribute('data-widget_type'), [el]));
  const popstate = () => (winListeners.popstate || []).forEach((fn) => fn(new Ev('popstate')));
  return { doc, ctx, tick, fetches, pushes, location, ready, popstate };
}
const submit = (form) => { const ev = new Ev('submit'); form.dispatchEvent(ev); return ev; };
const input = (el, value, type = 'input') => { el.value = value; el.dispatchEvent(new Ev(type)); };
const formOf = (e, id) => e.doc.querySelector(`.elementor-element-${id} form`);
const response = (n) => ({ data: `<div class="elementor-widget-container"><div class="elementor-loop-container">${Array.from({ length: n }, (_, i) => item(100 + i)).join('')}</div></div>` });

// ---------------------------------------------------------------------------------------------------------------
// Tamaños: filters-form.js ≤ 6 KB y el coordinador ≤ 8 KB; la hoja propia mantiene visible el botón del buscador.
ok(statSync(JS('filters-form.js')).size <= 6144, `filters-form.js pesa ${statSync(JS('filters-form.js')).size} B, más de 6 KB`);
// El coordinador sube a 10 KB en C5 (programa «sin widgets de PRO»): refresca también la Cuadrícula de bucle propia.
ok(statSync(JS('filters.js')).size <= 10240, `filters.js pesa ${statSync(JS('filters.js')).size} B, más de 10 KB`);
ok(/\.digi-filter--search\.digi-filter--live \.digi-filter__submit\{display:inline-block\}/.test(CSS), 'Con el coordinador, el buscador conserva su botón (no se busca al teclear).');

// 1 · Arranque: element_ready de los cuatro widgets; registro en el coordinador con su tipo y sus claves.
{
  const e = env();
  for (const name of ['digitalisimo-loop-search', 'digitalisimo-loop-meta-filter', 'digitalisimo-loop-orderby', 'digitalisimo-loop-per-page', 'digitalisimo-woo-price-filter', 'digitalisimo-woo-attribute-filter']) {
    ok(e.ready.includes(`frontend/element_ready/${name}.default`), `element_ready de ${name}.`);
  }
  for (const id of ['fsearch', 'frange1', 'fcolor1', 'forder1', 'fpage01', 'farch01', 'fsarch1']) ok(formOf(e, id).getAttribute('data-digi-ready') === '1', `Formulario ${id} iniciado.`);
  ok(e.ctx.digitalisimoWooFilters.active(), 'Coordinador activo.');
  same(JSON.parse(JSON.stringify(e.ctx.digitalisimoWooFilters.state('loop123'))), {}, 'Sin nada elegido, el estado está vacío.');
  ok(formOf(e, 'fsearch').classList.contains('digi-filter--live') && formOf(e, 'fcolor1').classList.contains('digi-filter--live') && formOf(e, 'forder1').classList.contains('digi-filter--live'), 'Con el coordinador, los formularios de lista son «en vivo».');
  ok(!formOf(e, 'frange1').classList.contains('digi-filter--live'), 'El rango con «Aplicar» conserva su botón.');
  ok(!formOf(e, 'farch01').classList.contains('digi-filter--live') && !formOf(e, 'farch03').classList.contains('digi-filter--live'), 'Con destino «Archivo», el botón se ve siempre.');
}

// 2 · Buscador: no busca al teclear; al enviar, un refresco con el texto entero; «Limpiar» vacía y refresca.
{
  const e = env({ search: '?utm=x' });
  const form = formOf(e, 'fsearch');
  const field = form.querySelector('input[type=search]');
  input(field, 'zapatos');
  input(field, 'zapatos rojos');
  field.dispatchEvent(new Ev('change'));
  await e.tick(1000);
  ok(e.fetches.length === 0, 'Escribir (y salir del campo) no busca.');
  const ev = submit(form);
  ok(ev.defaultPrevented, 'Con el coordinador, el envío no recarga.');
  await e.tick(0);
  ok(e.fetches.length === 1, 'Un refresco al enviar.');
  same(e.fetches[0].body.widget_filters, { digitalisimo_search: { digi_s_fsearch: { terms: ['zapatos rojos'], logicalJoin: 'AND' } } }, 'El texto entero con el tipo y la clave del widget.');
  same(e.fetches[0].body.widget_id, 'loop123', 'Del bucle elegido.');
  ok(!('digi_ctx' in e.fetches[0].body), 'Un bucle sin data-digi-ctx no manda digi_ctx.');
  same(e.pushes, ['/catalogo/?utm=x&e-filter-loop123-digi_s_fsearch=zapatos%20rojos'], 'La URL con el nombre reservado.');
  const c = new Ev('click');
  form.querySelector('.digi-filter__clear').dispatchEvent(c);
  ok(c.defaultPrevented && field.value === '', '«Limpiar» vacía el campo sin recargar.');
  await e.tick(0);
  same(e.fetches[1].body.widget_filters, {}, 'Y refresca sin la búsqueda.');
  ok(e.location.search === '?utm=x', 'La URL vuelve a lo ajeno.');
}

// 3 · Campo, orden, por página y precio de F4-Woo en el mismo bucle: una sola petición con todos los tipos.
{
  const e = env();
  const range = formOf(e, 'frange1');
  input(range.querySelector('[data-digi-edge=min]'), '10');
  input(range.querySelector('[data-digi-edge=max]'), '50.5');
  await e.tick(1000);
  ok(e.fetches.length === 0, 'El rango con «Aplicar» no refresca al escribir.');
  submit(range);
  await e.tick(0);
  same(e.fetches[0].body.widget_filters, { digitalisimo_meta: { digi_m_frange1: { terms: ['10'], logicalJoin: 'AND' }, 'digi_m_frange1-max': { terms: ['50.5'], logicalJoin: 'AND' } } }, 'Rango: las dos claves del widget, -max incluida.');
  const color = formOf(e, 'fcolor1');
  for (const v of ['rojo', 'nueva-york']) { const b = color.querySelector(`input[value="${v}"]`); b.checked = true; b.dispatchEvent(new Ev('change')); }
  await e.tick(299);
  ok(e.fetches.length === 1, 'Casillas: 300 ms de espera.');
  await e.tick(1);
  same(e.fetches[1].body.widget_filters.digitalisimo_meta.digi_m_fcolor1, { terms: ['rojo', 'nueva-york'], logicalJoin: 'OR' }, 'Lista: las fichas elegidas con «O».');
  const select = formOf(e, 'forder1').querySelector('select');
  input(select, 'title_asc', 'change');
  await e.tick(0);
  same(e.fetches[2].body.widget_filters.digitalisimo_orderby, { digi_o_forder1: { terms: ['title_asc'], logicalJoin: 'AND' } }, 'Orden: el desplegable refresca de inmediato.');
  const page = formOf(e, 'fpage01');
  const [b12, b24] = page.querySelectorAll('[data-value]');
  const ev = new Ev('click');
  b12.dispatchEvent(ev);
  ok(ev.defaultPrevented && b12.getAttribute('aria-pressed') === 'true', 'Por página: el botón se marca con aria-pressed sin recargar.');
  b24.click();
  ok(b12.getAttribute('aria-pressed') === 'false' && b24.getAttribute('aria-pressed') === 'true', 'Una sola cantidad a la vez.');
  await e.tick(0);
  const price = formOf(e, 'fprice1');
  input(price.querySelector('[data-digi-edge=min]'), '5');
  submit(price);
  await e.tick(0);
  const last = e.fetches[e.fetches.length - 1].body.widget_filters;
  same(last, {
    digitalisimo_meta: { digi_m_frange1: { terms: ['10'], logicalJoin: 'AND' }, 'digi_m_frange1-max': { terms: ['50.5'], logicalJoin: 'AND' }, digi_m_fcolor1: { terms: ['rojo', 'nueva-york'], logicalJoin: 'OR' } },
    digitalisimo_orderby: { digi_o_forder1: { terms: ['title_asc'], logicalJoin: 'AND' } },
    digitalisimo_per_page: { digi_pp_fpage01: { terms: ['24'], logicalJoin: 'AND' } },
    digitalisimo_price: { digi_price_min: { terms: ['5'], logicalJoin: 'AND' } },
  }, 'El precio de F4-Woo y los cuatro filtros nuevos del mismo bucle, en una sola petición.');
  const url = e.pushes[e.pushes.length - 1];
  for (const part of ['e-filter-loop123-digi_m_frange1=10', 'e-filter-loop123-digi_m_frange1-max=50.5', 'e-filter-loop123-digi_m_fcolor1=rojo~nueva-york', 'e-filter-loop123-digi_o_forder1=title_asc', 'e-filter-loop123-digi_pp_fpage01=24', 'e-filter-loop123-digi_price_min=5']) {
    ok(url.includes(part), `URL con ${part}: ${url}`);
  }
  // popstate: el estado vuelve a los cuatro formularios y el bucle se refresca si cambió.
  const before = e.fetches.length;
  const pushed = e.pushes.length;
  e.location.search = '?e-filter-loop123-digi_s_fsearch=botas%20negras&e-filter-loop123-digi_m_fcolor1=nueva-york&e-filter-loop123-digi_m_frange1=5&e-filter-loop123-digi_o_forder1=date_desc&e-filter-loop123-digi_pp_fpage01=12';
  e.popstate();
  ok(e.location.reloads === 0, 'Claves conocidas: sin recargar.');
  ok(formOf(e, 'fsearch').querySelector('input[type=search]').value === 'botas negras', 'popstate: el buscador con el texto entero.');
  ok(color.querySelector('input[value="nueva-york"]').checked && !color.querySelector('input[value="rojo"]').checked, 'popstate: las casillas.');
  ok(range.querySelector('[data-digi-edge=min]').value === '5' && range.querySelector('[data-digi-edge=max]').value === '', 'popstate: el rango.');
  ok(select.value === 'date_desc', 'popstate: el desplegable.');
  ok(b12.getAttribute('aria-pressed') === 'true' && b24.getAttribute('aria-pressed') === 'false', 'popstate: los botones.');
  await e.tick(0);
  ok(e.fetches.length > before, 'popstate refresca el bucle.');
  const pops = e.fetches.slice(before).map((f) => f.body);
  const mine = pops.find((body) => body.widget_id === 'loop123');
  same(mine.widget_filters, {
    digitalisimo_search: { digi_s_fsearch: { terms: ['botas negras'], logicalJoin: 'AND' } },
    digitalisimo_meta: { digi_m_frange1: { terms: ['5'], logicalJoin: 'AND' }, digi_m_fcolor1: { terms: ['nueva-york'], logicalJoin: 'AND' } },
    digitalisimo_orderby: { digi_o_forder1: { terms: ['date_desc'], logicalJoin: 'AND' } },
    digitalisimo_per_page: { digi_pp_fpage01: { terms: ['12'], logicalJoin: 'AND' } },
  }, 'popstate: el refresco con lo que dice la URL (el precio, ausente, se quita).');
  ok(1 === pops.filter((body) => body.widget_id === 'loop123').length, 'Un solo refresco por bucle.');
  ok(pushed === e.pushes.length, 'popstate no añade historia.');
}

// 4 · digi_ctx (C2-13): sólo con data-digi-ctx numérico en el envoltorio del bucle destino.
{
  const e = env();
  const dates = formOf(e, 'fdate01');
  input(dates.querySelector('[data-digi-edge=min]'), '2026-01-01');
  submit(dates);
  await e.tick(0);
  same(e.fetches[0].body.widget_id, 'loopctx', 'El bucle con contexto.');
  ok(e.fetches[0].body.digi_ctx === 77, 'digi_ctx con el número del envoltorio.');
  same(e.fetches[0].body.widget_filters, { digitalisimo_meta: { digi_m_fdate01: { terms: ['2026-01-01'], logicalJoin: 'AND' } } }, 'Rango de fechas: una fecha no es un número, pero vale.');
  formOf(e, 'fpage03').querySelector('[data-value="12"]').click();
  await e.tick(0);
  ok(e.fetches[1].body.widget_id === 'loopbad' && !('digi_ctx' in e.fetches[1].body), 'Un data-digi-ctx que no es un número no se manda.');
  formOf(e, 'fpage01').querySelector('[data-value="12"]').click();
  await e.tick(0);
  ok(e.fetches[2].body.widget_id === 'loop123' && !('digi_ctx' in e.fetches[2].body), 'El contexto de un bucle no viaja con otro.');
  same(Object.keys(e.fetches[0].body).sort(), ['digi_ctx', 'pagination_base_url', 'post_id', 'widget_filters', 'widget_id'], 'Cuerpo de refresh-loop: los campos de PRO más digi_ctx.');
}

// 5 · Destino «Archivo»: GET a la URL del archivo con digi_f y los demás parámetros; listas con comas; búsqueda nativa.
{
  const e = env();
  const order = formOf(e, 'farch01');
  input(order.querySelector('select'), 'title_asc', 'change');
  await e.tick(300);
  ok(e.fetches.length === 0 && e.location.assigned.length === 0, 'Archivo: el desplegable espera al botón (sin coordinador).');
  ok(submit(order).defaultPrevented, 'El envío se hace por GET (sin el campo vacío).');
  same(e.location.assigned[0], 'https://example.test/catalogo/?digi_o_farch01=title_asc&digi_pp_farch02=24&digi_f=10-farch01', 'Orden con los demás parámetros y digi_f.');
  const color = formOf(e, 'farch03');
  color.querySelectorAll('input[type=checkbox]').forEach((b) => { b.checked = true; });
  submit(color);
  same(e.location.assigned[1], 'https://example.test/catalogo/?utm_source=x&digi_f=10-farch03&digi_m_farch03=rojo%2Cnueva-york', 'Casillas unidas por comas, como las lee el servidor.');
  const range = formOf(e, 'farch04');
  input(range.querySelector('[data-digi-edge=max]'), '300');
  submit(range);
  same(e.location.assigned[2], 'https://example.test/catalogo/?digi_m_farch04-max=300&digi_f=10-farch04', 'Rango: sólo el extremo escrito.');
  const s = formOf(e, 'fsarch1');
  input(s.querySelector('input[type=search]'), 'luz');
  submit(s);
  same(e.location.assigned[3], 'https://example.test/?s=luz', 'Buscador con destino «Archivo»: la página de resultados del sitio.');
  ok(e.fetches.length === 0, 'Ningún refresco por REST con destino «Archivo».');
}

// 6 · Cuadrícula de bucle propia (C5): sin el módulo ni el REST de PRO, el coordinador pide la misma página con la URL
// filtrada y sustituye el contenido del bucle; lee también el Filtro de taxonomías propio; popstate y fallo con GET.
{
  const ownLoop = (id, items) => `<div class="elementor-element elementor-element-${id} elementor-widget elementor-widget-digitalisimo-loop-grid" data-id="${id}" data-element_type="widget" data-widget_type="digitalisimo-loop-grid.post"><div class="digi-loop-grid__items" role="list">${items}</div></div>`;
  const tfilter = (loop, pressed) => `<div class="elementor-element elementor-element-ftax001 elementor-widget-digitalisimo-taxonomy-filter" data-id="ftax001" data-element_type="widget" data-widget_type="digitalisimo-taxonomy-filter.default"><search class="digi-tfilter" role="search" aria-label="Filtrar por Categoría" data-loop="${loop}" data-taxonomy="category" data-join="DISABLED" data-base-url="https://example.test/catalogo/" data-page-num="1">${['__all', 'guias', 'casos'].map((t) => `<button type="button" class="digi-tfilter__item" data-filter="${t}" aria-pressed="${t === pressed}">${t}</button>`).join('')}</search></div>`;
  const html = `<div data-elementor-id="10" class="elementor elementor-10">${ownLoop('loopown', item(1) + item(2) + item(3))}${tfilter('loopown', 'guias')}${orderForm('forder9', 'loopown')}${pageForm('fpage09', 'loopown')}</div>`;
  const page = (items, cls = '') => `<!doctype html><html><head><title>x</title></head><body><div data-elementor-id="99"><div class="elementor-element elementor-element-loopown"><p>Otro documento</p></div></div><div data-elementor-id="10" class="elementor elementor-10">${ownLoop('loopown', items).replace('elementor-widget-digitalisimo-loop-grid"', `elementor-widget-digitalisimo-loop-grid${cls}"`)}</div></body></html>`;
  const e = env({ html, search: '?e-filter-loopown-category=guias&utm=x', pro: false });
  const C = e.ctx.digitalisimoWooFilters;
  ok(C.active('loopown') && !C.active('loop123') && !C.active(), 'Sin PRO: activo para la Cuadrícula propia, no para un Loop Grid de PRO.');
  const order = formOf(e, 'forder9');
  ok(order.classList.contains('digi-filter--live'), 'Con la Cuadrícula propia, los formularios de lista son «en vivo» sin PRO.');
  same(JSON.parse(JSON.stringify(C.state('loopown'))), { taxonomy: { category: { terms: ['guias'], logicalJoin: 'AND' } } }, 'El estado lee el Filtro de taxonomías propio (sin «Todos»).');
  input(order.querySelector('select'), 'title_asc', 'change');
  await e.tick(0);
  ok(e.fetches.length === 1 && e.fetches[0].body === null && !e.fetches[0].init.method && e.fetches[0].init.credentials === 'same-origin', 'Un GET de la misma página, sin REST.');
  const want = '/catalogo/?utm=x&e-filter-loopown-digi_o_forder9=title_asc&e-filter-loopown-category=guias';
  same(e.fetches[0].url, want, 'La URL filtrada con el orden y la taxonomía del filtro propio, sin perder lo ajeno.');
  same(e.pushes, [want], 'La URL se escribe en la historia.');
  const L = e.doc.querySelector('.elementor-element-loopown');
  ok(L.getAttribute('aria-busy') === 'true', 'Ocupado mientras llega.');
  e.fetches[0].resolve(page(item(7) + item(8), ' e-load-more-pagination-end'));
  await e.tick(0);
  ok(L.getAttribute('aria-busy') === 'false', 'Ya no ocupado.');
  same(L.querySelectorAll('.e-loop-item').map((n) => n.querySelector('a').getAttribute('href')), ['#p7', '#p8'], 'El contenido del bucle es el de la respuesta (del mismo documento).');
  ok(L.classList.contains('e-load-more-pagination-end') && L.classList.contains('elementor-widget-digitalisimo-loop-grid'), 'Las clases del envoltorio, las de la respuesta.');
  ok(e.doc.querySelector('.digi-filter-status').textContent === '2 resultados', 'Anuncia el resultado en la región de estado.');
  ok(e.doc.querySelector('.elementor-element-loopown') === L, 'El envoltorio es el mismo elemento (sus manejadores siguen).');
  // Por página + popstate: los formularios, los botones del filtro propio y el bucle siguen a la URL.
  formOf(e, 'fpage09').querySelector('[data-value="12"]').click();
  await e.tick(0);
  ok(e.fetches.length === 2 && e.fetches[1].url.includes('e-filter-loopown-digi_pp_fpage09=12'), 'Por página: otro GET con la cantidad.');
  e.fetches[1].resolve(page(item(9)));
  await e.tick(0);
  e.location.search = '?e-filter-loopown-category=casos&e-filter-loopown-digi_o_forder9=date_desc';
  e.popstate();
  await e.tick(0);
  ok(e.location.reloads === 0, 'popstate con claves conocidas (la taxonomía del filtro propio): sin recargar.');
  ok(order.querySelector('select').value === 'date_desc', 'popstate: el desplegable.');
  same(e.doc.querySelectorAll('.digi-tfilter__item').map((b) => b.getAttribute('aria-pressed')), ['false', 'false', 'true'], 'popstate: los botones del filtro propio.');
  ok(e.fetches.length === 3 && e.fetches[2].url === '/catalogo/?e-filter-loopown-digi_o_forder9=date_desc&e-filter-loopown-category=casos' && e.pushes.length === 2, 'popstate: un GET con la URL de la historia, sin añadir historia.');
  // Una respuesta sin el bucle (o un error): se navega a la URL filtrada, que el servidor pinta igual.
  e.fetches[2].resolve('<html><body>sin bucle</body></html>');
  await e.tick(0);
  same(e.location.assigned, ['/catalogo/?e-filter-loopown-digi_o_forder9=date_desc&e-filter-loopown-category=casos'], 'Sin el bucle en la respuesta, navega a la URL filtrada.');
  input(order.querySelector('select'), 'title_asc', 'change');
  await e.tick(0);
  e.fetches[3].resolve('error', 500);
  await e.tick(0);
  ok(e.location.assigned.length === 2, 'Con un error del servidor, también.');
}

console.log(`DIGITALÍSIMO Elements: formularios de los filtros ampliados de F7b con filters-form.js y el coordinador (registro, buscador, rango, lista, orden, por página, digi_ctx, popstate, «Archivo» y la Cuadrícula de bucle propia de C5) en verde: ${checks} comprobaciones.`);
