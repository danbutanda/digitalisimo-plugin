// Panel fuera de lienzo y Búsqueda propios (C3, frente F de docs/pro/c3.md) en Chrome real (sin interfaz), con los
// scripts del widget (`assets/js/nested-off-canvas.js`, `assets/js/search.js`) y la hoja, sobre el marcado que pinta el
// servidor (lo comprueban tests/elements-pro-off-canvas.php y tests/elements-pro-search.php) y un `elementorFrontend` de
// prueba que arranca como el del núcleo (`elementor/frontend/init`, `components:init`, acciones de URL en el clic):
// - panel: cerrado e inerte al cargar, disparadores con aria-controls/aria-expanded; abre con el enlace de la etiqueta
//   dinámica (codificado) y con un enlace de acción escrito a mano (alternar), lleva el foco al contenido con el ratón
//   y al primer control con el teclado (como PRO: un campo de texto no se enfoca con el ratón), lo atrapa (Tab y
//   Mayús+Tab, también desde el contenido), el contenido no se acota a la ventana, Escape y la capa cierran y el foco vuelve al disparador; «no cerrar» con Escape o con la
//   capa; bloqueo del desplazamiento; animaciones de entrada y salida del núcleo (y ninguna con movimiento reducido);
//   manejadores de los hijos una sola vez; no pisa una acción `off_canvas:*` ya registrada; editor (sin foco atrapado);
// - búsqueda: relleno del campo según los iconos, «Borrar» (aparece con texto, vacía, devuelve el foco), resultados en
//   vivo (mínimo de caracteres, espera de 300 ms, cuerpo de la petición, indicador de carga, estado anunciado,
//   manejadores de los resultados), paginación sin recargar, flechas, Escape, clic fuera, foco que los vuelve a mostrar,
//   sin resultados (mensaje y vacío), Intro y respuestas atrasadas descartadas.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-pro-off-canvas-search-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readdirSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const npx = '/home/digitalisimo/.npm/_npx';
const found = existsSync(npx) ? readdirSync(npx).map(d => `${npx}/${d}/node_modules/puppeteer-core`).find(d => existsSync(d + '/package.json')) : '';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : found;
if (!existsSync(chrome) || !pptrDir || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del panel y la búsqueda (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const dir = new URL('../digitalisimo-elements/modules/digitalisimo-navigation/assets/', import.meta.url);
const read = f => readFileSync(new URL(f, dir), 'utf8');
const files = { ocJs: read('js/nested-off-canvas.js'), ocCss: read('css/nested-off-canvas.css'), seJs: read('js/search.js'), seCss: read('css/search.css') };
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));
const b64 = o => Buffer.from(JSON.stringify(o)).toString('base64');
const ORIGIN = 'http://digi.test';

// `elementorFrontend` de prueba: lo que usan los dos scripts, con el orden de arranque del núcleo (frontend.js).
const FRONTEND = `
window.__ready = []; window.__fetches = [];
(() => {
  const events = {};
  const F = window.elementorFrontend = {
    edit: false,
    isEditMode() { return this.edit; },
    on(n, f) { (events[n] = events[n] || []).push(f); },
    trigger(n) { (events[n] || []).forEach(f => f()); },
    getCurrentDeviceMode: () => innerWidth < 768 ? 'mobile' : 'desktop',
  };
  F.init = () => {
    const hooks = {};
    F.hooks = { addAction(n, f) { (hooks[n] = hooks[n] || []).push(f); }, doAction(n, ...a) { (hooks[n] || []).forEach(f => f(...a)); } };
    F.breakpoints = {};
    window.dispatchEvent(new CustomEvent('elementor/frontend/init'));
    const actions = { lightbox() {} };
    F.utils = {
      controls: { getResponsiveControlValue(s, k) { const d = F.getCurrentDeviceMode(); return (d !== 'desktop' && s[k + '_' + d]) || s[k] || ''; } },
      urlActions: {
        actions,
        addAction(n, f) { actions[n] = f; },
        runAction(url, ev) {
          url = decodeURIComponent(decodeURI(url));
          const m = url.match(/action=(.+?)&/); if (!m || !actions[m[1]]) return;
          const s = url.match(/settings=(.+)/); const settings = s ? JSON.parse(atob(s[1])) : {};
          settings.previousEvent = ev; actions[m[1]](settings);
        },
        createActionHash(a, s) { return encodeURIComponent('#elementor-action:action=' + a + '&settings=' + btoa(JSON.stringify(s))); },
      },
    };
    F.elementsHandler = { runReadyTrigger(el) { window.__ready.push(el.dataset.id); } };
    document.addEventListener('click', e => {
      const a = e.target.closest('a[href^="%23elementor-action"], a[href^="#elementor-action"]');
      if (!a) return; e.preventDefault(); F.utils.urlActions.runAction(a.getAttribute('href'), e);
    });
    document.querySelectorAll('.elementor-widget').forEach(el => F.hooks.doAction('frontend/element_ready/' + el.dataset.widget_type, [el]));
    F.trigger('components:init');
  };
})();`;

