/* Formulario de búsqueda (digitalisimo-search-form), piel «Pantalla completa»: el botón abre el <dialog> modal (capa
   superior, foco en el campo), el aspa, Escape o un clic en la superposición lo cierran y el foco vuelve al botón.
   Delegado en el documento: sirve también para los widgets que el editor vuelve a pintar. Sin dependencias. */
(function () {
	'use strict';
	var DIALOG = 'dialog.digi-search-form__container';
	function toggleOf(dialog) {
		var form = dialog.closest('form');
		return form ? form.querySelector('.digi-search-form__toggle') : null;
	}
	function open(toggle) {
		var form = toggle.closest('form');
		var dialog = form ? form.querySelector(DIALOG) : null;
		if (!dialog || dialog.open || typeof dialog.showModal !== 'function') {
			return;
		}
		dialog.showModal();
		toggle.setAttribute('aria-expanded', 'true');
		var input = dialog.querySelector('.digi-search-form__input');
		if (input) {
			input.focus();
		}
	}
	document.addEventListener('click', function (event) {
		var target = event.target;
		if (!target || !target.closest) {
			return;
		}
		var toggle = target.closest('.digi-search-form__toggle');
		if (toggle) {
			open(toggle);
			return;
		}
		var close = target.closest('.digi-search-form__close');
		var dialog = close ? close.closest(DIALOG) : (target.matches(DIALOG) ? target : null);
		if (dialog && dialog.open) {
			dialog.close();
		}
	});
	// `close` no burbujea: se escucha en la fase de captura (aspa, Escape y clic fuera pasan por aquí).
	document.addEventListener('close', function (event) {
		var dialog = event.target;
		if (!dialog || !dialog.matches || !dialog.matches(DIALOG)) {
			return;
		}
		var toggle = toggleOf(dialog);
		if (toggle) {
			toggle.setAttribute('aria-expanded', 'false');
			toggle.focus();
		}
	}, true);
}());
