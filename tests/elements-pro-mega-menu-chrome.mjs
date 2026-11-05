// Menú con submenús de contenido propio (digitalisimo-mega-menu, C3 frente E de docs/pro/c3.md) en Chrome real (sin
// interfaz) sobre tests/fixtures/mega-menu.html, que es el marcado del widget (lo comprueba
// tests/elements-pro-mega-menu.php):
// - 1280 px, «Abrir con: ratón» y ancho completo (m1): disposición horizontal; el contenido se abre con el ratón y se
//   cierra al salir, sale bajo el menú a todo el ancho de la ventana; el clic del ratón no lo cierra; teclado (Intro,
//   Tab dentro, Escape devuelve el foco al botón, Inicio y Fin); salir con Tab y hacer clic fuera cierran; zona de
//   pulsación de 24 × 24 px y foco visible de 2 px;
// - «Abrir con: clic», ajustado al contenido y centrado, con fundido (m2): el ratón no abre, el clic en el título abre y
//   cierra, el contenido se centra bajo su elemento, un elemento cierra al otro, el fundido y el movimiento reducido;
//   anclas activas al desplazarse;
// - sólo desplegable (m3) y móvil (375 px): hamburguesa (aria-expanded, el envoltorio aparece a todo el ancho bajo el
//   botón), los contenidos se abren en su sitio, Escape cierra el contenido y luego el desplegable y devuelve el foco;
// - el evento digi-mm-go (panel del editor) abre el elemento pedido; reinicio con frontend/element_ready del widget.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-pro-mega-menu-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const npx = '/home/digitalisimo/.npm/_npx';
const found = existsSync(npx) ? readdirSync(npx).map(d => `${npx}/${d}/node_modules/puppeteer-core`).find(d => existsSync(d + '/package.json')) : '';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : found;
if (!existsSync(chrome) || !pptrDir || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del menú con submenús de contenido (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/mega-menu.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ width = 1280, reduce = false, editor = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  if (editor) await page.evaluateOnNewDocument(() => { window.elementorFrontend = { isEditMode: () => true, hooks: { addAction: (n, f) => { window.__hook = [n, f]; } } }; });
  await page.goto(fixture, { waitUntil: 'load' });
  await page.waitForFunction(() => document.querySelectorAll('.digi-mm--ready').length === 3);
  return page;
}
// Estado de un elemento (1, 2, 3, 4) de un menú.
const item = (page, id, n) => page.evaluate((id, n) => {
  const li = document.querySelectorAll(`[data-id="${id}"] .digi-mm__heading > li`)[n - 1], b = li.querySelector('.digi-mm__dropdown-icon'), c = li.querySelector('.digi-mm__content > .e-con');
  const r = c && c.getBoundingClientRect(), t = li.querySelector('.digi-mm__title').getBoundingClientRect();
  return { expanded: b && b.getAttribute('aria-expanded'), visible: !!(c && c.offsetParent), active: !!(c && c.classList.contains('digi-mm__active')), focused: document.activeElement === b, rect: r && { left: r.left, top: r.top, width: r.width, right: r.right }, title: { left: t.left, right: t.right, bottom: t.bottom }, fade: !!(c && c.classList.contains('digi-mm__fade')), anim: c ? getComputedStyle(c).animationName : '', pos: c ? getComputedStyle(c.parentNode).position : '' };
}, id, n);
const sel = (id, n, s) => `[data-id="${id}"] .digi-mm__heading > li:nth-child(${n}) ${s}`;

// 1. 1280 px, ratón y ancho completo (m1).
{
  const page = await open();
  ok(await page.evaluate(() => ['m1', 'm2', 'm3'].map(id => document.querySelector(`[data-id="${id}"] .digi-mm`).dataset.layout).join() === 'horizontal,horizontal,dropdown'), 'Disposición: horizontal en escritorio; m3 sólo desplegable.');
  ok(await page.evaluate(() => [...document.querySelectorAll('.digi-mm__dropdown-icon')].every(b => document.getElementById(b.getAttribute('aria-controls')) && /^Submenú de /.test(b.getAttribute('aria-label')))), 'Cada botón controla su contenido y se llama «Submenú de …».');
  ok(await page.evaluate(() => !document.querySelector('[data-id="m1"] .digi-mm__heading > li:nth-child(3) .digi-mm__dropdown-icon')), 'Un elemento sin contenido desplegable no tiene botón.');
  const hit = await page.evaluate(s => { const b = document.querySelector(s), r = b.getBoundingClientRect(), p = getComputedStyle(b, '::before'); return { w: parseFloat(p.width), h: parseFloat(p.height), at: document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2 + 9) === b }; }, sel('m1', 1, '.digi-mm__dropdown-icon'));
  ok(hit.w >= 24 && hit.h >= 24 && hit.at, 'Zona de pulsación del botón de al menos 24 × 24 px: ' + JSON.stringify(hit));
  await page.hover(sel('m1', 1, '.digi-mm__title-text'));
  await sleep(150);
  let s = await item(page, 'm1', 1);
  const vw = await page.evaluate(() => document.documentElement.clientWidth);
  ok(s.expanded === 'true' && s.visible && s.active, 'Ratón: abre el contenido.');
  const nav = await page.evaluate(() => document.querySelector('[data-id="m1"] .digi-mm').getBoundingClientRect().bottom);
  ok(Math.abs(s.rect.left) < 1 && Math.abs(s.rect.width - vw) < 1 && Math.abs(s.rect.top - nav) < 1, 'Ancho completo: el contenido ocupa la ventana bajo el menú: ' + JSON.stringify({ rect: s.rect, vw, nav }));
  await page.hover(sel('m1', 2, '.digi-mm__title-text'));
  await sleep(200);
  ok((await item(page, 'm1', 2)).visible && !(await item(page, 'm1', 1)).visible, 'Ratón sobre otro elemento: abre el suyo y cierra el anterior.');
  await page.mouse.move(5, 880);
  await sleep(300);
  ok(!(await item(page, 'm1', 2)).visible, 'Ratón fuera: cierra.');
  await page.click(sel('m1', 1, '.digi-mm__dropdown-icon'));
  await sleep(200);
  ok((await item(page, 'm1', 1)).visible, 'Con «ratón», el clic del ratón en el botón no cierra lo que abrió el paso del ratón.');
  await page.mouse.move(5, 880);
  await sleep(300);
  // Teclado.
  await page.focus(sel('m1', 1, '.digi-mm__dropdown-icon'));
  await page.keyboard.press('Enter');
  s = await item(page, 'm1', 1);
  ok(s.expanded === 'true' && s.visible, 'Intro en el botón: abre.');
  await sleep(400); // El botón tiene la transición «all» de PRO.
  const ring = await page.evaluate(() => { const c = getComputedStyle(document.activeElement); return `${c.outlineStyle} ${c.outlineWidth} ${c.outlineOffset}`; });
  ok(ring === 'solid 2px 2px', 'Foco visible del botón: ' + ring);
  await page.keyboard.press('Tab');
  ok(await page.evaluate(() => document.activeElement.getAttribute('href') === '#s1'), 'Tab: entra en el contenido.');
  await page.keyboard.press('Escape');
  s = await item(page, 'm1', 1);
  ok(s.expanded === 'false' && !s.visible && s.focused, 'Escape en el contenido: cierra y el foco vuelve al botón.');
  await page.keyboard.press('End');
  ok(await page.evaluate(() => document.activeElement === document.querySelector('[data-id="m1"] .digi-mm__heading > li:nth-child(4) .digi-mm__dropdown-icon')), 'Fin: el último control de los títulos.');
  await page.keyboard.press('Home');
  ok(await page.evaluate(() => document.activeElement === document.querySelector('[data-id="m1"] .digi-mm__heading > li:nth-child(1) .digi-mm__dropdown-icon')), 'Inicio: el primero.');
  await page.keyboard.press(' ');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  ok(!(await item(page, 'm1', 1)).visible, 'Salir del contenido con Tab lo cierra.');
  await page.focus(sel('m1', 2, '.digi-mm__dropdown-icon'));
  await page.keyboard.press('Enter');
  await page.mouse.click(5, 880);
  ok(!(await item(page, 'm1', 2)).visible, 'Clic fuera: cierra.');
  // 2. Clic, ajustado y centrado, con fundido (m2).
  await page.hover(sel('m2', 1, '.digi-mm__title-text'));
  await sleep(200);
  ok(!(await item(page, 'm2', 1)).visible, 'Con «clic», el ratón no abre.');
  await page.click(sel('m2', 1, '.digi-mm__title-text'));
  await sleep(100);
  s = await item(page, 'm2', 1);
  ok(s.visible && s.fade && s.anim === 'digi-mm-fade', 'Clic en el título: abre con fundido.');
  const mid = (s.rect.left + s.rect.right) / 2, tmid = (s.title.left + s.title.right) / 2;
  ok(s.rect.width < vw - 1 && (Math.abs(mid - tmid) < 1.5 || s.rect.left < 1), 'Ajustado al contenido y centrado bajo su elemento: ' + JSON.stringify({ rect: s.rect, title: s.title }));
  await page.click(sel('m2', 2, '.digi-mm__dropdown-icon'));
  ok((await item(page, 'm2', 2)).visible && !(await item(page, 'm2', 1)).visible, 'Un elemento cierra al otro.');
  await page.click(sel('m2', 2, '.digi-mm__dropdown-icon'));
  ok(!(await item(page, 'm2', 2)).visible, 'Segundo clic en el botón: cierra.');
  // Anclas: el título del ancla se marca mientras su sección está en pantalla.
  await page.evaluate(() => document.getElementById('equipo').scrollIntoView());
  await sleep(400);
  ok(await page.evaluate(() => document.querySelector('[data-id="m1"] .digi-mm__heading > li:nth-child(2) .digi-mm__title').classList.contains('digi-mm__current') && document.querySelector('[data-id="m1"] .digi-mm__heading > li:nth-child(2) a').getAttribute('aria-current') === 'location'), 'Ancla en pantalla: título actual y aria-current="location".');
  await page.evaluate(() => window.scrollTo(0, 0));
  // Panel del editor: abre el elemento pedido.
  await page.evaluate(() => document.querySelector('[data-id="m1"] .digi-mm').dispatchEvent(new CustomEvent('digi-mm-go', { detail: 4 })));
  ok((await item(page, 'm1', 4)).visible, 'digi-mm-go abre el elemento pedido.');
  // m3: sólo desplegable también en escritorio.
  ok(await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="m3"] .digi-mm__toggle')).display === 'flex' && getComputedStyle(document.querySelector('[data-id="m3"] .digi-mm__wrapper')).display === 'none'), 'Sólo desplegable: hamburguesa y lista oculta en escritorio.');
  await page.close();
}

