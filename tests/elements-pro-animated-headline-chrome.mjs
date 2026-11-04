// Titular animado (`digitalisimo-animated-headline`, C2 frente A de docs/pro/c2.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-animated-headline.html, que es el marcado del servidor (lo comprueba
// tests/elements-pro-animated-headline.php; `--write-fixture` lo regenera):
// - sin JavaScript: el texto se lee (la palabra activa a la vista, las demás ocultas) y el trazo no se ve;
// - resaltado: se dibuja al entrar en la pantalla (no antes: el titular lejano espera al desplazamiento), con bucle se
//   oculta tras la duración y el 80 % de la pausa y vuelve a dibujarse; sin bucle se queda dibujado;
// - texto que rota (tiempos de PRO a partir de la duración): escritura (letras, selección y la palabra siguiente
//   escrita letra a letra), recorte (el ancho baja a 2 px y abre la palabra siguiente), giro sin bucle (se detiene en la
//   última palabra), remolino, ola y persianas (letras que entran y salen), deslizamiento hacia abajo;
// - lectores de pantalla: al partir en letras, la palabra entera queda en un texto oculto y las letras, aria-hidden;
// - movimiento reducido: el trazo aparece dibujado sin animación y el texto no rota;
// - enlace con foco visible de 2 px separado 2 px; ninguna petición fuera del archivo ni errores; reinicio en el editor
//   con frontend/element_ready/global.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-animated-headline-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del Titular animado (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-animated-headline.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
const outside = [];
async function open({ width = 1280, reduce = false, js = true, editor = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.setRequestInterception(true);
  page.on('request', r => { if (!r.url().startsWith('file:') && !r.url().startsWith('data:')) { outside.push(r.url()); r.abort().catch(() => {}); } else r.continue().catch(() => {}); });
  await page.setJavaScriptEnabled(js);
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 900 });
  if (editor) await page.evaluateOnNewDocument(() => { window.elementorFrontend = { hooks: { addAction: (n, f) => { window.__hook = [n, f]; } } }; });
  await page.goto(fixture, { waitUntil: 'load' });
  return page;
}
// Estado de un titular: clases, palabra activa, palabras visibles y letras a la vista.
const state = (page, id) => page.evaluate(id => {
  const h = document.querySelector(`[data-id="${id}"] .digi-ah`), dyn = h.querySelector('.digi-ah__dyn');
  const words = [...dyn.querySelectorAll('.digi-ah__word')];
  const shown = w => { const cs = getComputedStyle(w); return cs.visibility !== 'hidden' && +cs.opacity > 0; };
  const path = h.querySelector('svg path');
  return {
    cls: h.className,
    sel: dyn.classList.contains('digi-ah--sel'),
    on: words.findIndex(w => w.classList.contains('digi-ah__word--on')),
    onAll: words.map(w => (w.classList.contains('digi-ah__word--on') ? 'X' : w.classList.contains('digi-ah__word--off') ? '-' : '.')).join(''),
    visible: words.filter(shown).map(w => w.textContent),
    letters: words.map(w => w.querySelectorAll('.digi-ah__letter--in').length),
    sr: words.map(w => w.querySelector('.elementor-screen-only')?.textContent ?? null),
    hidden: words.map(w => w.querySelector('[aria-hidden="true"]') ? 1 : 0),
    width: dyn.style.width,
    pathOpacity: path ? getComputedStyle(path).opacity : null,
    pathAnim: path ? getComputedStyle(path).animationName : null,
    pathDur: path ? getComputedStyle(path).animationDuration : null,
  };
}, id);

