// Auditoría de cero rastro en Playground (brand-zero-trace.sh): cuenta las apariciones de «elementor» (sin distinguir
// mayúsculas) por capa en un sitio real con DIGITALÍSIMO Elements y escribe <dir>/result.json con `capas` (para
// tests/brand-zero-trace.mjs --sitio) y `checks`.
//   front_html       HTML de la portada y de digi-zt (sin la URL de la imagen de prueba `elementor-banner.png`, que es
//                    un medio del cliente y nunca se toca)
//   front_assets     CSS y JS que pide el navegador en esas páginas (también los trozos que se cargan al abrir el
//                    lightbox)
//   escritorio_url   barra de direcciones y enlaces del escritorio (Escritorio, Rendimiento, Plantillas, Ajustes del
//                    constructor) · escritorio_texto: texto visible · escritorio_html: HTML completo (herramientas de
//                    desarrollo)
//   editor_url / editor_texto / editor_html   lo mismo en el editor de digi-zt
//   mcp              initialize, tools/list, resources/list y una lectura por /wp-json/digitalisimo/mcp
//   wp_json          el índice /wp-json/
//   urls_directas    archivos del paquete pedidos por URL directa (readme, idioma, hojas del motor)
// Checks: el lightbox abre con el HTML y el JS transformados; los trozos del webpack se piden al espejo; la imagen de
// prueba conserva su URL; sin errores de consola en el front.
// Uso: node run.cjs <puerto> <host> <dir>   (PUPPETEER_PATH con puppeteer-core, CHROME con el binario)
const fs = require('fs');
const http = require('http');
const path = require('path');
const [port, host, dir] = process.argv.slice(2);
const puppeteer = require(path.join(process.env.PUPPETEER_PATH || '', 'puppeteer-core'));
const setup = JSON.parse(fs.readFileSync(`${dir}/probe/setup.json`, 'utf8'));
const secret = () => JSON.parse(fs.readFileSync(`${dir}/probe/secrets.json`, 'utf8')).password;
const base = `http://${host}`;
const count = (t) => (String(t || '').match(/elementor/gi) || []).length;
const capas = {};
const checks = [];
const add = (layer, n, ejemplo) => { capas[layer] = capas[layer] || { n: 0, ejemplos: [] }; capas[layer].n += n; if (n && ejemplo && capas[layer].ejemplos.length < 15) capas[layer].ejemplos.push(ejemplo); };
const check = (id, ok, what, detail = '') => { checks.push({ id, ok: !!ok, what, detail: ok ? '' : String(detail).slice(0, 800) }); console.log(`${ok ? 'ok' : 'FALLA'} · ${id} · ${what}${ok ? '' : ' · ' + String(detail).slice(0, 400)}`); };
const snippets = (t, n = 3) => (String(t).match(/.{0,40}elementor.{0,30}/gi) || []).slice(0, n).join(' | ');
const media = (t) => String(t).split(setup.image).join('').replace(/elementor-banner(-\d+x\d+)?\.png/g, '');

function request(method, p, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { Host: host, ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path: p, headers: all, timeout: 300000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => { const text = Buffer.concat(chunks).toString('utf8'); let json = null; try { json = JSON.parse(text); } catch {} resolve({ status: res.statusCode, headers: res.headers, text, json }); });
    });
    req.on('error', reject);
    if (data !== undefined) req.write(data);
    req.end();
  });
}

