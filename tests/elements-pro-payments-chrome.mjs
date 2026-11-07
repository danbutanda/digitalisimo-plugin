// Botones de PayPal y Stripe propios (C9, docs/pro/c9.md) en Chrome real (sin interfaz) sobre
// tests/fixtures/pro-payments.html, que es el marcado del servidor (lo comprueba tests/elements-pro-payments.php), con
// todas las peticiones interceptadas (ninguna sale de la máquina):
// - PayPal: con datos que faltan no se envía nada y se muestran los mensajes (role="alert"); con datos, el formulario se
//   envía por POST a PayPal con sus campos ocultos; con el teclado (Tab e Intro) también;
// - Stripe: un clic envía a admin-ajax.php la acción `submit_stripe_form` con `data[action]`, `data[postId]` (del
//   documento), `data[widgetId]`, `data[pageUrl]` y `data[nonce]`, como el script de PRO, sin ninguna clave; con 200 abre
//   la dirección de la sesión con el destino elegido; con 401/403 o con otro código pinta el mensaje del servicio como
//   texto (nunca HTML) o el personalizado; un fallo de red pinta el mensaje global; con datos que faltan no hay petición;
//   en el editor no hace nada; el botón queda ocupado mientras espera;
// - foco visible de 2 px a 2 px y objetivo de 24 × 24 px; sin errores de JavaScript.
// Sin Chrome o sin puppeteer-core, se salta con un aviso. CHROME_PATH y PUPPETEER_PATH cambian las rutas.
// Uso: timeout 120 node tests/elements-pro-payments-chrome.mjs
import assert from 'node:assert/strict';
import { existsSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
if (!existsSync(chrome) || !existsSync(pptrDir + '/package.json')) {
  console.log(`AVISO: se salta la prueba en Chrome de los botones de pago (sin ${existsSync(chrome) ? 'puppeteer-core' : 'Chrome'}).`);
  process.exit(0);
}
const { default: puppeteer } = await import(pathToFileURL(pptrDir + '/lib/puppeteer/puppeteer-core.js').href);
const fixture = new URL('./fixtures/pro-payments.html', import.meta.url).href;
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };
const sleep = ms => new Promise(r => setTimeout(r, ms));

const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--allow-file-access-from-files', '--disable-gpu'] });
const errors = [];
let requests = [];
let reply = () => ({ status: 200, body: '{}' });

