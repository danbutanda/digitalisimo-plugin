// Galería avanzada (digitalisimo-gallery, C2 frente C de docs/pro/c2.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-gallery.html, con el marcado del widget (lo comprueba tests/elements-pro-gallery.php):
// - justificada y mampostería: la posición y el tamaño de cada imagen y la altura del contenedor son los que calcula la
//   librería de galería del núcleo (su algoritmo, portado aquí tal cual: filas con la altura ideal, cierre por el ancho
//   más cercano, última fila sin estirar por debajo del 70 %; columna de la posición salvo otra 5 px más baja), también
//   tras cambiar el ancho;
// - sin JavaScript: justificada y mampostería se ven como una cuadrícula sin huecos vacíos;
// - filtros: clic y teclado (Intro y Espacio), aria-pressed, ocultos con hidden, grupo del lightbox de lo visible y
//   nueva disposición de la justificada; botones de 24 px de alto como mínimo;
// - animación al pasar el cursor (variables en línea) y movimiento reducido; el gancho del editor.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-gallery-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la Galería avanzada (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-gallery.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const near = (a, b) => Math.abs(a - b) < 0.6;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

// ── Referencia: la librería de galería del núcleo (types/justified.js y types/masonry.js), en píxeles ──────────────
function refJustified(dims, W, ideal, hgap, vgap) {
  const data = dims.map(([w, h]) => ({ w, h, ratio: w / h }));
  const out = [];
  const rowsHeights = [];
  let rowsCount = 0;
  const fit = (start, end, rowWidth) => {
    const gapCount = end - start - 1;
    let agg = 0;
    for (let i = start; i < end; i++) {
      const pw = data[i].computedWidth / rowWidth;
      out[i] = { start: agg, width: pw, gaps: gapCount, idx: i - start };
      agg += pw;
      if (i === start) rowsHeights.push((pw * (W - gapCount * hgap)) / data[i].ratio);
    }
  };
  const row = (start) => {
    let old = 0;
    for (let i = start; ; i++) {
      let w = Math.round(ideal * data[i].ratio);
      if (w > W) w = W;
      const next = old + w;
      if (next > W && W - old < next - W) {
        fit(start, i, old);
        rowsCount++;
        row(i);
        break;
      }
      data[i].computedWidth = w;
      if (i === data.length - 1) {
        fit(start, i + 1, 0.7 <= next / W ? next : W);
        break;
      }
      old = next;
    }
  };
  row(0);
  let r = -1;
  let top = 0;
  const boxes = out.map((o) => {
    if (o.idx === 0) {
      r++;
      if (r) top += rowsHeights[r - 1];
    }
    const room = W - hgap * o.gaps;
    return { left: o.start * room + hgap * o.idx, top: top + r * vgap, width: o.width * room, height: rowsHeights[r] };
  });
  return { boxes, height: rowsHeights.reduce((a, b) => a + b) + rowsCount * vgap };
}
function refMasonry(dims, W, columns, hgap, vgap) {
  const heights = new Array(columns).fill(0);
  const inColumn = new Array(columns).fill(0);
  const itemWidth = (W - hgap * (columns - 1)) / columns;
  const boxes = dims.map(([w, h], index) => {
    const ratio = w / h;
    let col = index % columns;
    let natural = heights[col];
    heights.forEach((ch, c) => { if (ch && natural > ch + 5) { natural = ch; col = c; } });
    const box = { left: col * (itemWidth + hgap), top: heights[col] + inColumn[col] * vgap, width: itemWidth, height: itemWidth / ratio };
    heights[col] += itemWidth / ratio;
    inColumn[col]++;
    return box;
  });
  const highest = Math.max(...heights);
  return { boxes, height: highest + (inColumn[heights.indexOf(highest)] - 1) * vgap };
}

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ width = 1280, js = true, reduce = false, editor = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setJavaScriptEnabled(js);
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  if (editor) await page.evaluateOnNewDocument(() => { window.__hooks = []; window.elementorFrontend = { hooks: { addAction: (n, f) => window.__hooks.push([n, f]) } }; });
  await page.goto(fixture, { waitUntil: 'load' });
  if (js) await page.waitForFunction(() => document.querySelectorAll('.digi-gallery').length === 5 && !document.querySelector('.digi-gallery:not(.digi-gallery--ready)'));
  return page;
}
// Cajas de los elementos visibles de un contenedor, relativas a él.
const boxes = (page, id) => page.evaluate((id) => {
  const box = document.querySelector(`[data-id="${id}"] .digi-gallery__container`);
  const b = box.getBoundingClientRect();
  return {
    height: b.height,
    width: box.clientWidth,
    abs: box.classList.contains('digi-gallery__container--abs'),
    items: [...box.children].filter((el) => !el.hidden).map((el) => { const r = el.getBoundingClientRect(); const i = el.querySelector('img').getBoundingClientRect(); return { left: r.left - b.left, top: r.top - b.top, width: r.width, height: r.height, img: [i.width, i.height], dims: [+el.dataset.w, +el.dataset.h] }; }),
  };
}, id);
const compare = (got, ref, label) => {
  ok(got.abs, `${label}: el script coloca las imágenes.`);
  ok(got.items.length === ref.boxes.length, `${label}: ${got.items.length} imágenes colocadas.`);
  got.items.forEach((it, i) => {
    const r = ref.boxes[i];
    ok(near(it.left, r.left) && near(it.top, r.top) && near(it.width, r.width) && near(it.height, r.height), `${label} ${i}: ${JSON.stringify(it)} ≠ ${JSON.stringify(r)}`);
    ok(near(it.img[0], it.width) && near(it.img[1], it.height), `${label} ${i}: la imagen llena su caja.`);
  });
  ok(near(got.height, ref.height), `${label}: altura del contenedor ${got.height} ≠ ${ref.height}`);
};

