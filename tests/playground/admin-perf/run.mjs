// Conductor de la auditoría del escritorio (admin-perf.sh): mide cada pantalla con cada conjunto de plugins
// (cabecera X-Digi-Ap-Set), alternando el orden de los conjuntos en cada repetición, y recorre en Chrome las
// pantallas, el editor de bloques (con sus peticiones REST y un guardado) y el editor del constructor (abrir y
// guardar). Escribe $OUT/measures.json (cada medida del cliente) y $OUT/chrome.json; el servidor deja
// $OUT/requests.jsonl (sonda). Uso: node run.mjs <base>   (SETS, REPS, MULTI, PHASES, PLAYWRIGHT_CORE)
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const base = process.argv[2];
const OUT = process.env.OUT || '/tmp/digi-admin-perf';
const SETS = (process.env.SETS || 'none,el,elmig').split(',');
const REPS = Number(process.env.REPS || 5);
// Repeticiones de REST, guardar, subir y actualizar (por defecto REPS; subir y actualizar, como mucho 3).
const REPS_FUNC = Number(process.env.REPS_FUNC || REPS);
const MULTI = process.env.MULTI === '1';
// Conjuntos por fase (por defecto, todos los de SETS): CHROME_SETS, BUILDER_SETS, PROFILE_SETS, GITHUB_SETS.
const setsFor = (name) => (process.env[name] ? process.env[name].split(',') : SETS).filter((s) => SETS.includes(s));
const PHASES = new Set((process.env.PHASES || 'screens,own,rest,save,media,update,heartbeat,cron,profile,github,chrome,builder,lazymcp,autoload').split(','));
const ids = JSON.parse(fs.readFileSync(path.join(OUT, 'ids.json'), 'utf8'));
const measures = [];
const notes = [];
let counter = 0;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

// ---------------------------------------------------------------- Cliente HTTP con sesión
const jar = new Map();
function keep(res) {
  for (const c of res.headers.getSetCookie ? res.headers.getSetCookie() : []) {
    const [pair] = c.split(';');
    const i = pair.indexOf('=');
    const name = pair.slice(0, i).trim();
    const value = pair.slice(i + 1).trim();
    if (/expires=Thu, 01 Jan 1970|max-age=0/i.test(c) || value === 'deleted') jar.delete(name); else jar.set(name, value);
  }
}
const cookieHeader = () => [...jar].map(([k, v]) => `${k}=${v}`).join('; ');

async function req(url, { set = '', label = '', method = 'GET', body, headers = {}, extra = {}, keepText = false, timeout = 300000 } = {}) {
  const id = `m${Date.now()}-${++counter}`;
  const h = { Cookie: cookieHeader(), 'X-Digi-Ap-Id': id, 'X-Digi-Ap-Label': label, ...headers };
  if (set) h['X-Digi-Ap-Set'] = set;
  const t = performance.now();
  let res; let text = '';
  try {
    res = await fetch(url.startsWith('http') ? url : `${base}/${url.replace(/^\//, '')}`, { method, body, headers: h, redirect: 'manual', signal: AbortSignal.timeout(timeout) });
    keep(res);
    text = await res.text();
  } catch (e) {
    const m = { id, label, set, url, method, error: String(e.message || e), client_ms: performance.now() - t, ...extra };
    measures.push(m);
    return { ...m, text: '' };
  }
  const m = { id, label, set, url, method, status: res.status, client_ms: Math.round((performance.now() - t) * 10) / 10, bytes: text.length, ...extra };
  measures.push(m);
  return keepText ? { ...m, text, headers: res.headers } : { ...m, text: text.length < 400000 ? text : '' , headers: res.headers };
}

