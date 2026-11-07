// Cuenta regresiva e Indicador de progreso (frente D de C2, docs/pro/c2.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-time.html, que es el marcado del servidor (lo comprueba tests/elements-pro-time.php), con el reloj
// del navegador fijado (Date y Date.now):
// - cuenta regresiva: dígitos al día con dos cifras, avance segundo a segundo, cero al vencer; acciones al terminar
//   (ocultar y mostrar el mensaje) una sola vez con el evento `digi-countdown-expire` y, si la página tiene jQuery, el
//   `countdown_expire` de PRO antes de las acciones; redirección sólo a http/https;
//   temporizador perpetuo con las claves de PRO en el navegador (se conserva al recargar, se reinicia si cambia el
//   intervalo); un elemento quitado deja de contar; reinicio en el editor (frontend/element_ready);
// - indicador de progreso: barra y círculo según la página (con «Scroll Snap» en el body), un selector y el contenido
//   de la entrada, con las fórmulas de PRO; porcentaje que sólo se ve si cabe; dirección RTL de la barra y sentido del
//   círculo; un selector no válido o ausente lo deja a cero sin errores.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-time-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de la cuenta regresiva y el indicador de progreso (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-time.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));
const T0 = Date.UTC(2030, 0, 1); // 1893456000 s

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
async function open({ query = '', now = T0, editor = false, context = null, jq = false } = {}) {
  const page = await (context || browser).newPage();
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error' && !/ERR_|net::/.test(m.text())) errors.push(m.text()); });
  await page.setViewport({ width: 1280, height: 900 });
  await page.evaluateOnNewDocument((now, editor, jq) => {
    window.__now = now;
    const D = Date;
    class F extends D { constructor(...a) { super(...(a.length ? a : [window.__now])); } static now() { return window.__now; } }
    window.Date = F;
    window.__expired = [];
    document.addEventListener('digi-countdown-expire', e => window.__expired.push(e.target.getAttribute('data-id')));
    if (editor) window.elementorFrontend = { hooks: { addAction: (n, f) => { (window.__hooks = window.__hooks || {})[n] = f; } } };
    // jQuery mínimo de la página: anota cada trigger (nombre, elemento, datos y estado en ese momento).
    if (jq) { window.__jq = []; window.jQuery = n => ({ 0: n, length: 1, trigger: (name, data) => window.__jq.push({ name, id: n.getAttribute('data-id'), data: !!data && data[0] === n && data.length === 1, own: window.__expired.slice(), hidden: n.querySelector('.digi-countdown').hidden }) }); }
  }, now, editor, jq);
  await page.goto(fixture + query, { waitUntil: 'load' });
  await sleep(150);
  return page;
}
const digits = (page, id) => page.evaluate(id => [...document.querySelectorAll(`[data-id="${id}"] .digi-countdown__digits`)].map(s => s.textContent).join(':'), id);

