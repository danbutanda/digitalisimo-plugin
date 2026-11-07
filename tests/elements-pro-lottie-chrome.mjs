// Animación Lottie (digitalisimo-lottie, C2 frente C de docs/pro/c2.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-lottie.html, servida por HTTP desde la raíz del repositorio (la librería pide el JSON con XHR), con
// el marcado del widget (lo comprueba tests/elements-pro-lottie.php):
// - la librería local no está en la carga inicial: la inserta el script una sola vez, y sólo si hay un widget a una
//   pantalla de distancia; un widget lejano no crea su animación hasta acercarse, y con «Carga diferida» a 200 px;
// - disparadores: sin disparador se reproduce y termina en el último fotograma (o en el del tramo de inicio y fin); «Al
//   pulsar» espera al clic y responde a Intro y Espacio; «Al pasar el cursor» con «Invertir» vuelve al quitarlo; «Al
//   entrar en la pantalla» y «Con el desplazamiento» (el fotograma sigue al desplazamiento);
// - la espera antes del enlace: el clic no navega y abre la dirección después del tiempo elegido;
// - movimiento reducido: cada animación queda fija en su estado final y nada se reproduce;
// - sin peticiones a otros orígenes ni errores.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 180 node tests/elements-pro-lottie-chrome.mjs
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { extname, join, normalize } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la Animación Lottie (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const root = fileURLToPath(new URL('..', import.meta.url));
const types = { '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript', '.json': 'application/json' };
const requests = [];
const server = createServer((req, res) => {
  const path = decodeURIComponent(new URL(req.url, 'http://x').pathname);
  requests.push(path);
  if ('/favicon.ico' === path) {
    res.writeHead(204).end();
    return;
  }
  if ('/far' === path) {
    // La misma página sin los widgets de arriba: el primero queda a más de una pantalla.
    const page = readFileSync(join(root, 'tests/fixtures/pro-lottie.html'), 'utf8').replace(/<body>[\s\S]*?(<div style="height:3000px" id="spacer">)/, '<body>\n$1');
    res.writeHead(200, { 'content-type': 'text/html' }).end(page);
    return;
  }
  if ('/motion' === path) {
    // Lo convertido de PRO (`motion`, corrección de fidelidad de C2): «Al pulsar» con movimiento reducido espera al clic.
    const page = readFileSync(join(root, 'tests/fixtures/pro-lottie.html'), 'utf8').replace(/(data-id="click"[^\n]*?&quot;trigger&quot;:&quot;on_click&quot;)/, '$1,&quot;motion&quot;:true');
    res.writeHead(200, { 'content-type': 'text/html' }).end(page);
    return;
  }
  const file = normalize(join(root, path === '/' ? 'tests/fixtures/pro-lottie.html' : path));
  if (!file.startsWith(root) || !existsSync(file) || !statSync(file).isFile()) {
    res.writeHead(404).end();
    return;
  }
  res.writeHead(200, { 'content-type': types[extname(file)] || 'application/octet-stream' }).end(readFileSync(file));
});
await new Promise((r) => server.listen(0, '127.0.0.1', r));
const base = `http://127.0.0.1:${server.address().port}`;

let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const libHits = () => requests.filter((p) => p.endsWith('/assets/lib/lottie/lottie.min.js')).length;

const browser = await puppeteer.launch({ executablePath: chrome, headless: 'new', args: ['--no-sandbox', '--disable-gpu'] });
try {
  // Estado de una animación por su nombre: fotograma actual, en pausa, y si tiene SVG.
  const state = (page, name) => page.evaluate((n) => {
    const a = window.lottie && window.lottie.getRegisteredAnimations().find((x) => x.name === 'digi-lottie-' + n);
    return a ? { frame: Math.round(a.currentFrame + a.firstFrame), total: a.animationData.op - a.animationData.ip, paused: a.isPaused, svg: !!document.querySelector(`[data-id="${n}"] svg`) } : null;
  }, name);
  const until = async (page, fn, ms = 6000) => {
    const end = Date.now() + ms;
    for (;;) {
      const v = await fn();
      if (v || Date.now() > end) return v;
      await sleep(100);
    }
  };

  // 1. Carga y disparadores.
  const page = await browser.newPage();
  const errors = [];
  const foreign = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (m) => { if ('error' === m.type()) errors.push(m.text()); });
  page.on('request', (r) => { if (!r.url().startsWith(base) && !r.url().startsWith('data:')) foreign.push(r.url()); });
  await page.setViewport({ width: 1000, height: 800 });
  await page.evaluateOnNewDocument(() => { window.__opened = []; window.open = (u, t) => { window.__opened.push([u, t]); }; });
  await page.goto(base + '/', { waitUntil: 'load' });
  ok(!(await page.evaluate(() => [...document.scripts].some((s) => s.src.includes('lottie.min.js') && !s.async))), 'La librería no está en el HTML: la inserta el script.');
  ok(await until(page, () => page.evaluate(() => !!window.lottie)), 'Con un widget en pantalla, la librería se carga.');
  ok(1 === libHits(), 'La librería se pide una sola vez para varios widgets: ' + libHits());
  ok((await until(page, async () => { const s = await state(page, 'none'); return s && s.svg && s.paused && s.frame >= s.total - 1; })), 'Sin disparador: se reproduce y queda en el último fotograma.');
  const range = await until(page, async () => { const s = await state(page, 'range'); return s && s.paused && s.frame > 0 ? s : null; });
  ok(range && Math.abs(range.frame - range.total * 0.75) <= 1, 'Tramo de inicio y fin: termina en el 75 % (' + JSON.stringify(range) + ').');
  ok(null === (await state(page, 'viewport')) && null === (await state(page, 'lazy')) && null === (await state(page, 'scroll')), 'Los widgets lejanos aún no crean su animación.');
  const click0 = await until(page, () => state(page, 'click'));
  ok(click0 && click0.svg && 0 === click0.frame && click0.paused, '«Al pulsar»: espera en el primer fotograma.');
  await page.click('[data-id="click"] .digi-lottie__container');
  await sleep(300);
  let s = await state(page, 'click');
  ok(!s.paused && s.frame > 0, '«Al pulsar»: el clic reproduce.');
  await until(page, async () => (await state(page, 'click')).paused, 5000);
  ok(await page.$eval('[data-id="click"] .digi-lottie__container', (el) => 'button' === el.getAttribute('role') && '0' === el.getAttribute('tabindex')), 'El contenedor de «Al pulsar» es un botón enfocable.');
  await page.evaluate(() => { const a = window.lottie.getRegisteredAnimations().find((x) => x.name === 'digi-lottie-click'); a.goToAndStop(0, true); });
  await page.focus('[data-id="click"] .digi-lottie__container');
  await page.keyboard.press('Enter');
  await sleep(250);
  ok(!(await state(page, 'click')).paused, 'Intro reproduce la animación.');
  await until(page, async () => (await state(page, 'click')).paused, 5000);
  await page.evaluate(() => { const a = window.lottie.getRegisteredAnimations().find((x) => x.name === 'digi-lottie-click'); a.goToAndStop(0, true); });
  await page.keyboard.press('Space');
  await sleep(250);
  ok(!(await state(page, 'click')).paused && 0 === (await page.evaluate(() => window.scrollY)), 'Espacio reproduce la animación sin desplazar la página.');
  // Cursor con «Invertir».
  await page.hover('[data-id="hover"] .digi-lottie__container');
  await sleep(1200);
  const hovered = await state(page, 'hover');
  ok(hovered.frame > 10, '«Al pasar el cursor»: avanza (' + hovered.frame + ').');
  await page.mouse.move(990, 790);
  await sleep(300);
  const back = await state(page, 'hover');
  ok(!back.paused && back.frame < hovered.frame + 3, '«Invertir»: al quitar el cursor vuelve hacia atrás.');
  await until(page, async () => { const x = await state(page, 'hover'); return x.paused && x.frame <= 1; }, 5000);
  ok((await state(page, 'hover')).frame <= 1, '«Invertir»: termina en el primer fotograma.');
  // Espera antes del enlace.
  const href = page.url();
  await page.click('[data-id="link"] .digi-lottie__container');
  await sleep(100);
  ok(page.url() === href && 0 === (await page.evaluate(() => window.__opened.length)), 'Con espera, el clic no navega en el momento.');
  await sleep(400);
  ok('[["https://sitio.test/destino","_blank"]]' === JSON.stringify(await page.evaluate(() => window.__opened)), 'Después de la espera abre el enlace (externo en otra pestaña).');
  // Al entrar en la pantalla, carga diferida y desplazamiento.
  await page.evaluate(() => document.querySelector('[data-id="viewport"]').scrollIntoView({ block: 'center' }));
  ok(await until(page, async () => { const x = await state(page, 'viewport'); return x && x.frame > 0; }), '«Al entrar en la pantalla»: se crea y se reproduce al llegar.');
  ok(null === (await state(page, 'lazy')), '«Carga diferida»: a más de 200 px todavía no se crea.');
  await page.evaluate(() => { const r = document.querySelector('[data-id="lazy"]').getBoundingClientRect(); window.scrollBy(0, r.top - innerHeight - 150); });
  ok(await until(page, () => state(page, 'lazy')), '«Carga diferida»: a 150 px se crea.');
  ok(1 === libHits(), 'La librería sigue pedida una sola vez.');
  await page.evaluate(() => { const r = document.querySelector('[data-id="scroll"]').getBoundingClientRect(); window.scrollBy(0, r.top - innerHeight + 50); });
  await until(page, () => state(page, 'scroll'));
  await sleep(300);
  const s1 = await state(page, 'scroll');
  await page.evaluate(() => window.scrollBy(0, 400));
  await sleep(300);
  const s2 = await state(page, 'scroll');
  await page.evaluate(() => window.scrollBy(0, 400));
  await sleep(300);
  const s3 = await state(page, 'scroll');
  ok(s1 && s1.paused && s2.frame > s1.frame && s3.frame > s2.frame, '«Con el desplazamiento»: el fotograma sigue al desplazamiento (' + [s1.frame, s2.frame, s3.frame].join(' → ') + ').');
  await page.evaluate(() => window.scrollBy(0, -600));
  await sleep(300);
  ok((await state(page, 'scroll')).frame < s3.frame, '«Con el desplazamiento»: hacia arriba retrocede.');
  ok(!errors.length, 'Sin errores: ' + errors.join(' | '));
  ok(!foreign.length, 'Ninguna petición a otro origen: ' + foreign.join(', '));
  await page.close();

  // 2. Sin widgets cerca de la pantalla: ninguna petición de la librería hasta acercarse.
  const before = libHits();
  const far = await browser.newPage();
  await far.setViewport({ width: 1000, height: 800 });
  await far.goto(base + '/far', { waitUntil: 'load' });
  await sleep(800);
  ok(before === libHits() && !(await far.evaluate(() => !!window.lottie)) && 3 === (await far.$$('.digi-lottie__container')).length, 'Sin un widget a una pantalla de distancia, la librería no se pide.');
  await far.evaluate(() => window.scrollBy(0, 2600));
  ok(await until(far, () => far.evaluate(() => !!window.lottie)) && before + 1 === libHits(), 'Al acercarse el primero (a menos de una pantalla), se pide.');
  await far.close();

  // 3. Movimiento reducido: estado final, fijo.
  const calm = await browser.newPage();
  await calm.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await calm.setViewport({ width: 1000, height: 800 });
  await calm.goto(base + '/', { waitUntil: 'load' });
  await until(calm, async () => { const x = await state(calm, 'click'); return x && x.svg; });
  await sleep(300);
  for (const name of ['none', 'click', 'hover', 'range']) {
    const x = await state(calm, name);
    const end = 'range' === name ? Math.round(x.total * 0.75) : x.total - 1;
    ok(x.paused && Math.abs(x.frame - end) <= 1, `Movimiento reducido: «${name}» queda fijo en su estado final (${x.frame}/${end}).`);
  }
  await calm.click('[data-id="click"] .digi-lottie__container');
  await sleep(200);
  ok((await state(calm, 'click')).paused, 'Movimiento reducido: el clic no reproduce.');
  await calm.close();

  // 4. Movimiento reducido en lo convertido de PRO (`motion`): se anima como en PRO.
  const moved = await browser.newPage();
  await moved.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await moved.setViewport({ width: 1000, height: 800 });
  await moved.goto(base + '/motion', { waitUntil: 'load' });
  await until(moved, async () => { const x = await state(moved, 'click'); return x && x.svg; });
  await sleep(300);
  let mx = await state(moved, 'click');
  ok(mx.paused && mx.frame <= 1, `Con motion, «Al pulsar» espera al clic en su primer fotograma, como PRO (${mx.frame}/${mx.total}).`);
  ok((await state(moved, 'none')).frame >= (await state(moved, 'none')).total - 1, 'Sin motion, lo demás sigue fijo en su estado final.');
  await moved.click('[data-id="click"] .digi-lottie__container');
  await sleep(250);
  mx = await state(moved, 'click');
  ok(!mx.paused || mx.frame > 1, `Con motion, el clic reproduce con movimiento reducido (${mx.frame}).`);
  await moved.close();
} finally {
  await browser.close();
  server.close();
}
console.log(`DIGITALÍSIMO Elements: Animación Lottie en Chrome (${checks} comprobaciones) validada.`);
