// Lista de videos (digitalisimo-video-playlist, C2, frente C de docs/pro/c2.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/video-playlist.html y video-playlist-pro.html, que son el marcado del widget con y sin las fachadas de
// vídeo del sitio (lo comprueba tests/elements-pro-video-playlist.php), servidas por un servidor HTTP local con la raíz del
// repositorio:
// - con las fachadas: ninguna petición a YouTube, Vimeo ni a sus miniaturas, ni al vídeo alojado (preload="none"), antes
//   del clic;
// - pestañas: flechas, Inicio y Fin mueven el foco (en bucle, sin elegir); Intro, Espacio y el clic eligen y reproducen
//   (aria-selected, tabindex, paneles y pestañas internas visibles); el botón de la fachada crea el iframe (YouTube sin
//   cookies, con autoplay) y le pasa el foco; Vimeo sin seguimiento; ?playlist=&video= al elegir;
// - vídeo alojado: se reproduce al elegirlo, se pausa al cambiar de pestaña; al terminar, se marca como visto (también en
//   una visita nueva) y pasa al siguiente;
// - pestañas internas con teclado (activación automática) y «Mostrar más» sólo con texto que no cabe;
// - ?playlist=w2&video=y elige ese vídeo sin reproducirlo; reproducción automática al cargar del vídeo alojado,
//   silenciado, al cargar (como PRO); en el móvil, el botón de 24 × 24 px pliega la lista y las pestañas internas se apilan
//   con un título por panel; ningún error de JavaScript;
// - sin las fachadas, como PRO: el reproductor del primer vídeo al cargar (sin reproducir ni quitar el foco) o, con «Carga
//   diferida», al acercarse; «Al cargar» lo crea silenciado y en marcha; la miniatura guardada del servicio se pinta;
//   degradados de la lista al desplazarla; el título de cada vídeo en un botón con la letra de los botones.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 150 node tests/elements-pro-video-playlist-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { createServer } from 'node:http';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la Lista de videos (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const root = fileURLToPath(new URL('..', import.meta.url));
const types = { '.html': 'text/html; charset=utf-8', '.css': 'text/css', '.js': 'text/javascript', '.mp4': 'video/mp4' };
const server = createServer((req, res) => {
  const path = normalize(join(root, decodeURIComponent(new URL(req.url, 'http://x').pathname)));
  if (!path.startsWith(root) || !existsSync(path) || !statSync(path).isFile()) { res.writeHead(404); res.end(); return; }
  const body = readFileSync(path);
  const range = /bytes=(\d+)-(\d*)/.exec(req.headers.range || '');
  if (range) {
    const start = +range[1], end = range[2] ? +range[2] : body.length - 1;
    res.writeHead(206, { 'Content-Type': types[extname(path)] || 'application/octet-stream', 'Content-Range': `bytes ${start}-${end}/${body.length}`, 'Accept-Ranges': 'bytes', 'Content-Length': end - start + 1 });
    res.end(body.subarray(start, end + 1));
    return;
  }
  res.writeHead(200, { 'Content-Type': types[extname(path)] || 'application/octet-stream', 'Accept-Ranges': 'bytes' });
  res.end(body);
});
await new Promise(r => server.listen(0, '127.0.0.1', r));
const origin = `http://127.0.0.1:${server.address().port}/tests/fixtures/`;
const base = origin + 'video-playlist.html';
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu', '--autoplay-policy=no-user-gesture-required'] });
const errors = [];
async function open({ width = 1280, query = '', context = null, url = base } = {}) {
  const page = await (context || browser).newPage();
  const requests = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(m.text()); });
  await page.setRequestInterception(true);
  page.on('request', r => {
    requests.push(r.url());
    if (r.url().startsWith('http://127.0.0.1') || r.url().startsWith('data:')) r.continue().catch(() => {}); else r.abort().catch(() => {});
  });
  await page.setViewport({ width, height: 900 });
  await page.goto(url + query, { waitUntil: 'load' });
  await page.waitForFunction(() => [...document.querySelectorAll('[data-digi-vpl]')].every(r => r.digiVpl));
  page.requests = requests;
  return page;
}
const state = (page, id) => page.evaluate(id => {
  const r = document.querySelector(`[data-id="${id}"] .digi-vpl`);
  const tabs = [...r.querySelectorAll('.digi-vpl__tab')];
  return {
    selected: tabs.map(t => (t.getAttribute('aria-selected') === 'true' ? 'X' : '-')).join(''),
    tabindex: tabs.map(t => t.tabIndex).join(','),
    panels: [...r.querySelectorAll('.digi-vpl__panel')].map(p => (p.hidden ? '-' : 'X')).join(''),
    inner: [...r.querySelectorAll('.digi-vpl__in')].map(p => (p.hidden ? '-' : 'X')).join(''),
    seen: tabs.map(t => (t.classList.contains('digi-vpl__tab--seen') ? 'V' : '-')).join(''),
    frames: [...r.querySelectorAll('iframe')].map(f => f.src),
    focus: document.activeElement === r ? 'root' : (document.activeElement.closest('[data-id]') ? (document.activeElement.className || document.activeElement.tagName) + ':' + (document.activeElement.textContent || '').trim().slice(0, 20) : document.activeElement.tagName),
  };
}, id);
const third = list => list.filter(u => !u.startsWith('http://127.0.0.1') && !u.startsWith('data:'));

