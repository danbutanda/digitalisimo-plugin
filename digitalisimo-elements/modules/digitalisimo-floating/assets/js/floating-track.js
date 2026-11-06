/* Botones flotantes, barra flotante (C8): recuento de clics por documento en el mismo punto del sitio que PRO
   (`elementor_send_clicks` del núcleo, con su nonce). Sin peticiones a terceros; se envía al llegar a 10 o al salir. */
(function () {
	'use strict';
	var clicks = [];
	function send() {
		var c = window.elementorFrontendConfig || {};
		var url = c.urls && c.urls.ajaxurl;
		var nonce = c.nonces && c.nonces.floatingButtonsClickTracking;
		if (!clicks.length || !url || !nonce) { clicks = []; return; }
		var data = new FormData();
		data.append('action', 'elementor_send_clicks');
		data.append('_nonce', nonce);
		clicks.forEach(function (id) { data.append('clicks[]', id); });
		clicks = [];
		if (navigator.sendBeacon && navigator.sendBeacon(url, data)) { return; }
		if (window.fetch) { fetch(url, { method: 'POST', body: data, keepalive: true }).catch(function () {}); }
	}
	window.digiFloatingTrack = function (el) {
		var doc = el && el.closest('[data-elementor-type="floating-buttons"]');
		var id = doc && doc.getAttribute('data-elementor-id');
		if (!id || document.body.classList.contains('elementor-editor-active')) { return; }
		clicks.push(id);
		if (clicks.length >= 10) { send(); }
	};
	window.addEventListener('pagehide', send);
})();
