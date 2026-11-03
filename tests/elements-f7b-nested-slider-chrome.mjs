// F7b, frente F: el slider anidado ligero en Chrome real (sin interfaz) sobre tests/fixtures/f7b-nested-slider.html, cuyos
// tres sliders son el HTML de render() (lo comprueba elements-f7b-nested.php): slide con flechas y puntos sin bucle,
// fade con reproducción automática y scale con sólo flechas a los lados.
// - Sin JavaScript: pista con scroll-snap que se desplaza, diapositivas de la altura de la más alta, nada inerte y la
//   regla [hidden] de C2-2 (contenedor flex, contenedor grid y columna con display:none).
// - Con JavaScript, en tres anchos (360, 768 y 1280): orden del foco con Tab (pausa primero, diapositivas ocultas
//   inertes, puntos ocultos fuera del orden), flechas del teclado sólo en los controles, aria-current, aria-live,
//   botones de 24 × 24 px como mínimo, reproducción automática con sus pausas (botón, puntero, foco, fuera de pantalla),
//   movimiento reducido, ningún desplazamiento de diseño entre pases (alturas, posición de lo que sigue y layout-shift)
//   y el árbol de accesibilidad (región «carrusel», grupos «N de M», botones con nombre; lo inerte, fuera).
// F8 (V.10.4): cuatro sliders más con el contador «N / M» (flechas y puntos debajo, sólo flechas debajo, sólo flechas a
// los lados y flechas a los lados con puntos y reproducción automática): sin JavaScript no se ve pero guarda su sitio;
// con JavaScript, su celda de la rejilla real, cifras tabulares, el color de la navegación, fuera del árbol de
// accesibilidad, al día con botones, teclado y reproducción automática, y sin desplazamiento de diseño.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-f7b-nested-slider-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del slider anidado (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/f7b-nested-slider.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ width = 1280, js = true, reduce = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setJavaScriptEnabled(js);
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  await page.evaluateOnNewDocument(() => {
    window.__shift = 0;
    new PerformanceObserver(list => { for (const e of list.getEntries()) if (!e.hadRecentInput) window.__shift += e.value; }).observe({ type: 'layout-shift', buffered: true });
  });
  await page.goto(fixture, { waitUntil: 'load' });
  if (js) await page.waitForFunction(() => document.querySelectorAll('.digi-nslider--ready').length === document.querySelectorAll('.digi-nslider').length && document.querySelectorAll('.digi-nslider').length === 7);
  return page;
}
// Estado de un slider: diapositiva actual, inertes, puntos y región viva.
const state = (page, id) => page.evaluate(id => {
  const r = document.querySelector(`[data-id="${id}"] .digi-nslider`), t = r.querySelector(':scope>.digi-nslider__track');
  const slides = [...t.children], dots = [...r.querySelector(':scope>.digi-nslider__dots').children];
  const rt = t.getBoundingClientRect();
  return {
    inert: slides.map(s => (s.inert ? '-' : 'X')).join(''),
    current: dots.map(d => (d.getAttribute('aria-current') === 'true' ? 'X' : '-')).join(''),
    visible: slides.map(s => { const b = s.getBoundingClientRect(), cs = getComputedStyle(s); return Math.abs(b.left - rt.left) < 2 && cs.visibility !== 'hidden' && +cs.opacity > 0.5 ? 'X' : '-'; }).join(''),
    live: t.getAttribute('aria-live'),
    pause: r.querySelector(':scope>.digi-nslider__pause')?.getAttribute('aria-label') || null,
    height: Math.round(r.getBoundingClientRect().height),
    heights: slides.map(s => s.offsetHeight),
  };
}, id);
const active = page => page.evaluate(() => {
  const a = document.activeElement;
  if (!a || a === document.body) return 'body';
  const w = a.closest('[data-id^="w"]');
  return (w ? w.dataset.id + ':' : '') + (a.getAttribute('aria-label') || a.textContent.trim());
});
const settle = page => page.waitForFunction(() => [...document.querySelectorAll('.digi-nslider__track')].every(t => { const v = t.scrollLeft; t.__last = t.__last === undefined ? NaN : t.__last; const same = t.__last === v; t.__last = v; return same; }), { polling: 150, timeout: 5000 });

