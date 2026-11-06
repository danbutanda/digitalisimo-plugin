// «Añadir al carrito» propio (`digitalisimo-woo-product-add-to-cart`, C7 frente M de docs/pro/c7.md) en Chrome real (sin
// interfaz), con su hoja y su script del bucle tal cual y el marcado que pinta el widget en una plantilla de bucle
// (tests/elements-pro-woo-single.php comprueba ese marcado contra el de PRO):
// - la cantidad elegida pasa al botón de WooCommerce (`data-quantity`) al cambiarla y al escribirla;
// - «Ver carrito», que WooCommerce añade junto al botón dentro del formulario, pasa al contenedor del bucle sin repetirse
//   (se simula lo que hace el script de WooCommerce tras añadir al carrito);
// - la hoja: la envoltura apilada y en línea, la alineación por dispositivo (variables), la cantidad oculta sin
//   «Mostrar la cantidad» y el foco visible (contorno de 2 px con separación de 2 px);
// - sin errores en la consola.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-woo-atc-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de «Añadir al carrito» (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const base = new URL('../digitalisimo-elements/modules/digitalisimo-woo/single/assets/', import.meta.url);
const css = readFileSync(new URL('product-add-to-cart.css', base), 'utf8');
const js = readFileSync(new URL('product-add-to-cart.js', base), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

// Marcado del widget en un bucle (envoltura del núcleo + el de render_loop_add_to_cart()).
const loop = (id, classes, vars = '') => `<div class="elementor-element elementor-widget elementor-widget-digitalisimo-woo-product-add-to-cart digi-atc digi-atc--product ${classes}" data-id="${id}" style="${vars}"><div class="digi-atc__wrap digi-atc--type-simple"><div class="digi-atc__loop"><form class="cart digi-atc__loop-form"><div class="quantity"><label class="screen-reader-text" for="q-${id}">Cantidad</label><input type="number" id="q-${id}" class="input-text qty text" min="1" value="1"></div><a href="?add-to-cart=11" data-quantity="1" data-product_id="11" class="button product_type_simple add_to_cart_button ajax_add_to_cart">Añadir al carrito</a></form></div></div></div>`;
// Marcado del widget en una ficha (formulario de WooCommerce con la envoltura «apilado»).
const single = (id, classes, vars = '') => `<div class="elementor-element elementor-widget digi-atc digi-atc--product ${classes}" data-id="${id}" style="${vars}"><div class="digi-atc__wrap digi-atc--type-simple"><form class="cart"><div class="digi-atc__holder"><div class="quantity"><input type="number" class="input-text qty text" value="1"></div><button type="submit" class="single_add_to_cart_button button alt">Añadir al carrito</button></div></form></div></div>`;
const inline = (id, classes, vars = '') => `<div class="elementor-element elementor-widget digi-atc digi-atc--product ${classes}" data-id="${id}" style="width:600px;${vars}"><div class="digi-atc__wrap digi-atc--type-simple"><form class="cart"><div class="quantity"><input type="number" class="input-text qty text" value="1"></div><button type="submit" class="single_add_to_cart_button button alt">Añadir al carrito</button></form></div></div>`;
const html = `<!doctype html><html lang="es"><head><meta charset="utf-8"><style>body{font:16px sans-serif}${css}</style></head><body>
${loop('a', 'digi-atc--show-quantity-yes')}
${loop('b', 'digi-atc--show-quantity-yes digi-atc--align-justify', '--digi-atc-qm:auto;--digi-atc-bb:100%;--digi-atc-bw:100%;--digi-atc-lb:100%;--digi-atc-lj:center;--digi-atc-ab:auto')}
${single('c', 'digi-atc--layout-stacked digi-atc--show-quantity-yes')}
${inline('d', 'digi-atc--show-quantity-yes digi-atc--align-right', '--digi-atc-ta:right;--digi-atc-jc:flex-end;--digi-atc-qm:0;--digi-atc-bb:auto')}
${inline('e', '')}
<script>${js}</script></body></html>`;

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const errors = [];
try {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setViewport({ width: 1024, height: 900 });
  await page.setContent(html, { waitUntil: 'load' });

  // 1. Cantidad → botón.
  await page.$eval('#q-a', i => { i.value = ''; });
  await page.focus('#q-a');
  await page.keyboard.type('4');
  const typed = await page.evaluate(() => [document.querySelector('#q-a').value, document.querySelector('[data-id="a"] .ajax_add_to_cart').getAttribute('data-quantity')]);
  ok('4' === typed[1], 'Al escribir la cantidad, el botón la lleva (data-quantity): ' + typed.join(' → '));
  await page.$eval('#q-a', i => { i.value = '7'; i.dispatchEvent(new Event('change', { bubbles: true })); });
  ok('7' === await page.$eval('[data-id="a"] .ajax_add_to_cart', b => b.getAttribute('data-quantity')), 'Al cambiar la cantidad, también.');
  ok('1' === await page.$eval('[data-id="b"] .ajax_add_to_cart', b => b.getAttribute('data-quantity')), 'Sólo cambia el botón de su formulario.');

  // 2. «Ver carrito» pasa al contenedor del bucle, sin repetirse.
  const added = async (id) => {
    await page.$eval(`[data-id="${id}"] .ajax_add_to_cart`, b => { b.addEventListener('click', e => e.preventDefault(), { once: true }); });
    await page.click(`[data-id="${id}"] .ajax_add_to_cart`);
    // Lo que hace el script de WooCommerce al terminar: el enlace tras el botón.
    await page.$eval(`[data-id="${id}"] .ajax_add_to_cart`, b => b.insertAdjacentHTML('afterend', '<a href="/carrito/" class="added_to_cart wc-forward" title="Ver carrito">Ver carrito</a>'));
    await sleep(50);
  };
  await added('a');
  ok(await page.$eval('[data-id="a"] .digi-atc__loop', box => box.lastElementChild.matches('a.added_to_cart') && !box.querySelector('form .added_to_cart')), '«Ver carrito» pasa al contenedor del bucle, fuera del formulario.');
  await added('a');
  ok(1 === await page.$$eval('[data-id="a"] .added_to_cart', l => l.length), 'Al añadir otra vez, «Ver carrito» no se repite.');
  const gap = await page.$eval('[data-id="a"] .digi-atc__loop', box => getComputedStyle(box).columnGap);
  ok('10px' === gap, `Separación por defecto entre el formulario y «Ver carrito» (10 px): ${gap}.`);

  // 3. Hoja.
  const layout = await page.evaluate(() => {
    const r = (sel) => document.querySelector(sel).getBoundingClientRect();
    const qty = r('[data-id="c"] .quantity'), btn = r('[data-id="c"] button');
    const dq = r('[data-id="d"] .quantity'), db = r('[data-id="d"] button'), dw = r('[data-id="d"]');
    return {
      stacked: btn.top >= qty.bottom - 1,
      inline: Math.abs(dq.top - db.top) < 2 && db.left > dq.right,
      right: dw.right - db.right < 2,
      bJustify: getComputedStyle(document.querySelector('[data-id="b"] .digi-atc__loop > form')).flexBasis,
      hidden: getComputedStyle(document.querySelector('[data-id="e"] .quantity')).display,
      shown: getComputedStyle(document.querySelector('[data-id="d"] .quantity')).display,
    };
  });
  ok(layout.stacked, 'Apilado: el botón bajo la cantidad.');
  ok(layout.inline, 'En línea: cantidad y botón en la misma fila.');
  ok(layout.right, 'Alineado a la derecha con la variable de su dispositivo.');
  ok('100%' === layout.bJustify, `Justificado en el bucle: cada parte a todo el ancho (${layout.bJustify}).`);
  ok('none' === layout.hidden && 'none' !== layout.shown, 'Sin «Mostrar la cantidad», la cantidad no se ve.');
  await page.focus('#q-a');
  await page.keyboard.press('Tab');
  const ring = await page.evaluate(() => { const s = getComputedStyle(document.activeElement); return [document.activeElement.className, s.outlineStyle, s.outlineWidth, s.outlineOffset]; });
  ok('solid' === ring[1] && '2px' === ring[2] && '2px' === ring[3], `Foco visible de 2 px con separación de 2 px: ${ring.join(' ')}.`);
  ok(!errors.length, 'Sin errores en la consola: ' + errors.join(' | '));
} finally {
  await browser.close();
}
console.log(`DIGITALÍSIMO Elements: «Añadir al carrito» propio en Chrome (script del bucle y hoja): ${checks} comprobaciones en verde.`);
