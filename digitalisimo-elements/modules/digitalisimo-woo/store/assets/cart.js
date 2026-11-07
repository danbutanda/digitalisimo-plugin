/* Carrito (digitalisimo-woo-cart): actualización automática al cambiar una cantidad, columna derecha fija, animación
 * de los botones que pinta WooCommerce y, en el editor, acciones desactivadas. Sin dependencias: escucha el
 * DOM y vuelve a aplicar lo suyo cuando WooCommerce reemplaza el formulario o los totales. Ajustes: `data-settings` del
 * elemento (los controles `frontend_available`). */
(function () {
	'use strict';
	function settings(el) {
		try {
			return JSON.parse(el.getAttribute('data-settings') || '{}') || {};
		} catch (e) {
			return {};
		}
	}
	function editor() {
		return document.body.classList.contains('elementor-editor-active');
	}
	function decorate(el, s) {
		var anim = [['.checkout-button', s.checkout_button_hover_animation], ['.shop_table .button', s.forms_buttons_hover_animation]];
		anim.forEach(function (pair) {
			if (pair[1]) {
				el.querySelectorAll(pair[0]).forEach(function (b) {
					b.classList.add('elementor-animation-' + pair[1]);
				});
			}
		});
		// Destino de los formularios: la página y el elemento, para que el servidor repita los textos del widget en las
		// peticiones de WooCommerce.
		var page = el.closest('[data-elementor-id]');
		el.querySelectorAll('[name=_wp_http_referer]').forEach(function (input) {
			var url = new URL(document.location.href);
			if (page) {
				url.searchParams.set('elementorPageId', page.getAttribute('data-elementor-id'));
			}
			url.searchParams.set('elementorWidgetId', el.getAttribute('data-id') || '');
			input.value = url.toString();
		});
		if (editor()) {
			el.querySelectorAll('button[name=update_cart]').forEach(function (b) {
				b.disabled = true;
				b.setAttribute('aria-disabled', 'true');
			});
			el.querySelectorAll('.qty').forEach(function (q) {
				q.disabled = true;
			});
			el.querySelectorAll('.product-remove a').forEach(function (a) {
				a.style.pointerEvents = 'none';
			});
			el.querySelectorAll('input[type=hidden]').forEach(function (i) {
				var row = i.closest('.form-row');
				if (row) {
					row.classList.add('elementor-hidden');
				}
			});
			el.querySelectorAll('.shipping-calculator-form').forEach(function (f) {
				f.style.display = 'block';
			});
		}
	}
	function init(el) {
		var root = el && el.querySelector('.digi-cart');
		if (!root || el.digiCartRoot === root) {
			return;
		}
		el.digiCartRoot = root;
		var s = settings(el);
		var sticky = el.querySelector('.digi-cart__sticky');
		if (sticky && 'yes' === s.sticky_right_column) {
			var bar = document.getElementById('wpadminbar');
			var top = parseInt(s.sticky_right_column_offset, 10) || 0;
			if (bar && 'fixed' === getComputedStyle(bar).position) {
				top += bar.offsetHeight;
			}
			sticky.classList.add('digi-cart__sticky--active');
			sticky.style.top = top + 'px';
		}
		var timer = 0;
		if ('yes' === s.update_cart_automatically && !el.digiCartAuto) {
			el.digiCartAuto = true;
			el.addEventListener('input', function (e) {
				if (!e.target.classList || !e.target.classList.contains('qty')) {
					return;
				}
				clearTimeout(timer);
				timer = setTimeout(function () {
					var button = el.querySelector('button[name=update_cart]');
					if (button) {
						button.disabled = false;
						button.click();
					}
				}, 1500);
			});
		}
		// Desplegable del país (select2, colgado del body): la clase propia, para los colores de los campos. Como PRO en
		// `select2:open`: también al abrirlo con el teclado (selectWoo abre con `mousedown` o con una tecla).
		var mark = function (e) {
			if (e.target.closest && e.target.closest('.select2-selection')) {
				setTimeout(function () {
					document.querySelectorAll('.select2-container--open .select2-dropdown').forEach(function (d) {
						d.classList.add('digi-cart-select2');
					});
				});
			}
		};
		el.addEventListener('mousedown', mark);
		el.addEventListener('keydown', mark);
		decorate(el, s);
		if (typeof MutationObserver === 'function') {
			new MutationObserver(function () {
				decorate(el, s);
			}).observe(root, { childList: true, subtree: true });
		}
	}
	function all() {
		document.querySelectorAll('.elementor-widget-digitalisimo-woo-cart').forEach(init);
	}
	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', all);
	} else {
		all();
	}
	window.addEventListener('elementor/frontend/init', function () {
		if (window.elementorFrontend && elementorFrontend.hooks) {
			elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-woo-cart.default', function ($el) {
				init($el && $el[0] ? $el[0] : $el);
			});
		}
	});
})();
