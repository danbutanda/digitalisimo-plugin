/* Mi cuenta (digitalisimo-woo-my-account): lo que hacía el script del widget de PRO, sin dependencias. Iguala la altura
   de las direcciones y de los formularios de acceso, junta la nota de compra con su producto y pone la animación de los
   botones, marca el desplegable de países abierto; en el editor alterna las páginas de la cuenta (todas pintadas) y el
   botón «Editar plantilla» del panel. */
(function () {
	'use strict';
	var W = '.elementor-widget-digitalisimo-woo-my-account';
	var editing = function () { return document.body.classList.contains('elementor-editor-active'); };
	function equalize(list) {
		var max = 0;
		list.forEach(function (el) { el.style.height = ''; });
		list.forEach(function (el) { max = Math.max(max, el.offsetHeight); });
		if (max > 0) list.forEach(function (el) { el.style.height = max + 'px'; });
	}
	function heights(root) {
		equalize([].slice.call(root.querySelectorAll('address')));
		if (!editing()) equalize([].slice.call(root.querySelectorAll('form.login, form.register')));
	}
	function settings(el) {
		try { return JSON.parse(el.getAttribute('data-settings') || '{}'); } catch (e) { return {}; }
	}
	function show(el, page) {
		var acc = el.querySelector('.digi-acc');
		el.querySelectorAll('[data-digi-page]').forEach(function (p) { p.style.display = p.getAttribute('data-digi-page') === page ? 'block' : 'none'; });
		if (acc) {
			acc.className = acc.className.replace(/\s*digi-acc--[a-z-]+|\s*digi-acc-s\b/g, '');
			var custom = 'dashboard' === page && acc.querySelector('[data-digi-page="dashboard"] .elementor');
			acc.className += ' digi-acc--' + page + (custom ? ' digi-acc--custom' : ' digi-acc-s');
		}
		if ('view-order' !== page) {
			el.querySelectorAll('.woocommerce-MyAccount-navigation li').forEach(function (li) {
				li.classList.toggle('is-active', li.classList.contains('woocommerce-MyAccount-navigation-link--' + page));
			});
		}
		heights(el);
	}
	function init(el) {
		if (!el || el.digiAccount) return;
		el.digiAccount = true;
		var s = settings(el);
		if (s.forms_buttons_hover_animation) el.querySelectorAll('.woocommerce button.button, #add_payment_method #payment #place_order').forEach(function (b) { b.classList.add('elementor-animation-' + s.forms_buttons_hover_animation); });
		if (s.tables_button_hover_animation) el.querySelectorAll('.order-again .button, td .button, .woocommerce-pagination .button').forEach(function (b) { b.classList.add('elementor-animation-' + s.tables_button_hover_animation); });
		el.querySelectorAll('tr.product-purchase-note').forEach(function (tr) {
			var prev = tr.previousElementSibling;
			if (prev) prev.querySelectorAll(':scope > td').forEach(function (td) { td.classList.add('product-purchase-note-is-below'); });
		});
		heights(el);
		el.addEventListener('input', function (e) { if (e.target && 'reg_password' === e.target.id) heights(el); });
		// Desplegable de países abierto (selectWoo, en el cuerpo del documento): la clase de sus opciones, como PRO
		// (`e-woo-select2-wrapper` en `select2:open`).
		var mark = function (e) {
			if (!e.target.closest || !e.target.closest('.select2-selection')) return;
			setTimeout(function () {
				document.querySelectorAll('body > .select2-container--open').forEach(function (c) { c.classList.add('digi-acc-select2'); });
			}, 0);
		};
		el.addEventListener('mousedown', mark);
		el.addEventListener('keydown', mark);
		if (!editing()) return;
		el.addEventListener('click', function (e) {
			var link = e.target.closest('a');
			if (!link || !el.contains(link)) return;
			var item = link.closest('.woocommerce-MyAccount-navigation-link');
			var page = '';
			if (item) page = (item.className.match(/woocommerce-MyAccount-navigation-link--([a-z-]+)/) || [])[1] || '';
			else if (link.closest('.woocommerce-orders-table__cell-order-number') || link.matches('.my_account_orders .woocommerce-button.view')) page = 'view-order';
			if (page && el.querySelector('[data-digi-page="' + page + '"]')) {
				e.preventDefault();
				el.setAttribute('data-digi-page', page);
				show(el, page);
			}
		});
		show(el, el.getAttribute('data-digi-page') || 'dashboard');
	}
	function all() { document.querySelectorAll(W).forEach(init); }
	if (window.elementorFrontend && window.elementorFrontend.hooks) {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-woo-my-account.default', function ($el) { init($el[0] || $el); });
	}
	var p = window.parent;
	if (editing() && p && p !== window && p.elementor && p.elementorPro && p.elementorPro.modules && p.elementorPro.modules.woocommerce && !p.digiAccountPanel) {
		p.digiAccountPanel = true;
		p.elementor.channels.editor.on('editor:widget:digitalisimo-woo-my-account:section_additional_options:activated', function () {
			p.elementorPro.modules.woocommerce.onTemplateIdChange('customize_dashboard_select');
		});
	}
	if ('loading' === document.readyState) document.addEventListener('DOMContentLoaded', all); else all();
}());
