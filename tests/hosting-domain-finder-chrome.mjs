// Buscador de dominios estilo registrador de DIGITALÍSIMO Hosting en Chrome real (sin interfaz), a 375 px de ancho, sobre
// tests/fixtures/hosting-domain-finder.html (el marcado de [digitalisimo_buscador_dominios], su hoja y su script, con el
// servidor simulado y los colores de fábrica de un kit nuevo): sin desbordar a lo ancho, contraste de 4.5:1 en el botón y
// en los grises, el aviso en role=status (los resultados no son región viva), principal + una fila por extensión,
// requisitos de .mx, ocupado con «¿Es tuyo? Transfiérelo», variantes libres cuando todo está ocupado, búsquedas que se
// pisan, y agregar dos dominios y un plan SIN recargar (Store API con el nonce renovado una vez): «Agregado ✓» con su
// nombre accesible, «Se compran juntos», el evento de bloques y la barra fija con artículos, nombres exactos, subtotal
// (con o sin IVA, como las filas) y «Pagar»; en la página del carrito no hay barra. Lo escrito se pinta como texto.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 180 node tests/hosting-domain-finder-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';
import { homedir } from 'node:os';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const npx = `${homedir()}/.npm/_npx`;
const found = existsSync(npx) ? readdirSync(npx).map((d) => `${npx}/${d}/node_modules/puppeteer-core`).find((d) => existsSync(d + '/package.json')) : '';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : found;
if (!existsSync(chrome) || !pptrDir || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del buscador estilo registrador (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const entry = ['/lib/puppeteer/puppeteer-core.js', '/lib/esm/puppeteer/puppeteer-core.js'].map((f) => pptrDir + f).find(existsSync);
const { default: puppeteer } = await import(pathToFileURL(entry).href);
const fixture = new URL('./fixtures/hosting-domain-finder.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; console.log(`ok · ${message}`); };

// Contraste WCAG entre dos colores calculados por Chrome (rgb/rgba).
const contrast = (a, b) => {
  const p = (s) => s.match(/[\d.]+/g).slice(0, 3).map(Number);
  const l = (c) => { const f = (v) => { const s = v / 255; return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4; }; const [r, g, bb] = p(c); return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(bb); };
  const x = l(a); const y = l(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05);
};

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
const open = async (query = '') => {
  const page = await browser.newPage();
  await page.setViewport({ width: 375, height: 740, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(fixture + query, { waitUntil: 'load' });
  return page;
};
try {
  const page = await open();
  const wide = () => page.evaluate(() => document.documentElement.scrollWidth);
  const text = (sel) => page.$eval(sel, (e) => e.textContent);
  const waitText = (sel, t) => page.waitForFunction((s, x) => (document.querySelector(s)?.textContent || '').includes(x), { timeout: 5000 }, sel, t).catch(async (e) => { throw new Error(`${e.message} esperando «${t}» en ${sel}; aviso: «${await page.$eval('.digitalisimo-finder__status', (x) => x.textContent)}»`); });
  // Como una persona: lleva el botón al centro de la pantalla (la barra fija ocupa la parte de abajo) y lo toca.
  const press = async (sel) => { await page.$eval(sel, (b) => b.scrollIntoView({ block: 'center' })); await page.tap(sel); };
  const search = async (q, expect) => { await page.$eval('#dhf-1', (i, v) => { i.value = v; }, q); await page.click('.digitalisimo-finder__submit'); await waitText('.digitalisimo-finder__status', expect); };
  const colors = (sel) => page.$eval(sel, (e) => { const s = getComputedStyle(e); let n = e; let bg = 'rgb(255, 255, 255)'; while (n) { const b = getComputedStyle(n).backgroundColor; if (b && !/rgba\(0, 0, 0, 0\)|transparent/.test(b)) { bg = b; break; } n = n.parentElement; } return { fg: s.color, bg: s.backgroundColor !== 'rgba(0, 0, 0, 0)' ? s.backgroundColor : bg }; });

  ok((await wide()) <= 375, `375 px: el buscador no desborda a lo ancho (${await wide()} px)`);
  ok((await page.$eval('.digitalisimo-finder__bar', (b) => getComputedStyle(b).flexDirection)) === 'column', 'En el celular el campo y el botón van uno debajo del otro');
  const btn = await colors('.digitalisimo-finder__submit');
  ok(contrast(btn.fg, btn.bg) >= 4.5, `Con el acento de fábrica del kit (verde claro), el texto del botón tiene contraste ${contrast(btn.fg, btn.bg).toFixed(2)}:1 (≥ 4.5)`);
  const hint = await colors('.digitalisimo-finder__hint');
  ok(contrast(hint.fg, hint.bg) >= 4.5, `El texto gris de ayuda tiene contraste ${contrast(hint.fg, hint.bg).toFixed(2)}:1 (≥ 4.5)`);
  ok((await page.$eval('.digitalisimo-finder__results', (r) => r.hasAttribute('aria-live'))) === false && (await page.$eval('.digitalisimo-finder__status', (r) => r.getAttribute('role'))) === 'status', 'Sólo el aviso (role=status) se anuncia; los resultados no son una región viva');

  // Nombre libre: principal .com y una fila por extensión.
  await page.type('#dhf-1', 'minegocio');
  await page.tap('.digitalisimo-finder__submit');
  await waitText('.digitalisimo-finder__status', 'minegocio.com está disponible');
  ok((await page.$eval('.digitalisimo-finder__results', (r) => r.getAttribute('aria-busy'))) === 'false' && !(await page.$eval('.digitalisimo-finder__submit', (b) => b.disabled)), 'Al terminar, aria-busy vuelve a false y el botón se reactiva');
  ok((await text('.digitalisimo-finder__main-domain')) === 'minegocio.com' && (await text('.digitalisimo-finder__main .digitalisimo-finder__first')) === '$309' && (await text('.digitalisimo-finder__main .digitalisimo-finder__renewal')).includes('$509'), 'Principal grande: minegocio.com, $309 el primer año y la renovación');
  const renewal = await colors('.digitalisimo-finder__main .digitalisimo-finder__renewal');
  ok(contrast(renewal.fg, renewal.bg) >= 4.5, `La renovación en gris con contraste ${contrast(renewal.fg, renewal.bg).toFixed(2)}:1 (≥ 4.5)`);
  const rows = await page.$$eval('.digitalisimo-finder__row .digitalisimo-finder__domain', (s) => s.map((x) => x.textContent));
  ok(JSON.stringify(rows) === '["minegocio.mx","minegocio.net"]', `Debajo, una fila por extensión (${rows})`);
  ok((await text('.digitalisimo-finder__row.is-available .digitalisimo-finder__req')).includes('titular'), '.mx con su aviso de requisitos');
  ok((await wide()) <= 375, 'Con resultados tampoco desborda a lo ancho');
  ok((await page.$$eval('.digitalisimo-finder button', (bs) => bs.filter((b) => b.getBoundingClientRect().height < 44).length)) === 0, 'Todos los botones miden al menos 44 px de alto');
  const add = await colors('.digitalisimo-finder__main .digitalisimo-finder__add');
  ok(contrast(add.fg, add.bg) >= 4.5, `«Agregar» con contraste ${contrast(add.fg, add.bg).toFixed(2)}:1`);

  // Agregar .com sin recargar (el nonce de la página venció: se renueva con el carrito y se reintenta una vez).
  await page.click('.digitalisimo-finder__main .digitalisimo-finder__add');
  await waitText('.digitalisimo-finder__main .digitalisimo-finder__add', 'Agregado ✓');
  ok(page.url() === fixture && (await page.evaluate(() => window.navigations)) === 0, 'Agregar no recarga ni cambia de página');
  ok((await page.$eval('.digitalisimo-finder__main .digitalisimo-finder__add', (b) => b.getAttribute('aria-label'))) === 'minegocio.com ya está en tu carrito', 'El nombre accesible del botón también cambia («minegocio.com ya está en tu carrito»)');
  ok((await page.evaluate(() => window.events)).includes('wc-blocks_added_to_cart'), 'Se avisa a los bloques de WooCommerce (wc-blocks_added_to_cart)');
  const adds = await page.evaluate(() => window.log.filter((l) => l.url.endsWith('cart/add-item')));
  ok(adds.length === 2 && adds[0].headers.Nonce === 'viejo' && adds[1].headers.Nonce === 'fresco' && JSON.stringify(adds[1].body) === '{"id":20,"quantity":1,"digitalisimo_domain":"minegocio.com"}', 'Store API: con el nonce vencido se pide uno nuevo y se reintenta; el cuerpo lleva sólo producto, cantidad 1 y el nombre exacto');
  await page.waitForSelector('.digitalisimo-finder__main + .digitalisimo-finder__together', { timeout: 5000 });
  ok((await page.$$eval('.digitalisimo-finder__together-name', (s) => s.map((x) => x.textContent))).join('|') === 'Web Hosting Starter|Correo profesional', '«Se compran juntos» se abre debajo con los planes de hosting y correo');
  await page.waitForSelector('.digitalisimo-finder-cartbar:not([hidden])', { timeout: 5000 });
  ok((await text('.digitalisimo-finder-cartbar__count')) === '1 artículo' && (await text('.digitalisimo-finder-cartbar__items')).includes('minegocio.com'), 'Aparece la barra fija con el artículo y el nombre exacto');

  // Segundo dominio (.net) y un plan.
  await press('.digitalisimo-finder__add[data-add-domain="minegocio.net"]');
  await waitText('.digitalisimo-finder-cartbar__count', '2 artículos');
  await press('.digitalisimo-finder__together .digitalisimo-finder__add[data-add-product="14693"]');
  await waitText('.digitalisimo-finder-cartbar__count', '3 artículos');
  ok((await page.$eval('.digitalisimo-finder__together .digitalisimo-finder__add[data-add-product="14693"]', (b) => b.getAttribute('aria-label'))) === 'Web Hosting Starter ya está en tu carrito', 'El plan agregado también cambia su nombre accesible');
  const items = await page.$$eval('.digitalisimo-finder-cartbar__item', (s) => s.map((x) => x.textContent));
  ok(JSON.stringify(items) === '["Dominio .com: minegocio.com","Dominio .net: minegocio.net","Web Hosting Starter"]', `La barra: dos dominios con su nombre exacto y el plan (${items})`);
  ok((await text('.digitalisimo-finder-cartbar__subtotal')).replace(/\s+/g, ' ').includes('$1,559'), `Subtotal de la Store API (${await text('.digitalisimo-finder-cartbar__subtotal')})`);
  ok((await page.$$('.digitalisimo-finder-cartbar__note')).length === 0, 'Sin transferencia, no hay nota del código EPP');
  ok((await page.$eval('.digitalisimo-finder-cartbar__pay', (a) => a.href)) === 'https://tienda.test/pagar/', '«Pagar» lleva al pago');
  const pay = await colors('.digitalisimo-finder-cartbar__pay');
  ok(contrast(pay.fg, pay.bg) >= 4.5, `«Pagar» con contraste ${contrast(pay.fg, pay.bg).toFixed(2)}:1`);
  const barBox = await page.$eval('.digitalisimo-finder-cartbar', (b) => { const r = b.getBoundingClientRect(); return { bottom: r.bottom, width: r.width, pos: getComputedStyle(b).position }; });
  ok(barBox.pos === 'fixed' && Math.round(barBox.bottom) === 740 && barBox.width <= 375, 'La barra va fija abajo y cabe en 375 px');
  ok((await page.$eval('.digitalisimo-finder-cartbar', (b) => b.getAttribute('role') + '|' + b.getAttribute('aria-label'))) === 'region|Tu carrito', 'La barra es una región con nombre');
  ok((await page.evaluate(() => document.body.classList.contains('digitalisimo-finder-has-bar'))) && (await wide()) <= 375, 'La página deja espacio para la barra y sigue sin desbordar');

  // Ocupado: transferencia (una sola vez) y la nota del EPP por la etiqueta «Dominio a transferir».
  await search('ocupado', 'ocupado.com está ocupado. ocupado.mx sí está disponible');
  ok((await text('.digitalisimo-finder__instead')).includes('ocupado.com está ocupado') && (await text('.digitalisimo-finder__main-domain')) === 'ocupado.mx', 'Ocupado: el principal pasa a la mejor libre');
  ok((await text('.digitalisimo-finder__results > .digitalisimo-finder__transfer')).includes('¿ocupado.com es tuyo? Transfiérelo'), '«¿Es tuyo? Transfiérelo» junto al principal');
  ok((await page.$$('.digitalisimo-finder__transfer')).length === 1, 'La transferencia del ocupado sale una sola vez (no se repite en su fila)');
  await press('.digitalisimo-finder__results > .digitalisimo-finder__transfer .digitalisimo-finder__add');
  await waitText('.digitalisimo-finder-cartbar__count', '4 artículos');
  ok((await text('.digitalisimo-finder-cartbar__items')).includes('Transferencia de dominio: ocupado.com') && (await text('.digitalisimo-finder-cartbar__note')).includes('código de transferencia'), 'La transferencia entra con su nombre y la barra avisa que se pedirá el código');

  // Todo ocupado: variantes libres del nombre, con su botón.
  await search('todo', 'todo.com: Ocupado');
  const ideas = await page.$$eval('.digitalisimo-finder__list-title', (h) => h.map((x) => x.textContent));
  ok(ideas[0] === 'Otras ideas libres' && JSON.stringify(await page.$$eval('.digitalisimo-finder__row.is-available .digitalisimo-finder__domain', (s) => s.map((x) => x.textContent))) === '["mitodo.com","todomx.com"]', 'Todo ocupado: sugiere variantes libres del nombre con su botón «Agregar»');

  // Sin consultar: el aviso dice eso, no «no está disponible».
  await search('raro', 'raro.com: No pudimos consultarlo');
  ok(!(await text('.digitalisimo-finder__status')).includes('no está disponible'), 'Sin poder consultar, el aviso no dice «no está disponible»');

  // Búsquedas que se pisan: la lenta (vieja) no pisa a la nueva y el botón queda inactivo mientras busca.
  await page.$eval('#dhf-1', (i) => { i.value = 'lento'; });
  await page.click('.digitalisimo-finder__submit');
  ok(await page.$eval('.digitalisimo-finder__submit', (b) => b.disabled), 'Mientras busca, el botón queda inactivo');
  await page.$eval('#dhf-1', (i) => { i.value = 'rapido'; i.form.requestSubmit(); });
  await waitText('.digitalisimo-finder__status', 'rapido.com está disponible');
  await new Promise((r) => setTimeout(r, 900));
  ok((await text('.digitalisimo-finder__main-domain')) === 'rapido.com' && (await text('.digitalisimo-finder__status')).includes('rapido.com'), 'La respuesta vieja («lento») llega después y se descarta');

  await search('minegocio', 'minegocio.com está disponible');
  ok((await text('.digitalisimo-finder__main .digitalisimo-finder__add')) === 'Agregado ✓', 'Al buscar de nuevo, lo que ya está en el carrito sale como «Agregado ✓»');

  // Un error del servidor (nombre inválido) se avisa como texto, sin crear elementos.
  await search('<img src=x onerror=alert(1)>', 'Usa sólo letras');
  ok((await page.$$('.digitalisimo-finder img')).length === 0, 'Lo escrito nunca se pinta como HTML');

  // Precios con IVA en la tienda: el subtotal de la barra también lo lleva (como las filas).
  const iva = await open('?iva=1');
  await iva.type('#dhf-1', 'conimpuesto');
  await iva.click('.digitalisimo-finder__submit');
  await iva.waitForFunction(() => document.querySelector('.digitalisimo-finder__main .digitalisimo-finder__add'), { timeout: 5000 });
  await iva.click('.digitalisimo-finder__main .digitalisimo-finder__add');
  await iva.waitForSelector('.digitalisimo-finder-cartbar:not([hidden])', { timeout: 5000 });
  const sub = (await iva.$eval('.digitalisimo-finder-cartbar__subtotal', (e) => e.textContent)).replace(/\s+/g, ' ');
  ok(sub.includes('$358.44') && !sub.includes('IVA al pagar'), `Con precios con IVA, el subtotal suma el IVA y no dice «+ IVA» (${sub})`);

  // Página del carrito (bloques, vacío): se agrega sin barra encima del carrito real.
  const cartPage = await open('?carrito=1');
  await cartPage.type('#dhf-1', 'desdecarrito');
  await cartPage.click('.digitalisimo-finder__submit');
  await cartPage.waitForFunction(() => document.querySelector('.digitalisimo-finder__main .digitalisimo-finder__add'), { timeout: 5000 });
  await cartPage.click('.digitalisimo-finder__main .digitalisimo-finder__add');
  await cartPage.waitForFunction(() => document.querySelector('.digitalisimo-finder__main .digitalisimo-finder__add')?.textContent === 'Agregado ✓', { timeout: 5000 });
  ok(!(await cartPage.$('.digitalisimo-finder-cartbar:not([hidden])')) && (await cartPage.evaluate(() => window.events)).includes('wc-blocks_added_to_cart'), 'En la página del carrito no aparece la barra fija; el carrito de bloques recibe el evento para actualizarse');

  // ?dominio= en la dirección (formulario sin JavaScript, enlace compartido o el elemento atómico): busca al cargar.
  const linked = await open('?dominio=desdeenlace');
  await linked.waitForFunction(() => (document.querySelector('.digitalisimo-finder__main-domain')?.textContent || '') === 'desdeenlace.com', { timeout: 5000 });
  ok((await linked.$eval('#dhf-1', (i) => i.value)) === 'desdeenlace', 'Con ?dominio= en la dirección, llena el campo y busca al cargar');
  ok(!errors.length, `Sin errores de JavaScript${errors.length ? ': ' + errors.join(' | ') : ''}`);
} finally {
  await browser.close();
}
console.log(`Hosting, buscador estilo registrador en Chrome: ${checks} comprobaciones correctas.`);
