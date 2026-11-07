// Fachadas de los widgets de Facebook propios (C9, frente R de docs/pro/c9.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-facebook.html, que es el marcado del servidor (lo comprueba y lo escribe tests/elements-pro-facebook.php):
// - antes del clic, ninguna petición fuera del archivo (ni el SDK, ni iframes, ni imágenes) y ningún plugin en la página;
//   la fachada ocupa el alto del plugin (alto elegido en la Página, 16:9 en el vídeo) y su botón mide ≥ 24 × 24 px;
// - al pulsar (ratón o teclado: Intro y Espacio), el plugin del <template> entra en la página, el SDK se pide UNA sola vez
//   aunque se pulsen varios widgets (la petición se intercepta y se responde con un `FB` simulado), `FB.init` recibe
//   el App ID, la versión v2.10 y `xfbml: false`, `FB.XFBML.parse` se llama sobre cada widget pulsado (y no sobre los
//   demás) y el foco pasa al contenido;
// - foco visible: contorno de 2 px a 2 px en el botón;
// - el Botón y los Comentarios, que Meta retiró, sólo con el `div` del plugin de PRO (vacío, 1 px), sin fachada ni SDK.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-facebook-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los widgets de Facebook (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-facebook.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

// SDK simulado: registra init y parse en la ventana.
const FAKE_SDK = 'window.__fb={init:[],parse:[]};window.FB={init:function(c){window.__fb.init.push(c);},XFBML:{parse:function(el){window.__fb.parse.push(el.parentNode.getAttribute("data-id"));var p=el.querySelector(".digi-fb__plugin");if(p)p.classList.add("fb_iframe_widget");}}};';

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open() {
  const page = await browser.newPage();
  const external = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/ERR_|net::/.test(m.text())) errors.push(m.text()); });
  await page.setRequestInterception(true);
  page.on('request', r => {
    const u = r.url();
    if (u.startsWith('file:') || u.startsWith('data:')) { r.continue(); return; }
    external.push(u);
    if (/^https:\/\/connect\.facebook\.net\/[a-z]{2,3}_[A-Z]{2}\/sdk\.js$/.test(u)) r.respond({ status: 200, contentType: 'application/javascript', body: FAKE_SDK });
    else r.abort();
  });
  await page.setViewport({ width: 1280, height: 900 });
  await page.goto(fixture, { waitUntil: 'load' });
  await sleep(200);
  return { page, external };
}