// 3. 375 px.
{
  const page = await open({ width: 375 });
  const tog = id => page.evaluate(id => { const t = document.querySelector(`[data-id="${id}"] .digi-mm__toggle`), w = document.querySelector(`[data-id="${id}"] .digi-mm__wrapper`), r = w.getBoundingClientRect(), tr = t.getBoundingClientRect(); return { layout: t.closest('.digi-mm').dataset.layout, shown: getComputedStyle(t).display, expanded: t.getAttribute('aria-expanded'), controls: t.getAttribute('aria-controls') === w.id, wrap: getComputedStyle(w).display, left: r.left, width: r.width, below: r.top - tr.bottom, vw: document.documentElement.clientWidth, focused: document.activeElement === t }; }, id);
  let t = await tog('m1');
  ok(t.layout === 'dropdown' && t.shown === 'flex' && t.expanded === 'false' && t.controls && t.wrap === 'none', 'Móvil: hamburguesa que controla la lista, cerrada: ' + JSON.stringify(t));
  await page.click('[data-id="m1"] .digi-mm__toggle');
  await sleep(200);
  t = await tog('m1');
  ok(t.expanded === 'true' && t.wrap === 'flex' && Math.abs(t.left) < 1 && Math.abs(t.width - t.vw) < 1 && Math.abs(t.below) < 1, 'Hamburguesa: la lista aparece a todo el ancho bajo el botón: ' + JSON.stringify(t));
  await page.click(sel('m1', 1, '.digi-mm__dropdown-icon'));
  let s = await item(page, 'm1', 1);
  ok(s.visible && s.pos !== 'absolute' && s.rect.top >= s.title.bottom - 1, 'Desplegable: el contenido se abre en su sitio, bajo su título.');
  await page.focus(sel('m1', 1, '.digi-mm__content a'));
  await page.keyboard.press('Escape');
  ok(!(await item(page, 'm1', 1)).visible && (await item(page, 'm1', 1)).focused, 'Escape: cierra el contenido y vuelve al botón.');
  await page.keyboard.press('Escape');
  t = await tog('m1');
  ok(t.expanded === 'false' && t.wrap === 'none' && t.focused, 'Escape otra vez: cierra el desplegable y vuelve a la hamburguesa.');
  const hit = await page.evaluate(() => { const p = getComputedStyle(document.querySelector('[data-id="m1"] .digi-mm__toggle'), '::before'); return parseFloat(p.width) >= 24 && parseFloat(p.height) >= 24; });
  ok(hit, 'Zona de pulsación de la hamburguesa de al menos 24 × 24 px.');
  await page.close();
}

