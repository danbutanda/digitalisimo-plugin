// Motor de carrusel digi-crs (C1, frente E de docs/pro/c1.md §1.3) en Chrome real (sin interfaz) sobre
// tests/fixtures/crs-engine.html, que es el marcado de Engine (lo comprueba tests/elements-crs-engine.php):
// - sin JavaScript: pista con scroll-snap desplazable, nada inerte, controles a la vista;
// - con JavaScript (1280 y 375 px): --ready en todos; páginas con varias diapositivas visibles y paso de 2 (puntos que
//   sobran ocultos, aria-current, inert fuera de la ventana, flechas aria-disabled en los extremos sin bucle), flechas,
//   puntos y teclado (flechas, Inicio y Fin; foco en el punto), API digiCrs y eventos digi-crs-go / digi-crs-change;
//   deslizamiento del usuario; flechas de 24 × 24 px como mínimo y en su sitio (10 px, centradas en la pista); puntos con
//   el paso de PRO (18 px) y 24 px de área táctil en vertical;
// - fundido con fracción y reproducción automática (avanza; región viva off; pausa con el puntero encima, con el foco
//   dentro, con el botón Pausar/Reanudar —oculto a la vista salvo con el foco del teclado— y fuera de la pantalla),
//   detención tras una interacción, movimiento reducido;
// - RTL con barra de progreso y bucle; miniaturas centradas sincronizadas con el carrusel principal; una sola diapositiva y
//   todo en una página sin bucle (--locked: flechas atenuadas, sin paginación); el editor (sin autoplay, reinicio con
//   frontend/element_ready/global y, en el carrusel anidado, sin copias);
// - bucle real como Swiper (docs/pro/c1.md): copias a los lados, siguiente y reproducción automática siempre hacia delante,
//   todo a la vista sin bloqueo, miniaturas centradas con la última a la izquierda, copias repetidas con pocas
//   diapositivas, manejadores del núcleo sobre las copias; arrastre con el ratón (cursor de agarre, gesto rápido, clic
//   anulado tras arrastrar, sin seleccionar texto) y clic en una diapositiva; puntos del carrusel anidado pegados al borde
//   y visibles sin acotar al número de diapositivas; bucle con paso 2 y un número impar de diapositivas (w25): de página en
//   página, con la actual, el punto y lo que se ve de acuerdo tras flechas, arrastre, clic y API.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-crs-engine-chrome.mjs
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
const fixture = new URL('./fixtures/crs-engine.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ width = 1280, js = true, reduce = false, editor = false, frontend = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setJavaScriptEnabled(js);
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  await page.evaluateOnNewDocument((editor, frontend) => {
    window.__changes = [];
    document.addEventListener('digi-crs-change', e => window.__changes.push([e.target.closest('[data-id]').dataset.id, e.detail.index]));
    if (editor) window.elementorFrontend = { isEditMode: () => true, hooks: { addAction: (n, f) => { window.__hook = [n, f]; } } };
    // Front con el núcleo: los manejadores que corren sobre las copias del bucle (w21 hace de carrusel anidado, con sus
    // diapositivas como elementos del núcleo, también en el editor).
    if (frontend) { window.__ready = []; window.elementorFrontend = { isEditMode: () => false, hooks: { addAction: () => {} }, elementsHandler: { runReadyTrigger: e => window.__ready.push(e) } }; }
    if (editor || frontend) document.addEventListener('readystatechange', () => { if (document.readyState === 'interactive') for (const e of document.querySelectorAll('[data-id="w21"] .digi-crs__track > *')) { e.classList.add('elementor-element'); e.innerHTML += '<div class="elementor-element">x</div>'; } });
  }, editor, frontend);
  await page.goto(fixture, { waitUntil: 'load' });
  if (js) await page.waitForFunction(() => document.querySelectorAll('body > [data-id] > .digi-crs').length === 23 && !document.querySelector('.digi-crs:not(.digi-crs--ready)'));
  return page;
}
// Estado de un carrusel.
const state = (page, id) => page.evaluate(id => {
  const r = document.querySelector(`[data-id="${id}"] .digi-crs`), t = r.querySelector(':scope>.digi-crs__track'), q = s => r.querySelector(':scope>' + s);
  const dots = [...r.querySelectorAll(':scope>.digi-crs__pagination>.digi-crs__dot')];
  // Con bucle la pista lleva copias: `inert` y `current` miran sólo las originales; `cur` es la diapositiva actual (también
  // si es una copia), `vis` las no inertes y `seen` las que se ven (su centro dentro de la pista), en orden visual.
  const kids = [...t.children], orig = kids.filter(s => !s.classList.contains('digi-crs__clone')), T = t.getBoundingClientRect(), num = s => parseInt(s.getAttribute('aria-label'), 10);
  const cur = t.querySelector(':scope>.digi-crs__current');
  return {
    inert: orig.map(s => (s.inert ? '-' : 'X')).join(''),
    current: orig.findIndex(s => s.classList.contains('digi-crs__current')),
    cur: cur ? num(cur) - 1 : -1,
    vis: kids.filter(s => !s.inert).map(num).join(','),
    seen: kids.map(s => [s.getBoundingClientRect(), num(s)]).filter(([b]) => b.left + b.width / 2 > T.left + 1 && b.left + b.width / 2 < T.right - 1).sort((a, b) => a[0].left - b[0].left).map(x => x[1]).join(','),
    clones: kids.length - orig.length,
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
// Pistas quietas: sin desplazamiento propio en curso (lo marca el imán apagado en línea) y sin cambios entre dos lecturas.
// Cada lectura anota todas las pistas (`map` y después `every`: con `every` solo, una pista nueva por lectura).
const settle = async page => { await sleep(60); await page.waitForFunction(() => [...document.querySelectorAll('.digi-crs__track')].map(t => { const v = t.scrollLeft; const same = t.__last === v && t.style.scrollSnapType !== 'none'; t.__last = v; return same; }).every(Boolean), { polling: 200, timeout: 5000 }); };
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
    ok(geo.prev.width >= 24 && geo.prev.height >= 24 && geo.dot.height >= 24, `${width}: flechas de 24 × 24 px como mínimo y puntos de 24 px de alto: ${JSON.stringify([geo.prev, geo.dot])}`);
    ok(Math.abs(geo.icon.left - geo.root.left - 10) <= 1 && Math.abs(geo.icon.top + geo.icon.height / 2 - (geo.track.top + geo.track.height / 2)) <= 1.5 && Math.abs(geo.icon.width - 25) <= 1, `${width}: icono de 25 px a 10 px del borde y centrado en la pista (paginación fuera): ${JSON.stringify([geo.icon, geo.track])}`);
    ok(Math.abs(geo.root.bottom - geo.track.bottom - 30) <= 1 && geo.pag.bottom <= geo.root.bottom - 4 && geo.pag.top >= geo.track.bottom - 1, `${width}: paginación fuera: 30 px bajo la pista y los puntos a 5 px del borde.`);
    ok(geo.color === 'rgba(237, 237, 237, 0.9)' && geo.bg === 'rgba(0, 0, 0, 0)', `${width}: flechas del color de PRO sin el fondo del tema: ${geo.color} ${geo.bg}`);
    const dg = await page.evaluate(() => {
      const r = document.querySelector('[data-id="w1"] .digi-crs'), R = r.getBoundingClientRect(), el = [...r.querySelectorAll('.digi-crs__dot:not([hidden])')];
      const ds = el.map(d => { const b = d.getBoundingClientRect(), a = getComputedStyle(d, '::before'); return { x: b.left + b.width / 2, y: b.bottom - parseFloat(getComputedStyle(d).paddingBottom) - parseFloat(a.height) / 2, w: b.width, h: b.height, l: b.left, r: b.right }; });
      // Área táctil: el botón de 24 px de alto (el punto, a 7 px de su pie): 9 px por debajo y 13 px por encima del centro
      // del punto responde ese punto.
      const hit = el.every((d, k) => [-13, 9].every(dy => document.elementFromPoint(ds[k].x, ds[k].y + dy) === d));
      return { gap: ds[1].x - ds[0].x, bottom: R.bottom - ds[0].y, center: (ds[0].x + ds[2].x) / 2 - (R.left + R.width / 2), w: ds[0].w, h: ds[0].h, overlap: ds[1].l < ds[0].r - 0.5, hit };
    });
    ok(Math.abs(dg.gap - 18) < 0.6 && Math.abs(dg.w - 18) < 0.6 && !dg.overlap && dg.hit && Math.abs(dg.bottom - 15) < 1 && Math.abs(dg.center) < 1 && dg.h >= 24, `${width}: puntos con el paso de PRO (6 + 2 × 6 = 18 px entre centros, sin solaparse), 24 px de área táctil en vertical, centro a 15 px del borde inferior como PRO y centrados: ${JSON.stringify(dg)}`);

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
    // Deslizamiento del usuario (rueda y desplazamiento).
    await page.evaluate(() => { const t = document.querySelector('[data-id="w1"] .digi-crs__track'); t.dispatchEvent(new WheelEvent('wheel', { deltaX: 300 })); t.scrollTo({ left: t.children[2].offsetLeft - t.children[0].offsetLeft, behavior: 'instant' }); });
    await settle(page);
    await sleep(250);
    s = await state(page, 'w1');
    ok(s.current === 2 && s.dots === '-X-___', `${width}: deslizar la pista actualiza el estado: ${JSON.stringify(s)}`);
    // Un reajuste del navegador sin acción del usuario no cambia de página: la pista vuelve a la suya.
    await page.evaluate(() => { document.querySelector('[data-id="w1"] .digi-crs__track').scrollLeft = 0; });
    await settle(page);
    await sleep(250);
    s = await state(page, 'w1');
    ok(s.current === 2 && Math.abs(s.scroll - s.off[2]) <= 1, `${width}: un desplazamiento que no hizo el usuario se deshace: ${JSON.stringify(s)}`);
    // Clic perdido (A/B de S): el diseño cambia en pleno movimiento y dos clics seguidos; la pista llega a su página.
    for (let round = 0; round < 3; round++) {
      await page.evaluate(() => document.querySelector('[data-id="w1"] .digi-crs').digiCrs.go(0));
      await settle(page);
      await page.click('[data-id="w1"] .digi-crs > .digi-crs__next');
      await page.evaluate(r => { for (const e of document.querySelectorAll('[data-id="w1"] .s')) e.style.height = 100 + 30 * (r + 1) + 'px'; }, round);
      await sleep(60);
      await page.click('[data-id="w1"] .digi-crs > .digi-crs__next');
      await settle(page);
      await sleep(250);
      s = await state(page, 'w1');
      ok(s.current === 3 && s.dots === '--X___' && Math.abs(s.scroll - s.off[3]) <= 1, `${width}: dos clics seguidos con el diseño cambiando: la pista llega a la última página (vuelta ${round}): ${JSON.stringify(s)}`);
    }
    await page.evaluate(() => document.querySelector('[data-id="w1"] .digi-crs').digiCrs.go(2));
    await settle(page);

    // 3. Fundido con fracción y autoplay de 1 s, bucle y pausa con el puntero.
    s = w2start;
    ok(s.now === '1' && s.total === '4' && s.live === 'off' && s.pause && !s.pause.hidden && s.pause.label === 'Pausar' && s.opacity[0] === '1' && s.opacity[1] === '0', `${width}: fundido en marcha: ${JSON.stringify(s)}`);
    // El botón Pausar/Reanudar no se ve ni lo alcanza el puntero (PRO no lo tenía); con el teclado aparece, con su fondo
    // aunque el tema pinte [type=button]:focus, y funciona.
    const pz = () => page.$eval('[data-id="w2"] .digi-crs__pause', b => { const r = b.getBoundingClientRect(), c = getComputedStyle(b); return { clip: c.clipPath, hit: b.contains(document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)), fv: b.matches(':focus-visible'), w: Math.round(r.width), h: Math.round(r.height), bg: c.backgroundColor, label: b.getAttribute('aria-label') }; });
    await page.$eval('[data-id="w2"]', e => e.scrollIntoView({ block: 'center' }));
    let pzs = await pz();
    ok(pzs.clip === 'inset(50%)' && !pzs.hit, `${width}: botón de pausa oculto a la vista y fuera del puntero: ${JSON.stringify(pzs)}`);
    await page.$eval('[data-id="w2"]', e => { e.tabIndex = -1; e.focus(); });
    await page.keyboard.press('Tab');
    pzs = await pz();
    ok(pzs.fv && pzs.clip === 'none' && pzs.hit && pzs.w === 28 && pzs.h === 28 && pzs.bg === 'rgba(0, 0, 0, 0.6)', `${width}: con el teclado, la pausa se ve (28 × 28, su fondo y no el del tema): ${JSON.stringify(pzs)}`);
    await page.keyboard.press('Enter');
    ok((await pz()).label === 'Reanudar', `${width}: Intro en la pausa → Reanudar.`);
    await page.keyboard.press('Enter');
    ok((await pz()).label === 'Pausar', `${width}: Intro otra vez → Pausar.`);
    await page.$eval('[data-id="w2"]', e => { e.blur(); e.removeAttribute('tabindex'); });
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
    await page.mouse.move(1, 899);
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
    await page.$eval('[data-id="w2"] .digi-crs__pause', b => b.click());
    s = await state(page, 'w2');
    ok(s.paused && s.pause.label === 'Reanudar' && s.pause.d === 'M8 5v14l11-7z' && s.live === 'polite', `${width}: Pausar → Reanudar, con su icono: ${JSON.stringify(s.pause)}`);
    const held = s.now;
    await sleep(1300);
    ok((await state(page, 'w2')).now === held, `${width}: pausado no avanza.`);
    await page.$eval('[data-id="w2"] .digi-crs__pause', b => b.click());
    await page.focus('[data-id="w2"] .digi-crs__next');
    s = await state(page, 'w2');
    ok(!s.paused && s.live === 'polite', `${width}: reanudado, pero con el foco dentro no avanza.`);
    await page.mouse.move(1, 899);
    await page.evaluate(() => document.activeElement.blur());
    await page.waitForFunction(() => document.querySelector('[data-id="w2"] .digi-crs__track').getAttribute('aria-live') === 'off');
    ok(true, `${width}: sin foco ni puntero, vuelve a reproducir (región viva off).`);
    // Fundido en bucle (sin copias, como el fadeEffect de Swiper): desde la última, siguiente → la primera.
    await page.evaluate(() => document.querySelector('[data-id="w2"] .digi-crs').digiCrs.go(3));
    await click(page, 'w2', '.digi-crs__next');
    s = await state(page, 'w2');
    ok(s.now === '1' && s.next === 'false' && s.prev === 'false' && s.clones === 0, `${width}: fundido con bucle: de la última a la primera, sin copias, y ninguna flecha se desactiva.`);
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
    const rtl0 = s.scroll;
    ok(s.fill === '20%' && s.inert === 'X----' && s.seen === '1' && s.clones === 4, `${width}: barra de progreso al 20 % en la primera de cinco (bucle con dos copias a cada lado): ${JSON.stringify(s)}`);
    await page.focus('[data-id="w3"] .digi-crs__next');
    await page.keyboard.press('ArrowLeft');
    await settle(page);
    s = await state(page, 'w3');
    ok(s.cur === 1 && s.fill === '40%' && s.seen === '2' && s.scroll < rtl0, `${width}: en RTL la flecha izquierda avanza y la pista se desplaza hacia la izquierda: ${JSON.stringify(s)}`);
    await page.evaluate(() => document.querySelector('[data-id="w3"] .digi-crs').digiCrs.go(4));
    await settle(page);
    const rtl4 = (await state(page, 'w3')).scroll;
    await page.keyboard.press('ArrowLeft');
    await settle(page);
    s = await state(page, 'w3');
    ok(s.cur === 0 && s.fill === '20%' && s.seen === '1' && s.scroll < rtl4, `${width}: en RTL, de la última a la primera sigue hacia la izquierda (bucle real): ${JSON.stringify(s)}`);
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
    const lockedCtl = await page.evaluate(() => [...document.querySelectorAll('[data-id="w8"] .digi-crs > :not(.digi-crs__track)')].map(e => `${e.className.replace('digi-crs__arrow ', '')}:${getComputedStyle(e).display}:${getComputedStyle(e).opacity}:${e.getAttribute('aria-disabled')}`).join(' '));
    ok(s.locked && s.inert === 'XXX' && lockedCtl === 'digi-crs__prev:grid:0.3:true digi-crs__pagination:none:1:null digi-crs__next:grid:0.3:true', `${width}: sin bucle y todo a la vista: flechas atenuadas y deshabilitadas, sin paginación (como PRO con la hoja del núcleo): ${lockedCtl}`);
    const fill = await page.evaluate(() => { const t = document.querySelector('[data-id="w8"] .digi-crs__track'); return [t.clientWidth, [...t.children].reduce((a, c) => a + c.getBoundingClientRect().width, 0)]; });
    ok(Math.abs(fill[0] - fill[1]) <= 1, `${width}: las visibles se acotan al número de diapositivas (--digi-crs-n, como el Math.min de PRO): ${fill}`);

    // Bucle con 2 visibles de 4: como Swiper, puntos y fracción cuentan diapositivas (4), no páginas; la última se ve con
    // la copia de la primera y la siguiente sigue hacia delante.
    s = await state(page, 'w12');
    ok(s.dots === 'X---' && s.inert === 'XX--' && s.seen === '1,2' && s.clones === 6, `${width}: bucle: un punto por diapositiva y tres copias a cada lado: ${JSON.stringify(s)}`);
    let f = await state(page, 'w13');
    ok(f.total === '4' && f.now === '1', `${width}: bucle: fracción «1 / 4»: ${f.now} / ${f.total}`);
    const fr = await page.evaluate(() => { const r = document.querySelector('[data-id="w13"] .digi-crs'), e = r.querySelector('.digi-crs__fraction'), b = e.getBoundingClientRect(), R = r.getBoundingClientRect(); return { align: getComputedStyle(e).textAlign, center: b.left + b.width / 2 - (R.left + R.width / 2) }; });
    ok(fr.align === 'center' && Math.abs(fr.center) < 1, `${width}: la fracción, centrada como en PRO: ${JSON.stringify(fr)}`);
    for (let k = 0; k < 3; k++) await click(page, 'w13', '.digi-crs__next');
    await settle(page);
    f = await state(page, 'w13');
    const w13at = f.scroll;
    ok(f.now === '4' && f.cur === 3 && f.seen === '4,1' && f.vis === '4,1', `${width}: bucle: «4 / 4» muestra la última con la copia de la primera, como Swiper: ${JSON.stringify(f)}`);
    await click(page, 'w13', '.digi-crs__next');
    await settle(page);
    f = await state(page, 'w13');
    ok(f.now === '1' && f.cur === 0 && f.seen === '1,2' && f.scroll > w13at, `${width}: bucle: desde la última, siguiente sigue hacia delante (no rebobina): ${JSON.stringify(f)}`);
    await click(page, 'w13', '.digi-crs__next');
    await settle(page);
    f = await state(page, 'w13');
    ok(f.now === '2' && f.seen === '2,3' && f.current === 1, `${width}: bucle: antes del paso siguiente vuelve a la original sin que se note: ${JSON.stringify(f)}`);
    await page.evaluate(() => document.querySelector('[data-id="w13"] .digi-crs').digiCrs.go(0));
    await settle(page);
    const w13first = (await state(page, 'w13')).scroll;
    await click(page, 'w13', '.digi-crs__prev');
    await settle(page);
    f = await state(page, 'w13');
    ok(f.now === '4' && f.seen === '4,1' && f.scroll < w13first, `${width}: bucle: anterior desde la primera va hacia atrás hasta la última: ${JSON.stringify(f)}`);
    await page.click('[data-id="w12"] .digi-crs__dot:nth-child(4)');
    await settle(page);
    s = await state(page, 'w12');
    ok(s.dots === '---X' && s.cur === 3 && s.seen === '4,1' && JSON.stringify(s.api) === '[3,4]', `${width}: bucle: el cuarto punto lleva a la cuarta: ${JSON.stringify(s)}`);
    const flex = await page.evaluate(() => { const t = document.querySelector('[data-id="w12"] .digi-crs__track'), c = t.children[0]; c.classList.add('e-con', 'e-flex'); const st = document.createElement('style'); st.textContent = '.e-con.e-flex{--flex-basis:auto;--flex-grow:0;--flex-shrink:1;flex:var(--flex-grow) var(--flex-shrink) var(--flex-basis)}'; document.head.append(st); const w = [c.getBoundingClientRect().width, t.children[1].getBoundingClientRect().width]; st.remove(); c.classList.remove('e-con', 'e-flex'); return w; });
    ok(Math.abs(flex[0] - flex[1]) < 0.5, `${width}: el ancho de la diapositiva gana a la regla .e-con.e-flex del núcleo: ${flex}`);

    // 7. Autoplay con deslizamiento y los valores de PRO (pausa con el puntero, detener tras una interacción): arranca,
    // avanza sin detenerse solo, también al cambiar el ancho, y la pista acompaña al estado (A/B de S).
    await page.mouse.move(1, 1);
    await page.$eval('[data-id="w11"]', e => e.scrollIntoView({ block: 'center' }));
    s = await state(page, 'w11');
    ok(!s.paused && s.pause.label === 'Pausar', `${width}: autoplay en marcha al cargar: ${JSON.stringify(s.pause)}`);
    const visited = new Set([s.cur]);
    for (let round = 0; round < 4; round++) {
      if (round === 2) {
        await page.setViewport({ width: width === 1280 ? 900 : 420, height: 900 });
        await page.$eval('[data-id="w11"]', e => e.scrollIntoView({ block: 'center' }));
      }
      await sleep(1050);
      s = await state(page, 'w11');
      visited.add(s.cur);
      ok(!s.paused && s.pause.label === 'Pausar', `${width}: el autoplay no se detiene solo (vuelta ${round}): ${JSON.stringify(s)}`);
    }
    await page.waitForFunction(() => { const t = document.querySelector('[data-id="w11"] .digi-crs__track'), c = t.querySelector('.digi-crs__current'); return Math.abs(t.scrollLeft - (c.offsetLeft - t.children[0].offsetLeft)) <= 1; }, { timeout: 2000 });
    s = await state(page, 'w11');
    ok(visited.size >= 3 && s.dots.indexOf('X') === s.cur, `${width}: avanza y la pista acompaña al estado: ${[...visited]} ${JSON.stringify(s)}`);
    await page.mouse.move(1, 1);

    // 9. Bucle con todo a la vista (3 de 3, como el Carrusel anidado por defecto): sin --locked, flechas y puntos a la vista
    // y funcionando (Swiper no bloquea en bucle). Las reproducciones automáticas cercanas, en pausa mientras tanto (las
    // pistas en movimiento no dejarían «asentarse» a las demás).
    for (const id of ['w11', 'w17']) await page.$eval(`[data-id="${id}"] .digi-crs__pause`, b => b.click());
    s = await state(page, 'w15');
    const shown = await page.evaluate(() => [...document.querySelectorAll('[data-id="w15"] .digi-crs > :not(.digi-crs__track)')].every(e => getComputedStyle(e).display !== 'none' && getComputedStyle(e).opacity === '1'));
    ok(!s.locked && shown && s.dots === 'X--' && s.seen === '1,2,3' && s.prev === 'false' && s.next === 'false', `${width}: bucle con todo a la vista: sin bloqueo, flechas y tres puntos: ${JSON.stringify(s)}`);
    await click(page, 'w15', '.digi-crs__next');
    await settle(page);
    s = await state(page, 'w15');
    ok(s.seen === '2,3,1' && s.cur === 1 && s.dots === '-X-' && s.vis === '2,3,1', `${width}: siguiente → 2, 3 y la copia de 1: ${JSON.stringify(s)}`);
    await page.evaluate(() => document.querySelector('[data-id="w15"] .digi-crs').digiCrs.go(0));
    await settle(page);
    await click(page, 'w15', '.digi-crs__prev');
    await settle(page);
    s = await state(page, 'w15');
    ok(s.seen === '3,1,2' && s.cur === 2 && s.dots === '--X', `${width}: anterior desde la primera → 3, 1, 2: ${JSON.stringify(s)}`);
    // Miniaturas centradas en bucle (7, 4 visibles): la última asoma a la izquierda de la primera, sin hueco; en la última,
    // la primera a la derecha.
    const edge = await page.evaluate(() => {
      const t = document.querySelector('[data-id="w16"] .digi-crs__track'), c = t.querySelector('.digi-crs__current'), l = c.previousElementSibling, T = t.getBoundingClientRect(), a = c.getBoundingClientRect(), b = l.getBoundingClientRect();
      return { left: parseInt(l.getAttribute('aria-label'), 10), gap: Math.round(a.left - b.right), inside: b.right > T.left, centered: Math.abs(a.left + a.width / 2 - (T.left + T.width / 2)) };
    });
    ok(edge.left === 7 && edge.gap === 10 && edge.inside && edge.centered <= 1.5, `${width}: miniaturas centradas en bucle: la 7 a la izquierda de la 1, sin hueco: ${JSON.stringify(edge)}`);
    await page.evaluate(() => document.querySelector('[data-id="w16"] .digi-crs').digiCrs.go(6));
    await settle(page);
    const edge2 = await page.evaluate(() => { const c = document.querySelector('[data-id="w16"] .digi-crs__current'), r = c.nextElementSibling; return [parseInt(c.getAttribute('aria-label'), 10), parseInt(r.getAttribute('aria-label'), 10), Math.round(r.getBoundingClientRect().left - c.getBoundingClientRect().right)]; });
    ok(edge2.join() === '7,1,10', `${width}: en la última, la 1 a la derecha: ${edge2}`);
    // Copias repetidas (4 diapositivas, 3 visibles, paso 2): siguiente → 3, 4, 1; otra vez → 1, 2, 3 (como Swiper).
    s = await state(page, 'w18');
    ok(s.clones === 10 && s.dots === 'X-__' && s.seen === '1,2,3', `${width}: cinco copias a cada lado (visibles + paso), repetidas: ${JSON.stringify(s)}`);
    await click(page, 'w18', '.digi-crs__next');
    await settle(page);
    s = await state(page, 'w18');
    ok(s.seen === '3,4,1' && s.dots === '-X__', `${width}: paso de 2 en bucle → 3, 4, 1: ${JSON.stringify(s)}`);
    await click(page, 'w18', '.digi-crs__next');
    await settle(page);
    s = await state(page, 'w18');
    ok(s.seen === '1,2,3' && s.dots === 'X-__', `${width}: y otra vez → 1, 2, 3: ${JSON.stringify(s)}`);
    const cloneInfo = await page.evaluate(() => [...document.querySelectorAll('[data-id="w18"] .digi-crs__clone')].every(c => c.classList.contains('slick-cloned') && !c.hasAttribute('aria-hidden')));
    ok(cloneInfo, `${width}: las copias llevan slick-cloned (el lightbox del núcleo no las repite) y su inert sigue a la ventana, sin aria-hidden.`);
    // Reproducción automática en bucle (5, 3 visibles): cada intervalo avanza una, también de la última a la primera, y la
    // pista se desplaza siempre hacia delante.
    await page.$eval('[data-id="w17"]', e => e.scrollIntoView({ block: 'center' }));
    await page.$eval('[data-id="w17"] .digi-crs__pause', b => b.click());
    const trail = [];
    let back = 0, last = (await state(page, 'w17')).scroll;
    for (let k = 0; k < 60 && trail.length < 7; k++) {
      await sleep(100);
      const w17 = await state(page, 'w17');
      if (w17.scroll < last - 1) back++;
      last = w17.scroll;
      if (trail[trail.length - 1] !== w17.cur) trail.push(w17.cur);
      if (w17.paused) break;
    }
    ok(trail.join() === '0,1,2,3,4,0,1' && back <= 1, `${width}: la reproducción automática en bucle avanza sin rebobinar (${trail}; retrocesos de la pista: ${back}, la vuelta a la original entre pasos).`);
    await page.$eval('[data-id="w17"] .digi-crs__pause', b => b.click());
    // Carrusel anidado (w20): puntos pegados al borde inferior de la raíz, con la paginación de la altura del punto; arriba
    // con su posición personalizada.
    const nd = () => page.evaluate(() => { const r = document.querySelector('[data-id="w20"] .digi-crs'), R = r.getBoundingClientRect(), p = r.querySelector('.digi-crs__pagination').getBoundingClientRect(), d = r.querySelector('.digi-crs__dot'), b = d.getBoundingClientRect(), y = b.top + b.height / 2; return { ph: p.height, h: b.height, bottom: R.bottom - b.bottom, top: b.top - R.top, hit: [-10, 10].every(dy => document.elementFromPoint(b.left + b.width / 2, y + dy) === d), w: b.width }; });
    await page.$eval('[data-id="w20"]', e => e.scrollIntoView({ block: 'center' }));
    let n1 = await nd();
    ok(Math.abs(n1.ph - 6) < 0.5 && Math.abs(n1.h - 6) < 0.5 && Math.abs(n1.bottom) < 0.5 && n1.hit && Math.abs(n1.w - 18) < 0.5, `${width}: carrusel anidado: el punto mide 6 px y queda pegado al borde inferior, con 24 px de área táctil: ${JSON.stringify(n1)}`);
    await page.$eval('[data-id="w20"]', e => e.style.cssText += ';--digi-crs-pag-y:0%;--digi-crs-pag-t:-50% 0%');
    n1 = await nd();
    ok(Math.abs(n1.top) < 0.5, `${width}: carrusel anidado con los puntos arriba: el punto toca el borde superior: ${JSON.stringify(n1)}`);
    // Carrusel anidado con 2 de 3 visibles (w21): como el CarouselBase del núcleo, cada diapositiva mide un tercio, y en
    // bucle el tercer hueco es la copia de la primera.
    s = await state(page, 'w21');
    const thirds = await page.evaluate(() => { const t = document.querySelector('[data-id="w21"] .digi-crs__track'); return [t.clientWidth, t.children[0].getBoundingClientRect().width]; });
    ok(Math.abs((thirds[0] - 20) / 3 - thirds[1]) < 1 && s.seen === '1,2,1' && !s.locked, `${width}: anidado con menos diapositivas que visibles: un tercio cada una y la copia en el tercer hueco: ${thirds} ${JSON.stringify(s)}`);
    // Carruseles dentro de las copias del bucle (w22): todos iniciados, también tras rehacer las copias al cambiar de
    // punto de corte (3 → 1 visibles), y los de una copia a la vista responden a sus flechas.
    const inner = async () => page.evaluate(() => {
      const outer = document.querySelector('[data-id="w22"] > .digi-crs'), all = [...outer.querySelectorAll('.digi-crs')];
      return { n: all.length, dead: all.filter(r => !r.digiCrs || !r.classList.contains('digi-crs--ready')).length, inClones: all.filter(r => r.closest('.digi-crs__clone')).length };
    });
    let w22 = await inner();
    const lc0 = width === 1280 ? 8 : 4;
    ok(w22.dead === 0 && w22.inClones === lc0 && w22.n === 3 + lc0, `${width}: carruseles interiores vivos, también en las copias del exterior: ${JSON.stringify(w22)}`);
    await page.setViewport({ width: width === 1280 ? 600 : 1280, height: 900 });
    await settle(page);
    w22 = await inner();
    const lc = width === 1280 ? 4 : 8;
    ok(w22.dead === 0 && w22.inClones === lc, `${width}: tras rehacer las copias al cambiar de ancho, ningún carrusel interior queda muerto: ${JSON.stringify(w22)}`);
    await page.$eval('[data-id="w22"]', e => e.scrollIntoView({ block: 'center' }));
    await page.evaluate(() => document.querySelector('[data-id="w22"] > .digi-crs').digiCrs.go(2));
    await settle(page);
    const live = await page.evaluate(() => {
      const t = document.querySelector('[data-id="w22"] > .digi-crs > .digi-crs__track'), T = t.getBoundingClientRect();
      const copy = [...t.children].find(k => k.classList.contains('digi-crs__clone') && !k.inert && k.getBoundingClientRect().left < T.right - 5);
      if (!copy) return 'sin copia a la vista';
      const r = copy.querySelector('.digi-crs'), before = r.digiCrs.index();
      r.querySelector(':scope > .digi-crs__next').click();
      return `${before}→${r.digiCrs.index()}`;
    });
    ok(live === '0→1', `${width}: el carrusel de una copia a la vista responde a su flecha: ${live}`);
    await page.setViewport({ width, height: 900 });
    await settle(page);
    // Carrusel anidado con un solo elemento (w24): a todo el ancho, como PRO sin Swiper, aunque se vean 3.
    const one = await page.$eval('[data-id="w24"] .digi-crs__track', t => [t.clientWidth, t.children[0].getBoundingClientRect().width, t.children.length]);
    ok(Math.abs(one[0] - one[1]) < 0.5 && one[2] === 1, `${width}: carrusel anidado con un solo elemento, a todo el ancho: ${one}`);
    await page.close();
  }

  // 10. Arrastre con el ratón y clic en una diapositiva (w19: grabCursor y slideToClickedSlide de PRO) y en bucle (w12).
  {
    const page = await open();
    for (const id of ['w11', 'w17']) await page.$eval(`[data-id="${id}"] .digi-crs__pause`, b => b.click());
    await page.$eval('[data-id="w19"]', e => e.scrollIntoView({ block: 'center' }));
    const tb = await page.$eval('[data-id="w19"] .digi-crs__track', e => { const r = e.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width }; });
    const cursor = () => page.$eval('[data-id="w19"] .digi-crs', e => getComputedStyle(e).cursor);
    ok(await cursor() === 'grab', 'Cursor de agarre sobre el carrusel.');
    // Arrastre lento de 270 px (más de medio paso de 2 diapositivas no: 417 px por diapositiva): va a la más cercana.
    const dragBy = async (dx, ms, y = 40) => { await page.mouse.move(tb.x + tb.w * 0.6, tb.y + y); await page.mouse.down(); const k = 10; for (let i = 1; i <= k; i++) { await page.mouse.move(tb.x + tb.w * 0.6 + (dx * i) / k, tb.y + y); await sleep(ms / k); } return true; };
    await dragBy(-270, 600);
    const during = await page.evaluate(() => ({ c: getComputedStyle(document.querySelector('[data-id="w19"] .digi-crs')).cursor, sel: String(getSelection()) }));
    await page.mouse.up();
    await settle(page);
    let s = await state(page, 'w19');
    ok(during.c === 'grabbing' && during.sel === '' && s.cur === 1 && s.seen === '2,3,4' && (await page.evaluate(() => location.hash)) === '', `Arrastre lento: la pista sigue al puntero (cursor de agarre cerrado, sin seleccionar texto), va a la diapositiva más cercana y el clic de después no sigue el enlace: ${JSON.stringify([during, s])}`);
    // Gesto rápido y corto (40 px en 100 ms): la siguiente página.
    await dragBy(-40, 80);
    await page.mouse.up();
    await settle(page);
    s = await state(page, 'w19');
    ok(s.cur === 2 && s.seen === '3,4,5', `Gesto rápido: la siguiente: ${JSON.stringify(s)}`);
    // Arrastre corto y lento (60 px en 600 ms): vuelve a su sitio.
    await dragBy(60, 600);
    await page.mouse.up();
    await settle(page);
    s = await state(page, 'w19');
    ok(s.cur === 2 && s.seen === '3,4,5', `Arrastre corto y lento: vuelve: ${JSON.stringify(s)}`);
    // Clic (sin arrastre) en el enlace de la tercera visible: la lleva a la vista y el enlace funciona.
    const link = await page.$eval('[data-id="w19"] .digi-crs__track', t => { const a = [...t.children].filter(k => !k.inert)[2].querySelector('a').getBoundingClientRect(); return { x: a.left + 5, y: a.top + 5 }; });
    await page.mouse.click(link.x, link.y);
    await settle(page);
    s = await state(page, 'w19');
    ok(s.cur === 3 && s.seen === '4,5,6' && (await page.evaluate(() => location.hash)) === '#e4', `Clic en una diapositiva: se pone la primera (slideToClickedSlide) y el enlace funciona: ${JSON.stringify(s)}`);
    // Bucle (w12, 2 de 4): arrastrar hacia atrás desde la primera trae la última (copia) y deja la cuarta como actual.
    await page.evaluate(() => document.querySelector('[data-id="w12"] .digi-crs').digiCrs.go(0));
    await page.$eval('[data-id="w12"]', e => e.scrollIntoView({ block: 'center' }));
    const t12 = await page.$eval('[data-id="w12"] .digi-crs__track', e => { const r = e.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width }; });
    await page.mouse.move(t12.x + t12.w * 0.3, t12.y + 40);
    await page.mouse.down();
    for (let i = 1; i <= 10; i++) { await page.mouse.move(t12.x + t12.w * 0.3 + i * 40, t12.y + 40); await sleep(60); }
    await page.mouse.up();
    await settle(page);
    s = await state(page, 'w12');
    ok(s.cur === 3 && s.seen === '4,1' && s.dots === '---X', `Bucle: arrastrar hacia atrás desde la primera trae la última: ${JSON.stringify(s)}`);
    ok(await page.$eval('[data-id="w1"] .digi-crs', e => getComputedStyle(e).cursor) === 'auto', 'Sin data-digi-grab (carrusel anidado y de bucle de PRO), sin cursor de agarre.');
    // Carrusel anidado (w20, data-digi-long: shortSwipes desactivado de PRO sin pantalla táctil): un gesto rápido vuelve a
    // la actual aunque pase de la mitad; uno lento, a la más cercana.
    await page.$eval('[data-id="w20"]', e => e.scrollIntoView({ block: 'center' }));
    const t20 = await page.$eval('[data-id="w20"] .digi-crs__track', e => { const r = e.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width }; });
    const drag20 = async (dx, ms) => { await page.mouse.move(t20.x + t20.w * 0.6, t20.y + 40); await page.mouse.down(); for (let i = 1; i <= 8; i++) { await page.mouse.move(t20.x + t20.w * 0.6 + (dx * i) / 8, t20.y + 40); await sleep(ms / 8); } await page.mouse.up(); await settle(page); return state(page, 'w20'); };
    s = await drag20(-300, 80);
    ok(s.cur === 0 && s.seen === '1,2,3', `Anidado: un gesto rápido de 300 px vuelve a la actual (como PRO en escritorio): ${JSON.stringify(s)}`);
    s = await drag20(-300, 600);
    ok(s.cur === 1 && s.seen === '2,3,4', `Anidado: el mismo arrastre, lento, pasa a la siguiente: ${JSON.stringify(s)}`);
    await page.close();
  }

  // 11. Front con el núcleo: en el primer cambio, los manejadores del núcleo corren sobre los elementos de las copias (como
  // PRO en el carrusel anidado y el de bucle).
  {
    const page = await open({ frontend: true });
    const before = await page.evaluate(() => window.__ready.length);
    await page.$eval('[data-id="w21"] .digi-crs__next', b => b.click());
    await sleep(100);
    const after = await page.evaluate(() => window.__ready.map(e => !!e.closest('.digi-crs__clone')));
    ok(before === 0 && after.length === 16 && after.every(Boolean), `Manejadores del núcleo sobre las copias en el primer cambio (${before} → ${after.length}).`);
    await page.$eval('[data-id="w21"] .digi-crs__next', b => b.click());
    ok((await page.evaluate(() => window.__ready.length)) === 16, 'Y sólo una vez.');
    await page.close();
  }

  // 12. Bucle con paso 2 y número impar de diapositivas (w25: 5, 2 visibles; arrastre y clic): avanza por páginas como
  // Swiper (1-2, 3-4, 5-1 y otra vez 1-2, siempre hacia delante), y la diapositiva actual, el punto y lo que se ve
  // coinciden después de las flechas, de un arrastre, de un clic en una diapositiva y de la API (las anclas del imán son
  // las de las páginas: una posición fuera de ellas la recolocaba el navegador y el estado quedaba desfasado).
  {
    const page = await open();
    for (const id of ['w11', 'w17']) await page.$eval(`[data-id="${id}"] .digi-crs__pause`, b => b.click());
    await page.$eval('[data-id="w25"]', e => e.scrollIntoView({ block: 'center' }));
    const look = async () => { await settle(page); const s = await state(page, 'w25'); return { cur: s.cur, seen: s.seen, dots: s.dots, scroll: s.scroll, api: s.api }; };
    const same = (s, seen, dots) => s.seen === seen && s.dots === dots && s.cur + 1 === +seen.split(',')[0] && s.api[0] === s.cur;
    let s = await look();
    ok(same(s, '1,2', 'X--__'), `Bucle con paso 2: tres páginas para cinco diapositivas: ${JSON.stringify(s)}`);
    const seq = [['3,4', '-X-__'], ['5,1', '--X__'], ['1,2', 'X--__']];
    for (const [seen, dots] of seq) {
      const at = s.scroll;
      await click(page, 'w25', '.digi-crs__next');
      s = await look();
      ok(same(s, seen, dots) && s.scroll > at, `Bucle con paso 2: siguiente → ${seen}, hacia delante y con el punto de lo que se ve: ${JSON.stringify(s)}`);
    }
    await click(page, 'w25', '.digi-crs__prev');
    s = await look();
    ok(same(s, '5,1', '--X__'), `Bucle con paso 2: anterior desde la primera página → 5, 1: ${JSON.stringify(s)}`);
    await click(page, 'w25', '.digi-crs__next');
    s = await look();
    const tb = await page.$eval('[data-id="w25"] .digi-crs__track', e => { const r = e.getBoundingClientRect(); return { x: r.left, y: r.top, w: r.width, slide: e.children[0].getBoundingClientRect().width }; });
    const drag = async dx => { const x0 = tb.x + tb.w * 0.8; await page.mouse.move(x0, tb.y + 30); await page.mouse.down(); for (let i = 1; i <= 10; i++) { await page.mouse.move(x0 + (dx * i) / 10, tb.y + 30); await sleep(50); } await page.mouse.up(); return look(); };
    s = await drag(-0.6 * tb.slide);
    ok(same(s, '1,2', 'X--__'), `Bucle con paso 2: un arrastre lento de 0,6 diapositivas vuelve a la página: ${JSON.stringify(s)}`);
    s = await drag(-1.2 * tb.slide);
    ok(same(s, '3,4', '-X-__'), `Bucle con paso 2: uno de 1,2 diapositivas pasa a la página siguiente, no a la diapositiva siguiente: ${JSON.stringify(s)}`);
    const visible = k => page.$eval('[data-id="w25"] .digi-crs__track', (t, k) => { const T = t.getBoundingClientRect(), b = [...t.children].filter(c => { const r = c.getBoundingClientRect(); return r.left >= T.left - 1 && r.right <= T.right + 1; })[k].getBoundingClientRect(); return { x: b.left + 20, y: b.top + 20 }; }, k);
    let p = await visible(1);
    await page.mouse.click(p.x, p.y);
    s = await look();
    ok(same(s, '3,4', '-X-__'), `Bucle con paso 2: un clic en la segunda visible deja la página (slideToClickedSlide va al inicio de su grupo): ${JSON.stringify(s)}`);
    await click(page, 'w25', '.digi-crs__next');
    p = await visible(1);
    await page.mouse.click(p.x, p.y);
    s = await look();
    ok(same(s, '1,2', 'X--__'), `Bucle con paso 2: un clic en la copia de la 1 (5, 1) lleva a la página de la 1: ${JSON.stringify(s)}`);
    await page.evaluate(() => document.querySelector('[data-id="w25"] .digi-crs').digiCrs.go(3));
    s = await look();
    ok(same(s, '3,4', '-X-__'), `Bucle con paso 2: la API lleva a la página de la diapositiva pedida: ${JSON.stringify(s)}`);
    await page.close();
  }

  // 7. Movimiento reducido: sin reproducción automática; el botón ofrece Reanudar.
  {
    const page = await open({ reduce: true });
    const s = await state(page, 'w2');
    await sleep(1300);
    ok(s.paused && s.pause.label === 'Reanudar' && s.live === 'polite' && (await state(page, 'w2')).now === '1', 'Movimiento reducido: no arranca solo.');
    await page.$eval('[data-id="w2"] .digi-crs__pause', b => b.click());
    await page.mouse.move(1, 899);
    await page.waitForFunction(() => document.querySelector('[data-id="w2"] .digi-crs__now').textContent === '2', { timeout: 3000 });
    ok(true, 'Movimiento reducido: al pedirlo, reproduce.');
    await page.close();
  }

  // 7b. A/B de M (mc-images a 375 px): en una pestaña en segundo plano (el arnés abre A y B a la vez, y ahí no hay
  // fotogramas) las acciones acumuladas —siguiente, tercer punto, foco en la flecha + Intro, quitar foco y puntero— dejan
  // la pista en su página, sin quedarse a medio camino.
  {
    const page = await open({ width: 375 });
    const front = await browser.newPage();
    await front.goto('about:blank');
    const hidden = await page.evaluate(() => document.visibilityState);
    const act = async step => { await step(); await sleep(900); await page.mouse.move(0, 0); await page.evaluate(() => document.activeElement && document.activeElement !== document.body && document.activeElement.blur()); await sleep(300); return state(page, 'w14'); };
    let s = await act(() => page.$eval('[data-id="w14"] .digi-crs__next', b => b.click()));
    ok(s.current === 1 && Math.abs(s.scroll - s.off[1]) <= 1, `Segundo plano (${hidden}): siguiente → 2.ª en su sitio: ${JSON.stringify(s)}`);
    s = await act(() => page.$eval('[data-id="w14"] .digi-crs__dot:nth-of-type(3)', b => b.click()));
    ok(s.current === 2 && Math.abs(s.scroll - s.off[2]) <= 1, `Segundo plano: tercer punto → 3.ª en su sitio: ${JSON.stringify(s)}`);
    s = await act(async () => { const b = await page.$('[data-id="w14"] .digi-crs__next'); await b.focus(); await page.keyboard.press('Enter'); });
    ok(s.current === 3 && s.dots === '---X--' && s.prev === 'false' && Math.abs(s.scroll - s.off[3]) <= 1, `Segundo plano: foco en la flecha + Intro → 4.ª en su sitio, sin quedarse a medio camino: ${JSON.stringify(s)}`);
    await front.close();
    await page.close();
  }

  // 8. Editor: sin reproducción automática y reinicio con frontend/element_ready/global.
  {
    const page = await open({ editor: true });
    const hook = await page.evaluate(() => window.__hook?.[0]);
    const s = await state(page, 'w2');
    await sleep(1300);
    ok(hook === 'frontend/element_ready/global' && (await state(page, 'w2')).now === '1' && s.live === 'polite' && s.pause.hidden, 'Editor: sin reproducción automática (ni botón de pausa) y con el gancho global.');
    const nested = await state(page, 'w21'), plain = await state(page, 'w15');
    ok(nested.clones === 0 && nested.next === 'true' && plain.clones === 8, `Editor: el carrusel anidado (diapositivas que son elementos) no copia ni da la vuelta, como PRO; los demás sí: ${JSON.stringify([nested, plain.clones])}`);
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
