// F4-Woo, frente B: el script de la lista de deseos (assets/js/wishlist.js) con un DOM mínimo y `fetch` simulado.
// Comprueba la clave por sitio, que nada se escribe antes del primer clic, la hidratación (`aria-pressed`, contador,
// fichas pedidas por POST), la región de estado, la purga de 365 días, el evento `storage`, «pedir iniciar sesión», la
// fusión sólo con «Añadir» (sin respuesta, ni `merge` ni POST; «Descartar» borra sin enviar), el nonce renovado una vez,
// la Store API (su `Nonce` de cada respuesta reutilizado, el variable no se ofrece, la mudanza) y el foco al quitar.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { createDocument, event, response, storage, sleep } from './elements-woo-be/dom.mjs';

const SOURCE = readFileSync(new URL('../digitalisimo-elements/assets/js/wishlist.js', import.meta.url), 'utf8');
const T = { saved: 'Guardado en tu lista de deseos', removed: 'Quitado de tu lista de deseos', full: 'Tu lista de deseos está llena.', cleared: 'Lista vaciada', error: 'No se pudo actualizar tu lista de deseos.' };
const TA = { ask1: 'Tienes 1 producto guardado en este navegador. ¿Añadirlo a tu lista?', ask: 'Tienes %d productos guardados en este navegador. ¿Añadirlos a tu lista?', add: 'Añadir', discard: 'Descartar', added: 'Añadidos a tu lista de deseos', discarded: 'Se borró la lista de este navegador.', failed: '%d no se pudieron añadir: si tienen opciones, guárdalos desde su ficha.' };
const NOW = Math.floor(Date.now() / 1000);
const btn = (id, extra = '') => `<button type="button" class="digi-wish" data-digi-wish="${id}"${extra} aria-pressed="false" hidden><span class="digi-wish__text">Guardar</span><span class="screen-reader-text"> Producto ${id} en la lista de deseos</span></button>`;
const LIST = '<div data-elementor-id="30"><section class="digi-wishlist" data-digi-wishlist data-post="30" data-widget="abc1234"><h2 id="t" class="digi-wishlist__title" tabindex="-1">Lista</h2><ul class="digi-wishlist__items" data-digi-wish-items></ul><p data-digi-wish-empty hidden>Vacía</p><button type="button" data-digi-wish-clear hidden>Vaciar lista</button></section></div>';
const card = (id) => `<li class="digi-wishlist__item" data-digi-wish-item="${id}"><a href="/p/${id}/">Producto ${id}</a><button type="button" class="digi-wishlist__remove" data-digi-wish-remove="${id}">Quitar</button></li>`;

function page(html, config, handler, initial = {}) {
  const doc = createDocument();
  doc.body.innerHTML = html;
  const ls = storage(initial);
  const calls = [];
  const ctx = {
    document: doc, localStorage: ls, JSON, Math, Date, Promise, Array, Number, String, Object, RegExp, Error, console,
    setTimeout, clearTimeout, queueMicrotask,
    CustomEvent: class { constructor(type, init = {}) { Object.assign(this, event(type, init)); } },
    digitalisimoWishlist: config,
    fetch: (url, init = {}) => { calls.push({ url, method: init.method || 'GET', headers: init.headers || {}, body: init.body ? JSON.parse(init.body) : undefined }); return Promise.resolve(handler(url, init, calls)); },
  };
  ctx.window = ctx;
  const listeners = {};
  ctx.addEventListener = (type, fn) => (listeners[type] ||= []).push(fn);
  const events = [];
  doc.addEventListener('digitalisimo:wishlist', (e) => events.push([...e.detail.ids]));
  vm.runInNewContext(SOURCE, ctx);
  const fire = (type, ev) => (listeners[type] || []).forEach((fn) => fn(ev));
  return { doc, ctx, ls, calls, events, fire, q: (s) => doc.querySelector(s), qa: (s) => doc.querySelectorAll(s) };
}
const status = (p) => (p.q('.digi-wish-status') || { textContent: '' }).textContent;
const cards = (ids) => response(200, { html: ids.map(card).join(''), ids });
const guestConfig = (over = {}) => ({ key: 'digi_wishlist', cards: '/wp-json/digitalisimo-elements/v1/woo/cards', mode: 'own', guest: true, limit: 100, max: 100, t: T, ...over });

