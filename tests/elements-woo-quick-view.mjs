// F4-Woo, frente C: `assets/js/quick-view.js` con un DOM mínimo (V.5 y V.16.2).
// - V-B3: en una página sin variables, el script fija los parámetros, inserta las plantillas una vez, carga en orden lo
//   que falta y llama a `wc_variation_form` después de los «documento listo» de WooCommerce.
// - Diálogo: título propio con el foco, una sola etiqueta de encabezado, ancho copiado del botón, `activeElement`
//   nunca fuera, Escape y fondo cierran, el foco vuelve al botón, aborto al cerrar.
// - Navegación (la ficha) sin HTMLDialogElement, con teclas de modificación, en el editor, sin configuración de
//   variaciones, con `fallback`, con un script que no se puede cargar tarde o con código en línea bloqueado.
// - Modo plantilla: hojas, parámetros, código en línea, HTML del pie una sola vez, PhotoSwipe dentro del diálogo
//   mientras está abierto, galería y `runReadyTrigger`.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { createDOM } from './elements-woo-cd/dom.mjs';

const source = readFileSync(new URL('../digitalisimo-elements/assets/js/quick-view.js', import.meta.url), 'utf8');
const flush = async (n = 6) => { for (let i = 0; i < n; i++) await new Promise((r) => setTimeout(r, 0)); };

const PAGE = '<div class="elementor-widget e-loop-item" style="--digi-qv-width: 40rem"><a class="digi-qv__open" href="/producto/camiseta/" data-digi-qv="10" data-post="5" data-widget="abc1234" aria-haspopup="dialog" data-variable>Vista rápida<span class="screen-reader-text"> de Camiseta</span></a></div><a id="fuera" href="/otra">Otra</a>';
const OWN = {
  mode: 'own', title: 'Camiseta', product: 10, variable: true,
  html: '<div class="digi-qv__product product"><div class="digi-qv__media images"><div class="woocommerce-product-gallery__image"><img class="wp-post-image" src="/1.jpg" alt="Camiseta"></div><div class="digi-qv__thumbs" role="group"><button type="button" class="digi-qv__thumb" aria-pressed="true" data-src="/1.jpg" data-alt="A">1</button><button type="button" class="digi-qv__thumb" aria-pressed="false" data-src="/2.jpg" data-srcset="/2.jpg 600w" data-width="600" data-height="600" data-alt="B">2</button></div></div><div class="digi-qv__summary summary"><h2 class="digi-qv__title product_title" data-digi-qv-title>Camiseta</h2><form class="variations_form cart" action="/tienda/"></form></div></div>',
  assets: { styles: [], scripts: [], params: {} },
  templates: '<script type="text/template" id="tmpl-variation-template">p</script><script type="text/template" id="tmpl-unavailable-variation-template">u</script>',
  gallery_defaults: '<div class="woocommerce-product-gallery">g</div>', footer_html: '',
};
const VARIATION = {
  scripts: [{ handle: 'underscore', src: '/u.js' }, { handle: 'wp-util', src: '/wu.js' }, { handle: 'wc-add-to-cart-variation', src: '/v.js' }],
  params: { wc_add_to_cart_variation_params: { wc_ajax_url: '/?wc-ajax=%%endpoint%%' }, _wpUtilSettings: { ajax: { url: '/wp-admin/admin-ajax.php' } } },
};