// 1. Carga: nada de terceros ni el vídeo alojado; la primera pestaña elegida.
let page = await open();
await sleep(400);
ok(third(page.requests).length === 0, 'Ninguna petición a terceros al cargar: ' + third(page.requests).join(', '));
ok(await page.$eval('#digi-vpl-w1-panel-4 video', v => v.readyState === 0 && v.preload === 'none'), 'El vídeo alojado no se descarga antes de reproducirlo (preload="none"; el de «Al cargar» de w3 sí, como en PRO).');
let s = await state(page, 'w1');
ok(s.selected === 'X---' && s.panels === 'X---' && s.tabindex === '0,-1,-1,-1' && s.inner === '-' && s.frames.length === 0, 'Estado inicial: ' + JSON.stringify(s));
ok(await page.$eval('[data-id="w1"] .digi-vpl__overlay .digi-vpl__play', b => b.getAttribute('aria-label')) === 'Reproducir el vídeo: Uno', 'Imagen superpuesta con el botón de reproducir.');

// 2. Teclado: flechas, Inicio y Fin mueven el foco sin elegir; Intro elige y reproduce.
await page.focus('[data-id="w1"] .digi-vpl__tab');
await page.keyboard.press('ArrowDown');
s = await state(page, 'w1');
ok(/digi-vpl__tab:Dos/.test(s.focus) && s.selected === 'X---', 'Flecha abajo: foco en la siguiente pestaña (la sección no es una pestaña), sin elegirla: ' + s.focus);
await page.keyboard.press('End');
ok(/Cuatro/.test((await state(page, 'w1')).focus), 'Fin: la última.');
await page.keyboard.press('ArrowDown');
ok(/Uno/.test((await state(page, 'w1')).focus), 'Bucle: de la última a la primera.');
await page.keyboard.press('ArrowUp');
await page.keyboard.press('Home');
ok(/Uno/.test((await state(page, 'w1')).focus), 'Inicio: la primera.');
await page.keyboard.press('ArrowRight');
await page.keyboard.press('Enter');
await sleep(300);
s = await state(page, 'w1');
ok(s.selected === '-X--' && s.panels === '-X--' && s.tabindex === '-1,0,-1,-1', 'Intro elige la pestaña y muestra su panel: ' + JSON.stringify(s));
ok(s.frames.length === 1 && s.frames[0].startsWith('https://player.vimeo.com/video/76979871?dnt=1&autoplay=1'), 'Vimeo sin seguimiento, creado al elegir: ' + s.frames[0]);
ok(third(page.requests).every(u => u.startsWith('https://player.vimeo.com/')), 'Sólo el reproductor elegido, después de elegirlo: ' + third(page.requests).join(', '));
ok(new URL(page.url()).searchParams.get('playlist') === 'w1' && new URL(page.url()).searchParams.get('video') === 'b', 'La dirección lleva ?playlist=w1&video=b: ' + page.url());

