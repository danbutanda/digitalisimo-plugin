// Scripts de los listados propios (C5, frente I) en Chrome real (sin interfaz): `loop.js` (mampostería, «Cargar más» y
// desplazamiento infinito) y `portfolio.js` (filtro). Las páginas son el marcado de `Posts_Base::render_pagination()` y de
// las pieles (lo comprueba tests/elements-pro-posts.php), servidas por la interceptación de peticiones desde un origen
// de prueba (`https://listados.test/`), con las hojas reales del módulo y la rejilla del núcleo:
// - «Cargar más»: pide la página siguiente del ancla, añade los elementos del mismo widget, anuncia lo cargado en la región
//   viva, lleva el foco al primer elemento nuevo, desactiva el botón mientras carga y, en la última, oculta el botón y
//   muestra el mensaje; sin peticiones repetidas;
// - infinita: carga al llegar al ancla, hasta la última página;
// - mampostería: cada elemento sube a su columna (como PRO) con 3 columnas; con 1 columna no hay mampostería; se recoloca
//   al cambiar el ancho;
// - Portafolio: el filtro marca `aria-pressed`, oculta los de otros términos y «Todos» los devuelve; con movimiento
//   reducido no hay animación de entrada;
// - el editor: `frontend/element_ready/global` vuelve a iniciar lo que pinta.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. Uso: timeout 150 node tests/elements-pro-posts-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los listados (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const mod = new URL('../digitalisimo-elements/modules/digitalisimo-loop/assets/', import.meta.url);
const asset = (p) => readFileSync(new URL(p, mod), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const ORIGIN = 'https://listados.test';

// ── Páginas de prueba ────────────────────────────────────────────────────────────────────────────────────────────
const item = (n, h) => `<article class="digi-posts__item elementor-grid-item post-${n}" role="listitem"><div class="digi-posts__text" style="height:${h}px"><h3 class="digi-posts__title"><a href="/entrada-${n}/">Entrada ${n}</a></h3></div></article>`;
const heights = [100, 160, 120, 140, 100, 180, 110, 130, 150];
function postsPage(page, mode) {
  const items = [0, 1, 2].map((i) => item(page * 3 + i - 2, heights[(page - 1) * 3 + i])).join('');
  const next = page < 3 ? `${ORIGIN}/${mode}/${page + 1}/` : `${ORIGIN}/${mode}/4/`;
  const foot = `<span class="digi-loop__spinner"><i aria-hidden="true"></i></span><div class="digi-loop__anchor" data-page="${page}" data-max-page="3" data-next-page="${next}" data-digi-more="${mode === 'click' ? 'click' : 'infinite'}"></div>`
    + (mode === 'click' ? '<div class="digi-loop__more elementor-button-wrapper"><button type="button" class="elementor-button elementor-size-sm"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">Cargar más</span></span></button></div>' : '')
    + '<div class="digi-loop__message">No hay más</div><span class="digi-loop__status elementor-screen-only" aria-live="polite" data-digi-loaded="Se cargaron %d elementos más."></span>';
  const spacer = mode === 'infinite' ? '<div style="height:1600px">relleno</div>' : '';
  return doc(`${spacer}<div class="elementor-element elementor-widget elementor-widget-digitalisimo-posts digi-loop--more-align-center elementor-grid-3" data-id="w1"><div class="digi-posts digi-posts--skin-classic elementor-grid" role="list" data-digi-items="">${items}</div>${foot}</div>`);
}
function masonryPage() {
  const items = heights.map((h, i) => item(i + 1, h)).join('');
  return doc(`<div class="elementor-element elementor-widget elementor-grid-3 elementor-grid-mobile-1" data-id="m1"><div class="digi-posts elementor-grid" role="list" data-digi-items="" data-digi-masonry="">${items}</div></div>`);
}
function portfolioPage() {
  const terms = ['5', '6', '5 6', '6', '5', '7'];
  const items = terms.map((t, i) => `<article class="digi-portfolio__item" role="listitem" data-digi-terms="${t}"><a class="digi-portfolio__link" href="/p-${i}/"><div class="digi-portfolio__img" style="height:80px"></div><div class="digi-portfolio__overlay"><h3 class="digi-portfolio__title">P${i}</h3></div></a></article>`).join('');
  const filters = [['__all', 'Todos'], ['5', 'Cinco'], ['6', 'Seis'], ['7', 'Siete']].map(([f, l], i) => `<li><button type="button" class="digi-portfolio__filter" data-filter="${f}" aria-pressed="${i ? 'false' : 'true'}">${l}</button></li>`).join('');
  return doc(`<div class="elementor-element elementor-widget elementor-grid-3" data-id="p1"><ul class="digi-portfolio__filters" aria-label="Filtrar">${filters}</ul><div class="digi-portfolio elementor-grid" role="list" data-digi-items="">${items}</div></div>`, ['portfolio.js']);
}
function doc(body, scripts = ['loop.js']) {
  const grid = '.elementor-grid{display:grid;grid-column-gap:var(--grid-column-gap);grid-row-gap:var(--grid-row-gap)}.elementor-grid .elementor-grid-item{min-width:0}.elementor-grid-3 .elementor-grid{grid-template-columns:repeat(3,1fr)}@media(max-width:767px){.elementor-grid-mobile-1 .elementor-grid{grid-template-columns:repeat(1,1fr)}}.elementor-screen-only{position:absolute;top:-10000em;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.elementor-element{--grid-column-gap:30px;--grid-row-gap:35px}body{margin:0;font:16px sans-serif}';
  return `<!doctype html><html><head><meta charset="utf-8"><style>${grid}</style><link rel="stylesheet" href="/a/posts.css"><link rel="stylesheet" href="/a/loop.css"><link rel="stylesheet" href="/a/portfolio.css"></head><body>${body}${scripts.map((s) => `<script src="/a/${s}" defer></script>`).join('')}</body></html>`;
}

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu'] });
const errors = [];
async function open(path, { width = 1280, reduce = false, editor = false, delay = 0 } = {}) {
  const page = await browser.newPage();
  page.requests = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setRequestInterception(true);
  page.on('request', async (r) => {
    const u = new URL(r.url());
    if (u.origin !== ORIGIN) return r.abort();
    page.requests.push(u.pathname);
    let m;
    if ((m = u.pathname.match(/^\/a\/(.+)$/))) return r.respond({ status: 200, contentType: m[1].endsWith('.js') ? 'text/javascript' : 'text/css', body: asset((m[1].endsWith('.js') ? 'js/' : 'css/') + m[1]) });
    if ((m = u.pathname.match(/^\/(click|infinite)\/(\d)\/$/))) {
      if (delay && m[2] !== '1') await sleep(delay);
      return r.respond({ status: 200, contentType: 'text/html', body: postsPage(+m[2], m[1]) });
    }
    if (u.pathname === '/masonry/') return r.respond({ status: 200, contentType: 'text/html', body: masonryPage() });
    if (u.pathname === '/portfolio/') return r.respond({ status: 200, contentType: 'text/html', body: portfolioPage() });
    if (u.pathname === '/favicon.ico') return r.respond({ status: 204, body: '' });
    return r.respond({ status: 404, body: '' });
  });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 800 });
  if (editor) await page.evaluateOnNewDocument(() => { window.elementorFrontend = { isEditMode: () => true, hooks: { addAction: (n, f) => { window.__hook = [n, f]; } } }; });
  await page.goto(ORIGIN + path, { waitUntil: 'load' });
  await page.waitForFunction(() => window.digiLoop || window.digiPortfolio);
  return page;
}
const count = (page, sel) => page.$$eval(sel, (l) => l.length);

