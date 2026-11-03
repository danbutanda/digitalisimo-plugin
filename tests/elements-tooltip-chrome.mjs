// F8, frente D: «Información emergente» en Chrome real (sin interfaz) sobre tests/fixtures/f8-tooltip.html, cuyos widgets
// pinta tests/elements-tooltip.php con la clase del rasgo (marcado clásico y optimizado, anidado, enlace del elemento y
// estilo generado).
// - Sin JavaScript: oculto y recortado (en el árbol de accesibilidad), se ve al pasar el puntero o al enfocar, sin
//   desplazamiento horizontal de la página.
// - Con JavaScript: id único generado en el cliente, aria-describedby (descripción accesible) en los operables propios,
//   sin foco vacío, se puede recorrer con el puntero (1.4.13), Escape lo descarta hasta salir, disparador «clic», volteo
//   arriba ↔ abajo y desplazamiento dentro de la ventana, colores por defecto con contraste ≥ 4,5:1, estilo generado,
//   movimiento reducido, popups que llegan después.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-tooltip-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la información emergente (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/f8-tooltip.html', import.meta.url).href;
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
  await page.setViewport({ width, height: 800 });
  await page.goto(fixture, { waitUntil: 'load' });
  if (js) await page.waitForFunction(() => document.querySelectorAll('.digi-tooltip[id]').length === 10);
  await page.mouse.move(width - 2, 790);
  return page;
}
// Estado del globo propio de un widget (el último .digi-tooltip de su envoltorio) y de su anfitrión.
const tip = (page, id) => page.evaluate(id => {
  const h = document.querySelector(`[data-id="${id}"]`), t = [...h.querySelectorAll('.digi-tooltip')].pop();
  const box = el => { const b = el.getBoundingClientRect(); return { l: b.left, r: b.right, t: b.top, b: b.bottom, w: b.width, h: b.height, cx: (b.left + b.right) / 2 }; };
  const cs = getComputedStyle(t), after = getComputedStyle(t, '::after');
  const chip = h.querySelector('.digi-test-chip') || h;
  return {
    id: t.id, cls: t.className, shown: cs.clipPath === 'none' && t.getBoundingClientRect().width > 2, clip: cs.clipPath, opacity: +cs.opacity, transition: cs.transitionDuration,
    box: box(t), host: box(chip), wrapper: box(h), color: cs.color, bg: cs.backgroundColor, maxw: cs.maxWidth, arrow: after.content !== 'none' && after.content !== '', arrowBg: after.backgroundColor,
    vw: document.documentElement.clientWidth, vh: innerHeight, sw: document.documentElement.scrollWidth,
  };
}, id);
const center = async (page, selector) => { const b = await (await page.$(selector)).boundingBox(); return [b.x + b.width / 2, b.y + b.height / 2]; };
const hover = async (page, selector) => { const [x, y] = await center(page, selector); await page.mouse.move(x, y, { steps: 2 }); await sleep(60); };
const away = async page => { await page.mouse.move(1270, 790, { steps: 2 }); await sleep(30); };
// Contraste WCAG entre dos colores rgb().
const contrast = (a, b) => {
  const lum = c => { const [r, g, bl] = c.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4; }); return 0.2126 * r + 0.7152 * g + 0.0722 * bl; };
  const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p);
  return (x + 0.05) / (y + 0.05);
};

