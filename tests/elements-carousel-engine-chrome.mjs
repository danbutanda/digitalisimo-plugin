// Motor de carrusel digi-crs (C1, frente E de docs/pro/c1.md §1.3) en Chrome real (sin interfaz) sobre
// tests/fixtures/carousel-engine.html, que es el marcado de Engine (lo comprueba tests/elements-carousel-engine.php):
// - sin JavaScript: pista con scroll-snap desplazable, nada inerte, controles a la vista;
// - con JavaScript (1280 y 375 px): --ready en todos; páginas con varias diapositivas visibles y paso de 2 (puntos que
//   sobran ocultos, aria-current, inert fuera de la ventana, flechas aria-disabled en los extremos sin bucle), flechas,
//   puntos y teclado (flechas, Inicio y Fin; foco en el punto), API digiCrs y eventos digi-crs-go / digi-crs-change;
//   deslizamiento del usuario; botones de 24 × 24 px como mínimo y flechas en su sitio (10 px, centradas en la pista);
// - fundido con fracción y reproducción automática (avanza; región viva off; pausa con el puntero encima, con el foco
//   dentro, con el botón Pausar/Reanudar y fuera de la pantalla), detención tras una interacción, movimiento reducido;
// - RTL con barra de progreso y bucle (rebobina); miniaturas centradas sincronizadas con el carrusel principal; una sola
//   diapositiva y todo en una página (--locked); el editor (sin autoplay y reinicio con frontend/element_ready/global).
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-carousel-engine-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del motor de carrusel (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/carousel-engine.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ width = 1280, js = true, reduce = false, editor = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setJavaScriptEnabled(js);
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  await page.evaluateOnNewDocument(editor => {
    window.__changes = [];
    document.addEventListener('digi-crs-change', e => window.__changes.push([e.target.closest('[data-id]').dataset.id, e.detail.index]));
    if (editor) window.elementorFrontend = { isEditMode: () => true, hooks: { addAction: (n, f) => { window.__hook = [n, f]; } } };
  }, editor);
  await page.goto(fixture, { waitUntil: 'load' });
  if (js) await page.waitForFunction(() => document.querySelectorAll('.digi-crs').length === 9 && !document.querySelector('.digi-crs:not(.digi-crs--ready)'));
  return page;
}
// Estado de un carrusel.
const state = (page, id) => page.evaluate(id => {
  const r = document.querySelector(`[data-id="${id}"] .digi-crs`), t = r.querySelector(':scope>.digi-crs__track'), q = s => r.querySelector(':scope>' + s);
  const dots = [...r.querySelectorAll(':scope>.digi-crs__pagination>.digi-crs__dot')];
  return {
    inert: [...t.children].map(s => (s.inert ? '-' : 'X')).join(''),
    current: [...t.children].findIndex(s => s.classList.contains('digi-crs__current')),
    dots: dots.map(d => (d.hidden ? '_' : d.getAttribute('aria-current') === 'true' ? 'X' : '-')).join(''),
    labels: dots.filter(d => !d.hidden).map(d => d.getAttribute('aria-label')),
    prev: q('.digi-crs__prev')?.getAttribute('aria-disabled') ?? null,
    next: q('.digi-crs__next')?.getAttribute('aria-disabled') ?? null,
    live: t.getAttribute('aria-live'),
    pause: q('.digi-crs__pause') ? { label: q('.digi-crs__pause').getAttribute('aria-label'), hidden: q('.digi-crs__pause').hidden, d: q('.digi-crs__pause path').getAttribute('d') } : null,
    paused: r.classList.contains('digi-crs--paused'),
    locked: r.classList.contains('digi-crs--locked'),
    scroll: Math.round(t.scrollLeft),
    off: [...t.children].map(s => s.offsetLeft - t.children[0].offsetLeft),
    now: r.querySelector('.digi-crs__now')?.textContent ?? null,
    total: r.querySelector('.digi-crs__total')?.textContent ?? null,
    fill: r.querySelector('.digi-crs__fill')?.style.width ?? null,
    synced: [...t.children].findIndex(s => s.classList.contains('digi-crs__synced')),
    api: r.digiCrs ? [r.digiCrs.index(), r.digiCrs.count()] : null,
    opacity: [...t.children].map(s => getComputedStyle(s).opacity),
  };
}, id);
const settle = page => page.waitForFunction(() => [...document.querySelectorAll('.digi-crs__track')].every(t => { const v = t.scrollLeft; const same = t.__last === v; t.__last = v; return same; }), { polling: 200, timeout: 5000 });
const click = async (page, id, sel) => { await page.click(`[data-id="${id}"] .digi-crs > ${sel}`); await sleep(50); };

