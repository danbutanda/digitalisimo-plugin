// F4-Woo, frente F: coordinador de filtros (`assets/js/filters.js`) y formularios (`assets/js/filters-form.js`), V.8.3
// y V.8.6 de la especificación. Con un DOM mínimo, relojes falsos y dobles de `fetch`, del núcleo y del módulo de PRO:
// - V-B2: petición propia a refresh-loop con `signal`; cada refresco aborta el anterior y la respuesta vieja no se
//   inserta; `post_id` del documento del filtro con dos copias del bucle; nunca `prepareLoopUpdateRequestData`,
//   `getFetchArgumentsForLoopUpdate`, `fetchUpdatedLoopWidgetMarkup` ni `getFilterHelperAttributes`.
// - La subclase entra por `elementor-pro/frontend/handlers` con prioridad 20 y sólo atiende los bucles coordinados;
//   el estado del filtro de PRO se lee de su marcado; un bucle sin filtros F4-Woo sigue con el refresco de PRO.
// - Debounce de 300 ms en campos y casillas, 0 en botones; `aria-busy`, región viva, foco; `popstate` sin `pushState`,
//   con los botones y el almacén de PRO al día; parámetro desconocido → recarga; sin las piezas de PRO, GET.
// - «Tienda»: URL con los parámetros nativos y las casillas unidas por comas. Topes de 8 KB por script.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const JS = (name) => new URL(`../digitalisimo-elements/assets/js/${name}`, import.meta.url);
const source = { coordinator: readFileSync(JS('filters.js'), 'utf8'), form: readFileSync(JS('filters-form.js'), 'utf8'), editor: readFileSync(JS('filters-editor.js'), 'utf8') };

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
// Página: un bucle coordinado en el documento 10 (precio y filtro de PRO), su copia en el documento 20 (atributo) y
// otro bucle sólo con un filtro de PRO.
const loopHtml = (id, items) => `<div class="elementor-element elementor-element-${id} elementor-widget-loop-grid" data-id="${id}" data-element_type="widget" data-widget_type="loop-grid.product"><div class="elementor-widget-container"><div class="elementor-loop-container">${items}</div></div></div>`;
const item = (n) => `<div class="e-loop-item"><div class="elementor-element elementor-element-it${n}" data-element_type="widget" data-widget_type="heading.default"><a href="#p${n}">P${n}</a></div></div>`;
const helper = '<span class="e-filter" hidden data-base-url="https://example.test/tienda/" data-page-num="1"></span>';
const status = 'data-status="{&quot;updated&quot;:&quot;Resultados actualizados&quot;,&quot;none&quot;:&quot;Ningún producto con estos filtros&quot;,&quot;one&quot;:&quot;1 producto&quot;,&quot;many&quot;:&quot;%d productos&quot;}"';
const priceForm = (id, apply, extra = '') => `<div class="elementor-element elementor-element-${id} elementor-widget-digitalisimo-woo-price-filter" data-id="${id}" data-element_type="widget" data-widget_type="digitalisimo-woo-price-filter.default"><form class="digi-filter digi-filter--price" method="get" action="https://example.test/tienda/" aria-label="Filtrar por precio" data-digi-filter="price" data-target="${extra.includes('shop') ? 'shop' : 'loop'}" data-loop="${extra.includes('shop') ? '' : 'abc1234'}" ${status} data-apply="${apply}" data-display="both" data-min="0" data-max="200" data-key-min="digi_price_min" data-key-max="digi_price_max" data-currency="{&quot;s&quot;:&quot;€&quot;,&quot;d&quot;:2,&quot;ds&quot;:&quot;,&quot;,&quot;ts&quot;:&quot;.&quot;,&quot;f&quot;:&quot;%2$s %1$s&quot;}">${helper}<fieldset class="digi-filter__set"><legend class="digi-filter__legend">Precio</legend><input class="digi-filter__input" type="number" name="${extra.includes('shop') ? 'min_price' : 'e-filter-abc1234-digi_price_min'}" data-digi-edge="min" value=""><input class="digi-filter__input" type="number" name="${extra.includes('shop') ? 'max_price' : 'e-filter-abc1234-digi_price_max'}" data-digi-edge="max" value=""><input class="digi-filter__slider" type="range" data-digi-edge="min" value="0" aria-hidden="true" tabindex="-1"><input class="digi-filter__slider" type="range" data-digi-edge="max" value="200" aria-hidden="true" tabindex="-1"></fieldset><input type="hidden" name="utm" value="x"><div class="digi-filter__actions"><button type="submit" class="digi-filter__submit">Aplicar</button><a class="digi-filter__clear" rel="nofollow" href="https://example.test/tienda/">Quitar filtro</a></div></form></div>`;
const attrForm = (id, display, extra = '') => {
  const shop = extra.includes('shop');
  const name = shop ? 'filter_color' : 'e-filter-abc1234-digiattr_pa_color';
  const options = display === 'buttons'
    ? `<ul class="digi-filter__list"><li><a class="digi-filter__term" rel="nofollow" role="button" aria-pressed="false" data-value="rojo" href="?x">Rojo</a></li><li><a class="digi-filter__term" rel="nofollow" role="button" aria-pressed="false" data-value="azul" href="?y">Azul</a></li></ul>`
    : `<ul class="digi-filter__list"><li><label class="digi-filter__option"><input type="checkbox" name="${name}" value="rojo"><span>Rojo</span></label></li><li><label class="digi-filter__option"><input type="checkbox" name="${name}" value="azul"><span>Azul</span></label></li></ul>`;
  return `<div class="elementor-element elementor-element-${id} elementor-widget-digitalisimo-woo-attribute-filter" data-id="${id}" data-element_type="widget" data-widget_type="digitalisimo-woo-attribute-filter.default"><form class="digi-filter digi-filter--attribute digi-filter--${display}" method="get" action="https://example.test/tienda/?page_id=5" aria-label="Filtrar por color" data-digi-filter="attribute" data-target="${shop ? 'shop' : 'loop'}" data-loop="${shop ? '' : 'abc1234'}" ${status} data-key="${shop ? '' : 'digiattr_pa_color'}" data-logic="OR" data-multiple="1" data-display="${display}">${helper}<fieldset class="digi-filter__set"><legend class="digi-filter__legend">Color</legend>${options}</fieldset>${shop ? '<input type="hidden" name="query_type_color" value="or">' : ''}<div class="digi-filter__actions"><button type="submit" class="digi-filter__submit">Aplicar</button><a class="digi-filter__clear" rel="nofollow" href="?">Limpiar</a></div></form></div>`;
};
const proFilter = (id, loop, tax = 'product_cat') => `<div class="elementor-element elementor-element-${id} elementor-widget-taxonomy-filter" data-id="${id}" data-element_type="widget" data-widget_type="taxonomy-filter.default" data-settings="{&quot;selected_element&quot;:&quot;${loop}&quot;,&quot;taxonomy&quot;:&quot;${tax}&quot;,&quot;multiple_selection&quot;:&quot;yes&quot;,&quot;logical_combination&quot;:&quot;OR&quot;}"><search class="e-filter" role="search" data-base-url="https://example.test/tienda/" data-page-num="1"><button class="e-filter-item" data-filter="ropa" aria-pressed="false">Ropa</button><button class="e-filter-item" data-filter="zapatos" aria-pressed="false">Zapatos</button><button class="e-filter-item" data-filter="__all" aria-pressed="true">Todos</button></search></div>`;
const PAGE = `<div data-elementor-id="10" class="elementor elementor-10">${loopHtml('abc1234', item(1) + item(2))}${priceForm('f000001', 'button')}${proFilter('t000001', 'abc1234')}${loopHtml('def5678', item(9))}${proFilter('t000002', 'def5678', 'product_tag')}</div><div data-elementor-id="20" class="elementor elementor-20">${loopHtml('abc1234', item(5))}${attrForm('f000002', 'checkboxes')}</div><div data-elementor-id="30">${priceForm('f000003', 'button', 'shop')}${attrForm('f000004', 'checkboxes', 'shop')}</div>`;

