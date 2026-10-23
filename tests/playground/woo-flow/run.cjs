// Escenarios de woo-flow.sh (etapa F4-Woo, V.16.4) y $OUT/summary.json. Lee $ENV_FILE (lo escribe woo-flow.sh):
// prepara la tienda de cada sitio (`?digi_wf=setup`), comprueba que WooCommerce es el fijado y corre los escenarios de
// SCENARIOS (por defecto W0–W13). Cada escenario es una lista de comprobaciones con su estado: «ok», «falla» o
// «pendiente» (descrita, sin código hasta que existan los widgets de F4-Woo); el del escenario es «falla» si alguna
// falla, «pendiente» si ninguna está hecha, «parcial» si hay hechas y pendientes, y «ok» si están todas hechas y en
// verde. Con KEEP=1 sólo prepara las tiendas.
const fs = require('fs');
const path = require('path');
const http = require('http');

const ENV = JSON.parse(fs.readFileSync(process.env.ENV_FILE, 'utf8'));
const OUT = ENV.OUT;
const BASE = ENV.BASE.replace(/\/$/, '');
const PORT = +ENV.PORT;
const HOST = ENV.HOST;
const TOKEN = ENV.TOKEN;
const NET = ENV.NET === '1';
const QUICK = process.env.QUICK === '1';
const KEEP = process.env.KEEP === '1';
const ALL = ['W0', 'W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7', 'W8', 'W9', 'W10', 'W11', 'W12', 'W13'];
const SCENARIOS = process.env.SCENARIOS ? process.env.SCENARIOS.split(',').map((s) => s.trim()).filter(Boolean) : ALL;
const SITES = NET ? [{ id: 1, path: '/' }, { id: 2, path: '/dos/' }, { id: 3, path: '/tres/' }] : [{ id: 1, path: '/' }];
const WIDTHS = QUICK ? [1280] : [360, 768, 1280];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => { const line = a.join(' '); console.log(line); fs.appendFileSync(path.join(OUT, 'run.log'), line + '\n'); };
const readJson = (file, fallback = null) => { try { return JSON.parse(fs.readFileSync(file, 'utf8')); } catch (e) { return fallback; } };

for (const id of SCENARIOS) if (!ALL.includes(id)) { console.error(`Escenario desconocido: ${id} (válidos: ${ALL.join(', ')})`); process.exit(1); }

const summary = {
  harness: 'woo-flow',
  mode: ENV.MODE_LABEL,
  options: { core: ENV.CORE, net: NET, cache: ENV.CACHE === '1', theme: ENV.THEME, quick: QUICK, keep: KEEP, scenarios: SCENARIOS, widths: WIDTHS },
  git: ENV.GIT_SHA,
  elements: { dir: ENV.ELEMENTS_DIR, version: ENV.ELEMENTS_VERSION, sha: ENV.ELEMENTS_SHA, live: ENV.LIVE === '1' },
  woocommerce: readJson(path.join(OUT, 'woocommerce.json')),
  started: new Date(+ENV.STARTED * 1000).toISOString(),
  sites: {},
  scenarios: {},
};
const write = () => fs.writeFileSync(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 1) + '\n');

