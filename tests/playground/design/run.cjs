// Conductor de design.sh (F7a, DS1–DS13): habla con el sitio de Playground por HTTP (con su host, cookies por usuario y
// el controlador `?digi_design=` de probe.php), guarda documentos por elementor_ajax como lo haría el editor, mide en
// Chrome sin interfaz (front y vista previa del editor) y escribe $OUT/summary.json. Cada comprobación lleva un nombre
// estable: CORE=plugin compara sus resultados con los del modo normal (COMPARE_SUMMARY).
//
// Se puede correr a mano contra un sitio de `KEEP=1`: ENV_FILE=$OUT/env.json SCENARIOS=DS3 node run.cjs.
'use strict';
const fs = require('fs');
const path = require('path');
const http = require('http');

const ENV = JSON.parse(fs.readFileSync(process.env.ENV_FILE, 'utf8'));
const OUT = ENV.OUT;
const BASE = ENV.BASE;
const PORT = +ENV.PORT;
const HOST = ENV.HOST;
const TOKEN = ENV.TOKEN;
const MODE = JSON.parse(fs.readFileSync(path.join(OUT, 'mode.json'), 'utf8'));
const QUICK = process.env.QUICK === '1' || MODE.core === 'plugin';
const CASES = JSON.parse(fs.readFileSync(path.join(OUT, 'cases.json'), 'utf8'));
const PRESETS = JSON.parse(fs.readFileSync(path.join(OUT, 'presets.json'), 'utf8'));
const GRADIENTS = JSON.parse(fs.readFileSync(path.join(OUT, 'gradients.json'), 'utf8'));
const WIDTHS = QUICK ? [1280] : [360, 768, 1280];
const DEVICE = { 360: 'mobile', 768: 'tablet', 1280: 'desktop' };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => { const line = a.join(' '); console.log(line); fs.appendFileSync(path.join(OUT, 'run.log'), line + '\n'); };

// ── Resultados ─────────────────────────────────────────────────────────────────────────────────────────────────
const summary = { harness: 'design', mode: MODE.label, git: ENV.GIT_SHA, elements_sha: ENV.ELEMENTS_SHA, quick: QUICK, started: new Date(+ENV.STARTED * 1000).toISOString(), scenarios: {} };
let current = null;
function scenario(name) {
  current = summary.scenarios[name] = summary.scenarios[name] || { status: 'pass', checks: [], notes: [], ms: 0 };
  return current;
}
/** Comprobación con nombre estable. `known` = fallo conocido y declarado (se anota, no suspende). */
function check(name, ok, detail = '', { known = '' } = {}) {
  const entry = { name, ok: !!ok };
  if (detail !== '' && detail !== undefined) entry.detail = typeof detail === 'string' ? detail : JSON.stringify(detail);
  if (!ok && known) entry.known = known;
  current.checks.push(entry);
  if (!ok && !known) current.status = 'fail';
  log(`${ok ? 'OK   ' : known ? 'NOTA ' : 'FALLA'}  ${name}${entry.detail && (!ok || process.env.VERBOSE) ? ' · ' + entry.detail.slice(0, 400) : ''}`);
  return !!ok;
}
const note = (text) => { current.notes.push(text); log(`       · ${text}`); };
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);

