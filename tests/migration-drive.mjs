/*
 * F8 · La pantalla de Migrar con la barrera de red (V.6): `assets/js/migrate.js` en `vm`, con `fetch` simulado y un
 * reloj virtual. Comprueba que `wait_network` termina drive() y la pantalla del sitio vuelve a preguntar cada 15 s;
 * que la de red conduce los sitios de dos en dos, sin más de una petición en vuelo por sitio ni más de una cada 5 s por
 * sitio, no conduce a los que esperan, pide el paso de red (que libera) y conduce después a los liberados (tres fases);
 * que el resumen se pide cada 15 s cuando no hay nada que conducir; y que el análisis previo avanza por trozos sin botón.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../digitalisimo-migration/assets/js/migrate.js', import.meta.url), 'utf8');
let checks = 0;
function check(ok, message) {
	checks++;
	assert.ok(ok, message);
}

/** Elementos mínimos del DOM. */
function element(tag) {
	return {
		tag,
		attrs: {},
		children: [],
		className: '',
		textContent: '',
		disabled: false,
		listeners: {},
		_html: '',
		get innerHTML() { return this._html; },
		set innerHTML(v) { this._html = String(v); this.children = []; },
		get firstChild() { return this.children[0] || null; },
		getAttribute(name) { return Object.prototype.hasOwnProperty.call(this.attrs, name) ? this.attrs[name] : null; },
		setAttribute(name, value) { this.attrs[name] = String(value); },
		appendChild(child) { this.children.push(child); child.parentNode = this; return child; },
		insertBefore(child, ref) {
			const i = ref ? this.children.indexOf(ref) : -1;
			if (i < 0) this.children.push(child); else this.children.splice(i, 0, child);
			child.parentNode = this;
			return child;
		},
		querySelector(sel) { return this.query ? this.query[sel] || null : null; },
		addEventListener(type, fn) { this.listeners[type] = fn; },
		parentNode: null,
	};
}

/**
 * Entorno: reloj virtual, temporizadores ordenados y `fetch` que responde con `handler(url, body)` tras `latency` ms.
 * Registra cada petición con su hora, su sitio y las que había en vuelo para ese sitio.
 */
function environment({ network, running = '0', handler, analysis = null, latency = 100 }) {
	let now = 1_000_000;
	let seq = 0;
	const timers = [];
	const log = [];
	const inflight = {};
	let maxInflight = 0;
	const root = element('div');
	root.attrs = { 'data-network': network ? '1' : '0', 'data-ajax': 'https://red.test/wp-admin/admin-ajax.php', 'data-nonce': 'n', 'data-running': running };
	const button = element('button');
	const status = element('div');
	root.query = { '.digi-migrate__button': button, '.digi-migrate__status': status };
	const document = {
		getElementById: (id) => (id === 'digi-migrate' ? root : null),
		querySelector: (sel) => (sel === '.digi-migrate__analysis' ? analysis : null),
		createElement: (tag) => element(tag),
		createTextNode: (text) => ({ textContent: text }),
	};
	function setTimeout(fn, ms) {
		timers.push({ at: now + Math.max(0, ms || 0), fn, seq: seq++ });
		return seq;
	}
	function fetch(url, opts) {
		const body = Object.fromEntries(new URLSearchParams(opts.body.toString()));
		inflight[url] = (inflight[url] || 0) + 1;
		const total = Object.values(inflight).reduce((a, b) => a + b, 0);
		maxInflight = Math.max(maxInflight, total);
		log.push({ at: now, url, body, inflight: inflight[url], total });
		return new Promise((resolve) => {
			setTimeout(() => {
				inflight[url]--;
				let data;
				try {
					data = handler(url, body, now);
				} catch (error) {
					resolve({ status: 500, json: () => Promise.reject(new Error('500')) });
					return;
				}
				resolve({ status: 200, json: () => Promise.resolve({ success: true, data }) });
			}, latency);
		});
	}
	const context = {
		window: { fetch, URLSearchParams, location: { href: 'https://red.test/wp-admin/network/settings.php', origin: 'https://red.test' } },
		document,
		fetch,
		URLSearchParams,
		URL,
		Promise,
		setTimeout,
		Date: { now: () => now },
		Math,
		Object,
		Array,
		String,
		Error,
	};
	context.window.document = document;
	vm.createContext(context);
	return {
		context,
		button,
		status,
		log,
		get now() { return now; },
		get maxInflight() { return maxInflight; },
		start() { vm.runInContext(source, context); },
		/** Avanza el reloj hasta `limit` ms virtuales o hasta que no queden temporizadores. */
		async run(limit) {
			const end = now + limit;
			for (let guard = 0; guard < 100000; guard++) {
				await new Promise((r) => setImmediate(r));
				timers.sort((a, b) => a.at - b.at || a.seq - b.seq);
				const next = timers[0];
				if (!next || next.at > end) {
					now = end;
					break;
				}
				timers.shift();
				now = next.at;
				next.fn();
			}
			await new Promise((r) => setImmediate(r));
		},
	};
}