function boot({ config = { rest: '/wp-json/digitalisimo-elements/v1/woo/quick-view', variation: VARIATION }, respond = () => OWN, dialog = true, editor = false, csp = false, present = [] } = {}) {
  const log = { fetch: [], loaded: [], assign: [], forms: [], galleries: [], ready: [], inline: [], links: [] };
  let ctx;
  const readyQueue = [];
  const dom = createDOM({
    currentScript: { nonce: 'n0nce' },
    onConnect(el) {
      if (el._parsed) return; // Lo insertado como HTML no se ejecuta, como en un navegador.
      if (el.localName === 'script' && el.getAttribute('src')) {
        log.loaded.push(el.id);
        // Ejecución y `load` en la misma tarea, como en un navegador: el «listo» de jQuery queda para después.
        setTimeout(() => {
          if (el.id === 'roto-js') return el.onerror();
          if (el.id === 'wc-add-to-cart-variation-js') {
            ctx.jQuery.fn.wc_variation_form = function () { log.forms.push(this.node); log.ready.push('form'); return this; };
            ctx.jQuery(() => log.ready.push('wc'));
          }
          if (el.id === 'wc-single-product-js') ctx.jQuery.fn.wc_product_gallery = function () { log.galleries.push(this.node); return this; };
          el.onload();
        }, 0);
      } else if (el.localName === 'script' && el.textContent) {
        log.inline.push([el.id, el.nonce]);
        if (!csp) vm.runInContext(el.textContent, ctx);
      } else if (el.localName === 'link') {
        log.links.push([el.id, el.href]);
        setTimeout(() => el.onload && el.onload(), 0);
      }
    },
  });
  const { window: w, document: d } = dom;
  d.body.innerHTML = PAGE;
  if (editor) d.body.className = 'elementor-editor-active';
  for (const h of present) { const s = d.createElement('script'); s._parsed = true; s.id = h + '-js'; d.body.appendChild(s); }
  // jQuery mínimo: `$(fn)` asíncrono en orden de registro, `.find/.each/.data/.trigger` y plugins en `$.fn`.
  const $ = (x) => {
    if (typeof x === 'function') { readyQueue.push(x); setTimeout(() => { const fn = readyQueue.shift(); if (fn) fn(); }, 0); return null; }
    const nodes = Array.isArray(x) ? x : [x];
    const api = Object.create($.fn);
    Object.assign(api, { node: nodes[0], nodes, find: (sel) => $(nodes.flatMap((n) => n.querySelectorAll(sel))), each(fn) { nodes.forEach((n) => fn.call(n)); return api; }, data: (k) => nodes[0] && nodes[0]['_' + k], trigger() { return api; } });
    return api;
  };
  $.fn = {};
  Object.assign(w, {
    console, setTimeout, clearTimeout, Promise, URLSearchParams, AbortController, Object, JSON, Error,
    jQuery: $, digitalisimoQuickView: config,
    location: { href: 'https://sitio.test/tienda/#arriba', assign: (u) => log.assign.push(u) },
    fetch: (url, init) => {
      log.fetch.push([url, init]);
      return new Promise((ok, no) => {
        init.signal.addEventListener('abort', () => no(Object.assign(new Error('abort'), { name: 'AbortError' })));
        const out = respond(url);
        if (out === 'never') return;
        if (out === 'error') { setTimeout(() => ok({ ok: false, status: 500, json: () => Promise.resolve({}) }), 0); return; }
        setTimeout(() => ok({ ok: true, json: () => Promise.resolve(JSON.parse(JSON.stringify(out))) }), 0);
      });
    },
    elementorFrontend: { elementsHandler: { runReadyTrigger: (n) => log.ready.push('el:' + n.getAttribute('data-id')) } },
  });
  if (!dialog) delete w.HTMLDialogElement;
  ctx = vm.createContext(w);
  vm.runInContext(source, ctx);
  const link = d.querySelector('a[data-digi-qv]');
  const click = (extra = {}) => { const e = new w.Event('click', Object.assign({ bubbles: true, button: 0 }, extra)); link.dispatchEvent(e); return e; };
  return { w, d, link, click, log, dialog: () => d.querySelector('dialog.digi-qv') };
}