// ── Cuenta regresiva ───────────────────────────────────────────────────────────────────────────────────────────────
let page = await open();
ok(await digits(page, 'cd1') === '03:10:05:06', 'Fecha límite: 3 días, 10 h, 5 min y 6 s desde el reloj fijado (zona del sitio aplicada en el servidor): ' + await digits(page, 'cd1'));
await page.evaluate(() => { window.__now += 1000; });
await sleep(1100);
ok(await digits(page, 'cd1') === '03:10:05:05', 'Avanza segundo a segundo: ' + await digits(page, 'cd1'));
ok(await page.evaluate(() => document.querySelector('[data-id="cd1"] .digi-countdown').getAttribute('role') === 'timer' && !document.querySelector('[data-id="cd1"] .digi-countdown').hidden), 'La cuenta es un temporizador (región viva apagada) y sigue a la vista.');
const cd1 = await page.evaluate(() => { const i = document.querySelector('[data-id="cd1"] .digi-countdown__item'), r = i.getBoundingClientRect(), cs = getComputedStyle(i), d = getComputedStyle(i.querySelector('.digi-countdown__digits')); return { w: r.width, color: cs.color, size: d.fontSize, display: d.display, flex: getComputedStyle(i.parentNode).display }; });
ok(cd1.flex === 'flex' && cd1.display === 'block' && cd1.size === '69px' && cd1.color === 'rgb(255, 255, 255)' && cd1.w > 250, 'Disposición en bloque: cuatro cajas iguales en fila, dígitos de 69 px en blanco: ' + JSON.stringify(cd1));
ok(await digits(page, 'cd2') === '00:00:00:00', 'Una cuenta vencida marca cero.');
ok(await page.evaluate(() => { const r = document.querySelector('[data-id="cd2"] .digi-countdown'), m = document.querySelector('[data-id="cd2"] .digi-countdown__message'); return r.hidden && getComputedStyle(r).display === 'none' && !m.hidden && getComputedStyle(m).display === 'block' && m.textContent === 'Terminó'; }), 'Al vencer: la cuenta se oculta (también en bloque, que es flex) y aparece el mensaje.');
await page.evaluate(() => { window.__now += 5000; });
await sleep(2100);
ok(JSON.stringify(await page.evaluate(() => window.__expired)) === '["cd2"]', 'Las acciones y el evento digi-countdown-expire, una sola vez (PRO las repetía cada segundo): ' + JSON.stringify(await page.evaluate(() => window.__expired)));
ok(await page.evaluate(() => typeof window.jQuery === 'undefined'), 'Sin jQuery en la página, la cuenta funciona igual (no lo pide ni lo carga).');
// Con jQuery en la página, también el `countdown_expire` de PRO sobre el envoltorio, con el envoltorio como dato, una
// sola vez, después del evento propio y antes de las acciones (como `runActions` de PRO).
const pj = await open({ jq: true });
await pj.evaluate(() => { window.__now += 5000; });
await sleep(2100);
const jqEvents = await pj.evaluate(() => window.__jq);
ok(JSON.stringify(jqEvents) === JSON.stringify([{ name: 'countdown_expire', id: 'cd2', data: true, own: ['cd2'], hidden: false }]), 'Con jQuery, el countdown_expire de PRO sobre el envoltorio una vez, antes de las acciones: ' + JSON.stringify(jqEvents));
await pj.close();
// Temporizador perpetuo: claves de PRO, se conserva al recargar y se reinicia al cambiar el intervalo.
ok(await digits(page, 'ev1') === '00:02:29:54', 'Perpetuo de 2 h 30 min, seis segundos después: ' + await digits(page, 'ev1'));
const stored = await page.evaluate(() => [localStorage.getItem('ev1-evergreen_due_date'), localStorage.getItem('ev1-evergreen_interval')]);
ok(stored[0] === String(T0 + 9000e3) && stored[1] === '9000', 'Guarda su vencimiento con las claves de PRO (<id>-evergreen_due_date / _interval): ' + JSON.stringify(stored));
ok(await page.evaluate(() => getComputedStyle(document.querySelector('[data-id="ev1"] .digi-countdown__item')).display === 'inline-block' && getComputedStyle(document.querySelector('[data-id="ev1"]')).textAlign === 'center'), 'Disposición en línea, centrada.');
// Un elemento quitado deja de contar.
const before = await page.evaluate(() => { const r = document.querySelector('[data-id="cd1"] .digi-countdown'); window.__gone = r; r.remove(); window.__now += 3000; return r.querySelector('.digi-countdown__seconds').textContent; });
await sleep(1200);
ok(await page.evaluate(() => window.__gone.querySelector('.digi-countdown__seconds').textContent) === before, 'Un elemento quitado del documento deja de contar.');
const context = await browser.createBrowserContext();
let p2 = await open({ now: T0 + 3600e3, context });
await p2.evaluate(() => { localStorage.setItem('ev1-evergreen_due_date', String(Date.UTC(2030, 0, 1) + 9000e3)); localStorage.setItem('ev1-evergreen_interval', '9000'); });
await p2.close();
p2 = await open({ now: T0 + 3600e3, context });
ok(await digits(p2, 'ev1') === '00:01:30:00', 'Al volver una hora después conserva su vencimiento: ' + await digits(p2, 'ev1'));
await p2.evaluate(() => localStorage.setItem('ev1-evergreen_interval', '60'));
await p2.close();
p2 = await open({ now: T0 + 3600e3, context });
ok(await digits(p2, 'ev1') === '00:02:30:00', 'Si el intervalo cambió, empieza de nuevo: ' + await digits(p2, 'ev1'));
await p2.close();
await context.close();
// Redirección (sólo a http/https): la petición al destino se intercepta.
const nav = [];
const p3 = await browser.newPage();
await p3.setRequestInterception(true);
p3.on('request', r => { if (r.url().startsWith('https://destino.test/')) { nav.push(r.url()); r.abort(); } else r.continue(); });
await p3.evaluateOnNewDocument(now => { const D = Date; class F extends D { constructor(...a) { super(...(a.length ? a : [now])); } static now() { return now; } } window.Date = F; }, T0);
await p3.goto(fixture + '?redirect', { waitUntil: 'load' }).catch(() => {});
await sleep(800);
ok(nav.length === 1 && nav[0] === 'https://destino.test/fin', 'Al vencer redirige una vez a la URL de destino: ' + JSON.stringify(nav));
await p3.close();
// Editor: frontend/element_ready vuelve a iniciar la cuenta del elemento pintado otra vez.
const p4 = await open({ editor: true });
ok(await p4.evaluate(() => typeof window.__hooks['frontend/element_ready/digitalisimo-countdown.default'] === 'function' && typeof window.__hooks['frontend/element_ready/digitalisimo-progress-tracker.default'] === 'function'), 'Los dos scripts se enganchan a frontend/element_ready en el editor.');
await p4.evaluate(() => {
  const host = document.querySelector('[data-id="cd1"]'), box = host.querySelector('.elementor-widget-container');
  box.innerHTML = box.innerHTML.replace(/digits digi-countdown__(\w+)">\d+/g, 'digits digi-countdown__$1">--');
  window.__hooks['frontend/element_ready/digitalisimo-countdown.default']([host]);
});
ok(await digits(p4, 'cd1') === '03:10:05:06', 'Tras pintarse de nuevo en el editor, la cuenta vuelve a contar: ' + await digits(p4, 'cd1'));
await p4.close();

// ── Indicador de progreso ──────────────────────────────────────────────────────────────────────────────────────────
const tracker = (pg, id) => pg.evaluate(id => {
  const r = document.querySelector(`[data-id="${id}"] .digi-progress-tracker`), bar = r.querySelector('.digi-progress-tracker__progress'), txt = r.querySelector('.digi-progress-tracker__percentage');
  return { on: r.classList.contains('digi-progress-tracker--on'), display: getComputedStyle(bar).display, width: bar.style.width, text: txt.textContent, off: txt.classList.contains('digi-progress-tracker__percentage--off'), offset: bar.style.strokeDashoffset, dash: bar.style.strokeDasharray, dir: getComputedStyle(r).direction };
}, id);
const scroll = async (pg, y, el = 'window') => { await pg.evaluate((y, el) => (el === 'body' ? document.body : window).scrollTo(0, y), y, el); await sleep(120); };
page = await open();
let s = await tracker(page, 'pt1');
ok(!s.on && s.display === 'none' && s.text === '', 'Arriba del todo la barra no muestra avance (como PRO, por debajo de 1 %).');
const max = await page.evaluate(() => document.body.getBoundingClientRect().height - innerHeight);
await scroll(page, max / 2);
s = await tracker(page, 'pt1');
ok(s.on && s.display === 'flex' && Math.abs(parseFloat(s.width) - 50) < 0.6 && s.text === '50%' && !s.off, 'A mitad de la página, 50 % y el porcentaje a la vista: ' + JSON.stringify(s));
const pt5 = await tracker(page, 'pt5');
ok(pt5.dir === 'rtl' && pt5.on && await page.evaluate(() => { const r = document.querySelector('[data-id="pt5"] .digi-progress-tracker').getBoundingClientRect(), b = document.querySelector('[data-id="pt5"] .digi-progress-tracker__progress').getBoundingClientRect(); return Math.abs(b.right - r.right) < 1 && b.left > r.left + 100; }), 'Con dirección «derecha» la barra crece desde la derecha.');
await scroll(page, max / 2 + 2);
const pt6 = await tracker(page, 'pt6');
ok(pt6.on && pt6.off, 'Una barra demasiado corta para el texto lo deja transparente (como PRO: la primera vez lo escribe, después lo mide): ' + JSON.stringify(pt6));
const pt7 = await tracker(page, 'pt7');
const circ = await page.evaluate(() => document.querySelector('[data-id="pt7"] .digi-progress-tracker__progress').r.baseVal.value * 2 * Math.PI);
ok(pt7.on && Math.abs(parseFloat(pt7.offset) - circ / 2) < 1 && pt7.text === '50%', 'Círculo a mitad: la mitad de la circunferencia, en el sentido por defecto: ' + JSON.stringify(pt7));
await scroll(page, max + 50);
s = await tracker(page, 'pt1');
ok(s.width === '100%' && s.text === '100%', 'Al final de la página, 100 %.');
// Seguimiento de un selector: desde que el borde superior entra por abajo hasta que el inferior llega abajo.
const target = await page.evaluate(() => { const r = document.getElementById('objetivo').getBoundingClientRect(); return { top: r.top + scrollY, h: r.height }; });
await scroll(page, target.top - 900 - 10);
ok(!(await tracker(page, 'pt2')).on, 'Antes de que el objetivo asome, el círculo está vacío.');
await scroll(page, target.top - 900 + target.h / 4);
s = await tracker(page, 'pt2');
const circ2 = await page.evaluate(() => document.querySelector('[data-id="pt2"] .digi-progress-tracker__progress').r.baseVal.value * 2 * Math.PI);
ok(s.on && s.text === '25%' && Math.abs(parseFloat(s.offset) + circ2 * 0.75) < 1 && parseFloat(s.dash) > 0, 'Selector a un cuarto: 25 % y, con dirección «izquierda», el desplazamiento negativo como PRO: ' + JSON.stringify(s));
await scroll(page, target.top + target.h - 900 + 5);
ok((await tracker(page, 'pt2')).text === '100%', 'Con el borde inferior del objetivo abajo, 100 %.');
ok(!(await tracker(page, 'pt3')).on && !(await tracker(page, 'pt4')).on, 'Un selector no válido y el contenido de la entrada ausente dejan el indicador a cero, sin errores.');
await page.setViewport({ width: 600, height: 900 });
await sleep(300);
ok((await tracker(page, 'pt7')).on, 'Al cambiar el tamaño se vuelve a medir.');
await page.close();
// «Scroll Snap»: la página se desplaza dentro del body.
page = await open({ query: '?snap' });
ok(await page.evaluate(() => document.documentElement.className === 'snap' && document.body.scrollHeight > document.body.clientHeight), 'Con «Scroll Snap» el body es el que se desplaza.');
const bmax = await page.evaluate(() => document.body.scrollHeight - document.body.clientHeight);
await scroll(page, bmax / 4, 'body');
s = await tracker(page, 'pt1');
ok(s.on && Math.abs(parseFloat(s.width) - 25) < 0.6, 'Con «Scroll Snap», el avance del desplazamiento del body (sin envolver la página): ' + JSON.stringify(s));
await page.close();

ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
await browser.close();
console.log(`DIGITALÍSIMO Elements: Cuenta regresiva e Indicador de progreso en Chrome validados (${checks} comprobaciones).`);
