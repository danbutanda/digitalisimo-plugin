// Escenarios W6 (muestras de variación), W7 (barra de envío con las tarifas reales) y W8 (cupón en el mini carrito) de
// woo-flow.sh (F4-Woo, V.16.4 y V.17 #12–#16). Preparación en woo-flow/setup/swatchescart.php y operaciones de sonda
// en woo-flow/ops/swatchescart.php (`swatchescart_set&case=…` deja el sitio en un caso y restablece los demás).
//
// - W6 se comprueba en cada sitio (NET=1: también /dos/ y /tres/). W6.4 (bloque de WooCommerce y widget clásico en un
//   tema de bloques) sólo puede existir con THEME=block: en los demás modos se comprueba la mitad clásica (mismos colores
//   que las metas de WooCommerce) y la del bloque queda pendiente de esa corrida.
// - W7 y W8 corren en un sitio (el /dos/ con NET=1: un sitio de la red que no es el principal).
// - W6_BASELINE=<summary o json de una corrida con ELEMENTS_DIR=4.3.0.165>: compara además los <select> con los que
//   imprimió 4.3.0.165. Con ELEMENTS_DIR=4.3.0.165, W6 sólo guarda esa línea base ($OUT/w6-baseline.json).
const fs = require('fs');
const path = require('path');

