// Scripts de los listados propios (C5, frente I) en Chrome real (sin interfaz): `loop.js` (mampostería, «Cargar más» y
// desplazamiento infinito) y `portfolio.js` (filtro). Las páginas son el marcado de `Posts_Base::render_pagination()` y de
// las pieles (lo comprueba tests/elements-pro-posts.php), servidas por la interceptación de peticiones desde un origen
// de prueba (`https://listados.test/`), con las hojas reales del módulo y la rejilla del núcleo:
// - «Cargar más»: pide la página siguiente del ancla, añade los elementos del mismo widget, anuncia lo cargado en la región
//   viva, lleva el foco al primer elemento nuevo, desactiva el botón mientras carga (sin quitarle el foco del teclado) y,
//   en la última, oculta el botón y muestra el mensaje; sin peticiones repetidas;
// - infinita: carga al llegar al ancla, hasta la última página;
// - mampostería: cada elemento sube a su columna (como PRO) con 3 columnas; con 1 columna no hay mampostería; se recoloca
//   al cambiar el ancho;
// - Portafolio: el filtro marca `aria-pressed`, oculta los de otros términos y «All» los devuelve, con la animación de
//   `arrangeGrid()` de PRO (los que salen encogen y se desvanecen, los demás se deslizan; ninguna con movimiento reducido),
//   y la imagen con «Proporción del elemento» ocupa la misma caja que en PRO (ajustada al alto si es más apaisada);
// - el editor: `frontend/element_ready/global` vuelve a iniciar lo que pinta;
// - fidelidad con PRO (regla del propietario, 2026-11-05):
//   - paginación: la barra de números de PRO y la propia, con letra de 12 y 16 px y separación de 0 y 10 px, tienen la
//     misma geometría y los mismos píxeles, y el centro de cada número abre su propio enlace;
//   - cascada: «Cargar más» (el enlace de PRO, sin `href`) y el filtro del Portafolio frente al marcado de PRO, con las
//     hojas reales del tema en paridad con Hello (`reset.css`), del núcleo y de PRO, un kit con Estilo del tema → Botones
//     y el CSS del documento con y sin colores globales: los mismos estilos calculados y la misma caja en reposo, al pasar
//     el cursor y al pulsar (con el teclado sólo cambia el contorno de foco, diferencia declarada 1);
//   - mampostería: la separación es el número del ajuste en px (`--digi-masonry-gap`, como PRO), 0 sin valor, ninguna con
//     una expresión, medida desde el borde exterior del contenedor y sin heredarla de una lista exterior.
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
// Hojas reales del tema en paridad con Hello, del núcleo y de PRO (las mismas que sirve un sitio).
const sheets = { reset: '../digitalisimo-theme/assets/css/parity/reset.css', frontend: '../digitalisimo-elements/elementor/assets/css/frontend.min.css', 'pro-posts': '../digitalisimo-elements/assets/css/widget-posts.min.css' };

