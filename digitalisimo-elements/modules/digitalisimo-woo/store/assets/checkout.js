/* Finalizar compra (digitalisimo-woo-checkout): abre y cierra el acceso y el cupón, aplica el cupón y accede por las
   mismas peticiones que el widget al que sustituye, marca la página y el elemento en `_wp_http_referer` (la revisión del
   pedido por `?wc-ajax=` vuelve a aplicar los ganchos del widget), la animación del botón de compra y el desplegable de
   países. Sin jQuery: los avisos de WooCommerce se lanzan como eventos del documento. */
(function () {
	'use strict';
	var SEL = '.digi-co';

	function post(url, data) {
		var body = new URLSearchParams();
		Object.keys(data).forEach(function (k) { body.append(k, data[k]); });
		return fetch(url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: body }).then(function (r) { return r.text(); });
	}

	function busy(el, on) {
		el.classList.toggle('processing', on);
		el.setAttribute('aria-busy', on ? 'true' : 'false');
	}

	function notice(root, html) {
		document.querySelectorAll('.woocommerce-error, .woocommerce-message').forEach(function (n) { n.remove(); });
		var form = root.querySelector('form.woocommerce-checkout');
		if (form && html) form.insertAdjacentHTML('beforebegin', html);
	}

	function fire(name) {
		document.body.dispatchEvent(new CustomEvent(name, { bubbles: true }));
	}

	function toggle(button, panel, force) {
		if (!panel) return;
		var open = typeof force === 'boolean' ? force : panel.hidden;
		panel.hidden = !open;
		button.setAttribute('aria-expanded', open ? 'true' : 'false');
		if (open) {
			var field = panel.querySelector('input:not([type=hidden])');
			if (field) field.focus();
		}
	}

	function coupon(root) {
		var box = root.querySelector('.digi-co__coupon');
		var params = window.wc_checkout_params;
		if (!box || !params || box.classList.contains('processing')) return;
		var code = box.querySelector('input[name="coupon_code"]');
		busy(box, true);
		post(String(params.wc_ajax_url).replace('%%endpoint%%', 'apply_coupon'), { security: params.apply_coupon_nonce, coupon_code: code ? code.value : '' }).then(function (html) {
			busy(box, false);
			if (!html) return;
			notice(root, html);
			if (/woocommerce-error|does not exist/.test(html)) window.scrollTo({ top: 0 });
			var button = box.querySelector('.digi-co__show-coupon');
			if (button) toggle(button, box.querySelector('.digi-co__coupon-form'), false);
			fire('applied_coupon_in_checkout');
			fire('update_checkout');
		}).catch(function () { busy(box, false); });
	}

	function login(root) {
		var box = root.querySelector('.digi-co__login');
		var url = root.getAttribute('data-digi-ajax');
		if (!box || !url || box.classList.contains('processing')) return;
		var val = function (s) { var f = box.querySelector(s); return f ? f.value : ''; };
		var remember = box.querySelector('#rememberme');
		busy(box, true);
		post(url, { action: 'elementor_woocommerce_checkout_login_user', username: val('input[name="username"]'), password: val('input[name="password"]'), nonce: val('input[name="woocommerce-login-nonce"]'), remember: remember && remember.checked ? 'true' : 'false' }).then(function (text) {
			busy(box, false);
			var data = {};
			try { data = JSON.parse(text); } catch (e) { data = {}; }
			if (data.logged_in) { location.reload(); return; }
			notice(root, data.message || '');
			fire('checkout_error');
		}).catch(function () { busy(box, false); });
	}

	function referers(root) {
		var host = root.closest('[data-id]');
		var url = new URL(location.href);
		if (root.getAttribute('data-digi-page')) url.searchParams.set('elementorPageId', root.getAttribute('data-digi-page'));
		if (host) url.searchParams.set('elementorWidgetId', host.getAttribute('data-id'));
		root.querySelectorAll('[name="_wp_http_referer"]').forEach(function (i) { i.value = url.toString(); });
	}

	function animate(root) {
		var cls = root.getAttribute('data-digi-animation');
		var button = cls && root.querySelector('#place_order');
		if (button && !button.classList.contains(cls)) button.classList.add(cls);
	}

	function init(root) {
		if (!root || root.digiCheckout) return;
		root.digiCheckout = true;
		referers(root);
		animate(root);
		if (window.MutationObserver) new MutationObserver(function () { animate(root); referers(root); }).observe(root, { childList: true, subtree: true });
		root.addEventListener('click', function (e) {
			var b = e.target.closest('button');
			if (!b || !root.contains(b)) return;
			if (b.matches('.digi-co__show-login')) { e.preventDefault(); toggle(b, root.querySelector('.digi-co__login-form')); }
			else if (b.matches('.digi-co__show-coupon')) { e.preventDefault(); toggle(b, root.querySelector('.digi-co__coupon-form')); }
			else if (b.matches('.digi-co__apply')) { e.preventDefault(); coupon(root); }
			else if (b.matches('.digi-co__login-submit')) { e.preventDefault(); login(root); }
		});
		// Intro en el cupón o en el acceso: aplica o accede (sin enviar el pedido).
		root.addEventListener('keydown', function (e) {
			if (e.key !== 'Enter' || !e.target.matches('input')) return;
			if (e.target.closest('.digi-co__coupon-form')) { e.preventDefault(); coupon(root); }
			else if (e.target.closest('.digi-co__login-form')) { e.preventDefault(); login(root); }
		});
		// Desplegable de países (selectWoo, en el cuerpo del documento): la clase de sus opciones.
		var mark = function (e) {
			if (!e.target.closest || !e.target.closest('.select2-selection')) return;
			setTimeout(function () {
				document.querySelectorAll('body > .select2-container--open').forEach(function (c) { c.classList.add('digi-co-select2'); });
			}, 0);
		};
		root.addEventListener('mousedown', mark);
		root.addEventListener('keydown', mark);
	}

	function boot(scope) {
		(scope || document).querySelectorAll(SEL).forEach(init);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { boot(); });
	else boot();
	window.addEventListener('elementor/frontend/init', function () {
		var f = window.elementorFrontend;
		if (f && f.hooks) f.hooks.addAction('frontend/element_ready/digitalisimo-woo-checkout.default', function ($scope) { boot($scope && $scope[0]); });
	});
})();
