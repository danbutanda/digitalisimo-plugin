// Conductor de seo-admin-perf.sh: mide el escritorio con y sin DIGITALÍSIMO SEO, IA Tools y Hosting en el mismo sitio.
// Uso: node measure.mjs <puerto> <host> <dir-de-la-sonda> <dir-de-salida>
// Variables: LOADS (cargas medidas por pantalla y conjunto; 7), SETS («none seo ia hosting all»), CHROME (1: consola y
// editor en Chrome; necesita PUPPETEER_PATH), ONLY (pantallas separadas por comas).
// Cada pantalla se pide intercalando los conjuntos (none, seo, ia, hosting, all, none, …) para que el ruido de la máquina
// caiga igual en todos; los números salen del lado del servidor (la sonda), no del reloj del cliente.
import http from 'node:http';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { pathToFileURL } from 'node:url';

const [port, host, probeDir, outDir] = process.argv.slice(2);
const LOADS = Number(process.env.LOADS || 7);
const SET_NAMES = (process.env.SETS || 'none seo ia hosting all').split(/\s+/).filter(Boolean);
const ONLY = (process.env.ONLY || '').split(',').filter(Boolean);
const PLUGINS = { seo: 'digitalisimo-seo/digitalisimo-integrations.php', ia: 'digitalisimo.chatbot/digitalisimo-chatbot.php', hosting: 'digitalisimo-hosting/digitalisimo-hosting.php' };
const SET_VALUE = { none: 'none', all: 'all', seo: PLUGINS.seo, ia: PLUGINS.ia, hosting: PLUGINS.hosting };
const setup = JSON.parse(readFileSync(`${probeDir}/setup.json`, 'utf8'));
const token = readFileSync(`${probeDir}/token.txt`, 'utf8').trim();
const LOG = `${probeDir}/log.jsonl`;
mkdirSync(outDir, { recursive: true });
const multisite = !!setup.multisite;
const main = setup.sites['1'];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let seq = 0;
const jar = {};

