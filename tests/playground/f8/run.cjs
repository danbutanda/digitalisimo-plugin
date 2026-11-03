// Conductor de f8.sh (F8, frente J): habla con el sitio de Playground del escenario por HTTP (con su host, cookies por
// usuario y la sonda `?digi_f8=` de probe.php), mide en Chrome sin interfaz y suma el escenario a $OUT/summary.json.
// Cada escenario vive en f8/pN.cjs (`module.exports = (api) => async (s) => { … }`) y es una lista de comprobaciones
// con un nombre estable y su estado: «ok», «falla» o «pendiente» (con el motivo). El del escenario es «falla» si alguna
// falla, «pendiente» si ninguna está hecha, «parcial» si hay hechas y pendientes, y «ok» si están todas en verde.
// Los escenarios delegados (P5, P6, P7, P10) no tienen sitio propio: su pN.cjs lee el resultado del otro arnés.
//
// A mano contra un sitio de `KEEP=1`: ENV_FILE=$OUT/P2/env.json NODE_PATH=<puppeteer> node run.cjs
'use strict';
const fs = require('fs');
const path = require('path');
const http = require('http');

const ENV = JSON.parse(fs.readFileSync(process.env.ENV_FILE, 'utf8'));
const OUT = ENV.OUT;
const RUN = ENV.RUN;
const SCENARIO = ENV.SCENARIO;
const DELEGATED = ENV.DELEGATED === '1';
const BASE = (ENV.BASE || '').replace(/\/$/, '');
const PORT = +ENV.PORT;
const HOST = ENV.HOST || '';
const TOKEN = ENV.TOKEN || '';
const TITLES = {
  P0: 'A/B de Prime Slider Lite 4.7.0', P1: 'Prime Slider Pro sintético', P2: 'Element Pack sintético en un sitio', P3: 'Red: barrera y retirada',
  P4: 'Marcadores de K', P5: 'Presupuesto del front', P6: 'Interacción', P7: 'Marca y proveedores', P8: 'Vuelta atrás',
  P9: 'Reversión', P10: 'A/B con Element Pack Lite 10.0.1',
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const T0 = Date.now();
const log = (...a) => { const line = `[${String(Math.round((Date.now() - T0) / 1000)).padStart(4)} s] ` + a.join(' '); console.log(line); fs.appendFileSync(path.join(RUN, 'run.log'), line + '\n'); };

// ── Resultados ─────────────────────────────────────────────────────────────────────────────────────────────────
const SUMMARY_FILE = path.join(OUT, 'summary.json');
function readSummary() { try { return JSON.parse(fs.readFileSync(SUMMARY_FILE, 'utf8')); } catch (e) { return null; } }
const s = { id: SCENARIO, title: TITLES[SCENARIO] || SCENARIO, status: 'pendiente', checks: [], problems: [], notes: [], facts: {}, ms: 0, run: ENV.RUN_ID, git: ENV.GIT_SHA };
function check(sc, id, what, ok, detail = '') {
  const d = detail === '' || detail === undefined ? undefined : (typeof detail === 'string' ? detail : JSON.stringify(detail));
  sc.checks.push({ id, what, status: ok ? 'ok' : 'falla', detail: d ? d.slice(0, 2000) : undefined });
  if (!ok) sc.problems.push(`${id} · ${what}${d ? ': ' + d.slice(0, 400) : ''}`);
  log(`${ok ? 'OK   ' : 'FALLA'}  ${id} · ${what}${d && (!ok || process.env.VERBOSE) ? ' · ' + d.slice(0, 500) : ''}`);
  return !!ok;
}
function pending(sc, id, what, why) {
  sc.checks.push({ id, what, status: 'pendiente', detail: why });
  log(`PEND   ${id} · ${what} · ${why}`);
}
const note = (sc, text) => { sc.notes.push(text); log(`       · ${text}`); };
function settle(sc) {
  const states = new Set(sc.checks.map((c) => c.status));
  if (states.has('falla') || sc.problems.length) sc.status = 'falla';
  else if (!states.has('ok')) sc.status = 'pendiente';
  else if (states.has('pendiente')) sc.status = 'parcial';
  else sc.status = 'ok';
}
function write() {
  const summary = readSummary() || { harness: 'f8', started: new Date(+ENV.STARTED * 1000).toISOString(), scenarios: {}, runs: [] };
  summary.git = ENV.GIT_SHA;
  summary.elements = { version: ENV.ELEMENTS_VERSION, tree: ENV.TREE_ELEMENTS || summary.elements && summary.elements.tree, sha: ENV.ELEMENTS_SHA, stamped: ENV.STAMP === '1' };
  summary.migration = { version: ENV.MIGRATION_VERSION, tree: ENV.TREE_MIGRATION || summary.migration && summary.migration.tree, sha: ENV.MIGRATION_SHA, stamped: ENV.STAMP === '1' };
  summary.scenarios = summary.scenarios || {};
  summary.scenarios[SCENARIO] = s;
  summary.runs = summary.runs || [];
  const logs = {};
  for (const f of ['fatal.log', 'php-errors.log', 'http-blocked.log', 'http-out.log']) logs[f] = serverLog(f).split('\n').filter(Boolean).length;
  summary.runs = summary.runs.filter((r) => !(r.scenario === SCENARIO && r.run === ENV.RUN_ID));
  summary.runs.push({ scenario: SCENARIO, run: ENV.RUN_ID, git: ENV.GIT_SHA, elements_sha: ENV.ELEMENTS_SHA, migration_sha: ENV.MIGRATION_SHA, status: s.status, duration_s: Math.round(s.ms / 1000), logs });
  const failed = Object.values(summary.scenarios).filter((x) => x.status === 'falla');
  summary.status = failed.length ? 'falla' : 'ok';
  summary.finished = new Date().toISOString();
  fs.writeFileSync(SUMMARY_FILE, JSON.stringify(summary, null, 1) + '\n');
}

// ── Registros del servidor ──────────────────────────────────────────────────────────────────────────────────────
function serverLog(file, from = 0) {
  try { return fs.readFileSync(path.join(RUN, file)).slice(from).toString('utf8'); } catch (e) { return ''; }
}
const logSize = (file) => { try { return fs.statSync(path.join(RUN, file)).size; } catch (e) { return 0; } };

// ── HTTP con host y cookies ────────────────────────────────────────────────────────────────────────────────────
const jars = {};
function request(method, url, { body = null, headers = {}, jar = '', timeout = 600000 } = {}) {
  const u = new URL(url, BASE + '/');
  const cookies = Object.entries(jars[jar] || {}).map(([k, v]) => `${k}=${v}`).join('; ');
  const h = { Host: HOST, ...(cookies ? { Cookie: cookies } : {}), ...headers };
  if (body !== null && !Buffer.isBuffer(body)) body = Buffer.from(typeof body === 'string' ? body : JSON.stringify(body));
  if (body !== null) h['Content-Length'] = body.length;
  return new Promise((resolve, reject) => {
    const req = http.request({ host: '127.0.0.1', port: PORT, method, path: u.pathname + u.search, headers: h, timeout }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        if (jar) {
          jars[jar] = jars[jar] || {};
          for (const c of [].concat(res.headers['set-cookie'] || [])) {
            const [pair] = c.split(';');
            const i = pair.indexOf('=');
            const name = pair.slice(0, i).trim();
            const value = pair.slice(i + 1).trim();
            if (/expires=Thu, 01[- ]Jan[- ]1970/i.test(c) || value === 'deleted') delete jars[jar][name]; else jars[jar][name] = value;
          }
        }
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch (e) { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${url}`)));
    if (body !== null) req.write(body);
    req.end();
  });
}
const form = (obj) => new URLSearchParams(obj).toString();
const FORM = { 'Content-Type': 'application/x-www-form-urlencoded' };

/** Sonda (`?digi_f8=`) del sitio de `site` (ruta del sitio en una red: '' o '/dos'). */
async function control(op, params = {}, { post = null, site = '' } = {}) {
  const q = new URLSearchParams({ digi_f8: op, token: TOKEN, ...params }).toString();
  const res = post ? await request('POST', `${site}/?${q}`, { body: form(post), headers: FORM }) : await request('GET', `${site}/?${q}`);
  if (res.status !== 200 || !res.json) throw new Error(`Sonda ${op}${site ? ' en ' + site : ''}: ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
}

/** WP-CLI dentro del sitio (f8/cli.php): `{ stdout, stderr, notices, fatal, ms }`. */
async function cli(args) {
  const q = new URLSearchParams({ token: TOKEN, args: JSON.stringify(args) }).toString();
  const res = await request('GET', `/wp-content/probe/cli.php?${q}`, { timeout: 600000 });
  return res.json || { stdout: '', stderr: res.text.slice(0, 2000), fatal: `HTTP ${res.status}`, notices: [] };
}

async function login(user = 'admin', pass = 'password', jar = user, site = '') {
  jars[jar] = {};
  await request('GET', `${site}/wp-login.php`, { jar });
  jars[jar].wordpress_test_cookie = 'WP%20Cookie%20check';
  const res = await request('POST', `${site}/wp-login.php`, { jar, body: form({ log: user, pwd: pass, 'wp-submit': 'Acceder', testcookie: '1', redirect_to: `${site}/wp-admin/` }), headers: FORM });
  if (res.status !== 302) throw new Error(`No se pudo iniciar sesión como ${user} (${res.status})`);
  return jar;
}

// ── «Migrar» por el mismo AJAX que la pantalla ─────────────────────────────────────────────────────────────────
const PAGE = 'digitalisimo-ep-migration';
/** Pantalla de la herramienta del sitio (o de la red): `{ ajax, nonce, html, buttons }`. */
async function tool(jar, { site = '', network = false } = {}) {
  const url = network ? '/wp-admin/network/settings.php?page=' + PAGE : `${site}/wp-admin/tools.php?page=${PAGE}`;
  const res = await request('GET', url, { jar });
  const box = (res.text.match(/<div id="digi-migrate"[^>]*>/) || [''])[0];
  const attr = (n) => ((box.match(new RegExp(`${n}="([^"]*)"`)) || [])[1] || '').replace(/&amp;/g, '&');
  // Botones del contenido de la pantalla (sin el menú de la administración ni las plantillas del constructor).
  const start = res.text.indexOf('<div class="wrap');
  const end = res.text.indexOf('id="wpfooter"');
  const body = start >= 0 ? res.text.slice(start, end > start ? end : undefined).replace(/<script[\s\S]*?<\/script>/g, '') : '';
  const buttons = [...body.matchAll(/<button\b[^>]*>([\s\S]*?)<\/button>|<input\b[^>]*type="submit"[^>]*value="([^"]*)"/g)].map((m) => (m[1] || m[2] || '').replace(/<[^>]+>/g, '').trim()).filter(Boolean);
  return { status: res.status, ajax: attr('data-ajax'), nonce: attr('data-nonce'), running: attr('data-running'), html: res.text, buttons };
}
/** Un paso de `digitalisimo_migration_step` (lo mismo que envía assets/js/migrate.js). */
async function step(jar, t, { start = false, run = '', network = false } = {}) {
  const body = { action: 'digitalisimo_migration_step', _ajax_nonce: t.nonce, start: start ? '1' : '', run };
  if (network) body.network = '1';
  const u = new URL(t.ajax);
  const t0 = Date.now();
  const res = await request('POST', u.pathname, { jar, body: form(body), headers: FORM });
  return { http: res.status, ok: !!(res.json && res.json.success), data: res.json && res.json.data, raw: res.json ? null : res.text.slice(0, 600), ms: Date.now() - t0 };
}
/**
 * Conduce la tarea del sitio como drive() de migrate.js: pasos hasta `done`, `wait_network` o `idle`, con `busy` a 3 s.
 * `onStep(r, i)` corre después de cada respuesta (P2 sondea la portada ahí). Devuelve `{ steps, last }`.
 */
async function migrate(jar, { site = '', onStep = null, max = 400, start = true } = {}) {
  const t = await tool(jar, { site });
  if (!t.nonce) throw new Error(`sin nonce en la herramienta de ${site || '/'} (${t.status})`);
  const run = start ? Date.now().toString(36) + Math.random().toString(36).slice(2, 8) : '';
  let create = start;
  const steps = [];
  let last = null;
  let failures = 0;
  for (let i = 0; i < max; i++) {
    const r = await step(jar, t, { start: create, run });
    steps.push({ i, http: r.http, ok: r.ok, ms: r.ms, stage: r.data && r.data.stage, done: !!(r.data && r.data.done), pause: !!(r.data && r.data.pause), busy: !!(r.data && r.data.busy), wait: !!(r.data && r.data.wait_network), idle: !!(r.data && r.data.idle), error: r.ok ? '' : (r.raw || JSON.stringify(r.data || '')).slice(0, 300) });
    last = r;
    if (onStep) await onStep(r, i);
    if (!r.ok) { if (++failures > 4) break; await sleep(1500 * failures); continue; }
    failures = 0;
    if (r.data.stage || r.data.done) create = false;
    if (r.data.done || r.data.wait_network || (r.data.idle && !create)) break;
    await sleep(r.data.busy ? 3000 : 200);
  }
  return { steps, last };
}

// ── Chrome ─────────────────────────────────────────────────────────────────────────────────────────────────────
let browser = null;
async function startBrowser() {
  if (browser) return browser;
  const puppeteer = require('puppeteer-core');
  const target = HOST.split(':')[0];
  // Sólo el sitio de prueba existe: cualquier otra petición falla (nada sale a internet).
  const args = ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', '--font-render-hinting=none', `--host-resolver-rules=MAP ${target} 127.0.0.1:${PORT}, MAP * ~NOTFOUND`];
  browser = await puppeteer.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: 'new', args, protocolTimeout: 600000 });
  return browser;
}
async function context(jar = '') {
  const ctx = await (await startBrowser()).createBrowserContext();
  const cookies = jar ? jars[jar] || {} : {};
  if (Object.keys(cookies).length) {
    const page = await ctx.newPage();
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value: String(value), url: BASE + '/' })));
    await page.close();
  }
  return ctx;
}
async function newPage(ctx, { width = 1280, height = 900 } = {}) {
  const page = await ctx.newPage();
  await page.setViewport({ width, height });
  page.setDefaultTimeout(120000);
  const facts = { errors: [], failed: [], outside: [], requests: [] };
  page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) facts.errors.push(m.text().slice(0, 300)); });
  page.on('pageerror', (e) => facts.errors.push(`pageerror: ${String(e.message || e).slice(0, 300)}`));
  page.on('request', (r) => facts.requests.push(r.url()));
  page.on('requestfailed', (r) => {
    const f = r.failure();
    const reason = f ? f.errorText : 'falló';
    if (/ERR_ABORTED/.test(reason)) return;
    let host = '';
    try { host = new URL(r.url()).host; } catch (e) { /* */ }
    (host === HOST ? facts.failed : facts.outside).push(`${reason} ${r.url().slice(0, 200)}`);
  });
  page.on('response', (r) => { let host = ''; try { host = new URL(r.url()).host; } catch (e) { /* */ } if (r.status() >= 400 && host === HOST) facts.failed.push(`${r.status()} ${r.url().slice(0, 200)}`); });
  page.facts = facts;
  return page;
}
async function open(target, { jar = '', ctx = null, width = 1280, height = 900, wait = 'networkidle0', before = null, js = true } = {}) {
  const own = !ctx;
  ctx = ctx || await context(jar);
  const page = await newPage(ctx, { width, height });
  if (!js) await page.setJavaScriptEnabled(false);
  if (before) await before(page);
  const res = await page.goto(target.startsWith('http') ? target : BASE + target, { waitUntil: wait, timeout: 240000 });
  const html = res ? await res.text().catch(() => '') : '';
  return { ctx, page, status: res ? res.status() : 0, html, probe: probeOf(html), facts: page.facts, close: async () => { if (own) await ctx.close().catch(() => {}); else await page.close().catch(() => {}); } };
}
function probeOf(html) {
  const m = String(html).match(/<!-- digi-f8-probe (\{.*?\}) -->/);
  try { return m ? JSON.parse(m[1]) : null; } catch (e) { return null; }
}
async function shot(page, name) {
  await page.screenshot({ path: path.join(RUN, 'shots', `${name}.png`), fullPage: true }).catch(() => {});
}

