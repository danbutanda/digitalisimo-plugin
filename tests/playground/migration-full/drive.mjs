// Conduce «Migrar» de DIGITALÍSIMO Migración en Playground como migrate.js (sólo Playground): sesión de administrador,
// el nonce de la pantalla, doble clic al empezar, cierre de la pantalla a mitad y reanudación, y en la red los pasos de
// cada sitio que la respuesta pide conducir. Al terminar lee check.php y comprueba el resultado.
// Uso: node drive.mjs <url-base> <site|network>
const [base, mode] = process.argv.slice(2);
const network = mode === 'network';
const jar = new Map();
const problems = [];
const check = (ok, message) => { if (!ok) problems.push(message); };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

import http from 'node:http';
// La red vive en http://playground.test (multisite no admite puerto): cada petición va a 127.0.0.1:PORT con su Host.
const PORT = process.env.PORT || '9620';
function raw(url, options) {
	const target = new URL(url);
	return new Promise((resolve, reject) => {
		const req = http.request({ host: '127.0.0.1', port: target.hostname === 'playground.test' ? PORT : (target.port || 80), path: target.pathname + target.search, method: options.method || 'GET', headers: { ...(options.headers || {}), Host: target.host } }, (res) => {
			const chunks = [];
			res.on('data', (c) => chunks.push(c));
			res.on('end', () => {
				const body = Buffer.concat(chunks).toString('utf8');
				const cookies = [].concat(res.headers['set-cookie'] || []);
				resolve({ status: res.statusCode, headers: { get: (k) => res.headers[k.toLowerCase()] || null, getSetCookie: () => cookies }, text: async () => body });
			});
		});
		req.setTimeout(1500000, () => req.destroy(new Error('tiempo agotado')));
		req.on('error', reject);
		if (options.body) req.write(String(options.body));
		req.end();
	});
}

