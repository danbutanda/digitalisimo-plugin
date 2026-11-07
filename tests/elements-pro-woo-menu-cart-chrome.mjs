// Carrito del menú (`digitalisimo-woo-menu-cart`, C7 fila O) en Chrome real (sin interfaz), con sus dos hojas y su script
// tal cual y el marcado que pinta el widget (tests/elements-pro-woo-menu-cart.php comprueba ese marcado contra el
// servidor; el fragmento del panel se simula sustituyendo `.digi-mc__ct` como hace `wc-cart-fragments`):
// - clic: abre (aria-expanded, `inert` fuera, clase en el elemento), clic fuera o en la capa cierra, el aspa cierra y
//   devuelve el foco al botón, Escape cierra y devuelve el foco si estaba dentro; con el panel cerrado nada es enfocable;
// - Intro y Espacio en el botón abren; «Al pasar el cursor» abre al entrar y cierra al salir;
// - «Abrir automáticamente»: abre cuando el contador sube tras una acción de la persona (no al cargar ni al recibir los
//   fragmentos iniciales); con `added` (envío sin AJAX) abre al cargar;
// - mini carrito: la lista se ajusta a la altura de la ventana; foco visible de 2 px con separación de 2 px; aspa y botón
//   de 24 × 24 px como mínimo y «quitar artículo» de 22 × 22, como PRO; movimiento reducido sin transiciones; sin errores
//   en la consola.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-woo-menu-cart-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del carrito del menú (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const base = new URL('../digitalisimo-elements/modules/digitalisimo-woo/store/assets/', import.meta.url);
const css = readFileSync(new URL('menu-cart.css', base), 'utf8') + readFileSync(new URL('menu-cart-2.css', base), 'utf8');
const js = readFileSync(new URL('menu-cart.js', base), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const item = n => `<div class="digi-mc__item woocommerce-cart-form__cart-item cart_item"><div class="digi-mc__img product-thumbnail"><a href="#p${n}" tabindex="-1" aria-hidden="true"><span style="display:block;width:60px;height:60px;background:#ccc"></span></a></div><div class="digi-mc__name product-name"><a href="#p${n}">Producto ${n}</a></div><div class="digi-mc__price product-price"><span class="quantity"><span class="product-quantity">1 &times;</span> 25,00 €</span></div><div class="digi-mc__rm product-remove"><a href="#r${n}" class="digi-mc__rml" aria-label="Quitar «Producto ${n}» del carrito"></a><a href="#r${n}" class="remove_from_cart_button" aria-label="Quitar «Producto ${n}» del carrito"></a></div></div>`;
const panel = n => `<div class="digi-mc__ct"><div class="digi-mc__list woocommerce-mini-cart">${Array.from({ length: n }, (_, i) => item(i + 1)).join('')}</div><div class="digi-mc__sub"><strong>Subtotal:</strong> ${n * 25},00 €</div><div class="digi-mc__btns"><a href="#cart" class="digi-mc__view elementor-button elementor-size-md"><span class="elementor-button-text">View cart</span></a><a href="#pay" class="digi-mc__pay elementor-button elementor-size-md"><span class="elementor-button-text">Checkout</span></a></div></div>`;
const widget = (id, conf, classes = '') => `<div class="elementor-element digi-mc--items-indicator-bubble digi-mc--show-subtotal-yes digi-mc--show-remove-button-yes ${classes}" data-id="${id}"><div class="digi-mc"><div class="digi-mc__tw" data-digi-menu-cart='${JSON.stringify(conf)}'><div class="digi-mc__tg elementor-button-wrapper"><button type="button" class="digi-mc__btn elementor-button elementor-size-sm" aria-expanded="false" aria-controls="p-${id}"><span class="elementor-button-text">50,00 €</span><span class="elementor-button-icon"><span class="elementor-button-icon-qty" data-counter="2">2</span><svg class="digi-mc__ico digi-mc__ico--medium" viewBox="0 0 24 24" width="1em" height="1em" aria-hidden="true"><path d="M3 4h18v16H3z" stroke="currentColor"/></svg><span class="elementor-screen-only">Carrito</span></span></button></div><div class="digi-mc__box" id="p-${id}" inert><div class="digi-mc__main" role="dialog" aria-label="Carrito"><button type="button" class="digi-mc__x" aria-label="Cerrar el carrito"></button>${panel(0).replace(/<div class="digi-mc__list[\s\S]*$/, '</div>')}</div><!-- /digi-mc__main --></div></div></div></div>`;
const page_html = (body, extra = '') => `<!doctype html><html lang="es"><head><meta charset="utf-8"><style>body{margin:0;font:16px sans-serif}.elementor-screen-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}${css}${extra}</style></head><body><a href="#" id="before">antes</a><div style="padding:40px">${body}</div><p style="height:1200px">texto</p><a href="#" id="after">después</a><script>${js}</script></body></html>`;

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const errors = [];
async function open(html, { reduce = false, width = 1280, height = 800 } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height });
  await page.setContent(html, { waitUntil: 'load' });
  // Fragmento inicial de WooCommerce: el panel se llena (sin acción de la persona).
  await page.evaluate(h => document.querySelectorAll('div.digi-mc__ct').forEach(el => { el.outerHTML = h; }), panel(2));
  await sleep(50);
  return page;
}
const state = (page, id) => page.evaluate(id => {
  const el = document.querySelector(`[data-id="${id}"]`);
  const box = el.querySelector('.digi-mc__box');
  const main = el.querySelector('.digi-mc__main');
  return { shown: el.classList.contains('digi-mc--shown'), expanded: el.querySelector('.digi-mc__btn').getAttribute('aria-expanded'), inert: box.hasAttribute('inert'), active: document.activeElement && (document.activeElement.className || document.activeElement.id), opacity: getComputedStyle(main).opacity, transform: getComputedStyle(box).transform };
}, id);

