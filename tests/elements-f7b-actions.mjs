// F7b, frente B: `modules/digitalisimo-f7b/assets/js/actions.js` con `vm` y el DOM mínimo de F4-Woo (V.3 y V.14).
// - Arranque real: con `elementorFrontend` ya iniciado o tras `elementor/frontend/init`; nada en el editor; idempotente
//   (`data-digi-actions-ready`) y contenido que llega después por `frontend/element_ready/global`.
// - Validación en el cliente con las listas cerradas de `Rules` (lo de un kit o de otra versión no se confía).
// - `click`: el envoltorio que el servidor hizo botón (clic, Intro y Espacio), el único enlace o botón, nunca con varios.
// - `hover`: puntero fino o foco, 300 ms de margen sobre el disparador o el destino, Escape, y en táctil como un clic.
// - Foco devuelto al ocultar; `aria-expanded`/`aria-controls` sólo en el control; `id` `digi-t-<id>` sólo si falta.
// - `scroll_in` con un solo IntersectionObserver, `load` con retraso y tope; popups y paneles por `createActionHash()`
//   + `runAction()` del núcleo; `scroll_to` con foco y movimiento reducido; animación; «Cargar más».
// Los nodos se comparan con assert.ok(a === b): un assert.equal sobre nodos del DOM simulado agota la memoria.
import assert from 'node:assert/strict';
import { readFileSync, statSync } from 'node:fs';
import vm from 'node:vm';
import { createDOM } from './elements-woo-cd/dom.mjs';

const file = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/assets/js/actions.js', import.meta.url);
const source = readFileSync(file, 'utf8');
let checks = 0;
const ok = (value, message) => { checks++; assert.ok(value, message); };

