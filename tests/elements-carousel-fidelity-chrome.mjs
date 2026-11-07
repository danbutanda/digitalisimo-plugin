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

async function open(html, { width = 1100, rtl = false, images = null, wait = 'load', reduce = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  await page.setViewport({ width, height: 900, deviceScaleFactor: 1 });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  if (images) {
    await page.setRequestInterception(true);
    page.on('request', (r) => { const h = images(r.url()); h ? h(r) : r.continue().catch(() => {}); });
  }
  await page.setContent(`<!doctype html><html lang="es"${rtl ? ' dir="rtl"' : ''}><head><meta charset="utf-8"><style>body{margin:0;font:16px/1.5 sans-serif;background:#fff}*,::before,::after{box-sizing:border-box}</style></head><body${rtl ? ' class="rtl"' : ''}>${html}</body></html>`, { waitUntil: wait });
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

// ---------------------------------------------------------------------------------------------------------------------
// 2. «Carga diferida»: el aro de Swiper (`swiper-lazy-preloader`, en PRO dentro del envoltorio de la imagen y centrado en
// la diapositiva —testimonios y reseñas— o en la imagen —multimedia—) mientras la imagen carga; se retira al cargar, al
// fallar y, si ya había cargado, al llegar el script.
{
  const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==', 'base64');
  let release = null;
  const gate = new Promise((r) => { release = r; });
  const images = (url) => {
    if (url.endsWith('/slow.png')) return (r) => gate.then(() => r.respond({ status: 200, contentType: 'image/png', body: PNG }).catch(() => {}));
    if (url.endsWith('/broken.png')) return (r) => r.respond({ status: 404, body: '' }).catch(() => {});
    if (url.endsWith('/ok.png')) return (r) => r.respond({ status: 200, contentType: 'image/png', body: PNG }).catch(() => {});
    return null;
  };
  const tSlide = (src, id) => `<div class="digi-crs__slide" role="group"><div class="digi-testimonial"><div class="digi-testimonial__content"><div class="digi-testimonial__text">Texto de prueba con varias palabras para que la diapositiva tenga alto.</div></div><div class="digi-testimonial__footer"><div class="digi-testimonial__image"><img id="${id}" src="http://img.test/${src}" width="50" height="50" alt=""><span class="digi-crs__preloader" aria-hidden="true"></span></div><cite class="digi-testimonial__cite"><span class="digi-testimonial__name">Ana</span></cite></div></div></div>`;
  const proSlide = `<div class="swiper-slide"><div class="elementor-testimonial"><div class="elementor-testimonial__content"><div class="elementor-testimonial__text">Texto de prueba con varias palabras para que la diapositiva tenga alto.</div></div><div class="elementor-testimonial__footer"><div class="elementor-testimonial__image"><img class="swiper-lazy" alt=""><div class="swiper-lazy-preloader"></div></div><cite class="elementor-testimonial__cite"><span class="elementor-testimonial__name">Ana</span></cite></div></div></div>`;
  const page = await open(`<style>${CSS.swiper}${CSS.proTc}${CSS.engine}${CSS.tc}${CSS.lazy}</style><div class="elementor-widget-testimonial-carousel" style="width:600px;margin:20px"><div class="swiper" id="pro"><div class="swiper-wrapper">${proSlide}</div></div></div><div class="elementor-widget-digitalisimo-testimonial-carousel" style="width:600px;margin:20px"><section class="digi-crs digi-testimonials" id="own" data-digi-effect="slide" style="--digi-crs-speed:0ms;--digi-crs-n:3"><div class="digi-crs__track">${tSlide('slow.png', 'i1')}${tSlide('broken.png', 'i2')}${tSlide('slow.png', 'i3')}</div></section></div><script>${JS.engine}</script><script>${JS.lazy}</script>`, { images, wait: 'domcontentloaded' });
  const ring = () => page.evaluate(() => {
    const at = (p, s) => { const a = p.getBoundingClientRect(), b = s.getBoundingClientRect(), c = getComputedStyle(p); return { dx: Math.round(a.left + a.width / 2 - b.left - b.width / 2), dy: Math.round(a.top + a.height / 2 - b.top - b.height / 2), w: p.offsetWidth, h: p.offsetHeight, border: c.borderLeftWidth + ' ' + c.borderLeftColor, top: c.borderTopColor, anim: c.animationName + ' ' + c.animationDuration + ' ' + c.animationIterationCount + ' ' + c.animationTimingFunction }; };
    const pro = document.querySelector('#pro .swiper-lazy-preloader'), own = document.querySelector('#own .digi-crs__preloader');
    return { pro: at(pro, pro.closest('.swiper-slide')), own: own && at(own, own.closest('.digi-crs__slide')), left: document.querySelectorAll('#own .digi-crs__preloader').length };
  });
  await page.waitForFunction(() => document.querySelectorAll('#own .digi-crs__preloader').length === 2);
  const r = await ring();
  ok(2 === r.left, `La imagen que falla retira su indicador; las que cargan lo conservan (${r.left}).`);
  ok(42 === r.own.w && 42 === r.own.h && '4px rgb(0, 122, 255)' === r.own.border && 'rgba(0, 0, 0, 0)' === r.own.top && r.pro.border === r.own.border && r.pro.top === r.own.top, `El aro de Swiper: 42 px, borde de 4 px azul con el superior transparente (PRO ${r.pro.w}×${r.pro.h} ${r.pro.border}; propio ${r.own.w}×${r.own.h} ${r.own.border}).`);
  ok(r.own.anim.startsWith('digi-crs-spin 1s infinite linear') && r.pro.anim.includes('1s infinite linear'), `Gira 1 s lineal sin fin, como el de Swiper (${r.own.anim} / ${r.pro.anim}).`);
  ok(r.pro.dx === r.own.dx && r.pro.dy === r.own.dy && 0 === r.own.dx && Math.abs(r.own.dy) <= 1, `En el centro de la diapositiva, como PRO (PRO ${r.pro.dx},${r.pro.dy}; propio ${r.own.dx},${r.own.dy}).`);
  release();
  await page.waitForFunction(() => !document.querySelector('#own .digi-crs__preloader'), { timeout: 5000 }).then(() => ok(true, 'Al cargar las imágenes se retiran sus indicadores.'), () => ok(false, 'Al cargar las imágenes se retiran sus indicadores.'));
  await page.close();
  // Una imagen que ya había cargado cuando llega el script; y la multimedia, centrada en la imagen.
  const late = await open(`<style>${CSS.engine}${CSS.media}${CSS.lazy}</style><div class="elementor-widget-digitalisimo-media-carousel digi-media-carousel--skin-carousel" style="width:600px;margin:20px"><section class="digi-crs digi-media-carousel digi-media-carousel__main" data-digi-effect="slide" style="--digi-crs-speed:0ms;--digi-crs-n:2"><div class="digi-crs__track"><div class="digi-crs__slide"><div class="digi-media-carousel__image"><img class="digi-media-carousel__img" src="http://img.test/ok.png" alt=""><span class="digi-crs__preloader" aria-hidden="true"></span></div></div><div class="digi-crs__slide"><div class="digi-media-carousel__image"><img class="digi-media-carousel__img" alt=""><span class="digi-crs__preloader" aria-hidden="true"></span></div></div></div></section></div>`, { images });
  const centered = await late.evaluate(() => { const p = document.querySelector('.digi-crs__preloader'), a = p.getBoundingClientRect(), b = p.parentNode.getBoundingClientRect(); return Math.abs(a.left + a.width / 2 - b.left - b.width / 2) < 1 && Math.abs(a.top + a.height / 2 - b.top - b.height / 2) < 1; });
  ok(centered, 'Carrusel multimedia: el aro en el centro de la imagen (como en `.elementor-carousel-image`).');
  await late.addScriptTag({ content: JS.lazy });
  ok(0 === await late.evaluate(() => document.querySelectorAll('.digi-crs__preloader').length), 'Las imágenes que ya habían cargado (o sin dirección) retiran su indicador al llegar el script.');
  await late.close();
}

// ---------------------------------------------------------------------------------------------------------------------
// 3. Diapositivas: animación del contenido como PRO (los fotogramas de animate.css del núcleo durante 1,25 s, la clase
// `.animated`) y Ken Burns «alejar» de la siguiente sobre la actual.
{
  const core = (name) => read(`elementor/assets/lib/animations/styles/${name}.min.css`);
  const slide = (i, kb = '') => `<div class="digi-crs__slide digi-slides__slide" role="group"><div class="digi-slides__bg${kb}" style="background:${COLORS[i]}"></div><div class="digi-slides__inner"><div class="digi-slides__contents"><div class="digi-slides__heading">Título ${i + 1}</div></div></div></div>`;
  const root = (id, n, anim, speed, extra = '') => `<div class="elementor-widget-digitalisimo-slides" style="width:800px;height:300px;margin:10px"><section class="digi-crs digi-slides" id="${id}" data-digi-effect="slide" data-digi-animation="${anim}" style="--digi-crs-speed:${speed}ms;--digi-crs-n:${n};--digi-crs-height:300px"><div class="digi-crs__track">${Array.from({ length: n }, (_, i) => slide(i, i === 1 ? extra : '')).join('')}</div></section></div>`;
  const ref = `<style>.animated{animation-duration:1.25s}${core('zoomIn')}${core('fadeInUp')}</style><div id="ref-zoom" class="animated zoomIn" style="width:100px;height:20px;background:#000"></div><div id="ref-up" class="animated fadeInUp" style="width:100px;height:20px;background:#000"></div>`;
  const page = await open(`<style>${CSS.engine}${CSS.slides}</style>${ref}${root('one', 1, 'fadeInUp', 500)}${root('many', 3, 'zoomIn', 300)}<script>${JS.engine}</script><script>${JS.slides}</script>`);
  await page.waitForFunction(() => document.querySelectorAll('.digi-crs--ready').length === 2);
  const anim = (sel) => page.evaluate((sel) => { const a = document.querySelector(sel).getAnimations()[0]; return a ? a.animationName + ' ' + a.effect.getTiming().duration : ''; }, sel);
  ok('digi-slides-fadeInUp 1250' === await anim('#one .digi-slides__contents'), 'Una diapositiva única: el contenido entra al cargar con fadeInUp de 1,25 s, como `initSingleSlideAnimations()` de PRO.');
  ok('' === await anim('#many .digi-slides__contents'), 'Con varias, la diapositiva inicial no se anima (como PRO).');
  await page.evaluate(() => document.getElementById('many').digiCrs.go(1));
  ok('none' === await page.evaluate(() => getComputedStyle(document.querySelector('#many .digi-crs__slide:nth-child(2) .digi-slides__contents')).display), 'Durante el cambio, el contenido se oculta (`hide()` de PRO).');
  await page.waitForFunction(() => getComputedStyle(document.querySelector('#many .digi-crs__slide:nth-child(2) .digi-slides__contents')).display !== 'none', { timeout: 3000 });
  ok('digi-slides-zoomIn 1250' === await anim('#many .digi-crs__slide:nth-child(2) .digi-slides__contents'), 'Al terminar el cambio, entra con zoomIn de 1,25 s.');
  // Los mismos valores que los fotogramas del núcleo en cada instante (también la fase intermedia de «Zoom»).
  for (const [own, core] of [['#many .digi-crs__slide:nth-child(2) .digi-slides__contents', '#ref-zoom'], ['#one .digi-slides__contents', '#ref-up']]) {
    for (const t of [0, 300, 625, 1000]) {
      const v = await page.evaluate((own, core, t) => {
        const at = (sel) => { const e = document.querySelector(sel), a = e.getAnimations()[0]; a.pause(); a.currentTime = t; const c = getComputedStyle(e); return (+c.opacity).toFixed(3) + ' ' + c.transform.replace(/matrix\(([^)]*)\)/, (m, x) => 'matrix(' + x.split(',').map((n) => (+n).toFixed(3) + (/^-?\d/.test(n.trim()) && n.includes('%') ? '' : '')).slice(0, 4).join(',') + ')'); };
        return [at(own), at(core)];
      }, own, core, t);
      ok(v[0].split(' ')[0] === v[1].split(' ')[0] && (v[0].includes('matrix') ? v[0].split(' ')[1] === v[1].split(' ')[1] : true), `${core} a ${t} ms: opacidad y escala del propio (${v[0]}) como animate.css del núcleo (${v[1]}).`);
    }
  }
  ok('1.000' === (await page.evaluate(() => { const e = document.querySelector('#many .digi-crs__slide:nth-child(2) .digi-slides__contents'), a = e.getAnimations()[0]; a.currentTime = 625; return (+getComputedStyle(e).opacity).toFixed(3); })), '«Zoom» llega opaco a la mitad (zoomIn de animate.css).');
  await page.close();
  // Movimiento reducido: ni se oculta ni se anima.
  const still = await open(`<style>${CSS.engine}${CSS.slides}</style>${root('one', 1, 'fadeInUp', 500)}${root('many', 3, 'zoomIn', 300)}<script>${JS.engine}</script><script>${JS.slides}</script>`, { reduce: true });
  await still.waitForFunction(() => document.querySelectorAll('.digi-crs--ready').length === 2);
  await still.evaluate(() => document.getElementById('many').digiCrs.go(1));
  ok(await still.evaluate(() => [...document.querySelectorAll('.digi-slides__contents')].every((e) => !e.getAnimations().length && getComputedStyle(e).display !== 'none')), 'Con movimiento reducido el contenido ni se oculta ni se anima.');
  await still.close();
  // Ken Burns «alejar» en la segunda: su fondo ampliado (1,3) asoma sobre el 15 % derecho de la primera, como en PRO, y
  // la pista no se desplaza en vertical.
  const kb = await open(`<style>${CSS.engine}${CSS.slides}</style>${root('kb', 3, '', 500, ' digi-slides__bg--kb digi-slides__bg--kb-out')}<script>${JS.engine}</script>`);
  await kb.waitForFunction(() => document.querySelector('.digi-crs--ready'));
  const strip = await kb.evaluate(() => { const T = document.querySelector('#kb>.digi-crs__track'); return { vertical: T.scrollHeight - T.clientHeight, w: T.clientWidth }; });
  ok(0 === strip.vertical, `La pista no se desplaza en vertical con el fondo ampliado (${strip.vertical} px de más).`);
  const shot = await kb.screenshot({ clip: { x: 10, y: 10, width: 800, height: 300 }, encoding: 'base64' });
  const px = await kb.evaluate(async (src, points) => { const i = new Image(); await new Promise((r) => { i.onload = r; i.src = 'data:image/png;base64,' + src; }); const c = document.createElement('canvas'); c.width = i.width; c.height = i.height; const g = c.getContext('2d'); g.drawImage(i, 0, 0); return points.map(([x, y]) => [...g.getImageData(x, y, 1, 1).data].slice(0, 3).map((v) => v.toString(16).padStart(2, '0')).join('')); }, shot, [[790, 150], [700, 150], [400, 150]]);
  ok(px[0] === COLORS[1].slice(1) && px[1] === COLORS[1].slice(1) && px[2] === COLORS[0].slice(1), `El fondo «alejar» de la segunda asoma sobre el borde derecho de la primera, como en PRO (${px.join(', ')}).`);
  await kb.close();
}

// ---------------------------------------------------------------------------------------------------------------------
// 4. Carrusel de testimonios, piel burbuja: el pico de lo convertido (`pro_bubble`, que fija el mapa con la alineación de
// escritorio) queda donde lo pinta la hoja de PRO, con y sin «Tamaño» de la imagen (sus reglas, tomadas del widget de PRO)
// y en RTL. El pico se pinta en rojo y se compara el centro de sus píxeles respecto al contenido.
{
  const proPhp = read('modules/carousel/widgets/testimonial-carousel.php');
  // Los `selectors` de un control, tal como están en el código (las claves pueden ocupar varias líneas).
  const selectors = (php, name) => {
    const rest = php.slice(php.search(new RegExp(`add_(?:responsive_)?control\\(\\s*'${name}',`)));
    const sel = rest.slice(rest.indexOf("'selectors'"));
    return [...sel.slice(0, sel.search(/\n\t\t\t\t(?:\]|\)),/)).matchAll(/'([^']+)'\s*=>\s*'([^']+)'/g)].map((m) => [m[1], m[2]]);
  };
  const fill = (rules, id, size) => rules.map(([sel, decl]) => `${sel.replace(/\{\{WRAPPER\}\}/g, id)}{${decl.replace(/\{\{SIZE\}\}\{\{UNIT\}\}/g, size).replace(/\{\{text_padding\.(TOP|RIGHT|BOTTOM|LEFT)\}\}\{\{text_padding\.UNIT\}\}/g, '20px')}}`).join('\n');
  const proSize = selectors(proPhp, 'image_size');
  const ownSize = selectors(tcSource, 'image_size');
  const ownBubble = selectors(tcSource, 'pro_bubble');
  ok(proSize.length >= 5 && 2 === ownSize.length && 2 === ownBubble.length, `Se leen las reglas de «Tamaño» de PRO (${proSize.length}) y las propias (${ownSize.length}, pro_bubble ${ownBubble.length}).`);
  const body = (p, cls) => `<div class="${p}"><div class="${p}__content"><div class="${p}__text">Una opinión breve de prueba.</div></div><div class="${p}__footer"><div class="${p}__image"><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt=""></div><cite class="${p}__cite"><span class="${p}__name">Ana</span></cite></div></div>`;
  const cases = [];
  for (const rtl of [false, true]) {
    for (const desk of ['left', 'center', 'right', '']) {
      for (const size of ['', '80px']) cases.push({ rtl, desk, dev: '', size });
    }
    cases.push({ rtl, desk: 'center', dev: 'right', size: '' }, { rtl, desk: 'right', dev: 'left', size: '80px' }, { rtl, desk: 'left', dev: 'right', size: '' });
  }
  for (const rtl of [false, true]) {
    const list = cases.filter((c) => c.rtl === rtl);
    let html = `<style>${CSS.proTc}${CSS.tc}.elementor-testimonial__content:after,.digi-testimonial__content:after{background-color:red!important}.box{width:500px;padding:10px;margin-inline:30px}</style>`;
    list.forEach((c, i) => {
      const value = c.desk || 'start';
      // El valor que fija el mapa (maps/testimonial-carousel.php, comprobado en tests/elements-pro-testimonials.php).
      const pro = value;
      const own = `#o${i}{${ALIGNMENT[c.desk] || ''}}@media (max-width:1024px){#o${i}{${c.dev ? ALIGNMENT[c.dev] : ''}}}` + (c.size ? fill(ownSize, `#o${i}`, c.size) : '') + (pro ? fill(ownBubble.map(([s, d]) => [s, d.replace('{{VALUE}}', PRO_BUBBLE[pro])]), `#o${i}`, '') : '');
      const proCss = c.size ? fill(proSize, `#p${i}`, c.size) : '';
      html += `<style>${own}${proCss}</style><div class="box elementor-element elementor-widget-testimonial-carousel elementor-testimonial--skin-bubble elementor-testimonial--layout-image_inline elementor-testimonial--align-${c.desk}${c.dev ? ` elementor-testimonial--tablet-align-${c.dev}` : ''}" id="p${i}">${body('elementor-testimonial')}</div><div class="box elementor-widget-digitalisimo-testimonial-carousel digi-testimonial--skin-bubble digi-testimonial--layout-image_inline" id="o${i}" data-pro="${pro}">${body('digi-testimonial')}</div>`;
    });
    for (const width of [1100, 800]) {
      const page = await open(html, { rtl, width });
      for (let i = 0; i < list.length; i++) {
        const c = list[i];
        if (800 === width && !c.dev) continue;
        const where = async (id) => {
          const box = await page.evaluate((id) => { const r = document.querySelector(`#${id} [class$="__content"]`).getBoundingClientRect(); return { x: r.left, y: r.bottom - 12, w: r.width }; }, id);
          const left = Math.max(0, Math.floor(box.x) - 20);
          const shot = await page.screenshot({ clip: { x: left, y: Math.floor(box.y), width: Math.ceil(box.w) + 40, height: 24 }, encoding: 'base64' });
          return page.evaluate(async (src, off) => { const i = new Image(); await new Promise((r) => { i.onload = r; i.src = 'data:image/png;base64,' + src; }); const cv = document.createElement('canvas'); cv.width = i.width; cv.height = i.height; const g = cv.getContext('2d'); g.drawImage(i, 0, 0); const d = g.getImageData(0, 0, i.width, i.height).data; let sx = 0, n = 0; for (let k = 0; k < d.length; k += 4) if (d[k] > 200 && d[k + 1] < 80 && d[k + 2] < 80) { sx += (k / 4) % i.width; n++; } return n ? sx / n - off : null; }, shot, box.x - left);
        };
        const [a, b] = [await where('p' + i), await where('o' + i)];
        ok(null !== a && null !== b && Math.abs(a - b) <= 1, `${rtl ? 'RTL' : 'LTR'} ${width}px · escritorio «${c.desk || 'vacía'}»${c.dev ? `, tableta «${c.dev}»` : ''}${c.size ? ', con «Tamaño» ' + c.size : ''}: el pico de lo convertido en ${b?.toFixed(1)} px, como PRO (${a?.toFixed(1)} px).`);
      }
      await page.close();
    }
  }
  // Prueba del arnés: sin `pro_bubble` (un testimonio nuevo), con «Derecha» el pico apunta a la imagen y no coincide.
  {
    const html = `<style>${CSS.proTc}${CSS.tc}.elementor-testimonial__content:after,.digi-testimonial__content:after{background-color:red!important}.box{width:500px;padding:10px}#o{${ALIGNMENT.right}}</style><div class="box elementor-widget-testimonial-carousel elementor-testimonial--skin-bubble elementor-testimonial--layout-image_inline elementor-testimonial--align-right" id="p">${body('elementor-testimonial')}</div><div class="box elementor-widget-digitalisimo-testimonial-carousel digi-testimonial--skin-bubble digi-testimonial--layout-image_inline" id="o">${body('digi-testimonial')}</div>`;
    const page = await open(html);
    const x = (id) => page.evaluate((id) => { const c = document.querySelector(`#${id} [class$="__content"]`), r = c.getBoundingClientRect(), s = getComputedStyle(c, '::after'); return s.left === 'auto' ? r.width - parseFloat(s.right) : parseFloat(s.left); }, id);
    const [a, b] = [await x('p'), await x('o')];
    ok(Math.abs(a - b) > 100, `Prueba del arnés: sin pro_bubble, «Derecha» lleva el pico hacia la imagen (${b} px frente a ${a} px de PRO).`);
    await page.close();
  }
}