// 1. Clic, clic fuera, aspa y Escape.
let page = await open(page_html(widget('a', { open: 'click', auto: false, type: 'side-cart', added: false })), { reduce: true });
let s = await state(page, 'a');
ok(!s.shown && s.expanded === 'false' && s.inert, 'Cerrado al cargar: aria-expanded falso y panel inerte.');
await page.focus('#before');
await page.keyboard.press('Tab');
ok((await state(page, 'a')).active.includes('digi-mc__btn'), 'El tabulador va del enlace anterior al botón (el panel cerrado no es enfocable).');
await page.keyboard.press('Tab');
ok((await state(page, 'a')).active === 'after', 'Con el panel cerrado, el siguiente Tab sale del widget.');
await page.click('.digi-mc__btn');
await sleep(50);
s = await state(page, 'a');
ok(s.shown && s.expanded === 'true' && !s.inert && s.opacity === '1', 'Clic: abre, aria-expanded verdadero y panel sin inert: ' + JSON.stringify(s));
await page.click('.digi-mc__btn');
await sleep(50);
ok(!(await state(page, 'a')).shown, 'Otro clic en el botón cierra (modo clic).');
await page.click('.digi-mc__btn');
await page.mouse.click(30, 700);
await sleep(50);
ok(!(await state(page, 'a')).shown, 'Un clic en la capa, fuera del panel, cierra.');
await page.click('.digi-mc__btn');
await page.click('.digi-mc__name a');
await sleep(50);
ok((await state(page, 'a')).shown, 'Un clic dentro del panel no lo cierra.');
await page.click('.digi-mc__x');
await sleep(50);
s = await state(page, 'a');
ok(!s.shown && s.active.includes('digi-mc__btn'), 'El aspa cierra y devuelve el foco al botón.');
await page.keyboard.press('Enter');
await sleep(50);
ok((await state(page, 'a')).shown, 'Intro en el botón abre.');
await page.keyboard.press('Tab');
await page.keyboard.press('Tab');
ok((await state(page, 'a')).active === '' || (await page.evaluate(() => document.querySelector('.digi-mc__main').contains(document.activeElement))), 'Abierto, el tabulador entra en el panel.');
await page.keyboard.press('Escape');
await sleep(50);
s = await state(page, 'a');
ok(!s.shown && s.active.includes('digi-mc__btn') && s.inert, 'Escape cierra y devuelve el foco al botón.');
await page.keyboard.press(' ');
await sleep(50);
ok((await state(page, 'a')).shown, 'Espacio en el botón abre.');
const sizes = await page.evaluate(() => [...document.querySelectorAll('.digi-mc__x, .digi-mc__btn')].map(e => { const r = e.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; }));
ok(sizes.every(([w, h]) => w >= 24 && h >= 24), 'Aspa y botón de 24 × 24 px como mínimo: ' + JSON.stringify(sizes));
// «Quitar artículo» mide 22 × 22 px por defecto, como el de PRO (`--remove-item-button-size`, 22px); está aislado en su
// esquina, así que entra en la excepción de separación de WCAG 2.5.8, y el control de tamaño sigue mandando.
// Se lee el ancho calculado: en la página real el núcleo pone `box-sizing:border-box` (`.elementor *`) y el aro, con su
// borde, mide eso; aquí no hay esa regla.
const rm = await page.evaluate(() => [...document.querySelectorAll('.digi-mc__rm')].map(e => { const cs = getComputedStyle(e); return [parseFloat(cs.width), parseFloat(cs.height)]; }));
ok(rm.length && rm.every(([w, h]) => w === 22 && h === 22), 'Quitar artículo de 22 × 22 px, como PRO: ' + JSON.stringify(rm));
const ring = await page.evaluate(() => { const b = document.querySelector('.digi-mc__btn'); b.focus(); const cs = getComputedStyle(b); return [cs.outlineStyle, cs.outlineWidth, cs.outlineOffset]; });
ok(ring[0] === 'solid' && ring[1] === '2px' && ring[2] === '2px', 'Foco visible de 2 px con separación de 2 px: ' + ring);
const motion = await page.evaluate(() => getComputedStyle(document.querySelector('.digi-mc__main')).transitionDuration);
ok(/^0s/.test(motion), 'Movimiento reducido: sin transición en el panel (' + motion + ').');
await page.close();