async function open({ editor = false } = {}) {
  const page = await browser.newPage();
  page.on('pageerror', e => errors.push(e.message));
  await page.setViewport({ width: 1280, height: 900 });
  await page.setRequestInterception(true);
  page.on('request', req => {
    const url = req.url();
    if (url.startsWith('file:')) return req.continue();
    requests.push({ url, method: req.method(), body: req.postData() || '' });
    if (url.startsWith('https://sitio.test/wp-admin/admin-ajax.php')) {
      const r = reply(req);
      if (r.abort) return req.abort('failed');
      return req.respond({ status: 200, contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': '*' }, body: r.body });
    }
    return req.respond({ status: 200, contentType: 'text/html', body: '<!doctype html><title>fuera</title>' });
  });
  await page.evaluateOnNewDocument(editor => {
    window.__opened = [];
    window.open = (u, t) => { window.__opened.push([u, t]); return null; };
    if (editor) document.addEventListener('DOMContentLoaded', () => document.body.classList.add('elementor-editor-active'));
  }, editor);
  await page.goto(fixture, { waitUntil: 'load' });
  await sleep(100);
  return page;
}
const params = body => Object.fromEntries(new URLSearchParams(body));
// `lead`: el blanco antes del texto (los mensajes del servidor lo llevan, como la plantilla de PRO; los del servicio, no).
const messages = (page, id) => page.evaluate(id => [...document.querySelectorAll(`[data-id="${id}"] .digi-pay__message`)].filter(m => !m.hidden).map(m => ({ text: m.textContent.trim(), lead: /^\s/.test(m.textContent), role: m.getAttribute('role'), html: m.innerHTML, cls: m.className })), id);

// ── PayPal ─────────────────────────────────────────────────────────────────────────────────────────────────────────
let page = await open();
requests = [];
await page.click('[data-id="pp2"] button');
await sleep(300);
let shown = await messages(page, 'pp2');
ok(requests.length === 0 && page.url() === fixture, 'PayPal sin cuenta: no se envía nada.');
ok(shown.length === 1 && shown[0].role === 'alert' && /No hay ningún método de pago conectado/.test(shown[0].text) && /digi-pay__message--payment/.test(shown[0].cls), 'PayPal sin cuenta: se muestra el mensaje de método de pago: ' + JSON.stringify(shown));
const box = await page.evaluate(() => { const b = document.querySelector('[data-id="pp1"] button').getBoundingClientRect(); return { w: b.width, h: b.height }; });
ok(box.w >= 24 && box.h >= 24, 'El botón mide al menos 24 × 24 px: ' + JSON.stringify(box));
await page.close();
page = await open();
requests = [];
await page.keyboard.press('Tab');
const focus = await page.evaluate(() => { const a = document.activeElement, cs = getComputedStyle(a); return { id: a.closest('[data-id]')?.getAttribute('data-id'), tag: a.tagName, outline: cs.outlineWidth + ' ' + cs.outlineStyle, offset: cs.outlineOffset }; });
ok(focus.id === 'pp1' && focus.tag === 'BUTTON' && focus.outline === '2px solid' && focus.offset === '2px', 'Con Tab el foco llega al botón de PayPal, con contorno de 2 px a 2 px: ' + JSON.stringify(focus));
await Promise.all([page.waitForNavigation({ waitUntil: 'load' }).catch(() => null), page.keyboard.press('Enter')]);
await sleep(200);
const post = requests.find(r => r.url === 'https://www.paypal.com/cgi-bin/webscr');
ok(post && post.method === 'POST', 'PayPal: Intro en el botón envía el formulario por POST a PayPal.');
const fields = params(post.body);
ok(fields.cmd === '_xclick' && fields.business === 'ventas@ejemplo.test' && fields.item_name === 'Camiseta' && fields.amount === '25' && fields.currency_code === 'USD' && fields.quantity === '1' && fields.lc === 'US' && fields.no_note === '1', 'PayPal: los campos del formulario de PRO: ' + post.body);
await page.close();

// ── Stripe ─────────────────────────────────────────────────────────────────────────────────────────────────────────
page = await open();
requests = [];
let release;
const gate = new Promise(r => { release = r; });
reply = () => ({ status: 200, body: JSON.stringify({ response: { code: 200 }, body: JSON.stringify({ url: 'https://checkout.stripe.test/c/pay/cs_test_1' }) }) });
// La primera respuesta se retiene para ver el botón ocupado.
page.removeAllListeners('request');
page.on('request', async req => {
  const url = req.url();
  if (url.startsWith('file:')) return req.continue();
  requests.push({ url, method: req.method(), body: req.postData() || '' });
  await gate;
  const r = reply(req);
  return req.respond({ status: 200, contentType: 'application/json', headers: { 'Access-Control-Allow-Origin': '*' }, body: r.body });
});
await page.click('[data-id="st1"] button');
await sleep(200);
const busy = await page.evaluate(() => { const b = document.querySelector('[data-id="st1"] button'); return { busy: b.getAttribute('aria-busy'), disabled: b.disabled }; });
ok(busy.busy === 'true' && busy.disabled, 'Stripe: el botón queda ocupado mientras espera la sesión.');
release();
await sleep(300);
ok(requests.length === 1 && requests[0].url === 'https://sitio.test/wp-admin/admin-ajax.php' && requests[0].method === 'POST', 'Stripe: una sola petición POST a admin-ajax.php.');
const sent = params(requests[0].body);
ok(sent.action === 'submit_stripe_form' && sent['data[action]'] === 'submit_stripe_form' && sent['data[postId]'] === '321' && sent['data[widgetId]'] === 'st1' && sent['data[pageUrl]'] === fixture && /^n0nce/.test(sent['data[nonce]']), 'Stripe: los campos del script de PRO (acción, documento, elemento, página y nonce): ' + requests[0].body);
ok(!/sk_|secret|Bearer/i.test(requests[0].body), 'Stripe: ninguna clave sale del navegador.');
let opened = await page.evaluate(() => window.__opened);
ok(opened.length === 1 && opened[0][0] === 'https://checkout.stripe.test/c/pay/cs_test_1' && opened[0][1] === '_blank', 'Stripe: abre la sesión de pago en una pestaña nueva: ' + JSON.stringify(opened));
ok(await page.evaluate(() => { const b = document.querySelector('[data-id="st1"] button'); return !b.disabled && !b.hasAttribute('aria-busy'); }), 'Stripe: el botón vuelve a estar disponible.');
await page.close();

page = await open();
requests = [];
reply = () => ({ body: JSON.stringify({ response: { code: 403 }, body: JSON.stringify({ error: { message: '<img src=x onerror="window.__xss=1">Clave no válida' } }) }) });
await page.click('[data-id="st1"] button');
await sleep(400);
shown = await messages(page, 'st1');
ok(shown.length === 1 && shown[0].text === '<img src=x onerror="window.__xss=1">Clave no válida' && !/<img/.test(shown[0].html.replace(/&lt;img/g, '')) && shown[0].role === 'alert', 'Stripe 403: el mensaje del servicio se pinta como texto, nunca como HTML: ' + JSON.stringify(shown));
ok(!(await page.evaluate(() => window.__xss)) && (await page.evaluate(() => window.__opened.length)) === 0, 'Stripe 403: nada se ejecuta ni se abre.');
await page.click('[data-id="st1"] button');
await sleep(400);
ok((await messages(page, 'st1')).length === 1, 'Stripe: un segundo intento sustituye el mensaje anterior.');
reply = () => ({ body: JSON.stringify({ response: { code: 401 }, body: JSON.stringify({ error: { message: 'Sin clave' } }) }) });
await page.click('[data-id="st2"] button');
await sleep(400);
shown = await messages(page, 'st2');
ok(shown.length === 1 && shown[0].text === 'Sin pasarela', 'Stripe 401 con mensajes personalizados: el de «no conectado»: ' + JSON.stringify(shown));
reply = () => ({ body: JSON.stringify({ response: { code: 500 }, body: JSON.stringify({ error: { message: 'Interno' } }) }) });
await page.click('[data-id="st2"] button');
await sleep(400);
shown = await messages(page, 'st2');
ok(shown.length === 1 && shown[0].text === 'Error general', 'Stripe 500 con mensajes personalizados: el global: ' + JSON.stringify(shown));
reply = () => ({ body: JSON.stringify({ response: { code: 400 }, body: JSON.stringify({ error: { message: 'Importe mínimo' } }) }) });
await page.click('[data-id="st1"] button');
await sleep(400);
shown = await messages(page, 'st1');
ok(shown.length === 1 && shown[0].text === 'Importe mínimo', 'Stripe 400 sin mensajes personalizados: el del servicio: ' + JSON.stringify(shown));
reply = () => ({ abort: true });
await page.click('[data-id="st1"] button');
await sleep(400);
shown = await messages(page, 'st1');
ok(shown.length === 1 && shown[0].text === 'Algo salió mal.', 'Stripe sin red: el mensaje global: ' + JSON.stringify(shown));
reply = () => ({ body: JSON.stringify({ response: { code: 200 }, body: JSON.stringify({ url: 'javascript:alert(1)' }) }) });
await page.click('[data-id="st2"] button');
await sleep(400);
ok((await page.evaluate(() => window.__opened.length)) === 0 && (await messages(page, 'st2'))[0].text === 'Error general', 'Stripe: una dirección que no es https nunca se abre.');
requests = [];
await page.click('[data-id="st3"] button');
await sleep(300);
shown = await messages(page, 'st3');
ok(requests.length === 0 && shown.length === 1 && shown[0].text === 'Algo salió mal.', 'Stripe sin nombre de producto: ninguna petición y el mensaje global.');
ok(shown[0].lead, 'El mensaje del servidor lleva un blanco antes del texto, como PRO (el mismo espacio tras el aspa del núcleo).');
await page.close();

page = await open({ editor: true });
requests = [];
await page.click('[data-id="st1"] button');
await sleep(300);
ok(requests.length === 0 && page.url() === fixture && (await messages(page, 'st1')).length === 0, 'Stripe en el editor: no hace nada, como PRO.');
await page.close();

ok(errors.length === 0, 'Sin errores de JavaScript: ' + errors.join(' | '));
await browser.close();
console.log(`OK elements-pro-payments-chrome: ${checks} comprobaciones.`);