// ── Páginas de prueba ────────────────────────────────────────────────────────────────────────────────────────────
const item = (n, h) => `<article class="digi-posts__item elementor-grid-item post-${n}" role="listitem"><div class="digi-posts__text" style="height:${h}px"><h3 class="digi-posts__title"><a href="/entrada-${n}/">Entrada ${n}</a></h3></div></article>`;
const heights = [100, 160, 120, 140, 100, 180, 110, 130, 150];
function postsPage(page, mode) {
  const items = [0, 1, 2].map((i) => item(page * 3 + i - 2, heights[(page - 1) * 3 + i])).join('');
  const next = page < 3 ? `${ORIGIN}/${mode}/${page + 1}/` : `${ORIGIN}/${mode}/4/`;
  const foot = `<span class="digi-loop__spinner"><i aria-hidden="true"></i></span><div class="digi-loop__anchor" data-page="${page}" data-max-page="3" data-next-page="${next}" data-digi-more="${mode === 'click' ? 'click' : 'infinite'}"></div>`
    + (mode === 'click' ? '<div class="digi-loop__more elementor-button-wrapper"><a class="elementor-button elementor-size-sm"><span class="elementor-button-content-wrapper" role="button" tabindex="0"><span class="elementor-button-text">Cargar más</span></span></a></div>' : '')
    + '<div class="digi-loop__message">No hay más</div><span class="digi-loop__status elementor-screen-only" aria-live="polite" data-digi-loaded="Se cargaron %d elementos más."></span>';
  const spacer = mode === 'infinite' ? '<div style="height:1600px">relleno</div>' : '';
  return doc(`${spacer}<div class="elementor-element elementor-widget elementor-widget-digitalisimo-posts digi-loop--more-align-center elementor-grid-3" data-id="w1"><div class="digi-posts digi-posts--skin-classic elementor-grid" role="list" data-digi-items="">${items}</div>${foot}</div>`);
}
function masonryPage() {
  const items = heights.map((h, i) => item(i + 1, h)).join('');
  return doc(`<div class="elementor-element elementor-widget elementor-grid-3 elementor-grid-mobile-1" data-id="m1" style="--digi-masonry-gap:35px"><div class="digi-posts elementor-grid" role="list" data-digi-items="" data-digi-masonry="">${items}</div></div>`);
}
// Mampostería con la separación de PRO: (a) Entradas con «2 em» (PRO: 2 px; la rejilla, 32 px); (b) Cuadrícula sin valor
// (PRO: 0; la rejilla, 35 px); (c) una expresión (unidad «custom»: PRO no sube nada); (d) una Cuadrícula dentro de una
// lista con 35 px (no la hereda); (e) contenedor con borde superior de 3 px (PRO mide desde el borde exterior).
function masonryPage2() {
  const grid = (id, style, inner = '') => `<div class="elementor-element elementor-widget elementor-widget-digitalisimo-posts elementor-grid-3" data-id="${id}" style="${style}"><div class="digi-posts elementor-grid" role="list" data-digi-items="" data-digi-masonry=""${inner}>${heights.map((h, i) => item(i + 1, h)).join('')}</div></div>`;
  const loop = (id) => `<div class="elementor-element elementor-widget elementor-widget-digitalisimo-loop-grid" data-id="${id}"><div class="digi-loop-grid__items" role="list" data-digi-items="" data-digi-masonry="">${heights.map((h, i) => `<div class="e-loop-item" role="listitem" style="height:${h}px">E${i + 1}</div>`).join('')}</div></div>`;
  return doc(grid('ma', '--grid-row-gap:2em;--digi-masonry-gap:2px')
    + loop('mb')
    + grid('mc', '--grid-row-gap:20px;--digi-masonry-gap:2vwpx')
    + `<div class="elementor-element" data-id="md0" style="--digi-masonry-gap:35px">${loop('md')}</div>`
    + grid('me', '--digi-masonry-gap:35px', ' style="border-top:3px solid"'));
}
// Paginación: el marcado de `render_pagination()` (anterior, actual, números, puntos, siguiente) en la barra de PRO
// (`nav.elementor-pagination`, sin hojas del módulo) y en la propia (`nav.digi-loop__pagination` con loop.css), con la
// alineación y la separación de PRO; letra y separación de la consulta.
function paginationPage(size, gap) {
  const links = ['<a class="page-numbers prev" href="/p/1/">&laquo; Anterior</a>', '<span aria-current="page" class="page-numbers current"><span class="elementor-screen-only">Página</span>2</span>']
    .concat([3, 4, 5].map((n) => `<a class="page-numbers" href="/p/${n}/"><span class="elementor-screen-only">Página</span>${n}</a>`))
    .concat(['<span class="page-numbers dots">&hellip;</span>', '<a class="page-numbers" href="/p/9/"><span class="elementor-screen-only">Página</span>9</a>', '<a class="page-numbers next" href="/p/3/">Siguiente &raquo;</a>']).join('\n');
  const css = `body{margin:0;font:16px/1.5 sans-serif}.bar{padding-top:7px}.after{height:20px}`
    + `body:not(.rtl) .elementor-element-pa .elementor-pagination .page-numbers:not(:first-child){margin-left:calc(${gap}px/2)}body:not(.rtl) .elementor-element-pa .elementor-pagination .page-numbers:not(:last-child){margin-right:calc(${gap}px/2)}.elementor-element-pa .elementor-pagination{text-align:center;font-size:${size}px}`
    + `.elementor-element-pb .digi-loop__pagination .page-numbers:not(:first-child){margin-inline-start:calc(${gap}px/2)}.elementor-element-pb .digi-loop__pagination .page-numbers:not(:last-child){margin-inline-end:calc(${gap}px/2)}.elementor-element-pb .digi-loop__pagination{text-align:center;font-size:${size}px}`;
  return `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/x/frontend.css"><link rel="stylesheet" href="/x/pro-posts.css"><link rel="stylesheet" href="/a/loop.css"><style>${css}</style></head><body>`
    + `<div class="bar elementor-element elementor-element-pa elementor-widget elementor-widget-posts"><div class="elementor-widget-container"><nav class="elementor-pagination" aria-label="Paginación">${links}</nav></div></div><div class="after" id="after-a"></div>`
    + `<div class="bar elementor-element elementor-element-pb elementor-widget elementor-widget-digitalisimo-posts"><div class="elementor-widget-container"><nav class="digi-loop__pagination" aria-label="Paginación">${links}</nav></div></div><div class="after" id="after-b"></div>`
    + '<script>window.digiLoop=1</script></body></html>';
}
// Cascada: el marcado de PRO (A) y el propio (B) en el mismo documento, con las hojas en el orden real (tema, núcleo,
// kit, PRO y módulo, documento). Las hojas se encienden y apagan por escenario.
const kitCss = '.elementor-kit-5{--e-global-color-primary:#6EC1E4;--e-global-color-text:#7A7A7A;--e-global-color-accent:#61CE70;--e-global-typography-primary-font-family:"Trebuchet MS";--e-global-typography-primary-font-weight:600;--e-global-typography-accent-font-family:"Verdana";--e-global-typography-accent-font-weight:500}'
  + '.elementor-kit-5 button,.elementor-kit-5 input[type="button"],.elementor-kit-5 input[type="submit"],.elementor-kit-5 .elementor-button{font-family:Georgia,serif;font-size:18px;text-transform:uppercase;letter-spacing:2px;text-shadow:1px 1px 0 #000;color:#fff;background-color:#112233;box-shadow:0 0 0 3px #00ff00;border-style:solid;border-width:2px;border-color:#112233;border-radius:30px;padding:16px 32px}'
  + '.elementor-kit-5 button:hover,.elementor-kit-5 button:focus,.elementor-kit-5 input[type="button"]:hover,.elementor-kit-5 input[type="button"]:focus,.elementor-kit-5 input[type="submit"]:hover,.elementor-kit-5 input[type="submit"]:focus,.elementor-kit-5 .elementor-button:hover,.elementor-kit-5 .elementor-button:focus{color:#000;background-color:#ff8800;border-color:#ff8800;border-radius:4px}';