/* ================================================================ Sitio: wait_network termina drive() */
{
	let calls = 0;
	const env = environment({
		network: false,
		handler: (url, body) => {
			calls++;
			if (calls <= 2) return { stage: 'platform_prepare', html: 'Preparando' };
			if (calls <= 4) return { stage: 'deactivate', wait_network: true, pause: true, html: 'Esperando a la red' };
			if (calls === 5) return { stage: 'convert', html: 'Convirtiendo' };
			return { stage: 'finish', done: true, html: 'Migración completada' };
		},
	});
	env.start();
	env.button.listeners.click();
	check(env.button.disabled === true, 'El botón se deshabilita mientras dura.');
	await env.run(1000);
	const waits = env.log.filter((r) => r.body.action === 'digitalisimo_migration_step');
	check(waits.length === 3, 'drive() se detiene al recibir wait_network (3 pasos): ' + waits.length);
	check(env.status.innerHTML === 'Esperando a la red', 'Y pinta el aviso de espera.');
	check(waits[0].body.start === '1' && waits[1].body.start === '', 'Sólo el primer paso pide crear la tarea.');
	await env.run(14000);
	check(env.log.length === 3, 'Durante la espera no pregunta antes de 15 s.');
	await env.run(1500);
	check(env.log.length === 4, 'A los 15 s vuelve a preguntar.');
	const gap = env.log[3].at - env.log[2].at;
	check(gap >= 15000, 'Con 15 s entre sondeos: ' + gap);
	await env.run(16000);
	await env.run(5000);
	check(env.status.innerHTML === 'Migración completada', 'Liberado, sigue hasta terminar.');
	check(env.button.disabled === false, 'Y vuelve a habilitar el botón.');
	check(env.log.every((r) => r.inflight === 1), 'Nunca hay dos peticiones a la vez del sitio.');
}

/* ================================================================ Red: tres fases, dos sitios a la vez */
{
	const sites = [1, 2, 3].map((id) => ({ id, name: 'Sitio ' + id, url: 'https://s' + id + '.test/', ajax: 'https://s' + id + '.test/wp-admin/admin-ajax.php', nonce: 'n' + id }));
	const state = { released: false, steps: { 1: 0, 2: 0, 3: 0 }, done: { 1: false, 2: false, 3: false }, summaries: 0, releaseAt: null };
	const siteOf = (url) => Number((url.match(/s(\d)\.test/) || [])[1] || 0);
	function summary() {
		const running = sites.filter((s) => !state.done[s.id]).map((s) => ({ ...s, waiting: !state.released && state.steps[s.id] >= 2 }));
		return { sites: running, done: running.length === 0, barrier: true, released: state.released, html: 'Red' };
	}
	const env = environment({
		network: true,
		handler: (url, body, now) => {
			if (body.network === '1') {
				state.summaries++;
				// El paso de red libera cuando los tres esperan.
				if (!state.released && [1, 2, 3].every((id) => state.steps[id] >= 2)) {
					state.released = true;
					state.releaseAt = now;
				}
				return summary();
			}
			const id = siteOf(url);
			state.steps[id]++;
			if (!state.released) {
				if (state.steps[id] < 2) return { stage: 'platform_prepare', html: 'P' + id };
				return { stage: 'deactivate', wait_network: true, html: 'W' + id };
			}
			if (state.steps[id] < 5) return { stage: 'convert', html: 'C' + id };
			state.done[id] = true;
			return { stage: 'finish', done: true, html: 'D' + id };
		},
	});
	env.start();
	env.button.listeners.click();
	await env.run(200000);
	const siteReqs = env.log.filter((r) => r.body.network !== '1');
	const netReqs = env.log.filter((r) => r.body.network === '1');
	check(netReqs[0].body.start === '1', 'La red arranca con el paso de red (start).');
	check(state.released, 'La barrera se libera desde el paso de red.');
	check([1, 2, 3].every((id) => state.done[id]), 'Los tres sitios terminan.');
	check(env.button.disabled === false, 'Y la pantalla termina.');
	// Fase 1: nunca más de dos sitios en vuelo, ni dos peticiones del mismo sitio.
	const maxSites = Math.max(...siteReqs.map((r) => siteReqs.filter((o) => o.at <= r.at && o.at + 100 > r.at && o.url !== r.url).length + 1));
	check(maxSites <= 2, 'Nunca más de dos sitios conducidos a la vez: ' + maxSites);
	check(siteReqs.some((r) => siteReqs.some((o) => o.url !== r.url && Math.abs(o.at - r.at) < 100)), 'Dos sitios se conducen en paralelo.');
	check(siteReqs.every((r) => r.inflight === 1), 'Nunca más de una petición en vuelo por sitio.');
	// Fase 1 → 2: antes de liberar, cada sitio sólo hasta su espera.
	const before = siteReqs.filter((r) => r.at < state.releaseAt);
	for (const id of [1, 2, 3]) {
		const own = before.filter((r) => siteOf(r.url) === id);
		check(own.length === 2, 'Antes de liberar, el sitio ' + id + ' se conduce hasta su espera (2 pasos): ' + own.length);
	}
	// Fase 2: el paso de red libera tras conducirlos.
	const releaseReq = netReqs.find((r) => r.at + 100 === state.releaseAt || r.at === state.releaseAt - 100);
	check(Boolean(releaseReq), 'La liberación la hace un paso de red.');
	const lastBefore = Math.max(...before.map((r) => r.at));
	check(state.releaseAt > lastBefore, 'Después de conducir a todos hasta su espera.');
	// Fase 3: los liberados se conducen hasta el final.
	const after = siteReqs.filter((r) => r.at >= state.releaseAt);
	for (const id of [1, 2, 3]) {
		check(after.filter((r) => siteOf(r.url) === id).length === 3, 'Tras liberar, el sitio ' + id + ' se conduce hasta terminar.');
	}
	// Ningún sitio en espera recibe peticiones.
	const waitingHits = siteReqs.filter((r) => r.at < state.releaseAt && r.body.action === 'digitalisimo_migration_step').length;
	check(waitingHits === 6, 'Un sitio que espera no se conduce (sólo sus dos pasos): ' + waitingHits);
	// Cada sitio: al menos 5 s entre el final de una conducción y la siguiente.
	for (const id of [1, 2, 3]) {
		const own = siteReqs.filter((r) => siteOf(r.url) === id);
		const waitEnd = own.filter((r) => r.at < state.releaseAt).pop();
		const nextStart = own.find((r) => r.at >= state.releaseAt);
		check(nextStart.at - waitEnd.at >= 5000, 'Al menos 5 s entre sondeos del sitio ' + id + ' en espera: ' + (nextStart.at - waitEnd.at));
	}
}

