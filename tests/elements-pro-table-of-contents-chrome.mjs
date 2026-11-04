// Tabla de contenido (digitalisimo-table-of-contents, C2 frente A de docs/pro/c2.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-table-of-contents.html, que es el marcado de Table_Of_Contents::render() dentro de documentos del
// constructor:
// - jerárquica con números: encabezados de los documentos que no son cabecera, pie ni emergente, sin el título de la
//   propia tabla ni las etiquetas no pedidas; niveles, anclas antes de los encabezados sin id (ids de PRO) y el id propio
//   del encabezado o de su widget; texto como texto (nunca HTML);
// - enlace activo al desplazarse (aria-current) y al pulsar; botón para minimizar con ratón y teclado (Intro y espacio),
//   de 24 × 24 px como mínimo; minimizada al cargar en su punto de corte (no por encima) y en todo con «Escritorio»;
// - viñetas en vista plana con el marcador, contenedor y exclusión por selector; plegado de subelementos; mensaje sin
//   encabezados; emergente (sólo sus encabezados); editor (sin anclas, reinicio con frontend/element_ready/global);
//   movimiento reducido (sin giro ni transición); sin JavaScript, el indicador de carga.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-pro-table-of-contents-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la Tabla de contenido (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-table-of-contents.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open(query = '', { width = 1280, reduce = false, js = true, before = null } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: reduce ? 'reduce' : 'no-preference' }]);
  await page.setViewport({ width, height: 700 });
  if (before) await page.evaluateOnNewDocument(before);
  await page.goto(fixture + '?' + query + (js ? '' : '&js=0'), { waitUntil: 'load' });
  if (js) await page.waitForFunction(() => !document.querySelector('.digi-toc__spinner'));
  return page;
}
// Árbol de la lista: [texto, href, activo, hijos].
const tree = page => page.evaluate(() => {
  const walk = list => [...list.children].map(li => {
    const a = li.querySelector(':scope>.digi-toc__row>a');
    const sub = li.querySelector(':scope>.digi-toc__list');
    return [a.textContent, a.getAttribute('href'), a.getAttribute('aria-current') === 'true', sub ? walk(sub) : null];
  });
  const top = document.querySelector('.digi-toc__body>.digi-toc__list');
  return top ? { tag: top.tagName, items: walk(top) } : null;
});
const state = page => page.evaluate(() => {
  const host = document.querySelector('[data-id="toc"]'), btn = host.querySelector('.digi-toc__toggle'), body = host.querySelector('.digi-toc__body');
  const r = btn ? btn.getBoundingClientRect() : null;
  return {
    collapsed: host.classList.contains('digi-toc--collapsed'),
    expanded: btn ? btn.getAttribute('aria-expanded') : null,
    bodyShown: getComputedStyle(body).display !== 'none',
    button: r ? [Math.round(r.width), Math.round(r.height)] : null,
    icon: btn ? [...btn.querySelectorAll('.digi-toc__toggle-icon')].map(s => getComputedStyle(s).display !== 'none') : null,
    active: [...host.querySelectorAll('.digi-toc__link--active')].map(a => a.textContent),
    open: [...host.querySelectorAll('.digi-toc__list--open')].length,
  };
});

