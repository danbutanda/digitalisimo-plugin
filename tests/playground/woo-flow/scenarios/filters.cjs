// Escenarios W9 y W10 de woo-flow.sh (F4-Woo, V.8 y V.16.4): filtros de precio y de atributo en el Loop Grid y en la
// tienda. Usa lo que preparó setup/filters.php (páginas, plantillas y términos) y ops/filters.php (catálogo del calzado,
// ajustes de la tienda y configuración de los controles).
//
// - W9 compara cada filtro del Loop Grid con la tienda nativa: los mismos parámetros de WooCommerce (`filter_*`,
//   `query_type_*`, `min_price`, `max_price`, `orderby`) sobre la consulta principal del archivo de la etiqueta
//   `digi-wf-filtros`, la base de los bucles. Con la tabla de atributos y sin ella, y con «Ocultar agotados» activo e
//   inactivo; al recargar (sin JS), por el refresco (REST) y en Chrome.
// - W10 usa la plantilla «Archivo de productos» del archivo de `digi-wf-calzado` (destino «Tienda»).
const CASES = [
  { id: 'talla-40', attr: { terms: ['eu-40'], join: 'OR' } },
  { id: 'talla-38-o-42', attr: { terms: ['eu-38', 'eu-42'], join: 'OR' } },
  { id: 'talla-38-y-42', attr: { terms: ['eu-38', 'eu-42'], join: 'AND' } },
  { id: 'precio-50-75', price: { min: '50', max: '75' } },
  { id: 'precio-desde-60', price: { min: '60' } },
  { id: 'talla-40-hasta-75', attr: { terms: ['eu-40'], join: 'OR' }, price: { max: '75' } },
];
const CONFIGS = [
  { lookup: 'yes', hide: 'no' },
  { lookup: 'yes', hide: 'yes' },
  { lookup: 'no', hide: 'yes' },
  { lookup: 'no', hide: 'no' },
];
// Oráculo independiente de «talla 40» (el catálogo de ops/filters.php): con la tabla y «Ocultar agotados», la
// variación agotada de la Deportiva la saca; sin la tabla, sólo sale la Bota agotada (la base de la tienda).
const ORACLE_40 = {
  'yes/no': ['digi-wf-bota-agotada', 'digi-wf-bota-alta', 'digi-wf-bota-corta', 'digi-wf-deportiva', 'digi-wf-deportiva-dos'],
  'yes/yes': ['digi-wf-bota-alta', 'digi-wf-bota-corta', 'digi-wf-deportiva-dos'],
  'no/yes': ['digi-wf-bota-alta', 'digi-wf-bota-corta', 'digi-wf-deportiva', 'digi-wf-deportiva-dos'],
  'no/no': ['digi-wf-bota-agotada', 'digi-wf-bota-alta', 'digi-wf-bota-corta', 'digi-wf-deportiva', 'digi-wf-deportiva-dos'],
};
const ATTR_KEY = 'digiattr_pa_talla-zapato';
const FATAL = /Fatal error|There has been a critical error|Ha habido un error crítico|Uncaught (?:Type)?Error/i;

