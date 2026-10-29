// Escenarios del grupo `resilience` de woo-flow.sh (F4-Woo, V.10, V.16.4 y V.17 criterios 3, 8 y 23): W0.8 y W0.9 (sin
// WooCommerce), W0.10 (red: primera petición anónima a un sitio nuevo), W3 (caché de página simulada) y W13 (vuelta
// atrás a 4.3.0.165 y guarda hacia delante). La preparación (setup/resilience.php) deja en cada sitio las páginas
// `digi-wf-res-sin-woo`, `digi-wf-res-cache`, `digi-wf-res-vuelta` y `digi-wf-res-futuro` y la plantilla de bucle
// `digi-wf-res-bucle`; las operaciones de sonda están en ops/resilience.php.
//
// - W0 amplía el W0 de run.cjs (`api.base.W0`) y sustituye sus dos pendientes:
//   - W0.8: con los siete widgets en una página, desactivar WooCommerce (como la pantalla Plugins) deja el front sin
//     salida ni errores y `woo/*` en 404; el editor (Chrome) los pinta con el aviso y ocultos del panel; se guarda
//     desde el editor (`$e.run('document/save/update')`) y, tras reactivar WooCommerce, el md5 de los ajustes de cada
//     widget es el mismo.
//   - W0.9: un kit exportado de este sitio con K se importa en sitios aparte sin WooCommerce (Playground en el puerto
//     9494 y, en paralelo, el 9490), uno por camino: la pantalla «Kits», la App (REST de importación) y WP-CLI
//     (wp-cli.phar dentro del Playground, como el paso «wp-cli» de los blueprints). Mismos marcadores y mismo informe.
//   - W0.10 (sólo NET=1): la primera petición anónima a un sitio recién creado de la red responde 200 sin fatales.
// - W3: dos visitantes con su propio carrito y su propia lista; Chrome sirve al segundo el HTML del primero
//   (intercepción de la petición) y los dos HTML se comparan byte a byte sin nonces; cada uno ve su estado tras los
//   fragmentos. Se repite con la caché de elementos del núcleo encendida (W3.3), además de la corrida CACHE=1.
// - W13: el árbol de trabajo (4.3.0.166, con F4-Woo) y el ZIP publicado de 4.3.0.165 se alternan en la misma carpeta
//   del plugin (la copia fija $OUT/src/digitalisimo-elements, montada en el Playground: se cambia el directorio en el
//   disco, como una actualización), y el guardado del editor se hace con las mismas peticiones que el editor
//   (`elementor_ajax`: `get_document_config` y `save_builder`; `get_revisions` y `get_revision_data` para Historial →
//   Revisiones). La guarda hacia delante se prueba además en el editor real con Chrome (fuera de QUICK).
'use strict';
const fs = require('fs');
const path = require('path');
const http = require('http');
const crypto = require('crypto');
const { spawn, execFileSync } = require('child_process');

const NAMES = [
  'digitalisimo-woo-shipping-bar', 'digitalisimo-woo-wishlist', 'digitalisimo-woo-wishlist-button', 'digitalisimo-woo-quick-view',
  'digitalisimo-woo-swatches', 'digitalisimo-woo-price-filter', 'digitalisimo-woo-attribute-filter',
];
const ASSETS = /\/assets\/(?:js|css)\/(?:wishlist|quick-view|swatches|cart-extras|filters)[.-]/;
const OLD_ZIP_DEFAULT = '/data/visual-studio-code/digitalisimo-plugin/dist/digitalisimo-elements-4.3.0.165.zip';
const OLD_ZIP_SHA = 'ed6f43a2ce1c41b9db6eabe46ff772a7cf1d5a7bb4475f4588789bde61808e40';
const NOWOO_PORTS = { a: 9494, b: 9490 };
/** ¿Pinta la página ese widget? (`…-wishlist` no debe casar con `…-wishlist-button`). */
const hasWidget = (html, name) => new RegExp(`elementor-widget-${name}(?![-\\w])`).test(html);
/** Las fuentes de Google del kit por defecto (núcleo, no F4-Woo): Chrome no resuelve nada fuera del sitio. */
const FONTS = /^net::ERR_NAME_NOT_RESOLVED https:\/\/fonts\.(?:googleapis|gstatic)\.com\//;
/** Errores y peticiones de un visitante sin lo que sólo causan esas fuentes bloqueadas. */
const realFacts = (facts) => {
  const fonts = facts.outside.filter((x) => FONTS.test(x));
  const outside = facts.outside.filter((x) => !FONTS.test(x));
  let skip = fonts.length;
  const errors = facts.errors.filter((e) => !(/^Failed to load resource: net::ERR_NAME_NOT_RESOLVED$/.test(e) && skip-- > 0));
  return { errors, outside, failed: facts.failed, fonts: fonts.length };
};
const PHP_NOTICE = /<b>(?:Warning|Notice|Deprecated|Fatal error|Parse error)<\/b>:|There has been a critical error|Ha habido un error crítico/i;

