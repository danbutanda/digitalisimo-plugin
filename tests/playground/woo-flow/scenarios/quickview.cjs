// Escenarios W4, W5 y W11 de woo-flow.sh (F4-Woo, frente C: vista rápida; V.5, V.16.4 y V.17 B6, 9, 10, 11, 24 y 25).
// La preparación (setup/quickview.php) deja en cada sitio dos páginas sin el formulario de variaciones, con el Menú del
// carrito (barra de envío y cupón) y dos botones (Camiseta, simple; Sudadera, variable): `digi-wf-qv` («Diseño
// propio») y `digi-wf-qv-plantilla` («Plantilla de producto», con «Imágenes del producto» de PRO).
//
// - W4 · accesible: teclado y ratón, foco al título propio, Tab y Mayús+Tab sin salir, Escape, fondo y «Cerrar», foco de
//   vuelta al botón, nombre accesible, `aria-haspopup`, `aria-busy` y región viva (con la respuesta retrasada), en los
//   dos modos y en cada ancho; Ctrl+clic y sin JavaScript abren la ficha.
// - W5 · compra: el variable en una página sin variaciones (parámetros, plantillas una vez, carga en orden,
//   `wc_variation_form`), B6 (precio, imagen, disponibilidad y compra habilitada) en los dos modos, galería funcional
//   en la plantilla y la compra con redirección tras el POST (recargar no repite), con el Menú del carrito y el aviso
//   en el carrito.
// - W11 · compra en modo de prueba: vista rápida → mini carrito (sin destino, sin barra) → calculadora del carrito →
//   mini carrito con barra → cupón → pago con «Contra reembolso» → pedido recibido, con el pago clásico
//   (`[woocommerce_checkout]`) y con el de bloques que crea WooCommerce (QV_W11=clasico|bloques para correr una).
'use strict';

