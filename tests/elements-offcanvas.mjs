import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// DOM mínimo: nodos con eventos, clases, atributos y `closest` por selector simple.
function node(name, attrs = {}) {
  const handlers = {};
  const classes = new Set((attrs.class || '').split(' ').filter(Boolean));
  const n = {
    name, attrs: { ...attrs }, hidden: false, parent: null, children: [], focused: false,
    classList: { add: (c) => classes.add(c), remove: (c) => classes.delete(c), contains: (c) => classes.has(c), toggle: (c, v) => (v ? classes.add(c) : classes.delete(c)) },
    addEventListener(type, fn) { (handlers[type] ||= []).push(fn); },
    dispatch(type, event) { let cur = n; while (cur) { for (const fn of cur.handlers?.[type] || []) fn(event); cur = cur.parent; } },
    handlers,
    getAttribute: (k) => (k in n.attrs ? n.attrs[k] : null),
    setAttribute: (k, v) => { n.attrs[k] = String(v); },
    hasAttribute: (k) => k in n.attrs,
    append(...kids) { for (const k of kids) { k.parent = n; n.children.push(k); } return n; },
    matches(sel) { return sel.split(',').some((s) => { s = s.trim(); if (s.startsWith('#')) return n.attrs.id === s.slice(1); if (s.startsWith('.')) return classes.has(s.slice(1)); const m = s.match(/^\[([\w-]+)\]$/); return m ? m[1] in n.attrs : n.name === s; }); },
    closest(sel) { let cur = n; while (cur) { if (cur.matches && cur.matches(sel)) return cur; cur = cur.parent; } return null; },
    contains(other) { let cur = other; while (cur) { if (cur === n) return true; cur = cur.parent; } return false; },
    all() { return n.children.flatMap((c) => [c, ...c.all()]); },
    querySelectorAll(sel) { if (sel.includes('a[href]')) return n.all().filter((c) => c.name === 'button' || c.name === 'a'); return n.all().filter((c) => c.matches(sel)); },
    querySelector(sel) { return n.querySelectorAll(sel)[0] || null; },
    focus() { globalThis.__active = n; },
    offsetWidth: 10, offsetHeight: 10, getClientRects: () => [1],
  };
  return n;
}

function build(config) {
  const widget = node('div', { 'data-digi-offcanvas': JSON.stringify(config) });
  const open = node('button', { 'data-digi-offcanvas-open': '', 'aria-expanded': 'false' });
  const panel = node('div', { class: 'digi-offcanvas__panel' });
  panel.hidden = true;
  const backdrop = node('div', { 'data-digi-offcanvas-backdrop': '' });
  const bar = node('div', { class: 'digi-offcanvas__bar' });
  const close = node('button', { 'data-digi-offcanvas-close': '' });
  const link = node('a', { href: '/x' });
  bar.append(close, link);
  panel.append(backdrop, bar);
  widget.append(open, panel);
  return { widget, open, panel, backdrop, bar, close, link };
}

function run(config) {
  const parts = build(config);
  const outside = node('a', { id: 'abrir', href: '#abrir' });
  const html = node('html');
  const document = node('document');
  document.readyState = 'complete';
  document.documentElement = html;
  document.querySelectorAll = (sel) => (sel === '[data-digi-offcanvas]' ? [parts.widget] : []);
  Object.defineProperty(document, 'activeElement', { get: () => globalThis.__active });
  outside.parent = document;
  parts.widget.parent = document;
  const context = { document, window: { matchMedia: () => ({ matches: true }), addEventListener() {} }, requestAnimationFrame: (fn) => fn(), setTimeout: (fn) => fn(), clearTimeout() {}, JSON, Array, WeakSet };
  vm.runInNewContext(readFileSync(new URL('../digitalisimo-elements/assets/js/offcanvas.js', import.meta.url), 'utf8'), context);
  return { ...parts, outside, html, document };
}

const ev = (target, extra = {}) => ({ target, key: extra.key, shiftKey: false, prevented: false, preventDefault() { this.prevented = true; } });

// Botón propio: abre, marca el estado, enfoca el primer control y Escape cierra devolviendo el foco.
let t = run({ selector: '', overlay: true, escape: true });
globalThis.__active = t.open;
t.open.dispatch('click', ev(t.open));
assert.equal(t.panel.hidden, false);
assert.ok(t.panel.classList.contains('is-open'));
assert.equal(t.open.getAttribute('aria-expanded'), 'true');
assert.ok(t.html.classList.contains('digi-offcanvas-open'));
assert.equal(globalThis.__active, t.close, 'el foco entra al panel');
t.panel.dispatch('keydown', ev(t.link, { key: 'Escape' }));
assert.equal(t.panel.hidden, true);
assert.equal(t.open.getAttribute('aria-expanded'), 'false');
assert.equal(globalThis.__active, t.open, 'el foco vuelve al botón');

// El fondo cierra sólo si está permitido; Tab desde el último control vuelve al primero.
t = run({ selector: '', overlay: false, escape: false });
t.open.dispatch('click', ev(t.open));
t.panel.dispatch('click', ev(t.backdrop));
t.panel.dispatch('keydown', ev(t.link, { key: 'Escape' }));
assert.equal(t.panel.hidden, false, 'sin permiso, ni el fondo ni Escape cierran');
globalThis.__active = t.link;
const tab = ev(t.link, { key: 'Tab' });
t.panel.dispatch('keydown', tab);
assert.ok(tab.prevented);
assert.equal(globalThis.__active, t.close, 'el foco queda atrapado en el panel');
t.panel.dispatch('click', ev(t.close));
assert.equal(t.panel.hidden, true, 'el botón de cerrar siempre cierra');

// Elementos externos por selector (presentación «custom» de Element Pack).
t = run({ selector: '#abrir', overlay: true, escape: true });
const outsideClick = ev(t.outside);
t.document.dispatch('click', outsideClick);
assert.ok(outsideClick.prevented);
assert.equal(t.panel.hidden, false);
t.panel.dispatch('click', ev(t.backdrop));
assert.equal(t.panel.hidden, true);
assert.equal(globalThis.__active, t.outside);

// Apertura automática.
t = run({ selector: '', overlay: true, escape: true, auto: 0, limit: 1, id: 'p1' });
assert.equal(t.panel.hidden, false, 'se abre sola al cargar');

console.log('DIGITALÍSIMO Elements: interacción del panel lateral validada.');
