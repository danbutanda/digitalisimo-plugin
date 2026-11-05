/* Botones de contacto (digitalisimo-contact-buttons, C8): abre y cierra el panel (botón con aria-expanded, Escape
   cierra y devuelve el foco), animaciones de entrada y salida, hora del mensaje y recuento de clics. Sin jQuery ni
   dependencias; con movimiento reducido no hay animaciones. Los enlaces de contacto son enlaces normales. */
(function () {
	'use strict';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	function editing() { return document.body.classList.contains('elementor-editor-active'); }
	function settings(root) {
		var w = root.closest('[data-settings]');
		try { return JSON.parse(w && w.getAttribute('data-settings') || '{}'); } catch (e) { return {}; }
	}
	function responsive(s, key) {
		var f = window.elementorFrontend, d = f && f.getCurrentDeviceMode ? f.getCurrentDeviceMode() : innerWidth <= 767 ? 'mobile' : innerWidth <= 1024 ? 'tablet' : 'desktop';
		return (d !== 'desktop' && s[key + '_' + d]) || (d === 'mobile' && s[key + '_tablet']) || s[key];
	}
	function track(e) { if (window.digiFloatingTrack) { window.digiFloatingTrack(e.currentTarget); } }

	function init(root) {
		if (!root || root.digiCb) { return; }
		root.digiCb = true;
		var s = settings(root);
		var wrap = root.querySelector('.digi-cb__content-wrapper');
		var content = root.querySelector('.digi-cb__content');
		var toggle = root.querySelector('.digi-cb__chat-button');
		var closers = root.querySelectorAll('.digi-cb__close-button');
		var animate = root.classList.contains('has-animations') && !reduce;
		var time = root.querySelector('.digi-cb__message-bubble-time');
		if (time) {
			time.textContent = new Intl.DateTimeFormat('default', { hour12: '12h' === time.getAttribute('data-time-format'), hour: 'numeric', minute: 'numeric' }).format(new Date());
		}
		function hidden() { return !wrap || wrap.classList.contains('hidden'); }
		function expanded(open) {
			[toggle].concat(Array.prototype.slice.call(closers)).forEach(function (b) { if (b) { b.setAttribute('aria-expanded', open ? 'true' : 'false'); } });
		}
		function open() {
			var name = responsive(s, 'style_chat_box_entrance_animation');
			if (animate && name && 'none' !== name && content) {
				content.classList.add('animated', name);
				wrap.classList.remove('animated-wrapper');
			} else if (content) { content.classList.add('visible'); }
			wrap.classList.remove('hidden');
			wrap.setAttribute('aria-hidden', 'false');
			wrap.inert = false;
			expanded(true);
			if (!editing()) {
				var first = wrap.querySelector('a[href],button');
				if (first) { first.focus(); }
			}
		}
		function close(back) {
			var name = responsive(s, 'style_chat_box_exit_animation');
			if (animate && name && 'none' !== name && content) {
				content.classList.add('animated', 'reverse', name);
				wrap.classList.add('animated-wrapper');
			} else if (content) { content.classList.remove('visible'); }
			wrap.classList.add('hidden');
			wrap.setAttribute('aria-hidden', 'true');
			wrap.inert = true;
			expanded(false);
			if (back && toggle) { toggle.focus(); }
		}
		if (content) {
			content.addEventListener('animationend', function () {
				var exit = content.classList.contains('reverse');
				content.classList.remove('animated', 'reverse', responsive(s, 'style_chat_box_entrance_animation') || 'x', responsive(s, 'style_chat_box_exit_animation') || 'x');
				content.classList.toggle('visible', !exit);
			});
		}
		if (wrap) {
			if (hidden()) { wrap.inert = true; }
			expanded(!hidden());
			if (toggle) { toggle.addEventListener('click', function (e) { if (hidden()) { open(); } else { close(false); } if (root.classList.contains('digi-cb--p5')) { track(e); } }); }
			Array.prototype.forEach.call(closers, function (b) { b.addEventListener('click', function () { close(true); }); });
			root.addEventListener('keydown', function (e) { if ('Escape' === e.key && !hidden()) { close(true); } });
			if (editing()) { open(); }
		} else if (toggle && root.classList.contains('digi-cb--p5')) {
			toggle.addEventListener('click', track);
		}
		Array.prototype.forEach.call(root.querySelectorAll('a[href]'), function (a) { a.addEventListener('click', track); });
		if (toggle && toggle.classList.contains('has-entrance-animation')) {
			var entry = responsive(s, 'style_chat_button_animation');
			var shown = function () { toggle.classList.remove(entry); toggle.classList.add('visible'); };
			if (!entry || 'none' === entry || reduce) { shown(); } else {
				toggle.addEventListener('animationend', shown, { once: true });
				toggle.classList.add(entry);
			}
		}
		if (root.classList.contains('digi-cb--p10')) {
			var links = root.querySelectorAll('.digi-cb__contact-icon-link');
			Array.prototype.forEach.call(links, function (a) {
				a.addEventListener('focus', function () { a.classList.add('active'); });
				a.addEventListener('blur', function () { a.classList.remove('active'); });
			});
		}
	}

	function scan(scope) {
		Array.prototype.forEach.call((scope || document).querySelectorAll('.digi-cb'), init);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { scan(); }); } else { scan(); }
	function hook() {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-contact-buttons.default', function ($el) { scan($el && $el[0] ? $el[0] : null); });
	}
	if (window.elementorFrontend && window.elementorFrontend.hooks) { hook(); } else { window.addEventListener('elementor/frontend/init', hook); }
})();