try {
  // 1. Sin JavaScript: legible y desplazable; la regla [hidden].
  {
    const page = await open({ js: false });
    const info = await page.evaluate(() => {
      const t = document.querySelector('[data-id="w1slide"] .digi-nslider__track'), cs = getComputedStyle(t);
      const s = [...t.children].map(x => ({ h: Math.round(x.getBoundingClientRect().height), snap: getComputedStyle(x).scrollSnapAlign, w: Math.round(x.getBoundingClientRect().width) }));
      t.scrollLeft = t.clientWidth;
      const second = t.children[1].getBoundingClientRect().left - t.getBoundingClientRect().left;
      const hidden = [...document.querySelectorAll('.digi-test-hidden > *')].map(x => getComputedStyle(x).display);
      const pause = getComputedStyle(document.querySelector('.digi-nslider__pause')).display;
      return { snap: cs.scrollSnapType, overflow: cs.overflowX, s, width: Math.round(t.clientWidth), scrollable: t.scrollWidth > t.clientWidth, second: Math.round(second), hidden, pause, inert: document.querySelectorAll('[inert]').length, stacked: getComputedStyle(document.querySelector('[data-id="w2fade"] .digi-nslider__track')).display };
    });
    ok(info.snap === 'x mandatory' && info.overflow === 'auto' && info.s.every(x => x.snap.includes('start')), 'Sin JavaScript: pista con scroll-snap x obligatorio y diapositivas alineadas al inicio.');
    ok(info.scrollable && info.second === 0 && info.s.every(x => x.w === info.width), 'Sin JavaScript se desplaza a la segunda diapositiva (táctil, rueda o barra), cada una del ancho de la pista.');
    ok(new Set(info.s.map(x => x.h)).size === 1, `Sin JavaScript, todas las diapositivas tienen la altura de la más alta (${info.s.map(x => x.h)}).`);
    ok(info.inert === 0 && info.stacked === 'flex', 'Sin JavaScript nada es inerte y fade/scale son la misma pista desplazable.');
    ok(info.hidden.join() === 'none,none,none' && info.pause === 'none', `La regla [hidden] oculta un contenedor flex, uno grid y una columna (${info.hidden}); la pausa espera al script.`);
    await page.close();
  }

  // 2. Con JavaScript, en tres anchos: estado inicial, tamaños, foco y ningún desplazamiento de diseño.
  for (const width of [360, 768, 1280]) {
    const page = await open({ width, reduce: true });
    const s1 = await state(page, 'w1slide'), s2 = await state(page, 'w2fade'), s3 = await state(page, 'w3scale');
    ok(s1.inert === 'X--' && s1.current === 'X--' && s1.visible === 'X--' && s1.live === 'polite', `${width}: slide arranca en la primera, las otras inertes, aria-current en el primer punto, región viva polite.`);
    ok(s2.inert === 'X--' && s2.visible === 'X--' && s2.pause === 'Reanudar' && s2.live === 'polite', `${width}: con movimiento reducido, fade no arranca solo («Reanudar») y su región viva es polite.`);
    ok(s3.inert === 'X--' && s3.visible === 'X--', `${width}: scale apilado en la primera.`);
    ok([s1, s2, s3].every(s => new Set(s.heights).size === 1), `${width}: en cada slider las diapositivas tienen la misma altura.`);
    const sizes = await page.evaluate(() => [...document.querySelectorAll('.digi-nslider button')].filter(b => b.offsetParent).map(b => { const r = b.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; }));
    ok(sizes.length >= 10 && sizes.every(([w, h]) => w >= 24 && h >= 24), `${width}: todos los botones miden 24 × 24 px como mínimo (${sizes.length}).`);
    ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width}: sin desplazamiento horizontal de la página.`);
    const after = () => page.evaluate(() => Math.round(document.getElementById('fin').getBoundingClientRect().top + scrollY));
    const before = [s1.height, s2.height, s3.height, await after()];
    // Orden del foco con Tab.
    const order = [];
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
    for (let k = 0; k < 18; k++) { await page.keyboard.press('Tab'); order.push(await active(page)); }
    const expected = ['w1slide:Diapositiva anterior', 'w1slide:Ir a la diapositiva 1', 'w1slide:Ir a la diapositiva 2', 'w1slide:Ir a la diapositiva 3', 'w1slide:Diapositiva siguiente', 'w1slide:Enlace 1',
      'w2fade:Reanudar', 'w2fade:Diapositiva anterior', 'w2fade:Ir a la diapositiva 1', 'w2fade:Ir a la diapositiva 2', 'w2fade:Ir a la diapositiva 3', 'w2fade:Diapositiva siguiente', 'w2fade:Enlace 1',
      'w3scale:Diapositiva anterior', 'w3scale:Diapositiva siguiente', 'w3scale:Enlace 1', 'Enlace después de los sliders'];
    ok(order.slice(0, expected.length).join('|') === expected.join('|'), `${width}: orden del foco: pausa primero, controles antes que el contenido, sólo la diapositiva visible (las inertes y los puntos ocultos fuera).\n${order.join('|')}`);
    // Teclado sobre los puntos: la flecha mueve la diapositiva y el foco.
    await page.focus('[data-id="w1slide"] .digi-nslider__dot');
    await page.keyboard.press('ArrowRight');
    await settle(page);
    let s = await state(page, 'w1slide');
    ok(s.inert === '-X-' && s.current === '-X-' && s.visible === '-X-' && (await active(page)) === 'w1slide:Ir a la diapositiva 2', `${width}: flecha derecha en un punto: segunda diapositiva visible y el foco en su punto.`);
    await page.keyboard.press('End');
    await settle(page);
    s = await state(page, 'w1slide');
    ok(s.visible === '--X' && s.current === '--X', `${width}: Fin lleva a la última.`);
    // Las flechas con el foco en el contenido no mueven nada.
    await page.focus('[data-id="w1slide"] .e-con:last-child a');
    await page.keyboard.press('ArrowLeft');
    await settle(page);
    ok((await state(page, 'w1slide')).current === '--X', `${width}: con el foco en el contenido de la diapositiva, las flechas del teclado no cambian de diapositiva.`);
    // Sin bucle, «siguiente» en la última no hace nada; «anterior» sí.
    await page.click('[data-id="w1slide"] .digi-nslider__next');
    await page.click('[data-id="w1slide"] .digi-nslider__prev');
    await settle(page);
    s = await state(page, 'w1slide');
    ok(s.visible === '-X-', `${width}: sin bucle, «siguiente» en la última no hace nada y «anterior» retrocede. ${JSON.stringify(s)}`);
    // scale con bucle: «anterior» desde la primera va a la última; el foco se queda en el botón.
    await page.click('[data-id="w3scale"] .digi-nslider__prev');
    await sleep(50);
    ok((await state(page, 'w3scale')).visible === '--X' && (await page.evaluate(() => document.activeElement.classList.contains('digi-nslider__prev'))), `${width}: scale con bucle: de la primera a la última; el foco sigue en «anterior».`);
    // fade con el ratón en los puntos.
    await page.click('[data-id="w2fade"] .digi-nslider__dot:nth-child(3)');
    await sleep(50);
    ok((await state(page, 'w2fade')).visible === '--X', `${width}: un punto de fade muestra su diapositiva.`);
    // Ningún desplazamiento de diseño entre pases.
    const s1b = await state(page, 'w1slide'), s2b = await state(page, 'w2fade'), s3b = await state(page, 'w3scale');
    ok([s1b.height, s2b.height, s3b.height, await after()].join() === before.join(), `${width}: las alturas y la posición de lo que sigue no cambian entre pases (${before}).`);
    ok((await page.evaluate(() => window.__shift)) === 0, `${width}: ningún layout-shift.`);
    await page.close();
  }

  // 3. Reproducción automática (sin movimiento reducido), pausas y región viva.
  {
    const page = await open({ width: 1024 });
    let s = await state(page, 'w2fade');
    ok(s.pause === 'Pausar' && s.live === 'off' && s.visible === 'X--', 'Con reproducción automática: botón «Pausar» visible y región viva off.');
    const top = await page.evaluate(() => Math.round(document.getElementById('fin').getBoundingClientRect().top));
    await sleep(2900);
    s = await state(page, 'w2fade');
    ok(s.visible === '-X-' && s.inert === '-X-' && s.current === '-X-', 'A los 2 s pasa a la segunda diapositiva (fundido terminado).');
    ok((await page.evaluate(() => window.__shift)) === 0 && top === await page.evaluate(() => Math.round(document.getElementById('fin').getBoundingClientRect().top)), 'El pase automático no desplaza nada (layout-shift 0 sin interacción).');
    // Puntero encima: se detiene.
    const box = await (await page.$('[data-id="w2fade"] .digi-nslider__track')).boundingBox();
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await sleep(2900);
    ok((await state(page, 'w2fade')).visible === '-X-', 'Con el puntero encima no avanza.');
    await page.mouse.move(2, 2);
    await sleep(2900);
    ok((await state(page, 'w2fade')).visible === '--X', 'Al salir el puntero sigue.');
    // Foco dentro: se detiene y la región viva pasa a polite.
    await page.focus('[data-id="w2fade"] .digi-nslider__dot');
    s = await state(page, 'w2fade');
    await sleep(2900);
    ok(s.live === 'polite' && (await state(page, 'w2fade')).visible === '--X', 'Con el foco en el slider no avanza y la región viva es polite.');
    await page.focus('#fin');
    // Botón de pausa (primero en el orden del foco).
    await page.click('[data-id="w2fade"] .digi-nslider__pause');
    s = await state(page, 'w2fade');
    await page.mouse.move(2, 2);
    await page.focus('#fin');
    await sleep(2900);
    ok(s.pause === 'Reanudar' && (await state(page, 'w2fade')).visible === s.visible && (await state(page, 'w2fade')).live === 'polite', '«Pausar» la detiene aunque el foco salga, dice «Reanudar» y la región viva queda polite.');
    await page.click('[data-id="w2fade"] .digi-nslider__pause');
    await page.mouse.move(2, 2);
    await page.focus('#fin');
    const resumed = (await state(page, 'w2fade')).visible;
    await sleep(2900);
    ok((await state(page, 'w2fade')).visible !== resumed && (await state(page, 'w2fade')).pause === 'Pausar', '«Reanudar» la pone en marcha otra vez.');
    // Fuera de la pantalla: se detiene.
    await page.evaluate(() => { const d = document.createElement('div'); d.style.height = '3000px'; document.body.append(d); scrollTo(0, document.body.scrollHeight); });
    await sleep(300);
    const away = (await state(page, 'w2fade')).visible;
    await sleep(2900);
    ok((await state(page, 'w2fade')).visible === away, 'Fuera de la pantalla no avanza (IntersectionObserver).');
    await page.evaluate(() => scrollTo(0, 0));
    await sleep(3000);
    ok((await state(page, 'w2fade')).visible !== away, 'Al volver a la pantalla sigue.');
    await page.close();
  }

  // 4. Árbol de accesibilidad (lo que lee un lector de pantalla).
  {
    const page = await open({ width: 1024, reduce: true });
    const region = await page.$('[data-id="w1slide"] .digi-nslider');
    const tree = await page.accessibility.snapshot({ root: region, interestingOnly: false });
    const flat = [];
    const walk = n => { flat.push(n); (n.children || []).forEach(walk); };
    walk(tree);
    ok(tree.role === 'region' && tree.name === 'Novedades' && tree.roledescription === 'carrusel', `La región: rol region, nombre «Novedades» y descripción «carrusel» (${tree.role}, ${tree.name}, ${tree.roledescription}).`);
    const buttons = flat.filter(n => n.role === 'button').map(n => n.name);
    ok(buttons.join('|') === 'Diapositiva anterior|Ir a la diapositiva 1|Ir a la diapositiva 2|Ir a la diapositiva 3|Diapositiva siguiente', `Botones con nombre, en orden (${buttons.join('|')}).`);
    const groups = flat.filter(n => n.role === 'group' && /de 3$/.test(n.name || ''));
    ok(groups.length === 1 && groups[0].name === '1 de 3' && groups[0].roledescription === 'diapositiva', `Sólo la diapositiva visible está en el árbol: grupo «1 de 3», «diapositiva» (${groups.map(g => g.name)}).`);
    ok(!flat.some(n => /Enlace 2|Enlace 3|Diapositiva 2|Diapositiva 3/.test(n.name || '')), 'El contenido de las diapositivas inertes no está en el árbol.');
    const dot = flat.find(n => n.name === 'Ir a la diapositiva 1');
    ok(dot && !('selected' in dot), 'Los puntos son botones (sin aria-selected).');
    await page.close();
  }
  // 5. Contador (F8, V.10.4).
  const counter = (page, id) => page.evaluate(id => {
    const r = document.querySelector(`[data-id="${id}"] .digi-nslider`), c = r.querySelector(':scope>.digi-nslider__count');
    const box = el => { if (!el) return null; const b = el.getBoundingClientRect(); return { l: b.left, r: b.right, t: b.top, b: b.bottom, cx: (b.left + b.right) / 2, cy: (b.top + b.bottom) / 2, w: b.width, h: b.height }; };
    const cs = getComputedStyle(c);
    const kid = n => r.querySelector(':scope>.digi-nslider__' + n);
    return {
      text: c.textContent, hidden: c.hidden, visibility: cs.visibility, display: cs.display, nums: cs.fontVariantNumeric, color: cs.color, sectionColor: getComputedStyle(r).color,
      area: [cs.gridRowStart, cs.gridColumnStart].join('/'), c: box(c), section: box(r), track: box(kid('track')), dots: kid('dots').hidden ? null : box(kid('dots')), prev: box(kid('prev')), next: box(kid('next')),
    };
  }, id);
  {
    // Sin JavaScript: no se ve, pero su sitio está reservado (la altura es la misma que con el script).
    const noJs = await open({ js: false, width: 1280 });
    const off = {};
    for (const id of ['w4both', 'w5arrows', 'w6sides', 'w7auto']) off[id] = await counter(noJs, id);
    ok(Object.values(off).every(c => c.hidden && c.visibility === 'hidden' && c.display === 'block'), 'Sin JavaScript el contador no se ve (visibility hidden), pero ocupa su celda.');
    await noJs.close();
    for (const width of [360, 1280]) {
      const page = await open({ width, reduce: true });
      const on = {};
      for (const id of ['w4both', 'w5arrows', 'w6sides', 'w7auto']) on[id] = await counter(page, id);
      for (const [id, c] of Object.entries(on)) {
        ok(!c.hidden && c.visibility === 'visible' && c.text === '1 / 3', `${width} ${id}: con el script se ve «1 / 3» (${c.text}, ${c.visibility}).`);
        ok(/tabular-nums/.test(c.nums) && c.color === c.sectionColor, `${width} ${id}: cifras tabulares y el color de la navegación (${c.nums}, ${c.color}).`);
        ok(Math.abs(c.c.cx - c.section.cx) < 2 && c.c.l >= c.section.l && c.c.r <= c.section.r, `${width} ${id}: centrado en la columna central del slider.`);
        if (width === 1280) ok(Math.round(c.section.h) === Math.round(off[id].section.h), `${id}: la misma altura con y sin JavaScript (${Math.round(off[id].section.h)} → ${Math.round(c.section.h)}): mostrarlo no desplaza nada.`);
      }
      const both = on.w4both, arrows = on.w5arrows, sides = on.w6sides, auto = on.w7auto;
      ok(both.area === '3/2' && both.c.t >= both.dots.b && both.c.t >= both.prev.b - 1, `${width}: flechas y puntos debajo: el contador va en la fila 3, bajo los puntos (${both.area}).`);
      ok(arrows.area === '2/2' && arrows.dots === null && Math.abs(arrows.c.cy - arrows.prev.cy) < 3 && arrows.prev.r <= arrows.c.l && arrows.c.r <= arrows.next.l, `${width}: sólo flechas debajo: el contador ocupa la celda de los puntos, entre las flechas y a su altura (${arrows.area}).`);
      ok(sides.area === '2/2' && sides.dots === null && sides.c.t >= sides.track.b && sides.prev.cy < sides.track.b, `${width}: sólo flechas a los lados: el contador va bajo la pista, en la fila 2 (${sides.area}).`);
      ok(auto.area === '3/2' && auto.c.t >= auto.dots.b && auto.prev.cy < auto.track.b, `${width}: flechas a los lados con puntos: el contador bajo los puntos (${auto.area}).`);
      ok((await page.evaluate(() => window.__shift)) === 0, `${width}: ningún layout-shift con los contadores.`);
      // Botones, teclado y bucle.
      await page.click('[data-id="w4both"] .digi-nslider__next');
      await settle(page);
      ok((await counter(page, 'w4both')).text === '2 / 3', `${width}: «siguiente» lo pasa a «2 / 3».`);
      await page.focus('[data-id="w4both"] .digi-nslider__dot');
      await page.keyboard.press('End');
      await settle(page);
      ok((await counter(page, 'w4both')).text === '3 / 3', `${width}: Fin en un punto: «3 / 3».`);
      await page.click('[data-id="w6sides"] .digi-nslider__next');
      await page.click('[data-id="w6sides"] .digi-nslider__next');
      await page.click('[data-id="w6sides"] .digi-nslider__next');
      await settle(page);
      ok((await counter(page, 'w6sides')).text === '3 / 3', `${width}: sin bucle, se queda en «3 / 3».`);
      await page.click('[data-id="w5arrows"] .digi-nslider__prev');
      await settle(page);
      ok((await counter(page, 'w5arrows')).text === '3 / 3', `${width}: con bucle, «anterior» desde la primera: «3 / 3».`);
      await page.close();
    }
    // Árbol de accesibilidad: el contador no está (aria-hidden); los grupos «N de M» siguen.
    const a11y = await open({ width: 1024, reduce: true });
    const tree = await a11y.accessibility.snapshot({ root: await a11y.$('[data-id="w4both"] .digi-nslider'), interestingOnly: false });
    const flat = [];
    const walk = n => { flat.push(n); (n.children || []).forEach(walk); };
    walk(tree);
    ok(!flat.some(n => /\d\s*\/\s*3/.test(n.name || '')) && flat.some(n => n.role === 'group' && n.name === '1 de 3'), 'El contador no está en el árbol de accesibilidad; la diapositiva sigue anunciándose como «1 de 3».');
    await a11y.close();
    // Reproducción automática: el contador la sigue.
    const auto = await open({ width: 1024 });
    await auto.evaluate(() => document.querySelector('[data-id="w7auto"]').scrollIntoView());
    await auto.mouse.move(2, 2);
    await sleep(2900);
    ok((await counter(auto, 'w7auto')).text === '2 / 3', 'Con reproducción automática, el contador pasa solo a «2 / 3».');
    await auto.close();
  }
  ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
} finally {
  await browser.close();
}
console.log(`F7b, slider anidado en Chrome: ${checks} comprobaciones en verde.`);
