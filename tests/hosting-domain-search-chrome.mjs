// Buscador de dominios de DIGITALÍSIMO Hosting en Chrome real (sin interfaz) sobre tests/fixtures/hosting-domain-search.html
// (B2 de la auditoría SEO y pedido del propietario del 2026-11-26): el formulario clásico de Elements y el nuevo del
// editor atómico (e-form) consultan el dominio sin enviarse como formulario de contacto (el envío se toma antes que el
// manejador de jQuery o de Alpine), las extensiones son las configuradas (.com, .net y .mx: una lista fija con .org se
// sustituye), [digitalisimo_domain_search] funciona dentro de un formulario atómico (botón y Enter, sin enviarlo), sin
// campo de extensión se consultan todas, el enlace al carrito conserva su consulta y lo escrito por el visitante se
// pinta como texto.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/hosting-domain-search-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del buscador de dominios (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const entry = ['/lib/puppeteer/puppeteer-core.js', '/lib/esm/puppeteer/puppeteer-core.js'].map((f) => pptrDir + f).find(existsSync);
const { default: puppeteer } = await import(pathToFileURL(entry).href);
const fixture = new URL('./fixtures/hosting-domain-search.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; console.log(`ok · ${message}`); };

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
try {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  await page.goto(fixture, { waitUntil: 'load' });
  const options = (sel) => page.$$eval(`${sel} option`, (os) => os.map((o) => o.value));
  ok(JSON.stringify(await options('#classic select')) === '[".com",".net",".mx"]', 'Formulario clásico: la lista fija con .org se sustituye por .com, .net y .mx');
  ok(JSON.stringify(await options('#atomic select')) === '[".com",".net",".mx"]', 'Formulario atómico: las mismas extensiones configuradas');
  const waitText = (sel, text) => page.waitForFunction((s, t) => (document.querySelector(s)?.textContent || '').includes(t), { timeout: 5000 }, sel, text);

  // Clásico: libre + .net.
  await page.type('#classic input', 'Libre');
  await page.select('#classic select', '.net');
  await page.click('#classic button');
  await waitText('#classic-result', 'libre.net está disponible');
  const link = await page.$eval('#classic-result a', (a) => a.href);
  const u = new URL(link);
  ok(u.searchParams.get('add-to-cart') === '22' && u.searchParams.get('domain_name') === 'libre.net' && u.searchParams.get('lang') === 'es' && u.pathname === '/carrito/', `Clásico: «Registrar» agrega el .net al carrito con su nombre (${link})`);
  ok(/\$280/.test(await page.$eval('#classic-result', (d) => d.textContent)), 'Clásico: muestra el precio del producto');
  ok((await page.evaluate(() => window.handlers.classic)) === 0 && page.url() === fixture, 'Clásico: el manejador del formulario (jQuery) no se ejecuta ni se navega');

  // Atómico: ocupado + .mx; el resultado sale junto a su formulario, no en el del clásico.
  await page.type('#atomic input[name=dominio]', 'ocupado');
  await page.select('#atomic select', '.mx');
  await page.click('#atomic button');
  await page.waitForFunction(() => document.querySelector('#atomic + .digitalisimo-hosting-result')?.textContent.includes('ocupado.mx no está disponible'), { timeout: 5000 });
  ok(true, 'Atómico (e-form): consulta y pinta el resultado junto a su formulario');
  ok((await page.evaluate(() => window.handlers.alpine)) === 0, 'Atómico: el envío de Alpine no se ejecuta');
  ok((await page.$eval('#classic-result', (d) => d.textContent)).includes('libre.net'), 'Atómico: no pisa el resultado del formulario clásico');

  // Un nombre con su extensión escrita manda sobre la elegida.
  await page.$eval('#atomic input[name=dominio]', (i) => { i.value = 'libre.com'; });
  await page.click('#atomic button');
  await page.waitForFunction(() => document.querySelector('#atomic + .digitalisimo-hosting-result')?.textContent.includes('libre.com está disponible'), { timeout: 5000 });
  ok(true, 'Un dominio escrito con su extensión se consulta tal cual');

  // Shortcode dentro de un formulario atómico: botón y Enter.
  await page.type('#widget input', 'miempresa');
  await page.select('#widget select', '.mx');
  await page.click('#widget button');
  await waitText('#widget .digitalisimo-domain-result', 'miempresa.mx está disponible');
  ok((await page.evaluate(() => window.handlers.alpine)) === 0, '[digitalisimo_domain_search] dentro de un formulario atómico: el botón consulta y no envía el formulario');
  await page.$eval('#widget input', (i) => { i.value = 'otraempresa'; });
  await page.focus('#widget input');
  await page.keyboard.press('Enter');
  await waitText('#widget .digitalisimo-domain-result', 'otraempresa.mx está disponible');
  ok((await page.evaluate(() => window.handlers.alpine)) === 0, 'Enter en el buscador consulta y tampoco envía el formulario atómico');
  await page.click('#atomic2-submit');
  ok((await page.evaluate(() => window.handlers.alpine)) === 1, 'El botón de envío propio del formulario atómico sigue funcionando');

  // Sin campo de extensión: todas las configuradas.
  await page.type('#noext input', 'minegocio');
  await page.click('#noext button');
  await page.waitForFunction(() => document.querySelectorAll('#noext + .digitalisimo-hosting-result .digitalisimo-hosting-option').length === 3, { timeout: 5000 });
  const rows = await page.$$eval('#noext + .digitalisimo-hosting-result strong', (s) => s.map((x) => x.textContent));
  ok(JSON.stringify(rows) === '["minegocio.com","minegocio.net","minegocio.mx"]', `Sin campo de extensión: .com, .net y .mx (${rows})`);

  // Lo escrito se pinta como texto; nada fuera de lo configurado se consulta.
  const before = await page.evaluate(() => window.fetchLog.length);
  await page.$eval('#noext input', (i) => { i.value = '<img src=x onerror=alert(1)>'; });
  await page.click('#noext button');
  await waitText('#noext + .digitalisimo-hosting-result', 'Escribe un dominio');
  ok((await page.$$('#noext + .digitalisimo-hosting-result img')).length === 0 && (await page.evaluate(() => window.fetchLog.length)) === before, 'Un nombre con etiquetas no se consulta ni crea elementos');
  ok((await page.evaluate(() => window.fetchLog.every((u) => !/\.org\b/.test(u)))), 'Nunca se consulta un .org');

  const sent = () => page.evaluate(() => Array.from(new FormData(document.getElementById('panel')).keys()));
  ok(!(await sent()).includes('d') && (await page.$eval('#panel [name=d]', (i) => i.disabled)), 'Panel con «Dominio nuevo»: el campo de «Uno que ya tengo» está desactivado y no se envía');
  await page.click('#panel input[value=own]');
  ok((await sent()).includes('d'), 'Con «Uno que ya tengo» el campo se activa y se envía');
  await page.click('#contact button');
  ok((await page.evaluate(() => window.handlers.contact)) === 1, 'Un formulario de contacto sin campo de dominio no se toca');
  ok(!errors.length, `Sin errores de JavaScript${errors.length ? ': ' + errors.join(' | ') : ''}`);

  // Enlaces permanentes simples: rest_url() da «?rest_route=/digitalisimo-hosting/v1/»; la consulta sigue siendo una URL válida.
  const plain = await browser.newPage();
  await plain.evaluateOnNewDocument(() => { Object.defineProperty(window, 'digitalisimoHosting', { configurable: true, set(v) { this._dh = { ...v, endpoint: 'https://tienda.test/?rest_route=/digitalisimo-hosting/v1/' }; }, get() { return this._dh; } }); });
  await plain.goto(fixture, { waitUntil: 'load' });
  await plain.type('#noext input', 'simple');
  await plain.click('#noext button');
  await plain.waitForFunction(() => window.fetchLog.length >= 3, { timeout: 5000 });
  const urls = await plain.evaluate(() => window.fetchLog);
  ok(urls.every((u) => { const x = new URL(u); return x.searchParams.get('rest_route') === '/digitalisimo-hosting/v1/check-domain' && /^simple\./.test(x.searchParams.get('domain')); }), `Con enlaces permanentes simples la consulta lleva rest_route y domain por separado (${urls[0]})`);
} finally {
  await browser.close();
}
console.log(`Hosting, buscador de dominios en Chrome: ${checks} comprobaciones correctas.`);
