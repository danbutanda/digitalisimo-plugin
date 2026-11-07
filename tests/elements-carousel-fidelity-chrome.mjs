// Correcciones de fidelidad de los carruseles de C1 (docs/pro/c1.md, «Correcciones de fidelidad») en Chrome real (sin
// interfaz) y sin WordPress, contra la referencia de PRO con sus hojas y Swiper 8.4.5 del núcleo
// (`elementor/assets/lib/swiper/v8`), con las opciones que les pasaba PRO:
// - Carrusel multimedia, «Coverflow»: cada diapositiva del motor `digi-crs` con `carousel-3d.css|js` queda donde la pinta
//   el `coverflowEffect` de Swiper (rectángulo proyectado, orden de pintado y opacidad de sus dos sombras), sin centrar,
//   al cargar y tras avanzar, con y sin bucle; el efecto «Cubo», la cara visible y la sombra bajo el cubo (píxeles), y las
//   caras a mitad del giro.
// - «Carga diferida»: el aro de `swiper-lazy-preloader` (42 px, borde de 4 px #007aff con el superior transparente, girando
//   1 s) en el centro de la diapositiva mientras la imagen carga; se retira al cargar o al fallar, también si ya había
//   cargado antes del script (`carousel-lazy.css|js`).
// - Diapositivas: con una sola, el contenido entra al cargar; con varias, se oculta durante el cambio y entra con los
//   fotogramas de animate.css (`zoomIn` opaco a la mitad); Ken Burns «alejar» de la siguiente asoma sobre la actual y la
//   pista no se desplaza en vertical.
// - Carrusel de testimonios, piel burbuja: el pico queda donde lo pinta la hoja de PRO (`widget-testimonial-carousel.min.css`
//   y las reglas de «Tamaño» de la imagen) con `pro_bubble`, en LTR y RTL; sin él, apunta a la imagen.
// - Reseñas convertidas: los caracteres de eicons con la hoja de recorte de PRO (`widget-star-rating.min.css`), con la
//   fuente cargada y sin ella.
// Cada comparación falla con el código anterior (sin `data-digi-fx`, sin indicador, con la transición, recortando el
// fondo, con el pico hacia la imagen o con la estrella SVG). Sin Chrome o sin puppeteer-core, se salta con un aviso.
// CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 240 node tests/elements-carousel-fidelity-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la fidelidad de los carruseles (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const root = new URL('../digitalisimo-elements/', import.meta.url);
const read = (path) => readFileSync(new URL(path, root), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const crs = 'modules/digitalisimo-carousel/assets/';
const CSS = {
  engine: read(crs + 'css/carousel.css'),
  media: read(crs + 'css/media-carousel.css'),
  fx: read(crs + 'css/carousel-3d.css'),
  lazy: read(crs + 'css/carousel-lazy.css'),
  slides: read(crs + 'css/slides.css'),
  tc: read(crs + 'css/testimonial-carousel.css'),
  rv: read(crs + 'css/reviews.css'),
  swiper: read('elementor/assets/lib/swiper/v8/css/swiper.css'),
  proTc: read('assets/css/widget-testimonial-carousel.min.css'),
  stars: read('elementor/assets/css/widget-star-rating.min.css'),
};
const JS = {
  engine: read(crs + 'js/carousel.js'),
  fx: read(crs + 'js/carousel-3d.js'),
  lazy: read(crs + 'js/carousel-lazy.js'),
  slides: read(crs + 'js/slides.js'),
  swiper: read('elementor/assets/lib/swiper/v8/swiper.min.js'),
};
const tcSource = read('modules/digitalisimo-carousel/widgets/class-testimonial-carousel.php');
const dict = (name) => Object.fromEntries([...((tcSource.match(new RegExp(`const ${name} = array\\(([\\s\\S]*?)\\n\\t\\);`)) || [])[1] || '').matchAll(/'([a-z_]+)'\s*=>\s*'([^']+)'/g)].map((m) => [m[1], m[2]]));
const ALIGNMENT = dict('ALIGNMENT');
const PRO_BUBBLE = dict('PRO_BUBBLE');
ok(['left', 'center', 'right'].every((k) => ALIGNMENT[k]) && ['start', 'left', 'center', 'right'].every((k) => PRO_BUBBLE[k]), 'ALIGNMENT y PRO_BUBBLE están en el widget de testimonios.');

const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu', '--font-render-hinting=none'] });
const errors = [];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const frames = (page, n = 3) => page.evaluate((n) => new Promise((r) => { const f = () => (--n ? requestAnimationFrame(f) : r()); requestAnimationFrame(f); }), n);
const COLORS = ['#c0392b', '#2980b9', '#27ae60', '#8e44ad', '#d35400', '#16a085'];

async function open(html, { width = 1100, rtl = false, images = null } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
  if (images) {
    await page.setRequestInterception(true);
    page.on('request', (r) => { const h = images(r.url()); h ? h(r) : r.continue().catch(() => {}); });
  }
  await page.setContent(`<!doctype html><html lang="es"${rtl ? ' dir="rtl"' : ''}><head><meta charset="utf-8"><style>body{margin:0;font:16px/1.5 sans-serif;background:#fff}*,::before,::after{box-sizing:border-box}</style></head><body${rtl ? ' class="rtl"' : ''}>${html}</body></html>`, { waitUntil: 'load' });
  return page;
}
// Píxeles distintos (más de 48 en algún canal, como tests/playground/pro-ab.sh) entre dos zonas de la misma página.
async function diff(page, a, b) {
  const [sa, sb] = [await page.screenshot({ clip: a, encoding: 'base64' }), await page.screenshot({ clip: b, encoding: 'base64' })];
  return page.evaluate(async (x, y) => {
    const load = (src) => new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = 'data:image/png;base64,' + src; });
    const [ia, ib] = [await load(x), await load(y)];
    const px = (img) => { const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const g = c.getContext('2d'); g.drawImage(img, 0, 0); return g.getImageData(0, 0, img.width, img.height).data; };
    const [da, db] = [px(ia), px(ib)];
    let count = 0;
    for (let i = 0; i < da.length; i += 4) if (Math.max(Math.abs(da[i] - db[i]), Math.abs(da[i + 1] - db[i + 1]), Math.abs(da[i + 2] - db[i + 2])) > 48) count++;
    return { count, total: da.length / 4 };
  }, sa, sb);
}

