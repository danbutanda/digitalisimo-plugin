// Botones de contacto y Barra flotante propios (C8, docs/pro/c8.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-floating.html, el marcado del servidor con sus hojas y scripts (lo escribe
// `tests/elements-pro-floating.php --write-fixture`):
// - panel de los botones: cerrado al cargar (`aria-expanded="false"`, `inert`), se abre con el botón (foco en el primer
//   enlace), Escape lo cierra y devuelve el foco al botón; el aspa también; ids únicos por elemento;
// - hora del mensaje pintada por el script; enlaces de contacto normales; foco visible de 2 px con 2 px de separación;
//   botones de 24 × 24 px como mínimo;
// - barra: «Pausar» y «Reanudar» se alternan con el foco en el que sirve; la cinta se pausa con el foco dentro; cerrar la
//   oculta; con movimiento reducido no hay animación;
// - cupón: copiar cambia al mensaje de éxito y lo anuncia en la región de estado;
// - ninguna petición fuera del archivo y ningún error de JavaScript.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-floating-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los botones flotantes (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-floating.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
try {
  for (const reduced of [false, true]) {
    const page = await browser.newPage();
    const errors = [];
    const outside = [];
    page.on('pageerror', (e) => errors.push(String(e.message || e)));
    await page.setRequestInterception(true);
    page.on('request', (r) => { const u = r.url(); if (!u.startsWith('file:') && !u.startsWith('data:')) outside.push(u); r.continue(); });
    await page.setViewport({ width: 1280, height: 900 });
    if (reduced) await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
    await page.goto(fixture, { waitUntil: 'load' });
    await sleep(300);
    const tag = reduced ? ' (movimiento reducido)' : '';

    // Botones de contacto.
    for (const p of ['1', '4', '8']) {
      const root = `#case-digitalisimo-contact-buttons-${p}`;
      const state = () => page.evaluate((r) => {
        const el = document.querySelector(r);
        const b = el.querySelector('.digi-cb__chat-button');
        const w = el.querySelector('.digi-cb__content-wrapper');
        return { expanded: b.getAttribute('aria-expanded'), controls: b.getAttribute('aria-controls'), id: w.id, hidden: w.classList.contains('hidden'), inert: w.inert, display: getComputedStyle(w).display, active: document.activeElement && (document.activeElement.className.baseVal ?? document.activeElement.className), inside: w.contains(document.activeElement) };
      }, root);
      let s = await state();
      ok(s.expanded === 'false' && s.hidden && s.inert && s.controls === s.id && /^digi-cb-w\d-panel$/.test(s.id), `presentación ${p}${tag}: panel cerrado al cargar, inerte, con id propio y el botón lo nombra.`);
      await page.click(`${root} .digi-cb__chat-button`);
      await sleep(300);
      s = await state();
      ok(s.expanded === 'true' && !s.hidden && !s.inert && s.inside, `presentación ${p}${tag}: el botón abre el panel y lleva el foco dentro (${s.active}).`);
      await page.keyboard.press('Escape');
      await sleep(2300);
      s = await state();
      ok(s.expanded === 'false' && s.hidden && /digi-cb__chat-button/.test(s.active), `presentación ${p}${tag}: Escape cierra el panel y devuelve el foco al botón (${s.display}, ${s.active}).`);
      if (p !== '4') {
        await page.click(`${root} .digi-cb__chat-button`);
        await sleep(300);
        await page.click(`${root} .digi-cb__close-button`);
        await sleep(2300);
        s = await state();
        ok(s.expanded === 'false' && s.hidden && /digi-cb__chat-button/.test(s.active), `presentación ${p}${tag}: el aspa cierra el panel y devuelve el foco.`);
      }
      const size = await page.evaluate((r) => { const b = document.querySelector(r + ' .digi-cb__chat-button').getBoundingClientRect(); return [b.width, b.height]; }, root);
      ok(size[0] >= 24 && size[1] >= 24, `presentación ${p}${tag}: botón de 24 × 24 px como mínimo (${size}).`);
    }
    const time = await page.$eval('#case-digitalisimo-contact-buttons-1 .digi-cb__message-bubble-time', (e) => e.textContent.trim());
    ok(/\d/.test(time), `presentación 1${tag}: el script pinta la hora del mensaje (${time}).`);
    // Foco visible con teclado.
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
    await page.focus('#case-digitalisimo-contact-buttons-1 .digi-cb__chat-button');
    await page.keyboard.press('Tab');
    await page.keyboard.down('Shift'); await page.keyboard.press('Tab'); await page.keyboard.up('Shift');
    await sleep(500); // las transiciones de PRO (`all .3s`) también animan el contorno
    const outline = await page.evaluate(() => { const cs = getComputedStyle(document.activeElement); return [cs.outlineStyle, cs.outlineWidth, cs.outlineOffset, document.activeElement.className, document.activeElement.closest('.digi-cb') ? 'dentro' : 'fuera']; });
    ok(outline[0] !== 'none' && outline[1] === '2px' && outline[2] === '2px', `foco visible de 2 px con 2 px de separación${tag} (${outline}).`);

    // Barra flotante 2: pausa y cierre.
    const bar = '#case-digitalisimo-floating-bar-2';
    const animated = await page.$eval(`${bar} .digi-fbar__headlines`, (e) => e.getAttribute('data-animated'));
    ok(reduced ? animated !== 'true' : animated === 'true', `cinta${tag}: ${reduced ? 'quieta con movimiento reducido' : 'animada'} (${animated}).`);
    const clones = await page.$$eval(`${bar} .digi-fbar__headline--clone, ${bar} .digi-fbar__headlines-inner--clone`, (l) => l.every((c) => c.inert && c.getAttribute('aria-hidden') === 'true'));
    ok(clones, `cinta${tag}: las copias son inertes y ocultas a los lectores de pantalla.`);
    await page.click(`${bar} .digi-fbar__pause-button`);
    await sleep(100);
    let b = await page.evaluate((r) => { const el = document.querySelector(r); return { paused: el.querySelector('.digi-fbar__headlines').classList.contains('is-paused'), pause: el.querySelector('.digi-fbar__pause-button').hidden, play: el.querySelector('.digi-fbar__play-button').hidden, focus: document.activeElement.className }; }, bar);
    ok(b.paused && b.pause && !b.play && /play-button/.test(b.focus), `cinta${tag}: «Pausar» la detiene y el foco pasa a «Reanudar».`);
    await page.keyboard.press('Enter');
    await sleep(100);
    b = await page.evaluate((r) => { const el = document.querySelector(r); return { paused: el.querySelector('.digi-fbar__headlines').classList.contains('is-paused'), focus: document.activeElement.className }; }, bar);
    ok(!b.paused && /pause-button/.test(b.focus), `cinta${tag}: «Reanudar» la sigue y el foco vuelve a «Pausar».`);
    await page.click(`${bar} .digi-fbar__close-button`);
    ok(await page.$eval(`${bar} .digi-fbar`, (e) => e.classList.contains('is-hidden') && getComputedStyle(e).display === 'none'), `barra${tag}: el botón de cierre la oculta.`);
    const name = await page.$eval(`${bar} .digi-fbar`, (e) => [e.getAttribute('role'), e.getAttribute('aria-label')]);
    ok(name[0] === 'region' && name[1], `barra${tag}: región con nombre (${name}).`);

    // Barra flotante 3: cupón.
    const coupon = '#case-digitalisimo-floating-bar-3';
    await page.evaluate(() => { try { Object.defineProperty(navigator, 'clipboard', { value: { writeText: (t) => { window.copied = t; return Promise.resolve(); } } }); } catch (e) { /* sin portapapeles */ } });
    await page.click(`${coupon} .digi-fbar__coupon-button`);
    await sleep(150);
    const c = await page.evaluate((r) => { const el = document.querySelector(r); return { code: el.querySelector('.digi-fbar__coupon-code').classList.contains('is-hidden'), done: el.querySelector('.digi-fbar__coupon-success').classList.contains('is-hidden'), status: el.querySelector('.digi-fbar__status').textContent, copied: window.copied, entry: el.querySelector('.digi-fbar__coupon-button').classList.contains('has-entrance-animation') }; }, coupon);
    ok(c.code && !c.done && c.status && c.copied === 'NEW10', `cupón${tag}: copia el código, muestra el mensaje y lo anuncia (${JSON.stringify(c)}).`);
    await page.keyboard.press('Escape');
    ok(await page.$eval(`${coupon} .digi-fbar`, (e) => e.classList.contains('is-hidden')), `cupón${tag}: Escape cierra la barra con el foco dentro.`);

    ok(!errors.length, `sin errores de JavaScript${tag}: ${errors.join(' | ')}`);
    ok(!outside.length, `ninguna petición fuera del archivo${tag}: ${outside.join(' | ')}`);
    await page.close();
  }
} finally {
  await browser.close();
}
console.log(`DIGITALÍSIMO Elements: botones flotantes y barra flotante propios (C8) en Chrome: ${checks} comprobaciones.`);
