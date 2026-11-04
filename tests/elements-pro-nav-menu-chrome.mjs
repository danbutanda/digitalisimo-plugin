// Menú de WordPress propio (digitalisimo-nav-menu, C3 frente E de docs/pro/c3.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/nav-menu.html, que es el marcado del widget (lo comprueba tests/elements-pro-nav-menu.php):
// - sin JavaScript: los submenús se abren con el puntero encima o con el foco dentro (:hover / :focus-within) y los
//   botones de submenú no se pintan;
// - con JavaScript a 1280 px: --ready; cada botón controla su lista (aria-controls), cubre el indicador y mide al menos
//   24 × 24 px; clic (abre y cierra, aria-expanded, clase del enlace), ratón (abre a los 250 ms y cierra a los 500 ms de
//   salir), teclado (Intro, Tab dentro, Escape devuelve el foco al botón; niveles anidados), un hermano cierra al otro,
//   salir con Tab o hacer clic fuera cierra, el padre «#» abre su submenú sin navegar, el submenú que no cabe se gira,
//   el puntero aparece con el ratón encima y el foco visible es de 2 px;
// - a 375 px: el menú principal se oculta, la hamburguesa abre y cierra el desplegable (aria-expanded, visibilidad),
//   los submenús del desplegable se abren en acordeón, Escape cierra el desplegable y devuelve el foco, «Ancho completo»
//   estira el desplegable a la ventana y sin punto de corte el menú principal sigue;
// - movimiento reducido y reinicio en el editor (frontend/element_ready/digitalisimo-nav-menu.default).
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-pro-nav-menu-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const npx = '/home/digitalisimo/.npm/_npx';
const found = existsSync(npx) ? readdirSync(npx).map(d => `${npx}/${d}/node_modules/puppeteer-core`).find(d => existsSync(d + '/package.json')) : '';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : found;
if (!existsSync(chrome) || !pptrDir || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del menú de WordPress (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/nav-menu.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ width = 1280, js = true, reduce = false, editor = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setRequestInterception(true);
  page.on('request', r => (r.url().startsWith('file:') ? r.continue() : r.abort()));
  await page.setJavaScriptEnabled(js);
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  if (editor) await page.evaluateOnNewDocument(() => { window.elementorFrontend = { isEditMode: () => true, hooks: { addAction: (n, f) => { window.__hook = [n, f]; } } }; });
  await page.goto(fixture, { waitUntil: 'load' });
  if (js) await page.waitForFunction(() => document.querySelectorAll('.digi-nav--ready').length === 3);
  return page;
}
// Estado de un elemento padre (por el texto de su enlace) en un widget y una de sus dos copias (main | dd).
const item = (page, id, copy, text) => page.evaluate((id, copy, text) => {
  const scope = document.querySelector(`[data-id="${id}"] ${copy === 'main' ? '.digi-nav__main' : '.digi-nav__container.digi-nav__dropdown'}`);
  const a = [...scope.querySelectorAll('a')].find(x => x.textContent.trim() === text);
  const li = a.parentNode, b = li.querySelector(':scope>.digi-nav__sub-toggle'), ul = li.querySelector(':scope>ul');
  return { open: li.classList.contains('digi-nav__item--open'), expanded: b && b.getAttribute('aria-expanded'), visible: !!(ul && ul.offsetParent), hl: a.classList.contains('digi-nav__open'), controls: b && b.getAttribute('aria-controls'), ulId: ul && ul.id, focused: document.activeElement === b };
}, id, copy, text);
const btn = (id, copy, text) => `[data-id="${id}"] ${copy === 'main' ? '.digi-nav__main' : '.digi-nav__container.digi-nav__dropdown'} li:has(> a[href]:is(${text})) > .digi-nav__sub-toggle`;
const SERV = '[href$="/servicios/"]', DESA = '[href$="/desarrollo/"]', EQUI = '[href="#"]';

// 1. Sin JavaScript.
{
  const page = await open({ js: false });
  ok(await page.evaluate(() => !document.querySelector('.digi-nav--ready') && [...document.querySelectorAll('.digi-nav__sub-toggle')].every(b => getComputedStyle(b).display === 'none')), 'Sin JS: sin --ready y sin botones de submenú.');
  ok(!(await item(page, 'n1', 'main', 'Servicios')).visible, 'Sin JS: el submenú está cerrado.');
  await page.hover(`[data-id="n1"] .digi-nav__main a${SERV}`);
  await sleep(100);
  ok((await item(page, 'n1', 'main', 'Servicios')).visible, 'Sin JS: se abre con el puntero encima.');
  await page.mouse.move(0, 800);
  await page.focus(`[data-id="n1"] .digi-nav__main a${SERV}`);
  ok((await item(page, 'n1', 'main', 'Servicios')).visible, 'Sin JS: se abre con el foco dentro.');
  await page.close();
}

// 2. Con JavaScript a 1280 px.
{
  const page = await open();
  ok(await page.evaluate(() => [...document.querySelectorAll('.digi-nav__sub-toggle')].every(b => { const ul = document.getElementById(b.getAttribute('aria-controls')); return ul && ul === b.parentNode.querySelector(':scope>ul'); })), 'Cada botón controla la lista que le sigue (aria-controls).');
  const geo = await page.evaluate(sel => {
    const b = document.querySelector(sel), a = b.previousElementSibling, ar = a.querySelector('.digi-nav__arrow').getBoundingClientRect(), r = b.getBoundingClientRect(), l = a.getBoundingClientRect();
    return { w: r.width, h: r.height, covers: r.left <= ar.left + 0.5 && r.right >= ar.right - 0.5 && r.top <= ar.top + 0.5 && r.bottom >= ar.bottom - 0.5, inside: r.right <= l.right + 0.5 && r.top >= l.top - 0.5 && r.bottom <= l.bottom + 0.5, bg: getComputedStyle(b).backgroundColor };
  }, btn('n1', 'main', SERV));
  ok(geo.w >= 24 && geo.h >= 24 && geo.covers && geo.inside && geo.bg === 'rgba(0, 0, 0, 0)', 'El botón cubre el indicador dentro del enlace, sin pintarse, y mide al menos 24 × 24: ' + JSON.stringify(geo));
  // Clic en el botón.
  await page.click(btn('n1', 'main', SERV));
  let s = await item(page, 'n1', 'main', 'Servicios');
  ok(s.open && s.expanded === 'true' && s.visible && s.hl, 'Clic: abre (aria-expanded, visible, enlace resaltado).');
  await page.click(btn('n1', 'main', SERV));
  s = await item(page, 'n1', 'main', 'Servicios');
  ok(!s.open && s.expanded === 'false' && !s.visible && !s.hl, 'Segundo clic: cierra.');
  // Ratón: 250 ms para abrir y 500 ms para cerrar.
  await page.mouse.move(0, 800);
  await sleep(700);
  await page.hover(`[data-id="n1"] .digi-nav__main a${SERV}`);
  await sleep(100);
  ok(!(await item(page, 'n1', 'main', 'Servicios')).visible, 'Ratón: no abre antes de 250 ms.');
  await sleep(300);
  ok((await item(page, 'n1', 'main', 'Servicios')).visible, 'Ratón: abre a los 250 ms.');
  const u = await page.evaluate(() => { const ul = document.querySelector('[data-id="n1"] .digi-nav__main a[href$="/servicios/"] ~ ul').getBoundingClientRect(), a = document.querySelector('[data-id="n1"] .digi-nav__main a[href$="/servicios/"]').getBoundingClientRect(); return { top: ul.top - a.bottom, left: ul.left - a.left, z: getComputedStyle(document.querySelector('[data-id="n1"] .digi-nav__main a[href$="/servicios/"] ~ ul')).position }; });
  ok(Math.abs(u.top) < 1 && Math.abs(u.left) < 1 && u.z === 'absolute', 'El submenú sale bajo su elemento, alineado al inicio: ' + JSON.stringify(u));
  await page.hover(`[data-id="n1"] .digi-nav__main a${DESA}`);
  await sleep(400);
  const deep = await page.evaluate(() => { const ul = document.querySelector('[data-id="n1"] .digi-nav__main a[href$="/desarrollo/"] ~ ul'), a = document.querySelector('[data-id="n1"] .digi-nav__main a[href$="/desarrollo/"]').getBoundingClientRect(), r = ul.getBoundingClientRect(); return { visible: !!ul.offsetParent, dx: r.left - a.right, dy: r.top - a.top }; });
  ok(deep.visible && Math.abs(deep.dx) < 1 && Math.abs(deep.dy) < 1, 'El tercer nivel sale al lado de su elemento, a su altura: ' + JSON.stringify(deep));
  await page.mouse.move(0, 800);
  await sleep(300);
  ok((await item(page, 'n1', 'main', 'Servicios')).visible, 'Ratón fuera: sigue abierto antes de 500 ms.');
  await sleep(400);
  s = await item(page, 'n1', 'main', 'Servicios');
  ok(!s.visible && !(await item(page, 'n1', 'main', 'Desarrollo web')).open, 'Ratón fuera: cierra a los 500 ms, también los niveles de dentro.');
  // Puntero subrayado con el ratón encima.
  await page.hover('[data-id="n1"] .digi-nav__main a[href="https://ejemplo.test/#contacto"]');
  await sleep(500);
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="n1"] .digi-nav__main a[href="https://ejemplo.test/#contacto"]'), '::after').opacity === '1'), 'Subrayado visible con el ratón encima.');
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="n1"] .digi-nav__main a[href="https://ejemplo.test/"]'), '::after').opacity === '1'), 'Subrayado visible en el elemento actual.');
  await page.mouse.move(0, 800);
  // Teclado.
  await page.focus(btn('n1', 'main', SERV));
  await page.keyboard.press('Enter');
  s = await item(page, 'n1', 'main', 'Servicios');
  ok(s.open && s.expanded === 'true', 'Intro en el botón: abre.');
  const ring = await page.evaluate(() => { const c = getComputedStyle(document.activeElement); return `${c.outlineStyle} ${c.outlineWidth} ${c.outlineOffset}`; });
  ok(ring === 'solid 2px 2px', 'Foco visible propio del botón: ' + ring);
  await page.keyboard.press('Tab');
  ok(await page.evaluate(() => document.activeElement.getAttribute('href') === 'https://ejemplo.test/diseno/'), 'Tab: el primer enlace del submenú.');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  ok(await page.evaluate(() => document.activeElement.classList.contains('digi-nav__sub-toggle') && document.activeElement.getAttribute('aria-label') === 'Submenú de Desarrollo web'), 'Tab: el botón del submenú de segundo nivel.');
  await page.keyboard.press(' ');
  ok((await item(page, 'n1', 'main', 'Desarrollo web')).open, 'Espacio: abre el tercer nivel.');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Escape');
  s = await item(page, 'n1', 'main', 'Desarrollo web');
  ok(!s.open && s.focused && (await item(page, 'n1', 'main', 'Servicios')).open, 'Escape en el tercer nivel: lo cierra y vuelve a su botón; el segundo sigue abierto.');
  await page.keyboard.press('Escape');
  s = await item(page, 'n1', 'main', 'Servicios');
  ok(!s.open && s.focused, 'Escape otra vez: cierra el submenú y el foco vuelve a su botón.');
  // Salir con Tab cierra.
  await page.keyboard.press('Enter');
  for (let i = 0; i < 6; i++) await page.keyboard.press('Tab');
  ok(!(await item(page, 'n1', 'main', 'Servicios')).open, 'Salir del submenú con Tab lo cierra.');
  // Un hermano cierra al otro; clic fuera cierra; el padre «#» abre sin navegar.
  await page.click(btn('n1', 'main', SERV));
  await page.click(`[data-id="n1"] .digi-nav__main a${EQUI}`);
  ok((await item(page, 'n1', 'main', 'Equipo')).open && !(await item(page, 'n1', 'main', 'Servicios')).open && (await page.evaluate(() => location.hash)) === '', 'El padre «#» abre su submenú sin navegar y cierra el hermano.');
  await page.mouse.click(5, 850);
  ok(!(await item(page, 'n1', 'main', 'Equipo')).open, 'Clic fuera: cierra.');
  // Marco con dibujo (vertical): el submenú sale al lado.
  await page.click(btn('n3', 'main', SERV));
  const side = await page.evaluate(() => { const a = document.querySelector('[data-id="n3"] .digi-nav__main a[href$="/servicios/"]').getBoundingClientRect(), ul = document.querySelector('[data-id="n3"] .digi-nav__main a[href$="/servicios/"] ~ ul').getBoundingClientRect(); return { dx: ul.left - a.right, dy: ul.top - a.top }; });
  ok(Math.abs(side.dx) < 1 && Math.abs(side.dy) < 1, 'Vertical: el submenú sale al lado del elemento: ' + JSON.stringify(side));
  await page.mouse.click(5, 850);
  // n2 alineado al final: el tercer nivel no cabe a la derecha y se gira.
  await page.click(btn('n2', 'main', SERV));
  await page.click(btn('n2', 'main', DESA));
  const flip = await page.evaluate(() => { const ul = document.querySelector('[data-id="n2"] .digi-nav__main a[href$="/desarrollo/"] ~ ul'); const r = ul.getBoundingClientRect(); return { flip: ul.classList.contains('digi-nav__flip'), right: r.right, vw: document.documentElement.clientWidth }; });
  await sleep(500);
  ok(flip.right <= flip.vw + 0.5, 'Un submenú que no cabe se gira hacia dentro de la ventana: ' + JSON.stringify(flip));
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="n2"] .digi-nav__main a[href$="/servicios/"]')).color === 'rgb(255, 255, 255)'), 'Puntero de fondo: texto blanco en el elemento abierto.');
  await page.mouse.click(5, 850);
  ok(await page.evaluate(() => [...document.querySelectorAll('.digi-nav__toggle')].every(t => getComputedStyle(t).display === 'none') && [...document.querySelectorAll('.digi-nav__main')].every(m => getComputedStyle(m).display !== 'none')), 'En escritorio: menú principal a la vista y sin hamburguesa.');
  await page.close();
}

