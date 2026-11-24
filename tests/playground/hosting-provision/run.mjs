// Ensayo de punta a punta de la venta automática de DIGITALÍSIMO Hosting contra el Playground que levantó
// hosting-provision.sh: una compra real por la Store API de WooCommerce (la que usa el checkout de bloques) con el
// dominio en el carrito como lo agrega el formulario (?domain_name=), pago con cheque/transferencia que el administrador
// marca como pagado, la cola real de Action Scheduler y los servidores simulados de la sonda. Comprueba: cuenta de
// Webuzo con el paquete del producto, dominio registrado con los nameservers propios, correo de accesos, notas sin
// contraseña, idempotencia, parada por clave rechazada sin reintentos, precios de dominios con el FIX, renovación con
// pedido pagable y la pantalla de servicios. Escribe <dir>/result.txt y result.json; sale 1 si algo falla.
// Uso: node run.mjs <puerto> <host> <dir>
import http from 'node:http';
import { appendFileSync, readFileSync, writeFileSync } from 'node:fs';

const [port, host, dir] = process.argv.slice(2);
const token = readFileSync(`${dir}/probe/token.txt`, 'utf8').trim();
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
  const q = new URLSearchParams({ digi_hp: step, token, ...args }).toString();
  const res = await request('GET', `/?${q}`);
  if (res.status !== 200 || !res.json) throw new Error(`sonda ${step}: HTTP ${res.status} ${res.text.slice(0, 400)}`);
  return res.json;
};
const passwords = () => { try { return JSON.parse(readFileSync(`${dir}/probe/passwords.json`, 'utf8')); } catch { return {}; } };

// Una compra por la Store API: carrito propio (Cart-Token), dominio y plan, checkout con cheque.
async function buy(domainProduct, domain, hostingProduct, billing = {}) {
  const cart = await request('GET', '/?rest_route=/wc/store/v1/cart');
  const h = { Nonce: cart.headers.nonce, 'Cart-Token': cart.headers['cart-token'] };
  if (!h.Nonce || !h['Cart-Token']) throw new Error(`Store API sin Nonce/Cart-Token: ${cart.status} ${cart.text.slice(0, 300)}`);
  if (domainProduct) {
    const r = await request('POST', `/?rest_route=/wc/store/v1/cart/add-item&domain_name=${encodeURIComponent(domain)}`, { headers: h, body: { id: domainProduct, quantity: 1 } });
    if (r.status !== 201 && r.status !== 200) throw new Error(`add-item dominio: ${r.status} ${r.text.slice(0, 300)}`);
  }
  if (hostingProduct) {
    const r = await request('POST', '/?rest_route=/wc/store/v1/cart/add-item', { headers: h, body: { id: hostingProduct, quantity: 1 } });
    if (r.status !== 201 && r.status !== 200) throw new Error(`add-item hosting: ${r.status} ${r.text.slice(0, 300)}`);
  }
  const address = { first_name: 'Ana', last_name: 'López', company: '', address_1: 'Av. Juárez 100', address_2: '', city: 'Guadalajara', state: 'JA', postcode: '44100', country: 'MX', email: 'ana@cliente.test', phone: '33 1234 5678', ...billing };
  const co = await request('POST', '/?rest_route=/wc/store/v1/checkout', { headers: h, body: { billing_address: address, shipping_address: address, payment_method: 'cheque' } });
  if (co.status !== 200 || !co.json?.order_id) throw new Error(`checkout: ${co.status} ${co.text.slice(0, 500)}`);
  return co.json.order_id;
}
const notes = (s) => (s.order?.notes || []).map((n) => n.text).join('\n');
const calls = (s, api, act) => (s.calls || []).filter((c) => c.api === api && (!act || c.act === act || c.cmd === act));

