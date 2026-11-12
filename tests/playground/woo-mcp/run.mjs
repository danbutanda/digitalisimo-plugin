// Ensayo de punta a punta de WooCommerce en el MCP de DIGITALÍSIMO Elements contra el Playground que levantó
// woo-mcp.sh: JSON-RPC real contra /wp-json/elementor/mcp (initialize, notifications/initialized, tools/list y
// tools/call) con contraseñas de aplicación, y la sonda (?digi_wm=…) para preparar el sitio y leer su estado sin pasar
// por el MCP. Escribe cada resultado en <dir>/result.txt («ok · …» o «FALLA · …») y <dir>/result.json; sale 1 si algo
// falla. Ningún secreto (contraseñas, la clave de la pasarela) se escribe en los resultados.
// Uso: node run.mjs <modo single|multisite> <puerto> <host> <dir>
import http from 'node:http';
import { createHash, randomBytes } from 'node:crypto';
import { appendFileSync, readFileSync, writeFileSync } from 'node:fs';

const [mode, port, host, dir] = process.argv.slice(2);
const network = mode === 'multisite';
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
const secrets = () => JSON.parse(readFileSync(`${dir}/probe/secrets.json`, 'utf8'));
const results = [];
let failures = 0;
let section = '';
const log = (line) => { appendFileSync(`${dir}/result.txt`, `${line}\n`); console.log(line); };
const check = (ok, what, detail = '') => {
  results.push({ section, what, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 600) });
  if (ok) log(`ok · ${what}`);
  else { failures++; log(`FALLA · ${what}${detail ? ` · ${String(detail).slice(0, 600)}` : ''}`); }
  return !!ok;
};
const start = (name) => { section = name; log(`== ${name}`); };
const sha = (text) => createHash('sha256').update(String(text)).digest('hex');

