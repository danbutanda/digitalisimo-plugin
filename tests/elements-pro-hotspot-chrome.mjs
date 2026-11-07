// Puntos de interés (digitalisimo-hotspot, C2, frente C de docs/pro/c2.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-hotspot.html, que es el marcado del servidor (lo comprueba tests/elements-pro-hotspot.php):
// - «al pulsar»: el botón abre y cierra (clase, aria-expanded, información emergente visible), abrir uno cierra los
//   demás, teclado (Intro y Espacio en el botón, Escape cierra y devuelve el foco desde un enlace interior), un punto
//   con enlace sigue siendo un enlace sin información emergente;
// - «al pasar»: abre con el puntero y se cierra al salir; abre con el foco del teclado y se cierra al salir con Tab;
//   Escape la cierra con el puntero encima; el enlace con información emergente la abre y la describe;
// - «siempre visible»: a la vista, sin nada que pulsar; disparador por dispositivo (al pasar en el escritorio, al pulsar
//   a 375 px); secuencia con retrasos al entrar en la pantalla; deslizamiento desde la dirección;
// - objetivos de 24 × 24 px como mínimo, foco visible de 2 px y movimiento reducido (sin animaciones ni transiciones);
// - sin errores de JavaScript y el editor (frontend/element_ready/digitalisimo-hotspot.default).
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-hotspot-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los Puntos de interés (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-hotspot.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ width = 1280, reduce = false, editor = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/ERR_NAME_NOT_RESOLVED|Failed to load resource/.test(m.text())) errors.push(m.text()); });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  if (editor) await page.evaluateOnNewDocument(() => { window.elementorFrontend = { hooks: { addAction: (n, f) => { window.__hook = [n, f]; } } }; });
  await page.goto(fixture, { waitUntil: 'load' });
  await page.waitForFunction(() => document.querySelectorAll('.digi-hotspots--ready').length === 5);
  return page;
}
// Estado de los puntos de un caso: abierto (clase), aria-expanded y visibilidad de su información emergente.
const state = (page, id) => page.evaluate(id => [...document.querySelectorAll(`[data-case="${id}"] .digi-hs`)].map(s => {
  const b = s.querySelector('.digi-hs__button'), t = s.querySelector('.digi-hs__tip');
  return { open: s.classList.contains('digi-hs--active'), expanded: b.getAttribute('aria-expanded'), visible: t ? t.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true }) : null };
}), id);
const sel = (id, n, part = '.digi-hs__button') => `[data-case="${id}"] .elementor-repeater-item-a${n} ${part}`;
const center = (page, s) => page.$eval(s, el => { el.scrollIntoView({ block: 'center' }); const r = el.getBoundingClientRect(); return [r.x + r.width / 2, r.y + r.height / 2]; });

// 1. «Al pulsar».
let page = await open();
let st = await state(page, 'click');
ok(st.every(s => !s.open) && st[0].expanded === 'false' && st[2].expanded === null && st[2].visible === null, 'Al empezar todo cerrado; el enlace no tiene información emergente.');
ok(st[0].visible === false && st[1].visible === false, 'Cerrada, la información emergente no se ve.');
ok(await page.$eval(sel('click', 2, '.digi-hs__tip a'), a => !a.checkVisibility({ checkVisibilityCSS: true })), 'El enlace de una información emergente cerrada no se ve ni recibe el foco (visibility).');
await page.click(sel('click', 1));
await sleep(650);
st = await state(page, 'click');
ok(st[0].open && st[0].expanded === 'true' && st[0].visible, 'Pulsar abre: clase, aria-expanded y visible.');
await page.click(sel('click', 2));
await sleep(650);
st = await state(page, 'click');
ok(!st[0].open && st[0].expanded === 'false' && st[1].open && st[1].visible, 'Abrir otro cierra el anterior.');
await page.click(sel('click', 2));
await sleep(650);
st = await state(page, 'click');
ok(st.every(s => !s.open) && st[1].visible === false, 'Pulsar otra vez cierra.');
await page.focus(sel('click', 1));
await page.keyboard.press('Enter');
await sleep(100);
ok((await state(page, 'click'))[0].open, 'Intro en el botón abre.');
await page.keyboard.press('Space');
await sleep(100);
ok(!(await state(page, 'click'))[0].open, 'Espacio en el botón cierra.');
await page.focus(sel('click', 2));
await page.keyboard.press('Enter');
await sleep(600);
await page.keyboard.press('Tab');
ok(await page.evaluate(() => document.activeElement.getAttribute('href') === '#isla'), 'Abierta, Tab entra en el enlace de la información emergente.');
await page.keyboard.press('Escape');
await sleep(100);
ok(!(await state(page, 'click'))[1].open && await page.evaluate(() => document.activeElement.matches('[data-case="click"] .elementor-repeater-item-a2 .digi-hs__button')), 'Escape cierra y devuelve el foco al botón.');
const outline = await page.evaluate(() => { const b = document.activeElement, c = getComputedStyle(b); return [c.outlineStyle, c.outlineWidth, c.outlineOffset]; });
ok(outline[0] === 'solid' && outline[1] === '2px' && outline[2] === '2px', 'Foco visible: contorno de 2 px a 2 px: ' + outline.join(' '));
const targets = await page.evaluate(() => [...document.querySelectorAll('[data-case="click"] .digi-hs__button')].map(b => { const r = b.getBoundingClientRect(), p = getComputedStyle(b, '::before'); return [Math.round(r.width), Math.round(r.height), p.content, p.inset]; }));
ok(targets.every(([w, h, c, i]) => w + 10 >= 24 && h + 10 >= 24 && c === '""' && i === '-5px'), 'Objetivos de 24 × 24 px como mínimo (con el área de 5 px alrededor): ' + JSON.stringify(targets));
ok(targets[0][0] === 26 && targets[0][1] === 26, 'El punto circular mide 26 × 26 px como en PRO (18 + 2 × 4).');
ok(await page.$eval('[data-case="click"] .elementor-repeater-item-a3', a => a.tagName === 'A' && a.getAttribute('href') === 'https://a.test/x' && !a.querySelector('.digi-hs__tip')), 'Con enlace y «al pulsar», el punto es el enlace, sin información emergente.');

