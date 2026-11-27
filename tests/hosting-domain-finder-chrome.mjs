// Buscador de dominios estilo registrador de DIGITALÍSIMO Hosting en Chrome real (sin interfaz), a 375 px de ancho, sobre
// tests/fixtures/hosting-domain-finder.html (el marcado de [digitalisimo_buscador_dominios], su hoja y su script, con el
// servidor simulado): sin desbordar a lo ancho, resultados en una región aria-live, principal + una fila por extensión,
// requisitos de .mx, ocupado con «¿Es tuyo? Transfiérelo» y libres, y agregar dos dominios y un plan SIN recargar
// (Store API con el nonce renovado una vez): «Agregado ✓», «Se compran juntos» y la barra fija con artículos, nombres
// exactos, subtotal y «Pagar». Lo escrito se pinta como texto.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/hosting-domain-finder-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del buscador estilo registrador (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const entry = ['/lib/puppeteer/puppeteer-core.js', '/lib/esm/puppeteer/puppeteer-core.js'].map((f) => pptrDir + f).find(existsSync);
const { default: puppeteer } = await import(pathToFileURL(entry).href);
const fixture = new URL('./fixtures/hosting-domain-finder.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; console.log(`ok · ${message}`); };

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
try {
  const page = await browser.newPage();
  await page.setViewport({ width: 375, height: 740, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(fixture, { waitUntil: 'load' });
  const wide = () => page.evaluate(() => document.documentElement.scrollWidth);
  const text = (sel) => page.$eval(sel, (e) => e.textContent);
  const waitText = (sel, t) => page.waitForFunction((s, x) => (document.querySelector(s)?.textContent || '').includes(x), { timeout: 5000 }, sel, t).catch(async (e) => { throw new Error(`${e.message} esperando «${t}» en ${sel}; aviso: «${await page.$eval('.digitalisimo-finder__status', (x) => x.textContent)}»`); });
  // Como una persona: lleva el botón al centro de la pantalla (la barra fija ocupa la parte de abajo) y lo toca.
  const press = async (sel) => { await page.$eval(sel, (b) => b.scrollIntoView({ block: 'center' })); await page.tap(sel); };
  ok((await wide()) <= 375, `375 px: el buscador no desborda a lo ancho (${await wide()} px)`);
  const bar = await page.$eval('.digitalisimo-finder__bar', (b) => getComputedStyle(b).flexDirection);
  ok(bar === 'column', 'En el celular el campo y el botón van uno debajo del otro');

  // Nombre libre: principal .com y una fila por extensión.
  await page.type('#dhf-1', 'minegocio');
  await page.tap('.digitalisimo-finder__submit');
  await waitText('.digitalisimo-finder__status', 'minegocio.com está disponible');
  ok((await page.$eval('.digitalisimo-finder__results', (r) => r.getAttribute('aria-live'))) === 'polite' && (await page.$eval('.digitalisimo-finder__results', (r) => r.getAttribute('aria-busy'))) === 'false', 'Resultados en una región aria-live (y aria-busy de vuelta en false)');
  ok((await text('.digitalisimo-finder__main-domain')) === 'minegocio.com' && (await text('.digitalisimo-finder__main .digitalisimo-finder__first')) === '$309' && (await text('.digitalisimo-finder__main .digitalisimo-finder__renewal')).includes('$509'), 'Principal grande: minegocio.com, $309 el primer año y la renovación en gris');
  const rows = await page.$$eval('.digitalisimo-finder__row .digitalisimo-finder__domain', (s) => s.map((x) => x.textContent));
  ok(JSON.stringify(rows) === '["minegocio.mx","minegocio.net"]', `Debajo, una fila por extensión (${rows})`);
  ok((await text('.digitalisimo-finder__row.is-available .digitalisimo-finder__req')).includes('titular'), '.mx con su aviso de requisitos');
  const renewalColor = await page.$eval('.digitalisimo-finder__main .digitalisimo-finder__renewal', (e) => getComputedStyle(e).color);
  const mainColor = await page.$eval('.digitalisimo-finder__main-domain', (e) => getComputedStyle(e).color);
  ok(renewalColor !== mainColor, `La renovación va en un tono atenuado (${renewalColor} frente a ${mainColor})`);
  ok((await wide()) <= 375, 'Con resultados tampoco desborda a lo ancho');
  const small = await page.$$eval('.digitalisimo-finder button', (bs) => bs.filter((b) => b.getBoundingClientRect().height < 44).length);
  ok(small === 0, 'Todos los botones miden al menos 44 px de alto');
  const labels = await page.$$eval('.digitalisimo-finder__add', (bs) => bs.map((b) => b.getAttribute('aria-label')));
  ok(labels.includes('Agregar minegocio.com al carrito') && labels.includes('Agregar minegocio.net al carrito'), 'Cada «Agregar» dice qué dominio agrega');

  // Agregar .com sin recargar (el nonce de la página venció: se renueva con el carrito y se reintenta una vez).
  await page.click('.digitalisimo-finder__main .digitalisimo-finder__add');
  await waitText('.digitalisimo-finder__main .digitalisimo-finder__add', 'Agregado ✓');
  ok(page.url() === fixture && (await page.evaluate(() => window.navigations)) === 0, 'Agregar no recarga ni cambia de página');
  const adds = await page.evaluate(() => window.log.filter((l) => l.url.endsWith('cart/add-item')));
  ok(adds.length === 2 && adds[0].headers.Nonce === 'viejo' && adds[1].headers.Nonce === 'fresco' && JSON.stringify(adds[1].body) === '{"id":20,"quantity":1,"digitalisimo_domain":"minegocio.com"}', 'Store API: con el nonce vencido se pide uno nuevo y se reintenta; el cuerpo lleva sólo producto, cantidad 1 y el nombre exacto');
  await page.waitForSelector('.digitalisimo-finder__main + .digitalisimo-finder__together, .digitalisimo-finder__main .digitalisimo-finder__together', { timeout: 5000 });
  ok((await page.$$eval('.digitalisimo-finder__together-name', (s) => s.map((x) => x.textContent))).join('|') === 'Web Hosting Starter|Correo profesional', '«Se compran juntos» se abre debajo con los planes de hosting y correo');
  await page.waitForSelector('.digitalisimo-finder-cartbar:not([hidden])', { timeout: 5000 });
  ok((await text('.digitalisimo-finder-cartbar__count')) === '1 artículo' && (await text('.digitalisimo-finder-cartbar__items')).includes('minegocio.com'), 'Aparece la barra fija con el artículo y el nombre exacto');

  // Segundo dominio (.net) y un plan.
  await press('.digitalisimo-finder__add[data-add-domain="minegocio.net"]');
  await waitText('.digitalisimo-finder-cartbar__count', '2 artículos');
  await press('.digitalisimo-finder__together .digitalisimo-finder__add[data-add-product="14693"]');
  await waitText('.digitalisimo-finder-cartbar__count', '3 artículos');
  const items = await page.$$eval('.digitalisimo-finder-cartbar__item', (s) => s.map((x) => x.textContent));
  ok(JSON.stringify(items) === '["Dominio .com: minegocio.com","Dominio .net: minegocio.net","Web Hosting Starter"]', `La barra: dos dominios con su nombre exacto y el plan (${items})`);
  ok((await text('.digitalisimo-finder-cartbar__subtotal')).replace(/\s+/g, ' ').includes('$1,559'), `Subtotal de la Store API (${await text('.digitalisimo-finder-cartbar__subtotal')})`);
  ok((await page.$eval('.digitalisimo-finder-cartbar__pay', (a) => a.href)) === 'https://tienda.test/pagar/', '«Pagar» lleva al pago');
  const barBox = await page.$eval('.digitalisimo-finder-cartbar', (b) => { const r = b.getBoundingClientRect(); return { bottom: r.bottom, width: r.width, pos: getComputedStyle(b).position }; });
  ok(barBox.pos === 'fixed' && Math.round(barBox.bottom) === 740 && barBox.width <= 375, 'La barra va fija abajo y cabe en 375 px');
  ok((await page.$eval('.digitalisimo-finder-cartbar', (b) => b.getAttribute('role') + '|' + b.getAttribute('aria-label'))) === 'region|Tu carrito', 'La barra es una región con nombre');
  ok((await page.evaluate(() => document.body.classList.contains('digitalisimo-finder-has-bar'))) && (await wide()) <= 375, 'La página deja espacio para la barra y sigue sin desbordar');

  // Ocupado: transferencia y libres; y lo ya agregado sale como «Agregado ✓».
  await page.$eval('#dhf-1', (i) => { i.value = 'ocupado'; });
  await page.click('.digitalisimo-finder__submit');
  await waitText('.digitalisimo-finder__status', 'ocupado.com no está disponible');
  ok((await text('.digitalisimo-finder__instead')).includes('ocupado.com está ocupado') && (await text('.digitalisimo-finder__main-domain')) === 'ocupado.mx', 'Ocupado: el principal pasa a la mejor libre');
  ok((await text('.digitalisimo-finder__results > .digitalisimo-finder__transfer')).includes('¿ocupado.com es tuyo? Transfiérelo'), '«¿Es tuyo? Transfiérelo» junto al principal');
  await press('.digitalisimo-finder__results > .digitalisimo-finder__transfer .digitalisimo-finder__add');
  await waitText('.digitalisimo-finder-cartbar__count', '4 artículos');
  ok((await text('.digitalisimo-finder-cartbar__items')).includes('Transferencia de dominio: ocupado.com') && (await text('.digitalisimo-finder-cartbar__note')).includes('código de transferencia'), 'La transferencia entra con su nombre y la barra avisa que se pedirá el código');

  await page.$eval('#dhf-1', (i) => { i.value = 'minegocio'; });
  await page.click('.digitalisimo-finder__submit');
  await waitText('.digitalisimo-finder__status', 'minegocio.com está disponible');
  ok((await text('.digitalisimo-finder__main .digitalisimo-finder__add')) === 'Agregado ✓', 'Al buscar de nuevo, lo que ya está en el carrito sale como «Agregado ✓»');

  // Un error del servidor (nombre inválido) se avisa como texto, sin crear elementos.
  await page.$eval('#dhf-1', (i) => { i.value = '<img src=x onerror=alert(1)>'; });
  await page.click('.digitalisimo-finder__submit');
  await waitText('.digitalisimo-finder__status', 'Usa sólo letras');
  ok((await page.$$('.digitalisimo-finder img')).length === 0, 'Lo escrito nunca se pinta como HTML');
  ok(!errors.length, `Sin errores de JavaScript${errors.length ? ': ' + errors.join(' | ') : ''}`);

  // ?dominio= en la dirección (formulario sin JavaScript o enlace compartido): busca al cargar.
  const p2 = await browser.newPage();
  await p2.setViewport({ width: 375, height: 740, isMobile: true });
  await p2.goto(fixture, { waitUntil: 'domcontentloaded' });
  await p2.evaluate(() => { document.getElementById('dhf-1').value = 'desdeenlace'; });
  await p2.evaluate(() => { document.querySelector('script[src$="finder.js"]').remove(); const s = document.createElement('script'); s.src = '../../digitalisimo-hosting/assets/finder.js?2'; document.body.appendChild(s); });
  await p2.waitForFunction(() => (document.querySelector('.digitalisimo-finder__main-domain')?.textContent || '') === 'desdeenlace.com', { timeout: 5000 });
  ok(true, 'Con el campo ya lleno (?dominio=) busca al cargar');
} finally {
  await browser.close();
}
console.log(`Hosting, buscador estilo registrador en Chrome: ${checks} comprobaciones correctas.`);
