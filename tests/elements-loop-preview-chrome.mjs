// Cuadrícula y Carrusel de bucle propios en la vista previa del editor (C5, docs/pro/c5.md, «Correcciones de
// fidelidad»), en Chrome real (sin interfaz), con `modules/digitalisimo-loop/assets/js/loop-preview.js`: como el
// manejador de PRO en modo edición (`attachEditDocumentHandle()`, `onInPlaceEditTemplate()` y `handleCTA()`):
// - con plantilla, el asa `elementor-document-handle` («Editar plantilla») sobre el primer Elemento de bucle de esa
//   plantilla (en el carrusel, sobre la pista), una sola vez aunque el elemento vuelva a estar listo; al pulsarla,
//   `editor/documents/switch` con el id de la plantilla y el selector de PRO, con los estados del panel; al cerrar, el
//   widget queda editable en su sitio y pierde los estilos del bucle, como PRO;
// - sin plantilla, el contenedor de PRO (`e-loop-empty-view__wrapper`) recibe en un shadow root la hoja y la llamada de
//   `elementorPro.modules.loopBuilder` (con el nombre de la plantilla de PRO de cada widget), y su botón crea la plantilla.
// Con el código anterior no había asa y, sin plantilla, sólo un aviso de texto. El editor y la vista previa son aquí la
// misma ventana, con dobles de `$e`, `elementorPro` y `elementorFrontend`.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. Uso: timeout 120 node tests/elements-loop-preview-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const npx = '/home/digitalisimo/.npm/_npx';
const found = existsSync(npx) ? readdirSync(npx).map((d) => `${npx}/${d}/node_modules/puppeteer-core`).find((d) => existsSync(d + '/package.json')) : '';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : found;
if (!existsSync(chrome) || !pptrDir || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los bucles en la vista previa (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const ORIGIN = 'https://vista.test';
const script = readFileSync(new URL('../digitalisimo-elements/modules/digitalisimo-loop/assets/js/loop-preview.js', import.meta.url), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const item = (tid, n) => `<div data-elementor-type="loop-item" data-elementor-id="${tid}" class="elementor elementor-${tid} e-loop-item post-${n}"><p>Entrada ${n}</p></div>`;
const widget = (id, type, inner) => `<div class="elementor-element elementor-element-${id} elementor-widget elementor-widget-${type}" data-id="${id}" data-element_type="widget" data-widget_type="${type}.default"><div class="elementor-widget-container">${inner}</div></div>`;
const page = `<!doctype html><html><head><meta charset="utf-8"></head><body><div data-elementor-id="77" class="elementor elementor-77">
${widget('g000001', 'digitalisimo-loop-grid', `<style id="loop-5">.x{}</style><style id="loop-dynamic-5">.y{}</style><div class="digi-loop-grid__items">${item(5, 1)}${item(5, 2)}</div>`)}
${widget('g000002', 'digitalisimo-loop-grid', '<div class="e-loop-empty-view__wrapper"></div>')}
${widget('c000001', 'digitalisimo-loop-carousel', `<div class="digi-crs"><div class="digi-crs__track">${item(9, 1)}${item(9, 2)}</div></div>`)}
${widget('c000002', 'digitalisimo-loop-carousel', '<div class="e-loop-empty-view__wrapper"></div>')}
</div><script src="/a/loop-preview.js"></script></body></html>`;

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const errors = [];
const tab = await browser.newPage();
tab.on('pageerror', (e) => errors.push(e.message));
await tab.setRequestInterception(true);
tab.on('request', (r) => {
  const u = new URL(r.url());
  if (u.origin !== ORIGIN) return r.abort();
  if ('/' === u.pathname) return r.respond({ status: 200, contentType: 'text/html', body: page });
  if ('/a/loop-preview.js' === u.pathname) return r.respond({ status: 200, contentType: 'text/javascript', body: script });
  return r.respond({ status: 404, body: '' });
});
await tab.evaluateOnNewDocument(() => {
  const SETTINGS = { g000001: { template_id: '5', edit_handle_selector: '[data-elementor-type="loop-item"]' }, g000002: {}, c000001: { template_id: '9', edit_handle_selector: '.digi-crs__track' }, c000002: {} };
  window.__calls = [];
  window.__hooks = {};
  window.elementorFrontend = { isEditMode: () => true, hooks: { addAction: (n, f) => { window.__hooks[n] = f; } } };
  window.$e = {
    components: { get: () => ({ utils: { findContainerById: (id) => ({ settings: { get: (k) => (SETTINGS[id] || {})[k] } }) } }) },
    run: (cmd, args) => { window.__calls.push(['run', cmd, args.id, args.selector]); window.__onClose = args.onClose; return Promise.resolve(); },
    internal: (cmd) => window.__calls.push(['internal', cmd]),
  };
  window.elementorPro = { modules: { loopBuilder: {
    getCtaStyles: () => { const s = document.createElement('style'); s.textContent = '.e-loop-empty-view__box{opacity:1}'; return s; },
    getCtaContent: (name) => { const c = document.createElement('div'); c.className = 'e-loop-empty-view__container elementor-grid ' + name; c.innerHTML = '<div class="e-loop-empty-view__box"><a href="#" class="e-loop-empty-view__box-cta">Create a template</a></div>'; return c; },
    createTemplate: () => window.__calls.push(['createTemplate']),
  } } };
});
await tab.goto(ORIGIN + '/', { waitUntil: 'load' });
await tab.evaluate(() => window.dispatchEvent(new Event('elementor/frontend/init')));
await sleep(100);

// Con plantilla: el asa sobre el primer elemento de esa plantilla (cuadrícula) y sobre la pista (carrusel).
let s = await tab.evaluate(() => ({
  hooks: Object.keys(window.__hooks).sort(),
  grid: [...document.querySelectorAll('.elementor-element-g000001 .e-loop-item')].map((e) => !!e.querySelector(':scope > .elementor-document-handle')),
  title: document.querySelector('.elementor-element-g000001 .elementor-document-handle__title')?.textContent,
  icon: !!document.querySelector('.elementor-element-g000001 .elementor-document-handle > .elementor-document-handle__inner > i.eicon-edit'),
  editable: document.querySelector('.elementor-element-g000001 .elementor-5')?.dataset.editableElementorDocument,
  carousel: !!document.querySelector('.elementor-element-c000001 .digi-crs__track > .elementor-document-handle:first-child'),
}));
ok(JSON.stringify(s.hooks) === JSON.stringify(['frontend/element_ready/digitalisimo-loop-carousel.default', 'frontend/element_ready/digitalisimo-loop-grid.default']), 'Se engancha a element_ready de la Cuadrícula y el Carrusel propios: ' + JSON.stringify(s.hooks));
ok('true,false' === s.grid.join() && 'Editar plantilla' === s.title && s.icon && '5' === s.editable, 'Cuadrícula: el asa de PRO sobre el primer Elemento de bucle: ' + JSON.stringify(s));
ok(s.carousel, 'Carrusel: el asa sobre la pista (su selector de edición).');
// Repintado: element_ready otra vez no duplica el asa.
await tab.evaluate(() => window.__hooks['frontend/element_ready/digitalisimo-loop-grid.default']([document.querySelector('.elementor-element-g000001')]));
ok(1 === (await tab.$$eval('.elementor-element-g000001 .elementor-document-handle', (l) => l.length)), 'Un element_ready repetido no duplica el asa.');
// Pulsar el asa: la plantilla se edita en su sitio, como PRO.
await tab.click('.elementor-element-g000001 .elementor-document-handle');
await sleep(100);
const calls = await tab.evaluate(() => window.__calls);
ok(JSON.stringify(calls) === JSON.stringify([['internal', 'panel/state-loading'], ['run', 'editor/documents/switch', 5, '.elementor-element-g000001 .elementor-5'], ['internal', 'panel/state-ready']]), 'El asa abre la plantilla con editor/documents/switch y el selector de PRO: ' + JSON.stringify(calls));
s = await tab.evaluate(() => { window.__onClose(); const el = document.querySelector('.elementor-element-g000001'); return { cls: el.classList.contains('elementor-in-place-template-editable'), styles: el.querySelectorAll('style').length }; });
ok(s.cls && 0 === s.styles, 'Al editarla en su sitio, el widget queda editable y sin los estilos del bucle (como onInPlaceEditTemplate de PRO).');

// Sin plantilla: la llamada de PRO en un shadow root, con el nombre de su plantilla, y su botón crea la plantilla.
s = await tab.evaluate(() => ['g000002', 'c000002'].map((id) => { const w = document.querySelector(`.elementor-element-${id} .e-loop-empty-view__wrapper`); const r = w && w.shadowRoot; return r ? [r.querySelector('style') ? 1 : 0, r.querySelector('.e-loop-empty-view__container').className] : null; }));
ok(JSON.stringify(s) === JSON.stringify([[1, 'e-loop-empty-view__container elementor-grid loop-grid'], [1, 'e-loop-empty-view__container elementor-grid loop-carousel']]), 'Sin plantilla, la llamada de PRO con su hoja en un shadow root: ' + JSON.stringify(s));
await tab.evaluate(() => { window.__calls = []; document.querySelector('.elementor-element-g000002 .e-loop-empty-view__wrapper').shadowRoot.querySelector('.e-loop-empty-view__box-cta').click(); });
ok(JSON.stringify(await tab.evaluate(() => window.__calls)) === JSON.stringify([['createTemplate']]), 'El botón de la llamada crea la plantilla con el módulo de PRO.');
await browser.close();
ok(!errors.length, 'Sin errores de JavaScript: ' + errors.join(' · '));
console.log(`DIGITALÍSIMO Elements: bucles propios en la vista previa del editor (asa de la plantilla y llamada de PRO, ${checks} comprobaciones) validados.`);