/* ================================================================ Red: nada que conducir → resumen cada 15 s */
{
	let summaries = 0;
	const env = environment({
		network: true,
		running: '1',
		handler: (url, body) => {
			summaries++;
			// Un sitio en espera (sin pantalla que lo conduzca) y luego la red termina con el WP-Cron.
			if (summaries < 4) return { sites: [{ id: 1, name: 'S1', url: 'https://s1.test/', ajax: 'https://s1.test/wp-admin/admin-ajax.php', nonce: 'n', waiting: true }], html: 'R' };
			return { sites: [], done: true, html: 'Hecho' };
		},
	});
	env.start();
	await env.run(60000);
	const times = env.log.map((r) => r.at);
	check(env.log.every((r) => r.body.network === '1'), 'Con el único sitio en espera, sólo se pide el resumen de red.');
	check(times.slice(1).every((t, i) => t - times[i] >= 15000), 'Cada 15 s: ' + times.slice(1).map((t, i) => t - times[i]).join(', '));
	check(env.log[0].body.start === '', 'Al abrir una red en marcha, no arranca otra.');
	check(env.status.innerHTML === 'Hecho', 'Termina cuando el resumen lo dice.');
}

/* ================================================================ Red: un sitio inalcanzable no bloquea */
{
	let net = 0;
	const env = environment({
		network: true,
		handler: (url, body) => {
			if (body.network === '1') {
				net++;
				return net < 3 ? { sites: [{ id: 9, name: 'Lejos', url: 'https://s9.test/', ajax: 'https://s9.test/wp-admin/admin-ajax.php', nonce: 'n' }], html: 'R' } : { sites: [], done: true, html: 'Hecho' };
			}
			throw new Error('inalcanzable');
		},
	});
	env.start();
	env.button.listeners.click();
	await env.run(120000);
	const siteHits = env.log.filter((r) => r.body.network !== '1').length;
	check(siteHits === 5, 'Un sitio que no responde se reintenta 5 veces y se deja a su WP-Cron: ' + siteHits);
	check(env.status.innerHTML === 'Hecho', 'Y la red sigue hasta terminar.');
}

/* ================================================================ Análisis previo por trozos (sin botón) */
{
	const box = element('div');
	box.attrs = { 'data-action': 'digitalisimo_migration_analyze', 'data-done': '0' };
	let n = 0;
	const env = environment({
		network: false,
		analysis: box,
		handler: (url, body) => {
			if (body.action === 'digitalisimo_migration_analyze') {
				n++;
				return n < 3 ? { done: false, processed: n * 10, total: 30, html: 'Analizando… (' + n * 10 + ' de 30)' } : { done: true, processed: 30, total: 30, html: 'Lo que hará Migrar' };
			}
			return { idle: true, html: '' };
		},
	});
	env.start();
	await env.run(5000);
	const hits = env.log.filter((r) => r.body.action === 'digitalisimo_migration_analyze');
	check(hits.length === 3 && hits.every((r) => r.body._ajax_nonce === 'n'), 'El análisis pide trozos con el nonce de la herramienta hasta terminar.');
	check(box.innerHTML === 'Lo que hará Migrar' && box.getAttribute('data-done') === '1', 'Y pinta el resultado.');
	check(env.log.every((r) => r.body.action === 'digitalisimo_migration_analyze'), 'Sin pulsar nada, no arranca ninguna migración.');
}

console.log(`DIGITALÍSIMO Migración: pantalla con la barrera de red: ${checks} comprobaciones en verde.`);
if (checks < 30) {
	throw new Error(`Se esperaban al menos 30 comprobaciones (${checks}).`);
}
