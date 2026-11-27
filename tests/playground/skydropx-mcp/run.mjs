// Ensayo de punta a punta de `digitalisimo-woo/skydropx` contra el Playground que levantó skydropx-mcp.sh: WooCommerce
// 11.2.1 y Skydropx 2.1.5 reales, la plataforma simulada por la sonda (pre_http_request, sin red) y JSON-RPC real contra
// /wp-json/elementor/mcp con contraseñas de aplicación. La pantalla del pedido y la descarga de la guía se abren como
// un navegador con la sesión del administrador (cookies de wp-login.php). Escribe <dir>/result.txt y result.json; sale 1
// si algo falla. Ningún secreto (contraseñas, clave del plugin, token de cotización, secretos de webhooks) se escribe.
// Uso: node run.mjs <puerto> <host> <dir> <posts|hpos>
import http from 'node:http';
import { appendFileSync, readFileSync, writeFileSync } from 'node:fs';

const [port, host, dir, mode = 'posts'] = process.argv.slice(2);
const hpos = mode === 'hpos';
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

function request(method, path, { headers = {}, body, form } = {}) {
  return new Promise((resolve, reject) => {
    let data;
    const all = { Host: host, ...headers };
    if (form !== undefined) { data = new URLSearchParams(form).toString(); all['Content-Type'] = 'application/x-www-form-urlencoded'; }
    else if (body !== undefined) { data = JSON.stringify(body); all['Content-Type'] = 'application/json'; }
    if (data !== undefined) all['Content-Length'] = Buffer.byteLength(data);
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => {
        const raw = Buffer.concat(chunks);
        const text = raw.toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch { /* no JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, raw, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}
const probe = async (step, value = '') => {
  const res = await request('GET', `/?digi_sk=${step}&token=${token}&value=${encodeURIComponent(value)}`);
  if (res.status !== 200) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
};
const local = (url) => { const u = new URL(url); return u.pathname + u.search; };

class Client {
  constructor(login) { this.login = login; this.id = 0; this.session = ''; }
  auth() { return `Basic ${Buffer.from(`${this.login}:${secrets()[`password:${this.login}`]}`).toString('base64')}`; }
  async post(payload) {
    const headers = { Authorization: this.auth(), Accept: 'application/json, text/event-stream', 'MCP-Protocol-Version': '2025-06-18' };
    if (this.session) headers['Mcp-Session-Id'] = this.session;
    return request('POST', `/?rest_route=${encodeURIComponent('/elementor/mcp')}`, { headers, body: payload });
  }
  async rpc(method, params = {}) {
    const res = await this.post({ jsonrpc: '2.0', id: ++this.id, method, params });
    if (!res.json) throw new Error(`${method}: respuesta no JSON (HTTP ${res.status}) ${res.text.slice(0, 300)}`);
    return res;
  }
  async init() {
    const res = await this.rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'digi-skydropx-e2e', version: '1.0.0' } });
    this.session = res.headers['mcp-session-id'] || '';
    await this.post({ jsonrpc: '2.0', method: 'notifications/initialized' });
    return res;
  }
  async tools() { return (await this.rpc('tools/list', {})).json?.result?.tools || []; }
  async call(args, tool = 'skydropx') {
    const res = await this.rpc('tools/call', { name: `digitalisimo-woo-${tool}`, arguments: args });
    const result = res.json?.result;
    const error = res.json?.error;
    const text = (result?.content || []).map((c) => c.text || '').join('\n');
    let data = result?.structuredContent;
    if (!data && text) { try { data = JSON.parse(text); } catch { data = null; } }
    if (data && data.result !== undefined && data.ok === undefined) data = data.result;
    const out = { isError: !!(result?.isError || error), error, text: error ? JSON.stringify(error) : text, data, raw: res.text };
    appendFileSync(`${dir}/responses.jsonl`, `${JSON.stringify({ args, out: out.raw.slice(0, 4000) })}\n`);
    return out;
  }
}
const okCall = (r, what) => check(!r.isError && r.data?.ok === true, what, r.text);

// Navegador con la sesión del administrador.
const cookies = new Map();
const keep = (res) => { for (const value of [].concat(res.headers?.['set-cookie'] || [])) { const [pair] = value.split(';'); const [name, ...rest] = pair.split('='); cookies.set(name.trim(), rest.join('=')); } return res; };
const cookie = () => [...cookies].map(([name, value]) => `${name}=${value}`).join('; ');

async function main() {
  start('S0 Preparación');
  check((await probe('selftest')).notice, 'el detector de avisos de PHP funciona');
  let ping = await probe('ping');
  check(ping.ready && ping.woo && !ping.sky, `WooCommerce ${ping.wc} activo, Skydropx todavía no (WordPress ${ping.wp}, PHP ${ping.php}, HPOS ${ping.hpos})`, JSON.stringify(ping));
  await probe('users');
  const admin = new Client('admin');
  const init = await admin.init();
  check(init.status === 200 && admin.session, 'initialize con contraseña de aplicación', init.text.slice(0, 300));
  let tools = await admin.tools();
  check(tools.filter((t) => t.name.startsWith('digitalisimo-woo-')).length === 7 && !tools.some((t) => t.name === 'digitalisimo-woo-skydropx'), 'sin Skydropx: las 7 herramientas de tienda y no la de Skydropx', tools.map((t) => t.name).join(','));
  const act = await probe('activate-sky');
  check(act.active, 'Skydropx 2.1.5 activado', JSON.stringify(act));
  check((await probe('hpos', hpos ? 'yes' : 'no')).hpos === (hpos ? 'yes' : 'no'), hpos ? 'HPOS activo (pedidos en sus tablas) y webhooks por Action Scheduler, como en una tienda real' : 'pedidos como entradas y webhooks entregados en la misma petición');
  const store = await probe('store');
  const P = store.products; const O = store.orders;
  check(P.complete && P.zero && P.grande && O.a && O.b && O.c, 'tienda con productos y pedidos de prueba', JSON.stringify(store));
  check((await probe('link')).linked === true, 'la «plataforma» vincula la tienda: clave del plugin, cotización con su URL y su zona, y webhooks de pedidos');
  ping = await probe('ping');
  check(ping.sky === '2.1.5' && ping.hpos === hpos, `Skydropx cargado; HPOS ${ping.hpos}`, JSON.stringify(ping));

  start('S1 La herramienta aparece con Skydropx');
  tools = await admin.tools();
  const tool = tools.find((t) => t.name === 'digitalisimo-woo-skydropx');
  check(tool && tools.filter((t) => t.name.startsWith('digitalisimo-woo-')).length === 8, 'con Skydropx: 8 herramientas de tienda, la suya incluida', tools.map((t) => t.name).join(','));
  const schema = tool?.inputSchema || {};
  check(schema.additionalProperties === false && schema.required?.includes('action') && schema.properties?.action?.enum?.length === 9, 'entrada cerrada con nueve acciones', JSON.stringify(schema).slice(0, 300));
  check(tool?.annotations?.readOnlyHint === false && tool?.annotations?.destructiveHint === true, 'anotaciones como las otras que escriben', JSON.stringify(tool?.annotations));
  check(/sólo permite leer/.test(tool?.description || '') && /a mano/.test(tool?.description || ''), 'la descripción dice el modo (sólo lectura) y que crear envíos se hace a mano');

  start('S2 status');
  let r = await admin.call({ action: 'status' });
  okCall(r, 'status');
  const st = r.data?.data || {};
  check(st.plugin?.version === '2.1.5' && st.plugin?.missing?.length === 0, 'plugin 2.1.5 sin piezas faltantes', JSON.stringify(st.plugin));
  check(st.linked === true && st.quotation?.enabled === true && st.quotation?.url_host === 'cotiza.skydropx.test' && st.quotation?.https === true, 'vinculada, cotización activa; de la URL sólo el host', JSON.stringify(st.quotation));
  check(st.zone?.found && st.zone?.first && st.zone?.method?.enabled && st.zone?.locations?.some((l) => l.code === 'MX'), 'zona Skydropx primera, con México y el método activo', JSON.stringify(st.zone));
  check(st.units?.weight === 'kg' && st.units?.dimension === 'cm' && st.origin?.complete === true && st.origin?.postcode === '44100', 'unidades y origen completo', JSON.stringify({ units: st.units, origin: st.origin }));
  check(st.webhooks?.active === 2 && JSON.stringify(st.webhooks?.topics) === '["order.created","order.updated"]', 'webhooks de la plataforma: 2 activos (sin su URL)', JSON.stringify(st.webhooks));
  check(st.permalinks?.pretty === true && JSON.stringify(st.tracking_meta) === '["Tracking Number","Tracking Company","Tracking URL"]', 'enlaces permanentes y metas de rastreo del plugin', JSON.stringify(st.tracking_meta));
  check((r.data?.warnings || []).length === 0, 'sin avisos en una tienda bien vinculada', JSON.stringify(r.data?.warnings));

  start('S3 Auditar productos');
  r = await admin.call({ action: 'audit_products', per_page: 50 });
  okCall(r, 'audit_products');
  const ids = (r.data?.data || []).map((p) => p.id).sort((a, b) => a - b);
  check(JSON.stringify(ids) === JSON.stringify([P.zero, P.grande].sort((a, b) => a - b)), 'encuentra el producto con peso 0 y sin medidas y la variación sin peso propio ni del padre (no el virtual ni la variación que hereda)', JSON.stringify(r.data?.data));
  const zero = (r.data?.data || []).find((p) => p.id === P.zero);
  check(JSON.stringify(zero?.missing) === '["weight","length","width","height"]' && zero?.fix?.product_id === P.zero, 'qué le falta y cómo corregirlo', JSON.stringify(zero));
  const grande = (r.data?.data || []).find((p) => p.id === P.grande);
  check(JSON.stringify(grande?.missing) === '["weight"]' && grande?.parent_id === P.variable && grande?.fix?.variation_id === P.grande, 'la variación hereda las medidas del padre y sólo le falta el peso', JSON.stringify(grande));
  check(r.data?.total === 2 && r.data?.summary?.skipped_virtual === 1 && r.data?.summary?.scanned === 5, 'totales: 5 revisados (3 simples y 2 variaciones), 1 virtual omitido', JSON.stringify(r.data?.summary));

  start('S4 Corregir productos');
  // Acceso total (2026-11-27): con «Sólo lectura» guardado de antes, escribe igual (antes se rechazaba).
  r = await admin.call({ action: 'update_products', items: [{ product_id: P.zero, weight: 2 }] });
  okCall(r, '«Sólo lectura» guardado: update_products escribe igual');
  await probe('mode', 'write');
  r = await admin.call({ action: 'update_products', items: [{ product_id: P.zero, weight: 1500, length: 300, width: 100, height: 50 }, { product_id: P.variable, variation_id: P.grande, weight: 750 }], weight_unit: 'g', dimension_unit: 'mm' });
  okCall(r, 'update_products en gramos y milímetros');
  const pz = await probe('product', P.zero);
  const pg = await probe('product', P.grande);
  check(pz.weight === '1.5' && pz.length === '30' && pz.width === '10' && pz.height === '5' && pg.weight === '0.75', 'guardados en kg y cm de la tienda por la REST de WooCommerce', JSON.stringify({ pz, pg }));
  r = await admin.call({ action: 'update_products', items: [{ product_id: P.zero, weight: 0 }] });
  check(r.isError && /mayor que 0/.test(r.text), 'un peso en cero se rechaza', r.text);
  r = await admin.call({ action: 'update_products', items: Array.from({ length: 101 }, (_, i) => ({ product_id: P.zero + i, weight: 1 })) });
  check(r.isError && /100/.test(r.text), 'un lote de 101 se rechaza (tope 100)', r.text);
  r = await admin.call({ action: 'audit_products' });
  check(!r.isError && r.data?.total === 0, 'la auditoría queda en cero', r.text.slice(0, 300));

  start('S5 Cotizar con el método del plugin');
  const before = await probe('cart-meta');
  const quoteArgs = { action: 'quote', address: { postcode: '06700', state: 'DF', city: 'Ciudad de México' }, cart: [{ product_id: P.complete, quantity: 2 }, { product_id: P.variable, variation_id: P.grande }, { product_id: P.virtual }] };
  r = await admin.call(quoteArgs);
  okCall(r, 'quote');
  let platform = await probe('platform');
  const q1 = (platform.quotes || []).at(-1);
  check((platform.quotes || []).length === 1 && q1?.to === '06700' && q1?.from === '44100', 'el plugin cotizó una vez con la plataforma (destino y origen)', JSON.stringify(platform.quotes));
  check(JSON.stringify(q1?.items?.map((i) => [i.quantity, i.weight, i.length])) === JSON.stringify([[2, 1.2, 20], [1, 0.75, 30]]), 'el plugin mandó peso (kg) y medidas (cm) de cada artículo, sin el virtual y con la herencia de la variación', JSON.stringify(q1?.items));
  check(q1?.total === 1149, 'y el total del carrito (2 × 300 + 450 + 99)', JSON.stringify(q1?.total));
  const rates = r.data?.data?.rates || [];
  check(rates.length === 2 && rates[0].provider === 'fedex' && rates[0].cost === 167.25 && rates[1].provider === 'dhl' && rates[0].currency === 'MXN' && r.data?.data?.from_cache === false, 'tarifas resumidas del plugin, de la más barata a la más cara', JSON.stringify(rates));
  check(r.data?.data?.zone?.matches === true && /virtual/.test((r.data?.warnings || []).join(' ')), 'la zona del cliente tiene Skydropx; avisa del producto virtual', JSON.stringify(r.data?.warnings));
  r = await admin.call(quoteArgs);
  platform = await probe('platform');
  check(!r.isError && r.data?.data?.from_cache === true && (platform.quotes || []).length === 1, 'la segunda vez sale de la caché del plugin (sin otra petición)', r.text.slice(0, 300));
  r = await admin.call({ ...quoteArgs, cache: false });
  platform = await probe('platform');
  check(!r.isError && r.data?.data?.from_cache === false && (platform.quotes || []).length === 2, 'cache:false vuelve a cotizar', r.text.slice(0, 300));
  const after = await probe('cart-meta');
  check(after.persistent_carts === before.persistent_carts && after.sessions === before.sessions, 'ningún carrito guardado ni sesión de cliente creados o tocados', JSON.stringify({ before, after }));
  r = await admin.call({ action: 'quote', address: { postcode: 'C1000', country: 'AR' }, cart: [{ product_id: P.complete }] });
  check(!r.isError && r.data?.data?.zone?.matches === false && /no vería/.test((r.data?.warnings || []).join(' ')), 'una dirección fuera de la zona de Skydropx: avisa que el cliente no vería esas tarifas', r.text.slice(0, 400));

  start('S6 Envíos');
  r = await admin.call({ action: 'shipments', statuses: ['processing'], per_page: 20 });
  okCall(r, 'shipments');
  const rows = r.data?.data || [];
  const rowA = rows.find((o) => o.id === O.a); const rowB = rows.find((o) => o.id === O.b);
  check(rowA && rowB && !rows.some((o) => o.id === O.c) && rowA.has_tracking === false && rowB.has_tracking === true && rowB.tracking?.number === 'SKY-777' && rowB.label === true, 'pedidos en proceso con su rastreo (B) y sin él (A)', JSON.stringify(rows));
  r = await admin.call({ action: 'shipments', statuses: ['processing'], tracking_filter: 'without' });
  check(!r.isError && (r.data?.data || []).every((o) => !o.has_tracking) && (r.data?.data || []).some((o) => o.id === O.a), 'tracking_filter without', r.text.slice(0, 300));
  r = await admin.call({ action: 'get_shipment', order_id: O.b });
  check(!r.isError && r.data?.data?.label === true && r.data?.data?.destination?.postcode === '06700' && /page=wc-orders|post\.php/.test(r.data?.data?.edit_url || ''), 'get_shipment: guía del plugin, destino y enlace al pedido', r.text.slice(0, 400));

  start('S7 Rastreo de un envío hecho a mano');
  const orderBefore = await probe('order', O.a);
  r = await admin.call({ action: 'set_tracking', order_id: O.a, tracking: { number: 'MANUAL-123', company: 'Estafeta', url: 'https://rastreo.test/MANUAL-123' }, order_status: 'completed', customer_note: 'Tu pedido va en camino con Estafeta.' });
  okCall(r, 'set_tracking');
  check(r.data?.data?.label === 'manual' && /Guía generada/.test(r.data?.notice || ''), 'un rastreo hecho a mano queda como «manual» y la respuesta explica la caja del plugin', r.text.slice(0, 400));
  if (hpos) check((await probe('run-queue')).ran >= 1, 'Action Scheduler entrega el webhook encolado');
  const orderAfter = await probe('order', O.a);
  const meta = orderAfter.lines?.[0]?.meta || {};
  check(meta['Tracking Number'] === 'MANUAL-123' && meta['Tracking Company'] === 'Estafeta' && meta['Tracking URL'] === 'https://rastreo.test/MANUAL-123', 'las tres metas que lee el plugin, en la línea de envío', JSON.stringify(orderAfter.lines));
  check(orderAfter.status === 'completed' && orderAfter.customer_notes.some((n) => /Estafeta/.test(n)), 'estado completado y nota al cliente', JSON.stringify({ status: orderAfter.status, notes: orderAfter.customer_notes }));
  check(orderAfter.total === orderBefore.total && orderAfter.total_tax === orderBefore.total_tax && orderAfter.shipping_total === orderBefore.shipping_total, 'los totales del pedido no cambian (no se recalcula nada)', JSON.stringify({ before: orderBefore.total, after: orderAfter.total }));
  platform = await probe('platform');
  check((platform.webhooks || []).some((w) => w.topic === 'order.updated' && w.order === O.a && w.tracking === 'MANUAL-123'), 'WooCommerce avisó a la plataforma (webhook order.updated con el rastreo nuevo)', JSON.stringify(platform.webhooks));
  const cBefore = await probe('order', O.c);
  r = await admin.call({ action: 'set_tracking', order_id: O.c, tracking: { number: 'X-1', company: 'DHL', url: 'https://rastreo.test/X-1' } });
  check(r.isError && /no tiene línea de envío/.test(r.text) && /create_shipping_line/.test(r.text), 'un pedido sin línea de envío falla con un mensaje claro (no toca lo que ve el cliente)', r.text);
  check((await probe('order', O.c)).lines.length === 0, 'y el pedido sigue sin línea de envío');
  r = await admin.call({ action: 'set_tracking', order_id: O.c, tracking: { number: 'X-1', company: 'DHL', url: 'https://rastreo.test/X-1' }, create_shipping_line: true });
  const cAfter = await probe('order', O.c);
  check(!r.isError && r.data?.data?.created_line === true && cAfter.lines.length === 1 && cAfter.lines[0].method_id === 'digitalisimo_manual' && cAfter.total === cBefore.total && /el cliente verá/.test(r.data?.notice || ''), 'con create_shipping_line: una línea sin costo, los totales iguales y el aviso de lo que verá el cliente', r.text.slice(0, 400));
  // Permiso por pedido (como la REST): un rol que edita pedidos pero no los ajenos no puede tocar uno ajeno.
  const almacen = new Client('almacen');
  await almacen.init();
  const restDenied = await almacen.call({ action: 'update', order_id: O.b, data: { customer_note: 'x' } }, 'orders');
  r = await almacen.call({ action: 'set_tracking', order_id: O.b, tracking: { number: 'AJENO-1' } });
  const bNow = await probe('order', O.b);
  check(restDenied.isError && r.isError && /permiso sobre el pedido/.test(r.text) && bNow.lines[0].meta['Tracking Number'] === 'SKY-777', 'Almacén (sin edit_others_shop_orders): orders update y set_tracking rechazan el pedido ajeno; nada escrito', `${restDenied.text.slice(0, 160)} · ${r.text.slice(0, 200)}`);
  r = await almacen.call({ action: 'resync', order_id: O.b });
  check(r.isError && /permiso sobre el pedido/.test(r.text), 'Almacén: resync tampoco', r.text.slice(0, 200));

  start('S8 La pantalla del pedido del plugin y la guía');
  keep(await request('POST', '/wp-login.php', { headers: { Cookie: 'wordpress_test_cookie=WP%20Cookie%20check' }, form: { log: 'admin', pwd: 'password', 'wp-submit': 'Log In', testcookie: '1', redirect_to: '/wp-admin/' } }));
  check([...cookies.keys()].some((n) => n.startsWith('wordpress_logged_in_')), 'sesión de administrador en el navegador');
  const screen = ping.hpos ? `/wp-admin/admin.php?page=wc-orders&action=edit&id=${O.a}` : `/wp-admin/post.php?post=${O.a}&action=edit`;
  const page = await request('GET', screen, { headers: { Cookie: cookie() } });
  check(page.status === 200 && /Etiqueta de envío Skydropx/.test(page.text) && /Guía generada/.test(page.text) && /Imprimir guía/.test(page.text), 'la caja del plugin en la pantalla del pedido dice «Guía generada» con «Imprimir guía»', `${page.status} ${page.text.length}`);
  check(/MANUAL-123/.test(page.text) && /Estafeta/.test(page.text), 'el rastreo se ve en la línea de envío del pedido');
  r = await admin.call({ action: 'label', order_id: O.a });
  check(r.isError && /a mano/.test(r.text) && /Guía generada/.test(r.text), 'label de un rastreo hecho a mano: no ofrece un PDF que no existe', r.text.slice(0, 300));
  r = await admin.call({ action: 'label', order_id: O.b });
  okCall(r, 'label de una guía de Skydropx');
  const labelUrl = r.data?.data?.url || '';
  check(/admin-post\.php\?action=digitalisimo_woo_skydropx_label&order_id=\d+&exp=\d+&sig=[0-9a-f]{20}$/.test(labelUrl) && !/_wpnonce|skydropx_print_label/.test(r.text), 'label: un enlace propio firmado para este usuario, sin nonce ni enlace del plugin en la respuesta', r.text.slice(0, 300));
  const before3 = ((await probe('platform')).labels || []).length;
  const cross = await request('GET', local(labelUrl.replace(/&exp=.*$/, '')), { headers: { Cookie: cookie() } });
  const after3 = ((await probe('platform')).labels || []).length;
  check(cross.status === 200 && /Descargar la guía/.test(cross.text) && after3 === before3, 'sin la firma (lo que pondría otra página), con la sesión abierta: sólo una página con un botón; nada se pide a Skydropx', `${cross.status} ${before3}→${after3}`);
  const hop = await request('GET', local(labelUrl), { headers: { Cookie: cookie() } });
  const loc = hop.headers.location || '';
  check(hop.status === 302 && /admin-post\.php\?action=skydropx_print_label&order_id=\d+&_wpnonce=[0-9a-f]{10}$/.test(loc), 'abierto con la sesión: lleva al endpoint del plugin con el nonce de esa sesión', `${hop.status} ${loc}`);
  const pdf = await request('GET', local(loc), { headers: { Cookie: cookie() } });
  check(pdf.status === 200 && /application\/pdf/.test(pdf.headers['content-type'] || '') && pdf.raw.subarray(0, 5).toString() === '%PDF-', 'el plugin descarga el PDF de la guía', `${pdf.status} ${pdf.headers['content-type']} ${pdf.text.slice(0, 80)}`);
  platform = await probe('platform');
  const lab = (platform.labels || []).at(-1);
  check(lab?.order === O.b && lab?.signed === true && lab?.valid === true, 'el plugin pidió la guía firmada con su clave (firma válida)', JSON.stringify(platform.labels));
  const anon = await request('GET', local(labelUrl));
  check(anon.status === 302 && /wp-login\.php/.test(anon.headers.location || ''), 'sin sesión, el enlace lleva al acceso', `${anon.status} ${anon.headers.location}`);
  r = await admin.call({ action: 'label', order_id: O.c });
  // C recibió arriba un rastreo a mano (create_shipping_line): no hay guía de Skydropx y la herramienta lo dice.
  check(r.isError && /a mano/.test(r.text) && /pedido \d+/.test(r.text), 'label del pedido C (rastreo a mano): no ofrece un PDF que no existe', r.text);

  start('S9 Reenviar un pedido a Skydropx');
  const countBefore = (platform.webhooks || []).filter((w) => w.order === O.b && w.topic === 'order.updated').length;
  const modBefore = (await probe('order', O.b)).modified;
  r = await admin.call({ action: 'resync', order_id: O.b });
  okCall(r, 'resync');
  if (hpos) check((await probe('run-queue')).ran >= 1, 'Action Scheduler entrega el webhook encolado por resync');
  platform = await probe('platform');
  const countAfter = (platform.webhooks || []).filter((w) => w.order === O.b && w.topic === 'order.updated').length;
  check(countAfter === countBefore + 1, 'WooCommerce entregó el webhook order.updated del pedido a la plataforma', JSON.stringify(platform.webhooks?.slice(-3)));
  check(!r.data?.warnings && r.data?.data?.webhooks?.active === 2, 'resync ve el webhook order.updated de la plataforma (sin aviso)', JSON.stringify(r.data?.data?.webhooks));
  const orderB = await probe('order', O.b);
  check(orderB.modified >= modBefore && orderB.lines?.[0]?.meta?.['Tracking Number'] === 'SKY-777' && orderB.status === 'processing', 'fecha de modificación al día; el resto del pedido igual', JSON.stringify(orderB));

  start('S10 Permisos, bitácora y modos');
  const editora = new Client('editora');
  await editora.init();
  const etools = await editora.tools();
  r = await editora.call({ action: 'status' });
  check(r.isError && /permiso/.test(r.text), `una editora no puede usarla (${etools.length} herramientas listadas)`, r.text.slice(0, 300));
  const gerente = new Client('gerente');
  await gerente.init();
  r = await gerente.call({ action: 'shipments' });
  okCall(r, 'un gerente de tienda sí');
  const entries = (await probe('log')).log || [];
  const ours = entries.filter((e) => e.tool === 'skydropx');
  check(['update_products', 'set_tracking', 'resync'].every((a) => ours.some((e) => e.action === a && e.result === 'ok')) && !ours.some((e) => e.code === 'read_mode'), 'bitácora: cada escritura y ningún rechazo por modo', JSON.stringify(ours.slice(0, 6)));
  check(!/MANUAL|Estafeta|camino|1500|SKY-777/.test(JSON.stringify(entries)), 'bitácora sin valores (rastreo, nota ni medidas)');
  await probe('mode', 'off');
  tools = await admin.tools();
  check(tools.some((t) => t.name === 'digitalisimo-woo-skydropx'), '«Apagado» guardado: la herramienta de Skydropx sigue');
  await probe('mode', 'write');
  check((await probe('deactivate-sky')).active === false, 'Skydropx desactivado');
  tools = await admin.tools();
  check(!tools.some((t) => t.name === 'digitalisimo-woo-skydropx') && tools.filter((t) => t.name.startsWith('digitalisimo-woo-')).length === 7, 'sin Skydropx la herramienta desaparece y las otras siete siguen', tools.map((t) => t.name).join(','));
  if (!hpos) return;

  start('S11 audit_products con 5 000 productos y variaciones');
  check((await probe('activate-sky')).active, 'Skydropx activado de nuevo');
  let expected = 0; let made = 0;
  for (const batch of ['simple-1', 'simple-2', 'simple-3', 'simple-4', 'simple-5', 'simple-6', 'variable-7', 'variable-8', 'variable-9', 'variable-10']) {
    const b = await probe('bulk', batch);
    expected += b.missing; made += b.made;
  }
  check(made === 5000, `5 000 productos y variaciones de volumen (${expected} sin peso)`, String(made));
  const t0 = Date.now();
  r = await admin.call({ action: 'audit_products', per_page: 50 });
  const m1 = (await probe('requests')).last;
  const page1 = r.data || {};
  check(!r.isError && page1.truncated === true && page1.summary?.scanned === 5000 && page1.cached === false && page1.data?.length === 50, `página 1: 5 000 revisados en orden de ID con next_after_id (${Date.now() - t0} ms en el cliente)`, r.text.slice(0, 300));
  log(`medida · página 1: ${m1?.ms} ms, ${m1?.queries} consultas, ${m1?.peak_mb} MB pico`);
  const t1 = Date.now();
  r = await admin.call({ action: 'audit_products', per_page: 50, page: 2 });
  const m2 = (await probe('requests')).last;
  check(!r.isError && r.data?.cached === true && r.data?.data?.length === 50 && r.data?.total === page1.total, `página 2 desde el recorrido de la 1 (${Date.now() - t1} ms en el cliente)`, r.text.slice(0, 200));
  // Un recorrido nuevo carga los 5 000 elementos otra vez: al menos la memoria pico de la página 1. Las consultas no sirven
  // de criterio con HPOS (el trabajo de fondo de WooCommerce, Action Scheduler y sincronización, varía de una petición a
  // otra); la página 2 sale de la caché (cached arriba) y con al menos un 15 % menos de memoria.
  check(m2 && m1 && m2.peak_mb < m1.peak_mb * 0.85, `la página 2 no vuelve a recorrer: ${m2?.peak_mb} MB y ${m2?.queries} consultas frente a ${m1?.peak_mb} MB y ${m1?.queries}`);
  log(`medida · página 2: ${m2?.ms} ms, ${m2?.queries} consultas, ${m2?.peak_mb} MB pico`);
  r = await admin.call({ action: 'audit_products', per_page: 50, after_id: page1.next_after_id });
  const m3 = (await probe('requests')).last;
  check(!r.isError && !r.data?.truncated && page1.total + r.data?.total === expected, `after_id sigue con el resto: ${page1.total} + ${r.data?.total} = ${expected} sin peso`, r.text.slice(0, 200));
  log(`medida · after_id: ${m3?.ms} ms, ${m3?.queries} consultas, ${m3?.peak_mb} MB pico`);
  check(m1.queries / 5000 < 4, `consultas por elemento en la página 1: ${(m1.queries / 5000).toFixed(2)} (términos y metas precargados por bloque)`);
}

try {
  await main();
} catch (error) {
  check(false, 'el ensayo terminó sin excepción', error.stack || error.message);
}
writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, results }, null, 1));
log(failures ? `${failures} fallas de ${results.length}` : `${results.length} comprobaciones en verde`);
process.exit(failures ? 1 : 0);
