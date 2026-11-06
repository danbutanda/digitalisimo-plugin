// «Relieve 3D» en Chrome real (sin interfaz) sobre tests/fixtures/f8-text-relief.html, cuyos encabezados pinta
// tests/elements-text-relief.php con la clase del rasgo (marcado clásico y optimizado, enlace, Encabezado avanzado y
// estilo generado).
// - Sin JavaScript: el título se ve tal cual, una vez, ya en inline-block y con su perspectiva (no se mueve al cargar).
// - Con JavaScript: capa frontal con el contenido original y copias detrás con aria-hidden, inert, sin id repetidos,
//   sin puntero ni selección; desplazamiento en Z de profundidad/2 − i·profundidad/N, opacidad (1 − i/N)/2 con el
//   desvanecido, color de la profundidad sólo en las copias, perspectiva propia, la capa frontal crece P/(P − d/2);
//   el nombre accesible y el orden de tabulación no cambian; giro con el puntero (y al revés) y al desplazar; sin giro
//   con movimiento reducido; popups y elementos de la cuadrícula de bucle que llegan después; sin errores ni
//   desplazamiento horizontal.
// Sin Chrome o sin puppeteer-core (CI), se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-text-relief-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del relieve 3D (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/f8-text-relief.html', import.meta.url).href;
let checks = 0;
// Sólo valores primitivos en las comprobaciones: un assert sobre objetos grandes agota la memoria.
const ok = (value, message) => { assert.ok(value === true, message); checks++; };
const near = (a, b, tolerance = 0.5) => Math.abs(a - b) <= tolerance;
const sleep = ms => new Promise(r => setTimeout(r, ms));
// Árbol de accesibilidad, en texto: rol, nombre y nivel de cada nodo.
const axTree = async page => {
  const out = [];
  const walk = (n, d) => { out.push(' '.repeat(d) + n.role + ' «' + (n.name || '') + '»' + (n.level ? ' ' + n.level : '')); (n.children || []).forEach(c => walk(c, d + 1)); };
  walk(await page.accessibility.snapshot({ interestingOnly: true }), 0);
  return out.join('\n');
};
let axPlain = '';

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
  if (js) await page.waitForFunction(() => document.querySelectorAll('.digi-relief').length === 7);
  return page;
}
// Estado de un encabezado: anfitrión, capas y pila.
const state = (page, id) => page.evaluate(id => {
  const w = document.querySelector(`[data-id="${id}"]`), h = w.querySelector('.elementor-heading-title,.digi-advanced-heading__main');
  const box = el => { const b = el.getBoundingClientRect(); return { l: b.left, t: b.top, w: b.width, h: b.height }; };
  const layers = [...h.querySelectorAll('.digi-relief__layer')];
  const stack = h.querySelector('.digi-relief__stack');
  const cs = getComputedStyle(h);
  return {
    hostDisplay: cs.display, hostPosition: cs.position, perspective: cs.perspective, hostText: h.textContent, hostBox: box(h),
    reliefs: h.querySelectorAll('.digi-relief').length, layers: layers.length,
    front: layers[0] ? { transform: layers[0].style.transform, hidden: layers[0].getAttribute('aria-hidden'), inert: layers[0].hasAttribute('inert'), text: layers[0].textContent, box: box(layers[0]), color: getComputedStyle(layers[0]).color, offset: [layers[0].offsetLeft, layers[0].offsetTop, layers[0].offsetWidth, layers[0].offsetHeight].join() } : null,
    copies: layers.slice(1).map(c => ({ transform: c.style.transform, opacity: c.style.opacity, hidden: c.getAttribute('aria-hidden'), inert: c.hasAttribute('inert'), copy: c.classList.contains('digi-relief__copy'), color: getComputedStyle(c).color, events: getComputedStyle(c).pointerEvents, select: getComputedStyle(c).userSelect, position: getComputedStyle(c).position, offset: [c.offsetLeft, c.offsetTop, c.offsetWidth, c.offsetHeight].join(), text: c.textContent })),
    stack: stack ? stack.style.transform : null, stackStyle: stack ? getComputedStyle(stack).transformStyle : null,
    sw: document.documentElement.scrollWidth, vw: document.documentElement.clientWidth,
  };
}, id);

// 1. Sin JavaScript: el título tal cual, una sola vez, ya preparado (no salta al cargar el script).
{
  const page = await open({ js: false });
  const plain = await state(page, 'r0plain');
  const base = await state(page, 'r1base');
  ok(plain.hostDisplay === 'block' && plain.perspective === 'none', 'Sin el rasgo, el encabezado no cambia (bloque, sin perspectiva).');
  ok(base.hostDisplay === 'inline-block' && base.hostPosition === 'relative' && base.perspective === '500px' && base.reliefs === 0 && base.hostText === 'Tres de', 'Sin JavaScript: título una vez, en inline-block y con 500 px de perspectiva desde el primer pintado.');
  ok(base.hostBox.w < plain.hostBox.w, 'El anfitrión en línea se ajusta al texto (como el de origen).');
  axPlain = await axTree(page);
  await page.close();
}

