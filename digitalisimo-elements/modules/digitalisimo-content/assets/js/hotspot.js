/* Puntos de interés (digitalisimo-hotspot): información emergente al pulsar o al pasar el puntero (por dispositivo),
 * también con el foco en el modo «al pasar»; Escape la cierra; animación en secuencia al entrar en la pantalla. Sin
 * dependencias. Deriva del manejador de PRO Elements (GPL, modules/hotspot/assets/js/frontend/handlers/hotspot.js). */
(function () {
	'use strict';
	var CHAIN = { widescreen: ['widescreen'], laptop: ['laptop'], tablet_extra: ['tablet_extra', 'laptop'], tablet: ['tablet', 'tablet_extra', 'laptop'], mobile_extra: ['mobile_extra', 'tablet', 'tablet_extra', 'laptop'], mobile: ['mobile', 'mobile_extra', 'tablet', 'tablet_extra', 'laptop'] };

	// Dispositivo actual según los puntos de corte del sitio (los del núcleo), o «desktop».
	function device() {
		var cfg = window.elementorFrontendConfig, bp = (cfg && cfg.responsive && cfg.responsive.activeBreakpoints) || {}, w = window.innerWidth, cur = 'desktop', best = Infinity;
		Object.keys(bp).forEach(function (k) {
			var b = bp[k];
			if ('max' === b.direction && w <= b.value && b.value < best) { best = b.value; cur = k; }
			if ('min' === b.direction && w >= b.value && Infinity === best) cur = k;
		});
		return cur;
	}

	function trigger(root) {
		var t = root.digiHsTriggers, d = device(), list = (CHAIN[d] || []).concat('desktop');
		for (var i = 0; i < list.length; i++) if (t[list[i]]) return t[list[i]];
		return 'click';
	}

	function set(spot, open) {
		var b = spot.querySelector('.digi-hs__button');
		spot.classList.toggle('digi-hs--active', open);
		if (b && b.hasAttribute('aria-expanded')) b.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	function only(root, spot, open) {
		root.querySelectorAll('.digi-hs--active').forEach(function (s) { if (s !== spot) set(s, false); });
		set(spot, open);
	}

	function init(root) {
		if (!root || root.digiHsTriggers) return;
		try { root.digiHsTriggers = JSON.parse(root.getAttribute('data-digi-trigger') || '{}'); } catch (e) { root.digiHsTriggers = {}; }
		root.querySelectorAll('.digi-hs:not(.digi-hs--no-tip)').forEach(function (spot) {
			var b = spot.querySelector('.digi-hs__button'), f = spot.matches('a') ? spot : b;
			if (!b || !spot.querySelector('.digi-hs__tip')) return;
			// Con enlace, en el móvil el paso del puntero no abre nada (como PRO): el toque sigue el enlace.
			var hover = function () { return 'mouseenter' === trigger(root) && !(spot.classList.contains('digi-hs--link') && 'mobile' === device()); };
			b.addEventListener('click', function () {
				var t = trigger(root);
				if ('click' === t) only(root, spot, !spot.classList.contains('digi-hs--active'));
				else if (hover()) only(root, spot, true);
			});
			b.addEventListener('pointerenter', function (e) { if ('touch' !== e.pointerType && hover()) only(root, spot, true); });
			spot.addEventListener('pointerleave', function (e) { if ('touch' !== e.pointerType && hover() && !spot.matches(':focus-visible') && !spot.querySelector(':focus-visible')) set(spot, false); });
			f.addEventListener('focus', function () { if (hover() && !spot.digiHsEsc) only(root, spot, true); spot.digiHsEsc = false; });
			spot.addEventListener('focusout', function (e) { if (hover() && !spot.contains(e.relatedTarget) && !spot.matches(':hover')) set(spot, false); });
		});
		var seq = root.getAttribute('data-digi-sequence');
		if (null !== seq && 'IntersectionObserver' in window) {
			var spots = root.querySelectorAll('.digi-hs'), io = new IntersectionObserver(function (entries) {
				if (!entries.some(function (x) { return x.isIntersecting; })) return;
				io.disconnect();
				spots.forEach(function (s, i) { if (i) s.style.animationDelay = (i * (Number(seq) / spots.length)) + 'ms'; });
			});
			io.observe(root);
		}
		root.classList.add('digi-hotspots--ready');
	}

	// Escape cierra la información emergente abierta y devuelve el foco a su punto si estaba dentro.
	document.addEventListener('keydown', function (e) {
		if ('Escape' !== e.key) return;
		document.querySelectorAll('.digi-hotspots .digi-hs--active').forEach(function (spot) {
			var inside = spot.contains(document.activeElement);
			set(spot, false);
			if (inside) { var f = spot.matches('a') ? spot : spot.querySelector('.digi-hs__button'); spot.digiHsEsc = f !== document.activeElement; if (f) f.focus(); }
		});
	});

	function all() { document.querySelectorAll('.digi-hotspots').forEach(init); }
	if ('loading' === document.readyState) document.addEventListener('DOMContentLoaded', all); else all();
	// Editor: cada vez que el núcleo vuelve a pintar el widget.
	function hook() { elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-hotspot.default', function ($el) { init($el && ($el[0] || $el).querySelector('.digi-hotspots')); }); }
	if (window.elementorFrontend && elementorFrontend.hooks) hook(); else window.addEventListener('elementor/frontend/init', hook);
})();