async function mcp() {
  const auth = `Basic ${Buffer.from(`admin:${secret()}`).toString('base64')}`;
  let session = '';
  let id = 0;
  const post = async (payload) => {
    const headers = { Authorization: auth, Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' };
    if (session) headers['Mcp-Session-Id'] = session;
    return request('POST', `/?rest_route=${encodeURIComponent('/digitalisimo/mcp')}`, { headers, body: payload });
  };
  const init = await post({ jsonrpc: '2.0', id: ++id, method: 'initialize', params: { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-zt', version: '1' } } });
  check('M1', init.status === 200 && init.json?.result, 'el MCP con nombre propio responde en /wp-json/digitalisimo/mcp', `${init.status} ${init.text.slice(0, 300)}`);
  session = init.headers['mcp-session-id'] || '';
  await post({ jsonrpc: '2.0', method: 'notifications/initialized' });
  add('mcp', count(init.text), 'initialize: ' + snippets(init.text));
  const tools = await post({ jsonrpc: '2.0', id: ++id, method: 'tools/list', params: {} });
  add('mcp', count(tools.text), 'tools/list: ' + snippets(tools.text));
  const names = (tools.json?.result?.tools || []).map((t) => t.name);
  check('M2', names.length > 10 && names.every((n) => !/elementor/i.test(n)), `tools/list con ${names.length} herramientas y ningún nombre elementor`, names.filter((n) => /elementor/i.test(n)).join(','));
  const res = await post({ jsonrpc: '2.0', id: ++id, method: 'resources/list', params: {} });
  add('mcp', count(res.text), 'resources/list: ' + snippets(res.text));
  const uri = (res.json?.result?.resources || []).map((r) => r.uri).find((u) => /best-practices/.test(u)) || (res.json?.result?.resources || [])[0]?.uri;
  if (uri) { const r = await post({ jsonrpc: '2.0', id: ++id, method: 'resources/read', params: { uri } }); add('mcp', count(r.text), `resources/read ${uri}: ` + snippets(r.text)); }
  const list = names.find((n) => n === 'digitalisimo-list-posts');
  if (list) { const r = await post({ jsonrpc: '2.0', id: ++id, method: 'tools/call', params: { name: list, arguments: {} } }); add('mcp', count(media(r.text).replace(/guía de Elementor/g, '')), 'tools/call list-posts: ' + snippets(r.text)); }
  const old = await request('POST', `/?rest_route=${encodeURIComponent('/elementor/mcp')}`, { headers: { Authorization: auth, Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' }, body: { jsonrpc: '2.0', id: 1, method: 'initialize', params: { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-zt', version: '1' } } } });
  check('M3', old.status === 200 && /digitalisimo\/mcp/.test(old.text), 'la ruta vieja sigue (alias) y avisa la nueva', `${old.status} ${old.text.slice(0, 200)}`);
}

(async () => {
  // API: índice y MCP.
  const index = await request('GET', '/?rest_route=%2F');
  add('wp_json', count(index.text), snippets(index.text));
  await mcp();
  // URL directas a archivos del paquete.
  const core = path.basename(String(setup.core || 'elementor/').replace(/\/$/, ''));
  for (const f of [`${core}/readme.txt`, `${core}/languages/elementor-es_MX.l10n.php`, `${core}/assets/css/frontend.min.css`, `${core}/assets/js/frontend.min.js`, 'assets/js/elements-handlers.min.js', 'readme.txt', 'marca/reglas.json']) {
    const r = await request('GET', `/wp-content/plugins/digitalisimo-elements/${f}`);
    const n = r.status === 200 ? count(r.text) + count(f) : 0;
    add('urls_directas', n, `${f} (${r.status}, ${n})`);
  }

  const browser = await puppeteer.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--lang=es-MX'] });
  const page = await browser.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  const assets = new Map();
  page.on('response', async (res) => {
    const u = res.url();
    if (/\.(css|js)(\?|$)/.test(u) && u.startsWith(base)) { try { assets.set(u, await res.text()); } catch {} }
  });
  // Front: visitante sin sesión.
  for (const p of ['/', `/?page_id=${setup.page}`]) {
    const r = await page.goto(base + p, { waitUntil: 'networkidle0', timeout: 180000 });
    const html = await r.text();
    add('front_html', count(media(html)), `${p}: ` + snippets(media(html)));
    check('F0', html.includes(setup.image), `la imagen de prueba conserva su URL en ${p}`);
  }
  // Lightbox: abre con el HTML y el JS transformados, y su trozo sale del espejo (si hay espejo).
  const link = await page.$('a[data-dg-open-lightbox], a[data-elementor-open-lightbox]');
  if (link) {
    await link.click();
    await new Promise((r) => setTimeout(r, 3000));
    const open = await page.evaluate(() => !!document.querySelector('.dialog-lightbox-widget:not([style*="display: none"]), [id^="dg-lightbox"], [id^="elementor-lightbox"]'));
    check('F1', open, 'el lightbox del carrusel abre', errors.join(' | '));
  } else check('F1', false, 'no se encontró el enlace del lightbox');
  const chunk = [...assets.keys()].find((u) => /js\/chunks\/lightbox/.test(u));
  check('F2', !!chunk, `el trozo del lightbox se cargó: ${chunk || 'ninguno'}`);
  for (const [u, t] of assets) add('front_assets', count(t) + count(u.replace(base, '')), `${u.replace(base, '')} (${count(t)})`);
  check('F3', errors.length === 0, 'sin errores de consola en el front', errors.join(' | '));

  // Escritorio y editor, con sesión.
  await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0' });
  await page.type('#user_login', 'admin');
  await page.type('#user_pass', 'password');
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0', timeout: 180000 }), page.click('#wp-submit')]);
  const screens = [
    ['escritorio', '/wp-admin/'],
    ['escritorio', '/wp-admin/admin.php?page=digitalisimo-elements-front'],
    ['escritorio', '/wp-admin/edit.php?post_type=elementor_library&tabs_group=library'],
    ['escritorio', '/wp-admin/admin.php?page=elementor-settings'],
    ['editor', `/wp-admin/post.php?post=${setup.page}&action=elementor`],
  ];
  for (const [layer, p] of screens) {
    const r = await page.goto(base + p, { waitUntil: layer === 'editor' ? 'networkidle2' : 'networkidle0', timeout: 240000 }).catch((e) => null);
    if (layer === 'editor') await new Promise((res) => setTimeout(res, 8000));
    const url = page.url();
    const hrefs = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).filter((h) => /elementor/i.test(h)));
    const text = await page.evaluate(() => document.body ? document.body.innerText : '');
    const html = await page.content();
    add(`${layer}_url`, count(url) + hrefs.length, `${p} → ${url}${hrefs.length ? ' · ' + hrefs.slice(0, 3).join(' ') : ''}`);
    add(`${layer}_texto`, count(media(text).replace(/guía de Elementor/g, '')), `${p}: ` + snippets(text));
    add(`${layer}_html`, count(html), `${p} (${count(html)})`);
  }
  await browser.close();
  const result = { capas, checks, failures: checks.filter((c) => !c.ok).length };
  fs.writeFileSync(`${dir}/result.json`, JSON.stringify(result, null, 1));
  for (const [k, v] of Object.entries(capas)) console.log(`${v.n ? 'CUENTA' : 'cero  '} · ${k}: ${v.n}${v.ejemplos.length ? ' · ' + v.ejemplos.slice(0, 3).join(' · ') : ''}`);
  process.exit(result.failures ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