const panel = (id, cfg, extra = '') => `
<div class="elementor-element elementor-widget elementor-widget-digitalisimo-nested-off-canvas" data-id="${id}" data-widget_type="digitalisimo-nested-off-canvas.default">
 <div id="off-canvas-${id}" class="digi-noc" role="dialog" aria-modal="true" aria-label="Menú ${id}" aria-hidden="true" inert data-delay-child-handlers="true" data-digi-noc='${JSON.stringify(cfg)}'>
  <div class="digi-noc__overlay"></div><div class="digi-noc__main"><div class="digi-noc__content">
   <div class="e-con elementor-element" data-id="${id}c"><h2>Menú</h2><div class="elementor-element" data-id="${id}b"><a href="/contacto/" class="first">Contacto</a></div>${extra}<a href="/tienda/" class="last">Tienda</a></div>
  </div></div>
 </div>
</div>`;
const OC_PAGE = `<!doctype html><html><head><meta charset="utf-8"><style>${files.ocCss}
.elementor-widget-digitalisimo-nested-off-canvas{--digi-noc-duration:.15s}.fadeIn{animation-name:fi}.slideInRight{animation-name:fi}.animated.reverse{animation-direction:reverse;animation-fill-mode:forwards}@keyframes fi{from{opacity:0}to{opacity:1}}
body{margin:0;min-height:3000px}.digi-noc__content a{transition:all .3s}</style></head><body>
<div data-elementor-id="10">
 <p><a id="t1" href="${encodeURIComponent('#elementor-action:action=off_canvas:open&settings=' + b64({ id: 'oc00001', displayMode: 'open' }))}">Abrir</a>
 <a id="t2" href="#elementor-action:action=off_canvas:toggle&settings=${b64({ id: 'oc00001', displayMode: 'toggle' })}">Alternar</a>
 <a id="t3" href="#elementor-action:action=off_canvas:open&settings=${b64({ id: 'oc00002', displayMode: 'open' })}">Abrir 2</a>
 <a id="t4" href="#elementor-action:action=off_canvas:open&settings=${b64({ id: 'oc00003', displayMode: 'open' })}">Abrir 3</a>
 <a id="t5" href="/otra/">Otra</a></p>
 ${panel('oc00001', { in: {}, out: {}, o: true, e: true, s: false })}
 ${panel('oc00002', { in: { '': 'fadeIn', mobile: 'slideInRight' }, out: { '': 'fadeIn' }, o: false, e: false, s: true })}
 ${panel('oc00003', { in: {}, out: {}, o: true, e: true, s: false }, '<span>sin controles</span>').replace(/<a href="\/(contacto|tienda)\/"[^>]*>[^<]*<\/a>/g, '')}
</div>
<script>${FRONTEND}</script><script>__PRE__</script><script>${files.ocJs}</script><script>elementorFrontend.init()</script></body></html>`;