// 1. Justificada y mampostería, como la librería; y tras cambiar el ancho.
let page = await open();
let got = await boxes(page, 'justified');
compare(got, refJustified(got.items.map((i) => i.dims), 600, 200, 10, 10), 'Justificada 600');
ok(new Set(got.items.map((i) => Math.round(i.top))).size >= 3, 'Justificada: varias filas.');
got = await boxes(page, 'masonry');
compare(got, refMasonry(got.items.map((i) => i.dims), 610, 3, 10, 10), 'Mampostería 610');
await page.evaluate(() => { document.querySelector('[data-id="justified"]').style.width = '420px'; document.querySelector('[data-id="masonry"]').style.cssText += ';width:430px;--columns:2;--hgap:4px;--vgap:6px'; });
await sleep(200);
got = await boxes(page, 'justified');
compare(got, refJustified(got.items.map((i) => i.dims), 420, 200, 10, 10), 'Justificada 420');
got = await boxes(page, 'masonry');
compare(got, refMasonry(got.items.map((i) => i.dims), 430, 2, 4, 6), 'Mampostería 430 a 2 columnas');

// 2. Filtros: clic, teclado, aria-pressed, ocultos, lightbox y nueva disposición.
const filters = (page, id) => page.evaluate((id) => {
  const root = document.querySelector(`[data-id="${id}"]`);
  return {
    pressed: [...root.querySelectorAll('.digi-gallery__filter')].map((b) => (b.getAttribute('aria-pressed') === 'true' ? 'X' : '-')).join(''),
    active: [...root.querySelectorAll('.digi-gallery__filter')].map((b) => (b.classList.contains('digi-gallery__filter--active') ? 'X' : '-')).join(''),
    shown: [...root.querySelectorAll('.digi-gallery__item')].map((el) => (el.hidden ? '-' : 'X')).join(''),
    group: [...root.querySelectorAll('.digi-gallery__item')].map((el) => el.getAttribute('data-elementor-lightbox-slideshow')),
    display: [...root.querySelectorAll('.digi-gallery__item')].map((el) => getComputedStyle(el).display).join(','),
    heights: [...root.querySelectorAll('.digi-gallery__filter')].map((b) => b.getBoundingClientRect().height),
  };
}, id);
let f = await filters(page, 'filters');
ok(f.pressed === 'X--' && f.active === 'X--' && f.shown === 'XX--X' && f.display === 'block,block,none,none,block', 'Estado inicial del servidor: primer filtro y sus imágenes; lo demás, display:none. ' + JSON.stringify(f));
ok(f.heights.every((h) => h >= 24), 'Filtros de 24 px de alto como mínimo: ' + f.heights.join(', '));
await page.click('[data-id="filters"] [data-digi-filter="1"]');
f = await filters(page, 'filters');
ok(f.pressed === '-X-' && f.active === '-X-' && f.shown === '-XXX-' && f.group[1] === '1_f1' && f.group[3] === '1_f1' && f.group[0] === '0_f1', 'Clic en «Playas»: aria-pressed, visibles y grupo del lightbox. ' + JSON.stringify(f));
await page.focus('[data-id="filters"] [data-digi-filter="all"]');
await page.keyboard.press('Enter');
f = await filters(page, 'filters');
ok(f.pressed === '--X' && f.shown === 'XXXXX' && f.group.every((g) => g === 'all_f1'), 'Intro en «Todas»: todas visibles en el grupo all. ' + JSON.stringify(f));
await page.focus('[data-id="filters"] [data-digi-filter="0"]');
await page.keyboard.press('Space');
f = await filters(page, 'filters');
ok(f.pressed === 'X--' && f.shown === 'XX--X', 'Espacio en «Faros». ' + JSON.stringify(f));
const outline = await page.evaluate(() => { const s = getComputedStyle(document.activeElement); return [s.outlineStyle, s.outlineWidth, s.outlineOffset]; });
ok(outline.join() === 'solid,2px,2px', 'Foco visible de 2 px con separación de 2 px en el filtro: ' + outline.join());
await sleep(400);
const pointer = await page.evaluate(() => { const b = document.querySelector('[data-id="filters"] .digi-gallery__filter--active'); const a = getComputedStyle(b, '::after'); return [a.content, a.height, a.opacity]; });
ok(pointer.join() === '"",3px,1', 'Indicador subrayado en el filtro activo: ' + pointer.join());
// Justificada con filtros: al filtrar se vuelve a colocar con lo visible.
got = await boxes(page, 'filtered-justified');
compare(got, refJustified(got.items.map((i) => i.dims), 600, 150, 10, 10), 'Justificada filtrada (todas)');
await page.click('[data-id="filtered-justified"] [data-digi-filter="1"]');
got = await boxes(page, 'filtered-justified');
ok(got.items.length === 3, 'Filtro «Uno»: tres imágenes.');
compare(got, refJustified(got.items.map((i) => i.dims), 600, 150, 10, 10), 'Justificada filtrada (Uno)');
await sleep(400);
const bg = await page.evaluate(() => { const b = document.querySelector('[data-id="filtered-justified"] .digi-gallery__filter--active'); const a = getComputedStyle(b, '::before'); return [a.content, a.opacity, a.zIndex]; });
ok(bg.join() === '"",1,-1', 'Indicador de fondo detrás del texto: ' + bg.join());