// ---------------------------------------------------------------------------------------------------------------
// Entorno: relojes falsos, fetch controlable, historia, núcleo y módulo de PRO.
function env({ html = PAGE, edit = false, proComplete = true, search = '', ignoreAbort = false, lateHooks = false, proLate = false } = {}) {
  const doc = makeDocument(html);
  let now = 0; const timers = [];
  const setTimeout = (fn, ms = 0) => { const t = { fn, at: now + ms, id: timers.length + 1 }; timers.push(t); return t.id; };
  const clearTimeout = (id) => { const t = timers.find((x) => x.id === id); if (t) t.done = true; };
  const tick = async (ms = 0) => { now += ms; for (let t; (t = timers.filter((x) => !x.done && x.at <= now).sort((a, b) => a.at - b.at)[0]);) { t.done = true; t.fn(); } for (let i = 0; i < 5; i++) await Promise.resolve(); };
  const fetches = [];
  const fetch = (url, init) => new Promise((resolve, reject) => {
    const call = { url, init, body: JSON.parse(init.body), resolve: (data) => resolve({ ok: true, status: 200, json: async () => data }), fail: () => resolve({ ok: false, status: 400, json: async () => ({}) }) };
    init.signal.addEventListener('abort', () => { call.aborted = true; if (!ignoreAbort) reject(Object.assign(new Error('abort'), { name: 'AbortError' })); });
    fetches.push(call);
  });
  const location = { origin: 'https://example.test', pathname: '/tienda/', search, hash: '', reloads: 0, assigned: [], get href() { return this.origin + this.pathname + this.search + this.hash; }, reload() { this.reloads++; }, assign(u) { this.assigned.push(u); } };
  const pushes = [];
  const history = { pushState(state, title, url) { pushes.push(url); const u = new URL(url, location.href); location.pathname = u.pathname; location.search = u.search; location.hash = u.hash; } };
  const filters = {}; const actions = {}; const ready = [];
  const hooks = {
    addFilter(name, fn, priority = 10) { (filters[name] = filters[name] || []).push({ fn, priority }); },
    applyFilters(name, value) { return (filters[name] || []).slice().sort((a, b) => a.priority - b.priority).reduce((v, f) => f.fn(v), value); },
    addAction(name, fn) { (actions[name] = actions[name] || []).push(fn); },
    doAction(name, ...args) { (actions[name] || []).forEach((fn) => fn(...args)); },
  };
  // `lateHooks`: como el núcleo real, que crea `hooks` en init() (en el DOM listo) y avisa con elementor/frontend/init.
  const elementorFrontend = { hooks: lateHooks ? undefined : hooks, isEditMode: () => edit, config: { post: { id: 99 } }, elementsHandler: { runReadyTrigger: (el) => ready.push(el) } };
  const winListeners = {};
  const ctx = {
    document: doc, location, history, fetch, AbortController, URLSearchParams, URL, setTimeout, clearTimeout, Event: Ev, FormData: FormDataDouble, JSON, console,
    elementorFrontend, ElementorProFrontendConfig: { urls: { rest: 'https://example.test/wp-json/' }, settings: { lazy_load_background_images: true } },
    addEventListener: (type, fn) => { (winListeners[type] = winListeners[type] || []).push(fn); },
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  // PRO: su propio filtro (10) añade el módulo; después crea la instancia (`initModules()`).
  const proCalls = [];
  const forbidden = ['prepareLoopUpdateRequestData', 'getFetchArgumentsForLoopUpdate', 'fetchUpdatedLoopWidgetMarkup', 'getFilterHelperAttributes'];
  class LoopFilter {
    refreshLoopWidget(l, f) { proCalls.push(['original', l, f]); }
    getQueryStringInObjectForm() { return { 'e-filter-abc1234-product_cat': 'zapatos', 'e-filter-def5678-product_tag': 'b' }; }
    setFilterDataForLoopWidget(l, f, data, refresh = true, join = 'DISABLED') { proCalls.push(['set', l, f, data.filterData.terms.join(','), refresh, join]); if (refresh) this.refreshLoopWidget(l, f); }
    removeFilterFromLoopWidget(l, f, term = '', def = '') { proCalls.push(['remove', l, f, term, def]); this.refreshLoopWidget(l, f); }
  }
  forbidden.forEach((m) => { LoopFilter.prototype[m] = () => { throw new Error('Ayuda interna de PRO llamada: ' + m); }; });
  if (!proComplete) delete LoopFilter.prototype.getQueryStringInObjectForm;
  const extend = (h) => Object.assign({}, h, { taxonomyFilter: LoopFilter });
  // Arranque real (`lateHooks`): el script de PRO ya creó `elementorProFrontend` (con `on`/`trigger`) y su paquete se
  // enganchó a `elementor-pro/modules/init/before`; con `proLate`, PRO llega después de los nuestros (DOM listo).
  const proEvents = {};
  const proFrontend = { modules: {}, on(name, fn) { (proEvents[name] = proEvents[name] || []).push(fn); }, trigger(name) { (proEvents[name] || []).forEach((fn) => fn()); } };
  proFrontend.on('elementor-pro/modules/init/before', () => hooks.addFilter('elementor-pro/frontend/handlers', extend, 10));
  if (lateHooks && !proLate) ctx.elementorProFrontend = proFrontend;
  vm.runInContext(source.coordinator, ctx);
  vm.runInContext(source.form, ctx);
  if (lateHooks) {
    if (proLate) {
      ctx.elementorProFrontend = proFrontend;
      doc.listeners.filter((l) => l.type === 'DOMContentLoaded').forEach((l) => l.fn(new Ev('DOMContentLoaded')));
    }
    // init(): crea `hooks` y despacha elementor/frontend/init. En Chrome, el oyente de PRO (jQuery, registrado antes)
    // corre primero y crea los módulos; los oyentes nativos de los nuestros, después.
    elementorFrontend.hooks = hooks;
    proFrontend.trigger('elementor-pro/modules/init/before');
    proFrontend.modules.taxonomyFilter = new (hooks.applyFilters('elementor-pro/frontend/handlers', {}).taxonomyFilter)();
    (winListeners['elementor/frontend/init'] || []).forEach((fn) => fn(new Ev('elementor/frontend/init')));
  } else {
    hooks.addFilter('elementor-pro/frontend/handlers', extend, 10);
    const handlers = hooks.applyFilters('elementor-pro/frontend/handlers', {});
    ctx.elementorProFrontend = { modules: { taxonomyFilter: new handlers.taxonomyFilter() } };
  }
  // El núcleo inicia cada widget con su element_ready.
  doc.querySelectorAll('[data-widget_type]').forEach((el) => hooks.doAction('frontend/element_ready/' + el.getAttribute('data-widget_type'), [el]));
  const popstate = () => (winListeners.popstate || []).forEach((fn) => fn(new Ev('popstate')));
  return { doc, ctx, tick, fetches, pushes, location, proCalls, mod: ctx.elementorProFrontend.modules.taxonomyFilter, filters, ready, popstate, LoopFilter };
}
const submit = (form) => { const ev = new Ev('submit'); form.dispatchEvent(ev); return ev; };
const input = (el, value, type = 'input') => { el.value = value; el.dispatchEvent(new Ev(type)); };
const response = (n) => ({ data: `<div class="elementor-widget-container"><div class="elementor-loop-container">${Array.from({ length: n }, (_, i) => item(100 + i)).join('')}</div></div>` });

// ---------------------------------------------------------------------------------------------------------------
// Tamaños y lo que el coordinador nunca llama.
for (const name of ['filters.js', 'filters-form.js', 'filters-editor.js']) {
  assert.ok(statSync(JS(name)).size <= 8192, `${name} pesa ${statSync(JS(name)).size} B, más de 8 KB`);
}
for (const name of ['prepareLoopUpdateRequestData', 'getFetchArgumentsForLoopUpdate', 'fetchUpdatedLoopWidgetMarkup', 'getFilterHelperAttributes', 'loopWidgetsStore', 'AjaxHelper', 'jQuery']) {
  assert.ok(!source.coordinator.includes(name) && !source.form.includes(name), `El coordinador no usa ${name}`);
}
assert.ok(!/<script|innerHTML\s*=\s*[^h]/.test(source.form), 'Los formularios no escriben HTML.');

// 1 · Subclase con prioridad 20, sólo en bucles coordinados; el estado de PRO sale de su marcado.
{
  const e = env();
  const ours = e.filters['elementor-pro/frontend/handlers'].find((f) => f.priority === 20);
  assert.ok(ours, 'La subclase se registra con prioridad 20, después de la de PRO (10).');
  assert.ok(e.mod instanceof e.LoopFilter && e.mod.constructor !== e.LoopFilter, 'PRO crea el módulo con la subclase.');
  assert.equal(e.ctx.digitalisimoWooFilters.version, 2);
  // Un bucle sin filtros F4-Woo: el refresco de PRO, tal cual.
  e.mod.refreshLoopWidget('def5678', 't000002');
  await e.tick(0);
  assert.deepEqual(e.proCalls, [['original', 'def5678', 't000002']]);
  assert.equal(e.fetches.length, 0);
  // Un botón del filtro de PRO en el bucle coordinado: PRO marca el botón y pide el refresco; lo hace el coordinador.
  const ropa = e.doc.querySelector('.elementor-element-t000001 [data-filter="ropa"]');
  e.mod.setFilterDataForLoopWidget('abc1234', 't000001', { filterType: 'taxonomy', filterData: { selectedTaxonomy: 'product_cat', terms: ['ropa'] } }, true, 'OR');
  ropa.setAttribute('aria-pressed', 'true');
  e.doc.querySelector('.elementor-element-t000001 [data-filter="__all"]').setAttribute('aria-pressed', 'false');
  await e.tick(0);
  assert.equal(e.proCalls.filter((c) => c[0] === 'original').length, 1, 'El refresco original de PRO no corre en el bucle coordinado.');
  assert.equal(e.fetches.length, 1);
  const call = e.fetches[0];
  assert.equal(call.url, 'https://example.test/wp-json/elementor-pro/v1/refresh-loop');
  assert.equal(call.init.method, 'POST');
  assert.equal(call.init.headers['Content-Type'], 'application/json');
  assert.ok(call.init.signal && typeof call.init.signal.aborted === 'boolean', 'La petición lleva signal.');
  assert.deepEqual(call.body, { post_id: 10, widget_id: 'abc1234', widget_filters: { taxonomy: { product_cat: { terms: ['ropa'], logicalJoin: 'OR' } } }, pagination_base_url: 'https://example.test/tienda/' }, 'El estado del filtro de PRO se lee de su marcado (aria-pressed), tras marcar el botón.');
  assert.deepEqual(e.pushes, ['/tienda/?e-filter-abc1234-product_cat=ropa']);
  // getQueryStringInObjectForm(): otro bucle no borra los parámetros del coordinado.
  assert.deepEqual(e.mod.getQueryStringInObjectForm(), { 'e-filter-def5678-product_tag': 'b', 'e-filter-abc1234-product_cat': 'ropa' });
}

// 1b · Arranque real del núcleo: `elementorFrontend.hooks` no existe al evaluar los scripts (init() lo crea en el DOM
// listo y despacha elementor/frontend/init, cuyo oyente de PRO, registrado antes, crea los módulos antes que ningún
// otro). La subclase entra por el aviso previo de PRO (`elementor-pro/modules/init/before`), también si PRO se evalúa
// después de los nuestros, y los formularios por element_ready: el coordinador queda activo y una casilla refresca
// por AJAX (W9 lo encontró en Chrome).
for (const proLate of [false, true]) {
  const e = env({ lateHooks: true, proLate });
  assert.ok((e.filters['elementor-pro/frontend/handlers'] || []).some((f) => f.priority === 20), `Con \`hooks\` creado en init(), la subclase se registra con prioridad 20 (PRO ${proLate ? 'después' : 'antes'}).`);
  assert.ok(e.mod instanceof e.LoopFilter && e.mod.constructor !== e.LoopFilter, 'PRO crea el módulo con la subclase aunque `hooks` llegue en init().');
  assert.ok(e.ctx.digitalisimoWooFilters.active(), 'El coordinador queda activo.');
  const attr = e.doc.querySelector('.elementor-element-f000002 form');
  assert.equal(attr.getAttribute('data-digi-ready'), '1', 'El formulario se inicia en element_ready.');
  assert.ok(attr.classList.contains('digi-filter--live'), 'Con el coordinador activo al iniciarse, el formulario es «en vivo».');
  const box = attr.querySelector('input[value="rojo"]');
  box.checked = true;
  box.dispatchEvent(new Ev('change'));
  await e.tick(300);
  assert.equal(e.fetches.length, 1, 'La casilla refresca el bucle por AJAX.');
}

// 2 · Precio: submit, aria-busy, inserción, región viva, foco; respuesta vieja descartada; abort.
{
  const e = env({ search: '?utm=x&e-page-abc1234=3' });
  const form = e.doc.querySelector('.elementor-element-f000001 form');
  const loop = e.doc.querySelector('[data-elementor-id="10"] .elementor-element-abc1234');
  assert.ok(!form.classList.contains('digi-filter--live'), 'Con «Aplicar» y botón, el botón se ve.');
  const region = loop.nextSibling;
  assert.equal(region.getAttribute('role'), 'status', 'Región viva junto al bucle, creada al registrar el filtro.');
  input(form.querySelector('input[type=number][data-digi-edge=min]'), '20');
  assert.equal(form.querySelector('input[type=range][data-digi-edge=min]').value, '20', 'El deslizador sigue al campo.');
  assert.equal(form.querySelector('input[type=range][data-digi-edge=min]').getAttribute('aria-valuetext'), '20,00 €', 'aria-valuetext con el formato de la tienda.');
  await e.tick(1000);
  assert.equal(e.fetches.length, 0, 'Con «Aplicar», escribir no refresca.');
  const ev = submit(form);
  assert.ok(ev.defaultPrevented, 'Con el coordinador activo, el GET no se envía.');
  await e.tick(0);
  assert.equal(e.fetches.length, 1);
  assert.deepEqual(e.fetches[0].body.widget_filters, { digitalisimo_price: { digi_price_min: { terms: ['20'], logicalJoin: 'AND' } } });
  assert.deepEqual(e.pushes, ['/tienda/?utm=x&e-filter-abc1234-digi_price_min=20'], 'URL: conserva lo ajeno y quita e-page-{bucle}.');
  assert.equal(loop.getAttribute('aria-busy'), 'true');
  assert.ok(loop.classList.contains('e-loading-overlay'));
  // Foco en una ficha del bucle; una segunda petición aborta la primera.
  loop.querySelector('a').focus();
  input(form.querySelector('input[type=number][data-digi-edge=max]'), '50');
  submit(form);
  await e.tick(0);
  assert.equal(e.fetches.length, 2);
  assert.ok(e.fetches[0].aborted, 'El refresco nuevo aborta el anterior.');
  assert.deepEqual(e.fetches[1].body.widget_filters.digitalisimo_price, { digi_price_min: { terms: ['20'], logicalJoin: 'AND' }, digi_price_max: { terms: ['50'], logicalJoin: 'AND' } });
  e.fetches[0].resolve(response(7));
  await e.tick(0);
  assert.equal(loop.querySelectorAll('.e-loop-item').length, 2, 'La respuesta abortada no se inserta.');
  e.fetches[1].resolve(response(3));
  await e.tick(0);
  assert.equal(loop.querySelectorAll('.e-loop-item').length, 3, 'La última respuesta se inserta.');
  assert.equal(loop.getAttribute('aria-busy'), 'false');
  assert.ok(!loop.classList.contains('e-loading-overlay'));
  assert.equal(region.textContent, '3 productos', 'Sin paginación, el total de fichas.');
  assert.equal(e.doc.activeElement, loop, 'El foco que estaba en lo reemplazado pasa al bucle.');
  assert.equal(loop.getAttribute('tabindex'), '-1');
  assert.ok(e.ready.includes(loop) && e.ready.some((el) => el.getAttribute('data-widget_type') === 'heading.default'), 'runReadyTrigger en el bucle y en los elementos de cada ficha.');
  assert.ok((e.doc.events || []).includes('elementor/lazyload/observe'));
  // Respuesta con paginación: «Resultados actualizados»; vacía: «Ningún producto…». El foco en el filtro no se mueve.
  form.querySelector('.digi-filter__submit').focus();
  input(form.querySelector('input[type=number][data-digi-edge=max]'), '60');
  submit(form);
  await e.tick(0);
  e.fetches[2].resolve({ data: response(2).data.replace('</div></div>', '</div><nav class="elementor-pagination"></nav></div>') });
  await e.tick(0);
  assert.equal(region.textContent, 'Resultados actualizados');
  assert.equal(e.doc.activeElement, form.querySelector('.digi-filter__submit'), 'El foco en el filtro no se mueve.');
  submit(form);
  await e.tick(0);
  e.fetches[3].resolve({ data: '' });
  await e.tick(0);
  assert.equal(region.textContent, 'Ningún producto con estos filtros');
  assert.ok(loop.querySelector('.elementor-widget-container'), 'Una respuesta vacía deja el contenedor vacío, como PRO.');
  // Un error del servidor no toca el bucle.
  submit(form);
  await e.tick(0);
  e.fetches[4].fail();
  await e.tick(0);
  assert.equal(loop.getAttribute('aria-busy'), 'false');
  // «Quitar filtro»: limpia los campos y refresca sin recargar.
  const clear = form.querySelector('.digi-filter__clear');
  const cev = new Ev('click');
  clear.dispatchEvent(cev);
  assert.ok(cev.defaultPrevented);
  assert.equal(form.querySelector('input[type=number][data-digi-edge=min]').value, '');
  assert.equal(form.querySelector('input[type=range][data-digi-edge=max]').value, '200');
  await e.tick(0);
  assert.deepEqual(e.fetches[5].body.widget_filters, {});
  assert.equal(e.location.search, '?utm=x');
}

// 2b · Un servidor que responde aunque la petición se abortara: sólo se inserta la última (número de secuencia).
{
  const e = env({ ignoreAbort: true });
  const form = e.doc.querySelector('.elementor-element-f000001 form');
  const loop = e.doc.querySelector('[data-elementor-id="10"] .elementor-element-abc1234');
  input(form.querySelector('input[type=number][data-digi-edge=min]'), '20');
  submit(form);
  await e.tick(0);
  input(form.querySelector('input[type=number][data-digi-edge=min]'), '30');
  submit(form);
  await e.tick(0);
  e.fetches[1].resolve(response(4));
  await e.tick(0);
  e.fetches[0].resolve(response(9));
  await e.tick(0);
  assert.equal(loop.querySelectorAll('.e-loop-item').length, 4, 'La respuesta vieja que llega tarde no pisa la nueva.');
}

// 3 · Debounce: «al cambiar» con 300 ms; casillas 300 ms; botones 0.
{
  const html = PAGE.replace(priceForm('f000001', 'button'), priceForm('f000001', 'change'));
  const e = env({ html });
  const form = e.doc.querySelector('.elementor-element-f000001 form');
  assert.ok(form.classList.contains('digi-filter--live'), '«Al cambiar» oculta el botón con el coordinador activo.');
  const range = form.querySelector('input[type=range][data-digi-edge=min]');
  for (const v of ['10', '20', '30', '40', '50']) { input(range, v); await e.tick(40); }
  assert.equal(e.fetches.length, 0, 'Cinco cambios en 200 ms: todavía ninguna petición.');
  await e.tick(300);
  assert.equal(e.fetches.length, 1, '…y una sola después de 300 ms sin cambios.');
  assert.equal(form.querySelector('input[type=number][data-digi-edge=min]').value, '50');
  // El deslizador mínimo no pasa del máximo.
  input(form.querySelector('input[type=range][data-digi-edge=max]'), '30');
  assert.equal(form.querySelector('input[type=range][data-digi-edge=max]').value, '50');
  await e.tick(300);
  assert.equal(e.fetches.length, 2);
  // Casillas del filtro de atributo (copia del documento 20): 300 ms y post_id del documento del filtro.
  const attr = e.doc.querySelector('.elementor-element-f000002 form');
  assert.ok(attr.classList.contains('digi-filter--live'));
  const box = attr.querySelector('input[value="azul"]');
  box.checked = true;
  box.dispatchEvent(new Ev('change'));
  await e.tick(299);
  assert.equal(e.fetches.length, 2);
  await e.tick(1);
  assert.equal(e.fetches.length, 3);
  assert.equal(e.fetches[2].body.post_id, 20, 'Dos copias del bucle: el post_id es el del documento del filtro.');
  assert.deepEqual(e.fetches[2].body.widget_filters, { digitalisimo_attribute: { digiattr_pa_color: { terms: ['azul'], logicalJoin: 'AND' } } }, 'Cada copia, su propio estado.');
  assert.ok(!e.fetches[1].aborted, 'El refresco de la otra copia no aborta éste.');
  e.fetches[2].resolve(response(1));
  await e.tick(0);
  assert.equal(e.doc.querySelector('[data-elementor-id="20"] .elementor-element-abc1234').querySelectorAll('.e-loop-item').length, 1, 'Se actualiza la copia del documento del filtro…');
  assert.equal(e.doc.querySelector('[data-elementor-id="10"] .elementor-element-abc1234').querySelectorAll('.e-loop-item').length, 2, '…y la otra no.');
  assert.equal(e.doc.querySelector('[data-elementor-id="20"] .elementor-element-abc1234').nextSibling.textContent, '1 producto');
}

// 4 · Botones del filtro de atributo: 0 ms, alternan aria-pressed; Espacio los activa.
{
  const html = PAGE.replace(attrForm('f000002', 'checkboxes'), attrForm('f000002', 'buttons'));
  const e = env({ html });
  const form = e.doc.querySelector('.elementor-element-f000002 form');
  const rojo = form.querySelector('[data-value="rojo"]');
  const ev = new Ev('click');
  rojo.dispatchEvent(ev);
  assert.ok(ev.defaultPrevented, 'El enlace no navega con el coordinador activo.');
  assert.equal(rojo.getAttribute('aria-pressed'), 'true');
  await e.tick(0);
  assert.equal(e.fetches.length, 1, 'Un botón refresca sin espera.');
  const azul = form.querySelector('[data-value="azul"]');
  const key = new Ev('keydown', { key: ' ' });
  azul.dispatchEvent(key);
  assert.ok(key.defaultPrevented && azul.getAttribute('aria-pressed') === 'true', 'Espacio activa el botón (role=button).');
  await e.tick(0);
  assert.deepEqual(e.fetches[1].body.widget_filters.digitalisimo_attribute.digiattr_pa_color, { terms: ['rojo', 'azul'], logicalJoin: 'OR' });
  assert.ok(e.pushes.at(-1).endsWith('e-filter-abc1234-digiattr_pa_color=rojo~azul'), '«O» con ~ en la URL: ' + e.pushes.at(-1));
}

// 5 · popstate: controles, botones de PRO y su almacén (sin refrescos de más), sin pushState; desconocido → recarga.
{
  const e = env();
  const form = e.doc.querySelector('.elementor-element-f000001 form');
  e.location.search = '?e-filter-abc1234-digi_price_min=15&e-filter-abc1234-product_cat=zapatos~ropa';
  const pushes = e.pushes.length;
  e.popstate();
  assert.equal(form.querySelector('input[type=number][data-digi-edge=min]').value, '15', 'El campo vuelve al valor de la URL.');
  assert.equal(form.querySelector('input[type=range][data-digi-edge=min]').value, '15');
  const pro = e.doc.querySelector('.elementor-element-t000001');
  assert.equal(pro.querySelector('[data-filter="zapatos"]').getAttribute('aria-pressed'), 'true');
  assert.equal(pro.querySelector('[data-filter="ropa"]').getAttribute('aria-pressed'), 'true');
  assert.equal(pro.querySelector('[data-filter="__all"]').getAttribute('aria-pressed'), 'false');
  assert.deepEqual(e.proCalls.filter((c) => c[0] !== 'original'), [['remove', 'abc1234', 't000001', '', ''], ['set', 'abc1234', 't000001', 'zapatos,ropa', false, 'OR']], 'El almacén de PRO se rehace por su API.');
  assert.equal(e.proCalls.filter((c) => c[0] === 'original').length, 0, 'Sin el refresco original de PRO.');
  await e.tick(0);
  assert.equal(e.pushes.length, pushes, 'popstate no hace pushState.');
  assert.equal(e.fetches.length, 2, 'Un refresco por copia del bucle (documentos 10 y 20).');
  assert.deepEqual(e.fetches.map((f) => f.body.post_id).sort(), [10, 20]);
  assert.deepEqual(e.fetches.find((f) => f.body.post_id === 10).body.widget_filters, { digitalisimo_price: { digi_price_min: { terms: ['15'], logicalJoin: 'AND' } }, taxonomy: { product_cat: { terms: ['ropa', 'zapatos'], logicalJoin: 'OR' } } }, 'En el orden de los botones.');
  // El mismo estado (un cambio sólo de hash) no vuelve a pedir nada.
  e.location.hash = '#x';
  e.popstate();
  await e.tick(0);
  assert.equal(e.fetches.length, 2);
  // Un filtro que el coordinador no conoce: recarga.
  e.location.search = '?e-filter-abc1234-desconocido=1';
  e.popstate();
  assert.equal(e.location.reloads, 1);
}

// 6 · Sin las piezas de PRO (o en el editor): ninguna sustitución; el formulario va por GET (o no navega en el editor).
{
  const e = env({ proComplete: false });
  assert.equal(e.mod.constructor, e.LoopFilter, 'Falta un método: PRO crea su módulo sin la subclase.');
  const form = e.doc.querySelector('.elementor-element-f000001 form');
  input(form.querySelector('input[type=number][data-digi-edge=min]'), '20');
  assert.ok(!submit(form).defaultPrevented, 'Sin coordinador, el GET de siempre.');
  await e.tick(0);
  assert.equal(e.fetches.length, 0);
  const attr = e.doc.querySelector('.elementor-element-f000002 form');
  assert.ok(!attr.classList.contains('digi-filter--live'), 'Sin coordinador, el botón «Aplicar» se ve.');
  const ed = env({ edit: true });
  assert.ok(submit(ed.doc.querySelector('.elementor-element-f000001 form')).defaultPrevented, 'En el editor, el formulario no navega…');
  ed.mod.refreshLoopWidget('abc1234', 't000001');
  await ed.tick(0);
  assert.equal(ed.fetches.length, 0, '…ni pide nada.');
  assert.deepEqual(ed.proCalls, [['original', 'abc1234', 't000001']], 'En el editor, el refresco de PRO (con widget_model) sigue igual.');
}

// 7 · Tienda: parámetros nativos; casillas unidas por comas; vacíos y deslizadores en su límite fuera; sin AJAX.
{
  const e = env();
  const attr = e.doc.querySelector('.elementor-element-f000004 form');
  attr.querySelectorAll('input[type=checkbox]').forEach((i) => { i.checked = true; });
  assert.ok(submit(attr).defaultPrevented);
  assert.deepEqual(e.location.assigned, ['https://example.test/tienda/?query_type_color=or&filter_color=rojo%2Cazul'], 'La acción sin su cadena: los campos ocultos llevan lo demás.');
  const price = e.doc.querySelector('.elementor-element-f000003 form');
  input(price.querySelector('input[type=number][data-digi-edge=max]'), '80');
  submit(price);
  assert.equal(e.location.assigned[1], 'https://example.test/tienda/?max_price=80&utm=x');
  await e.tick(0);
  assert.equal(e.fetches.length, 0, '«Tienda» nunca usa el refresco del Loop Grid.');
  const both = new URL(e.location.assigned[0]);
  assert.equal(both.searchParams.get('filter_color'), 'rojo,azul', 'WooCommerce recibe los términos separados por comas.');
}

// 8 · API pública (contrato V.15.2).
{
  const e = env();
  const api = e.ctx.digitalisimoWooFilters;
  assert.equal(typeof api.register, 'function');
  assert.equal(typeof api.refresh, 'function');
  assert.equal(api.register('NO', e.doc.querySelector('form')), false, 'Un ID de bucle inválido no se registra.');
  assert.equal(api.register('abc1234', e.doc.querySelector('.elementor-element-f000001 form')), false, 'Un filtro ya registrado no se repite.');
  assert.equal(JSON.stringify(api.state('abc1234')), '{}', 'Estado vacío al empezar.');
}

// 9 · Editor: rellena «Loop Grid» con los del documento sin tocar el valor guardado.
{
  const doc = makeDocument('<div data-elementor-id="7">' + loopHtml('abc1234', '') + loopHtml('fff0000', '') + '</div><div data-elementor-id="8">' + loopHtml('zzz0000', '') + '</div>');
  let options = null, rendered = 0; const on = {};
  const model = { cid: 'c1', set: (k, v) => { options = v; } };
  const page = { collection: { findWhere: ({ name }) => (name === 'selected_element' ? model : null) }, children: { findByModelCid: () => ({ render: () => { rendered++; } }) }, model: { getSetting: () => 'old0000' } };
  const elementor = { getPanelView: () => ({ getCurrentPageView: () => page }), $previewContents: [doc], config: { document: { id: 7 } }, channels: { editor: { on: (name, fn) => { on[name] = fn; } } } };
  const ctx = { elementor, addEventListener: () => {} };
  ctx.window = ctx;
  vm.runInNewContext(source.editor, ctx);
  on['editor:widget:digitalisimo-woo-attribute-filter:section_filter:activated']();
  assert.deepEqual({ ...options }, { '': 'Elige un Loop Grid', abc1234: 'Loop Grid 1', fff0000: 'Loop Grid 2', old0000: 'Loop Grid que ya no está en este documento' }, 'Sólo los Loop Grid del documento, y el valor guardado se conserva.');
  assert.equal(rendered, 1);
  assert.ok(on['editor:widget:digitalisimo-woo-price-filter:section_filter:activated'], 'También en el filtro de precio.');
}

console.log('DIGITALÍSIMO Elements: coordinador de filtros (petición propia, aborto, debounce, popstate, foco, destino por documento) y formularios validados.');
