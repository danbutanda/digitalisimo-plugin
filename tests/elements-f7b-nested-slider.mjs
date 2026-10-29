// F7b, frente F: script y hoja del slider anidado ligero (modules/digitalisimo-f7b/assets/{js,css}/nested-slider.*,
// V.7 y V.13) en un DOM falso con `vm` (sin navegador): estados (inert, clases, aria-current, aria-disabled, aria-live),
// botones, teclado sólo sobre los controles (y RTL), reproducción automática y sus pausas (botón, puntero, foco, fuera
// de pantalla con un solo IntersectionObserver, pestaña oculta), movimiento reducido, editor (sin reproducción
// automática, `data-digi-start`, `digi-nslider-go`, repetidor), arranque en `frontend/element_ready` además de la carga,
// idempotencia, un slider anidado dentro de otro y topes de tamaño. La plantilla del editor del fixture
// (`tests/fixtures/f7b-nested-slider.html`, generado por elements-f7b-nested.php) se compila con los delimitadores del
// editor y debe dar el mismo marcado que el servidor para los tres sliders del fixture.
// Uso: (ulimit -v 3000000; timeout 150 node --max-old-space-size=600 tests/elements-f7b-nested-slider.mjs)
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';

const base = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/assets/', import.meta.url);
const jsFile = new URL('js/nested-slider.js', base), cssFile = new URL('css/nested-slider.css', base), editorFile = new URL('js/nested-slider-editor.js', base);
const source = readFileSync(jsFile, 'utf8'), css = readFileSync(cssFile, 'utf8'), editorSource = readFileSync(editorFile, 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

// 1. Topes (V.13), sin jQuery ni URL, y la hoja primero CSS.
ok(statSync(jsFile).size <= 6144, `nested-slider.js cabe en 6 KB (${statSync(jsFile).size} B).`);
ok(statSync(jsFile).size <= 5120, `nested-slider.js cumple el objetivo de 5 KB (${statSync(jsFile).size} B).`);
ok(statSync(cssFile).size <= 4096, `nested-slider.css cabe en 4 KB (${statSync(cssFile).size} B).`);
ok(statSync(cssFile).size <= 3072, `nested-slider.css cumple el objetivo de 3 KB (${statSync(cssFile).size} B).`);
ok(statSync(editorFile).size <= 8192, `nested-slider-editor.js cabe en 8 KB (${statSync(editorFile).size} B).`);
for (const [name, text] of [['nested-slider.js', source], ['nested-slider-editor.js', editorSource]]) {
  assert.doesNotThrow(() => new vm.Script(text, { filename: name }), `${name} compila.`);
  ok(!/jQuery|\$\(|https?:\/\//.test(text), `${name} sin jQuery ni URL.`);
}
ok(css.startsWith(':is(.elementor-element,.elementor-column)[hidden],.digi-nslider [hidden]{display:none!important}'), 'La hoja lleva la regla [hidden] de C2-2 (contenedores, columnas y los controles del slider).');
ok(/\.digi-nslider__track\{[^}]*display:flex[^}]*overflow-x:auto[^}]*scroll-snap-type:x mandatory/.test(css), 'slide: pista flexible con scroll-snap obligatorio en x (sin arrastre propio).');
ok(/\.digi-nslider__track>\.e-con\{flex:0 0 100%[^}]*scroll-snap-align:start/.test(css) && !/align-items:(start|center|flex-start)[^}]*\}[^]*__track/.test(css.split('.digi-nslider__track{')[1].split('}')[0]), 'Cada diapositiva ocupa la pista y se estira a la más alta (align-items por defecto, stretch).');
ok(/\.digi-nslider--stack>\.digi-nslider__track>\.e-con\{grid-area:1\/1;opacity:0;visibility:hidden\}/.test(css) && /\.digi-nslider--stack>\.digi-nslider__track\{display:grid/.test(css), 'fade y scale: diapositivas apiladas con grid-area 1/1 (altura de la más alta).');
ok(/@media \(prefers-reduced-motion:reduce\)\{[^{]*--anim[^{]*\{transition:none\}\}/.test(css) && /\.digi-nslider--anim>/.test(css) && !/--stack>[^{]*\{[^}]*transition:/.test(css), 'Transiciones sólo tras el primer pase (--anim) y ninguna con movimiento reducido.');
ok(/max\(24px,var\(--digi-ns-btn,40px\)\)/.test(css) && /\.digi-nslider \.digi-nslider__dot\{[^}]*width:24px;height:24px/.test(css), 'Botones y puntos de 24 × 24 px como mínimo.');
ok(/:focus-visible\{outline:2px solid currentColor/.test(css) && /\[aria-current=true\]::before\{background:currentColor;transform:scale\(1\.4\)\}/.test(css), 'Foco visible con currentColor; el punto actual se distingue por más que el color (relleno y tamaño).');
ok(!/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(|url\(|--e-global|!important[^}]*\}(?!\n\.digi-nslider\{)/i.test(css.replace(/^[^\n]*\n/, '')), 'Sin paleta propia, url() ni !important fuera de la regla [hidden].');
ok((css.match(/\.([a-z][a-z0-9_-]*)/g) || []).every(c => /^\.(digi-|e-con|elementor-element|elementor-column)/.test(c)), 'Sólo clases digi- (y las del núcleo que se leen: e-con, elementor-element, elementor-column).');
ok(!/height:auto|min-height/.test(css), 'Ninguna altura automática que cambie entre pases.');

// 2. DOM falso.
function classList(node) {
  const get = () => (node.attrs.class || '').split(/\s+/).filter(Boolean);
  const set = list => { node.attrs.class = list.join(' '); };
  return {
    contains: c => get().includes(c),
    add: c => { const l = get(); if (!l.includes(c)) { l.push(c); set(l); } },
    remove: c => set(get().filter(x => x !== c)),
    toggle(c, force) { const has = get().includes(c), want = force === undefined ? !has : !!force; if (want && !has) this.add(c); if (!want && has) this.remove(c); return want; },
  };
}
class Node {
  constructor(tag, attrs = {}) {
    this.tagName = tag.toUpperCase();
    this.attrs = {};
    for (const [k, v] of Object.entries(attrs)) if (v !== null && v !== undefined) this.attrs[k] = String(v);
    this.children = [];
    this.parentNode = null;
    this.listeners = {};
    this.classList = classList(this);
    this.scrollLeft = 0;
    this.clientWidth = 0;
    this.offsetLeft = 0;
    this.scrolls = [];
    this.dir = '';
  }
  get hidden() { return 'hidden' in this.attrs; }
  set hidden(v) { if (v) this.attrs.hidden = ''; else delete this.attrs.hidden; }
  getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; }
  setAttribute(n, v) { this.attrs[n] = String(v); }
  removeAttribute(n) { delete this.attrs[n]; }
  hasAttribute(n) { return n in this.attrs; }
  toggleAttribute(n, force) { const want = force === undefined ? !(n in this.attrs) : !!force; if (want) this.attrs[n] = ''; else delete this.attrs[n]; return want; }
  append(...kids) { for (const k of kids) { k.parentNode = this; this.children.push(k); } return this; }
  closest(tag) { for (let n = this; n; n = n.parentNode) if (n.tagName === tag.toUpperCase()) return n; return null; }
  contains(node) { for (let n = node; n; n = n.parentNode) if (n === this) return true; return false; }
  all(fn, out = []) { for (const k of this.children) { if (fn(k)) out.push(k); k.all(fn, out); } return out; }
  querySelector(sel) {
    const m = /^:scope>\.([\w-]+)$/.exec(sel);
    if (!m) throw new Error('Selector no previsto: ' + sel);
    return this.children.find(k => k.classList.contains(m[1])) || null;
  }
  querySelectorAll(sel) {
    const m = /^\.([\w-]+)$/.exec(sel);
    if (!m) throw new Error('Selector no previsto: ' + sel);
    return this.all(k => k.classList.contains(m[1]));
  }
  addEventListener(type, fn) { (this.listeners[type] = this.listeners[type] || []).push(fn); }
  emit(type, props = {}) {
    const event = { type, target: this, defaultPrevented: false, preventDefault() { this.defaultPrevented = true; }, ...props };
    for (let n = this; n; n = n.parentNode) for (const fn of n.listeners[type] || []) fn(event);
    return event;
  }
  scrollTo(opts) { this.scrolls.push(opts); this.scrollLeft = opts.left; }
  focus() { this.ownerDocument.activeElement = this; }
}

function slider({ effect = 'slide', loop = false, autoplay = 0, hover = false, nav = 'both', count = 3, start = 0, rtl = false, id = 's' } = {}) {
  const root = new Node('section', { class: 'digi-nslider', 'aria-roledescription': 'carrusel', 'aria-label': 'Diapositivas', 'data-digi-effect': effect, 'data-digi-loop': loop ? '1' : null, 'data-digi-autoplay': autoplay || null, 'data-digi-hover': hover ? '1' : null, 'data-digi-start': start || null });
  root.dir = rtl ? 'rtl' : 'ltr';
  const parts = { root };
  if (autoplay) root.append(parts.pause = new Node('button', { type: 'button', class: 'digi-nslider__btn digi-nslider__pause', 'aria-label': 'Pausar', 'data-digi-pause': 'Pausar', 'data-digi-play': 'Reanudar', hidden: '' }));
  if (nav !== 'dots') root.append(parts.prev = new Node('button', { type: 'button', class: 'digi-nslider__btn digi-nslider__prev', 'aria-label': 'Diapositiva anterior' }));
  root.append(parts.box = new Node('div', { class: 'digi-nslider__dots', 'data-digi-dot': 'Ir a la diapositiva %d', hidden: nav === 'arrows' ? '' : null }));
  for (let k = 0; k < count; k++) parts.box.append(new Node('button', { type: 'button', class: 'digi-nslider__dot', 'aria-label': 'Ir a la diapositiva ' + (k + 1), 'aria-current': k ? null : 'true' }));
  if (nav !== 'dots') root.append(parts.next = new Node('button', { type: 'button', class: 'digi-nslider__btn digi-nslider__next', 'aria-label': 'Diapositiva siguiente' }));
  root.append(parts.track = new Node('div', { class: 'digi-nslider__track', id: 'digi-nslider-' + id, 'aria-live': autoplay ? 'off' : 'polite' }));
  parts.track.clientWidth = 800;
  for (let k = 0; k < count; k++) {
    const s = new Node('div', { class: 'elementor-element e-con e-flex', role: 'group', 'aria-roledescription': 'diapositiva', 'aria-label': `${k + 1} de ${count}` });
    s.offsetLeft = 100 + (rtl ? -k : k) * 800;
    s.append(new Node('a', { href: '#e' + k }));
    parts.track.append(s);
  }
  parts.slides = parts.track.children;
  parts.dots = parts.box.children;
  return parts;
}

function page(roots, { reduce = false, frontend = null, ready = 'complete', io = true } = {}) {
  const timers = new Map();
  let now = 0, seq = 0;
  const observers = [];
  const body = new Node('body');
  const document = new Node('#document');
  document.body = body;
  document.readyState = ready;
  document.hidden = false;
  document.activeElement = null;
  document.querySelectorAll = sel => body.querySelectorAll(sel);
  const winListeners = {};
  const ctx = {
    document,
    console,
    matchMedia: q => ({ matches: reduce && /prefers-reduced-motion: reduce/.test(q), media: q }),
    getComputedStyle: n => ({ direction: n.dir || 'ltr' }),
    setTimeout: (fn, ms) => { const id = ++seq; timers.set(id, { fn, at: now + ms, ms }); return id; },
    clearTimeout: id => { timers.delete(id); },
    addEventListener: (type, fn) => { (winListeners[type] = winListeners[type] || []).push(fn); },
  };
  ctx.window = ctx;
  if (io) ctx.IntersectionObserver = class { constructor(cb) { this.cb = cb; this.targets = []; observers.push(this); } observe(el) { this.targets.push(el); } fire(el, v) { this.cb([{ target: el, isIntersecting: v }]); } };
  if (frontend) ctx.elementorFrontend = frontend;
  const walk = (n) => { n.ownerDocument = document; n.children.forEach(walk); };
  for (const r of roots) { body.append(r); }
  walk(body);
  vm.createContext(ctx);
  vm.runInContext(source, ctx, { filename: 'nested-slider.js' });
  return {
    ctx, document, observers,
    pending: () => [...timers.values()].map(t => t.ms),
    tick(ms) { now += ms; for (const [id, t] of [...timers].sort((a, b) => a[1].at - b[1].at)) { if (t.at <= now && timers.has(id)) { timers.delete(id); t.fn(); } } },
    winEmit(type, detail) { for (const fn of winListeners[type] || []) fn({ type, detail }); },
    loaded() { document.readyState = 'complete'; for (const fn of document.listeners.DOMContentLoaded || []) fn({}); },
  };
}
const current = s => [...s.slides].map(n => (n.hasAttribute('inert') ? '-' : 'X')).join('');
const marks = s => [...s.dots].map(n => (n.getAttribute('aria-current') === 'true' ? 'X' : '-')).join('');
const classOf = (n, c) => n.classList.contains(c);

// 3. Arranque y estado inicial (slide, sin bucle ni reproducción automática).
{
  const s = slider();
  const actions = [];
  const p = page([s.root], { frontend: { isEditMode: () => false, hooks: { addAction: (name, fn) => actions.push(fn) } } });
  ok(classOf(s.root, 'digi-nslider--ready') && !classOf(s.root, 'digi-nslider--stack'), 'slide: listo y sin apilar.');
  ok(current(s) === 'X--' && marks(s) === 'X--', 'Al arrancar: sólo la primera diapositiva sin inert y aria-current en el primer punto.');
  ok([...s.slides].every((n, k) => classOf(n, 'digi-nslider__current') === (k === 0)), 'La clase de la actual sólo en la primera.');
  ok(s.prev.getAttribute('aria-disabled') === 'true' && s.next.getAttribute('aria-disabled') === 'false', 'Sin bucle, «anterior» deshabilitado (aria-disabled, sin perder el foco) en la primera.');
  ok(s.track.getAttribute('aria-live') === 'polite' && p.pending().length === 0 && p.observers.length === 0, 'Sin reproducción automática: región viva polite, ningún temporizador ni observador.');
  ok(s.track.scrolls.length === 1 && s.track.scrolls[0].left === 0 && s.track.scrolls[0].behavior === 'auto', 'El arranque coloca la pista sin animación.');
  // Idempotencia: otro escaneo (element_ready) no duplica oyentes.
  const before = (s.root.listeners.click || []).length;
  actions[0]([p.document.body]);
  actions[0]([p.document.body]);
  ok(actions.length === 1 && (s.root.listeners.click || []).length === before && before === 1, 'Arrancar otra vez (element_ready) no duplica los oyentes.');

  // Botones.
  let e = s.next.emit('click');
  ok(current(s) === '-X-' && marks(s) === '-X-' && s.track.scrollLeft === 800 && s.track.scrolls.at(-1).behavior === 'smooth', '«Siguiente»: segunda diapositiva, desplazamiento suave hasta su posición.');
  ok(s.prev.getAttribute('aria-disabled') === 'false', '«Anterior» se habilita.');
  s.box.children[2].emit('click');
  ok(current(s) === '--X' && marks(s) === '--X' && s.next.getAttribute('aria-disabled') === 'true', 'Un punto lleva a su diapositiva; sin bucle, «siguiente» se deshabilita en la última.');
  const scrolls = s.track.scrolls.length;
  s.next.emit('click');
  ok(current(s) === '--X' && s.track.scrolls.length === scrolls, 'Un botón con aria-disabled no hace nada.');
  // Un clic en el icono del botón (svg) cuenta como el botón.
  const icon = new Node('svg'); s.prev.append(icon); icon.ownerDocument = p.document;
  icon.emit('click');
  ok(current(s) === '-X-', 'Un clic en el icono acciona su botón.');
  // Un clic en el contenido de una diapositiva no navega.
  s.slides[1].children[0].emit('click');
  ok(current(s) === '-X-', 'Un clic dentro de una diapositiva no navega.');

  // Teclado: sólo sobre los controles.
  e = s.box.children[1].emit('keydown', { key: 'ArrowRight' });
  ok(e.defaultPrevented && current(s) === '--X' && p.document.activeElement === s.box.children[2], 'Flecha derecha en un punto: siguiente diapositiva y el foco pasa a su punto.');
  e = s.box.children[2].emit('keydown', { key: 'Home' });
  ok(current(s) === 'X--' && p.document.activeElement === s.box.children[0], 'Inicio: primera diapositiva.');
  e = s.next.emit('keydown', { key: 'End' });
  ok(current(s) === '--X' && p.document.activeElement === s.box.children[0], 'Fin desde «siguiente»: última, y el foco se queda en el botón.');
  e = s.slides[2].children[0].emit('keydown', { key: 'ArrowLeft' });
  ok(e.defaultPrevented && current(s) === '--X', 'Las flechas con el foco en el contenido de una diapositiva no cambian de diapositiva ni desplazan la pista (slide).');
  e = s.track.emit('keydown', { key: 'ArrowLeft' });
  ok(e.defaultPrevented && current(s) === '--X', 'Ni con el foco en la pista.');
  const field = new Node('input', { type: 'text' }); s.slides[2].append(field);
  e = field.emit('keydown', { key: 'ArrowLeft' });
  ok(!e.defaultPrevented && current(s) === '--X', 'Dentro de un campo, las flechas mueven el cursor como siempre.');
  const editable = new Node('div'); editable.isContentEditable = true; s.slides[2].append(editable);
  ok(!editable.emit('keydown', { key: 'ArrowRight' }).defaultPrevented, 'También en un elemento editable.');
  ok(!s.slides[2].children[0].emit('keydown', { key: 'ArrowDown' }).defaultPrevented, 'Las flechas verticales siguen desplazando la página.');
  e = s.next.emit('keydown', { key: 'Enter' });
  ok(!e.defaultPrevented, 'Enter y Espacio quedan para el botón nativo.');
  e = s.prev.emit('keydown', { key: 'ArrowLeft' });
  ok(e.defaultPrevented && current(s) === '-X-', 'Flecha izquierda en «anterior»: diapositiva anterior.');

  // Desplazamiento táctil (scroll-snap): al asentarse, el estado sigue a la pista.
  s.track.scrollLeft = 1600;
  s.track.emit('scroll');
  ok(current(s) === '-X-', 'Durante el desplazamiento no cambia nada.');
  p.tick(119);
  ok(current(s) === '-X-', 'Antes de asentarse, tampoco.');
  p.tick(1);
  ok(current(s) === '--X' && marks(s) === '--X', 'Al asentarse (120 ms), la diapositiva visible pasa a ser la actual.');
  // Si la pista se desplaza (táctil, rueda) con el foco dentro de la diapositiva que deja de verse, el foco pasa al punto.
  s.slides[2].children[0].focus();
  s.track.scrollLeft = 800;
  s.track.emit('scroll');
  p.tick(120);
  ok(current(s) === '-X-' && p.document.activeElement === s.box.children[1], 'El foco no se queda en una diapositiva inerte: pasa al punto de la nueva actual.');
}

// 4. RTL: las flechas del teclado siguen la dirección de lectura.
{
  const s = slider({ rtl: true, loop: true });
  page([s.root]);
  s.next.emit('keydown', { key: 'ArrowLeft' });
  ok(current(s) === '-X-', 'En RTL, flecha izquierda es «siguiente».');
  s.next.emit('click');
  ok(s.track.scrollLeft === -1600, 'En RTL, la pista se desplaza hacia la izquierda (scrollLeft negativo).');
}

// 5. Bucle con flechas; fade añade --anim al primer pase (no al arrancar) y no desplaza.
{
  const s = slider({ effect: 'fade', loop: true });
  page([s.root]);
  ok(classOf(s.root, 'digi-nslider--stack') && !classOf(s.root, 'digi-nslider--anim'), 'fade: apilado, sin transición al arrancar (ningún destello).');
  ok(s.prev.getAttribute('aria-disabled') === 'false' && s.next.getAttribute('aria-disabled') === 'false', 'Con bucle, ninguna flecha se deshabilita.');
  s.prev.emit('click');
  ok(current(s) === '--X' && classOf(s.root, 'digi-nslider--anim') && s.track.scrolls.length === 0, 'Con bucle, «anterior» desde la primera va a la última; fade no desplaza la pista.');
  s.next.emit('click');
  ok(current(s) === 'X--', 'Con bucle, «siguiente» desde la última va a la primera.');
  ok(!s.slides[0].children[0].emit('keydown', { key: 'ArrowRight' }).defaultPrevented && current(s) === 'X--', 'fade: las flechas en el contenido no hacen nada (no hay pista que desplazar).');
  const sc = slider({ effect: 'scale', nav: 'arrows' });
  page([sc.root]);
  ok(classOf(sc.root, 'digi-nslider--stack') && sc.box.hidden && current(sc) === 'X--', 'scale: apilado; con sólo flechas los puntos siguen ocultos.');
  const forged = slider({ effect: 'cube' });
  page([forged.root]);
  ok(!classOf(forged.root, 'digi-nslider--stack'), 'Un efecto desconocido se trata como slide.');
}

// 6. Reproducción automática y pausas.
{
  const s = slider({ autoplay: 3000, hover: true, effect: 'fade' });
  const p = page([s.root]);
  ok(!s.pause.hidden && s.pause.getAttribute('aria-label') === 'Pausar' && !classOf(s.root, 'digi-nslider--paused'), 'El botón «Pausar» aparece al arrancar el script.');
  ok(s.track.getAttribute('aria-live') === 'off' && p.pending().join() === '3000', 'Rotando: región viva off y un temporizador con el retraso.');
  ok(p.observers.length === 1 && p.observers[0].targets[0] === s.root, 'Un IntersectionObserver vigila el slider.');
  p.tick(3000);
  ok(current(s) === '-X-' && p.pending().join() === '3000', 'Pasa a la siguiente y programa la próxima.');
  p.tick(3000); p.tick(3000);
  ok(current(s) === 'X--', 'Sin bucle, la reproducción automática vuelve a la primera al terminar.');
  // Botón de pausa.
  s.pause.emit('click');
  ok(classOf(s.root, 'digi-nslider--paused') && s.pause.getAttribute('aria-label') === 'Reanudar' && s.track.getAttribute('aria-live') === 'polite' && p.pending().length === 0, '«Pausar»: se detiene, el botón dice «Reanudar» y la región viva pasa a polite.');
  p.tick(10000);
  ok(current(s) === 'X--', 'Pausado no avanza.');
  s.pause.emit('click');
  ok(s.pause.getAttribute('aria-label') === 'Pausar' && p.pending().join() === '3000' && s.track.getAttribute('aria-live') === 'off', '«Reanudar» la vuelve a poner en marcha.');
  // Puntero (sólo ratón).
  s.root.emit('pointerenter', { pointerType: 'touch' });
  ok(p.pending().length === 1, 'Un toque no la pausa (en táctil no hay «salir»).');
  s.root.emit('pointerenter', { pointerType: 'mouse' });
  ok(p.pending().length === 0 && s.track.getAttribute('aria-live') === 'polite' && s.pause.getAttribute('aria-label') === 'Pausar', 'Con el puntero encima se detiene sin cambiar el botón.');
  s.root.emit('pointerleave', { pointerType: 'mouse' });
  ok(p.pending().join() === '3000', 'Al salir el puntero, sigue.');
  // Foco.
  s.slides[0].children[0].emit('focusin');
  ok(p.pending().length === 0, 'Con el foco dentro del slider se detiene.');
  s.slides[0].children[0].emit('focusout', { relatedTarget: s.next || s.box.children[1] });
  ok(p.pending().length === 0, 'Si el foco se mueve dentro del slider, sigue detenido.');
  s.box.children[1].emit('focusout', { relatedTarget: null });
  ok(p.pending().join() === '3000', 'Cuando el foco sale del slider, sigue.');
  s.pause.emit('focusin');
  ok(p.pending().join() === '3000', 'El foco en el botón de pausa no la detiene (para poder pulsarlo mientras rota).');
  // Fuera de pantalla y pestaña oculta.
  p.observers[0].fire(s.root, false);
  ok(p.pending().length === 0, 'Fuera de la pantalla se detiene.');
  p.observers[0].fire(s.root, true);
  ok(p.pending().join() === '3000', 'Al volver a la pantalla, sigue.');
  p.document.hidden = true; p.document.emit('visibilitychange');
  ok(p.pending().length === 0, 'Con la pestaña oculta se detiene.');
  p.document.hidden = false; p.document.emit('visibilitychange');
  ok(p.pending().join() === '3000', 'Al volver a la pestaña, sigue.');
  // Una acción del usuario reinicia el retraso entero.
  p.tick(2500);
  s.box.children[2].emit('click');
  ok(current(s) === '--X' && p.pending().join() === '3000', 'Elegir una diapositiva reinicia el retraso.');
}

// 7. Un solo observador con dos sliders en la misma página; sin IntersectionObserver, sigue rotando.
{
  const a = slider({ autoplay: 3000, id: 'a' }), b = slider({ autoplay: 4000, id: 'b' });
  const p = page([a.root, b.root]);
  ok(p.observers.length === 1 && p.observers[0].targets.length === 2, 'Dos sliders con reproducción automática: un solo IntersectionObserver para los dos.');
  p.observers[0].fire(a.root, false);
  ok(p.pending().join() === '4000', 'El observador pausa sólo el slider que salió de la pantalla.');
  const c = slider({ autoplay: 3000, id: 'c' });
  const q = page([c.root], { io: false });
  ok(q.pending().join() === '3000', 'Sin IntersectionObserver, la reproducción automática funciona igual.');
}

// 8. Movimiento reducido: sin reproducción automática y sin animación.
{
  const s = slider({ autoplay: 3000 });
  const p = page([s.root], { reduce: true });
  ok(p.pending().length === 0 && classOf(s.root, 'digi-nslider--paused') && s.pause.getAttribute('aria-label') === 'Reanudar' && s.track.getAttribute('aria-live') === 'polite', 'Con movimiento reducido no arranca sola: el botón ofrece «Reanudar» y la región viva es polite.');
  s.next.emit('click');
  ok(s.track.scrolls.at(-1).behavior === 'auto', 'Con movimiento reducido, el cambio de diapositiva es inmediato.');
  s.pause.emit('click');
  ok(p.pending().join() === '3000', 'Quien lo pide expresamente puede ponerla en marcha.');
}

// 9. Editor: sin reproducción automática, diapositiva que se edita, evento del editor y repetidor.
{
  const actions = [];
  const frontend = { isEditMode: () => true, hooks: { addAction: (name, fn) => actions.push([name, fn]) } };
  const s = slider({ autoplay: 3000, start: 2, effect: 'fade' });
  const p = page([s.root], { frontend });
  ok(p.pending().length === 0 && p.observers.length === 0, 'En el editor no hay reproducción automática ni observador.');
  ok(current(s) === '-X-' && !classOf(s.root, 'digi-nslider--anim'), 'data-digi-start conserva la diapositiva que se edita, sin transición.');
  s.root.emit('digi-nslider-go', { detail: 2 });
  ok(current(s) === '--X', 'El editor muestra la diapositiva elegida en el panel (digi-nslider-go).');
  // Repetidor: el núcleo inserta un punto y un contenedor; el script renumera y repinta.
  const dot = new Node('button', { type: 'button', class: 'digi-nslider__dot', 'aria-label': 'Ir a la diapositiva 9' });
  s.box.children.splice(1, 0, dot); dot.parentNode = s.box; dot.ownerDocument = p.document;
  const slideNew = new Node('div', { class: 'e-con' }); s.track.children.splice(1, 0, slideNew); slideNew.parentNode = s.track;
  s.root.digiNslider.paint();
  ok([...s.dots].map(d => d.getAttribute('aria-label').slice(-1)).join('') === '1234' && current(s) === '--X-' && slideNew.hasAttribute('inert'), 'Tras añadir una diapositiva en el editor, los puntos se renumeran y la nueva queda inerte.');
  s.track.children.splice(1, 3); s.box.children.splice(1, 3);
  s.root.digiNslider.paint();
  ok(current(s) === 'X' && marks(s) === 'X', 'Al quitar diapositivas, la actual se ajusta a las que quedan.');
  ok(actions.length === 1 && actions[0][0] === 'frontend/element_ready/digitalisimo-nested-slider.default', 'Se engancha a frontend/element_ready del widget.');
}

// 10. Arranque: carga del documento, element_ready (contenido que llega después) y frontend/init tardío.
{
  const s = slider({ id: 'late' });
  const p = page([s.root], { ready: 'loading' });
  ok(!classOf(s.root, 'digi-nslider--ready'), 'Mientras el documento carga, nada arranca.');
  p.loaded();
  ok(classOf(s.root, 'digi-nslider--ready'), 'Arranca en DOMContentLoaded.');
  const actions = [];
  p.ctx.elementorFrontend = { isEditMode: () => false, hooks: { addAction: (name, fn) => actions.push([name, fn]) } };
  p.winEmit('elementor/frontend/init');
  ok(actions.length === 1 && actions[0][0] === 'frontend/element_ready/digitalisimo-nested-slider.default', 'Sin el frontend al cargar, se engancha cuando llega elementor/frontend/init.');
  const later = slider({ id: 'popup' });
  const wrapper = new Node('div'); wrapper.append(later.root); later.root.ownerDocument = p.document;
  actions[0][1]([wrapper]);
  ok(classOf(later.root, 'digi-nslider--ready') && current(later) === 'X--', 'Un slider que llega después (popup, bucle, filtros) arranca en element_ready.');
  actions[0][1]([]);
  ok(true, 'element_ready sin elemento no rompe nada.');
}

// 11. Un slider dentro de una diapositiva de otro: cada uno sólo sus controles.
{
  const outer = slider({ id: 'outer', loop: true });
  const inner = slider({ id: 'inner', autoplay: 3000 });
  outer.slides[0].append(inner.root);
  const p = page([outer.root]);
  ok(classOf(inner.root, 'digi-nslider--ready') && classOf(outer.root, 'digi-nslider--ready'), 'Los dos arrancan.');
  inner.next.emit('click');
  ok(current(inner) === '-X-' && current(outer) === 'X--', 'Las flechas del interior no mueven el exterior.');
  inner.box.children[0].emit('keydown', { key: 'End' });
  ok(current(inner) === '--X' && current(outer) === 'X--', 'Ni su teclado.');
  ok(p.pending().length === 1 && p.observers.length === 1 && p.observers[0].targets.length === 1 && p.observers[0].targets[0] === inner.root, 'Sólo el interior tiene reproducción automática.');
}

// 12. Editor: registro del tipo sobre NestedElementBase (como nested-tabs.js) y puente de activeItemIndex.
{
  const registered = [];
  const editorHandlers = {};
  class NestedView { onAddChild() { this.added = (this.added || 0) + 1; } }
  class NestedElementBase {}
  const winL = {};
  const ctx = {
    console,
    digitalisimoNestedSlider: { slide: 'diapositiva', of: '%1$s de %2$s' },
    elementor: { modules: { elements: { types: { NestedElementBase: Promise.resolve() } } }, elementsManager: { registerElementType: t => registered.push(t) }, channels: { editor: { on: (ev, fn) => { editorHandlers[ev] = fn; } } } },
    $e: { components: { get: name => (name === 'nested-elements' ? { exports: {} } : null) } },
    addEventListener: (t, fn) => { (winL[t] = winL[t] || []).push(fn); },
  };
  const previewL = {}, appL = {};
  const previewWin = { addEventListener: (t, fn) => { (previewL[t] = previewL[t] || []).push(fn); } };
  ctx.elementor.$preview = [{ contentWindow: previewWin }];
  ctx.elementor.on = (ev, fn) => { (appL[ev] = appL[ev] || []).push(fn); };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(editorSource, ctx, { filename: 'nested-slider-editor.js' });
  ok(registered.length === 0 && (winL['elementor/nested-element-type-loaded'] || []).length === 1, 'Mientras NestedElementBase es una promesa, espera a elementor/nested-element-type-loaded.');
  ctx.elementor.modules.elements.types.NestedElementBase = NestedElementBase;
  ctx.$e.components.get = name => (name === 'nested-elements' ? { exports: { NestedView } } : null);
  winL['elementor/nested-element-type-loaded'][0]();
  winL['elementor/nested-element-type-loaded'][0]();
  ok(registered.length === 1 && registered[0].getType() === 'digitalisimo-nested-slider' && registered[0] instanceof NestedElementBase, 'Registra una vez el tipo digitalisimo-nested-slider sobre NestedElementBase.');
  const View = registered[0].getView();
  ok(View.prototype instanceof NestedView, 'Su vista extiende la de los anidados del núcleo.');
  // Etiquetas de las diapositivas en el editor.
  const models = [{}, {}, {}];
  const views = models.map(m => ({ model: m, attrs: {}, $el: { attr(o) { Object.assign(this.owner.attrs, o); } } }));
  views.forEach(v => { v.$el.owner = v; });
  const view = Object.create(View.prototype);
  view.collection = { length: 3, indexOf: m => models.indexOf(m) };
  view.children = { each: fn => views.forEach(fn) };
  view.onAddChild(views[0]);
  ok(view.added === 1 && views.map(v => v.attrs['aria-label']).join('|') === '1 de 3|2 de 3|3 de 3' && views.every(v => v.attrs.role === 'group' && v.attrs['aria-roledescription'] === 'diapositiva'), 'En el editor, cada contenedor hijo se etiqueta como diapositiva «N de M» (la vista del núcleo también corre).');
  // Puente de activeItemIndex.
  const root = new Node('section', { class: 'digi-nslider' });
  const got = [];
  root.dispatchEvent = ev => got.push(ev);
  root.ownerDocument = { defaultView: { CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init.detail; } } } };
  const el = { querySelector: sel => (sel === '.digi-nslider' ? root : null) };
  editorHandlers['change:editSettings']({ changed: { activeItemIndex: 3 } }, { model: { get: k => (k === 'widgetType' ? 'digitalisimo-nested-slider' : '') }, el });
  editorHandlers['change:editSettings']({ changed: { activeItemIndex: 2 } }, { model: { get: () => 'nested-tabs' }, el });
  editorHandlers['change:editSettings']({ changed: { other: 1 } }, { model: { get: () => 'digitalisimo-nested-slider' }, el });
  ok(got.length === 1 && got[0].type === 'digi-nslider-go' && got[0].detail === 2, 'Elegir la diapositiva 3 en el panel la muestra en el lienzo (sólo para este widget).');
  // Repetidor: en la vista previa, cada cambio repinta el slider del widget afectado (una vez por ventana).
  ok((previewL['elementor/nested-container/atomic-repeater'] || []).length === 1 && (appL['preview:loaded'] || []).length === 1, 'Escucha los cambios del repetidor en la vista previa y vuelve a hacerlo al recargarla.');
  appL['preview:loaded'][0]();
  ok(previewL['elementor/nested-container/atomic-repeater'].length === 1, 'Sin oyentes repetidos en la misma ventana.');
  let painted = 0;
  const sliderRoot = { digiNslider: { paint: () => { painted++; } } };
  previewL['elementor/nested-container/atomic-repeater'][0]({ detail: { container: { view: { el: { querySelector: sel => (sel === '.digi-nslider' ? sliderRoot : null) } } } } });
  previewL['elementor/nested-container/atomic-repeater'][0]({ detail: { container: { view: { el: { querySelector: () => null } } } } });
  previewL['elementor/nested-container/atomic-repeater'][0]({ detail: null });
  ok(painted === 1, 'Tras añadir, quitar o mover diapositivas, el slider renumera y repinta (y nada se rompe con otros widgets).');
}

// 13. Plantilla del editor = marcado del servidor (fixture generado por elements-f7b-nested.php).
{
  const fixture = readFileSync(new URL('./fixtures/f7b-nested-slider.html', import.meta.url), 'utf8');
  const tpl = /<script type="text\/template" id="tmpl-digi-nslider">\n([\s\S]*?)\n<\/script>/.exec(fixture);
  ok(tpl, 'El fixture lleva la plantilla del editor.');
  const escapeHtml = s => String(s).replace(/[&<>"'`]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#x27;', '`': '&#x60;' })[c]);
  // _.template con los delimitadores del editor (evaluate <# #>, interpolate {{{ }}}, escape {{ }}).
  const compile = text => {
    const matcher = /\{\{([^}]+?)\}\}(?!\})|\{\{\{([\s\S]+?)\}\}\}|<#([\s\S]+?)#>|$/g;
    let index = 0, src = "__p+='";
    text.replace(matcher, (match, esc, interp, evaluate, offset) => {
      src += text.slice(index, offset).replace(/\\|'|\r|\n|\u2028|\u2029/g, c => '\\' + ({ '\\': '\\', "'": "'", '\r': 'r', '\n': 'n', '\u2028': 'u2028', '\u2029': 'u2029' })[c]);
      index = offset + match.length;
      if (esc) src += "'+\n((__t=(" + esc + "))==null?'':_.escape(__t))+\n'";
      else if (interp) src += "'+\n((__t=(" + interp + "))==null?'':__t)+\n'";
      else if (evaluate) src += "';\n" + evaluate + "\n__p+='";
      return match;
    });
    return new Function('obj', '_', "var __t,__p='';with(obj||{}){\n" + src + "';\n}\nreturn __p;");
  };
  const render = compile(tpl[1]);
  const _ = { contains: (l, v) => l.includes(v), isString: v => typeof v === 'string', each: (l, fn) => (l || []).forEach(fn), escape: escapeHtml };
  const defaults = { slides: [{}, {}, {}], effect: 'slide', navigation: 'both', arrows_position: 'bar', loop: 'yes', autoplay: '', autoplay_delay: 5000, pause_on_hover: 'yes', carousel_label: 'Diapositivas' };
  const cases = {
    w1slide: { loop: '', carousel_label: 'Novedades' },
    w2fade: { effect: 'fade', autoplay: 'yes', autoplay_delay: 2000, carousel_label: 'Destacados' },
    w3scale: { effect: 'scale', navigation: 'arrows', arrows_position: 'sides', carousel_label: 'Opiniones' },
  };
  const norm = h => h.replace(/>\s+</g, '><').replace(/\s+>/g, '>').replace(/ data-digi-start="1"/, '').replace(/(<div class="digi-nslider__track"[^>]*>)[\s\S]*<\/div><\/section>$/, '$1</div></section>').trim();
  for (const [id, settings] of Object.entries(cases)) {
    const view = { getID: () => id, getEditModel: () => ({ get: () => ({ get: () => undefined }) }) };
    const html = render({ settings: { ...defaults, ...settings }, view }, _);
    const server = new RegExp(`<!-- digi:server:${id} -->([\\s\\S]*?)<!-- /digi:server -->`).exec(fixture);
    ok(server && html.includes('data-digi-start="1"'), `${id}: la plantilla marca la diapositiva que se edita.`);
    ok(norm(html) === norm(server[1]), `${id}: la plantilla del editor da el mismo marcado que el servidor.\n${norm(html)}\n${norm(server[1])}`);
  }
  const forged = render({ settings: { ...defaults, effect: 'cube', navigation: 'none', carousel_label: '<b>x</b>' }, view: { getID: () => 'z', getEditModel: () => null } }, _);
  ok(forged.includes('data-digi-effect="slide"') && forged.includes('digi-nslider__prev') && forged.includes('aria-label="&lt;b&gt;x&lt;/b&gt;"') && !/<b>/.test(forged), 'La plantilla también cierra las listas y escapa el nombre.');
}

console.log(`F7b, slider anidado (vm): ${checks} comprobaciones en verde.`);