module.exports = (api) => {
  const { ENV, OUT, BASE, PORT, HOST, TOKEN, NET, QUICK, SITES, summary, sleep, log, check, pending, visitor, shot, settled } = api;
  const LIVE = ENV.LIVE === '1';
  const ROOT = ENV.ROOT;
  const SRC_ELEMENTS = LIVE ? ENV.ELEMENTS_DIR : path.join(OUT, 'src', 'digitalisimo-elements');
  const SRC_THEME = LIVE ? path.join(ROOT, 'digitalisimo-theme') : path.join(OUT, 'src', 'digitalisimo-theme');
  const RUNS = process.env.SCENARIOS ? process.env.SCENARIOS.split(',').map((x) => x.trim()) : null;
  const runs = (id) => !RUNS || RUNS.includes(id);
  const SITE = SITES[0];
  const short = (v, n = 600) => String(typeof v === 'string' ? v : JSON.stringify(v)).slice(0, n);
  const res = () => (((summary.sites[SITE.path] || {}).setup) || {}).resilience || null;
  const setupOf = () => ((summary.sites[SITE.path] || {}).setup) || {};

  // ── HTTP con host y cookies (el sitio de la corrida y los sitios sin WooCommerce) ──────────────────────────────
  /** Peticiones que el servidor cortó sin respuesta y se repitieron (un worker de php-wasm que se reinicia). */
  const hiccups = [];
  summary.resilience = { hiccups }; // summary.json las lleva (se escribe tras cada escenario).
  function httpTo(port, host) {
    const once = (method, url, { headers = {}, body = null, jar = null } = {}) => {
      const u = new URL(url, `http://${host}/`);
      const h = { Host: host, ...headers };
      if (jar && Object.keys(jar).length) h.Cookie = Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ');
      if (body !== null && !Buffer.isBuffer(body)) body = Buffer.from(String(body));
      if (body !== null) h['Content-Length'] = body.length;
      return new Promise((resolve, reject) => {
        const req = http.request({ host: '127.0.0.1', port, method, path: u.pathname + u.search, headers: h, timeout: 300000 }, (r) => {
          const chunks = [];
          r.on('data', (c) => chunks.push(c));
          r.on('end', () => {
            if (jar) {
              for (const c of [].concat(r.headers['set-cookie'] || [])) {
                const pair = c.split(';')[0];
                const i = pair.indexOf('=');
                const name = pair.slice(0, i).trim();
                const value = pair.slice(i + 1).trim();
                if (/expires=Thu, 01[- ]Jan[- ]1970/i.test(c) || value === 'deleted') delete jar[name]; else jar[name] = value;
              }
            }
            const text = Buffer.concat(chunks).toString('utf8');
            let json = null;
            try { json = JSON.parse(text); } catch (e) { /* no es JSON */ }
            resolve({ status: r.statusCode, headers: r.headers, text, json });
          });
        });
        req.on('error', reject);
        req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${url}`)));
        if (body !== null) req.write(body);
        req.end();
      });
    };
    // Un corte sin respuesta («socket hang up», ECONNRESET) se repite dos veces y queda anotado: si lo causa el
    // código, se repite igual y la comprobación falla.
    return async (method, url, opts = {}) => {
      for (let i = 0; ; i++) {
        try { return await once(method, url, opts); } catch (e) {
          if (i >= 2 || !/socket hang up|ECONNRESET/.test(String(e.message || e))) throw e;
          hiccups.push(`${method} ${String(url).slice(0, 120)} (${e.message})`);
          log(`   resilience: ${new Date().toISOString().slice(11, 19)} ${method} ${String(url).slice(0, 80)} sin respuesta (${e.message}); se repite`);
          await sleep(3000);
        }
      }
    };
  }
  const main = httpTo(PORT, HOST);
  const qs = (o) => new URLSearchParams(o).toString();

  /** Operación de ops/resilience.php en el sitio de la corrida. */
  async function op(name, params = {}) {
    const r = await main('GET', `${SITE.path}?${qs({ digi_wf: name, token: TOKEN, ...params })}`);
    if (r.status !== 200 || !r.json) throw new Error(`?digi_wf=${name}: ${r.status} ${r.text.slice(0, 300)}`);
    if (r.json.error) throw new Error(`?digi_wf=${name}: ${r.json.error}`);
    return r.json;
  }

  async function login(srv, base = '') {
    const jar = {};
    await srv('GET', `${base}/wp-login.php`, { jar });
    jar.wordpress_test_cookie = 'WP%20Cookie%20check';
    const form = qs({ log: 'admin', pwd: 'password', 'wp-submit': 'Acceder', testcookie: '1', redirect_to: `${base}/wp-admin/` });
    const r = await srv('POST', `${base}/wp-login.php`, { jar, body: form, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    if (r.status !== 302) throw new Error(`No se pudo iniciar sesión como admin (${r.status})`);
    return jar;
  }

  // ── Peticiones del editor (elementor_ajax), como las hace el editor ────────────────────────────────────────────
  async function editorPage(srv, jar, id) {
    const r = await srv('GET', `/wp-admin/post.php?post=${id}&action=elementor`, { jar });
    const m = r.text.match(/"ajax":\{"url":"[^"]*","nonce":"([0-9a-f]{10})"/);
    return { status: r.status, nonce: m ? m[1] : '', notice: PHP_NOTICE.test(r.text) };
  }
  async function eajax(srv, jar, nonce, id, actions) {
    const body = qs({ action: 'elementor_ajax', _nonce: nonce, editor_post_id: String(id), initial_document_id: String(id), actions: JSON.stringify(actions) });
    const r = await srv('POST', '/wp-admin/admin-ajax.php', { jar, body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } });
    const responses = (r.json && r.json.data && r.json.data.responses) || {};
    return { http: r.status, success: !!(r.json && r.json.success), responses, text: r.json ? '' : r.text.slice(0, 300) };
  }
  const walk = (nodes, fn) => { for (const n of nodes || []) { fn(n); walk(n.elements, fn); } };
  const setHeading = (id, title) => (els) => { walk(els, (n) => { if (n.id === id) n.settings = { ...(Array.isArray(n.settings) ? {} : n.settings), title }; }); return els; };
  /** Abrir (cargar la configuración del documento) y guardar con `save_builder`; `transform` cambia el árbol antes. */
  async function editorSave(srv, jar, id, transform = null) {
    const page = await editorPage(srv, jar, id);
    if (page.status !== 200 || !page.nonce) return { ok: false, why: `editor ${page.status}, sin nonce`, page };
    const cfg = await eajax(srv, jar, page.nonce, id, { cfg: { action: 'get_document_config', data: { id } } });
    const doc = cfg.responses.cfg;
    if (!doc || !doc.success || !doc.data || !Array.isArray(doc.data.elements)) return { ok: false, why: `get_document_config: ${short(doc || cfg.text, 300)}`, page };
    const loaded = JSON.parse(JSON.stringify(doc.data.elements));
    const elements = transform ? transform(JSON.parse(JSON.stringify(doc.data.elements))) : doc.data.elements;
    const save = await eajax(srv, jar, page.nonce, id, { save: { action: 'save_builder', data: { status: 'publish', elements, settings: { post_status: 'publish' } } } });
    const sr = save.responses.save;
    return { ok: !!(sr && sr.success), why: sr && !sr.success ? short(sr, 300) : save.text, loaded, page, nonce: page.nonce };
  }
  const types = (els) => { const out = []; walk(els, (n) => { if (n.elType === 'widget') out.push(n.widgetType); }); return out; };
  const md5Of = (doc, prefix) => Object.fromEntries((doc.widgets || []).filter((w) => w.id.startsWith(prefix) && NAMES.includes(w.type)).map((w) => [w.id, w.md5]));

  // ── Chrome: editor real con sesión de administrador ─────────────────────────────────────────────────────────────
  /** Contexto de Chrome con la sesión del administrador (se inicia en wp-login.php como una persona). */
  async function adminChrome() {
    const v = await visitor();
    const { page } = v;
    v.pageErrors = [];
    page.on('pageerror', (e) => v.pageErrors.push(String(e.message || e).slice(0, 300)));
    await page.setViewport({ width: 1500, height: 950 });
    await page.goto(`${BASE}${SITE.path}wp-login.php`, { waitUntil: 'load' });
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ waitUntil: 'load' }), page.click('#wp-submit')]);
    return v;
  }
  /** Abre (o vuelve a abrir) el editor de un documento en esa pestaña y espera a la vista previa pintada. */
  async function loadEditor(v, id) {
    const { page } = v;
    v.pageErrors.length = 0;
    await page.goto(`${BASE}${SITE.path}wp-admin/post.php?post=${id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 240000 });
    await page.waitForSelector('#elementor-panel', { timeout: 180000 });
    v.frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: 180000 })).contentFrame();
    await v.frame.waitForSelector('.elementor-element', { timeout: 180000 });
    await page.waitForFunction(() => { const l = document.querySelector('#elementor-loading'); return window.elementor && window.$e && (!l || getComputedStyle(l).display === 'none'); }, { timeout: 300000 }).catch(() => {});
    await sleep(2500);
    return v;
  }
  async function openEditor(id) {
    return loadEditor(await adminChrome(), id);
  }
  /** Cambia el título de un encabezado en el editor y guarda («Actualizar»). */
  async function editorUpdate(ed, headingId, title) {
    return ed.page.evaluate(async (hid, t) => {
      const c = window.elementor.getContainer(hid);
      if (!c) return 'sin el encabezado ' + hid;
      window.$e.run('document/elements/settings', { container: c, settings: { title: t }, options: { external: true } });
      try { await window.$e.run('document/save/update', { force: true }); } catch (e) { return 'guardar: ' + (e && e.message ? e.message : String(e)); }
      return 'ok';
    }, headingId, title);
  }
  async function waitHeading(id, headingId, title, ms = 90000) {
    const end = Date.now() + ms;
    let doc = null;
    while (Date.now() < end) {
      doc = await op('resilience_doc', { id });
      if ((doc.headings || {})[headingId] === title) return doc;
      await sleep(1500);
    }
    return null;
  }

  // ── Sitios sin WooCommerce (W0.9) ──────────────────────────────────────────────────────────────────────────────
  const nowoo = {};
  const CLI = (process.env.PLAYGROUND_CLI || 'npx --yes @wp-playground/cli@3.1.56').split(/\s+/);
  const SHARED = path.join(OUT, 'res-shared');
  function freeOwnPort(port) {
    let pids = '';
    try { pids = execFileSync('fuser', ['-n', 'tcp', String(port)], { stdio: ['ignore', 'pipe', 'ignore'] }).toString(); } catch (e) { return true; }
    for (const pid of pids.trim().split(/\s+/).filter(Boolean)) {
      let cmd = '';
      try { cmd = fs.readFileSync(`/proc/${pid}/cmdline`, 'utf8').replace(/\0/g, ' '); } catch (e) { continue; }
      if (!cmd.includes('res-nowoo-')) return false;
    }
    try { execFileSync('fuser', ['-k', '-n', 'tcp', String(port)], { stdio: 'ignore' }); } catch (e) { /* libre */ }
    return true;
  }
  function nowooFiles(dir) {
    fs.rmSync(dir, { recursive: true, force: true });
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, 'options.json'), JSON.stringify({ token: TOKEN }));
    fs.copyFileSync(path.join(ROOT, 'tests/playground/kit-roundtrip/cli.php'), path.join(dir, 'cli.php'));
    fs.writeFileSync(path.join(dir, 'fatal.php'), FATAL_PHP);
    fs.writeFileSync(path.join(dir, 'mu.php'), NOWOO_MU_PHP);
    fs.writeFileSync(path.join(dir, 'activate.php'), NOWOO_ACTIVATE_PHP);
    fs.writeFileSync(path.join(dir, 'blueprint.json'), JSON.stringify({ steps: [{ step: 'runPHP', code: "<?php require '/wordpress/wp-content/probe/activate.php';" }] }));
  }
  function startNoWoo(key) {
    const port = NOWOO_PORTS[key];
    const dir = path.join(OUT, `res-nowoo-${key}`);
    if (!freeOwnPort(port)) throw new Error(`el puerto ${port} está ocupado por otro proceso`);
    nowooFiles(dir);
    fs.mkdirSync(SHARED, { recursive: true });
    const host = `127.0.0.1:${port}`;
    const args = [...CLI.slice(1), 'server', `--port=${port}`, '--workers=1', `--site-url=http://${host}`, `--blueprint=${path.join(dir, 'blueprint.json')}`,
      `--mount=${SRC_ELEMENTS}:/wordpress/wp-content/plugins/digitalisimo-elements`,
      `--mount=${SRC_THEME}:/wordpress/wp-content/themes/digitalisimo-theme`,
      `--mount=${dir}:/wordpress/wp-content/probe`,
      `--mount=${SHARED}:/wordpress/wp-content/digi-shared`];
    const logFd = fs.openSync(path.join(dir, 'server.log'), 'w');
    const child = spawn(CLI[0], args, { stdio: ['ignore', logFd, logFd], detached: true, env: process.env });
    const srv = httpTo(port, host);
    const entry = { key, port, host, dir, child, srv, started: Date.now() };
    entry.ready = (async () => {
      for (;;) {
        await sleep(3000);
        if (child.exitCode !== null) throw new Error(`el Playground sin WooCommerce (${port}) terminó: ${fs.readFileSync(path.join(dir, 'server.log'), 'utf8').slice(-400)}`);
        if (fs.existsSync(path.join(dir, 'setup-error.txt'))) throw new Error(`preparación sin WooCommerce (${port}): ${fs.readFileSync(path.join(dir, 'setup-error.txt'), 'utf8').trim()}`);
        const ping = await srv('GET', `/?${qs({ digi_res: 'ping', token: TOKEN })}`).catch(() => null);
        if (ping && ping.json && ping.json.ready > 0) { entry.ms = Date.now() - entry.started; return ping.json; }
        if (Date.now() - entry.started > 420000) throw new Error(`el sitio sin WooCommerce (${port}) no quedó listo en 7 min`);
      }
    })();
    entry.ready.catch(() => {});
    nowoo[key] = entry;
    return entry;
  }
  function stopNoWoo(key) {
    const e = nowoo[key];
    if (!e) return;
    try { process.kill(-e.child.pid, 'SIGTERM'); } catch (err) { /* ya terminó */ }
    try { execFileSync('fuser', ['-k', '-n', 'tcp', String(e.port)], { stdio: 'ignore' }); } catch (err) { /* libre */ }
    delete nowoo[key];
  }
  process.on('exit', () => { for (const e of Object.values(nowoo)) { try { process.kill(-e.child.pid, 'SIGKILL'); } catch (err) { /* nada */ } } });
  // Arrancan ya, en paralelo con la preparación de la tienda y la compra de W0: W0.9 los usa al final de W0.
  if (runs('W0') && process.env.KEEP !== '1') {
    try { startNoWoo('a'); startNoWoo('b'); } catch (e) { log(`resilience: no se pudieron arrancar los sitios sin WooCommerce: ${e.message}`); }
  }

  function multipart(fields, files) {
    const boundary = '----digires' + crypto.randomBytes(8).toString('hex');
    const parts = [];
    for (const [name, value] of Object.entries(fields)) parts.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${name}"\r\n\r\n${value}\r\n`));
    for (const [name, file] of Object.entries(files)) {
      parts.push(Buffer.from(`--${boundary}\r\nContent-Disposition: form-data; name="${name}"; filename="${file.filename}"\r\nContent-Type: application/zip\r\n\r\n`));
      parts.push(file.data);
      parts.push(Buffer.from('\r\n'));
    }
    parts.push(Buffer.from(`--${boundary}--\r\n`));
    return { body: Buffer.concat(parts), type: `multipart/form-data; boundary=${boundary}` };
  }
  const REST = '/wp-json/elementor/v1/import-export-customization';
  const INCLUDE = ['templates', 'content', 'settings', 'plugins'];
  async function session(e) {
    const jar = await login(e.srv);
    const nonce = (await e.srv('GET', '/wp-admin/admin-ajax.php?action=rest-nonce', { jar })).text.trim();
    if (!/^[0-9a-f]{10}$/.test(nonce)) throw new Error(`sin nonce REST en ${e.host}`);
    return { jar, nonce };
  }
  async function rest(e, auth, route, body, mp = null) {
    const headers = { 'X-WP-Nonce': auth.nonce };
    let payload = null;
    if (mp) { headers['Content-Type'] = mp.type; payload = mp.body; } else { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }
    return e.srv('POST', `${REST}/${route}`, { jar: auth.jar, headers, body: payload });
  }
  async function runImport(e, auth, sessionId, extra = {}) {
    const imp = await rest(e, auth, 'import', { session: sessionId, include: INCLUDE, customization: { settings: null, templates: null, content: null, plugins: null }, ...extra });
    if (imp.status !== 200 || !imp.json || !imp.json.data) return { ok: false, session: sessionId, why: `import ${imp.status} ${imp.text.slice(0, 300)}` };
    const failed = [];
    for (const runner of imp.json.data.runners || []) {
      const r = await rest(e, auth, 'import-runner', { session: sessionId, runner });
      const data = r.json && r.json.data;
      if (r.status !== 200) failed.push(`${runner}: ${r.status} ${r.text.slice(0, 200)}`);
      if (data && Array.isArray(data.failed) && data.failed.length) failed.push(`${runner}: ${short(data.failed, 200)}`);
      if (data && data.status === 'error') failed.push(`${runner}: ${short(data, 200)}`);
    }
    return { ok: !failed.length, session: sessionId, why: failed.join('; '), runners: imp.json.data.runners };
  }
  /** Pantalla «Kits»: su formulario de subida (sin «sitio nuevo») y la App con el token, como el navegador. */
  async function importScreen(e, zip) {
    const auth = await session(e);
    const page = await e.srv('GET', '/wp-admin/admin.php?page=digitalisimo-elements-kits', { jar: auth.jar });
    const form = (page.text.match(/<form[^>]*action="[^"]*admin-post\.php[^"]*"[^>]*>[\s\S]*?<\/form>/gi) || []).find((f) => /name="action"\s+value="digitalisimo_elements_kit_upload"/.test(f));
    if (!form) return { ok: false, why: `la pantalla «Kits» no tiene el formulario de subida (${page.status})` };
    const fields = {};
    for (const m of form.matchAll(/<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"/g)) fields[m[1]] = m[2];
    const file = (form.match(/<input[^>]*type="file"[^>]*name="([^"]+)"/) || [])[1];
    if (!file) return { ok: false, why: 'el formulario de la pantalla «Kits» no tiene campo de archivo' };
    const mp = multipart(fields, { [file]: { filename: 'kit-resiliencia.zip', data: zip } });
    const post = await e.srv('POST', '/wp-admin/admin-post.php', { jar: auth.jar, body: mp.body, headers: { 'Content-Type': mp.type } });
    const location = post.headers.location || '';
    const token = (location.match(/[#?&]id=([0-9a-f]{32,64})/) || [])[1];
    if (post.status !== 302 || !token) return { ok: false, why: `sin redirección a la App con token: ${location || post.text.slice(0, 200)}` };
    const up = await rest(e, auth, 'upload', { referrer: 'digitalisimo', source: 'digitalisimo', kit_id: token });
    if (up.status !== 200 || !up.json || !up.json.data || !up.json.data.session) return { ok: false, why: `upload ${up.status} ${up.text.slice(0, 300)}` };
    return runImport(e, auth, up.json.data.session, { id: token, referrer: 'digitalisimo' });
  }
  /** App (Herramientas → Importar): /upload con el archivo, /import y cada runner. */
  async function importApp(e, zip) {
    const auth = await session(e);
    const up = await rest(e, auth, 'upload', null, multipart({}, { e_import_file: { filename: 'kit-resiliencia.zip', data: zip } }));
    if (up.status !== 200 || !up.json || !up.json.data || !up.json.data.session) return { ok: false, why: `upload ${up.status} ${up.text.slice(0, 300)}` };
    return runImport(e, auth, up.json.data.session);
  }
  /** WP-CLI dentro del Playground (kit-roundtrip/cli.php y wp-cli.phar). */
  async function importCli(e) {
    const args = ['digitalisimo-elements', 'kit', 'import', '/wordpress/wp-content/digi-shared/kit.zip', '--user=admin', '--format=json', `--include=${INCLUDE.join(',')}`];
    const r = await e.srv('GET', `/wp-content/probe/cli.php?${qs({ token: TOKEN, args: JSON.stringify(args) })}`);
    const out = r.json || { stdout: '', stderr: r.text.slice(0, 300), fatal: '' };
    let data = null;
    try { data = JSON.parse((String(out.stdout).match(/\{[\s\S]*\}\s*$/) || ['null'])[0]); } catch (err) { data = null; }
    const ok = r.status === 200 && !out.fatal && !/^Error:/m.test(out.stderr || '') && data && data.session;
    return { ok: !!ok, session: data && data.session, why: ok ? '' : short({ status: r.status, fatal: out.fatal, stderr: out.stderr, stdout: String(out.stdout).slice(0, 200) }, 500) };
  }
  /** Informe público y marcadores de la sesión, sin lo que cambia de una importación a otra (sesión, hora). */
  async function sessionFacts(e, sessionId) {
    const r = await e.srv('GET', `/?${qs({ digi_res: 'session', session: sessionId, token: TOKEN })}`);
    if (r.status !== 200 || !r.json) throw new Error(`sesión ${sessionId} en ${e.host}: ${r.status} ${r.text.slice(0, 200)}`);
    const f = r.json;
    const report = JSON.parse(JSON.stringify(f.report || {}));
    delete report.session;
    delete report.time;
    return { report, docs: f.docs || {}, unconverted: f.unconverted, woocommerce: f.woocommerce, fatal: f.fatal || '' };
  }
  /** Primeras diferencias entre dos valores JSON (rutas). */
  function diff(a, b, at = '', out = []) {
    if (out.length > 12) return out;
    if (JSON.stringify(a) === JSON.stringify(b)) return out;
    if (a && b && typeof a === 'object' && typeof b === 'object') {
      for (const k of new Set([...Object.keys(a), ...Object.keys(b)])) diff(a[k], b[k], `${at}.${k}`, out);
      return out;
    }
    out.push(`${at}: ${short(a, 120)} ≠ ${short(b, 120)}`);
    return out;
  }

  // ── W0.8 · sin WooCommerce en el mismo sitio ──────────────────────────────────────────────────────────────────
  async function w08(s) {
    const r = res();
    const id = r && r.ids && r.ids['digi-wf-res-sin-woo'];
    if (!id) { check(s, 'W0.8', 'la preparación deja la página con los siete widgets', false, short(r || setupOf().errors)); return; }
    const url = r.pages['digi-wf-res-sin-woo'];
    // Línea base: el mismo editor guarda la página con WooCommerce activo (el editor añade los valores por defecto de
    // los controles y quita los vacíos al guardar: lo que se compara después es lo que el editor guarda con
    // WooCommerce frente a lo que guarda sin él).
    let ed = await adminChrome();
    let base = null;
    try {
      await loadEditor(ed, id);
      const saved = await editorUpdate(ed, 'res0h00', 'Guardado con WooCommerce');
      base = saved === 'ok' ? await waitHeading(id, 'res0h00', 'Guardado con WooCommerce') : null;
      check(s, 'W0.8a', 'con WooCommerce, el editor guarda la página con los siete widgets de F4-Woo (línea base)', !!base && Object.keys(md5Of(base, 'res0w')).length === 7, short({ saved, types: base && base.widgets.map((w) => w.type) }));
    } catch (e) {
      await shot(ed.page, 'W0.8-editor-con-woo-falla');
      check(s, 'W0.8a', 'con WooCommerce, el editor guarda la página (línea base)', false, String(e.stack || e.message || e).slice(0, 600));
    }
    // La pestaña del editor se cierra mientras se desactiva WooCommerce y se mira el front (sin peticiones del editor
    // de por medio) y se abre otra para el editor sin WooCommerce.
    await ed.ctx.close().catch(() => {});
    if (!base) return;
    const before = md5Of(base, 'res0w');
    const htmlWith = (await main('GET', url)).text;
    const rendered = NAMES.filter((n) => hasWidget(htmlWith, n));
    const control = await main('GET', '/wp-json/digitalisimo-elements/v1/woo/cards');
    const ns = await main('GET', '/wp-json/digitalisimo-elements/v1');
    const nsRoutes = Object.keys((ns.json && ns.json.routes) || {}).filter((x) => /\/woo\//.test(x));
    let off = null;
    try {
      off = await op('resilience_woo', { on: 0 });
      const ping = await api.probe(SITE, 'ping');
      check(s, 'W0.8b', 'WooCommerce queda desactivado como en la pantalla Plugins (la petición siguiente no lo carga)', !off.active && !ping.woocommerce && !ping.plugins.includes('woocommerce/woocommerce.php'), short({ off, wc: ping.woocommerce }));

      // Front: anónimo, sin salida de los widgets, sin sus recursos y sin errores.
      const v = await visitor();
      const requests = [];
      v.page.on('request', (q) => requests.push(q.url()));
      try {
        await v.page.setViewport({ width: 1280, height: 900 });
        const resp = await v.page.goto(url, { waitUntil: 'load' });
        const html = resp ? await resp.text() : '';
        const dom = await v.page.evaluate((names) => ({
          widgets: names.map((n) => document.querySelectorAll(`.elementor-widget-${n}, [data-widget_type^="${n}."]`).length).reduce((a, b) => a + b, 0),
          inert: document.querySelectorAll('.digi-woo-inert').length,
          heading: !!document.querySelector('.elementor-element-res0h00'),
        }), NAMES);
        const assets = requests.filter((u) => ASSETS.test(u));
        await shot(v.page, 'W0.8-front-sin-woo');
        check(s, 'W0.8c', 'front sin WooCommerce: la página responde 200, el resto se pinta y los siete widgets no dejan salida, aviso ni recursos', resp && resp.status() === 200 && dom.heading && dom.widgets === 0 && dom.inert === 0 && !assets.length && !/digi-woo-inert|elementor-widget-digitalisimo-woo-/.test(html), short({ status: resp && resp.status(), dom, assets }));
        const f = realFacts(v.facts);
        check(s, 'W0.8d', 'front sin WooCommerce: sin errores de JavaScript, sin respuestas de error, sin avisos de PHP ni peticiones fuera (salvo las fuentes de Google del kit, que el núcleo pide con o sin WooCommerce)', !f.errors.length && !f.failed.length && !f.outside.length && !PHP_NOTICE.test(html), short({ errors: f.errors.slice(0, 3), failed: f.failed.slice(0, 3), outside: f.outside.slice(0, 3), fuentes: f.fonts, php: PHP_NOTICE.test(html) }));
      } finally {
        await v.ctx.close().catch(() => {});
      }

      // REST: woo/* no existe (404 rest_no_route); con WooCommerce, la misma ruta sí existía.
      const routes = {};
      for (const [m, p] of [['GET', 'woo/quick-view'], ['GET', 'woo/cards'], ['POST', 'woo/cards'], ['GET', 'woo/wishlist'], ['POST', 'woo/wishlist']]) {
        const x = await main(m, `/wp-json/digitalisimo-elements/v1/${p}`, m === 'POST' ? { body: '{}', headers: { 'Content-Type': 'application/json' } } : {});
        routes[`${m} ${p}`] = `${x.status} ${(x.json && x.json.code) || ''}`;
      }
      check(s, 'W0.8e', 'sin WooCommerce, woo/* responde 404 (`rest_no_route`); con WooCommerce, `GET woo/cards` existía (405)', Object.values(routes).every((x) => x === '404 rest_no_route') && control.status === 405, short({ routes, conWoo: `${control.status} ${(control.json && control.json.code) || ''}`, rutasConWoo: nsRoutes }, 900));

      // Editor (Chrome): los siete con el aviso, ocultos del panel; guardar desde el editor.
      try {
        ed = await adminChrome();
        await loadEditor(ed, id);
        const preview = await ed.frame.evaluate((names) => names.map((n) => {
          const els = Array.from(document.querySelectorAll(`.elementor-widget-${n}`));
          return { n, count: els.length, notice: els.filter((e) => e.querySelector('.digi-woo-inert')).length, text: els[0] ? els[0].textContent.replace(/\s+/g, ' ').trim().slice(0, 80) : '' };
        }), NAMES);
        const panel = await ed.page.evaluate((names) => names.map((n) => { const c = window.elementor && window.elementor.widgetsCache && window.elementor.widgetsCache[n]; return { n, known: !!c, show: c ? c.show_in_panel : null }; }), NAMES);
        await shot(ed.page, 'W0.8-editor-sin-woo');
        check(s, 'W0.8f', 'editor sin WooCommerce: cada uno de los siete widgets se pinta con el aviso «Necesita WooCommerce»', preview.every((p) => p.count === 1 && p.notice === 1 && /Necesita WooCommerce/.test(p.text)), short(preview, 900));
        check(s, 'W0.8g', 'editor sin WooCommerce: los siete están registrados y ocultos del panel', panel.every((p) => p.known && p.show === false), short(panel, 700));
        const saved = await editorUpdate(ed, 'res0h00', 'Guardado sin WooCommerce');
        const after = saved === 'ok' ? await waitHeading(id, 'res0h00', 'Guardado sin WooCommerce') : null;
        const kept = after ? md5Of(after, 'res0w') : {};
        check(s, 'W0.8h', 'el editor guarda sin WooCommerce y el documento conserva los siete widgets', saved === 'ok' && !!after && Object.keys(kept).length === 7, short({ saved, types: after && after.widgets.map((w) => w.type) }));
        // Como design.sh: los errores de red del editor (AxiosError: una petición REST cortada o con error) se anotan
        // con sus respuestas; sólo los demás son errores del código.
        const network = ed.pageErrors.filter((e) => /AxiosError|Network Error|timeout of \d+ms/i.test(e));
        const code = ed.pageErrors.filter((e) => !network.includes(e));
        check(s, 'W0.8i', 'el editor sin WooCommerce no tiene errores de JavaScript (los de red se anotan con sus respuestas)', !code.length, short({ codigo: code.slice(0, 3), red: network.length, respuestas: ed.facts.failed.slice(0, 6) }, 900));
      } catch (e) {
        await shot(ed.page, 'W0.8-editor-falla');
        check(s, 'W0.8f', 'editor sin WooCommerce (Chrome)', false, String(e.stack || e.message || e).slice(0, 600));
      } finally {
        await ed.ctx.close().catch(() => {});
      }
    } finally {
      if (off) {
        const on = await op('resilience_woo', { on: 1 }).catch((e) => ({ error: e.message }));
        const ping = await api.probe(SITE, 'ping').catch(() => ({}));
        check(s, 'W0.8j', 'WooCommerce vuelve a activarse (la petición siguiente lo carga, 11.2.1)', !on.error && ping.woocommerce === '11.2.1', short({ on, wc: ping.woocommerce }));
      }
    }
    const back = await op('resilience_doc', { id });
    const now = md5Of(back, 'res0w');
    const changed = Object.keys(before).filter((k) => before[k] !== now[k]);
    const detail = changed.map((k) => {
      const a = base.widgets.find((w) => w.id === k);
      const b = back.widgets.find((w) => w.id === k);
      return `${k} ${a && a.type}: ${diff(a && a.settings, b && b.settings).join(' · ')}`;
    });
    check(s, 'W0.8k', 'tras guardar en el editor sin WooCommerce y reactivarlo, los ajustes de cada widget tienen el mismo md5 que los que guardó el editor con WooCommerce', Object.keys(now).length === 7 && !changed.length, changed.length ? detail.join(' | ') : short(now));
    const htmlBack = await main('GET', url);
    const again = NAMES.filter((n) => hasWidget(htmlBack.text, n));
    check(s, 'W0.8l', 'con WooCommerce de nuevo, la página pinta los mismos widgets que antes de desactivarlo', htmlBack.status === 200 && JSON.stringify(again) === JSON.stringify(rendered) && rendered.length >= 4, short({ antes: rendered, despues: again }));
  }

  // ── W0.9 · el mismo kit por los tres caminos, en sitios aparte sin WooCommerce ──────────────────────────────────
  async function w09(s, exp) {
    try {
      const kitFile = path.join(SHARED, 'kit.zip');
      check(s, 'W0.9a', 'K exporta este sitio (con WooCommerce y los widgets de F4-Woo) como kit', fs.existsSync(kitFile) && exp.size > 0, short(exp));
      if (!exp || exp.error || !exp.size || !fs.existsSync(kitFile)) return;
      const zip = fs.readFileSync(kitFile);
      let phar = process.env.WP_CLI_PHAR || '/var/tmp/digi-k/cache/wp-cli.phar';
      if (!fs.existsSync(phar)) {
        phar = path.join(OUT, 'wp-cli.phar');
        if (!fs.existsSync(phar)) execFileSync('curl', ['-fsSL', '-m', '120', '-o', phar, 'https://playground.wordpress.net/wp-cli.phar']);
      }
      fs.copyFileSync(phar, path.join(SHARED, 'wp-cli.phar'));
      if (!nowoo.a) startNoWoo('a');
      if (!nowoo.b) startNoWoo('b');
      const [pa, pb] = await Promise.all([nowoo.a.ready, nowoo.b.ready]);
      check(s, 'W0.9b', 'dos sitios aparte sin WooCommerce (puertos 9494 y 9490) con DIGITALÍSIMO Elements activo', !pa.woocommerce && !pb.woocommerce && pa.k && pb.k, short({ a: { wc: pa.woocommerce, k: pa.k, ms: nowoo.a.ms }, b: { wc: pb.woocommerce, k: pb.k, ms: nowoo.b.ms } }));
      const runs3 = {};
      const facts = {};
      // Pantalla «Kits» en A y App en B a la vez; WP-CLI, en un sitio nuevo: B se vuelve a crear desde cero (mismo
      // puerto, base de datos nueva) en cuanto termina la App.
      await Promise.all([
        importScreen(nowoo.a, zip).then(async (ra) => {
          runs3.pantalla = ra;
          if (ra.ok) facts.pantalla = await sessionFacts(nowoo.a, ra.session);
          stopNoWoo('a');
        }),
        importApp(nowoo.b, zip).then(async (rb) => {
          runs3.app = rb;
          if (rb.ok) facts.app = await sessionFacts(nowoo.b, rb.session);
          stopNoWoo('b');
          await sleep(1500);
          startNoWoo('b');
          await nowoo.b.ready;
          runs3.cli = await importCli(nowoo.b);
          if (runs3.cli.ok) facts.cli = await sessionFacts(nowoo.b, runs3.cli.session);
          stopNoWoo('b');
        }),
      ]);
      fs.writeFileSync(path.join(OUT, 'res-w09.json'), JSON.stringify({ export: exp, runs: runs3, facts }, null, 1));
      for (const via of ['pantalla', 'app', 'cli']) check(s, `W0.9c ${via}`, `la importación por ${via === 'pantalla' ? 'la pantalla «Kits»' : via === 'app' ? 'la App' : 'WP-CLI'} termina sin WooCommerce`, runs3[via].ok, runs3[via].why || runs3[via].session);
      const ref = facts.pantalla || facts.app || facts.cli;
      if (!ref) return;
      // Los siete widgets quedan en marcadores de K (motivo `unknown`), en cada camino.
      for (const via of Object.keys(facts)) {
        const markers = Object.values(facts[via].docs).flatMap((d) => d.markers);
        const seen = new Set(markers.filter((m) => m.reason === 'unknown' && m.source === 'kit').map((m) => m.original));
        check(s, `W0.9d ${via}`, 'los siete widgets de F4-Woo quedan en el marcador de K (`digitalisimo-unconverted`, motivo `unknown`) y el informe dice `woocommerce.inactive`', NAMES.every((n) => seen.has(n)) && JSON.stringify(facts[via].report).includes('woocommerce.inactive') && !facts[via].woocommerce, short({ faltan: NAMES.filter((n) => !seen.has(n)), marcadores: markers.length, contador: facts[via].unconverted }));
      }
      const vias = Object.keys(facts);
      const sameMarkers = vias.map((v) => diff(ref.docs, facts[v].docs)).flat();
      // El informe se compara sección a sección con sus avisos ordenados; el orden distinto dentro de una sección se anota.
      const sorted = (rep) => { const x = JSON.parse(JSON.stringify(rep)); for (const sec of Object.values(x.sections || {})) if (Array.isArray(sec.entries)) sec.entries.sort((a, b) => JSON.stringify(a).localeCompare(JSON.stringify(b))); return x; };
      const sameReport = vias.map((v) => diff(sorted(ref.report), sorted(facts[v].report)).map((d) => `${v}${d}`)).flat();
      const order = vias.filter((v) => diff(ref.report, facts[v].report).length);
      check(s, 'W0.9e', 'mismos marcadores de K por los tres caminos (documento, tipo original, motivo y md5 de los ajustes originales)', vias.length === 3 && !sameMarkers.length && vias.every((v) => facts[v].unconverted === ref.unconverted), short({ vias, diferencias: sameMarkers.slice(0, 6), contadores: vias.map((v) => facts[v].unconverted) }, 900));
      check(s, 'W0.9f', 'mismo informe de K por los tres caminos (salvo la sesión y la hora; los avisos de cada sección, en cualquier orden)', vias.length === 3 && !sameReport.length, short({ vias, diferencias: sameReport.slice(0, 8), ordenDistinto: order.map((v) => `${v}: ${diff(ref.report, facts[v].report).slice(0, 2).join(' · ')}`) }, 900));
      check(s, 'W0.9g', 'sin errores fatales en los sitios sin WooCommerce', vias.every((v) => !facts[v].fatal), short(vias.map((v) => facts[v].fatal).filter(Boolean)));
    } finally {
      stopNoWoo('a');
      stopNoWoo('b');
    }
  }

  // ── W0.10 · red: primera petición anónima a un sitio nuevo ────────────────────────────────────────────────────
  async function w010(s) {
    const created = await op('resilience_new_site');
    const fatal0 = (await op('resilience_fatal')).size;
    const first = await main('GET', created.path);
    const fatal1 = await op('resilience_fatal');
    const after = await op('resilience_site', { blog: created.id });
    check(s, 'W0.10', 'red: la primera petición anónima (sin el token de la sonda) a un sitio recién creado responde 200 y no deja nada en fatal.log', first.status === 200 && !/Access denied/.test(first.text) && !PHP_NOTICE.test(first.text) && fatal1.size === fatal0, short({ path: created.path, antes: { wc_db: created.wc_db, cart: created.cart }, status: first.status, despues: after, fatal: fatal1.size > fatal0 ? fatal1.tail : '' }));
  }

  async function W0(s) {
    if (process.env.RES_SKIP_BASE !== '1') await api.base.W0(s); // RES_SKIP_BASE: sólo para iterar, nunca en la aceptación.
    for (let i = s.checks.length - 1; i >= 0; i--) if (s.checks[i].status === 'pendiente' && /^W0\.(8|9)$/.test(s.checks[i].id)) s.checks.splice(i, 1);
    if (!res()) {
      check(s, 'W0.8', 'la preparación de resilience está completa', false, short(setupOf().errors));
      return;
    }
    const t = Date.now();
    const lap = (what) => log(`   resilience ${what}: ${Math.round((Date.now() - t) / 1000)} s (${new Date().toISOString().slice(11, 19)})`);
    if (process.env.RES_SKIP_BASE !== '1') lap('W0 de run.cjs');
    // En orden: con W0.9 en paralelo (los otros Playground importando mientras el editor carga en este), el único worker
    // de este sitio llegó a quedarse sin responder; el kit se exporta después de W0.8, con WooCommerce reactivado.
    try { await w08(s); } catch (e) { check(s, 'W0.8', 'sin WooCommerce en el mismo sitio', false, String(e.stack || e.message || e).slice(0, 600)); }
    lap('W0.8');
    let exp = null;
    try { exp = await op('resilience_export'); } catch (e) { exp = { error: String(e.message || e) }; }
    try { await w09(s, exp); } catch (e) { check(s, 'W0.9', 'el mismo kit por los tres caminos sin WooCommerce', false, String(e.stack || e.message || e).slice(0, 600)); }
    lap('W0.9');
    if (NET) {
      try { await w010(s); } catch (e) { check(s, 'W0.10', 'red: primera petición anónima a un sitio nuevo', false, String(e.stack || e.message || e).slice(0, 600)); }
    }
  }

  // ── W3 · caché de página simulada ─────────────────────────────────────────────────────────────────────────────
  /** El HTML sin nonces (claves con «nonce» seguidas de 10 hexadecimales, y `_wpnonce=`). */
  const denonce = (html) => html
    .replace(/(nonce[A-Za-z_-]*\\?["']?\s*[:=]\s*\\?["']?)[0-9a-f]{10}\b/gi, '$1NONCE')
    .replace(/([?&](?:amp;)?_wpnonce=)[0-9a-f]{10}\b/gi, '$1NONCE');
  function firstDiff(a, b) {
    let i = 0;
    while (i < a.length && i < b.length && a[i] === b[i]) i++;
    return i >= a.length && i >= b.length ? '' : `posición ${i}: «${a.slice(Math.max(0, i - 80), i + 80)}» ≠ «${b.slice(Math.max(0, i - 80), i + 80)}»`;
  }
  async function hydrated(page, mine, other) {
    await page.waitForFunction((p) => { const b = document.querySelector(`button[data-digi-wish="${p}"]`); return b && !b.hidden && b.getAttribute('aria-pressed') === 'true'; }, { timeout: 60000 }, mine).catch(() => {});
    await page.waitForFunction(() => { const st = document.querySelector('.elementor-element-res1w01 .digi-ship__state'); return st && st.hasAttribute('data-state'); }, { timeout: 60000 }).catch(() => {});
    await page.waitForFunction((p) => !!document.querySelector(`[data-digi-wish-items] li[data-digi-wish-item="${p}"]`), { timeout: 60000 }, mine).catch(() => {});
    return page.evaluate((m, o) => {
      const pressed = (p) => Array.from(document.querySelectorAll(`button[data-digi-wish="${p}"]`)).map((b) => b.getAttribute('aria-pressed'));
      const st = document.querySelector('.elementor-element-res1w01 .digi-ship__state');
      const bar = document.querySelector('.elementor-element-res1w01 .digi-ship');
      return {
        mine: pressed(m),
        other: pressed(o),
        list: Array.from(document.querySelectorAll('[data-digi-wish-items] li[data-digi-wish-item]')).map((li) => +li.getAttribute('data-digi-wish-item')),
        ship: st ? { state: st.getAttribute('data-state'), remaining: st.getAttribute('data-remaining'), text: st.textContent.replace(/\s+/g, ' ').trim().slice(0, 100) } : null,
        barHidden: bar ? bar.hidden : null,
      };
    }, mine, other);
  }
  /** Dos visitantes, cada uno con su carrito (con el envío calculado) y su lista de deseos. */
  async function visitors() {
    const r = res();
    const url = r.pages['digi-wf-res-cache'];
    const cart = setupOf().pages['digi-wf-carrito'];
    const { camiseta, gorra } = r.products;
    const A = await visitor();
    const B = await visitor();
    for (const [v, product, el] of [[A, camiseta, 'res1w03'], [B, gorra, 'res1w04']]) {
      await v.page.setViewport({ width: 1280, height: 900 });
      await v.page.goto(`${BASE}${SITE.path}?add-to-cart=${product}`, { waitUntil: 'load' });
      // Destino conocido (V.7.1): la calculadora de envío del carrito clásico (España, Madrid, 28001), enviada como
      // formulario, marca el envío del visitante como calculado.
      await v.page.goto(cart, { waitUntil: 'load' });
      const sent = await v.page.evaluate(() => {
        const f = document.querySelector('form.woocommerce-shipping-calculator');
        if (!f) return false;
        const set = (n, val) => { const el = f.querySelector(`[name="${n}"]`); if (el) el.value = val; };
        set('calc_shipping_country', 'ES');
        set('calc_shipping_state', 'M');
        set('calc_shipping_city', 'Madrid');
        set('calc_shipping_postcode', '28001');
        const flag = document.createElement('input');
        flag.type = 'hidden';
        flag.name = 'calc_shipping';
        flag.value = 'x';
        f.appendChild(flag);
        return true;
      });
      if (sent) await Promise.all([v.page.waitForNavigation({ waitUntil: 'load' }), v.page.evaluate(() => HTMLFormElement.prototype.submit.call(document.querySelector('form.woocommerce-shipping-calculator')))]);
      await v.page.goto(url, { waitUntil: 'load' });
      const btn = `.elementor-element-${el} button[data-digi-wish="${product}"]`;
      await v.page.waitForSelector(`${btn}:not([hidden])`, { timeout: 60000 });
      await v.page.click(btn);
      await v.page.waitForFunction((b) => document.querySelector(b).getAttribute('aria-pressed') === 'true', { timeout: 30000 }, btn);
    }
    // La caché de página simulada: con `B.serve`, la petición de la página del segundo visitante recibe ese HTML.
    B.serve = null;
    B.served = 0;
    await B.page.setRequestInterception(true);
    B.page.on('request', (q) => {
      if (q.isInterceptResolutionHandled()) return;
      if (B.serve !== null && q.isNavigationRequest() && q.url().split('#')[0] === url) {
        const body = B.serve;
        B.serve = null;
        B.served++;
        q.respond({ status: 200, contentType: 'text/html; charset=UTF-8', body });
      } else q.continue();
    });
    return { A, B, url, camiseta, gorra };
  }
  async function cacheRound(s, label, ids, { A, B, url, camiseta, gorra }) {
    const ra = await A.page.goto(url, { waitUntil: 'load' });
    const htmlA = await ra.text();
    const stateA = await hydrated(A.page, camiseta, gorra);
    const rb = await B.page.goto(url, { waitUntil: 'load' });
    const htmlB = await rb.text();
    const served = B.served;
    B.serve = htmlA;
    const rc = await B.page.goto(url, { waitUntil: 'load' });
    const htmlC = rc ? await rc.text() : '';
    const stateB = await hydrated(B.page, gorra, camiseta);
    await shot(A.page, `${label}-visitante-a`);
    await shot(B.page, `${label}-visitante-b-cache`);
    fs.writeFileSync(path.join(OUT, `res-${label}-a.html`), htmlA);
    fs.writeFileSync(path.join(OUT, `res-${label}-b.html`), htmlB);
    const present = NAMES.filter((n) => hasWidget(htmlA, n));
    const na = denonce(htmlA);
    const nb = denonce(htmlB);
    const nonces = (htmlA.match(/nonce[A-Za-z_-]*\\?["']?\s*[:=]\s*\\?["']?[0-9a-f]{10}\b/gi) || []).length;
    const intercepted = B.served - served;
    check(s, ids[0], 'los dos visitantes reciben el mismo HTML byte a byte (sin nonces) en la página con los siete widgets de F4-Woo; el segundo recibe el del primero', na === nb && present.length === 7 && intercepted === 1 && htmlC === htmlA, short({ iguales: na === nb, bytes: [htmlA.length, htmlB.length], nonces, tipos: present.length, servido: intercepted, diferencia: na === nb ? '' : firstDiff(na, nb) }, 900));
    const okA = stateA.mine.length && stateA.mine.every((x) => x === 'true') && stateA.other.every((x) => x === 'false') && JSON.stringify(stateA.list) === JSON.stringify([camiseta]) && stateA.ship && stateA.ship.state === 'done';
    const okB = stateB.mine.length && stateB.mine.every((x) => x === 'true') && stateB.other.every((x) => x === 'false') && JSON.stringify(stateB.list) === JSON.stringify([gorra]) && stateB.ship && stateB.ship.state === 'progress' && /32/.test(stateB.ship.remaining || stateB.ship.text);
    check(s, ids[1], 'cada visitante ve su carrito (barra: 60 € con envío gratis, 18 € con 32 € pendientes) y su lista tras los fragmentos, también con el HTML del otro', okA && okB, short({ A: stateA, B: stateB }, 900));
    const fa = realFacts(A.facts);
    const fb = realFacts(B.facts);
    check(s, ids[2], 'sin errores de JavaScript, respuestas de error ni peticiones fuera en los dos visitantes (salvo las fuentes de Google del kit)', !fa.errors.length && !fb.errors.length && !fa.failed.length && !fb.failed.length && !fa.outside.length && !fb.outside.length, short({ A: fa, B: fb }));
  }
  async function W3(s) {
    const r = res();
    if (!r || !r.pages || !r.pages['digi-wf-res-cache']) { check(s, 'W3.0', 'la preparación deja la página para la caché', false, short(r || setupOf().errors)); return; }
    const runCache = ENV.CACHE === '1';
    await op('resilience_swatches', { type: 'label' });
    let pair = null;
    try {
      pair = await visitors();
      // Primero el modo de la corrida y después el contrario: W3.1–W3.2b sin caché de elementos, W3.3 con ella.
      for (const cacheOn of [runCache, !runCache]) {
        await op('resilience_cache', { on: cacheOn ? 1 : 0 });
        if (cacheOn) await cacheRound(s, 'W3-cache', ['W3.3a', 'W3.3b', 'W3.3c'], pair);
        else await cacheRound(s, 'W3', ['W3.1', 'W3.2', 'W3.2b'], pair);
      }
    } finally {
      if (pair) { await pair.A.ctx.close().catch(() => {}); await pair.B.ctx.close().catch(() => {}); }
      await op('resilience_cache', { on: runCache ? 1 : 0 }).catch(() => {});
      await op('resilience_swatches', { type: 'select' }).catch(() => {});
    }
  }

  // ── W13 · vuelta atrás a 4.3.0.165 y guarda hacia delante ─────────────────────────────────────────────────────
  const OLD = path.join(OUT, 'w13-165');
  const PARKED = path.join(OUT, 'w13-166');
  function prepareOld() {
    const zip = process.env.OLD_ELEMENTS_ZIP || OLD_ZIP_DEFAULT;
    const dir = path.join(OLD, 'digitalisimo-elements');
    const ok = () => fs.existsSync(path.join(dir, 'pro-elements.php')) && !fs.existsSync(path.join(dir, 'modules/digitalisimo-woo'));
    if (ok()) return { dir, zip, cached: true };
    if (!fs.existsSync(zip)) throw new Error(`falta el ZIP de 4.3.0.165: ${zip}`);
    const sha = crypto.createHash('sha256').update(fs.readFileSync(zip)).digest('hex');
    if (!process.env.OLD_ELEMENTS_ZIP && sha !== OLD_ZIP_SHA) throw new Error(`${zip} no es el ZIP de 4.3.0.165 (sha256 ${sha})`);
    fs.rmSync(OLD, { recursive: true, force: true });
    fs.mkdirSync(OLD, { recursive: true });
    execFileSync('unzip', ['-q', zip, '-d', OLD]);
    if (!ok()) throw new Error('el ZIP extraído no es un DIGITALÍSIMO Elements sin F4-Woo');
    return { dir, zip, sha, cached: false };
  }
  /** Cambia la carpeta montada del plugin: `old` = 4.3.0.165, si no, el árbol de trabajo. */
  let swapped = false;
  async function swap(old) {
    if (old === swapped) return;
    if (old) {
      fs.rmSync(PARKED, { recursive: true, force: true });
      fs.mkdirSync(PARKED, { recursive: true });
      fs.renameSync(SRC_ELEMENTS, path.join(PARKED, 'digitalisimo-elements'));
      fs.renameSync(path.join(OLD, 'digitalisimo-elements'), SRC_ELEMENTS);
    } else {
      fs.renameSync(SRC_ELEMENTS, path.join(OLD, 'digitalisimo-elements'));
      fs.renameSync(path.join(PARKED, 'digitalisimo-elements'), SRC_ELEMENTS);
    }
    swapped = old;
    await sleep(2500); // OPcache revalida por fecha cada 2 s.
    await op('resilience_version');
    return op('resilience_version');
  }
  process.on('exit', () => { if (swapped) { try { fs.renameSync(SRC_ELEMENTS, path.join(OLD, 'digitalisimo-elements')); fs.renameSync(path.join(PARKED, 'digitalisimo-elements'), SRC_ELEMENTS); } catch (e) { /* $OUT/src se vuelve a copiar en cada corrida */ } } });

  /** Lo que se ve en el front (anónimo, sin cookies). */
  async function front(r) {
    const p2 = r.pages['digi-wf-res-vuelta'];
    const page = await main('GET', p2);
    const filtered = await main('GET', `${p2}${p2.includes('?') ? '&' : '?'}e-filter-resl002-digi_price_min=40`);
    const product = await main('GET', r.variable);
    const loopItems = (h) => (h.match(/class="[^"]*\be-loop-item\b/g) || []).length;
    const between = (h, a, b) => { const i = h.indexOf(`data-id="${a}"`); const j = b ? h.indexOf(`data-id="${b}"`, i + 1) : -1; return i < 0 ? '' : h.slice(i, j > i ? j : i + 60000); };
    const products = between(page.text, 'res2p01', 'res2m01');
    const cart = between(page.text, 'res2m01', '');
    return {
      status: [page.status, filtered.status, product.status],
      notice: [page.text, filtered.text, product.text].some((h) => PHP_NOTICE.test(h)),
      widgets: NAMES.filter((n) => hasWidget(page.text, n)),
      loop: loopItems(page.text),
      loopPrice: loopItems(filtered.text),
      swatches: /data-digi-swatches="pa_color"/.test(product.text),
      select: /<select[^>]*name="attribute_pa_color"/.test(product.text),
      parts: { quickview: /digi-qv__open|data-digi-qv/.test(products), wishlist: /data-digi-wish=/.test(products), swatches: /digi-swatches/.test(products), products: (products.match(/<li[^>]*class="[^"]*\bproduct\b/g) || []).length },
      cartExtras: /digi-cart-extras/.test(cart),
    };
  }

  async function W13(s) {
    const r = res();
    if (!r || !r.ids || !r.ids['digi-wf-res-vuelta']) { check(s, 'W13.0', 'la preparación deja las páginas de la vuelta atrás', false, short(r || setupOf().errors)); return; }
    if (LIVE) {
      pending(s, 'W13.1', 'LIVE=1 monta el árbol de trabajo: W13 cambia la carpeta del plugin en el disco y sólo corre con la copia fija ($OUT/src)');
      pending(s, 'W13.2', 'ídem');
      pending(s, 'W13.3', 'ídem');
      return;
    }
    const P2 = r.ids['digi-wf-res-vuelta'];
    const old = prepareOld();
    const fatal = async () => (await op('resilience_fatal')).size;
    const fatal0 = await fatal();
    // Dos versiones del plugin en la misma carpeta dentro de un mismo proceso de php-wasm: sin OPcache para el código
    // que carga después de un mu-plugin (sin esto, el worker cayó al volver a 4.3.0.166). RES_OPCACHE=1 lo deja.
    const noOpcache = process.env.RES_OPCACHE !== '1';
    if (noOpcache) await op('resilience_opcache', { on: 0 });
    await op('resilience_swatches', { type: 'label' });
    const jar = await login(main);
    let base = null;
    let after165 = null;
    try {
      // 4.3.0.166: el editor guarda el documento (revisión con los siete widgets) y el front lo pinta todo.
      const s166 = await editorSave(main, jar, P2, setHeading('res2h00', 'v1 · 4.3.0.166'));
      base = await op('resilience_doc', { id: P2 });
      const seven = md5Of(base, 'res2w');
      const R1 = (base.revisions[0] || {}).id;
      check(s, 'W13.0a', '4.3.0.166: el editor guarda la página con los siete widgets, las partes de Productos y el rasgo del Menú del carrito, y queda una revisión con ellos', s166.ok && Object.keys(seven).length === 7 && !!R1 && NAMES.every((n) => base.revisions[0].types.includes(n)) && base.pro.products && base.pro.menu_cart && base.pro.menu_cart.bar === 'yes', short({ ok: s166.ok, why: s166.why, widgets: Object.keys(seven).length, R1, pro: base.pro }));
      const f166 = await front(r);
      check(s, 'W13.0b', '4.3.0.166 (control): los widgets, el filtro de precio, las muestras de la ficha, las partes nuevas y el rasgo se pintan', f166.status.every((x) => x === 200) && f166.widgets.length >= 5 && f166.loopPrice > 0 && f166.loopPrice < f166.loop && f166.swatches && f166.parts.quickview && f166.parts.wishlist && f166.cartExtras, short(f166));

      // 4.3.0.165: la carpeta del plugin pasa a ser la del ZIP publicado.
      const v165 = await swap(true);
      check(s, 'W13.1a', `se instala 4.3.0.165 (ZIP publicado${old.cached ? ', ya extraído' : ''}) en la misma carpeta del plugin y la petición siguiente lo carga`, v165 && v165.woo === false, short({ v165, zip: old.zip }));
      const f165 = await front(r);
      check(s, 'W13.1b', '4.3.0.165: sin fatales ni avisos de PHP en la página, el bucle filtrado y la ficha', f165.status.every((x) => x === 200) && !f165.notice && (await fatal()) === fatal0, short({ status: f165.status, notice: f165.notice }));
      check(s, 'W13.1c', '4.3.0.165: los siete widgets no pintan nada, ni las partes nuevas de Productos ni el rasgo del Menú del carrito', !f165.widgets.length && !f165.parts.quickview && !f165.parts.wishlist && !f165.parts.swatches && f165.parts.products > 0 && !f165.cartExtras, short({ widgets: f165.widgets, parts: f165.parts, cartExtras: f165.cartExtras }));
      check(s, 'W13.1d', '4.3.0.165: el bucle con `digi_price_min` en la URL sale vacío (falla cerrado) y sin el filtro muestra productos', f165.loopPrice === 0 && f165.loop > 0, short({ loop: f165.loop, loopPrice: f165.loopPrice, con166: [f166.loop, f166.loopPrice] }));
      check(s, 'W13.1e', '4.3.0.165: las muestras vuelven al <select> de WooCommerce', !f165.swatches && f165.select, short({ swatches: f165.swatches, select: f165.select }));

      // Abrir y guardar en el editor de 4.3.0.165 borra los widgets (límite del núcleo, D26).
      const s165 = await editorSave(main, jar, P2, setHeading('res2h00', 'v2 · 4.3.0.165'));
      after165 = await op('resilience_doc', { id: P2 });
      const left = after165.widgets.filter((w) => NAMES.includes(w.type)).length;
      check(s, 'W13.2a', 'abrir y guardar en el editor de 4.3.0.165 borra los siete widgets (el editor ya no los carga)', s165.ok && s165.page.status === 200 && !s165.page.notice && !types(s165.loaded).some((t) => NAMES.includes(t)) && left === 0 && after165.headings.res2h00 === 'v2 · 4.3.0.165', short({ ok: s165.ok, why: s165.why, cargados: types(s165.loaded).length, quedan: left }));
    } catch (e) {
      check(s, 'W13.x', 'vuelta atrás', false, String(e.stack || e.message || e).slice(0, 600));
    } finally {
      try {
        const v166 = await swap(false);
        check(s, 'W13.2b', 'se vuelve a 4.3.0.166 (la petición siguiente carga F4-Woo)', !!v166 && v166.woo === true, short(v166));
      } catch (e) {
        check(s, 'W13.2b', 'se vuelve a 4.3.0.166', false, String(e.message || e));
      }
    }
    if (base && after165) try {
      // Historial → Revisiones: la revisión anterior al guardado de 4.3.0.165, aplicada y guardada como en el editor.
      const R1 = base.revisions[0].id;
      const page = await editorPage(main, jar, P2);
      const list = await eajax(main, jar, page.nonce, P2, { revs: { action: 'get_revisions', data: {} } });
      const listed = ((list.responses.revs || {}).data || []).map((x) => +x.id);
      const data = await eajax(main, jar, page.nonce, P2, { rev: { action: 'get_revision_data', data: { id: R1 } } });
      const elements = ((data.responses.rev || {}).data || {}).elements;
      const save = Array.isArray(elements) ? await eajax(main, jar, page.nonce, P2, { save: { action: 'save_builder', data: { status: 'publish', elements, settings: { post_status: 'publish' } } } }) : null;
      const restored = await op('resilience_doc', { id: P2 });
      const before = md5Of(base, 'res2w');
      const now = md5Of(restored, 'res2w');
      const changed = Object.keys(before).filter((k) => before[k] !== now[k]);
      check(s, 'W13.2c', 'de vuelta en 4.3.0.166, restaurar la revisión anterior (Historial → Revisiones) devuelve los siete widgets con el mismo md5 de ajustes', listed.includes(R1) && !!(save && save.responses.save && save.responses.save.success) && Object.keys(now).length === 7 && !changed.length, short({ R1, listada: listed.includes(R1), guardado: save && save.responses.save && save.responses.save.success, cambiados: changed.map((k) => `${k}: ${diff(base.widgets.find((w) => w.id === k).settings, (restored.widgets.find((w) => w.id === k) || {}).settings).join(' · ')}`) }, 900));
      const kept = { products: after165.pro.products, menu_cart: after165.pro.menu_cart };
      const survived = !!(kept.products && Array.isArray(kept.products.parts) && ['quickview', 'wishlist', 'swatches'].every((p) => kept.products.parts.includes(p)) && kept.menu_cart && kept.menu_cart.bar === 'yes' && kept.menu_cart.coupon === 'yes');
      check(s, 'W13.2d', `nota: las claves nuevas de los widgets de PRO ${survived ? 'sobreviven' : 'NO sobreviven'} al guardado en 4.3.0.165 (\`digitalisimo_wc_parts\` con quickview, wishlist y swatches; \`digitalisimo_cart_bar\` y \`digitalisimo_cart_coupon\`)`, true, short(kept));
      const fBack = await front(r);
      check(s, 'W13.2e', 'tras restaurar, el front de 4.3.0.166 vuelve a pintar los widgets, el filtro de precio y las partes', fBack.widgets.length >= 5 && fBack.loopPrice > 0 && fBack.parts.quickview && fBack.cartExtras, short(fBack));
    } catch (e) {
      check(s, 'W13.2c', 'restaurar la revisión anterior en 4.3.0.166', false, String(e.stack || e.message || e).slice(0, 600));
    }
    await op('resilience_swatches', { type: 'select' }).catch(() => {});
    try { await w133(s, jar); } catch (e) { check(s, 'W13.3', 'guarda hacia delante', false, String(e.stack || e.message || e).slice(0, 600)); }
    if (noOpcache) await op('resilience_opcache', { on: 1 }).catch(() => {});
    if (hiccups.length) log(`   resilience: peticiones repetidas tras un corte del servidor: ${hiccups.join(' | ')}`);
  }

  /** W13.3 · guarda hacia delante: un widget `digitalisimo-*` de una versión futura. */
  async function w133(s, jar) {
    const r = res();
    const P3 = r.ids['digi-wf-res-futuro'];
    const FUT = 'digitalisimo-futuro-res';
    const before = await op('resilience_doc', { id: P3 });
    const orig = before.widgets.find((w) => w.type === FUT);
    if (!orig) { check(s, 'W13.3', 'la página tiene el widget del futuro', false, short(before.widgets)); return; }
    const sv = await editorSave(main, jar, P3, setHeading('res3h00', 'Guardado con un widget futuro'));
    const loadedMarkers = [];
    walk(sv.loaded || [], (n) => { if (n.widgetType === 'digitalisimo-unconverted') loadedMarkers.push(n); });
    const after = await op('resilience_doc', { id: P3 });
    const m = after.markers.find((x) => x.original === FUT);
    check(s, 'W13.3a', '4.3.0.166: abrir (el editor recibe el marcador) y guardar un documento con un widget `digitalisimo-*` no registrado lo conserva en el marcador de K con su original', sv.ok && loadedMarkers.length === 1 && !!m && m.reason === 'unknown' && m.orig_id === 'res3f01' && m.md5 === orig.md5 && !after.widgets.some((w) => w.type === FUT) && after.headings.res3h00 === 'Guardado con un widget futuro', short({ ok: sv.ok, why: sv.why, cargados: loadedMarkers.length, marcador: m, contador: after.unconverted }));
    if (!QUICK) {
      let ed = null;
      try {
        ed = await openEditor(P3);
        const inPreview = await ed.frame.evaluate(() => document.querySelectorAll('.elementor-widget-digitalisimo-unconverted').length);
        const saved = await editorUpdate(ed, 'res3h00', 'Guardado en el editor real');
        const real = saved === 'ok' ? await waitHeading(P3, 'res3h00', 'Guardado en el editor real') : null;
        const mr = real && real.markers.find((x) => x.original === FUT);
        check(s, 'W13.3c', 'en el editor real (Chrome), abrir y guardar conserva el marcador con el original (el marcador está registrado a tiempo para el guardado)', saved === 'ok' && !!mr && mr.md5 === orig.md5 && real.markers.length === 1, short({ saved, enLaVistaPrevia: inPreview, marcador: mr, errores: ed.pageErrors.slice(0, 2) }));
      } catch (e) {
        if (ed) await shot(ed.page, 'W13.3-editor-falla');
        check(s, 'W13.3c', 'guarda hacia delante en el editor real', false, String(e.stack || e.message || e).slice(0, 600));
      } finally {
        if (ed) await ed.ctx.close().catch(() => {});
      }
    }
    // Cuando el tipo existe, K lo devuelve al leer (sin escribir nada hasta que alguien guarda).
    await op('resilience_future', { on: 1 });
    try {
      const page = await editorPage(main, jar, P3);
      const cfg = await eajax(main, jar, page.nonce, P3, { cfg: { action: 'get_document_config', data: { id: P3 } } });
      const els = ((cfg.responses.cfg || {}).data || {}).elements || [];
      let back = null;
      walk(els, (n) => { if (n.widgetType === FUT) back = n; });
      const html = (await main('GET', r.pages['digi-wf-res-futuro'])).text;
      check(s, 'W13.3b', 'con el tipo registrado (la versión que lo trae), el editor recibe el widget original con su ID y el front lo pinta', !!back && back.id === 'res3f01' && JSON.stringify(back.settings.lista) === JSON.stringify(['a', 'b']) && back.settings.texto === 'Hola desde el futuro' && /class="digi-wf-futuro">Hola desde el futuro</.test(html), short({ back: back && { id: back.id, settings: back.settings }, front: /digi-wf-futuro/.test(html) }));
    } finally {
      await op('resilience_future', { on: 0 }).catch(() => {});
    }
  }

  return { W0, W3, W13 };
};

// ── PHP de los sitios sin WooCommerce (W0.9), escrito en $OUT/res-nowoo-<x>/ ────────────────────────────────────
const FATAL_PHP = String.raw`<?php
register_shutdown_function( static function () {
	$e = error_get_last();
	if ( $e && in_array( $e['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
		file_put_contents( '/wordpress/wp-content/probe/fatal.log', ( $_SERVER['REQUEST_URI'] ?? '' ) . ' ' . $e['message'] . ' en ' . $e['file'] . ':' . $e['line'] . "\n", FILE_APPEND );
	}
} );
`;

const NOWOO_ACTIVATE_PHP = String.raw`<?php
// Paso del blueprint del sitio sin WooCommerce de woo-flow.sh (resilience, W0.9): sonda, registro de fatales,
// DIGITALÍSIMO Elements y el tema DIGITALÍSIMO; nada de WooCommerce.
require '/wordpress/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$dir  = '/wordpress/wp-content/probe';
$fail = static function ( $message ) use ( $dir ) {
	file_put_contents( $dir . '/setup-error.txt', $message . "\n", FILE_APPEND );
};
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
	mkdir( WPMU_PLUGIN_DIR, 0777, true );
}
copy( $dir . '/mu.php', WPMU_PLUGIN_DIR . '/digi-res-probe.php' );
copy( $dir . '/fatal.php', WPMU_PLUGIN_DIR . '/digi-fatal-log.php' );
if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
	$fail( 'WooCommerce está instalado en el sitio que debe no tenerlo' );
}
$result = activate_plugin( 'digitalisimo-elements/pro-elements.php' );
if ( is_wp_error( $result ) ) {
	$fail( 'No se pudo activar DIGITALÍSIMO Elements: ' . $result->get_error_message() );
}
if ( file_exists( get_theme_root() . '/digitalisimo-theme/style.css' ) ) {
	switch_theme( 'digitalisimo-theme' );
}
global $wp_rewrite;
$wp_rewrite->set_permalink_structure( '/%postname%/' );
flush_rewrite_rules( false );
update_option( 'elementor_onboarded', true );
update_option( 'digi_res_ready', time() );
`;

const NOWOO_MU_PHP = String.raw`<?php
/**
 * Plugin Name: Sonda del sitio sin WooCommerce (woo-flow.sh, resilience; sólo Playground)
 * Description: ?digi_res=ping|session&token=… (token de probe/options.json). Nunca forma parte de un plugin.
 */
defined( 'ABSPATH' ) || exit;
add_filter( 'pre_wp_mail', '__return_true' );
add_action(
	'wp_loaded',
	static function () {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- sonda de Playground protegida por token.
		if ( ! isset( $_GET['digi_res'] ) ) {
			return;
		}
		$opts = json_decode( (string) @file_get_contents( WP_CONTENT_DIR . '/probe/options.json' ), true );
		if ( empty( $opts['token'] ) || ! hash_equals( (string) $opts['token'], (string) wp_unslash( $_GET['token'] ?? '' ) ) ) {
			wp_send_json( array( 'error' => 'token' ), 403 );
		}
		wp_set_current_user( 1 );
		$op     = sanitize_key( wp_unslash( $_GET['digi_res'] ) );
		$report = '\\Digitalisimo\\Elements\\Import\\Kits\\Report';
		if ( 'ping' === $op ) {
			wp_send_json(
				array(
					'ready'       => (int) get_option( 'digi_res_ready' ),
					'woocommerce' => defined( 'WC_VERSION' ) || class_exists( 'WooCommerce', false ),
					'k'           => class_exists( $report ),
					'plugins'     => get_option( 'active_plugins' ),
				)
			);
		}
		if ( 'session' === $op ) {
			$session = sanitize_text_field( wp_unslash( $_GET['session'] ?? '' ) );
			$canon   = static function ( $value ) use ( &$canon ) {
				if ( ! is_array( $value ) ) {
					return $value;
				}
				if ( array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
					ksort( $value, SORT_STRING );
				}
				foreach ( $value as $key => $item ) {
					$value[ $key ] = $canon( $item );
				}
				return $value;
			};
			global $wpdb;
			$docs = array();
			$ids  = $wpdb->get_col( "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_elementor_data' AND p.post_type <> 'revision'" ); // phpcs:ignore WordPress.DB
			foreach ( $ids as $id ) {
				$post = get_post( (int) $id );
				$raw  = get_post_meta( (int) $id, '_elementor_data', true );
				$tree = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
				if ( ! $post || ! is_array( $tree ) ) {
					continue;
				}
				$markers = array();
				$types   = array();
				$walk    = static function ( array $nodes ) use ( &$walk, &$markers, &$types, $canon ) {
					foreach ( $nodes as $node ) {
						if ( ! is_array( $node ) ) {
							continue;
						}
						if ( 'widget' === ( $node['elType'] ?? '' ) ) {
							$types[] = (string) ( $node['widgetType'] ?? '' );
							if ( 'digitalisimo-unconverted' === ( $node['widgetType'] ?? '' ) ) {
								$payload   = (array) ( $node['settings']['digitalisimo_unconverted'] ?? array() );
								$original  = (array) ( $payload['element'] ?? array() );
								$markers[] = array(
									'original' => (string) ( $original['widgetType'] ?? $original['elType'] ?? '' ),
									'orig_id'  => (string) ( $original['id'] ?? '' ),
									'reason'   => (string) ( $payload['reason'] ?? '' ),
									'source'   => (string) ( $payload['source'] ?? '' ),
									'md5'      => md5( (string) wp_json_encode( $canon( (array) ( $original['settings'] ?? array() ) ) ) ),
								);
							}
						}
						if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
							$walk( $node['elements'] );
						}
					}
				};
				$walk( $tree );
				sort( $types );
				$docs[ $post->post_type . ':' . $post->post_name ] = array( 'status' => $post->post_status, 'types' => $types, 'markers' => $markers );
			}
			ksort( $docs );
			wp_send_json(
				array(
					'report'      => class_exists( $report ) ? $report::public_report( $session ) : null,
					'docs'        => $docs,
					'unconverted' => (int) get_option( 'digitalisimo_elements_unconverted' ),
					'woocommerce' => class_exists( 'WooCommerce', false ),
					'fatal'       => (string) @file_get_contents( WP_CONTENT_DIR . '/probe/fatal.log' ),
				)
			);
		}
		// phpcs:enable
		wp_send_json( array( 'error' => 'desconocido' ), 404 );
	},
	1
);
`;