// 1. Invitado: nada se escribe antes del primer clic; hidratación; clic guarda y anuncia; fichas por POST.
{
  const p = page(btn(10) + btn(16) + '<a data-digi-wish-link><span data-digi-wish-count>0</span></a>' + LIST, guestConfig(), (url, init) => cards(JSON.parse(init.body).ids));
  await sleep(5);
  assert.equal(p.ls.writes.length, 0, 'localStorage vacío antes del primer clic');
  assert.equal(p.calls.length, 0, 'sin productos, ninguna petición');
  for (const b of p.qa('button[data-digi-wish]')) { assert.equal(b.hidden, false); assert.equal(b.getAttribute('aria-pressed'), 'false'); }
  assert.equal(p.q('[data-digi-wish-empty]').hidden, false, 'lista vacía: su texto se ve');
  p.qa('button[data-digi-wish]')[0].click();
  await sleep(50);
  const saved = JSON.parse(p.ls.getItem('digi_wishlist'));
  assert.equal(saved.v, 1);
  assert.deepEqual(saved.items.map((r) => r[0]), [10]);
  assert.ok(Math.abs(saved.items[0][1] - NOW) < 5, 'con la fecha de alta en segundos');
  assert.equal(p.qa('button[data-digi-wish]')[0].getAttribute('aria-pressed'), 'true');
  assert.equal(p.q('[data-digi-wish-count]').textContent, '1');
  assert.equal(status(p), T.saved, 'región de estado: «Guardado en tu lista de deseos»');
  assert.equal(p.q('.digi-wish-status').getAttribute('role'), 'status');
  assert.deepEqual(p.events.at(-1), [10], 'evento digitalisimo:wishlist para los demás widgets');
  const req = p.calls.at(-1);
  assert.equal(req.method, 'POST', 'las fichas se piden por POST');
  assert.equal(req.url, '/wp-json/digitalisimo-elements/v1/woo/cards', 'ningún ID en la URL');
  assert.deepEqual(req.body, { post_id: 30, widget_id: 'abc1234', ids: [10] }, 'post_id del documento que contiene el widget');
  assert.equal(p.qa('li').length, 1);
  assert.equal(p.q('[data-digi-wish-empty]').hidden, true);
  assert.equal(p.q('[data-digi-wish-clear]').hidden, false, '«Vaciar lista» sólo con productos');
  p.qa('button[data-digi-wish]')[0].click();
  await sleep(50);
  assert.equal(p.ls.getItem('digi_wishlist'), null, 'quitar el último borra la clave');
  assert.equal(status(p), T.removed);
  assert.equal(p.qa('li').length, 0);
}

// 2. Clave por sitio en una red, purga de lo de más de 365 días y de lo que el sitio ya no reconoce.
{
  const old = NOW - 366 * 86400;
  const p = page(btn(10) + btn(16) + LIST, guestConfig({ key: 'digi_wishlist_3' }), () => cards([16]), {
    digi_wishlist: JSON.stringify({ v: 1, items: [[10, NOW]] }),
    digi_wishlist_3: JSON.stringify({ v: 1, items: [[10, old], [16, NOW], [99, NOW]] }),
  });
  await sleep(30);
  assert.equal(p.qa('button[data-digi-wish]')[0].getAttribute('aria-pressed'), 'false', 'la lista de otro sitio no cuenta');
  assert.equal(p.qa('button[data-digi-wish]')[1].getAttribute('aria-pressed'), 'true');
  assert.deepEqual(JSON.parse(p.ls.getItem('digi_wishlist_3')).items.map((r) => r[0]), [16], 'se purgan lo de más de 365 días y el producto que el sitio no devolvió');
  assert.equal(p.ls.getItem('digi_wishlist'), JSON.stringify({ v: 1, items: [[10, NOW]] }), 'la clave de otro sitio no se toca');
}

// 3. Evento `storage` (otra pestaña) y tope.
{
  const p = page(btn(10) + btn(16), guestConfig({ limit: 1 }), () => response(200, {}));
  await sleep(5);
  p.ls.data.set('digi_wishlist', JSON.stringify({ v: 1, items: [[16, NOW]] }));
  p.fire('storage', { key: 'otra_clave' });
  assert.equal(p.qa('button[data-digi-wish]')[1].getAttribute('aria-pressed'), 'false', 'el evento de otra clave no cuenta');
  p.fire('storage', { key: 'digi_wishlist' });
  assert.equal(p.qa('button[data-digi-wish]')[1].getAttribute('aria-pressed'), 'true', 'otra pestaña: el evento storage sincroniza');
  p.qa('button[data-digi-wish]')[0].click();
  await sleep(50);
  assert.deepEqual(JSON.parse(p.ls.getItem('digi_wishlist')).items.map((r) => r[0]), [16], 'con la lista llena no se añade');
  assert.equal(status(p), T.full);
}