// ── HTTP con host y cookies ────────────────────────────────────────────────────────────────────────────────────
const jars = {};
function request(method, url, { body = null, headers = {}, jar = '', timeout = 600000 } = {}) {
  const u = new URL(url, BASE);
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

/** Controlador de la sonda (`?digi_design=`), en el sitio `base` ('' o '/uno'). */
async function control(op, params = {}, base = '', { admin = false } = {}) {
  const q = new URLSearchParams({ digi_design: op, token: TOKEN, ...params }).toString();
  // `admin`: por admin-ajax.php (is_admin(), pilas de controles completas como en el editor; el front las optimiza).
  const res = await request('GET', `${base}/${admin ? 'wp-admin/admin-ajax.php' : ''}?${q}`);
  if (res.status !== 200 || !res.json) throw new Error(`Sonda ${op}: ${res.status} ${res.text.slice(0, 300)}`);
  return res.json;
}

async function login(user, pass, jar, base = '') {
  jars[jar] = {};
  await request('GET', `${base}/wp-login.php`, { jar });
  jars[jar].wordpress_test_cookie = 'WP%20Cookie%20check';
  const form = new URLSearchParams({ log: user, pwd: pass, 'wp-submit': 'Acceder', testcookie: '1', redirect_to: `${base}/wp-admin/` }).toString();
  const res = await request('POST', `${base}/wp-login.php`, { jar, body: form, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
  if (res.status !== 302) throw new Error(`No se pudo iniciar sesión como ${user} (${res.status})`);
  return jar;
}

/** Nonce de elementor_ajax del usuario de `jar`, leído del editor del documento (como lo usa el editor). */
async function editorNonce(jar, id, base = '') {
  const res = await request('GET', `${base}/wp-admin/post.php?post=${id}&action=elementor`, { jar });
  const m = res.text.match(/"ajax":\{"url":"[^"]*","nonce":"([0-9a-f]{10})"/);
  return m ? m[1] : '';
}

/** elementor_ajax con varias acciones: devuelve `{ http, success, responses }` (cada respuesta con su `code`). */
async function eajax(jar, nonce, id, actions, base = '') {
  const body = new URLSearchParams({ action: 'elementor_ajax', _nonce: nonce, editor_post_id: String(id), initial_document_id: String(id), actions: JSON.stringify(actions) }).toString();
  const res = await request('POST', `${base}/wp-admin/admin-ajax.php`, { jar, body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
  const responses = (res.json && res.json.data && res.json.data.responses) || {};
  return { http: res.status, success: !!(res.json && res.json.success), responses, text: res.json ? '' : res.text.slice(0, 300) };
}
const codeOf = (r) => (r ? Number(r.code) : 0);

/** Guarda un documento de cases.json por elementor_ajax (`save_builder`), con su árbol de la sonda. */
async function saveDoc(jar, nonce, key, ids, { transform = null, base = '', status = 'publish' } = {}) {
  const tree = await control('tree', { doc: key }, base);
  const elements = transform ? transform(JSON.parse(JSON.stringify(tree.elements))) : tree.elements;
  const settings = Object.assign({ post_status: status }, Array.isArray(tree.settings) ? {} : tree.settings);
  const r = await eajax(jar, nonce, ids[key], { save: { action: 'save_builder', data: { status, elements, settings } } }, base);
  return { ...r, code: codeOf(r.responses.save), meta: tree.meta };
}

/** Recorre un árbol de elementos guardado. */
function walk(nodes, fn) {
  for (const n of nodes || []) { fn(n); walk(n.elements, fn); }
}
function findEl(nodes, id) {
  let out = null;
  walk(nodes, (n) => { if (n.id === id) out = n; });
  return out;
}

// ── Chrome ─────────────────────────────────────────────────────────────────────────────────────────────────────
let browser = null;
async function startBrowser() {
  const puppeteer = require('puppeteer-core');
  const target = BASE.replace(/^https?:\/\//, '').split('/')[0];
  const args = ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', '--font-render-hinting=none'];
  // Sólo el sitio de prueba existe: cualquier otra petición falla (DS3: ninguna petición fuera; sin proveedores).
  args.push(`--host-resolver-rules=MAP ${target.split(':')[0]} 127.0.0.1:${PORT}, MAP * ~NOTFOUND`);
  browser = await puppeteer.launch({ executablePath: process.env.CHROME || '/usr/bin/google-chrome', headless: 'new', args, protocolTimeout: 600000 });
}

/** Un contexto de navegador nuevo (visitante sin cookies), con cookies opcionales del sitio. */
async function context(cookies = {}) {
  const ctx = await browser.createBrowserContext();
  ctx.__cookies = cookies;
  if (Object.keys(cookies).length) {
    const page = await ctx.newPage();
    await page.setCookie(...Object.entries(cookies).map(([name, value]) => ({ name, value: String(value), url: BASE + '/' })));
    await page.close();
  }
  return ctx;
}
/** Contexto con la sesión de `jar` (cookies de wp-login.php). */
const sessionContext = (jar) => context(jars[jar] || {});

/** Abre una URL del front: `{ page, status, html, probe, requests, errors }`. */
async function open(ctx, url, { width = 1280, height = 900, wait = 'networkidle0', before = null, lazy = true } = {}) {
  const page = await ctx.newPage();
  await page.setViewport({ width, height });
  const requests = [];
  const errors = [];
  page.on('request', (r) => requests.push(r.url()));
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  page.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource/.test(m.text())) errors.push(m.text()); });
  if (before) await before(page);
  const res = await page.goto(url.startsWith('http') ? url : BASE + url, { waitUntil: wait, timeout: 240000 });
  const html = res ? await res.text().catch(() => '') : '';
  // La carga diferida de fondos del núcleo deja `background-image: none` en los contenedores que no se han visto
  // (`e-lazyloaded`): se recorre la página y se vuelve arriba antes de medir.
  if (lazy) {
    const h = await page.evaluate(() => document.documentElement.scrollHeight).catch(() => 0);
    for (let y = 0; y < h; y += 500) await page.evaluate((y) => window.scrollTo({ top: y, behavior: 'instant' }), y).then(() => sleep(50));
    await page.evaluate(() => window.scrollTo({ top: 0, behavior: 'instant' })).catch(() => {});
    await sleep(400);
  }
  const m = html.match(/<!-- digi-design-probe (\{.*?\}) -->/);
  let probe = null;
  try { probe = m ? JSON.parse(m[1]) : null; } catch (e) { probe = null; }
  return { page, status: res ? res.status() : 0, html, probe, requests, errors };
}

/** Visita como visitante (contexto nuevo) y cierra; en CACHE=1 lo hace dos veces y devuelve las dos. */
async function visit(url, fn, { cookies = {}, width = 1280, twice = MODE.cache, lazy = true } = {}) {
  const out = [];
  for (let i = 0; i < (twice ? 2 : 1); i++) {
    const ctx = await context(cookies);
    const v = await open(ctx, url, { width, lazy });
    try { out.push(await fn(v)); } finally { await ctx.close(); }
  }
  if (twice && out.length === 2) {
    const a = JSON.stringify(out[0]);
    const b = JSON.stringify(out[1]);
    check(`DS9 · ${url} (${width} px): misma decisión en la segunda petición`, a === b, a === b ? '' : `primera ${a.slice(0, 300)} · segunda ${b.slice(0, 300)}`);
  }
  return out[out.length - 1];
}

/**
 * Huella del estilo calculado de un elemento y sus descendientes (propiedades de estilo, no de tamaño). Se evalúa en el
 * front y en el iframe de la vista previa.
 */
const STYLE_PROPS = ['color', 'font-size', 'font-weight', 'font-family', 'font-style', 'line-height', 'letter-spacing', 'text-transform', 'text-decoration-line', 'background-color', 'background-image', 'box-shadow', 'text-shadow', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'border-top-width', 'border-top-style', 'border-top-color', 'border-bottom-width', 'opacity', 'position', 'left', 'right', 'mask-image', 'z-index'];
function fingerprintIn(sel, props) {
  const el = document.querySelector(sel);
  if (!el) return null;
  const out = [];
  const take = (node, label) => {
    const cs = getComputedStyle(node);
    const row = { at: label };
    for (const p of props) row[p] = cs.getPropertyValue(p);
    out.push(row);
  };
  // Nodos que sólo pinta el editor (capa de herramientas, asas, vistas vacías) o que no tienen estilo propio.
  const EDITOR_ONLY = /\b(elementor-element-overlay|elementor-editor-[\w-]+|ui-resizable[\w-]*|elementor-empty-view|elementor-add-section[\w-]*|elementor-first-add|elementor-sortable-placeholder|e-con-select-preset[\w-]*)\b/;
  const kept = (ch) => !/^(STYLE|SCRIPT|TEMPLATE|LINK|NOSCRIPT)$/.test(ch.tagName) && !EDITOR_ONLY.test(typeof ch.className === 'string' ? ch.className : '');
  const rec = (node, label, depth) => {
    take(node, label);
    if (depth > 4) return;
    [...node.children].filter(kept).forEach((ch, i) => rec(ch, `${label}>${ch.tagName.toLowerCase()}${i}`, depth + 1));
  };
  rec(el, 'el', 0);
  return out;
}
const fingerprint = (frame, sel) => frame.evaluate(fingerprintIn, sel, STYLE_PROPS);
/** Diferencias entre dos huellas: [`at prop: a ≠ b`]. `mask` = propiedades que no se comparan por fila. */
function diff(a, b, mask = null) {
  if (!a || !b) return [`${a ? '' : 'falta el primero '}${b ? '' : 'falta el segundo'}`.trim()];
  if (a.length !== b.length) return [`estructura distinta (${a.length} ≠ ${b.length} nodos: ${a.map((r) => r.at).join(',')} · ${b.map((r) => r.at).join(',')})`];
  const out = [];
  a.forEach((row, i) => {
    for (const p of STYLE_PROPS) {
      if (mask && mask[i] && mask[i].has(p)) continue;
      if (row[p] !== b[i][p]) out.push(`${row.at} ${p}: ${row[p]} ≠ ${b[i][p]}`);
    }
  });
  return out;
}

/** Diferencia de píxeles entre dos PNG (base64) en Chrome: fracción de píxeles distintos (o 1 si cambian de tamaño). */
async function pixelDiff(page, a, b) {
  return page.evaluate(async (a, b) => {
    const load = (s) => new Promise((res, rej) => { const i = new Image(); i.onload = () => res(i); i.onerror = rej; i.src = 'data:image/png;base64,' + s; });
    const [x, y] = await Promise.all([load(a), load(b)]);
    if (x.width !== y.width || x.height !== y.height) return { ratio: 1, size: `${x.width}×${x.height} ≠ ${y.width}×${y.height}` };
    const c = document.createElement('canvas');
    c.width = x.width; c.height = x.height;
    const g = c.getContext('2d');
    g.drawImage(x, 0, 0);
    const d1 = g.getImageData(0, 0, c.width, c.height).data;
    g.clearRect(0, 0, c.width, c.height);
    g.drawImage(y, 0, 0);
    const d2 = g.getImageData(0, 0, c.width, c.height).data;
    let n = 0;
    for (let i = 0; i < d1.length; i += 4) if (Math.abs(d1[i] - d2[i]) + Math.abs(d1[i + 1] - d2[i + 1]) + Math.abs(d1[i + 2] - d2[i + 2]) > 24) n++;
    return { ratio: n / (d1.length / 4), size: `${x.width}×${x.height}` };
  }, a, b);
}
async function shot(page, sel, file = '') {
  const el = await page.$(sel);
  if (!el) return '';
  const b64 = await el.screenshot({ encoding: 'base64' });
  if (file) fs.writeFileSync(path.join(OUT, 'shots', file), Buffer.from(b64, 'base64'));
  return b64;
}

/** Editor de un documento con la sesión de `jar`: `{ page, frame, ctx }`, con la vista previa pintada. */
async function openEditor(jar, id, base = '') {
  const ctx = await sessionContext(jar);
  const page = await ctx.newPage();
  await page.setViewport({ width: 1640, height: 1000 });
  const errors = [];
  const requests = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  page.on('request', (r) => requests.push(r.url()));
  const failed = [];
  page.on('response', (r) => { if (r.status() >= 400) failed.push(`${r.status()} ${r.url().replace(BASE, '')}`); });
  page.on('requestfailed', (r) => { const f = r.failure(); if (f && !/ERR_ABORTED|NAME_NOT_RESOLVED/.test(f.errorText)) failed.push(`${f.errorText} ${r.url().replace(BASE, '')}`); });
  try {
    await page.goto(`${BASE}${base}/wp-admin/post.php?post=${id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 240000 });
    await page.waitForSelector('#elementor-panel', { timeout: 180000 });
  } catch (e) {
    await page.screenshot({ path: path.join(OUT, 'shots', `editor-${id}-error.png`) }).catch(() => {});
    const where = page.url();
    await ctx.close();
    throw new Error(`${e.message} (en ${where})`);
  }
  const frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: 180000 })).contentFrame();
  await frame.waitForSelector('.elementor-element', { timeout: 180000 });
  await page.waitForFunction(() => { const l = document.querySelector('#elementor-loading'); return window.elementor && window.$e && (!l || getComputedStyle(l).display === 'none'); }, { timeout: 300000 }).catch(() => {});
  await sleep(2500);
  return { ctx, page, frame, errors, requests, failed };
}
/** Modo de dispositivo de la vista previa (desktop, tablet, mobile) y espera a que el iframe cambie de ancho. */
async function deviceMode(ed, device) {
  await ed.page.evaluate((d) => {
    try { if (window.$e && $e.run) { $e.run('panel/change-device-mode', { device: d }); return; } } catch (e) { /* sigue */ }
    window.elementor.changeDeviceMode(d);
  }, device);
  await sleep(2500);
  return ed.frame.evaluate(() => window.innerWidth);
}

/** Reglas de las hojas del documento que contienen un texto (CSSOM, también hojas externas del mismo origen). */
function cssomIn(needle) {
  const out = [];
  const walkRules = (rules, sheet) => {
    for (const r of rules) {
      if (r.cssRules && r.cssRules.length) walkRules(r.cssRules, sheet);
      const text = r.cssText || '';
      if (text.includes(needle)) out.push({ sheet, text: text.slice(0, 4000) });
    }
  };
  for (const s of document.styleSheets) {
    let rules = null;
    try { rules = s.cssRules; } catch (e) { continue; }
    if (rules) walkRules(rules, s.href || (s.ownerNode && s.ownerNode.id) || 'inline');
  }
  return out;
}
const cssom = (frame, needle) => frame.evaluate(cssomIn, needle);

/** Errores fatales y avisos de PHP de Elements desde el principio (o desde `from` bytes). */
function serverLog(file, from = 0) {
  try { const buf = fs.readFileSync(path.join(OUT, file)); return buf.slice(from).toString('utf8'); } catch (e) { return ''; }
}
const logSize = (file) => { try { return fs.statSync(path.join(OUT, file)).size; } catch (e) { return 0; } };

module.exports = { ENV, OUT, BASE, MODE, QUICK, CASES, PRESETS, GRADIENTS, WIDTHS, DEVICE, sleep, log, summary, scenario, check, note, same, request, control, login, editorNonce, eajax, codeOf, saveDoc, walk, findEl, context, sessionContext, open, visit, fingerprint, diff, pixelDiff, shot, openEditor, deviceMode, cssom, serverLog, logSize, jars, STYLE_PROPS };

// ── Principal ──────────────────────────────────────────────────────────────────────────────────────────────────
if (require.main === module) {
  (async () => {
    const S = require('./scenarios.cjs');
    const all = S.defaults(MODE);
    const wanted = process.env.SCENARIOS ? process.env.SCENARIOS.split(',').map((s) => s.trim()).filter(Boolean) : all;
    if (wanted.includes('DS13') && ENV.LIVE === '1') throw new Error('DS13 reemplaza la copia montada: no se corre con LIVE=1');
    await startBrowser();
    const ping = await control('ping', {}, MODE.net ? '/uno' : '');
    summary.core = ping.core;
    summary.bundled = ping.bundled;
    summary.design_missing = ping.missing;
    log(`design · modo ${MODE.label} · núcleo ${ping.core}${ping.bundled ? ' (incluido)' : ' (plugin)'} · escenarios ${wanted.join(',')}${QUICK ? ' · rápido' : ''}`);
    let failed = false;
    for (const name of wanted) {
      if (!S[name]) { scenario(name); check(`${name}: existe`, false, 'escenario desconocido'); failed = true; continue; }
      const t0 = Date.now();
      scenario(name);
      log(`── ${name}`);
      try {
        await S[name]();
      } catch (e) {
        check(`${name}: terminó sin excepción del arnés`, false, e.stack ? e.stack.split('\n').slice(0, 4).join(' | ') : String(e));
      }
      current.ms = Date.now() - t0;
      if (current.status !== 'pass') failed = true;
      summary.duration_s = Math.round(Date.now() / 1000 - +ENV.STARTED);
      fs.writeFileSync(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 1));
    }
    const fatal = serverLog('fatal.log');
    summary.fatal = fatal ? fatal.split('\n').filter(Boolean).slice(0, 50) : [];
    summary.php_notices = serverLog('php-errors.log').split('\n').filter(Boolean).length;
    if (fatal) failed = true;
    if (S.compareWithNormal) S.compareWithNormal();
    if (Object.values(summary.scenarios).some((sc) => sc.status !== 'pass')) failed = true;
    summary.status = failed ? 'fail' : 'pass';
    summary.finished = new Date().toISOString();
    summary.duration_s = Math.round(Date.now() / 1000 - +ENV.STARTED);
    fs.writeFileSync(path.join(OUT, 'summary.json'), JSON.stringify(summary, null, 1));
    const counts = Object.entries(summary.scenarios).map(([k, v]) => `${k} ${v.status} (${v.checks.filter((c) => c.ok).length}/${v.checks.length}, ${Math.round(v.ms / 1000)} s)`);
    log(`Resultado: ${summary.status} · ${counts.join(' · ')}`);
    await browser.close().catch(() => {});
    process.exitCode = failed ? 1 : 0;
  })().catch(async (e) => { log('FALLO DEL ARNÉS:', e.stack || e.message); if (browser) await browser.close().catch(() => {}); process.exitCode = 1; });
}