async function login() {
  await req('wp-login.php', { label: 'login' });
  jar.set('wordpress_test_cookie', 'WP%20Cookie%20check');
  const body = new URLSearchParams({ log: 'admin', pwd: 'password', testcookie: '1', 'wp-submit': 'Acceder', redirect_to: `${base}/wp-admin/` });
  const r = await req('wp-login.php', { label: 'login', method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
  if (![...jar.keys()].some((k) => k.startsWith('wordpress_logged_in'))) throw new Error(`No se pudo iniciar sesión (HTTP ${r.status})`);
}

/** Mide una lista de peticiones con cada conjunto: una vuelta de calentamiento y REPS medidas, orden rotado. */
async function measure(items, sets = SETS, reps = REPS, extraHeaders = {}) {
  for (const item of items) {
    for (let rep = 0; rep <= reps; rep++) {
      const order = sets.map((_, i) => sets[(i + rep) % sets.length]);
      for (const set of order) {
        if (item.only && !item.only.includes(set)) continue;
        const body = typeof item.body === 'function' ? item.body(set) : item.body;
        if (item.before) await item.before(set);
        await req(item.url, { set, label: item.label, method: item.method || 'GET', body, headers: { ...(item.headers || {}), ...extraHeaders }, extra: { rep, warm: rep === 0, kind: item.kind || 'screen' } });
      }
    }
    log(`medido ${item.label}`);
  }
}

// ---------------------------------------------------------------- Pantallas
const admin = (p) => (MULTI && p.startsWith('network/') ? `wp-admin/${p}` : `wp-admin/${p}`);
const CORE = MULTI ? [
  ['red-escritorio', 'network/index.php'], ['red-sitios', 'network/sites.php'], ['red-plugins', 'network/plugins.php'],
  ['red-usuarios', 'network/users.php'], ['red-ajustes', 'network/settings.php'], ['red-actualizaciones', 'network/update-core.php'],
  ['escritorio', 'index.php'], ['entradas', 'edit.php'], ['editar-entrada', `post.php?post=${ids.post_edit}&action=edit`],
  ['paginas', 'edit.php?post_type=page'], ['medios', 'upload.php?mode=list'], ['usuarios', 'users.php'], ['ajustes-generales', 'options-general.php'],
  ['sitio2-escritorio', 'SITE2:index.php'], ['sitio2-entradas', 'SITE2:edit.php'],
] : [
  ['escritorio', 'index.php'], ['entradas', 'edit.php'], ['editar-entrada', `post.php?post=${ids.post_edit}&action=edit`],
  ['nueva-entrada', 'post-new.php'], ['paginas', 'edit.php?post_type=page'], ['medios', 'upload.php?mode=list'], ['medios-cuadricula', 'upload.php?mode=grid'],
  ['plugins', 'plugins.php'], ['usuarios', 'users.php'], ['ajustes-generales', 'options-general.php'], ['actualizaciones', 'update-core.php'],
  ['comentarios', 'edit-comments.php'],
];
const site2 = (p) => p.replace(/^SITE2:/, MULTI ? 'segundo/wp-admin/' : 'wp-admin/');
const screenUrl = (p) => (p.startsWith('SITE2:') ? site2(p) : admin(p));

/** Enlaces del menú lateral (y del de red) que aparecen con un conjunto y no sin plugins: las pantallas propias. */
async function ownScreens(set, page = 'index.php') {
  const links = async (s) => {
    const r = await req(admin(page), { set: s, label: 'menu', keepText: true });
    const menu = (r.text.match(/<ul id="adminmenu"[\s\S]*?<\/ul>\s*<\/div>/) || [''])[0];
    return new Set([...menu.matchAll(/href=['"]([^'"]+)['"]/g)].map((m) => m[1].replace(/&amp;/g, '&').replace(/^.*?\/wp-admin\//, '')).filter((h) => !/^#|^https?:/.test(h)));
  };
  const none = await links('none');
  const mine = await links(set);
  return [...mine].filter((h) => !none.has(h));
}

async function main() {
  await login();
  log('sesión iniciada');
  const dash = await req(admin(MULTI ? 'network/index.php' : 'index.php'), { set: SETS[0], label: 'nonce', keepText: true });
  const hb = (dash.text.match(/heartbeatSettings\s*=\s*\{[^}]*"nonce":"([a-f0-9]+)"/) || [])[1];
  const restNonce = (await req('wp-admin/admin-ajax.php?action=rest-nonce', { set: SETS[0], label: 'nonce', keepText: true })).text.trim();
  if (!hb) notes.push('No se encontró el nonce del heartbeat en el escritorio');

  if (PHASES.has('screens')) {
    await measure(CORE.map(([label, p]) => ({ label, url: screenUrl(p) })));
  }
  const own = {};
  if (PHASES.has('own')) {
    for (const set of SETS.filter((s) => s !== 'none')) {
      own[set] = await ownScreens(set);
      if (MULTI) own[set + ':red'] = (await ownScreens(set, 'network/index.php')).map((h) => 'network/' + h.replace(/^network\//, ''));
    }
    const all = [...new Set(Object.values(own).flat())].filter((h) => !/post-new\.php|action=elementor_new_post|elementor-app/.test(h));
    fs.writeFileSync(path.join(OUT, 'own-screens.json'), JSON.stringify(own, null, 1));
    await measure(all.map((h) => ({ label: 'propia ' + h, url: admin(h), kind: 'own', only: SETS.filter((s) => s !== 'none' && (own[s] || []).concat(own[s + ':red'] || []).includes(h)) })));
  }
  if (PHASES.has('heartbeat') && hb) {
    const body = () => new URLSearchParams({ action: 'heartbeat', _nonce: hb, screen_id: MULTI ? 'dashboard-network' : 'dashboard', interval: '60', has_focus: 'false' });
    const bodyPost = () => new URLSearchParams({ action: 'heartbeat', _nonce: hb, screen_id: 'post', interval: '15', has_focus: 'true', 'data[wp-refresh-post-lock][post_id]': String(ids.post_edit), 'data[wp-refresh-post-lock][lock]': '' });
    await measure([
      { label: 'heartbeat', url: 'wp-admin/admin-ajax.php', method: 'POST', body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, kind: 'ajax' },
      { label: 'heartbeat-edicion', url: 'wp-admin/admin-ajax.php', method: 'POST', body: bodyPost, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, kind: 'ajax' },
      { label: 'medios-ajax', url: 'wp-admin/admin-ajax.php', method: 'POST', body: () => new URLSearchParams({ action: 'query-attachments', 'query[posts_per_page]': '80', 'query[paged]': '1', 'query[orderby]': 'date', 'query[order]': 'DESC' }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, kind: 'ajax' },
    ]);
  }
  if (PHASES.has('rest')) {
    // Las peticiones REST que pide el editor de bloques al abrir (las recoge Chrome más abajo si ya corrió; si no,
    // la lista habitual de WordPress 6.8/6.9).
    const restList = [
      `wp-json/wp/v2/posts/${ids.post_edit}?context=edit&_locale=user`,
      'wp-json/wp/v2/types?context=view&_locale=user',
      'wp-json/wp/v2/users/me?_locale=user',
      'wp-json/wp/v2/settings?_locale=user',
      'wp-json/wp/v2/taxonomies?context=view&_locale=user',
      'wp-json/wp/v2/block-patterns/patterns?_locale=user',
      'wp-json/wp/v2/blocks?context=edit&per_page=100&_locale=user',
      'wp-json/wp/v2/categories?context=view&per_page=100&_fields=id%2Cname%2Cparent&_locale=user',
      'wp-json/wp/v2/global-styles/themes/' + (process.env.THEME || 'twentytwentyfive') + '?context=view&_locale=user',
      'wp-json/',
    ];
    await measure(restList.map((u) => ({ label: 'rest ' + u.replace(/^wp-json\//, '').replace(/\?.*$/, ''), url: u, headers: { 'X-WP-Nonce': restNonce }, kind: 'rest' })), SETS, REPS_FUNC);
  }
  if (PHASES.has('save')) {
    let n = 0;
    await measure([{ label: 'guardar-entrada', url: `wp-json/wp/v2/posts/${ids.post_edit}`, method: 'POST', body: () => JSON.stringify({ title: `Entrada editada ${++n}`, content: `<!-- wp:paragraph --><p>Contenido ${n}</p><!-- /wp:paragraph -->` }), headers: { 'X-WP-Nonce': restNonce, 'Content-Type': 'application/json' }, kind: 'rest' }], SETS, REPS_FUNC);
  }
  if (PHASES.has('media')) {
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAGklEQVR4nGNkaGBgIA0wkah+VMOohlENQ0kDAP+8ARHVPDlXAAAAAElFTkSuQmCC', 'base64');
    let n = 0;
    await measure([{ label: 'subir-medio', url: 'wp-json/wp/v2/media', method: 'POST', body: () => png, headers: { 'X-WP-Nonce': restNonce, 'Content-Type': 'image/png', 'Content-Disposition': `attachment; filename="subida-${Date.now()}-${++n}.png"` }, kind: 'rest' }], SETS, Math.min(REPS_FUNC, 3));
  }
  if (PHASES.has('update') && !MULTI) {
    const plugins = await req('wp-admin/plugins.php', { set: SETS[0], label: 'nonce', keepText: true });
    const nonce = (plugins.text.match(/_wpUpdatesSettings\s*=\s*\{[^}]*"ajax_nonce":"([a-f0-9]+)"/) || [])[1];
    if (!nonce) notes.push('Sin nonce de actualizaciones en Plugins');
    else {
      await measure([{ label: 'actualizar-plugin', url: 'wp-admin/admin-ajax.php', method: 'POST', kind: 'ajax',
        before: async (set) => { await req('wp-admin/index.php', { set, label: 'reset', headers: { 'X-Digi-Ap-Report': 'reset-dummy' } }); },
        body: () => new URLSearchParams({ action: 'update-plugin', _ajax_nonce: nonce, plugin: 'digi-ap-dummy/digi-ap-dummy.php', slug: 'digi-ap-dummy' }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' } }], SETS, Math.min(REPS_FUNC, 3));
    }
  }
  if (PHASES.has('cron')) {
    const before = JSON.parse(fs.readFileSync(path.join(OUT, 'cron-0.json'), 'utf8')).events.map((e) => e.hook);
    for (const set of SETS) {
      const now = await req('wp-admin/index.php', { set, label: 'cron-lista', headers: { 'X-Digi-Ap-Report': 'cron' }, keepText: true });
      try { fs.writeFileSync(path.join(OUT, `cron-${set}.json`), now.text); } catch (e) { /* nada */ }
    }
    const after = JSON.parse(fs.readFileSync(path.join(OUT, 'cron-1.json'), 'utf8')).events.map((e) => e.hook);
    const ours = [...new Set(after.filter((h) => !before.includes(h)))];
    await measure(ours.map((hook) => ({ label: 'cron ' + hook, url: 'wp-admin/index.php', headers: { 'X-Digi-Ap-Cron': hook }, kind: 'cron', only: SETS.filter((s) => s !== 'none') })), SETS, Math.min(REPS, 2));
  }
  if (PHASES.has('profile')) {
    const pick = CORE.filter(([l]) => /escritorio|entradas|editar-entrada|medios$|plugins|actualizaciones/.test(l)).map(([label, p]) => ({ label: 'perfil ' + label, url: screenUrl(p), kind: 'profile' }));
    await measure(pick, setsFor('PROFILE_SETS').filter((s) => s !== 'none'), 2, { 'X-Digi-Ap-Profile': '1' });
    if (hb) await measure([{ label: 'perfil heartbeat', url: 'wp-admin/admin-ajax.php', method: 'POST', body: () => new URLSearchParams({ action: 'heartbeat', _nonce: hb, screen_id: 'dashboard', interval: '60', has_focus: 'false' }), headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, kind: 'profile' }], setsFor('PROFILE_SETS').filter((s) => s !== 'none'), 2, { 'X-Digi-Ap-Profile': '1' });
  }
  if (PHASES.has('github')) {
    // GitHub sin respuesta: cachés de las actualizaciones vacías y tres cargas seguidas de cada pantalla, contando las
    // peticiones salientes de cada una (la sonda las corta con un error, como un tiempo de espera agotado).
    for (const set of setsFor('GITHUB_SETS').filter((s) => s !== 'none')) {
      for (const [label, p] of [['escritorio', 'index.php'], ['plugins', 'plugins.php'], ['actualizaciones', 'update-core.php'], ['entradas', 'edit.php']]) {
        await req('wp-admin/index.php', { set, label: 'github-limpiar', headers: { 'X-Digi-Ap-Report': 'clear-github' } });
        for (let i = 1; i <= 3; i++) await req(admin(MULTI ? 'network/' + p : p), { set, label: `github-caido ${label} ${i}`, headers: { 'X-Digi-Ap-Github': 'down' }, extra: { kind: 'github' } });
      }
      for (const [label, p] of [['escritorio', 'index.php'], ['plugins', 'plugins.php']]) {
        await req('wp-admin/index.php', { set, label: 'github-limpiar', headers: { 'X-Digi-Ap-Report': 'clear-github' } });
        for (let i = 1; i <= 3; i++) await req(admin(MULTI ? 'network/' + p : p), { set, label: `github-ok ${label} ${i}`, extra: { kind: 'github' } });
      }
      log(`github ${set}`);
    }
  }
  if (PHASES.has('autoload')) {
    for (const set of SETS) {
      const r = await req('wp-admin/index.php', { set, label: 'autoload', headers: { 'X-Digi-Ap-Report': 'autoload' }, keepText: true });
      fs.writeFileSync(path.join(OUT, `autoload-${set}.json`), r.text);
    }
  }
  fs.writeFileSync(path.join(OUT, 'measures.json'), JSON.stringify({ measures, notes }, null, 0));

  if (PHASES.has('chrome') || PHASES.has('builder') || PHASES.has('lazymcp')) await chrome(own);
  fs.writeFileSync(path.join(OUT, 'measures.json'), JSON.stringify({ measures, notes }, null, 0));
  log('fin');
}

// ---------------------------------------------------------------- Chrome
async function chrome(own) {
  const require = createRequire(import.meta.url);
  const { chromium } = require(process.env.PLAYWRIGHT_CORE || `${process.env.HOME}/digi-wt/pw/node_modules/playwright-core`);
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  const out = { screens: [], editor: [], builder: [] };
  const url = new URL(base);
  for (const set of [...new Set([...setsFor('CHROME_SETS'), ...setsFor('BUILDER_SETS')])]) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    await context.addCookies([...jar].map(([name, value]) => ({ name, value, domain: url.hostname, path: '/' })).concat([{ name: 'digi_ap_set', value: set, domain: url.hostname, path: '/' }]));
    const page = await context.newPage();
    const cdp = await context.newCDPSession(page);
    await cdp.send('Performance.enable');
    // Métricas del navegador de la última navegación: tiempo de script y de tareas (CDP, acumulado desde la anterior),
    // DOMContentLoaded y load, montón de JavaScript y bytes de scripts y estilos descargados (sin caché).
    let lastMetrics = {};
    const metrics = async () => {
      const m = Object.fromEntries(((await cdp.send('Performance.getMetrics').catch(() => ({ metrics: [] }))).metrics || []).map((x) => [x.name, x.value]));
      const d = (k) => Math.round(((m[k] || 0) - (lastMetrics[k] || 0)) * 1000);
      lastMetrics = m;
      const nav = await page.evaluate(() => {
        const n = performance.getEntriesByType('navigation')[0] || {};
        const res = performance.getEntriesByType('resource');
        const sum = (re) => res.filter((r) => re.test(r.name.split('?')[0])).reduce((a, r) => a + (r.decodedBodySize || 0), 0);
        return { dcl: Math.round(n.domContentLoadedEventEnd || 0), load: Math.round(n.loadEventEnd || 0), js_kb: Math.round(sum(/\.js$/) / 1024), css_kb: Math.round(sum(/\.css$/) / 1024), resources: res.length };
      }).catch(() => ({}));
      return { script_ms: d('ScriptDuration'), task_ms: d('TaskDuration'), layout_ms: d('LayoutDuration'), heap_mb: Math.round((m.JSHeapUsedSize || 0) / 1048576), nodes: m.Nodes || 0, ...nav };
    };
    let current = { console: [], pageerrors: [], failed: [], requests: [] };
    page.on('console', (m) => { if (['error', 'warning'].includes(m.type())) current.console.push(`${m.type()}: ${m.text().slice(0, 300)}`); });
    page.on('pageerror', (e) => current.pageerrors.push(String(e.message || e).slice(0, 300)));
    page.on('response', (r) => { if (r.status() >= 400) current.failed.push(`${r.status()} ${r.url().replace(base, '')}`); });
    page.on('request', (r) => { const u = r.url(); if (/wp-json|rest_route|admin-ajax/.test(u)) current.requests.push(`${r.method()} ${u.replace(base, '')}`); });
    const visit = async (label, p, { wait = 1500 } = {}) => {
      current = { console: [], pageerrors: [], failed: [], requests: [] };
      await context.setExtraHTTPHeaders({ 'X-Digi-Ap-Id': 'chrome', 'X-Digi-Ap-Label': `chrome ${label}`, 'X-Digi-Ap-Set': set });
      const t = performance.now();
      let status = 0;
      try {
        const r = await page.goto(`${base}/${p}`, { waitUntil: 'load', timeout: 240000 });
        status = r ? r.status() : 0;
        await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {});
      } catch (e) { current.pageerrors.push('navegación: ' + e.message.slice(0, 200)); }
      await sleep(wait);
      const menu = await page.evaluate(() => document.querySelectorAll('#adminmenu > li').length).catch(() => 0);
      const perf = await metrics();
      return { set, label, url: p, status, ms: Math.round(performance.now() - t), menu, perf, ...current };
    };
    if (PHASES.has('chrome') && setsFor('CHROME_SETS').includes(set)) {
      const list = CORE.map(([label, p]) => [label, screenUrl(p)]).concat((own[set] || []).concat(own[set + ':red'] || []).filter((h) => !/action=elementor_new_post/.test(h)).map((h) => ['propia ' + h, admin(h)]));
      // Dos visitas por pantalla: la segunda, con la caché del navegador caliente, es la que se compara.
      for (const [label, p] of list) { const first = await visit(label, p, { wait: 300 }); const second = await visit(label, p); out.screens.push({ ...second, first: first.perf, console: [...new Set([...first.console, ...second.console])], pageerrors: [...new Set([...first.pageerrors, ...second.pageerrors])], failed: [...new Set([...first.failed, ...second.failed])] }); }
      log(`chrome pantallas ${set}`);
      // Editor de bloques: abrir, esperar a que esté listo, cambiar el título y guardar.
      const ed = await visit('editor-bloques', admin(`post.php?post=${ids.post_edit}&action=edit`), { wait: 500 });
      const ready = await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/editor') && wp.data.select('core/editor').getCurrentPostId() && !wp.data.select('core/editor').isSavingPost() && (wp.data.select('core').hasFinishedResolution ? true : true), null, { timeout: 120000 }).then(() => true).catch(() => false);
      await sleep(3000);
      const loadRequests = [...current.requests];
      current.requests = [];
      await context.setExtraHTTPHeaders({ 'X-Digi-Ap-Id': 'chrome', 'X-Digi-Ap-Label': 'chrome editor-bloques-guardar', 'X-Digi-Ap-Set': set });
      const saved = ready ? await page.evaluate(async () => {
        const t = performance.now();
        wp.data.dispatch('core/editor').editPost({ title: 'Guardado desde Chrome ' + Date.now() });
        await wp.data.dispatch('core/editor').savePost();
        const err = wp.data.select('core/editor').didPostSaveRequestFail();
        return { ok: !err, ms: Math.round(performance.now() - t) };
      }).catch((e) => ({ ok: false, error: e.message })) : { ok: false, error: 'el editor no quedó listo' };
      await sleep(1500);
      out.editor.push({ ...ed, ready, loadRequests, save: saved, saveRequests: [...current.requests], afterSave: { console: current.console, pageerrors: current.pageerrors, failed: current.failed } });
      log(`chrome editor de bloques ${set}: listo=${ready} guardado=${JSON.stringify(saved)}`);
    }
    if (PHASES.has('builder') && set !== 'none' && setsFor('BUILDER_SETS').includes(set)) {
      for (let rep = 1; rep <= Number(process.env.BUILDER_REPS || 3); rep++) {
        const id = ids.builder[rep % ids.builder.length];
        current = { console: [], pageerrors: [], failed: [], requests: [] };
        await context.setExtraHTTPHeaders({ 'X-Digi-Ap-Id': 'chrome', 'X-Digi-Ap-Label': `chrome constructor-abrir ${rep}`, 'X-Digi-Ap-Set': set });
        const t = performance.now();
        let ready = false; let err = '';
        try {
          await page.goto(`${base}/wp-admin/post.php?post=${id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 300000 });
          await page.waitForSelector('#elementor-panel', { timeout: 300000 });
          const frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: 300000 })).contentFrame();
          await frame.waitForSelector('.elementor-widget', { timeout: 300000 });
          await page.waitForFunction(() => { const l = document.querySelector('#elementor-loading'); return !l || getComputedStyle(l).display === 'none' || l.getBoundingClientRect().width === 0; }, null, { timeout: 120000 });
          ready = true;
        } catch (e) { err = e.message.slice(0, 200); }
        const openMs = Math.round(performance.now() - t);
        await page.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {});
        const openState = { console: [...current.console], pageerrors: [...current.pageerrors], failed: [...current.failed], requests: [...current.requests] };
        current = { console: [], pageerrors: [], failed: [], requests: [] };
        await context.setExtraHTTPHeaders({ 'X-Digi-Ap-Id': 'chrome', 'X-Digi-Ap-Label': `chrome constructor-guardar ${rep}`, 'X-Digi-Ap-Set': set });
        const save = ready ? await page.evaluate(async () => {
          const t0 = performance.now();
          try {
            window.$e.internal('document/save/set-is-modified', { status: true });
            await window.$e.run('document/save/update', { force: true });
            return { ok: true, ms: Math.round(performance.now() - t0) };
          } catch (e) { return { ok: false, error: String(e && e.message || e), ms: Math.round(performance.now() - t0) }; }
        }).catch((e) => ({ ok: false, error: e.message })) : { ok: false, error: 'el editor no abrió' };
        await sleep(1500);
        await page.evaluate(() => { try { window.onbeforeunload = null; if (window.elementor && elementor.saver && elementor.saver.setFlagEditorChange) elementor.saver.setFlagEditorChange(false); } catch (e) { /* nada */ } }).catch(() => {});
        out.builder.push({ set, rep, post: id, ready, error: err, openMs, open: openState, save, saveState: { console: current.console, pageerrors: current.pageerrors, failed: current.failed, requests: current.requests } });
        log(`constructor ${set} ${rep}: listo=${ready} ${openMs} ms, guardado=${JSON.stringify(save)}`);
        await page.goto('about:blank').catch(() => {});
      }
    }
    await context.close();
  }
  // MCP del navegador a demanda: sin WebMCP no se carga; con WebMCP (simulado) se carga entero y sin errores.
  if (PHASES.has('lazymcp')) {
    out.lazymcp = [];
    for (const set of setsFor('CHROME_SETS').filter((s) => s !== 'none')) {
      for (const webmcp of [false, true]) {
        const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
        await ctx.addCookies([...jar].map(([name, value]) => ({ name, value, domain: url.hostname, path: '/' })).concat([{ name: 'digi_ap_set', value: set, domain: url.hostname, path: '/' }]));
        if (webmcp) await ctx.addInitScript(() => { const tools = []; const mc = { registerTool(t) { tools.push(t && t.name); }, unregisterTool() {}, provideContext() {}, clearContext() {}, tools }; Object.defineProperty(navigator, 'modelContext', { value: mc, configurable: true }); });
        const pg = await ctx.newPage();
        const errs = [];
        pg.on('console', (m) => { if (m.type() === 'error') errs.push(m.text().slice(0, 200)); });
        pg.on('pageerror', (e) => errs.push(String(e.message || e).slice(0, 200)));
        await ctx.setExtraHTTPHeaders({ 'X-Digi-Ap-Id': 'chrome', 'X-Digi-Ap-Label': `chrome mcp-${webmcp ? 'webmcp' : 'sin'}`, 'X-Digi-Ap-Set': set });
        await pg.goto(`${base}/wp-admin/index.php`, { waitUntil: 'load', timeout: 240000 }).catch((e) => errs.push('navegación: ' + e.message));
        await pg.waitForLoadState('networkidle', { timeout: 60000 }).catch(() => {});
        await sleep(3000);
        const facts = await pg.evaluate(() => ({
          capabilities: !!(window.elementorV2 && window.elementorV2.elementorCapabilitiesMcp),
          editorMcp: !!(window.elementorV2 && window.elementorV2.editorMcp),
          loader: !!document.getElementById('digitalisimo-elements-browser-mcp'),
          js_kb: Math.round(performance.getEntriesByType('resource').filter((r) => /\.js$/.test(r.name.split('?')[0])).reduce((a, r) => a + (r.decodedBodySize || 0), 0) / 1024),
          mcp_scripts: performance.getEntriesByType('resource').filter((r) => /packages\/(editor-mcp|elementor-mcp-common|schema|elementor-capabilities-mcp)\//.test(r.name)).length,
        })).catch((e) => ({ error: e.message }));
        out.lazymcp.push({ set, webmcp, ...facts, errors: errs });
        log(`mcp ${set} webmcp=${webmcp}: ${JSON.stringify(facts)} errores=${errs.length}`);
        await ctx.close();
      }
    }
  }
  await browser.close();
  fs.writeFileSync(path.join(OUT, 'chrome.json'), JSON.stringify(out, null, 1));
}

main().catch((e) => { console.error(e); fs.writeFileSync(path.join(OUT, 'measures.json'), JSON.stringify({ measures, notes, error: String(e.stack || e) })); process.exit(1); });
