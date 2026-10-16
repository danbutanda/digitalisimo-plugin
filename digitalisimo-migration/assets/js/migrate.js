/*
 * DIGITALÍSIMO Migración · botón Migrar. Avanza en el servidor la tarea del sitio (o, en la red, la de cada sitio con
 * peticiones a la administración de ese sitio) hasta terminar, y muestra su avance y su informe. Si se cierra la
 * pantalla, la tarea sigue con el WP-Cron del sitio y esta pantalla la retoma al volver.
 */
(function () {
	'use strict';
	var root = document.getElementById('digi-migrate');
	if (!root || !window.fetch || !window.URLSearchParams) return;
	var button = root.querySelector('.digi-migrate__button');
	var status = root.querySelector('.digi-migrate__status');
	var network = root.getAttribute('data-network') === '1';
	var ajax = root.getAttribute('data-ajax');
	var nonce = root.getAttribute('data-nonce');
	var running = false;

	function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }

	function post(url, data) {
		var body = new URLSearchParams();
		Object.keys(data).forEach(function (key) { body.append(key, data[key]); });
		var cross = false;
		try { cross = new URL(url, window.location.href).origin !== window.location.origin; } catch (e) {}
		return fetch(url, { method: 'POST', credentials: cross ? 'include' : 'same-origin', body: body }).then(function (response) {
			return response.json().catch(function () { throw new Error('respuesta no válida (' + response.status + ')'); });
		}).then(function (result) {
			if (!result || !result.success) throw new Error(result && typeof result.data === 'string' ? result.data : 'la petición fue rechazada');
			return result.data;
		});
	}

	function notice(message) {
		var box = document.createElement('div');
		box.className = 'notice notice-error inline';
		var p = document.createElement('p');
		p.textContent = message;
		box.appendChild(p);
		return box;
	}

	function finish() {
		running = false;
		if (button) button.disabled = false;
	}

	// La tarea de un sitio, paso a paso, hasta que termina. Tolera fallos pasajeros (un 502, un tiempo agotado) y, si
	// se pidió crearla, lo sigue pidiendo hasta que el servidor confirma que existe.
	function drive(url, siteNonce, start, render) {
		var failures = 0;
		var create = start;
		// Identificador de este clic: si la pasada terminó y sólo se perdió la respuesta, el reintento no lanza otra.
		var run = start ? Date.now().toString(36) + Math.random().toString(36).slice(2, 8) : '';
		function step() {
			return post(url, { action: 'digitalisimo_migration_step', _ajax_nonce: siteNonce, start: create ? '1' : '', run: run }).then(function (data) {
				failures = 0;
				if (data.stage || data.done) create = false;
				render(data.html || '');
				if (data.done || (data.idle && !create)) return data;
				return wait(data.busy ? 3000 : 200).then(step);
			}, function (error) {
				failures++;
				if (failures > 4) throw error;
				return wait(1500 * failures).then(step);
			});
		}
		return step();
	}

	function runSite(start) {
		running = true;
		if (button) button.disabled = true;
		drive(ajax, nonce, start, function (html) { status.innerHTML = html; }).then(finish, function (error) {
			status.appendChild(notice('La migración se detuvo en esta pantalla (' + error.message + '). Sigue en segundo plano y continúa al volver a abrirla.'));
			finish();
		});
	}

	// Red: arranca (o retoma) la migración de red y conduce, uno a uno, los sitios que tienen su tarea en marcha.
	function runNetwork(start) {
		running = true;
		if (button) button.disabled = true;
		var attempted = {};
		function summary(first) {
			return post(ajax, { action: 'digitalisimo_migration_step', _ajax_nonce: nonce, network: '1', start: first ? '1' : '' });
		}
		function siteRow(site, html) {
			var box = document.createElement('div');
			var title = document.createElement('p');
			var strong = document.createElement('strong');
			strong.textContent = site.name;
			title.appendChild(strong);
			title.appendChild(document.createTextNode(' · ' + site.url));
			box.appendChild(title);
			var body = document.createElement('div');
			body.innerHTML = html;
			box.appendChild(body);
			return box;
		}
		function loop(data) {
			status.innerHTML = data.html || '';
			if (data.done || data.idle) return finish();
			var sites = (data.sites || []).filter(function (site) { return !attempted[site.id]; });
			if (!sites.length) {
				// Lo que queda corre con el WP-Cron de cada sitio: se consulta cada poco.
				return wait(15000).then(function () { return summary(false); }).then(loop, fail);
			}
			var site = sites[0];
			attempted[site.id] = true;
			var live = document.createElement('div');
			live.className = 'digi-migrate__site';
			status.insertBefore(live, status.firstChild);
			return drive(site.ajax, site.nonce, false, function (html) {
				live.innerHTML = '';
				live.appendChild(siteRow(site, html));
			}).catch(function () {
				// El navegador no alcanza la administración de ese sitio (dominio propio): sigue con su WP-Cron.
			}).then(function () { return summary(false); }).then(loop, fail);
		}
		function fail(error) {
			status.appendChild(notice('La pantalla de red perdió la conexión (' + error.message + '). Cada sitio sigue en segundo plano; vuelve a abrir esta pantalla para ver el avance.'));
			finish();
		}
		summary(start).then(loop, fail);
	}

	if (button) {
		button.addEventListener('click', function () {
			if (running) return;
			if (network) runNetwork(true); else runSite(true);
		});
	}
	if (root.getAttribute('data-running') === '1') {
		if (network) runNetwork(false); else runSite(false);
	}
})();