// 3. A 375 px.
{
  const page = await open({ width: 375 });
  const tog = id => page.evaluate(id => { const t = document.querySelector(`[data-id="${id}"] .digi-nav__toggle`), d = document.querySelector(`[data-id="${id}"] .digi-nav__container.digi-nav__dropdown`); return { shown: !!t.offsetParent, expanded: t.getAttribute('aria-expanded'), controls: t.getAttribute('aria-controls') === d.id, dd: getComputedStyle(d).visibility, h: d.getBoundingClientRect().height, w: t.getBoundingClientRect().width, main: getComputedStyle(document.querySelector(`[data-id="${id}"] .digi-nav__main`)).display }; }, id);
  let t = await tog('n1');
  ok(t.shown && t.expanded === 'false' && t.controls && t.dd === 'hidden' && t.main === 'none' && t.w >= 24, 'Móvil: hamburguesa (≥ 24 px) que controla el desplegable, cerrado; sin menú principal: ' + JSON.stringify(t));
  await page.click('[data-id="n1"] .digi-nav__toggle');
  await sleep(400);
  t = await tog('n1');
  ok(t.expanded === 'true' && t.dd === 'visible' && t.h > 100, 'Hamburguesa: abre el desplegable: ' + JSON.stringify(t));
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="n1"] .digi-nav__toggle .digi-nav__toggle-close')).display !== 'none' && getComputedStyle(document.querySelector('[data-id="n1"] .digi-nav__toggle .digi-nav__toggle-open')).display === 'none'), 'Hamburguesa abierta: icono de cerrar.');
  await page.click(btn('n1', 'dd', SERV));
  await page.click(btn('n1', 'dd', EQUI));
  ok((await item(page, 'n1', 'dd', 'Servicios')).visible && (await item(page, 'n1', 'dd', 'Equipo')).visible, 'Desplegable: los submenús se abren en acordeón (uno no cierra al otro).');
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="n1"] .digi-nav__container.digi-nav__dropdown a[href$="/diseno/"]').closest('ul')).position === 'static'), 'Desplegable: los submenús ocupan su sitio (no flotan).');
  await page.focus('[data-id="n1"] .digi-nav__container.digi-nav__dropdown a[href="https://ejemplo.test/"]');
  await page.keyboard.press('Escape');
  t = await tog('n1');
  ok(t.expanded === 'false' && (await page.evaluate(() => document.activeElement.classList.contains('digi-nav__toggle'))), 'Escape en el desplegable: lo cierra y el foco vuelve a la hamburguesa.');
  await page.click('[data-id="n1"] .digi-nav__toggle');
  await page.click('[data-id="n1"] .digi-nav__toggle');
  ok((await tog('n1')).expanded === 'false' && !(await item(page, 'n1', 'dd', 'Servicios')).open, 'Hamburguesa: segundo clic cierra, también los submenús.');
  // Ancho completo (n2, punto de corte móvil).
  await page.click('[data-id="n2"] .digi-nav__toggle');
  await sleep(400);
  const st = await page.evaluate(() => { const d = document.querySelector('[data-id="n2"] .digi-nav__container.digi-nav__dropdown'), r = d.getBoundingClientRect(), t = document.querySelector('[data-id="n2"] .digi-nav__toggle').getBoundingClientRect(); return { left: r.left, width: r.width, vw: document.documentElement.clientWidth, pos: getComputedStyle(d).position, below: r.top - t.bottom, center: getComputedStyle(d.querySelector('a')).justifyContent }; });
  ok(Math.abs(st.left) < 1 && Math.abs(st.width - st.vw) < 1 && st.pos === 'absolute' && Math.abs(st.below - 10) < 1.5 && st.center === 'center', 'Ancho completo: el desplegable ocupa la ventana, 10 px bajo la hamburguesa, con el texto centrado: ' + JSON.stringify(st));
  // Sin punto de corte: el menú principal sigue y no hay hamburguesa.
  ok(await page.evaluate(() => !document.querySelector('[data-id="n3"] .digi-nav__toggle') || getComputedStyle(document.querySelector('[data-id="n3"] .digi-nav__toggle')).display === 'none') && (await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="n3"] .digi-nav__main')).display !== 'none' && getComputedStyle(document.querySelector('[data-id="n3"] .digi-nav__container.digi-nav__dropdown')).display === 'none')), 'Sin punto de corte: menú principal en móvil, sin desplegable.');
  await page.close();
}