module.exports = (api) => {
  const { SITES, WIDTHS, summary, sleep, log, probe, check, pending, visitor, shot, settled } = api;
  // W11 tiene dos compras completas (pago clásico y de bloques). Con Playground de un solo proceso y la máquina
  // compartida, juntas pasan de los 10 minutos de una corrida: QV_W11=clasico o QV_W11=bloques corre sólo una (la
  // otra queda «pendiente»). Por defecto, las dos.
  const FLOWS = String(process.env.QV_W11 || 'clasico,bloques').split(',').map((x) => x.trim());
  const DLG = 'dialog.digi-qv';
  const OWN = 'digi-wf-qv';
  const TPL = 'digi-wf-qv-plantilla';
  const WIDE = WIDTHS[WIDTHS.length - 1];

  const setupOf = (site) => ((summary.sites[site.path] || {}).setup) || {};
  const qvOf = (site) => setupOf(site).quickview || null;
  const button = (qv, slug, kind) => `a[data-digi-qv][data-widget="${qv.buttons[slug][kind]}"]`;
  const short = (v) => JSON.stringify(v).slice(0, 700);
  const money = (n) => Number(n).toFixed(2).replace('.', ',');
  const same = (a, b) => String(a || '').replace(/\/$/, '') === String(b || '').replace(/\/$/, '');

  /** Sin preparación no hay nada que comprobar: una sola falla con los errores de la tienda. */
  function prepared(s, id, site) {
    const qv = qvOf(site);
    const errors = (qv && qv.errors) || setupOf(site).errors || [];
    if (!qv || !qv.pages || !qv.pages[OWN] || !qv.pages[TPL] || errors.length) {
      check(s, `${id}.0 ${site.path}`, 'la preparación de la vista rápida (imágenes, plantilla y páginas) está completa', false, short({ errors, pages: qv && qv.pages }));
      return null;
    }
    return qv;
  }

  /**
   * Visitante sin cookies. Playground sirve con un solo proceso de PHP (4–7 s por respuesta, más con peticiones en
   * cola), así que en el navegador de la prueba se alargan dos plazos pensados para un servidor real:
   * - el de 10 s de la vista rápida para mostrar «No se pudo cargar» (temporizadores de 10 000 ms → 90 s; el
   *   comportamiento del plazo lo cubre la prueba unitaria);
   * - el de 5 s de `wc-cart-fragments` (`wc_cart_fragments_params.request_timeout` → 60 s): con 5 s, WooCommerce aborta
   *   el refresco de los fragmentos y el mini carrito no se rehace tras el cupón.
   */
  async function guest() {
    const v = await visitor();
    await v.page.evaluateOnNewDocument(() => {
      const st = window.setTimeout;
      window.setTimeout = function (fn, ms, ...rest) { return st.call(this, fn, ms === 10000 ? 90000 : ms, ...rest); };
      let params;
      Object.defineProperty(window, 'wc_cart_fragments_params', {
        configurable: true,
        enumerable: true,
        get() { return params; },
        set(value) { if (value && typeof value === 'object' && +value.request_timeout > 0) value.request_timeout = 60000; params = value; },
      });
    });
    return v;
  }
  /** Navega y espera a que la página termine sus peticiones (fragmentos del carrito): un solo proceso de PHP. */
  async function go(page, url) {
    const res = await page.goto(url, { waitUntil: 'load' });
    await page.waitForNetworkIdle({ idleTime: 500, timeout: 60000 }).catch(() => {});
    return res;
  }
  /**
   * Errores del sitio. Aparte, sin fallar: las fuentes del kit que se piden fuera (Google Fonts, bloqueadas en la
   * prueba) y las peticiones que agotan el tiempo de Chrome (un solo proceso de PHP con peticiones en cola).
   */
  function clean(facts) {
    const font = (u) => /fonts\.(googleapis|gstatic)\.com/.test(u);
    const fonts = facts.outside.filter(font).length;
    const slow = (f) => /^net::ERR_TIMED_OUT /.test(f);
    return {
      errors: facts.errors.filter((e) => !(fonts && /Failed to load resource: net::ERR_NAME_NOT_RESOLVED/.test(e))),
      failed: facts.failed.filter((f) => !slow(f)),
      timeouts: facts.failed.filter(slow),
      outside: facts.outside.filter((u) => !font(u)),
      fonts,
    };
  }
  const quiet = (f) => !f.errors.length && !f.failed.length;

  // ── En la página ──────────────────────────────────────────────────────────────────────────────────────────────
  /** Estado del diálogo y del foco. */
  const state = (page) => page.evaluate(() => {
    const d = document.querySelector('dialog.digi-qv');
    const a = document.activeElement;
    const body = d && d.querySelector('.digi-qv__body');
    const title = document.getElementById('digi-qv-title');
    const live = d ? Array.from(d.querySelectorAll('[role="status"], [aria-live]')) : [];
    const name = (n) => (n ? (n.id ? '#' + n.id : n.tagName.toLowerCase() + (n.className && typeof n.className === 'string' ? '.' + n.className.split(' ')[0] : '')) : null);
    return {
      exists: !!d,
      open: !!(d && d.open),
      labelledby: d ? d.getAttribute('aria-labelledby') : null,
      titles: document.querySelectorAll('#digi-qv-title').length,
      titleInside: !!(d && title && d.contains(title)),
      titleText: title ? title.textContent.replace(/\s+/g, ' ').trim() : '',
      busy: body ? body.getAttribute('aria-busy') : null,
      live: live.length,
      status: live.length ? live[0].textContent : null,
      error: !!(d && d.querySelector('.digi-qv__error')),
      active: name(a),
      activeTitle: !!(a && a.id === 'digi-qv-title'),
      activeInside: !!(d && a && d.contains(a)),
      activeOutside: !!(a && a !== document.body && a !== document.documentElement && !(d && d.contains(a))),
      htmlOpen: document.documentElement.classList.contains('digi-qv-open'),
    };
  });
  /** Registra cualquier elemento de fuera del diálogo que reciba el foco mientras está abierto (fase de captura). */
  const watchFocus = (page) => page.evaluate(() => {
    window.digiWfOutside = [];
    document.addEventListener('focusin', (e) => {
      const d = document.querySelector('dialog.digi-qv');
      if (d && d.open && !d.contains(e.target)) window.digiWfOutside.push((e.target.tagName || '') + '.' + String(e.target.className || '').split(' ')[0]);
    }, true);
  });
  const outside = (page) => page.evaluate(() => window.digiWfOutside || []);
  /** Espera el contenido (o el error) del diálogo. */
  async function loaded(page, ms = 45000) {
    try {
      await page.waitForFunction(() => {
        const d = document.querySelector('dialog.digi-qv');
        const b = d && d.querySelector('.digi-qv__body');
        return !!(d && d.open && b && b.getAttribute('aria-busy') === 'false' && b.childElementCount);
      }, { timeout: ms });
      const error = await page.$eval(`${DLG} .digi-qv__error`, (e) => e.textContent.trim()).catch(() => '');
      return { ok: !error, error };
    } catch (e) {
      return { ok: false, error: String(e.message || e).slice(0, 200), url: page.url() };
    }
  }
  /** Cerrado y limpio: el evento `close` (asíncrono) vacía el cuerpo, quita la clase de `html` y devuelve el foco. */
  const closed = (page) => page.waitForFunction(() => !document.querySelector('dialog.digi-qv[open]') && !document.documentElement.classList.contains('digi-qv-open'), { timeout: 15000 }).then(() => true, () => false);
  const focusOn = (page, selector) => page.evaluate((sel) => document.activeElement === document.querySelector(sel), selector);
  /** Tab (o Mayús+Tab) más veces que elementos enfocables tiene el diálogo: el foco nunca sale. */
  async function cycle(page, shift) {
    const n = await page.evaluate(() => {
      const d = document.querySelector('dialog.digi-qv');
      return d ? Array.from(d.querySelectorAll('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea, [tabindex]:not([tabindex="-1"])')).filter((n) => n.getClientRects().length).length : 0;
    });
    const steps = [];
    for (let i = 0; i < n + 3; i++) {
      if (shift) await page.keyboard.down('Shift');
      await page.keyboard.press('Tab');
      if (shift) await page.keyboard.up('Shift');
      const st = await state(page);
      steps.push(st.activeOutside ? `FUERA:${st.active}` : st.active);
    }
    return { n, steps, outside: steps.filter((x) => String(x).startsWith('FUERA:')), inside: new Set(steps.filter((x) => x && !String(x).startsWith('FUERA:') && x !== 'body')).size };
  }
  /** Nombre accesible calculado por Chrome. */
  async function axName(page, selector) {
    const handle = await page.$(selector);
    if (!handle) return null;
    const node = await page.accessibility.snapshot({ root: handle, interestingOnly: false }).catch(() => null);
    return node ? { role: node.role, name: node.name } : null;
  }
  /** Retrasa las respuestas de la ruta para ver el estado «cargando». */
  async function slowRoute(page, delay) {
    await page.setRequestInterception(true);
    page.on('request', (r) => {
      if (r.isInterceptResolutionHandled()) return;
      if (/woo(\/|%2F)quick-view/i.test(r.url()) && delay.ms > 0) setTimeout(() => r.continue().catch(() => {}), delay.ms);
      else r.continue().catch(() => {});
    });
  }

  // ── W4 · Vista rápida accesible ───────────────────────────────────────────────────────────────────────────────
  async function a11y(s, site, qv, slug, label) {
    const url = qv.pages[slug];
    const sel = button(qv, slug, 'simple');
    const product = qv.products.simple;
    const name = product.name;
    const { ctx, page, facts } = await guest();
    const delay = { ms: 1500 };
    try {
      await slowRoute(page, delay);
      await page.setViewport({ width: WIDTHS[0], height: 900 });
      await go(page, url);
      await page.waitForSelector(sel);
      await watchFocus(page);
      const attrs = await page.$eval(sel, (a) => ({ haspopup: a.getAttribute('aria-haspopup'), href: a.getAttribute('href') }));
      const ax = await axName(page, sel);
      check(s, `W4.2a ${site.path} ${label}`, 'el botón es un enlace a la ficha con `aria-haspopup="dialog"` y el nombre del producto en su nombre accesible', attrs.haspopup === 'dialog' && same(attrs.href, product.url) && !!ax && ax.name.includes(name), short({ attrs, ax }));
      // La misma página en cada ancho (sin recargar: un solo proceso de PHP).
      for (const width of WIDTHS) {
        const at = `${site.path} ${label} ${width}px`;
        const full = width === WIDE;
        await page.setViewport({ width, height: 900 });

        // Teclado: Intro sobre el botón; con la respuesta retrasada (la primera vez) se ve el estado «cargando».
        await page.focus(sel);
        await page.keyboard.press('Enter');
        await page.waitForSelector(`${DLG}[open]`, { timeout: 30000 });
        if (delay.ms) {
          const busy = await state(page);
          check(s, `W4.2b ${at}`, 'mientras carga: `aria-busy="true"`, «Cargando…» en la región viva del diálogo y el foco en el título propio', busy.busy === 'true' && busy.live >= 1 && /Cargando/.test(busy.status || '') && busy.activeTitle && busy.activeInside && busy.htmlOpen, short(busy));
        }
        let res = await loaded(page);
        delay.ms = 0;
        let st = await state(page);
        check(s, `W4.1a ${at}`, 'con teclado: el diálogo se abre con el contenido, `aria-busy="false"` y el foco en el título propio (`#digi-qv-title`, dentro del diálogo)', res.ok && st.busy === 'false' && st.activeTitle && st.titleInside && st.titles === 1 && st.labelledby === 'digi-qv-title' && st.status === '', short({ res, st }));
        const dialogName = await axName(page, DLG);
        const fits = await page.$eval(DLG, (d) => { const r = d.getBoundingClientRect(); return r.left >= 0 && r.right <= innerWidth && r.top >= 0 && r.bottom <= innerHeight; });
        check(s, `W4.2c ${at}`, 'nombre accesible del diálogo: el del producto (por `aria-labelledby`), con una región viva dentro; el diálogo cabe en la ventana', !!dialogName && dialogName.role === 'dialog' && dialogName.name === name && st.live >= 1 && fits, short({ dialogName, live: st.live, fits }));
        await shot(page, `W4-${site.id}-${label}-${width}`);
        let shut;
        if (full) {
          const fwd = await cycle(page, false);
          const back = await cycle(page, true);
          check(s, `W4.1b ${at}`, 'Tab y Mayús+Tab recorren el diálogo sin llegar a la página (`activeElement` nunca fuera)', fwd.n > 1 && !fwd.outside.length && !back.outside.length && fwd.inside > 1 && back.inside > 1, short({ n: fwd.n, tab: fwd.steps, shiftTab: back.steps }));
          await page.keyboard.press('Escape');
          shut = await closed(page);
          st = await state(page);
          check(s, `W4.1c ${at}`, 'Escape cierra, el contenido se vacía y el foco vuelve al botón', shut && !st.htmlOpen && await focusOn(page, sel), short({ shut, st }));
          // Ratón: clic en el botón; después, «Cerrar».
          await page.click(sel);
          res = await loaded(page);
          st = await state(page);
          check(s, `W4.1d ${at}`, 'con ratón: el diálogo se abre con el foco en el título propio', res.ok && st.activeTitle && st.activeInside, short({ res, st }));
          await page.click(`${DLG} .digi-qv__close`);
          shut = await closed(page);
          check(s, `W4.1f ${at}`, '«Cerrar» cierra y el foco vuelve al botón', res.ok && shut && await focusOn(page, sel), short({ res, shut, active: (await state(page)).active }));
          await page.click(sel);
          res = await loaded(page);
        }
        // Clic en el fondo (en todos los anchos: el margen del diálogo es de 1 rem).
        await page.mouse.click(3, 3);
        shut = await closed(page);
        check(s, `W4.1e ${at}`, 'el clic en el fondo cierra y el foco vuelve al botón', res.ok && shut && await focusOn(page, sel), short({ shut, active: (await state(page)).active }));
      }
      const away = await outside(page);
      check(s, `W4.2d ${site.path} ${label}`, 'ningún elemento de la página fuera del diálogo recibió el foco mientras estaba abierto', !away.length, away.slice(0, 5).join(' | '));

      // Ctrl+clic: la ficha en otra pestaña, sin diálogo.
      const created = ctx.waitForTarget((t) => same(t.url().split('#')[0], product.url), { timeout: 60000 }).catch(() => null);
      await page.keyboard.down('Control');
      await page.click(sel);
      await page.keyboard.up('Control');
      const target = await created;
      await sleep(500);
      const after = await state(page);
      check(s, `W4.3a ${site.path} ${label}`, 'Ctrl+clic abre la ficha en otra pestaña y no abre el diálogo', !!target && !after.open && same(page.url(), url), short({ tab: target && target.url(), dialog: after.open, here: page.url() }));
      if (target) { const tab = await target.page().catch(() => null); if (tab) await tab.close().catch(() => {}); }
      check(s, `W4.2e ${site.path} ${label}`, 'sin errores de JavaScript ni respuestas de error del sitio', quiet(clean(facts)), short(clean(facts)));
    } catch (e) {
      await shot(page, `W4-${site.id}-${label}-falla`);
      check(s, `W4.x ${site.path} ${label}`, 'el recorrido accesible termina', false, String(e.stack || e.message || e).slice(0, 600));
    }
    // Sin JavaScript (otra pestaña del mismo visitante): el enlace lleva a la ficha.
    try {
      const plain = await ctx.newPage();
      await plain.setJavaScriptEnabled(false);
      await plain.setViewport({ width: WIDE, height: 900 });
      await plain.goto(url, { waitUntil: 'load' });
      await plain.waitForSelector(sel);
      await Promise.all([plain.waitForNavigation({ waitUntil: 'load' }), plain.click(sel)]);
      const title = await plain.$eval('.product_title', (e) => e.textContent.trim()).catch(() => '');
      check(s, `W4.3b ${site.path} ${label}`, 'sin JavaScript, el botón lleva a la ficha del producto', same(plain.url(), product.url) && title === product.name, short({ landed: plain.url(), title }));
    } catch (e) {
      check(s, `W4.3b ${site.path} ${label}`, 'sin JavaScript, el botón lleva a la ficha', false, String(e.message || e).slice(0, 400));
    } finally {
      await ctx.close().catch(() => {});
    }
  }

  async function W4(s) {
    for (const site of SITES) {
      const qv = prepared(s, 'W4', site);
      if (!qv) continue;
      await a11y(s, site, qv, OWN, 'propio');
      await a11y(s, site, qv, TPL, 'plantilla');
    }
  }

  // ── W5 · Vista rápida y compra ────────────────────────────────────────────────────────────────────────────────
  /** Formulario de variaciones iniciado por `wc_variation_form` (sus eventos de jQuery) en el diálogo. */
  const variationForm = (page) => page.waitForFunction(() => {
    const f = document.querySelector('dialog.digi-qv form.variations_form');
    const $ = window.jQuery;
    const ev = f && $ && $._data ? $._data(f, 'events') : null;
    return !!(ev && ev.check_variations && ev.found_variation);
  }, { timeout: 30000 }).then(() => true, () => false);

  /** B6: elegir una combinación en el diálogo; devuelve lo que muestra antes y después. */
  async function choose(page, variation) {
    const read = () => page.evaluate(() => {
      const d = document.querySelector('dialog.digi-qv');
      const img = d.querySelector('.product .images img.wp-post-image, .product .woocommerce-product-gallery img');
      const btn = d.querySelector('.single_add_to_cart_button');
      const text = (q) => { const n = d.querySelector(q); return n ? n.textContent.replace(/\s+/g, ' ').trim() : ''; };
      return {
        variation: +((d.querySelector('input[name="variation_id"]') || {}).value || 0),
        price: text('.woocommerce-variation-price'),
        availability: text('.woocommerce-variation-availability'),
        img: img ? img.getAttribute('src') : null,
        disabled: !btn || btn.classList.contains('disabled') || btn.disabled,
      };
    });
    // `wc_variation_form` deshabilita la compra al iniciarse (100 ms después, con `check_variations`).
    await page.waitForFunction(() => { const b = document.querySelector('dialog.digi-qv .single_add_to_cart_button'); return !!(b && b.classList.contains('disabled')); }, { timeout: 5000 }).catch(() => {});
    const before = await read();
    await page.select(`${DLG} select[name="attribute_pa_color"]`, variation.color);
    await page.select(`${DLG} select[name="attribute_pa_talla"]`, variation.talla);
    await page.waitForFunction((id) => +((document.querySelector('dialog.digi-qv input[name="variation_id"]') || {}).value || 0) === id, { timeout: 30000 }, variation.id).catch(() => {});
    await sleep(600);
    return { before, after: await read() };
  }
  const b6 = (r, v) => r.after.variation === v.id && r.after.price.includes(money(v.price)) && !!r.after.availability && !r.after.disabled && r.before.disabled
    && !!r.after.img && r.after.img !== r.before.img && r.after.img.includes(`sudadera-${v.color}`);

  /** Envía el formulario del diálogo y devuelve la cadena de la navegación. */
  async function buy(page) {
    const [resp] = await Promise.all([page.waitForNavigation({ waitUntil: 'load', timeout: 180000 }), page.click(`${DLG} .single_add_to_cart_button`)]);
    await page.waitForNetworkIdle({ idleTime: 500, timeout: 60000 }).catch(() => {});
    const chain = resp ? resp.request().redirectChain() : [];
    const first = chain[0];
    return {
      final: page.url(),
      method: resp ? resp.request().method() : null,
      status: resp ? resp.status() : null,
      post: first ? { method: first.method(), status: first.response() ? first.response().status() : null, url: first.url(), location: first.response() ? first.response().headers().location : null } : null,
    };
  }
  /** Contador y contenido del Menú del carrito. */
  const menuCart = (page) => page.evaluate(() => {
    const q = document.querySelector('#elementor-menu-cart__toggle_button .elementor-button-icon-qty');
    const c = document.querySelector('.elementor-menu-cart__main .widget_shopping_cart_content');
    return { counter: q ? q.getAttribute('data-counter') : null, items: c ? c.textContent.replace(/\s+/g, ' ').trim().slice(0, 300) : null };
  });
  async function openMiniCart(page) {
    await page.click('#elementor-menu-cart__toggle_button');
    return page.waitForFunction(() => {
      const w = document.querySelector('.elementor-menu-cart--shown');
      return !!(w && w.querySelector('.elementor-menu-cart__main'));
    }, { timeout: 30000 }).then(() => true, () => false);
  }
  const notice = (page) => page.$$eval('.woocommerce-message, .wc-block-components-notice-banner', (els) => els.map((e) => e.textContent.replace(/\s+/g, ' ').trim()).join(' | ')).catch(() => '');

  async function W5own(s, site, qv) {
    const url = qv.pages[OWN];
    const sel = button(qv, OWN, 'variable');
    const variation = (qv.variations || []).find((v) => v.color === 'azul' && v.talla === 'm');
    const { ctx, page, facts } = await guest();
    const requested = [];
    let recording = false;
    page.on('request', (r) => { if (recording && r.resourceType() === 'script') requested.push(r.url()); });
    try {
      await page.setViewport({ width: WIDE, height: 900 });
      await go(page, url);
      await page.waitForSelector(sel);
      const before = await page.evaluate(() => {
        const cfg = (window.digitalisimoQuickView || {}).variation || null;
        return {
          params: typeof window.wc_add_to_cart_variation_params,
          script: !!document.getElementById('wc-add-to-cart-variation-js'),
          forms: document.querySelectorAll('form.variations_form').length,
          templates: document.querySelectorAll('#tmpl-variation-template, #tmpl-unavailable-variation-template').length,
          config: cfg ? { handles: (cfg.scripts || []).map((x) => x.handle), srcs: (cfg.scripts || []).map((x) => x.src), params: Object.keys(cfg.params || {}) } : null,
          present: Array.from(document.querySelectorAll('script[id$="-js"]')).map((x) => x.id.replace(/-js$/, '')),
        };
      });
      const cfg = before.config;
      check(s, `W5.1a ${site.path}`, 'la página no trae el formulario, el script, los parámetros ni las plantillas de variaciones; la configuración del pie trae los scripts (el de variaciones al final) y `wc_add_to_cart_variation_params`', before.params === 'undefined' && !before.script && !before.forms && !before.templates && !!cfg && cfg.handles[cfg.handles.length - 1] === 'wc-add-to-cart-variation' && cfg.params.includes('wc_add_to_cart_variation_params') && !cfg.handles.some((h) => before.present.includes(h)), short(before));

      recording = true;
      await page.click(sel);
      const res = await loaded(page);
      const init = await variationForm(page);
      recording = false;
      const after = await page.evaluate(() => {
        const cfg = (window.digitalisimoQuickView || {}).variation || {};
        return {
          params: JSON.stringify(window.wc_add_to_cart_variation_params || null) === JSON.stringify((cfg.params || {}).wc_add_to_cart_variation_params || {}),
          keys: Object.keys(window.wc_add_to_cart_variation_params || {}).slice(0, 12),
          templates: [document.querySelectorAll('#tmpl-variation-template').length, document.querySelectorAll('#tmpl-unavailable-variation-template').length],
          inserted: Array.from(document.querySelectorAll('body > script[id$="-js"]')).map((x) => x.id.replace(/-js$/, '')),
          form: !!document.querySelector('dialog.digi-qv form.variations_form'),
        };
      });
      const path = (u) => { try { return new URL(u, 'http://x').pathname; } catch (e) { return u; } };
      const order = requested.map((u) => cfg ? cfg.srcs.findIndex((src) => path(src) === path(u)) : -1).filter((i) => i >= 0);
      const wanted = cfg ? cfg.handles.filter((h) => !before.present.includes(h)) : [];
      const inOrder = order.length === wanted.length && order.every((v, i) => i === 0 || v > order[i - 1]);
      check(s, `W5.1b ${site.path}`, 'al abrir el variable: parámetros fijados (los de la configuración), plantillas `tmpl-variation-*` una vez y scripts cargados en orden, una vez cada uno', res.ok && after.params && after.templates[0] === 1 && after.templates[1] === 1 && inOrder && wanted.every((h) => after.inserted.filter((x) => x === h).length === 1), short({ res, after, wanted, requested: requested.map(path), order }));
      check(s, `W5.1c ${site.path}`, '`wc_variation_form` inicia el formulario del diálogo', after.form && init, short({ form: after.form, init }));

      if (!variation) throw new Error('la preparación no devolvió la variación azul/M');
      const r = await choose(page, variation);
      check(s, `W5.2a ${site.path}`, `B6 en «Diseño propio»: elegir azul/M muestra su precio (${money(variation.price)}), su imagen y su disponibilidad y habilita la compra`, b6(r, variation), short(r));
      await shot(page, `W5-${site.id}-propio-variable`);

      // Cerrar y volver a abrir: nada se repite.
      await page.keyboard.press('Escape');
      await closed(page);
      const count = requested.length;
      recording = true;
      await page.click(sel);
      const again = await loaded(page);
      await variationForm(page);
      recording = false;
      const twice = await page.evaluate(() => [document.querySelectorAll('#tmpl-variation-template').length, document.querySelectorAll('#tmpl-unavailable-variation-template').length]);
      check(s, `W5.1d ${site.path}`, 'al volver a abrirlo, ni las plantillas ni los scripts se repiten', again.ok && twice[0] === 1 && twice[1] === 1 && requested.length === count, short({ again, twice, extra: requested.slice(count) }));

      // Compra: POST → redirección a la página de origen; recargar no repite.
      const r2 = await choose(page, variation);
      const nav = await buy(page);
      check(s, `W5.3a ${site.path}`, 'añadir al carrito envía un POST que responde con una redirección a la página de origen', !!nav.post && nav.post.method === 'POST' && [302, 303].includes(nav.post.status) && same(nav.final, url) && nav.method === 'GET' && b6(r2, variation), short(nav));
      const cart1 = await menuCart(page);
      const shown = await openMiniCart(page);
      await page.waitForFunction((n) => (document.querySelector('.elementor-menu-cart__main .widget_shopping_cart_content') || {}).textContent.includes(n), { timeout: 30000 }, qv.products.variable.name).catch(() => {});
      const cart2 = await menuCart(page);
      const origin = await notice(page);
      check(s, `W5.3b ${site.path}`, 'el Menú del carrito cuenta 1 producto y lo lista', cart1.counter === '1' && shown && String(cart2.items).includes(qv.products.variable.name), short({ cart1, cart2, shown }));
      await shot(page, `W5-${site.id}-propio-menu`);
      const reload = await page.reload({ waitUntil: 'load' });
      await page.waitForNetworkIdle({ idleTime: 500, timeout: 60000 }).catch(() => {});
      const cart3 = await menuCart(page);
      check(s, `W5.3c ${site.path}`, 'recargar la página de origen es un GET y no repite la compra (el contador sigue en 1)', !!reload && reload.request().method() === 'GET' && cart3.counter === '1', short({ method: reload && reload.request().method(), cart3 }));
      const pages = setupOf(site).pages || {};
      await go(page, pages['digi-wf-carrito']);
      const cartNotice = await notice(page);
      const rows = await page.$$eval('.woocommerce-cart-form .cart_item', (els) => els.map((e) => ({ name: (e.querySelector('.product-name') || {}).textContent.replace(/\s+/g, ' ').trim(), qty: (e.querySelector('input.qty') || {}).value }))).catch(() => []);
      const where = cartNotice.includes(qv.products.variable.name) ? 'carrito' : (origin.includes(qv.products.variable.name) ? 'origen' : '');
      check(s, `W5.3d ${site.path}`, 'el aviso «añadido al carrito» aparece en el carrito y la línea tiene cantidad 1', where === 'carrito' && rows.length === 1 && rows[0].qty === '1', short({ where, cartNotice, origin, rows }));
      check(s, `W5.3e ${site.path}`, 'sin errores de JavaScript ni respuestas de error del sitio', quiet(clean(facts)), short(clean(facts)));
    } catch (e) {
      await shot(page, `W5-${site.id}-propio-falla`);
      check(s, `W5.x ${site.path} propio`, 'el recorrido de «Diseño propio» termina', false, String(e.stack || e.message || e).slice(0, 600));
    } finally {
      await ctx.close().catch(() => {});
    }
  }

  async function W5template(s, site, qv) {
    const url = qv.pages[TPL];
    const variation = (qv.variations || []).find((v) => v.color === 'verde' && v.talla === 's');
    const { ctx, page, facts } = await guest();
    try {
      await page.setViewport({ width: WIDE, height: 900 });
      await go(page, url);
      await page.waitForSelector(button(qv, TPL, 'variable'));
      const pre = await page.evaluate(() => ({ variation: !!document.getElementById('wc-add-to-cart-variation-js'), single: !!document.getElementById('wc-single-product-js'), forms: document.querySelectorAll('form.variations_form, form.cart').length }));

      // Variable en la plantilla: B6 con «Imágenes del producto».
      await page.click(button(qv, TPL, 'variable'));
      let res = await loaded(page);
      const init = await variationForm(page);
      const tpl = await page.evaluate((id) => {
        const d = document.querySelector('dialog.digi-qv');
        return { doc: !!d.querySelector(`.elementor-${id}`), images: !!d.querySelector('.elementor-widget-woocommerce-product-images .woocommerce-product-gallery'), title: (d.querySelector('.product_title') || {}).textContent || '' };
      }, qv.template);
      check(s, `W5.2b ${site.path}`, 'el variable en «Plantilla de producto»: la plantilla con «Imágenes del producto», el título del producto y `wc_variation_form` iniciado, en una página sin variaciones', res.ok && init && tpl.doc && tpl.images && tpl.title.trim() === qv.products.variable.name && !pre.variation && !pre.forms, short({ res, init, tpl, pre }));
      if (variation) {
        const r = await choose(page, variation);
        check(s, `W5.2c ${site.path}`, `B6 en la plantilla: elegir verde/S muestra su precio (${money(variation.price)}), su imagen y su disponibilidad y habilita la compra`, b6(r, variation), short(r));
      }
      await shot(page, `W5-${site.id}-plantilla-variable`);
      await page.keyboard.press('Escape');
      await closed(page);

      // Simple en la plantilla: galería funcional (miniaturas y ampliación) y compra con redirección.
      await page.click(button(qv, TPL, 'simple'));
      res = await loaded(page);
      const ready = await page.waitForFunction(() => document.querySelectorAll('dialog.digi-qv .woocommerce-product-gallery .flex-control-thumbs li').length > 1, { timeout: 30000 }).then(() => true, () => false);
      let slide = null;
      // Las miniaturas las crea flexslider sin dimensiones: hay que esperar a que la imagen cargue para pulsarla.
      const thumb = ready && await page.waitForFunction(() => {
        const img = document.querySelector('dialog.digi-qv .flex-control-thumbs li:nth-child(2) img');
        const g = document.querySelector('dialog.digi-qv .woocommerce-product-gallery');
        return !!(img && img.complete && img.naturalWidth > 0 && img.getBoundingClientRect().width > 0 && g && getComputedStyle(g).opacity === '1');
      }, { timeout: 30000 }).then(() => true, () => false);
      const gallery = await page.evaluate(() => {
        const d = document.querySelector('dialog.digi-qv');
        const g = d.querySelector('.woocommerce-product-gallery');
        return { thumbs: g ? g.querySelectorAll('.flex-control-thumbs li').length : 0, slides: g ? g.querySelectorAll('.woocommerce-product-gallery__image').length : 0, viewport: !!(g && g.querySelector('.flex-viewport')), trigger: !!(g && g.querySelector('.woocommerce-product-gallery__trigger')), opacity: g ? getComputedStyle(g).opacity : null };
      });
      if (thumb) {
        await page.click(`${DLG} .flex-control-thumbs li:nth-child(2) img`);
        slide = await page.waitForFunction(() => {
          const s = Array.from(document.querySelectorAll('dialog.digi-qv .woocommerce-product-gallery__image'));
          return s.findIndex((n) => n.classList.contains('flex-active-slide')) === 1;
        }, { timeout: 15000 }).then(() => true, () => false);
      }
      check(s, `W5.2d ${site.path}`, '«Imágenes del producto» en la plantilla: galería iniciada (tres miniaturas, visible) y una miniatura cambia la imagen activa', res.ok && gallery.thumbs === 3 && gallery.viewport && gallery.opacity === '1' && slide === true, short({ res, gallery, thumb, slide }));
      let zoom = { trigger: gallery.trigger };
      if (gallery.trigger) {
        await page.click(`${DLG} .woocommerce-product-gallery__trigger`);
        zoom = await page.waitForFunction(() => document.querySelector('.pswp.pswp--open'), { timeout: 15000 }).then(async () => {
          await sleep(500);
          return page.evaluate(() => {
            const p = document.querySelector('.pswp.pswp--open');
            const r = p.getBoundingClientRect();
            const top = document.elementFromPoint(Math.round(r.left + r.width / 2), Math.round(r.top + r.height / 2));
            return { open: true, count: document.querySelectorAll('.pswp').length, onTop: !!(top && top.closest('.pswp')), top: top ? top.tagName + '.' + String(top.className).split(' ')[0] : null, inert: !!p.closest('[inert]') || (typeof p.checkVisibility === 'function' && !p.checkVisibility()) };
          });
        }, () => ({ open: false }));
      }
      await shot(page, `W5-${site.id}-plantilla-galeria`);
      let after = null;
      if (zoom.open) {
        await page.click('.pswp__button--close').catch(() => {});
        const shut = await page.waitForFunction(() => !document.querySelector('.pswp.pswp--open'), { timeout: 10000 }).then(() => true, () => false);
        after = { shut, ...(await state(page)) };
      }
      check(s, `W5.2e ${site.path}`, 'la ampliación de la galería (PhotoSwipe de «Imágenes del producto») se abre por encima del diálogo y su «Cerrar» vuelve al diálogo abierto', !!zoom.open && zoom.onTop && zoom.count === 1 && !!after && after.shut && after.open && !after.activeOutside, short({ zoom, after: after && { shut: after.shut, open: after.open, active: after.active } }));
      const stillOpen = (await state(page)).open;
      if (!stillOpen) {
        await page.click(button(qv, TPL, 'simple'));
        await loaded(page);
      }
      const nav = await buy(page);
      const cart = await menuCart(page);
      check(s, `W5.3f ${site.path}`, 'compra desde la plantilla: POST con redirección a la página de origen y el Menú del carrito al día', !!nav.post && nav.post.method === 'POST' && [302, 303].includes(nav.post.status) && same(nav.final, url) && nav.method === 'GET' && cart.counter === '1', short({ nav, cart }));
      check(s, `W5.3g ${site.path}`, 'sin errores de JavaScript ni respuestas de error del sitio en la plantilla', quiet(clean(facts)), short(clean(facts)));
    } catch (e) {
      await shot(page, `W5-${site.id}-plantilla-falla`);
      check(s, `W5.y ${site.path} plantilla`, 'el recorrido de «Plantilla de producto» termina', false, String(e.stack || e.message || e).slice(0, 600));
    } finally {
      await ctx.close().catch(() => {});
    }
  }

  async function W5(s) {
    for (const site of SITES) {
      const qv = prepared(s, 'W5', site);
      if (!qv) continue;
      await W5own(s, site, qv);
      await W5template(s, site, qv);
    }
  }

  // ── W11 · Compra en modo de prueba ────────────────────────────────────────────────────────────────────────────
  const bar = (page) => page.evaluate(() => {
    const box = document.querySelector('.elementor-menu-cart__main .digi-ship');
    const st = box && box.querySelector('.digi-ship__state');
    return { exists: !!box, hidden: box ? box.hidden : null, state: st ? st.getAttribute('data-state') : null, initial: !!(st && st.hasAttribute('data-digi-initial')), text: box ? box.textContent.replace(/\s+/g, ' ').trim() : '' };
  });
  const waitBar = (page, test, arg) => page.waitForFunction(test, { timeout: (arg && arg.ms) || 25000 }, arg).then(() => true, () => false);
  /** Registro de las peticiones `wc-ajax` de una página (punto final, estado y duración), para el detalle. */
  function ajaxLog(page) {
    const list = [];
    const started = new Map();
    page.on('request', (r) => { if (/wc-ajax=/.test(r.url())) started.set(r, Date.now()); });
    const end = (r, status) => { if (started.has(r)) { list.push(`${r.url().replace(/.*wc-ajax=/, '')} ${status} ${Date.now() - started.get(r)}ms`); started.delete(r); } };
    page.on('requestfinished', (r) => end(r, r.response() ? r.response().status() : '?'));
    page.on('requestfailed', (r) => end(r, r.failure() ? r.failure().errorText : 'falló'));
    return list;
  }

  /** Vista rápida → compra → mini carrito con la barra → cupón DIGI10. Devuelve lo visto. */
  const barIs = (page, want) => waitBar(page, (w) => {
    const b = document.querySelector('.elementor-menu-cart__main .digi-ship');
    const st = b && b.querySelector('.digi-ship__state');
    return !!(b && st && !st.hasAttribute('data-digi-initial') && st.getAttribute('data-state') === w.state && (w.state === 'none' ? b.hidden : !b.hidden && b.textContent.includes(w.has)));
  }, want);

  /** Calculadora de envío del carrito clásico: España, Madrid, 28001 (el destino que la barra necesita, V.7.1.2). */
  async function calculator(page, site) {
    const pages = setupOf(site).pages || {};
    await go(page, pages['digi-wf-carrito']);
    await page.waitForSelector('form.woocommerce-shipping-calculator', { timeout: 60000 });
    const toggle = await page.$('.shipping-calculator-button');
    if (toggle) { await toggle.click(); await sleep(400); }
    if (await page.$('select#calc_shipping_country')) await page.select('select#calc_shipping_country', 'ES');
    await sleep(300);
    if (await page.$('select#calc_shipping_state')) await page.select('select#calc_shipping_state', 'M');
    for (const [selector, value] of [['#calc_shipping_city', 'Madrid'], ['#calc_shipping_postcode', '28001']]) {
      const el = await page.$(selector);
      if (el && await el.evaluate((n) => n.offsetParent !== null)) { await el.click({ clickCount: 3 }); await el.type(value); }
    }
    await page.click('form.woocommerce-shipping-calculator button[name="calc_shipping"]');
    await settled(page);
    await page.waitForNetworkIdle({ idleTime: 500, timeout: 60000 }).catch(() => {});
    return page.$$eval('#shipping_method li, .woocommerce-shipping-totals td', (els) => els.map((e) => e.textContent.replace(/\s+/g, ' ').trim()).join(' | ')).catch(() => '');
  }

  /**
   * Vista rápida → compra → mini carrito (sin destino, sin barra) → calculadora del carrito → mini carrito con la barra
   * → cupón DIGI10 en el mini carrito → la barra se rehace.
   */
  async function cartPart(s, id, site, qv, page, kind, expect) {
    const url = qv.pages[OWN];
    const ajax = ajaxLog(page);
    await go(page, url);
    await page.click(button(qv, OWN, kind));
    const res = await loaded(page);
    if (kind === 'variable') {
      await variationForm(page);
      await choose(page, expect.variation);
    }
    const nav = await buy(page);
    check(s, `${id}a ${site.path}`, `vista rápida (${kind === 'variable' ? 'Sudadera roja S' : 'Camiseta'}) → «Añadir al carrito» vuelve a la página con una redirección`, res.ok && !!nav.post && nav.post.method === 'POST' && same(nav.final, url), short({ res, nav }));
    let shown = await openMiniCart(page);
    const hidden = await barIs(page, { state: 'none' });
    const b0 = await bar(page);
    check(s, `${id}b ${site.path}`, 'sin destino conocido (el visitante aún no dio su dirección), el mini carrito no muestra la barra (V.7.1.2: si no se puede saber, nada)', shown && hidden, short(b0));

    const rates = await calculator(page, site);
    await go(page, url);
    shown = await openMiniCart(page);
    // Sin cambios en el carrito, WooCommerce reutiliza los fragmentos guardados (mismo hash): no hay que esperar más.
    const ok1 = await waitBar(page, (w) => {
      const b = document.querySelector('.elementor-menu-cart__main .digi-ship');
      const st = b && b.querySelector('.digi-ship__state');
      return !!(b && st && !b.hidden && st.getAttribute('data-state') === w.state && b.textContent.includes(w.has));
    }, { state: expect.before.state, has: expect.before.has, ms: 8000 });
    const b1 = await bar(page);
    check(s, `${id}c ${site.path}`, `con el destino de la calculadora, el mini carrito muestra la barra: ${expect.before.label}`, shown && ok1, short({ bar: b1, rates: rates.slice(0, 160), ajax: ajax.slice(-6) }));
    await shot(page, `${id.replace(/\./g, '')}-${site.id}-minicarrito`);
    const couponReady = await page.waitForFunction(() => { const c = document.querySelector('.elementor-menu-cart__main .digi-coupon'); return !!(c && !c.hidden); }, { timeout: 30000 }).then(() => true, () => false);
    if (couponReady) {
      await page.type('.elementor-menu-cart__main .digi-coupon__input', 'DIGI10');
      await page.click('.elementor-menu-cart__main .digi-coupon__apply');
    }
    const applied = couponReady && await page.waitForFunction(() => /digi10/i.test((document.querySelector('.elementor-menu-cart__main .digi-coupon__list') || {}).textContent || ''), { timeout: 45000 }).then(() => true, () => false);
    const ok2 = await barIs(page, { state: expect.after.state, has: expect.after.has });
    const b2 = await bar(page);
    const status = await page.$eval('.elementor-menu-cart__main .digi-coupon__status', (e) => e.textContent.trim()).catch(() => '');
    check(s, `${id}d ${site.path}`, `el cupón DIGI10 se aplica en el mini carrito y la barra se rehace: ${expect.after.label}`, couponReady && applied && ok2, short({ couponReady, applied, bar: b2, status, ajax: ajax.slice(-6) }));
    await shot(page, `${id.replace(/\./g, '')}-${site.id}-cupon`);
  }

  async function classicCheckout(s, site, qv, page) {
    const pages = setupOf(site).pages || {};
    await go(page, pages['digi-wf-pago']);
    await page.waitForSelector('#billing_first_name');
    await settled(page);
    const fields = { '#billing_first_name': 'Ana', '#billing_last_name': 'Vista', '#billing_address_1': 'Calle de Prueba 3', '#billing_city': 'Madrid', '#billing_postcode': '28001', '#billing_phone': '600000001', '#billing_email': 'ana.vista@example.com' };
    // Valores puestos de una vez y un solo `change`: cada tecla en un campo de dirección pediría `update_order_review`.
    await page.evaluate((f) => { for (const [sel, value] of Object.entries(f)) { const el = document.querySelector(sel); if (el) el.value = value; } }, fields);
    if (await page.$('select#billing_state')) await page.select('select#billing_state', 'M');
    await page.$eval('#billing_postcode', (el) => el.dispatchEvent(new Event('change', { bubbles: true })));
    await settled(page);
    const free = await page.$('input.shipping_method[value^="free_shipping"]');
    if (free && await free.evaluate((el) => el.type === 'radio')) { await free.click(); await settled(page); }
    const cod = await page.$('#payment_method_cod');
    if (cod && await cod.evaluate((el) => el.type === 'radio' && !el.checked)) await cod.click();
    const offered = await page.$$eval('input.shipping_method', (els) => els.map((e) => e.value)).catch(() => []);
    const coupon = await page.$('tr.cart-discount.coupon-digi10');
    check(s, `W11.1e ${site.path}`, 'el pago clásico conserva DIGI10, ofrece «Envío gratuito» (como dijo la barra) y «Contra reembolso»', !!free && !!cod && !!coupon, short({ offered, cod: !!cod, coupon: !!coupon }));
    await shot(page, `W11-${site.id}-clasico-pago`);
    await page.click('#place_order');
    await page.waitForFunction(() => /order-received/.test(location.href) || !!document.querySelector('.woocommerce-error, .woocommerce-NoticeGroup-checkout'), { timeout: 180000 });
    const err = await page.$eval('.woocommerce-error, .woocommerce-NoticeGroup-checkout', (el) => el.textContent.replace(/\s+/g, ' ').trim()).catch(() => '');
    await page.waitForSelector('.woocommerce-order, .woocommerce-thankyou-order-received', { timeout: 60000 }).catch(() => {});
    await shot(page, `W11-${site.id}-clasico-recibido`);
    return { landed: page.url(), err };
  }

  async function blocksCheckout(s, site, qv, page) {
    const pages = setupOf(site).pages || {};
    await go(page, pages['wc-checkout']);
    await page.waitForSelector('.wc-block-checkout #email', { timeout: 120000 });
    await sleep(1000);
    const type = async (selector, value) => {
      const el = await page.$(selector);
      if (!el) return false;
      await el.click({ clickCount: 3 });
      await page.keyboard.press('Backspace');
      await el.type(value);
      return true;
    };
    await type('#email', 'luis.vista@example.com');
    if (await page.$('select#shipping-country')) await page.select('select#shipping-country', 'ES');
    const filled = {};
    for (const [selector, value] of Object.entries({ '#shipping-first_name': 'Luis', '#shipping-last_name': 'Vista', '#shipping-address_1': 'Calle de Prueba 4', '#shipping-city': 'Madrid', '#shipping-postcode': '28001', '#shipping-phone': '600000002' })) {
      filled[selector] = await type(selector, value);
    }
    if (await page.$('select#shipping-state')) await page.select('select#shipping-state', 'M');
    await page.keyboard.press('Tab');
    await sleep(1500);
    await page.waitForNetworkIdle({ idleTime: 1000, timeout: 90000 }).catch(() => {});
    const flat = await page.$('.wc-block-checkout input[type="radio"][value^="flat_rate"]');
    if (flat && await flat.evaluate((el) => !el.checked)) { await flat.click(); await page.waitForNetworkIdle({ idleTime: 1000, timeout: 60000 }).catch(() => {}); }
    const cod = await page.$('#radio-control-wc-payment-method-options-cod');
    if (cod && await cod.evaluate((el) => !el.checked)) { await cod.click(); await sleep(500); }
    const view = await page.evaluate(() => {
      const text = (sel) => Array.from(document.querySelectorAll(sel)).map((e) => e.textContent.replace(/\s+/g, ' ').trim()).join(' | ');
      return {
        rates: Array.from(document.querySelectorAll('.wc-block-checkout input[type="radio"]')).map((e) => e.value).filter((v) => /^[a-z_]+:\d+$/.test(v)),
        totals: text('.wc-block-checkout__sidebar .wc-block-components-totals-item'),
        coupon: /digi10/i.test(text('.wc-block-components-totals-discount')),
        total: text('.wc-block-checkout__sidebar .wc-block-components-totals-footer-item'),
        payment: text('.wc-block-checkout__payment-method'),
      };
    });
    const noFree = !view.rates.some((r) => r.startsWith('free_shipping')) && !/gratuito|free shipping/i.test(view.totals);
    check(s, `W11.2e ${site.path}`, 'el pago de bloques conserva DIGI10, ofrece sólo la tarifa plana (como dijo la barra), «Contra reembolso» y 32,00 €', view.coupon && noFree && /Tarifa plana/.test(view.totals) && /Contra reembolso/i.test(view.payment) && /32,00/.test(view.total), short({ ...view, filled }));
    await shot(page, `W11-${site.id}-bloques-pago`);
    await page.click('.wc-block-components-checkout-place-order-button');
    await page.waitForFunction(() => /order-received/.test(location.href) || !!document.querySelector('.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error'), { timeout: 180000 }).catch(() => {});
    await page.waitForFunction(() => document.readyState === 'complete', { timeout: 60000 }).catch(() => {});
    const err = await page.$eval('.wc-block-components-notice-banner.is-error, .wc-block-store-notice.is-error', (el) => el.textContent.replace(/\s+/g, ' ').trim()).catch(() => '');
    await page.waitForSelector('.woocommerce-order, .wc-block-order-confirmation-status, .woocommerce-thankyou-order-received', { timeout: 60000 }).catch(() => {});
    await shot(page, `W11-${site.id}-bloques-recibido`);
    return { landed: page.url(), err };
  }

  /** El pedido más reciente del sitio con lo que se espera. */
  async function order(s, id, site, want, what) {
    const orders = await probe(site, 'orders');
    const got = orders[0] || {};
    const pick = Object.fromEntries(Object.keys(want).map((k) => [k, got[k]]));
    check(s, `${id} ${site.path}`, what, JSON.stringify(pick) === JSON.stringify(want), short({ got: pick, want, items: got.items }));
  }

  async function W11(s) {
    for (const site of SITES) {
      const qv = prepared(s, 'W11', site);
      if (!qv) continue;
      const simple = qv.products.simple;
      // Clásico: Camiseta (60 €) → barra «Tienes envío gratis» → DIGI10 (54 €, sigue gratis) → «Envío gratuito».
      if (!FLOWS.includes('clasico')) pending(s, `W11.1 ${site.path}`, 'compra con el pago clásico: en otra corrida (QV_W11=clasico)');
      else {
        const { ctx, page, facts } = await guest();
        try {
          await page.setViewport({ width: WIDE, height: 900 });
          await cartPart(s, 'W11.1', site, qv, page, 'simple', {
            before: { state: 'done', has: 'envío gratis', label: '«Tienes envío gratis» con 60 €' },
            after: { state: 'done', has: 'envío gratis', label: 'sigue «Tienes envío gratis» con 54 €' },
          });
          const r = await classicCheckout(s, site, qv, page);
          check(s, `W11.1f ${site.path}`, 'el pago clásico (`[woocommerce_checkout]`) termina en «Pedido recibido»', /order-received/.test(r.landed), r.err || r.landed);
          await order(s, 'W11.1g', site, { status: 'processing', payment: 'cod', shipping: ['free_shipping'], coupons: ['digi10'], discount: '6.00', total: '54.00', created: 'checkout', items: [{ product: simple.id, variation: 0, qty: 1, total: '54.00' }] }, 'el pedido queda «procesando», con contra reembolso, envío gratuito, DIGI10 y 54,00 €');
          check(s, `W11.1h ${site.path}`, 'sin errores de JavaScript ni respuestas de error del sitio; ninguna petición sale del sitio', quiet(clean(facts)) && !clean(facts).outside.length, short(clean(facts)));
        } catch (e) {
          await shot(page, `W11-${site.id}-clasico-falla`);
          check(s, `W11.1x ${site.path}`, 'la compra con el pago clásico termina', false, String(e.stack || e.message || e).slice(0, 600));
        } finally {
          await ctx.close().catch(() => {});
        }
      }
      // Bloques: Sudadera roja S (30 €) → barra con 20 € → DIGI10 (27 €, faltan 23 €) → tarifa plana (5 €): 32 €.
      if (!FLOWS.includes('bloques')) pending(s, `W11.2 ${site.path}`, 'compra con el pago de bloques: en otra corrida (QV_W11=bloques)');
      else {
        const variation = (qv.variations || []).find((v) => v.color === 'rojo' && v.talla === 's');
        const { ctx, page, facts } = await guest();
        try {
          if (!variation) throw new Error('la preparación no devolvió la variación roja/S');
          await page.setViewport({ width: WIDE, height: 900 });
          await cartPart(s, 'W11.2', site, qv, page, 'variable', {
            variation,
            before: { state: 'progress', has: '20,00', label: 'faltan 20,00 € con 30 €' },
            after: { state: 'progress', has: '23,00', label: 'faltan 23,00 € con 27 €' },
          });
          const r = await blocksCheckout(s, site, qv, page);
          check(s, `W11.2f ${site.path}`, 'el pago de bloques (la página que crea WooCommerce) termina en «Pedido recibido»', /order-received/.test(r.landed), r.err || r.landed);
          await order(s, 'W11.2g', site, { status: 'processing', payment: 'cod', shipping: ['flat_rate'], coupons: ['digi10'], discount: '3.00', total: '32.00', created: 'store-api', items: [{ product: qv.products.variable.id, variation: variation.id, qty: 1, total: '27.00' }] }, 'el pedido queda «procesando», con contra reembolso, tarifa plana, DIGI10 y 32,00 €');
          check(s, `W11.2h ${site.path}`, 'sin errores de JavaScript ni respuestas de error del sitio; ninguna petición sale del sitio', quiet(clean(facts)) && !clean(facts).outside.length, short(clean(facts)));
        } catch (e) {
          await shot(page, `W11-${site.id}-bloques-falla`);
          check(s, `W11.2x ${site.path}`, 'la compra con el pago de bloques termina', false, String(e.stack || e.message || e).slice(0, 600));
        } finally {
          await ctx.close().catch(() => {});
        }
      }
      log(`   W11 ${site.path}: núcleo ${summary.options.core}`);
    }
  }

  return { W4, W5, W11 };
};