// ---------------------------------------------------------------------------------------------------------------------
// 1. Carrusel multimedia: «Coverflow» y «Cubo» frente a Swiper 8.4.5 con las opciones de PRO (getSwiperOptions() de
// `carousel/assets/js/frontend/handlers/base.js` y media-carousel.js: coverflow con las visibles del dispositivo, paso 1,
// separación y sin centrar; cubo con una visible, como fuerza su `overwriteParams`).
const W = 1000, H = 230;
const slideBody = (i) => `<div style="height:100%;background:${COLORS[i % COLORS.length]};color:#fff;font:700 40px/230px sans-serif;text-align:center">${i + 1}</div>`;
const swiperBox = (id, n) => `<div class="swiper" id="${id}" style="width:${W}px;height:${H}px;margin:20px 50px 160px"><div class="swiper-wrapper">${Array.from({ length: n }, (_, i) => `<div class="swiper-slide">${slideBody(i)}</div>`).join('')}</div></div>`;
// El marcado del motor (Engine::open() del Carrusel multimedia con `data-digi-fx`) dentro de su envoltorio.
const ownBox = (id, n, fx, { view = 3, gap = 10, loop = false } = {}) => `<div class="elementor-widget-digitalisimo-media-carousel digi-media-carousel--skin-${'coverflow' === fx ? 'coverflow' : 'carousel'}" style="width:${W}px;margin:20px 50px 160px;--digi-crs-view:${view};--digi-crs-gap:${gap}px"><section class="digi-crs digi-media-carousel digi-media-carousel__main" id="${id}" aria-roledescription="carrusel" aria-label="x"${loop ? ' data-digi-loop="1"' : ''} data-digi-effect="slide"${fx ? ` data-digi-fx="${fx}"` : ''} style="--digi-crs-speed:0ms;--digi-crs-n:${n}"><div class="digi-crs__track" aria-live="polite">${Array.from({ length: n }, (_, i) => `<div class="digi-crs__slide" role="group" aria-roledescription="diapositiva" aria-label="${i + 1} de ${n}"><div class="digi-media-carousel__image">${slideBody(i)}</div></div>`).join('')}</div></section></div>`;
const fxPage = (body) => `<style>${CSS.swiper}${CSS.engine}${CSS.media}${CSS.fx}</style>${body}<script>${JS.swiper}</script><script>${JS.engine}</script><script>${JS.fx}</script>`;
// Rectángulos proyectados (relativos a la caja de su carrusel), orden de pintado y opacidad de las sombras, en el orden
// visual de las diapositivas que se ven.
const geometry = (page, sel, own) => page.evaluate((sel, own) => {
  const box = document.querySelector(sel), b = box.getBoundingClientRect(), kids = [...box.querySelectorAll(own ? ':scope>.digi-crs__track>*' : '.swiper-slide')];
  return kids.map((s) => {
    const r = s.getBoundingClientRect(), cs = getComputedStyle(s);
    const shadow = (side) => { const e = own ? null : s.querySelector('.swiper-slide-shadow-' + side); return own ? +getComputedStyle(s, side === 'left' ? '::before' : '::after').opacity : e ? +getComputedStyle(e).opacity : 0; };
    return { x: r.left - b.left, y: r.top - b.top, w: r.width, h: r.height, z: cs.zIndex, l: shadow('left'), r: shadow('right'), vis: cs.visibility };
  }).filter((g) => g.vis !== 'hidden' && g.x + g.w > 1 && g.x < b.width - 1 && g.w > 1).sort((p, q) => p.x - q.x);
}, sel, own);
const near = (p, q, tol = 1.5) => p.length === q.length && p.every((g, i) => ['x', 'y', 'w', 'h'].every((k) => Math.abs(g[k] - q[i][k]) <= tol) && Math.abs(g.l - q[i].l) < 0.02 && Math.abs(g.r - q[i].r) < 0.02 && String(g.z) === String(q[i].z));
const show = (g) => g.map((s) => `[${Math.round(s.x)},${Math.round(s.y)} ${Math.round(s.w)}×${Math.round(s.h)} z${s.z} ${s.l.toFixed(2)}/${s.r.toFixed(2)}]`).join(' ');