// ---------------------------------------------------------------------------------------------------------------------
// 5. Reseñas convertidas: los caracteres de eicons de PRO con su hoja de recorte (`widget-star-rating.min.css`), píxel a
// píxel, con la fuente cargada y sin ella (PRO no la encolaba: donde no cargaba, no se veía ninguna estrella).
{
  const font = readFileSync(new URL('elementor/assets/lib/eicons/fonts/eicons.woff2', root)).toString('base64');
  const face = `@font-face{font-family:eicons;src:url(data:font/woff2;base64,${font}) format("woff2");font-display:block}`;
  // `render_stars()` de PRO, con el valor tal como se guardó.
  const proStars = (rating, outline) => {
    const icon = outline ? '&#xE933;' : '&#xE934;';
    const value = parseFloat(rating) > 5 ? 5 : rating, whole = Math.trunc(parseFloat(value));
    let html = '';
    for (let s = 1; s <= 5; s++) {
      if (s <= whole) html += `<i class="elementor-star-full">${icon}</i>`;
      else if (whole + 1 === s && !(typeof value === 'number' && value === whole)) html += `<i class="elementor-star-${String(+((parseFloat(value) - whole) * 10).toPrecision(14))}">${icon}</i>`;
      else html += `<i class="elementor-star-empty">${icon}</i>`;
    }
    return `<div class="elementor-star-rating">${html}</div>`;
  };
  // Reviews::stars( …, $glyph = true ): el mismo marcado que comprueba tests/elements-pro-testimonials.php.
  const ownStars = (rating, outline) => {
    const base = outline ? '&#xE933;' : '&#xE934;', fill = '&#xE934;';
    const value = parseFloat(rating) > 5 ? 5 : rating, whole = Math.trunc(parseFloat(value));
    const part = String(+((parseFloat(value) - whole) * 10).toPrecision(14));
    let html = '';
    for (let s = 1; s <= 5; s++) {
      if (s <= whole) html += `<i class="digi-review__star digi-review__star--full">${base}<span class="digi-review__fill">${fill}</span></i>`;
      else if (whole + 1 === s && !(typeof value === 'number' && value === whole)) {
        const cut = /^[1-9]$/.test(part);
        html += `<i class="digi-review__star digi-review__star--${cut ? part : 'full'}">${base}<span class="digi-review__fill"${cut ? ` style="width:${part * 10}%"` : ''}>${fill}</span></i>`;
      } else html += `<i class="digi-review__star digi-review__star--empty">${base}</i>`;
    }
    return `<div class="digi-review__stars digi-review__stars--eicons" role="img" aria-label="x">${html}</div>`;
  };
  for (const loaded of [true, false]) {
    let html = `<style>${loaded ? face : ''}${CSS.stars}${CSS.rv}.row{display:inline-block;font-size:30px;line-height:1.5;padding:4px}</style>`;
    const list = [[5, false], [4.5, false], [3, false], ['4', false], [2.3, true], [4.25, false]];
    list.forEach(([r, o], i) => { html += `<div><span class="row" id="p${i}">${proStars(r, o)}</span></div><div><span class="row" id="o${i}">${ownStars(r, o)}</span></div>`; });
    const page = await open(html);
    await page.evaluate(() => document.fonts.ready);
    for (let i = 0; i < list.length; i++) {
      const box = (id) => page.evaluate((id) => { const r = document.getElementById(id).getBoundingClientRect(); return { x: Math.floor(r.left), y: Math.floor(r.top), width: Math.ceil(r.width), height: Math.ceil(r.height) }; }, id);
      const [a, b] = [await box('p' + i), await box('o' + i)];
      ok(a.width === b.width && a.height === b.height, `${loaded ? 'Con' : 'Sin'} la fuente · ${JSON.stringify(list[i][0])}${list[i][1] ? ' contorno' : ''}: la fila mide lo mismo (${a.width}×${a.height} / ${b.width}×${b.height}).`);
      const d = await diff(page, a, { ...b, width: a.width, height: a.height });
      ok(0 === d.count, `${loaded ? 'Con' : 'Sin'} la fuente · ${JSON.stringify(list[i][0])}${list[i][1] ? ' contorno' : ''}: las estrellas de lo convertido son las de PRO (${d.count} píxeles distintos).`);
    }
    await page.close();
  }
}

await browser.close();
assert.deepEqual(errors, [], 'Sin errores de JavaScript: ' + errors.join(' | '));
console.log(`DIGITALÍSIMO Elements: fidelidad de los carruseles de C1 en Chrome (${checks} comprobaciones) validada.`);
