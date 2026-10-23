// F4-Woo, frente D: `assets/js/swatches.js` con un DOM mínimo (V.6.4 y V.16.2).
// - Elegir un radio fija el <select> de WooCommerce y lanza un `change` nativo con `bubbles`.
// - Opciones **quitadas** por WooCommerce (reemplazo completo del <select>) y opciones deshabilitadas: radio
//   deshabilitado con «(no disponible)» en su nombre accesible.
// - «Limpiar» por `click` (el `.val('')` de jQuery no produce mutación ni evento nativo) y el modo AJAX con el puente
//   jQuery obligatorio (`woocommerce_variation_has_changed`, `reset_data`, `show_variation`).
// - Los radios no viajan en el pedido (formulario vacío propio fuera del de compra) y `required` pasa al primer radio.
// - Contenido que llega después (`digitalisimo:content`, `frontend/element_ready/widget`) y el cambio de imagen del bucle.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { createDOM } from './elements-woo-cd/dom.mjs';

const source = readFileSync(new URL('../digitalisimo-elements/assets/js/swatches.js', import.meta.url), 'utf8');
const tick = () => new Promise((resolve) => setTimeout(resolve, 0));

const FIELD = `<form class="variations_form cart" data-product_variations="[]"><table class="variations"><tbody><tr><th class="label"><label for="pa_color">Color</label></th><td class="value">
<div class="digi-swatch-field" data-digi-swatches="pa_color"><select id="pa_color" name="attribute_pa_color" data-attribute_name="attribute_pa_color" required><option value="">Elige una opción</option><option value="rojo">Rojo</option><option value="azul">Azul</option><option value="verde">Verde</option></select><div class="digi-swatches digi-swatches--circle digi-swatches--visual" role="radiogroup" aria-label="Color" style="--digi-swatch-size-attr:32px"><label class="digi-swatch" style="--digi-swatch-color:#cc0000"><input type="radio" name="digi_sw_p40-1" form="digi-sw-form-p40-1" value="rojo" required><span class="digi-swatch__face" aria-hidden="true"></span><span class="digi-swatch__label screen-reader-text">Rojo</span><span class="digi-swatch__na screen-reader-text"></span></label><label class="digi-swatch"><input type="radio" name="digi_sw_p40-1" form="digi-sw-form-p40-1" value="azul"><span class="digi-swatch__label screen-reader-text">Azul</span><span class="digi-swatch__na screen-reader-text"></span></label><label class="digi-swatch digi-swatch--text"><input type="radio" name="digi_sw_p40-1" form="digi-sw-form-p40-1" value="verde"><span class="digi-swatch__label">Verde</span><span class="digi-swatch__na screen-reader-text"></span></label></div></div>
<a class="reset_variations" href="#">Limpiar</a></td></tr></tbody></table><button type="submit" class="single_add_to_cart_button">Añadir al carrito</button></form>`;

// jQuery mínimo: `.on()` y `.trigger()` sobre un nodo, como el puente que usa el script.
function jquery() {
  const handlers = new Map();
  const $ = (node) => ({
    on(events, fn) { for (const e of events.split(/\s+/)) { const h = handlers.get(node) || {}; (h[e] ||= []).push(fn); handlers.set(node, h); } return this; },
    trigger(e) { for (const fn of (handlers.get(node) || {})[e] || []) fn({ type: e }); return this; },
  });
  $.handlers = handlers;
  return $;
}

function boot({ html = FIELD, jq = true, elementor = null } = {}) {
  const dom = createDOM();
  const { window: w, document: d } = dom;
  d.body.innerHTML = html;
  Object.assign(w, { console, setTimeout, clearTimeout, Promise, JSON, Date, Array });
  if (jq) w.jQuery = jquery();
  if (elementor) w.elementorFrontend = elementor;
  vm.createContext(w);
  vm.runInContext(source, w);
  return { w, d, field: d.querySelector('.digi-swatch-field'), select: d.querySelector('select'), radios: d.querySelectorAll('input[type="radio"]'), form: d.querySelector('form.variations_form') };
}
const label = (r) => r.closest('.digi-swatch');
const na = (r) => label(r).querySelector('.digi-swatch__na').textContent;
const choose = (w, r) => { r.checked = true; r.dispatchEvent(new w.Event('change', { bubbles: true })); };