try {
  // 1. Sin JavaScript.
  {
    const page = await open({ js: false });
    const info = await page.evaluate(() => {
      const t = document.querySelector('[data-id="w1"] .digi-crs__track');
      t.scrollLeft = 350;
      const r = t.getBoundingClientRect(), s = [...t.children].map(x => Math.round(x.getBoundingClientRect().width));
      return { left: t.scrollLeft, snap: getComputedStyle(t).scrollSnapType, inert: document.querySelectorAll('[inert]').length, ready: document.querySelectorAll('.digi-crs--ready').length, w: Math.round(r.width), s, gap: t.children[1].getBoundingClientRect().left - t.children[0].getBoundingClientRect().right };
    });
    ok(info.left > 0 && /x mandatory/.test(info.snap), 'Sin JavaScript la pista se desplaza con scroll-snap: ' + JSON.stringify(info));
    ok(info.inert === 0 && info.ready === 0, 'Sin JavaScript nada es inerte ni está listo.');
    ok(Math.abs(info.s[0] * 3 + 20 - info.w) <= 2 && Math.abs(info.gap - 10) < 1, `Tres visibles con 10 px de separación (las variables mandan sin script): ${JSON.stringify(info)}`);
    await page.close();
  }

  for (const width of [1280, 375]) {
    const page = await open({ width });
    const w2start = await state(page, 'w2');
    // 2. Varias visibles y paso de 2: 6 diapositivas, 3 visibles → páginas en 0, 2 y 3.
    let s = await state(page, 'w1');
    ok(s.inert === 'XXX---' && s.current === 0 && s.dots === 'X--___' && s.prev === 'true' && s.next === 'false' && s.live === 'polite' && s.pause === null, `${width}: estado inicial de w1 ${JSON.stringify(s)}`);
    ok(JSON.stringify(s.labels) === JSON.stringify(['Ir a la diapositiva 1', 'Ir a la diapositiva 3', 'Ir a la diapositiva 4']), `${width}: los puntos nombran la diapositiva de su página: ${s.labels}`);
    const geo = await page.evaluate(() => {
      const r = document.querySelector('[data-id="w1"] .digi-crs'), t = r.querySelector('.digi-crs__track').getBoundingClientRect().toJSON(), b = sel => r.querySelector(sel).getBoundingClientRect().toJSON(), icon = b('.digi-crs__prev svg');
      return { prev: b('.digi-crs__prev'), dot: b('.digi-crs__dot'), root: r.getBoundingClientRect().toJSON(), track: t, icon, pag: b('.digi-crs__pagination'), color: getComputedStyle(r.querySelector('.digi-crs__prev')).color, bg: getComputedStyle(r.querySelector('.digi-crs__prev')).backgroundColor };
    });
    ok(geo.prev.width >= 24 && geo.prev.height >= 24 && geo.dot.width >= 24 && geo.dot.height >= 24, `${width}: flechas y puntos de 24 × 24 px como mínimo: ${JSON.stringify([geo.prev, geo.dot])}`);
    ok(Math.abs(geo.icon.left - geo.root.left - 10) <= 1 && Math.abs(geo.icon.top + geo.icon.height / 2 - (geo.track.top + geo.track.height / 2)) <= 1.5 && Math.abs(geo.icon.width - 25) <= 1, `${width}: icono de 25 px a 10 px del borde y centrado en la pista (paginación fuera): ${JSON.stringify([geo.icon, geo.track])}`);
    ok(Math.abs(geo.root.bottom - geo.track.bottom - 30) <= 1 && geo.pag.bottom <= geo.root.bottom - 4 && geo.pag.top >= geo.track.bottom - 1, `${width}: paginación fuera: 30 px bajo la pista y los puntos a 5 px del borde.`);
    ok(geo.color === 'rgba(238, 238, 238, 0.9)' && geo.bg === 'rgba(0, 0, 0, 0)', `${width}: flechas del color de PRO sin el fondo del tema: ${geo.color} ${geo.bg}`);

    await click(page, 'w1', '.digi-crs__next');
    await settle(page);
    s = await state(page, 'w1');
    ok(s.inert === '--XXX-' && s.current === 2 && s.dots === '-X-___' && s.prev === 'false' && Math.abs(s.scroll - s.off[2]) <= 1, `${width}: siguiente → página 2: ${JSON.stringify(s)}`);
    await click(page, 'w1', '.digi-crs__next');
    await settle(page);
    s = await state(page, 'w1');
    ok(s.inert === '---XXX' && s.current === 3 && s.next === 'true' && Math.abs(s.scroll - s.off[3]) <= 1, `${width}: la última página llega al final sin hueco: ${JSON.stringify(s)}`);
    await click(page, 'w1', '.digi-crs__next');
    s = await state(page, 'w1');
    ok(s.current === 3, `${width}: en el extremo sin bucle la flecha no hace nada.`);
    // Teclado en los controles.
    await page.focus('[data-id="w1"] .digi-crs__prev');
    await page.keyboard.press('Home');
    await settle(page);
    s = await state(page, 'w1');
    ok(s.current === 0 && s.dots === 'X--___', `${width}: Inicio → primera página.`);
    await page.keyboard.press('ArrowRight');
    s = await state(page, 'w1');
    ok(s.current === 2, `${width}: flecha derecha en un control → siguiente.`);
    await page.focus('[data-id="w1"] .digi-crs__dot');
    await page.keyboard.press('End');
    await settle(page);
    s = await state(page, 'w1');
    const focused = await page.evaluate(() => document.activeElement.getAttribute('aria-label'));
    ok(s.current === 3 && focused === 'Ir a la diapositiva 4', `${width}: Fin desde un punto → última página y el foco en su punto (${focused}).`);
    await page.click('[data-id="w1"] .digi-crs__dot:nth-child(2)');
    await settle(page);
    s = await state(page, 'w1');
    ok(s.current === 2 && s.dots === '-X-___', `${width}: un punto lleva a su página.`);
    // API y eventos.
    await page.evaluate(() => { const r = document.querySelector('[data-id="w1"] .digi-crs'); r.digiCrs.go(5); });
    s = await state(page, 'w1');
    ok(s.current === 3 && JSON.stringify(s.api) === '[3,6]', `${width}: digiCrs.go(5) → la página que la muestra; index() y count(): ${s.api}`);
    await page.evaluate(() => document.querySelector('[data-id="w1"] .digi-crs').dispatchEvent(new CustomEvent('digi-crs-go', { detail: 0 })));
    await settle(page);
    s = await state(page, 'w1');
    const changes = await page.evaluate(() => window.__changes.filter(c => c[0] === 'w1').map(c => c[1]).join(','));
    ok(s.current === 0 && changes.endsWith('3,0') && changes.startsWith('2,3'), `${width}: digi-crs-go y digi-crs-change con su índice (${changes}).`);
    // Deslizamiento del usuario.
    await page.evaluate(() => { const t = document.querySelector('[data-id="w1"] .digi-crs__track'); t.scrollTo({ left: t.children[2].offsetLeft - t.children[0].offsetLeft, behavior: 'instant' }); });
    await settle(page);
    await sleep(250);
    s = await state(page, 'w1');
    ok(s.current === 2 && s.dots === '-X-___', `${width}: deslizar la pista actualiza el estado: ${JSON.stringify(s)}`);

    // 3. Fundido con fracción y autoplay de 1 s, bucle y pausa con el puntero.
    s = w2start;
    ok(s.now === '1' && s.total === '4' && s.live === 'off' && s.pause && !s.pause.hidden && s.pause.label === 'Pausar' && s.opacity[0] === '1' && s.opacity[1] === '0', `${width}: fundido en marcha: ${JSON.stringify(s)}`);
    const before = (await state(page, 'w2')).current;
    await page.waitForFunction(b => document.querySelector('[data-id="w2"] .digi-crs__current') !== document.querySelector('[data-id="w2"] .digi-crs__track').children[b], { timeout: 3000 }, before);
    await sleep(350);
    s = await state(page, 'w2');
    ok(s.current === (before + 1) % 4 && s.inert === [0, 1, 2, 3].map(k => (k === s.current ? 'X' : '-')).join('') && s.opacity[s.current] === '1' && s.opacity[before] === '0' && s.now === String(s.current + 1), `${width}: la reproducción automática avanza y funde: ${JSON.stringify(s)}`);
    const box = await page.$eval('[data-id="w2"] .digi-crs__track', e => { const r = e.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + 20 }; });
    await page.mouse.move(box.x, box.y);
    await sleep(50);
    s = await state(page, 'w2');
    const at = s.now;
    await sleep(1400);
    ok((await state(page, 'w2')).now === at && s.live === 'polite', `${width}: con el puntero encima se detiene (región viva polite).`);
    await page.mouse.move(1, 899);
    await page.waitForFunction(at => document.querySelector('[data-id="w2"] .digi-crs__now').textContent !== at, { timeout: 3000 }, at);
    ok(true, `${width}: al salir el puntero sigue.`);
    await click(page, 'w2', '.digi-crs__pause');
    s = await state(page, 'w2');
    ok(s.paused && s.pause.label === 'Reanudar' && s.pause.d === 'M8 5v14l11-7z' && s.live === 'polite', `${width}: Pausar → Reanudar, con su icono: ${JSON.stringify(s.pause)}`);
    const held = s.now;
    await sleep(1300);
    ok((await state(page, 'w2')).now === held, `${width}: pausado no avanza.`);
    await click(page, 'w2', '.digi-crs__pause');
    await page.focus('[data-id="w2"] .digi-crs__next');
    s = await state(page, 'w2');
    ok(!s.paused && s.live === 'polite', `${width}: reanudado, pero con el foco dentro no avanza.`);
    await page.mouse.move(1, 899);
    await page.evaluate(() => document.activeElement.blur());
    await page.waitForFunction(() => document.querySelector('[data-id="w2"] .digi-crs__track').getAttribute('aria-live') === 'off');
    ok(true, `${width}: sin foco ni puntero, vuelve a reproducir (región viva off).`);
    // Bucle por rebobinado: desde la última, siguiente → la primera.
    await page.evaluate(() => document.querySelector('[data-id="w2"] .digi-crs').digiCrs.go(3));
    await click(page, 'w2', '.digi-crs__next');
    s = await state(page, 'w2');
    ok(s.now === '1' && s.next === 'false' && s.prev === 'false', `${width}: con bucle, rebobina y ninguna flecha se desactiva.`);
    // Detención tras una interacción (w7).
    await click(page, 'w7', '.digi-crs__next');
    s = await state(page, 'w7');
    ok(s.paused && s.pause.label === 'Reanudar' && s.current === 1, `${width}: con «detener tras una interacción», una acción la detiene.`);
    // Fuera de la pantalla no avanza (w9, al final de la página).
    s = await state(page, 'w9');
    await sleep(900);
    ok((await state(page, 'w9')).current === s.current && !s.paused && s.live === 'polite', `${width}: fuera de la pantalla no avanza.`);
    await page.$eval('[data-id="w9"]', e => e.scrollIntoView());
    await page.waitForFunction(() => document.querySelector('[data-id="w9"] .digi-crs__current') !== document.querySelector('[data-id="w9"] .digi-crs__track').children[0], { timeout: 3000 });
    ok(true, `${width}: a la vista, avanza.`);
    await page.evaluate(() => window.scrollTo(0, 0));

    // 4. RTL con barra y bucle.
    s = await state(page, 'w3');
    ok(s.fill === '20%' && s.inert === 'X----', `${width}: barra de progreso al 20 % en la primera de cinco.`);
    await page.focus('[data-id="w3"] .digi-crs__next');
    await page.keyboard.press('ArrowLeft');
    await settle(page);
    s = await state(page, 'w3');
    ok(s.current === 1 && s.fill === '40%' && s.scroll < 0 && Math.abs(s.scroll - s.off[1]) <= 1, `${width}: en RTL la flecha izquierda avanza y la pista se desplaza hacia la izquierda: ${JSON.stringify(s)}`);
    const rtl = await page.evaluate(() => { const r = document.querySelector('[data-id="w3"] .digi-crs'), b = s => r.querySelector(s).getBoundingClientRect(), x = r.getBoundingClientRect(); return { next: b('.digi-crs__next').left - x.left, prev: x.right - b('.digi-crs__prev').right, bar: b('.digi-crs__bar').top - x.top }; });
    ok(rtl.next < 40 && rtl.prev < 40 && rtl.bar === 0, `${width}: en RTL la siguiente va a la izquierda y la barra arriba: ${JSON.stringify(rtl)}`);

    // 5. Miniaturas centradas y sincronizadas.
    s = await state(page, 'w4');
    ok(s.synced === 0 && s.current === 0 && Math.abs(s.scroll) <= 1, `${width}: miniatura 1 marcada y centrada.`);
    const centered = await page.evaluate(() => { const t = document.querySelector('[data-id="w4"] .digi-crs__track'), a = t.getBoundingClientRect(), b = t.children[0].getBoundingClientRect(); return Math.abs(a.left + a.width / 2 - (b.left + b.width / 2)); });
    ok(centered <= 1.5, `${width}: la actual centrada (${centered}).`);
    await click(page, 'w5', '.digi-crs__next');
    await settle(page);
    s = await state(page, 'w4');
    ok(s.synced === 1 && s.current === 1 && Math.abs(s.scroll - s.off[1]) <= 1, `${width}: el principal avanza y la miniatura le sigue: ${JSON.stringify(s)}`);
    await page.click('[data-id="w4"] .digi-crs__track > :nth-child(3) .s');
    await settle(page);
    ok((await state(page, 'w5')).current === 2 && (await state(page, 'w4')).synced === 2 && (await state(page, 'w4')).current === 2, `${width}: pulsar una miniatura visible mueve el principal (y se centra).`);

    // 6. Una sola diapositiva y todo en una página.
    s = await state(page, 'w6');
    ok(s.locked && s.inert === 'X' && s.prev === null, `${width}: una diapositiva: sin controles.`);
    s = await state(page, 'w8');
    const hiddenCtl = await page.evaluate(() => [...document.querySelectorAll('[data-id="w8"] .digi-crs > :not(.digi-crs__track)')].every(e => getComputedStyle(e).display === 'none'));
    ok(s.locked && s.inert === 'XXX' && hiddenCtl, `${width}: tres diapositivas con cuatro visibles: todo a la vista y controles ocultos.`);
    await page.close();
  }

  // 7. Movimiento reducido: sin reproducción automática; el botón ofrece Reanudar.
  {
    const page = await open({ reduce: true });
    const s = await state(page, 'w2');
    await sleep(1300);
    ok(s.paused && s.pause.label === 'Reanudar' && s.live === 'polite' && (await state(page, 'w2')).now === '1', 'Movimiento reducido: no arranca solo.');
    await click(page, 'w2', '.digi-crs__pause');
    await page.mouse.move(1, 899);
    await page.waitForFunction(() => document.querySelector('[data-id="w2"] .digi-crs__now').textContent === '2', { timeout: 3000 });
    ok(true, 'Movimiento reducido: al pedirlo, reproduce.');
    await page.close();
  }

  // 8. Editor: sin reproducción automática y reinicio con frontend/element_ready/global.
  {
    const page = await open({ editor: true });
    const hook = await page.evaluate(() => window.__hook?.[0]);
    const s = await state(page, 'w2');
    await sleep(1300);
    ok(hook === 'frontend/element_ready/global' && (await state(page, 'w2')).now === '1' && s.live === 'polite', 'Editor: sin reproducción automática y con el gancho global.');
    const again = await page.evaluate(() => {
      const w = document.querySelector('[data-id="w1"]'), copy = w.cloneNode(true);
      copy.dataset.id = 'w10';
      copy.querySelector('.digi-crs').classList.remove('digi-crs--ready');
      document.body.prepend(copy);
      window.__hook[1]([copy]);
      return copy.querySelector('.digi-crs').classList.contains('digi-crs--ready') && !!copy.querySelector('.digi-crs').digiCrs;
    });
    ok(again, 'Editor: un elemento que se vuelve a pintar se inicia de nuevo.');
    await page.close();
  }
  ok(errors.length === 0, 'Sin errores en la consola: ' + errors.join(' | '));
  console.log(`DIGITALÍSIMO Elements: motor de carrusel digi-crs en Chrome validado: ${checks} comprobaciones.`);
} finally {
  await browser.close();
}
