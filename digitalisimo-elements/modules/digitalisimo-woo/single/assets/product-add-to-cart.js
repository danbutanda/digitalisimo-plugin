/* Añadir al carrito (digitalisimo-woo-product-add-to-cart) en una plantilla de bucle. Sin jQuery ni dependencias.
 * - La cantidad elegida pasa al botón de WooCommerce (data-quantity), que la envía al añadir por AJAX.
 * - «Ver carrito», que WooCommerce añade junto al botón dentro del formulario, pasa al contenedor del bucle (como el
 *   controlador de PRO), sin repetirse. Se vigila sólo el formulario cuyo botón se pulsó, y sólo un rato. */
(function () {
	'use strict';
	var FORM = '.digi-atc__loop-form';
	function sync(event) {
		var input = event.target;
		if (!input || !input.matches || !input.matches(FORM + ' input.qty')) {
			return;
		}
		var button = input.closest(FORM).querySelector('.ajax_add_to_cart');
		if (button) {
			button.setAttribute('data-quantity', input.value);
		}
	}
	function move(form) {
		var box = form.closest('.digi-atc__loop');
		var link = form.querySelector('.added_to_cart');
		if (!box || !link) {
			return false;
		}
		Array.prototype.forEach.call(box.querySelectorAll(':scope > .added_to_cart'), function (old) {
			old.remove();
		});
		box.appendChild(link);
		return true;
	}
	function watch(event) {
		var button = event.target && event.target.closest ? event.target.closest(FORM + ' .ajax_add_to_cart') : null;
		if (!button || !window.MutationObserver) {
			return;
		}
		var form = button.closest(FORM);
		var observer = new MutationObserver(function () {
			if (move(form)) {
				observer.disconnect();
			}
		});
		observer.observe(form, { childList: true, subtree: true });
		setTimeout(function () {
			observer.disconnect();
		}, 30000);
	}
	document.addEventListener('change', sync);
	document.addEventListener('input', sync);
	document.addEventListener('click', watch, true);
})();