// ── Antes del clic ───────────────────────────────────────────────────────────────────────────────────────────────────
let { page, external } = await open();
await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
await sleep(300);
ok(external.length === 0, 'Antes del clic, ninguna petición fuera del sitio: ' + external.join(', '));
const before = await page.evaluate(() => ({
  facades: document.querySelectorAll('[data-digi-fb]').length,
  plugins: document.querySelectorAll('body .digi-fb__plugin').length,
  iframes: document.querySelectorAll('iframe').length,
  scripts: [...document.scripts].map(s => s.src).filter(s => !s.startsWith('file:')),
  fb: typeof window.FB,
}));
ok(before.facades === 6 && before.plugins === 6 && before.iframes === 0 && before.scripts.length === 0 && before.fb === 'undefined', 'Fachadas en la Publicación y la Página; fuera del <template> sólo los seis div de los retirados; ni iframes, ni scripts ajenos, ni FB: ' + JSON.stringify(before));
// Retirados por Meta (corrección de fidelidad): como PRO, el div del plugin con sus data-*, vacío y de 1 px, sin fachada.
const retired = await page.evaluate(() => ['button', 'comments'].flatMap((k) => [0, 1, 2].map((i) => { const w = document.querySelector(`[data-id="digitalisimo-facebook-${k}-${i}"]`); const p = w.querySelector(':scope > .digi-fb__plugin'); return { k, facade: !!w.querySelector('.digi-fb'), h: p ? Math.round(p.getBoundingClientRect().height) : -1, href: p && p.getAttribute('data-href') }; })));
ok(retired.every((r) => !r.facade && 1 === r.h && /^https?:/.test(r.href || '')), 'Botón y Comentarios: el div del plugin de PRO, vacío, de 1 px, sin fachada: ' + JSON.stringify(retired));
const sizes = await page.evaluate(() => {
  const box = id => { const r = document.querySelector(`[data-id="${id}"] .digi-fb`).getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; };
  const btn = [...document.querySelectorAll('.digi-fb__load')].map(b => { const r = b.getBoundingClientRect(); return Math.min(r.width, r.height); });
  return { page0: box('digitalisimo-facebook-page-0'), page1: box('digitalisimo-facebook-page-1'), video: box('digitalisimo-facebook-embed-2'), post: box('digitalisimo-facebook-embed-0'), minButton: Math.min(...btn) };
});
ok(sizes.page0[1] === 500 && sizes.page0[0] === 500, 'Página: la fachada reserva 500 × 500 px como el plugin: ' + JSON.stringify(sizes.page0));
ok(sizes.page1[1] === 480, 'Página: alto en em (30em × 16 px): ' + JSON.stringify(sizes.page1));
ok(Math.abs(sizes.video[0] / sizes.video[1] - 16 / 9) < 0.02, 'Vídeo: la fachada reserva 16:9: ' + JSON.stringify(sizes.video));
ok(sizes.post[1] >= 200 && sizes.post[0] <= 500, 'Publicación: alto mínimo de 200 px: ' + JSON.stringify(sizes.post));
ok(sizes.minButton >= 24, 'Todos los botones de al menos 24 × 24 px: ' + JSON.stringify(sizes));
const names = await page.evaluate(() => [...document.querySelectorAll('.digi-fb__load')].map(b => b.textContent.trim()));
ok(names.every(n => /^Facebook(Mostrar|Ver) /.test(n)), 'Cada botón dice qué carga: ' + names.slice(0, 3).join(' | '));
const name = await page.$eval('[data-id="digitalisimo-facebook-page-0"] .digi-fb__load', b => b.closest('div'));
const ax = await page.accessibility.snapshot({ root: await page.$('[data-id="digitalisimo-facebook-page-0"] .digi-fb__load') });
ok(ax && ax.role === 'button' && ax.name === 'Ver la página de Facebook' && /Al pulsar se carga contenido de Facebook/.test(ax.description || ''), 'Nombre accesible sin el rótulo repetido y descripción con la nota: ' + JSON.stringify(ax));

// Foco visible con el teclado.
await page.evaluate(() => window.scrollTo(0, 0));
await page.keyboard.press('Tab');
const focus = await page.evaluate(() => { const e = document.activeElement; const cs = getComputedStyle(e); return { cls: e.className, outline: cs.outlineWidth + ' ' + cs.outlineStyle, offset: cs.outlineOffset }; });
ok(focus.cls === 'digi-fb__load' && focus.outline === '2px solid' && focus.offset === '2px', 'Foco visible de 2 px a 2 px en el botón: ' + JSON.stringify(focus));

