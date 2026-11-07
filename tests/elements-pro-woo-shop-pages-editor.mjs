// Páginas de la tienda en el editor (`Woo\Store\Shop_Pages`, C7 fila O, corrección de fidelidad c7#11 de docs/pro/c7.md):
// `store/assets/shop-pages-editor.js` con el módulo de WooCommerce del editor simulado (sus `pageSettingsWidgets` con las
// claves de los widgets de PRO y su `onTemplateIdChange`), en una ventana de `node:vm`:
// - copia las entradas de los cuatro widgets de PRO a los propios (carrito, pago, Mi cuenta, resumen), sin pisar una que
//   ya exista, así que el aviso «¿Guardar esto como página…?» sale también con los propios;
// - al abrir «Opciones adicionales» del Carrito propio, actualiza el botón «Editar plantilla» con la plantilla elegida
//   (`onTemplateIdChange( 'additional_template_select' )`, como el de PRO), una sola vez aunque el script corra dos;
// - sin el editor todavía, espera a `elementor/init`; sin el módulo de PRO, no hace nada ni falla.
// Uso: node tests/elements-pro-woo-shop-pages-editor.mjs
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const code = readFileSync(new URL('../digitalisimo-elements/modules/digitalisimo-woo/store/assets/shop-pages-editor.js', import.meta.url), 'utf8');
let checks = 0;
const ok = (value, message) => { assert.ok(value, message); checks++; };

function windowWith({ pro = true, editor = true } = {}) {
  const listeners = {};
  const channel = {};
  const calls = [];
  const entry = (name) => ({ headerMessage: `Want to save this as your ${name} page?`, optionName: `woocommerce_${name}_page_id`, woocommercePageName: name });
  const w = {
    listeners, channel, calls,
    addEventListener: (type, fn) => { (listeners[type] = listeners[type] || []).push(fn); },
  };
  if (pro) {
    w.elementorPro = { modules: { woocommerce: {
      pageSettingsWidgets: { 'woocommerce-cart': entry('cart'), 'woocommerce-checkout-page': entry('checkout'), 'woocommerce-my-account': entry('myaccount'), 'woocommerce-purchase-summary': entry('summary'), 'digitalisimo-woo-purchase-summary': { own: true } },
      onTemplateIdChange: (control) => calls.push(control),
    } } };
  }
  const elementor = { channels: { editor: { on: (event, fn) => { (channel[event] = channel[event] || []).push(fn); } } } };
  if (editor) w.elementor = elementor;
  w.window = w;
  return { w, elementor };
}
const run = (w) => vm.runInNewContext(code, w);

// 1. Con el editor listo.
let { w } = windowWith();
run(w);
const pages = w.elementorPro.modules.woocommerce.pageSettingsWidgets;
ok(pages['digitalisimo-woo-cart'] === pages['woocommerce-cart'], 'Carrito propio: el aviso del carrito de PRO.');
ok(pages['digitalisimo-woo-checkout'] === pages['woocommerce-checkout-page'], 'Finalizar compra propio: el aviso del pago de PRO.');
ok(pages['digitalisimo-woo-my-account'] === pages['woocommerce-my-account'], 'Mi cuenta propia: el aviso de Mi cuenta de PRO.');
ok(true === pages['digitalisimo-woo-purchase-summary'].own, 'No pisa una entrada que ya exista.');
const event = 'editor:widget:digitalisimo-woo-cart:section_additional_options:activated';
ok(1 === (w.channel[event] || []).length, 'Escucha «Opciones adicionales» del Carrito propio.');
w.channel[event][0]();
ok('additional_template_select' === w.calls[0], 'Al abrirla, actualiza «Editar plantilla» con la plantilla del carrito vacío.');
run(w);
ok(1 === w.channel[event].length, 'Si el script corre otra vez, no repite el enganche.');

// 2. Antes del editor: espera a `elementor/init`.
let elementor;
({ w, elementor } = windowWith({ editor: false }));
run(w);
ok(w.elementorPro.modules.woocommerce.pageSettingsWidgets['digitalisimo-woo-cart'], 'Sin el editor todavía, las entradas ya se copian (el módulo de PRO existe desde que carga su script).');
ok(1 === (w.listeners['elementor/init'] || []).length && !w.channel[event], 'Espera a `elementor/init` para escuchar el panel.');
w.elementor = elementor;
w.listeners['elementor/init'][0]();
ok(1 === (w.channel[event] || []).length, 'Con `elementor/init`, escucha el panel.');

// 3. Sin el módulo de PRO: nada, sin errores.
({ w } = windowWith({ pro: false }));
run(w);
ok(!w.channel[event] && !w.digiShopPages, 'Sin el módulo de WooCommerce del editor, no hace nada.');

// 4. El editor ya existe pero el módulo de PRO llega después: espera a `elementor/init` en lugar de rendirse.
let late;
({ w: late } = windowWith());
const module = late.elementorPro;
delete late.elementorPro;
run(late);
ok(1 === (late.listeners['elementor/init'] || []).length && !late.channel[event], 'Con el editor y sin el módulo de PRO todavía, espera a `elementor/init`.');
late.elementorPro = module;
late.listeners['elementor/init'][0]();
ok(1 === (late.channel[event] || []).length && late.elementorPro.modules.woocommerce.pageSettingsWidgets['digitalisimo-woo-cart'] === late.elementorPro.modules.woocommerce.pageSettingsWidgets['woocommerce-cart'], 'Con `elementor/init`, copia las entradas y escucha el panel.');

console.log(`DIGITALÍSIMO Elements: páginas de la tienda en el editor (${checks} comprobaciones) en verde.`);
