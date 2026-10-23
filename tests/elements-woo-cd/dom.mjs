// DOM mínimo para las pruebas de los frentes C y D de F4-Woo (vista rápida y muestras), sin dependencias.
// Cubre lo que usan `quick-view.js` y `swatches.js`: análisis de HTML (innerHTML, insertAdjacentHTML), selectores
// (etiqueta, #id, .clase, [atributo], [atributo="valor"], :not(), descendiente y listas con coma), eventos con captura
// y burbuja, foco, formularios (select, option, radio, required, reportValidity), <dialog> con showModal(),
// MutationObserver (entrega en microtarea) y la carga de <script> y <link> por un gancho de la prueba.

const VOID = new Set(['img', 'input', 'br', 'hr', 'link', 'meta', 'source']);
const RAW = new Set(['script', 'style', 'template']);
const BOOL = ['hidden', 'disabled', 'required', 'open', 'checked', 'selected', 'multiple'];
const decode = (s) => s.replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const encode = (s) => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

export function createDOM(options = {}) {
  const observers = [];
  const doc = { listeners: {}, activeElement: null, readyState: 'complete', nodeType: 9 };
  const queue = (fn) => Promise.resolve().then(fn);

  function notify(target, record) {
    for (const o of observers) {
      for (const [node, opts] of o.targets) {
        const inside = node === target || (opts.subtree && node.contains(target));
        if (!inside) continue;
        if (record.type === 'childList' && !opts.childList) continue;
        if (record.type === 'attributes' && !opts.attributes) continue;
        o.records.push(record);
        if (!o.pending) { o.pending = true; queue(() => { o.pending = false; const r = o.records.splice(0); if (r.length) o.cb(r, o); }); }
        break;
      }
    }
  }

  class Node {
    constructor() { this.parentNode = null; this.childNodes = []; this.listeners = {}; }
    get children() { return this.childNodes.filter((n) => n.nodeType === 1); }
    get firstChild() { return this.childNodes[0] || null; }
    get textContent() { return this.childNodes.map((n) => n.textContent).join(''); }
    set textContent(v) { this._replace([]); if (v !== '' && v != null) this._append(new Text(String(v))); }
    _append(n, before = null) {
      if (n.parentNode) n.parentNode._remove(n);
      n.parentNode = this;
      const i = before ? this.childNodes.indexOf(before) : -1;
      if (i < 0) this.childNodes.push(n); else this.childNodes.splice(i, 0, n);
      notify(this, { type: 'childList', target: this, addedNodes: [n], removedNodes: [] });
      if (this.isConnected && n.nodeType === 1) for (const el of [n, ...n.all()]) options.onConnect?.(el);
      return n;
    }
    _remove(n) {
      const i = this.childNodes.indexOf(n);
      if (i >= 0) { this.childNodes.splice(i, 1); n.parentNode = null; notify(this, { type: 'childList', target: this, addedNodes: [], removedNodes: [n] }); }
      return n;
    }
    _replace(nodes) {
      const old = this.childNodes.splice(0);
      old.forEach((n) => { n.parentNode = null; });
      nodes.forEach((n) => { n.parentNode = this; this.childNodes.push(n); });
      notify(this, { type: 'childList', target: this, addedNodes: nodes, removedNodes: old });
      if (this.isConnected) for (const n of nodes) if (n.nodeType === 1) for (const el of [n, ...n.all()]) options.onConnect?.(el);
    }
    appendChild(n) { return this._append(n); }
    insertBefore(n, ref) { return this._append(n, ref); }
    removeChild(n) { return this._remove(n); }
    remove() { if (this.parentNode) this.parentNode._remove(this); }
    contains(n) { for (let c = n; c; c = c.parentNode) if (c === this) return true; return false; }
    get isConnected() { let c = this; while (c.parentNode) c = c.parentNode; return c === doc; }
    all() { return this.children.flatMap((c) => [c, ...c.all()]); }
    querySelectorAll(sel) { const groups = parseList(sel); return this.all().filter((el) => groups.some((g) => matchChain(el, g, this))); }
    querySelector(sel) { return this.querySelectorAll(sel)[0] || null; }
    addEventListener(type, fn, opts) { const capture = opts === true || !!(opts && opts.capture); (this.listeners[type] ||= []).push({ fn, capture }); }
    removeEventListener(type, fn) { this.listeners[type] = (this.listeners[type] || []).filter((l) => l.fn !== fn); }
    dispatchEvent(ev) {
      if (!ev.target) ev.target = this;
      const path = [];
      for (let c = this; c; c = c.parentNode) path.push(c);
      if (path[path.length - 1] === doc) path.push(win);
      const call = (node, capture) => {
        for (const l of [...(node.listeners[ev.type] || [])]) { if (l.capture !== capture && node !== ev.target) continue; ev.currentTarget = node; l.fn.call(node, ev); if (ev._stopImmediate) return; }
      };
      for (let i = path.length - 1; i > 0 && !ev._stop; i--) call(path[i], true);
      if (!ev._stop) call(this, false);
      if (ev.bubbles) for (let i = 1; i < path.length && !ev._stop; i++) call(path[i], false);
      return !ev.defaultPrevented;
    }
  }

  class Text extends Node { constructor(v) { super(); this.nodeType = 3; this.data = v; } get textContent() { return this.data; } set textContent(v) { this.data = String(v); } }
  class Comment extends Node { constructor(v) { super(); this.nodeType = 8; this.data = v; } get textContent() { return ''; } }

  class Style {
    constructor(el) { this.el = el; this.props = {}; }
    setProperty(k, v) { this.props[k] = String(v); }
    removeProperty(k) { delete this.props[k]; }
    getPropertyValue(k) { if (k in this.props) return this.props[k]; const m = (this.el.getAttribute('style') || '').match(new RegExp(k.replace(/[-]/g, '\\-') + '\\s*:\\s*([^;]+)')); return m ? m[1].trim() : ''; }
  }

  class Element extends Node {
    constructor(tag) { super(); this.nodeType = 1; this.localName = tag.toLowerCase(); this.tagName = this.localName.toUpperCase(); this.attrs = {}; this.style = new Style(this); this._checked = null; this._value = null; }
    getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; }
    setAttribute(k, v) { this.attrs[k] = String(v); notify(this, { type: 'attributes', target: this, attributeName: k }); }
    removeAttribute(k) { if (k in this.attrs) { delete this.attrs[k]; notify(this, { type: 'attributes', target: this, attributeName: k }); } }
    hasAttribute(k) { return k in this.attrs; }
    get id() { return this.getAttribute('id') || ''; } set id(v) { this.setAttribute('id', v); }
    get className() { return this.getAttribute('class') || ''; } set className(v) { this.setAttribute('class', v); }
    get classList() {
      const el = this, list = () => el.className.split(/\s+/).filter(Boolean), set = (a) => el.setAttribute('class', a.join(' '));
      return {
        contains: (c) => list().includes(c),
        add: (...c) => set([...new Set([...list(), ...c])]),
        remove: (...c) => set(list().filter((x) => !c.includes(x))),
        toggle: (c, f) => { const on = f === undefined ? !list().includes(c) : !!f; set(on ? [...new Set([...list(), c])] : list().filter((x) => x !== c)); return on; },
      };
    }
    get tabIndex() { return this.hasAttribute('tabindex') ? Number(this.getAttribute('tabindex')) : (['a', 'button', 'input', 'select'].includes(this.localName) ? 0 : -1); }
    set tabIndex(v) { this.setAttribute('tabindex', v); }
    get href() { return this.getAttribute('href') || ''; } set href(v) { this.setAttribute('href', v); }
    get src() { return this.getAttribute('src') || ''; } set src(v) { this.setAttribute('src', v); }
    get type() { return this.getAttribute('type') || (this.localName === 'input' ? 'text' : ''); } set type(v) { this.setAttribute('type', v); }
    get name() { return this.getAttribute('name') || ''; } set name(v) { this.setAttribute('name', v); }
    get rel() { return this.getAttribute('rel') || ''; } set rel(v) { this.setAttribute('rel', v); }
    get media() { return this.getAttribute('media') || ''; } set media(v) { this.setAttribute('media', v); }
    get async() { return this.getAttribute('async') !== 'false'; } set async(v) { this.setAttribute('async', v ? 'true' : 'false'); }
    get nonce() { return this.getAttribute('nonce') || ''; } set nonce(v) { this.setAttribute('nonce', v); }
    get options() { return this.querySelectorAll('option'); }
    get form() { const f = this.getAttribute('form'); if (f !== null) { const t = doc.getElementById(f); return t && t.localName === 'form' ? t : null; } return this.closest('form'); }
    get value() {
      if (this.localName === 'select') { const o = this.options.find((x) => x.selected); return o ? o.value : ''; }
      if (this.localName === 'option') return this.hasAttribute('value') ? this.getAttribute('value') : this.textContent;
      return this._value !== null ? this._value : (this.getAttribute('value') || '');
    }
    set value(v) {
      if (this.localName === 'select') { let found = false; for (const o of this.options) { o._selected = !found && o.value === String(v); found = found || o._selected; } return; }
      this._value = String(v);
    }
    get selected() { if (this._selected !== undefined) return this._selected; return this.hasAttribute('selected'); }
    set selected(v) { this._selected = !!v; }
    get checked() { return this._checked !== null ? this._checked : this.hasAttribute('checked'); }
    set checked(v) {
      this._checked = !!v;
      if (v && this.type === 'radio' && this.name) {
        const root = this.form || doc;
        for (const r of root === doc ? doc.querySelectorAll('input') : [...doc.querySelectorAll('input')]) if (r !== this && r.type === 'radio' && r.name === this.name && r.form === this.form) r._checked = false;
      }
    }
    get innerHTML() { return this.childNodes.map(serialize).join(''); }
    set innerHTML(html) { this._replace(parse(html)); }
    insertAdjacentHTML(pos, html) { if (pos !== 'beforeend') throw new Error('posición no admitida'); for (const n of parse(html)) this._append(n); }
    matches(sel) { return parseList(sel).some((g) => matchChain(this, g, null)); }
    closest(sel) { for (let c = this; c && c.nodeType === 1; c = c.parentNode) if (c.matches(sel)) return c; return null; }
    focus() { if (doc.inert && !doc.inert.contains(this)) return; const prev = doc.activeElement; doc.activeElement = this; if (prev !== this) this.dispatchEvent(new Ev('focusin', { bubbles: true })); }
    getBoundingClientRect() { return { left: 100, right: 500, top: 100, bottom: 500 }; }
    reportValidity() { (doc.reported ||= []).push(this); return false; }
    click() { this.dispatchEvent(new Ev('click', { bubbles: true, button: 0 })); }
    get open() { return this.hasAttribute('open'); }
    showModal() { if (this.open) throw new Error('InvalidStateError'); this.setAttribute('open', ''); doc.inert = this; doc.modals = (doc.modals || 0) + 1; }
    close() { if (!this.open) return; this.removeAttribute('open'); if (doc.inert === this) doc.inert = null; this.dispatchEvent(new Ev('close')); }
  }
  for (const k of BOOL) {
    if (['checked', 'selected', 'open'].includes(k)) continue;
    Object.defineProperty(Element.prototype, k, { get() { return this.hasAttribute(k); }, set(v) { if (v) this.setAttribute(k, ''); else this.removeAttribute(k); } });
  }

  function serialize(n) {
    if (n.nodeType === 3) return encode(n.data);
    if (n.nodeType === 8) return `<!--${n.data}-->`;
    const a = Object.entries(n.attrs).map(([k, v]) => (v === '' ? ` ${k}` : ` ${k}="${String(v).replace(/"/g, '&quot;')}"`)).join('');
    return VOID.has(n.localName) ? `<${n.localName}${a}>` : `<${n.localName}${a}>${RAW.has(n.localName) ? n.textContent : n.childNodes.map(serialize).join('')}</${n.localName}>`;
  }

  function parse(html) {
    const root = new Element('#root');
    let cur = root, i = 0;
    const s = String(html);
    while (i < s.length) {
      if (s.startsWith('<!--', i)) { const e = s.indexOf('-->', i); cur._append(new Comment(s.slice(i + 4, e))); i = e + 3; continue; }
      if (s[i] === '<' && s[i + 1] === '/') { const e = s.indexOf('>', i); const tag = s.slice(i + 2, e).trim().toLowerCase(); let c = cur; while (c !== root && c.localName !== tag) c = c.parentNode; if (c !== root) cur = c.parentNode; i = e + 1; continue; }
      if (s[i] === '<' && /[a-z]/i.test(s[i + 1] || '')) {
        const m = /^<([a-zA-Z0-9-]+)((?:\s+[^\s=>\/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*\/?>/.exec(s.slice(i));
        if (!m) { cur._append(new Text(s[i])); i++; continue; }
        const el = new Element(m[1]);
        el._parsed = true;
        for (const a of m[2].matchAll(/([^\s=>\/]+)(?:\s*=\s*("([^"]*)"|'([^']*)'|([^\s>]+)))?/g)) el.attrs[a[1].toLowerCase()] = a[2] === undefined ? '' : decode(a[3] ?? a[4] ?? a[5] ?? '');
        cur._append(el);
        i += m[0].length;
        if (RAW.has(el.localName)) { const e = s.indexOf(`</${el.localName}`, i); el._append(new Text(s.slice(i, e))); i = s.indexOf('>', e) + 1; continue; }
        if (!VOID.has(el.localName) && !m[0].endsWith('/>')) cur = el;
        continue;
      }
      const e = s.indexOf('<', i + 1);
      const end = e < 0 ? s.length : e;
      cur._append(new Text(decode(s.slice(i, end))));
      i = end;
    }
    const nodes = root.childNodes.splice(0);
    nodes.forEach((n) => { n.parentNode = null; });
    return nodes;
  }

  // Selectores: lista con comas; cada grupo es una cadena de compuestos unidos por descendiente.
  function parseList(sel) { return sel.split(',').map((g) => g.trim()).filter(Boolean).map((g) => g.split(/\s+(?![^\[]*\])(?![^(]*\))/).map(parseCompound)); }
  function parseCompound(c) {
    const out = { tag: null, id: null, classes: [], attrs: [], not: [] };
    const re = /^([a-zA-Z0-9*-]+)|#([\w-]+)|\.([\w-]+)|\[([\w-]+)(?:="([^"]*)")?\]|:not\(([^)]+)\)/g;
    let m;
    while ((m = re.exec(c))) {
      if (m[0] === '') { re.lastIndex++; continue; }
      if (m[1]) out.tag = m[1].toLowerCase(); else if (m[2]) out.id = m[2]; else if (m[3]) out.classes.push(m[3]); else if (m[4]) out.attrs.push([m[4], m[5]]); else if (m[6]) out.not.push(parseCompound(m[6]));
    }
    return out;
  }
  function matchCompound(el, c) {
    if (!el || el.nodeType !== 1) return false;
    if (c.tag && c.tag !== '*' && el.localName !== c.tag) return false;
    if (c.id && el.id !== c.id) return false;
    for (const k of c.classes) if (!el.classList.contains(k)) return false;
    for (const [k, v] of c.attrs) if (!el.hasAttribute(k) || (v !== undefined && el.getAttribute(k) !== v)) return false;
    for (const n of c.not) if (matchCompound(el, n)) return false;
    return true;
  }
  function matchChain(el, chain, scope) {
    if (!matchCompound(el, chain[chain.length - 1])) return false;
    let i = chain.length - 2;
    for (let c = el.parentNode; i >= 0 && c; c = c.parentNode) if (matchCompound(c, chain[i])) i--;
    return i < 0;
  }

  class Ev {
    constructor(type, init = {}) { Object.assign(this, { button: 0 }, init); this.type = type; this.bubbles = !!init.bubbles; this.defaultPrevented = false; }
    preventDefault() { this.defaultPrevented = true; }
    stopPropagation() { this._stop = true; }
    stopImmediatePropagation() { this._stop = this._stopImmediate = true; }
  }
  class CustomEv extends Ev { constructor(type, init = {}) { super(type, init); this.detail = init.detail; } }

  class MO {
    constructor(cb) { this.cb = cb; this.targets = []; this.records = []; observers.push(this); }
    observe(node, opts) { this.targets.push([node, opts]); }
    disconnect() { this.targets = []; }
  }

  Object.setPrototypeOf(doc, Node.prototype);
  Object.assign(doc, { parentNode: null, childNodes: [], listeners: {} });
  const html = new Element('html'), head = new Element('head'), body = new Element('body');
  doc._append(html); html._append(head); html._append(body);
  Object.assign(doc, {
    documentElement: html, head, body,
    createElement: (t) => new Element(t),
    createComment: (v) => new Comment(v),
    createTextNode: (v) => new Text(v),
    getElementById: (id) => doc.all().find((el) => el.id === id) || null,
    currentScript: options.currentScript || null,
  });
  const win = { listeners: {}, document: doc };
  Object.setPrototypeOf(win, Node.prototype);
  Object.assign(win, { parentNode: null, childNodes: [], listeners: {} });
  Object.assign(win, {
    window: win, Event: Ev, CustomEvent: CustomEv, MutationObserver: MO, HTMLDialogElement: function HTMLDialogElement() {},
    getComputedStyle: (el) => ({ getPropertyValue: (k) => { for (let c = el; c && c.nodeType === 1; c = c.parentNode) { const v = c.style.getPropertyValue(k); if (v) return v; } return ''; } }),
  });
  return { window: win, document: doc, Element, Event: Ev, CustomEvent: CustomEv, parse };
}
