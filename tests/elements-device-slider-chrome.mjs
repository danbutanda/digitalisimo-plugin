// Slider de dispositivos (`digitalisimo-device-slider`) en Chrome real (sin interfaz) y sin WordPress, con el HTML que
// pinta el widget (tests/fixtures/device-slider-harness.php), su hoja (`assets/css/device-slider.css`), la del motor
// (`carousel-engine.css`) y el motor (`carousel-engine.js`). Tres capturas, en escritorio (1280 px) y a 375 px:
// - El dispositivo no se desborda y la página no tiene desplazamiento horizontal.
// - La captura llena la pantalla: la imagen ocupa exactamente la pantalla con `object-fit: cover` y los píxeles del borde
//   interior de la pantalla son de la imagen (ninguna franja blanca ni negra), también con una imagen de otra proporción.
// - Las flechas no se cruzan con la pantalla ni con el marco: en escritorio, a los lados y centradas en vertical; a
//   375 px, debajo del dispositivo y a los lados de los puntos. Miden 44 px o más, tienen foco visible y el icono
//   contrasta con el botón (también con un color propio claro u oscuro) y el botón con el fondo de la sección.
// - Navegación: «siguiente», los puntos, el arrastre con el ratón (sin abrir el enlace de la captura) y el deslizamiento
//   táctil cambian de captura; con movimiento reducido no hay transiciones.
// Con DS_SHOTS=<carpeta> guarda capturas de cada dispositivo; con DS_BEFORE=<carpeta con la versión anterior del widget,
// sus hojas y class-image-markup/class-carousel-engine>, también las de antes.
// Sin Chrome, sin puppeteer-core o sin PHP, se salta con un aviso. CHROME_PATH, PUPPETEER_PATH y PHP_BIN cambian las rutas.
// Uso: timeout 240 node tests/elements-device-slider-chrome.mjs
import assert from 'node:assert/strict';
import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
const php = process.env.PHP_BIN || 'php';
const hasPhp = !spawnSync(php, ['-v'], { encoding: 'utf8' }).error;
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json') || !hasPhp) {
  console.log(`AVISO: se salta la prueba en Chrome del Slider de dispositivos (sin ${!existsSync(chrome) ? 'Chrome' : !hasPhp ? 'PHP' : 'puppeteer-core'}).`);
  process.exit(0);
}
const root = fileURLToPath(new URL('..', import.meta.url));
const read = (path) => readFileSync(join(root, path), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const SHOTS = process.env.DS_SHOTS || '';
const BEFORE = process.env.DS_BEFORE || '';
if (SHOTS) mkdirSync(SHOTS, { recursive: true });

const CSS = read('digitalisimo-elements/assets/css/carousel-engine.css') + read('digitalisimo-elements/assets/css/device-slider.css');
const JS = read('digitalisimo-elements/assets/js/carousel-engine.js');

// HTML del widget (o de la versión anterior, con DS_WIDGET_DIR) para cada juego de ajustes.
const renderPhp = (list, dir = '') => execFileSync(php, [join(root, 'tests/fixtures/device-slider-harness.php')], { input: JSON.stringify(list), encoding: 'utf8', env: { ...process.env, ...(dir ? { DS_WIDGET_DIR: dir } : {}) }, maxBuffer: 64 * 1024 * 1024 }).split('\f');

// puppeteer-core hasta la 2x publica `lib/puppeteer/`; la 24, `lib/esm/puppeteer/`.
const entry = ['/lib/puppeteer/puppeteer-core.js', '/lib/esm/puppeteer/puppeteer-core.js'].map((p) => pptrDir + p).find((p) => existsSync(p));
const { default: puppeteer } = await import(pathToFileURL(entry).href);
const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu', '--font-render-hinting=none', '--hide-scrollbars'] });
const errors = [];
const outside = [];
try {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  await page.setRequestInterception(true);
  page.on('request', (r) => { if (/^(about|data):/.test(r.url())) r.continue().catch(() => {}); else { outside.push(r.url()); r.abort().catch(() => {}); } });

  // Imágenes: lisas (para medir franjas) y pantallas de ejemplo (para las capturas), dibujadas con canvas.
  await page.setContent('<!doctype html><meta charset="utf-8"><body></body>');
  const IMG = await page.evaluate(() => {
    const solid = (w, h, color) => { const c = document.createElement('canvas'); c.width = w; c.height = h; const g = c.getContext('2d'); g.fillStyle = color; g.fillRect(0, 0, w, h); return c.toDataURL('image/png'); };
    const app = (w, h, accent, title, wide) => {
      const c = document.createElement('canvas'); c.width = w; c.height = h; const g = c.getContext('2d');
      const u = wide ? h / 1000 : w / 1080;
      g.fillStyle = '#ffffff'; g.fillRect(0, 0, w, h);
      g.fillStyle = accent; g.fillRect(0, 150 * u, w, h * 0.42);
      g.fillStyle = '#ffffff'; g.fillRect(0, 0, w, 150 * u);
      g.fillStyle = '#111827'; g.font = `700 ${56 * u}px sans-serif`; g.fillText('Tienda Demo', 48 * u, 95 * u);
      g.fillStyle = accent; g.fillRect(w - 380 * u, 40 * u, 330 * u, 80 * u);
      g.fillStyle = '#ffffff'; g.font = `700 ${34 * u}px sans-serif`; g.fillText('Comprar', w - 300 * u, 92 * u);
      g.font = `800 ${(wide ? 110 : 120) * u}px sans-serif`; g.textAlign = 'center'; g.fillText(title, w / 2, 150 * u + h * 0.16);
      g.font = `500 ${48 * u}px sans-serif`; g.fillText('Lo mejor para tu negocio', w / 2, 150 * u + h * 0.25);
      const cols = wide ? 4 : 2, gap = 40 * u, top = 150 * u + h * 0.42 + gap, cw = (w - gap * (cols + 1)) / cols;
      for (let i = 0; i < cols * 2; i++) {
        const x = gap + (i % cols) * (cw + gap), y = top + Math.floor(i / cols) * (cw * 0.75 + gap);
        if (y + cw * 0.75 > h - 190 * u) break;
        g.fillStyle = '#f1f5f9'; g.fillRect(x, y, cw, cw * 0.75);
        g.fillStyle = accent; g.globalAlpha = 0.25; g.fillRect(x + cw * 0.2, y + cw * 0.12, cw * 0.6, cw * 0.4); g.globalAlpha = 1;
        g.fillStyle = '#111827'; g.font = `600 ${36 * u}px sans-serif`; g.fillText('Producto ' + (i + 1), x + cw / 2, y + cw * 0.66);
      }
      g.fillStyle = '#f8fafc'; g.fillRect(0, h - 170 * u, w, 170 * u);
      g.fillStyle = '#e2e8f0'; g.fillRect(0, h - 170 * u, w, 3 * u);
      ['Inicio', 'Tienda', 'WhatsApp', 'Perfil', 'Carrito'].forEach((label, i) => {
        const x = (w / 5) * (i + 0.5);
        g.fillStyle = i === 4 ? accent : '#475569'; g.beginPath(); g.arc(x, h - 110 * u, 24 * u, 0, Math.PI * 2); g.fill();
        g.font = `600 ${30 * u}px sans-serif`; g.fillText(label, x, h - 45 * u);
      });
      return c.toDataURL('image/png');
    };
    return {
      solid: ['#d9468f', '#1f9d74', '#3b6fd8'].map((color) => solid(1200, 1200, color)),
      colors: [[217, 70, 143], [31, 157, 116], [59, 111, 216]],
      phone: [['#2563eb', 'Kombucha'], ['#059669', 'Temporada'], ['#db2777', 'Ofertas']].map(([a, t]) => app(1080, 2200, a, t, false)),
      wide: [['#2563eb', 'Kombucha'], ['#059669', 'Temporada'], ['#db2777', 'Ofertas']].map(([a, t]) => app(1600, 1000, a, t, true)),
    };
  });
  const gallery = (urls) => urls.map((url) => ({ url }));
  const slides = (urls) => urls.map((url, i) => ({ _id: 's' + i, background: 'image', image: { url }, link: { url: 'https://site.test/captura-' + i } }));

  const page_ = (widget, { bg = '#eef2f7', extra = '' } = {}) => `<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>${CSS}</style><style>*{box-sizing:border-box}html,body{margin:0}body{font:16px/1.5 system-ui,sans-serif;color:#111827}.sec{background:${bg};padding:40px 16px}.box{max-width:1140px;margin:0 auto}${extra}</style></head><body><div class="sec"><div class="box" id="w">${widget}</div></div><script>${JS}</script></body></html>`;

  // Medidas de un widget en la página.
  const measure = () => page.evaluate(() => {
    const r = (el) => { if (!el) return null; const b = el.getBoundingClientRect(); return { left: b.left, top: b.top, right: b.right, bottom: b.bottom, width: b.width, height: b.height }; };
    const q = (s) => document.querySelector(s);
    // Colores en rgb(), también los que Chrome devuelve como lch() (el icono automático): pintados en un canvas.
    const g = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    const norm = (css) => { g.clearRect(0, 0, 1, 1); g.fillStyle = '#000'; g.fillStyle = css; g.fillRect(0, 0, 1, 1); const d = g.getImageData(0, 0, 1, 1).data; return `rgb(${d[0]}, ${d[1]}, ${d[2]})`; };
    const arrows = [...document.querySelectorAll('.digi-device-slider__arrow')].map((a) => ({ rect: r(a), cs: { color: norm(getComputedStyle(a).color), bg: norm(getComputedStyle(a).backgroundColor), transition: getComputedStyle(a).transitionDuration } }));
    const screen = q('.digi-device-slider__screen');
    const imgs = [...screen.querySelectorAll('img')].map((img) => ({ rect: r(img), fit: getComputedStyle(img).objectFit, loaded: img.complete && img.naturalWidth > 0 }));
    return {
      vw: document.documentElement.clientWidth, scrollW: document.scrollingElement.scrollWidth,
      root: r(q('.digi-device-slider')), device: r(q('.digi-device-slider__device')), frame: r(q('.digi-device-slider__frame')), screen: r(screen),
      island: r(q('.digi-device-slider__island')), nav: r(q('.digi-device-slider__nav')), dots: [...document.querySelectorAll('.digi-device-slider__dot')].map(r),
      extra: r(q('.digi-device-slider__base') || q('.digi-device-slider__stand')), arrows, imgs,
      radius: Math.max(...['borderTopLeftRadius', 'borderTopRightRadius', 'borderBottomLeftRadius', 'borderBottomRightRadius'].flatMap((k) => {
        // Radio elíptico «h v», en px o en % del ancho (h) y del alto (v) de la pantalla.
        const [h, v = h] = getComputedStyle(screen)[k].split(' '), box = screen.getBoundingClientRect();
        const px = (value, size) => (value.endsWith('%') ? (parseFloat(value) / 100) * size : parseFloat(value)) || 0;
        return [px(h, box.width), px(v, box.height)];
      })),
      current: [...document.querySelectorAll('.digi-device-slider__dot')].findIndex((d) => d.getAttribute('aria-current') === 'true'),
      scroll: q('[data-digi-carousel-track]').scrollLeft, slideW: r(screen.querySelector('li')).width,
    };
  });
  const cross = (a, b) => a && b && a.left < b.right - 0.5 && a.right > b.left + 0.5 && a.top < b.bottom - 0.5 && a.bottom > b.top + 0.5;
  const ready = async () => { await page.waitForFunction(() => [...document.querySelectorAll('.digi-device-slider img')].every((i) => i.complete && i.naturalWidth > 0) && document.querySelector('.digi-carousel[data-digi-carousel-ready]'), { timeout: 15000 }); await new Promise((res) => setTimeout(res, 120)); };
  const settle = () => page.waitForFunction(() => new Promise((res) => { const t = document.querySelector('[data-digi-carousel-track]'); let last = -1, same = 0; const tick = () => { if (t.scrollLeft === last) { if (++same > 6) return res(true); } else { same = 0; last = t.scrollLeft; } requestAnimationFrame(tick); }; tick(); }), { timeout: 8000 });
  // Píxeles del borde interior de la pantalla (3 px hacia dentro), sin las esquinas redondeadas ni la isla.
  const edgePixels = async (m) => {
    const s = m.screen, inset = 3, skip = Math.ceil(m.radius) + 4;
    const shot = await page.screenshot({ encoding: 'base64', clip: { x: s.left, y: s.top, width: s.width, height: s.height } });
    return page.evaluate(async (src, w, h, inset, skip, island) => {
      const img = await new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = 'data:image/png;base64,' + src; });
      const c = document.createElement('canvas'); c.width = img.width; c.height = img.height; const g = c.getContext('2d'); g.drawImage(img, 0, 0);
      const sx = img.width / w, sy = img.height / h, out = [];
      const at = (x, y) => { if (island && x >= island.left - 2 && x <= island.right + 2 && y >= island.top - 2 && y <= island.bottom + 2) return; const d = g.getImageData(Math.round(x * sx), Math.round(y * sy), 1, 1).data; out.push([d[0], d[1], d[2]]); };
      for (let x = skip; x <= w - skip; x += 4) { at(x, inset); at(x, h - 1 - inset); }
      for (let y = skip; y <= h - skip; y += 4) { at(inset, y); at(w - 1 - inset, y); }
      return out;
    }, shot, s.width, s.height, inset, skip, m.island ? { left: m.island.left - s.left, right: m.island.right - s.left, top: m.island.top - s.top, bottom: m.island.bottom - s.top } : null);
  };
  const lum = (rgb) => { const [r, g, b] = rgb.map((v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * b; };
  const contrast = (a, b) => { const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p); return (x + 0.05) / (y + 0.05); };
  const rgb = (css) => (css.match(/[\d.]+/g) || []).slice(0, 3).map(Number);

  const DEVICES = [
    { name: 'celular', s: { device_type: 'mobile' }, img: 'phone' },
    { name: 'celular-horizontal', s: { device_type: 'mobile', orientation: 'landscape' }, img: 'wide' },
    { name: 'tableta', s: { device_type: 'tablet' }, img: 'phone' },
    { name: 'laptop', s: { device_type: 'laptop' }, img: 'wide' },
    { name: 'monitor', s: { device_type: 'desktop' }, img: 'wide' },
    { name: 'navegador', s: { device_type: 'browser', frame_style: 'white' }, img: 'wide' },
  ];
  const VIEWPORTS = [{ name: 'escritorio', width: 1280, height: 900 }, { name: '375', width: 375, height: 812 }];

  // --- Medidas: cada dispositivo, con flechas y con flechas y puntos, en los dos anchos ---------------------------------
  for (const vp of VIEWPORTS) {
    await page.setViewport({ width: vp.width, height: vp.height, deviceScaleFactor: 1 });
    for (const d of DEVICES) {
      for (const navigation of ['arrows', 'arrows_dots']) {
        const [html] = renderPhp([{ ...d.s, navigation, gallery_images: gallery(IMG.solid) }]);
        await page.setContent(page_(html), { waitUntil: 'load' });
        await ready();
        const m = await measure();
        const tag = `${vp.name} · ${d.name} · ${navigation}`;
        ok(m.scrollW <= m.vw && m.root.left >= -0.5 && m.root.right <= m.vw + 0.5 && m.device.left >= 15.5 && m.device.right <= m.vw - 15.5, `${tag}: el dispositivo cabe y la página no se desplaza a lo ancho (${m.device.left.toFixed(1)}–${m.device.right.toFixed(1)} de ${m.vw}).`);
        ok(m.imgs.length === 3 && m.imgs.every((i) => i.fit === 'cover' && i.loaded), `${tag}: tres capturas con object-fit: cover.`);
        const first = m.imgs[0].rect;
        ok(Math.abs(first.left - m.screen.left) <= 1 && Math.abs(first.top - m.screen.top) <= 1 && Math.abs(first.width - m.screen.width) <= 1 && Math.abs(first.height - m.screen.height) <= 1, `${tag}: la captura ocupa toda la pantalla (${first.width.toFixed(1)}×${first.height.toFixed(1)} en ${m.screen.width.toFixed(1)}×${m.screen.height.toFixed(1)}).`);
        const px = await edgePixels(m);
        const bad = px.filter((p) => Math.max(...p.map((v, k) => Math.abs(v - IMG.colors[0][k]))) > 40);
        ok(px.length > 40 && bad.length === 0, `${tag}: el borde interior de la pantalla es de la imagen, sin franjas (${bad.length} de ${px.length} píxeles distintos${bad.length ? ', p. ej. rgb(' + bad[0].join(',') + ')' : ''}).`);
        ok(m.arrows.length === 2, `${tag}: dos flechas.`);
        for (const [k, a] of m.arrows.entries()) {
          ok(!cross(a.rect, m.screen) && !cross(a.rect, m.frame) && !cross(a.rect, m.extra), `${tag}: la flecha ${k + 1} no toca la pantalla ni el dispositivo.`);
          ok(a.rect.width >= 44 && a.rect.height >= 44, `${tag}: flecha de ${a.rect.width}×${a.rect.height} px (44 o más).`);
          ok(a.rect.left >= -0.5 && a.rect.right <= m.vw + 0.5, `${tag}: la flecha ${k + 1} se ve entera.`);
          ok(contrast(rgb(a.cs.color), rgb(a.cs.bg)) >= 4.5, `${tag}: el icono contrasta con el botón (${a.cs.color} sobre ${a.cs.bg}).`);
        }
        const [prev, next] = m.arrows.map((a) => a.rect);
        if (vp.width >= 1000) {
          const mid = (m.frame.top + m.frame.bottom) / 2;
          ok(prev.right <= m.frame.left - 4 && next.left >= m.frame.right + 4, `${tag}: flechas a los lados del dispositivo.`);
          ok(Math.abs((prev.top + prev.bottom) / 2 - mid) <= 1 && Math.abs((next.top + next.bottom) / 2 - mid) <= 1, `${tag}: flechas centradas en vertical con el marco.`);
          ok(navigation === 'arrows' ? m.nav.height === 0 : m.dots.length === 3 && m.dots.every((dot) => dot.top >= m.device.bottom), `${tag}: ${navigation === 'arrows' ? 'sin fila de puntos' : 'los puntos van debajo'}.`);
        } else {
          const bottom = Math.max(m.device.bottom, m.extra ? m.extra.bottom : 0);
          ok(prev.top >= bottom + 4 && next.top >= bottom + 4 && Math.abs(prev.top - next.top) <= 0.5, `${tag}: flechas debajo del dispositivo, en la misma fila.`);
          ok(prev.left <= m.frame.left + 1 && next.right >= m.frame.right - 1, `${tag}: flechas a los extremos de la fila.`);
          if (m.dots.length) {
            ok(m.dots.every((dot) => dot.left >= prev.right && dot.right <= next.left && Math.abs((dot.top + dot.bottom) / 2 - (prev.top + prev.bottom) / 2) <= 1), `${tag}: los puntos quedan entre las flechas, a su altura.`);
          }
        }
        if (d.s.device_type === 'mobile' && !d.s.orientation) ok(m.island && m.island.top > m.screen.top && m.island.bottom < m.screen.top + m.screen.height * 0.06 && Math.abs((m.island.left + m.island.right) / 2 - (m.screen.left + m.screen.right) / 2) <= 1, `${tag}: la isla arriba y al centro de la pantalla.`);
        if (d.s.device_type === 'mobile' && !d.s.orientation) ok(Math.abs(m.frame.height / m.frame.width - 2.0617) < 0.02 && Math.abs(m.screen.height / m.screen.width - 19.5 / 9) < 0.02, `${tag}: proporción de celular real (pantalla 19,5:9, marco ${(m.frame.height / m.frame.width).toFixed(3)}).`);
      }
    }
  }

  // --- Navegación: siguiente, puntos, arrastre con el ratón y deslizamiento táctil ----------------------------------
  await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
  {
    const [html] = renderPhp([{ device_type: 'mobile', navigation: 'arrows_dots', slides: slides(IMG.solid) }]);
    await page.setContent(page_(html), { waitUntil: 'load' });
    await ready();
    let m = await measure();
    ok(m.current === 0 && (await page.$eval('[data-digi-carousel-prev]', (b) => b.disabled)), 'Empieza en la primera captura, con «anterior» desactivada.');
    await page.click('[data-digi-carousel-next]');
    await settle();
    m = await measure();
    ok(m.current === 1 && Math.abs(m.scroll - m.slideW) <= 1, `«Captura siguiente» pasa a la segunda (${m.scroll} px).`);
    ok(!(await page.$eval('[data-digi-carousel-prev]', (b) => b.disabled)), '«Captura anterior» se activa.');
    await page.click('.digi-device-slider__dot[data-digi-carousel-dot="2"]');
    await settle();
    m = await measure();
    ok(m.current === 2 && Math.abs(m.scroll - 2 * m.slideW) <= 1 && (await page.$eval('[data-digi-carousel-next]', (b) => b.disabled)), 'El tercer punto lleva a la última captura y desactiva «siguiente».');
    const yBefore = await page.evaluate(() => window.scrollY);
    await page.click('[data-digi-carousel-prev]');
    await settle();
    ok((await page.evaluate(() => window.scrollY)) === yBefore, 'Cambiar de captura no mueve la página.');
    // Arrastre con el ratón hacia la izquierda: siguiente captura, sin abrir el enlace.
    await page.evaluate(() => { window.__nav = 0; document.querySelectorAll('.digi-device-slider__slide a').forEach((a) => a.addEventListener('click', (e) => { if (!e.defaultPrevented) window.__nav++; e.preventDefault(); })); });
    m = await measure();
    const startIndex = m.current;
    const cx = (m.screen.left + m.screen.right) / 2, cy = (m.screen.top + m.screen.bottom) / 2;
    await page.mouse.move(cx + 60, cy);
    await page.mouse.down();
    for (let i = 1; i <= 8; i++) await page.mouse.move(cx + 60 - i * 15, cy);
    await page.mouse.up();
    await settle();
    m = await measure();
    ok(m.current === startIndex + 1 && Math.abs(m.scroll - m.current * m.slideW) <= 1, `Arrastrar con el ratón pasa a la captura ${startIndex + 2} y queda alineada.`);
    ok((await page.evaluate(() => window.__nav)) === 0, 'Soltar tras arrastrar no abre el enlace de la captura.');
    await page.mouse.click(cx, cy);
    ok((await page.evaluate(() => window.__nav)) === 1, 'Un clic sin arrastrar sí abre el enlace.');
    // Deslizamiento táctil (gesto sintético de Chrome) desde la captura del medio: hacia un lado y de vuelta.
    await page.click('.digi-device-slider__dot[data-digi-carousel-dot="1"]');
    await settle();
    const client = await page.target().createCDPSession();
    const swipe = async (dx) => { await client.send('Input.synthesizeScrollGesture', { x: Math.round(cx), y: Math.round(cy), xDistance: dx, yDistance: 0, gestureSourceType: 'touch', speed: 800, preventFling: true }); await settle(); return measure(); };
    const w = Math.round(m.slideW * 0.7);
    m = await swipe(-w);
    const moved = m.current;
    ok(moved !== 1 && Math.abs(m.scroll - m.current * m.slideW) <= 1, `Deslizar con el dedo cambia de captura (de la 2 a la ${moved + 1}, ${m.scroll} px) y queda alineada.`);
    m = await swipe(w);
    ok(m.current === 1 && Math.abs(m.scroll - m.slideW) <= 1, `Deslizar al otro lado vuelve a la captura 2 (${m.scroll} px).`);
    // Foco visible con el teclado: pista › «anterior» › «siguiente» › primer punto.
    await page.focus('[data-digi-carousel-track]');
    const focused = async () => page.evaluate(() => { const b = document.activeElement, cs = getComputedStyle(b); return { cls: b.className, label: b.getAttribute('aria-label'), style: cs.outlineStyle, width: parseFloat(cs.outlineWidth), visible: b.matches(':focus-visible') }; });
    for (const label of ['Captura anterior', 'Captura siguiente', 'Captura 1 de 3']) {
      await page.keyboard.press('Tab');
      const f = await focused();
      ok(f.label === label && f.visible && f.style === 'solid' && f.width >= 2, `Con el tabulador, «${label}» muestra el foco (${f.label}: ${f.style} ${f.width}px).`);
    }
  }

  // --- Contraste: sección clara, blanca y oscura; color propio claro u oscuro ----------------------------------------
  for (const bg of ['#ffffff', '#eef2f7', '#0f172a']) {
    for (const color of ['', '#ffffff', '#facc15', '#1e3a8a']) {
      const [html] = renderPhp([{ device_type: 'mobile', gallery_images: gallery(IMG.solid) }]);
      await page.setContent(page_(html, { bg, extra: color ? `#w .digi-device-slider{--digi-ds-arrow-bg:${color}}` : '' }), { waitUntil: 'load' });
      await ready();
      const a = (await measure()).arrows[1].cs;
      ok(contrast(rgb(a.color), rgb(a.bg)) >= 4.5, `Sección ${bg}, flecha ${color || 'por defecto'}: icono ${a.color} sobre ${a.bg} (${contrast(rgb(a.color), rgb(a.bg)).toFixed(1)}:1).`);
      // El aro del botón es del color del icono: el botón o su aro se distinguen siempre del fondo de la sección.
      const section = bg.replace('#', '').match(/../g).map((h) => parseInt(h, 16));
      ok(Math.max(contrast(rgb(a.bg), section), contrast(rgb(a.color), section)) >= 3, `Sección ${bg}, flecha ${color || 'por defecto'}: el botón o su aro se distinguen del fondo.`);
    }
  }

  // --- Movimiento reducido ---------------------------------------------------------------------------------------------------
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  {
    const [html] = renderPhp([{ device_type: 'mobile', gallery_images: gallery(IMG.solid) }]);
    await page.setContent(page_(html), { waitUntil: 'load' });
    await ready();
    const m = await measure();
    ok(m.arrows.every((a) => a.cs.transition.split(',').every((t) => parseFloat(t) === 0)), 'Con movimiento reducido las flechas no tienen transición.');
    await page.click('[data-digi-carousel-next]');
    await new Promise((res) => setTimeout(res, 60));
    ok(Math.abs((await measure()).scroll - m.slideW) <= 1, 'Con movimiento reducido el cambio de captura es inmediato.');
  }
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'no-preference' }]);

  // --- Capturas para el propietario --------------------------------------------------------------------------------------------
  if (SHOTS) {
    const shoot = async (file, html, css = CSS, bg = '#eef2f7') => {
      const doc = page_(html, { bg }).replace(CSS, () => css);
      await page.setContent(doc, { waitUntil: 'load' });
      await ready();
      await page.screenshot({ path: join(SHOTS, file), fullPage: true });
    };
    for (const vp of VIEWPORTS) {
      await page.setViewport({ width: vp.width, height: vp.height, deviceScaleFactor: 2 });
      for (const d of DEVICES) {
        const [html] = renderPhp([{ ...d.s, navigation: vp.width < 600 ? 'arrows_dots' : 'arrows', gallery_images: gallery(IMG[d.img]) }]);
        await shoot(`despues-${vp.name}-${d.name}.png`, html);
      }
      for (const frame of ['silver', 'white']) {
        const [html] = renderPhp([{ device_type: 'mobile', frame_style: frame, navigation: 'arrows_dots', gallery_images: gallery(IMG.phone) }]);
        await shoot(`despues-${vp.name}-celular-${frame === 'silver' ? 'plata' : 'blanco'}.png`, html, CSS, frame === 'white' ? '#0f172a' : '#ffffff');
      }
      if (BEFORE) {
        const oldCss = readFileSync(join(BEFORE, 'carousel-engine.css'), 'utf8') + readFileSync(join(BEFORE, 'device-slider.css'), 'utf8');
        const [html] = renderPhp([{ device_type: 'mobile', gallery_images: gallery(IMG.phone) }], BEFORE);
        await shoot(`antes-${vp.name}-celular.png`, html, oldCss);
        const [wide] = renderPhp([{ device_type: 'desktop', gallery_images: gallery(IMG.wide) }], BEFORE);
        await shoot(`antes-${vp.name}-monitor.png`, wide, oldCss);
      }
    }
  }

  ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
  ok(outside.length === 0, 'Ninguna petición fuera de la página: ' + outside.join(', '));
  console.log(`DIGITALÍSIMO Elements: Slider de dispositivos en Chrome (${DEVICES.length} dispositivos, escritorio y 375 px, flechas fuera de la pantalla, captura sin franjas, arrastre, deslizamiento táctil, foco y contraste): ${checks} comprobaciones.`);
} finally {
  await browser.close();
}