// 3. Animación al pasar el cursor.
const fx = (page) => page.evaluate(() => { const r = document.querySelector('[data-id="fx"]'); return [getComputedStyle(r.querySelector('img')).transform, getComputedStyle(r.querySelector('.digi-gallery__title')).opacity, getComputedStyle(r.querySelector('img')).transitionDuration]; });
let state = await fx(page);
ok(state[0] === 'none' && state[1] === '0', 'En reposo: sin transformación y el título oculto (aparecer). ' + state.join());
await page.hover('[data-id="fx"] .digi-gallery__item');
await sleep(100);
state = await fx(page);
ok(/^matrix\(1\.1, 0, 0, 1\.1,/.test(state[0]) && state[1] === '1', 'Con el cursor: la imagen crece y el título aparece. ' + state.join());
ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
await page.close();

// 4. Sin JavaScript: cuadrícula sin huecos.
page = await open({ js: false });
got = await boxes(page, 'justified');
ok(!got.abs && got.items.length === 7 && got.items.every((i) => i.width > 100 && i.height > 50) && near(got.items[1].top, 0) && got.items[4].top > 50, 'Sin script, la justificada es una cuadrícula de 4 columnas con cada imagen a su proporción.');
f = await filters(page, 'filters');
ok(f.shown === 'XX--X', 'Sin script, el filtro inicial del servidor sigue a la vista.');
await page.close();

// 5. Movimiento reducido y editor.
page = await open({ reduce: true, editor: true });
const durations = await page.evaluate(() => [getComputedStyle(document.querySelector('[data-id="fx"] img')).transitionDuration, getComputedStyle(document.querySelector('.digi-gallery__filter'), '::after').transitionDuration, getComputedStyle(document.querySelector('.digi-gallery__overlay')).transitionDuration]);
ok(durations.every((d) => d === '0s'), 'Movimiento reducido: sin transiciones. ' + durations.join());
await page.evaluate(() => window.dispatchEvent(new Event('elementor/frontend/init')));
const hook = await page.evaluate(() => window.__hooks.map((h) => h[0]));
ok(hook.length === 1 && hook[0] === 'frontend/element_ready/digitalisimo-gallery.default', 'En el editor se engancha a frontend/element_ready del widget.');
const again = await page.evaluate(() => {
  const w = document.querySelector('[data-id="masonry"]');
  const copy = w.cloneNode(true);
  copy.dataset.id = 'copy';
  copy.querySelector('.digi-gallery').classList.remove('digi-gallery--ready');
  document.body.appendChild(copy);
  window.__hooks[0][1]([copy]);
  return copy.querySelector('.digi-gallery').classList.contains('digi-gallery--ready');
});
ok(again, 'El gancho del editor inicia el widget que se vuelve a pintar.');

// 6. Correcciones de fidelidad (docs/pro/c2.md): carga diferida con la entrada de la librería de PRO e indicadores de
// los filtros con las animaciones de PRO (las reglas e--pointer-*/e--animation-* de la hoja de su Menú).
const PX = 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%2230%22 height=%2220%22%3E%3Crect width=%22100%25%22 height=%22100%25%22 fill=%22%23245eff%22/%3E%3C/svg%3E';
const lazy = await page.evaluate(async (px) => {
  const w = document.createElement('div');
  w.className = 'w';
  w.dataset.id = 'lazy';
  w.innerHTML = '<div class="digi-gallery digi-gallery--grid"><div id="digi-gallery-lz" class="digi-gallery__container" data-digi-lazy><a class="digi-gallery__item" data-w="3" data-h="2"><img class="digi-gallery__image" alt="a" width="30" height="20"></a><a class="digi-gallery__item" data-w="3" data-h="2"><img class="digi-gallery__image" alt="b" width="30" height="20" src="' + px + '"></a></div></div>';
  document.body.prepend(w);
  const [a, b] = w.querySelectorAll('img');
  await new Promise((r) => (b.complete ? r() : b.addEventListener('load', r)));
  window.__hooks[0][1]([w]);
  const st = (el) => [getComputedStyle(el).transform, getComputedStyle(el).filter, el.classList.contains('is-loaded')].join('|');
  // El observador de la vista responde en el siguiente fotograma.
  const frames = () => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(() => setTimeout(r, 50))));
  const out = { cls: w.querySelector('.digi-gallery__container').className, before: st(a), sync: st(b) };
  await frames();
  out.loaded = st(b);
  a.src = px;
  await new Promise((r) => (a.complete ? r() : a.addEventListener('load', r)));
  await frames();
  out.after = st(a);
  out.others = document.querySelectorAll('.digi-gallery__container--lazy').length;
  // Como `lazyLoadImages()` de PRO (sólo las imágenes en la vista): una ya cargada pero fuera de la vista espera oculta
  // y entra al verse, no al cargarse (la carga diferida nativa la pide mucho antes).
  const far = document.createElement('div');
  far.className = 'w';
  far.innerHTML = '<div style="height:3000px"></div><div class="digi-gallery digi-gallery--grid"><div id="digi-gallery-lz2" class="digi-gallery__container" data-digi-lazy><a class="digi-gallery__item" data-w="3" data-h="2"><img class="digi-gallery__image" alt="c" width="30" height="20" src="' + px + '"></a></div></div>';
  document.body.append(far);
  const c = far.querySelector('img');
  await new Promise((r) => (c.complete ? r() : c.addEventListener('load', r)));
  window.__hooks[0][1]([far]);
  await frames();
  out.away = st(c);
  c.scrollIntoView();
  await frames();
  out.seen = st(c);
  window.scrollTo(0, 0);
  far.remove();
  return out;
}, PX);
ok(lazy.cls === 'digi-gallery__container digi-gallery__container--lazy' && lazy.others === 1, 'Con «Carga diferida» el script marca el contenedor (sólo ese): ' + JSON.stringify(lazy));
ok(lazy.before === 'matrix(0.5, 0, 0, 0.5, 0, 0)|opacity(0)|false' && lazy.sync === lazy.before, 'La imagen espera a la mitad de su tamaño y sin opacidad, como la de PRO, hasta verse: ' + lazy.before + ' / ' + lazy.sync);
ok(lazy.loaded === 'none|none|true' && lazy.after === 'none|none|true', 'En la vista, la ya cargada entra enseguida y la otra al cargarse: ' + lazy.loaded + ' / ' + lazy.after);
ok(lazy.away === 'matrix(0.5, 0, 0, 0.5, 0, 0)|opacity(0)|false' && lazy.seen === 'none|none|true', 'Fuera de la vista, la ya cargada espera oculta y entra al desplazarse hasta ella, como PRO: ' + lazy.away + ' / ' + lazy.seen);
ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
await page.close();