// 2. «Al pasar».
await page.mouse.move(...await center(page, sel('hover', 1)));
await sleep(650);
st = await state(page, 'hover');
ok(st[0].open && st[0].expanded === 'true' && st[0].visible, 'El puntero encima abre.');
await page.mouse.move(5, 5);
await sleep(650);
st = await state(page, 'hover');
ok(!st[0].open && st[0].visible === false, 'Al salir el puntero se cierra.');
await page.mouse.move(...await center(page, sel('hover', 2)));
await sleep(100);
await page.keyboard.press('Escape');
await sleep(100);
ok(!(await state(page, 'hover'))[1].open, 'Escape la cierra con el puntero encima (WCAG 1.4.13).');
await page.mouse.move(5, 5);
await page.focus('[data-case="click"] .elementor-repeater-item-a3');
await page.keyboard.press('Tab');
await sleep(100);
ok((await state(page, 'hover'))[0].open, 'El foco del teclado abre en el modo «al pasar».');
await page.keyboard.press('Tab');
await sleep(100);
st = await state(page, 'hover');
ok(!st[0].open && st[1].open, 'Tab al siguiente: se cierra el anterior y se abre el siguiente.');
await page.evaluate(() => document.activeElement.blur());
await sleep(650);
ok(!(await state(page, 'hover'))[1].open, 'Al perder el foco (sin el puntero encima) se cierra.');
await page.mouse.move(...await center(page, sel('hover', 3)));
await sleep(650);
st = await state(page, 'hover');
ok(st[2].open && st[2].visible && await page.$eval('[data-case="hover"] .elementor-repeater-item-a3', a => a.tagName === 'A' && a.querySelector('.digi-hs__tip').textContent === 'Enlace con información'), 'El enlace con información emergente (dentro, como PRO) la abre al pasar.');
await page.mouse.move(5, 5);
await page.focus('[data-case="hover"] .elementor-repeater-item-a3');
await sleep(100);
ok((await state(page, 'hover'))[2].open, 'El foco del teclado en el enlace también la abre.');
await page.keyboard.press('Escape');
await sleep(100);
ok(!(await state(page, 'hover'))[2].open && await page.evaluate(() => document.activeElement.matches('[data-case="hover"] a.digi-hs')), 'Escape la cierra y el foco sigue en el enlace.');
await page.evaluate(() => document.activeElement.blur());
await page.mouse.move(5, 5);
await page.click(sel('hover', 1));
await sleep(100);
ok((await state(page, 'hover'))[0].open, 'En «al pasar», pulsar abre (un toque en pantallas táctiles), nunca cierra.');

