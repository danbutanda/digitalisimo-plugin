// F4-Woo, frente E: el script de la barra de envío gratis y del cupón (assets/js/cart-extras.js) con un DOM mínimo.
// Los nodos de estado se reemplazan como hace WooCommerce (`replaceWith`): el campo del cupón no pierde el foco ni lo
// escrito; un nonce caducado (403 o -1) provoca un solo `wc_fragment_refresh` y un solo reintento con el nonce nuevo;
// `remove_coupon` va con `coupon`; los avisos se leen como texto y el error se reconoce por su clase (el aviso de éxito
// también lleva role="alert"); la barra aplica las plantillas de su instancia y se oculta sin estado.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { createDocument, event, response, sleep } from './elements-woo-be/dom.mjs';

const SOURCE = readFileSync(new URL('../digitalisimo-elements/assets/js/cart-extras.js', import.meta.url), 'utf8');
const BAR = '<div class="digi-ship" data-digi-ship data-t-progress="Solo {restante} más (mínimo {umbral})" data-t-done="¡Envío gratis!" hidden><div class="digi-ship__state" data-digi-initial hidden></div></div>';
const COUPON = '<div class="digi-coupon" data-digi-coupon hidden><form class="digi-coupon__form" novalidate><label for="digi-coupon-m1">Código de cupón</label><span><input id="digi-coupon-m1" class="digi-coupon__input" type="text" name="coupon_code"><button type="submit">Aplicar</button></span></form><div class="digi-coupon__state" data-digi-initial hidden></div><p class="digi-coupon__status" role="status"></p></div>';
const shipState = (s, extra = '') => `<div class="digi-ship__state" data-state="${s}" ${extra}><p class="digi-ship__text">x</p><div class="digi-ship__bar" aria-hidden="true"><span></span></div></div>`;
const couponState = (n, codes = []) => `<div class="digi-coupon__state" data-apply="a${n}" data-remove="r${n}">${codes.length ? '<ul>' + codes.map((c) => `<li><span>${c}</span> <button type="button" class="digi-coupon__remove" data-coupon="${c}">Quitar</button></li>`).join('') + '</ul>' : ''}</div>`;
const OK = '<div class="woocommerce-message" role="alert">Cupón aplicado correctamente.</div>';
const ERR = '<ul class="woocommerce-error" role="alert"><li>El cupón «x» no existe.</li></ul>';

function page(handler, params = { wc_ajax_url: '/?wc-ajax=%%endpoint%%' }, extra = {}) {
  const doc = createDocument();
  doc.body.innerHTML = '<div class="elementor-menu-cart__main"><div class="digi-cart-extras">' + BAR + COUPON + '</div></div>';
  const calls = [];
  const refreshes = [];
  const ctx = {
    document: doc, Promise, Math, Number, String, RegExp, Error, JSON, console, setTimeout, clearTimeout, queueMicrotask, URLSearchParams,
    MutationObserver: doc.MutationObserver,
    Event: class { constructor(type, init = {}) { Object.assign(this, event(type, init)); } },
    DOMParser: class { parseFromString(html) { const d = createDocument(); d.body.innerHTML = html; return d; } },
    wc_cart_fragments_params: params,
    fetch: (url, init = {}) => { calls.push({ url, body: Object.fromEntries(new URLSearchParams(init.body)) }); return Promise.resolve(handler(url, calls)); },
    addEventListener() {},
    ...extra,
  };
  ctx.window = ctx;
  doc.body.addEventListener('wc_fragment_refresh', () => refreshes.push(1));
  vm.runInNewContext(SOURCE, ctx);
  const q = (s) => doc.querySelector(s);
  // Lo que hace WooCommerce con cada fragmento: $( clave ).replaceWith( valor ).
  const fragment = (selector, html) => { for (const n of doc.querySelectorAll(selector)) { const holder = doc.createElement('div'); holder.innerHTML = html; n.replaceWith(holder.firstChild); } };
  return { doc, ctx, calls, refreshes, q, fragment };
}

// 1. Barra: plantillas de la instancia, porcentaje y oculta sin estado.
{
  const p = page(() => response(200, ''));
  await sleep(5);
  assert.equal(p.q('.digi-ship').hidden, true, 'sin estado, la barra no se ve');
  p.fragment('div.digi-ship__state', shipState('progress', 'data-remaining="20,00 €" data-threshold="50,00 €" data-percent="60"'));
  await sleep(5);
  assert.equal(p.q('.digi-ship').hidden, false);
  assert.equal(p.q('.digi-ship__text').textContent, 'Solo 20,00 € más (mínimo 50,00 €)', 'el texto de la instancia, con los importes del fragmento');
  assert.equal(p.q('.digi-ship__bar').styles['--digi-ship'], '60%', 'el avance va en una variable (sin estilo en línea en el HTML)');
  p.fragment('div.digi-ship__state', shipState('done', 'data-percent="100"'));
  await sleep(5);
  assert.equal(p.q('.digi-ship__text').textContent, '¡Envío gratis!');
  p.fragment('div.digi-ship__state', '<div class="digi-ship__state" data-state="none" hidden></div>');
  await sleep(5);
  assert.equal(p.q('.digi-ship').hidden, true, 'si no se puede saber, nada');
}

