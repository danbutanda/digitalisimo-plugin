/* Barra flotante (digitalisimo-floating-bar, C8): cierre (botón y Escape), posición arriba, cinta de titulares con
   pausa, cupón que se copia con anuncio y animación de entrada. Sin jQuery ni dependencias; respeta el movimiento
   reducido (la cinta queda quieta y el cupón aparece sin animación). */
(function () {
	'use strict';
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	function editing() { return document.body.classList.contains('elementor-editor-active'); }
	function settings(root) {
		var w = root.closest('[data-settings]');
		try { return JSON.parse(w && w.getAttribute('data-settings') || '{}'); } catch (e) { return {}; }
	}
	function device() {
		var f = window.elementorFrontend;
		if (f && f.getCurrentDeviceMode) { return f.getCurrentDeviceMode(); }
		return innerWidth <= 767 ? 'mobile' : innerWidth <= 1024 ? 'tablet' : 'desktop';
	}
	function responsive(s, key) {
		var d = device();
		return (d !== 'desktop' && s[key + '_' + d]) || (d === 'mobile' && s[key + '_tablet']) || s[key];
	}
	function stickyTop(root) { return root.classList.contains('is-sticky') && root.classList.contains('has-vertical-position-top'); }
	function track(e) { if (window.digiFloatingTrack) { window.digiFloatingTrack(e.currentTarget); } }

	function close(root) {
		if (editing()) { return; }
		root.classList.add('is-hidden');
		if (stickyTop(root)) { document.body.style.paddingTop = ''; }
	}

	function ticker(root, s) {
		var box = root.querySelector('.digi-fbar__headlines');
		var inner = box && box.querySelector('.digi-fbar__headlines-inner');
		if (!inner || !inner.children.length) { return; }
		var pause = root.querySelector('.digi-fbar__pause-button');
		var play = root.querySelector('.digi-fbar__play-button');
		function toggle(paused) {
			box.classList.toggle('is-paused', paused);
			if (pause && play) {
				var hide = paused ? pause : play, show = paused ? play : pause;
				var had = document.activeElement === hide;
				hide.hidden = true; show.hidden = false;
				if (had) { show.focus(); }
			}
		}
		if (pause) { pause.addEventListener('click', function () { toggle(true); }); }
		if (play) { play.addEventListener('click', function () { toggle(false); }); }
		Array.prototype.forEach.call(inner.querySelectorAll('a'), function (a) { a.addEventListener('click', track); });
		if (reduce || editing()) { return; }
		var items = Array.prototype.slice.call(inner.children);
		function copy(parent, nodes) {
			nodes.forEach(function (n) {
				var c = n.cloneNode(true);
				c.setAttribute('aria-hidden', 'true');
				c.inert = true;
				c.classList.add('digi-fbar__headline--clone');
				parent.appendChild(c);
			});
		}
		for (var i = 0; i < 50 && inner.offsetWidth < box.offsetWidth; i++) { copy(inner, items); }
		if ('autoplay' === (responsive(s, 'style_ticker_animation_type') || 'autoplay')) {
			var twin = inner.cloneNode(true);
			twin.classList.add('digi-fbar__headlines-inner--clone');
			twin.setAttribute('aria-hidden', 'true');
			twin.inert = true;
			box.insertBefore(twin, box.querySelector('.digi-fbar__overlay'));
			box.setAttribute('data-animated', 'true');
			return;
		}
		var rtl = 'rtl' === getComputedStyle(root).direction;
		window.addEventListener('scroll', function () {
			inner.style.transform = 'translateX(' + (rtl ? '' : '-') + scrollY + 'px)';
			var last = inner.lastElementChild.getBoundingClientRect(), edge = box.getBoundingClientRect();
			if (rtl ? last.left >= edge.left : last.right <= edge.right) { copy(inner, items); }
		}, { passive: true });
	}

	function coupon(root, s) {
		var btn = root.querySelector('.digi-fbar__coupon-button');
		if (!btn) { return; }
		var code = btn.querySelector('.digi-fbar__coupon-code');
		var done = btn.querySelector('.digi-fbar__coupon-success');
		var status = root.querySelector('.digi-fbar__status');
		var timer = 0;
		if (btn.classList.contains('has-entrance-animation')) {
			var name = responsive(s, 'style_coupon_animation');
			var shown = function () {
				btn.classList.remove('animated', name, 'has-entrance-animation');
				btn.classList.add('visible');
			};
			if (reduce || editing() || !name) { shown(); } else {
				btn.addEventListener('animationend', shown, { once: true });
				btn.classList.add('animated', name);
				setTimeout(shown, (parseInt(s.style_coupon_animation_delay, 10) || 0) + 2600);
			}
		}
		btn.addEventListener('click', function (e) {
			track(e);
			var text = code ? code.textContent.trim() : '';
			var d = responsive(s, 'style_coupon_success_message_duration') || {};
			var ms = d.size !== undefined && d.size !== '' ? parseFloat(d.size) * ('ms' === d.unit ? 1 : 1000) : 1500;
			var r = btn.getBoundingClientRect();
			root.style.setProperty('--digi-fbar-coupon-width', r.width + 'px');
			root.style.setProperty('--digi-fbar-coupon-height', r.height + 'px');
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).catch(function () {});
			} else {
				var t = document.createElement('textarea');
				t.value = text; t.style.position = 'fixed'; t.style.opacity = '0';
				document.body.appendChild(t); t.select();
				try { document.execCommand('copy'); } catch (err) { /* sin portapapeles */ }
				t.remove();
				btn.focus();
			}
			if (code && done) { code.classList.add('is-hidden'); done.classList.remove('is-hidden'); }
			if (status) { status.textContent = status.getAttribute('data-message') || ''; }
			clearTimeout(timer);
			timer = setTimeout(function () {
				if (code && done) { code.classList.remove('is-hidden'); done.classList.add('is-hidden'); }
				if (status) { status.textContent = ''; }
				root.style.setProperty('--digi-fbar-coupon-width', 'initial');
				root.style.setProperty('--digi-fbar-coupon-height', 'initial');
			}, ms);
		});
	}

	function init(root) {
		if (!root || root.digiFbar) { return; }
		root.digiFbar = true;
		var s = settings(root);
		var x = root.querySelector('.digi-fbar__close-button');
		if (x) { x.addEventListener('click', function () { close(root); }); }
		root.addEventListener('keydown', function (e) {
			if ('Escape' === e.key && x) { close(root); }
		});
		if (!editing() && root.classList.contains('has-vertical-position-top')) {
			if (!root.classList.contains('is-sticky')) {
				var doc = root.closest('.elementor');
				var bar = document.getElementById('wpadminbar');
				if (doc) { if (bar) { bar.after(doc); } else { document.body.prepend(doc); } }
			} else {
				var admin = document.getElementById('wpadminbar');
				if (admin) { root.style.top = admin.offsetHeight + 'px'; }
				document.body.style.paddingTop = root.offsetHeight + 'px';
			}
		}
		if (root.classList.contains('digi-fbar--p3')) { coupon(root, s); } else { ticker(root, s); }
	}

	function scan(scope) {
		Array.prototype.forEach.call((scope || document).querySelectorAll('.digi-fbar'), init);
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', function () { scan(); }); } else { scan(); }
	function hook() {
		window.elementorFrontend.hooks.addAction('frontend/element_ready/digitalisimo-floating-bar.default', function ($el) { scan($el && $el[0] ? $el[0] : null); });
	}
	if (window.elementorFrontend && window.elementorFrontend.hooks) { hook(); } else { window.addEventListener('elementor/frontend/init', hook); }
})();