// ── Utilidades de las comprobaciones ───────────────────────────────────────────────────────────────────────────
/** Cabeceras y pies en el HTML: los del origen simulado (mu-ep-header.php) y los del Theme Builder de Elements. */
function headers(html) {
  const h = String(html);
  const ep = [...h.matchAll(/<header data-ep-fake="(\d+)"/g)].map((m) => +m[1]);
  const epF = [...h.matchAll(/<footer data-ep-fake-footer="(\d+)"/g)].map((m) => +m[1]);
  const el = [...h.matchAll(/data-elementor-type="header"[^>]*data-elementor-id="(\d+)"|data-elementor-id="(\d+)"[^>]*data-elementor-type="header"/g)].map((m) => +(m[1] || m[2]));
  const elF = [...h.matchAll(/data-elementor-type="footer"[^>]*data-elementor-id="(\d+)"|data-elementor-id="(\d+)"[^>]*data-elementor-type="footer"/g)].map((m) => +(m[1] || m[2]));
  return { ep, epF, el, elF, header: ep.length + el.length, footer: epF.length + elF.length };
}
/** Rastro del proveedor en un HTML (hosts de vendors.json, vídeos del proveedor y carpetas de sus plugins). */
let VENDORS = null;
function vendorTrace(html) {
  if (!VENDORS) { try { VENDORS = JSON.parse(fs.readFileSync(path.join(ENV.VENDOR || path.join(OUT, 'vendor'), 'vendors.json'), 'utf8')); } catch (e) { VENDORS = { hosts: [], vendorVideos: [] }; } }
  const h = String(html).replace(/<!-- digi-f8-probe .*? -->/g, '');
  const out = [];
  for (const host of [...(VENDORS.hosts || []), ...(VENDORS.future || [])]) {
    const re = new RegExp(`(?:https?:)?//(?:[a-z0-9-]+\\.)*${host.replace(/\./g, '\\.')}\\b`, 'i');
    const m = h.match(re);
    if (m) out.push(m[0]);
  }
  for (const id of VENDORS.vendorVideos || []) if (id && h.includes(id)) out.push(`vídeo ${id}`);
  const plugin = h.match(/\/plugins\/bdthemes-[a-z-]+\//);
  if (plugin) out.push(plugin[0]);
  return out;
}
/** Texto visible de un fragmento de HTML. */
const text = (html) => String(html || '').replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/g, ' ').replace(/<[^>]+>/g, ' ').replace(/&#0?38;|&amp;/g, '&').replace(/\s+/g, ' ').trim();
/** Busca un elemento por ID en un árbol de Elementor. */
const find = (els, id) => { for (const e of els || []) { if (e.id === id) return e; const f = find(e.elements, id); if (f) return f; } return null; };
/** Todos los elementos de un árbol. */
const walk = (els, out = []) => { for (const e of els || []) { out.push(e); walk(e.elements, out); } return out; };

const API = {
  ENV, OUT, RUN, BASE, PORT, HOST, TOKEN, SCENARIO, sleep, log, check, pending, note, request, form, FORM, control, cli, login,
  tool, step, migrate, startBrowser, context, newPage, open, probeOf, shot, serverLog, logSize, headers, vendorTrace, text, find, walk, jars,
};

(async () => {
  const file = path.join(__dirname, `${SCENARIO.toLowerCase()}.cjs`);
  log(`f8 · ${SCENARIO} · ${TITLES[SCENARIO] || ''} · Elements ${ENV.ELEMENTS_VERSION} · Migración ${ENV.MIGRATION_VERSION}${DELEGATED ? ' · delegado' : ''}`);
  const t0 = Date.now();
  try {
    if (!fs.existsSync(file)) {
      pending(s, `${SCENARIO}.0`, TITLES[SCENARIO] || SCENARIO, 'sin escenario implementado');
    } else {
      if (!DELEGATED) {
        const ping = await control('ping');
        s.facts.site = { core: ping.core, bundled: ping.bundled, elements: ping.elements, migration: ping.migration, ep: ping.ep, ps: ping.ps, pro: ping.pro, theme: ping.theme, woo: ping.woo };
        check(s, `${SCENARIO}.0`, 'preparación sin errores (núcleo incluido, Elements y Migración con su versión)', !ping.errors && ping.bundled && ping.elements === ENV.ELEMENTS_VERSION && ping.migration === ENV.MIGRATION_VERSION, JSON.stringify({ errors: ping.errors, bundled: ping.bundled, elements: ping.elements, migration: ping.migration }));
        if (ping.warnings) note(s, `avisos de la preparación: ${ping.warnings.slice(0, 400)}`);
      }
      await require(file)(API)(s);
    }
  } catch (e) {
    check(s, `${SCENARIO}.x`, 'el escenario termina sin excepción del arnés', false, e.stack ? e.stack.split('\n').slice(0, 6).join(' | ') : String(e));
  }
  if (!DELEGATED) {
    const fatal = serverLog('fatal.log');
    check(s, `${SCENARIO}.z1`, 'sin errores fatales de PHP en toda la corrida', !fatal, fatal.slice(0, 600));
    const blocked = serverLog('http-blocked.log');
    check(s, `${SCENARIO}.z2`, 'el servidor no intentó ninguna petición a un dominio del proveedor', !blocked, blocked.slice(0, 600));
    const notices = serverLog('php-errors.log').split('\n').filter(Boolean);
    s.facts.php_notices = notices.length;
    if (notices.length) {
      const top = {};
      for (const l of notices) { const k = l.replace(/^\S+ \S+ /, ''); top[k] = (top[k] || 0) + 1; }
      s.facts.php_notices_top = Object.entries(top).sort((a, b) => b[1] - a[1]).slice(0, 8).map(([k, n]) => `${n}× ${k.slice(0, 300)}`);
      note(s, `${notices.length} avisos de PHP de los plugins propios (ver logs/): ${s.facts.php_notices_top.slice(0, 3).join(' | ')}`);
    }
  }
  s.ms = Date.now() - t0;
  settle(s);
  write();
  const counts = { ok: s.checks.filter((c) => c.status === 'ok').length, falla: s.checks.filter((c) => c.status === 'falla').length, pendiente: s.checks.filter((c) => c.status === 'pendiente').length };
  log(`${SCENARIO} ${s.title}: ${s.status} (${counts.ok} ok, ${counts.falla} fallan, ${counts.pendiente} pendientes) en ${Math.round(s.ms / 1000)} s`);
  if (browser) await browser.close().catch(() => {});
  process.exit(s.status === 'falla' ? 1 : 0);
})().catch(async (e) => { log('FALLO DEL ARNÉS:', e.stack || e.message); if (browser) await browser.close().catch(() => {}); process.exit(1); });