// ---------------------------------------------------------------- V-B3: variable en una página sin variaciones.
let t = boot();
let e = t.click();
assert.ok(e.defaultPrevented, 'Con JS y <dialog>, el clic se intercepta.');
let dlg = t.dialog();
assert.ok(dlg && dlg.open && t.d.modals === 1, 'Diálogo modal nativo (showModal), uno por página.');
assert.equal(dlg.getAttribute('aria-labelledby'), 'digi-qv-title', 'Nombre accesible por el título propio.');
assert.equal(t.d.activeElement, t.d.getElementById('digi-qv-title'), 'El foco va al título propio al abrir (D31).');
assert.equal(t.d.getElementById('digi-qv-title').textContent, 'Vista rápida de Camiseta', 'Mientras carga, el título es el del botón.');
assert.equal(dlg.querySelector('.digi-qv__status').textContent, 'Cargando…');
assert.equal(dlg.querySelector('.digi-qv__body').getAttribute('aria-busy'), 'true');
assert.ok(t.d.documentElement.classList.contains('digi-qv-open'));
assert.equal(dlg.style.getPropertyValue('--digi-qv-width'), '40rem', 'El ancho se copia del botón al diálogo (V-I9).');
const url = new URL(t.log.fetch[0][0], 'https://sitio.test');
assert.equal(url.pathname, '/wp-json/digitalisimo-elements/v1/woo/quick-view');
assert.deepEqual([url.searchParams.get('post_id'), url.searchParams.get('widget_id'), url.searchParams.get('product'), url.searchParams.get('return')], ['5', 'abc1234', '10', 'https://sitio.test/tienda/'], 'Documento, elemento, producto y página de origen (sin fragmento).');
assert.ok(t.log.fetch[0][1].signal, 'Petición con AbortController.');
await flush(10);
assert.deepEqual(t.w.wc_add_to_cart_variation_params, VARIATION.params.wc_add_to_cart_variation_params, 'Los parámetros de WooCommerce se fijan antes de cargar su script.');
assert.ok(t.w._wpUtilSettings);
assert.ok(t.d.getElementById('tmpl-variation-template') && t.d.getElementById('tmpl-unavailable-variation-template'), 'Las plantillas `tmpl-variation-*` se insertan en la página.');
assert.equal(t.w.wc_variation_gallery_defaults[10], OWN.gallery_defaults, 'Estado inicial de la galería para el producto.');
assert.deepEqual(t.log.loaded, ['underscore-js', 'wp-util-js', 'wc-add-to-cart-variation-js'], 'Los scripts que faltan, en orden.');
assert.deepEqual(t.log.ready, ['wc', 'form'], 'El «documento listo» de WooCommerce corre antes de pintar…');
assert.equal(t.log.forms.length, 1, '…y después el puente llama una vez a `wc_variation_form` sobre el formulario insertado.');
assert.ok(t.log.forms[0].classList.contains('variations_form') && dlg.contains(t.log.forms[0]));
const title = t.d.getElementById('digi-qv-title');
assert.ok(title.classList.contains('digi-qv__title'), 'El título visible recibe el id: una sola etiqueta de encabezado.');
assert.equal(t.d.activeElement, title, 'Con el contenido, el foco va al título.');
assert.equal(dlg.querySelector('h2.screen-reader-text').hidden, true);
assert.equal(dlg.querySelector('.digi-qv__body').getAttribute('aria-busy'), 'false');
assert.equal(dlg.querySelector('.digi-qv__status').textContent, '');
assert.equal(t.d.querySelector('dialog[open]'), dlg, 'Con el diálogo abierto, `dialog[open]` es el destino de las regiones vivas de F4-Woo.');

// Foco atrapado: nada fuera del diálogo puede quedar activo.
const outside = t.d.getElementById('fuera');
t.d.activeElement = outside; // Lo que haría el navegador si algo escapara al modal.
outside.dispatchEvent(new t.w.Event('focusin', { bubbles: true }));
assert.ok(dlg.contains(t.d.activeElement), '`document.activeElement` nunca queda en la página fuera del diálogo (R-M3).');

// Miniaturas.
const thumbs = dlg.querySelectorAll('.digi-qv__thumb');
thumbs[1].dispatchEvent(new t.w.Event('click', { bubbles: true }));
const main = dlg.querySelector('.wp-post-image');
assert.deepEqual([main.getAttribute('src'), main.getAttribute('srcset'), main.getAttribute('width'), main.getAttribute('alt')], ['/2.jpg', '/2.jpg 600w', '600', 'B'], 'Una miniatura cambia la imagen principal.');
assert.deepEqual(thumbs.map((b) => b.getAttribute('aria-pressed')), ['false', 'true']);
assert.ok(dlg.open, 'Un clic dentro no cierra.');

// Escape (cancel nativo → close): limpieza y foco de vuelta.
dlg.close();
assert.equal(dlg.querySelector('.digi-qv__body').innerHTML, '', 'Al cerrar, el contenido se vacía.');
assert.ok(!t.d.documentElement.classList.contains('digi-qv-open'));
assert.equal(t.d.activeElement, t.link, 'El foco vuelve al botón que lo abrió.');