// 2. Cupón: el refresco reemplaza el estado sin quitar el foco ni lo escrito; aplicar con coupon_code y su nonce.
{
  const p = page(() => response(200, OK));
  await sleep(5);
  assert.equal(p.q('.digi-coupon').hidden, true, 'sin estado, el cupón no se ve');
  p.fragment('div.digi-coupon__state', couponState(1));
  await sleep(5);
  assert.equal(p.q('.digi-coupon').hidden, false);
  const input = p.q('.digi-coupon__input');
  input.focus();
  input.value = 'VERA';
  p.fragment('div.digi-coupon__state', couponState(2)); // otra pestaña cambió el carrito
  await sleep(5);
  assert.ok(p.doc.activeElement === input, 'el foco sigue en el campo');
  assert.equal(input.value, 'VERA', 'lo escrito sigue');
  input.value = ' verano10 ';
  p.q('.digi-coupon__form').dispatchEvent(event('submit', { bubbles: true }));
  await sleep(60);
  assert.equal(p.calls.length, 1);
  assert.equal(p.calls[0].url, '/?wc-ajax=apply_coupon');
  assert.deepEqual(p.calls[0].body, { coupon_code: 'verano10', security: 'a2' }, 'el nonce del estado vigente');
  assert.equal(p.q('.digi-coupon__status').textContent, 'Cupón aplicado correctamente.', 'el aviso de éxito (también role=alert) no es un error');
  assert.equal(input.value, '', 'aplicado: el campo se vacía');
  assert.equal(p.refreshes.length, 1, 'y WooCommerce rehace el mini carrito, la barra y la lista');
}

// 3. Error de WooCommerce: se lee como texto; nada se refresca.
{
  const p = page(() => response(200, ERR + '<img src="x" onerror="globalThis.hacked=1">'));
  p.fragment('div.digi-coupon__state', couponState(1));
  await sleep(5);
  p.q('.digi-coupon__input').value = 'x';
  p.q('.digi-coupon__form').dispatchEvent(event('submit', { bubbles: true }));
  await sleep(60);
  assert.equal(p.q('.digi-coupon__status').textContent, 'El cupón «x» no existe.');
  assert.equal(p.q('.digi-coupon__input').value, 'x', 'con error, lo escrito se queda para corregirlo');
  assert.equal(p.refreshes.length, 0);
  assert.equal(globalThis.hacked, undefined);
  assert.ok(p.q('.digi-coupon__status img') === null, 'el HTML de la respuesta nunca entra en la página');
}

// 4. Nonce caducado: un wc_fragment_refresh, espera al estado nuevo y un solo reintento con su nonce.
{
  let n = 0;
  const p = page(() => (++n === 1 ? response(403, '-1') : response(200, OK)));
  p.doc.body.addEventListener('wc_fragment_refresh', () => setTimeout(() => p.fragment('div.digi-coupon__state', couponState(9)), 10));
  p.fragment('div.digi-coupon__state', couponState(1));
  await sleep(5);
  p.q('.digi-coupon__input').value = 'verano10';
  p.q('.digi-coupon__form').dispatchEvent(event('submit', { bubbles: true }));
  await sleep(120);
  assert.deepEqual(p.calls.map((c) => c.body.security), ['a1', 'a9'], 'se repite una vez con el nonce del estado nuevo');
  assert.equal(p.q('.digi-coupon__status').textContent, 'Cupón aplicado correctamente.');
}
{
  const p = page(() => response(200, '-1'));
  p.doc.body.addEventListener('wc_fragment_refresh', () => setTimeout(() => p.fragment('div.digi-coupon__state', couponState(9)), 10));
  p.fragment('div.digi-coupon__state', couponState(1));
  await sleep(5);
  p.q('.digi-coupon__input').value = 'verano10';
  p.q('.digi-coupon__form').dispatchEvent(event('submit', { bubbles: true }));
  await sleep(120);
  assert.equal(p.calls.length, 2, 'si vuelve a fallar, no se insiste');
  assert.equal(p.q('.digi-coupon__status').textContent, 'No se pudo aplicar el cupón. Recarga la página.');
}