const docCss = '.elementor-10 .elementor-element.elementor-element-pm .elementor-button{background-color:var(--e-global-color-accent);font-family:var(--e-global-typography-accent-font-family),Sans-serif;font-weight:var(--e-global-typography-accent-font-weight)}'
  + '.elementor-10 .elementor-element.elementor-element-om .digi-loop__more .elementor-button{background-color:var(--e-global-color-accent);font-family:var(--e-global-typography-accent-font-family),Sans-serif;font-weight:var(--e-global-typography-accent-font-weight)}'
  + '.elementor-10 .elementor-element.elementor-element-pf .elementor-portfolio__filter{color:var(--e-global-color-text);font-family:var(--e-global-typography-primary-font-family),Sans-serif;font-weight:var(--e-global-typography-primary-font-weight)}.elementor-10 .elementor-element.elementor-element-pf .elementor-portfolio__filter.elementor-active{color:var(--e-global-color-primary)}'
  + '.elementor-10 .elementor-element.elementor-element-of .digi-portfolio__filter{color:var(--e-global-color-text);font-family:var(--e-global-typography-primary-font-family),Sans-serif;font-weight:var(--e-global-typography-primary-font-weight)}.elementor-10 .elementor-element.elementor-element-of .digi-portfolio__filter[aria-pressed="true"]{color:var(--e-global-color-primary)}';
// Valores por defecto que el documento siempre imprime (no son colores globales): separación del filtro y de la barra.
const baseCss = 'body{margin:0}.elementor-10 .elementor-element.elementor-element-pf .elementor-portfolio__filter:not(:last-child){margin-right:calc(10px/2)}.elementor-10 .elementor-element.elementor-element-pf .elementor-portfolio__filter:not(:first-child){margin-left:calc(10px/2)}.elementor-10 .elementor-element.elementor-element-pf .elementor-portfolio__filters{margin-bottom:10px}'
  + '.elementor-10 .elementor-element.elementor-element-of .digi-portfolio__filters > li:not(:last-child){margin-right:calc(10px/2)}.elementor-10 .elementor-element.elementor-element-of .digi-portfolio__filters > li:not(:first-child){margin-left:calc(10px/2)}.elementor-10 .elementor-element.elementor-element-of .digi-portfolio__filters{margin-bottom:10px}.w{width:320px;padding:10px}';
// Tipografía del filtro y del botón: más pequeña que la del cuerpo (la caja de un botón en línea dentro del <li> crecería
// con la línea del <li>) y más grande con interlineado propio.
const typoCss = '.elementor-10 .elementor-element.elementor-element-pf .elementor-portfolio__filter,.elementor-10 .elementor-element.elementor-element-of .digi-portfolio__filter{font-size:12px;text-decoration:underline}'
  + '.elementor-10 .elementor-element.elementor-element-pm .elementor-button,.elementor-10 .elementor-element.elementor-element-om .digi-loop__more .elementor-button{font-size:22px;line-height:1.1}';