// 4. A 768 px (tableta): el punto de corte de tableta también muestra la hamburguesa.
{
  const page = await open({ width: 768 });
  ok(await page.evaluate(() => !!document.querySelector('[data-id="n1"] .digi-nav__toggle').offsetParent && !document.querySelector('[data-id="n2"] .digi-nav__toggle').offsetParent && !!document.querySelector('[data-id="n2"] .digi-nav__main').offsetParent), 'Tableta: hamburguesa con el punto de corte de tableta; menú principal con el de móvil.');
  await page.close();
}

// 5. Movimiento reducido y editor.
{
  const page = await open({ reduce: true });
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('.digi-nav__main a'), '::after').transitionDuration === '0s' && getComputedStyle(document.querySelector('.digi-nav__main a')).transitionDuration === '0s'), 'Movimiento reducido: sin transiciones.');
  await page.close();
  const ed = await open({ editor: true, js: true });
  ok(await ed.evaluate(() => window.__hook && window.__hook[0] === 'frontend/element_ready/digitalisimo-nav-menu.default'), 'Editor: se engancha a frontend/element_ready del widget.');
  ok(await ed.evaluate(() => {
    const w = document.querySelector('[data-id="n1"]'), html = w.innerHTML;
    w.innerHTML = html.replace(/ digi-nav--ready/g, '');
    window.__hook[1]([w]);
    return w.querySelector('.digi-nav').classList.contains('digi-nav--ready');
  }), 'Editor: el widget repintado se vuelve a iniciar.');
  await ed.close();
}

await browser.close();
assert.ok(errors.length === 0, 'Errores de JavaScript: ' + errors.join(' | '));
console.log(`DIGITALÍSIMO Elements: Menú de WordPress en Chrome (${checks} comprobaciones) validado.`);