try {
  // 1. Sin JavaScript: el texto se lee y el trazo no se ve.
  {
    const page = await open({ js: false });
    await sleep(1300); // la palabra activa entra con su animación al cargar (como en PRO)
    for (const id of ['h3', 'h5', 'h6', 'h9']) {
      const s = await state(page, id);
      ok(s.visible.length === 1 && s.on === 0, `${id} sin JavaScript: sólo la primera palabra a la vista (${JSON.stringify(s.visible)}).`);
    }
    const h1 = await state(page, 'h1');
    ok(h1.pathOpacity === '0' && !/digi-ah--drawn/.test(h1.cls), 'Sin JavaScript el trazo del resaltado no se ve.');
    await page.close();
  }

  // 2. Resaltado, al entrar en la pantalla.
  {
    const page = await open();
    await sleep(150);
    let h1 = await state(page, 'h1');
    ok(/digi-ah--ready/.test(h1.cls) && /digi-ah--drawn/.test(h1.cls) && h1.pathAnim === 'digi-ah-dash' && h1.pathDur === '0.6s', `El resaltado a la vista se dibuja con su duración (${h1.pathAnim} ${h1.pathDur}).`);
    const far = await state(page, 'hz');
    ok(!/digi-ah--ready|digi-ah--drawn/.test(far.cls), 'El titular fuera de la pantalla no arranca todavía.');
    await sleep(900);
    h1 = await state(page, 'h1');
    ok(h1.pathOpacity === '1', 'Al terminar el trazo queda a la vista.');
    await sleep(500); // 600 + 0,8 × 1000 = 1 400 ms
    h1 = await state(page, 'h1');
    ok(/digi-ah--hide/.test(h1.cls) && h1.pathAnim === 'digi-ah-hide', 'Con bucle, el trazo se borra tras la duración y el 80 % de la pausa.');
    await sleep(500); // 600 + 1000 = 1 600 ms
    h1 = await state(page, 'h1');
    ok(!/digi-ah--hide/.test(h1.cls) && h1.pathAnim === 'digi-ah-dash', 'Y vuelve a dibujarse al terminar la pausa.');
    const h2 = await state(page, 'h2');
    ok(/digi-ah--drawn/.test(h2.cls) && !/digi-ah--hide/.test(h2.cls) && h2.pathOpacity === '1', 'Sin bucle el trazo se queda dibujado.');
    ok(await page.$eval('[data-id="h2"] svg', s => s.getAttribute('aria-hidden') === 'true' && s.querySelectorAll('path').length === 2), 'El trazo es decorativo y la forma X tiene dos trazos.');
    await page.evaluate(() => document.querySelector('[data-id="hz"]').scrollIntoView({ block: 'center', behavior: 'instant' }));
    await sleep(200);
    ok(/digi-ah--drawn/.test((await state(page, 'hz')).cls), 'El titular lejano arranca al desplazarse hasta él.');
    // Foco visible del enlace.
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' }));
    await page.keyboard.press('Tab');
    const focus = await page.evaluate(() => { const a = document.activeElement; const cs = getComputedStyle(a); return [a.className, cs.outlineStyle, cs.outlineWidth, cs.outlineOffset]; });
    ok(focus[0] === 'digi-ah-link' && focus[1] === 'solid' && focus[2] === '2px' && focus[3] === '2px', `El enlace del titular tiene foco visible de 2 px separado 2 px (${focus}).`);
    await page.close();
  }

  // 3. Texto que rota (duración 400 ms: escritura 24 ms por letra, selección 80 ms y 880 ms hasta escribir la siguiente).
  {
    const page = await open();
    await sleep(100);
    let h3 = await state(page, 'h3');
    ok(h3.sr.join() === 'uno,dos,tres' && h3.hidden.every(Boolean) && h3.letters.join() === '3,0,0', `Escritura: letras aria-hidden, palabra entera para lectores de pantalla y la primera escrita (${h3.letters}).`);
    ok(h3.visible.length === 1, 'Sólo la palabra activa a la vista.');
    // Muestras cada 25 ms durante 3,5 s: selección, la palabra siguiente escrita letra a letra y la tercera.
    const seq = new Set();
    const clip = new Set();
    let sel = false;
    for (let i = 0; i < 140; i++) { const x = await state(page, 'h3'); sel = sel || x.sel; seq.add(x.onAll + ' ' + x.letters.join('')); const c = await state(page, 'h4'); clip.add(c.onAll + ' ' + (c.width === '2px' ? 'cerrado' : 'abierto')); await sleep(25); }
    ok(sel, 'Escritura: la palabra se selecciona antes de borrarse.');
    ok(seq.has('-X. 010') && seq.has('-X. 020') && seq.has('-X. 030') && seq.has('--X 004'), `Escritura: cada palabra siguiente se escribe letra a letra (${[...seq].join(' | ')}).`);

    // Recorte: abre con 10 px de más y, tras la pausa, cierra a 2 px y abre la siguiente.
    const w0 = parseFloat((await state(page, 'h4')).width);
    ok(w0 > 10, `Recorte: el ancho inicial lleva 10 px de más (${w0}).`);

    // Giro sin bucle: se detiene en la última palabra.
    const h5 = await state(page, 'h5');
    ok(h5.on === 2 && h5.onAll === '--X' && h5.visible.join() === 'tres', `Giro sin bucle: termina en la última palabra (${h5.onAll}).`);
    ok(/^\d+(\.\d+)?px$/.test((await state(page, 'h9')).width), 'Deslizamiento hacia abajo: el ancho sigue a la palabra activa.');
    ok(clip.has('X. cerrado') && clip.has('-X abierto') && clip.has('X- abierto'), `Recorte: cierra a 2 px, cambia de palabra, abre y vuelve a la primera con bucle (${[...clip].join(' | ')}).`);

    // Letras: remolino, ola y persianas alternan las letras de las dos palabras.
    for (const id of ['h6', 'h7', 'h8']) {
      const s = await state(page, id);
      ok(s.letters[0] + s.letters[1] === 4 && s.sr.join() === 'alfa,beta', `${id}: una palabra con sus letras dentro y la palabra entera para lectores (${s.letters}).`);
    }
    let seen = new Set();
    for (let i = 0; i < 20; i++) { seen.add((await state(page, 'h6')).letters.join()); await sleep(100); }
    ok(seen.has('4,0') && seen.has('0,4'), `Remolino: las letras de cada palabra entran y salen por turnos (${[...seen].join(' | ')}).`);
    await page.close();
  }

  // 4. Movimiento reducido: dibujado sin animación y sin rotar.
  {
    const page = await open({ reduce: true });
    await sleep(1600);
    const h1 = await state(page, 'h1');
    ok(/digi-ah--drawn/.test(h1.cls) && !/digi-ah--hide/.test(h1.cls) && h1.pathAnim === 'none' && h1.pathOpacity === '0', 'Con movimiento reducido el resaltado no se anima.');
    for (const id of ['h3', 'h5', 'h6', 'h9']) {
      const s = await state(page, id);
      ok(s.on === 0 && s.visible.length === 1 && !s.sel, `${id}: con movimiento reducido no rota (${s.onAll}).`);
    }
    await page.close();
  }

  // 5. Editor: se engancha a frontend/element_ready/global y arranca lo que se pinta de nuevo.
  {
    const page = await open({ editor: true });
    const hooked = await page.evaluate(() => {
      if (!window.__hook || window.__hook[0] !== 'frontend/element_ready/global') return false;
      const el = document.querySelector('[data-id="h2"]');
      el.innerHTML = el.innerHTML; // el editor vuelve a pintar el widget
      window.__hook[1]([el]);
      return true;
    });
    await sleep(200);
    ok(hooked && /digi-ah--drawn/.test((await state(page, 'h2')).cls), 'En el editor el titular pintado de nuevo vuelve a arrancar.');
    await page.close();
  }

  ok(!errors.length, 'Sin errores de JavaScript: ' + errors.join(' | '));
  ok(!outside.length, 'Ninguna petición fuera del archivo: ' + outside.join(', '));
  console.log(`DIGITALÍSIMO Elements: Titular animado en Chrome validado (${checks} comprobaciones).`);
} finally {
  await browser.close();
}