function request(method, path, { headers = {}, body, set } = {}) {
  return new Promise((resolve, reject) => {
    const id = `r${++seq}`;
    const cookie = Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ');
    const all = { Host: host, 'X-Digi-AP-Id': id, ...(cookie ? { Cookie: cookie } : {}), ...(set ? { 'X-Digi-AP-Set': SET_VALUE[set] } : {}), ...headers };
    let data = body;
    if (body !== undefined && typeof body !== 'string' && !Buffer.isBuffer(body)) { data = JSON.stringify(body); all['Content-Type'] = all['Content-Type'] || 'application/json'; }
    if (data !== undefined) all['Content-Length'] = Buffer.byteLength(data);
    const started = Date.now();
    const req = http.request({ host: '127.0.0.1', port: Number(port), method, path, headers: all, timeout: 600_000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        for (const c of res.headers['set-cookie'] || []) { const [pair] = c.split(';'); const i = pair.indexOf('='); const k = pair.slice(0, i).trim(); const v = pair.slice(i + 1).trim(); if (/expires=Thu, 01[- ]Jan[- ]1970/i.test(c) || v === 'deleted') delete jar[k]; else jar[k] = v; }
        resolve({ id, status: res.statusCode, headers: res.headers, text: Buffer.concat(chunks).toString('utf8'), wall: Date.now() - started });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado: ${method} ${path}`)));
    if (data !== undefined) req.write(data);
    req.end();
  });
}

function logLines() {
  if (!existsSync(LOG)) return [];
  return readFileSync(LOG, 'utf8').split('\n').filter(Boolean).map((l) => { try { return JSON.parse(l); } catch { return null; } }).filter(Boolean);
}
async function waitLog(id, tries = 40) {
  for (let i = 0; i < tries; i++) { const hit = logLines().find((l) => l.id === id); if (hit) return hit; await sleep(150); }
  return null;
}
const median = (xs) => { const s = xs.filter((x) => Number.isFinite(x)).sort((a, b) => a - b); if (!s.length) return null; const m = Math.floor(s.length / 2); return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2; };
const form = (o) => Object.entries(o).map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(v)}`).join('&');
const state = async (extra = '', prefix = '') => JSON.parse((await request('GET', `${prefix}/?digi_ap_state=${extra || 1}`, { headers: { 'X-Digi-AP-Token': token } })).text);

async function login(prefix = '') {
  jar.wordpress_test_cookie = 'WP%20Cookie%20check';
  const r = await request('POST', `${prefix}/wp-login.php`, { headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: form({ log: 'admin', pwd: 'password', 'wp-submit': 'Log In', testcookie: '1', redirect_to: `${prefix}/wp-admin/` }) });
  if (r.status !== 302 || !Object.keys(jar).some((k) => k.startsWith('wordpress_logged_in'))) throw new Error(`no se pudo iniciar sesión (${r.status})`);
}

// ---------------------------------------------------------------------------------------------------------------------
await login();
const nonceRest = (await request('GET', '/wp-admin/admin-ajax.php?action=rest-nonce')).text.trim();
const dash = await request('GET', '/wp-admin/index.php');
const hb = (dash.text.match(/heartbeatSettings\s*=\s*\{[^}]*"nonce":"([a-f0-9]+)"/) || [])[1] || '';
if (!/^[a-f0-9]{10}$/.test(nonceRest)) throw new Error('sin nonce de la REST');
const P = main.post, PG = main.page;
const IMG = Buffer.from(readFileSync(`${probeDir}/upload.jpg`));
const before = JSON.parse(readFileSync(`${probeDir}/before.json`, 'utf8'));

const SCREENS = [
  { key: 'escritorio', path: '/wp-admin/index.php' },
  { key: 'entradas', path: '/wp-admin/edit.php' },
  { key: 'editar-entrada', path: `/wp-admin/post.php?post=${P}&action=edit` },
  { key: 'rest-entrada', path: `/wp-json/wp/v2/posts/${P}?context=edit&_locale=user`, headers: { 'X-WP-Nonce': nonceRest } },
  { key: 'rest-guardar', method: 'POST', path: `/wp-json/wp/v2/posts/${P}?_locale=user`, headers: { 'X-WP-Nonce': nonceRest }, body: () => ({ title: `Título medido ${seq}`, content: undefined }) },
  { key: 'paginas', path: '/wp-admin/edit.php?post_type=page' },
  { key: 'editar-pagina', path: `/wp-admin/post.php?post=${PG}&action=edit` },
  { key: 'medios-lista', path: '/wp-admin/upload.php?mode=list' },
  { key: 'medios-cuadricula-ajax', method: 'POST', path: '/wp-admin/admin-ajax.php', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: () => form({ action: 'query-attachments', 'query[orderby]': 'date', 'query[order]': 'DESC', 'query[posts_per_page]': 80, 'query[paged]': 1 }) },
  { key: 'subir-medio', method: 'POST', path: '/wp-json/wp/v2/media', headers: { 'X-WP-Nonce': nonceRest, 'Content-Type': 'image/jpeg', 'Content-Disposition': 'attachment; filename="medido.jpg"' }, body: () => IMG },
  { key: 'plugins', path: '/wp-admin/plugins.php' },
  { key: 'usuarios', path: '/wp-admin/users.php' },
  { key: 'ajustes-generales', path: '/wp-admin/options-general.php' },
  { key: 'actualizaciones', path: '/wp-admin/update-core.php' },
  { key: 'heartbeat', method: 'POST', path: '/wp-admin/admin-ajax.php', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: () => form({ action: 'heartbeat', _nonce: hb, screen_id: 'dashboard', interval: 60, has_focus: 'false' }) },
  { key: 'heartbeat-editor', method: 'POST', path: '/wp-admin/admin-ajax.php', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: () => form({ action: 'heartbeat', _nonce: hb, screen_id: 'post', interval: 15, has_focus: 'true', 'data[wp-refresh-post-lock][post_id]': P, 'data[wp-refresh-post-lock][lock]': '' }) },
];
if (multisite) {
  SCREENS.push(
    { key: 'red-escritorio', path: '/wp-admin/network/index.php' },
    { key: 'red-sitios', path: '/wp-admin/network/sites.php' },
    { key: 'red-plugins', path: '/wp-admin/network/plugins.php' },
    { key: 'red-ajustes', path: '/wp-admin/network/settings.php' },
    { key: 'red-actualizaciones', path: '/wp-admin/network/update-core.php' },
    { key: 'subsitio-escritorio', path: '/uno/wp-admin/index.php' },
    { key: 'subsitio-entradas', path: '/uno/wp-admin/edit.php' },
  );
}
const screens = ONLY.length ? SCREENS.filter((s) => ONLY.includes(s.key)) : SCREENS;

async function hit(screen, set, extraHeaders = {}) {
  const body = typeof screen.body === 'function' ? screen.body() : screen.body;
  const r = await request(screen.method || 'GET', screen.path, { headers: { ...(screen.headers || {}), ...extraHeaders }, body, set });
  const line = await waitLog(r.id);
  return { status: r.status, wall: r.wall, bytes: r.text.length, line, fatal: /Fatal error|There has been a critical error|Ha habido un error crítico/i.test(r.text) };
}

const results = { setup, sets: SET_NAMES, loads: LOADS, screens: {}, own: {}, cold: null, expired: {}, cron: {}, http: [], errors: [], chrome: null };

// Primera carga con todo activo tras la activación (migraciones y trabajos de una sola vez).
results.cold = await hit({ path: '/wp-admin/index.php' }, 'all');
results.cold = { ms: results.cold.line?.ms, queries: results.cold.line?.queries, http: results.cold.line?.http, errors: results.cold.line?.errors };
// Calentamiento: una carga por pantalla y conjunto (cachés de opcode/objetos de la petición, transitorios del núcleo).
for (const screen of screens) for (const set of SET_NAMES) await hit(screen, set);

for (const screen of screens) {
  const rows = Object.fromEntries(SET_NAMES.map((s) => [s, []]));
  for (let i = 0; i < LOADS; i++) for (const set of SET_NAMES) rows[set].push(await hit(screen, set));
  const out = {};
  for (const set of SET_NAMES) {
    const lines = rows[set].map((r) => r.line).filter(Boolean);
    out[set] = {
      status: [...new Set(rows[set].map((r) => r.status))],
      fatal: rows[set].some((r) => r.fatal),
      ms: median(lines.map((l) => l.ms)), queries: median(lines.map((l) => l.queries)), peak_kb: median(lines.map((l) => l.peak_kb)), bytes: median(rows[set].map((r) => r.bytes)),
      marks: Object.fromEntries(Object.keys(lines[0]?.marks || {}).map((k) => [k, median(lines.map((l) => l.marks?.[k]))])),
      load: Object.fromEntries([...new Set(lines.flatMap((l) => Object.keys(l.load || {})))].map((k) => [k, median(lines.map((l) => l.load?.[k]))])),
      opcache: lines.some((l) => l.opcache),
      http: lines.flatMap((l) => l.http.map((h) => ({ ...h, load: l.id }))),
      errors: lines.flatMap((l) => l.errors),
      assets: lines[0]?.assets || [],
      active: lines[0]?.active || [],
    };
  }
  // Detalle (SAVEQUERIES): una petición sin plugins y otra con todos, consulta a consulta.
  out.detail = {};
  for (const set of ['none', 'all']) { const d = await hit(screen, set, { 'X-Digi-AP-Detail': '1' }); out.detail[set] = d.line?.detail || null; }
  results.screens[screen.key] = out;
  const n = out.none || {}, a = out.all || {};
  console.log(`${screen.key.padEnd(24)} sin: ${String(n.ms).padStart(8)} ms ${String(n.queries).padStart(4)} q ${String(n.peak_kb).padStart(6)} KB · con: ${String(a.ms).padStart(8)} ms ${String(a.queries).padStart(4)} q ${String(a.peak_kb).padStart(6)} KB · HTTP ${a.http?.length ?? 0} · avisos ${a.errors?.length ?? 0}`);
}

// Pantallas propias: los enlaces admin.php?page=digitalisimo* del menú y de las pestañas, con todo activo.
const ownPaths = new Set();
for (const start of ['/wp-admin/index.php', '/wp-admin/admin.php?page=digitalisimo-seo', ...(multisite ? ['/wp-admin/network/index.php', '/wp-admin/network/admin.php?page=digitalisimo-network-seo'] : [])]) {
  const r = await request('GET', start, { set: 'all' });
  for (const m of r.text.matchAll(/href=["']((?:https?:\/\/[^"']+)?\/wp-admin\/(?:network\/)?admin\.php\?page=digitalisimo[^"'#]*)["']/g)) {
    const u = m[1].replace(/&amp;/g, '&').replace(/^https?:\/\/[^/]+/, '');
    if (!/action=|_wpnonce|delete|run|export|download/.test(u)) ownPaths.add(u);
  }
}
for (const path of [...ownPaths].sort()) {
  const runs = [];
  for (let i = 0; i < 3; i++) runs.push(await hit({ path }, 'all'));
  const d = await hit({ path }, 'all', { 'X-Digi-AP-Detail': '1' });
  const lines = runs.map((r) => r.line).filter(Boolean);
  results.own[path] = { status: [...new Set(runs.map((r) => r.status))], fatal: runs.some((r) => r.fatal), ms: median(lines.map((l) => l.ms)), queries: median(lines.map((l) => l.queries)), peak_kb: median(lines.map((l) => l.peak_kb)), http: lines.flatMap((l) => l.http), errors: lines.flatMap((l) => l.errors), detail: d.line?.detail || null };
  console.log(`propia ${path.slice(0, 70).padEnd(70)} ${String(results.own[path].ms).padStart(8)} ms ${String(results.own[path].queries).padStart(4)} q HTTP ${results.own[path].http.length}`);
}

// Cachés remotas vencidas (lo que pasa cada 10 minutos en un sitio real): escritorio y Plugins con cada conjunto.
for (const set of SET_NAMES) {
  for (const path of ['/wp-admin/index.php', '/wp-admin/plugins.php']) {
    await state('expire');
    const r = await hit({ path }, set);
    results.expired[`${set} ${path}`] = { ms: r.line?.ms, http: r.line?.http || [] };
  }
}
console.log('cachés vencidas:', Object.entries(results.expired).map(([k, v]) => `${k}: ${v.http.length} HTTP`).join(' · '));

// Estado: autoload y cron frente a la foto sin plugins; los ganchos de cron de los plugins, ejecutados una vez y medidos.
const after = await state();
results.autoload = { before: before.autoload, after: after.autoload };
results.cron = { before: before.cron, after: after.cron };
results.options = after.options; results.sitemeta = after.sitemeta; results.plugin_postmeta = after.plugin_postmeta; results.postmeta_rows = after.postmeta_rows;
results.active_raw = after.active_raw; results.sitewide_raw = after.sitewide_raw;
const pluginHooks = [...new Set(after.cron.map((e) => e.hook))].filter((h) => !before.cron.some((b) => b.hook === h));
for (const hook of pluginHooks) {
  const r = await request('GET', `/?digi_ap_state=cron&hook=${encodeURIComponent(hook)}`, { headers: { 'X-Digi-AP-Token': token } });
  const line = await waitLog(r.id);
  results.cron[hook] = { status: r.status, ms: line?.ms, queries: line?.queries, http: line?.http || [], errors: line?.errors || [] };
}

// Chrome: errores de consola en cada pantalla HTML sin y con los plugins, y guardar desde el editor de bloques.
if (process.env.CHROME === '1') results.chrome = await chrome();

writeFileSync(`${outDir}/results.json`, JSON.stringify(results, null, 1));
console.log(`Resultados en ${outDir}/results.json`);

async function chrome() {
  const pptr = `${String(process.env.PUPPETEER_PATH || '').replace(/\/$/, '')}/puppeteer-core/lib/puppeteer/puppeteer-core.js`;
  const { default: puppeteer } = await import(pathToFileURL(pptr).href);
  const profile = mkdtempSync(existsSync('/tmp') ? '/tmp/dgap-' : `${tmpdir()}/dgap-`);
  const browser = await puppeteer.launch({ userDataDir: profile, executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: true, protocolTimeout: 600_000,
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${host} 127.0.0.1:${port}, MAP * ~NOTFOUND`] });
  const out = { console: {}, save: {} };
  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(300_000); page.setDefaultNavigationTimeout(300_000);
    await page.setViewport({ width: 1366, height: 900 });
    const base = `http://${host}`;
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'domcontentloaded' });
    await page.type('#user_login', 'admin'); await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
    let events = [];
    page.on('console', (m) => { if (m.type() === 'error') events.push({ kind: 'console', text: m.text().slice(0, 300), url: m.location()?.url || '' }); });
    page.on('pageerror', (e) => events.push({ kind: 'pageerror', text: String(e.message || e).slice(0, 300) }));
    page.on('response', (r) => { if (r.status() >= 400 && !/favicon/.test(r.url())) events.push({ kind: 'http', text: `${r.status()} ${r.url().replace(base, '')}`.slice(0, 300) }); });
    const html = screens.filter((s) => !s.method && s.path.startsWith('/') && !s.path.includes('/wp-json/'));
    const htmlPaths = [...html.map((s) => s.path), ...Object.keys(results.own)];
    for (const set of ['none', 'all']) {
      await page.setCookie({ name: 'digi_ap_set', value: SET_VALUE[set], url: base }, { name: 'digi_ap_log', value: '1', url: base });
      for (const path of htmlPaths) {
        if (set === 'none' && path.includes('page=digitalisimo')) continue;
        events = [];
        await page.goto(`${base}${path}`, { waitUntil: 'networkidle2' }).catch((e) => events.push({ kind: 'goto', text: String(e.message).slice(0, 200) }));
        if (/post\.php\?post=/.test(path)) await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId?.(), { timeout: 120_000 }).catch(() => events.push({ kind: 'editor', text: 'el editor no terminó de cargar' }));
        await sleep(1500);
        out.console[`${set} ${path}`] = events;
      }
      // Guardar una entrada desde el editor de bloques (REST + POST de metaboxes): tres veces.
      out.save[set] = [];
      for (let i = 0; i < 3; i++) {
        await page.goto(`${base}/wp-admin/post.php?post=${P}&action=edit`, { waitUntil: 'networkidle2' });
        await page.waitForFunction(() => window.wp?.data?.select('core/editor')?.getCurrentPostId?.(), { timeout: 120_000 });
        await sleep(1000);
        const t0 = Date.now() / 1000;
        const wall = await page.evaluate(async (n) => {
          const t = performance.now();
          const ed = wp.data.dispatch('core/editor');
          ed.editPost({ title: `Guardado medido ${n} ${Date.now()}` });
          await ed.savePost();
          const sel = wp.data.select('core/edit-post');
          for (let k = 0; k < 600 && (wp.data.select('core/editor').isSavingPost() || (sel.isSavingMetaBoxes && sel.isSavingMetaBoxes())); k++) await new Promise((r) => setTimeout(r, 100));
          return performance.now() - t;
        }, i);
        await sleep(800);
        const t1 = Date.now() / 1000 + 1;
        const lines = logLines().filter((l) => l.t >= t0 && l.t <= t1 && l.set === SET_VALUE[set]);
        out.save[set].push({ wall_ms: Math.round(wall), requests: lines.map((l) => ({ uri: l.uri.slice(0, 120), method: l.method, ms: l.ms, queries: l.queries, http: l.http.length, errors: l.errors.length })) });
      }
    }
  } finally {
    await browser.close();
    rmSync(profile, { recursive: true, force: true });
  }
  return out;
}