// 4. 768 px: el punto de corte de tableta pasa a desplegable; el de móvil, no.
{
  const page = await open({ width: 768 });
  ok(await page.evaluate(() => document.querySelector('[data-id="m1"] .digi-mm').dataset.layout === 'dropdown' && document.querySelector('[data-id="m2"] .digi-mm').dataset.layout === 'horizontal'), 'Tableta: m1 (tableta) desplegable, m2 (móvil) horizontal.');
  await page.close();
}

// 5. Movimiento reducido y editor.
{
  const page = await open({ reduce: true });
  await page.click(sel('m2', 1, '.digi-mm__title-text'));
  ok((await item(page, 'm2', 1)).anim === 'none', 'Movimiento reducido: sin fundido.');
  await page.close();
  const ed = await open({ editor: true });
  ok(await ed.evaluate(() => window.__hook && window.__hook[0] === 'frontend/element_ready/digitalisimo-mega-menu.default'), 'Editor: se engancha a frontend/element_ready del widget.');
  ok(await ed.evaluate(() => {
    const w = document.querySelector('[data-id="m1"]');
    w.innerHTML = w.innerHTML.replace(/ digi-mm--ready/g, '');
    window.__hook[1]([w]);
    return w.querySelector('.digi-mm').classList.contains('digi-mm--ready');
  }), 'Editor: el widget repintado se vuelve a iniciar.');
  await ed.hover(sel('m1', 1, '.digi-mm__title-text'));
  await sleep(150);
  ok(!(await item(ed, 'm1', 1)).visible, 'Editor: el ratón no abre (se edita con clic).');
  await ed.click(sel('m1', 1, '.digi-mm__title-text'));
  ok((await item(ed, 'm1', 1)).visible, 'Editor: el clic en el título abre.');
  await ed.close();
}

await browser.close();
assert.ok(errors.length === 0, 'Errores de JavaScript: ' + errors.join(' | '));
console.log(`DIGITALÍSIMO Elements: Menú con submenús de contenido en Chrome (${checks} comprobaciones) validado.`);
