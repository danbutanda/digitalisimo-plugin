// Formulario de búsqueda (`digitalisimo-search-form`, C4 frente H de docs/pro/c4.md) en Chrome real (sin interfaz),
// con su hoja y su script tal cual y el marcado que pinta el widget (tests/elements-pro-search-form.php comprueba ese
// marcado contra el servidor):
// - pantalla completa: el botón abre el <dialog> modal (capa superior, aria-expanded, foco en el campo); Tab no sale del
//   diálogo; Escape, el aspa y un clic en la superposición lo cierran y el foco vuelve al botón; un widget que el
//   editor vuelve a pintar sigue funcionando (delegación); movimiento reducido sin animación;
// - foco visible (contorno de 2 px con separación de 2 px) en el conmutador, el aspa y el campo de la clásica, y
//   botones de 24 × 24 px como mínimo; la mínima enfoca el campo al pulsar la lupa sin script;
// - sin errores en la consola.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-search-form-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del formulario de búsqueda (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const base = new URL('../digitalisimo-elements/modules/digitalisimo-theme-elements/assets/', import.meta.url);
const css = readFileSync(new URL('css/search-form.css', base), 'utf8');
const js = readFileSync(new URL('js/search-form.js', base), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const svg = '<svg class="digi-search-form__svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 2h20v20H2z"/></svg>';
const full = id => `<div class="elementor-element digi-search-form--skin-full_screen" data-id="${id}"><search role="search"><form class="digi-search-form" action="about:blank" method="get"><button type="button" class="digi-search-form__toggle" aria-label="Buscar" aria-haspopup="dialog" aria-expanded="false" aria-controls="digi-search-form-${id}-dialog">${svg}</button><dialog class="digi-search-form__container" id="digi-search-form-${id}-dialog" aria-label="Buscar"><label class="digi-search-form__label" for="digi-search-form-${id}">Buscar</label><input id="digi-search-form-${id}" placeholder="Buscar..." class="digi-search-form__input" type="search" name="s" value=""><button type="button" class="digi-search-form__close" aria-label="Cerrar la búsqueda">${svg}</button></dialog></form></search></div>`;
const classic = `<div class="elementor-element digi-search-form--skin-classic digi-search-form--button-type-icon" data-id="c"><search role="search"><form class="digi-search-form" action="about:blank" method="get"><div class="digi-search-form__container"><label class="digi-search-form__label" for="digi-search-form-c">Buscar</label><input id="digi-search-form-c" placeholder="Buscar..." class="digi-search-form__input" type="search" name="s" value=""><button class="digi-search-form__submit" type="submit" aria-label="Buscar">${svg}</button></div></form></search></div>`;
const minimal = `<div class="elementor-element digi-search-form--skin-minimal" data-id="m"><search role="search"><form class="digi-search-form" action="about:blank" method="get"><div class="digi-search-form__container"><label class="digi-search-form__icon" for="digi-search-form-m">${svg}<span class="digi-search-form__label">Buscar</span></label><input id="digi-search-form-m" placeholder="Buscar..." class="digi-search-form__input" type="search" name="s" value=""></div></form></search></div>`;
const html = `<!doctype html><html lang="es"><head><meta charset="utf-8"><style>${css}</style></head><body><a href="#" id="before">antes</a>${classic}${minimal}${full('f')}<div id="slot">${full('g')}</div><a href="#" id="after">después</a><script>${js}</script></body></html>`;

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const errors = [];
async function open({ reduce = false, width = 1280 } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 800 });
  await page.setContent(html, { waitUntil: 'load' });
  return page;
}
const state = (page, id) => page.evaluate(id => {
  const root = document.querySelector(`[data-id="${id}"]`);
  const dialog = root.querySelector('dialog');
  const toggle = root.querySelector('.digi-search-form__toggle');
  const a = document.activeElement;
  return {
    open: dialog.open,
    modal: dialog.matches(':modal'),
    expanded: toggle.getAttribute('aria-expanded'),
    focus: a === toggle ? 'toggle' : a && a.classList.contains('digi-search-form__input') && root.contains(a) ? 'input' : a && a.classList.contains('digi-search-form__close') && root.contains(a) ? 'close' : a ? a.id || a.tagName : null,
    visible: dialog.getBoundingClientRect().width > 0,
  };
}, id);

