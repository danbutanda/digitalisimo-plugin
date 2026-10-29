// DOM mínimo para las pruebas de los scripts de F4-Woo (lista de deseos y carrito): árbol con atributos, clases,
// `hidden`, selectores simples (etiqueta, .clase, [atributo], [atributo="valor"] y listas con comas), eventos con
// propagación, foco, `innerHTML` para el marcado que pintan las rutas y un MutationObserver de `childList`.
const VOID = new Set(['img', 'input', 'br', 'meta', 'link']);

export function createDocument() {
  const observers = [];
  const doc = makeNode('#document');
  doc.readyState = 'complete';
  doc.activeElement = null;
  doc.createElement = (tag) => makeNode(tag, doc);
  doc.documentElement = makeNode('html', doc);
  doc.body = makeNode('body', doc);
  doc.appendChild(doc.documentElement);
  doc.documentElement.appendChild(doc.body);
  doc.activeElement = doc.body;
  doc._observers = observers;
  doc.MutationObserver = class {
    constructor(fn) { this.fn = fn; }
    observe(target) { observers.push({ target, fn: this.fn }); }
    disconnect() {}
  };
  return doc;

  function notify(target) {
    for (const o of observers) if (o.target === target) queueMicrotask(() => o.fn([{ type: 'childList', target }]));
  }

  function makeNode(tag, owner) {
    const n = {
      nodeType: tag === '#text' ? 3 : 1, tagName: tag.toUpperCase(), localName: tag, attrs: {}, childNodes: [], parentNode: null,
      ownerDocument: owner, handlers: {}, _text: '',
      get children() { return n.childNodes.filter((c) => c.nodeType === 1); },
      get firstChild() { return n.childNodes[0] || null; },
      get nextElementSibling() { const s = n.parentNode ? n.parentNode.children : []; return s[s.indexOf(n) + 1] || null; },
      get previousElementSibling() { const s = n.parentNode ? n.parentNode.children : []; const i = s.indexOf(n); return i > 0 ? s[i - 1] : null; },
      get isConnected() { let c = n; while (c) { if (c.tagName === '#DOCUMENT') return true; c = c.parentNode; } return false; },
      get className() { return n.attrs.class || ''; },
      set className(v) { n.attrs.class = String(v); },
      get hidden() { return 'hidden' in n.attrs; },
      set hidden(v) { if (v) n.attrs.hidden = ''; else delete n.attrs.hidden; },
      get value() { return n.attrs.value || ''; },
      set value(v) { n.attrs.value = String(v); },
      get textContent() { return n.nodeType === 3 ? n._text : n.childNodes.map((c) => c.textContent).join(''); },
      set textContent(v) { n.childNodes.forEach((c) => { c.parentNode = null; }); n.childNodes = []; if (String(v) !== '') { const t = makeNode('#text', owner); t._text = String(v); t.parentNode = n; n.childNodes.push(t); } notify(n); },
      get innerHTML() { return n.childNodes.map(serialize).join(''); },
      set innerHTML(html) { n.childNodes.forEach((c) => { c.parentNode = null; }); n.childNodes = []; parse(String(html), n, owner); notify(n); },
      get outerHTML() { return serialize(n); },
      get style() { return { setProperty: (k, v) => { n.styles = { ...(n.styles || {}), [k]: v }; } }; },
      classList: {
        contains: (c) => (n.attrs.class || '').split(/\s+/).includes(c),
        add: (c) => { if (!n.classList.contains(c)) n.attrs.class = ((n.attrs.class || '') + ' ' + c).trim(); },
        remove: (c) => { n.attrs.class = (n.attrs.class || '').split(/\s+/).filter((x) => x && x !== c).join(' '); },
      },
      getAttribute: (k) => (k in n.attrs ? n.attrs[k] : null),
      setAttribute: (k, v) => { n.attrs[k] = String(v); },
      removeAttribute: (k) => { delete n.attrs[k]; },
      hasAttribute: (k) => k in n.attrs,
      appendChild(c) { if (c.parentNode) c.remove(); c.parentNode = n; n.childNodes.push(c); notify(n); return c; },
      append(...cs) { cs.forEach((c) => n.appendChild(c)); },
      remove() { const p = n.parentNode; if (!p) return; p.childNodes = p.childNodes.filter((c) => c !== n); n.parentNode = null; notify(p); if (owner && owner.activeElement && !owner.activeElement.isConnected) owner.activeElement = owner.body; },
      replaceWith(other) { const p = n.parentNode; if (!p) return; const i = p.childNodes.indexOf(n); if (other.parentNode) other.remove(); p.childNodes.splice(i, 1, other); other.parentNode = p; n.parentNode = null; notify(p); if (owner && owner.activeElement && !owner.activeElement.isConnected) owner.activeElement = owner.body; },
      contains(o) { let c = o; while (c) { if (c === n) return true; c = c.parentNode; } return false; },
      matches: (sel) => matches(n, sel),
      closest(sel) { let c = n; while (c && c.nodeType === 1 && c.tagName !== '#DOCUMENT') { if (matches(c, sel)) return c; c = c.parentNode; } return null; },
      querySelectorAll(sel) { const out = []; walk(n, (c) => { if (c !== n && matches(c, sel)) out.push(c); }); return out; },
      querySelector(sel) { return n.querySelectorAll(sel)[0] || null; },
      addEventListener(type, fn) { (n.handlers[type] ||= []).push(fn); },
      removeEventListener(type, fn) { n.handlers[type] = (n.handlers[type] || []).filter((f) => f !== fn); },
      dispatchEvent(ev) {
        ev.target = ev.target || n;
        let c = n;
        while (c) {
          ev.currentTarget = c;
          for (const fn of [...(c.handlers[ev.type] || [])]) fn(ev);
          if (ev._stop || !ev.bubbles) break;
          c = c.parentNode;
        }
        return !ev.defaultPrevented;
      },
      // Como en un navegador: enfocar otro nodo dispara `focusin`, que burbujea.
      focus() { if (!owner || owner.activeElement === n) return; owner.activeElement = n; n.dispatchEvent(event('focusin', { bubbles: true })); },
      click() { n.dispatchEvent(event('click', { bubbles: true })); },
    };
    return n;
  }
}