const SEARCH = (id, { live = false, icon = false, loader = true, wide = false } = {}) => `
<div class="elementor-element elementor-widget elementor-widget-digitalisimo-search" data-id="${id}" data-widget_type="digitalisimo-search.default" style="position:relative;width:600px;margin:40px">
<search class="digi-search${icon ? ' digi-search--icon' : ''}" role="search"${live ? ` data-digi-search='${JSON.stringify({ rest: ORIGIN + '/wp-json/digitalisimo-elements/v1/search', min: 3, t: { found: '%d resultados', one: '1 resultado', none: 'No hay resultados', pages: 'Páginas de resultados' } })}'` : ''}>
<form class="digi-search__form" action="${ORIGIN}/" method="get">
<label class="digi-search__label" for="search-${id}"><span class="elementor-screen-only">Buscar</span>${icon ? '<svg viewBox="0 0 10 10" width="20" style="width:20px;height:20px"><rect width="10" height="10"/></svg>' : ''}</label>
<div class="digi-search__field"><input id="search-${id}" class="digi-search__input" type="search" name="s" value="" placeholder="Escribe" autocomplete="off"${live ? ` aria-controls="results-${id}"` : ''}>
<button type="button" class="digi-search__clear digi-search__clear--off" aria-label="Borrar la búsqueda"><svg viewBox="0 0 8 12" style="width:8px;height:12px"><rect width="8" height="12"/></svg></button>
${live && !wide ? `<div id="results-${id}" class="digi-search__results" role="region" aria-label="Resultados de la búsqueda" hidden><div class="digi-search__box"></div>${loader ? '<div class="digi-search__loader" hidden><svg viewBox="0 0 28 28"><circle cx="14" cy="14" r="10"/></svg></div>' : ''}</div><span class="elementor-screen-only" role="status"></span>` : ''}
</div>
<button class="digi-search__submit" type="submit"><span>Buscar</span></button>
<input type="hidden" name="e_search_props" value="${id}-10">
</form></search></div>`;
const SE_PAGE = `<!doctype html><html><head><meta charset="utf-8"><style>${files.seCss} body{margin:0;font:16px sans-serif}</style></head><body>
<div data-elementor-id="10">${SEARCH('se00001', { icon: true })}${SEARCH('se00002', { live: true })}<p id="fuera">Fuera</p></div>
<script>${FRONTEND}</script><script>${files.seJs}</script><script>elementorFrontend.init()</script></body></html>`;

// Resultados de prueba: dos por página, como la plantilla de bucle de PRO (e-loop-item con su elemento).
const item = n => `<div class="e-loop-item"><div class="elementor-element" data-id="li${n}"><h3><a href="${ORIGIN}/mesa-${n}/">Mesa ${n}</a></h3></div></div>`;
const reply = body => {
  if ('zzzz' === body.search_term) return { data: '<div class="digi-search__nothing">Nada por aquí</div>', pagination: '' };
  if ('vacio' === body.search_term) return { data: '', pagination: '' };
  if ('una' === body.search_term) return { data: item(1), pagination: '' };
  const first = body.page_number === 1;
  return {
    data: first ? item(1) + item(2) : item(3),
    pagination: first ? `<span class="prev page-numbers inactive">Anterior</span><span aria-current="page" class="page-numbers current">1</span><a rel="nofollow" class="page-numbers" href="${ORIGIN}/buscar/?e-search-page=2">2</a>` : `<a rel="nofollow" class="page-numbers" href="${ORIGIN}/buscar/">1</a><span aria-current="page" class="page-numbers current">2</span>`,
  };
};

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const errors = [];
async function open(html, { width = 1280, reduce = false, pre = '', delays = {} } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(m.text()); });
  await page.setRequestInterception(true);
  page.__requests = [];
  page.on('request', async r => {
    const url = r.url();
    if (url === ORIGIN + '/') return r.respond({ status: 200, contentType: 'text/html; charset=utf-8', body: html.replace('__PRE__', pre) });
    if (url.startsWith(ORIGIN + '/wp-json/digitalisimo-elements/v1/search')) {
      const body = JSON.parse(r.postData() || '{}');
      page.__requests.push({ method: r.method(), headers: r.headers(), body });
      await sleep(delays[body.search_term] || 50);
      return r.respond({ status: 200, contentType: 'application/json', body: JSON.stringify(reply(body)) }).catch(() => {});
    }
    return r.abort();
  });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  await page.goto(ORIGIN + '/', { waitUntil: 'load' });
  return page;
}
const active = page => page.evaluate(() => { const a = document.activeElement; return a ? a.id || a.className || a.tagName : ''; });
const oc = (page, id) => page.evaluate(id => {
  const p = document.getElementById('off-canvas-' + id), main = p.querySelector('.digi-noc__main');
  return { hidden: p.getAttribute('aria-hidden'), inert: p.hasAttribute('inert'), delay: p.hasAttribute('data-delay-child-handlers'), visible: getComputedStyle(p).visibility, closing: p.classList.contains('digi-noc--closing'), main: main.className, lock: document.body.classList.contains('digi-noc-lock') };
}, id);

