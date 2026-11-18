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

async function request(url, options = {}) {
	for (let hop = 0; hop < 10; hop++) {
		const headers = { ...(options.headers || {}), Cookie: [...jar].map(([k, v]) => `${k}=${v}`).join('; ') };
		const response = await fetch(url, { ...options, headers, redirect: 'manual' });
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
		last = data.stage || '';
		console.log(`${Math.round((Date.now() - started) / 1000)} s · ${last}`);
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
console.log(JSON.stringify({ failed: l.failed, error: l.error, deleted: l.deleted, kept: l.kept, verified: l.verified, theme: l.theme, verify: l.verify, downloads: result.downloads, blocked: result.blocked }, null, 1));
check(l.done && !l.failed, 'Sin fallos: ' + (l.error || ''));
check(l.backup && l.backup.ok && /^[a-f0-9]{64}$/.test(l.backup.sha256 || ''), 'Respaldo completo verificado antes de tocar nada.');
check(result.backups === 1, `Un solo respaldo de la migración (${result.backups}).`);
const zips = result.downloads.map((z) => z.replace(/-[0-9.]+\.zip$/, ''));
check(JSON.stringify(zips) === JSON.stringify(['digitalisimo-backups', 'digitalisimo-seo', 'digitalisimo-theme']), 'Se descargan una vez Backups, SEO y el tema, en ese orden: ' + zips.join(','));
check(result.blocked.length === 0, 'Ninguna petición sale del sitio: ' + result.blocked.join(', '));
for (const file of ['bdthemes-element-pack/bdthemes-element-pack.php', 'seo-by-rank-math/rank-math.php', 'updraftplus/updraftplus.php']) {
	check(!result.installed.includes(file), `Borrado: ${file}`);
}
for (const file of ['digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php', 'digitalisimo-migration/digitalisimo-migration.php']) {
	check(result.installed.includes(file), `Instalado: ${file}`);
}
check(!result.themes.includes('hello-elementor') && result.themes.includes('digitalisimo-theme'), 'Hello se reemplaza por el tema propio y se borra: ' + result.themes.join(','));
for (const [id, site] of Object.entries(result.sites)) {
	const active = network ? result.sitewide : site.active;
	for (const file of ['digitalisimo-backups/digitalisimo-backups.php', 'digitalisimo-elements/pro-elements.php', 'digitalisimo-seo/digitalisimo-integrations.php']) {
		check(active.includes(file), `Sitio ${id}: activo ${file}`);
	}
	check(site.stylesheet === 'digitalisimo-theme', `Sitio ${id}: tema propio activo (${site.stylesheet}).`);
	check(site.seo_title === 'Sobre el vivero familiar' && /macetas artesanales/.test(site.seo_desc), `Sitio ${id}: metas de Rank Math importadas (${site.seo_title} | ${site.seo_desc}).`);
	check(site.home === 200 && site.home_text && !site.fatal, `Sitio ${id}: la portada responde (${site.home}).`);
}
if (problems.length) {
	console.log('FALLA\n  - ' + problems.join('\n  - '));
	process.exit(1);
}
console.log(`ok · ${mode}`);