// ---------------------------------------------------------------- Arranque.
let t = boot();
assert.ok(t.field.classList.contains('digi-swatch-field--ready'), 'Con JS, el envoltorio queda listo (la hoja oculta el <select> sólo entonces).');
assert.ok(t.field.hasAttribute('data-digi-ready'));
const holderId = t.radios[0].getAttribute('form');
const holder = t.d.getElementById(holderId);
assert.ok(holder && holder.localName === 'form' && holder.hidden && holder.parentNode === t.d.body, 'Formulario vacío propio, al final de body (no puede ir dentro del de compra).');
assert.ok(!t.form.contains(holder));
assert.ok(t.radios.every((r) => r.getAttribute('form') === holderId && r.name === holderId), 'Radios agrupados por un nombre nuevo y asociados al formulario vacío.');
assert.ok(t.radios.every((r) => r.form === holder), 'Ningún radio pertenece al formulario de compra: no viajan en el POST.');
assert.equal(t.select.required, false, 'El <select> oculto deja de ser obligatorio (no podría mostrar su aviso).');
assert.equal(t.radios[0].required, true, '`required` en el primer radio.');
assert.equal(t.radios.filter((r) => r.required).length, 1);

// ---------------------------------------------------------------- Elegir un radio.
const native = [];
t.form.addEventListener('change', (e) => native.push([e.target, e.bubbles]));
choose(t.w, t.radios[1]);
assert.equal(t.select.value, 'azul', 'Elegir un radio fija el <select>.');
assert.ok(native.some(([target, bubbles]) => target === t.select && bubbles), 'Lanza un `change` nativo con `bubbles` (llega al manejador delegado de WooCommerce).');
assert.ok(label(t.radios[1]).classList.contains('is-selected'));
assert.ok(!label(t.radios[0]).classList.contains('is-selected'));

// ---------------------------------------------------------------- Opciones que WooCommerce quita o deshabilita.
t.select.innerHTML = '<option value="">Elige una opción</option><option value="rojo" class="attached enabled">Rojo</option><option value="azul" class="attached" disabled>Azul</option>';
await tick();
assert.equal(t.radios[2].disabled, true, 'Una opción quitada deja su radio deshabilitado (R-I13, V-I5).');
assert.equal(na(t.radios[2]), ' (no disponible)', '«(no disponible)» en el nombre accesible.');
assert.ok(label(t.radios[2]).classList.contains('is-disabled'));
assert.equal(t.radios[1].disabled, true, 'Una opción deshabilitada también.');
assert.equal(t.radios[0].disabled, false);
assert.equal(na(t.radios[0]), '');
t.select.value = 'rojo';
t.radios[2].checked = true;
t.radios[2].dispatchEvent(new t.w.Event('change', { bubbles: true }));
assert.equal(t.select.value, 'rojo', 'Un radio sin <option> no fija un valor inexistente (el <select> no queda vacío).');
await tick();
t.select.querySelector('option[value="azul"]').removeAttribute('disabled');
await tick();
assert.equal(t.radios[1].disabled, false, 'Vuelve a estar disponible cuando WooCommerce la habilita.');
assert.equal(t.radios[0].checked, true, 'El estado se relee del <select>.');

// ---------------------------------------------------------------- «Limpiar».
t.select.value = ''; // `.val('')` de jQuery: sin mutación ni evento nativo.
t.d.querySelector('.reset_variations').dispatchEvent(new t.w.Event('click', { bubbles: true }));
await tick();
assert.ok(t.radios.every((r) => !r.checked), '«Limpiar» desmarca los radios tras una microtarea.');

// ---------------------------------------------------------------- Obligatorio al enviar.
const submit = new t.w.Event('submit', { bubbles: true });
t.form.dispatchEvent(submit);
assert.ok(submit.defaultPrevented, 'Sin elegir, el envío se detiene…');
assert.ok((t.d.reported || []).includes(t.radios[0]), '…y el primer radio muestra el aviso de obligatorio.');

// ---------------------------------------------------------------- Modo AJAX: el puente jQuery es obligatorio.
t = boot({ html: FIELD.replace('data-product_variations="[]"', 'data-product_variations="false"') });
assert.ok(t.w.jQuery.handlers.get(t.form), 'Con jQuery, el puente escucha el formulario.');
t.select.value = 'verde'; // WooCommerce cambia el valor sin tocar las opciones ni lanzar eventos nativos.
t.w.jQuery(t.form).trigger('woocommerce_variation_has_changed');
assert.equal(t.radios[2].checked, true, '`woocommerce_variation_has_changed` relee el estado.');
t.select.value = '';
t.w.jQuery(t.form).trigger('reset_data');
assert.ok(t.radios.every((r) => !r.checked), '`reset_data` también.');
t.select.value = 'rojo';
t.w.jQuery(t.form).trigger('show_variation');
assert.equal(t.radios[0].checked, true, 'Y `show_variation`.');
t = boot({ jq: false });
choose(t.w, t.radios[0]);
assert.equal(t.select.value, 'rojo', 'Sin jQuery, los radios funcionan como en el modo nativo.');