// ── Panel ────────────────────────────────────────────────────────────────────────────────────────────────────────
{
  const page = await open(OC_PAGE);
  let s = await oc(page, 'oc00001');
  ok(s.hidden === 'true' && s.inert && s.delay && s.visible === 'hidden', 'Panel cerrado e inerte al cargar, con los manejadores de los hijos en espera.');
  const triggers = await page.evaluate(() => ['t1', 't2', 't3', 't5'].map(id => { const a = document.getElementById(id); return [a.getAttribute('aria-controls'), a.getAttribute('aria-expanded')]; }));
  ok(JSON.stringify(triggers) === JSON.stringify([['off-canvas-oc00001', 'false'], ['off-canvas-oc00001', 'false'], ['off-canvas-oc00002', 'false'], [null, null]]), 'Cada enlace de acción (codificado o no) controla su panel; los demás enlaces no se tocan: ' + JSON.stringify(triggers));
  ok(await page.evaluate(() => ['open', 'close', 'toggle'].every(m => typeof elementorFrontend.utils.urlActions.actions['off_canvas:' + m] === 'function')), 'Las tres acciones de URL quedan registradas en el núcleo.');
  // Abrir con el ratón (etiqueta dinámica, enlace codificado).
  await page.click('#t1');
  await sleep(150);
  s = await oc(page, 'oc00001');
  ok(s.hidden === 'false' && !s.inert && !s.delay && s.visible === 'visible' && !s.lock, 'Abre con el enlace de la etiqueta dinámica: visible, sin inert, sin bloqueo (no pedido).');
  // Con el ratón, como PRO, el foco no va al primer control (en un teléfono abriría el teclado de un campo de texto):
  // entra en el contenido, sin anillo, y el diálogo sigue atrapándolo.
  ok(await page.evaluate(() => document.activeElement.classList.contains('digi-noc__content') && document.activeElement.getAttribute('tabindex') === '-1' && !document.activeElement.matches(':focus-visible')), 'Abrir con el ratón lleva el foco al contenido del panel, sin anillo: ' + await active(page));
  await page.keyboard.down('Shift');
  await page.keyboard.press('Tab');
  await page.keyboard.up('Shift');
  ok('last' === await active(page), 'Mayús+Tab desde el contenido va al último control (no sale del panel).');
  await page.keyboard.press('Tab');
  ok('first' === await active(page), 'Tab desde el último vuelve al primero.');
  await page.evaluate(() => document.querySelector('#off-canvas-oc00001 .digi-noc__content').focus());
  await page.keyboard.press('Tab');
  ok('first' === await active(page), 'Tab desde el contenido va al primer control.');
  ok(await page.evaluate(() => document.getElementById('t1').getAttribute('aria-expanded') === 'true' && document.getElementById('t2').getAttribute('aria-expanded') === 'true'), 'Los disparadores dicen que está abierto.');
  ok(JSON.stringify(await page.evaluate(() => window.__ready)) === JSON.stringify(['oc00001c', 'oc00001b']), 'Los manejadores de los hijos corren al abrir: ' + JSON.stringify(await page.evaluate(() => window.__ready)));
  // Foco atrapado.
  await page.keyboard.press('Tab');
  ok('last' === await active(page), 'Tab va al segundo control.');
  await page.keyboard.press('Tab');
  ok('first' === await active(page), 'Tab desde el último vuelve al primero (foco atrapado).');
  await page.keyboard.down('Shift');
  await page.keyboard.press('Tab');
  await page.keyboard.up('Shift');
  ok('last' === await active(page), 'Mayús+Tab desde el primero va al último.');
  // Escape: cierra y devuelve el foco al disparador.
  await page.keyboard.press('Escape');
  await sleep(100);
  s = await oc(page, 'oc00001');
  ok(s.hidden === 'true' && s.inert && s.visible === 'hidden' && 't1' === await active(page), 'Escape cierra y el foco vuelve al disparador.');
  ok(await page.evaluate(() => document.getElementById('t1').getAttribute('aria-expanded') === 'false'), 'Los disparadores dicen que está cerrado.');
  // Alternar con el enlace escrito a mano; la capa cierra.
  await page.click('#t2');
  await sleep(150);
  ok((await oc(page, 'oc00001')).hidden === 'false', 'El enlace de acción «alternar» abre.');
  await page.mouse.click(1000, 450);
  await sleep(150);
  s = await oc(page, 'oc00001');
  ok(s.hidden === 'true', 'Un clic en la capa cierra.');
  await page.evaluate(() => { document.getElementById('t2').focus(); });
  await page.keyboard.press('Enter');
  await sleep(150);
  ok((await oc(page, 'oc00001')).hidden === 'false' && 'first' === await active(page), 'Intro en el enlace de acción abre y lleva el foco al primer control (teclado, como PRO).');
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('elementor-pro/off-canvas/toggle-display-mode', { detail: { id: 'oc00001', displayMode: 'toggle' } })));
  await sleep(100);
  ok((await oc(page, 'oc00001')).hidden === 'true' && 't2' === await active(page), 'El evento de PRO (alternar) cierra y el foco vuelve a quien lo abrió.');
  ok(JSON.stringify(await page.evaluate(() => window.__ready)) === JSON.stringify(['oc00001c', 'oc00001b']), 'Los manejadores de los hijos corren una sola vez.');
  // Panel que no se cierra con Escape ni con la capa, con bloqueo y animaciones.
  await page.click('#t3');
  await sleep(20);
  s = await oc(page, 'oc00002');
  ok(s.hidden === 'false' && s.lock && /\banimated\b/.test(s.main) && /\bfadeIn\b/.test(s.main), 'Abre con su animación de entrada del núcleo y bloquea el desplazamiento: ' + s.main);
  await sleep(300);
  ok(!/animated/.test((await oc(page, 'oc00002')).main), 'Al terminar la animación se quitan sus clases.');
  await page.keyboard.press('Escape');
  await page.mouse.click(1000, 450);
  await sleep(100);
  ok((await oc(page, 'oc00002')).hidden === 'false', '«No cerrar» con Escape ni con la capa.');
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('elementor-pro/off-canvas/toggle-display-mode', { detail: { id: 'oc00002', displayMode: 'close' } })));
  await sleep(20);
  s = await oc(page, 'oc00002');
  ok(s.hidden === 'true' && s.closing && s.visible === 'visible' && /\breverse\b/.test(s.main) && !s.lock, 'Cierra con la animación de salida (visible mientras dura) y libera el desplazamiento: ' + JSON.stringify(s));
  await sleep(300);
  s = await oc(page, 'oc00002');
  ok(!s.closing && s.visible === 'hidden' && !/animated/.test(s.main), 'Al terminar la salida queda oculto y sin clases.');
  // Panel sin controles: el foco va al contenido.
  await page.click('#t4');
  await sleep(150);
  ok(await page.evaluate(() => document.activeElement.classList.contains('digi-noc__content') && document.activeElement.getAttribute('tabindex') === '-1'), 'Sin controles dentro, el foco va al contenido del panel.');
  await page.keyboard.press('Tab');
  ok(await page.evaluate(() => document.activeElement.classList.contains('digi-noc__content')), 'Y Tab no sale del panel.');
  await page.keyboard.press('Escape');
  await sleep(100);
  // Un campo de texto como primer control: con el ratón no se enfoca (PRO no movía el foco); con Intro, sí.
  await page.evaluate(() => { const a = document.querySelector('#off-canvas-oc00001 .first'); const i = document.createElement('input'); i.type = 'search'; i.id = 'campo'; i.setAttribute('aria-label', 'Buscar'); a.before(i); });
  await page.click('#t1');
  await sleep(150);
  ok(await page.evaluate(() => document.activeElement.classList.contains('digi-noc__content')), 'Con un campo de texto primero, abrir con el ratón no lo enfoca: ' + await active(page));
  await page.keyboard.press('Escape');
  await sleep(100);
  await page.evaluate(() => { document.getElementById('t2').focus(); });
  await page.keyboard.press('Enter');
  await sleep(150);
  ok('campo' === await active(page), 'Con Intro, el foco va al campo de texto (primer control).');
  await page.keyboard.press('Escape');
  await page.close();
}
{
  // Ancho: el contenido no se acota a la ventana (como PRO). Con «Alto: ajustado al contenido» (sin desplazamiento
  // interno) y un bloque de 390 px en una ventana de 375 px, el panel de 400 px mide 390 px y desborda por el inicio.
  const page = await open(OC_PAGE.replace('</style>', '#off-canvas-oc00003{--digi-noc-overflow:initial;--digi-noc-height:fit-content;--digi-noc-justify:flex-end}</style>').replace('<span>sin controles</span>', '<div style="width:390px;height:20px"></div>'), { width: 375 });
  await page.click('#t4');
  await sleep(150);
  const w = await page.evaluate(() => { const c = document.querySelector('#off-canvas-oc00003 .digi-noc__content').getBoundingClientRect(), m = document.querySelector('#off-canvas-oc00003 .digi-noc__main').getBoundingClientRect(); return [Math.round(c.width), Math.round(c.left), Math.round(m.width)]; });
  ok(w[2] < 390 && w[0] === 390 && w[1] === w[2] - 390, 'El contenido ajustado mide lo que su bloque (390 px), no el ancho de la ventana, y desborda por el inicio: ' + JSON.stringify(w));
  await page.close();
}
{
  // Movimiento reducido: sin animaciones; dispositivo móvil: la animación del móvil.
  let page = await open(OC_PAGE, { reduce: true });
  await page.click('#t3');
  await sleep(20);
  ok(!/animated/.test((await oc(page, 'oc00002')).main), 'Con movimiento reducido no hay animación de entrada.');
  await page.evaluate(() => window.dispatchEvent(new CustomEvent('elementor-pro/off-canvas/toggle-display-mode', { detail: { id: 'oc00002', displayMode: 'close' } })));
  await sleep(20);
  ok((await oc(page, 'oc00002')).visible === 'hidden', 'Ni de salida: se oculta al momento.');
  await page.close();
  page = await open(OC_PAGE, { width: 375 });
  await page.click('#t3');
  await sleep(20);
  ok(/\bslideInRight\b/.test((await oc(page, 'oc00002')).main), 'En el móvil, la animación del móvil.');
  await page.close();
  // Una acción ya registrada (p. ej. por los paquetes de PRO) no se pisa.
  page = await open(OC_PAGE, { pre: "addEventListener('elementor/frontend/init', () => elementorFrontend.on('components:init', () => elementorFrontend.utils.urlActions.addAction('off_canvas:open', s => { window.__theirs = s.id; window.dispatchEvent(new CustomEvent('elementor-pro/off-canvas/toggle-display-mode', { detail: s })); })), { once: true });" });
  await page.click('#t1');
  await sleep(150);
  ok('oc00001' === await page.evaluate(() => window.__theirs) && (await oc(page, 'oc00001')).hidden === 'false', 'Una acción off_canvas:open ya registrada no se pisa, y su evento abre el panel propio igual.');
  await page.close();
  // Editor: abierto por «Modo de edición», sin atrapar el foco ni moverlo.
  page = await open(OC_PAGE.replace('aria-hidden="true" inert data-delay-child-handlers="true" data-digi-noc=\'{"in":{},"out":{},"o":true,"e":true,"s":false}\'', 'aria-hidden="false" data-digi-noc=\'{"in":{},"out":{},"o":true,"e":true,"s":false}\''), { pre: 'elementorFrontend.edit = true;' });
  ok((await oc(page, 'oc00001')).hidden === 'false', 'En el editor, con «Modo de edición», el panel llega abierto.');
  await page.mouse.click(1000, 450);
  await sleep(100);
  ok((await oc(page, 'oc00001')).hidden === 'false', 'En el editor la capa no lo cierra.');
  await page.evaluate(() => document.getElementById('t5').focus());
  await page.evaluate(() => document.getElementById('t4').click());
  await sleep(150);
  { const st = await oc(page, 'oc00003'), ac = await active(page); ok(st.hidden === 'false' && 't5' === ac, 'En el editor abrir no mueve el foco del lienzo: ' + JSON.stringify([st, ac])); }
  await page.close();
}