// 3. Clic en la pestaña de YouTube y en el botón de la fachada.
await page.click('[data-id="w1"] #digi-vpl-w1-tab-1');
await sleep(300);
s = await state(page, 'w1');
ok(s.selected === 'X---' && s.frames.length === 2 && /^https:\/\/www\.youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?rel=0&enablejsapi=1&autoplay=1&origin=/.test(s.frames.find(f => /youtube/.test(f))), 'Clic en la pestaña: YouTube sin cookies con autoplay (el de Vimeo sigue en su panel oculto, en pausa): ' + s.frames.join(' '));
ok(!(await page.$('[data-id="w1"] .digi-vpl__overlay')), 'Reproducir quita la imagen superpuesta.');
ok(!page.requests.some(u => /img\.youtube|i\.ytimg|youtube\.com\/iframe_api|www\.youtube\.com\//.test(u)), 'Nunca la API ni las miniaturas del servicio.');

// 4. Vídeo alojado: reproducción, pausa al cambiar y, al terminar, visto y siguiente.
await page.click('[data-id="w1"] #digi-vpl-w1-tab-4');
await page.waitForFunction(() => !document.querySelector('#digi-vpl-w1-panel-4 video').paused, { timeout: 5000 });
ok(await page.$eval('#digi-vpl-w1-panel-4 video', v => v.readyState > 0), 'El vídeo alojado se pide al reproducirlo.');
await page.click('[data-id="w1"] #digi-vpl-w1-tab-1');
await sleep(200);
ok(await page.$eval('#digi-vpl-w1-panel-4 video', v => v.paused), 'Cambiar de pestaña pausa el vídeo alojado.');
await page.click('[data-id="w1"] #digi-vpl-w1-tab-4');
await sleep(200);
await page.$eval('#digi-vpl-w1-panel-4 video', v => v.dispatchEvent(new Event('ended')));
await sleep(300);
s = await state(page, 'w1');
ok(s.selected === '---X' && s.seen === '--V-' && s.inner === 'X', 'Al terminar: visto y pasa al siguiente, con sus pestañas internas: ' + JSON.stringify(s));
ok(await page.evaluate(() => localStorage.getItem('digi-vpl-watched-w1')) === '["c"]', 'Vistos en el almacenamiento local del navegador.');

// 5. Pestañas internas y «Mostrar más».
const inner = () => page.evaluate(() => {
  const b = document.querySelector('[data-id="w1"] .digi-vpl__in');
  const tabs = [...b.querySelectorAll('[role="tab"]')];
  const more = b.querySelector('.digi-vpl__in-panel:not([hidden]) .digi-vpl__more');
  return { sel: tabs.map(t => (t.getAttribute('aria-selected') === 'true' ? 'X' : '-')).join(''), panels: [...b.querySelectorAll('[role="tabpanel"]')].map(p => (p.hidden ? '-' : 'X')).join(''), more: more ? (more.hidden ? 'oculto' : more.textContent + '|' + more.getAttribute('aria-expanded')) : 'ninguno', h: b.querySelector('.digi-vpl__in-panel:not([hidden]) .digi-vpl__in-text').clientHeight };
});
let i = await inner();
ok(i.sel === 'X-' && i.panels === 'X-' && i.more === 'Mostrar más|false' && i.h === 54, '«Mostrar más» visible con un texto que no cabe: ' + JSON.stringify(i));
await page.click('[data-id="w1"] .digi-vpl__in-panel:not([hidden]) .digi-vpl__more');
i = await inner();
ok(i.more === 'Mostrar menos|true' && i.h > 54, '«Mostrar más» despliega el texto: ' + JSON.stringify(i));
await page.focus('[data-id="w1"] .digi-vpl__in-tab');
await page.keyboard.press('ArrowRight');
i = await inner();
ok(i.sel === '-X' && i.panels === '-X' && i.more === 'oculto', 'Flecha derecha elige la segunda pestaña interna; su texto cabe y no ofrece «Mostrar más»: ' + JSON.stringify(i));
await page.close();

// 6. Una visita nueva conserva los vistos; ?playlist=w2&video=y elige sin reproducir.
const ctx = await browser.createBrowserContext();
page = await open({ context: ctx });
await page.evaluate(() => localStorage.setItem('digi-vpl-watched-w1', '["c","a"]'));
await page.close();
page = await open({ context: ctx, query: '?playlist=w2&video=y' });
await sleep(600);
ok((await state(page, 'w1')).seen === 'V-V-', 'Vistos de una visita anterior.');
s = await state(page, 'w2');
ok(s.selected === '-X' && s.panels === '-X' && s.frames.length === 0 && third(page.requests).length === 0, 'La dirección elige el vídeo sin reproducirlo ni pedir nada: ' + JSON.stringify(s));
ok(await page.evaluate(() => { const r = document.querySelector('[data-id="w2"]').getBoundingClientRect(); return r.top < innerHeight && r.bottom > 0; }), 'La página baja hasta la lista de la dirección.');
await page.close();
await ctx.close();

// 7. Reproducción automática al cargar: el vídeo alojado, silenciado, al cargar la página (sin «Carga diferida», PRO no
// espera a que se vea).
page = await open();
await page.waitForFunction(() => !document.querySelector('[data-id="w3"] video').paused, { timeout: 5000 });
ok(await page.$eval('[data-id="w3"] video', v => v.muted), 'Al cargar: reproducido y silenciado, aunque esté fuera de la pantalla, como PRO.');
ok(await page.evaluate(() => scrollY === 0 && document.activeElement === document.body), 'Reproducir al cargar no mueve la página ni el foco.');
await page.close();

// 8. Móvil: el botón pliega la lista.
page = await open({ width: 375 });
const fold = await page.$eval('[data-id="w1"] .digi-vpl__fold', b => { const r = b.getBoundingClientRect(); return [r.width, r.height, getComputedStyle(b).display, b.getAttribute('aria-label')]; });
ok(fold[0] >= 24 && fold[1] >= 24 && fold[2] !== 'none' && fold[3] === 'Mostrar la lista de vídeos', 'Botón de la lista visible en el móvil, de 24 × 24 px: ' + fold.join(' '));
const big = await page.$eval('[data-id="w1"] .digi-vpl__tab', t => t.getBoundingClientRect().height);
ok(big >= 24, 'Pestañas de 24 px de alto como mínimo.');
// Pestañas internas en el móvil, como PRO: sin la fila de pestañas, un título por panel que lo abre en lugar del otro.
await page.click('[data-id="w1"] #digi-vpl-w1-tab-5');
await sleep(200);
const mob = () => page.evaluate(() => {
  const b = document.querySelector('[data-id="w1"] .digi-vpl__in');
  return { list: getComputedStyle(b.querySelector('.digi-vpl__in-list')).display, titles: [...b.querySelectorAll('.digi-vpl__in-mob')].map(m => [getComputedStyle(m).display, Math.round(m.getBoundingClientRect().height), getComputedStyle(m).fontSize, getComputedStyle(m).fontWeight, getComputedStyle(m).color, m.getAttribute('aria-expanded')].join(' ')), panels: [...b.querySelectorAll('[role="tabpanel"]')].map(p => (p.hidden ? '-' : 'X')).join('') };
});
let m = await mob();
ok(m.list === 'none' && m.titles[0] === 'block 38 16px 700 rgb(0, 0, 0) true' && m.titles[1] === 'block 38 16px 700 rgb(0, 0, 0) false' && m.panels === 'X-', 'Móvil: títulos apilados de 38 px, 16 px en negrita, con el color del texto (no el de los botones del tema): ' + JSON.stringify(m));
await page.click('[data-id="w1"] .digi-vpl__in-mob[aria-expanded="false"]');
m = await mob();
ok(m.panels === '-X' && /true$/.test(m.titles[1]) && /false$/.test(m.titles[0]), 'Móvil: el título del panel cerrado lo abre en lugar del otro: ' + JSON.stringify(m));
await page.click('[data-id="w1"] .digi-vpl__fold');
ok(await page.$eval('[data-id="w1"]', w => w.querySelector('.digi-vpl__fold').getAttribute('aria-expanded') === 'false' && getComputedStyle(w.querySelector('.digi-vpl__list')).display === 'none'), 'Plegada: aria-expanded="false" y la lista oculta.');
const stage = await page.$eval('[data-id="w1"] .digi-vpl__stage', e => [e.getBoundingClientRect().width, e.getBoundingClientRect().height]);
ok(Math.abs(stage[1] - stage[0] * 9 / 16) < 2, 'En el móvil, el reproductor en 16:9: ' + stage.join('×'));
await page.close();

// 9. Sin las fachadas del sitio, como PRO.
page = await open({ url: origin + 'video-playlist-pro.html' });
await sleep(400);
s = await state(page, 'p1');
ok(s.frames.length === 1 && /^https:\/\/www\.youtube-nocookie\.com\/embed\/aaaaaaaaaaa\?rel=0&enablejsapi=1&origin=/.test(s.frames[0]) && !/autoplay|mute/.test(s.frames[0]), 'Sin las fachadas, el reproductor del primer vídeo se crea al cargar, sin reproducir: ' + s.frames.join(' '));
ok(await page.evaluate(() => document.activeElement === document.body && scrollY === 0), 'Crear el reproductor al cargar no mueve el foco ni la página.');
ok(page.requests.some(u => u === 'https://img.youtube.com/vi/aaaaaaaaaaa/maxresdefault.jpg'), 'La miniatura guardada del servicio se pinta, como en PRO.');
s = await state(page, 'p3');
ok(s.frames.length === 1 && s.frames[0].startsWith('https://player.vimeo.com/video/76979871?dnt=1&autoplay=1&muted=1'), '«Al cargar» con Vimeo: creado silenciado y en marcha, como PRO: ' + s.frames.join(' '));
ok((await state(page, 'p2')).frames.length === 0, 'Con «Carga diferida», fuera de la pantalla todavía no.');
await page.$eval('[data-id="p2"]', e => e.scrollIntoView());
await page.waitForFunction(() => document.querySelector('[data-id="p2"] iframe'), { timeout: 5000 });
s = await state(page, 'p2');
ok(s.frames.length === 1 && !/autoplay/.test(s.frames[0]), 'Con «Carga diferida», el reproductor se crea al acercarse, sin reproducir: ' + s.frames[0]);
await page.$eval('[data-id="p1"]', e => e.scrollIntoView());
// Degradados (diez vídeos desbordan la lista).
const shade = () => page.evaluate(() => {
  const l = document.querySelector('[data-id="p1"] .digi-vpl__list');
  return [l.classList.contains('digi-vpl__list--top'), l.classList.contains('digi-vpl__list--bottom'), getComputedStyle(l.querySelector('.digi-vpl__shadow--top')).display, getComputedStyle(l.querySelector('.digi-vpl__shadow--bottom')).display, getComputedStyle(l.querySelector('.digi-vpl__shadow--bottom')).height, getComputedStyle(l.querySelector('.digi-vpl__shadow--bottom')).backgroundImage].join('|');
});
ok(await shade() === 'false|true|none|block|98px|linear-gradient(rgba(0, 0, 0, 0), rgb(255, 255, 255))', 'La lista que desborda: degradado de 98 px abajo, como PRO: ' + await shade());
await page.$eval('[data-id="p1"] .digi-vpl__items', e => { e.scrollTop = 120; });
await sleep(150);
ok(/^true\|true\|block\|block/.test(await shade()), 'Al desplazarla: arriba y abajo: ' + await shade());
await page.$eval('[data-id="p1"] .digi-vpl__items', e => { e.scrollTop = e.scrollHeight; });
await sleep(150);
ok(/^true\|false\|block\|none/.test(await shade()), 'Al final: sólo arriba: ' + await shade());
// El título en un botón con la letra de los botones del navegador y del tema (fuera del orden del teclado).
const title = await page.$eval('[data-id="p1"] .digi-vpl__title button', b => [b.tabIndex, getComputedStyle(b).fontSize, getComputedStyle(b).borderTopStyle, getComputedStyle(b).paddingLeft, getComputedStyle(b).color, b.textContent].join('|'));
ok(title === '-1|13.3333px|none|0px|rgb(204, 51, 102)|Primero', 'El título en un botón, como PRO (13,33 px del navegador, el color de los botones del tema y sin su borde ni su relleno): ' + title);
await page.click('[data-id="p1"] #digi-vpl-p1-tab-3 .digi-vpl__title button');
await sleep(200);
ok((await state(page, 'p1')).selected === '--X-------', 'Pulsar el título elige su pestaña.');
await page.close();

ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
await browser.close();
server.close();
console.log(`DIGITALÍSIMO Elements: Lista de videos en Chrome (pestañas, fachadas, vídeo alojado, vistos, dirección, pestañas internas y móvil) validada: ${checks} comprobaciones.`);