// 2. Con JavaScript: capas, transformaciones, accesibilidad.
const page = await open();
const plain = await state(page, 'r0plain');
const base = await state(page, 'r1base');
ok(base.reliefs === 1 && base.layers === 8 && base.copies.length === 7, `Ocho capas: la frontal y siete copias (${base.layers}).`);
ok(base.front.transform === 'translateZ(15px)' && base.front.hidden === null && base.front.inert === false && base.front.text === 'Tres de', 'La capa frontal lleva el contenido original, accesible, a +profundidad/2.');
const expectedZ = [11.25, 7.5, 3.75, 0, -3.75, -7.5, -11.25];
ok(base.copies.every((c, j) => c.transform === `translateZ(${expectedZ[j]}px)`), 'Cada copia a profundidad/2 − i·profundidad/N: ' + base.copies.map(c => c.transform).join(' '));
ok(base.copies.every((c, j) => near(Number(c.opacity), (1 - (j + 1) / 8) / 2, 1e-9)), 'Desvanecido (1 − i/N)/2: ' + base.copies.map(c => c.opacity).join(' '));
ok(base.copies.every(c => c.hidden === 'true' && c.inert && c.copy && c.events === 'none' && c.select === 'none' && c.position === 'absolute' && c.text === 'Tres de'), 'Copias con aria-hidden, inert, sin puntero ni selección y superpuestas.');
ok(base.copies.every(c => c.offset === base.front.offset), 'Cada copia ocupa la misma caja que la capa frontal antes de su desplazamiento.');
ok(base.stackStyle === 'preserve-3d' && base.stack === '', 'La pila conserva el 3D y no gira sin evento.');
const scale = base.front.box.h / plain.hostBox.h;
ok(near(scale, 500 / 485, 0.01), `La capa frontal crece P/(P − d/2) = ${(500 / 485).toFixed(4)} (mide ${scale.toFixed(4)}).`);
ok(base.sw <= base.vw, 'Sin desplazamiento horizontal.');

// Accesibilidad: el título se anuncia una vez y el orden de tabulación no cambia.
const axRelief = await axTree(page);
ok(axRelief === axPlain && 2 === axRelief.split('heading «Tres de» 2').length - 1 && 1 === axRelief.split('link «Con enlace»').length - 1 && axRelief.includes('heading «Avanzado extra» 2'), 'El árbol de accesibilidad es idéntico con y sin el script: cada título y su enlace, una sola vez.\n' + axRelief);
const link = await page.evaluate(() => ({ ids: document.querySelectorAll('#enlace-titulo').length, links: [...document.querySelectorAll('a[href="#destino"]')].filter(a => !a.closest('[inert]')).length, all: document.querySelectorAll('a[href="#destino"]').length }));
ok(link.ids === 1 && link.links === 1 && link.all === 4, `Un solo id y un solo enlace operable; las copias del enlace son inertes (${link.links} de ${link.all}).`);
await page.focus('body');
const tabbed = [];
for (let i = 0; i < 3; i++) { await page.keyboard.press('Tab'); tabbed.push(await page.evaluate(() => document.activeElement.getAttribute('href'))); }
ok(tabbed[0] === '#destino' && tabbed[1] === '#fin', 'Tabulación: el enlace del título una vez y luego el final: ' + tabbed.join(' '));
const hit = await page.evaluate(() => { const a = document.querySelector('#enlace-titulo'), b = a.getBoundingClientRect(); return document.elementFromPoint(b.left + b.width / 2, b.top + b.height / 2) === a; });
ok(hit, 'Un clic en el título llega al enlace de la capa frontal.');

// Color de la profundidad, perspectiva propia y unidad em.
const colored = await state(page, 'r3color');
ok(colored.perspective === '200px' && colored.layers === 3 && colored.front.transform === 'translateZ(0.5em)' && colored.copies[0].transform.startsWith('translateZ(0.1666') && colored.copies[1].transform.startsWith('translateZ(-0.1666'), 'Perspectiva propia y profundidad en em: ' + colored.front.transform + ' ' + colored.copies.map(c => c.transform).join(' '));
ok(colored.copies.every(c => c.color === 'rgb(192, 57, 43)' && c.opacity === '') && colored.front.color === 'rgb(26, 26, 26)', 'El color de la profundidad sólo pinta las copias; sin desvanecido, opacidad plena.');