// ── Búsqueda ─────────────────────────────────────────────────────────────────────────────────────────────────────
{
  const page = await open(SE_PAGE, { delays: { mesa: 250 } });
  const vars = await page.evaluate(() => [...document.querySelectorAll('.digi-search')].map(r => [r.style.getPropertyValue('--dgs-iw'), r.style.getPropertyValue('--dgs-cw'), r.style.getPropertyValue('--dgs-xw')]));
  ok(vars[0][0] === 'calc(20px + var(--dgs-gap,4px))' && vars[0][1] === 'calc(8px + var(--dgs-gap,4px))' && vars[0][2] === '8px' && vars[1][0] === '0px', 'El relleno del campo sigue al ancho medido de cada icono (con la separación), como PRO: ' + JSON.stringify(vars));
  const pad = await page.evaluate(() => { const cs = getComputedStyle(document.querySelector('#search-se00001')); return [cs.paddingLeft, cs.paddingRight]; });
  ok(pad[0] === '40px' && pad[1] === '28px', 'Relleno: 16 + 20 + 4 a la izquierda y 16 + 8 + 4 a la derecha: ' + pad);
  // Foco: el campo no lleva contorno ni con el ratón ni con el teclado (PRO: `outline:none`); los botones, el propio.
  const ring = sel => page.evaluate(s => { const e = document.querySelector(s), cs = getComputedStyle(e); return [document.activeElement === e, cs.outlineStyle, cs.outlineWidth]; }, sel);
  await page.click('#search-se00001');
  let rg = await ring('#search-se00001');
  ok(rg[0] && rg[1] === 'none', 'Clic en el campo: enfocado y sin contorno, como PRO: ' + JSON.stringify(rg));
  await page.keyboard.press('Tab');
  await page.keyboard.down('Shift');
  await page.keyboard.press('Tab');
  await page.keyboard.up('Shift');
  rg = await ring('#search-se00001');
  ok(rg[0] && rg[1] === 'none', 'Con el teclado, el campo tampoco lleva contorno: ' + JSON.stringify(rg));
  await page.focus('[data-id="se00001"] .digi-search__submit');
  await page.keyboard.press('Shift');
  await sleep(400); // El botón anima todas sus propiedades (`transition: .3s`, como PRO).
  rg = await ring('[data-id="se00001"] .digi-search__submit');
  ok(rg[0] && rg[1] === 'solid' && rg[2] === '2px', 'El botón «Buscar» con el foco del teclado sí lleva el contorno propio: ' + JSON.stringify(rg));
  await page.evaluate(() => document.activeElement.blur());
  const clear = () => page.evaluate(() => { const b = document.querySelector('[data-id="se00001"] .digi-search__clear'); const r = b.getBoundingClientRect(); return { off: b.classList.contains('digi-search__clear--off'), vis: getComputedStyle(b).visibility, w: r.width, h: r.height }; });
  let c = await clear();
  ok(c.off && c.vis === 'hidden' && c.w >= 24 && c.h >= 24, '«Borrar» oculto sin texto, de 24 × 24 px como mínimo: ' + JSON.stringify(c));
  await page.type('#search-se00001', 'hola');
  c = await clear();
  ok(!c.off && c.vis === 'visible', '«Borrar» aparece con texto.');
  await page.click('[data-id="se00001"] .digi-search__clear');
  ok('' === await page.evaluate(() => document.querySelector('#search-se00001').value) && 'search-se00001' === await active(page) && (await clear()).off, '«Borrar» vacía el campo, devuelve el foco y se oculta.');
  await sleep(400);
  ok(0 === page.__requests.length, 'Sin resultados en vivo no se pide nada.');
  // Resultados en vivo.
  await page.type('#search-se00002', 'me');
  await sleep(450);
  ok(0 === page.__requests.length, 'Por debajo del mínimo de caracteres no se pide nada.');
  await page.type('#search-se00002', 'sa');
  await sleep(320);
  const loading = await page.evaluate(() => { const r = document.getElementById('results-se00002'); return [r.hidden, r.querySelector('.digi-search__loader').hidden, r.querySelector('.digi-search__box').hidden]; });
  ok(1 === page.__requests.length && JSON.stringify(loading) === '[false,false,true]', 'Una petición tras 300 ms; mientras llega, el indicador de carga: ' + JSON.stringify(loading));
  const req = page.__requests[0];
  ok(req.method === 'POST' && req.headers['content-type'] === 'application/json' && !req.headers['x-wp-nonce'] && JSON.stringify(req.body) === JSON.stringify({ post_id: 10, widget_id: 'se00002', search_term: 'mesa', page_number: 1, breakpoint: 'desktop' }), 'Petición al propio sitio con el documento, el widget, el término, la página y el dispositivo: ' + JSON.stringify(req.body));
  await sleep(350);
  const res = () => page.evaluate(() => { const r = document.getElementById('results-se00002'); return { hidden: r.hidden, loader: r.querySelector('.digi-search__loader').hidden, items: [...r.querySelectorAll('.digi-search__list h3')].map(h => h.textContent), nav: r.querySelector('nav.digi-search__pages')?.getAttribute('aria-label') ?? null, none: r.classList.contains('digi-search--none'), status: document.querySelector('[data-id="se00002"] [role=status]').textContent }; });
  let r = await res();
  ok(!r.hidden && r.loader && JSON.stringify(r.items) === '["Mesa 1","Mesa 2"]' && r.nav === 'Páginas de resultados' && !r.none && r.status === '2 resultados', 'Resultados a la vista, con su paginación y el número anunciado: ' + JSON.stringify(r));
  ok(JSON.stringify(await page.evaluate(() => window.__ready)) === '["li1","li2"]', 'Los manejadores de los resultados corren.');
  await page.click('#results-se00002 a.page-numbers');
  await sleep(450);
  r = await res();
  ok(2 === page.__requests.length && 2 === page.__requests[1].body.page_number && JSON.stringify(r.items) === '["Mesa 3"]' && r.status === '1 resultado', 'La paginación pide la página 2 sin recargar: ' + JSON.stringify(r));
  // Teclado.
  await page.focus('#search-se00002');
  await page.keyboard.press('ArrowDown');
  ok(await page.evaluate(() => document.activeElement.textContent === 'Mesa 3'), 'Flecha abajo: al primer resultado.');
  await page.keyboard.press('ArrowDown');
  ok(await page.evaluate(() => document.activeElement.textContent === '1'), 'Flecha abajo: al siguiente control de los resultados.');
  await page.keyboard.press('ArrowDown');
  ok('search-se00002' === await active(page), 'Desde el último, flecha abajo vuelve al campo.');
  await page.keyboard.press('ArrowUp');
  ok(await page.evaluate(() => document.activeElement.textContent === '1'), 'Flecha arriba desde el campo: al último control.');
  await page.keyboard.press('Escape');
  ok((await res()).hidden && 'search-se00002' === await active(page), 'Escape cierra los resultados y devuelve el foco al campo.');
  await page.click('#fuera');
  await page.focus('#search-se00002');
  ok(!(await res()).hidden, 'Volver al campo muestra otra vez los resultados.');
  await page.click('#fuera');
  ok((await res()).hidden, 'Un clic fuera los cierra.');
  // Sin resultados.
  const retype = async (text, wait = 450) => {
    await page.evaluate(() => { document.querySelector('#search-se00002').value = ''; });
    await page.type('#search-se00002', text);
    await sleep(wait);
  };
  await retype('zzzz');
  r = await res();
  ok(!r.hidden && r.none && r.status === 'Nada por aquí' && JSON.stringify(r.items) === '[]', 'Sin resultados: el mensaje del widget, anunciado: ' + JSON.stringify(r));
  await retype('vacio');
  r = await res();
  ok(r.hidden && r.status === 'No hay resultados', 'Sin mensaje: región cerrada y «No hay resultados» anunciado.');
  // Respuestas atrasadas: sólo cuenta la última.
  await page.evaluate(() => { document.querySelector('#search-se00002').value = ''; });
  await page.type('#search-se00002', 'mesa');
  await sleep(330);
  await page.evaluate(() => { const i = document.querySelector('#search-se00002'); i.value = 'una'; i.dispatchEvent(new Event('input', { bubbles: true })); });
  await sleep(800);
  r = await res();
  ok(JSON.stringify(r.items) === '["Mesa 1"]' && r.status === '1 resultado', 'Una respuesta atrasada no pisa la última: ' + JSON.stringify(r));
  await page.focus('#search-se00002');
  await page.evaluate(() => document.querySelector('form:has(#search-se00002)').addEventListener('submit', e => { e.preventDefault(); window.__submitted = true; }));
  await page.keyboard.press('Enter');
  ok((await res()).hidden && await page.evaluate(() => window.__submitted === true), 'Intro envía el formulario y cierra los resultados.');
  await page.close();
}

ok(!errors.length, 'Sin errores de JavaScript: ' + errors.join(' | '));
await browser.close();
console.log(`DIGITALÍSIMO Elements: Panel fuera de lienzo y Búsqueda en Chrome (${checks} comprobaciones) validados.`);