async function request(url, options = {}) {
	for (let hop = 0; hop < 10; hop++) {
		const headers = { ...(options.headers || {}), Cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; ') };
		const response = await raw(url, { ...options, headers });
		for (const line of response.headers.getSetCookie()) {
			const [pair] = line.split(';');
			const at = pair.indexOf('=');
			jar.set(pair.slice(0, at).trim(), pair.slice(at + 1).trim());
		}
		if (response.status >= 300 && response.status < 400 && response.headers.get('location')) {
			url = new URL(response.headers.get('location'), url).href;
			options = { method: 'GET' };
			continue;
		}
		return response;
	}
	throw new Error('demasiadas redirecciones: ' + url);
}

async function step(url, data) {
	const body = new URLSearchParams(data);
	const response = await request(url, { method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
	const text = await response.text();
	try {
		return JSON.parse(text);
	} catch (e) {
		return { success: false, data: `respuesta no válida (${response.status}): ${text.slice(0, 200)}` };
	}
}

const started = Date.now();
const screen = network ? `${base}/wp-admin/network/settings.php?page=digitalisimo-ep-migration` : `${base}/wp-admin/tools.php?page=digitalisimo-ep-migration`;
let html = await (await request(screen)).text();
if (/name="log"/.test(html)) {
	await request(`${base}/wp-login.php`, { method: 'POST', body: new URLSearchParams({ log: 'admin', pwd: 'password', 'wp-submit': 'Acceder', testcookie: '1' }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
	html = await (await request(screen)).text();
}
const nonce = (html.match(/id="digi-migrate"[^>]*data-nonce="([^"]+)"/) || [])[1];
check(!!nonce, 'La pantalla de Migrar (sin Elements activo) pinta el botón con su nonce.');
check(/Lo que hará Migrar/.test(html) && /Rank Math/.test(html) && /UpdraftPlus/.test(html) && /Hello Elementor/.test(html), 'El análisis previo dice lo que se instala, importa, reemplaza y borra.');
check(!/necesita DIGITALÍSIMO Elements activo/.test(html), 'Sin el aviso «necesita Elements».');
const ajax = `${base}/wp-admin/admin-ajax.php`;
const common = { action: 'digitalisimo_migration_step', _ajax_nonce: nonce || '' };
if (network) common.network = '1';

// Doble clic: dos peticiones de inicio a la vez, con clics distintos.
const first = await Promise.all([step(ajax, { ...common, start: '1', run: 'clicuno' }), step(ajax, { ...common, start: '1', run: 'clicdos' })]);
console.log('inicio:', first.map((r) => r.success ? `${r.data.stage || ''}${r.data.busy ? ' (ocupado)' : ''}` : r.data).join(' | '));
check(first.every((r) => r.success), 'Las dos peticiones del doble clic responden: ' + first.map((r) => r.success ? 'ok' : r.data).join(' | '));

let data = first[0].data || {};
let last = '';
let closed = false;
let steps = 0;
while (!(data && data.done) && Date.now() - started < 40 * 60 * 1000) {
	if ((data.stage || '') !== last) {
		globalThis.since = Date.now();
		globalThis.dumped = false;
	} else if (!globalThis.dumped && Date.now() - (globalThis.since || Date.now()) > 120000) {
		// Una etapa que no avanza en 2 minutos: el estado de la tarea, para diagnosticar.
		globalThis.dumped = true;
		const job = JSON.parse(await (await request(`${base}/wp-content/probe/check.php`)).text()).job || {};
		console.log('ESTADO', JSON.stringify({ stage: job.stage, cursor: job.cursor, attempts: job.attempts, imports: job.imports, notes: job.notes, error: job.error, sites: job.sites }));
	}
	if ((data.stage || '') !== last || Date.now() - (globalThis.shown || 0) > 60000) {
		last = data.stage || '';
		globalThis.shown = Date.now();
		const text = String(data.html || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 140);
		console.log(`${Math.round((Date.now() - started) / 1000)} s · ${last} · ${text}`);
	}
	// Se cierra la pantalla a mitad (al empezar la importación) y se vuelve a abrir: la tarea sigue donde quedó.
	if (!closed && ['import', 'retire', 'theme'].includes(last)) {
		closed = true;
		await sleep(3000);
		const again = await (await request(screen)).text();
		check(/data-running="1"/.test(again), 'Al volver a abrir la pantalla, la migración figura en marcha.');
		const second = await step(ajax, { ...common, start: '1', run: 'clictres' });
		check(second.success, 'Un clic más a mitad no falla.');
	}
	if (network && data.sites && data.sites.length) {
		for (const site of data.sites) {
			let s = { stage: 'x' };
			for (let i = 0; i < 400 && !(s.done || s.wait_network || (s.idle && !s.stage)); i++) {
				const r = await step(site.ajax, { action: 'digitalisimo_migration_step', _ajax_nonce: site.nonce, start: '' });
				if (!r.success) { problems.push(`paso del sitio ${site.name}: ${r.data}`); break; }
				s = r.data;
				if (s.busy) await sleep(2000);
			}
		}
	}
	const r = await step(ajax, { ...common, start: '' });
	steps++;
	if (!r.success) {
		problems.push('paso rechazado: ' + r.data);
		break;
	}
	data = r.data;
	if (data.busy || data.pause) await sleep(data.busy ? 2000 : 300);
}
console.log(`${Math.round((Date.now() - started) / 1000)} s · terminó tras ${steps} pasos`);
check(data && data.done, 'La migración terminó.');

const result = JSON.parse(await (await request(`${base}/wp-content/probe/check.php`)).text());
const l = result.last || {};
console.log(JSON.stringify({ failed: l.failed, error: l.error, deleted: l.deleted, kept: l.kept, verified: l.verified, theme: l.theme, verify: l.verify, downloads: result.downloads, blocked: [...new Set(result.blocked.map((url) => url.replace(/\?.*$/, '')))] }, null, 1));
check(l.done && !l.failed, 'Sin fallos: ' + (l.error || ''));
check(l.backup && l.backup.ok && /^[a-f0-9]{64}$/.test(l.backup.sha256 || ''), 'Respaldo completo verificado antes de tocar nada.');
check(result.backups === 1, `Un solo respaldo de la migración (${result.backups}).`);
const zips = result.downloads.map((z) => z.replace(/-[0-9.]+\.zip$/, ''));
check(JSON.stringify(zips) === JSON.stringify(['digitalisimo-backups', 'digitalisimo-elements', 'digitalisimo-seo', 'digitalisimo-theme']), 'Se descargan una vez Backups, Elements, SEO y el tema, en ese orden: ' + zips.join(','));
// Migración nunca consulta GitHub con el índice en caché (las demás peticiones bloqueadas se listan arriba).
check(!result.blocked.some((url) => /github\.com/.test(url)), 'Migración no consulta GitHub: ' + result.blocked.filter((url) => /github/.test(url)).join(', '));
for (const file of ['bdthemes-element-pack/bdthemes-element-pack.php', 'seo-by-rank-math/rank-math.php', 'updraftplus/updraftplus.php']) {
	check(!result.installed.includes(file), `Borrado: ${file}`);
}
for (const file of ['digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-migration/digitalisimo-migration.php']) {
	check(result.installed.includes(file), `Instalado: ${file}`);
}
check(!result.themes.includes('hello-elementor') && result.themes.includes('digitalisimo-theme'), 'Hello se reemplaza por el tema propio y se borra: ' + result.themes.join(','));
for (const [id, site] of Object.entries(result.sites)) {
	const active = Object.values(network ? result.sitewide : site.active);
	for (const file of ['digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php']) {
		check(active.includes(file), `Sitio ${id}: activo ${file}`);
	}
	check(site.stylesheet === 'digitalisimo-theme', `Sitio ${id}: tema propio activo (${site.stylesheet}).`);
	check(site.seo_title === 'Sobre el vivero familiar' && /macetas artesanales/.test(site.seo_desc), `Sitio ${id}: metas de Rank Math importadas (${site.seo_title} | ${site.seo_desc}).`);
	check(site.home === 200 && site.home_text && !site.fatal, `Sitio ${id}: la portada responde (${site.home}).`);
}
// La pantalla final (pedido del propietario del 2026-11-19): el resultado arriba en pocas líneas, «Necesita tu atención»
// con la acción y el enlace al editor, el detalle plegado y agregado, y el análisis previo plegado debajo.
const finalScreen = await (await request(screen)).text();
const statusAt = finalScreen.indexOf('digi-migrate__status');
const foldAt = finalScreen.indexOf('<details class="digi-migrate__before"');
const report = statusAt > -1 && foldAt > statusAt ? finalScreen.slice(statusAt, foldAt) : '';
/** Lo que se ve sin desplegar: cada <details> cerrado se queda en su <summary>. */
function visibleLines(html) {
	let prev = null;
	while (prev !== html) {
		prev = html;
		html = html.replace(/<details(?![^>]*\sopen)[^>]*>\s*<summary>([^<]*)<\/summary>(?:(?!<details)[\s\S])*?<\/details>/, '<p>$1</p>');
	}
	return html.replace(/<\/(p|li|tr|h[1-6]|div|summary|caption)>/g, '\n').replace(/<[^>]+>/g, ' ').split('\n').map((l) => l.trim()).filter(Boolean);
}
const lines = visibleLines(report);
console.log(`pantalla final: ${lines.length} líneas visibles\n  ${lines.join('\n  ')}`);
check(report && (report.match(/Migración completada/g) || []).length === 1, 'La pantalla final dice una sola vez «Migración completada».');
check(foldAt > -1 && !/^<details class="digi-migrate__before"[^>]*\sopen/.test(finalScreen.slice(foldAt, foldAt + 300)) && /<summary>Ver el análisis/.test(finalScreen.slice(foldAt, foldAt + 400)), 'El análisis previo queda plegado bajo el informe.');
check(lines.length > 0 && lines.length < 40, `Lo visible sin desplegar cabe en menos de 40 líneas (${lines.length}).`);
check(!/había nada que/.test(report), 'Con conversiones, no dice «no había nada que migrar».');
check(/<details class="digi-migrate__detail"><summary>Detalle<\/summary>/.test(report) && report.indexOf('Necesita tu atención') < report.indexOf('<details class="digi-migrate__detail">'), 'Lo que necesita atención va antes del detalle plegado.');
if (!network) {
	const site = Object.values(result.sites)[0] || {};
	check(site.quote_type === 'digitalisimo-blockquote', `La Cita de PRO pasó a la propia (${site.quote_type}).`);
	check(/blockquote → digitalisimo-blockquote: 1 en 1 documento/.test(report), 'El detalle agrega los widgets convertidos por tipo.');
	check(JSON.stringify(site.cart_types) === JSON.stringify(['woocommerce-cart', 'blockquote']), `El Carrito sin WooCommerce se queda como estaba (${JSON.stringify(site.cart_types)}).`);
	check(new RegExp(`post\\.php\\?post=${site.cart}&(amp;)?action=elementor`).test(report) && /WooCommerce/.test(report) && /vuelve a pulsar Migrar/.test(report), 'El Carrito sin WooCommerce va en «Necesita tu atención», con la acción y el enlace a su editor.');
} else {
	check(/Necesita tu atención/.test(report) && /Sitios con asuntos por revisar/.test(report) && /Informe del sitio|tools\.php\?page=digitalisimo-ep-migration/.test(report), 'En la red, los sitios con asuntos van en «Necesita tu atención» con el enlace a su informe.');
}

if (problems.length) {
	console.log('FALLA\n  - ' + problems.join('\n  - '));
	process.exit(1);
}
console.log(`ok · ${mode}`);
