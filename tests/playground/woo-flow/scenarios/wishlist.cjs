// Escenarios W1, W2 y W12 de woo-flow.sh (F4-Woo, frente B: lista de deseos; V.4, V.16.4 y V.17 B1, B2, 5, 6, 7 y 26).
// La preparación (setup/wishlist.php) deja en cada sitio «Guardar» (un botón por producto, el variable incluido, y el
// contador), «Lista» (el widget «Lista de deseos» con «Vaciar lista») y «Vista» (una vista rápida y una lista), un
// producto privado y clientes de prueba; las operaciones (ops/wishlist.php) dan sesiones reales, el estado de cada
// almacén, la función `product_wishlist` de WooCommerce y las solicitudes de privacidad.
//
// - W1 · invitado: nada en `localStorage` antes del primer clic; guardar dos, recargar, la lista, quitar uno (el foco va a
//   la ficha siguiente) y la otra pestaña se sincroniza; ninguna petición con IDs en la URL (registro de red de Chrome)
//   y fichas por POST; la lista no escribe cookies; en red, el sitio 2 no ve la lista del sitio 3.
// - W2 · con sesión: «A invitado, B inicia sesión» sin «Añadir» (nada llega a la cuenta) y con «Añadir» (sí), y
//   «Descartar»; exportar y borrar con las acciones AJAX reales de las herramientas de privacidad; con la lista de
//   WooCommerce encendida: Store API, mudanza de la lista propia, el variable sin botón, un solo exportador con datos y
//   la fusión de un invitado; en red, quitar al usuario del sitio 3 (pantalla de la red y desde otro sitio) y un no
//   miembro en modo invitado.
// - W12 · seguridad: `GET woo/cards` 405; `woo/wishlist` 409 con la función de WooCommerce, 401 sin sesión, 403 con un
//   nonce falso y 401 sin nonce (sin escribir); `woo/quick-view` 403 con un `widget_id` ajeno, 404 con un producto
//   privado y `return` a otro dominio rechazada; ninguna ruta `woo/*` escribe sin nonce; respuestas sin caché.
'use strict';

