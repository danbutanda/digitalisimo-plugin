/*
 * DIGITALÍSIMO Migración · botón Migrar. Avanza en el servidor la tarea del sitio (o, en la red, la de cada sitio con
 * peticiones a la administración de ese sitio) hasta terminar, y muestra su avance y su informe. Si se cierra la
 * pantalla, la tarea sigue con el WP-Cron del sitio y esta pantalla la retoma al volver.
 *
 * Barrera de red (V.6): un sitio que espera a la red responde `wait_network`. La pantalla deja de conducirlo y vuelve
 * a preguntar cada 15 s; la de red conduce los sitios de dos en dos hasta que terminan, esperan o no se alcanzan, pide
 * el paso de red (que libera la barrera) y conduce los liberados. Nunca hay más de una petición en vuelo por sitio, ni
 * más de una cada 5 s por sitio en espera.
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
	// Esperas (ms): entre pasos, con otra petición trabajando, entre sondeos de quien espera y del resumen de red.
	var STEP = 200, BUSY = 3000, POLL = 15000, MIN_SITE = 5000, PARALLEL = 2;

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

	function notice(message, kind) {
		var box = document.createElement('div');
		box.className = 'notice notice-' + (kind || 'error') + ' inline';
		var p = document.createElement('p');
		p.textContent = message;
		box.appendChild(p);
		return box;
	}

	function finish() {
		running = false;
		if (button) button.disabled = false;
	}

	// La tarea de un sitio, paso a paso, hasta que termina o espera a la red. Tolera fallos pasajeros (un 502, un tiempo
	// agotado) y, si se pidió crearla, lo sigue pidiendo hasta que el servidor confirma que existe.
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
				if (data.done || data.wait_network || (data.idle && !create)) return data;
				return wait(data.busy ? BUSY : STEP).then(step);
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
		function render(html) { status.innerHTML = html; }
		function go(first) {
			return drive(ajax, nonce, first, render).then(function (data) {
				// Esperando a la migración de la red: se vuelve a preguntar cada 15 s, sin bloquear la pantalla.
				if (data && data.wait_network) return wait(POLL).then(function () { return go(false); });
				return data;
			});
		}
		go(start).then(finish, function (error) {
			status.appendChild(notice('La migración se detuvo en esta pantalla (' + error.message + '). Sigue en segundo plano y continúa al volver a abrirla.'));
			finish();
		});
	}

	// Red: arranca (o retoma) la migración de red y conduce los sitios que tienen su tarea en marcha, de dos en dos.
	function runNetwork(start) {
		running = true;
		if (button) button.disabled = true;
		var unreachable = {};
		var inflight = {};
		var last = {};
		var boxes = {};
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
		function live(site) {
			if (!boxes[site.id]) {
				boxes[site.id] = document.createElement('div');
				boxes[site.id].className = 'digi-migrate__site';
			}
			return boxes[site.id];
		}
		function show(html) {
			status.innerHTML = html || '';
			Object.keys(boxes).forEach(function (id) { status.insertBefore(boxes[id], status.firstChild); });
		}
		// Fase 1: conduce hasta dos sitios a la vez hasta que terminan, esperan a la red o no se alcanzan.
		function driveSites(sites) {
			var queue = sites.slice();
			function worker() {
				var site = queue.shift();
				if (!site) return Promise.resolve();
				inflight[site.id] = true;
				last[site.id] = Date.now();
				var box = live(site);
				if (!box.parentNode) status.insertBefore(box, status.firstChild);
				return drive(site.ajax, site.nonce, false, function (html) {
					box.innerHTML = '';
					box.appendChild(siteRow(site, html));
				}).then(function () {}, function () {
					// El navegador no alcanza la administración de ese sitio (dominio propio): sigue con su WP-Cron.
					unreachable[site.id] = true;
				}).then(function () {
					inflight[site.id] = false;
					last[site.id] = Date.now();
					return worker();
				});
			}
			var workers = [];
			for (var i = 0; i < PARALLEL; i++) workers.push(worker());
			return Promise.all(workers);
		}
		function loop(data) {
			show(data.html);
			if (data.error) {
				status.appendChild(notice(data.error));
				return finish();
			}
			if (data.done || data.idle) return finish();
			var now = Date.now();
			var sites = (data.sites || []).filter(function (site) {
				return !site.waiting && !unreachable[site.id] && !inflight[site.id] && (!last[site.id] || now - last[site.id] >= MIN_SITE);
			});
			if (!sites.length) {
				// Lo que queda espera a la red o corre con el WP-Cron de cada sitio: el resumen (que libera la barrera) cada 15 s.
				return wait(POLL).then(function () { return summary(false); }).then(loop, fail);
			}
			// Fase 2 tras conducirlos: el paso de red libera la barrera si todos están listos; la fase 3 es la siguiente vuelta.
			return driveSites(sites).then(function () { return summary(false); }).then(loop, fail);
		}
		function fail(error) {
			status.appendChild(notice('La pantalla de red perdió la conexión (' + error.message + '). Cada sitio sigue en segundo plano; vuelve a abrir esta pantalla para ver el avance.'));
			finish();
		}
		summary(start).then(loop, fail);
	}

	// Análisis previo por trozos al abrir la herramienta del sitio (no es un botón): «Analizando… (N de M)».
	function analyze() {
		var box = document.querySelector('.digi-migrate__analysis');
		if (network || !box || box.getAttribute('data-done') === '1') return;
		var action = box.getAttribute('data-action');
		var failures = 0;
		function next() {
			return post(ajax, { action: action, _ajax_nonce: nonce }).then(function (data) {
				failures = 0;
				box.innerHTML = data.html || '';
				if (data.done) {
					box.setAttribute('data-done', '1');
					return data;
				}
				return wait(STEP).then(next);
			}, function () {
				failures++;
				if (failures > 2) return null;
				return wait(1500 * failures).then(next);
			});
		}
		next();
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
	analyze();
})();
