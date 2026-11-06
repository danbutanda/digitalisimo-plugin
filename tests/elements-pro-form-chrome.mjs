// Formulario propio (digitalisimo-form, C6, docs/pro/c6.md) en Chrome real (sin interfaz) sobre tests/fixtures/pro-form.html,
// el marcado del servidor que comprueba tests/elements-pro-form.php. Las peticiones a admin-ajax.php se interceptan y se
// responden como el manejador de envíos del constructor:
// - envío con fetch y FormData: la acción y los parámetros de PRO (`action`, `referrer`, `post_id`, `form_id`,
//   `referer_title`, `queried_id`, `form_fields[…]`), una sola petición aunque se pulse dos veces o se pida dos veces;
// - errores del servidor por campo: mensaje con `role=alert` e `id`, `aria-invalid` y `aria-describedby`, mensaje del
//   formulario; se limpian en el siguiente envío; error de red con el texto del servidor;
// - éxito: eventos `digi-form-submit_success` y `digi-form-form_destruct` con los datos, el formulario se vacía, mensaje;
//   redirección (`redirect_url`);
// - pasos: «siguiente» valida el paso, indicadores y región viva al día, foco en el primer campo, Intro avanza, error
//   del servidor vuelve al paso del campo; barra de progreso con su valor;
// - tamaño máximo de un archivo; reinicio en el editor (frontend/element_ready).
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-form-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync, readdirSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const npx = '/home/digitalisimo/.npm/_npx';
const found = existsSync(npx) ? readdirSync(npx).map((d) => `${npx}/${d}/node_modules/puppeteer-core`).find((d) => existsSync(d + '/package.json')) : '';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : found;
if (!existsSync(chrome) || !pptrDir || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome del Formulario (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-form.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
let replies = [];
let posts = [];
async function open({ editor = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', (e) => errors.push(e.message));
  await page.setRequestInterception(true);
  page.on('request', async (req) => {
    if (!req.url().startsWith('https://sitio.test/')) return req.continue();
    posts.push({ url: req.url(), method: req.method(), body: req.postData() || '' });
    const reply = replies.shift() || { success: true, data: { message: 'Listo.', data: {} } };
    await sleep(300);
    if ('network' === reply) return req.abort();
    req.respond({ status: 200, contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': '*' }, body: JSON.stringify(reply) });
  });
  await page.evaluateOnNewDocument((editor) => {
    window.__events = [];
    ['digi-form-submit_success', 'digi-form-form_destruct', 'digi-form-error'].forEach((n) => document.addEventListener(n, (e) => window.__events.push([n, e.detail && e.detail.message])));
    if (editor) window.elementorFrontend = { hooks: { addAction: (n, f) => { (window.__hooks = window.__hooks || {})[n] = f; } } };
  }, editor);
  await page.setViewport({ width: 1280, height: 900 });
  await page.goto(fixture, { waitUntil: 'load' });
  await sleep(100);
  return page;
}

// ── Envío ──────────────────────────────────────────────────────────────────────────────────────────────────────────
let page = await open();
ok(await page.evaluate(() => [...document.querySelectorAll('form[data-digi-form]')].every((f) => f.digiForm)), 'El script arranca cada formulario.');
await page.type('#basico #form-field-name', 'Ana');
replies = [{ success: false, data: { message: 'Hay errores.', errors: { email: 'Este campo es obligatorio.' }, data: [] } }];
posts = [];
await page.evaluate(() => { const f = document.getElementById('basico'); const b = f.querySelector('[type=submit]'); b.click(); b.click(); f.requestSubmit(); });
ok(await page.evaluate(() => document.getElementById('basico').getAttribute('aria-busy') === 'true' && document.querySelector('#basico [type=submit]').disabled && document.getElementById('basico').classList.contains('elementor-form-waiting')), 'Mientras envía: ocupado, botón deshabilitado y clase de espera.');
await sleep(700);
ok(1 === posts.length && 'POST' === posts[0].method && 'https://sitio.test/wp-admin/admin-ajax.php' === posts[0].url, 'Dos clics y un requestSubmit: una sola petición POST a admin-ajax.php.');
const body = posts[0].body;
for (const part of ['name="action"\r\n\r\nelementor_pro_forms_send_form', 'name="referrer"', 'name="post_id"\r\n\r\n77', 'name="form_id"\r\n\r\nf0r1d2', 'name="referer_title"', 'name="queried_id"\r\n\r\n42', 'name="form_fields[name]"\r\n\r\nAna', 'name="form_fields[email]"', 'name="form_fields[message]"']) {
  ok(body.includes(part), `El envío lleva ${part.split('\r\n')[0]} como el de PRO.`);
}
const err = await page.evaluate(() => {
  const e = document.querySelector('#basico #form-field-email'), m = document.getElementById('form-field-email-error');
  return { invalid: e.getAttribute('aria-invalid'), desc: e.getAttribute('aria-describedby'), role: m && m.getAttribute('role'), text: m && m.textContent, cls: m && m.className, group: e.closest('.elementor-field-group').classList.contains('elementor-error'), form: document.querySelector('#basico > .elementor-message-danger').textContent, busy: document.getElementById('basico').hasAttribute('aria-busy'), disabled: document.querySelector('#basico [type=submit]').disabled };
});
ok('true' === err.invalid && 'form-field-email-error' === err.desc && 'alert' === err.role && 'Este campo es obligatorio.' === err.text && /elementor-form-help-inline/.test(err.cls) && err.group, 'Error del campo: role=alert, aria-invalid y aria-describedby: ' + JSON.stringify(err));
ok('Hay errores.' === err.form && !err.busy && !err.disabled, 'Mensaje del formulario y el botón vuelve a estar disponible.');
ok(await page.evaluate(() => window.__events.some((e) => 'digi-form-error' === e[0])), 'Evento de error.');
// Éxito.
await page.type('#basico #form-field-email', 'ana@ejemplo.test');
replies = [{ success: true, data: { message: 'Gracias.', data: { redirect_url: '#gracias' } } }];
posts = [];
await page.click('#basico [type=submit]');
await sleep(700);
const done = await page.evaluate(() => ({ msg: document.querySelector('#basico .elementor-message-success') && document.querySelector('#basico .elementor-message-success').textContent, role: document.querySelector('#basico .elementor-message-success') && document.querySelector('#basico .elementor-message-success').getAttribute('role'), errs: document.querySelectorAll('#basico .elementor-message-danger').length, invalid: document.querySelector('#basico #form-field-email').getAttribute('aria-invalid'), desc: document.querySelector('#basico #form-field-email').getAttribute('aria-describedby'), name: document.querySelector('#basico #form-field-name').value, hash: location.hash, events: window.__events.map((e) => e[0]) }));
ok(1 === posts.length && 'Gracias.' === done.msg && 'alert' === done.role && 0 === done.errs, 'Éxito: un envío, mensaje con role=alert y sin los errores anteriores.');
ok('false' === done.invalid && null === done.desc && '' === done.name, 'El campo deja de estar inválido y el formulario se vacía: ' + JSON.stringify(done));
ok(done.events.includes('digi-form-submit_success') && done.events.includes('digi-form-form_destruct') && '#gracias' === done.hash, 'Eventos de éxito y redirección a redirect_url.');
// Error de red: el texto del servidor del formulario.
replies = ['network'];
await page.type('#basico #form-field-name', 'Red');
await page.click('#basico [type=submit]');
await sleep(800);
ok(await page.evaluate(() => /error del servidor/.test(document.querySelector('#basico > .elementor-message-danger:last-of-type').textContent) && !document.querySelector('#basico [type=submit]').disabled), 'Un error de red muestra el mensaje de error del servidor y libera el botón.');

// ── Pasos ──────────────────────────────────────────────────────────────────────────────────────────────────────────
const stepState = () => page.evaluate(() => ({
  visible: [...document.querySelectorAll('#pasos [data-digi-step]')].map((s) => !s.hidden),
  ind: [...document.querySelectorAll('#pasos .digi-step')].map((s) => s.className.replace('digi-step digi-step--', '')),
  status: document.querySelector('#pasos [data-digi-status]').textContent,
  focus: document.activeElement && document.activeElement.id,
  shown: [...document.querySelectorAll('#pasos [data-digi-step]')].map((s) => getComputedStyle(s).display),
}));
let s = await stepState();
ok('true,false,false' === s.visible.join() && 'on,off,off' === s.ind.join() && 'flex,none,none' === s.shown.join(), 'Al cargar sólo el primer paso, indicador activo el primero: ' + JSON.stringify(s));
await page.click('#pasos [data-digi-step="0"] [data-direction="next"]');
s = await stepState();
ok('true,false,false' === s.visible.join() && 'form-field-nombre' === s.focus, 'El nombre es obligatorio: «Continuar» no avanza con el paso incompleto.');
await page.type('#form-field-nombre', 'Ana');
await page.click('#pasos [data-digi-step="0"] [data-direction="next"]');
s = await stepState();
ok('false,true,false' === s.visible.join() && 'done,on,off' === s.ind.join() && 'Paso 2 de 3: Contacto' === s.status && 'form-field-correo' === s.focus, '«Continuar»: paso 2, indicadores, región viva y foco en su primer campo: ' + JSON.stringify(s));
await page.keyboard.press('Enter');
s = await stepState();
ok('false,false,true' === s.visible.join() && 'done,done,on' === s.ind.join() && 'form-field-msg' === s.focus, 'Intro en un campo avanza al último paso: ' + JSON.stringify(s));
await page.click('#pasos [data-digi-step="2"] [data-direction="previous"]');
s = await stepState();
ok('false,true,false' === s.visible.join() && 'done,on,off' === s.ind.join(), '«Volver» regresa al paso 2.');
// Un campo obligatorio vacío detiene «siguiente».
await page.evaluate(() => { document.getElementById('form-field-correo').required = true; });
await page.click('#pasos [data-digi-step="1"] [data-direction="next"]');
s = await stepState();
ok('false,true,false' === s.visible.join(), 'Un campo obligatorio vacío detiene «siguiente».');
await page.type('#form-field-correo', 'p@ejemplo.test');
await page.click('#pasos [data-digi-step="1"] [data-direction="next"]');
replies = [{ success: false, data: { message: 'Revisa.', errors: { nombre: 'Falta el nombre.' }, data: [] } }];
await page.click('#pasos [type=submit]');
await sleep(700);
s = await stepState();
ok('true,false,false' === s.visible.join() && 'form-field-nombre' === s.focus, 'Un error del servidor en un campo de otro paso vuelve a ese paso: ' + JSON.stringify(s));
replies = [{ success: true, data: { message: 'Hecho.', data: {} } }];
await page.evaluate(() => document.getElementById('pasos').requestSubmit());
await sleep(700);
s = await stepState();
ok('true,false,false' === s.visible.join() && 'on,off,off' === s.ind.join(), 'Tras el éxito vuelve al primer paso.');
// Barra de progreso.
const bar = () => page.evaluate(() => { const b = document.querySelector('#barra .digi-steps__bar'); return { now: b.getAttribute('aria-valuenow'), text: b.getAttribute('aria-valuetext'), meter: b.firstChild.textContent, width: Math.round(b.firstChild.getBoundingClientRect().width / b.getBoundingClientRect().width * 100) }; });
let b = await bar();
ok('33' === b.now && '33%' === b.meter && b.width >= 32 && b.width <= 34, 'Barra al 33 %: ' + JSON.stringify(b));
await page.type('#barra #form-field-nombre', 'Ana');
await page.click('#barra [data-digi-step="0"] [data-direction="next"]');
b = await bar();
ok('66' === b.now && 'Paso 2 de 3: Contacto' === b.text && '66%' === b.meter, 'Barra al 66 % con el paso anunciado: ' + JSON.stringify(b));

// ── Archivo demasiado grande y editor ─────────────────────────────────────────────────────────────────────────────
await page.evaluate(() => {
  const g = document.querySelector('#basico .elementor-field-group-message');
  g.insertAdjacentHTML('beforeend', '<input type="file" id="form-field-cv" name="form_fields[cv]" data-maxsize="1" data-maxsize-message="Máximo 1 MB.">');
  const i = document.getElementById('form-field-cv'), dt = new DataTransfer();
  dt.items.add(new File([new Uint8Array(1100000)], 'cv.pdf'));
  i.files = dt.files;
  i.dispatchEvent(new Event('change', { bubbles: true }));
});
ok(await page.evaluate(() => { const m = document.getElementById('form-field-cv-error'); return m && 'Máximo 1 MB.' === m.textContent && 'true' === document.getElementById('form-field-cv').getAttribute('aria-invalid'); }), 'Un archivo mayor que el máximo muestra el aviso del campo.');
await page.close();
page = await open({ editor: true });
ok(await page.evaluate(() => {
  const w = document.querySelector('[data-id="basico"]'), f = w.querySelector('form'), c = f.cloneNode(true);
  f.replaceWith(c);
  window.__hooks['frontend/element_ready/digitalisimo-form.default']([w]);
  return !!c.digiForm;
}), 'En el editor, element_ready vuelve a arrancar el formulario repintado.');
await page.close();
await browser.close();
ok(!errors.length, 'Sin errores de JavaScript: ' + errors.join(' · '));
console.log(`DIGITALÍSIMO Elements: Formulario en Chrome (${checks} comprobaciones) validado.`);