module.exports = (api) => {
  const { BASE, HOST, TOKEN, NET, SITES, WIDTHS, summary, sleep, log, request, check, visitor, shot } = api;
  const T = {
    saved: 'Guardado en tu lista de deseos',
    removed: 'Quitado de tu lista de deseos',
    added: 'Añadidos a tu lista de deseos',
    discarded: 'Se borró la lista de este navegador.',
    ask1: 'Tienes 1 producto guardado en este navegador. ¿Añadirlo a tu lista?',
    ask: (n) => `Tienes ${n} productos guardados en este navegador. ¿Añadirlos a tu lista?`,
    erased: 'Se borró la lista de deseos de este sitio.',
  };
  const OWN_GROUP = 'digitalisimo-elements-wishlist';
  const WOO_GROUP = 'woocommerce-shopper-lists-wishlist';
  const WAIT = { timeout: 45000 };
  const short = (v, n = 700) => JSON.stringify(v).slice(0, n);
  const sorted = (a) => [...(a || [])].map(Number).sort((x, y) => x - y);
  const same = (a, b) => JSON.stringify(sorted(a)) === JSON.stringify(sorted(b));
  const wlOf = (site) => (((summary.sites[site.path] || {}).setup) || {}).wishlist || null;
  const siteById = (id) => SITES.find((x) => x.id === id);
  /** Duración de cada tramo de un escenario (en `timings` del resumen y en run.log). */
  const marks = new Map();
  function lap(s, label) {
    const now = Date.now();
    const last = marks.has(s) ? marks.get(s) : now;
    marks.set(s, now);
    if (label === null) return;
    (s.timings = s.timings || {})[label] = Math.round((now - last) / 1000);
    log(`   ${s.id} · ${label}: ${s.timings[label]} s`);
  }

  // ── Sonda y HTTP ────────────────────────────────────────────────────────────────────────────────────────────────
  async function op(site, name, params = {}) {
    const qs = new URLSearchParams({ digi_wf: name, token: TOKEN, ...Object.fromEntries(Object.entries(params).map(([k, v]) => [k, String(v)])) });
    const res = await request('GET', `${site.path}?${qs}`);
    if (res.status !== 200 || !res.json) throw new Error(`?digi_wf=${name} en ${site.path}: ${res.status} ${res.text.slice(0, 300)}`);
    if (res.json.error) throw new Error(`?digi_wf=${name} en ${site.path}: ${res.json.error}`);
    return res.json;
  }
  const state = (site, user, blogs, rest) => op(site, 'wishlist_state', { user, ...(blogs ? { blogs: blogs.join(',') } : {}), ...(rest ? { rest: 1 } : {}) });
  const session = (site, user, nonces = []) => op(site, 'wishlist_session', { user, nonces: nonces.join(',') });
  const cookieHeader = (sess) => sess.cookies.map((c) => `${c.name}=${encodeURIComponent(c.value)}`).join('; ');
  async function login(page, sess) {
    await page.setCookie(...sess.cookies.map((c) => ({ name: c.name, value: encodeURIComponent(c.value), url: BASE + '/' })));
  }
  /** Petición al sitio con cuerpo y longitud (JSON o formulario). */
  function send(method, url, { headers = {}, json, form } = {}) {
    let body = null;
    const h = { ...headers };
    if (json !== undefined) { body = typeof json === 'string' ? json : JSON.stringify(json); h['Content-Type'] = 'application/json'; }
    if (form) { body = new URLSearchParams(form).toString(); h['Content-Type'] = 'application/x-www-form-urlencoded'; }
    if (body !== null) h['Content-Length'] = String(Buffer.byteLength(body));
    return request(method, url, { headers: h, body });
  }

  // ── Navegador ───────────────────────────────────────────────────────────────────────────────────────────────────
  /** Registra las peticiones al sitio de una pestaña (método, URL, cuerpo y cabeceras). */
  function track(page, list) {
    page.on('request', (r) => {
      let u;
      try { u = new URL(r.url()); } catch (e) { return; }
      if (u.host !== HOST) return;
      list.push({ method: r.method(), url: r.url(), body: r.postData() || '', headers: r.headers() });
    });
    return list;
  }
  /** Errores de una pestaña añadida (la de `visitor()` ya los registra en `facts`). */
  function watch(page, facts) {
    page.on('console', (m) => { if (m.type() === 'error') facts.errors.push(m.text().slice(0, 300)); });
    page.on('pageerror', (e) => facts.errors.push(`pageerror: ${String(e.message || e).slice(0, 300)}`));
    page.on('response', (r) => { if (r.status() >= 400 && new URL(r.url()).host === HOST) facts.failed.push(`${r.status()} ${r.url().slice(0, 200)}`); });
  }
  const ready = (page, id) => page.waitForFunction((sel) => { const b = document.querySelector(sel); return !!b && !b.hidden; }, WAIT, `button[data-digi-wish="${id}"]`);
  const listed = (page, n) => page.waitForFunction((count) => {
    const ul = document.querySelector('[data-digi-wish-items]');
    return !!ul && ul.getAttribute('aria-busy') !== 'true' && document.querySelectorAll('[data-digi-wish-item]').length === count;
  }, WAIT, n);
  const said = (page, text) => page.waitForFunction((t) => { const n = document.querySelector('.digi-wish-status'); return !!n && n.textContent === t; }, WAIT, text);
  /** Lo que ve el visitante: almacenamiento, botones, contador, fichas, aviso de fusión, región de estado y configuración. */
  const look = (page, key) => page.evaluate((k) => {
    let stored = null;
    try { const raw = localStorage.getItem(k); stored = raw ? JSON.parse(raw).items.map((r) => r[0]) : null; } catch (e) { stored = 'ilegible'; }
    const buttons = {};
    document.querySelectorAll('button[data-digi-wish]').forEach((b) => { buttons[b.getAttribute('data-digi-wish')] = { hidden: b.hidden, pressed: b.getAttribute('aria-pressed') }; });
    const merge = document.querySelector('.digi-wish-merge');
    const status = document.querySelector('.digi-wish-status');
    const c = window.digitalisimoWishlist || null;
    return {
      keys: Object.keys(localStorage),
      stored,
      buttons,
      count: [...document.querySelectorAll('[data-digi-wish-count]')].map((n) => n.textContent),
      items: [...document.querySelectorAll('[data-digi-wish-item]')].map((li) => +li.getAttribute('data-digi-wish-item')),
      empty: (() => { const e = document.querySelector('[data-digi-wish-empty]'); return e ? !e.hidden : null; })(),
      merge: merge ? { role: merge.getAttribute('role'), text: merge.querySelector('p') ? merge.querySelector('p').textContent : '', buttons: [...merge.querySelectorAll('button')].map((b) => b.textContent) } : null,
      status: status ? status.textContent : '',
      config: c ? { loggedIn: !!c.loggedIn, mode: c.mode, key: c.key, legacy: !!c.legacy, nonce: !!c.nonce, items: c.items || null } : null,
      overflow: document.documentElement.scrollWidth - window.innerWidth,
    };
  }, key);
  const ownRequests = (net) => net.filter((r) => /\/woo\/wishlist(\?|$)/.test(r.url));
  const storeRequests = (net) => net.filter((r) => r.url.includes('/wc/store/v1/shopper-lists/wishlist/items'));
  // Las páginas del constructor piden las fuentes de Google del kit por defecto (Roboto) del núcleo, no de la lista: el
  // arnés las corta (nada sale del sitio) y se cuentan aparte, como en swatchescart.cjs.
  const FONTS = /fonts\.(googleapis|gstatic)\.com/;
  const outside = (facts) => facts.outside.filter((u) => !FONTS.test(u));
  const kitFonts = (facts) => facts.outside.length - outside(facts).length;
  const jsErrors = (facts) => facts.errors.filter((e) => !(/net::ERR_NAME_NOT_RESOLVED/.test(e) && kitFonts(facts) > 0 && !outside(facts).length));
  const clean = (facts) => !jsErrors(facts).length && !facts.failed.length && !outside(facts).length;
  const factsOf = (facts) => short({ errors: jsErrors(facts).slice(0, 4), failed: facts.failed.slice(0, 4), outside: outside(facts).slice(0, 4), kitFonts: kitFonts(facts) }, 500);

  /** Un invitado guarda `ids` en «Guardar» y después entra como `user`: devuelve la pestaña ya con la sesión. */
  async function guestThenLogin(site, user, ids) {
    const w = wlOf(site);
    const v = await visitor();
    v.net = track(v.page, []);
    await v.page.setViewport({ width: 1280, height: 900 });
    await v.page.goto(w.urls.save, { waitUntil: 'load' });
    await ready(v.page, w.products.camiseta);
    for (const id of ids) {
      await v.page.click(`button[data-digi-wish="${id}"]`);
      await v.page.waitForSelector(`button[data-digi-wish="${id}"][aria-pressed="true"]`, WAIT);
    }
    v.guest = await look(v.page, w.key);
    v.session = await session(site, user.id);
    await login(v.page, v.session);
    return v;
  }
  const mergeBox = (page) => page.waitForSelector('.digi-wish-merge button[data-add]', WAIT);

  // ── Privacidad: «Exportar datos personales» y «Borrar datos personales» ────────────────────────────────────────────
  const OWN_ID = 'digitalisimo-elements-wishlist';
  /**
   * Todos los exportadores que ve la administración (los de WordPress, los de WooCommerce y, si está enganchado a
   * `admin_init`, el propio), llamados con el correo en una sola petición de la sonda. Devuelve los que traen datos de
   * una lista de deseos (la propia o la de WooCommerce).
   */
  async function exportAll(site, email) {
    const res = await op(site, 'wishlist_privacy', { email, type: 'export', run: 1 });
    const withList = Object.entries(res.results || {})
      .filter(([, r]) => r.groups && (r.groups[OWN_GROUP] || r.groups[WOO_GROUP]))
      .map(([key, r]) => ({ key, groups: Object.keys(r.groups).filter((g) => g === OWN_GROUP || g === WOO_GROUP), items: (r.groups[OWN_GROUP] || 0) + (r.groups[WOO_GROUP] || 0), names: r.names || [] }));
    return { keys: res.keys || [], withList, own: (res.keys || []).includes(OWN_ID) };
  }
  /**
   * La acción AJAX real de la pantalla (`wp-privacy-export-personal-data` o `wp-privacy-erase-personal-data`) para el
   * exportador o borrador propio: una solicitud confirmada, el nonce de esa solicitud y `admin-ajax.php` con la sesión
   * del administrador. El índice es su posición en la lista que ve la administración.
   */
  async function ajaxOwn(site, kind, email) {
    const w = wlOf(site);
    const list = await op(site, 'wishlist_privacy', { email, type: kind, request: 1 });
    const first = (list.keys || []).indexOf(OWN_ID) + 1;
    if (!first) return { index: 0, keys: list.keys, error: 'el propio no está registrado' };
    const action = kind === 'erase' ? 'wp-privacy-erase-personal-data' : 'wp-privacy-export-personal-data';
    const admin = list.admin;
    const tried = [];
    // La administración puede registrar alguno más antes que el propio (en `admin_init`): se sigue hacia arriba hasta
    // reconocerlo (sus filas o su mensaje) o salir de la lista.
    for (let index = first; index < first + 4; index++) {
      const form = { action, id: String(list.request), page: '1', security: list.nonce, [kind === 'erase' ? 'eraser' : 'exporter']: String(index) };
      if (kind !== 'erase') form.sendAsEmail = 'false';
      const res = await send('POST', w.ajax, { headers: { Cookie: cookieHeader(admin) }, form });
      const j = res.json;
      if (!j) return { index, tried, keys: list.keys, error: `HTTP ${res.status} ${res.text.slice(0, 160)}` };
      const message = j.success ? '' : String(typeof j.data === 'string' ? j.data : JSON.stringify(j.data));
      if (!j.success && /out of range/i.test(message)) break;
      if (!j.success) return { index, tried, keys: list.keys, error: message.slice(0, 300) };
      const mine = kind === 'erase' ? Array.isArray(j.data.messages) && j.data.messages.includes(T.erased) : Array.isArray(j.data.data) && j.data.data.some((d) => d.group_id === OWN_GROUP);
      if (mine) return { index, tried, opIndex: first, data: j.data };
      tried.push({ index, data: j.data });
    }
    return { index: 0, tried, keys: list.keys, error: 'no se reconoció el propio en la lista de la administración' };
  }

  // ── W1 · invitado ───────────────────────────────────────────────────────────────────────────────────────────────
  async function W1(s) {
    lap(s, null);
    const site = SITES[0];
    const w = wlOf(site);
    if (!check(s, 'W1.0', 'la preparación de la lista de deseos dejó sus páginas, productos y widgets', !!(w && w.urls && w.urls.save && w.urls.list), short(w && w.urls))) return;
    const P = w.products;
    const { ctx, page, facts } = await visitor();
    const net = track(page, []);
    let page2 = null;
    try {
      await page.setViewport({ width: 1280, height: 900 });
      // Saltos de diseño (CLS) de cada carga, con el elemento que se movió o cambió de tamaño.
      await page.evaluateOnNewDocument(() => {
        window.digiWfCls = { value: 0, shifts: [] };
        try {
          new PerformanceObserver((list) => {
            for (const e of list.getEntries()) {
              if (e.hadRecentInput) continue;
              window.digiWfCls.value += e.value;
              window.digiWfCls.shifts.push({ value: +e.value.toFixed(4), nodes: (e.sources || []).map((x) => (x.node && x.node.nodeType === 1 ? x.node.tagName.toLowerCase() + (x.node.className && typeof x.node.className === 'string' ? '.' + x.node.className.trim().split(/\s+/).join('.') : '') : '#texto')) });
            }
          }).observe({ type: 'layout-shift', buffered: true });
        } catch (e) { window.digiWfCls.error = String(e); }
      });
      const cls = () => page.evaluate(() => window.digiWfCls);
      // 1. Cargar la lista vacía y los botones no escribe nada.
      await page.goto(w.urls.list, { waitUntil: 'load' });
      await page.waitForSelector('[data-digi-wish-empty]:not([hidden])', WAIT);
      await sleep(700);
      const emptyList = await look(page, w.key);
      const clsList = await cls();
      await page.goto(w.urls.save, { waitUntil: 'load' });
      await ready(page, P.camiseta);
      await sleep(700);
      const before = await look(page, w.key);
      const clsSave = await cls();
      check(s, 'W1.1c', 'hidratar no mueve la página (CLS ≤ 0,01): el botón y el texto de lista vacía guardan su hueco desde el servidor', !!clsList && !!clsSave && clsList.value <= 0.01 && clsSave.value <= 0.01, short({ lista: clsList, guardar: clsSave }));
      const cookies0 = await page.cookies();
      const ours = (keys) => keys.filter((k) => /^digi_/.test(k));
      check(s, 'W1.1', '`localStorage` vacío antes del primer clic: abrir la lista y los botones no guarda nada', !ours(emptyList.keys).length && !ours(before.keys).length && before.stored === null && emptyList.empty === true, short({ lista: emptyList.keys, guardar: before.keys, vacia: emptyList.empty }));
      check(s, 'W1.1b', 'hidratación de invitado: botones visibles sin pulsar (también el del variable) y contador a 0', Object.values(before.buttons).length === 7 && Object.values(before.buttons).every((b) => !b.hidden && b.pressed === 'false') && before.count.length > 0 && before.count.every((c) => c === '0') && before.config && !before.config.loggedIn && before.config.key === w.key, short({ buttons: before.buttons, count: before.count, config: before.config }));

      // 2. Guardar dos, recargar y abrir la lista.
      await page.click(`button[data-digi-wish="${P.camiseta}"]`);
      await page.waitForSelector(`button[data-digi-wish="${P.camiseta}"][aria-pressed="true"]`, WAIT);
      await said(page, T.saved);
      await page.click(`button[data-digi-wish="${P.gorra}"]`);
      await page.waitForSelector(`button[data-digi-wish="${P.gorra}"][aria-pressed="true"]`, WAIT);
      await sleep(200);
      const cookies1 = await page.cookies();
      const saved = await look(page, w.key);
      check(s, 'W1.2a', 'guardar dos productos: el navegador guarda los dos en orden, los botones quedan pulsados, el contador dice 2 y la región de estado lo anuncia', JSON.stringify(saved.stored) === JSON.stringify([P.camiseta, P.gorra]) && saved.count.every((c) => c === '2') && saved.status === T.saved, short({ stored: saved.stored, count: saved.count, status: saved.status }));
      await page.reload({ waitUntil: 'load' });
      await ready(page, P.camiseta);
      await sleep(200);
      const reloaded = await look(page, w.key);
      check(s, 'W1.2b', 'al recargar, los dos siguen pulsados (los demás no) y el contador dice 2', reloaded.buttons[P.camiseta].pressed === 'true' && reloaded.buttons[P.gorra].pressed === 'true' && reloaded.buttons[P.taza].pressed === 'false' && reloaded.count.every((c) => c === '2'), short({ buttons: reloaded.buttons, count: reloaded.count }));
      await page.goto(w.urls.list, { waitUntil: 'load' });
      await listed(page, 2);
      const list = await look(page, w.key);
      const cardText = await page.$$eval('[data-digi-wish-item]', (lis) => lis.map((li) => li.textContent.replace(/\s+/g, ' ').trim().slice(0, 120)));
      check(s, 'W1.2c', 'la página de la lista pinta las dos fichas (la última guardada primero) con nombre, precio, «Quitar» y el contador', JSON.stringify(list.items) === JSON.stringify([P.gorra, P.camiseta]) && cardText.every((t) => /Quitar/.test(t) && /€/.test(t)) && list.empty === false && list.count.every((c) => c === '2'), short({ items: list.items, cards: cardText }));

      // 3. Otra pestaña con la lista abierta; quitar en la primera.
      page2 = await ctx.newPage();
      watch(page2, facts);
      track(page2, net);
      await page2.setViewport({ width: 1280, height: 900 });
      await page2.goto(w.urls.list, { waitUntil: 'load' });
      await listed(page2, 2);
      await page.bringToFront();
      await page.click(`[data-digi-wish-remove="${P.gorra}"]`);
      await listed(page, 1);
      await page.waitForFunction(() => !!(document.activeElement && document.activeElement.closest('[data-digi-wish-item]')), WAIT).catch(() => {});
      await said(page, T.removed).catch(() => {});
      const focus = await page.evaluate(() => {
        const a = document.activeElement;
        const li = a && a.closest('[data-digi-wish-item]');
        return { tag: a ? a.tagName : null, text: a ? a.textContent.replace(/\s+/g, ' ').trim().slice(0, 60) : '', item: li ? +li.getAttribute('data-digi-wish-item') : 0, hidden: !!(a && a.closest('[aria-hidden="true"]')), tabindex: a ? a.getAttribute('tabindex') : null, media: !!(li && li.querySelector('.digi-wishlist__media')) };
      });
      const removed = await look(page, w.key);
      check(s, 'W1.2d', 'quitar una ficha: el foco pasa a la ficha siguiente (a un enlace o botón enfocable y no oculto; con imagen, no a su enlace oculto), el navegador la olvida y la región de estado lo anuncia', focus.item === P.camiseta && !focus.hidden && focus.tabindex !== '-1' && JSON.stringify(removed.stored) === JSON.stringify([P.camiseta]) && removed.status === T.removed, short({ focus, stored: removed.stored, status: removed.status }));
      const synced = await page2.waitForFunction((id) => {
        const items = [...document.querySelectorAll('[data-digi-wish-item]')];
        return items.length === 1 && +items[0].getAttribute('data-digi-wish-item') === id && [...document.querySelectorAll('[data-digi-wish-count]')].every((n) => n.textContent === '1');
      }, { timeout: 20000 }, P.camiseta).then(() => true, () => false);
      const other = await look(page2, w.key);
      check(s, 'W1.2e', 'la otra pestaña se sincroniza sola (evento `storage`): una ficha y el contador a 1, sin recargar', synced, short({ items: other.items, count: other.count }));

      // 4. Anchos: la lista no desborda y «Quitar» se ve.
      const widths = {};
      for (const width of WIDTHS) {
        await page.setViewport({ width, height: 900 });
        await sleep(150);
        const seen = await page.evaluate(() => { const b = document.querySelector('[data-digi-wish-remove]'); const r = b && b.getBoundingClientRect(); return { remove: !!(r && r.width > 0 && r.height > 0), overflow: document.documentElement.scrollWidth - window.innerWidth }; });
        widths[width] = seen;
        await shot(page, `W1-lista-${width}`);
      }
      check(s, 'W1.2f', `la lista se usa en cada ancho (${WIDTHS.join(', ')} px): «Quitar» visible y sin desplazamiento horizontal`, Object.values(widths).every((x) => x.remove && x.overflow <= 0), short(widths));

      // 5. Red: ningún ID en una URL; las fichas, por POST.
      const ids = new Set([P.camiseta, P.gorra].map(String));
      const dynamic = net.filter((r) => !/^\/(wp-content|wp-includes)\//.test(new URL(r.url).pathname.replace(/^\/(dos|tres)\//, '/')));
      const leaks = dynamic.filter((r) => {
        const u = new URL(r.url);
        const tokens = [...u.pathname.split('/'), ...[...u.searchParams.entries()].flatMap(([k, v]) => [k, ...v.split(/[,/\s]+/)])];
        return tokens.some((t) => ids.has(t)) || [...u.searchParams.keys()].some((k) => /^(ids?|items?|products?|product_id)(\[\])?$/i.test(k));
      });
      const cards = net.filter((r) => /\/woo\/cards(\?|$)/.test(r.url));
      check(s, 'W1.3', 'ninguna petición lleva IDs de productos en la URL (registro de red de Chrome, ' + net.length + ' peticiones); las fichas se piden por POST con los IDs en el cuerpo', !leaks.length && cards.length > 0 && cards.every((r) => r.method === 'POST' && !new URL(r.url).search && /"ids":\[/.test(r.body)), short({ leaks: leaks.slice(0, 5).map((r) => `${r.method} ${r.url}`), cards: cards.map((r) => `${r.method} ${new URL(r.url).pathname} ${r.body.slice(0, 120)}`) }, 900));

      // 6. Cookies: la lista no escribe ninguna (las de atribución de pedidos de WooCommerce, `sbjs_*`, cambian en cada carga).
      const cookiesEnd = await page.cookies();
      const jar = (list) => list.filter((c) => !/^sbjs_/.test(c.name)).map((c) => `${c.name}=${c.value}`).sort();
      check(s, 'W1.4', 'la lista no escribe cookies: las del sitio son las mismas antes del primer clic, tras guardar y al terminar (salvo `sbjs_*`, atribución de pedidos de WooCommerce)', JSON.stringify(jar(cookies0)) === JSON.stringify(jar(cookies1)) && JSON.stringify(jar(cookies0)) === JSON.stringify(jar(cookiesEnd)), short({ antes: jar(cookies0).map((c) => c.split('=')[0]), despues: jar(cookiesEnd).map((c) => c.split('=')[0]) }));
      check(s, 'W1.5', 'sin errores de JavaScript ni respuestas de error del sitio, y nada sale del sitio (salvo las fuentes de Google del kit por defecto)', clean(facts), factsOf(facts));
    } catch (e) {
      await shot(page, 'W1-falla');
      throw e;
    } finally {
      await ctx.close().catch(() => {});
    }

    lap(s, 'W1.1–W1.5');
    // 7. Red: el sitio 2 no ve la lista del sitio 3.
    if (!NET) {
      s.net_only = ['W1.6: aislamiento entre sitios de una red (corre con NET=1)'];
      return;
    }
    const s2 = siteById(2);
    const s3 = siteById(3);
    const w2 = wlOf(s2);
    const w3 = wlOf(s3);
    if (!check(s, 'W1.6a', 'la preparación de la lista existe en los sitios 2 y 3', !!(w2 && w3), short({ dos: !!w2, tres: !!w3 }))) return;
    const v = await visitor();
    try {
      await v.page.setViewport({ width: 1280, height: 900 });
      await v.page.goto(w3.urls.save, { waitUntil: 'load' });
      await ready(v.page, w3.products.taza);
      await v.page.click(`button[data-digi-wish="${w3.products.taza}"]`);
      await v.page.waitForSelector(`button[data-digi-wish="${w3.products.taza}"][aria-pressed="true"]`, WAIT);
      const three = await look(v.page, w3.key);
      await v.page.goto(w2.urls.save, { waitUntil: 'load' });
      await ready(v.page, w2.products.taza);
      await sleep(200);
      const two = await look(v.page, w2.key);
      await v.page.goto(w2.urls.list, { waitUntil: 'load' });
      await v.page.waitForSelector('[data-digi-wish-empty]:not([hidden])', WAIT);
      const twoList = await look(v.page, w2.key);
      check(s, 'W1.6', `con NET=1, el sitio 2 no ve la lista del sitio 3: claves propias por sitio (${w3.key} / ${w2.key}), en el 2 nada pulsado, contador a 0 y lista vacía`,
        three.config && three.config.key === w3.key && w3.key !== w2.key && JSON.stringify(three.stored) === JSON.stringify([w3.products.taza]) && two.keys.includes(w3.key) && !two.keys.includes(w2.key) && two.stored === null
          && Object.values(two.buttons).every((b) => b.pressed === 'false') && two.count.every((c) => c === '0') && twoList.items.length === 0 && twoList.empty === true,
        short({ tres: { key: three.config && three.config.key, stored: three.stored }, dos: { keys: two.keys, pressed: Object.values(two.buttons).map((b) => b.pressed), count: two.count, lista: twoList.items } }));
      check(s, 'W1.6b', 'en red, sin errores de JavaScript ni respuestas de error', clean(v.facts), factsOf(v.facts));
    } finally {
      await v.ctx.close().catch(() => {});
    }
    lap(s, 'W1.6');
  }

  // ── W2 · con sesión ─────────────────────────────────────────────────────────────────────────────────────────────
  async function W2(s) {
    lap(s, null);
    const site = SITES[0];
    const w = wlOf(site);
    if (!check(s, 'W2.0', 'la preparación de la lista de deseos dejó sus páginas y clientes de prueba', !!(w && w.users && w.users.b && w.users.e), short(w && w.users))) return;
    const P = w.products;
    const U = w.users;

    // 1. «A invitado, B inicia sesión»: sin respuesta nada llega a la cuenta; con «Añadir», sí.
    const a = await guestThenLogin(site, U.b, [P.camiseta, P.taza]);
    try {
      a.net.length = 0;
      await a.page.goto(w.urls.save, { waitUntil: 'load' });
      await mergeBox(a.page);
      await sleep(100);
      const asked = await look(a.page, w.key);
      const b0 = await state(site, U.b.id);
      check(s, 'W2.1a', '«A invitado, B inicia sesión»: aviso no modal (región de estado con «Añadir» y «Descartar») y la cuenta de B sigue vacía mientras no responde',
        !!asked.merge && asked.merge.role === 'status' && asked.merge.text === T.ask(2) && JSON.stringify(asked.merge.buttons) === JSON.stringify(['Añadir', 'Descartar']) && asked.config && asked.config.loggedIn && asked.config.mode === 'own'
          && !b0.own.length && !ownRequests(a.net).length && asked.buttons[P.camiseta].pressed === 'false' && asked.count.every((c) => c === '0'),
        short({ merge: asked.merge, cuenta: b0.own, peticiones: ownRequests(a.net).map((r) => r.method), pressed: asked.buttons[P.camiseta], count: asked.count }));
      await a.page.reload({ waitUntil: 'load' });
      await mergeBox(a.page);
      const again = await look(a.page, w.key);
      const b1 = await state(site, U.b.id);
      check(s, 'W2.1b', 'sin «Añadir» (recargar sin responder): nada se envía, la cuenta de B no tiene nada de A y la copia sigue en el navegador', !b1.own.length && !ownRequests(a.net).length && same(again.stored, [P.camiseta, P.taza]) && !!again.merge, short({ cuenta: b1.own, stored: again.stored, peticiones: ownRequests(a.net).length }));
      const merged = a.page.waitForResponse((r) => /\/woo\/wishlist(\?|$)/.test(r.url()) && r.request().method() === 'POST', WAIT);
      await a.page.click('.digi-wish-merge button[data-add]');
      const mres = await merged;
      await said(a.page, T.added);
      await sleep(150);
      const after = await look(a.page, w.key);
      const b2 = await state(site, U.b.id);
      const mreq = ownRequests(a.net).find((r) => r.method === 'POST');
      check(s, 'W2.1c', 'con «Añadir»: un `merge` con nonce lleva los dos a la cuenta de B, se borra la copia del navegador y los botones quedan pulsados',
        mres.status() === 200 && same(b2.own, [P.camiseta, P.taza]) && after.stored === null && !after.keys.includes(w.key) && !after.merge && after.buttons[P.camiseta].pressed === 'true' && after.buttons[P.taza].pressed === 'true' && after.count.every((c) => c === '2')
          && !!mreq && !!mreq.headers['x-wp-nonce'] && /"op":"merge"/.test(mreq.body),
        short({ status: mres.status(), cuenta: b2.own, stored: after.stored, body: mreq && mreq.body, nonce: !!(mreq && mreq.headers['x-wp-nonce']) }));
      check(s, 'W2.1d', 'la fusión, sin errores de JavaScript ni respuestas de error', clean(a.facts), factsOf(a.facts));
    } catch (e) {
      await shot(a.page, 'W2-fusion-falla');
      throw e;
    } finally {
      await a.ctx.close().catch(() => {});
    }

    lap(s, 'W2.1 Añadir');
    // En una red, lo que no depende de la red (W2.1e, W2.2, W2.3 y W2.4i) ya lo prueba la corrida de un sitio: así la
    // corrida de W2 con NET=1 cabe en el tiempo del arnés (tres tiendas que preparar y el tramo W2.5). La lista de
    // WooCommerce (W2.4) se prueba allí en el sitio 3, con las metas y el contador de un sitio que no es el principal.
    if (NET) s.single_only = ['W2.1e «Descartar»', 'W2.2 lista con sesión y «Quitar»', 'W2.3 exportar y borrar con las herramientas de privacidad', 'W2.4i fusión de un invitado con la Store API'];
    if (!NET) {
      // «Descartar»: borra la copia local sin enviar nada.
      const c = await guestThenLogin(site, U.c, [P.gorra]);
      try {
        c.net.length = 0;
        await c.page.goto(w.urls.save, { waitUntil: 'load' });
        await mergeBox(c.page);
        await sleep(100);
        const asked = await look(c.page, w.key);
        await c.page.click('.digi-wish-merge button:not([data-add])');
        await said(c.page, T.discarded);
        const after = await look(c.page, w.key);
        const c1 = await state(site, U.c.id);
        check(s, 'W2.1e', '«Descartar» (un producto, texto en singular): borra la copia del navegador y no envía nada a la cuenta', asked.merge && asked.merge.text === T.ask1 && after.stored === null && !after.merge && !c1.own.length && !ownRequests(c.net).length, short({ texto: asked.merge && asked.merge.text, stored: after.stored, cuenta: c1.own, peticiones: ownRequests(c.net).length }));
      } finally {
        await c.ctx.close().catch(() => {});
      }

      lap(s, 'W2.1 Descartar');

      // 2. La lista de B con sesión (almacén propio): pintar y quitar con nonce.
      const vb = await visitor();
      try {
        vb.net = track(vb.page, []);
        await login(vb.page, await session(site, U.b.id));
        await vb.page.setViewport({ width: 1280, height: 900 });
        await vb.page.goto(w.urls.list, { waitUntil: 'load' });
        await listed(vb.page, 2);
        const before = await look(vb.page, w.key);
        const removed = vb.page.waitForResponse((r) => /\/woo\/wishlist(\?|$)/.test(r.url()) && r.request().method() === 'POST', WAIT);
        await vb.page.click(`[data-digi-wish-remove="${P.taza}"]`);
        const rres = await removed;
        await listed(vb.page, 1);
        const b3 = await state(site, U.b.id);
        const rreq = ownRequests(vb.net).find((r) => r.method === 'POST');
        check(s, 'W2.2a', 'con sesión, la lista sale de la cuenta (fichas por POST) y «Quitar» escribe en ella con `X-WP-Nonce`', same(before.items, [P.camiseta, P.taza]) && rres.status() === 200 && same(b3.own, [P.camiseta]) && !!rreq && !!rreq.headers['x-wp-nonce'] && /"op":"remove"/.test(rreq.body) && before.stored === null, short({ items: before.items, cuenta: b3.own, body: rreq && rreq.body }));
        check(s, 'W2.2b', 'la lista con sesión, sin errores de JavaScript ni respuestas de error', clean(vb.facts), factsOf(vb.facts));
      } finally {
        await vb.ctx.close().catch(() => {});
      }
      lap(s, 'W2.2');
    }

    // 3. Privacidad (modo propio): exportar incluye la lista, borrar la quita. B guarda Camiseta (Taza se quitó en W2.2).
    if (!NET) {
      const bList = [P.camiseta];
      const exp1 = await exportAll(site, U.b.email);
      const ajax1 = await ajaxOwn(site, 'export', U.b.email);
      const ajaxRows = ajax1.data && Array.isArray(ajax1.data.data) ? ajax1.data.data.filter((d) => d.group_id === OWN_GROUP) : [];
      check(s, 'W2.3a', '«Exportar datos personales» de B incluye su lista: entre todos los exportadores, sólo el propio trae datos de listas, y la acción AJAX de la pantalla lo devuelve',
        exp1.own && exp1.withList.length === 1 && exp1.withList[0].key === OWN_ID && exp1.withList[0].items === bList.length && /Camiseta de prueba/.test(exp1.withList[0].names.join(' ')) && ajaxRows.length === bList.length && /Camiseta de prueba/.test(JSON.stringify(ajaxRows[0].data)),
        short({ exportadores: exp1.keys, conLista: exp1.withList, ajax: { index: ajax1.index, op: ajax1.opIndex, filas: ajaxRows.length, error: ajax1.error } }, 900));
      const era = await ajaxOwn(site, 'erase', U.b.email);
      const fields = era.data ? Object.keys(era.data).sort() : [];
      const b4 = await state(site, U.b.id);
      const exp2 = await exportAll(site, U.b.email);
      check(s, 'W2.3b', '«Borrar datos personales» (acción AJAX de la pantalla) quita la lista: el borrador responde con sus cuatro campos (`items_removed`, `items_retained` falso, `messages`, `done`) y después nada se exporta',
        !!era.data && JSON.stringify(fields) === JSON.stringify(['done', 'items_removed', 'items_retained', 'messages']) && era.data.items_removed === true && era.data.items_retained === false && era.data.done === true && era.data.messages.includes(T.erased) && !b4.own.length && !exp2.withList.length,
        short({ borrador: era, cuenta: b4.own, despues: exp2.withList }));
      lap(s, 'W2.3');
    }

    // 4. Lista de WooCommerce encendida: Store API, mudanza, el variable sin botón y un solo exportador (en red, sitio 3).
    const site4 = NET ? siteById(3) : site;
    const w4 = wlOf(site4);
    const P4 = w4.products;
    const vd = await visitor();
    let featureOn = false;
    try {
      vd.net = track(vd.page, []);
      await login(vd.page, await session(site4, U.d.id));
      await vd.page.setViewport({ width: 1280, height: 900 });
      // Lista propia previa de D (el variable incluido), guardada con el almacén del módulo: guardar y quitar desde el
      // navegador en modo propio ya lo prueban W2.1 y W2.2.
      await op(site4, 'wishlist_seed', { lists: `${U.d.id}:${[P4.camiseta, P4.calcetin, P4.sudadera].join(',')}` });
      const d0 = await state(site4, U.d.id);
      check(s, 'W2.4a', 'antes de encender la lista de WooCommerce, D tiene una lista propia de tres productos (el variable incluido)', same(d0.own, [P4.camiseta, P4.calcetin, P4.sudadera]) && d0.mode_front === 'own' && !d0.woo.length && Number(d0.legacy) >= 1, short(d0));

      featureOn = true;
      const on = await op(site4, 'wishlist_feature', { on: 1 });
      const d1 = await state(site4, U.d.id, null, true);
      check(s, 'W2.4b', 'con `product_wishlist` encendida en el sitio, la fuente es la Store API en el front y en REST (la ruta `shopper-lists` registrada)', on.feature === 'yes' && d1.mode_front === 'store' && d1.mode_rest === 'store' && d1.own_exporter === true, short({ feature: on.feature, front: d1.mode_front, rest: d1.mode_rest, exportadorPropio: d1.own_exporter, legacy: d1.legacy }));
      lap(s, 'W2.4 preparación');

      vd.net.length = 0;
      const moved = vd.page.waitForResponse((r) => /\/woo\/wishlist(\?|$)/.test(r.url()) && r.request().method() === 'POST', { timeout: 60000 });
      await vd.page.goto(w4.urls.save, { waitUntil: 'load' });
      const mres = await moved;
      await ready(vd.page, P4.camiseta);
      await vd.page.waitForSelector(`button[data-digi-wish="${P4.calcetin}"][aria-pressed="true"]`, WAIT).catch(() => {});
      const look1 = await look(vd.page, w4.key);
      const d2 = await state(site4, U.d.id);
      const stores = storeRequests(vd.net);
      const posts = stores.filter((r) => r.method === 'POST');
      const owns = ownRequests(vd.net);
      check(s, 'W2.4c', 'mudanza: en la siguiente visita, cada producto no variable va a la Store API (`POST` con su `Nonce`) y después `clear` con `moved` vacía el almacén propio; el contador de listas propias vuelve a 0',
        mres.status() === 200 && same(d2.woo, [P4.camiseta, P4.calcetin]) && !d2.own.length && Number(d2.legacy) === 0 && posts.length === 2 && posts.every((r) => !!r.headers.nonce) && !posts.some((r) => new RegExp(`"product_id":${P4.sudadera}\\b`).test(r.body))
          && owns.length === 1 && /"op":"clear"/.test(owns[0].body) && /"moved":true/.test(owns[0].body) && look1.config.mode === 'store' && look1.config.legacy === true,
        short({ woo: d2.woo, propio: d2.own, legacy: d2.legacy, store: stores.map((r) => `${r.method} ${r.body}`), propias: owns.map((r) => r.body), config: look1.config }, 900));
      check(s, 'W2.4d', 'en modo Store API, los guardados quedan pulsados y el botón del variable se oculta (desviación declarada: WooCommerce pide la variación)', look1.buttons[P4.camiseta].pressed === 'true' && look1.buttons[P4.calcetin].pressed === 'true' && look1.buttons[P4.sudadera].hidden === true && look1.buttons[P4.taza].pressed === 'false' && !look1.buttons[P4.taza].hidden && look1.count.every((x) => x === '2'), short({ buttons: look1.buttons, count: look1.count }));
      lap(s, 'W2.4 mudanza');

      vd.net.length = 0;
      const added = vd.page.waitForResponse((r) => r.url().includes('/wc/store/v1/shopper-lists/wishlist/items') && r.request().method() === 'POST', WAIT);
      await vd.page.click(`button[data-digi-wish="${P4.taza}"]`);
      const ares = await added;
      await vd.page.waitForSelector(`button[data-digi-wish="${P4.taza}"][aria-pressed="true"]`, WAIT);
      const d3 = await state(site4, U.d.id);
      check(s, 'W2.4e', 'una sola fuente: guardar con la función activa escribe sólo en la Store API (201) y nunca en el almacén propio', ares.status() === 201 && same(d3.woo, [P4.camiseta, P4.calcetin, P4.taza]) && !d3.own.length && !ownRequests(vd.net).length, short({ status: ares.status(), woo: d3.woo, propio: d3.own, propias: ownRequests(vd.net).length }));
      await vd.page.goto(w4.urls.list, { waitUntil: 'load' });
      await listed(vd.page, 3);
      const dl = await look(vd.page, w4.key);
      check(s, 'W2.4f', 'la página de la lista pinta la lista de WooCommerce', same(dl.items, [P4.camiseta, P4.calcetin, P4.taza]), short(dl.items));
      check(s, 'W2.4g', 'la mudanza y la Store API, sin errores de JavaScript ni respuestas de error', clean(vd.facts), factsOf(vd.facts));
      lap(s, 'W2.4 Store API');

      const exp3 = await exportAll(site4, U.d.email);
      check(s, 'W2.4h', 'un solo exportador con datos: el de WooCommerce (`woocommerce-shopper-lists`, con los tres productos); el propio ya no se registra (sin listas propias en el sitio)', exp3.withList.length === 1 && exp3.withList[0].key === 'woocommerce-shopper-lists' && JSON.stringify(exp3.withList[0].groups) === JSON.stringify([WOO_GROUP]) && exp3.withList[0].items === 3 && !exp3.own, short({ conLista: exp3.withList, exportadores: exp3.keys }));
      lap(s, 'W2.4 exportador');

      // Un invitado que entra con la función activa: «Añadir» hace un POST por producto a la Store API.
      if (!NET) {
        const e = await guestThenLogin(site4, U.e, [P4.gorra, P4.lampara]);
        try {
          const guestLook = e.guest;
          e.net.length = 0;
          await e.page.goto(w4.urls.save, { waitUntil: 'load' });
          await mergeBox(e.page);
          await e.page.click('.digi-wish-merge button[data-add]');
          await said(e.page, T.added);
          await sleep(150);
          const e1 = await state(site4, U.e.id);
          const eLook = await look(e.page, w4.key);
          const ePosts = storeRequests(e.net).filter((r) => r.method === 'POST');
          check(s, 'W2.4i', 'con la función activa, «Añadir» lleva la lista del invitado a la Store API (un POST por producto), nada al almacén propio, y borra la copia del navegador; el invitado tampoco ve el botón del variable',
            same(e1.woo, [P4.gorra, P4.lampara]) && !e1.own.length && ePosts.length === 2 && !ownRequests(e.net).length && eLook.stored === null && guestLook.buttons[P4.sudadera].hidden === true,
            short({ woo: e1.woo, propio: e1.own, posts: ePosts.length, propias: ownRequests(e.net).length, stored: eLook.stored, variableInvitado: guestLook.buttons[P4.sudadera] }));
        } finally {
          await e.ctx.close().catch(() => {});
        }
      }
    } catch (err) {
      await shot(vd.page, 'W2-store-falla');
      throw err;
    } finally {
      if (featureOn) await op(site4, 'wishlist_feature', { on: 0 }).catch(() => {});
      await vd.ctx.close().catch(() => {});
    }

    lap(s, 'W2.4');
    // 5. Red: quitar al usuario del sitio 3 y un no miembro.
    if (!NET) {
      s.net_only = ['W2.5: quitar del sitio 3 desde la red y no miembro en modo invitado (corren con NET=1; en un sitio único todo usuario con sesión es miembro)'];
      return;
    }
    const s1 = siteById(1);
    const s2 = siteById(2);
    const s3 = siteById(3);
    const w3 = wlOf(s3);
    const w2 = wlOf(s2);
    const F = U.f;
    const G = U.g;
    const w3users = (w3 && w3.users) || {};
    if (!check(s, 'W2.5a', 'F y G son miembros de los sitios 1 y 3 (no del 2)', !!(w3 && w2 && w3users.f && w3users.f.member && w3users.g && w3users.g.member && w2.users.f && !w2.users.f.member), short({ tres: w3users, dos: w2 && w2.users && w2.users.f }))) return;
    const seed1 = await op(s1, 'wishlist_seed', { lists: `${F.id}:${w.products.gorra}|${G.id}:${w.products.cojin}` });
    const seed3 = await op(s3, 'wishlist_seed', { lists: `${F.id}:${w3.products.taza}|${G.id}:${w3.products.lampara}` });
    const before = (u) => ({ 1: seed1.own[String(u.id)] || [], 3: seed3.own[String(u.id)] || [] });
    const f0 = { own_by_blog: before(F) };
    // Pantalla de la red: Sitios → Editar → Usuarios → «Quitar» (site-users.php, con el nonce `bulk-users`).
    const admin = await session(s1, 1, ['bulk-users']);
    const url = `${w.network}?action=remove&id=3&user=${F.id}&_wpnonce=${admin.nonces['bulk-users']}`;
    const res = await send('GET', url, { headers: { Cookie: cookieHeader(admin) } });
    const f1 = await state(s3, F.id, [1, 3]);
    check(s, 'W2.5b', 'quitar a F del sitio 3 desde la administración de la red borra su lista del 3 y conserva la del 1', same(f0.own_by_blog['1'], [w.products.gorra]) && same(f0.own_by_blog['3'], [w3.products.taza]) && res.status === 302 && /update=remove/.test(String(res.headers.location || '')) && f1.member === false && !f1.own_by_blog['3'].length && same(f1.own_by_blog['1'], [w.products.gorra]), short({ antes: f0.own_by_blog, status: res.status, location: res.headers.location, despues: f1.own_by_blog, miembro3: f1.member }));
    const g0 = { own_by_blog: before(G) };
    const removal = await op(s1, 'wishlist_remove_from_blog', { user: G.id, blog: 3 });
    const g1 = await state(s1, G.id, [1, 3]);
    check(s, 'W2.5c', 'quitar a G del sitio 3 desde otra petición del sitio principal (el sitio en curso no es el 3) borra la lista del 3 y no la del 1', removal.current === 1 && removal.member === false && same(g0.own_by_blog['3'], [w3.products.lampara]) && !g1.own_by_blog['3'].length && same(g1.own_by_blog['1'], [w.products.cojin]), short({ antes: g0.own_by_blog, removal, despues: g1.own_by_blog }));

    lap(s, 'W2.5 quitar');
    // Un no miembro (F en el sitio 2, del que nunca fue miembro, y en el 3, del que se le quitó) usa el modo invitado.
    const vf = await visitor();
    try {
      vf.net = track(vf.page, []);
      const sf = await session(s1, F.id);
      await login(vf.page, sf);
      await vf.page.setViewport({ width: 1280, height: 900 });
      const seen = {};
      for (const [site2, wx] of [[s2, w2], [s3, w3]]) {
        await vf.page.goto(wx.urls.save, { waitUntil: 'load' });
        await ready(vf.page, wx.products.camiseta);
        await vf.page.click(`button[data-digi-wish="${wx.products.camiseta}"]`);
        await vf.page.waitForSelector(`button[data-digi-wish="${wx.products.camiseta}"][aria-pressed="true"]`, WAIT);
        await sleep(200);
        seen[site2.id] = await look(vf.page, wx.key);
      }
      const fRest = await send('POST', `${w2.rest}wishlist`, { headers: { Cookie: cookieHeader(sf), 'X-WP-Nonce': sf.nonces.wp_rest }, json: { op: 'add', product: w2.products.camiseta } });
      const f2 = await state(s2, F.id, [2, 3]);
      check(s, 'W2.5d', 'un no miembro (F en los sitios 2 y 3) usa el modo invitado: sin configuración de cuenta ni aviso de fusión, guarda en el navegador de ese sitio y `woo/wishlist` le responde 403',
        [2, 3].every((id) => seen[id].config && !seen[id].config.loggedIn && !seen[id].config.nonce && !seen[id].merge && JSON.stringify(seen[id].stored) === JSON.stringify([id === 2 ? w2.products.camiseta : w3.products.camiseta]))
          && !ownRequests(vf.net).length && fRest.status === 403 && fRest.json && fRest.json.code === 'digitalisimo_wishlist_member' && !f2.own_by_blog['2'].length && !f2.own_by_blog['3'].length,
        short({ dos: seen[2] && seen[2].config, tres: seen[3] && seen[3].config, rest: [fRest.status, fRest.json && fRest.json.code], cuenta: f2.own_by_blog }));
      check(s, 'W2.5e', 'el no miembro, sin errores de JavaScript ni respuestas de error', clean(vf.facts), factsOf(vf.facts));
    } finally {
      await vf.ctx.close().catch(() => {});
    }
    lap(s, 'W2.5 no miembro');
  }

  // ── W12 · seguridad ─────────────────────────────────────────────────────────────────────────────────────────────
  async function W12(s) {
    lap(s, null);
    const site = SITES[0];
    const w = wlOf(site);
    if (!check(s, 'W12.0', 'la preparación de la lista de deseos dejó sus páginas, la vista rápida y el producto privado', !!(w && w.pages && w.pages.view && w.products.private), short(w && w.pages))) return;
    const P = w.products;
    const R = w.rest;
    const user = w.users.w12;
    const sess = await session(site, user.id);
    const auth = { Cookie: cookieHeader(sess), 'X-WP-Nonce': sess.nonces.wp_rest };
    const nocache = (res) => /no-store/.test(String(res.headers['cache-control'] || '')) && /private/.test(String(res.headers['cache-control'] || ''));
    const code = (res) => (res.json && res.json.code) || '';
    const headers = {};

    // woo/cards: sólo POST, con el widget del documento; los IDs nunca en la URL.
    const getCards = await send('GET', `${R}cards?post_id=${w.pages.list}&widget_id=${w.widgets.list}&ids[]=${P.camiseta}`);
    headers.getCards = getCards.headers['cache-control'];
    check(s, 'W12.1', '`GET woo/cards` (con IDs en la URL) responde 405 con `Allow: POST`', getCards.status === 405 && /POST/.test(String(getCards.headers.allow || '')), short({ status: getCards.status, allow: getCards.headers.allow, code: code(getCards) }));
    const okCards = await send('POST', `${R}cards`, { json: { post_id: w.pages.list, widget_id: w.widgets.list, ids: [P.camiseta, P.private] } });
    const foreignCards = await send('POST', `${R}cards`, { json: { post_id: w.pages.view, widget_id: w.widgets.quick_view, ids: [P.camiseta] } });
    const bigCards = await send('POST', `${R}cards`, { json: { post_id: w.pages.list, widget_id: w.widgets.list, ids: [P.camiseta], pad: 'x'.repeat(9000) } });
    check(s, 'W12.1b', '`POST woo/cards`: con su widget pinta sólo lo público (el privado se omite); con un widget ajeno 403; con más de 8 KB 400', okCards.status === 200 && okCards.json && JSON.stringify(okCards.json.ids) === JSON.stringify([P.camiseta]) && /Camiseta de prueba/.test(okCards.json.html || '') && !/privado/i.test(okCards.json.html || '') && foreignCards.status === 403 && bigCards.status === 400, short({ ok: [okCards.status, okCards.json && okCards.json.ids], ajeno: [foreignCards.status, code(foreignCards)], grande: [bigCards.status, code(bigCards)] }));

    lap(s, 'W12.1');
    // woo/wishlist: sesión, nonce y una sola fuente.
    const anon = await send('POST', `${R}wishlist`, { json: { op: 'add', product: P.camiseta } });
    const fake = await send('POST', `${R}wishlist`, { headers: { Cookie: cookieHeader(sess), 'X-WP-Nonce': 'a1b2c3d4e5' }, json: { op: 'add', product: P.camiseta } });
    const bare = await send('POST', `${R}wishlist`, { headers: { Cookie: cookieHeader(sess) }, json: { op: 'add', product: P.camiseta } });
    const u0 = await state(site, user.id);
    check(s, 'W12.2', '`POST woo/wishlist` sin sesión 401, con un nonce falso 403 y con la cookie pero sin nonce 401, sin escribir nada', anon.status === 401 && fake.status === 403 && code(fake) === 'rest_cookie_invalid_nonce' && bare.status === 401 && !u0.own.length, short({ sinSesion: [anon.status, code(anon)], falso: [fake.status, code(fake)], sinNonce: [bare.status, code(bare)], cuenta: u0.own }));
    const okAdd = await send('POST', `${R}wishlist`, { headers: auth, json: { op: 'add', product: P.camiseta } });
    const okDel = await send('POST', `${R}wishlist`, { headers: auth, json: { op: 'remove', product: P.camiseta } });
    headers.wishlist = okAdd.headers['cache-control'];
    check(s, 'W12.2b', 'con sesión y nonce, la misma petición sí escribe (el 401/403 de arriba se debe al nonce)', okAdd.status === 200 && okAdd.json && okAdd.json.items.some((x) => x.id === P.camiseta) && okDel.status === 200 && okDel.json && !okDel.json.items.length, short({ add: [okAdd.status, okAdd.json && okAdd.json.items], del: [okDel.status, okDel.json && okDel.json.items] }));
    let on = null;
    try {
      on = await op(site, 'wishlist_feature', { on: 1 });
      const moved = await send('POST', `${R}wishlist`, { headers: auth, json: { op: 'add', product: P.camiseta } });
      const movedGet = await send('GET', `${R}wishlist`, { headers: auth });
      const u1 = await state(site, user.id);
      headers.moved = moved.headers['cache-control'];
      check(s, 'W12.3', 'con la lista de WooCommerce activa, `POST woo/wishlist` responde 409 (`digitalisimo_wishlist_moved`), también `GET`, y nada se escribe', on.feature === 'yes' && moved.status === 409 && code(moved) === 'digitalisimo_wishlist_moved' && movedGet.status === 409 && !u1.own.length && !u1.woo.length, short({ post: [moved.status, code(moved)], get: [movedGet.status, code(movedGet)], propio: u1.own, woo: u1.woo }));
    } finally {
      if (on) await op(site, 'wishlist_feature', { on: 0 }).catch(() => {});
    }

    lap(s, 'W12.2–W12.3');
    // woo/quick-view: permiso por predicado, producto público y `return` del mismo sitio.
    const qv = (params) => send('GET', `${R}quick-view?${new URLSearchParams(params)}`);
    const qvForeign = await qv({ post_id: w.pages.view, widget_id: w.widgets.view_list, product: P.camiseta });
    const qvOther = await qv({ post_id: w.pages.list, widget_id: w.widgets.quick_view, product: P.camiseta });
    const qvPrivate = await qv({ post_id: w.pages.view, widget_id: w.widgets.quick_view, product: P.private });
    const qvOk = await qv({ post_id: w.pages.view, widget_id: w.widgets.quick_view, product: P.camiseta, return: w.urls.view });
    const qvEvil = await qv({ post_id: w.pages.view, widget_id: w.widgets.quick_view, product: P.camiseta, return: 'https://otro.example/robo' });
    headers.quickView = qvForeign.headers['cache-control'];
    const action = (res) => { const m = /<form[^>]*\baction="([^"]*)"/.exec((res.json && res.json.html) || ''); return m ? m[1].replace(/&#0?38;|&amp;/g, '&') : null; };
    check(s, 'W12.4', '`woo/quick-view` con un `widget_id` ajeno responde 403 (una lista de deseos en la misma página y la vista rápida buscada en otro documento)', qvForeign.status === 403 && qvOther.status === 403, short({ ajeno: [qvForeign.status, code(qvForeign)], otroDocumento: [qvOther.status, code(qvOther)] }));
    check(s, 'W12.4b', '`woo/quick-view` con un producto privado responde 404 (y con uno público, 200)', qvPrivate.status === 404 && qvOk.status === 200, short({ privado: [qvPrivate.status, code(qvPrivate)], publico: qvOk.status }));
    check(s, 'W12.5', 'un `return` a otro dominio se rechaza: la acción del formulario vuelve a la ficha del producto (una del mismo sitio se respeta)', qvEvil.status === 200 && !/otro\.example/.test((qvEvil.json && qvEvil.json.html) || '') && action(qvEvil) === w.links.camiseta && action(qvOk) === w.urls.view, short({ evil: action(qvEvil), ok: action(qvOk), ficha: w.links.camiseta }));

    lap(s, 'W12.4–W12.5');
    // Ninguna escritura sin nonce: todas las rutas `woo/*` con métodos de escritura, con la cookie y sin nonce.
    const index = await send('GET', w.index);
    const routes = (index.json && index.json.routes) || {};
    const writes = [];
    for (const [route, def] of Object.entries(routes)) {
      if (!/\/woo\//.test(route)) continue;
      const methods = [...new Set((def.endpoints || []).flatMap((e) => e.methods || []).filter((m) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(m)))];
      for (const method of methods) writes.push({ route, method });
    }
    const results = [];
    for (const { route, method } of writes) {
      const path = route.replace(/^\/digitalisimo-elements\/v1\/woo\//, '').replace(/\(\?P<[^>]+>[^)]+\)/g, '1');
      const res = await send(method, `${R}${path}`, { headers: { Cookie: cookieHeader(sess) }, json: { op: 'add', product: P.camiseta, post_id: w.pages.list, widget_id: w.widgets.list, ids: [P.camiseta] } });
      results.push({ route: path, method, status: res.status, code: code(res), cookies: [].concat(res.headers['set-cookie'] || []).map((c) => c.split('=')[0]) });
    }
    const u2 = await state(site, user.id);
    const publicRead = new Set(['cards']);
    const allowed = results.every((r) => (publicRead.has(r.route) ? r.status === 200 && !r.cookies.some((c) => /^wordpress_/.test(c)) : [401, 403].includes(r.status)));
    check(s, 'W12.6', 'ninguna escritura sin nonce: con la cookie de sesión y sin `X-WP-Nonce`, toda ruta `woo/*` con métodos de escritura responde 401/403 (salvo `cards`, lectura pública por POST, que no escribe) y la cuenta sigue vacía', writes.length >= 2 && allowed && writes.some((x) => /wishlist/.test(x.route)) && !u2.own.length && !u2.woo.length, short({ rutas: results, cuenta: u2.own }));
    check(s, 'W12.7', 'las respuestas de `woo/*`, también los errores (405, 409, 403), llevan `Cache-Control: no-store, private`', nocache(getCards) && nocache(okCards) && nocache(foreignCards) && nocache(okAdd) && nocache(qvForeign) && nocache(qvOk) && /no-store/.test(String(headers.moved || '')), short(headers));
    lap(s, 'W12.6–W12.7');
  }

  return { W1, W2, W12 };
};