// Encabezado avanzado: el anfitrión es el título principal, el resto no se toca.
const adv = await state(page, 'r7adv');
ok(adv.layers === 2 && adv.hostText.startsWith('Avanzado') && adv.front.text === 'Avanzado', 'En el Encabezado avanzado se apila sólo el título principal.');
ok(await page.evaluate(() => document.querySelector('[data-id="r7adv"] .digi-advanced-heading__split').closest('.digi-relief') === null), 'El texto destacado y el antetítulo quedan fuera de la pila.');

// Giro con el puntero: arriba a la izquierda, x = y = −1.
await page.mouse.move(0, 0);
await sleep(80);
let pointer = await state(page, 'r4pointer');
let reverse = await state(page, 'r5reverse');
ok(pointer.stack === 'rotateX(20deg) rotateY(-20deg)', 'Con el puntero arriba a la izquierda gira 20° hacia él: ' + pointer.stack);
ok(reverse.stack === 'rotateX(-20deg) rotateY(20deg)', 'En sentido inverso gira al revés: ' + reverse.stack);
await page.mouse.move(640, 400);
await sleep(80);
pointer = await state(page, 'r4pointer');
ok(pointer.stack === 'rotateX(0deg) rotateY(0deg)', 'Con el puntero en el centro no gira: ' + pointer.stack);
ok((await state(page, 'r1base')).stack === '', 'Un encabezado sin evento no gira con el puntero.');

// Giro al desplazar (sólo vertical): cambia con el desplazamiento y nunca en Y.
const before = (await state(page, 'r6scroll')).stack;
await page.evaluate(() => scrollTo(0, 400));
await sleep(120);
const after = (await state(page, 'r6scroll')).stack;
ok(/^rotateX\(-?[\d.e-]+deg\) rotateY\(0deg\)$/.test(before) && before !== after && after.endsWith('rotateY(0deg)'), `Al desplazar en vertical gira sólo sobre X: ${before} → ${after}`);

// Popups que llegan después.
await page.evaluate(() => {
  const w = document.querySelector('[data-id="r1base"]').cloneNode(true);
  w.dataset.id = 'popup';
  w.querySelector('h2').textContent = 'Emergente';
  delete w.r;
  document.body.append(w);
  dispatchEvent(new Event('elementor/popup/show'));
});
const popup = await state(page, 'popup');
ok(popup.layers === 8 && popup.front.text === 'Emergente', 'Un encabezado que llega en un popup se apila al mostrarse.');
ok((await state(page, 'r1base')).layers === 8, 'Volver a recorrer la página no apila dos veces.');

// Elementos que añade la cuadrícula de bucle sin recargar: «Cargar más» (`digi-loop-loaded`) y filtros o paginación
// (`digi-loop-updated`), los dos con burbuja desde el widget, como en modules/digitalisimo-loop.
for (const [id, event] of [['loopmore', 'digi-loop-loaded'], ['loopfilter', 'digi-loop-updated']]) {
  await page.evaluate((id, event) => {
    const grid = document.createElement('div');
    const w = document.querySelector('[data-id="r1base"]').cloneNode(true);
    w.dataset.id = id;
    w.querySelector('h2').textContent = 'Del bucle';
    grid.append(w);
    document.body.append(grid);
    grid.dispatchEvent(new CustomEvent(event, { bubbles: true, detail: { items: [w] } }));
  }, id, event);
  const loop = await state(page, id);
  ok(loop.layers === 8 && loop.front.text === 'Del bucle' && loop.copies.every(c => c.hidden === 'true' && c.inert), `Un encabezado que añade la cuadrícula de bucle (${event}) se apila.`);
}
ok((await state(page, 'popup')).layers === 8 && (await state(page, 'r1base')).layers === 8, 'Los eventos del bucle no vuelven a apilar lo ya apilado.');
await page.close();

// 3. Movimiento reducido: capas sí, giro no.
{
  const reduced = await open({ reduce: true });
  await reduced.mouse.move(0, 0);
  await sleep(80);
  const r = await state(reduced, 'r4pointer');
  const s = await state(reduced, 'r6scroll');
  ok(r.layers === 8 && r.stack === '' && s.stack === '', 'Con movimiento reducido el relieve se ve, pero no gira.');
  await reduced.close();
}

// 4. Teléfono: sin desplazamiento horizontal.
{
  const phone = await open({ width: 360 });
  const p = await state(phone, 'r1base');
  ok(p.sw <= p.vw, 'En un teléfono no hay desplazamiento horizontal.');
  await phone.close();
}

await browser.close();
ok(errors.length === 0, 'Sin errores en la consola: ' + errors.join(' | '));
console.log(`Relieve 3D en Chrome: ${checks} comprobaciones en verde.`);