// ── HTTP al sitio (con su host) ───────────────────────────────────────────────────────────────────────────────────
function request(method, url, { headers = {}, body = null } = {}) {
  const u = new URL(url, BASE + '/');
  return new Promise((resolve, reject) => {
    const req = http.request({ host: '127.0.0.1', port: PORT, method, path: u.pathname + u.search, headers: { Host: HOST, ...headers }, timeout: 300000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch (e) { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${url}`)));
    if (body) req.write(body);
    req.end();
  });
}
const probe = async (site, op) => {
  const res = await request('GET', `${site.path}?digi_wf=${op}&token=${TOKEN}`);
  if (res.status !== 200 || !res.json) throw new Error(`?digi_wf=${op} en ${site.path}: ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
};

// ── Comprobaciones ────────────────────────────────────────────────────────────────────────────────────────────────
function scenario(id, title) {
  return { id, title, checks: [], problems: [], status: 'pendiente' };
}
function check(s, id, what, ok, detail = '') {
  s.checks.push({ id, what, status: ok ? 'ok' : 'falla', detail: ok ? detail || undefined : detail });
  if (!ok) s.problems.push(`${id} · ${what}${detail ? ': ' + detail : ''}`);
  return ok;
}
function pending(s, id, what) {
  s.checks.push({ id, what, status: 'pendiente' });
}
function settle(s) {
  const states = new Set(s.checks.map((c) => c.status));
  if (states.has('falla') || s.problems.length) s.status = 'falla';
  else if (!states.has('ok')) s.status = 'pendiente';
  else if (states.has('pendiente')) s.status = 'parcial';
  else s.status = 'ok';
}

// ── Chrome ─────────────────────────────────────────────────────────────────────────────────────────────────────
let browser = null;
async function startBrowser() {
  if (browser) return browser;
  const puppeteer = require('puppeteer-core');
  const target = HOST.split(':')[0];
  // Sólo el sitio de prueba existe: cualquier otra petición falla (nada sale a internet).
  const args = ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', `--host-resolver-rules=MAP ${target} 127.0.0.1:${PORT}, MAP * ~NOTFOUND`];
  browser = await puppeteer.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: 'new', args, protocolTimeout: 600000 });
  return browser;
}
/** Página nueva en un contexto propio (visitante sin cookies) que registra errores y peticiones fallidas. */
async function visitor() {
  const ctx = await (await startBrowser()).createBrowserContext();
  const page = await ctx.newPage();
  page.setDefaultTimeout(120000);
  page.setDefaultNavigationTimeout(180000);
  const facts = { errors: [], failed: [], outside: [], aborted: [] };
  page.on('console', (m) => { if (m.type() === 'error') facts.errors.push(m.text().slice(0, 300)); });
  page.on('pageerror', (e) => facts.errors.push(`pageerror: ${String(e.message || e).slice(0, 300)}`));
  page.on('requestfailed', (r) => {
    const url = r.url();
    const reason = r.failure() ? r.failure().errorText : 'falló';
    // Abortadas por una navegación o por WooCommerce (cada `update_order_review` cancela el anterior): no son errores.
    if (reason === 'net::ERR_ABORTED' && new URL(url).host === HOST) { facts.aborted.push(url.slice(0, 200)); return; }
    (new URL(url).host === HOST ? facts.failed : facts.outside).push(`${reason} ${url.slice(0, 200)}`);
  });
  page.on('response', (r) => { if (r.status() >= 400 && new URL(r.url()).host === HOST) facts.failed.push(`${r.status()} ${r.url().slice(0, 200)}`); });
  return { ctx, page, facts };
}
async function shot(page, name) {
  await page.screenshot({ path: path.join(OUT, 'shots', `${name}.png`), fullPage: true }).catch(() => {});
}
/** Espera a que WooCommerce termine sus peticiones AJAX (capas .blockUI de carrito y pago). */
async function settled(page, ms = 120000) {
  await sleep(300);
  await page.waitForFunction(() => !document.querySelector('.blockUI.blockOverlay, .processing'), { timeout: ms });
}

// ── Preparación de cada sitio ─────────────────────────────────────────────────────────────────────────────────────
async function prepare() {
  for (const site of SITES) {
    const entry = { path: site.path };
    try {
      entry.ping = await probe(site, 'ping');
      const t0 = Date.now();
      entry.setup = await probe(site, 'setup');
      entry.setup_ms = Date.now() - t0;
      entry.state = await probe(site, 'state');
    } catch (e) {
      entry.error = String(e.message || e);
    }
    summary.sites[site.path] = entry;
    log(`Tienda ${site.path}: ${entry.error ? 'FALLA ' + entry.error : (entry.setup.errors.length ? 'con errores: ' + entry.setup.errors.join('; ') : `lista en ${Math.round(entry.setup_ms / 1000)} s · WooCommerce ${entry.ping.woocommerce} · ${entry.state.products} productos`)}`);
  }
  write();
}

// ── W0 · Preparación y sin WooCommerce ────────────────────────────────────────────────────────────────────────────
async function W0(s) {
  const pin = summary.woocommerce || {};
  check(s, 'W0.1', 'WooCommerce es la copia fijada de reference/ (sha256 comprobada)', pin.source === 'reference' && pin.version === '11.2.1', JSON.stringify({ source: pin.source, version: pin.version }));
  for (const site of SITES) {
    const entry = summary.sites[site.path] || {};
    if (entry.error) { check(s, `W0.2 ${site.path}`, 'la tienda del sitio se prepara', false, entry.error); continue; }
    const ping = entry.ping || {};
    const state = entry.state || {};
    const setup = entry.setup || {};
    check(s, `W0.2 ${site.path}`, 'WooCommerce 11.2.1 cargado y con su base de datos al día, activo junto a DIGITALÍSIMO Elements', ping.woocommerce === pin.version && ping.wc_db === pin.version && ping.plugins.includes('woocommerce/woocommerce.php') && ping.plugins.includes('digitalisimo-elements/pro-elements.php'), JSON.stringify({ wc: ping.woocommerce, db: ping.wc_db, elementor: ping.elementor, elements: ping.elements, bundled: ping.bundled, theme: ping.theme }));
    check(s, `W0.3 ${site.path}`, 'preparación sin errores', !(setup.errors || []).length, (setup.errors || []).join('; '));
    check(s, `W0.4 ${site.path}`, 'catálogo: seis simples y un variable con seis variaciones; tabla de atributos activa con filas', state.products === 7 && setup.variations === 6 && state.options.woocommerce_attribute_lookup_enabled === 'yes' && state.lookup > 0, JSON.stringify({ products: state.products, variations: setup.variations, lookup: state.lookup, lookupEnabled: state.options && state.options.woocommerce_attribute_lookup_enabled }));
    const spain = (state.zones || []).find((z) => (z.locations || []).includes('ES'));
    const free = spain && spain.methods.find((m) => m.id === 'free_shipping');
    check(s, `W0.5 ${site.path}`, 'zona «España» con tarifa plana y «Envío gratuito» desde 50 €; zona 0 sin métodos', !!(spain && free && free.requires === 'min_amount' && free.min_amount === '50' && spain.methods.some((m) => m.id === 'flat_rate')) && (state.zones || []).some((z) => z.id === 0 && !z.methods.length), JSON.stringify(state.zones));
    check(s, `W0.6 ${site.path}`, 'cupones DIGI10 (10 %) y DIGIENVIO (envío gratuito); «Contra reembolso» activo', !!(state.coupons && state.coupons.digi10 && state.coupons.digi10.type === 'percent' && state.coupons.digienvio && state.coupons.digienvio.free_shipping && state.gateways && state.gateways.cod === true), JSON.stringify({ coupons: state.coupons, gateways: state.gateways }));
    try {
      await purchase(s, site, entry);
    } catch (e) {
      check(s, `W0.7 ${site.path}`, 'compra de prueba completa', false, String(e.stack || e.message || e).slice(0, 600));
    }
  }
  pending(s, 'W0.8', 'sin WooCommerce (v1 §9.3): con widgets F4-Woo en una página, desactivar WooCommerce → front sin salida ni errores, woo/* responde 404, el editor muestra el aviso y, tras guardar y reactivar, los ajustes de cada widget tienen el mismo md5');
  pending(s, 'W0.9', 'sin WooCommerce (V.16.4, sitio aparte en el puerto 9494): importar el mismo kit por la pantalla «Kits», por la App y por WP-CLI deja los mismos marcadores de K y el mismo informe');
}

// Compra de invitado: Camiseta (60 €) → carrito clásico con DIGI10 → pago clásico con «Envío gratuito» y «Contra
// reembolso» → pedido recibido; el pedido queda «procesando» con 54,00 €.
async function purchase(s, site, entry) {
  const { ctx, page, facts } = await visitor();
  const tag = `W0-${site.id}`;
  const urls = entry.setup.urls || {};
  const pages = entry.setup.pages || {};
  try {
    await page.setViewport({ width: 1280, height: 900 });
    await page.goto(urls['digi-wf-camiseta'], { waitUntil: 'load' });
    await page.waitForSelector('form.cart button[name="add-to-cart"], form.cart button.single_add_to_cart_button');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('form.cart button[name="add-to-cart"], form.cart button.single_add_to_cart_button')]);
    const added = await page.$eval('.woocommerce-message, .wc-block-components-notice-banner', (el) => el.textContent.trim()).catch(() => '');
    check(s, `W0.7a ${site.path}`, 'la ficha añade la Camiseta al carrito (formulario clásico)', /carrito|cart/i.test(added), added.slice(0, 160));

    await page.goto(pages['digi-wf-carrito'], { waitUntil: 'load' });
    await page.waitForSelector('#coupon_code');
    await page.type('#coupon_code', 'DIGI10');
    await page.click('button[name="apply_coupon"]');
    await page.waitForSelector('tr.cart-discount.coupon-digi10', { timeout: 120000 });
    await settled(page);
    check(s, `W0.7b ${site.path}`, 'el carrito clásico aplica DIGI10', true);

    await page.goto(pages['digi-wf-pago'], { waitUntil: 'load' });
    await page.waitForSelector('#billing_first_name');
    await settled(page);
    const fields = { '#billing_first_name': 'Ana', '#billing_last_name': 'Prueba', '#billing_address_1': 'Calle de Prueba 2', '#billing_city': 'Madrid', '#billing_postcode': '28001', '#billing_phone': '600000000', '#billing_email': 'ana.prueba@example.com' };
    for (const [selector, value] of Object.entries(fields)) {
      await page.$eval(selector, (el) => { el.value = ''; });
      await page.type(selector, value);
    }
    const country = await page.$eval('#billing_country', (el) => el.value).catch(() => '');
    if (await page.$('select#billing_state')) await page.select('select#billing_state', 'M');
    await page.$eval('#billing_postcode', (el) => el.dispatchEvent(new Event('change', { bubbles: true })));
    await settled(page);
    const free = await page.$('input.shipping_method[value^="free_shipping"]');
    if (free) {
      const type = await free.evaluate((el) => el.type);
      if (type === 'radio') { await free.click(); await settled(page); }
    }
    const cod = await page.$('#payment_method_cod');
    if (cod && await cod.evaluate((el) => el.type === 'radio' && !el.checked)) await cod.click();
    const review = await page.$eval('#order_review', (el) => el.textContent.replace(/\s+/g, ' ').trim()).catch(() => '');
    check(s, `W0.7c ${site.path}`, 'el pago clásico ofrece «Envío gratuito» y «Contra reembolso» (país de la tienda)', !!free && !!cod && country === 'ES', JSON.stringify({ country, free: !!free, cod: !!cod, review: review.slice(0, 200) }));
    await shot(page, `${tag}-pago`);
    await page.click('#place_order');
    await page.waitForFunction(() => /order-received/.test(location.href) || !!document.querySelector('.woocommerce-error, .woocommerce-NoticeGroup-checkout'), { timeout: 180000 });
    const landed = page.url();
    const notice = await page.$eval('.woocommerce-error, .woocommerce-NoticeGroup-checkout', (el) => el.textContent.replace(/\s+/g, ' ').trim()).catch(() => '');
    await page.waitForSelector('.woocommerce-order, .wc-block-order-confirmation-status, .woocommerce-thankyou-order-received', { timeout: 60000 }).catch(() => {});
    await shot(page, `${tag}-recibido`);
    check(s, `W0.7d ${site.path}`, 'la compra termina en «Pedido recibido»', /order-received/.test(landed), notice || landed);

    const orders = await probe(site, 'orders');
    const order = orders[0] || {};
    const want = { status: 'processing', payment: 'cod', shipping: ['free_shipping'], coupons: ['digi10'], discount: '6.00', total: '54.00', currency: 'EUR', country: 'ES', created: 'checkout' };
    const got = Object.fromEntries(Object.keys(want).map((k) => [k, order[k]]));
    check(s, `W0.7e ${site.path}`, 'el pedido queda «procesando», con contra reembolso, envío gratuito, DIGI10 y 54,00 €', JSON.stringify(got) === JSON.stringify(want), JSON.stringify({ got, items: order.items }));
    check(s, `W0.7f ${site.path}`, 'sin errores de JavaScript ni respuestas de error del sitio durante la compra', !facts.errors.length && !facts.failed.length, JSON.stringify({ errors: facts.errors.slice(0, 5), failed: facts.failed.slice(0, 5) }));
    check(s, `W0.7g ${site.path}`, 'ninguna petición sale del sitio', !facts.outside.length, facts.outside.slice(0, 5).join(' | '));
  } catch (e) {
    await shot(page, `${tag}-falla`);
    throw e;
  } finally {
    await ctx.close().catch(() => {});
  }
}

// ── W1–W13: descritos y pendientes hasta que existan los widgets de F4-Woo ──────────────────────────────────────
// Cada función declara sus comprobaciones (V.16.4; lo que V no cambia, de la v1 §9.3). Al implementarlas, cada
// `pending()` se sustituye por su `check()`; el resumen distingue lo hecho de lo pendiente.
async function W1(s) {
  pending(s, 'W1.1', '`localStorage` vacío antes del primer clic (nada se guarda al cargar)');
  pending(s, 'W1.2', 'guardar dos productos, recargar, abrir la página de la lista, quitar uno (el foco va a la ficha siguiente) y otra pestaña se sincroniza');
  pending(s, 'W1.3', 'ninguna petición lleva IDs de productos en la URL (registro de red de Chrome); las fichas se piden por POST');
  pending(s, 'W1.4', 'la cookie del sitio no cambia; con NET=1, el sitio 2 no ve la lista del sitio 3');
}
async function W2(s) {
  pending(s, 'W2.1', '«A invitado, B inicia sesión» sin «Añadir»: la cuenta de B no tiene nada de A; con «Añadir», sí (confirmación explícita)');
  pending(s, 'W2.2', 'con `product_wishlist` activa en la preparación: la lista va a la Store API (`shopper-lists`), la mudanza de una lista propia previa y un solo exportador con datos');
  pending(s, 'W2.3', '«Exportar datos personales» incluye la lista y «Borrar» la quita (cuatro campos del borrador)');
  pending(s, 'W2.4', 'con NET=1: quitar al usuario del sitio 3 desde la red borra la del 3 y no la del 1; un no miembro usa el modo de invitado');
}
async function W3(s) {
  pending(s, 'W3.1', 'caché de página simulada: Chrome intercepta la página del segundo visitante y le sirve el HTML del primero; los dos HTML se comparan byte a byte (sin nonces ni estado) en las páginas con los siete widgets');
  pending(s, 'W3.2', 'cada visitante ve su propio carrito y su propia lista tras los fragmentos');
  pending(s, 'W3.3', 'CACHE=1 (caché de elementos) aparte: el mismo resultado');
}
async function W4(s) {
  pending(s, 'W4.1', 'vista rápida con teclado y con ratón: foco al título propio, Tab y Mayús+Tab no salen del diálogo, Escape y el fondo cierran y el foco vuelve al botón');
  pending(s, 'W4.2', 'nombre accesible, `aria-haspopup`, `aria-busy` y región viva dentro del diálogo; `activeElement` nunca fuera');
  pending(s, 'W4.3', 'lo mismo en «Plantilla de producto»; Ctrl+clic y sin JS abren la ficha');
}
async function W5(s) {
  pending(s, 'W5.1', 'un variable en una página sin variables: parámetros, plantillas `tmpl-variation-*` una vez, carga en orden y `wc_variation_form`');
  pending(s, 'W5.2', 'elegir una combinación cambia precio e imagen; «Imágenes del producto» en la plantilla con galería funcional');
  pending(s, 'W5.3', 'añadir al carrito vuelve a la página de origen con redirección tras el POST (recargar no repite la compra), aviso en el Menú del carrito y en el carrito');
}
async function W6(s) {
  pending(s, 'W6.1', 'producto con 31 variaciones (modo AJAX): opciones quitadas por WooCommerce = radio deshabilitado, `required`, los radios no viajan en el POST');
  pending(s, 'W6.2', 'un atributo sin configurar deja el <select> con el mismo HTML que 4.3.0.164; «Limpiar»; el bucle enlaza con la preselección');
  pending(s, 'W6.3', 'CLS de la ficha con muestras, también con NET=1');
  pending(s, 'W6.4', 'THEME=block con `wc-visual` activa: el bloque pinta sus muestras y el widget clásico las suyas con los mismos colores');
}
async function W7(s) {
  pending(s, 'W7.1', 'con 30 € dice cuánto falta y WooCommerce no ofrece la tarifa; con 50 €, «Tienes envío gratis» y la tarifa ofrecida; el estado coincide siempre con las tarifas del carrito');
  pending(s, 'W7.2', 'mu-plugins que filtran `woocommerce_package_rates` y `woocommerce_shipping_free_shipping_is_available` (sin «Te faltan»); `min_amount` convertido por `…_instance_option`');
  pending(s, 'W7.3', 'dos paquetes, cliente sin país, `requires = ""` (sólo «done» con tarifa), `requires = coupon` y carrito vacío');
}
async function W8(s) {
  pending(s, 'W8.1', 'cupón válido, inválido (aviso en la región) y quitar (`remove_coupon` con `coupon`); totales, barra y panel se rehacen');
  pending(s, 'W8.2', 'escribir mientras otra pestaña cambia el carrito no pierde lo escrito ni el foco');
  pending(s, 'W8.3', 'nonce caducado (fecha simulada) con un solo reintento; cupones apagados → sin formulario');
}
async function W9(s) {
  pending(s, 'W9.1', 'summary.json anota `woocommerce_attribute_lookup_enabled`; con la tabla y sin ella, y con «Ocultar agotados» activo e inactivo, siempre el mismo conjunto que la tienda nativa');
  pending(s, 'W9.2', '`pa_talla-zapato`: recargar con la URL filtrada da el mismo conjunto que el AJAX, también sin JS');
  pending(s, 'W9.3', 'una URL con sólo `digi_price_min` en una página con el filtro de taxonomía de PRO en el mismo bucle no da error');
  pending(s, 'W9.4', 'dos categorías hijas con OR (`Hierarchy_Or_Query`); precio con orden por precio; 5 cambios en 200 ms → una petición; Atrás y Adelante');
}
async function W10(s) {
  pending(s, 'W10.1', 'el widget Productos con «Consulta actual» se filtra en la tienda con los parámetros nativos; «Limpiar» y paginación');
  pending(s, 'W10.2', 'un Loop Grid con «Consulta actual» no se filtra y el editor lo avisa');
}
async function W11(s) {
  pending(s, 'W11.1', 'producto desde la vista rápida → mini carrito con barra → cupón → pago con «Contra reembolso» → pedido recibido');
  pending(s, 'W11.2', 'con el pago de bloques (el que crea WooCommerce) y con la página `[woocommerce_checkout]`; con CORE=bundled y CORE=plugin');
}
async function W12(s) {
  pending(s, 'W12.1', '`GET woo/cards` 405; `POST woo/wishlist` con la función de WooCommerce activa 409; sin sesión 401 y con nonce falso 403');
  pending(s, 'W12.2', '`woo/quick-view` con un `widget_id` ajeno 403 y con un producto privado 404; `return` a otro dominio rechazada; ninguna escritura sin nonce');
}
async function W13(s) {
  pending(s, 'W13.1', 'con contenido de 4.3.0.165 se instala el ZIP de 4.3.0.164: sin fatales, los widgets no pintan nada, el bucle con precio sale vacío y las muestras vuelven al <select>');
  pending(s, 'W13.2', 'abrir y guardar en el editor de 4.3.0.164 borra los widgets; de vuelta en 4.3.0.165, restaurar la revisión anterior los devuelve con el mismo md5 de ajustes; se anota si sobrevivieron las claves nuevas de los widgets de PRO');
  pending(s, 'W13.3', 'con 4.3.0.165 y un widget `digitalisimo-*` de una versión futura (tipo no registrado), abrir y guardar lo conserva en el marcador');
}
const RUN = { W0, W1, W2, W3, W4, W5, W6, W7, W8, W9, W10, W11, W12, W13 };
const TITLES = {
  W0: 'Preparación y sin WooCommerce', W1: 'Lista de deseos de invitado', W2: 'Lista con sesión', W3: 'Caché de página', W4: 'Vista rápida accesible',
  W5: 'Vista rápida y compra', W6: 'Muestras', W7: 'Barra de envío', W8: 'Cupón en el mini carrito', W9: 'Filtros en el Loop Grid', W10: 'Filtros en la tienda',
  W11: 'Compra en modo de prueba', W12: 'Seguridad', W13: 'Vuelta atrás',
};

(async () => {
  const t0 = Date.now();
  log(`woo-flow · modo ${summary.mode} · ${SITES.length} sitio(s) · escenarios ${KEEP ? '(KEEP=1: sólo la preparación)' : SCENARIOS.join(',')}`);
  await prepare();
  if (KEEP) {
    summary.ms = Date.now() - t0;
    write();
    process.exit(Object.values(summary.sites).some((e) => e.error || (e.setup && e.setup.errors.length)) ? 1 : 0);
  }
  for (const id of SCENARIOS) {
    const s = scenario(id, TITLES[id]);
    const started = Date.now();
    try {
      await RUN[id](s);
    } catch (e) {
      s.problems.push(`excepción: ${String(e.stack || e.message || e).slice(0, 600)}`);
    }
    const fatal = path.join(OUT, 'fatal.log');
    if (fs.existsSync(fatal) && fs.statSync(fatal).size) s.problems.push(`fatal.log: ${fs.readFileSync(fatal, 'utf8').slice(0, 400)}`);
    s.ms = Date.now() - started;
    settle(s);
    summary.scenarios[id] = s;
    const done = s.checks.filter((c) => c.status !== 'pendiente').length;
    log(`${id} ${s.title}: ${s.status} (${done} hechas, ${s.checks.length - done} pendientes) en ${Math.round(s.ms / 1000)} s${s.problems.length ? '\n   - ' + s.problems.slice(0, 8).join('\n   - ') : ''}`);
    write();
  }
  if (browser) await browser.close().catch(() => {});
  summary.ms = Date.now() - t0;
  write();
  const failed = Object.values(summary.scenarios).filter((s) => s.status === 'falla');
  log(failed.length ? `\n${failed.length} escenario(s) fallan: ${failed.map((s) => s.id).join(', ')}` : `\nSin fallos (${Object.values(summary.scenarios).map((s) => `${s.id} ${s.status}`).join(' · ')}).`);
  process.exit(failed.length ? 1 : 0);
})().catch((e) => { console.error(e); try { write(); } catch (err) { /* sin resumen */ } process.exit(1); });