try {
  // 1. Sin JavaScript.
  {
    const page = await open({ js: false, width: 360 });
    const all = await page.evaluate(() => [...document.querySelectorAll('.digi-tooltip')].map(t => ({ w: t.getBoundingClientRect().width, clip: getComputedStyle(t).clipPath, vis: getComputedStyle(t).visibility, id: t.id })));
    ok(all.length === 10 && all.every(t => t.w <= 40 && t.clip === 'inset(50%)' && t.vis === 'visible' && t.id === ''), `Sin JavaScript: los diez globos ocultos por recorte (no visibility:hidden), reducidos a su relleno y sin id (${all.map(t => Math.round(t.w))}).`);
    ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Sin JavaScript, a 360 px, los globos ocultos no desplazan la página en horizontal.');
    const tree = await page.accessibility.snapshot({ root: await page.$('[data-id="t1text"]'), interestingOnly: false });
    const texts = [];
    const walk = n => { if (n.name) texts.push(n.name); (n.children || []).forEach(walk); };
    walk(tree);
    ok(texts.some(t => /Sólo texto/.test(t)), `El globo oculto sigue en el árbol de accesibilidad: el lector de pantalla lo lee (${texts.join(' | ')}).`);
    await page.setViewport({ width: 1280, height: 800 });
    await hover(page, '[data-id="t2link"] .digi-test-chip');
    let s = await tip(page, 't2link');
    ok(s.shown && s.box.t >= s.host.b, 'Sin JavaScript, al pasar el puntero se ve (abajo, bajo el botón).');
    await away(page);
    await page.focus('[data-id="t2link"] a');
    s = await tip(page, 't2link');
    ok(s.shown, 'Sin JavaScript, al enfocar el enlace se ve (:focus-within).');
    await page.close();
  }

  // 2. Con JavaScript: identificadores, descripciones y foco.
  {
    const page = await open({ width: 1280, reduce: true });
    const ids = await page.evaluate(() => [...document.querySelectorAll('.digi-tooltip')].map(t => t.id));
    ok(ids.every(id => /^digi-tooltip-\d+$/.test(id)) && new Set(ids).size === 10, `Cada globo recibe en el cliente un id único (${ids.join(', ')}).`);
    const described = await page.evaluate(() => [...document.querySelectorAll('[aria-describedby]')].map(e => [e.closest('[data-id]').dataset.id, e.textContent.trim() || e.getAttribute('aria-label'), e.getAttribute('aria-describedby').trim().split(/\s+/)]));
    const of = id => described.filter(d => d[0] === id);
    const bubbleId = id => page.evaluate(id => [...document.querySelector(`[data-id="${id}"]`).querySelectorAll('.digi-tooltip')].pop().id, id);
    ok(of('t2link').length === 1 && of('t2link')[0][2].join() === await bubbleId('t2link'), 'Un solo operable: su enlace se describe con el globo.');
    ok(of('t3two').length === 2 && of('t3two').every(d => d[2].includes(ids[3])) && of('t3two')[1][2][0] === 'digi-test-previa', 'Varios operables: cada uno se describe con el globo, y una descripción previa se conserva.');
    ok(of('t1text').length === 0 && await page.evaluate(() => !document.querySelector('[data-id="t1text"]').hasAttribute('tabindex') && !document.querySelector('[data-id="t1text"] [tabindex]')), 'Sin operables, el anfitrión no se hace enfocable (sin foco vacío) ni se describe nada.');
    const inner = await bubbleId('t7inner'), outer = await bubbleId('t7nested');
    ok(of('t7inner').length === 1 && of('t7inner')[0][2].join() === inner && of('t7nested').length === 1 && of('t7nested')[0][2].join() === outer, 'Anidados: cada enlace se describe sólo con el globo de su propio widget.');
    ok(of('t9overlay').length === 1 && of('t9overlay')[0][1] === 'Abrir la tarjeta', 'El enlace que cubre el elemento también se describe.');
    const tree = await page.accessibility.snapshot({ root: await page.$('[data-id="t2link"]'), interestingOnly: false });
    const nodes = [];
    const walk = n => { nodes.push(n); (n.children || []).forEach(walk); };
    walk(tree);
    const link = nodes.find(n => n.role === 'link');
    ok(link && link.name === 'Un botón' && link.description === 'Describe el botón', `Descripción accesible del enlace: «${link && link.description}».`);
    // Tab: el orden del foco no gana paradas.
    const order = [];
    await page.evaluate(() => document.activeElement && document.activeElement.blur());
    for (let k = 0; k < 9; k++) { await page.keyboard.press('Tab'); order.push(await page.evaluate(() => document.activeElement.textContent.trim() || document.activeElement.getAttribute('aria-label'))); }
    ok(order.join('|') === 'Un botón|Uno|dos|Ayuda|i|Fuera|Dentro|Abrir la tarjeta|Con estilo', `Orden del foco: sólo los operables, sin paradas nuevas (${order.join('|')}).`);
    // Movimiento reducido.
    ok((await tip(page, 't1text')).transition === '0s', 'Con movimiento reducido, sin transición.');
    await page.close();
  }

  // 3. Puntero, Escape, recorrido del globo, clic, volteo, estilo y contraste.
  {
    const page = await open({ width: 1280 });
    await hover(page, '[data-id="t1text"] .digi-test-chip');
    await sleep(200);
    let s = await tip(page, 't1text');
    ok(s.shown && s.opacity === 1 && s.cls.includes('digi-tooltip--top'), 'Al pasar el puntero, el globo de arriba se ve (opacidad 1).');
    ok(Math.abs(s.host.t - s.box.b - 8) <= 1 && Math.abs(s.box.cx - s.wrapper.cx) <= 1, `Arriba, centrado sobre el widget y a 8 px por defecto (${(s.host.t - s.box.b).toFixed(1)} px).`);
    ok(s.arrow && s.arrowBg === s.bg, 'La flecha toma el fondo del globo.');
    ok(contrast(s.color, s.bg) >= 4.5, `Colores por defecto del sistema con contraste ≥ 4,5:1 (${contrast(s.color, s.bg).toFixed(1)}:1).`);
    // Recorrer el globo: atravesar la distancia y quedarse encima.
    await page.mouse.move(s.box.cx, s.host.t - 4, { steps: 3 });
    await sleep(30);
    ok((await tip(page, 't1text')).shown, 'Al atravesar la distancia hacia el globo, sigue visible (puente).');
    await page.mouse.move(s.box.cx, (s.box.t + s.box.b) / 2, { steps: 3 });
    await sleep(30);
    ok((await tip(page, 't1text')).shown, 'Con el puntero sobre el globo, sigue visible (1.4.13: se puede recorrer).');
    // Escape lo descarta sin mover el puntero; no vuelve hasta salir y entrar.
    await page.mouse.move(s.box.cx, s.host.t + 10, { steps: 3 });
    await sleep(30);
    ok((await tip(page, 't1text')).shown, 'De vuelta sobre el widget, sigue visible.');
    await page.keyboard.press('Escape');
    await sleep(30);
    ok(!(await tip(page, 't1text')).shown, 'Escape lo descarta sin mover el puntero (1.4.13: se puede descartar).');
    await page.mouse.move(s.box.cx + 6, s.host.t + 14, { steps: 2 });
    await sleep(60);
    ok(!(await tip(page, 't1text')).shown, 'Moviéndose dentro del widget no vuelve a aparecer.');
    await away(page);
    await hover(page, '[data-id="t1text"] .digi-test-chip');
    ok((await tip(page, 't1text')).shown, 'Al salir y volver a entrar, vuelve a mostrarse.');
    await away(page);
    // Teclado.
    await page.focus('[data-id="t2link"] a');
    await sleep(60);
    ok((await tip(page, 't2link')).shown, 'Al enfocar el enlace se ve.');
    await page.keyboard.press('Escape');
    ok(!(await tip(page, 't2link')).shown, 'Escape lo descarta con el foco dentro.');
    await page.focus('#fin');
    // Enlace que cubre el elemento.
    await hover(page, '[data-id="t9overlay"] .digi-test-chip');
    ok((await tip(page, 't9overlay')).shown, 'Con «Enlace del elemento» encima, el globo se ve al pasar el puntero.');
    await away(page);
    // Anidados: el de dentro no aparece al pasar sólo por el de fuera.
    await hover(page, '[data-id="t7nested"] a[href="#fuera"]');
    ok((await tip(page, 't7nested')).shown && !(await tip(page, 't7inner')).shown, 'Sobre el widget de fuera sólo se ve su globo, no el del widget anidado.');
    await away(page);
    // Disparador «clic».
    await hover(page, '[data-id="t4click"] button');
    ok(!(await tip(page, 't4click')).shown, 'Disparador «clic»: pasar el puntero no lo abre.');
    await page.click('[data-id="t4click"] button');
    await sleep(30);
    s = await tip(page, 't4click');
    ok(s.shown && s.cls.includes('digi-tooltip--open') && s.box.r <= s.wrapper.l - 7 && s.arrow, 'Un clic lo abre, a la izquierda del widget y con flecha.');
    await away(page);
    ok((await tip(page, 't4click')).shown, 'Abierto con un clic, sigue visible al apartar el puntero.');
    await page.click('[data-id="t4click"] button');
    ok(!(await tip(page, 't4click')).shown, 'Otro clic lo cierra.');
    await page.focus('[data-id="t4click"] button');
    await page.keyboard.press('Enter');
    ok((await tip(page, 't4click')).shown, 'Con el teclado (Intro en el botón) también se abre.');
    await page.keyboard.press('Escape');
    ok(!(await tip(page, 't4click')).shown, 'Escape lo cierra.');
    // Estilo generado.
    await page.evaluate(() => scrollTo(0, 0));
    await hover(page, '[data-id="t10styled"] .digi-test-chip');
    s = await tip(page, 't10styled');
    ok(s.bg === 'rgb(29, 58, 138)' && s.color === 'rgb(255, 255, 255)' && s.maxw === '140px' && s.box.w <= 140.5, 'Los controles de estilo (fondo, color, ancho máximo) mandan sobre los valores de la hoja.');
    ok(Math.abs(s.host.t - s.box.b - 20) <= 1 && s.arrowBg === 'rgb(29, 58, 138)', `La distancia elegida (20 px) y la flecha con el fondo elegido (${(s.host.t - s.box.b).toFixed(1)} px).`);
    await away(page);
    // Volteo: el de arriba del todo no cabe y pasa abajo, dentro de la ventana.
    await page.evaluate(() => scrollTo(0, 0));
    await hover(page, '[data-id="t5top"] .digi-test-chip');
    await sleep(60);
    s = await tip(page, 't5top');
    ok(s.shown && s.cls.includes('digi-tooltip--bottom') && !s.cls.includes('digi-tooltip--top') && s.box.t >= s.host.b && s.box.t >= 0, 'Arriba no cabe: se voltea abajo, dentro de la ventana.');
    await away(page);
    await page.evaluate(() => { document.querySelector('[data-id="t5top"]').style.marginTop = '300px'; });
    await hover(page, '[data-id="t5top"] .digi-test-chip');
    await sleep(60);
    ok((await tip(page, 't5top')).cls.includes('digi-tooltip--top'), 'Con sitio arriba, vuelve a su lado (el volteo se recalcula cada vez).');
    await away(page);
    // Popup que llega después.
    const popup = await page.evaluate(() => {
      const h = document.querySelector('[data-id="t2link"]').cloneNode(true);
      h.dataset.id = 'tpopup';
      h.querySelector('.digi-tooltip').removeAttribute('id');
      h.querySelector('a').removeAttribute('aria-describedby');
      document.body.append(h);
      dispatchEvent(new CustomEvent('elementor/popup/show', { detail: { id: 1 } }));
      return [h.querySelector('.digi-tooltip').id, h.querySelector('a').getAttribute('aria-describedby'), document.querySelectorAll('.digi-tooltip[id]').length];
    });
    ok(/^digi-tooltip-\d+$/.test(popup[0]) && popup[1].trim() === popup[0] && popup[2] === 11, 'Un popup que se abre después: su globo recibe id y su enlace, la descripción.');
    await page.close();
  }

  // 4. Teléfono: el globo largo pegado al borde se desplaza dentro de la ventana.
  {
    const page = await open({ width: 360 });
    await page.evaluate(() => document.querySelector('[data-id="t6edge"]').scrollIntoView({ block: 'center' }));
    await hover(page, '[data-id="t6edge"] a');
    await sleep(80);
    const s = await tip(page, 't6edge');
    ok(s.shown && s.box.l >= -0.5 && s.box.r <= s.vw + 0.5, `A 360 px, el globo largo junto al borde queda dentro de la ventana (${s.box.l.toFixed(1)}–${s.box.r.toFixed(1)} de ${s.vw}).`);
    ok(s.box.w <= 0.9 * 360 + 1 && s.box.b <= s.host.t, 'Su ancho no pasa de 90vw y sigue encima del icono.');
    ok(s.sw <= s.vw, 'Mostrarlo no desplaza la página en horizontal.');
    await page.close();
  }
  ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
} finally {
  await browser.close();
}
console.log(`F8, información emergente en Chrome: ${checks} comprobaciones en verde.`);