module.exports = (api) => {
  const { ENV, OUT, NET, SITES, WIDTHS, TOKEN, summary, sleep, log, request, check, pending, visitor, shot, settled } = api;
  const BLOCK = ENV.THEME === 'block';
  // Línea base: un Elements sin F4-Woo (ELEMENTS_DIR=<ZIP de 4.3.0.165 extraído>); la versión del árbol aún no sube.
  const baselineMode = () => SITES.every((site) => { const sc = setupOf(site); return sc && sc.f4woo === false; });
  const setupOf = (site) => ((summary.sites[site.path] || {}).setup || {}).swatchescart || null;
  const sfx = (site) => (SITES.length > 1 ? ` ${site.path}` : '');
  const norm = (t) => String(t || '').replace(/ /g, ' ').replace(/\s+/g, ' ').trim();
  const money = (n) => `${n.toFixed(2).replace('.', ',')} €`;
  const json = (v) => JSON.stringify(v).slice(0, 900);
  const own = () => (NET ? SITES[1] : SITES[0]);

  async function op(site, name, params = {}) {
    const q = new URLSearchParams({ digi_wf: name, token: TOKEN, ...params });
    const res = await request('GET', `${site.path}?${q}`);
    if (res.status !== 200 || !res.json || res.json.error) throw new Error(`${name} en ${site.path}: ${res.status} ${res.text.slice(0, 300)}`);
    return res.json;
  }
  /** Visitante sin cookies que descarta los diálogos (WooCommerce usa alert()) y anota las peticiones POST. */
  async function guest(width = 1280) {
    const v = await visitor();
    v.dialogs = [];
    v.posts = [];
    v.page.on('dialog', (d) => { v.dialogs.push(d.message()); d.dismiss().catch(() => {}); });
    v.page.on('request', (r) => {
      if (r.method() !== 'POST') return;
      const entry = { url: r.url(), body: r.postData() || '' };
      v.posts.push(entry);
      if (!entry.body && typeof r.hasPostData === 'function' && r.hasPostData() && typeof r.fetchPostData === 'function') r.fetchPostData().then((b) => { entry.body = b || ''; }).catch(() => {});
    });
    await v.page.setViewport({ width, height: 900 });
    timing(v.page, v.facts);
    return v;
  }
  /** Duración de las peticiones `wc-ajax` (fragmentos, cupón) para el detalle: el servidor de pruebas es lento. */
  function timing(page, facts) {
    const open = new Map();
    facts.ajax = facts.ajax || [];
    page.on('request', (r) => { const m = r.url().match(/wc-ajax=([a-z_]+)/); if (m) open.set(r, [m[1], Date.now()]); });
    const done = (r) => { const o = open.get(r); if (o) { facts.ajax.push(`${o[0]}:${Date.now() - o[1]}`); open.delete(r); } };
    page.on('requestfinished', done);
    page.on('requestfailed', done);
  }
  const goto = (page, url) => page.goto(url, { waitUntil: 'load' });
  // La tipografía por defecto del kit pide Google Fonts (núcleo, no F4-Woo): Chrome no resuelve ese dominio. Se anota
  // aparte y no cuenta como error de los widgets; cualquier otra petición fuera del sitio sí.
  const FONTS = /fonts\.(googleapis|gstatic)\.com/;
  const outside = (facts) => facts.outside.filter((u) => !FONTS.test(u));
  const fonts = (facts) => facts.outside.length - outside(facts).length;
  const jsErrors = (facts) => facts.errors.filter((e) => !/Failed to load resource: the server responded with a status of 403/.test(e) && !(/net::ERR_NAME_NOT_RESOLVED/.test(e) && fonts(facts) > 0 && !outside(facts).length));
  /** Errores de JavaScript y peticiones fallidas de una pestaña más del mismo visitante. */
  function track(page, facts) {
    page.on('console', (m) => { if (m.type() === 'error') facts.errors.push(m.text().slice(0, 300)); });
    page.on('pageerror', (e) => facts.errors.push(`pageerror: ${String(e.message || e).slice(0, 300)}`));
    page.on('requestfailed', (r) => { const u = r.url(); const why = r.failure() ? r.failure().errorText : 'falló'; if (why === 'net::ERR_ABORTED' && new URL(u).host === api.HOST) return; (new URL(u).host === api.HOST ? facts.failed : facts.outside).push(`${why} ${u.slice(0, 200)}`); });
    page.on('response', (r) => { if (r.status() >= 400 && new URL(r.url()).host === api.HOST) facts.failed.push(`${r.status()} ${r.url().slice(0, 200)}`); });
    timing(page, facts);
    return page;
  }
  const clean = (facts) => !jsErrors(facts).length && !facts.failed.length && !outside(facts).length;
  const slowest = (facts) => { const ms = (facts.ajax || []).map((x) => +x.split(':')[1]).sort((a, b) => a - b); return ms.length ? { n: ms.length, median: ms[Math.floor(ms.length / 2)], max: ms[ms.length - 1] } : null; };
  const factsOf = (facts) => json({ errors: jsErrors(facts).slice(0, 4), failed: facts.failed.slice(0, 4), outside: outside(facts).slice(0, 4), kitFonts: fonts(facts), wcAjaxMs: slowest(facts) });

  // ── W6 · Muestras ──────────────────────────────────────────────────────────────────────────────────────────────
  const fieldInfo = () => [...document.querySelectorAll('.digi-swatch-field')].map((f) => {
    const sel = f.querySelector('select');
    const radios = [...f.querySelectorAll('input[type="radio"]')];
    return {
      tax: f.getAttribute('data-digi-swatches'),
      ready: f.classList.contains('digi-swatch-field--ready'),
      value: sel ? sel.value : null,
      selectRequired: sel ? sel.required : null,
      selectHidden: sel ? getComputedStyle(sel).display === 'none' : null,
      options: sel ? [...sel.options].map((o) => o.value + (o.disabled ? '!' : '')) : [],
      radios: radios.map((r) => ({ v: r.value, on: r.checked, off: r.disabled, req: r.required, sel: r.closest('.digi-swatch').classList.contains('is-selected'), name: (r.labels && r.labels[0] ? r.labels[0].textContent : '').replace(/\s+/g, ' ').trim(), own: !!(r.form && r.form.hidden && !r.form.contains(r) && r.form !== r.closest('form.variations_form') && !r.closest('form.variations_form').contains(r.form)) })),
    };
  });
  const byTax = (fields, tax) => fields.find((f) => f.tax === tax) || { radios: [], options: [] };
  const radio = (field, v) => field.radios.find((r) => r.v === v) || {};
  const clickRadio = (page, tax, v) => page.click(`.digi-swatch-field[data-digi-swatches="${tax}"] input[value="${v}"]`);
  async function readyFields(page) {
    await page.waitForSelector('.digi-swatch-field--ready', { timeout: 60000 });
    await sleep(250);
    return page.evaluate(fieldInfo);
  }

  /** W6.1: producto de 31 variaciones (modo AJAX): puente, `required`, combinación inexistente y POST sin radios. */
  async function w6Ajax(s, site, sc) {
    const t = sfx(site);
    const { ctx, page, facts, posts, dialogs } = await guest();
    try {
      await goto(page, sc.urls.ajax);
      let fields = await readyFields(page);
      const mode = await page.$eval('form.variations_form', (f) => f.getAttribute('data-product_variations'));
      const tono = byTax(fields, 'pa_tono');
      const medida = byTax(fields, 'pa_medida');
      check(s, `W6.1a${t}`, 'producto de 31 variaciones en modo AJAX (`data-product_variations="false"`): un radio por opción, <select> oculto e intacto y cada radio en un formulario vacío propio, fuera del de compra', mode === 'false' && sc.variations.ajax === 31 && tono.radios.length === 4 && medida.radios.length === 8 && tono.selectHidden && medida.selectHidden && [...tono.radios, ...medida.radios].every((r) => r.own) && tono.options.length === 5 && medida.options.length === 9, json({ mode, variations: sc.variations.ajax, tono: tono.radios.length, medida: medida.radios.length, hidden: [tono.selectHidden, medida.selectHidden], options: [tono.options.length, medida.options.length], own: [...tono.radios, ...medida.radios].every((r) => r.own) }));

      // `required`: el <select> lo traía (mu-plugin) y pasa al primer radio; enviar sin elegir no hace el POST.
      const before = posts.length;
      const req = await page.evaluate(async () => {
        const form = document.querySelector('form.variations_form');
        let passed = null;
        const watch = (e) => { passed = !e.defaultPrevented; };
        form.addEventListener('submit', watch);
        form.requestSubmit();
        await new Promise((r) => setTimeout(r, 400));
        form.removeEventListener('submit', watch);
        const first = document.querySelector('.digi-swatch-field input[type="radio"]');
        return { passed, valueMissing: first.validity.valueMissing, message: first.validationMessage };
      });
      await sleep(500);
      check(s, `W6.1b${t}`, '`required`: el <select> oculto deja de serlo, el primer radio de cada grupo lo es y enviar sin elegir no envía el pedido (aviso de obligatorio en el radio)', tono.selectRequired === false && medida.selectRequired === false && radio(tono, 'rojo').req && radio(medida, 'xs').req && !tono.radios.slice(1).some((r) => r.req) && req.passed === false && req.valueMissing && posts.length === before, json({ req, selectRequired: [tono.selectRequired, medida.selectRequired], firstRequired: [radio(tono, 'rojo').req, radio(medida, 'xs').req], posts: posts.length - before }));

      // Puente jQuery del modo AJAX: un cambio con `.val().trigger('change')` (sin evento nativo ni mutación) se refleja.
      await page.evaluate(() => window.jQuery('select[name="attribute_pa_tono"]').val('azul').trigger('change'));
      await sleep(400);
      fields = await page.evaluate(fieldInfo);
      check(s, `W6.1c${t}`, 'puente del modo AJAX: un cambio del <select> hecho con jQuery (`woocommerce_variation_has_changed`) marca su radio', radio(byTax(fields, 'pa_tono'), 'azul').on && radio(byTax(fields, 'pa_tono'), 'azul').sel && byTax(fields, 'pa_tono').radios.filter((r) => r.on).length === 1, json(byTax(fields, 'pa_tono').radios));

      // Combinación inexistente (negro 4XL): WooCommerce no quita opciones en modo AJAX; radios y <select> coinciden.
      // En modo AJAX, WooCommerce cubre el formulario (blockUI) mientras pide la variación: se espera entre clics.
      await clickRadio(page, 'pa_tono', 'negro');
      await settled(page);
      await clickRadio(page, 'pa_medida', '4xl');
      await page.waitForSelector('.wc-no-matching-variations', { timeout: 60000 }).catch(() => {});
      await settled(page);
      fields = await page.evaluate(fieldInfo);
      const none = await page.$('.wc-no-matching-variations');
      check(s, `W6.1d${t}`, 'combinación inexistente en modo AJAX: WooCommerce avisa y los radios siguen el <select> (habilitados, porque en este modo no quita opciones)', !!none && byTax(fields, 'pa_tono').value === 'negro' && byTax(fields, 'pa_medida').value === '4xl' && radio(byTax(fields, 'pa_tono'), 'negro').on && radio(byTax(fields, 'pa_medida'), '4xl').on && ![...byTax(fields, 'pa_tono').radios, ...byTax(fields, 'pa_medida').radios].some((r) => r.off), json({ none: !!none, values: [byTax(fields, 'pa_tono').value, byTax(fields, 'pa_medida').value] }));

      // Combinación válida: precio de la variación, compra, y los radios no viajan en el POST.
      await clickRadio(page, 'pa_tono', 'rojo');
      await settled(page);
      await clickRadio(page, 'pa_medida', 's');
      await settled(page);
      await page.waitForFunction(() => { const i = document.querySelector('form.variations_form input.variation_id'); return i && i.value && !document.querySelector('.single_add_to_cart_button.disabled'); }, { timeout: 60000 });
      await settled(page);
      const sent = await page.evaluate(() => { const fd = new FormData(document.querySelector('form.variations_form')); return [...fd.keys()]; });
      const variation = await page.$eval('form.variations_form input.variation_id', (i) => i.value);
      const shown = await page.$eval('.single_variation_wrap', (e) => getComputedStyle(e).display !== 'none').catch(() => false);
      const mark = posts.length;
      await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 120000 }), page.click('form.variations_form .single_add_to_cart_button')]);
      await sleep(300);
      const post = posts.slice(mark).find((p) => !/wc-ajax=/.test(p.url)) || {};
      const notice = await page.$eval('.woocommerce-message, .wc-block-components-notice-banner', (e) => e.textContent.trim()).catch(() => '');
      const body = String(post.body || '').replace(/\r?\n/g, ' ');
      check(s, `W6.1e${t}`, 'una combinación válida (Rojo, S) habilita la compra y se compra; el POST lleva `attribute_pa_tono=rojo`, `attribute_pa_medida=s` y `variation_id`, y ningún radio (`digi_sw…`)', !!variation && shown && sent.includes('attribute_pa_tono') && sent.includes('attribute_pa_medida') && sent.includes('variation_id') && !sent.some((k) => /^digi/.test(k)) && /name="attribute_pa_tono"\s+rojo/.test(body) && /name="attribute_pa_medida"\s+s\b/.test(body) && !/digi[-_]sw/.test(body) && /carrito|cart/i.test(notice), json({ variation, shown, sent, body: body.slice(0, 400), notice: notice.slice(0, 120) }));
      check(s, `W6.1f${t}`, 'sin errores de JavaScript ni respuestas de error del sitio; ninguna petición sale del sitio (salvo Google Fonts del kit, que no es F4-Woo)', clean(facts) && !dialogs.length, factsOf(facts) + json({ dialogs }));
    } catch (e) {
      await shot(page, `W6-ajax-${site.id}-falla`);
      check(s, `W6.1${t}`, 'producto de 31 variaciones (modo AJAX)', false, String(e.stack || e).slice(0, 500));
    } finally {
      await ctx.close().catch(() => {});
    }
  }

  /** W6.1 (opciones quitadas) y W6.2 («Limpiar») en el producto de 5 variaciones (modo nativo). */
  async function w6Native(s, site, sc) {
    const t = sfx(site);
    const { ctx, page, facts } = await guest();
    try {
      await goto(page, sc.urls.muestras);
      let fields = await readyFields(page);
      const start = byTax(fields, 'pa_tono');
      await clickRadio(page, 'pa_medida', 'm');
      await page.waitForFunction(() => { const s = document.querySelector('select[name="attribute_pa_tono"]'); return s && ![...s.options].some((o) => o.value === 'verde'); }, { timeout: 30000 });
      await sleep(300);
      fields = await page.evaluate(fieldInfo);
      const verde = radio(byTax(fields, 'pa_tono'), 'verde');
      const swatch = await page.$eval('.digi-swatch-field[data-digi-swatches="pa_tono"] input[value="verde"]', (r) => r.closest('.digi-swatch').classList.contains('is-disabled'));
      check(s, `W6.1g${t}`, 'opción quitada por WooCommerce (elegir M quita «Verde»): su radio queda deshabilitado, con «(no disponible)» en su nombre accesible y marcado; los demás siguen activos', start.radios.length === 3 && !start.radios.some((r) => r.off) && verde.off && /\(no disponible\)/.test(verde.name) && swatch && !radio(byTax(fields, 'pa_tono'), 'rojo').off && !radio(byTax(fields, 'pa_tono'), 'azul').off && radio(byTax(fields, 'pa_medida'), 'm').on, json({ verde, options: byTax(fields, 'pa_tono').options }));

      await clickRadio(page, 'pa_tono', 'rojo');
      await page.waitForFunction(() => { const i = document.querySelector('form.variations_form input.variation_id'); return i && i.value; }, { timeout: 30000 });
      await page.waitForSelector('.reset_variations', { visible: true, timeout: 30000 });
      await page.click('.reset_variations');
      await sleep(600);
      fields = await page.evaluate(fieldInfo);
      const all = [...byTax(fields, 'pa_tono').radios, ...byTax(fields, 'pa_medida').radios];
      check(s, `W6.2b${t}`, '«Limpiar»: los <select> vuelven a vacío y los radios a ninguno marcado y todos habilitados (con «Verde» de vuelta)', byTax(fields, 'pa_tono').value === '' && byTax(fields, 'pa_medida').value === '' && all.length === 5 && !all.some((r) => r.on || r.sel || r.off) && !/no disponible/.test(radio(byTax(fields, 'pa_tono'), 'verde').name), json(fields.map((f) => ({ tax: f.tax, value: f.value, radios: f.radios }))));
      check(s, `W6.1h${t}`, 'sin errores de JavaScript en el modo nativo', clean(facts), factsOf(facts));
    } catch (e) {
      await shot(page, `W6-native-${site.id}-falla`);
      check(s, `W6.1g${t}`, 'opciones quitadas y «Limpiar» (modo nativo)', false, String(e.stack || e).slice(0, 500));
    } finally {
      await ctx.close().catch(() => {});
    }
  }

  const selectIn = (html, id) => { const m = String(html).match(new RegExp(`<select id="${id}"[\\s\\S]*?</select>`)); return m ? m[0] : ''; };
  /** Línea base de 4.3.0.165 si se pasó W6_BASELINE (un w6-baseline.json o el summary.json de esa corrida). */
  function baseline() {
    const file = process.env.W6_BASELINE;
    if (!file) return null;
    const raw = api.readJson(file);
    if (!raw) return { error: `W6_BASELINE ilegible: ${file}` };
    return raw.w6_baseline || raw;
  }

  /** W6.2a: un atributo sin configurar deja el <select> de WooCommerce igual que 4.3.0.165. */
  async function w6Select(s, site, sc) {
    const t = sfx(site);
    const raw = await op(site, 'swatchescart_select', { product: sc.products.sudadera, attribute: 'pa_color' });
    const page = await request('GET', sc.urls.sudadera.replace(/^https?:\/\/[^/]+/, ''));
    const served = selectIn(page.text, 'pa_color');
    // 4.3.0.165 no engancha el filtro del desplegable: su <select> es el de WooCommerce sin ningún filtro de Elements.
    const old = '/var/tmp/digi-f4/sc-165/digitalisimo-elements';
    let hooks165 = null;
    if (fs.existsSync(old)) {
      hooks165 = 0;
      const walk = (dir) => { for (const e of fs.readdirSync(dir, { withFileTypes: true })) { const p = path.join(dir, e.name); if (e.isDirectory()) walk(p); else if (/\.php$/.test(e.name) && fs.readFileSync(p, 'utf8').includes('woocommerce_dropdown_variation_attribute_options')) hooks165++; } };
      walk(old);
    }
    const base = baseline();
    const baseServed = base && base.sites && base.sites[site.path] ? base.sites[site.path].sudadera_pa_color : null;
    const ok = raw.hooked !== false && raw.with === raw.without && served === raw.without && served.length > 0 && !/digi-swatch-field|digitalisimo-woo-swatches/.test(page.text) && (hooks165 === null || hooks165 === 0) && (baseServed === null || baseServed === served);
    check(s, `W6.2a${t}`, 'un atributo sin configurar (`pa_color` de la Sudadera): el <select> servido es byte a byte el de WooCommerce sin el filtro de las muestras (lo que imprime 4.3.0.165, que no lo engancha), sin envoltorio ni la hoja o el script de las muestras', ok, json({ hooked: raw.hooked, same: raw.with === raw.without, served: served === raw.without, bytes: served.length, assets: /digitalisimo-woo-swatches/.test(page.text), hooks165, baseline: baseServed === null ? (base && base.error) || 'sin W6_BASELINE' : baseServed === served }));
    // El <select> de un atributo con muestras queda intacto dentro del envoltorio (sin JS se ve tal cual).
    const tono = await op(site, 'swatchescart_select', { product: sc.products.muestras, attribute: 'pa_tono' });
    const muestras = await request('GET', sc.urls.muestras.replace(/^https?:\/\/[^/]+/, ''));
    const wrapped = selectIn(muestras.text, 'pa_tono');
    const head = muestras.text.split('</head>')[0];
    const baseTono = base && base.sites && base.sites[site.path] ? base.sites[site.path].muestras_pa_tono : null;
    check(s, `W6.2d${t}`, 'atributo con muestras: el <select> de WooCommerce sigue byte a byte dentro del envoltorio, y la hoja de las muestras va en <head> (ficha con muestras)', tono.hooked !== false && tono.with.startsWith('<div class="digi-swatch-field"') && tono.with.includes(tono.without) && wrapped === tono.without && /digitalisimo-woo-swatches-css/.test(head) && (baseTono === null || baseTono === wrapped), json({ hooked: tono.hooked, inside: tono.with.includes(tono.without), served: wrapped === tono.without, head: /digitalisimo-woo-swatches-css/.test(head), baseline: baseTono === null ? 'sin W6_BASELINE' : baseTono === wrapped }));
  }

  /** W6.2c: el bucle enlaza con la preselección (`attribute_pa_*`, `rel="nofollow"`). */
  async function w6Loop(s, site, sc) {
    const t = sfx(site);
    const { ctx, page, facts } = await guest();
    try {
      await goto(page, sc.pages.bucle);
      const sel = `a.digi-swatch[href*="digi-wf-sc-muestras"][href*="attribute_pa_tono=azul"]`;
      await page.waitForSelector(sel, { timeout: 60000 });
      const link = await page.$eval(sel, (a) => ({ href: a.getAttribute('href'), rel: a.getAttribute('rel'), name: a.textContent.replace(/\s+/g, ' ').trim(), color: getComputedStyle(a).backgroundColor }));
      const count = await page.$$eval('a.digi-swatch[href*="digi-wf-sc-muestras"]', (l) => l.length);
      await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click(sel)]);
      const fields = await readyFields(page);
      const tono = byTax(fields, 'pa_tono');
      check(s, `W6.2c${t}`, 'bucle: cada término enlaza la ficha con `attribute_pa_tono` y `rel="nofollow"`; abrir «Azul» llega con su radio y su <select> ya elegidos', count === 3 && /nofollow/.test(link.rel || '') && tono.value === 'azul' && radio(tono, 'azul').on && radio(tono, 'azul').sel, json({ link, count, value: tono.value }));
      check(s, `W6.2e${t}`, 'bucle y ficha sin errores de JavaScript', clean(facts), factsOf(facts));
    } catch (e) {
      await shot(page, `W6-loop-${site.id}-falla`);
      check(s, `W6.2c${t}`, 'el bucle enlaza con la preselección', false, String(e.stack || e).slice(0, 500));
    } finally {
      await ctx.close().catch(() => {});
    }
  }

  /** CLS de una ficha: desplazamientos sin entrada reciente; los atribuidos al formulario de variaciones aparte. */
  async function cls(url, width, throttle) {
    const { ctx, page } = await guest(width);
    try {
      if (throttle) { const cdp = await page.target().createCDPSession(); await cdp.send('Emulation.setCPUThrottlingRate', { rate: throttle }); }
      await page.evaluateOnNewDocument(() => {
        window.__digiShifts = [];
        window.__digiReady = null;
        try {
          new PerformanceObserver((list) => {
            for (const e of list.getEntries()) {
              const r = (x) => `${Math.round(x.x)},${Math.round(x.y)} ${Math.round(x.width)}x${Math.round(x.height)}`;
              window.__digiShifts.push({ value: e.value, input: e.hadRecentInput, t: Math.round(e.startTime), sources: (e.sources || []).map((src) => {
                let n = src.node;
                const el = n && n.nodeType !== 1 ? n.parentElement : n;
                const inForm = !!(el && el.closest && el.closest('form.variations_form, .digi-swatch-field'));
                const name = !n ? '?' : n.nodeType !== 1 ? `${n.nodeName}(${String(n.textContent).trim().slice(0, 20)})<${el ? el.nodeName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/)[0] : '') : ''}` : n.nodeName.toLowerCase() + (n.id ? '#' + n.id : '') + (typeof n.className === 'string' && n.className.trim() ? '.' + n.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
                return `${inForm ? 'FORM>' : ''}${name} ${r(src.previousRect)}→${r(src.currentRect)}`;
              }) });
            }
          }).observe({ type: 'layout-shift', buffered: true });
          new MutationObserver(() => { if (window.__digiReady === null && document.querySelector('.digi-swatch-field--ready')) window.__digiReady = Math.round(performance.now()); }).observe(document, { subtree: true, attributes: true, attributeFilter: ['class'] });
        } catch (e) { /* sin API */ }
      });
      await goto(page, url);
      await page.waitForSelector('.digi-swatch-field--ready', { timeout: 60000 }).catch(() => {});
      await sleep(1500);
      return await page.evaluate(() => {
        const paint = performance.getEntriesByType('paint').find((p) => p.name === 'first-contentful-paint');
        const shifts = window.__digiShifts.filter((x) => !x.input);
        const sum = (list) => Math.round(list.reduce((a, x) => a + x.value, 0) * 10000) / 10000;
        // El paso a los radios, sin depender del momento: quitar `--ready` (como antes de arrancar el script) no cambia
        // la caja de los campos ni la posición del botón de compra.
        const box = (el) => { if (!el) return ''; const r = el.getBoundingClientRect(); return [r.x, r.y, r.width, r.height].map(Math.round).join(','); };
        const fields = [...document.querySelectorAll('.digi-swatch-field--ready')];
        const button = document.querySelector('form.variations_form .single_add_to_cart_button');
        const ready = fields.map(box).concat(box(button)).join('|');
        fields.forEach((f) => f.classList.remove('digi-swatch-field--ready'));
        const before = fields.map(box).concat(box(button)).join('|');
        fields.forEach((f) => f.classList.add('digi-swatch-field--ready'));
        // Desplazamientos del paso: los que ocurren desde que el script marca los campos hasta 500 ms después.
        const swap = window.__digiReady === null ? [] : shifts.filter((x) => x.t >= window.__digiReady - 16 && x.t <= window.__digiReady + 500);
        const late = paint ? performance.getEntriesByType('resource').filter((x) => x.responseEnd > paint.startTime && /\.(css|woff2?|ttf)(\?|$)/.test(x.name)).map((x) => `${Math.round(x.responseEnd)} ${x.name.replace(/^https?:\/\/[^/]+/, '').slice(0, 90)}`) : [];
        return { cls: sum(shifts), form: sum(shifts.filter((x) => x.sources.some((n) => n.startsWith('FORM>')))), swap: sum(swap), stable: !fields.length || ready === before, fcp: paint ? Math.round(paint.startTime) : null, ready: window.__digiReady, shifts: shifts.slice(0, 6), late };
      });
    } finally {
      await ctx.close().catch(() => {});
    }
  }

  /** W6.3: el paso del <select> a los radios no mueve la ficha (CLS), en cada ancho y con la CPU frenada ×6. */
  async function w6Cls(s, site, sc, light = false) {
    const t = sfx(site);
    const rows = [];
    // En cada ancho, la ficha con muestras con la CPU normal y frenada ×6; en el ancho mayor, además la de 31
    // variaciones (frenada) y la ficha sin muestras (referencia del tema) con la CPU normal y frenada.
    for (const width of WIDTHS) {
      const plan = [['muestras', sc.urls.muestras, 0], ['muestras', sc.urls.muestras, 6]];
      if (width === Math.max(...WIDTHS) && !light) plan.push(['ajax', sc.urls.ajax, 6], ['sudadera', sc.urls.sudadera, 0], ['sudadera', sc.urls.sudadera, 6]);
      for (const [key, url, throttle] of plan) {
        const r = await cls(url, width, throttle);
        rows.push({ width, throttle, key, ...r });
      }
    }
    const swatches = rows.filter((r) => r.key !== 'sudadera');
    const swap = Math.max(...swatches.map((r) => r.swap));
    const total = Math.max(...swatches.map((r) => r.cls));
    const refs = rows.filter((r) => r.key === 'sudadera').map((r) => r.cls);
    const stable = swatches.every((r) => r.stable && r.ready !== null);
    const afterPaint = swatches.filter((r) => r.ready !== null && r.fcp !== null && r.ready > r.fcp).length;
    // Lo que se exige: el paso del <select> a los radios no mueve nada (medido y comprobado sin depender del momento) y la
    // ficha queda en «bueno» (< 0,1). La tabla de variaciones de WooCommerce puede reajustar sus columnas tras la primera
    // pintura con la CPU frenada, también sin muestras: se anota la ficha sin muestras como referencia.
    check(s, `W6.3${t}`, `CLS de la ficha con muestras (${WIDTHS.join(', ')} px; CPU normal y ×6): el paso del <select> a los radios no desplaza nada (0 en su ventana y cajas iguales con y sin el script) y el CLS total queda por debajo de 0,1`, swap === 0 && stable && total < 0.1, json({ swap, stable, total, sinMuestras: refs, readyAfterFcp: `${afterPaint}/${swatches.length}`, rows: rows.map((r) => `${r.key}@${r.width}${r.throttle ? 'x' + r.throttle : ''}:${r.cls}/${r.swap}${r.stable ? '' : ' MUEVE'} fcp ${r.fcp} ready ${r.ready}`) }));
    fs.writeFileSync(path.join(OUT, `w6-cls-${site.id}.json`), JSON.stringify(rows, null, 1));
  }

  /** W6.4: colores de las muestras clásicas = metas `color` de WooCommerce; con THEME=block, iguales a las del bloque. */
  async function w6Colors(s, site, sc) {
    const t = sfx(site);
    const { ctx, page, facts } = await guest();
    const rgb = (hex) => { const h = hex.replace('#', ''); const f = h.length === 3 ? h.split('').map((c) => c + c).join('') : h; return `rgb(${parseInt(f.slice(0, 2), 16)}, ${parseInt(f.slice(2, 4), 16)}, ${parseInt(f.slice(4, 6), 16)})`; };
    try {
      if (!BLOCK) {
        await goto(page, sc.urls.muestras);
        await readyFields(page);
        const classic = await page.$$eval('.digi-swatch-field[data-digi-swatches="pa_tono"] input[type="radio"]', (l) => l.map((r) => [r.value, getComputedStyle(r.closest('.digi-swatch')).backgroundColor]));
        const ok = classic.length === 3 && classic.every(([v, c]) => sc.colors[v] && c === rgb(sc.colors[v]));
        check(s, `W6.4a${t}`, 'el formulario clásico pinta cada término con su meta `color` de WooCommerce (la misma que lee el bloque)', ok, json({ classic, metas: sc.colors }));
        pending(s, `W6.4b${t}`, 'THEME=block con `wc-visual-attribute` activa: el bloque «Añadir al carrito con opciones» pinta sus muestras y el widget clásico las suyas con los mismos colores (el bloque sólo existe con un tema de bloques: corrida THEME=block)');
        return;
      }
      await goto(page, sc.pages.convivencia);
      await page.waitForSelector('.wc-block-add-to-cart-with-options', { timeout: 60000 });
      await readyFields(page);
      const got = await page.evaluate(() => {
        const block = document.querySelector('.wc-block-add-to-cart-with-options');
        const chips = [...block.querySelectorAll('.wc-block-product-filter-chips__swatch')].map((sw) => { const b = sw.closest('button'); return [b ? b.value : '', getComputedStyle(sw).backgroundColor, sw.classList.contains('wc-block-product-filter-chips__swatch--no-color')]; });
        const classic = [...document.querySelectorAll('.elementor .digi-swatch-field[data-digi-swatches="pa_tono"] input[type="radio"]')].map((r) => [r.value, getComputedStyle(r.closest('.digi-swatch')).backgroundColor]);
        return { chips, classic, nested: !!block.querySelector('.digi-swatch-field, select[name="attribute_pa_tono"]'), forms: document.querySelectorAll('form.variations_form').length };
      });
      const chips = Object.fromEntries(got.chips.map(([v, c]) => [v, c]));
      const classic = Object.fromEntries(got.classic.map(([v, c]) => [v, c]));
      const terms = Object.keys(sc.colors).filter((k) => k !== 'negro');
      const same = terms.every((k) => chips[k] && classic[k] && chips[k] === classic[k] && chips[k] === rgb(sc.colors[k]));
      check(s, `W6.4a${t}`, 'THEME=block: el formulario clásico pinta cada término con su meta `color` de WooCommerce', terms.every((k) => classic[k] === rgb(sc.colors[k])), json({ classic, metas: sc.colors }));
      check(s, `W6.4b${t}`, 'THEME=block con `wc-visual-attribute` activa y `pa_tono` de tipo `wc-visual`: el bloque «Añadir al carrito con opciones» pinta sus muestras y el widget «Añadir al carrito» de PRO las suyas, en la misma página, con los mismos colores y sin doble pintado', sc.feature === 'yes' && sc.attribute_types && sc.attribute_types.tono === 'wc-visual' && got.chips.length === 3 && !got.chips.some((c) => c[2]) && got.classic.length === 3 && same && !got.nested && got.forms === 1, json({ feature: sc.feature, types: sc.attribute_types, chips: got.chips, classic: got.classic, nested: got.nested, forms: got.forms }));
      check(s, `W6.4c${t}`, 'THEME=block: la página con el bloque y el widget no da errores de JavaScript', clean(facts), factsOf(facts));
      await shot(page, `W6-convivencia-${site.id}`);
    } catch (e) {
      await shot(page, `W6-colores-${site.id}-falla`);
      check(s, `W6.4${t}`, 'convivencia con las muestras de WooCommerce', false, String(e.stack || e).slice(0, 500));
    } finally {
      await ctx.close().catch(() => {});
    }
  }

  /** Con ELEMENTS_DIR=4.3.0.165: guarda los <select> que imprime esa versión (línea base de W6.2). */
  async function w6Baseline(s) {
    const out = { version: ENV.ELEMENTS_VERSION, sha: ENV.ELEMENTS_SHA, sites: {} };
    for (const site of SITES) {
      const sc = setupOf(site);
      if (!sc) { check(s, `W6.base${sfx(site)}`, 'preparación', false, 'sin preparación'); continue; }
      const sud = await request('GET', sc.urls.sudadera.replace(/^https?:\/\/[^/]+/, ''));
      const mu = await request('GET', sc.urls.muestras.replace(/^https?:\/\/[^/]+/, ''));
      out.sites[site.path] = { sudadera_pa_color: selectIn(sud.text, 'pa_color'), muestras_pa_tono: selectIn(mu.text, 'pa_tono'), muestras_pa_medida: selectIn(mu.text, 'pa_medida') };
      check(s, `W6.base${sfx(site)}`, `línea base de ${ENV.ELEMENTS_VERSION}: <select> de la Sudadera y de la ficha con muestras`, !!out.sites[site.path].sudadera_pa_color && !!out.sites[site.path].muestras_pa_tono && !/digi-swatch/.test(mu.text), json(Object.fromEntries(Object.entries(out.sites[site.path]).map(([k, v]) => [k, v.length]))));
    }
    fs.writeFileSync(path.join(OUT, 'w6-baseline.json'), JSON.stringify(out, null, 1));
    summary.w6_baseline = out;
  }

  async function W6(s) {
    if (baselineMode()) return w6Baseline(s);
    for (const site of SITES) {
      const sc = setupOf(site);
      const t = sfx(site);
      const okSetup = !!sc && sc.f4woo && !sc.errors.length && sc.variations.muestras === 5 && sc.variations.ajax === 31 && sc.ajax_threshold === 30 && sc.swatches && sc.swatches.tono && sc.swatches.tono.type === 'visual' && sc.swatches.medida && sc.swatches.medida.type === 'label';
      check(s, `W6.0${t}`, 'preparación: `pa_tono` «Muestra visual» con la meta `color` de WooCommerce, `pa_medida` «Etiqueta», un variable de 5 variaciones y otro de 31 (umbral AJAX 30)', okSetup, json(sc ? { errors: sc.errors, variations: sc.variations, threshold: sc.ajax_threshold, swatches: sc.swatches, types: sc.attribute_types } : 'sin preparación'));
      if (!okSetup) continue;
      if (NET && site !== own()) { await w6Cls(s, site, sc, true); continue; }
      // SC_ONLY=cls,colors… (iteración local): sólo esas partes; la corrida de aceptación las hace todas.
      const only = (part) => !process.env.SC_ONLY || process.env.SC_ONLY.split(',').includes(part);
      if (only('ajax')) await w6Ajax(s, site, sc);
      if (only('native')) await w6Native(s, site, sc);
      if (only('select')) await w6Select(s, site, sc);
      if (only('loop')) await w6Loop(s, site, sc);
      if (only('cls')) await w6Cls(s, site, sc);
      if (only('colors')) await w6Colors(s, site, sc);
    }
  }

  // ── W7 · Barra de envío ────────────────────────────────────────────────────────────────────────────────────────
  /** Carrito clásico con N unidades del producto de 10 € (`?add-to-cart` en la propia página del carrito). */
  async function fill(page, cartUrl, sc, qty) {
    await goto(page, `${cartUrl}${cartUrl.includes('?') ? '&' : '?'}add-to-cart=${sc.products.diez}&quantity=${qty}`);
    await settled(page);
  }
  /**
   * Calculadora del carrito clásico (en la página actual) con la dirección de la tienda: el destino queda conocido
   * (`has_calculated_shipping()`). La envía cart.js por AJAX, que después pide los fragmentos (`updated_wc_div`).
   */
  async function calculate(page) {
    const found = await page.evaluate(() => {
      const $ = window.jQuery;
      const form = document.querySelector('form.woocommerce-shipping-calculator');
      if (!form || !$) return false;
      const set = (sel, v) => { const el = form.querySelector(sel); if (el) { el.value = v; $(el).trigger('change'); } };
      set('#calc_shipping_country', 'ES');
      return true;
    });
    if (!found) throw new Error('el carrito no tiene la calculadora de envío');
    await sleep(400);
    await page.evaluate(() => {
      const $ = window.jQuery;
      const form = document.querySelector('form.woocommerce-shipping-calculator');
      const set = (sel, v) => { const el = form.querySelector(sel); if (el) { el.value = v; $(el).trigger('change'); } };
      const st = form.querySelector('#calc_shipping_state');
      if (st) set('#calc_shipping_state', st.tagName === 'SELECT' ? 'M' : 'Madrid');
      set('#calc_shipping_city', 'Madrid');
      set('#calc_shipping_postcode', '28001');
      $(form).trigger('submit');
    });
    await sleep(600);
    await settled(page);
  }
  /** Tarifas que WooCommerce ofrece en el carrito clásico: los métodos de cada paquete. */
  async function cartRates(page) {
    return page.evaluate(() => {
      const rows = [...document.querySelectorAll('tr.woocommerce-shipping-totals')];
      return {
        packages: rows.length,
        rates: rows.map((row) => [...row.querySelectorAll('input.shipping_method')].map((i) => i.value.split(':')[0])),
        text: rows.map((row) => row.textContent.replace(/\s+/g, ' ').trim().slice(0, 160)),
        empty: !!document.querySelector('.cart-empty, .wc-empty-cart-message'),
      };
    });
  }
  /**
   * Estado de las dos barras (la del Menú del carrito y el widget suelto) una vez llegan los fragmentos (`url`: navega
   * antes). Con `mark()` previo, espera además a que WooCommerce haya reemplazado los nodos marcados.
   */
  async function bars(page, url = null) {
    if (url) await goto(page, url);
    await page.waitForFunction(() => { const st = [...document.querySelectorAll('[data-digi-ship] .digi-ship__state')]; return st.length === 2 && st.every((n) => !n.hasAttribute('data-digi-initial') && !n.__digiOld); }, { timeout: 60000 }).catch(async (e) => {
      const dom = await page.evaluate(() => ({ states: [...document.querySelectorAll('.digi-ship__state')].map((n) => (n.__digiOld ? 'VIEJO ' : '') + n.outerHTML.slice(0, 160)), timeout: window.wc_cart_fragments_params && window.wc_cart_fragments_params.request_timeout, storage: Object.keys(sessionStorage), ajax: performance.getEntriesByType('resource').filter((x) => /wc-ajax/.test(x.name)).map((x) => `${x.name.split('wc-ajax=')[1]}:${Math.round(x.duration)}`) })).catch(() => null);
      throw new Error(`${e.message} · ${JSON.stringify(dom)}`);
    });
    await sleep(300);
    return page.evaluate(() => [...document.querySelectorAll('[data-digi-ship]')].map((b) => {
      const st = b.querySelector('.digi-ship__state');
      const p = b.querySelector('.digi-ship__text');
      return { where: b.closest('.elementor-widget-woocommerce-menu-cart') ? 'menu' : 'widget', hidden: b.hidden, state: st ? st.getAttribute('data-state') : null, text: p ? p.textContent.replace(/ /g, ' ').replace(/\s+/g, ' ').trim() : '', percent: st ? st.getAttribute('data-percent') : null };
    }));
  }
  const mark = (page) => page.evaluate(() => document.querySelectorAll('.digi-ship__state').forEach((n) => { n.__digiOld = 1; }));
  /** El estado de la barra coincide con las tarifas: «done» ⇒ envío gratuito ofrecido en un único paquete; «progress» ⇒ no ofrecido. */
  function consistent(state, cart) {
    const free = cart.rates.some((list) => list.includes('free_shipping'));
    if (state === 'done') return free && cart.packages === 1;
    if (state === 'progress') return !free && cart.packages === 1;
    return state === 'none';
  }
  /** Comprueba un caso: estado de las dos barras, texto, coherencia con las tarifas del carrito y lo que se espera. */
  function judge(s, c, got, cart, ms) {
    const states = [...new Set(got.map((b) => b.state))];
    const state = states.length === 1 ? states[0] : `mixto:${states.join('/')}`;
    const visible = got.filter((b) => !b.hidden);
    const textOk = c.expect === 'none' ? visible.length === 0 : visible.length === 2 && visible.every((b) => b.text === c.text);
    const free = cart.rates.some((list) => list.includes('free_shipping'));
    const ok = got.length === 2 && state === c.expect && textOk && consistent(state, cart) && (c.free === undefined || c.free === free) && (c.packages === undefined || c.packages === cart.packages);
    check(s, c.id, c.what, ok, json({ state, expect: c.expect, bars: got.map((b) => `${b.where}:${b.hidden ? 'oculta' : b.text}`), cart: { packages: cart.packages, rates: cart.rates }, free, ms }));
  }

  /** Añade unidades del producto de 10 € por `wc-ajax=add_to_cart` (sin recargar la página). */
  async function addItems(page, sc, qty) {
    const ok = await page.evaluate(async (id, n) => {
      const u = window.wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart');
      const r = await fetch(u, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: `product_id=${id}&quantity=${n}` });
      const d = await r.json().catch(() => null);
      return r.ok && !!(d && d.fragments);
    }, sc.products.diez, qty);
    if (!ok) throw new Error(`no se pudieron añadir ${qty} unidades`);
  }
  /** Tarifas que WooCommerce ofrece para el carrito del visitante, por la Store API (la API del carrito de bloques). */
  async function storeRates(page, site) {
    const d = await page.evaluate(async (path) => {
      const r = await fetch(`${path}?rest_route=/wc/store/v1/cart`, { credentials: 'same-origin' });
      const j = await r.json().catch(() => null);
      return { status: r.status, j };
    }, site.path);
    if (d.status !== 200 || !d.j) throw new Error(`Store API: ${d.status}`);
    const packages = d.j.shipping_rates || [];
    return { packages: packages.length, rates: packages.map((pk) => (pk.shipping_rates || []).map((x) => x.method_id)), calculated: d.j.has_calculated_shipping, needs: d.j.needs_shipping, items: d.j.items_count };
  }
  /** Pide los fragmentos (como tras cualquier cambio del carrito) y lee las barras rehechas. */
  async function refreshBars(page) {
    await mark(page);
    await page.evaluate(() => window.jQuery(document.body).trigger('wc_fragment_refresh'));
    return bars(page);
  }

  async function W7(s) {
    const site = own();
    const sc = setupOf(site);
    if (!sc || !sc.f4woo) { check(s, 'W7.0', 'preparación del grupo', false, json(sc)); return; }
    check(s, 'W7.0', `preparación en ${site.path}: «Envío gratuito» desde 50 € (instancia ${sc.free_instance}), el producto de 10 € y la bandera de fragmentos activa`, !!sc.free_instance && !!sc.products.diez && sc.fragments_flag === 1, json({ free: sc.free_instance, diez: sc.products.diez, flag: sc.fragments_flag }));
    const cartUrl = (summary.sites[site.path].setup.pages || {})['digi-wf-carrito'];
    const T = (n) => `Te faltan ${money(n)} para el envío gratis`;
    const DONE = 'Tienes envío gratis';
    const facts7 = { errors: [], failed: [], outside: [] };
    const merge = (f) => { for (const k of Object.keys(facts7)) facts7[k].push(...(f[k] || [])); facts7.ajax = (facts7.ajax || []).concat(f.ajax || []); };
    const guarded = async (c, fn) => { try { await fn(); } catch (e) { check(s, c.id, c.what, false, String(e.stack || e).slice(0, 600)); } };

    // Destino conocido: la calculadora del carrito clásico una sola vez. Después, en la página del mini carrito, cada
    // caso cambia el sitio (y, si hace falta, añade unidades), lee las tarifas que ofrece WooCommerce para ese carrito
    // y pide los fragmentos, como tras cualquier cambio del carrito.
    const known = [
      { id: 'W7.1a', what: 'con 30 € y destino conocido: «Te faltan 20,00 €» y WooCommerce no ofrece el envío gratuito (en el carrito clásico y en la Store API)', expect: 'progress', text: T(20), free: false },
      { id: 'W7.2a', setting: 'rates', what: 'mu-plugin que filtra `woocommerce_package_rates` (quita el envío gratuito), con 30 €: sin «Te faltan»', expect: 'none', free: false },
      { id: 'W7.2c', setting: 'available', what: 'mu-plugin que filtra `woocommerce_shipping_free_shipping_is_available` a falso, con 30 €: sin «Te faltan»', expect: 'none', free: false },
      { id: 'W7.3d', setting: 'requires_empty', what: '`requires = ""` (envío gratuito sin requisito), con 30 €: «Tienes envío gratis» sólo porque WooCommerce ofrece la tarifa', expect: 'done', text: DONE, free: true },
      { id: 'W7.1b', add: 2, what: 'con 50 €: «Tienes envío gratis» y WooCommerce ofrece el envío gratuito', expect: 'done', text: DONE, free: true },
      { id: 'W7.2b', setting: 'rates', what: 'el mismo mu-plugin de `woocommerce_package_rates`, con 50 €: WooCommerce no ofrece el envío gratuito y la barra no dice nada', expect: 'none', free: false },
      { id: 'W7.2d', setting: 'available', what: 'el mismo mu-plugin de `…_is_available`, con 50 €: sin envío gratuito ofrecido y la barra no dice nada', expect: 'none', free: false },
      { id: 'W7.2e', setting: 'instance', what: '`min_amount` convertido por `woocommerce_shipping_free_shipping_instance_option` (×2 = 100 €), con 50 €: «Te faltan 50,00 €» y sin envío gratuito ofrecido', expect: 'progress', text: T(50), free: false },
      { id: 'W7.3a', setting: 'packages', what: 'dos paquetes (`woocommerce_cart_shipping_packages`), con 50 €: la barra no dice nada', expect: 'none', packages: 2 },
      { id: 'W7.2f', setting: 'instance', add: 5, what: 'el mismo `min_amount` convertido, con 100 €: «Tienes envío gratis» y el envío gratuito ofrecido', expect: 'done', text: DONE, free: true },
      { id: 'W7.3e', setting: 'requires_coupon', what: '`requires = coupon`, con 100 € y sin cupón: nada (no se dice cuánto falta) y sin envío gratuito ofrecido', expect: 'none', free: false },
      { id: 'W7.3f', setting: 'requires_coupon', coupon: 'DIGIENVIO', what: '`requires = coupon` con DIGIENVIO aplicado desde el mini carrito: la barra se rehace con «Tienes envío gratis» y el envío gratuito ofrecido', expect: 'done', text: DONE, free: true },
    ];
    const v = await guest();
    try {
      const t0 = Date.now();
      await op(site, 'swatchescart_set', { case: '' });
      await fill(v.page, cartUrl, sc, 3);
      await calculate(v.page);
      const classic = await cartRates(v.page);
      await goto(v.page, sc.pages.minicarrito);
      await bars(v.page);
      log(`   W7: calculadora y mini carrito en ${Math.round((Date.now() - t0) / 1000)} s`);
      for (const [i, c] of known.entries()) {
        const t1 = Date.now();
        try {
          await op(site, 'swatchescart_set', { case: c.setting || '' });
          if (c.add) await addItems(v.page, sc, c.add);
          const cart = await storeRates(v.page, site);
          if (i === 0 && JSON.stringify(cart.rates) !== JSON.stringify(classic.rates)) throw new Error(`tarifas distintas en el carrito clásico ${JSON.stringify(classic.rates)} y en la Store API ${JSON.stringify(cart.rates)}`);
          if (!cart.calculated) throw new Error('la Store API no ve el destino calculado');
          if (c.coupon) {
            // El cupón desde el propio mini carrito: su script pide los fragmentos al aplicarlo.
            await mark(v.page);
            await openPanel(v.page);
            await v.page.type('.elementor-menu-cart__main .digi-coupon__input', c.coupon);
            await v.page.click('.elementor-menu-cart__main .digi-coupon__apply');
            const got = await bars(v.page);
            judge(s, c, got, await storeRates(v.page, site), Date.now() - t1);
            continue;
          }
          judge(s, c, await refreshBars(v.page), cart, Date.now() - t1);
        } catch (e) {
          await shot(v.page, `W7-${c.id}-falla`);
          check(s, c.id, c.what, false, String(e.stack || e).slice(0, 600));
        }
      }
    } catch (e) {
      check(s, 'W7.1', 'destino conocido con la calculadora del carrito clásico', false, String(e.stack || e).slice(0, 600));
    } finally {
      merge(v.facts);
      await v.ctx.close().catch(() => {});
    }

    // Carrito vacío y destino sin confirmar (un visitante nuevo) y cliente sin país (otro).
    const alone = async (setting, fn) => {
      const w = await guest();
      try { await op(site, 'swatchescart_set', { case: setting }); await fn(w); } finally { merge(w.facts); await w.ctx.close().catch(() => {}); }
    };
    const cases = {
      g: { id: 'W7.3g', what: 'carrito vacío: la barra no aparece', expect: 'none' },
      c: { id: 'W7.3c', what: 'destino sin confirmar (ubicación por defecto de la tienda, sin calculadora), con 50 €: nada, aunque WooCommerce calcule para la tienda', expect: 'none' },
      b: { id: 'W7.3b', what: 'cliente sin país (sin ubicación por defecto ni calculadora), con 30 €: nada, y WooCommerce no ofrece ninguna tarifa', expect: 'none', free: false },
    };
    await guarded(cases.g, () => alone('', async (w) => {
      let t0 = Date.now();
      await goto(w.page, sc.pages.minicarrito);
      judge(s, cases.g, await bars(w.page), await storeRates(w.page, site), Date.now() - t0);
      t0 = Date.now();
      await addItems(w.page, sc, 5);
      const cart = await storeRates(w.page, site);
      judge(s, cases.c, await refreshBars(w.page), cart, Date.now() - t0);
    }));
    await guarded(cases.b, () => alone('nocountry', async (w) => {
      const t0 = Date.now();
      await goto(w.page, sc.pages.minicarrito);
      await bars(w.page);
      await addItems(w.page, sc, 3);
      const cart = await storeRates(w.page, site);
      judge(s, cases.b, await refreshBars(w.page), cart, Date.now() - t0);
    }));
    await op(site, 'swatchescart_set', { case: '' }).catch((e) => check(s, 'W7.reset', 'restablecer el sitio', false, String(e)));
    check(s, 'W7.4', 'sin errores de JavaScript ni respuestas de error del sitio; ninguna petición sale del sitio (salvo Google Fonts del kit, que no es F4-Woo)', clean(facts7), factsOf(facts7));
  }

  // ── W8 · Cupón en el mini carrito ──────────────────────────────────────────────────────────────────────────────
  async function openPanel(page) {
    const shown = await page.$('.elementor-menu-cart--shown');
    if (!shown) await page.click('#elementor-menu-cart__toggle_button');
    await page.waitForSelector('.elementor-menu-cart--shown .digi-coupon:not([hidden]) .digi-coupon__input', { visible: true, timeout: 30000 });
    await sleep(300);
  }
  async function waitState(page) {
    await page.waitForFunction(() => { const st = [...document.querySelectorAll('.digi-coupon__state, .digi-ship__state')]; return st.length && st.every((n) => !n.hasAttribute('data-digi-initial')); }, { timeout: 60000 });
  }
  const status = (page) => page.$eval('.elementor-menu-cart__main .digi-coupon__status', (r) => r.textContent.replace(/\s+/g, ' ').trim());
  // La región se vacía antes de cada acción: el aviso siguiente puede repetir el texto del anterior.
  const clearStatus = (page) => page.$eval('.elementor-menu-cart__main .digi-coupon__status', (r) => { r.textContent = ''; });
  async function waitStatus(page) {
    await page.waitForFunction(() => { const r = document.querySelector('.elementor-menu-cart__main .digi-coupon__status'); return r && r.textContent.trim(); }, { timeout: 60000 });
    await sleep(100);
    return status(page);
  }
  const coupons = (page) => page.$$eval('.elementor-menu-cart__main .digi-coupon__list .digi-coupon__code', (l) => l.map((c) => c.textContent.trim().toLowerCase()));
  const shipState = (page) => page.$$eval('.digi-ship__state', (l) => l.map((n) => `${n.getAttribute('data-state')}:${(n.textContent || '').replace(/ /g, ' ').replace(/\s+/g, ' ').trim()}`));
  const ajaxPosts = (posts, from, ep) => posts.slice(from).filter((p) => p.url.includes(`wc-ajax=${ep}`));

  async function W8(s) {
    const site = own();
    const sc = setupOf(site);
    if (!sc || !sc.f4woo) { check(s, 'W8.0', 'preparación del grupo', false, json(sc)); return; }
    await op(site, 'swatchescart_set', { case: '' });
    const cartUrl = (summary.sites[site.path].setup.pages || {})['digi-wf-carrito'];
    const { ctx, page, facts, posts } = await guest();
    let other = null;
    try {
      await fill(page, cartUrl, sc, 5);
      await calculate(page);
      await goto(page, sc.pages.minicarrito);
      await waitState(page);
      await openPanel(page);
      const start = await shipState(page);

      // W8.1 · válido: aplicar DIGI10 rehace la lista, la barra (50 € − 10 % = 45 €: «Te faltan 5,00 €») y el panel.
      await page.evaluate(() => { document.querySelector('.widget_shopping_cart_content').__digiOld = 1; document.querySelectorAll('.digi-ship__state, .digi-coupon__state').forEach((n) => { n.__digiOld = 1; }); });
      let mark = posts.length;
      await page.click('.elementor-menu-cart__main .digi-coupon__input');
      await page.keyboard.type('DIGI10');
      await page.click('.elementor-menu-cart__main .digi-coupon__apply');
      await page.waitForFunction(() => [...document.querySelectorAll('.elementor-menu-cart__main .digi-coupon__code')].some((c) => /digi10/i.test(c.textContent)), { timeout: 60000 });
      await page.waitForFunction(() => document.querySelector('.digi-ship__state') && !document.querySelector('.digi-ship__state').__digiOld && !document.querySelector('.widget_shopping_cart_content').__digiOld, { timeout: 60000 });
      await sleep(300);
      let apply = ajaxPosts(posts, mark, 'apply_coupon');
      const applied = { status: await status(page), list: await coupons(page), ship: await shipState(page), input: await page.$eval('.elementor-menu-cart__main .digi-coupon__input', (i) => i.value) };
      check(s, 'W8.1a', 'cupón válido (DIGI10): un POST `apply_coupon` con `security` y `coupon_code`, aviso de WooCommerce en la región, la lista con DIGI10 y «Quitar», el panel rehecho y la barra recalculada con el descuento («Tienes envío gratis» → «Te faltan 5,00 €»)', apply.length === 1 && /security=/.test(apply[0].body) && /coupon_code=DIGI10/.test(apply[0].body) && /applied|aplicado/i.test(applied.status) && applied.list.join() === 'digi10' && start.every((x) => x.startsWith('done:')) && applied.ship.every((x) => x === `progress:Te faltan ${money(5)} para el envío gratis`) && applied.input === '', json({ start, applied, body: apply.map((p) => p.body) }));
      const totals = await ctx.newPage();
      await goto(totals, cartUrl);
      const server = await totals.evaluate(() => ({ discount: !!document.querySelector('tr.cart-discount.coupon-digi10'), total: (document.querySelector('.order-total .amount') || {}).textContent || '' }));
      await totals.close();
      check(s, 'W8.1b', 'los totales de WooCommerce reflejan el cupón aplicado desde el mini carrito (descuento DIGI10 y total 45 € + 5 € de tarifa plana)', server.discount && norm(server.total) === money(50), json(server));

      // Inválido: aviso de error en la región; lo escrito se conserva y la lista no cambia.
      await page.click('.elementor-menu-cart__main .digi-coupon__input');
      await page.keyboard.type('NOEXISTE');
      mark = posts.length;
      await clearStatus(page);
      await page.click('.elementor-menu-cart__main .digi-coupon__apply');
      const bad = await waitStatus(page);
      await sleep(800);
      check(s, 'W8.1c', 'cupón inválido: el aviso de error de WooCommerce llega a la región `role="status"`, lo escrito se conserva y la lista sigue con DIGI10', ajaxPosts(posts, mark, 'apply_coupon').length === 1 && /exist|existe|not valid|no es v/i.test(bad) && (await page.$eval('.elementor-menu-cart__main .digi-coupon__input', (i) => i.value)) === 'NOEXISTE' && (await coupons(page)).join() === 'digi10', json({ bad, list: await coupons(page) }));

      // Quitar con el teclado: `remove_coupon` con `coupon`; el foco pasa al campo cuando el botón desaparece.
      mark = posts.length;
      await page.focus('.elementor-menu-cart__main .digi-coupon__remove[data-coupon="digi10"]');
      await clearStatus(page);
      await page.keyboard.press('Enter');
      await page.waitForFunction(() => !document.querySelector('.elementor-menu-cart__main .digi-coupon__remove'), { timeout: 60000 });
      const removed = await waitStatus(page);
      await sleep(500);
      const remove = ajaxPosts(posts, mark, 'remove_coupon');
      const focus = await page.evaluate(() => { const a = document.activeElement; return a ? (a.classList.contains('digi-coupon__input') ? 'campo' : a.tagName + '.' + a.className) : null; });
      check(s, 'W8.1d', 'quitar un cupón: un POST `remove_coupon` con `security` y `coupon=digi10`, aviso en la región, lista vacía, el foco en el campo y la barra de vuelta a «Tienes envío gratis»', remove.length === 1 && /security=/.test(remove[0].body) && /(^|&)coupon=digi10(&|$)/.test(remove[0].body) && !/coupon_code=/.test(remove[0].body) && /removed|quitado|eliminado/i.test(removed) && (await coupons(page)).length === 0 && focus === 'campo' && (await shipState(page)).every((x) => x.startsWith('done:')), json({ removed, body: remove.map((p) => p.body), focus, ship: await shipState(page) }));

      // W8.2 · escribir mientras otra pestaña cambia el carrito.
      await page.$eval('.elementor-menu-cart__main .digi-coupon__input', (i) => { i.value = ''; });
      await page.focus('.elementor-menu-cart__main .digi-coupon__input');
      await page.keyboard.type('DIG');
      await page.evaluate(() => { document.querySelector('.widget_shopping_cart_content').__digiOld = 1; document.querySelectorAll('.digi-coupon__state').forEach((n) => { n.__digiOld = 1; }); });
      other = await ctx.newPage();
      await goto(other, sc.pages.minicarrito);
      await waitState(other);
      const hash = await other.evaluate(async (id) => {
        const url = window.wc_cart_fragments_params.wc_ajax_url.toString().replace('%%endpoint%%', 'add_to_cart');
        const r = await fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: `product_id=${id}&quantity=1` });
        const data = await r.json();
        window.jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash]);
        return data.cart_hash;
      }, sc.products.diez);
      await page.bringToFront();
      await page.waitForFunction(() => !document.querySelector('.widget_shopping_cart_content').__digiOld && !document.querySelector('.elementor-menu-cart__main .digi-coupon__state').__digiOld, { timeout: 60000 });
      await sleep(300);
      const during = await page.evaluate(() => { const i = document.querySelector('.elementor-menu-cart__main .digi-coupon__input'); return { focus: document.activeElement === i, value: i.value, qty: (document.querySelector('.elementor-menu-cart__toggle .elementor-button-icon-qty') || {}).textContent }; });
      await page.keyboard.type('I10');
      const typed = await page.$eval('.elementor-menu-cart__main .digi-coupon__input', (i) => ({ value: i.value, focus: document.activeElement === i }));
      check(s, 'W8.2', 'escribir mientras otra pestaña cambia el carrito: los fragmentos rehacen el panel y el estado del cupón sin quitar el foco ni lo escrito («DIG» + «I10» = «DIGI10»)', !!hash && during.focus && during.value === 'DIG' && typed.focus && typed.value === 'DIGI10', json({ hash: !!hash, during, typed }));
      await page.keyboard.press('Enter');
      await page.waitForFunction(() => [...document.querySelectorAll('.elementor-menu-cart__main .digi-coupon__code')].some((c) => /digi10/i.test(c.textContent)), { timeout: 60000 });
      await other.close().catch(() => {});
      other = null;

      // W8.3 · nonce caducado: el reloj de los nonces del servidor avanza; un solo reintento tras `wc_fragment_refresh`.
      await op(site, 'swatchescart_set', { case: 'clock' });
      mark = posts.length;
      await clearStatus(page);
      await page.$eval('.elementor-menu-cart__main .digi-coupon__input', (i) => { i.value = ''; });
      await page.click('.elementor-menu-cart__main .digi-coupon__input');
      await page.keyboard.type('DIGIENVIO');
      await page.click('.elementor-menu-cart__main .digi-coupon__apply');
      await page.waitForFunction(() => [...document.querySelectorAll('.elementor-menu-cart__main .digi-coupon__code')].some((c) => /digienvio/i.test(c.textContent)), { timeout: 60000 });
      const renewed = await waitStatus(page);
      apply = ajaxPosts(posts, mark, 'apply_coupon');
      const refreshes = ajaxPosts(posts, mark, 'get_refreshed_fragments').length;
      check(s, 'W8.3a', 'nonce caducado (el reloj de los nonces del servidor avanza): el primer `apply_coupon` recibe -1/403, se pide `wc_fragment_refresh` y se repite una sola vez con el nonce nuevo, que aplica DIGIENVIO', apply.length === 2 && apply[0].body !== apply[1].body && refreshes >= 1 && /applied|aplicado/i.test(renewed), json({ apply: apply.length, refreshes, renewed, nonces: apply.map((p) => (p.body.match(/security=([^&]+)/) || [])[1]) }));

      await op(site, 'swatchescart_set', { case: 'nonce_fail' });
      mark = posts.length;
      await clearStatus(page);
      await page.click('.elementor-menu-cart__main .digi-coupon__input');
      await page.keyboard.type('DIGI10');
      await page.click('.elementor-menu-cart__main .digi-coupon__apply');
      const failed = await page.waitForFunction(() => /Recarga la página/.test((document.querySelector('.elementor-menu-cart__main .digi-coupon__status') || {}).textContent || ''), { timeout: 150000 }).then(() => status(page)).catch(() => status(page));
      await sleep(2500);
      apply = ajaxPosts(posts, mark, 'apply_coupon');
      check(s, 'W8.3b', 'si el nonce nuevo también falla: dos `apply_coupon` en total (un solo reintento) y «No se pudo aplicar el cupón. Recarga la página.» en la región', apply.length === 2 && /No se pudo aplicar el cupón\. Recarga la página/.test(failed), json({ apply: apply.length, failed }));

      // Cupones apagados: sin formulario. En una pestaña nueva: la de antes conserva la copia de fragmentos de
      // WooCommerce (sessionStorage) hasta que cambie el carrito, como el propio mini carrito.
      await op(site, 'swatchescart_set', { case: 'coupons_off' });
      other = track(await ctx.newPage(), facts);
      await other.setViewport({ width: 1280, height: 900 });
      await goto(other, sc.pages.minicarrito);
      await waitState(other);
      await other.click('#elementor-menu-cart__toggle_button');
      await other.waitForSelector('.elementor-menu-cart--shown', { timeout: 30000 });
      await sleep(800);
      const off = await other.evaluate(() => { const box = document.querySelector('.elementor-menu-cart__main .digi-coupon'); const form = box && box.querySelector('.digi-coupon__form'); const st = box && box.querySelector('.digi-coupon__state'); return { hidden: box ? box.hidden : null, visible: !!(form && form.offsetParent), nonce: st ? st.hasAttribute('data-apply') : null }; });
      await other.close().catch(() => {});
      other = null;
      const cartForm = await (async () => { const p = await ctx.newPage(); await goto(p, cartUrl); const has = !!(await p.$('#coupon_code')); await p.close(); return has; })();
      check(s, 'W8.3c', 'con los cupones apagados en WooCommerce no hay formulario de cupón (oculto, sin nonces), igual que en el carrito clásico', off.hidden === true && !off.visible && off.nonce === false && !cartForm, json({ off, cartForm }));
      check(s, 'W8.4', 'sin errores de JavaScript (salvo los 403 esperados del nonce caducado) ni peticiones fuera del sitio (salvo Google Fonts del kit)', !jsErrors(facts).length && facts.failed.every((f) => /^403 .*wc-ajax=apply_coupon/.test(f)) && !outside(facts).length, json({ errors: jsErrors(facts).slice(0, 4), failed: facts.failed.slice(0, 6), outside: outside(facts).slice(0, 4), kitFonts: fonts(facts) }));
      await shot(page, 'W8-final');
    } catch (e) {
      await shot(page, 'W8-falla');
      check(s, 'W8', 'cupón en el mini carrito', false, String(e.stack || e).slice(0, 600));
    } finally {
      if (other) await other.close().catch(() => {});
      await ctx.close().catch(() => {});
      await op(site, 'swatchescart_set', { case: '' }).catch((e) => check(s, 'W8.reset', 'restablecer el sitio', false, String(e)));
    }
  }

  return { W6, W7, W8 };
};
