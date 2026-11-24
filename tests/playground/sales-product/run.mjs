// Ensayo de punta a punta de la landing de producto del kit de ventas contra el Playground que levantó
// sales-product.sh: JSON-RPC real contra /wp-json/elementor/mcp (initialize, tools/list y tools/call de
// digitalisimo-sales-create-page con product_id y offer, como lo llamaría el Asistente IA) y Chrome real para ver la
// página creada (1 280 y 390 px, capturas en <dir>/capturas).
// Comprueba, en la respuesta y en el HTML que pinta el sitio: ningún [placeholder], el nombre, el precio normal tachado
// y el de oferta, las tres imágenes del producto, el botón que agrega el producto al carrito, el WhatsApp del pie del
// sitio (nunca el de muestra), el logo del sitio, la cuenta regresiva y que cada enlace del sitio en la página lleva a
// una página que existe (HTTP 200).
// Escribe cada resultado en <dir>/result.txt («ok · …» o «FALLA · …») y <dir>/result.json; sale 1 si algo falla.
// Ningún secreto (la contraseña de aplicación) se escribe en los resultados.
// Uso: node run.mjs <puerto> <dir>   (PUPPETEER_PATH: carpeta node_modules con puppeteer-core)
import http from 'node:http';
import { appendFileSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { join } from 'node:path';

const [port, dir] = process.argv.slice(2);
const base = `http://127.0.0.1:${port}`;
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const setup = JSON.parse(readFileSync(`${dir}/probe/setup.json`, 'utf8'));
const secret = () => JSON.parse(readFileSync(`${dir}/probe/secrets.json`, 'utf8'))['password:admin'];
const results = [];
let failures = 0;
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  results.push({ what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 1200) });
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 1200)}` : ''}`); }
  return !!ok;
};

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const probe = async (step, value = '') => {
  const res = await request('GET', `/?digi_sp=${step}&token=${token}&value=${encodeURIComponent(value)}`);
  if (res.status !== 200) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
};