// Segunda apertura: nada se repite.
t.click();
await flush(10);
assert.equal(t.d.querySelectorAll('#tmpl-variation-template').length, 1, 'Las plantillas se insertan una sola vez.');
assert.equal(t.log.loaded.length, 3, 'Los scripts se cargan una sola vez por página.');
assert.equal(t.log.forms.length, 2, 'Cada contenido nuevo inicia su formulario.');
assert.equal(t.d.getElementById('digi-qv-title').classList.contains('digi-qv__title'), true);
// Clic en el fondo (fuera del rectángulo del diálogo).
dlg.dispatchEvent(new t.w.Event('click', { bubbles: true, clientX: 10, clientY: 10 }));
assert.equal(dlg.open, false, 'El clic en el fondo cierra.');
assert.equal(t.d.activeElement, t.link);

// Cerrar mientras carga aborta y no pinta nada después.
t = boot({ respond: () => 'never' });
t.click();
const signal = t.log.fetch[0][1].signal;
t.dialog().querySelector('.digi-qv__close').dispatchEvent(new t.w.Event('click', { bubbles: true }));
assert.ok(signal.aborted, 'Cerrar aborta la petición en curso.');
await flush();
assert.equal(t.dialog().querySelector('.digi-qv__body').innerHTML, '', 'Sin mensaje de error al cerrar a mano.');

// Error: mensaje y enlace a la ficha con el foco.
t = boot({ respond: () => 'error' });
t.click();
await flush();
const more = t.dialog().querySelector('.digi-qv__error a');
assert.ok(more && more.getAttribute('href') === '/producto/camiseta/', 'Si falla, «Abrir la ficha del producto».');
assert.equal(t.d.activeElement, more, 'El enlace a la ficha recibe el foco.');

// ---------------------------------------------------------------- Navegación hacia la ficha.
t = boot({ dialog: false });
assert.ok(!t.click().defaultPrevented && !t.log.fetch.length, 'Sin HTMLDialogElement, el enlace abre la ficha.');
t = boot();
assert.ok(!t.click({ ctrlKey: true }).defaultPrevented && !t.click({ metaKey: true }).defaultPrevented && !t.click({ shiftKey: true }).defaultPrevented, 'Ctrl/Cmd/Mayús + clic abren la ficha.');
assert.ok(!t.click({ button: 1 }).defaultPrevented, 'El botón central también.');
t = boot({ editor: true });
assert.ok(!t.click().defaultPrevented, 'En el editor, el botón no abre el diálogo.');
t = boot({ config: { rest: '/wp-json/x' } });
assert.ok(!t.click().defaultPrevented, 'Un variable sin configuración de variaciones en la página abre la ficha (falla hacia la navegación).');
t = boot({ config: {} });
assert.ok(!t.click().defaultPrevented, 'Sin ruta configurada, la ficha.');
t = boot({ respond: () => ({ fallback: true }) });
t.click();
await flush();
assert.deepEqual(t.log.assign, ['/producto/camiseta/'], 'Con `fallback`, la ficha.');
assert.equal(t.dialog().open, false);