function cascadePage() {
  // «I»: un término de una letra, más estrecho que 24 px con el relleno de PRO (un mínimo de 24 px lo ensancharía).
  const terms = ['All', 'Noticias', 'Guías con un nombre de término bastante largo para partirse', 'Casos', 'I'];
  const pro = terms.map((t, i) => `<li id="pro-f${i}" class="elementor-portfolio__filter${i ? '' : ' elementor-active'}" tabindex="0" data-filter="${i ? i : '__all'}">${t}</li>`).join('');
  const own = terms.map((t, i) => `<li><button id="own-f${i}" type="button" class="digi-portfolio__filter" data-filter="${i ? i : '__all'}" aria-pressed="${i ? 'false' : 'true'}">${t}</button></li>`).join('');
  return `<!doctype html><html><head><meta charset="utf-8"><link id="reset" rel="stylesheet" href="/x/reset.css"><link id="core" rel="stylesheet" href="/x/frontend.css"><style id="kit">${kitCss}</style><link id="pro" rel="stylesheet" href="/x/pro-posts.css"><link id="loop" rel="stylesheet" href="/a/loop.css"><link id="portfolio" rel="stylesheet" href="/a/portfolio.css"><style id="base">${baseCss}</style><style id="doc">${docCss}</style><style id="typo">${typoCss}</style><style id="still">*,*::before,*::after{transition:none!important}</style></head>`
    + '<body class="elementor-kit-5"><div class="elementor elementor-10">'
    + '<div class="w elementor-element elementor-element-pm elementor-widget elementor-widget-posts load-more-align-center"><div class="elementor-widget-container"><div class="elementor-button-wrapper"><a id="pro-more" class="elementor-button elementor-size-sm" role="button"><span class="elementor-button-content-wrapper"><span class="elementor-button-text">Load More</span></span></a></div></div></div>'
    + '<div class="w elementor-element elementor-element-om elementor-widget elementor-widget-digitalisimo-posts digi-loop--more-align-center"><div class="elementor-widget-container"><div class="digi-loop__more elementor-button-wrapper"><a id="own-more" class="elementor-button elementor-size-sm"><span class="elementor-button-content-wrapper" role="button" tabindex="0"><span class="elementor-button-text">Load More</span></span></a></div></div></div>'
    + `<div class="w elementor-element elementor-element-pf elementor-widget elementor-widget-portfolio"><div class="elementor-widget-container"><ul class="elementor-portfolio__filters" id="pro-bar">${pro}</ul></div></div>`
    + `<div class="w elementor-element elementor-element-of elementor-widget elementor-widget-digitalisimo-portfolio"><div class="elementor-widget-container"><ul class="digi-portfolio__filters" aria-label="Filtrar" id="own-bar">${own}</ul></div></div>`
    + '</div><script>window.digiLoop=1</script></body></html>';
}
function portfolioPage() {
  const terms = ['5', '6', '5 6', '6', '5', '7'];
  const items = terms.map((t, i) => `<article class="digi-portfolio__item" role="listitem" data-digi-terms="${t}"><a class="digi-portfolio__link" href="/p-${i}/"><div class="digi-portfolio__img" style="height:80px"></div><div class="digi-portfolio__overlay"><h3 class="digi-portfolio__title">P${i}</h3></div></a></article>`).join('');
  const filters = [['__all', 'All'], ['5', 'Cinco'], ['6', 'Seis'], ['7', 'Siete']].map(([f, l], i) => `<li><button type="button" class="digi-portfolio__filter" data-filter="${f}" aria-pressed="${i ? 'false' : 'true'}">${l}</button></li>`).join('');
  return doc(`<div class="elementor-element elementor-widget elementor-widget-digitalisimo-portfolio elementor-grid-3" data-id="p1"><ul class="digi-portfolio__filters" aria-label="Filtrar">${filters}</ul><div class="digi-portfolio elementor-grid" role="list" data-digi-items="">${items}</div></div>`, ['portfolio.js']);
}
// Imagen del Portafolio con «Proporción del elemento» (0,66): la de PRO (su hoja real y su `fitImage()`, que pone
// `elementor-fit-height` si la imagen es más apaisada que la caja) junto a la propia (portfolio.css y las reglas que genera
// el control `item_ratio`), con una imagen apaisada (300 × 100) y una vertical (100 × 300) de tamaño natural conocido.
const svg = (w, h) => `data:image/svg+xml,${encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><rect width="${w}" height="${h}" fill="#c33"/></svg>`)}`;
function portfolioFitPage() {
  const pro = [[300, 100, ' elementor-fit-height'], [100, 300, '']].map(([w, h, fit], i) => `<article class="elementor-portfolio-item elementor-post" id="pro${i}"><a class="elementor-post__thumbnail__link" href="#"><div class="elementor-portfolio-item__img elementor-post__thumbnail${fit}"><img src="${svg(w, h)}" width="${w}" height="${h}" alt=""></div></a></article>`).join('');
  const own = [[300, 100], [100, 300]].map(([w, h], i) => `<article class="digi-portfolio__item" role="listitem" id="own${i}" data-digi-terms=""><a class="digi-portfolio__link" href="#"><div class="digi-portfolio__img"><img src="${svg(w, h)}" width="${w}" height="${h}" alt=""></div></a></article>`).join('');
  // Las reglas del control con la especificidad del CSS del documento (`.elementor-N .elementor-element.elementor-element-ID …`).
  const ratio = '.pf.elementor-element.elementor-widget .elementor-post__thumbnail__link{padding-bottom:calc(0.66 * 100%)}'
    + '.of .digi-portfolio__link{padding-bottom:calc(0.66 * 100%)}.of .digi-portfolio__img{position:absolute;inset:0;background-color:rgba(0,0,0,.1)}.of .digi-portfolio__img img{position:absolute;top:calc(50% + 1px);left:calc(50% + 1px);height:auto;transform:scale(1.01) translate(-50%,-50%)}.of .digi-portfolio__img--fit-height img{height:100%;width:auto}'
    + '.pf,.of{width:410px}';
  return `<!doctype html><html><head><meta charset="utf-8"><style>body{margin:0}${ratio}</style><link rel="stylesheet" href="/x/pro-posts.css"><link rel="stylesheet" href="/a/portfolio.css"></head><body>`
    + `<div class="pf elementor-element elementor-widget elementor-widget-portfolio"><div class="elementor-portfolio elementor-grid elementor-posts-container elementor-has-item-ratio">${pro}</div></div>`
    + `<div class="of elementor-element elementor-widget elementor-widget-digitalisimo-portfolio" data-id="p2"><div class="digi-portfolio elementor-grid" role="list" data-digi-items="">${own}</div></div>`
    + '<script src="/a/portfolio.js" defer></script></body></html>';
}
function doc(body, scripts = ['loop.js']) {
  const grid = '.elementor-grid{display:grid;grid-column-gap:var(--grid-column-gap);grid-row-gap:var(--grid-row-gap)}.elementor-grid .elementor-grid-item{min-width:0}.elementor-grid-3 .elementor-grid{grid-template-columns:repeat(3,1fr)}@media(max-width:767px){.elementor-grid-mobile-1 .elementor-grid{grid-template-columns:repeat(1,1fr)}}.elementor-screen-only{position:absolute;top:-10000em;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}.elementor-element{--grid-column-gap:30px;--grid-row-gap:35px}body{margin:0;font:16px sans-serif}';
  return `<!doctype html><html><head><meta charset="utf-8"><style>${grid}</style><link rel="stylesheet" href="/a/posts.css"><link rel="stylesheet" href="/a/loop.css"><link rel="stylesheet" href="/a/portfolio.css"><link rel="stylesheet" href="/a/loop-grid.css"></head><body>${body}${scripts.map((s) => `<script src="/a/${s}" defer></script>`).join('')}</body></html>`;
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
    if (u.pathname === '/masonry2/') return r.respond({ status: 200, contentType: 'text/html', body: masonryPage2() });
    if (u.pathname === '/pagination/') return r.respond({ status: 200, contentType: 'text/html', body: paginationPage(+u.searchParams.get('s'), +u.searchParams.get('g')) });
    if (u.pathname === '/cascade/') return r.respond({ status: 200, contentType: 'text/html', body: cascadePage() });
    if ((m = u.pathname.match(/^\/x\/(reset|frontend|pro-posts)\.css$/))) return r.respond({ status: 200, contentType: 'text/css', body: readFileSync(new URL(sheets[m[1]], import.meta.url), 'utf8') });
    if (u.pathname === '/portfolio/') return r.respond({ status: 200, contentType: 'text/html', body: portfolioPage() });
    if (u.pathname === '/portfolio-fit/') return r.respond({ status: 200, contentType: 'text/html', body: portfolioFitPage() });
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
  ok(await page.$eval('[data-id="w1"]', (e) => e.classList.contains('digi-loop--loading')) && await page.$eval('.digi-loop__more [role="button"]', (e) => e.getAttribute('aria-disabled') === 'true'), 'Mientras carga: clase de carga y botón desactivado.');
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
// «Cargar más» con el teclado: el contenido del enlace es el botón (Tab llega a él; Intro y Espacio cargan; Espacio no
// desplaza la página). El enlace no tiene href ni tabindex, como el de PRO.
{
  const page = await open('/click/1/');
  ok(await page.$eval('.digi-loop__more .elementor-button', (e) => e.tagName === 'A' && !e.hasAttribute('href') && !e.hasAttribute('tabindex') && !e.hasAttribute('role')), '«Cargar más» es el enlace de PRO, sin href ni tabindex.');
  ok(await page.$eval('[data-id="w1"]', (w) => w.querySelector('.e-load-more-anchor+.elementor-button-wrapper .elementor-button,.digi-loop__anchor+.digi-loop__more .elementor-button') === w.querySelector('.digi-loop__more .elementor-button')), 'La acción «Cargar más» de F7b (assets/js/actions.js) encuentra el botón propio tras su ancla.');
  for (let i = 0; i < 4; i++) await page.keyboard.press('Tab');
  ok(await page.evaluate(() => document.activeElement.matches('.digi-loop__more [role="button"][tabindex="0"]')), 'Tab llega al botón de «Cargar más» después de los tres títulos.');
  await page.keyboard.press('Enter');
  await page.waitForFunction(() => document.querySelectorAll('.digi-posts__item').length === 6);
  await page.focus('.digi-loop__more [role="button"]');
  const y = await page.evaluate(() => scrollY);
  await page.keyboard.press(' ');
  await page.waitForFunction(() => document.querySelectorAll('.digi-posts__item').length === 9);
  await sleep(100);
  ok(await page.evaluate((y) => scrollY === y && document.activeElement.textContent === 'Entrada 7', y), 'Intro y Espacio cargan la página siguiente (Espacio no desplaza la página) y el foco pasa al primer elemento nuevo.');
  await page.close();
}
// Mientras carga, el foco del teclado sigue en el botón: se ocultan los hijos de su contenido (lo que se ve es lo mismo que
// en PRO, que oculta el contenido entero), no el elemento enfocado, que el navegador dejaría sin foco (y con elementos sin
// enlaces, el foco acabaría en <body>).
{
  const page = await open('/click/1/', { delay: 400 });
  await page.focus('.digi-loop__more [role="button"]');
  await page.keyboard.press('Enter');
  await sleep(100);
  ok(await page.evaluate(() => document.querySelector('[data-id="w1"]').classList.contains('digi-loop--loading') && document.activeElement.matches('.digi-loop__more [role="button"]') && [...document.activeElement.children].every((e) => getComputedStyle(e).visibility === 'hidden')), 'Mientras carga, el foco sigue en el botón de «Cargar más» y su texto no se ve (como PRO).');
  await page.waitForFunction(() => document.querySelectorAll('.digi-posts__item').length === 6);
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
  ok(await count(page, 'button, .digi-loop__more') === 0, 'Infinita: sin botón.');
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
  // Como `arrangeGrid()` de PRO (corrección de fidelidad): durante 450 ms los que salen encogen y se desvanecen en su sitio
  // y los que se quedan se deslizan al suyo; al terminar, los que salen se ocultan. Con movimiento reducido, al instante.
  const rects = () => page.$$eval('.digi-portfolio__item', (l) => l.map((e) => { const r = e.getBoundingClientRect(); return [Math.round(r.left), Math.round(r.top)]; }));
  const before = await rects();
  await page.click('[data-filter="6"]');
  await sleep(200);
  if (!reduce) {
    const mid = await page.$$eval('.digi-portfolio__item', (l) => l.map((e) => { const c = getComputedStyle(e); return { hidden: e.hidden, anim: e.getAnimations().length, opacity: +c.opacity, t: c.transform }; }));
    ok(mid.every((m) => !m.hidden && 1 === m.anim) && [0, 4, 5].every((i) => mid[i].opacity < 1 && /matrix/.test(mid[i].t)) && [1, 2, 3].every((i) => 1 === mid[i].opacity && /matrix/.test(mid[i].t)), 'A mitad del filtro: los que salen encogen y se desvanecen sin dejar su sitio, los que se quedan se desplazan: ' + JSON.stringify(mid));
    await sleep(450);
  } else {
    ok(await page.$$eval('.digi-portfolio__item', (l) => l.every((e) => !e.getAnimations().length)), 'Con movimiento reducido no hay animación.');
  }
  ok(await visible() === '-XXX--', 'Filtrar por un término oculta los demás (también con varios términos por elemento).');
  const after = await rects();
  ok(JSON.stringify([after[1], after[2], after[3]]) === JSON.stringify([before[0], before[1], before[2]]) && await page.$$eval('.digi-portfolio__item', (l) => l.every((e) => !e.getAnimations().length && 'none' === getComputedStyle(e).transform)), 'Al terminar, los que quedan ocupan las primeras celdas, sin transformación ni animaciones: ' + JSON.stringify(after));
  ok(await page.$$eval('.digi-portfolio__filter', (l) => l.map((e) => e.getAttribute('aria-pressed')).join(',')) === 'false,false,true,false', 'aria-pressed marca el filtro elegido.');
  await page.click('[data-filter="__all"]');
  await sleep(100);
  if (!reduce) ok(await page.$$eval('.digi-portfolio__item', (l) => [0, 4, 5].every((i) => { const c = getComputedStyle(l[i]); return !l[i].hidden && l[i].getAnimations().length && +c.opacity < 1; })), 'Los que vuelven aparecen en su sitio y crecen desde 0,2 mientras los demás vuelven al suyo.');
  await sleep(reduce ? 50 : 500);
  ok(await visible() === 'XXXXXX' && JSON.stringify(await rects()) === JSON.stringify(before), '«All» devuelve todos, cada uno en su sitio.');
  // Un segundo filtro durante la animación termina el primero (sin elementos a medio animar).
  await page.click('[data-filter="5"]');
  await sleep(100);
  await page.click('[data-filter="7"]');
  await sleep(reduce ? 50 : 600);
  ok(await visible() === '----X-' || await visible() === (await page.$$eval('.digi-portfolio__item', (l) => l.map((e) => ((e.getAttribute('data-digi-terms') || '').split(' ').includes('7') ? 'X' : '-')).join(''))), 'Dos filtros seguidos: queda el último.');
  ok(await page.$$eval('.digi-portfolio__item', (l) => l.every((e) => !e.getAnimations().length && 'none' === getComputedStyle(e).transform && '1' === getComputedStyle(e).opacity)), 'Dos filtros seguidos: sin restos de la primera animación.');
  await page.click('[data-filter="__all"]');
  await sleep(reduce ? 50 : 600);
  const box = await page.$eval('.digi-portfolio__filter', (e) => { const r = e.getBoundingClientRect(); return r.width >= 24 && r.height >= 24; });
  ok(box, 'Con el relleno de PRO (8 px), el botón «All» mide 24 × 24 px o más (sin un mínimo propio: la cascada compara la caja con la de PRO).');
  await page.focus('[data-filter="__all"]');
  await page.keyboard.press('Tab');
  ok(await page.evaluate(() => document.activeElement.dataset.filter === '5' && getComputedStyle(document.activeElement).outlineStyle === 'solid' && getComputedStyle(document.activeElement).outlineOffset === '2px'), 'Foco visible en el filtro con el teclado (2 px con separación de 2 px).');
  await page.keyboard.press('Enter');
  await sleep(reduce ? 50 : 600);
  ok(await visible() === 'X-X-X-', 'El teclado activa el filtro (Intro).');
  await page.close();
}
// 4a. Imagen del Portafolio con la geometría de PRO (corrección de fidelidad): la misma caja de imagen que PRO, apaisada
// (ajustada al alto, `--fit-height`, que pone el script como `fitImage()`) y vertical (a todo el ancho).
{
  const page = await open('/portfolio-fit/');
  await page.waitForFunction(() => [...document.images].every((i) => i.complete && i.naturalWidth));
  await sleep(200);
  // Caja de la imagen respecto a su elemento (los dos widgets están uno debajo del otro).
  const geo = await page.evaluate(() => [0, 1].map((i) => ['pro', 'own'].map((k) => { const a = document.getElementById(k + i).getBoundingClientRect(), r = document.querySelector(`#${k}${i} img`).getBoundingClientRect(); return [r.left - a.left, r.top - a.top, r.width, r.height].map((v) => Math.round(v * 100) / 100); })));
  const fit = await page.$$eval('.of .digi-portfolio__img', (l) => l.map((e) => e.classList.contains('digi-portfolio__img--fit-height')));
  ok(fit[0] && !fit[1], 'portfolio.js ajusta al alto la imagen apaisada y no la vertical (como fitImage() de PRO): ' + JSON.stringify(fit));
  ok(geo.every(([a, b]) => a.every((v, j) => Math.abs(v - b[j]) <= 0.05)), 'La imagen ocupa la misma caja que en PRO (posición, ancho y alto): ' + JSON.stringify(geo));
  // En el editor la proporción cambia sin repintar: la caja cambia y el script vuelve a decidir.
  await page.evaluate(() => { document.querySelectorAll('.of .digi-portfolio__link').forEach((l) => { l.style.paddingBottom = '20%'; }); });
  await sleep(200);
  ok(JSON.stringify(await page.$$eval('.of .digi-portfolio__img', (l) => l.map((e) => e.classList.contains('digi-portfolio__img--fit-height')))) === '[false,false]', 'Con una caja más apaisada que la imagen, el script quita el ajuste al alto (ResizeObserver).');
  await page.close();
}
// 4b. Mampostería con la separación de PRO.
{
  const page = await open('/masonry2/');
  await sleep(300);
  const geo = (id) => page.$$eval(`[data-id="${id}"] [data-digi-items] > *`, (l) => l.map((e) => { const r = e.getBoundingClientRect(); return [r.top, r.bottom, e.style.marginTop]; }));
  const gaps = (g) => g.slice(3).map((x, i) => Math.round((x[0] - g[i][1]) * 100) / 100);
  // Con borde superior (me), PRO mide desde el borde exterior: la segunda fila sube el grosor del borde (35 − 3 px).
  const expect = { ma: [2, 2, 2, 2, 2, 2], mb: [0, 0, 0, 0, 0, 0], md: [0, 0, 0, 0, 0, 0], me: [32, 32, 32, 35, 35, 35] };
  for (const [id, want] of Object.entries(expect)) {
    const g = gaps(await geo(id));
    ok(g.length === 6 && g.every((v, i) => Math.abs(v - want[i]) <= 0.5), `Mampostería ${id}: separación de ${want.join(', ')} px en cada columna, como PRO (${g.join(', ')}).`);
  }
  ok(await page.$eval('[data-id="mb"] .digi-loop-grid__items', (e) => getComputedStyle(e).rowGap === '35px'), 'La rejilla de la Cuadrícula conserva su row-gap: sólo la mampostería usa 0 px (como PRO).');
  const mc = await geo('mc');
  ok(await page.$eval('[data-id="mc"] .digi-posts', (e) => e.classList.contains('digi-loop--masonry')) && mc.every((x) => x[2] === ''), 'Con una expresión (unidad «custom»), la clase de mampostería y ningún elemento sube (PRO: NaN).');
  await page.close();
}
// 6. Paginación: la barra propia mide y se ve como la de PRO (sin mínimo de 24 px en los números).
for (const size of [12, 16]) {
  for (const gap of [0, 10]) {
    const page = await open(`/pagination/?s=${size}&g=${gap}`);
    const bars = await page.evaluate(() => ['.elementor-pagination', '.digi-loop__pagination'].map((sel) => {
      const nav = document.querySelector(sel);
      const n = nav.getBoundingClientRect();
      const nums = [...nav.querySelectorAll(':scope > .page-numbers')].map((e) => { const r = e.getBoundingClientRect(); return [r.left - n.left, r.width, r.top - n.top, r.height]; });
      const after = document.getElementById(sel === '.elementor-pagination' ? 'after-a' : 'after-b').getBoundingClientRect().top - n.top;
      const hits = [...nav.querySelectorAll(':scope > a.page-numbers')].every((a) => { const r = a.getBoundingClientRect(); const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); return hit && hit.closest('.page-numbers') === a; });
      return { nums, h: n.height, after, hits, clip: { x: n.left, y: n.top + scrollY, width: n.width, height: n.height } };
    }));
    const [a, b] = bars;
    ok(a.nums.length === 8 && a.nums.every((x, i) => x.every((v, j) => Math.abs(v - b.nums[i][j]) < 0.01)), `Paginación a ${size} px con separación ${gap}: cada número en la misma posición y con el mismo ancho que PRO (${JSON.stringify(b.nums.map((x) => x.map(Math.round)))} frente a ${JSON.stringify(a.nums.map((x) => x.map(Math.round)))}).`);
    ok(Math.abs(a.h - b.h) < 0.01 && Math.abs(a.after - b.after) < 0.01, `Paginación a ${size} px con separación ${gap}: la barra mide lo mismo que la de PRO (${b.h} frente a ${a.h}) y lo siguiente empieza en el mismo sitio.`);
    ok(a.hits && b.hits, `Paginación a ${size} px con separación ${gap}: el centro de cada número abre su propio enlace.`);
    const [shotA, shotB] = [await page.screenshot({ clip: a.clip }), await page.screenshot({ clip: b.clip })];
    ok(Buffer.from(shotA).equals(Buffer.from(shotB)), `Paginación a ${size} px con separación ${gap}: los mismos píxeles que la barra de PRO.`);
    await page.close();
  }
}
// 7. Cascada: «Cargar más» y el filtro del Portafolio frente al marcado de PRO con los estilos del tema y del kit.
{
  const page = await open('/cascade/');
  const props = ['backgroundColor', 'backgroundImage', 'color', 'borderTopWidth', 'borderTopStyle', 'borderTopColor', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth', 'borderTopLeftRadius', 'borderBottomRightRadius', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft', 'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'lineHeight', 'letterSpacing', 'wordSpacing', 'textTransform', 'textDecorationLine', 'textShadow', 'textAlign', 'textIndent', 'whiteSpace', 'boxShadow', 'cursor', 'userSelect', 'outlineStyle', 'opacity'];
  const scenarios = {
    'Hello con colores globales': { reset: 1, kit: 0, doc: 1, typo: 0 },
    'Hello con «Desactivar colores predeterminados»': { reset: 1, kit: 0, doc: 0, typo: 0 },
    'Hello y un kit con botones': { reset: 1, kit: 1, doc: 1, typo: 0 },
    'Hello, kit con botones y tipografías propias': { reset: 1, kit: 1, doc: 1, typo: 1 },
    'otro tema y un kit con botones': { reset: 0, kit: 1, doc: 1, typo: 0 },
    'otro tema, kit con botones y sin colores predeterminados': { reset: 0, kit: 1, doc: 0, typo: 0 },
    'otro tema sin kit': { reset: 0, kit: 0, doc: 1, typo: 1 },
  };
  const read = (ids, skip) => page.evaluate((ids, props, skip) => ids.map((id) => {
    const el = document.getElementById(id);
    const cs = getComputedStyle(el);
    const box = el.closest('.elementor-widget-container').getBoundingClientRect();
    const r = el.getBoundingClientRect();
    const out = { rect: [r.left - box.left, r.top - box.top, r.width, r.height].map((v) => Math.round(v * 100) / 100), wrap: Math.round(box.height * 100) / 100 };
    for (const p of props) if (!skip.includes(p)) out[p] = cs[p];
    return out;
  }), ids, props, skip);
  const pairs = [['pro-more', 'own-more'], ['pro-f0', 'own-f0'], ['pro-f1', 'own-f1'], ['pro-f2', 'own-f2'], ['pro-f4', 'own-f4']];
  const compare = async (label, skip = []) => {
    const a = await read(pairs.map((p) => p[0]), skip);
    const b = await read(pairs.map((p) => p[1]), skip);
    pairs.forEach(([pa, pb], i) => {
      const diff = Object.keys(a[i]).filter((k) => JSON.stringify(a[i][k]) !== JSON.stringify(b[i][k])).map((k) => `${k}: ${JSON.stringify(a[i][k])} → ${JSON.stringify(b[i][k])}`);
      ok(diff.length === 0, `${label}: ${pb} se ve como ${pa} de PRO (${diff.join('; ')}).`);
    });
  };
  for (const [name, s] of Object.entries(scenarios)) {
    await page.evaluate((s) => { for (const k of Object.keys(s)) document.getElementById(k).disabled = !s[k]; }, s);
    await page.mouse.move(0, 0);
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
    await compare(`${name}, en reposo`);
    for (const [pa, pb] of pairs) {
      await page.hover('#' + pa);
      const a = (await read([pa], []))[0];
      await page.hover('#' + pb);
      const b = (await read([pb], []))[0];
      const diff = Object.keys(a).filter((k) => JSON.stringify(a[k]) !== JSON.stringify(b[k])).map((k) => `${k}: ${JSON.stringify(a[k])} → ${JSON.stringify(b[k])}`);
      ok(diff.length === 0, `${name}, al pasar el cursor: ${pb} se ve como ${pa} de PRO (${diff.join('; ')}).`);
    }
    // Al pulsar con el ratón: el filtro de PRO (<li tabindex>) y el propio (<button>) quedan con el foco; «Cargar más»
    // de PRO no se enfoca y el propio enfoca su contenido, que no cambia el enlace.
    for (const [pa, pb] of pairs) {
      await page.click('#' + pa);
      const a = (await read([pa], []))[0];
      await page.click('#' + pb);
      const b = (await read([pb], []))[0];
      const diff = Object.keys(a).filter((k) => JSON.stringify(a[k]) !== JSON.stringify(b[k])).map((k) => `${k}: ${JSON.stringify(a[k])} → ${JSON.stringify(b[k])}`);
      ok(diff.length === 0, `${name}, al pulsar: ${pb} se ve como ${pa} de PRO (${diff.join('; ')}).`);
      await page.mouse.move(0, 0);
    }
    // Con el teclado: sólo cambian el contorno y la sombra del foco (diferencia declarada 1).
    await page.keyboard.press('Shift');
    for (const [pa, pb] of pairs.slice(1)) {
      await page.focus('#' + pa);
      const a = (await read([pa], ['outlineStyle', 'boxShadow']))[0];
      await page.focus('#' + pb);
      const visible = await page.evaluate(() => document.activeElement.matches(':focus-visible'));
      const b = (await read([pb], ['outlineStyle', 'boxShadow']))[0];
      const diff = Object.keys(a).filter((k) => JSON.stringify(a[k]) !== JSON.stringify(b[k])).map((k) => `${k}: ${JSON.stringify(a[k])} → ${JSON.stringify(b[k])}`);
      ok(visible && diff.length === 0, `${name}, con el teclado: ${pb} se ve como ${pa} de PRO salvo el contorno de foco (${diff.join('; ')}).`);
    }
    await page.focus('#own-more > [role="button"]');
    ok(await page.evaluate(() => getComputedStyle(document.querySelector('#own-more > [role="button"]')).outlineStyle === 'solid'), `${name}: el botón de «Cargar más» muestra su contorno de foco con el teclado.`);
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
  }
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