// 4b. La espera del estado nuevo sigue a la de WooCommerce (`request_timeout` + 3 s), nunca menos de 8 s.
for (const [timeout, expected] of [[undefined, 8000], [5000, 8000], [60000, 63000]]) {
  const delays = [];
  const params = { wc_ajax_url: '/?wc-ajax=%%endpoint%%', ...(timeout ? { request_timeout: timeout } : {}) };
  const p = page(() => response(403, '-1'), params, { setTimeout: (fn, ms) => { delays.push(ms); return setTimeout(fn, ms >= 8000 ? 20 : ms); } });
  p.fragment('div.digi-coupon__state', couponState(1));
  await sleep(5);
  p.q('.digi-coupon__input').value = 'verano10';
  p.q('.digi-coupon__form').dispatchEvent(event('submit', { bubbles: true }));
  await sleep(80);
  assert.ok(delays.includes(expected), `con request_timeout ${timeout}, se esperan ${expected} ms (${delays.join(', ')})`);
  assert.equal(p.calls.length, 1, 'sin estado nuevo a tiempo, no se repite');
  assert.equal(p.q('.digi-coupon__status').textContent, 'No se pudo aplicar el cupón. Recarga la página.');
}

// 5. Quitar un cupón: `remove_coupon` con `coupon`; el foco pasa al campo cuando el botón desaparece.
{
  const p = page(() => response(200, '<div class="woocommerce-message" role="alert">Se quitó el cupón.</div>'));
  p.doc.body.addEventListener('wc_fragment_refresh', () => setTimeout(() => p.fragment('div.digi-coupon__state', couponState(3)), 5));
  p.fragment('div.digi-coupon__state', couponState(2, ['verano10']));
  await sleep(5);
  const remove = p.q('.digi-coupon__remove');
  remove.focus();
  remove.click();
  await sleep(80);
  assert.equal(p.calls[0].url, '/?wc-ajax=remove_coupon');
  assert.deepEqual(p.calls[0].body, { coupon: 'verano10', security: 'r2' }, 'remove_coupon lee `coupon`, no `coupon_code`');
  assert.ok(p.q('.digi-coupon__remove') === null, 'el botón «Quitar» desaparece con el fragmento');
  assert.ok(p.doc.activeElement === p.q('.digi-coupon__input'), 'el foco que estaba en «Quitar» pasa al campo');
}

// 6. Cupones apagados o sin los parámetros de fragmentos: el formulario no aparece (falla cerrado).
{
  const p = page(() => response(200, OK));
  p.fragment('div.digi-coupon__state', '<div class="digi-coupon__state" hidden></div>');
  await sleep(5);
  assert.equal(p.q('.digi-coupon').hidden, true);
  const q = page(() => response(200, OK), null);
  q.fragment('div.digi-coupon__state', couponState(1));
  await sleep(5);
  assert.equal(q.q('.digi-coupon').hidden, true, 'sin wc-cart-fragments, ni cupón ni barra');
}

// 7. Al cargar: una copia de fragmentos de WooCommerce anterior a la barra (sin sus nodos) provoca un solo refresco; sin
// copia (WooCommerce ya pidió los fragmentos), con una copia que trae los nodos o sin almacenamiento, ninguno.
{
  const params = { wc_ajax_url: '/?wc-ajax=%%endpoint%%', fragment_name: 'wc_fragments_x' };
  const store = (frag) => ({ sessionStorage: { getItem: (key) => (key === 'wc_fragments_x' && frag ? JSON.stringify(frag) : null) } });
  const old = page(() => response(200, ''), params, store({ 'div.widget_shopping_cart_content': '<div></div>' }));
  const none = page(() => response(200, ''), params, store(null));
  const fresh = page(() => response(200, ''), params, store({ 'div.widget_shopping_cart_content': '<div></div>', 'div.digi-ship__state': shipState('none') }));
  const blocked = page(() => response(200, ''), params, { sessionStorage: { getItem() { throw new Error('SecurityError'); } } });
  await sleep(30);
  assert.equal(old.refreshes.length, 1, 'copia anterior a la barra: un refresco');
  assert.equal(none.refreshes.length + fresh.refreshes.length + blocked.refreshes.length, 0, 'sin copia, con los nodos o sin almacenamiento: ningún refresco duplicado');
}

assert.ok(Buffer.byteLength(SOURCE) <= 8192, `cart-extras.js pesa ${Buffer.byteLength(SOURCE)} B (tope 8 KB)`);
const CODE = SOURCE.replace(/^\s*\/\/[^\n]*$/gm, ''); // los comentarios citan el $( clave ).replaceWith() de WooCommerce
assert.ok(!/jQuery|\$\(/.test(CODE), 'sin jQuery: los eventos de WooCommerce se observan con MutationObserver');
assert.ok(!/innerHTML\s*=/.test(SOURCE), 'el script nunca inserta HTML de una respuesta');

console.log('DIGITALÍSIMO Elements: script de la barra de envío gratis y del cupón validado (foco, nonce, quitar y avisos).');