function request(method, path, { headers = {}, body } = {}) {
  return new Promise((resolve, reject) => {
    const data = body === undefined ? undefined : JSON.stringify(body);
    const all = { Host: host, ...headers };
    if (data !== undefined) { all['Content-Type'] = 'application/json'; all['Content-Length'] = Buffer.byteLength(data); }
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* respuesta no JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}

const probe = async (step, value = '', sub = '') => {
  const res = await request('GET', `/${sub}?digi_wm=${step}&token=${token}&value=${encodeURIComponent(value)}`);
  if (res.status !== 200) throw new Error(`sonda ${step} (${sub || '/'}): HTTP ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
};

const WOO_TOOLS = ['products', 'catalog', 'orders', 'customers', 'coupons', 'store-settings', 'reports'].map((t) => `digitalisimo-woo-${t}`);

// Cliente MCP: una sesión por usuario y sitio.
class Client {
  constructor(login, sub = '', blog = 1) { this.login = login; this.sub = sub; this.blog = blog; this.id = 0; this.session = ''; }
  auth() {
    const pass = secrets()[`password:${this.login}:${this.blog}`];
    if (!pass) throw new Error(`sin contraseña de aplicación de ${this.login} en el sitio ${this.blog}`);
    return `Basic ${Buffer.from(`${this.login}:${pass}`).toString('base64')}`;
  }
  async post(payload) {
    const headers = { Authorization: this.auth(), Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' };
    if (this.session) headers['Mcp-Session-Id'] = this.session;
    return request('POST', `/${this.sub}?rest_route=${encodeURIComponent('/elementor/mcp')}`, { headers, body: payload });
  }
  async rpc(method, params = {}) {
    const res = await this.post({ jsonrpc: '2.0', id: ++this.id, method, params });
    if (!res.json) throw new Error(`${method}: respuesta no JSON (HTTP ${res.status}) ${res.text.slice(0, 300)}`);
    return res;
  }
  async init() {
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-woo-mcp-e2e', version: '1.0.0' } });
    this.session = res.headers['mcp-session-id'] || '';
    const note = await this.post({ jsonrpc: '2.0', method: 'notifications/initialized' });
    return { res, note };
  }
  async tools() {
    const res = await this.rpc('tools/list', {});
    return res.json?.result?.tools || [];
  }
  async call(name, args) {
    const res = await this.rpc('tools/call', { name: `digitalisimo-woo-${name}`, arguments: args });
    const result = res.json?.result;
    const error = res.json?.error;
    const text = (result?.content || []).map((c) => c.text || '').join('\n');
    let data = result?.structuredContent;
    if (!data && text) { try { data = JSON.parse(text); } catch { data = null; } }
    if (data && data.result !== undefined && data.ok === undefined) data = data.result;
    return { isError: !!(result?.isError || error), error, text: error ? JSON.stringify(error) : text, data, raw: res.text };
  }
}

const okCall = (r, what) => check(!r.isError && r.data?.ok === true, what, r.text);

// =====================================================================================================================
async function single() {
  start('S0 Preparación sin WooCommerce');
  const self = await probe('selftest');
  check(self.notice && self.doing_it_wrong, 'el detector de avisos de PHP del MCP de tienda funciona (aviso y _doing_it_wrong)', JSON.stringify(self));
  const ping = await probe('ping');
  check(ping.ready && !ping.woo, `sitio listo sin WooCommerce (WordPress ${ping.wp}, PHP ${ping.php})`, JSON.stringify(ping));
  await probe('users', 'base');
  const admin = new Client('admin');
  const { res: init } = await admin.init();
  check(init.status === 200 && admin.session && init.json?.result?.serverInfo, 'initialize con contraseña de aplicación: sesión del servidor MCP de Elements', init.text.slice(0, 300));
  let tools = await admin.tools();
  check(tools.length > 0 && !tools.some((t) => t.name.startsWith('digitalisimo-woo-')), `sin WooCommerce: ninguna herramienta de tienda (${tools.length} del constructor)`, tools.map((t) => t.name).join(','));

  start('S1 WooCommerce 11.2.1 activo, modo predeterminado');
  check((await probe('activate-woo')).active === true, 'WooCommerce activado');
  check((await probe('store')).store === true, 'tienda preparada (pasarela de prueba con clave aleatoria)');
  await probe('users', 'shop');
  check((await probe('ping')).woo === true, 'WooCommerce cargado en la petición siguiente');
  tools = await admin.tools();
  const ours = tools.filter((t) => t.name.startsWith('digitalisimo-woo-'));
  check(WOO_TOOLS.every((n) => ours.some((t) => t.name === n)) && ours.length === 7, 'las 7 herramientas aparecen con WooCommerce y modo ≠ off', ours.map((t) => t.name).join(','));
  check(!tools.some((t) => /^woocommerce[-_]/.test(t.name)), 'ninguna ability propia de WooCommerce en el servidor de Elements', tools.map((t) => t.name).join(','));
  for (const t of ours) {
    const schema = t.inputSchema || {};
    check(schema.additionalProperties === false && (schema.required || []).includes('action') && Array.isArray(schema.properties?.action?.enum), `${t.name}: entrada cerrada con action (enum)`, JSON.stringify(schema).slice(0, 300));
    check(!JSON.stringify(schema).includes('"post_id"'), `${t.name}: sin post_id`);
    const readonly = t.name === 'digitalisimo-woo-reports';
    check(t.annotations?.readOnlyHint === readonly && t.annotations?.destructiveHint === !readonly, `${t.name}: anotaciones (readOnly ${readonly})`, JSON.stringify(t.annotations));
    check(!/\bElementor\b/.test(`${t.title} ${t.description}`), `${t.name}: sin el nombre del constructor original`);
  }
  check(/sólo permite leer/.test(ours.find((t) => t.name === 'digitalisimo-woo-products')?.description || ''), 'la descripción dice que el sitio sólo permite leer');
  const abilities = (await probe('abilities')).abilities || [];
  check(abilities.includes('digitalisimo-woo/products'), `abilities registradas: ${abilities.filter((a) => a.startsWith('digitalisimo-woo/')).length} propias y ${abilities.filter((a) => a.startsWith('woocommerce/')).length} de WooCommerce (éstas no se exponen)`);

  start('S2 Modo read: lee, no escribe');
  let r = await admin.call('products', { action: 'list', per_page: 5 });
  okCall(r, 'products list en modo read');
  r = await admin.call('products', { action: 'create', data: { name: 'No debe existir', type: 'simple', regular_price: '1' } });
  check(r.isError && /Sólo lectura/.test(r.text) && /Rendimiento/.test(r.text) && /page=digitalisimo-elements-front#digitalisimo-woo-mcp/.test(r.text), 'create rechazado con un error que dice dónde se cambia el modo', r.text);
  r = await admin.call('reports', { action: 'sales', query: { period: 'month' } });
  okCall(r, 'reports sales en modo read');

  start('S3 Modo off');
  check((await probe('mode', 'off')).mode === 'off', 'modo off guardado');
  tools = await admin.tools();
  check(!tools.some((t) => t.name.startsWith('digitalisimo-woo-')) && tools.length > 0, 'modo off: ninguna herramienta de tienda (las del constructor siguen)', tools.map((t) => t.name).join(','));
  check((await probe('mode', 'write')).mode === 'write', 'modo write guardado');
  tools = await admin.tools();
  check(tools.filter((t) => t.name.startsWith('digitalisimo-woo-')).length === 7 && /permite escribir/.test(tools.find((t) => t.name === 'digitalisimo-woo-orders')?.description || ''), 'modo write: las 7, y la descripción lo dice');

  start('S4 Catálogo: categoría, atributo y términos');
  r = await admin.call('catalog', { action: 'create', resource: 'category', data: { name: 'Ropa MCP' } });
  okCall(r, 'categoría creada');
  const catId = r.data?.data?.id;
  r = await admin.call('catalog', { action: 'create', resource: 'attribute', data: { name: 'Talla MCP', slug: 'talla-mcp' } });
  okCall(r, 'atributo global creado');
  const attrId = r.data?.data?.id;
  for (const name of ['Chica', 'Grande']) okCall(await admin.call('catalog', { action: 'create', resource: 'attribute_term', attribute_id: attrId, data: { name } }), `término «${name}» del atributo`);
  r = await admin.call('catalog', { action: 'list', resource: 'attribute_term', attribute_id: attrId, fields: ['name'] });
  check(!r.isError && r.data.data.length === 2 && r.data.data.every((t) => Object.keys(t).sort().join() === 'id,name'), 'términos listados con fields (id y name)', r.text);
  r = await admin.call('catalog', { action: 'update', resource: 'category', term_id: catId, data: { description: 'Desde el MCP' } });
  check(!r.isError && r.data.data.description === 'Desde el MCP', 'categoría actualizada', r.text);
  r = await admin.call('catalog', { action: 'delete', resource: 'tag', term_id: 999999 });
  check(r.isError && /force:true/.test(r.text), 'delete de una etiqueta sin force: rechazado antes de WooCommerce (sin papelera)', r.text);

  start('S5 Producto variable con variaciones y stock');
  r = await admin.call('products', { action: 'create', data: { name: 'Camiseta MCP', type: 'variable', status: 'publish', categories: [{ id: catId }], attributes: [{ id: attrId, visible: true, variation: true, options: ['Chica', 'Grande'] }] } });
  okCall(r, 'producto variable creado');
  const prodId = r.data?.data?.id;
  check(r.data?.data?.type === 'variable' && r.data?.data?.categories?.[0]?.id === catId, 'tipo variable y en su categoría', r.text.slice(0, 300));
  r = await admin.call('products', { action: 'batch', resource: 'variation', product_id: prodId, data: { create: [
    { regular_price: '199.00', manage_stock: true, stock_quantity: 5, attributes: [{ id: attrId, option: 'Chica' }] },
    { regular_price: '219.00', manage_stock: true, stock_quantity: 3, attributes: [{ id: attrId, option: 'Grande' }] },
  ] } });
  okCall(r, 'dos variaciones creadas en un lote');
  const varIds = (r.data?.data?.create || []).map((v) => v.id);
  check(varIds.length === 2 && varIds.every(Boolean), 'lote con dos IDs', JSON.stringify(r.data?.data).slice(0, 300));
  check(r.data?.ok === true && r.data?.batch?.done === 2 && r.data?.batch?.failed === 0, 'el lote informa 2 hechos y 0 fallidos', JSON.stringify(r.data?.batch));
  // Revisión R2/R3: fields por elemento, error parcial y total, y product_id anidado en los elementos.
  r = await admin.call('products', { action: 'batch', resource: 'variation', product_id: prodId, fields: ['regular_price'], data: { update: [{ id: varIds[0], regular_price: '205.00', product_id: prodId }, { id: varIds[1], regular_price: '225.00' }] } });
  check(!r.isError && r.data.ok === true && r.data.data.update.length === 2 && r.data.data.update.every((v) => Object.keys(v).sort().join() === 'id,regular_price'), 'lote con fields: cada elemento recortado (id y regular_price), con el mismo product_id anidado quitado', r.text.slice(0, 400));
  r = await admin.call('products', { action: 'batch', resource: 'variation', product_id: prodId, data: { update: [{ id: varIds[0], regular_price: '199.00' }, { id: 999999, regular_price: '1.00' }] } });
  check(!r.isError && r.data.ok === false && r.data.partial === true && r.data.batch.done === 1 && r.data.batch.failed === 1 && r.data.batch.errors[0].index === 1 && /^woocommerce_rest_/.test(r.data.batch.errors[0].code), `lote parcial: ok false, 1 hecho y 1 fallido (${r.data?.batch?.errors?.[0]?.code})`, r.text.slice(0, 400));
  const partialCode = r.data?.batch?.errors?.[0]?.code;
  r = await admin.call('products', { action: 'batch', resource: 'variation', product_id: prodId, data: { update: [{ id: 999998, regular_price: '1.00' }] } });
  check(r.isError && /ningún elemento/.test(r.text), 'lote sin ningún elemento aplicado: error', r.text);
  r = await admin.call('products', { action: 'batch', resource: 'variation', product_id: prodId, data: { update: [{ id: varIds[0], product_id: prodId + 1000, regular_price: '1.00' }] } });
  check(r.isError && /product_id/.test(r.text), 'un product_id distinto dentro de un elemento del lote se rechaza', r.text);
  r = await admin.call('products', { action: 'update', resource: 'variation', product_id: prodId, variation_id: varIds[0], data: { stock_quantity: 7 } });
  check(!r.isError && r.data.data.stock_quantity === 7, 'stock de una variación actualizado', r.text.slice(0, 300));
  r = await admin.call('products', { action: 'list', resource: 'variation', product_id: prodId, fields: ['stock_quantity', 'price'] });
  check(!r.isError && r.data.total === 2 && r.data.data.map((v) => v.stock_quantity).sort().join() === '3,7', 'variaciones listadas con su stock (total 2)', r.text.slice(0, 300));
  r = await admin.call('products', { action: 'get', product_id: prodId, fields: ['variations', 'type'] });
  check(!r.isError && r.data.data.variations.length === 2, 'el producto tiene sus dos variaciones', r.text.slice(0, 300));
  r = await admin.call('products', { action: 'delete', resource: 'variation', product_id: prodId, variation_id: varIds[1] });
  check(r.isError && /force:true/.test(r.text), 'borrar una variación sin force: rechazado (WooCommerce no tiene papelera para variaciones)', r.text);
  r = await admin.call('products', { action: 'batch', data: { delete: [123456] } });
  check(r.isError && /force:true/.test(r.text), 'un lote con delete exige force:true', r.text);
  r = await admin.call('products', { action: 'get', product_id: '12/../../wp/v2/users' });
  check(r.isError, 'un ID con recorrido de ruta no llega a ninguna ruta', r.text);
  r = await admin.call('products', { action: 'get', product_id: prodId, route: '/wp/v2/users' });
  check(r.isError, 'un parámetro «route» se rechaza (entrada cerrada)', r.text);

  start('S6 Cupón');
  r = await admin.call('coupons', { action: 'create', data: { code: 'otono-mcp', discount_type: 'percent', amount: '10' } });
  okCall(r, 'cupón creado');
  const couponId = r.data?.data?.id;
  r = await admin.call('coupons', { action: 'update', coupon_id: couponId, data: { amount: '15' } });
  check(!r.isError && Number(r.data.data.amount) === 15, 'cupón actualizado', r.text.slice(0, 300));
  r = await admin.call('coupons', { action: 'list', query: { code: 'otono-mcp' } });
  check(!r.isError && r.data.data.length === 1, 'cupón encontrado por código', r.text.slice(0, 300));

  start('S7 Cliente');
  r = await admin.call('customers', { action: 'create', data: { email: 'clara.mcp@example.test', first_name: 'Clara', last_name: 'Prueba', username: 'clara-mcp', password: randomBytes(12).toString('hex'), billing: { city: 'Puebla', phone: '2220000000' } } });
  okCall(r, 'cliente creado');
  const customerId = r.data?.data?.id;
  r = await admin.call('customers', { action: 'update', customer_id: customerId, data: { billing: { city: 'Mérida' } } });
  check(!r.isError && r.data.data.billing.city === 'Mérida', 'cliente actualizado', r.text.slice(0, 300));
  r = await admin.call('customers', { action: 'list', resource: 'download', customer_id: customerId });
  check(!r.isError && Array.isArray(r.data.data), 'descargas del cliente', r.text.slice(0, 300));
  r = await admin.call('customers', { action: 'delete', customer_id: customerId });
  check(r.isError && /force:true/.test(r.text), 'borrar un cliente sin force: rechazado (los usuarios no tienen papelera)', r.text);

  start('S8 Pedido: creado, estado, nota y reembolso manual');
  r = await admin.call('orders', { action: 'create', data: { customer_id: customerId, payment_method: 'bacs', payment_method_title: 'Transferencia', status: 'pending', billing: { first_name: 'Clara', email: 'clara.mcp@example.test' }, line_items: [{ product_id: prodId, variation_id: varIds[0], quantity: 2 }] } });
  okCall(r, 'pedido creado');
  const orderId = r.data?.data?.id;
  check(Number(r.data?.data?.total) > 0, `total del pedido ${r.data?.data?.total}`);
  for (const status of ['processing', 'completed']) {
    r = await admin.call('orders', { action: 'update', order_id: orderId, data: { status } });
    check(!r.isError && r.data.data.status === status, `pedido en «${status}»`, r.text.slice(0, 300));
  }
  r = await admin.call('orders', { action: 'create', resource: 'note', order_id: orderId, data: { note: 'Nota desde el MCP', customer_note: false } });
  okCall(r, 'nota del pedido');
  const noteId = r.data?.data?.id;
  r = await admin.call('orders', { action: 'list', resource: 'note', order_id: orderId });
  check(!r.isError && r.data.data.some((n) => n.id === noteId), 'nota listada', r.text.slice(0, 300));
  r = await admin.call('orders', { action: 'create', resource: 'refund', order_id: orderId, data: { amount: '10.00', reason: 'Prueba MCP' } });
  okCall(r, 'reembolso manual registrado (api_refund false por defecto: con Transferencia, true fallaría)');
  const refundId = r.data?.data?.id;
  check((await probe('post', String(orderId))).refunded === 10, 'el pedido tiene 10.00 reembolsados');
  r = await admin.call('orders', { action: 'create', resource: 'refund', order_id: orderId, api_refund: true, data: { amount: '1.00' } });
  check(r.isError, 'api_refund:true explícito llega a WooCommerce (la pasarela no devuelve dinero automáticamente: error)', r.text);
  r = await admin.call('orders', { action: 'list', resource: 'refund', order_id: orderId });
  check(!r.isError && r.data.data.length === 1 && r.data.data[0].id === refundId, 'reembolsos listados', r.text.slice(0, 300));
  r = await admin.call('orders', { action: 'delete', resource: 'note', order_id: orderId, note_id: noteId, force: true });
  check(!r.isError && r.data.deleted === 'permanent', 'nota borrada con force', r.text.slice(0, 300));

  start('S9 Papelera y borrado definitivo');
  r = await admin.call('products', { action: 'create', data: { name: 'Producto a borrar MCP', type: 'simple', regular_price: '5' } });
  const trashId = r.data?.data?.id;
  r = await admin.call('products', { action: 'delete', product_id: trashId });
  check(!r.isError && r.data.deleted === 'trash' && (await probe('post', String(trashId))).post_status === 'trash', 'delete manda el producto a la papelera', r.text.slice(0, 300));
  r = await admin.call('products', { action: 'delete', product_id: trashId, force: true });
  check(!r.isError && r.data.deleted === 'permanent' && (await probe('post', String(trashId))).post_status === false, 'force:true lo borra para siempre', r.text.slice(0, 300));
  r = await admin.call('coupons', { action: 'delete', coupon_id: couponId });
  check(!r.isError && (await probe('post', String(couponId))).post_status === 'trash', 'cupón a la papelera', r.text.slice(0, 300));
  r = await admin.call('orders', { action: 'delete', order_id: orderId });
  check(!r.isError && (await probe('post', String(orderId))).order_status === 'trash', 'pedido a la papelera', r.text.slice(0, 300));

  start('S10 Ajustes de la tienda');
  r = await admin.call('store-settings', { action: 'list', resource: 'setting_group', fields: ['label'] });
  check(!r.isError && r.data.data.some((g) => g.id === 'general'), 'grupos de ajustes', r.text.slice(0, 300));
  r = await admin.call('store-settings', { action: 'update', resource: 'setting', group_id: 'general', setting_id: 'woocommerce_store_city', data: { value: 'Guadalajara' } });
  check(!r.isError && (await probe('option', 'woocommerce_store_city')).value === 'Guadalajara', 'ajuste de la tienda cambiado', r.text.slice(0, 300));
  // Revisión R3: group_id anidado en un lote de ajustes.
  r = await admin.call('store-settings', { action: 'batch', resource: 'setting', group_id: 'general', data: { update: [{ id: 'woocommerce_store_city', group_id: 'general', value: 'Monterrey' }] } });
  check(!r.isError && r.data.ok === true && (await probe('option', 'woocommerce_store_city')).value === 'Monterrey', 'lote de ajustes con el mismo group_id anidado (se quita): aplicado', r.text.slice(0, 300));
  r = await admin.call('store-settings', { action: 'batch', resource: 'setting', group_id: 'general', data: { update: [{ id: 'woocommerce_store_city', group_id: 'products', value: 'Toluca' }] } });
  check(r.isError && /group_id/.test(r.text) && (await probe('option', 'woocommerce_store_city')).value === 'Monterrey', 'lote de ajustes con otro group_id anidado: rechazado, nada cambia', r.text);
  r = await admin.call('store-settings', { action: 'create', resource: 'shipping_zone', data: { name: 'México MCP' } });
  okCall(r, 'zona de envío creada');
  const zoneId = r.data?.data?.id;
  r = await admin.call('store-settings', { action: 'update', resource: 'shipping_zone_location', zone_id: zoneId, data: { locations: [{ code: 'MX', type: 'country' }] } });
  check(!r.isError && r.data.data.length === 1 && r.data.data[0].code === 'MX', 'ubicaciones de la zona', r.text.slice(0, 300));
  r = await admin.call('store-settings', { action: 'create', resource: 'shipping_zone_method', zone_id: zoneId, data: { method_id: 'flat_rate' } });
  okCall(r, 'método de envío añadido a la zona');
  const instanceId = r.data?.data?.instance_id;
  r = await admin.call('store-settings', { action: 'update', resource: 'shipping_zone_method', zone_id: zoneId, instance_id: instanceId, data: { settings: { cost: '50' } } });
  check(!r.isError && r.data.data.settings.cost.value === '50', 'costo del método actualizado', r.text.slice(0, 300));
  r = await admin.call('store-settings', { action: 'delete', resource: 'shipping_zone', zone_id: zoneId });
  check(r.isError && /force:true/.test(r.text), 'borrar una zona sin force: rechazado', r.text);
  r = await admin.call('store-settings', { action: 'delete', resource: 'shipping_zone', zone_id: zoneId, force: true });
  check(!r.isError && r.data.deleted === 'permanent', 'zona borrada con force', r.text.slice(0, 300));
  r = await admin.call('store-settings', { action: 'create', resource: 'tax_rate', data: { country: 'MX', rate: '16.0000', name: 'IVA MCP' } });
  okCall(r, 'tasa de impuesto creada');
  r = await admin.call('store-settings', { action: 'delete', resource: 'tax_rate', rate_id: r.data?.data?.id, force: true });
  okCall(r, 'tasa de impuesto borrada con force');
  r = await admin.call('store-settings', { action: 'create', resource: 'tax_class', data: { name: 'Reducida MCP' } });
  okCall(r, 'clase de impuesto creada');
  r = await admin.call('store-settings', { action: 'delete', resource: 'tax_class', tax_class: r.data?.data?.slug, force: true });
  okCall(r, 'clase de impuesto borrada por su slug con force');

  start('S11 Pasarela con clave enmascarada');
  const key = secrets()['gateway_key:1'];
  const tok = secrets()['gateway_token:1'];
  r = await admin.call('store-settings', { action: 'get', resource: 'payment_gateway', gateway_id: 'digi_wm_test' });
  check(!r.isError && r.data.data.settings.api_key.masked === true && r.data.data.settings.api_key.value !== '' && r.data.data.settings.identity_token.masked === true, 'la clave y el token salen enmascarados', r.text.slice(0, 300));
  check(!r.raw.includes(key) && !r.raw.includes(tok), 'ni la clave ni el token aparecen en la respuesta');
  r = await admin.call('store-settings', { action: 'list', resource: 'payment_gateway' });
  check(!r.isError && !r.raw.includes(key) && !r.raw.includes(tok), 'tampoco en la lista de pasarelas');
  check(r.data.page === undefined && r.data.per_page === undefined && r.data.total_pages === undefined && r.data.total === r.data.data.length, `lista de pasarelas sin paginar: sin page ni total_pages, total ${r.data.total}`, JSON.stringify({ page: r.data.page, total: r.data.total, total_pages: r.data.total_pages }));
  const page2 = await admin.call('store-settings', { action: 'list', resource: 'payment_gateway', page: 2 });
  check(page2.isError && /no se pagina/.test(page2.text), 'page 2 de una lista que WooCommerce no pagina: rechazada con un motivo claro', page2.text);
  const mask = r.data.data.find((g) => g.id === 'digi_wm_test').settings.api_key.value;
  r = await admin.call('store-settings', { action: 'update', resource: 'payment_gateway', gateway_id: 'digi_wm_test', data: { title: 'Pago renombrado', settings: { api_key: mask, identity_token: '', title: 'Pago renombrado' } } });
  let state = await probe('secret');
  check(!r.isError && state.key_sha256 === sha(key) && state.token_sha256 === sha(tok) && /2 claves/.test(r.data.notice || ''), 'reenviar la máscara o vacío conserva las claves guardadas', r.text.slice(0, 300));
  check(r.data.data.title === 'Pago renombrado', 'y el título sí cambia');
  const fresh = `sk_test_${randomBytes(12).toString('hex')}`;
  r = await admin.call('store-settings', { action: 'update', resource: 'payment_gateway', gateway_id: 'digi_wm_test', data: { settings: { api_key: fresh } } });
  state = await probe('secret');
  check(!r.isError && state.key_sha256 === sha(fresh) && !r.raw.includes(fresh), 'escribir una clave nueva sí se permite y tampoco se devuelve', r.text.slice(0, 300));

  start('S12 Informes y bitácora');
  for (const action of ['list', 'sales', 'top_sellers', 'orders_totals', 'products_totals', 'customers_totals', 'coupons_totals', 'reviews_totals']) {
    okCall(await admin.call('reports', { action, ...(action === 'sales' || action === 'top_sellers' ? { query: { period: 'year' } } : {}) }), `informe ${action}`);
  }
  r = await admin.call('reports', { action: 'log', per_page: 100 });
  check(!r.isError && r.data.total >= 20 && r.data.data.every((e) => e.user > 0 && e.tool && e.action && e.result), `bitácora con ${r.data?.total} escrituras`, r.text.slice(0, 300));
  const raw = JSON.stringify((await probe('log')).log);
  for (const needle of ['clara', 'Clara', 'Prueba MCP', 'Puebla', 'Mérida', '2220000000', 'Nota desde', 'Guadalajara', 'Camiseta', fresh, key, tok, '10.00', 'example.test']) {
    check(!raw.includes(needle), `la bitácora no lleva «${needle.startsWith('sk_') || needle.startsWith('tok_') ? 'la clave' : needle}»`);
  }
  check(/"result":"denied"/.test(raw) && /"code":"read_mode"/.test(raw), 'la escritura rechazada en modo read quedó como «denied»');
  check(raw.includes(`"result":"partial","code":"${partialCode}"`) && /"result":"error","code":"woocommerce_rest_/.test(raw), `el lote parcial quedó como «partial» (${partialCode}) y el fallido como «error», con su código real`);
  const auto = (await probe('option', 'autoload')).autoload || [];
  check(auto.length >= 2 && auto.every((row) => ['no', 'off', 'auto-off'].includes(row.autoload)), 'bitácora y modo sin carga automática', JSON.stringify(auto));

  start('S13 Permisos: gerente sí, editora y suscriptora no');
  const gerente = new Client('gerente');
  await gerente.init();
  okCall(await gerente.call('products', { action: 'create', data: { name: 'Del gerente', type: 'simple', regular_price: '3' } }), 'el gerente de tienda crea un producto');
  okCall(await gerente.call('orders', { action: 'list' }), 'el gerente lista pedidos');
  r = await gerente.call('customers', { action: 'delete', customer_id: customerId, force: true });
  check(r.isError && /delete_users/.test(r.text), 'el gerente no borra clientes (delete_users, como en WooCommerce)', r.text);
  for (const login of ['editora', 'suscriptora']) {
    const client = new Client(login);
    const { res } = await client.init();
    if (res.status !== 200 || !client.session) { check(true, `${login}: ni siquiera abre sesión en el MCP (HTTP ${res.status})`); continue; }
    for (const [tool, args] of [['products', { action: 'list' }], ['catalog', { action: 'list', resource: 'review' }], ['orders', { action: 'list' }], ['reports', { action: 'sales' }], ['store-settings', { action: 'list', resource: 'payment_gateway' }]]) {
      r = await client.call(tool, args);
      check(r.isError && !r.raw.includes(key), `${login}: ${tool} ${args.action}${args.resource ? ` ${args.resource}` : ''} rechazado`, r.text);
    }
  }

  start('S14 De vuelta a read');
  await probe('mode', 'read');
  r = await gerente.call('coupons', { action: 'create', data: { code: 'no-debe', amount: '1' } });
  check(r.isError && /Sólo lectura/.test(r.text), 'modo read: el gerente tampoco escribe', r.text);
}

// =====================================================================================================================
async function multisite() {
  start('M0 Red: sitios 1 y 2 con tienda, 3 sin WooCommerce');
  const self = await probe('selftest');
  check(self.notice && self.doing_it_wrong, 'el detector de avisos de PHP del MCP de tienda funciona (aviso y _doing_it_wrong)', JSON.stringify(self));
  for (const sub of ['', 'dos/']) {
    check((await probe('activate-woo', '', sub)).active === true, `WooCommerce activo en /${sub}`);
    check((await probe('store', '', sub)).store === true, `tienda preparada en /${sub}`);
  }
  await probe('users', 'base');
  await probe('users', 'base', 'dos/');
  await probe('users', 'base', 'tres/');
  await probe('users', 'shop', 'dos/');
  check((await probe('ping', '', 'tres/')).woo === false, 'el sitio 3 no tiene WooCommerce');

  const one = new Client('admin', '', 1);
  const two = new Client('admin', 'dos/', 2);
  const three = new Client('admin', 'tres/', 3);
  for (const c of [one, two, three]) await c.init();
  const count = async (c) => (await c.tools()).filter((t) => t.name.startsWith('digitalisimo-woo-')).length;
  check(await count(one) === 7 && await count(two) === 7, 'sitios con tienda: 7 herramientas (modo heredado: read)');
  check(await count(three) === 0 && (await three.tools()).length > 0, 'sitio sin WooCommerce: ninguna herramienta de tienda');

  start('M1 Herencia del modo');
  await probe('netmode', 'write');
  await probe('mode', 'inherit', 'dos/');
  const mode1 = await probe('mode', 'read');
  check(mode1.mode === 'read' && mode1.origin === 'site', 'el sitio 1 personaliza «read» aunque la red diga write');
  let r = await two.call('products', { action: 'create', data: { name: 'Sólo en el sitio dos', type: 'simple', regular_price: '2' } });
  okCall(r, 'el sitio 2 hereda write de la red y crea un producto');
  r = await one.call('products', { action: 'create', data: { name: 'No debe existir', type: 'simple', regular_price: '2' } });
  check(r.isError && /Sólo lectura/.test(r.text) && /red/.test(r.text), 'el sitio 1 (read propio) rechaza la escritura', r.text);
  r = await one.call('products', { action: 'list', query: { search: 'Sólo en el sitio dos' } });
  check(!r.isError && r.data.data.length === 0, 'cada sitio expone su propia tienda: el producto del 2 no está en el 1', r.text.slice(0, 300));
  const gerente2 = new Client('gerente', 'dos/', 2);
  await gerente2.init();
  okCall(await gerente2.call('orders', { action: 'list' }), 'el gerente del sitio 2 usa su tienda');

  start('M2 La red apaga');
  await probe('netmode', 'off');
  check(await count(two) === 0, 'red en off: el sitio 2 (hereda) no ofrece herramientas de tienda');
  check(await count(one) === 7, 'el sitio 1 (read propio) las conserva');
  await probe('mode', 'inherit');
  check(await count(one) === 0, 'el sitio 1 vuelve a heredar: off');
}

try {
  writeFileSync(`${dir}/result.txt`, '');
  if (network) await multisite(); else await single();
} catch (error) {
  check(false, `excepción en ${section}`, error.stack || error);
}
writeFileSync(`${dir}/result.json`, JSON.stringify({ mode, failures, checks: results.length, results }, null, 1));
log(`${mode}: ${results.length - failures}/${results.length} comprobaciones en verde`);
process.exit(failures ? 1 : 0);