try {
  // 1. Jerárquica con números (por defecto), a 1280 px.
  {
    const page = await open('case=default');
    const t = await tree(page);
    ok(t.tag === 'OL', 'Números: lista ordenada.');
    const flat = JSON.stringify(t.items.map(([x, h, , s]) => [x, h, s && s.map(([y, g, , u]) => [y, g, u && u.map(([z, k]) => [z, k])])]));
    ok(flat === JSON.stringify([
      ['Primera <b>sección</b>', '#elementor-toc__heading-anchor-0', [['Uno punto uno', '#elementor-toc__heading-anchor-1', null], ['Uno punto dos', '#propio', [['Detalle', '#elementor-toc__heading-anchor-3']]]]],
      ['Segunda sección', '#seccion-b', [['Tres', '#elementor-toc__heading-anchor-5', null]]],
    ]), 'Jerarquía, textos como texto, ids de ancla de PRO e ids propios del encabezado o de su widget, sin cabecera, pie, emergente, h1 ni el título de la tabla: ' + flat);
    const anchors = await page.evaluate(() => [...document.querySelectorAll('.digi-toc__anchor')].map(s => [s.id, s.nextElementSibling.textContent]));
    ok(JSON.stringify(anchors) === JSON.stringify([['elementor-toc__heading-anchor-0', 'Primera <b>sección</b>'], ['elementor-toc__heading-anchor-1', 'Uno punto uno'], ['elementor-toc__heading-anchor-3', 'Detalle'], ['elementor-toc__heading-anchor-5', 'Tres']]), 'Un ancla antes de cada encabezado sin id propio: ' + JSON.stringify(anchors));
    ok(await page.evaluate(() => !document.querySelector('.digi-toc__body b, .digi-toc__body img')), 'El texto del encabezado no crea elementos.');
    ok(await page.evaluate(() => document.querySelectorAll('.digi-toc__link--top').length === 2), 'Dos enlaces de primer nivel.');
    let s = await state(page);
    ok(!s.collapsed && s.expanded === 'true' && s.bodyShown && s.icon.join() === 'false,true', 'A 1280 px no empieza minimizada (punto de corte de la tableta); se ve el icono para plegar.');
    ok(s.button[0] >= 24 && s.button[1] >= 24, 'Botón de 24 × 24 px como mínimo: ' + s.button);
    ok(s.active.join('|') === 'Primera <b>sección</b>', 'Activo el encabezado a la vista: ' + s.active);
    ok(await page.evaluate(() => document.querySelector('.digi-toc__link--active').getAttribute('aria-current') === 'true'), 'El activo lleva aria-current.');
    await page.evaluate(() => document.getElementById('seccion-b').scrollIntoView());
    await sleep(400);
    s = await state(page);
    ok(s.active.join('|') === 'Segunda sección', 'Al desplazarse, activo el encabezado que se ve: ' + s.active);
    await page.click('.digi-toc__toggle');
    await sleep(100);
    s = await state(page);
    ok(s.collapsed && s.expanded === 'false' && !s.bodyShown && s.icon.join() === 'true,false', 'Clic: minimizada, aria-expanded="false" y el icono para desplegar.');
    await page.focus('.digi-toc__toggle');
    await page.keyboard.press('Enter');
    await sleep(100);
    s = await state(page);
    ok(!s.collapsed && s.expanded === 'true' && s.bodyShown, 'Intro: se despliega.');
    await page.keyboard.press(' ');
    await sleep(100);
    ok((await state(page)).collapsed, 'Espacio: se minimiza (botón nativo).');
    await page.keyboard.press(' ');
    await sleep(100);
    const focus = await page.evaluate(() => { const b = document.activeElement, cs = getComputedStyle(b); return [b.className, cs.outlineStyle, cs.outlineWidth, cs.outlineOffset]; });
    ok(focus.join('|') === 'digi-toc__toggle|solid|2px|2px', 'El foco queda en el botón, con contorno de 2 px y separación de 2 px: ' + focus);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.click('.digi-toc__link[href="#propio"]');
    await sleep(150);
    const clickedActive = (await state(page)).active;
    ok(clickedActive[0] === 'Uno punto dos', 'Al pulsar un enlace, queda activo (con los demás encabezados a la vista al terminar el salto, como PRO): ' + clickedActive);
    await page.waitForFunction(() => location.hash === '#propio');
    await page.close();
  }
  // 2. Minimizada al cargar en su punto de corte (y no por encima), y con «Escritorio» en todos.
  {
    const page = await open('case=default', { width: 800 });
    const s = await state(page);
    ok(s.collapsed && s.expanded === 'false' && !s.bodyShown, 'A 800 px empieza minimizada (≤ 1024 px).');
    await page.setViewport({ width: 1280, height: 700 });
    await sleep(100);
    ok((await state(page)).collapsed, 'Como PRO, el punto de corte se evalúa al cargar.');
    await page.close();
    const big = await open('case=empty', { width: 1600 });
    const e = await state(big);
    ok(!e.collapsed, 'Sin encabezados no se minimiza (como PRO, que sólo lo evalúa con lista).');
    ok(await big.evaluate(() => document.querySelector('.digi-toc__body').innerHTML === 'Sin <strong>encabezados</strong>.'), 'Sin encabezados, el mensaje de su plantilla (con su HTML permitido).');
    await big.close();
    const pop = await open('case=popup', { width: 1600 });
    const p = await state(pop);
    ok(p.collapsed && p.expanded === 'false', '«Escritorio (o menor)»: minimizada también a 1600 px.');
    const pt = await tree(pop);
    ok(pt.items.length === 1 && pt.items[0][0] === 'Del emergente', 'Dentro de un emergente, sólo sus encabezados: ' + JSON.stringify(pt.items));
    await pop.close();
  }
  // 3. Viñetas, vista plana, contenedor y exclusión.
  {
    const page = await open('case=bullets');
    const t = await tree(page);
    ok(t.tag === 'UL' && JSON.stringify(t.items.map(i => [i[0], i[3]])) === JSON.stringify([['Alfa', null], ['Alfa uno', null], ['Beta', null]]), 'Viñetas en una sola lista, sólo dentro del contenedor y sin lo excluido: ' + JSON.stringify(t.items));
    ok(await page.evaluate(() => [...document.querySelectorAll('.digi-toc__row')].every(r => r.firstElementChild.matches('svg.marca') && r.firstElementChild.getAttribute('aria-hidden') === 'true')), 'Cada fila lleva el marcador decorativo antes del enlace.');
    ok(await page.evaluate(() => !document.querySelector('.digi-toc__link--top') && getComputedStyle(document.querySelector('.digi-toc__row'), '::before').content === 'none'), 'Vista plana: sin primer nivel y sin números.');
    await page.close();
  }
  // 4. Plegar subelementos.
  {
    const page = await open('case=collapse');
    const shown = () => page.evaluate(() => [...document.querySelectorAll('.digi-toc__body>.digi-toc__list>.digi-toc__item>.digi-toc__list')].map(l => getComputedStyle(l).display !== 'none'));
    await sleep(200);
    ok((await shown()).join() === 'true,false', 'Se despliegan sólo los subelementos de la sección a la vista: ' + (await shown()));
    await page.evaluate(() => document.getElementById('elementor-toc__heading-anchor-3').scrollIntoView());
    await sleep(400);
    ok((await shown()).join() === 'false,true', 'Al llegar a B se despliega la suya y se pliega la de A: ' + (await shown()));
    // Sin el salto (el desplazamiento al terminar volvería a desplegar la sección a la vista, como en PRO).
    await page.evaluate(() => { const a = document.querySelector('.digi-toc__link--top[href="#elementor-toc__heading-anchor-3"]'); a.addEventListener('click', e => e.preventDefault()); a.click(); });
    await sleep(150);
    ok((await shown()).join() === 'false,false', 'Pulsar un primer nivel con su lista abierta la pliega (como PRO): ' + (await shown()));
    await page.close();
  }
  // 5. Editor: sin anclas, y reinicio con frontend/element_ready/global.
  {
    const page = await open('case=default&editor=1', {
      before: () => { window.elementorFrontend = { isEditMode: () => true, hooks: { addAction: (n, f) => { window.__hook = [n, f]; } } }; },
    });
    ok(await page.evaluate(() => !document.querySelector('.digi-toc__anchor') && document.querySelectorAll('.digi-toc__link').length === 6), 'En el editor se construye la lista sin añadir anclas.');
    ok(await page.evaluate(() => window.__hook && window.__hook[0] === 'frontend/element_ready/global'), 'Se engancha a frontend/element_ready/global.');
    const rebuilt = await page.evaluate(() => {
      const host = document.querySelector('[data-id="toc"]'), nav = host.querySelector('.digi-toc'), copy = nav.cloneNode(true);
      copy.querySelector('.digi-toc__body').innerHTML = '<span class="digi-toc__spinner"></span>';
      nav.replaceWith(copy);
      window.__hook[1]([host]);
      return copy.querySelectorAll('.digi-toc__link').length;
    });
    ok(rebuilt === 6, 'El elemento que el editor vuelve a pintar se construye de nuevo.');
    await page.close();
  }
  // 6. Movimiento reducido y sin JavaScript.
  {
    const page = await open('case=default', { reduce: true });
    ok(await page.evaluate(() => getComputedStyle(document.querySelector('.digi-toc')).transitionDuration === '0s'), 'Con «reducir movimiento», sin transición de la altura.');
    await page.close();
    const nojs = await open('case=default', { js: false, reduce: true });
    ok(await nojs.evaluate(() => { const s = document.querySelector('.digi-toc__spinner'); return !!s && getComputedStyle(s).animationName === 'none'; }), 'Sin JavaScript se ve el indicador de carga; con «reducir movimiento» no gira.');
    await nojs.close();
    const spin = await open('case=default', { js: false });
    ok(await spin.evaluate(() => getComputedStyle(document.querySelector('.digi-toc__spinner')).animationName === 'digi-toc-spin'), 'Sin «reducir movimiento», el indicador gira.');
    await spin.close();
  }
  ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
} finally {
  await browser.close();
}
console.log(`DIGITALÍSIMO Elements: Tabla de contenido en Chrome (${checks} comprobaciones) validada.`);
