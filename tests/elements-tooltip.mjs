// F8, frente D: script de la «Información emergente» (modules/digitalisimo-widgets/assets/js/tooltip.js, §3.C.3 y V.10.5)
// en un DOM falso con `vm` (sin navegador): id del globo generado en el cliente y único (también en un bucle, al volver a
// arrancar y en un anfitrión clonado), aria-describedby en los operables propios (y nunca un foco vacío), Escape,
// disparador «clic», volteo arriba ↔ abajo y desplazamiento dentro de la ventana, popups que llegan después, y tope.
// Uso: (ulimit -v 3000000; timeout 150 node --max-old-space-size=600 tests/elements-tooltip.mjs)
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const file = new URL('../digitalisimo-elements/modules/digitalisimo-widgets/assets/js/tooltip.js', import.meta.url);
const source = readFileSync(file, 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// 1. Tope y forma.
ok(statSync(file).size <= 1024, `tooltip.js cabe en 1 KB (${statSync(file).size} B, §5).`);
assert.doesNotThrow(() => new vm.Script(source, { filename: 'tooltip.js' }), 'tooltip.js compila.');
ok(!/jQuery|\$\(|https?:\/\/|innerHTML|insertAdjacentHTML|eval\(/.test(source), 'Sin jQuery, URL ni HTML construido con cadenas.');
ok(!/setAttribute\(\s*['"]tabindex|\.tabIndex\s*=|role=/.test(source), 'El script nunca hace enfocable al anfitrión ni le da un rol (sin foco vacío, V.10.5).');
ok(!/elementorFrontend|localStorage|fetch\(|XMLHttpRequest/.test(source), 'No depende del frontend del núcleo ni hace peticiones.');

// 2. DOM falso.
const toCamel = n => n.replace(/^data-/, '').replace(/-([a-z])/g, (m, c) => c.toUpperCase());
const match = (node, sel) => {
  sel = sel.trim();
  let m;
  if ((m = /^\.([\w-]+)$/.exec(sel))) return node.classList.contains(m[1]);
  if ((m = /^\[([\w-]+)\]$/.exec(sel))) return node.hasAttribute(m[1]);
  if ((m = /^([a-z]+)$/.exec(sel))) return node.tagName === m[1].toUpperCase();
  throw new Error('Selector no previsto: ' + sel);
};
class Node {
  constructor(tag, attrs = {}, ...kids) {
    this.tagName = tag.toUpperCase();
    this.attrs = {};
    for (const [k, v] of Object.entries(attrs)) if (v !== null && v !== undefined) this.attrs[k] = String(v);
    this.children = [];
    this.parentNode = null;
    this.listeners = {};
    this.style = { cssText: '' };
    this.rects = null;
    const self = this;
    this.classList = {
      list: () => (self.attrs.class || '').split(/\s+/).filter(Boolean),
      set(l) { self.attrs.class = l.join(' '); },
      contains(c) { return this.list().includes(c); },
      add(c) { const l = this.list(); if (!l.includes(c)) { l.push(c); this.set(l); } },
      remove(c) { this.set(this.list().filter(x => x !== c)); },
      toggle(c, force) { const has = this.contains(c), want = force === undefined ? !has : !!force; if (want) this.add(c); else this.remove(c); return want; },
      replace(a, b) { const l = this.list(), i = l.indexOf(a); if (i < 0) return false; l[i] = b; this.set([...new Set(l)]); return true; },
    };
    this.dataset = new Proxy({}, {
      get: (o, k) => { const e = Object.entries(self.attrs).find(([n]) => n.startsWith('data-') && toCamel(n) === k); return e ? e[1] : undefined; },
      set: (o, k, v) => { self.attrs['data-' + k.replace(/[A-Z]/g, c => '-' + c.toLowerCase())] = String(v); return true; },
    });
    this.append(...kids);
  }
  get className() { return this.attrs.class || ''; }
  get id() { return this.attrs.id || ''; }
  set id(v) { this.attrs.id = String(v); }
  getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; }
  setAttribute(n, v) { this.attrs[n] = String(v); }
  removeAttribute(n) { delete this.attrs[n]; }
  hasAttribute(n) { return n in this.attrs; }
  append(...kids) { for (const k of kids) { if (typeof k === 'string') continue; k.parentNode = this; this.children.push(k); } return this; }
  all(out = []) { for (const k of this.children) { out.push(k); k.all(out); } return out; }
  querySelectorAll(sel) { const parts = sel.split(','); return this.all().filter(n => parts.some(p => match(n, p))); }
  closest(sel) { for (let n = this; n && n.tagName; n = n.parentNode) if (n.tagName !== '#DOCUMENT' && match(n, sel)) return n; return null; }
  contains(node) { for (let n = node; n; n = n.parentNode) if (n === this) return true; return false; }
  addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); }
  emit(type, props = {}) {
    const event = { type, target: this, ...props };
    for (let n = this; n; n = n.parentNode) for (const fn of n.listeners[type] || []) fn(event);
    return event;
  }
  // Solo los eventos que no burbujean (pointerenter).
  fire(type, props = {}) { for (const fn of this.listeners[type] || []) fn({ type, target: this, ...props }); }
  getBoundingClientRect() {
    const side = (/--(top|right|bottom|left)\b/.exec(this.className) || [])[1];
    const r = (this.rects && this.rects[side]) || { top: 100, bottom: 130, left: 100, right: 200 };
    // El desplazamiento que haya en el estilo en línea mueve la caja, como en el navegador.
    const x = +((/--x:(-?[\d.]+)px/.exec(this.style.cssText) || [])[1] || 0);
    return { ...r, left: r.left + x, right: r.right + x };
  }
}
const bubble = (side = 'top', extra = '') => new Node('span', { class: `digi-tooltip digi-tooltip--${side}${extra}`, role: 'tooltip' }, new Node('b'));
// Widget con globo: clásico (con .elementor-widget-container) u optimizado.
const host = ({ side = 'top', click = false, optimized = false, kids = [], tip = true, id = 'w' } = {}) => {
  const wrapper = new Node('div', { class: 'elementor-element elementor-widget', 'data-id': id, 'data-digi-tooltip': '1', 'data-digi-tooltip-trigger': click ? 'click' : null });
  const content = optimized ? wrapper : new Node('div', { class: 'elementor-widget-container' });
  if (!optimized) wrapper.append(content);
  content.append(...kids);
  const t = tip ? bubble(side) : null;
  if (t) content.append(t);
  return { h: wrapper, t, content };
};
function page(roots, { width = 1024, height = 768 } = {}) {
  const timers = [];
  const body = new Node('body', {}, ...roots);
  const document = new Node('#document', {}, body);
  const winL = {};
  const ctx = {
    document, console, innerWidth: width, innerHeight: height,
    setTimeout: fn => { timers.push(fn); return timers.length; },
    addEventListener: (t, fn) => { (winL[t] = winL[t] || []).push(fn); },
  };
  ctx.self = ctx;
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(source, ctx, { filename: 'tooltip.js' });
  return {
    document, ctx,
    flush() { while (timers.length) timers.shift()(); },
    key(k) { document.emit('keydown', { key: k, keyCode: { Escape: 27, Enter: 13, Tab: 9 }[k] }); },
    win(type) { for (const fn of winL[type] || []) fn({ type }); },
    add(node) { body.append(node); },
  };
}
const shut = t => t.classList.contains('digi-tooltip--shut');
const open = t => t.classList.contains('digi-tooltip--open');
const side = t => (/--(top|right|bottom|left)\b/.exec(t.className) || [])[1];

// 3. Identificadores generados en el cliente.
{
  const a = host({ kids: [new Node('a', { href: '#a' })], id: 'a' }), b = host({ kids: [new Node('a', { href: '#a' })], id: 'a' });
  const p = page([a.h, b.h]);
  ok(/^digi-tooltip-\d+$/.test(a.t.id) && /^digi-tooltip-\d+$/.test(b.t.id) && a.t.id !== b.t.id, `El mismo widget repetido en un bucle recibe ids distintos (${a.t.id}, ${b.t.id}).`);
  const before = [a.t.id, b.t.id];
  p.win('elementor/popup/show');
  ok(a.t.id === before[0] && b.t.id === before[1], 'Arrancar otra vez (al abrirse un popup) no cambia los ids.');
  const listeners = (a.h.listeners.pointerenter || []).length;
  p.win('elementor/popup/show');
  ok(listeners === 1 && a.h.listeners.pointerenter.length === 1, 'Ni duplica los oyentes.');
  // Un anfitrión clonado (copia el id del globo, no el estado del script) recibe un id nuevo.
  const clone = host({ kids: [new Node('a', { href: '#c' })], id: 'c' });
  clone.t.id = a.t.id;
  p.add(clone.h);
  p.win('elementor/popup/show');
  ok(clone.t.id !== a.t.id && /^digi-tooltip-\d+$/.test(clone.t.id), 'Un anfitrión clonado con el id de otro recibe uno nuevo.');
  const ids = p.document.querySelectorAll('.digi-tooltip').map(t => t.id);
  ok(new Set(ids).size === ids.length, `Ningún id repetido en la página (${ids.join(', ')}).`);
}

// 4. Descripciones: operables propios, previos conservados, anidados y sin foco vacío.
{
  const one = host({ kids: [new Node('p', {}, new Node('a', { href: '#x' }))], id: 'one' });
  const two = host({ kids: [new Node('a', { href: '#1' }), new Node('button', { type: 'button', 'aria-describedby': 'previa' }), new Node('input', { type: 'text' }), new Node('div', { tabindex: '0' })], optimized: true, id: 'two' });
  const none = host({ kids: [new Node('h2'), new Node('p')], id: 'none' });
  const innerHost = host({ kids: [new Node('a', { href: '#dentro' })], id: 'inner' });
  const outer = host({ kids: [new Node('a', { href: '#fuera' }), innerHost.h], id: 'outer' });
  page([one.h, two.h, none.h, outer.h]);
  const link = one.content.children[0].children[0];
  ok(link.getAttribute('aria-describedby').trim() === one.t.id, 'Un operable: su descripción es el globo.');
  const [l1, btn, input, div] = two.h.children;
  ok([l1, input, div].every(e => e.getAttribute('aria-describedby').trim() === two.t.id), 'Varios operables (enlace, campo, tabindex): cada uno se describe con el globo (marcado optimizado).');
  ok(btn.getAttribute('aria-describedby') === 'previa ' + two.t.id, 'Una descripción previa se conserva y el globo se añade detrás.');
  ok(none.h.all().every(n => !n.hasAttribute('aria-describedby') && !n.hasAttribute('tabindex')) && !none.h.hasAttribute('tabindex') && !none.h.hasAttribute('aria-describedby') && !none.h.hasAttribute('role'), 'Sin operables: nadie se describe y el anfitrión no se hace enfocable ni recibe un rol (sin foco vacío).');
  ok(/^digi-tooltip-\d+$/.test(none.t.id), 'Aun sin operables, el globo recibe su id (sigue en el árbol de accesibilidad).');
  const outLink = outer.content.children[0], inLink = innerHost.content.children[0];
  ok(outLink.getAttribute('aria-describedby').trim() === outer.t.id && inLink.getAttribute('aria-describedby').trim() === innerHost.t.id, 'Anidados: cada operable sólo con el globo de su propio widget.');
  ok(!one.t.hasAttribute('aria-describedby') && one.t.all().every(n => !n.hasAttribute('aria-describedby')), 'El globo y su contenido no se describen a sí mismos.');
  ok(outer.t !== innerHost.t && [...outer.h.querySelectorAll('.digi-tooltip')].pop() === outer.t, 'El globo propio es el último del widget (el anidado va antes).');
}

// 5. Puntero, foco y Escape (disparador por defecto).
{
  const w = host({ kids: [new Node('a', { href: '#x' })], id: 'hover' }), other = host({ id: 'other' });
  const p = page([w.h, other.h]);
  ok(!shut(w.t) && !open(w.t), 'Por defecto no hay estado: lo muestran :hover y :focus-within (sin JavaScript también).');
  p.key('Enter');
  ok(!shut(w.t), 'Otra tecla no lo descarta.');
  p.key('Escape');
  ok(shut(w.t) && shut(other.t), 'Escape descarta los globos de la página (1.4.13, sin mover el puntero ni el foco).');
  w.content.children[0].emit('pointermove');
  ok(shut(w.t), 'Moverse dentro del widget no lo vuelve a mostrar.');
  w.h.fire('pointerenter');
  ok(!shut(w.t) && shut(other.t), 'Al volver a entrar con el puntero, vuelve a mostrarse (sólo ese).');
  p.key('Escape');
  w.content.children[0].emit('focusin');
  ok(!shut(w.t), 'Al enfocar de nuevo algo dentro, vuelve a mostrarse.');
  ok(!open(w.t), 'El disparador por defecto nunca fuerza el globo abierto.');
  w.h.emit('click');
  ok(!open(w.t) && !shut(w.t), 'Un clic no cambia el disparador por defecto.');
}

// 6. Disparador «clic».
{
  const w = host({ click: true, kids: [new Node('button', { type: 'button' })], id: 'click' });
  const p = page([w.h]);
  const btn = w.content.children[0];
  ok(shut(w.t) && !open(w.t), 'Disparador «clic»: cerrado al arrancar (pasar el puntero o enfocar no lo abren).');
  w.h.fire('pointerenter');
  p.flush();
  ok(shut(w.t), 'Pasar el puntero no lo abre.');
  btn.emit('click');
  ok(open(w.t) && !shut(w.t), 'Un clic (o Intro en el botón) lo abre.');
  w.t.children[0].emit('click');
  ok(open(w.t), 'Un clic dentro del globo no lo cierra (se puede seleccionar su texto).');
  btn.emit('click');
  ok(shut(w.t) && !open(w.t), 'Otro clic lo cierra.');
  btn.emit('click');
  p.key('Escape');
  ok(shut(w.t) && !open(w.t), 'Escape lo cierra.');
  w.h.fire('pointerenter');
  ok(shut(w.t), 'Tras Escape, entrar con el puntero no lo abre (sigue siendo de clic).');
}

// 7. Volteo y desplazamiento dentro de la ventana.
{
  const top = host({ id: 'top', kids: [new Node('a', { href: '#t' })] }), bottom = host({ id: 'bottom', side: 'bottom' }), left = host({ id: 'left', side: 'left' }), right = host({ id: 'right', side: 'right' }), wide = host({ id: 'wide' });
  top.t.rects = { top: { top: -20, bottom: 10, left: 100, right: 200 }, bottom: { top: 60, bottom: 90, left: 100, right: 200 } };
  bottom.t.rects = { bottom: { top: 700, bottom: 790, left: 10, right: 90 }, top: { top: 600, bottom: 690, left: 10, right: 90 } };
  left.t.rects = { left: { top: 100, bottom: 130, left: -40, right: 20 } };
  right.t.rects = { right: { top: 100, bottom: 130, left: 980, right: 1060 } };
  wide.t.rects = { top: { top: 200, bottom: 240, left: -30, right: 226 } };
  const p = page([top.h, bottom.h, left.h, right.h, wide.h], { width: 1024, height: 768 });
  top.h.fire('pointerenter');
  ok(side(top.t) === 'top', 'El volteo se mide con el globo ya visible, en el turno siguiente.');
  p.flush();
  ok(side(top.t) === 'bottom' && !top.t.classList.contains('digi-tooltip--top'), 'Arriba no cabe en la ventana: pasa abajo.');
  ok(top.t.style.cssText === '--x:0px', 'Dentro de la ventana en horizontal: sin desplazamiento.');
  top.t.rects.top = { top: 40, bottom: 70, left: 100, right: 200 };
  top.content.children[0].emit('focusin');
  p.flush();
  ok(side(top.t) === 'top', 'Con sitio arriba, vuelve a su lado (cada vez se recalcula desde el original).');
  bottom.h.fire('pointerenter');
  p.flush();
  ok(side(bottom.t) === 'top', 'Abajo no cabe: pasa arriba.');
  wide.h.fire('pointerenter');
  p.flush();
  ok(wide.t.style.cssText === '--x:30px' && side(wide.t) === 'top', `Se sale por la izquierda: se desplaza 30 px hacia dentro (${wide.t.style.cssText}).`);
  wide.t.rects.top = { top: 200, bottom: 240, left: 900, right: 1100 };
  wide.h.fire('pointerenter');
  p.flush();
  ok(wide.t.style.cssText === '--x:-76px', `Se sale por la derecha: se desplaza 76 px hacia dentro, medido sin el desplazamiento anterior (${wide.t.style.cssText}).`);
  wide.h.fire('pointerenter');
  p.flush();
  ok(wide.t.style.cssText === '--x:-76px', 'Al volver a mostrarse, el desplazamiento no se acumula.');
  left.h.fire('pointerenter');
  right.h.fire('pointerenter');
  p.flush();
  ok(side(left.t) === 'left' && left.t.style.cssText === '--x:40px' && side(right.t) === 'right' && right.t.style.cssText === '--x:-36px', 'A los lados no se voltea: se desplaza dentro de la ventana.');
}

// 8. Popups y anfitriones incompletos.
{
  const p = page([]);
  const late = host({ kids: [new Node('a', { href: '#p' })], id: 'late' });
  const broken = host({ tip: false, kids: [new Node('a', { href: '#b' })], id: 'broken' });
  p.add(late.h);
  p.add(broken.h);
  ok(!late.t.id, 'Un popup que llega después no tiene id hasta que se abre.');
  assert.doesNotThrow(() => p.win('elementor/popup/show'), 'Al abrirse un popup no falla nada.');
  ok(/^digi-tooltip-\d+$/.test(late.t.id) && late.content.children[0].getAttribute('aria-describedby').trim() === late.t.id, 'Al abrirse un popup (elementor/popup/show), sus globos arrancan.');
  ok(!broken.content.children[0].hasAttribute('aria-describedby') && !(broken.h.listeners.pointerenter || []).length, 'Un anfitrión sin globo se ignora (sin oyentes ni descripciones).');
  assert.doesNotThrow(() => p.key('Escape'), 'Escape con anfitriones incompletos no falla.');
}

console.log(`F8, información emergente (vm): ${checks} comprobaciones en verde.`);