let rpcId = 0;
let session = '';
const auth = () => `Basic ${Buffer.from(`admin:${secret()}`).toString('base64')}`;
async function rpc(method, params = {}, notify = false) {
  const headers = { Authorization: auth(), Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' };
  if (session) headers['Mcp-Session-Id'] = session;
  const payload = notify ? { jsonrpc: '2.0', method } : { jsonrpc: '2.0', id: ++rpcId, method, params };
  const res = await request('POST', `/?rest_route=${encodeURIComponent('/elementor/mcp')}`, { headers, body: payload });
  if (!session && res.headers['mcp-session-id']) session = res.headers['mcp-session-id'];
  return res;
}
async function call(name, args) {
  const res = await rpc('tools/call', { name: `digitalisimo-sales-${name}`, arguments: args });
  const result = res.json?.result;
  const error = res.json?.error;
  const text = (result?.content || []).map((c) => c.text || '').join('\n');
  let data = result?.structuredContent;
  if (!data && text) { try { data = JSON.parse(text); } catch { data = null; } }
  if (data && data.result !== undefined && data.ok === undefined) data = data.result;
  return { isError: !!(result?.isError || error), text: error ? JSON.stringify(error) : (text || res.text.slice(0, 2000)), data };
}

// «De hoy a fin de mes»: el último día del mes de mañana (hora del sitio), nunca una fecha fija.
const manana = new Date(Date.now() + 86400000 - 6 * 3600000);
const finDeMes = new Date(Date.UTC(manana.getUTCFullYear(), manana.getUTCMonth() + 1, 0)).toISOString().slice(0, 10);
const NOMBRE = 'Purificador y Alcalinizador de Agua sobre tarja Negro ROTOPLAS';
// Un placeholder del kit: «[» seguido de una letra (los JSON de data-settings van con &quot; y no cuentan).
const PLACEHOLDER = /\[[A-Za-zÁÉÍÓÚÑáéíóúñ¿¡][^\]\[<>"]{0,120}\]/g;

async function main() {
  const ping = await probe('ping');
  check(ping.ready && ping.sales && ping.woo, `sitio listo (WordPress ${ping.wp}, PHP ${ping.php}, tienda ${ping.woo}) con el kit de ventas cargado`, JSON.stringify(ping));
  const init = await rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-sales-product-e2e', version: '1.0.0' } });
  await rpc('notifications/initialized', {}, true);
  check(init.status === 200 && session, 'initialize con contraseña de aplicación', init.text.slice(0, 300));
  const tools = (await rpc('tools/list', {})).json?.result?.tools || [];
  const cp = tools.find((t) => t.name === 'digitalisimo-sales-create-page');
  check(cp && cp.inputSchema?.properties?.product_id && cp.inputSchema?.properties?.offer && /product_id/.test(cp.description || ''), 'tools/list: create-page admite product_id y offer y lo explica', JSON.stringify(cp?.inputSchema?.properties && Object.keys(cp.inputSchema.properties)));

  // La llamada del caso: la promo de Black Friday de $10,000 a $7,500, de hoy a fin de mes.
  const args = { product_id: setup.product_id, offer: { price: '7500', ends: finDeMes, label: 'Black Friday' } };
  const r = await call('create-page', args);
  writeFileSync(join(dir, 'respuesta.json'), JSON.stringify(r.data || r.text, null, 1));
  const d = r.data || {};
  if (!check(!r.isError && d.ok && d.status === 'draft' && d.template === 'venta-corta' && d.finished === true, `create-page con product_id ${setup.product_id}: borrador terminado de la landing de producto`, r.text.slice(0, 1500))) return;
  check(Array.isArray(d.placeholders_left) && d.placeholders_left.length === 0, 'la respuesta: ningún placeholder queda en la página', JSON.stringify(d.placeholders_left));
  check(d.whatsapp === '5218711112222' && /Pie del sitio/.test(d.whatsapp_source), `la respuesta: el WhatsApp del pie del sitio (${d.whatsapp} · ${d.whatsapp_source})`);
  check(!(d.pending || []).some((p) => p.required), 'la respuesta: nada obligatorio pendiente (la garantía sale del producto)', JSON.stringify(d.pending));
  const fuentes = Object.fromEntries((d.filled_from || []).map((f) => [f.field, f.source]));
  check(['nombre del producto', 'imágenes', 'beneficios', 'garantía', 'precio de oferta', 'precio normal', 'whatsapp', 'logo'].every((k) => fuentes[k]), 'la respuesta: qué se llenó y de dónde', JSON.stringify(fuentes));
  check((d.review || []).some((x) => /La tienda cobra hoy \$10,000 MXN/.test(x)), 'la respuesta avisa que la tienda cobra $10,000 y la landing anuncia $7,500', JSON.stringify(d.review));
  check(d.product?.buy_url === `${setup.checkout}?add-to-cart=${setup.product_id}`.replace('??', '?'), `el enlace de compra agrega el producto al carrito y lleva a pagar (${d.product?.buy_url})`);
  check(d.close_date === `${finDeMes} 23:59`, `el cierre real: ${d.close_date}`);
  check((d.pages_to_create || []).map((p) => p.path).join(',') === '/politica-de-reembolso/', 'la respuesta dice qué página falta (la de reembolso, en borrador)', JSON.stringify(d.pages_to_create));
  log(`   vista previa: ${d.preview_url} · edición: ${d.edit_url}`);

  // Chrome: la página como la pinta el sitio.
  const require = createRequire(join(process.env.PUPPETEER_PATH || '.', 'x.js'));
  const puppeteer = require('puppeteer-core');
  const browser = await puppeteer.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  try {
    const page = await browser.newPage();
    const errores = [];
    page.on('pageerror', (e) => errores.push(String(e.message || e)));
    await page.goto(`${base}/?digi_sp=login&token=${token}&value=admin`, { waitUntil: 'load' });
    mkdirSync(join(dir, 'capturas'), { recursive: true });
    for (const [ancho, alto] of [[1280, 800], [390, 844]]) {
      await page.setViewport({ width: ancho, height: alto });
      const res = await page.goto(d.preview_url, { waitUntil: 'networkidle0', timeout: 600_000 });
      check(res && res.status() === 200, `${ancho} px: la vista previa responde 200`, res && res.status());
      // Recorre la página (las imágenes de abajo cargan al acercarse) y espera a que cada imagen esté pintada: el
      // servidor de Playground atiende de una en una y la captura no debe salir con huecos en blanco.
      await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 400) { window.scrollTo(0, y); await new Promise((ok) => setTimeout(ok, 150)); } window.scrollTo(0, 0); });
      await page.waitForFunction(() => [...document.images].filter((i) => !i.classList.contains('emoji')).every((i) => i.complete && i.naturalWidth > 0), { timeout: 180_000 }).catch(() => {});
      await new Promise((ok) => setTimeout(ok, 1500));
      const pintadas = await page.evaluate((id) => {
        const root = document.querySelector(`[data-elementor-id="${id}"]`) || document.body;
        return [...root.querySelectorAll('img')].filter((i) => /purificador/.test(i.currentSrc || i.src)).map((i) => ({ src: (i.currentSrc || i.src).split('/').pop(), ok: i.complete && i.naturalWidth > 0 }));
      }, d.id);
      check(pintadas.length >= 3 && pintadas.every((i) => i.ok), `${ancho} px: las imágenes del producto cargan y se pintan (${pintadas.length})`, JSON.stringify(pintadas));
      await page.screenshot({ path: join(dir, 'capturas', `landing-producto-${ancho}.png`), fullPage: true });
      await page.screenshot({ path: join(dir, 'capturas', `landing-producto-${ancho}-primera-pantalla.png`) });
      const info = await page.evaluate((id) => {
        const root = document.querySelector(`[data-elementor-id="${id}"]`) || document.body;
        return {
          html: root.outerHTML,
          text: root.innerText,
          hrefs: [...root.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')),
          imgs: [...root.querySelectorAll('img')].map((i) => i.currentSrc || i.src),
          bgs: [...root.querySelectorAll('[style*="background-image"]')].map((e) => e.getAttribute('style')),
          countdown: !!root.querySelector('.elementor-countdown-wrapper'),
          overflow: document.documentElement.scrollWidth > window.innerWidth + 1,
        };
      }, d.id);
      if (ancho === 1280) writeFileSync(join(dir, 'landing.html'), info.html);
      const vistos = [...new Set([...(info.html.match(PLACEHOLDER) || []), ...(info.text.match(PLACEHOLDER) || [])])];
      check(vistos.length === 0, `${ancho} px: ningún [placeholder] en el HTML ni en el texto de la página`, vistos.slice(0, 10).join(' | '));
      check(info.text.includes(NOMBRE) && info.text.includes('Black Friday'), `${ancho} px: el nombre del producto y la promo`);
      check(/<del>\$10,000 MXN<\/del>/.test(info.html) && info.text.includes('$7,500 MXN') && info.text.includes('Ahorras $2,500 MXN'), `${ancho} px: el precio normal tachado, el de oferta y el ahorro`);
      const fotos = [...info.imgs, ...info.html.match(/https?:[^"'\s)]+\.(?:jpe?g|png)/g) || []].join(' ');
      for (const img of setup.images) {
        const nombre = img.split('/').pop().replace(/\.(jpe?g|png)$/, '');
        check(fotos.includes(nombre), `${ancho} px: la imagen del producto ${nombre}`);
      }
      check(fotos.includes(setup.logo.split('/').pop().replace(/\.png$/, '')), `${ancho} px: el logo del sitio`);
      check(!/placeholder\.png/.test(info.html), `${ancho} px: ninguna imagen de muestra`);
      check(info.hrefs.some((h) => h === d.product.buy_url || h === d.product.buy_url.replace(/&/g, '&amp;')), `${ancho} px: el botón al carrito del producto`, info.hrefs.join(' '));
      check(info.hrefs.some((h) => h.startsWith('https://wa.me/5218711112222')) && !info.html.includes('52XXXXXXXXXX'), `${ancho} px: el WhatsApp del sitio, nunca el de muestra`);
      check(info.countdown, `${ancho} px: la cuenta regresiva al fin de mes`);
      check(!info.overflow, `${ancho} px: sin desborde horizontal`);
      if (ancho === 1280) {
        // Cada enlace del sitio lleva a una página que existe.
        const propios = [...new Set(info.hrefs.filter((h) => h.startsWith(base) || h.startsWith('/')))].filter((h) => !h.startsWith('#'));
        for (const h of propios) {
          const st = await page.evaluate(async (u) => (await fetch(u, { credentials: 'include', redirect: 'follow' })).status, h);
          check(st === 200, `enlace del sitio ${h.replace(base, '')} → HTTP ${st}`);
        }
        check(info.hrefs.includes(setup.privacy) && info.hrefs.includes(setup.terms) && !info.hrefs.some((h) => /reembolso|refund/.test(h)), 'el pie enlaza a la privacidad y los términos reales; la de reembolso (en borrador) no', info.hrefs.join(' '));
        const otros = info.hrefs.filter((h) => /^https?:/.test(h) && !h.startsWith(base) && !h.startsWith('https://wa.me/'));
        check(otros.length === 0, 'ningún enlace a otro sitio', otros.join(' '));
      }
    }
    check(errores.length === 0, 'sin errores de JavaScript', errores.join(' | '));
  } finally {
    await browser.close();
  }
  const precio = await probe('product', String(setup.product_id));
  check(String(precio.price) === '10000', 'la herramienta no tocó el precio del producto (la tienda sigue en $10,000)', JSON.stringify(precio));
}

main().catch((e) => { check(false, 'el ensayo terminó con una excepción', e.stack || e); }).finally(() => {
  writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, failed: results.filter((x) => !x.ok), results }, null, 1));
  log(failures ? `== ${failures} fallas de ${results.length}` : `== ${results.length} comprobaciones en verde`);
  process.exit(failures ? 1 : 0);
});