// 2. Al pasar el cursor (desplegable: el lateral cubre la ventana con su capa y, como en PRO, se cierra con un clic fuera).
page = await open(page_html(widget('h', { open: 'mouseover', auto: false, type: 'mini-cart', added: false }, 'digi-mc--cart-type-mini-cart')));
await page.hover('.digi-mc__btn');
await sleep(50);
ok((await state(page, 'h')).shown, 'Al pasar el cursor abre.');
await page.mouse.move(1270, 790);
await sleep(50);
ok(!(await state(page, 'h')).shown, 'Al salir del widget cierra.');
await page.click('.digi-mc__btn');
await page.click('.digi-mc__btn');
await sleep(50);
ok((await state(page, 'h')).shown, 'En modo cursor, el clic siempre abre (como PRO).');
await page.close();

// 3. Abrir automáticamente.
page = await open(page_html(widget('u', { open: 'click', auto: true, type: 'side-cart', added: false })));
ok(!(await state(page, 'u')).shown, 'Los fragmentos iniciales no abren el panel.');
await page.evaluate(() => { document.querySelector('.digi-mc__btn .elementor-button-icon-qty').outerHTML = '<span class="elementor-button-icon-qty" data-counter="5">5</span>'; });
await sleep(50);
ok(!(await state(page, 'u')).shown, 'Sin una acción de la persona, un contador que sube no abre (caché o fragmentos tardíos).');
await page.click('#after');
await page.evaluate(() => { document.querySelector('.digi-mc__btn .elementor-button-icon-qty').outerHTML = '<span class="elementor-button-icon-qty" data-counter="6">6</span>'; });
await sleep(50);
ok((await state(page, 'u')).shown, 'Tras añadir un producto (contador que sube después de un clic), abre.');
await page.close();
page = await open(page_html(widget('v', { open: 'click', auto: true, type: 'side-cart', added: true })));
ok((await state(page, 'v')).shown, 'Con un producto añadido sin AJAX (`added`), abre al cargar.');
await page.close();
page = await open(page_html(widget('w', { open: 'click', auto: false, type: 'side-cart', added: true })));
ok(!(await state(page, 'w')).shown, 'Sin «Abrir automáticamente», `added` no abre.');
await page.close();

// 4. Mini carrito: desplegable bajo el botón y lista ajustada a la ventana.
page = await open(page_html(widget('m', { open: 'click', auto: false, type: 'mini-cart', added: false }, 'digi-mc--cart-type-mini-cart')), { height: 420 });
await page.evaluate(h => { document.querySelector('div.digi-mc__ct').outerHTML = h; }, panel(6));
await page.click('.digi-mc__btn');
await sleep(400);
const geo = await page.evaluate(() => { const b = document.querySelector('.digi-mc__btn').getBoundingClientRect(); const m = document.querySelector('.digi-mc__main').getBoundingClientRect(); const l = document.querySelector('.digi-mc__list'); return { below: m.top >= b.bottom - 1, max: parseInt(l.style.maxHeight, 10), pos: getComputedStyle(document.querySelector('.digi-mc__box')).position }; });
ok(geo.below && geo.pos === 'absolute' && geo.max >= 120 && geo.max < 420, 'Mini carrito: desplegable bajo el botón con la lista ajustada a la ventana: ' + JSON.stringify(geo));
await page.close();

await browser.close();
ok(!errors.length, 'Sin errores en la consola: ' + errors.join(' | '));
console.log(`DIGITALÍSIMO Elements: Carrito del menú en Chrome en verde (${checks} comprobaciones).`);