// 4. «Pedir iniciar sesión»: gana en la página; para un invitado, los botones se ocultan y se muestra el enlace.
{
  const login = '<a class="digi-wish digi-wish--login" href="/mi-cuenta/" data-digi-wish-login hidden>Inicia sesión para guardar</a>';
  const p = page(btn(10) + login + btn(16), guestConfig(), () => response(200, {}), { digi_wishlist: JSON.stringify({ v: 1, items: [[16, NOW]] }) });
  await sleep(5);
  assert.ok(p.qa('button[data-digi-wish]').every((b) => b.hidden), 'ningún botón guarda en el navegador');
  assert.equal(p.q('[data-digi-wish-login]').hidden, false, 'el enlace a la cuenta se muestra');
  const q = page(btn(10) + login, guestConfig({ loggedIn: true, rest: '/r/', items: [], nonce: 'n' }), () => response(200, {}));
  await sleep(5);
  assert.equal(q.q('[data-digi-wish-login]').hidden, true, 'con sesión, el enlace sigue oculto');
  assert.equal(q.q('button[data-digi-wish]').hidden, false);
  const r = page(btn(10), guestConfig({ guest: false }), () => response(200, {}));
  await sleep(5);
  assert.equal(r.q('button[data-digi-wish]').hidden, true, 'con el modo invitado apagado en el sitio, nada se guarda en el navegador');
}

