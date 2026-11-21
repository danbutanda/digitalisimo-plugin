// Recorrido de admin-perf-all.sh en Chrome: cada pantalla del escritorio con cada conjunto de plugins (cookie digi_ap_set),
// varias cargas, más las peticiones de fondo (heartbeat, REST del editor, la consulta de 30 s de Backups), los escenarios
// de los actualizadores, una vuelta de WP-Cron y las acciones que no pueden romperse (guardar una entrada, subir un medio,
// actualizar un plugin). Las métricas del servidor las escribe la sonda en metrics.jsonl; aquí se juntan con los errores
// de JavaScript de cada pantalla y se dejan en raw.json (report.cjs las resume).
// Uso: node run.cjs <modo single|multisite> <puerto> <host> <carpeta de la corrida>
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const path = require('path');

const [mode, port, host, dir] = process.argv.slice(2);
const base = `http://${host}`;
const network = mode === 'multisite';
const SETS = (process.env.SETS || 'none,backups,tools,theme,seo,ia,hosting,elements,migration,mine,all').split(',').filter(Boolean);
const LOADS = Number(process.env.LOADS || 3);
const PROFILE_SETS = (process.env.PROFILE_SETS || 'mine,all').split(',').filter(Boolean);
const FUNCTIONAL = process.env.FUNCTIONAL !== '0';
const metricsFile = path.join(dir, 'probe', 'metrics.jsonl');
const raw = { mode, sets: SETS, loads: LOADS, screens: {}, ajax: {}, functional: {}, updaters: {}, cron: {}, state: null, posts: null, js: {} };
let offset = 0;
let seq = 0;

function readMetrics() {
  if (!fs.existsSync(metricsFile)) return [];
  const text = fs.readFileSync(metricsFile, 'utf8');
  const chunk = text.slice(offset);
  offset = text.length;
  return chunk.split('\n').filter(Boolean).map((line) => { try { return JSON.parse(line); } catch (e) { return null; } }).filter(Boolean);
}
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