try {
  start('Tienda');
  const store = await probe('store');
  const P = store.products;
  check(store.gateways.includes('cheque'), 'Tienda en MXN con cheque/transferencia, planes con su paquete de Webuzo y dominios', JSON.stringify(store));
  log(`   WooCommerce ${store.woocommerce}`);

  start('Compra pagada');
  const order1 = await buy(P.com, 'mitienda.com', P.starter);
  let s = await probe('state', { id: order1 });
  check(s.order.status === 'on-hold' && !s.calls.length, 'Pagado con cheque, el pedido queda en espera y no se aprovisiona nada todavía', s.order.status);
  check(s.order.items.some((i) => i.domain === 'mitienda.com' && i.product === P.com), 'El dominio del carrito queda en el pedido (dato «Dominio»)', JSON.stringify(s.order.items));
  await probe('pay', { id: order1 });
  s = await probe('state', { id: order1 });
  check(!s.calls.length, 'Marcarlo pagado no llama a ninguna API en esa petición (va a la cola)');
  const run1 = await probe('run');
  check(run1.ran >= 1, 'Action Scheduler corre el aprovisionamiento', JSON.stringify(run1));
  s = await probe('state', { id: order1 });
  const add = calls(s, 'webuzo', 'add_user');
  check(add.length === 1 && add[0].fields.plan === 'digitalisimo_starter' && add[0].fields.domain === 'mitienda.com' && add[0].fields.user === 'mitienda' && add[0].fields.apiuser === 'root' && !add[0].key_in_url, 'Webuzo: una cuenta con el paquete del producto, el dominio y el usuario derivado; la clave no va en la URL', JSON.stringify(add));
  check(s.users.mitienda?.pass_ok, 'La contraseña es fuerte y se confirma');
  const create = calls(s, 'namecheap', 'namecheap.domains.create');
  check(create.length === 1 && create[0].fields.RegistrantStateProvince === 'Jalisco' && create[0].fields.AuxBillingPhone === '+52.3312345678' && create[0].fields.TechEmailAddress === 'ana@cliente.test' && create[0].key_ok, 'Namecheap: dominio registrado con los datos de facturación del cliente', JSON.stringify(create[0]?.fields));
  check(s.dns['mitienda.com'] === 'ns1.digitalisimo.website,ns2.digitalisimo.website', 'El dominio apunta a ns1 y ns2.digitalisimo.website', JSON.stringify(s.dns));
  const pass = passwords().mitienda || '§';
  const mail = s.mails.find((m) => m.to === 'ana@cliente.test');
  check(mail && mail.subject.includes('mitienda.com') && mail.body.includes(pass) && mail.body.includes('https://webuzo.test:2003/'), 'El cliente recibe el correo de WooCommerce con su panel (2003), usuario y contraseña', mail?.subject);
  check(!notes(s).includes(pass) && !s.order.meta.includes(pass) && s.order.notes.every((n) => !n.customer), 'Las notas son privadas y ni ellas ni la meta del pedido llevan la contraseña');
  check(s.order.status === 'completed' && s.service.status === 'active' && s.service.webuzo_user === 'mitienda' && s.service.has_pass, 'Pedido completado y servicio activo (contraseña sólo cifrada en el servicio)', JSON.stringify(s.service));

  start('Idempotencia');
  const before = s.calls.length;
  await probe('pay', { id: order1 }); await probe('run', { all: 1 });
  s = await probe('state', { id: order1 });
  check(s.calls.length === before && s.mails.filter((m) => m.to === 'ana@cliente.test').length === 1, 'Volver a marcarlo pagado no llama a nadie ni reenvía el correo');

  start('Precios de dominios');
  const prices = await probe('prices');
  s = await probe('state');
  const mx = prices.entry?.rows?.find((r) => r.ext === '.mx');
  check(prices.entry?.status === 'ok' && prices.entry.fx === 18.5 && mx?.base_usd === 58.98 && mx?.price === 1470 && s.prices['.mx'] === '1470', '.mx a 1470 MXN sin IVA con el FIX de Banxico (costo base = renovación 58.98 USD)', JSON.stringify(prices.entry));
  check(calls(s, 'banxico').every((c) => c.token_ok), 'El token de Banxico viaja en la cabecera');
  await probe('fake', { banxico_down: 1 });
  const p2 = await probe('prices');
  check(p2.entry?.status === 'ok' && p2.entry.fx === 18.6 && p2.entry.fx_source.startsWith('Frankfurter'), 'Banxico caído: respaldo Frankfurter', JSON.stringify(p2.entry));
  await probe('fake', { banxico_down: 0 }); await probe('prices');

  start('Costo que subió');
  await probe('fake', { mx_register: 70 });
  const cheap = await buy(P.mx, 'carisimo.mx', 0);
  await probe('pay', { id: cheap }); await probe('run');
  s = await probe('state', { id: cheap });
  check(s.order.status === 'on-hold' && !calls(s, 'namecheap', 'namecheap.domains.create').some((c) => c.fields.DomainName === 'carisimo.mx') && notes(s).includes('El costo real de registrar carisimo.mx subió'), 'Un .mx cuyo costo real ya no deja el margen mínimo queda en espera sin registrarse', notes(s));
  await probe('fake', { mx_register: 23.98 });

  start('Clave de Webuzo rechazada');
  await probe('badkey');
  const order2 = await buy(P.com, 'segundo.com', P.starter);
  await probe('pay', { id: order2 }); await probe('run', { all: 1 });
  s = await probe('state', { id: order2 });
  check(s.bad_keys === 1 && s.order.status === 'on-hold' && s.block && Object.keys(s.block).length === 1, 'Una sola llamada con la clave rechazada, sin reintentos; pedido en espera y parada guardada', `${s.bad_keys} ${s.order.status}`);
  check(s.mails.some((m) => m.to === 'admin@tienda.test' && m.subject.includes('Webuzo rechazó la clave')), 'El administrador recibe el aviso');
  const order3 = await buy(P.com, 'tercero.com', P.starter);
  await probe('pay', { id: order3 }); await probe('run', { all: 1 });
  s = await probe('state', { id: order3 });
  check(s.bad_keys === 1 && s.order.status === 'on-hold', 'Otro pedido pagado no vuelve a llamar a Webuzo mientras dure la parada', s.bad_keys);
  await probe('key'); await probe('run', { all: 1 });
  s = await probe('state', { id: order3 });
  const s2 = await probe('state', { id: order2 });
  check(!s.block && s.order.status === 'completed' && s2.order.status === 'completed' && s.bad_keys === 1, 'Con la clave nueva se levanta la parada y los dos pedidos se entregan', `${s.order.status} ${s2.order.status}`);

  start('Renovación');
  const svc = (await probe('state', { id: order1 })).order.service;
  await probe('daily', { svc, offset: -29 });
  s = await probe('state', { svc });
  const renewal = Number(s.service.renewal_order);
  const r = await probe('state', { id: renewal });
  check(renewal && r.order.status === 'pending' && r.order.renewal_of === svc && r.order.items.length === 2, 'A 29 días se crea el pedido de renovación pendiente de pago con el plan y el dominio', JSON.stringify(r.order));
  const reminder = r.mails.filter((m) => m.to === 'ana@cliente.test' && m.subject.includes('vence en 30 días'));
  check(reminder.length === 1 && reminder[0].body.includes('order-pay'), 'El cliente recibe el aviso con el enlace de pago de WooCommerce', reminder[0]?.subject);
  await probe('daily', { svc, offset: 8 }); await probe('run');
  s = await probe('state', { svc });
  check(s.service.status === 'suspended' && s.users.mitienda.status === 'suspended', 'Pasados los 7 días de gracia sin pago, la cuenta se suspende en Webuzo', JSON.stringify(s.service));
  const expires = Number(s.service.expires);
  await probe('pay', { id: renewal }); await probe('run');
  s = await probe('state', { id: renewal });
  check(calls(s, 'namecheap', 'namecheap.domains.renew').length === 1 && s.users.mitienda.status === '' && s.order.status === 'completed', 'Al pagar: dominio renovado en Namecheap, cuenta reactivada y pedido completado', notes(s));
  const after = await probe('state', { svc });
  check(Number(after.service.expires) > expires + 360 * 86400 && after.service.status === 'active', 'Un año más de servicio');

  start('Pantalla de servicios');
  const screen = await probe('screen');
  check(screen.html.includes('mitienda.com') && screen.html.includes('digitalisimo_starter') && screen.html.includes('digitalisimo_hosting_service_suspend_') && screen.html.includes('window.confirm') && screen.html.includes('Precios de dominios'), 'La lista muestra los servicios con sus acciones (nonce y confirmación) y el historial de precios');
  check(!screen.html.includes(pass), 'Sin contraseñas en la pantalla');
} catch (e) {
  check(false, 'El ensayo terminó', e.stack || e.message);
}
writeFileSync(`${dir}/result.json`, JSON.stringify({ checks: results.length, failures, results }, null, 1));
log(failures ? `${failures} fallas` : `Venta automática en Playground: ${results.length} comprobaciones correctas.`);
process.exit(failures ? 1 : 0);
