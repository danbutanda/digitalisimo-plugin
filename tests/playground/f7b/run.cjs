// Conductor de f7b.sh (F7b, escenarios B1–B12): habla con el sitio de Playground por HTTP (con su host, cookies por
// usuario y el controlador `?digi_f7b=` de probe.php), mide en Chrome sin interfaz (front y editor) y escribe
// $OUT/summary.json. Cada escenario vive en f7b/scenarios/<grupo>.cjs (`module.exports = (api) => ({ B1: async (s) =>
// … })`) y es una lista de comprobaciones con un nombre estable y su estado: «ok», «falla» o «pendiente» (con el
// motivo). El del escenario es «falla» si alguna falla, «pendiente» si ninguna está hecha, «parcial» si hay hechas y
// pendientes, y «ok» si están todas hechas y en verde. B0 (preparación) corre siempre primero.
//
// Se puede correr a mano contra un sitio de `KEEP=1`: ENV_FILE=$OUT/env.json SCENARIOS=B1 NODE_PATH=<puppeteer> node run.cjs.
'use strict';
const fs = require('fs');
const path = require('path');
const http = require('http');

const ENV = JSON.parse(fs.readFileSync(process.env.ENV_FILE, 'utf8'));
const OUT = ENV.OUT;
const BASE = ENV.BASE.replace(/\/$/, '');
const PORT = +ENV.PORT;
const HOST = ENV.HOST;
const TOKEN = ENV.TOKEN;
const MODE = JSON.parse(fs.readFileSync(path.join(OUT, 'mode.json'), 'utf8'));
const QUICK = process.env.QUICK === '1';
const WIDTHS = QUICK ? [1280] : [360, 768, 1280];
const ALL = ['B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'B7', 'B8', 'B9', 'B10', 'B11', 'B12'];
const DEFAULTS = MODE.woo ? ['B7'] : ALL.filter((id) => id !== 'B7');
const TITLES = {
  B0: 'Preparación', B1: 'Acciones con destino', B2: 'Interacciones PRO (v4)', B3: 'Consultas guardadas y contextos', B4: '«Desvincular» y borrado bloqueado',
  B5: 'Filtros nuevos en el Loop Grid', B6: 'Destino «Archivo»', B7: 'Convivencia con los filtros de F4-Woo', B8: 'Sobrescrituras del Template',
  B9: 'Etiquetas dentro del texto', B10: 'Formulario condicional', B11: 'Slider anidado y presentación', B12: 'Vuelta a 4.3.0.168',
};
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => { const line = a.join(' '); console.log(line); fs.appendFileSync(path.join(OUT, 'run.log'), line + '\n'); };

// ── Resultados ─────────────────────────────────────────────────────────────────────────────────────────────────
const summary = {
  harness: 'f7b',
  mode: MODE.label,
  git: ENV.GIT_SHA,
  elements: { version: ENV.ELEMENTS_VERSION, sha: ENV.ELEMENTS_SHA, live: ENV.LIVE === '1' },
  quick: QUICK,
  widths: WIDTHS,
  started: new Date(+ENV.STARTED * 1000).toISOString(),
  scenarios: {},
};
if (MODE.woo) { try { summary.woocommerce = JSON.parse(fs.readFileSync(path.join(OUT, 'woocommerce.json'), 'utf8')); } catch (e) { summary.woocommerce = null; } }
const write = () => fs.writeFileSync(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 1) + '\n');

function scenario(id) {
  return { id, title: TITLES[id] || id, status: 'pendiente', checks: [], problems: [], notes: [], ms: 0 };
}
/** Comprobación con nombre estable. */
function check(s, id, what, ok, detail = '') {
  const d = detail === '' || detail === undefined ? undefined : (typeof detail === 'string' ? detail : JSON.stringify(detail));
  s.checks.push({ id, what, status: ok ? 'ok' : 'falla', detail: d ? d.slice(0, 1500) : undefined });
  if (!ok) s.problems.push(`${id} · ${what}${d ? ': ' + d.slice(0, 300) : ''}`);
  log(`${ok ? 'OK   ' : 'FALLA'}  ${id} · ${what}${d && (!ok || process.env.VERBOSE) ? ' · ' + d.slice(0, 400) : ''}`);
  return !!ok;
}
/** Comprobación descrita que no se puede hacer aquí, con el motivo. */
function pending(s, id, what, why) {
  s.checks.push({ id, what, status: 'pendiente', detail: why });
  log(`PEND   ${id} · ${what} · ${why}`);
}
const note = (s, text) => { s.notes.push(text); log(`       · ${text}`); };
function settle(s) {
  const states = new Set(s.checks.map((c) => c.status));
  if (states.has('falla') || s.problems.length) s.status = 'falla';
  else if (!states.has('ok')) s.status = 'pendiente';
  else if (states.has('pendiente')) s.status = 'parcial';
  else s.status = 'ok';
}

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