// ---------------------------------------------------------------- Contenido que llega después.
const actions = [];
t = boot({ html: '<div id="dialogo"></div>', elementor: { hooks: { addAction: (name, fn) => actions.push([name, fn]) } } });
assert.ok(actions.some(([name]) => name === 'frontend/element_ready/widget'), 'Se engancha a `frontend/element_ready/widget` (refresco de bucles, modo plantilla).');
const box = t.d.getElementById('dialogo');
box.innerHTML = FIELD;
t.d.dispatchEvent(new t.w.CustomEvent('digitalisimo:content', { detail: { root: box } }));
assert.ok(box.querySelector('.digi-swatch-field').classList.contains('digi-swatch-field--ready'), '`digitalisimo:content` (vista rápida) inicia las muestras insertadas.');
const again = t.d.querySelectorAll('form[hidden]').length;
t.d.dispatchEvent(new t.w.CustomEvent('digitalisimo:content', { detail: { root: box } }));
assert.equal(t.d.querySelectorAll('form[hidden]').length, again, 'Iniciar dos veces no duplica nada.');
const widget = t.d.createElement('div');
widget.innerHTML = FIELD;
t.d.body.appendChild(widget);
actions.find(([name]) => name === 'frontend/element_ready/widget')[1]([widget]);
assert.ok(widget.querySelector('.digi-swatch-field').hasAttribute('data-digi-ready'), 'El gancho del constructor inicia los campos de su elemento.');
assert.equal(typeof t.w.digitalisimoSwatches.init, 'function', 'API mínima para otros scripts.');

// ---------------------------------------------------------------- Bucle: cambiar la imagen al pasar el cursor.
t = boot({ html: '<ul class="products"><li class="product"><a href="/p"><img src="/a.jpg" srcset="/a.jpg 300w" sizes="300px" alt="Camiseta"></a><ul class="digi-swatches digi-swatches--links" data-digi-sw-hover><li><a class="digi-swatch" href="/p?attribute_pa_color=rojo" rel="nofollow" data-digi-sw-img="{&quot;src&quot;:&quot;/rojo.jpg&quot;,&quot;srcset&quot;:&quot;/rojo.jpg 300w&quot;,&quot;sizes&quot;:&quot;&quot;}"><span class="screen-reader-text">Camiseta, Rojo</span></a></li></ul></li><li><p id="fuera">x</p></li></ul>' });
const img = t.d.querySelector('li.product img');
const link = t.d.querySelector('a[data-digi-sw-img]');
link.dispatchEvent(new t.w.Event('mouseover', { bubbles: true }));
assert.equal(img.getAttribute('src'), '/rojo.jpg', 'Al pasar el cursor, la imagen de la variación.');
assert.equal(img.getAttribute('srcset'), '/rojo.jpg 300w');
assert.equal(img.hasAttribute('sizes'), false);
link.dispatchEvent(new t.w.Event('mouseout', { bubbles: true, relatedTarget: t.d.getElementById('fuera') }));
assert.equal(img.getAttribute('src'), '/a.jpg', 'Al salir, la original.');
assert.equal(img.getAttribute('sizes'), '300px');
assert.equal(img.hasAttribute('data-digi-sw-src'), false);
link.dispatchEvent(new t.w.Event('focusin', { bubbles: true }));
assert.equal(img.getAttribute('src'), '/rojo.jpg', 'También con el teclado.');

// ---------------------------------------------------------------- Presupuesto.
assert.ok(Buffer.byteLength(source) <= 8192, `swatches.js pesa ${Buffer.byteLength(source)} B (tope 8 KB).`);
assert.ok(!/\bjQuery\s*\(/.test(source.replace(/w\.jQuery\(/g, '')), 'jQuery sólo como puente opcional (`window.jQuery`).');
assert.ok(!/addEventListener\(\s*'(found_variation|reset_data|wc_fragments_refreshed|added_to_cart)'/.test(source), 'Lo que WooCommerce dispara con `.trigger()` nunca se escucha con addEventListener (V.12.3).');
console.log(`DIGITALÍSIMO Elements: muestras en el navegador validadas (${Buffer.byteLength(source)} B).`);