(async () => {
  const browser = await puppeteer.launch({
    executablePath: process.env.CHROME_PATH || '/usr/bin/google-chrome', headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', `--host-resolver-rules=MAP ${host} 127.0.0.1:${port}, MAP * ~NOTFOUND`],
  });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  page.setDefaultTimeout(180000);
  let js = [];
  page.on('console', (msg) => { if (msg.type() === 'error') js.push({ kind: 'console', text: msg.text().slice(0, 300), url: (msg.location() || {}).url || '' }); });
  page.on('pageerror', (err) => js.push({ kind: 'pageerror', text: String(err && err.message || err).slice(0, 300) }));
  page.on('requestfailed', (req) => { if (req.url().startsWith(base)) js.push({ kind: 'requestfailed', text: `${req.failure() && req.failure().errorText} ${req.url().slice(0, 200)}` }); });
  page.on('response', (res) => { if (res.url().startsWith(base) && res.status() >= 400 && !/favicon/.test(res.url())) js.push({ kind: 'http', text: `${res.status()} ${res.url().slice(0, 200)}` }); });

  async function setCookies(set, req, extra = {}) {
    const cookies = [{ name: 'digi_ap_set', value: set }, { name: 'digi_ap_req', value: req }];
    for (const [name, value] of Object.entries(extra)) cookies.push({ name, value: String(value) });
    await page.setCookie(...cookies.map((c) => ({ ...c, url: base })));
  }
  async function clearExtra() {
    for (const name of ['digi_ap_prof', 'digi_ap_gh', 'digi_ap_delay']) await page.deleteCookie({ name, url: base });
  }
  async function cmd(name) {
    const res = await page.goto(`${base}/wp-admin/?digi_ap=${name}`, { waitUntil: 'domcontentloaded' });
    const text = await res.text();
    try { return JSON.parse(text); } catch (e) { return { error: text.slice(0, 300) }; }
  }

  // Inicio de sesión (sin conjunto: todos activos, como quedó la base).
  // Con el servidor ocupado, la primera carga de wp-login.php puede no dejar la cookie de prueba y el envío vuelve al
  // formulario: se reintenta hasta llegar al escritorio.
  for (let attempt = 1; ; attempt++) {
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle2' });
    await page.$eval('#user_login', (el) => { el.value = 'admin'; });
    await page.$eval('#user_pass', (el) => { el.value = 'password'; });
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
    if (page.url().includes('/wp-admin')) break;
    if (attempt >= 4) throw new Error(`No se pudo iniciar sesión: ${page.url()}`);
  }
  raw.posts = await cmd('posts');
  const P = raw.posts;

  const admin = network ? `${base}/wp-admin` : `${base}/wp-admin`;
  const net = `${base}/wp-admin/network`;
  const CORE = network ? [
    ['red-escritorio', `${net}/index.php`], ['red-sitios', `${net}/sites.php`], ['red-plugins', `${net}/plugins.php`], ['red-temas', `${net}/themes.php`],
    ['red-ajustes', `${net}/settings.php`], ['red-actualizaciones', `${net}/update-core.php`], ['red-usuarios', `${net}/users.php`],
    ['escritorio', `${admin}/index.php`], ['entradas', `${admin}/edit.php`], ['editar-entrada', `${admin}/post.php?post=${P.post}&action=edit`, 'editor'],
    ['medios', `${admin}/upload.php?mode=list`], ['usuarios', `${admin}/users.php`], ['ajustes', `${admin}/options-general.php`],
    ['subsitio-escritorio', `${base}/uno/wp-admin/index.php`], ['subsitio-entradas', `${base}/uno/wp-admin/edit.php`],
  ] : [
    ['escritorio', `${admin}/index.php`], ['entradas', `${admin}/edit.php`], ['editar-entrada', `${admin}/post.php?post=${P.post}&action=edit`, 'editor'],
    ['paginas', `${admin}/edit.php?post_type=page`], ['medios', `${admin}/upload.php?mode=list`], ['medios-cuadricula', `${admin}/upload.php?mode=grid`],
    ['plugins', `${admin}/plugins.php`], ['usuarios', `${admin}/users.php`], ['ajustes', `${admin}/options-general.php`], ['actualizaciones', `${admin}/update-core.php`],
  ];
  const nb = network ? `${net}/admin.php?page=digitalisimo-network-backups` : `${admin}/admin.php?page=digitalisimo-backups`;
  const nt = network ? `${net}/admin.php?page=digitalisimo-tools-elementor-templates` : `${admin}/admin.php?page=digitalisimo-tools-elementor-templates`;
  const OWN = {
    backups: [['backups-respaldos', `${nb}&tab=backups`], ['backups-ajustes', `${nb}&tab=settings`], ['backups-destinos', `${nb}&tab=servers`], ['backups-herramientas', `${nb}&tab=tools`]],
    tools: [...(network ? [['tools-plantillas', nt]] : []), ['tools-medios', `${nt}&tab=webp`], ['tools-sitio', `${nt}&tab=site`], ['tools-php', `${nt}&tab=php`], ['catalogo', network ? `${net}/admin.php?page=digitalisimo-plugins` : `${admin}/admin.php?page=digitalisimo-plugins`]],
    seo: [['seo', network ? `${net}/admin.php?page=digitalisimo-seo` : `${admin}/admin.php?page=digitalisimo-seo`]],
    ia: [['ia', `${admin}/admin.php?page=digitalisimo-ai`]],
    hosting: [['hosting', `${admin}/admin.php?page=digitalisimo-hosting`]],
    elements: [['elements-rendimiento', `${admin}/admin.php?page=digitalisimo-elements-front`]],
    migration: [['migracion', `${admin}/tools.php?page=digitalisimo-ep-migration`]],
  };
  const PARTS = { none: [], backups: ['backups'], tools: ['tools'], theme: [], seo: ['seo'], ia: ['ia'], hosting: ['hosting'], elements: ['elements'], migration: ['elements', 'migration'], mine: ['backups', 'tools'], all: ['backups', 'tools', 'seo', 'ia', 'hosting', 'elements', 'migration'] };

  async function load(set, name, url, kind, opts = {}) {
    const req = `${set}.${name}.${++seq}`;
    await setCookies(set, req, opts.cookies || {});
    js = [];
    let status = 0;
    const started = Date.now();
    try {
      const res = await page.goto(url, { waitUntil: kind === 'editor' ? 'domcontentloaded' : 'load', timeout: 180000 });
      status = res ? res.status() : 0;
      if (kind === 'editor') {
        await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/editor') && wp.data.select('core/editor').getCurrentPostId() && document.querySelector('.editor-styles-wrapper, iframe[name="editor-canvas"]'), { timeout: 180000 });
      }
      await sleep(opts.settle || 800);
    } catch (e) {
      js.push({ kind: 'nav', text: String(e.message).slice(0, 300) });
    }
    const wall = Date.now() - started;
    const rows = readMetrics().filter((r) => r.req === req);
    const pathOnly = url.replace(base, '').split('#')[0];
    const main = rows.find((r) => r.method === 'GET' && pathOnly.startsWith(r.uri.split('?')[0]) && r.uri.split('?')[0] !== '/wp-admin/admin-ajax.php') || rows[0] || null;
    return { req, status, wall, main, others: rows.filter((r) => r !== main).map((r) => ({ method: r.method, uri: r.uri.slice(0, 160), ms: r.ms, queries: r.queries, http: r.http, errors: r.errors, status: r.status })), js: js.slice() };
  }

  async function measureScreens(set, list) {
    for (const [name, url, kind] of list) {
      const key = `${set}|${name}`;
      const runs = [];
      for (let i = 0; i <= LOADS; i++) {
        const r = await load(set, name, url, kind);
        if (i > 0) runs.push(r); // la primera calienta
      }
      if (PROFILE_SETS.includes(set)) runs.push({ ...(await load(set, name, url, kind, { cookies: { digi_ap_prof: 1 } })), profile: true });
      await clearExtra();
      raw.screens[key] = runs;
      const ms = runs.filter((r) => !r.profile && r.main).map((r) => r.main.ms).sort((a, b) => a - b);
      console.log(`${mode} ${key}: ${ms.length ? ms[Math.floor(ms.length / 2)] : '—'} ms, ${runs[0] && runs[0].main ? runs[0].main.queries : '—'} consultas, JS ${runs.reduce((n, r) => n + r.js.length, 0)}`);
    }
  }

  // Peticiones de fondo desde una pantalla ya abierta (con su nonce): heartbeat, REST del editor y la consulta de Backups.
  async function ajax(set, name, setupUrl, fn, times = 5) {
    const key = `${set}|${name}`;
    await setCookies(set, `${set}.${name}.setup.${++seq}`);
    await page.goto(setupUrl, { waitUntil: 'load' });
    readMetrics();
    const runs = [];
    for (let i = 0; i <= times; i++) {
      const req = `${set}.${name}.${++seq}`;
      await setCookies(set, req);
      js = [];
      let result = null;
      try { result = await page.evaluate(fn); } catch (e) { result = { error: String(e.message).slice(0, 200) }; }
      await sleep(150);
      const rows = readMetrics().filter((r) => r.req === req);
      if (i > 0) runs.push({ result, rows: rows.map((r) => ({ method: r.method, uri: r.uri.slice(0, 160), action: r.action, ms: r.ms, queries: r.queries, mem_peak_kb: r.mem_peak_kb, http: r.http, errors: r.errors, status: r.status, cb: r.cb })), js: js.slice() });
    }
    raw.ajax[key] = runs;
    const ms = runs.map((r) => (r.rows[0] || {}).ms).filter((v) => v !== undefined).sort((a, b) => a - b);
    console.log(`${mode} ${key}: ${ms.length ? ms[Math.floor(ms.length / 2)] : '—'} ms (${JSON.stringify(runs[0] && runs[0].result).slice(0, 120)})`);
  }
  const heartbeat = () => fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams({ action: 'heartbeat', _nonce: window.heartbeatSettings && heartbeatSettings.nonce || '', screen_id: 'dashboard', has_focus: 'true', interval: '60', 'data[wp-auth-check]': 'true' }).toString() }).then((r) => r.status);
  const restPost = (id) => fetch(`/wp-json/wp/v2/posts/${id}?context=edit&_locale=user`, { credentials: 'same-origin', headers: { 'X-WP-Nonce': window.wpApiSettings ? wpApiSettings.nonce : '' } }).then((r) => r.status);
  const backupsPoll = () => fetch(window.digitalisimoBackupsManual.url, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }, body: new URLSearchParams({ action: 'digitalisimo_backups_job_status', nonce: digitalisimoBackupsManual.jobsNonce, context: digitalisimoBackupsManual.context, peek: '1' }).toString() }).then((r) => r.status);

  for (const set of SETS) {
    await measureScreens(set, CORE);
    const own = [];
    for (const part of PARTS[set] || []) own.push(...(OWN[part] || []));
    if (own.length) await measureScreens(set, own);
    const home = network ? `${admin}/index.php` : `${admin}/index.php`;
    await ajax(set, 'heartbeat', home, heartbeat);
    await ajax(set, 'rest-entrada', home, `(${restPost.toString()})(${P.post})`);
    if ((PARTS[set] || []).includes('backups')) await ajax(set, 'backups-consulta-30s', `${nb}&tab=backups`, backupsPoll);
  }

  // Estado (autoload, cron) con todo activo, tras recorrer las pantallas.
  await setCookies('all', `state.${++seq}`);
  raw.state = await cmd('state');
  if (network) {
    await page.goto(`${base}/uno/wp-admin/?digi_ap=state`, { waitUntil: 'domcontentloaded' });
    try { raw.stateSub = JSON.parse(await page.evaluate(() => document.body.innerText)); } catch (e) { raw.stateSub = null; }
  }

  // Actualizadores: caché vacía (a los 10 min), caché llena y GitHub caído, en el Escritorio y en Plugins.
  const dash = network ? `${net}/index.php` : `${admin}/index.php`;
  const plg = network ? `${net}/plugins.php` : `${admin}/plugins.php`;
  for (const set of (process.env.UPD_SETS || 'all,mine').split(',').filter(Boolean)) {
    const steps = [];
    const step = async (label, url, cookies) => { const r = await load(set, `upd-${label}`, url, '', { cookies }); steps.push({ label, ms: r.main && r.main.ms, http: r.main ? r.main.http : [], others: r.others.map((o) => o.http).flat(), queries: r.main && r.main.queries }); await clearExtra(); };
    await setCookies(set, `expire.${++seq}`); await cmd('expire');
    await step('escritorio-cache-vacia-300ms', dash, { digi_ap_delay: 300 });
    await step('escritorio-cache-llena', dash, {});
    await step('plugins-cache-llena', plg, {});
    await setCookies(set, `expire.${++seq}`); await cmd('expire');
    await step('escritorio-github-caido', dash, { digi_ap_gh: 'fail' });
    await step('escritorio-github-caido-2', dash, { digi_ap_gh: 'fail' });
    await step('plugins-github-caido', plg, { digi_ap_gh: 'fail' });
    await setCookies(set, `expire.${++seq}`); await cmd('expire');
    await step('escritorio-tras-caida', dash, {});
    raw.updaters[set] = steps;
    console.log(`${mode} actualizadores ${set}: ${steps.map((s) => `${s.label}=${s.http.length}/${s.ms}ms`).join(' ')}`);
  }

  // Una vuelta de WP-Cron con los eventos periódicos de los plugins vencidos.
  for (const set of (process.env.UPD_SETS || 'all,mine').split(',').filter(Boolean)) {
    await setCookies(set, `crondue.${++seq}`);
    const moved = await cmd('cron_due');
    const req = `${set}.cron.${++seq}`;
    await setCookies(set, req);
    // Sin ?doing_wp_cron: con él, wp-cron.php sólo corre si coincide con el cerrojo que pone spawn_cron().
    await page.goto(`${base}/wp-cron.php`, { waitUntil: 'load' }).catch(() => {});
    await sleep(500);
    const rows = readMetrics().filter((r) => r.req === req);
    raw.cron[set] = { moved: moved.moved, rows: rows.map((r) => ({ uri: r.uri, ms: r.ms, queries: r.queries, mem_peak_kb: r.mem_peak_kb, http: r.http, errors: r.errors })) };
    console.log(`${mode} cron ${set}: ${(moved.moved || []).length} eventos, ${rows.map((r) => `${r.ms} ms, ${r.http.length} HTTP`).join('; ')}`);
  }

  // Lo que no puede romperse, con ninguno, con los propios y con todos.
  if (FUNCTIONAL) {
    for (const set of ['none', 'mine', 'all']) {
      const f = {};
      // Guardar una entrada en el editor de bloques.
      let r = await load(set, 'guardar', `${admin}/post.php?post=${P.post}&action=edit`, 'editor');
      const req = `${set}.guardar.save.${++seq}`;
      await setCookies(set, req);
      js = [];
      f.save = await page.evaluate(async (title) => {
        const { dispatch, select } = wp.data;
        dispatch('core/editor').editPost({ title });
        await dispatch('core/editor').savePost();
        for (let i = 0; i < 600 && select('core/editor').isSavingPost(); i++) await new Promise((res) => setTimeout(res, 100));
        return { ok: select('core/editor').didPostSaveRequestSucceed(), title: select('core/editor').getEditedPostAttribute('title') };
      }, `Entrada guardada ${set} ${Date.now()}`).catch((e) => ({ ok: false, error: String(e.message).slice(0, 200) }));
      await sleep(500);
      f.saveRows = readMetrics().filter((x) => x.req === req).map((x) => ({ method: x.method, uri: x.uri.slice(0, 120), ms: x.ms, queries: x.queries, http: x.http, errors: x.errors, status: x.status, cb: x.cb }));
      f.saveJs = js.slice().concat(r.js);
      // Subir un medio por async-upload.php, como la biblioteca.
      r = await load(set, 'subir', `${admin}/media-new.php`, '');
      const ureq = `${set}.subir.upload.${++seq}`;
      await setCookies(set, ureq);
      js = [];
      f.upload = await page.evaluate(async () => {
        const c = document.createElement('canvas'); c.width = 1600; c.height = 1000;
        const g = c.getContext('2d'); for (let i = 0; i < 300; i++) { g.fillStyle = `hsl(${(i * 37) % 360},70%,50%)`; g.fillRect((i * 53) % 1600, (i * 97) % 1000, 120, 90); }
        const blob = await new Promise((res) => c.toBlob(res, 'image/jpeg', 0.85));
        // media-new.php deja el nonce en wpUploaderInit; el modal de Medios, en _wpPluploadSettings.
        const s = (window.wpUploaderInit && wpUploaderInit.multipart_params) || (window._wpPluploadSettings && _wpPluploadSettings.defaults.multipart_params);
        const fd = new FormData(); fd.append('name', `subida-${Date.now()}.jpg`); fd.append('action', 'upload-attachment'); fd.append('_wpnonce', s ? s._wpnonce : ''); fd.append('async-upload', blob, `subida-${Date.now()}.jpg`);
        const res = await fetch('/wp-admin/async-upload.php', { method: 'POST', credentials: 'same-origin', body: fd });
        const text = await res.text(); let json = null; try { json = JSON.parse(text); } catch (e) {}
        return { status: res.status, ok: !!(json && json.success), id: json && json.data && json.data.id, sizes: json && json.data && json.data.sizes ? Object.keys(json.data.sizes) : [], text: json ? '' : text.slice(0, 200) };
      }).catch((e) => ({ ok: false, error: String(e.message).slice(0, 200) }));
      await sleep(500);
      f.uploadRows = readMetrics().filter((x) => x.req === ureq).map((x) => ({ method: x.method, uri: x.uri.slice(0, 120), ms: x.ms, queries: x.queries, mem_peak_kb: x.mem_peak_kb, http: x.http, errors: x.errors, status: x.status }));
      f.uploadJs = js.slice().concat(r.js);
      raw.functional[set] = f;
      console.log(`${mode} funcional ${set}: guardar ${JSON.stringify(f.save)} · subir ${JSON.stringify(f.upload).slice(0, 160)}`);
    }
    // Actualizar un plugin desde Plugins con todo activo (Backups hace antes su respaldo previo, si está configurado así).
    const ureq = `all.actualizar.${++seq}`;
    await setCookies('all', ureq);
    js = [];
    await page.goto(plg, { waitUntil: 'load' });
    const started = Date.now();
    const result = await page.evaluate(async () => {
      const row = document.querySelector('tr[data-plugin="digi-ap-dummy/digi-ap-dummy.php"]');
      const link = document.querySelector('#digi-ap-dummy-update .update-link, tr.plugin-update-tr[data-plugin="digi-ap-dummy/digi-ap-dummy.php"] .update-link');
      if (!row || !link) return { ok: false, error: 'sin enlace de actualización' };
      window.confirm = () => true;
      link.click();
      for (let i = 0; i < 3600; i++) {
        await new Promise((res) => setTimeout(res, 200));
        const box = document.querySelector('#digi-ap-dummy-update .update-message, tr.plugin-update-tr[data-plugin="digi-ap-dummy/digi-ap-dummy.php"] .update-message');
        if (box && box.classList.contains('updated-message')) return { ok: true, text: box.textContent.trim().slice(0, 160) };
        if (box && box.classList.contains('notice-error')) return { ok: false, text: box.textContent.trim().slice(0, 300) };
      }
      return { ok: false, error: 'tiempo agotado' };
    }).catch((e) => ({ ok: false, error: String(e.message).slice(0, 200) }));
    raw.functional.update = { result, seconds: Math.round((Date.now() - started) / 1000), js: js.slice(), rows: readMetrics().filter((x) => x.req === ureq).map((x) => ({ method: x.method, uri: x.uri.slice(0, 120), action: x.action, ms: x.ms, http: x.http, errors: x.errors, status: x.status })) };
    console.log(`${mode} funcional actualizar: ${JSON.stringify(result)} en ${raw.functional.update.seconds} s`);
  }

  fs.writeFileSync(path.join(dir, 'raw.json'), JSON.stringify(raw));
  await browser.close();
})().catch((e) => { console.error(e); try { fs.writeFileSync(path.join(dir, 'raw.json'), JSON.stringify(raw)); } catch (x) {} process.exit(1); });