// Indicadores: una barra por animación, con el estado en reposo y con el cursor de cada una (sin transiciones).
page = await open({ reduce: true });
const pointers = await page.evaluate(() => {
  const root = document.querySelector('[data-id="filters"]');
  const out = {};
  const probe = (pointer, anim) => {
    const bar = root.querySelector('.digi-gallery__filters').cloneNode(true);
    bar.className = 'digi-gallery__filters digi-gallery__filters--' + pointer;
    bar.setAttribute('data-digi-fx', anim);
    root.querySelector('.digi-gallery').prepend(bar);
    const off = bar.querySelector('.digi-gallery__filter:not(.digi-gallery__filter--active)');
    const on = bar.querySelector('.digi-gallery__filter--active');
    const look = (el, p) => { const s = getComputedStyle(el, p); return [s.content, s.opacity, s.transform, s.width, s.height, s.left, s.top, s.bottom].join('|'); };
    out[pointer + ':' + anim] = { offB: look(off, '::before'), offA: look(off, '::after'), onB: look(on, '::before'), onA: look(on, '::after'), self: getComputedStyle(on).transform, offSelf: getComputedStyle(off).transform };
    bar.remove();
  };
  [['underline', 'line-grow'], ['overline', 'line-drop-in'], ['double-line', 'line-slide'], ['framed', 'framed-grow'], ['framed', 'framed-corners'], ['background', 'background-sweep-left'], ['background', 'background-shutter-in-horizontal'], ['text', 'text-grow'], ['underline', 'line-none']].forEach(([p, a]) => probe(p, a));
  return out;
});
const W = (v) => v.split('|');
let g = pointers['underline:line-grow'];
ok(W(g.offA)[1] === '0' && W(g.offA)[3] === '0px' && W(g.offA)[4] === '0px' && W(g.onA)[1] === '1' && W(g.onA)[4] === '3px', 'Subrayado «Crecer»: en reposo sin ancho ni alto, activo de 3 px: ' + JSON.stringify(g));
g = pointers['overline:line-drop-in'];
ok(W(g.offB)[6] === '-10px' && W(g.onB)[6] === '0px' && W(g.onB)[0] === '""' && W(g.onA)[0] === 'none', 'Línea superior «Caer hacia dentro»: 10 px por encima en reposo y arriba activa: ' + JSON.stringify(g));
g = pointers['double-line:line-slide'];
ok(W(g.offB)[3] === '10px' && W(g.offB)[5] === '-20px' && W(g.onB)[3] !== '10px' && W(g.onA)[0] === '""', 'Doble línea «Deslizar»: entra desde fuera con 10 px de ancho: ' + JSON.stringify(g));
g = pointers['framed:framed-grow'];
ok(/^matrix\(0\.75, 0, 0, 0\.75/.test(W(g.offB)[2]) && /^matrix\(1, 0, 0, 1/.test(W(g.onB)[2]) && W(g.onB)[0] === '""', 'Marco «Crecer»: al 75 % en reposo y entero activo: ' + JSON.stringify(g));
g = pointers['framed:framed-corners'];
ok(W(g.offA)[0] === '""' && W(g.offA)[3] === '3px' && W(g.onA)[3] !== '3px', 'Marco «Esquinas»: las dos esquinas de 3 px que se abren al marco entero: ' + JSON.stringify(g));
g = pointers['background:background-sweep-left'];
ok(W(g.onB)[0] === '""' && W(g.onB)[1] === '1' && W(g.offB)[5] !== W(g.onB)[5], 'Fondo «Barrido a la izquierda»: entra desde la derecha: ' + JSON.stringify(g));
g = pointers['background:background-shutter-in-horizontal'];
ok(W(g.offA)[0] === '""' && W(g.onA)[0] === '""' && W(g.onA)[1] === '1', 'Fondo «Persiana horizontal hacia dentro»: dos mitades: ' + JSON.stringify(g));
g = pointers['text:text-grow'];
ok(g.self === 'none' && W(g.onB)[0] === 'none', 'Texto «Crecer»: sin indicador ni transformación sin el cursor (el activo, como PRO, tampoco): ' + JSON.stringify(g));
const grown = await page.evaluate(() => {
  const root = document.querySelector('[data-id="filters"] .digi-gallery__filters');
  root.className = 'digi-gallery__filters digi-gallery__filters--text';
  root.setAttribute('data-digi-fx', 'text-grow');
  return true;
});
await page.hover('[data-id="filters"] [data-digi-filter="1"]');
await sleep(100);
const scaled = await page.$eval('[data-id="filters"] [data-digi-filter="1"]', (b) => getComputedStyle(b).transform);
ok(grown && /^matrix\(1\.2, 0, 0, 1\.2/.test(scaled), 'Texto «Crecer»: el filtro crece al 120 % con el cursor, como PRO: ' + scaled);
const none = await page.evaluate(() => { const b = document.querySelector('[data-id="filters"] .digi-gallery__filter'); return getComputedStyle(b).transitionDuration; });
ok(none === '0s', 'Movimiento reducido en los filtros: sin transiciones.');
ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
await page.close();
await browser.close();
console.log(`DIGITALÍSIMO Elements: Galería avanzada en Chrome (${checks} comprobaciones) validada.`);
