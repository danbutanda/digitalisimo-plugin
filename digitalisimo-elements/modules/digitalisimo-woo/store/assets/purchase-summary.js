/* Resumen de la compra (digitalisimo-woo-purchase-summary): lo que hacía el script del widget de PRO, sin dependencias.
   Iguala la altura de las direcciones, junta la nota de compra con su producto y pone la animación del botón. */
(function () {
	'use strict';
	function init(el) {
		if (!el || el.digiSummary) return;
		el.digiSummary = true;
		var s = {};
		try { s = JSON.parse(el.getAttribute('data-settings') || '{}'); } catch (e) {}
		if (s.order_details_button_hover_animation) el.querySelectorAll('.order-again .button, td .button').forEach(function (b) { b.classList.add('elementor-animation-' + s.order_details_button_hover_animation); });
		el.querySelectorAll('.product-purchase-note').forEach(function (tr) {
			var prev = tr.previousElementSibling;
			if (prev) prev.querySelectorAll(':scope > td').forEach(function (td) { td.classList.add('product-purchase-note-is-below'); });
		});
		var list = [].slice.call(el.querySelectorAll('address'));
		var max = 0;
		list.forEach(function (a) { a.style.height = ''; });
		list.forEach(function (a) { max = Math.max(max, a.offsetHeight); });
		if (max > 0) list.forEach(function (a) { a.style.height = max + 'px'; });
	}
	function all() { document.querySelectorAll('.elementor-widget-digitalisimo-woo-purchase-summary').forEach(init); }
	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-woo-purchase-summary.default', function ($el) { init($el[0] || $el); });
	}
	if ('loading' === document.readyState) document.addEventListener('DOMContentLoaded', all); else all();
}());