/** Controlador de la sonda (`?digi_f7b=`). `post` = parámetros por POST (árboles grandes). */
async function control(op, params = {}, { post = null, jar = '' } = {}) {
  const q = new URLSearchParams({ digi_f7b: op, token: TOKEN, ...params }).toString();
  const res = post ? await request('POST', `/?${q}`, { body: form(post), headers: FORM, jar }) : await request('GET', `/?${q}`, { jar });
  if (res.status !== 200 || !res.json) throw new Error(`Sonda ${op}: ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
}

async function login(user, pass = 'password', jar = user) {
  jars[jar] = {};
  await request('GET', '/wp-login.php', { jar });
  jars[jar].wordpress_test_cookie = 'WP%20Cookie%20check';
  const res = await request('POST', '/wp-login.php', { jar, body: form({ log: user, pwd: pass, 'wp-submit': 'Acceder', testcookie: '1', redirect_to: '/wp-admin/' }), headers: FORM });
  if (res.status !== 302) throw new Error(`No se pudo iniciar sesión como ${user} (${res.status})`);
  return jar;
}

/** Nonce de elementor_ajax del usuario de `jar`, leído del editor del documento (como lo usa el editor). */
async function editorNonce(jar, id) {
  const res = await request('GET', `/wp-admin/post.php?post=${id}&action=elementor`, { jar });
  const m = res.text.match(/"ajax":\{"url":"[^"]*","nonce":"([0-9a-f]{10})"/);
  if (m) return m[1];
  // Un usuario que no puede editar el documento no recibe el editor: el nonce de su sesión sirve igual.
  const who = await control('whoami', {}, { jar });
  return who.nonce || '';
}

/** elementor_ajax con varias acciones: devuelve `{ http, success, responses }` (cada respuesta con su `code`). */
async function eajax(jar, nonce, id, actions) {
  const body = form({ action: 'elementor_ajax', _nonce: nonce, editor_post_id: String(id), initial_document_id: String(id), actions: JSON.stringify(actions) });
  const res = await request('POST', '/wp-admin/admin-ajax.php', { jar, body, headers: FORM });
  const responses = (res.json && res.json.data && res.json.data.responses) || {};
  return { http: res.status, success: !!(res.json && res.json.success), responses, text: res.json ? '' : res.text.slice(0, 300) };
}
const codeOf = (r) => (r ? Number(r.code) : 0);

// ── Datos del sitio ─────────────────────────────────────────────────────────────────────────────────────────────
let MAP = null;
async function ids(refresh = false) {
  if (!MAP || refresh) MAP = await control('ids');
  return MAP;
}
const docId = (key) => (MAP && MAP.docs && MAP.docs[key]) || 0;
async function url(key) {
  const m = await ids();
  const u = m.urls && m.urls[key];
  if (!u) throw new Error(`sin dirección para ${key}`);
  return u;
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
/** Contexto nuevo (visitante sin cookies) o con las cookies de `jar`. */
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
/** Página con registro de errores, peticiones fallidas y peticiones hechas. */
async function newPage(ctx, { width = 1280, height = 900 } = {}) {
  const page = await ctx.newPage();
  await page.setViewport({ width, height });
  page.setDefaultTimeout(120000);
  const facts = { errors: [], failed: [], outside: [], requests: [] };
  page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) facts.errors.push(m.text().slice(0, 300)); });
  page.on('pageerror', (e) => facts.errors.push(`pageerror: ${String(e.message || e).slice(0, 300)}`));
  page.on('request', (r) => facts.requests.push({ url: r.url(), method: r.method(), post: r.postData() || '' }));
  page.on('requestfailed', (r) => {
    const f = r.failure();
    const reason = f ? f.errorText : 'falló';
    if (/ERR_ABORTED/.test(reason)) return;
    let host = '';
    try { host = new URL(r.url()).host; } catch (e) { /* url rara */ }
    (host === HOST ? facts.failed : facts.outside).push(`${reason} ${r.url().slice(0, 200)}`);
  });
  page.on('response', (r) => { let host = ''; try { host = new URL(r.url()).host; } catch (e) { /* */ } if (r.status() >= 400 && host === HOST) facts.failed.push(`${r.status()} ${r.url().slice(0, 200)}`); });
  page.facts = facts;
  return page;
}
/** Abre una URL del front en un contexto (visitante si no se da): `{ ctx, page, status, html, probe, facts }`. */
async function open(target, { jar = '', ctx = null, width = 1280, height = 900, wait = 'networkidle0', before = null, js = true } = {}) {
  const own = !ctx;
  ctx = ctx || await context(jar);
  const page = await newPage(ctx, { width, height });
  if (!js) await page.setJavaScriptEnabled(false);
  if (before) await before(page);
  const res = await page.goto(target.startsWith('http') ? target : BASE + target, { waitUntil: wait, timeout: 240000 });
  const html = res ? await res.text().catch(() => '') : '';
  return { ctx, own, page, status: res ? res.status() : 0, html, probe: probeOf(html), facts: page.facts, close: async () => { if (own) await ctx.close().catch(() => {}); else await page.close().catch(() => {}); } };
}
function probeOf(html) {
  const m = String(html).match(/<!-- digi-f7b-probe (\{.*?\}) -->/);
  try { return m ? JSON.parse(m[1]) : null; } catch (e) { return null; }
}
async function shot(page, name) {
  await page.screenshot({ path: path.join(OUT, 'shots', `${name}.png`), fullPage: true }).catch(() => {});
}

/** Editor de un documento con la sesión de `jar`: `{ ctx, page, frame, facts }`, con la vista previa pintada. */
async function openEditor(jar, id, { width = 1640, height = 1000 } = {}) {
  const ctx = await context(jar);
  const page = await newPage(ctx, { width, height });
  try {
    await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 240000 });
    await page.waitForSelector('#elementor-panel', { timeout: 180000 });
  } catch (e) {
    await shot(page, `editor-${id}-error`);
    const where = page.url();
    await ctx.close();
    throw new Error(`${e.message} (en ${where})`);
  }
  const frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: 180000 })).contentFrame();
  await frame.waitForSelector('.elementor-element', { timeout: 180000 }).catch(() => {});
  await page.waitForFunction(() => { const l = document.querySelector('#elementor-loading'); return window.elementor && window.$e && (!l || getComputedStyle(l).display === 'none'); }, { timeout: 300000 }).catch(() => {});
  await sleep(2500);
  return { ctx, page, frame, facts: page.facts, close: () => ctx.close().catch(() => {}) };
}
/** Abre el panel de un elemento en el editor (como un clic en el lienzo) y espera a que se pinte. */
async function selectElement(ed, elementId) {
  const ok = await ed.page.evaluate((eid) => {
    const c = window.elementor.getContainer(eid);
    if (!c) return false;
    window.$e.run('document/elements/select', { container: c });
    window.$e.run('panel/editor/open', { model: c.model, view: c.view });
    return true;
  }, elementId);
  await sleep(1200);
  return ok;
}
/** Abre una sección del panel del elemento abierto (`section_*`) por su nombre. */
async function activateSection(ed, section, tab = '') {
  await ed.page.evaluate((sec, t) => {
    const page = window.elementor.getPanelView().getCurrentPageView();
    if (t && page.activateTab) page.activateTab(t);
    if (page.activateSection) page.activateSection(sec);
    page.render();
  }, section, tab);
  await sleep(800);
}
/** Guarda el documento abierto en el editor (`document/save/update`) y espera a que termine. */
async function editorSave(ed) {
  return ed.page.evaluate(async () => {
    try {
      await window.$e.run('document/save/update', { force: true });
      return { ok: true };
    } catch (e) {
      return { ok: false, error: String(e && e.message || e) };
    }
  });
}
/** Ajustes de un elemento en el modelo del editor. */
const editorSettings = (ed, elementId) => ed.page.evaluate((eid) => { const c = window.elementor.getContainer(eid); return c ? JSON.parse(JSON.stringify(c.settings.toJSON())) : null; }, elementId);

/** Errores fatales y avisos de PHP desde el principio (o desde `from` bytes). */
function serverLog(file, from = 0) {
  try { return fs.readFileSync(path.join(OUT, file)).slice(from).toString('utf8'); } catch (e) { return ''; }
}
const logSize = (file) => { try { return fs.statSync(path.join(OUT, file)).size; } catch (e) { return 0; } };

/** HTML normalizado para comparar dos visitas: sin nonces, `ver=`, la sonda ni las horas. */
function normalize(html) {
  return String(html)
    .replace(/<!-- digi-f7b-probe .*? -->/g, '')
    .replace(/([?&;]ver=)[^"'&\s]+/g, '$1V')
    .replace(/("nonce"|"_nonce"|nonce)(\\?"?\s*[:=]\s*\\?["'])[0-9a-f]{10}/gi, '$1$2N')
    .replace(/"(?:wpRestNonce|restNonce|ajaxNonce)":"[0-9a-f]{10}"/g, '"N"')
    .replace(/data-nonce="[0-9a-f]{10}"/g, 'data-nonce="N"')
    .replace(/\s+/g, ' ');
}
/** HTML del elemento con ese ID (la etiqueta de apertura con `elementor-element-<id>` hasta su `</div>` de cierre). */
function elementHtml(html, id) {
  const re = new RegExp(`<div[^>]*\\belementor-element-${id}\\b[^>]*>`);
  const m = re.exec(html);
  if (!m) return null;
  const tag = /<(\/?)div\b[^>]*>/g;
  tag.lastIndex = m.index + m[0].length;
  let depth = 1;
  let t;
  while ((t = tag.exec(html))) {
    depth += t[1] ? -1 : 1;
    if (!depth) return html.slice(m.index, t.index + t[0].length);
  }
  return html.slice(m.index);
}
/** IDs de las entradas de un bucle (Loop Grid: `e-loop-item-<ID>`; Posts y archivo: `post-<ID>` en `article`), sin los de bucles anidados. */
function loopIds(html, widgetId) {
  const slice = elementHtml(html, widgetId);
  if (null === slice) return null;
  const out = [];
  const re = /<div data-elementor-type="loop-item"[^>]*\be-loop-item-(\d+)\b|<article[^>]*\bpost-(\d+)\b/g;
  let m;
  while ((m = re.exec(slice))) {
    const id = Number(m[1] || m[2]);
    if (!out.includes(id)) out.push(id);
  }
  return out;
}
/** IDs de los elementos de un bucle en la página viva (sólo los hijos directos del widget, sin bucles anidados). */
const domLoopIds = (page, widgetId) => page.evaluate((wid) => {
  const root = document.querySelector(`.elementor-element-${wid}`);
  if (!root) return null;
  const items = [...root.querySelectorAll('[class*="e-loop-item-"], article[class*="post-"]')].filter((el) => el.closest(`.elementor-element-${wid}`) === root && !el.parentElement.closest('[class*="e-loop-item-"]') || el.parentElement.closest('[class*="e-loop-item-"]') === null);
  const out = [];
  for (const el of items) {
    const m = String(el.className).match(/e-loop-item-(\d+)/) || String(el.className).match(/\bpost-(\d+)\b/);
    if (m && !out.includes(Number(m[1]))) out.push(Number(m[1]));
  }
  return out;
}, widgetId);

const API = {
  ENV, OUT, BASE, PORT, HOST, TOKEN, MODE, QUICK, WIDTHS, summary, sleep, log, check, pending, note, request, form, FORM, control,
  login, editorNonce, eajax, codeOf, ids, docId, url, startBrowser, context, newPage, open, probeOf, shot, openEditor, selectElement,
  activateSection, editorSave, editorSettings, serverLog, logSize, normalize, elementHtml, loopIds, domLoopIds, jars,
};

// ── B0 · preparación ───────────────────────────────────────────────────────────────────────────────────────────
async function B0(s) {
  const ping = await control('ping');
  summary.site = ping;
  check(s, 'B0.1', 'constructor incluido, F7b y el módulo neutro de filtros cargados, tema DIGITALÍSIMO', ping.bundled && ping.f7b && ping.filters && ping.theme === 'digitalisimo-theme', JSON.stringify({ core: ping.core, pro: ping.pro, f7b: ping.f7b, filters: ping.filters, theme: ping.theme }));
  check(s, 'B0.2', 'preparación sin errores', !ping.errors, ping.errors);
  const c = ping.counts || {};
  check(s, 'B0.3', 'los seis contadores de F7b reflejan los documentos de prueba (todos > 0)', ['actions', 'hidden', 'overrides', 'saved_queries', 'inline_tags', 'form_conditions'].every((k) => Number(c[k]) > 0), JSON.stringify(c));
  const m = await ids(true);
  check(s, 'B0.4', 'consultas guardadas y documentos creados', Object.keys(m.queries || {}).length >= 7 && Object.keys(m.docs || {}).length >= 20, JSON.stringify({ queries: Object.keys(m.queries || {}), docs: Object.keys(m.docs || {}).length }));
  await control('clearcss');
  const bad = [];
  for (const key of Object.keys(m.docs)) {
    if (/^(f7b-loop|b1-popup|b3-single|b4-free|b6-|b8-tpl|b8-outer|b9-header)/.test(key)) continue;
    const res = await request('GET', m.urls[key]);
    fs.writeFileSync(path.join(OUT, 'pages', `${key}.html`), res.text);
    if (res.status !== 200 || /critical error|Fatal error|Parse error/i.test(res.text)) bad.push(`${key}: ${res.status}`);
  }
  check(s, 'B0.5', 'cada página de prueba responde 200 sin error de PHP (HTML en pages/)', !bad.length, bad.join(', '));
  const fatal = serverLog('fatal.log');
  check(s, 'B0.6', 'sin errores fatales de PHP', !fatal, fatal.slice(0, 400));
}

// ── Principal ──────────────────────────────────────────────────────────────────────────────────────────────────
const RUN = { B0 };
for (const id of ALL) RUN[id] = async (s) => pending(s, `${id}.0`, TITLES[id], 'sin escenario implementado');
const SCENARIO_DIR = path.join(__dirname, 'scenarios');
for (const file of (fs.existsSync(SCENARIO_DIR) ? fs.readdirSync(SCENARIO_DIR) : []).filter((f) => f.endsWith('.cjs')).sort()) {
  const provided = require(path.join(SCENARIO_DIR, file))(API);
  for (const [id, fn] of Object.entries(provided)) {
    if (!ALL.includes(id) || typeof fn !== 'function') { console.error(`${file}: escenario desconocido ${id}`); process.exit(1); }
    RUN[id] = fn;
  }
}

(async () => {
  const wanted = process.env.SCENARIOS ? process.env.SCENARIOS.split(',').map((x) => x.trim()).filter(Boolean) : DEFAULTS;
  for (const id of wanted) if (!ALL.includes(id)) { console.error(`Escenario desconocido: ${id} (válidos: ${ALL.join(', ')})`); process.exit(1); }
  if (wanted.includes('B12') && ENV.LIVE === '1') throw new Error('B12 reemplaza la copia montada: no se corre con LIVE=1');
  // B12 reemplaza el plugin: siempre al final.
  const order = ['B0', ...wanted.filter((id) => id !== 'B12'), ...(wanted.includes('B12') ? ['B12'] : [])];
  summary.options = { scenarios: wanted, quick: QUICK, woo: !!MODE.woo };
  log(`f7b · modo ${MODE.label} · Elements ${ENV.ELEMENTS_VERSION} (${ENV.ELEMENTS_SHA}) · escenarios ${order.join(',')}${QUICK ? ' · rápido' : ''}`);
  let fatal0 = logSize('fatal.log');
  for (const id of order) {
    const s = scenario(id);
    const t0 = Date.now();
    log(`── ${id} · ${s.title}`);
    try {
      await RUN[id](s);
    } catch (e) {
      check(s, `${id}.x`, 'el escenario termina sin excepción del arnés', false, e.stack ? e.stack.split('\n').slice(0, 5).join(' | ') : String(e));
    }
    const fatal = serverLog('fatal.log', fatal0);
    if (fatal) { s.problems.push(`fatal.log: ${fatal.slice(0, 400)}`); fatal0 = logSize('fatal.log'); }
    s.ms = Date.now() - t0;
    settle(s);
    summary.scenarios[id] = s;
    const done = s.checks.filter((c) => c.status !== 'pendiente').length;
    log(`${id} ${s.title}: ${s.status} (${s.checks.filter((c) => c.status === 'ok').length} ok, ${s.checks.filter((c) => c.status === 'falla').length} fallan, ${s.checks.length - done} pendientes) en ${Math.round(s.ms / 1000)} s`);
    summary.duration_s = Math.round(Date.now() / 1000 - +ENV.STARTED);
    write();
  }
  summary.php_notices = serverLog('php-errors.log').split('\n').filter(Boolean).length;
  const failed = Object.values(summary.scenarios).filter((x) => x.status === 'falla');
  summary.status = failed.length ? 'falla' : 'ok';
  summary.finished = new Date().toISOString();
  summary.duration_s = Math.round(Date.now() / 1000 - +ENV.STARTED);
  write();
  log(`Resultado: ${summary.status} · ${Object.values(summary.scenarios).map((x) => `${x.id} ${x.status}`).join(' · ')}`);
  if (browser) await browser.close().catch(() => {});
  process.exit(failed.length ? 1 : 0);
})().catch(async (e) => { log('FALLO DEL ARNÉS:', e.stack || e.message); try { write(); } catch (err) { /* */ } if (browser) await browser.close().catch(() => {}); process.exit(1); });