// ── Clic en una Página ───────────────────────────────────────────────────────────────────────────────────────────────
await page.click('[data-id="digitalisimo-facebook-page-0"] .digi-fb__load');
await sleep(400);
let state = await page.evaluate(() => ({ fb: window.__fb, plugin: document.querySelector('[data-id="digitalisimo-facebook-page-0"] .digi-fb__plugin.fb-page')?.outerHTML || '', loaded: document.querySelector('[data-id="digitalisimo-facebook-page-0"] .digi-fb').className, active: document.activeElement.className, others: document.querySelectorAll('body .digi-fb:not(.digi-fb--loaded) > .digi-fb__plugin, body .digi-fb--loaded .digi-fb__plugin').length }));
ok(external.length === 1 && external[0] === 'https://connect.facebook.net/es_MX/sdk.js', 'Al pulsar, se pide el SDK en el idioma del sitio una vez: ' + external.join(', '));
ok(state.fb && state.fb.init.length === 1 && state.fb.init[0].appId === '1234567890' && state.fb.init[0].version === 'v2.10' && state.fb.init[0].xfbml === false, 'FB.init con el App ID, v2.10 y sin xfbml: ' + JSON.stringify(state.fb && state.fb.init));
ok(state.fb.parse.length === 1 && state.fb.parse[0] === 'digitalisimo-facebook-page-0', 'FB.XFBML.parse sólo sobre el widget pulsado: ' + JSON.stringify(state.fb.parse));
ok(/data-href="https:\/\/www\.facebook\.com\/pagina\/"/.test(state.plugin) && /data-tabs="timeline"/.test(state.plugin) && /data-height="500px"/.test(state.plugin) && state.others === 1, 'El plugin de PRO entra en la página con sus data-*; los demás siguen como fachada: ' + state.plugin);
ok(/digi-fb--loaded/.test(state.loaded) && /digi-fb--loaded/.test(state.active), 'El foco pasa al contenido cargado: ' + state.active);
const loadedSize = await page.evaluate(() => { const r = document.querySelector('[data-id="digitalisimo-facebook-page-0"] .digi-fb').getBoundingClientRect(); return Math.round(r.height); });
ok(loadedSize === 500, 'El plugin cargado mantiene el alto (sin salto): ' + loadedSize);

// ── Teclado en otros dos: Intro y Espacio. El SDK no se vuelve a pedir. ─────────────────────────────────────────────
await page.focus('[data-id="digitalisimo-facebook-embed-2"] .digi-fb__load');
await page.keyboard.press('Enter');
await sleep(200);
await page.focus('[data-id="digitalisimo-facebook-embed-0"] .digi-fb__load');
await page.keyboard.press('Space');
await sleep(300);
state = await page.evaluate(() => ({ fb: window.__fb, video: document.querySelector('[data-id="digitalisimo-facebook-embed-2"] .fb-video')?.getAttribute('data-autoplay'), comments: document.querySelector('[data-id="digitalisimo-facebook-comments-1"] .fb-comments')?.getAttribute('data-order-by'), scripts: [...document.scripts].filter(s => /connect\.facebook\.net/.test(s.src)).length }));
ok(external.length === 1 && state.scripts === 1 && state.fb.init.length === 1, 'Varios widgets, un solo SDK y un solo FB.init: ' + JSON.stringify({ external, scripts: state.scripts, init: state.fb.init.length }));
ok(JSON.stringify(state.fb.parse) === JSON.stringify(['digitalisimo-facebook-page-0', 'digitalisimo-facebook-embed-2', 'digitalisimo-facebook-embed-0']), 'Intro y Espacio cargan cada uno su widget: ' + JSON.stringify(state.fb.parse));
ok(state.video === 'true' && state.comments === 'reverse_time', 'Vídeo y comentarios con los data-* de PRO.');
// Un segundo clic en algo ya cargado no hace nada.
await page.click('[data-id="digitalisimo-facebook-page-0"] .digi-fb');
await sleep(200);
ok((await page.evaluate(() => window.__fb.parse.length)) === 3, 'Un clic en un widget ya cargado no repite nada.');
await page.close();

// ── Varios clics antes de que llegue el SDK: un solo pedido y una cola. ────────────────────────────────────────────
({ page, external } = await open());
await page.evaluate(() => {
  document.querySelector('[data-id="digitalisimo-facebook-embed-1"] .digi-fb__load').click();
  document.querySelector('[data-id="digitalisimo-facebook-page-1"] .digi-fb__load').click();
});
await sleep(400);
const queued = await page.evaluate(() => window.__fb);
ok(external.length === 1 && queued.init.length === 1 && queued.parse.length === 2, 'Dos clics seguidos: un pedido, un init y los dos widgets analizados: ' + JSON.stringify({ external, queued }));
await page.close();

ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
await browser.close();
console.log(`OK: fachadas de Facebook en Chrome (${checks} comprobaciones).`);