// ---------------------------------------------------------------- Modo plantilla.
const TEMPLATE = {
  mode: 'template', title: 'Camiseta', product: 10, variable: true,
  html: '<div class="elementor elementor-77"><div class="elementor-element" data-id="a1" data-element_type="widget"><div class="woocommerce-product-gallery">g</div></div><div class="elementor-element" data-id="a2" data-element_type="widget"><form class="variations_form cart"></form></div></div>',
  assets: {
    styles: [{ handle: 'widget-woocommerce-product-images', src: '/w.css', media: 'all' }, { handle: 'woocommerce-inline', src: '', after: '.x{}' }],
    scripts: [{ handle: 'wc-single-product', src: '/s.js', before: 'window.antes = 1;' }, { handle: 'wc-add-to-cart-variation', src: '/v.js' }, { handle: 'elementor-frontend', src: '/f.js', late: false }],
    params: { wc_single_product_params: { flexslider_enabled: '1' } },
  },
  templates: OWN.templates, gallery_defaults: '', footer_html: '<div class="pswp" role="dialog"></div>',
};
t = boot({ respond: () => TEMPLATE, present: ['elementor-frontend'] });
t.link.setAttribute('data-digi-qv-template', '');
t.click();
await flush(12);
assert.deepEqual(t.log.links, [['widget-woocommerce-product-images-css', '/w.css']], 'Las hojas de los widgets de la plantilla, una vez (V-I7).');
assert.equal(t.d.getElementById('woocommerce-inline-inline-css').textContent, '.x{}', 'El CSS en línea sin archivo.');
assert.deepEqual(t.w.wc_single_product_params, { flexslider_enabled: '1' }, 'Parámetros localizados como objetos, sin <script> en línea.');
assert.equal(t.w.antes, 1, 'El código `before` corre antes de su archivo…');
assert.deepEqual(t.log.inline[0], ['wc-single-product-js-before', 'n0nce'], '…con el nonce del script propio (CSP).');
assert.deepEqual(t.log.loaded, ['wc-single-product-js', 'wc-add-to-cart-variation-js'], 'Lo que la página no tiene, en orden; `elementor-frontend` ya estaba.');
assert.equal(t.d.querySelectorAll('.pswp').length, 1, 'El HTML del pie (PhotoSwipe) se inserta una vez.');
assert.ok(t.dialog().contains(t.d.querySelector('.pswp')), 'Con el diálogo abierto, PhotoSwipe vive dentro (si no, queda bajo la capa modal e inerte).');
assert.equal(t.log.galleries.length, 1, 'La galería de «Imágenes del producto» se inicia.');
assert.equal(t.log.forms.length, 1, 'El formulario de variaciones de la plantilla, también.');
assert.ok(t.log.ready.indexOf('wc') < t.log.ready.indexOf('form'), 'También en modo plantilla, después del «listo».');
assert.ok(t.log.ready.includes('el:a1') && t.log.ready.includes('el:a2'), '`runReadyTrigger` en cada elemento de la plantilla.');
assert.equal(t.d.getElementById('digi-qv-title').localName, 'h2', 'En modo plantilla, el título propio oculto es el destino del foco.');
assert.equal(t.d.activeElement, t.d.getElementById('digi-qv-title'));
t.dialog().close();
assert.ok(!t.dialog().contains(t.d.querySelector('.pswp')) && t.d.querySelector('.pswp').parentNode === t.d.body, 'Al cerrar, PhotoSwipe vuelve al final de la página (la galería de la página lo usa).');
t.click();
await flush(12);
assert.equal(t.d.querySelectorAll('.pswp').length, 1, 'Al reabrir no se repite el HTML del pie.');
assert.ok(t.dialog().contains(t.d.querySelector('.pswp')), 'Al reabrir, PhotoSwipe vuelve al diálogo.');
assert.equal(t.log.links.length, 1);

// Un script que no se puede cargar tarde y falta en la página: la ficha.
t = boot({ respond: () => TEMPLATE });
t.link.setAttribute('data-digi-qv-template', '');
t.click();
await flush(12);
assert.deepEqual(t.log.assign, ['/producto/camiseta/'], 'Sin `elementor-frontend` en la página (`late: false`), la ficha.');
// Código en línea bloqueado por la política de contenido: la ficha.
t = boot({ respond: () => TEMPLATE, present: ['elementor-frontend'], csp: true });
t.link.setAttribute('data-digi-qv-template', '');
t.click();
await flush(12);
assert.deepEqual(t.log.assign, ['/producto/camiseta/'], 'Con el código en línea bloqueado, la ficha.');
// Un script que no carga: la ficha.
t = boot({ respond: () => Object.assign({}, OWN, { variable: false, assets: { styles: [], scripts: [{ handle: 'roto', src: '/roto.js' }], params: {} } }) });
t.link.removeAttribute('data-variable');
t.click();
await flush(12);
assert.deepEqual(t.log.assign, ['/producto/camiseta/'], 'Un script que no carga abre la ficha.');

// ---------------------------------------------------------------- Presupuesto y reglas.
assert.ok(Buffer.byteLength(source) <= 8192, `quick-view.js pesa ${Buffer.byteLength(source)} B (tope 8 KB).`);
assert.ok(!/addEventListener\(\s*'(found_variation|reset_data|wc_fragments_refreshed|added_to_cart)'/.test(source), 'Ningún evento de `.trigger()` de WooCommerce con addEventListener (V.12.3).');
assert.ok(!/Elementor(?![A-Za-z])/.test(source.replace(/elementorFrontend/g, '')), 'Sin el nombre del proveedor en los textos.');
console.log(`DIGITALÍSIMO Elements: vista rápida en el navegador validada (${Buffer.byteLength(source)} B).`);
