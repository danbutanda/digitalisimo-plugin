// Ensayo de punta a punta del dominio antes de pagar y de DIGITALÍSIMO Hosting en el MCP, contra el Playground que
// levantó hosting-checkout.sh (WooCommerce 11.2.1 fijado, Hosting y Elements del repositorio, Namecheap simulado):
//   M  MCP real (JSON-RPC a /wp-json/elementor/mcp con contraseñas de aplicación): digitalisimo-hosting-settings get sin
//      secretos, update que combina (la cuenta y las claves guardadas siguen), la cuenta de Namecheap no se escribe, una
//      gerente de tienda (sin manage_options) no configura pero sí marca el producto de transferencia
//   P  productos por extensión sin IDs: .com, .net y .mx por SKU, nunca .org
//   C  compras reales como invitada por la Store API: dominio nuevo (comprobado en Namecheap antes de pagar), plan sin
//      dominio rechazado, «Uno que ya tengo», transferencia con EPP (cifrado en el pedido, fuera de notas, de la REST v3 y
//      de la URL) y sin EPP rechazada, dominio ocupado rechazado
//   A  página del editor atómico con el formulario e-form, [resultado_dominio] y [digitalisimo_domain_search]: el HTML y,
//      con Chrome, la consulta real desde el navegador
// Escribe <dir>/result.txt y result.json; sale 1 si algo falla. Uso: node run.mjs <puerto> <host> <dir>
import http from 'node:http';
import { appendFileSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const [port, host, dir] = process.argv.slice(2);
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const secrets = () => JSON.parse(readFileSync(`${dir}/probe/secrets.json`, 'utf8'));
const results = [];
let failures = 0;
let section = '';
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  results.push({ section, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 800) });
  if (ok) log(`ok · ${what}`); else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 800)}` : ''}`); }
  return !!ok;
};
const start = (name) => { section = name; log(`== ${name}`); };

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { Host: host, ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => { const text = Buffer.concat(chunks).toString('utf8'); let json = null; try { json = JSON.parse(text); } catch {} resolve({ status: res.statusCode, headers: res.headers, text, json }); });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const probe = async (step, args = {}) => {
  const res = await request('GET', `/?${new URLSearchParams({ digi_hc: step, token, ...args })}`);
  if (res.status !== 200 || !res.json) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 400)}`);
  return res.json;
};

class Client {
  constructor(user) { this.user = user; this.id = 0; this.session = ''; this.names = {}; }
  auth() { return `Basic ${Buffer.from(`${this.user}:${secrets()[`password:${this.user}`]}`).toString('base64')}`; }
  post(payload) {
    const headers = { Authorization: this.auth(), Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' };
    if (this.session) headers['Mcp-Session-Id'] = this.session;
    return request('POST', `/?rest_route=${encodeURIComponent('/elementor/mcp')}`, { headers, body: payload });
  }
  async rpc(method, params = {}) { const res = await this.post({ jsonrpc: '2.0', id: ++this.id, method, params }); if (!res.json) throw new Error(`${method}: HTTP ${res.status} ${res.text.slice(0, 300)}`); return res; }
  async init() {
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-hosting-e2e', version: '1.0.0' } });
    this.session = res.headers['mcp-session-id'] || '';
    await this.post({ jsonrpc: '2.0', method: 'notifications/initialized' });
  }
  async tools() { const res = await this.rpc('tools/list', {}); const tools = res.json?.result?.tools || []; tools.forEach((t) => { this.names[t.name] = t.name; }); return tools.map((t) => t.name); }
  async call(name, args) {
    const res = await this.rpc('tools/call', { name, arguments: args });
    const result = res.json?.result; const error = res.json?.error;
    const text = (result?.content || []).map((c) => c.text || '').join('\n');
    let data = result?.structuredContent; if (!data && text) { try { data = JSON.parse(text); } catch { data = null; } }
    return { isError: !!(result?.isError || error), text: error ? JSON.stringify(error) : text, data: data || {} };
  }
}

/** Una compra como invitada por la Store API: líneas { id, body, query }, checkout con cheque. */
async function buy(lines) {
  const cart = await request('GET', '/?rest_route=/wc/store/v1/cart');
  const h = { Nonce: cart.headers.nonce, 'Cart-Token': cart.headers['cart-token'] };
  if (!h.Nonce || !h['Cart-Token']) throw new Error(`Store API sin Nonce/Cart-Token: ${cart.status}`);
  const added = [];
  for (const l of lines) {
    const q = l.query ? `&${new URLSearchParams(l.query)}` : '';
    const r = await request('POST', `/?rest_route=/wc/store/v1/cart/add-item${q}`, { headers: h, body: { id: l.id, quantity: 1, ...(l.body || {}) } });
    added.push(r);
    if (r.status !== 201 && r.status !== 200) return { added, addError: r };
  }
  const address = { first_name: 'Ana', last_name: 'López', company: '', address_1: 'Av. Juárez 100', address_2: '', city: 'Guadalajara', state: 'JA', postcode: '44100', country: 'MX', email: 'ana@cliente.test', phone: '33 1234 5678' };
  const co = await request('POST', '/?rest_route=/wc/store/v1/checkout', { headers: h, body: { billing_address: address, shipping_address: address, payment_method: 'cheque' } });
  return { added, checkout: co, order: co.json?.order_id || 0, cart: added.at(-1) };
}
const message = (r) => JSON.stringify(r?.json || r?.text || '').slice(0, 600);

try {
  start('Tienda');
  const store = await probe('store');
  const P = store.products;
  check(store.gateways.includes('cheque'), `Tienda en MXN con cheque · WooCommerce ${store.woocommerce}`);

  start('MCP: digitalisimo-hosting-settings y digitalisimo-hosting-domain-product');
  const admin = new Client('admin');
  await admin.init();
  const tools = await admin.tools();
  check(tools.includes('digitalisimo-hosting-settings') && tools.includes('digitalisimo-hosting-domain-product'), 'El MCP del sitio ofrece digitalisimo-hosting-settings y digitalisimo-hosting-domain-product', tools.join(', '));
  let r = await admin.call('digitalisimo-hosting-settings', { action: 'get' });
  const S = secrets();
  const leaked = [S.namecheap, S.webuzo, S.banxico, S.nc_user, '203.0.113.5'].filter((v) => r.text.includes(v));
  check(!r.isError && !leaked.length && r.data.secrets_set?.api_key === true && r.data.namecheap?.configured === true, 'get: todos los ajustes, sin ningún secreto (sólo si están puestos)', leaked.length ? `fuga: ${leaked.length}` : r.text.slice(0, 300));
  check(r.data.settings?.domain_products === '.com|\n.net|\n.mx|' && !r.text.includes('.org|'), 'get: la lista de fábrica vieja (con .org) vale como .com, .net y .mx', r.data.settings?.domain_products);
  r = await admin.call('digitalisimo-hosting-settings', { action: 'update', data: { ask_domain: true, require_domain_hosting: true, domain_products: '.com|\n.net|\n.mx|' } });
  let opt = await probe('option');
  check(!r.isError && opt.same_account && opt.domain_products === '.com|\n.net|\n.mx|' && String(opt.ask_domain) === '1' && opt.hosting_category_ids && String(opt.provision_enabled) === '0', 'update combina: guarda lo enviado y la cuenta, las claves y lo demás siguen igual', JSON.stringify(opt) + r.text.slice(0, 200));
  check(Array.isArray(r.data.changed) && r.data.changed.includes('require_domain_hosting') && r.data.settings?.require_domain_hosting === true, 'update devuelve el valor resultante', r.text.slice(0, 300));
  r = await admin.call('digitalisimo-hosting-settings', { action: 'update', data: { api_key: 'ROBADA-123456', replace_hosting: true } });
  opt = await probe('option');
  check(r.isError && opt.same_account && !r.text.includes('ROBADA'), 'La cuenta de Namecheap no se escribe por MCP y no se guarda nada del lote', r.text.slice(0, 300));
  r = await admin.call('digitalisimo-hosting-settings', { action: 'update', data: { provision_enabled: true } });
  check(r.isError && String((await probe('option')).provision_enabled) === '0', 'La venta automática no se enciende por MCP');
  const manager = new Client('lectora');
  await manager.init();
  const mtools = await manager.tools();
  r = await manager.call('digitalisimo-hosting-settings', { action: 'update', data: { ask_domain: false } });
  check(r.isError && String((await probe('option')).ask_domain) === '1', 'Una gerente de tienda (sin manage_options) no cambia los ajustes de Hosting', mtools.join(', ') + ' · ' + r.text.slice(0, 200));
  r = await manager.call('digitalisimo-hosting-domain-product', { action: 'mark', product_id: P.transfer, kind: 'transfer' });
  check(!r.isError && r.data.product?.kind === 'transfer', 'Con manage_woocommerce marca el producto de transferencia', r.text.slice(0, 300));
  r = await admin.call('digitalisimo-hosting-settings', { action: 'log' });
  check(!r.isError && r.data.total >= 4 && !r.text.includes('ROBADA') && !r.text.includes('.com|'), 'La bitácora anota las escrituras sin valores', r.text.slice(0, 300));

  start('Productos por extensión');
  opt = await probe('option');
  check(JSON.stringify(opt.resolved) === JSON.stringify({ '.com': P.com, '.net': P.net, '.mx': P.mx }), 'Sin IDs: .com, .net y .mx resueltos por su SKU; el producto .org no se vende', JSON.stringify(opt.resolved));

  start('Compras como invitada (Store API)');
  let b = await buy([{ id: P.com, query: { domain_name: 'nuevonegocio.com' } }, { id: P.plan }]);
  let calls = (await probe('calls')).calls;
  const checks = calls.filter((c) => c.cmd === 'namecheap.domains.check' && c.fields.DomainList === 'nuevonegocio.com');
  check(b.order && checks.length >= 1 && checks.every((c) => c.key_ok && !c.key_in_url) && checks.some((c) => !/checkout/.test(c.uri)), 'Dominio nuevo: comprobado en Namecheap antes de pagar (al añadirlo), con la clave en el cuerpo', message(b.checkout));
  let s = await probe('state', { id: b.order });
  check(s.customer === 0 && s.items.some((i) => i.product === P.com && i.meta.Dominio === 'nuevonegocio.com'), 'El pedido de invitada lleva el dominio en su línea («Dominio»)', JSON.stringify(s.items));
  b = await buy([{ id: P.plan }]);
  check(!b.order && /Elige el dominio de tu plan/.test(message(b.checkout)), 'Un plan sin dominio no se paga: pide «Dominio nuevo» o «Uno que ya tengo»', message(b.checkout));
  b = await buy([{ id: P.plan, body: { digitalisimo_domain_mode: 'own', digitalisimo_domain: 'MiEmpresa.com' } }]);
  s = b.order ? await probe('state', { id: b.order }) : { items: [] };
  check(b.order && s.items.length === 1 && s.items[0].meta.Dominio === 'miempresa.com', '«Uno que ya tengo»: el nombre exacto queda en la línea del plan y se paga sin producto de dominio (con «Exigir dominio y hosting juntos»)', message(b.checkout) + JSON.stringify(s.items));
  b = await buy([{ id: P.com, query: { domain_name: 'ocupado.com' } }]);
  check(b.addError && /ya está registrado/.test(message(b.addError)), 'Un dominio ocupado no entra al carrito', message(b.addError));
  b = await buy([{ id: P.transfer, body: { digitalisimo_domain: 'ajeno.com' } }, { id: P.plan }]);
  check(!b.order && /EPP/.test(message(b.checkout)), 'Transferencia sin código EPP: no se paga', message(b.checkout));
  b = await buy([{ id: P.transfer, body: { digitalisimo_domain: 'ajeno.com' }, query: { digitalisimo_epp: 'EN-LA-URL-1234' } }, { id: P.plan }]);
  check(!b.order && /EPP/.test(message(b.checkout)), 'Un EPP en la URL se ignora (el carrito lo sigue pidiendo)', message(b.checkout));
  b = await buy([{ id: P.transfer, body: { digitalisimo_domain: 'ajeno.mx', digitalisimo_epp: S.epp } }]);
  check(b.addError && /no se pueden transferir/.test(message(b.addError)), 'Una extensión que Namecheap no transfiere por la API no entra', message(b.addError));
  b = await buy([{ id: P.transfer, body: { digitalisimo_domain: 'ajeno.com', digitalisimo_epp: S.epp } }, { id: P.plan }]);
  check(b.order && !b.cart.text.includes(S.epp) && !b.checkout.text.includes(S.epp), 'Transferencia con dominio y EPP: se paga; el EPP no vuelve en el carrito ni en el checkout', message(b.checkout));
  s = await probe('state', { id: b.order });
  check(s.epp_ok === true && s.meta_keys.includes('_digitalisimo_epp') && !JSON.stringify(s.items).includes(S.epp) && !JSON.stringify(s.items).toLowerCase().includes('epp'), 'El EPP se guarda cifrado en el pedido (descifra al del ensayo) y nunca en la línea', JSON.stringify(s.items));
  check(s.rest_status === 200 && !s.rest_meta_keys.includes('_digitalisimo_epp') && !s.rest_json.includes(S.epp), 'La REST v3 de pedidos no devuelve el EPP, ni cifrado', s.rest_meta_keys.join(','));
  check(!s.notes.join('\n').includes(S.epp), 'Las notas del pedido no llevan el EPP');
  calls = (await probe('calls')).calls;
  check(calls.some((c) => c.cmd === 'namecheap.domains.getTldList') && !JSON.stringify(calls).includes(S.epp), 'La transferencia se comprueba con getTldList y el EPP nunca viaja a Namecheap al validar');

  start('Página del editor atómico');
  const page = await probe('page');
  check(page.atomic && page.atomic_form, 'Editor atómico y formulario atómico activos', JSON.stringify(page));
  const html = (await request('GET', `/?page_id=${page.id}`)).text;
  writeFileSync(`${dir}/atomic-page.html`, html);
  const config = (html.match(/var digitalisimoHosting = (\{.*?\});/) || [])[1] || '';
  check(/data-element_type="e-form"/.test(html) && /name="?dominio"?/.test(html), 'La página pinta el formulario atómico (e-form) con el campo «dominio»');
  check(html.includes('class="digitalisimo-domain-search"') && !/<form[^>]*digitalisimo-domain-search/.test(html) && html.includes('digitalisimo-hosting-result'), '[digitalisimo_domain_search] (un <div>) y [resultado_dominio] se pintan en la página atómica');
  check(/domain-form\.js/.test(html) && config.includes('.com') && config.includes('.net') && config.includes('.mx') && !config.includes('.org'), 'El script y su configuración con .com, .net y .mx (sin .org)', config.slice(0, 300));
  const options = [...html.matchAll(/<option value="([^"]+)"/g)].map((m) => m[1]);
  check(JSON.stringify(options) === '[".com",".net",".mx"]', `El buscador ofrece .com, .net y .mx (${options})`);

  // Con Chrome: la consulta real desde el navegador en la página atómica.
  const chrome = process.env.CHROME_PATH || '/usr/bin/google-chrome';
  const pptrDir = process.env.PUPPETEER_PATH ? process.env.PUPPETEER_PATH.replace(/\/$/, '') + '/puppeteer-core' : '/home/digitalisimo/.npm/_npx/0f94ee7615faf582/node_modules/puppeteer-core';
  const entry = ['/lib/puppeteer/puppeteer-core.js', '/lib/esm/puppeteer/puppeteer-core.js'].map((f) => pptrDir + f).find((f) => existsSync(f));
  if (!existsSync(chrome) || !entry) log(`AVISO: sin Chrome o puppeteer-core se salta la parte del navegador (${chrome}, ${pptrDir})`);
  else {
    const { default: puppeteer } = await import(pathToFileURL(entry).href);
    const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--no-sandbox', '--disable-gpu', `--host-resolver-rules=MAP ${host.split(':')[0]} 127.0.0.1`] });
    try {
      const tab = await browser.newPage();
      // Playground atiende con un solo proceso: la página y sus recursos llegan de uno en uno.
      tab.setDefaultTimeout(180000); tab.setDefaultNavigationTimeout(180000);
      const errors = []; tab.on('pageerror', (e) => errors.push(e.message));
      await tab.goto(`http://${host}/?page_id=${page.id}`, { waitUntil: 'load' });
      await tab.waitForFunction(() => window.digitalisimoHosting && document.querySelector('form[data-element_type="e-form"] input[name="dominio"]'));
      await tab.type('form[data-element_type="e-form"] input[name="dominio"]', 'minegocio');
      await tab.click('form[data-element_type="e-form"] button[type="submit"], form[data-element_type="e-form"] [type="submit"]');
      await tab.waitForFunction(() => document.querySelectorAll('.digitalisimo-hosting-result .digitalisimo-hosting-option').length === 3, { timeout: 180000 });
      const rows = await tab.$$eval('.digitalisimo-hosting-result .digitalisimo-hosting-option', (ps) => ps.map((p) => p.textContent));
      check(rows.length === 3 && rows.every((t) => /disponible/.test(t)) && rows.some((t) => t.includes('minegocio.net')) && !rows.some((t) => t.includes('.org')), `Chrome: el formulario atómico consulta .com, .net y .mx en la REST real y no se envía (${rows.join(' | ')})`);
      const link = await tab.$eval('.digitalisimo-hosting-result a', (a) => a.href);
      check(/add-to-cart=\d+/.test(link) && /domain_name=minegocio\.com/.test(link), `Chrome: «Registrar» lleva al carrito con el dominio (${link})`);
      await tab.type('.digitalisimo-domain-search input[name="domain"]', 'ocupado');
      await tab.select('.digitalisimo-domain-search select', '.com');
      await tab.click('.digitalisimo-domain-search button');
      await tab.waitForFunction(() => /no está disponible/.test(document.querySelector('.digitalisimo-domain-search .digitalisimo-domain-result')?.textContent || ''), { timeout: 180000 });
      check(true, 'Chrome: [digitalisimo_domain_search] consulta en la página atómica');
      check(!errors.length, `Chrome: sin errores de JavaScript${errors.length ? ': ' + errors.join(' | ') : ''}`);
    } finally { await browser.close(); }
  }
} catch (e) {
  failures++;
  log(`FALLA · ${section} · ${e.stack || e}`);
}
writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, results }, null, 1));
log(failures ? `== ${failures} fallas` : `== todo bien (${results.length} comprobaciones)`);
process.exit(failures ? 1 : 0);