ok(statSync(file).size <= 6144, `actions.js cabe en 6 KB (${statSync(file).size} B).`);
ok(!/jQuery|\$\(|\beval\(|innerHTML|new Function/.test(source), 'Sin jQuery, eval, innerHTML ni Function.');
ok(!/https?:\/\//.test(source), 'Sin URLs.');
assert.doesNotThrow(() => new vm.Script(source, { filename: 'actions.js' }));

// El hash y su lectura, como `createActionHash()` y `runAction()` del núcleo (elementor/assets/js/frontend.js).
const core = readFileSync(new URL('../digitalisimo-elements/elementor/assets/js/frontend.js', import.meta.url), 'utf8');
ok(core.includes('createActionHash(action, settings) {\n\t\t\t\treturn encodeURIComponent(`#elementor-action:action=${action}&settings=${btoa(JSON.stringify(settings))}`);'), 'createActionHash() del núcleo.');
ok(core.includes('runAction(url, ...restArgs) {') && core.includes('const actionMatch = url.match(/action=(.+?)&/);') && core.includes('action(settings, ...restArgs);'), 'runAction() del núcleo.');
const decode = (url) => {
  url = decodeURIComponent(decodeURI(url));
  return { action: url.match(/action=(.+?)&/)[1], settings: JSON.parse(Buffer.from(url.match(/settings=(.+)/)[1], 'base64').toString()) };
};

function boot({ html, frontend = true, edit = false, media = {}, urlActions = true, late = false } = {}) {
  const dom = createDOM();
  const { window: w, document: d, Element } = dom;
  d.body.innerHTML = html;
  Element.prototype.scrollIntoView = function (o) { (w.scrolled ||= []).push([this, o]); };
  const timers = [];
  let now = 0;
  const calls = { actions: [], hooks: {}, warn: [], observers: [] };
  Object.assign(w, {
    console: { warn: (m) => calls.warn.push(m), log() {}, error() {} },
    btoa: (s) => Buffer.from(s, 'binary').toString('base64'),
    setTimeout: (fn, ms = 0) => { timers.push({ fn, at: now + ms, id: timers.length + 1 }); return timers.length; },
    clearTimeout: (id) => { const t = timers.find((x) => x.id === id); if (t) t.done = true; },
    matchMedia: (q) => ({ matches: !!media[q.replace(/\s+/g, '')] }),
    IntersectionObserver: class {
      constructor(cb) { this.cb = cb; this.list = []; this.un = []; calls.observers.push(this); }
      observe(el) { this.list.push(el); }
      unobserve(el) { this.un.push(el); }
      fire(el) { this.cb([{ target: el, isIntersecting: true }]); }
    },
  });
  const F = {
    isEditMode: () => edit,
    hooks: { addAction: (name, fn) => { (calls.hooks[name] ||= []).push(fn); } },
    utils: urlActions ? { urlActions: {
      createActionHash: (a, s) => encodeURIComponent(`#elementor-action:action=${a}&settings=${w.btoa(JSON.stringify(s))}`),
      runAction: (url, ...rest) => calls.actions.push([decode(url), ...rest]),
    } } : {},
  };
  if (frontend && !late) w.elementorFrontend = F;
  vm.createContext(w);
  vm.runInContext(source, w);
  if (late) { w.elementorFrontend = F; w.dispatchEvent(new w.CustomEvent('elementor/frontend/init')); }
  const tick = (ms) => { now += ms; for (const t of timers.slice()) if (!t.done && t.at <= now) { t.done = true; t.fn(); } };
  const $ = (s) => d.querySelector(s);
  const ev = (el, type, init = {}) => { const e = new w.Event(type, { bubbles: !/^pointer(enter|leave)$/.test(type), ...init }); el.dispatchEvent(e); return e; };
  return { w, d, $, ev, tick, calls, timers };
}
const attr = (rows) => JSON.stringify(rows).replace(/&/g, '&amp;').replace(/"/g, '&quot;');
const doc = (inner, id = 10) => `<div data-elementor-type="wp-page" data-elementor-id="${id}" class="elementor elementor-${id}">${inner}</div>`;
const con = (id, inner = '', extra = '') => `<div class="elementor-element elementor-element-${id} e-flex e-con-full e-con e-parent" data-id="${id}" data-element_type="container"${extra}>${inner}</div>`;
const FINE = {};

// ---------------------------------------------------------------- Arranque.
{
  const rows = [{ t: 'click', a: 'show', g: 'tgt0001' }];
  const html = doc(con('btn0001', '<span>Abrir</span>', ` role="button" tabindex="0" data-digi-actions="${attr(rows)}"`) + con('tgt0001', '<p>Panel</p>', ' hidden'));
  let t = boot({ html, edit: true });
  ok(!t.$('[data-digi-actions-ready]') && !Object.keys(t.calls.hooks).length, 'En el editor no arranca.');
  t = boot({ html, frontend: false });
  ok(!t.$('[data-digi-actions-ready]'), 'Sin el núcleo del front no hace nada.');
  t = boot({ html, late: true });
  ok(t.$('[data-digi-actions-ready]') && t.calls.hooks['frontend/element_ready/global'].length === 1, 'Arranca con elementor/frontend/init y se engancha a element_ready/global.');
  t.calls.hooks['frontend/element_ready/global'][0]([t.$('[data-id="btn0001"]')]);
  t.ev(t.$('[data-id="btn0001"]'), 'click');
  ok(t.$('[data-id="tgt0001"]').hidden === false, 'Un clic muestra el destino.');
  ok(t.$('[data-id="tgt0001"]').getAttribute('data-digi-actions-ready') === null, 'Sólo el disparador lleva la marca.');
}

// ---------------------------------------------------------------- Envoltorio-botón: clic, Intro, Espacio, aria y foco.
{
  const rows = [{ t: 'click', a: 'toggle', g: 'tgt0001' }];
  const html = doc(con('btn0001', '<span>Abrir</span>', ` role="button" tabindex="0" data-digi-actions="${attr(rows)}"`) + con('tgt0001', '<p>Panel</p><a href="/x" class="dentro">Enlace</a>', ' hidden'));
  const t = boot({ html });
  const btn = t.$('[data-id="btn0001"]');
  const panel = t.$('[data-id="tgt0001"]');
  ok(panel.id === 'digi-t-tgt0001' && btn.getAttribute('aria-controls') === 'digi-t-tgt0001' && btn.getAttribute('aria-expanded') === 'false', 'aria-controls con el id asignado y aria-expanded en el control.');
  ok(panel.getAttribute('aria-expanded') === null && panel.getAttribute('role') === null, 'Nada de aria en el destino.');
  const k = t.ev(btn, 'keydown', { key: 'Enter' });
  ok(k.defaultPrevented && panel.hidden === false && btn.getAttribute('aria-expanded') === 'true', 'Intro activa el envoltorio-botón.');
  t.ev(btn, 'keydown', { key: ' ' });
  ok(panel.hidden === true && btn.getAttribute('aria-expanded') === 'false', 'Espacio también.');
  t.ev(btn, 'keydown', { key: 'a' });
  ok(panel.hidden === true, 'Otra tecla no.');
  t.ev(btn, 'click');
  t.$('.dentro').focus();
  ok(t.d.activeElement === t.$('.dentro'), 'El foco entra en el destino.');
  t.ev(btn, 'click');
  ok(panel.hidden === true && t.d.activeElement === btn, 'Al ocultar lo que tiene el foco, el foco vuelve al disparador.');
  t.ev(t.$('.dentro'), 'keydown', { key: 'Enter' });
  ok(panel.hidden === true, 'Intro sobre un enlace nativo no se intercepta.');
}

// ---------------------------------------------------------------- Botón de cerrar dentro del destino: el foco va al que lo abrió.
{
  const open = [{ t: 'click', a: 'show', g: 'tgt0001' }];
  const close = [{ t: 'click', a: 'hide', g: 'tgt0001' }];
  const html = doc(`<div class="elementor-element elementor-widget" data-id="abr0001" data-digi-actions="${attr(open)}"><button type="button" class="abrir">Abrir</button></div>`
    + con('tgt0001', `<div class="elementor-element elementor-widget" data-id="cer0001" data-digi-actions="${attr(close)}"><button type="button" class="cerrar">Cerrar</button></div>`, ' hidden'));
  const t = boot({ html });
  t.ev(t.$('.abrir'), 'click');
  ok(t.$('[data-id="tgt0001"]').hidden === false && t.$('.abrir').getAttribute('aria-expanded') === 'true', 'El único botón es el control: lo muestra y lleva aria-expanded.');
  ok(t.$('[data-id="abr0001"]').getAttribute('role') === null && t.$('[data-id="abr0001"]').getAttribute('aria-expanded') === null, 'El envoltorio con un botón dentro no recibe role ni aria.');
  t.$('.cerrar').focus();
  t.ev(t.$('.cerrar'), 'click');
  ok(t.$('[data-id="tgt0001"]').hidden === true && t.d.activeElement === t.$('.abrir'), 'Cerrar desde dentro devuelve el foco al botón que lo abre.');
  ok(t.$('.abrir').getAttribute('aria-expanded') === 'false' && t.$('.cerrar').getAttribute('aria-expanded') === 'false', 'aria-expanded al día en los dos controles.');
}

// ---------------------------------------------------------------- Varios descendientes interactivos: sin click; hover sí.
{
  const rows = [{ t: 'click', a: 'show', g: 'tgt0001' }, { t: 'hover', a: 'toggle_class', g: 'tgt0001', c: 'activo' }];
  const html = doc(con('two0001', '<a href="/a">a</a><a href="/b">b</a>', ` data-digi-actions="${attr(rows)}"`) + con('tgt0001', '', ' hidden'));
  const t = boot({ html, media: FINE });
  t.ev(t.$('a[href="/a"]'), 'click');
  ok(t.$('[data-id="tgt0001"]').hidden === true, 'Con dos enlaces, «click» no se registra.');
  t.ev(t.$('[data-id="two0001"]'), 'pointerenter', { pointerType: 'mouse' });
  ok(t.$('[data-id="tgt0001"]').classList.contains('activo'), '«hover» sí (puntero).');
  const stale = doc(con('old0001', '<a href="/a">a</a>', ` role="button" tabindex="0" data-digi-actions="${attr([rows[0]])}"`) + con('tgt0001', '', ' hidden'));
  const s = boot({ html: stale });
  ok(s.$('[data-id="old0001"]').getAttribute('role') === null && s.$('[data-id="old0001"]').getAttribute('tabindex') === null, 'Un envoltorio-botón que ya tiene un control dentro deja de ser botón.');
  s.ev(s.$('a[href="/a"]'), 'click');
  ok(s.$('[data-id="tgt0001"]').hidden === false, '…y el único enlace pasa a ser el control.');
}

// ---------------------------------------------------------------- Validación en el cliente.
{
  const bad = [
    { t: 'click', a: 'set_attribute', g: 'tgt0001', n: 'aria-hidden', v: 'true' },
    { t: 'click', a: 'set_attribute', g: 'tgt0001', n: 'data-e-action-hash', v: '#x' },
    { t: 'click', a: 'set_attribute', g: 'tgt0001', n: 'onclick', v: 'x()' },
    { t: 'click', a: 'set_attribute', g: 'tgt0001', n: 'constructor', v: 'x' },
    { t: 'click', a: 'set_attribute', g: 'tgt0001', n: 'aria-pressed', v: 'sí' },
    { t: 'click', a: 'toggle_class', g: 'tgt0001', c: 'elementor-hidden' },
    { t: 'click', a: 'toggle_class', g: 'tgt0001', c: 'e-con' },
    { t: 'click', a: 'toggle_class', g: 'tgt0001' },
    { t: 'click', a: '__proto__', g: 'tgt0001' },
    { t: 'click', a: 'toString', g: 'tgt0001' },
    { t: 'click', a: 'eval', g: 'tgt0001' },
    { t: 'dblclick', a: 'show', g: 'tgt0001' },
    { t: 'scroll_in', a: 'show', g: 'tgt0001' },
    { t: 'load', a: 'toggle', g: 'tgt0001' },
    { t: 'load', a: 'load_more', g: 'tgt0001' },
    { t: 'click', a: 'show', g: 'tgt 0001' },
    { t: 'click', a: 'show' },
    { t: 'click', a: 'popup_open', p: 'x' },
    { t: 'click', a: 'show', g: 'tgt0001', m: 'fade;x' },
    null, 7, 'show',
  ];
  const html = doc(con('btn0001', '', ` role="button" tabindex="0" data-digi-actions="${attr(bad)}"`) + con('tgt0001', '', ' hidden aria-pressed="false"'));
  const t = boot({ html });
  const target = t.$('[data-id="tgt0001"]');
  t.ev(t.$('[data-id="btn0001"]'), 'click');
  ok(target.hidden === true && target.getAttribute('aria-hidden') === null && target.getAttribute('data-e-action-hash') === null && target.getAttribute('onclick') === null && target.getAttribute('aria-pressed') === 'false' && !target.classList.contains('elementor-hidden'), 'Nada fuera de las listas cerradas se ejecuta en el cliente (C2-7).');
  ok(t.calls.actions.length === 0 && t.timers.length === 0 && t.calls.observers.length === 0, 'Ni acciones de URL, ni temporizadores, ni observadores.');
  for (const broken of ['{roto', '{"t":"click"}', '"show"']) {
    const b = boot({ html: doc(con('btn0001', '', ` role="button" tabindex="0" data-digi-actions="${broken.replace(/"/g, '&quot;')}"`)) });
    ok(b.$('[data-digi-actions-ready]'), 'Un JSON ilegible o que no es una lista no rompe el arranque: ' + broken);
  }
  const many = Array.from({ length: 25 }, () => ({ t: 'click', a: 'toggle_class', g: 'tgt0001', c: 'x' }));
  const m = boot({ html: doc(con('btn0001', '', ` role="button" tabindex="0" data-digi-actions="${attr(many)}"`) + con('tgt0001')) });
  m.ev(m.$('[data-id="btn0001"]'), 'click');
  ok(m.$('[data-id="tgt0001"]').classList.contains('x') === false, 'Como mucho 20 filas: 20 alternancias dejan la clase como estaba.');
}

// ---------------------------------------------------------------- hover: puntero con margen de 300 ms, foco, Escape y táctil.
{
  const rows = [{ t: 'hover', a: 'show', g: 'tgt0001' }, { t: 'hover', a: 'set_attribute', g: 'tgt0001', n: 'data-digi-state', v: 'abierto' }];
  const html = doc(`<div class="elementor-element elementor-widget" data-id="men0001" data-digi-actions="${attr(rows)}"><a href="/menu" class="menu">Menú</a></div>`
    + con('tgt0001', '<a href="/sub" class="sub">Sub</a>', ' hidden data-digi-state="cerrado"'));
  const t = boot({ html, media: FINE });
  const trigger = t.$('[data-id="men0001"]');
  const panel = t.$('[data-id="tgt0001"]');
  const link = t.$('.menu');
  ok(link.getAttribute('aria-expanded') === 'false' && link.getAttribute('aria-controls') === 'digi-t-tgt0001', 'El único enlace es el control: aria-expanded y aria-controls.');
  t.ev(trigger, 'pointerenter', { pointerType: 'mouse' });
  ok(panel.hidden === false && panel.getAttribute('data-digi-state') === 'abierto' && link.getAttribute('aria-expanded') === 'true', 'Puntero encima: se muestra.');
  t.ev(trigger, 'pointerleave', { pointerType: 'mouse' });
  t.tick(299);
  ok(panel.hidden === false, 'Al salir, 300 ms de margen…');
  t.ev(panel, 'pointerenter', { pointerType: 'mouse' });
  t.tick(500);
  ok(panel.hidden === false, '…y sobre el destino sigue visible (1.4.13).');
  t.ev(panel, 'pointerleave', { pointerType: 'mouse' });
  t.tick(300);
  ok(panel.hidden === true && panel.getAttribute('data-digi-state') === 'cerrado' && link.getAttribute('aria-expanded') === 'false', 'Fuera de los dos, se oculta y el atributo vuelve a su valor.');
  // Foco.
  link.focus();
  ok(panel.hidden === false, 'Con el foco en el disparador se muestra.');
  t.$('.sub').focus();
  t.ev(link, 'focusout', { relatedTarget: t.$('.sub') });
  t.tick(10);
  ok(panel.hidden === false, 'El foco pasa al destino: sigue visible.');
  t.ev(t.$('.sub'), 'keydown', { key: 'Escape' });
  ok(panel.hidden === true && t.d.activeElement === link, 'Escape lo cierra y el foco vuelve al disparador.');
  t.tick(10);
  ok(panel.hidden === true, 'Volver al disparador por Escape no lo reabre.');
  t.ev(link, 'focusout', { relatedTarget: t.d.body });
  t.ev(link, 'focusin');
  ok(panel.hidden === false, 'Al volver a enfocarlo después, sí.');
  t.ev(link, 'focusout', { relatedTarget: t.d.body });
  t.tick(0);
  ok(panel.hidden === true, 'El foco fuera de los dos lo oculta.');
  t.ev(trigger, 'pointerenter', { pointerType: 'touch' });
  ok(panel.hidden === true, 'En táctil, entrar no muestra…');
  const tap = t.ev(link, 'click', { pointerType: 'touch' });
  ok(panel.hidden === false && !tap.defaultPrevented, '…un toque sí, como un clic (el enlace real no se bloquea).');
  t.ev(link, 'click', { pointerType: 'touch' });
  t.tick(0);
  ok(panel.hidden === true, 'Otro toque lo oculta.');
  t.ev(trigger, 'pointerenter', { pointerType: 'mouse' });
  t.ev(trigger, 'click', { pointerType: 'mouse' });
  ok(panel.hidden === false, 'Con ratón, el clic no alterna lo que muestra el puntero.');
  const old = boot({ html, media: { '(hover:none)': true } });
  old.ev(old.$('.menu'), 'click');
  ok(old.$('[data-id="tgt0001"]').hidden === false, 'Un clic sin pointerType en un dispositivo sin hover (navegador antiguo) también alterna.');
  const desk = boot({ html, media: {} });
  desk.ev(desk.$('.menu'), 'click');
  ok(desk.$('[data-id="tgt0001"]').hidden === true, 'Con hover, un clic sin pointerType (teclado) no alterna lo que muestra el foco.');
}

// ---------------------------------------------------------------- Acciones de clic con enlace "#" y "Sólo la primera vez".
{
  const rows = [{ t: 'click', a: 'toggle_class', g: 'tgt0001', c: 'abierto', o: 1 }];
  const html = doc(`<div class="elementor-element" data-id="lnk0001" data-digi-actions="${attr(rows)}"><a href="#" class="c">Abrir</a></div>` + con('tgt0001'));
  const t = boot({ html });
  const e1 = t.ev(t.$('.c'), 'click');
  ok(e1.defaultPrevented && t.$('[data-id="tgt0001"]').classList.contains('abierto'), 'Un enlace "#" no salta al inicio y aplica la clase.');
  t.ev(t.$('.c'), 'click');
  ok(t.$('[data-id="tgt0001"]').classList.contains('abierto'), '«Sólo la primera vez»: el segundo clic no la quita.');
  const real = boot({ html: html.replace('href="#"', 'href="/destino"') });
  ok(!real.ev(real.$('.c'), 'click').defaultPrevented, 'Un enlace real sigue su camino.');
}

// ---------------------------------------------------------------- Ámbito: elementos de bucle con los mismos IDs.
{
  const rows = [{ t: 'click', a: 'show', g: 'tgt0001' }];
  const item = (n) => `<div data-elementor-type="loop-item" data-elementor-id="50" class="elementor elementor-50 e-loop-item e-loop-item-${n}">${con('btn0001', '', ` role="button" tabindex="0" data-digi-actions="${attr(rows)}"`)}${con('tgt0001', '<p>Detalle</p>', ' hidden')}</div>`;
  const t = boot({ html: doc(item(1) + item(2)) });
  const [b1, b2] = t.d.querySelectorAll('[data-id="btn0001"]');
  const [p1, p2] = t.d.querySelectorAll('[data-id="tgt0001"]');
  ok(p1.id === 'digi-t-tgt0001' && p2.id === 'digi-t-tgt0001-2' && b2.getAttribute('aria-controls') === 'digi-t-tgt0001-2', 'Ids únicos aunque el bucle repita el elemento.');
  t.ev(b2, 'click');
  ok(p2.hidden === false && p1.hidden === true, 'Cada disparador actúa en su propio elemento del bucle.');
  ok(b1 !== b2, 'Dos disparadores distintos.');
}

// ---------------------------------------------------------------- scroll_in, load, popups, paneles, ir a, animación y «Cargar más».
{
  const rows = [
    { t: 'scroll_in', a: 'toggle_class', g: 'tgt0001', c: 'visto', m: 'fadeIn' },
    { t: 'scroll_in', a: 'popup_open', p: 77 },
    { t: 'load', a: 'offcanvas_toggle', g: 'off0001', d: 50000 },
    { t: 'click', a: 'scroll_to', g: 'tgt0001' },
    { t: 'click', a: 'popup_close' },
    { t: 'click', a: 'load_more', g: 'grd0001' },
  ];
  const second = [{ t: 'scroll_in', a: 'toggle_class', g: 'tgt0001', c: 'otro' }];
  const html = doc(con('btn0001', '', ` role="button" tabindex="0" data-digi-actions="${attr(rows)}"`) + con('sec0001', '', ` data-digi-actions="${attr(second)}"`) + con('tgt0001', '<p>Destino</p>')
    + '<div class="elementor-element elementor-widget-off-canvas" data-id="off0001"></div>'
    + '<div class="elementor-element elementor-widget-loop-grid" data-id="grd0001"><div class="e-load-more-anchor"></div><div class="elementor-button-wrapper"><a class="elementor-button" href="#" role="button">Más</a></div></div>');
  const t = boot({ html, media: { '(prefers-reduced-motion:reduce)': false } });
  const btn = t.$('[data-id="btn0001"]');
  const target = t.$('[data-id="tgt0001"]');
  ok(t.calls.observers.length === 1 && t.calls.observers[0].list.length === 2, 'Un solo IntersectionObserver para toda la página.');
  ok(!target.classList.contains('visto') && t.calls.actions.length === 0, 'Nada antes de verse ni de cargar.');
  t.calls.observers[0].fire(btn);
  ok(target.classList.contains('visto') && target.classList.contains('animated') && target.classList.contains('fadeIn'), 'Al aparecer: la clase y la animación del destino.');
  ok(t.calls.observers[0].un[0] === btn, 'Una sola vez: deja de observarlo.');
  const [popup, event] = t.calls.actions[0];
  ok(popup.action === 'popup:open' && popup.settings.id === '77' && popup.settings.toggle === false && event.target === btn, 'popup_open por createActionHash() + runAction() del núcleo.');
  target.dispatchEvent(new t.w.Event('animationend'));
  ok(!target.classList.contains('animated') && !target.classList.contains('fadeIn'), 'La animación se limpia al terminar.');
  t.tick(9999);
  ok(t.calls.actions.length === 1, '«Al cargar» espera su retraso, con tope de 10 000 ms…');
  t.tick(1);
  const [panel] = t.calls.actions[1];
  ok(panel.action === 'off_canvas:toggle' && panel.settings.id === 'off0001' && panel.settings.displayMode === 'toggle', '…y abre o cierra el panel lateral por su id.');
  const click = t.ev(btn, 'click');
  ok(t.w.scrolled.length === 1 && t.w.scrolled[0][0] === target && t.w.scrolled[0][1].behavior === 'smooth' && t.d.activeElement === target && target.getAttribute('tabindex') === '-1', 'Ir a: foco en el destino (tabindex -1) y desplazamiento suave.');
  const [close, closeEvent] = t.calls.actions[2];
  ok(close.action === 'popup:close' && closeEvent === click, 'popup_close con el evento (el núcleo busca el popup que lo contiene).');
  ok(t.$('.elementor-button').getAttribute('data-clicked') === null, 'Cargar más…');
  let more = 0;
  t.$('.elementor-button').addEventListener('click', () => more++);
  const sel = '.e-load-more-anchor+.elementor-button-wrapper .elementor-button';
  const grid = t.$('[data-id="grd0001"]');
  const native = grid.querySelector.bind(grid);
  grid.querySelector = (s) => (s === sel ? t.$('.elementor-button') : native(s));
  t.ev(btn, 'click');
  ok(more === 1, '…pulsa el botón de PRO que sigue al ancla.');
  const reduced = boot({ html, media: { '(prefers-reduced-motion:reduce)': true } });
  reduced.calls.observers[0].fire(reduced.$('[data-id="btn0001"]'));
  ok(reduced.$('[data-id="tgt0001"]').classList.contains('visto') && !reduced.$('[data-id="tgt0001"]').classList.contains('animated'), 'Con movimiento reducido: la clase sin animación.');
  reduced.ev(reduced.$('[data-id="btn0001"]'), 'click');
  ok(reduced.w.scrolled[0][1].behavior === 'auto', 'Y el desplazamiento sin animar.');
  const bare = boot({ html, urlActions: false });
  bare.calls.observers[0].fire(bare.$('[data-id="btn0001"]'));
  ok(bare.calls.warn.length === 1 && /popup:open/.test(bare.calls.warn[0]), 'Sin urlActions, no hace nada y lo dice en la consola.');
  ok(!t.$('[data-id="sec0001"]').hasAttribute('role'), 'Sin clic ni puntero, nada de role.');
}

// ---------------------------------------------------------------- actions-editor.js: destinos del modelo y «click».
{
  const editorFile = new URL('../digitalisimo-elements/modules/digitalisimo-f7b/assets/js/actions-editor.js', import.meta.url);
  const editor = readFileSync(editorFile, 'utf8');
  ok(statSync(editorFile).size <= 8192 && !/elementorCommon\.ajax|\.ajax\(|\bfetch\(|XMLHttpRequest|admin-ajax/.test(editor), 'actions-editor.js ≤ 8 KB y sin AJAX: el documento sale del modelo del editor.');
  const run = (previewHtml, saved = {}) => {
    const dom = createDOM();
    const { window: w, document: d } = dom;
    d.body.innerHTML = previewHtml;
    const listeners = {};
    const model = (attrs) => ({ attrs, get: (k) => attrs[k], set(k, v) { attrs[k] = v; } });
    const container = (id, title, children = []) => ({ id, label: title, model: { getTitle: () => title, get: () => 'widget' }, children });
    const fields = { trigger: { name: 'trigger', options: { click: 'Clic', hover: 'Puntero', scroll_in: 'Al aparecer', load: 'Al cargar' } }, target: { name: 'target', options: { '': 'Elige' } } };
    const repeater = model({ name: '_digitalisimo_actions', fields });
    const rendered = [];
    const rowControls = [model({ name: 'trigger', options: {} }), model({ name: 'target', options: {} })];
    const row = {
      model: { get: (k) => saved[k] },
      collection: { findWhere: ({ name }) => rowControls.find((m) => m.get('name') === name) },
      getControlViewByModel: (m) => ({ render: () => rendered.push(m.get('name')) }),
    };
    const page = {
      getOption: (k) => (k === 'editedElementView' ? { $el: [d.querySelector('[data-id="me00001"]')], getContainer: () => ({ id: 'me00001' }) } : null),
      getControlModel: (n) => (n === '_digitalisimo_actions' ? repeater : null),
      getControlViewByModel: () => ({ children: { each: (fn) => fn(row) } }),
    };
    const doc = { container: { children: [container('sec0001', 'Sección', [container('me00001', 'Encabezado'), container('pan0001', 'Panel', [container('txt0001', 'Texto')])])] } };
    Object.assign(w, { elementor: { channels: { editor: { on: (name, fn) => { listeners[name] = fn; } } }, documents: { getCurrent: () => doc } }, digitalisimoActionsTexts: { none: 'Elige un elemento', gone: 'Ya no está', noClick: ' (no disponible)' } });
    vm.createContext(w);
    vm.runInContext(editor, w);
    listeners['section:activated']('digitalisimo_actions_section', page);
    return { fields, rowControls, rendered, listeners };
  };
  const one = run('<div data-id="me00001"><h2><a href="/x">Título</a></h2><div class="elementor-element-overlay"><ul class="elementor-editor-element-settings"><li tabindex="0">Editar</li></ul></div></div>', { target: 'borrad1', trigger: 'click' });
  ok(JSON.stringify(Object.keys(one.fields.target.options)) === JSON.stringify(['', 'sec0001', 'pan0001', 'txt0001']), 'Destinos: los elementos del documento abierto, sin el propio.');
  ok(one.fields.target.options.txt0001 === '– – Texto · txt0001' && one.fields.target.options[''] === 'Elige un elemento', 'Con su título, su id y la sangría del nivel.');
  ok('click' in one.fields.trigger.options, 'Con un solo enlace (la interfaz del editor no cuenta), «click» se ofrece.');
  ok(one.rowControls[1].get('options').borrad1 === 'Ya no está' && one.rendered.includes('target') && one.rendered.includes('trigger'), 'Una fila con un destino que ya no existe lo muestra como tal; las filas pintadas se repintan.');
  const two = run('<div data-id="me00001"><a href="/a">a</a><button>b</button></div>', { trigger: 'click' });
  ok(!('click' in two.fields.trigger.options) && 'hover' in two.fields.trigger.options, 'Con varios descendientes interactivos, «click» no se ofrece en las filas nuevas.');
  ok(two.rowControls[0].get('options').click === 'Clic (no disponible)', 'Una fila que ya lo tenía lo muestra como no disponible.');
  const none = run('<div data-id="me00001"><p>Sólo texto</p></div>');
  ok('click' in none.fields.trigger.options, 'Sin descendientes interactivos, «click» se ofrece (el servidor hace botón el envoltorio).');
  const other = run('<div data-id="me00001"></div>');
  other.listeners['section:activated']('otra_seccion', null);
  ok(true, 'Otra sección no hace nada.');
}

console.log(`DIGITALÍSIMO Elements F7b: actions.js en verde con vm (${checks} comprobaciones, ${statSync(file).size} B).`);
