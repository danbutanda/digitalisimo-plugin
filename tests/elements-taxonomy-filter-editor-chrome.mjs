// Filtro de taxonomías propio en el editor (C5, docs/pro/c5.md, «Correcciones de fidelidad»), en Chrome real (sin
// interfaz): como PRO (`prepareLoopUpdateRequestData()` y `refreshLoopWidget()`), pulsar un término en la vista previa
// repinta la Cuadrícula de bucle con su modelo del editor por la REST de PRO, sin tocar la URL:
// - `taxonomy-filter.js` (vista previa) marca el botón y pasa el filtro a `taxonomy-filter-editor.js` (editor);
// - una sola petición POST a `<rest>elementor-pro/v1/refresh-loop` con el nonce de la configuración del editor
//   (`elementorPro.config.loopFilter.nonce`), `post_id` del documento, `widget_id`, los términos de todos los filtros
//   de ese bucle (`widget_filters.taxonomy`), `pagination_base_url`, el modelo del editor sin `default`/`editSettings`
//   y `is_edit_mode`;
// - la respuesta sustituye sólo el `.elementor-widget-container` de la Cuadrícula (las asas del editor siguen) y corre
//   los manejadores; «Todos» vuelve a pedirla sin filtros.
// Con el código anterior, pulsar en el editor sólo marcaba el botón. El editor y la vista previa son aquí la misma
// ventana (`window.top`), con dobles de `$e`, `elementorCommon`, `elementorPro` y `elementorFrontend`.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-taxonomy-filter-editor-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const npx = '/home/digitalisimo/.npm/_npx';
const found = existsSync(npx) ? readdirSync(npx).map((d) => `${npx}/${d}/node_modules/puppeteer-core`).find((d) => existsSync(d + '/package.json')) : '';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : found;
if (!existsSync(chrome) || !pptrDir || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del Filtro de taxonomías en el editor (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const ORIGIN = 'https://editor.test';
const js = (f) => readFileSync(new URL(`../digitalisimo-elements/modules/digitalisimo-loop/assets/js/${f}`, import.meta.url), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const buttons = ['__all', 'guias', 'casos'].map((t, i) => `<button type="button" class="digi-tfilter__item" data-filter="${t}" aria-pressed="${0 === i}">${t}</button>`).join('');
const page = `<!doctype html><html><head><meta charset="utf-8"></head><body><div data-elementor-id="77">
<div class="elementor-element elementor-element-g000001 elementor-widget elementor-widget-digitalisimo-loop-grid" data-id="g000001"><div class="elementor-editor-element-settings">asas</div><div class="elementor-widget-container"><div class="e-loop-item">Viejo</div></div></div>
<div class="elementor-element elementor-element-ftax001 elementor-widget-digitalisimo-taxonomy-filter" data-id="ftax001"><search class="digi-tfilter" role="search" data-loop="g000001" data-taxonomy="category" data-join="DISABLED" data-base-url="${ORIGIN}/catalogo/" data-page-num="1">${buttons}</search></div>
</div><script src="/a/taxonomy-filter-editor.js"></script><script src="/a/taxonomy-filter.js"></script></body></html>`;

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const errors = [];
const posts = [];
const tab = await browser.newPage();
tab.on('pageerror', (e) => errors.push(e.message));
await tab.setRequestInterception(true);
tab.on('request', (r) => {
  const u = new URL(r.url());
  if (u.origin !== ORIGIN) return r.abort();
  if ('/' === u.pathname) return r.respond({ status: 200, contentType: 'text/html', body: page });
  const m = u.pathname.match(/^\/a\/(.+\.js)$/);
  if (m) return r.respond({ status: 200, contentType: 'text/javascript', body: js(m[1]) });
  if ('/wp-json/elementor-pro/v1/refresh-loop' === u.pathname) {
    const body = JSON.parse(r.postData() || '{}');
    posts.push({ method: r.method(), nonce: r.headers()['x-wp-nonce'], body });
    const terms = ((body.widget_filters || {}).taxonomy || {}).category;
    return r.respond({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: `<div class="elementor-widget-container"><div class="e-loop-item">${terms ? terms.terms.join(',') : 'Todas'}</div></div>` }) });
  }
  return r.respond({ status: 404, body: '' });
});
await tab.evaluateOnNewDocument(() => {
  window.__ready = [];
  window.elementorFrontend = { isEditMode: () => true, elementsHandler: { runReadyTrigger: (el) => window.__ready.push(el.className.split(' ')[1] || el.className) }, hooks: { addAction() {} } };
  window.elementorCommon = { config: { urls: { rest: 'https://editor.test/wp-json/' } } };
  window.elementorPro = { config: { loopFilter: { nonce: 'n0nce', mainQueryPostType: 'post' } } };
  window.elementor = { config: { document: { id: 77 } } };
  window.$e = { components: { get: () => ({ utils: { findContainerById: (id) => ({ model: { toJSON: (o) => ({ id, elType: 'widget', widgetType: 'digitalisimo-loop-grid', settings: { template_id: '5' }, removed: o && o.remove }) } }) } }) } };
});
await tab.goto(ORIGIN + '/', { waitUntil: 'load' });
await sleep(100);
const href = await tab.evaluate(() => location.href);

await tab.click('[data-filter="guias"]');
await sleep(400);
let state = await tab.evaluate(() => ({ pressed: [...document.querySelectorAll('.digi-tfilter__item')].map((b) => b.getAttribute('aria-pressed')).join(','), items: document.querySelector('.elementor-element-g000001 .elementor-widget-container').textContent, handles: !!document.querySelector('.elementor-element-g000001 > .elementor-editor-element-settings'), containers: document.querySelectorAll('.elementor-element-g000001 .elementor-widget-container').length, ready: window.__ready.slice(), href: location.href, loading: document.querySelector('.elementor-element-g000001').classList.contains('e-loading') }));
ok('false,true,false' === state.pressed, 'El botón pulsado se marca: ' + state.pressed);
ok(1 === posts.length && 'POST' === posts[0].method && 'n0nce' === posts[0].nonce, 'Una petición POST a refresh-loop con el nonce del editor: ' + JSON.stringify(posts.map((p) => [p.method, p.nonce])));
const b = posts[0].body;
ok(77 === b.post_id && 'g000001' === b.widget_id && true === b.is_edit_mode && `${ORIGIN}/catalogo/` === b.pagination_base_url, 'Documento, Cuadrícula, modo edición y dirección base, como PRO: ' + JSON.stringify(b));
ok(JSON.stringify({ taxonomy: { category: { terms: ['guias'] } } }) === JSON.stringify(b.widget_filters), 'Los términos del filtro: ' + JSON.stringify(b.widget_filters));
ok('g000001' === b.widget_model.id && JSON.stringify(['default', 'editSettings', 'defaultEditSettings']) === JSON.stringify(b.widget_model.removed), 'El modelo del editor de la Cuadrícula, sin default ni editSettings.');
ok('guias' === state.items && 1 === state.containers && state.handles && !state.loading, 'La respuesta sustituye sólo el contenedor del widget (las asas del editor siguen): ' + JSON.stringify(state));
ok(state.ready.includes('elementor-element-g000001') && href === state.href, 'Corre los manejadores de la Cuadrícula y no toca la URL: ' + JSON.stringify(state.ready));

await tab.click('[data-filter="__all"]');
await sleep(400);
ok(2 === posts.length && JSON.stringify({}) === JSON.stringify(posts[1].body.widget_filters) && 'Todas' === (await tab.$eval('.elementor-element-g000001 .elementor-widget-container', (e) => e.textContent)), '«Todos» la vuelve a pedir sin filtros.');
await browser.close();
ok(!errors.length, 'Sin errores de JavaScript: ' + errors.join(' · '));
console.log(`DIGITALÍSIMO Elements: Filtro de taxonomías en el editor, repintado por la REST de PRO (${checks} comprobaciones) validado.`);