const page = await open();
// Cerrado: sólo el botón, de 33 × 33 px, con foco visible.
let s = await state(page, 'f');
ok(!s.open && !s.visible && s.expanded === 'false', 'Pantalla completa cerrada: el diálogo no se ve.');
const box = await page.$eval('[data-id="f"] .digi-search-form__toggle', b => { const r = b.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height), getComputedStyle(b).backgroundColor, getComputedStyle(b).borderRadius]; });
ok(box[0] === 33 && box[1] === 33 && box[2] === 'rgba(0, 0, 0, 0.05)' && box[3] === '3px', 'El conmutador mide 33 × 33 px con el fondo y el radio de PRO: ' + box);
await page.focus('#before');
for (let i = 0; i < 6; i++) {
  await page.keyboard.press('Tab');
  if ((await state(page, 'f')).focus === 'toggle') break;
}
await sleep(300); // El conmutador tiene la transición de PRO (.2s en todo).
const ring = await page.$eval('[data-id="f"] .digi-search-form__toggle', b => [getComputedStyle(b).outlineStyle, getComputedStyle(b).outlineWidth, getComputedStyle(b).outlineOffset]);
ok(ring.join() === 'solid,2px,2px', 'Foco visible del conmutador: ' + ring);
// Abrir con el teclado (Intro en el botón).
await page.keyboard.press('Enter');
await sleep(400);
s = await state(page, 'f');
ok(s.open && s.modal && s.visible && s.expanded === 'true' && s.focus === 'input', 'Intro abre el diálogo modal y enfoca el campo: ' + JSON.stringify(s));
const overlay = await page.$eval('[data-id="f"] dialog', d => { const r = d.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height), getComputedStyle(d).backgroundColor]; });
ok(overlay[0] === 1280 && overlay[1] === 800 && overlay[2] === 'rgba(0, 0, 0, 0.8)', 'Superposición a pantalla completa con el color de PRO: ' + overlay);
const input = await page.$eval('[data-id="f"] .digi-search-form__input', i => [getComputedStyle(i).fontSize, getComputedStyle(i).color, getComputedStyle(i).borderBottomWidth, getComputedStyle(i).textAlign, getComputedStyle(i).outlineStyle]);
ok(input.join() === '50px,rgb(255, 255, 255),1px,center,solid', 'Campo de pantalla completa como PRO (50 px, blanco, línea inferior, centrado) y con foco visible: ' + input);
// Tab no sale del diálogo.
await page.keyboard.press('Tab');
s = await state(page, 'f');
ok(s.focus === 'close', 'Tab pasa del campo al aspa: ' + s.focus);
const close = await page.$eval('[data-id="f"] .digi-search-form__close', b => { const r = b.getBoundingClientRect(); return [r.width >= 24 && r.height >= 24, getComputedStyle(b).outlineWidth, getComputedStyle(b).outlineOffset]; });
ok(close.join() === 'true,2px,2px', 'Aspa de 24 × 24 px como mínimo con foco visible: ' + close);
await page.keyboard.press('Tab');
s = await state(page, 'f');
ok(s.open && !['after', 'before', 'toggle'].includes(s.focus) && !(await page.evaluate(() => document.querySelector('[data-id="c"]').contains(document.activeElement))), 'Tab no lleva el foco al resto de la página mientras el diálogo está abierto: ' + s.focus);
await page.focus('[data-id="f"] .digi-search-form__input');
// Escape cierra y devuelve el foco.
await page.keyboard.press('Escape');
await sleep(200);
s = await state(page, 'f');
ok(!s.open && s.expanded === 'false' && s.focus === 'toggle', 'Escape cierra y el foco vuelve al botón: ' + JSON.stringify(s));
// El aspa.
await page.click('[data-id="f"] .digi-search-form__toggle');
await sleep(400);
ok((await state(page, 'f')).open, 'Un clic en el botón abre el diálogo.');
await page.click('[data-id="f"] .digi-search-form__close');
await sleep(200);
s = await state(page, 'f');
ok(!s.open && s.focus === 'toggle' && s.expanded === 'false', 'El aspa cierra y el foco vuelve al botón.');
// Clic en la superposición (fuera del campo).
await page.click('[data-id="f"] .digi-search-form__toggle');
await sleep(400);
await page.mouse.click(20, 400);
await sleep(200);
s = await state(page, 'f');
ok(!s.open && s.focus === 'toggle', 'Un clic en la superposición cierra el diálogo.');
await page.click('[data-id="f"] .digi-search-form__toggle');
await sleep(400);
await page.click('[data-id="f"] .digi-search-form__input');
await sleep(100);
ok((await state(page, 'f')).open, 'Un clic en el campo no cierra el diálogo.');
await page.keyboard.press('Escape');
// Un widget vuelto a pintar (editor) sigue funcionando sin reiniciar nada.
await page.evaluate(html => { document.querySelector('#slot').innerHTML = html; }, full('g'));
await page.click('[data-id="g"] .digi-search-form__toggle');
await sleep(400);
s = await state(page, 'g');
ok(s.open && s.focus === 'input' && !(await state(page, 'f')).open, 'El widget vuelto a pintar abre su propio diálogo.');
await page.keyboard.press('Escape');
await sleep(200);
ok((await state(page, 'g')).focus === 'toggle', 'Y devuelve el foco a su botón.');
// Clásica: foco visible en el contenedor al llegar al campo con el teclado; botón de 50 px.
await page.focus('#before');
await page.keyboard.press('Tab');
await sleep(300);
const cring = await page.evaluate(() => {
  const c = document.querySelector('[data-id="c"] .digi-search-form__container');
  return [document.activeElement.id, getComputedStyle(c).outlineStyle, getComputedStyle(c).outlineWidth, getComputedStyle(c).outlineOffset];
});
ok(cring.join() === 'digi-search-form-c,solid,2px,2px', 'Clásica: el contenedor muestra el foco del campo: ' + cring);
const submit = await page.$eval('[data-id="c"] .digi-search-form__submit', b => { const r = b.getBoundingClientRect(); return [Math.round(r.height), getComputedStyle(b).backgroundColor, getComputedStyle(b).color]; });
ok(submit[0] === 50 && submit[1] === 'rgb(105, 114, 125)' && submit[2] === 'rgb(255, 255, 255)', 'Botón de envío de 50 px con los colores de PRO: ' + submit);
// Mínima: la lupa es la etiqueta, enfoca el campo sin script.
await page.click('[data-id="m"] .digi-search-form__icon');
ok(await page.evaluate(() => document.activeElement.id === 'digi-search-form-m'), 'Mínima: pulsar la lupa enfoca el campo.');
const name = await page.evaluate(() => { const i = document.querySelector('#digi-search-form-m'); return [...i.labels].map(l => l.textContent).join('|'); });
ok(name === 'Buscar', 'Mínima: una sola etiqueta, «Buscar»: ' + name);
await page.close();

// Movimiento reducido: sin animación de apertura.
const quiet = await open({ reduce: true });
await quiet.click('[data-id="f"] .digi-search-form__toggle');
const anim = await quiet.$eval('[data-id="f"] dialog', d => getComputedStyle(d).animationName);
ok(anim === 'none', 'Con movimiento reducido el diálogo se abre sin animación: ' + anim);
await quiet.close();

await browser.close();
ok(errors.length === 0, 'Sin errores en la consola: ' + errors.join(' | '));
console.log(`DIGITALÍSIMO Elements: Formulario de búsqueda en Chrome (${checks} comprobaciones) validado.`);