module.exports = (api) => {
  const { TOKEN, SITES, QUICK, summary, sleep, log, request, check, visitor, shot } = api;
  const timing = { requests: 0, ms: 0 };

  // ── Utilidades ────────────────────────────────────────────────────────────────────────────────────────────────
  const op = async (site, name, params = '') => {
    const res = await request('GET', `${site.path}?digi_wf=${name}&token=${TOKEN}${params}`);
    if (res.status !== 200 || !res.json) throw new Error(`?digi_wf=${name} en ${site.path}: ${res.status} ${res.text.slice(0, 300)}`);
    return res.json;
  };
  const setupOf = (site) => (((summary.sites[site.path] || {}).setup || {}).filters) || null;
  const uniq = (list) => [...new Set(list)];
  const sorted = (list) => [...(list || [])].sort((a, b) => a - b);
  const same = (a, b) => !!a && !!b && JSON.stringify(sorted(a)) === JSON.stringify(sorted(b));
  const sameOrder = (a, b) => !!a && !!b && JSON.stringify(a) === JSON.stringify(b);
  const loopIdsIn = (html) => uniq([...String(html).matchAll(/class="[^"]*\be-loop-item-(\d+)\b/g)].map((m) => +m[1]));
  /** Fichas de un Loop Grid de la página: desde su `data-id` hasta el siguiente Loop Grid. */
  const loopIds = (html, loopId) => {
    const start = html.indexOf(`data-id="${loopId}"`);
    if (start < 0) return null;
    let end = html.indexOf('elementor-widget-loop-grid', start + 10);
    if (end < 0) end = html.length;
    return loopIdsIn(html.slice(start, end));
  };
  /** Productos de la plantilla de WooCommerce (`<li class="… type-product post-N …">`), en orden. */
  const nativeIds = (html) => uniq([...String(html).matchAll(/<li\b[^>]*class="([^"]*\btype-product\b[^"]*)"/g)].map((m) => +((m[1].match(/\bpost-(\d+)\b/) || [])[1] || 0)).filter(Boolean));
  /** Productos del widget Productos de PRO (hasta el Loop Grid que va detrás). */
  const widgetIds = (html) => {
    const start = html.indexOf('elementor-widget-woocommerce-products');
    if (start < 0) return null;
    let end = html.indexOf('elementor-widget-loop-grid', start);
    if (end < 0) end = html.length;
    return nativeIds(html.slice(start, end));
  };
  const withQuery = (url, pairs) => {
    const q = pairs.filter((p) => p && p[1] !== undefined && p[1] !== '').map(([k, v, raw]) => `${encodeURIComponent(k)}=${raw ? v : encodeURIComponent(v)}`).join('&');
    return q ? url + (url.includes('?') ? '&' : '?') + q : url;
  };
  const nativeParams = (c, extra = []) => {
    const out = [...extra];
    if (c.attr) out.push(['filter_talla-zapato', c.attr.terms.join(','), true], ['query_type_talla-zapato', c.attr.join.toLowerCase()]);
    if (c.price && c.price.min) out.push(['min_price', c.price.min]);
    if (c.price && c.price.max) out.push(['max_price', c.price.max]);
    return out;
  };
  const loopParams = (loopId, c) => {
    const pre = `e-filter-${loopId}-`;
    const out = [];
    if (c.attr) out.push([pre + ATTR_KEY, c.attr.terms.map(encodeURIComponent).join(c.attr.join === 'AND' ? '+' : '~'), true]);
    if (c.price && c.price.min) out.push([pre + 'digi_price_min', c.price.min]);
    if (c.price && c.price.max) out.push([pre + 'digi_price_max', c.price.max]);
    if (c.cat) out.push([pre + 'product_cat', c.cat.terms.map(encodeURIComponent).join(c.cat.join === 'AND' ? '+' : '~'), true]);
    return out;
  };
  const restFilters = (c) => {
    const wf = {};
    if (c.cat) wf.taxonomy = { product_cat: { terms: c.cat.terms, logicalJoin: c.cat.join } };
    if (c.attr) wf.digitalisimo_attribute = { [ATTR_KEY]: { terms: c.attr.terms, logicalJoin: c.attr.join } };
    if (c.price) {
      wf.digitalisimo_price = {};
      if (c.price.min) wf.digitalisimo_price.digi_price_min = { terms: [c.price.min], logicalJoin: 'AND' };
      if (c.price.max) wf.digitalisimo_price.digi_price_max = { terms: [c.price.max], logicalJoin: 'AND' };
    }
    return wf;
  };
  const timed = async (what, fn) => {
    const t0 = Date.now();
    try { return await fn(); } finally { const ms = Date.now() - t0; timing.requests++; timing.ms += ms; if (process.env.WF_FILTERS_TRACE === '1') log(`   · ${what} ${ms} ms`); }
  };
  const get = async (url) => {
    const res = await timed(`GET ${url.replace(/^https?:\/\/[^/]+/, '')}`, () => request('GET', url));
    return { status: res.status, html: res.text, fatal: FATAL.test(res.text) };
  };
  const rest = async (site, pageId, loopId, filters, base) => {
    const body = JSON.stringify({ post_id: pageId, widget_id: loopId, widget_filters: filters, pagination_base_url: base });
    const res = await timed(`REST ${loopId} ${Object.keys(filters).join('+')}`, () => request('POST', `${site.path}wp-json/elementor-pro/v1/refresh-loop`, { headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) }, body }));
    const html = res.json && typeof res.json.data === 'string' ? res.json.data : null;
    return { status: res.status, ids: html === null ? null : loopIdsIn(html), fatal: FATAL.test(res.text), text: html === null ? res.text.slice(0, 300) : '' };
  };
  const names = (catalog, ids) => (ids || []).map((id) => Object.keys(catalog.products).find((slug) => catalog.products[slug] === id) || `#${id}`);
  const nameList = (catalog, ids) => names(catalog, ids).join(', ');
  const configKey = (cfg) => `${cfg.lookup}/${cfg.hide}`;
  const configLabel = (cfg) => `${cfg.lookup === 'yes' ? 'con tabla' : 'sin tabla'}, ${cfg.hide === 'yes' ? 'ocultando agotados' : 'mostrando agotados'}`;
  const configure = (site, cfg) => op(site, 'filters_config', `&lookup=${cfg.lookup}&hide=${cfg.hide}${cfg.rows ? '&rows=' + cfg.rows : ''}`);

  // ── Chrome ───────────────────────────────────────────────────────────────────────────────────────────────────
  /** Registra las peticiones y respuestas del refresco del bucle. */
  const watchRefresh = (page) => {
    const log = { requests: [], responses: [], failed: [] };
    page.on('request', (r) => { if (r.url().includes('refresh-loop')) log.requests.push({ url: r.url(), method: r.method(), headers: r.headers(), body: r.postData() || '' }); });
    page.on('response', (r) => { if (r.url().includes('refresh-loop')) log.responses.push({ status: r.status() }); });
    page.on('requestfailed', (r) => { if (r.url().includes('refresh-loop')) log.failed.push(r.failure() ? r.failure().errorText : 'falló'); });
    return log;
  };
  /** Espera a que termine un refresco del bucle posterior a `n0` respuestas (o a que no haya ninguno en `quiet` ms). */
  const settleLoop = async (page, net, loopId, n0, ms = 60000) => {
    const t0 = Date.now();
    while (Date.now() - t0 < ms) {
      const busy = await page.evaluate((id) => { const el = document.querySelector('.elementor-element-' + id); return !!el && el.getAttribute('aria-busy') === 'true'; }, loopId).catch(() => true);
      if (net.responses.length > n0 && !busy && net.requests.length === net.responses.length + net.failed.length) break;
      await sleep(150);
    }
    await sleep(400);
  };
  const domIds = (page, loopId) => page.evaluate((id) => [...document.querySelectorAll('.elementor-element-' + id + ' .e-loop-item')].map((el) => +((el.className.match(/\be-loop-item-(\d+)\b/) || [])[1] || 0)).filter(Boolean), loopId);
  const param = (page, key) => page.evaluate((k) => new URLSearchParams(location.search).get(k), key);
  const ready = (page) => page.waitForFunction(() => window.digitalisimoWooFilters && window.digitalisimoWooFilters.active && window.digitalisimoWooFilters.active() && document.querySelector('form.digi-filter[data-digi-ready]'), { timeout: 60000 }).then(() => true).catch(() => false);

  // ── W9 ───────────────────────────────────────────────────────────────────────────────────────────────────────
  async function W9(s) {
    s.lookup = {};
    for (const site of SITES) {
      const tag = site.path;
      const f = setupOf(site);
      if (!f || !f.pages || !f.pages.main || !f.pages.main.id) { check(s, `W9.0 ${tag}`, 'la preparación del grupo filters dejó páginas y términos', false, JSON.stringify(f || null).slice(0, 300)); continue; }
      const heavy = site === SITES[0];
      const catalog = await op(site, 'filters_catalog');
      const state = summary.sites[site.path] && summary.sites[site.path].state ? summary.sites[site.path].state.options : {};
      s.lookup[tag] = {
        woocommerce_attribute_lookup_enabled: catalog.options && catalog.options.woocommerce_attribute_lookup_enabled,
        woocommerce_attribute_lookup_direct_updates: catalog.options && catalog.options.woocommerce_attribute_lookup_direct_updates,
        after_setup: { woocommerce_attribute_lookup_enabled: state.woocommerce_attribute_lookup_enabled, woocommerce_attribute_lookup_direct_updates: state.woocommerce_attribute_lookup_direct_updates },
        regenerated: catalog.regenerated, regenerate_error: catalog.regenerate_error || undefined, rows: catalog.lookup_rows, rows_before: catalog.lookup_rows_before, total_rows: catalog.lookup_total, pending: catalog.pending,
        runs: [],
      };
      const wantRows = ['digi-wf-bota-alta/eu-40/prod/in', 'digi-wf-bota-agotada/eu-40/prod/out', 'digi-wf-bota-corta/eu-38/prod/in', 'digi-wf-bota-corta/eu-40/prod/in', 'digi-wf-sandalia/eu-38/prod/in', 'digi-wf-sandalia/eu-42/prod/in', 'digi-wf-sandalia-dos/eu-42/prod/in', 'digi-wf-deportiva/eu-38/var/in', 'digi-wf-deportiva/eu-40/var/out', 'digi-wf-deportiva/eu-42/var/in', 'digi-wf-deportiva-dos/eu-40/var/in', 'digi-wf-deportiva-dos/eu-42/var/out'];
      const pendingActions = Object.values(catalog.pending || {}).reduce((a, b) => a + b, 0);
      check(s, `W9.0 ${tag}`, 'preparación: actualización directa de la tabla de atributos, tabla regenerada y sin cola pendiente; las 12 filas del calzado (variaciones agotadas incluidas) y `woocommerce_attribute_lookup_enabled` anotado', !catalog.errors.length && catalog.options.woocommerce_attribute_lookup_direct_updates === 'yes' && catalog.options.woocommerce_attribute_lookup_enabled === 'yes' && pendingActions === 0 && JSON.stringify([...catalog.lookup].sort()) === JSON.stringify([...wantRows].sort()), JSON.stringify({ errors: catalog.errors, created: catalog.created.length, regenerated: catalog.regenerated, lookup: catalog.lookup, pending: catalog.pending, options: catalog.options, stock: catalog.stock }));

      const main = f.pages.main;
      const tagUrl = f.links.tag;
      try {
        // W9.1 · Matriz: cada caso en la tienda nativa y por el refresco; al recargar (sin JS), todos los casos con la
        // primera configuración y «talla 40» en todas.
        const configs = heavy ? CONFIGS : [CONFIGS[0]];
        const cases = heavy ? (QUICK ? CASES.filter((c) => ['talla-40', 'talla-38-y-42', 'precio-50-75'].includes(c.id)) : CASES) : CASES.slice(0, 1);
        const matrix = {};
        for (const cfg of configs) {
          const t0 = Date.now();
          await configure(site, cfg);
          const key = configKey(cfg);
          const first = cfg === configs[0];
          const run = { config: key, cases: {} };
          matrix[key] = run;
          const baseNative = await get(tagUrl);
          const baseRest = await rest(site, main.id, 'wfloop1', {}, main.url);
          const baseLoop = first ? await get(main.url) : null;
          const nb = nativeIds(baseNative.html);
          const lb = baseLoop ? loopIds(baseLoop.html, 'wfloop1') : baseRest.ids;
          run.base = { native: names(catalog, nb), rest: names(catalog, baseRest.ids) };
          check(s, `W9.1 ${tag} ${key} base`, `sin filtros, el bucle y la tienda nativa muestran los mismos productos (${configLabel(cfg)})`, baseNative.status === 200 && baseRest.status === 200 && (!baseLoop || (baseLoop.status === 200 && !baseLoop.fatal)) && nb.length >= 7 && same(nb, baseRest.ids) && same(lb, baseRest.ids), JSON.stringify({ native: nameList(catalog, nb), rest: nameList(catalog, baseRest.ids), loop: baseLoop ? nameList(catalog, lb) : undefined, status: [baseNative.status, baseRest.status, baseLoop && baseLoop.status] }));
          for (const c of cases) {
            const native = await get(withQuery(tagUrl, nativeParams(c)));
            const viaRest = await rest(site, main.id, 'wfloop1', restFilters(c), main.url);
            const loop = first || c.id === 'talla-40' ? await get(withQuery(main.url, loopParams('wfloop1', c))) : null;
            const n = nativeIds(native.html);
            const l = loop ? loopIds(loop.html, 'wfloop1') : null;
            run.cases[c.id] = { native: names(catalog, n), rest: names(catalog, viaRest.ids), reload: l ? names(catalog, l) : undefined, ids: viaRest.ids };
            const ok = native.status === 200 && viaRest.status === 200 && !viaRest.fatal && same(n, viaRest.ids) && !same(n, nb) && (!loop || (loop.status === 200 && !loop.fatal && same(l, viaRest.ids)));
            check(s, `W9.1 ${tag} ${key} ${c.id}`, `«${c.id}» da el mismo conjunto que la tienda nativa por el refresco${loop ? ' y al recargar' : ''} (${configLabel(cfg)})`, ok, JSON.stringify({ native: nameList(catalog, n), rest: nameList(catalog, viaRest.ids), reload: loop ? nameList(catalog, l) : undefined, status: [native.status, viaRest.status, loop && loop.status], restText: viaRest.text || undefined }));
            if (c.id === 'talla-40') {
              const want = ORACLE_40[key];
              check(s, `W9.1b ${tag} ${key}`, `«talla 40» coincide con el catálogo esperado (${configLabel(cfg)}): la tabla, «Ocultar agotados» y las variaciones agotadas cambian el resultado`, JSON.stringify(names(catalog, viaRest.ids).sort()) === JSON.stringify(want), JSON.stringify({ got: names(catalog, viaRest.ids).sort(), want }));
            }
          }
          s.lookup[tag].runs.push({ woocommerce_attribute_lookup_enabled: cfg.lookup, woocommerce_hide_out_of_stock_items: cfg.hide, ms: Date.now() - t0 });
          log(`   W9 ${tag} ${key}: ${Date.now() - t0} ms`);
        }
        s.matrix = s.matrix || {};
        s.matrix[tag] = matrix;
        await configure(site, CONFIGS[0]);

        // W9.3 · B4: sólo `digi_price_min` con el filtro de PRO en el mismo bucle.
        const b4 = { price: { min: '70' } };
        const b4Native = await get(withQuery(tagUrl, nativeParams(b4)));
        const b4Loop = await get(withQuery(main.url, loopParams('wfloop1', b4)));
        const b4Rest = await rest(site, main.id, 'wfloop1', restFilters(b4), main.url);
        const proButtons = (b4Loop.html.slice(b4Loop.html.indexOf('data-id="wftax01"')).match(/class="e-filter-item"/g) || []).length;
        check(s, `W9.3 ${tag}`, 'B4: una URL con sólo `digi_price_min` en la página con el filtro de taxonomía de PRO del mismo bucle se pinta sin error, con el filtro de PRO y el conjunto de la tienda nativa; el refresco sin `taxonomy` también', b4Loop.status === 200 && !b4Loop.fatal && proButtons >= 3 && same(nativeIds(b4Native.html), loopIds(b4Loop.html, 'wfloop1')) && b4Rest.status === 200 && same(b4Rest.ids, loopIds(b4Loop.html, 'wfloop1')), JSON.stringify({ status: b4Loop.status, fatal: b4Loop.fatal, proButtons, native: nameList(catalog, nativeIds(b4Native.html)), loop: nameList(catalog, loopIds(b4Loop.html, 'wfloop1')), rest: [b4Rest.status, nameList(catalog, b4Rest.ids)] }));

        // W9.4a · Dos categorías hijas del mismo padre con «O» (Hierarchy_Or_Query).
        const or = { cat: { terms: ['digi-wf-botas', 'digi-wf-sandalias'], join: 'OR' } };
        const orNative = await get(withQuery(f.home, [['post_type', 'product'], ['product_cat', 'digi-wf-botas,digi-wf-sandalias', true]]));
        const orLoop = await get(withQuery(main.url, loopParams('wfloop1', or)));
        const orRest = await rest(site, main.id, 'wfloop1', restFilters(or), main.url);
        const orWant = ['digi-wf-bota-agotada', 'digi-wf-bota-alta', 'digi-wf-bota-corta', 'digi-wf-sandalia', 'digi-wf-sandalia-dos'];
        check(s, `W9.4a ${tag}`, 'dos categorías hijas del mismo padre con «O» (`Hierarchy_Or_Query`): sin error al recargar ni por el refresco, y las dos categorías enteras, como la tienda nativa', orLoop.status === 200 && !orLoop.fatal && orRest.status === 200 && !orRest.fatal && JSON.stringify(names(catalog, loopIds(orLoop.html, 'wfloop1')).sort()) === JSON.stringify(orWant) && same(loopIds(orLoop.html, 'wfloop1'), orRest.ids) && same(loopIds(orLoop.html, 'wfloop1'), nativeIds(orNative.html)), JSON.stringify({ status: [orLoop.status, orRest.status, orNative.status], loop: nameList(catalog, loopIds(orLoop.html, 'wfloop1')), rest: nameList(catalog, orRest.ids), native: nameList(catalog, nativeIds(orNative.html)), restText: orRest.text || undefined }));

        // W9.5 · Precio con orden por precio: mismo orden que la tienda nativa con `orderby=price`.
        if (heavy) {
          const order = f.pages.order;
          const pr = { price: { min: '30', max: '75' } };
          const oNative = await get(withQuery(tagUrl, nativeParams(pr, [['orderby', 'price']])));
          const oLoop = await get(withQuery(order.url, loopParams('wfloop2', pr)));
          const oRest = await rest(site, order.id, 'wfloop2', restFilters(pr), order.url);
          const oBaseNative = await get(withQuery(tagUrl, [['orderby', 'price']]));
          const oBaseLoop = await get(order.url);
          const on = nativeIds(oNative.html);
          const ol = loopIds(oLoop.html, 'wfloop2');
          check(s, `W9.5 ${tag}`, 'precio con orden por precio: el bucle ordenado por precio y filtrado da los productos de la tienda nativa (`orderby=price`, `min_price`, `max_price`) en el mismo orden, al recargar y por el refresco (el JOIN de `wc_product_meta_lookup` no se repite)', oLoop.status === 200 && !oLoop.fatal && ol.length >= 3 && sameOrder(on, ol) && sameOrder(ol, oRest.ids) && sameOrder(nativeIds(oBaseNative.html), loopIds(oBaseLoop.html, 'wfloop2')), JSON.stringify({ native: nameList(catalog, on), loop: nameList(catalog, ol), rest: nameList(catalog, oRest.ids), baseNative: nameList(catalog, nativeIds(oBaseNative.html)), baseLoop: nameList(catalog, loopIds(oBaseLoop.html, 'wfloop2')), restText: oRest.text || undefined }));
        }

        log(`   W9 ${tag}: servidor en ${timing.requests} peticiones, ${timing.ms} ms`);
        if (heavy) await browserW9(s, site, f, catalog, matrix);
        log(`   W9 ${tag}: Chrome terminado`);
      } finally {
        await configure(site, CONFIGS[0]).catch(() => {});
      }
    }
  }

  async function browserW9(s, site, f, catalog, matrix) {
    const tag = site.path;
    const main = f.pages.main;
    const expected = (key, caseId) => (matrix[key] && matrix[key].cases[caseId] ? matrix[key].cases[caseId].ids : null);
    // W9.2 · B3: «talla 40» en Chrome (refresco), al recargar y sin JS.
    {
      const { ctx, page, facts } = await visitor();
      const net = watchRefresh(page);
      try {
        await page.setViewport({ width: 1280, height: 900 });
        await page.goto(main.url, { waitUntil: 'load' });
        const active = await ready(page);
        check(s, `W9.2a ${tag}`, 'el coordinador queda activo (sustituye el manejador del filtro de PRO por su filtro público) y registra los dos formularios', active, JSON.stringify({ errors: facts.errors.slice(0, 3) }));
        const all = await domIds(page, 'wfloop1');
        const n0 = net.responses.length;
        await page.click(`form[data-digi-filter="attribute"] input[value="eu-40"]`);
        await settleLoop(page, net, 'wfloop1', n0);
        const ajax = await domIds(page, 'wfloop1');
        const value = await param(page, `e-filter-wfloop1-${ATTR_KEY}`);
        await shot(page, `W9-${site.id}-talla40`);
        const req = net.requests[net.requests.length - 1] || {};
        let body = {};
        try { body = JSON.parse(req.body || '{}'); } catch (e) { /* sin cuerpo */ }
        check(s, `W9.2b ${tag}`, 'B3 · `pa_talla-zapato` en Chrome: el refresco filtra el bucle con el conjunto de la tienda nativa y deja `e-filter-wfloop1-digiattr_pa_talla-zapato=eu-40` en la URL', same(ajax, expected('yes/no', 'talla-40')) && !same(ajax, all) && value === 'eu-40', JSON.stringify({ ajax: nameList(catalog, ajax), want: nameList(catalog, expected('yes/no', 'talla-40')), value, requests: net.requests.length }));
        check(s, `W9.8a ${tag}`, 'B5 · petición propia del coordinador: POST JSON a `elementor-pro/v1/refresh-loop` con el `post_id` del documento del filtro, el bucle y los filtros en el formato del servidor; el bucle guarda su estado propio', req.method === 'POST' && /\/elementor-pro\/v1\/refresh-loop$/.test(new URL(req.url || 'http://x/').pathname) && /application\/json/.test(req.headers ? req.headers['content-type'] || '' : '') && body.post_id === main.id && body.widget_id === 'wfloop1' && !!(body.widget_filters && body.widget_filters.digitalisimo_attribute && body.widget_filters.digitalisimo_attribute[ATTR_KEY]) && await page.evaluate(() => !!document.querySelector('.elementor-element-wfloop1').__digi), JSON.stringify({ method: req.method, url: req.url, body }));
        await page.reload({ waitUntil: 'load' });
        const reloaded = await domIds(page, 'wfloop1');
        const checked = await page.$eval(`form[data-digi-filter="attribute"] input[value="eu-40"]`, (el) => el.checked).catch(() => null);
        check(s, `W9.2c ${tag}`, 'B3 · recargar con la URL filtrada da el mismo conjunto que el refresco y la casilla sigue marcada', same(reloaded, ajax) && checked === true, JSON.stringify({ reloaded: nameList(catalog, reloaded), ajax: nameList(catalog, ajax), checked }));
        check(s, `W9.2e ${tag}`, 'sin errores de JavaScript ni respuestas de error del sitio; nada sale del sitio', !facts.errors.length && !facts.failed.length && !facts.outside.length, JSON.stringify({ errors: facts.errors.slice(0, 5), failed: facts.failed.slice(0, 5), outside: facts.outside.slice(0, 3) }));
      } catch (e) {
        await shot(page, `W9-${site.id}-b3-falla`);
        check(s, `W9.2 ${tag}`, 'B3 en Chrome', false, String(e.stack || e.message || e).slice(0, 500));
      } finally {
        await ctx.close().catch(() => {});
      }
    }
    {
      const { ctx, page } = await visitor();
      try {
        await page.setJavaScriptEnabled(false);
        await page.setViewport({ width: 1280, height: 900 });
        await page.goto(main.url, { waitUntil: 'load' });
        await page.click(`form[data-digi-filter="attribute"] input[value="eu-40"]`);
        const [res] = await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click(`form[data-digi-filter="attribute"] .digi-filter__submit`)]);
        const html = res ? await res.text() : '';
        const ids = loopIds(html, 'wfloop1');
        const url = new URL(page.url());
        await shot(page, `W9-${site.id}-sin-js`);
        check(s, `W9.2d ${tag}`, 'B3 · sin JS: el formulario GET lleva `e-filter-wfloop1-digiattr_pa_talla-zapato=eu-40` y el servidor pinta el mismo conjunto que el refresco', url.searchParams.get(`e-filter-wfloop1-${ATTR_KEY}`) === 'eu-40' && same(ids, expected('yes/no', 'talla-40')) && !FATAL.test(html), JSON.stringify({ url: url.pathname + url.search, ids: nameList(catalog, ids) }));
      } catch (e) {
        check(s, `W9.2d ${tag}`, 'B3 sin JS', false, String(e.stack || e.message || e).slice(0, 500));
      } finally {
        await ctx.close().catch(() => {});
      }
    }

    // W9.6 · cinco cambios en 200 ms → una petición; W9.7 · Atrás y Adelante; W9.4b · PRO con «O» por el coordinador.
    {
      const { ctx, page, facts } = await visitor();
      const net = watchRefresh(page);
      try {
        await page.setViewport({ width: 1280, height: 900 });
        await page.goto(main.url, { waitUntil: 'load' });
        await ready(page);
        const url0 = await page.evaluate(() => location.pathname + location.search);
        const ids0 = await domIds(page, 'wfloop1');

        // Cinco cambios del mínimo en 160 ms (el campo numérico, «Al cambiar»).
        let r0 = net.requests.length;
        await page.evaluate(async () => {
          const input = document.querySelector('form[data-digi-filter="price"] input[type="number"][data-digi-edge="min"]');
          for (let k = 1; k <= 5; k++) {
            input.value = String(10 * k + 20);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            await new Promise((r) => setTimeout(r, 40));
          }
        });
        await sleep(1500);
        await settleLoop(page, net, 'wfloop1', 0);
        const priceRequests = net.requests.length - r0;
        const minValue = await param(page, 'e-filter-wfloop1-digi_price_min');
        // Cinco clics en una casilla en 160 ms: queda marcada.
        r0 = net.requests.length;
        await page.evaluate(async () => {
          const box = document.querySelector('form[data-digi-filter="attribute"] input[value="eu-38"]');
          for (let k = 1; k <= 5; k++) { box.click(); await new Promise((r) => setTimeout(r, 40)); }
        });
        await sleep(1500);
        await settleLoop(page, net, 'wfloop1', 0);
        const boxRequests = net.requests.length - r0;
        const attrValue = await param(page, `e-filter-wfloop1-${ATTR_KEY}`);
        check(s, `W9.6 ${tag}`, 'cinco cambios en 200 ms (campo de precio y casilla) → una sola petición cada vez, con el último valor', priceRequests === 1 && boxRequests === 1 && minValue === '70' && attrValue === 'eu-38', JSON.stringify({ priceRequests, boxRequests, minValue, attrValue }));

        // Atrás y Adelante: estado 1 = talla 38 y mínimo 70; estado 2 = además talla 40 (O).
        await page.evaluate(() => { const i = document.querySelector('form[data-digi-filter="price"] input[type="number"][data-digi-edge="min"]'); i.value = ''; i.dispatchEvent(new Event('input', { bubbles: true })); });
        await sleep(600);
        await settleLoop(page, net, 'wfloop1', 0);
        const url1 = await page.evaluate(() => location.pathname + location.search);
        const ids1 = await domIds(page, 'wfloop1');
        let n0 = net.responses.length;
        await page.click(`form[data-digi-filter="attribute"] input[value="eu-40"]`);
        await settleLoop(page, net, 'wfloop1', n0);
        const url2 = await page.evaluate(() => location.pathname + location.search);
        const ids2 = await domIds(page, 'wfloop1');
        n0 = net.responses.length;
        await page.evaluate(() => history.back());
        await settleLoop(page, net, 'wfloop1', n0);
        const back = { url: await page.evaluate(() => location.pathname + location.search), ids: await domIds(page, 'wfloop1'), box40: await page.$eval(`form[data-digi-filter="attribute"] input[value="eu-40"]`, (el) => el.checked), box38: await page.$eval(`form[data-digi-filter="attribute"] input[value="eu-38"]`, (el) => el.checked) };
        n0 = net.responses.length;
        await page.evaluate(() => history.forward());
        await settleLoop(page, net, 'wfloop1', n0);
        const fwd = { url: await page.evaluate(() => location.pathname + location.search), ids: await domIds(page, 'wfloop1'), box40: await page.$eval(`form[data-digi-filter="attribute"] input[value="eu-40"]`, (el) => el.checked) };
        const oracle38 = ['digi-wf-bota-corta', 'digi-wf-deportiva', 'digi-wf-sandalia'];
        check(s, `W9.7 ${tag}`, 'Atrás y Adelante rehacen la URL, el bucle y los controles sin recargar', url1 !== url2 && JSON.stringify(names(catalog, ids1).sort()) === JSON.stringify(oracle38) && !same(ids1, ids2) && back.url === url1 && same(back.ids, ids1) && back.box40 === false && back.box38 === true && fwd.url === url2 && same(fwd.ids, ids2) && fwd.box40 === true, JSON.stringify({ url0, url1, url2, ids0: ids0.length, ids1: nameList(catalog, ids1), ids2: nameList(catalog, ids2), back: { ...back, ids: nameList(catalog, back.ids) }, fwd: { ...fwd, ids: nameList(catalog, fwd.ids) } }));

        // W9.4b · el filtro de PRO con dos hijas y «O» pasa por el coordinador (combinado con el atributo de la URL).
        await page.goto(main.url, { waitUntil: 'load' });
        await ready(page);
        r0 = net.requests.length;
        n0 = net.responses.length;
        await page.click('.elementor-element-wftax01 .e-filter-item[data-filter="digi-wf-botas"]');
        await settleLoop(page, net, 'wfloop1', n0);
        n0 = net.responses.length;
        await page.click('.elementor-element-wftax01 .e-filter-item[data-filter="digi-wf-sandalias"]');
        await settleLoop(page, net, 'wfloop1', n0);
        const orIds = await domIds(page, 'wfloop1');
        const orValue = await param(page, 'e-filter-wfloop1-product_cat');
        const lastBody = (() => { try { return JSON.parse((net.requests[net.requests.length - 1] || {}).body || '{}'); } catch (e) { return {}; } })();
        const orWant = ['digi-wf-bota-agotada', 'digi-wf-bota-alta', 'digi-wf-bota-corta', 'digi-wf-sandalia', 'digi-wf-sandalia-dos'];
        await shot(page, `W9-${site.id}-pro-o`);
        check(s, `W9.4b ${tag}`, 'el filtro de PRO del bucle coordinado con dos hijas y «O»: el coordinador manda `taxonomy` con «O», la URL lleva `digi-wf-botas~digi-wf-sandalias` y el bucle, las dos categorías', JSON.stringify(names(catalog, orIds).sort()) === JSON.stringify(orWant) && orValue === 'digi-wf-botas~digi-wf-sandalias' && !!(lastBody.widget_filters && lastBody.widget_filters.taxonomy && lastBody.widget_filters.taxonomy.product_cat && lastBody.widget_filters.taxonomy.product_cat.logicalJoin === 'OR'), JSON.stringify({ ids: nameList(catalog, orIds), orValue, body: lastBody.widget_filters, requests: net.requests.length - r0 }));

        // W9.3b · B4 en Chrome: sólo `digi_price_min` en la URL y un clic en el filtro de PRO (combinado).
        await page.goto(withQuery(main.url, [['e-filter-wfloop1-digi_price_min', '70']]), { waitUntil: 'load' });
        await ready(page);
        n0 = net.responses.length;
        await page.click('.elementor-element-wftax01 .e-filter-item[data-filter="digi-wf-botas"]');
        await settleLoop(page, net, 'wfloop1', n0);
        const comboIds = await domIds(page, 'wfloop1');
        const comboBody = (() => { try { return JSON.parse((net.requests[net.requests.length - 1] || {}).body || '{}'); } catch (e) { return {}; } })();
        const comboUrl = await page.evaluate(() => location.search);
        check(s, `W9.3b ${tag}`, 'B4 en Chrome: con sólo `digi_price_min` en la URL, el filtro de PRO combina su categoría con el precio (petición con `taxonomy` y `digitalisimo_price`, URL con los dos)', JSON.stringify(names(catalog, comboIds).sort()) === JSON.stringify(['digi-wf-bota-agotada', 'digi-wf-bota-alta']) && !!(comboBody.widget_filters && comboBody.widget_filters.taxonomy && comboBody.widget_filters.digitalisimo_price) && /digi_price_min=70/.test(comboUrl) && /product_cat=digi-wf-botas/.test(comboUrl), JSON.stringify({ ids: nameList(catalog, comboIds), body: comboBody.widget_filters, url: comboUrl }));

        // W9.8c · en la misma página, un bucle sin filtros F4-Woo (`wfloop4`) sigue el camino de PRO.
        const before4 = net.requests.length;
        n0 = net.responses.length;
        await page.click('.elementor-element-wftax04 .e-filter-item[data-filter="digi-wf-sandalias"]');
        await settleLoop(page, net, 'wfloop4', n0);
        const ids4 = await domIds(page, 'wfloop4');
        const req4 = net.requests.slice(before4).map((r) => { try { return JSON.parse(r.body || '{}'); } catch (e) { return {}; } });
        const path4 = await page.evaluate(() => ({ digi: !!document.querySelector('.elementor-element-wfloop4').__digi, loop1: !!document.querySelector('.elementor-element-wfloop1').__digi }));
        check(s, `W9.8c ${tag}`, 'B5 · en la misma página, un bucle sin filtros F4-Woo pasa por el camino de PRO (sin estado del coordinador) y se filtra', JSON.stringify(names(catalog, ids4).sort()) === JSON.stringify(['digi-wf-sandalia', 'digi-wf-sandalia-dos']) && req4.length >= 1 && req4.every((b) => b.widget_id === 'wfloop4' && b.widget_filters && !b.widget_filters.digitalisimo_price && !b.widget_filters.digitalisimo_attribute) && !path4.digi, JSON.stringify({ ids: nameList(catalog, ids4), requests: req4, path4 }));
        check(s, `W9.6b ${tag}`, 'sin errores de JavaScript ni respuestas de error del sitio; nada sale del sitio', !facts.errors.length && !facts.failed.length && !facts.outside.length, JSON.stringify({ errors: facts.errors.slice(0, 5), failed: facts.failed.slice(0, 5), outside: facts.outside.slice(0, 3) }));
      } catch (e) {
        await shot(page, `W9-${site.id}-coordinador-falla`);
        check(s, `W9.6 ${tag}`, 'coordinador en Chrome', false, String(e.stack || e.message || e).slice(0, 500));
      } finally {
        await ctx.close().catch(() => {});
      }
    }

    // W9.8b · una página sin F4-Woo: ni el coordinador ni sus scripts; el filtro de PRO funciona por su camino.
    {
      const { ctx, page, facts } = await visitor();
      const net = watchRefresh(page);
      try {
        await page.setViewport({ width: 1280, height: 900 });
        await page.goto(f.pages.pro.url, { waitUntil: 'load' });
        await page.waitForSelector('.elementor-element-wftax03 .e-filter-item');
        await sleep(500);
        const loaded = await page.evaluate(() => ({ coordinator: !!window.digitalisimoWooFilters, scripts: [...document.scripts].map((x) => x.src).filter((src) => /filters(-form)?\.js/.test(src)) }));
        const n0 = net.responses.length;
        await page.click('.elementor-element-wftax03 .e-filter-item[data-filter="digi-wf-botas"]');
        await settleLoop(page, net, 'wfloop3', n0);
        const ids = await domIds(page, 'wfloop3');
        const value = await param(page, 'e-filter-wfloop3-product_cat');
        const digi = await page.evaluate(() => !!document.querySelector('.elementor-element-wfloop3').__digi);
        check(s, `W9.8b ${tag}`, 'B5 · una página con sólo el filtro de PRO no carga el coordinador ni sus scripts, y el filtro de PRO refresca el bucle por su camino (una petición, URL con la categoría)', !loaded.coordinator && !loaded.scripts.length && !digi && net.requests.length === 1 && value === 'digi-wf-botas' && JSON.stringify(names(catalog, ids).sort()) === JSON.stringify(['digi-wf-bota-agotada', 'digi-wf-bota-alta', 'digi-wf-bota-corta']) && !facts.errors.length, JSON.stringify({ loaded, digi, requests: net.requests.length, value, ids: nameList(catalog, ids), errors: facts.errors.slice(0, 3) }));
      } catch (e) {
        check(s, `W9.8b ${tag}`, 'B5 en la página sin F4-Woo', false, String(e.stack || e.message || e).slice(0, 500));
      } finally {
        await ctx.close().catch(() => {});
      }
    }
  }

  // ── W10 ──────────────────────────────────────────────────────────────────────────────────────────────────────
  async function W10(s) {
    for (const site of SITES) {
      const tag = site.path;
      const f = setupOf(site);
      if (!f || !f.links || !f.links.calzado) { check(s, `W10.0 ${tag}`, 'la preparación del grupo filters dejó la plantilla de archivo', false, JSON.stringify(f || null).slice(0, 300)); continue; }
      const catalog = await op(site, 'filters_catalog');
      const previous = await configure(site, { lookup: 'yes', hide: 'no', rows: 1 });
      const archive = f.links.calzado;
      const tagUrl = f.links.tag;
      try {
        const base = await get(archive);
        const baseNative = await get(tagUrl);
        const pb = widgetIds(base.html);
        const lb = loopIds(base.html, 'wfloop5');
        const nb = nativeIds(baseNative.html);
        check(s, `W10.0 ${tag}`, 'el archivo de `digi-wf-calzado` usa la plantilla (dos filtros con destino «Tienda», Productos con «Consulta actual» y un Loop Grid con «Consulta actual»), cuatro productos por página como la tienda nativa', base.status === 200 && !base.fatal && /data-target="shop"/.test(base.html) && !!pb && pb.length === 4 && same(pb, nb) && !!lb && lb.length === 4, JSON.stringify({ status: base.status, products: nameList(catalog, pb), native: nameList(catalog, nb), loop: nameList(catalog, lb), rows: previous.after }));

        // W10.1 · en Chrome: precio «hasta 75» con el formulario, paginación, atributo y «Limpiar».
        const { ctx, page, facts } = await visitor();
        try {
          await page.setViewport({ width: 1280, height: 900 });
          await page.goto(archive, { waitUntil: 'load' });
          await page.waitForSelector('form.digi-filter[data-digi-filter="price"][data-digi-ready]');
          await page.type('form[data-digi-filter="price"] input[type="number"][data-digi-edge="max"]', '75');
          await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('form[data-digi-filter="price"] .digi-filter__submit')]);
          const url1 = new URL(page.url());
          const html1 = await page.content();
          const p1 = widgetIds(html1);
          const n1 = nativeIds((await get(withQuery(tagUrl, [['max_price', '75']]))).html);
          const n2 = nativeIds((await get(withQuery(tagUrl.replace(/\/?$/, '/') + 'page/2/', [['max_price', '75']]))).html);
          const l1 = loopIds(html1, 'wfloop5');
          await shot(page, `W10-${site.id}-precio`);
          check(s, `W10.1a ${tag}`, 'el filtro de precio con destino «Tienda» manda `max_price` (sin `e-filter-…` ni el mínimo vacío) y el widget Productos con «Consulta actual» muestra la primera página de la tienda nativa', url1.searchParams.get('max_price') === '75' && !url1.searchParams.has('min_price') && ![...url1.searchParams.keys()].some((k) => k.startsWith('e-filter-')) && same(p1, n1) && !same(p1, pb), JSON.stringify({ url: url1.pathname + url1.search, products: nameList(catalog, p1), native: nameList(catalog, n1) }));
          check(s, `W10.2a ${tag}`, 'un Loop Grid con «Consulta actual» no se filtra en la tienda: la misma primera página que sin filtro', same(l1, lb), JSON.stringify({ filtered: nameList(catalog, l1), unfiltered: nameList(catalog, lb) }));
          const next = await page.$eval('.elementor-widget-woocommerce-products .woocommerce-pagination a.next, .elementor-widget-woocommerce-products .woocommerce-pagination a.page-numbers:not(.current)', (a) => a.href).catch(() => '');
          let p2 = null;
          if (next) {
            await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('.elementor-widget-woocommerce-products .woocommerce-pagination a.page-numbers:not(.current):not(.prev)')]);
            p2 = widgetIds(await page.content());
          }
          check(s, `W10.1b ${tag}`, 'la paginación del widget conserva el filtro: la página 2 es la de la tienda nativa con `max_price`', !!next && /max_price=75/.test(next) && /\/page\/2\//.test(next) && same(p2, n2) && n2.length > 0, JSON.stringify({ next, page2: nameList(catalog, p2), native2: nameList(catalog, n2) }));

          // Atributo: 38 y 42 con «O» → parámetros nativos; «Limpiar» los quita.
          await page.goto(archive, { waitUntil: 'load' });
          await page.waitForSelector('form.digi-filter[data-digi-filter="attribute"][data-digi-ready]');
          await page.click('form[data-digi-filter="attribute"] input[value="eu-38"]');
          await page.click('form[data-digi-filter="attribute"] input[value="eu-42"]');
          await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('form[data-digi-filter="attribute"] .digi-filter__submit')]);
          const url3 = new URL(page.url());
          const p3 = widgetIds(await page.content());
          const n3 = nativeIds((await get(withQuery(tagUrl, [['filter_talla-zapato', 'eu-38,eu-42', true], ['query_type_talla-zapato', 'or']]))).html);
          check(s, `W10.1c ${tag}`, 'el filtro de atributo con destino «Tienda» manda `filter_talla-zapato=eu-38,eu-42` y `query_type_talla-zapato=or`, y Productos muestra la tienda nativa', url3.searchParams.get('filter_talla-zapato') === 'eu-38,eu-42' && url3.searchParams.get('query_type_talla-zapato') === 'or' && same(p3, n3) && p3.length > 0, JSON.stringify({ url: url3.pathname + url3.search, products: nameList(catalog, p3), native: nameList(catalog, n3) }));
          const clearHref = await page.$eval('form[data-digi-filter="attribute"] .digi-filter__clear', (a) => a.href + ' ' + a.rel).catch(() => '');
          await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('form[data-digi-filter="attribute"] .digi-filter__clear')]);
          const url4 = new URL(page.url());
          const p4 = widgetIds(await page.content());
          check(s, `W10.1d ${tag}`, '«Limpiar» (con `rel="nofollow"`) quita los parámetros del atributo y Productos vuelve a la primera página sin filtro', !url4.searchParams.has('filter_talla-zapato') && !url4.searchParams.has('query_type_talla-zapato') && same(p4, pb) && /nofollow/.test(clearHref), JSON.stringify({ url: url4.pathname + url4.search, products: nameList(catalog, p4), clearHref }));
          check(s, `W10.1e ${tag}`, 'sin errores de JavaScript ni respuestas de error del sitio; nada sale del sitio', !facts.errors.length && !facts.failed.length && !facts.outside.length, JSON.stringify({ errors: facts.errors.slice(0, 5), failed: facts.failed.slice(0, 5), outside: facts.outside.slice(0, 3) }));
        } catch (e) {
          await shot(page, `W10-${site.id}-falla`);
          check(s, `W10.1 ${tag}`, 'la tienda filtrada en Chrome', false, String(e.stack || e.message || e).slice(0, 500));
        } finally {
          await ctx.close().catch(() => {});
        }

        // W10.2b · el aviso del editor: la configuración de los controles que recibe el panel.
        const controls = await op(site, 'filters_controls');
        const notes = ['digitalisimo-woo-price-filter', 'digitalisimo-woo-attribute-filter'].map((name) => controls[name] || {});
        const okNotes = notes.every((c) => c.shop_note && c.shop_note.type === 'raw_html' && /no filtra un Loop Grid; usa el destino Loop Grid/.test(c.shop_note.raw) && c.shop_note.condition && c.shop_note.condition.destination === 'shop' && c.loop_note && /Consulta actual/.test(c.loop_note.raw) && c.loop_note.condition && c.loop_note.condition.destination === 'loop' && c.destination && c.destination.default === 'loop');
        check(s, `W10.2b ${tag}`, 'el editor avisa: con «Tienda», «Este destino no filtra un Loop Grid; usa el destino Loop Grid»; con «Loop Grid», que uno con «Consulta actual» no se puede filtrar (los dos widgets)', okNotes, JSON.stringify(controls).slice(0, 900));

        // Hecho anotado (no es una comprobación): sin la tabla de atributos, WooCommerce pone el atributo en el
        // `tax_query` de la consulta principal y un Loop Grid con «Consulta actual» lo copia.
        await configure(site, { lookup: 'no', hide: 'no' });
        const leak = await get(withQuery(archive, [['filter_talla-zapato', 'eu-40', true], ['query_type_talla-zapato', 'or']]));
        s.facts = s.facts || {};
        s.facts[tag] = { loop_current_query_without_lookup_table: { attribute: 'eu-40', loop: nameList(catalog, loopIds(leak.html, 'wfloop5')), products: nameList(catalog, widgetIds(leak.html)), unfiltered_loop: nameList(catalog, lb) } };
      } finally {
        await configure(site, { lookup: previous.before.woocommerce_attribute_lookup_enabled || 'yes', hide: previous.before.woocommerce_hide_out_of_stock_items || 'no', rows: previous.before.woocommerce_catalog_rows || 4 }).catch(() => {});
      }
    }
  }

  return { W9, W10 };
};