export function event(type, init = {}) {
  return { type, bubbles: !!init.bubbles, defaultPrevented: false, target: init.target || null, detail: init.detail, key: init.key, preventDefault() { this.defaultPrevented = true; }, stopPropagation() { this._stop = true; } };
}

function walk(n, fn) { for (const c of n.childNodes) { if (c.nodeType === 1) { fn(c); walk(c, fn); } } }

function matches(n, sel) {
  if (n.nodeType !== 1) return false;
  return sel.split(',').some((group) => {
    // Descendientes: la última parte es el nodo; las anteriores, antepasados en orden.
    const parts = group.trim().split(/\s+/);
    if (!simple(n, parts.pop())) return false;
    let c = n.parentNode;
    while (parts.length && c) { if (c.nodeType === 1 && simple(c, parts[parts.length - 1])) parts.pop(); c = c.parentNode; }
    return !parts.length;
  });
}

function simple(n, part) {
  if (n.nodeType !== 1 || n.tagName === '#DOCUMENT') return false;
  {
    const m = part.trim().match(/^([a-z0-9]+)?((?:\.[\w-]+)*)((?:\[[\w-]+(?:="[^"]*")?\])*)$/);
    if (!m) throw new Error('Selector no admitido en el DOM de prueba: ' + part);
    if (m[1] && n.localName !== m[1]) return false;
    for (const c of (m[2].match(/\.[\w-]+/g) || [])) if (!n.classList.contains(c.slice(1))) return false;
    for (const a of (m[3].match(/\[[\w-]+(?:="[^"]*")?\]/g) || [])) {
      const [, k, v] = a.match(/^\[([\w-]+)(?:="([^"]*)")?\]$/);
      if (!(k in n.attrs)) return false;
      if (v !== undefined && n.attrs[k] !== v) return false;
    }
    return true;
  }
}

function decode(t) { return t.replace(/&nbsp;/g, ' ').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&'); }

export function parse(html, parent, owner) {
  const stack = [parent];
  const re = /<(\/?)([a-zA-Z0-9]+)((?:\s+[\w-]+(?:="[^"]*")?)*)\s*\/?>|([^<]+)/g;
  let m;
  while ((m = re.exec(html))) {
    const top = stack[stack.length - 1];
    if (m[4] !== undefined) { const t = owner.createElement('#text'); t.nodeType = 3; t._text = decode(m[4]); t.parentNode = top; top.childNodes.push(t); continue; }
    const tag = m[2].toLowerCase();
    if (m[1]) { while (stack.length > 1 && stack.pop().localName !== tag); continue; }
    const el = owner.createElement(tag);
    for (const a of (m[3] || '').matchAll(/([\w-]+)(?:="([^"]*)")?/g)) el.attrs[a[1]] = a[2] === undefined ? '' : decode(a[2]);
    el.parentNode = top;
    top.childNodes.push(el);
    if (!VOID.has(tag)) stack.push(el);
  }
  return parent;
}

function serialize(n) {
  if (n.nodeType === 3) return n._text;
  const attrs = Object.entries(n.attrs).map(([k, v]) => (v === '' ? ' ' + k : ` ${k}="${v}"`)).join('');
  return `<${n.localName}${attrs}>` + (VOID.has(n.localName) ? '' : n.childNodes.map(serialize).join('') + `</${n.localName}>`);
}

/** Respuesta de `fetch` con cabeceras, JSON o texto. */
export function response(status, body, headers = {}) {
  const text = typeof body === 'string' ? body : JSON.stringify(body);
  return { ok: status >= 200 && status < 300, status, headers: { get: (k) => headers[k] ?? null }, json: () => Promise.resolve(JSON.parse(text)), text: () => Promise.resolve(text) };
}

/** `localStorage` en memoria con el registro de escrituras. */
export function storage(initial = {}) {
  const data = new Map(Object.entries(initial));
  const writes = [];
  return { writes, data, getItem: (k) => (data.has(k) ? data.get(k) : null), setItem: (k, v) => { writes.push(['set', k]); data.set(k, String(v)); }, removeItem: (k) => { writes.push(['remove', k]); data.delete(k); } };
}

export const sleep = (ms = 0) => new Promise((r) => setTimeout(r, ms));