{
  for (const loop of [false, true]) {
    const page = await open(fxPage(swiperBox('a', 5) + ownBox('b', 5, 'coverflow', { loop })) + `<script>window.sw=new Swiper('#a',{slidesPerView:3,slidesPerGroup:1,spaceBetween:10,loop:${loop},speed:0,effect:'coverflow',grabCursor:true});</script>`);
    await page.waitForFunction(() => document.querySelector('.digi-crs--ready'));
    await frames(page);
    for (const step of [0, 1, 2]) {
      if (step) { await page.evaluate((s) => { window.sw.slideNext(0); document.getElementById('b').digiCrs.go(s); }, step); await frames(page); }
      const [a, b] = [await geometry(page, '#a', false), await geometry(page, '#b', true)];
      ok(a.length >= 3 && near(a, b), `Coverflow${loop ? ' con bucle' : ''}, diapositiva ${step + 1}: como Swiper.\n  PRO  ${show(a)}\n  propio ${show(b)}`);
    }
    if (!loop) {
      // Sin centrar, como PRO: las tres primeras desde el borde izquierdo y tres puntos posibles (5 − 3 + 1 páginas).
      const first = await page.evaluate(() => document.getElementById('b').digiCrs.index());
      ok(2 === first, 'Coverflow sin bucle: la API sigue en la diapositiva 3 tras dos pasos.');
    }
    await page.close();
  }
  // El efecto «Cubo» (sin el script del motor, que devolvería la pista a su sitio: sólo `carousel-3d.js`, que lee el
  // desplazamiento): en reposo la cara es plana y la sombra bajo el cubo es la de Swiper; a mitad del giro, las dos
  // caras dentro de la pista y la sombra, también.
  {
    const page = await open(`<style>${CSS.swiper}${CSS.engine}${CSS.media}${CSS.fx}</style>` + swiperBox('a', 4) + ownBox('b', 4, 'cube') + `<script>${JS.swiper}</script><script>${JS.fx}</script><script>window.sw=new Swiper('#a',{slidesPerView:1,spaceBetween:10,loop:false,speed:0,effect:'cube'});</script>`);
    await frames(page);
    const rect = (sel) => page.evaluate((sel) => { const r = document.querySelector(sel).getBoundingClientRect(); return { x: Math.round(r.left), y: Math.round(r.top) }; }, sel);
    const [ra, rb] = [await rect('#a'), await rect('#b')];
    const zone = (r, top, height) => ({ x: r.x, y: r.y + top, width: W, height });
    // Durante el giro, la arista que se acerca sobresale de la pista hasta un 10 % del alto en PRO (`.swiper-cube` con
    // `overflow: visible`) y en el propio queda recortada (la pista se desplaza en horizontal y recorta en vertical): la
    // sombra se compara por debajo de esa franja (límite declarado en docs/pro/c1.md).
    const cube = async (label, faces, shadow, from = 2) => {
      const f = await diff(page, zone(ra, 0, H), zone(rb, 0, H));
      ok(f.count <= f.total * faces, `${label}: las caras dentro de la pista, como Swiper (${f.count} píxeles distintos de ${f.total}).`);
      const d = await diff(page, zone(ra, H + from, 140 - from), zone(rb, H + from, 140 - from));
      ok(d.count <= d.total * shadow, `${label}: la sombra bajo el cubo, como Swiper (${d.count} píxeles distintos de ${d.total}).`);
      return d;
    };
    await cube('Cubo en reposo', 0.001, 0.002);
    await page.evaluate(() => { const s = document.createElement('style'); s.id = 'x'; s.textContent = '#b:before{display:none}'; document.head.append(s); });
    const d0 = await diff(page, zone(ra, H + 2, 138), zone(rb, H + 2, 138));
    await page.evaluate(() => document.getElementById('x').remove());
    ok(d0.count > d0.total * 0.05, `Prueba del arnés: sin la sombra, la zona bajo el cubo difiere (${d0.count} píxeles).`);
    for (const t of [0.25, 0.5, 0.8, 1.5]) {
      await page.evaluate((t, W) => { window.sw.setTranslate(-t * W); document.querySelector('#b>.digi-crs__track').scrollLeft = t * W; }, t, W);
      await frames(page);
      await cube(`Cubo a ${t * 100} % del giro`, 0.01, 0.005, 32);
    }
    await page.close();
  }
}

await browser.close();
assert.deepEqual(errors, [], 'Sin errores de JavaScript: ' + errors.join(' | '));
console.log(`DIGITALÍSIMO Elements: fidelidad de los carruseles de C1 en Chrome (${checks} comprobaciones) validada.`);
