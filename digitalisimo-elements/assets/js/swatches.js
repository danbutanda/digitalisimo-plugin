/* DIGITALÍSIMO Elements · muestras de variación (F4-Woo, V.6.4). Sin dependencias. Los radios manejan el <select> de
 * WooCommerce sin cambiarlo: elegir uno fija su valor y lanza un `change` nativo con `bubbles`, que llega al manejador
 * delegado de WooCommerce. El estado se relee del <select> tras cada mutación (WooCommerce quita y deshabilita
 * opciones), tras «Limpiar» (su `.val('').trigger('change')` no produce ni mutación ni evento nativo) y, por el puente
 * jQuery que exige el modo AJAX (WooCommerce no toca las opciones), con `woocommerce_variation_has_changed`,
 * `reset_data` y `show_variation`. En los bucles, «Cambiar la imagen al pasar el cursor». */
(() => {
'use strict';
const w = window, d = document;
let n = 0;
const opt = (sel, v) => Array.prototype.find.call(sel.options, o => o.value === v) || null;

// Habilitado sólo si existe un <option> con ese valor y no está deshabilitado; elegido si es el valor del <select>.
function sync(field) {
	const sel = field.querySelector('select');
	if (!sel) return;
	field.querySelectorAll('input[type="radio"]').forEach(r => {
		const o = opt(sel, r.value), off = !o || o.disabled, l = r.closest('.digi-swatch') || r.parentNode, na = l.querySelector('.digi-swatch__na');
		r.disabled = off;
		r.checked = !off && sel.value !== '' && sel.value === r.value;
		l.classList.toggle('is-disabled', off);
		l.classList.toggle('is-selected', r.checked);
		if (na) na.textContent = off ? ' (no disponible)' : '';
	});
}

function init(field) {
	const sel = field.querySelector('select'), radios = field.querySelectorAll('input[type="radio"]');
	if (field.hasAttribute('data-digi-ready') || !sel || !radios.length) return;
	field.setAttribute('data-digi-ready', '');
	// Formulario vacío propio, fuera del de compra (no puede ir dentro): los radios siguen agrupados y no viajan.
	const id = 'digi-sw-form-' + (++n) + '-' + Date.now().toString(36), holder = d.createElement('form');
	holder.id = id;
	holder.hidden = true;
	d.body.appendChild(holder);
	radios.forEach(r => { r.setAttribute('form', id); r.name = id; });
	// El <select> oculto no puede mostrar su aviso de obligatorio: lo lleva el primer radio.
	if (sel.required) { sel.required = false; radios[0].required = true; }
	field.addEventListener('change', e => {
		const r = e.target;
		if (!r || r.type !== 'radio') return;
		if (opt(sel, r.value)) { sel.value = r.value; sel.dispatchEvent(new Event('change', { bubbles: true })); }
		sync(field);
	});
	new MutationObserver(() => sync(field)).observe(sel, { childList: true, subtree: true, attributes: true });
	const cart = sel.form;
	if (cart) {
		cart.addEventListener('submit', e => { if (radios[0].required && sel.value === '') { e.preventDefault(); radios[0].reportValidity(); } }, true);
		if (w.jQuery) w.jQuery(cart).on('woocommerce_variation_has_changed reset_data show_variation', () => sync(field));
	}
	field.classList.add('digi-swatch-field--ready');
	sync(field);
}

function scan(root) {
	root = root && root.querySelectorAll ? root : d;
	if (root.matches && root.matches('.digi-swatch-field')) init(root);
	root.querySelectorAll('.digi-swatch-field').forEach(init);
}

// «Limpiar»: cuando el clic llega a document, WooCommerce ya cambió el valor; se relee tras una microtarea.
d.addEventListener('click', e => {
	const a = e.target && e.target.closest ? e.target.closest('.reset_variations') : null;
	if (!a) return;
	const scope = a.closest('form') || d;
	Promise.resolve().then(() => scope.querySelectorAll('.digi-swatch-field[data-digi-ready]').forEach(sync));
});

// Bucle: la imagen de la variación en la ficha del producto; la original al salir.
const KEYS = ['src', 'srcset', 'sizes'];
function swap(a, on) {
	const list = a.closest('[data-digi-sw-hover]'), card = list && list.closest('.e-loop-item, [data-elementor-type="loop-item"], li.product, .product');
	const img = card && card.querySelector('img:not(.digi-swatch__img)');
	if (!img) return;
	if (on) {
		let data;
		try { data = JSON.parse(a.getAttribute('data-digi-sw-img')); } catch (x) { return; }
		if (!img.hasAttribute('data-digi-sw-src')) KEYS.forEach(k => img.setAttribute('data-digi-sw-' + k, img.getAttribute(k) || ''));
		KEYS.forEach(k => { if (data[k]) img.setAttribute(k, data[k]); else if (k !== 'src') img.removeAttribute(k); });
	} else if (img.hasAttribute('data-digi-sw-src')) {
		KEYS.forEach(k => { const v = img.getAttribute('data-digi-sw-' + k); if (v) img.setAttribute(k, v); else img.removeAttribute(k); img.removeAttribute('data-digi-sw-' + k); });
	}
}
const hover = on => e => {
	const a = e.target && e.target.closest ? e.target.closest('a[data-digi-sw-img]') : null;
	if (a && !(e.relatedTarget && a.contains(e.relatedTarget))) swap(a, on);
};
[['mouseover', true], ['mouseout', false], ['focusin', true], ['focusout', false]].forEach(([t, on]) => d.addEventListener(t, hover(on)));

// Contenido que llega después: vista rápida (`digitalisimo:content`) y refresco de bucles del constructor.
d.addEventListener('digitalisimo:content', e => scan(e.detail && e.detail.root));
const hooks = () => { const F = w.elementorFrontend; if (F && F.hooks) F.hooks.addAction('frontend/element_ready/widget', $s => scan($s && $s[0])); };
if (w.elementorFrontend && w.elementorFrontend.hooks) hooks(); else w.addEventListener('elementor/frontend/init', hooks);

w.digitalisimoSwatches = { init: scan, sync, version: 1 };
if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', () => scan(d)); else scan(d);
})();