// 1. «Cargar más».
{
  const page = await open('/click/1/', { delay: 400 });
  ok(await count(page, '.digi-posts__item') === 3, 'Página 1: tres entradas.');
  ok(await page.$eval('.digi-loop__more', (e) => getComputedStyle(e).display !== 'none' && getComputedStyle(e).textAlign === 'center'), 'El botón se ve, centrado por defecto.');
  ok(await page.$eval('.digi-loop__more .elementor-button .digi-loop__spinner', (e) => getComputedStyle(e).display === 'none'), 'El cargador va dentro del botón y no se ve hasta cargar.');
  await page.click('.digi-loop__more .elementor-button');
  await sleep(100);
  ok(await page.$eval('[data-id="w1"]', (e) => e.classList.contains('digi-loop--loading')) && await page.$eval('.digi-loop__more .elementor-button', (e) => e.getAttribute('aria-disabled') === 'true'), 'Mientras carga: clase de carga y botón desactivado.');
  ok(await page.$eval('.digi-loop__more .elementor-button .digi-loop__spinner', (e) => getComputedStyle(e).display === 'flex'), 'Mientras carga se ve el cargador.');
  await page.click('.digi-loop__more .elementor-button');
  await page.waitForFunction(() => document.querySelectorAll('.digi-posts__item').length === 6);
  await sleep(100);
  ok(page.requests.filter((p) => p === '/click/2/').length === 1, 'Un segundo clic mientras carga no repite la petición.');
  ok(await page.$eval('.digi-loop__anchor', (e) => e.dataset.page === '2' && e.dataset.nextPage === 'https://listados.test/click/3/'), 'El ancla pasa a la página 2 con la URL siguiente de la página cargada.');
  ok(await page.$eval('.digi-loop__status', (e) => e.textContent === 'Se cargaron 3 elementos más.'), 'La región viva anuncia lo cargado.');
  ok(await page.evaluate(() => document.activeElement && document.activeElement.textContent === 'Entrada 4'), 'El foco pasa al primer elemento nuevo.');
  ok(await page.$eval('[data-id="w1"]', (e) => !e.classList.contains('digi-loop--loading') && !e.classList.contains('digi-loop--end')), 'Tras cargar: sin clase de carga ni de fin.');
  await page.click('.digi-loop__more .elementor-button');
  await page.waitForFunction(() => document.querySelectorAll('.digi-posts__item').length === 9);
  await sleep(100);
  ok(await page.$eval('[data-id="w1"]', (e) => e.classList.contains('digi-loop--end')) && await page.$eval('.digi-loop__more', (e) => getComputedStyle(e).display === 'none') && await page.$eval('.digi-loop__message', (e) => getComputedStyle(e).display === 'block'), 'En la última página: sin botón y con el mensaje del final.');
  ok(!page.requests.includes('/click/4/'), 'No pide más allá de la última página.');
  ok(await page.$$eval('.digi-posts__item', (l) => l.map((e) => e.textContent).join(',')) === 'Entrada 1,Entrada 2,Entrada 3,Entrada 4,Entrada 5,Entrada 6,Entrada 7,Entrada 8,Entrada 9', 'Las entradas en orden.');
  await page.close();
}
// Una sola página: fin desde el principio.
{
  const page = await open('/click/3/');
  ok(await page.$eval('[data-id="w1"]', (e) => e.classList.contains('digi-loop--end')), 'Si la página actual es la última, el botón no se muestra.');
  await page.close();
}
// 2. Desplazamiento infinito.
{
  const page = await open('/infinite/1/');
  await sleep(300);
  ok(await count(page, '.digi-posts__item') === 3 && !page.requests.includes('/infinite/2/'), 'Infinita: nada se carga antes de llegar al ancla.');
  for (const n of [6, 9]) {
    await page.$eval('.digi-loop__anchor', (e) => e.scrollIntoView({ block: 'center' }));
    await page.waitForFunction((n) => document.querySelectorAll('.digi-posts__item').length === n, { timeout: 8000 }, n);
    await sleep(200);
  }
  await sleep(300);
  ok(await page.$eval('[data-id="w1"]', (e) => e.classList.contains('digi-loop--end')) && !page.requests.includes('/infinite/4/'), 'Infinita: carga hasta la última página y se detiene.');
  ok(await count(page, 'button') === 0, 'Infinita: sin botón.');
  await page.close();
}
// 3. Mampostería.
{
  const page = await open('/masonry/');
  await sleep(200);
  const geo = () => page.$$eval('.digi-posts__item', (l) => l.map((e) => { const r = e.getBoundingClientRect(); return [Math.round(r.top), Math.round(r.bottom)]; }));
  let g = await geo();
  ok(await page.$eval('.digi-posts', (e) => e.classList.contains('digi-loop--masonry')), 'Con 3 columnas, el contenedor queda en mampostería.');
  for (let i = 3; i < 9; i++) ok(Math.abs(g[i][0] - (g[i - 3][1] + 35)) <= 1, `Mampostería: el elemento ${i + 1} sube bajo el ${i - 2} con la separación entre filas (${g[i][0]} frente a ${g[i - 3][1] + 35}).`);
  await page.setViewport({ width: 375, height: 800 });
  await sleep(400);
  g = await geo();
  ok(await page.$eval('.digi-posts', (e) => !e.classList.contains('digi-loop--masonry')) && await page.$$eval('.digi-posts__item', (l) => l.every((e) => e.style.marginTop === '')), 'Con una columna (móvil) no hay mampostería ni márgenes.');
  await page.setViewport({ width: 1280, height: 800 });
  await sleep(400);
  g = await geo();
  ok(Math.abs(g[3][0] - (g[0][1] + 35)) <= 1, 'Al volver a 3 columnas se recoloca.');
  await page.close();
}
// 4. Portafolio.
for (const reduce of [false, true]) {
  const page = await open('/portfolio/', { reduce });
  const visible = () => page.$$eval('.digi-portfolio__item', (l) => l.map((e) => (e.hidden ? '-' : 'X')).join(''));
  ok(await visible() === 'XXXXXX', 'Portafolio: todo a la vista al principio.');
  await page.click('[data-filter="6"]');
  await sleep(50);
  ok(await visible() === '-XXX--', 'Filtrar por un término oculta los demás (también con varios términos por elemento).');
  ok(await page.$$eval('.digi-portfolio__filter', (l) => l.map((e) => e.getAttribute('aria-pressed')).join(',')) === 'false,false,true,false', 'aria-pressed marca el filtro elegido.');
  await page.click('[data-filter="__all"]');
  await sleep(50);
  ok(await visible() === 'XXXXXX', '«Todos» devuelve todos.');
  ok((await count(page, '.digi-portfolio--in')) === (reduce ? 0 : 3), reduce ? 'Con movimiento reducido no hay animación de entrada.' : 'Los que vuelven entran con su animación.');
  const box = await page.$eval('.digi-portfolio__filter', (e) => { const r = e.getBoundingClientRect(); return r.width >= 24 && r.height >= 24; });
  ok(box, 'Botones del filtro de 24 × 24 px como mínimo.');
  await page.focus('[data-filter="__all"]');
  await page.keyboard.press('Tab');
  ok(await page.evaluate(() => document.activeElement.dataset.filter === '5' && getComputedStyle(document.activeElement).outlineStyle === 'solid' && getComputedStyle(document.activeElement).outlineOffset === '2px'), 'Foco visible en el filtro con el teclado (2 px con separación de 2 px).');
  await page.keyboard.press('Enter');
  await sleep(50);
  ok(await visible() === 'X-X-X-', 'El teclado activa el filtro (Intro).');
  await page.close();
}
// 5. Editor: se engancha a frontend/element_ready/global.
{
  const page = await open('/masonry/', { editor: true });
  ok(await page.evaluate(() => window.__hook && window.__hook[0] === 'frontend/element_ready/global'), 'En el editor, loop.js vuelve a iniciar con frontend/element_ready/global.');
  await page.close();
}
await browser.close();
assert.ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
console.log(`DIGITALÍSIMO Elements: scripts de los listados propios en Chrome validados (${checks} comprobaciones).`);