// 5. Con sesión (almacén propio): la fusión sólo con «Añadir». Sin respuesta, nada sale del navegador.
const ownConfig = (over = {}) => guestConfig({ loggedIn: true, rest: '/wp-json/digitalisimo-elements/v1/woo/', ajax: '/wp-admin/admin-ajax.php', items: [16], nonce: 'abcdef0001', legacy: false, t: { ...T, ...TA }, ...over });
{
  const local = { digi_wishlist: JSON.stringify({ v: 1, items: [[10, NOW], [16, NOW]] }) };
  const p = page(btn(10) + btn(16), ownConfig(), () => response(200, { items: [] }), local);
  await sleep(60);
  assert.equal(p.qa('button[data-digi-wish]')[1].getAttribute('aria-pressed'), 'true', 'la lista de la cuenta, de la configuración');
  assert.equal(p.qa('button[data-digi-wish]')[0].getAttribute('aria-pressed'), 'false', 'lo del navegador no se mezcla solo');
  const box = p.q('.digi-wish-merge');
  assert.ok(box, 'aviso de fusión');
  assert.equal(box.getAttribute('role'), 'status');
  assert.equal(box.querySelector('p').textContent, TA.ask1, 'sólo cuenta lo que no está ya en la cuenta');
  assert.equal(p.calls.length, 0, 'sin respuesta al aviso: ni merge ni POST');
  assert.ok(p.ls.getItem('digi_wishlist'), '…y la copia sigue en el navegador');
  box.querySelectorAll('button')[1].click();
  await sleep(50);
  assert.equal(p.ls.getItem('digi_wishlist'), null, '«Descartar» borra la copia local');
  assert.equal(p.calls.length, 0, '…sin enviarla');
  assert.ok(p.q('.digi-wish-merge') === null, 'el aviso de fusión desaparece');
  assert.equal(status(p), TA.discarded);
}
{
  const local = { digi_wishlist: JSON.stringify({ v: 1, items: [[10, NOW], [12, NOW]] }) };
  const p = page(btn(10) + btn(16), ownConfig(), () => response(200, { items: [{ id: 16 }, { id: 10 }, { id: 12 }] }), local);
  await sleep(60);
  assert.equal(p.q('.digi-wish-merge p').textContent, 'Tienes 2 productos guardados en este navegador. ¿Añadirlos a tu lista?');
  p.q('.digi-wish-merge [data-add]').click();
  await sleep(50);
  assert.equal(p.calls.length, 1);
  assert.deepEqual(p.calls[0].body, { op: 'merge', items: [10, 12] }, '«Añadir» envía merge con lo del navegador');
  assert.equal(p.calls[0].headers['X-WP-Nonce'], 'abcdef0001');
  assert.equal(p.ls.getItem('digi_wishlist'), null, 'con la respuesta correcta, se borra la copia local');
  assert.equal(p.qa('button[data-digi-wish]')[0].getAttribute('aria-pressed'), 'true');
  assert.equal(status(p), TA.added);
}
// 6. Nonce de WordPress caducado: se renueva una sola vez.
{
  let posts = 0;
  const p = page(btn(10), ownConfig({ items: [] }), (url) => {
    if (url.includes('rest-nonce')) return response(200, 'fedcba9876');
    posts++;
    return posts === 1 ? response(403, { code: 'rest_cookie_invalid_nonce' }) : response(200, { items: [{ id: 10 }] });
  });
  await sleep(5);
  p.q('button[data-digi-wish]').click();
  await sleep(60);
  assert.deepEqual(p.calls.map((c) => c.url.replace(/^.*\//, '')), ['wishlist', 'admin-ajax.php?action=rest-nonce', 'wishlist']);
  assert.equal(p.calls[2].headers['X-WP-Nonce'], 'fedcba9876', 'se repite con el nonce nuevo');
  assert.equal(p.q('button[data-digi-wish]').getAttribute('aria-pressed'), 'true');
  const q = page(btn(10), ownConfig({ items: [] }), (url) => (url.includes('rest-nonce') ? response(200, 'fedcba9876') : response(403, { code: 'rest_cookie_invalid_nonce' })));
  await sleep(5);
  q.q('button[data-digi-wish]').click();
  await sleep(60);
  assert.equal(q.calls.length, 3, 'si vuelve a fallar, no se insiste');
  assert.equal(status(q), T.error);
}

// 7. Store API de WooCommerce: el Nonce de cada respuesta se reutiliza; un variable no se ofrece.
const KEY10 = 'a'.repeat(32);
const storeConfig = (over = {}) => ownConfig({ mode: 'store', store: '/wp-json/wc/store/v1/shopper-lists/wishlist/items', items: [], nonce: undefined, ...over });
{
  let n = 0;
  const p = page(btn(10) + btn(16) + btn(11, ' data-variable="1"'), storeConfig(), (url, init) => {
    n++;
    if ((init.method || 'GET') === 'GET') return response(200, [{ key: KEY10, id: 10, product_id: 10, variation_id: 0 }], { Nonce: 'N1' });
    if (init.method === 'POST') return n === 2 ? response(403, { code: 'woocommerce_rest_invalid_nonce' }, { Nonce: 'N2' }) : response(201, { key: 'b'.repeat(32), id: 16 }, { Nonce: 'N3' });
    return response(204, '', { Nonce: 'N4' });
  });
  await sleep(10);
  assert.equal(p.calls[0].method, 'GET');
  assert.equal(p.calls[0].headers['X-WP-Nonce'], undefined, 'la Store API no necesita X-WP-Nonce');
  const [b10, b16, b11] = p.qa('button[data-digi-wish]');
  assert.equal(b10.getAttribute('aria-pressed'), 'true');
  assert.equal(b11.hidden, true, 'un producto variable no se ofrece: la Store API exige la variación');
  b16.click();
  await sleep(50);
  assert.equal(p.calls[1].headers.Nonce, 'N1', 'la escritura usa el Nonce del GET');
  assert.equal(p.calls[2].headers.Nonce, 'N2', 'tras un nonce inválido, un reintento con el Nonce que trae la respuesta');
  assert.deepEqual(p.calls[2].body, { product_id: 16, quantity: 1 });
  assert.equal(b16.getAttribute('aria-pressed'), 'true');
  b10.click();
  await sleep(50);
  assert.equal(p.calls[3].method, 'DELETE');
  assert.ok(p.calls[3].url.endsWith('/items/' + KEY10), 'quitar borra por la clave del elemento');
  assert.equal(p.calls[3].headers.Nonce, 'N3', 'cada respuesta trae el Nonce siguiente');
  assert.equal(b10.getAttribute('aria-pressed'), 'false');
}
// 8. Mudanza del almacén propio y fusión de invitado en modo Store API.
{
  const p = page(btn(10) + btn(16), storeConfig({ legacy: true, items: [10, 16], nonce: 'abcdef0001' }), (url, init) => {
    if (url.includes('/woo/wishlist')) return response(200, { items: [], moved: true });
    if ((init.method || 'GET') === 'GET') return response(200, [{ key: KEY10, id: 10, product_id: 10 }], { Nonce: 'N1' });
    return response(201, { key: 'c'.repeat(32), id: 16 }, { Nonce: 'N2' });
  }, { digi_wishlist: JSON.stringify({ v: 1, items: [[16, NOW], [20, NOW]] }) });
  await sleep(60);
  assert.deepEqual(p.calls.map((c) => c.method + ' ' + c.url.replace(/^.*\/v1\//, '')), ['GET shopper-lists/wishlist/items', 'POST shopper-lists/wishlist/items', 'POST woo/wishlist'], 'sólo lo que falta pasa a WooCommerce y después se vacía lo propio');
  assert.deepEqual(p.calls[2].body, { op: 'clear', moved: true });
  assert.equal(p.calls[2].headers['X-WP-Nonce'], 'abcdef0001');
  assert.equal(p.q('.digi-wish-merge p').textContent, TA.ask1, 'de lo del navegador, sólo falta un producto');
  p.q('.digi-wish-merge [data-add]').click();
  await sleep(50);
  assert.deepEqual(p.calls.at(-1).body, { product_id: 20, quantity: 1 }, 'en modo Store API, un POST por producto');
  assert.equal(p.ls.getItem('digi_wishlist'), null);
}
// 9. Mudanza con un fallo: no se vacía lo propio.
{
  const p = page(btn(10), storeConfig({ legacy: true, items: [10], nonce: 'abcdef0001' }), (url, init) => ((init.method || 'GET') === 'GET' ? response(200, [], { Nonce: 'N1' }) : response(500, { code: 'x' })));
  await sleep(60);
  assert.ok(!p.calls.some((c) => c.url.includes('/woo/wishlist')), 'con una respuesta incorrecta, el almacén propio sigue');
}

// 10. Quitar desde la lista: la ficha se retira sin pedir nada y el foco pasa a la siguiente o al título.
{
  const local = { digi_wishlist: JSON.stringify({ v: 1, items: [[10, NOW - 5], [16, NOW]] }) };
  const p = page(LIST, guestConfig(), (url, init) => cards(JSON.parse(init.body).ids), local);
  await sleep(20);
  assert.deepEqual(p.calls[0].body.ids, [16, 10], 'lo más reciente primero');
  const [first, second] = p.qa('li');
  first.querySelector('button').click();
  await sleep(50);
  assert.equal(p.calls.length, 1, 'quitar no vuelve a pedir las fichas');
  assert.equal(p.qa('li').length, 1);
  assert.ok(p.doc.activeElement === second.querySelector('a'), 'el foco pasa a la ficha siguiente');
  second.querySelector('button').click();
  await sleep(50);
  assert.ok(p.doc.activeElement === p.q('.digi-wishlist__title'), 'sin fichas, el foco va al título');
  assert.equal(p.q('[data-digi-wish-empty]').hidden, false);
  assert.equal(p.ls.getItem('digi_wishlist'), null);
}
// 11. Vaciar la lista y la región dentro de un diálogo abierto.
{
  const local = { digi_wishlist: JSON.stringify({ v: 1, items: [[10, NOW]] }) };
  const p = page(LIST + '<dialog open><div>' + btn(16) + '</div></dialog>', guestConfig(), (url, init) => cards(JSON.parse(init.body).ids), local);
  await sleep(20);
  p.q('[data-digi-wish-clear]').click();
  await sleep(50);
  assert.equal(p.ls.getItem('digi_wishlist'), null);
  assert.ok(p.doc.activeElement === p.q('.digi-wishlist__title'), 'el foco sigue en el título');
  assert.equal(p.q('.digi-wish-status').parentNode.localName, 'dialog', 'con un diálogo abierto, la región va dentro');
  assert.equal(status(p), T.cleared);
}

// 12. Tamaño y reglas del script.
assert.ok(Buffer.byteLength(SOURCE) <= 8192, `wishlist.js pesa ${Buffer.byteLength(SOURCE)} B (tope 8 KB)`);
assert.ok(!/jQuery|\$\(/.test(SOURCE), 'sin jQuery');
assert.ok(!/document\.cookie/.test(SOURCE), 'ninguna cookie');
assert.ok(!/[?&]ids=/.test(SOURCE), 'ningún ID en una URL');

console.log('DIGITALÍSIMO Elements: script de la lista de deseos validado (invitado, cuenta, Store API, fusión y foco).');