// 3. «Siempre visible», secuencia, dirección.
st = await state(page, 'none');
ok(st[0].visible && st[0].expanded === null && await page.$eval(sel('none', 2), b => b.tagName === 'SPAN'), '«Siempre visible»: a la vista y sin botón.');
await page.$eval('[data-case="seq"]', el => el.scrollIntoView({ block: 'center' }));
await sleep(200);
const delays = await page.$$eval('[data-case="seq"] .digi-hs', s => s.map(x => x.style.animationDelay));
ok(delays.join() === ',300ms,600ms', 'Secuencia: retrasos de 900 ms / 3 desde el segundo punto: ' + delays.join());
ok(await page.$eval('[data-case="seq"] .digi-hs', s => getComputedStyle(s).animationName === 'digi-hs-in'), 'Secuencia con la animación de entrada propia.');
const slide = await page.$eval(sel('seq', 1, '.digi-hs__tip'), t => [getComputedStyle(t).transform, getComputedStyle(t.parentElement).overflow]);
ok(slide[0] !== 'none' && slide[1] === 'hidden', 'Deslizar desde la dirección: fuera de la máscara al empezar.');
await page.click(sel('seq', 1));
await sleep(650);
ok(await page.$eval(sel('seq', 1, '.digi-hs__tip'), t => getComputedStyle(t).transform === 'none' && t.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true })), 'Abierta, entra en la máscara.');
ok(await page.$eval('[data-case="click"] .digi-hs__outer', o => getComputedStyle(o).animationName === 'digi-hs-expand') && await page.$eval('[data-case="hover"] .digi-hs__button', b => getComputedStyle(b).animationName === 'digi-hs-beat'), 'Animaciones «Expansión» y «Latido suave».');
// Disparador por dispositivo: «al pasar» en el escritorio.
await page.mouse.move(...await center(page, sel('device', 2)));
await sleep(100);
ok((await state(page, 'device'))[0].open, 'Escritorio: «al pasar».');
await page.close();

page = await open({ width: 375 });
await page.mouse.move(...await center(page, sel('device', 2)));
await sleep(100);
ok(!(await state(page, 'device'))[0].open, 'Móvil: el puntero encima no abre.');
await page.click(sel('device', 2));
await sleep(100);
ok((await state(page, 'device'))[0].open, 'Móvil: «al pulsar».');
await page.click(sel('device', 2));
await sleep(100);
ok(!(await state(page, 'device'))[0].open, 'Móvil: pulsar otra vez cierra.');
await page.close();

// 4. Movimiento reducido.
page = await open({ reduce: true });
const anims = await page.evaluate(() => [...document.querySelectorAll('.digi-hotspots *')].filter(e => getComputedStyle(e).animationName !== 'none' || parseFloat(getComputedStyle(e).transitionDuration) > 0).length);
ok(anims === 0, 'Con movimiento reducido no hay animaciones ni transiciones: ' + anims);
await page.click(sel('click', 1));
await sleep(50);
ok((await state(page, 'click'))[0].visible, 'Con movimiento reducido se abre al instante.');
// Lo convertido de PRO (digi-hotspots--motion, corrección de fidelidad de C2) sigue animado, como PRO; sin transiciones.
const moving = await page.evaluate(() => {
  document.querySelectorAll('.digi-hotspots').forEach((r) => r.classList.add('digi-hotspots--motion'));
  const all = [...document.querySelectorAll('.digi-hotspots *')];
  return [all.filter(e => getComputedStyle(e).animationName !== 'none').length, all.filter(e => parseFloat(getComputedStyle(e).transitionDuration) > 0).length];
});
ok(moving[0] > 0 && moving[1] === 0, 'Con digi-hotspots--motion, las animaciones siguen con movimiento reducido y las transiciones no: ' + moving.join('/'));
await page.close();

// 5. Editor: se vuelve a iniciar con frontend/element_ready.
page = await open({ editor: true });
const hooked = await page.evaluate(() => {
  if (!window.__hook || window.__hook[0] !== 'frontend/element_ready/digitalisimo-hotspot.default') return 'sin gancho';
  const box = document.querySelector('[data-case="click"]'), copy = box.cloneNode(true);
  copy.dataset.case = 'copy';
  copy.querySelector('.digi-hotspots').classList.remove('digi-hotspots--ready');
  delete copy.querySelector('.digi-hotspots').digiHsTriggers;
  document.body.appendChild(copy);
  window.__hook[1]([copy]);
  copy.querySelector('.digi-hs__button').click();
  return copy.querySelector('.digi-hotspots').classList.contains('digi-hotspots--ready') && copy.querySelector('.digi-hs').classList.contains('digi-hs--active');
});
ok(hooked === true, 'Editor: frontend/element_ready inicia el widget repintado: ' + hooked);
await page.close();

ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
await browser.close();
console.log(`DIGITALÍSIMO Elements: Puntos de interés en Chrome (${checks} comprobaciones) validados.`);
