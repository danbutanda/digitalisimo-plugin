// Paridad visual del cambio de tema (theme-parity.sh): Hello 3.5.1 o un hijo suyo frente al tema DIGITALÍSIMO, con un
// kit con colores y tipografías globales y «Estilo del tema» (botones, enlaces, encabezados y formularios), cambiando
// desde Apariencia (VIA=apariencia) o con «Migrar» de DIGITALÍSIMO Migración (VIA=migrar), en un sitio o en una red.
// Por URL y ancho compara con la fase A (antes del cambio): estilos calculados de los elementos clave (exactos), píxeles
// de la captura de página completa (máximo MAX_PIXELS, 0,5 % por defecto, como theme-switch.sh: el suavizado de texto
// y de bordes da diferencias sueltas de un píxel; un color o una fuente cambiados mueven decenas de miles), y que
// ninguna hoja que no sea del tema anterior falte después. Las fases:
//   apariencia: A (tema de origen) · B (tema propio desde Apariencia) · C (vuelta al origen desde Apariencia) ·
//               B2 (otra vez el tema propio) · R (reversión como «wp digitalisimo-theme revert»)
//   migrar:     A (con «Migrar» detenido justo antes de su etapa del tema) · B (al terminar «Migrar»); con BREAK=1 el
//               tema propio pierde el CSS adicional y «Migrar» debe volver solo al tema anterior (B igual que A).
// En la red, el sitio 3 lleva un hijo con PHP que no se puede trasladar: desde Apariencia cambia (el administrador
// decide) y el registro del paso debe decir qué no trajo; «Migrar» no lo cambia y su informe dice por qué y qué hacer.
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');
const http = require('http');

const OUT = process.env.OUT;
const conf = JSON.parse(fs.readFileSync(path.join(OUT, 'conf.json'), 'utf8'));
const PORT = conf.port;
const network = conf.scenario === 'network';
const base = network ? 'http://playground.test' : `http://127.0.0.1:${PORT}`;
const token = fs.readFileSync(path.join(OUT, 'token'), 'utf8').trim();
const MAX_PIXELS = Number(process.env.MAX_PIXELS || 0.005);
const WIDTHS = [1280, 390];
const PATHS = { home: '/', post: '/tp-entrada/', page: '/tp-pagina/' };
const SELECTORS = {
  body: 'body', h1: 'h1', h2: '.elementor-widget-heading h2, h2', h3: 'h3', link: '.tp-links a', button: '.elementor-button',
  rawButton: 'button.tp-btn', input: 'input.tp-input', textarea: 'textarea.tp-textarea', select: 'select.tp-select', label: 'label.tp-label',
  header: '.elementor-location-header, header.site-header', footer: '.elementor-location-footer, footer.site-footer',
  custom: '.tp-custom', childBox: '.tp-child-box', extra: '.tp-extra', font: '.tp-font', phpHead: '.tp-php-head',
};
const PROPS = ['color', 'background-color', 'background-image', 'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing', 'text-transform',
  'text-decoration-line', 'text-decoration-thickness', 'text-underline-offset', 'border-top-color', 'border-top-width', 'border-top-style', 'border-left-color', 'border-left-width',
  'border-top-left-radius', 'padding-top', 'padding-left', 'margin-top', 'box-shadow', 'width'];

const problems = [];
const notes = [];
const result = { conf, phases: {}, compare: {}, state: {} };
const fail = (msg) => { problems.push(msg); console.log('  FALLA ' + msg); };
const check = (ok, msg) => { if (!ok) fail(msg); };
const log = (msg) => console.log(msg);
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

function findPuppeteerChrome() {
  return process.env.CHROME_PATH || ['/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser'].find((p) => fs.existsSync(p));
}

async function api(page, op, extra = '', at = base) {
  const url = `${at.replace(/\/$/, '')}/?tp=${op}&token=${token}${extra}`;
  const res = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 300000 });
  const text = await res.text();
  try { return JSON.parse(text); } catch (e) { throw new Error(`tp=${op}: respuesta no válida ${res.status()}: ${text.slice(0, 300)}`); }
}

async function login(page) {
  await page.goto(`${base}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 300000 });
  if (await page.$('#user_login')) {
    await page.$eval('#user_login', (el) => { el.value = 'admin'; });
    await page.$eval('#user_pass', (el) => { el.value = 'password'; });
    await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 300000 }), page.click('#wp-submit')]);
  }
}

/** Lo que se compara de una página: estilos calculados de los elementos clave, hojas y bloques de estilo. */
function collect(selectors, props) {
  const out = { styles: {}, sheets: [], inline: [], bodyClass: document.body.className.split(/\s+/).filter((c) => /^elementor-kit-/.test(c)) };
  for (const [key, selector] of Object.entries(selectors)) {
    const el = document.querySelector(selector);
    if (!el) { out.styles[key] = null; continue; }
    const s = getComputedStyle(el);
    out.styles[key] = Object.fromEntries(props.map((p) => [p, s.getPropertyValue(p)]));
  }
  for (const link of document.querySelectorAll('link[rel="stylesheet"]')) out.sheets.push({ id: link.id || '', href: (link.getAttribute('href') || '').replace(/[?#].*$/, '') });
  for (const style of document.querySelectorAll('style[id]')) out.inline.push(style.id);
  return out;
}

async function capture(browser, phase, sites) {
  const page = await browser.newPage();
  const shots = path.join(OUT, 'shots');
  fs.mkdirSync(shots, { recursive: true });
  const data = {};
  for (const [id, site] of Object.entries(sites)) {
    data[id] = {};
    for (const [key, p] of Object.entries(PATHS)) {
      data[id][key] = {};
      for (const w of WIDTHS) {
        await page.setViewport({ width: w, height: 900 });
        const target = `${site.home.replace(/\/$/, '')}${p}`;
        const errors = [];
        const onError = (e) => errors.push(String(e.message || e).slice(0, 200));
        page.on('pageerror', onError);
        const res = await page.goto(target, { waitUntil: 'networkidle0', timeout: 300000 });
        await page.evaluate(() => (document.fonts ? document.fonts.ready : null)).catch(() => {});
        await page.evaluate(() => Promise.race([Promise.all([...document.images].map((i) => { if (i.loading === 'lazy') i.loading = 'eager'; return i.complete ? null : new Promise((r) => { i.onload = r; i.onerror = r; }); })), new Promise((r) => setTimeout(r, 15000))])).catch(() => {});
        await page.mouse.move(0, 0);
        await sleep(500);
        const row = await page.evaluate(collect, SELECTORS, PROPS);
        row.status = res ? res.status() : 0;
        row.errors = errors;
        row.shot = path.join(shots, `${phase}-${id}-${key}-${w}.png`);
        await page.screenshot({ path: row.shot, fullPage: true, captureBeyondViewport: true });
        page.off('pageerror', onError);
        data[id][key][w] = row;
      }
    }
  }
  await page.close();
  result.phases[phase] = data;
  log(`  fase ${phase}: ${Object.keys(sites).length} sitio(s) × ${Object.keys(PATHS).length} URL × ${WIDTHS.length} anchos`);
  return data;
}

async function pixelDiff(page, fileA, fileB) {
  const a = fs.readFileSync(fileA).toString('base64');
  const b = fs.readFileSync(fileB).toString('base64');
  return page.evaluate(async (a, b) => {
    const load = (src) => new Promise((resolve, reject) => { const i = new Image(); i.onload = () => resolve(i); i.onerror = () => reject(new Error('imagen')); i.src = `data:image/png;base64,${src}`; });
    const [ia, ib] = await Promise.all([load(a), load(b)]);
    const w = Math.max(ia.width, ib.width), h = Math.max(ia.height, ib.height);
    const draw = (img) => { const c = document.createElement('canvas'); c.width = w; c.height = h; const x = c.getContext('2d'); x.fillStyle = '#ff00ff'; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0); return x.getImageData(0, 0, w, h).data; };
    const da = draw(ia), db = draw(ib);
    let diff = 0;
    for (let i = 0; i < da.length; i += 4) if (Math.abs(da[i] - db[i]) > 16 || Math.abs(da[i + 1] - db[i + 1]) > 16 || Math.abs(da[i + 2] - db[i + 2]) > 16) diff++;
    return { ratio: diff / (w * h), a: `${ia.width}x${ia.height}`, b: `${ib.width}x${ib.height}` };
  }, a, b);
}

/** Hojas del tema anterior (Hello y el hijo) que el tema propio sustituye: no cuentan como «falta». */
function ownSheet(sheet) {
  return /\/themes\/hello-elementor\//.test(sheet.href) || /^hello-elementor(-theme-style|-header-footer)?-css$/.test(sheet.id);
}

/** Compara la fase `phase` con A. `expectDiff`: sitios donde una diferencia es lo esperado (y se anota). */
async function compare(browser, phase, sites, opts = {}) {
  const page = await browser.newPage();
  await page.goto('about:blank');
  const A = result.phases.A;
  const B = result.phases[phase];
  if (!A || !B) { fail(`${phase}: falta la fase A o la ${phase} para comparar`); await page.close(); return {}; }
  const summary = {};
  for (const id of Object.keys(sites)) {
    const diffs = [];
    for (const key of Object.keys(PATHS)) {
      for (const w of WIDTHS) {
        const a = A[id][key][w];
        const b = B[id][key][w];
        if (b.status !== a.status) diffs.push(`${key}@${w}: HTTP ${a.status} → ${b.status}`);
        if (b.errors.length) diffs.push(`${key}@${w}: errores de JavaScript: ${b.errors.join(' | ')}`);
        for (const [sel, sa] of Object.entries(a.styles)) {
          const sb = b.styles[sel];
          if (!sa && !sb) continue;
          if (!sa || !sb) { diffs.push(`${key}@${w} ${sel}: ${sa ? 'desaparece' : 'aparece'}`); continue; }
          for (const prop of PROPS) if (sa[prop] !== sb[prop]) diffs.push(`${key}@${w} ${sel} ${prop}: «${sa[prop]}» → «${sb[prop]}»`);
        }
        for (const sheet of a.sheets) {
          if (ownSheet(sheet)) continue;
          if (!b.sheets.some((s) => (sheet.id && s.id === sheet.id) || s.href === sheet.href)) diffs.push(`${key}@${w}: falta la hoja ${sheet.id || sheet.href}`);
        }
        for (const id2 of a.inline) {
          if (/^hello-/.test(id2)) continue;
          if (!b.inline.includes(id2)) diffs.push(`${key}@${w}: falta el bloque de estilo #${id2}`);
        }
        if (JSON.stringify(a.bodyClass) !== JSON.stringify(b.bodyClass)) diffs.push(`${key}@${w}: clase del kit ${a.bodyClass} → ${b.bodyClass}`);
        const px = await pixelDiff(page, a.shot, b.shot);
        if (px.ratio > MAX_PIXELS) diffs.push(`${key}@${w}: píxeles ${(px.ratio * 100).toFixed(2)} % (${px.a} → ${px.b})`);
        result.compare[`${phase}-${id}-${key}-${w}`] = px;
      }
    }
    summary[id] = diffs;
    const kind = sites[id].kind;
    if ((opts.expectDiff || []).includes(String(id))) {
      check(diffs.length > 0, `${phase} sitio ${id} (${kind}): se esperaba una diferencia (PHP del hijo que no se traslada) y no la hubo`);
      notes.push(`${phase} sitio ${id} (${kind}): ${diffs.length} diferencias esperadas (PHP del hijo no trasladado), p. ej. ${diffs.slice(0, 3).join('; ')}`);
    } else if (diffs.length) {
      fail(`${phase} sitio ${id} (${kind}): ${diffs.length} diferencias con A:\n      ${diffs.slice(0, 25).join('\n      ')}`);
    } else {
      log(`  ${phase} sitio ${id} (${kind}): igual que A`);
    }
  }
  await page.close();
  return summary;
}

/** Activa un tema desde Apariencia del sitio (su enlace «Activar», con su nonce), como el administrador. */
async function activate(page, site, stylesheet) {
  await page.goto(`${site.home}wp-admin/themes.php`, { waitUntil: 'domcontentloaded', timeout: 300000 });
  const href = await page.evaluate((s) => { const a = [...document.querySelectorAll('a')].find((x) => /action=activate/.test(x.href) && new URL(x.href).searchParams.get('stylesheet') === s); return a ? a.href : ''; }, stylesheet);
  if (!href) { fail(`sitio ${site.home}: no hay enlace «Activar» de ${stylesheet} en Apariencia`); return; }
  await page.goto(href, { waitUntil: 'domcontentloaded', timeout: 300000 });
  // WordPress corre after_switch_theme en la petición siguiente (la que pinta Temas tras la redirección ya lo hizo).
  await page.goto(`${site.home}wp-admin/themes.php`, { waitUntil: 'domcontentloaded', timeout: 300000 });
}

/** El superadministrador habilita el tema en la red (como «Habilitar en la red» de Red → Temas). */
async function networkEnable(page, stylesheet) {
  const r = await api(page, 'enable', `&theme=${stylesheet}`);
  check(r.allowed && r.allowed.includes(stylesheet), `El tema ${stylesheet} queda habilitado en la red: ${JSON.stringify(r)}`);
}

/**
 * «Guardar y activar» de «Código PHP» de DIGITALÍSIMO Tools, de verdad, como el navegador del administrador: el
 * editor del fragmento, su paso «prepare» (con la casilla de revisión), la visita del escritorio con la sesión y de la
 * portada sin ella, y «activate» con sus reintentos. Devuelve la última respuesta de Tools.
 */
async function toolsActivate(browser, page, site, id) {
  await page.goto(`${site.home}wp-admin/admin.php?page=digitalisimo-tools-elementor-templates&tab=php&edit=${id}`, { waitUntil: 'domcontentloaded' });
  const cfg = await page.evaluate(() => window.digitalisimoToolsPhp || null);
  const code = await page.evaluate(() => { const t = document.querySelector('textarea'); return t ? t.value : ''; });
  if (!cfg || !code) return { success: false, data: { message: 'sin editor de Tools' } };
  const post = (data) => page.evaluate(async (ajax, data) => {
    const r = await fetch(ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data).toString() });
    const t = await r.text();
    try { return JSON.parse(t); } catch (e) { return { success: false, data: { message: `(${r.status}) ${t.slice(0, 300)}` } }; }
  }, cfg.ajax, { action: cfg.action, ...data });
  const title = await page.evaluate(() => { const i = document.querySelector('input[name="title"]'); return i ? i.value : 'PHP del hijo'; });
  const r1 = await post({ do: 'prepare', scope: cfg.scope, blog_id: cfg.blog, id: cfg.id, nonce: cfg.nonces.prepare, title, code_b64: Buffer.from(code).toString('base64'), reviewed: '1' });
  if (!r1.success || !r1.data || !r1.data.sid) return r1;
  // Como tools-php: el escritorio con la sesión (el navegador) y cada portada sin cookies, leída entera y sin seguir
  // redirecciones (la red de la prueba vive en playground.test: se pide a 127.0.0.1 con su cabecera Host).
  const anonGet = (url) => new Promise((resolve) => {
    const u = new URL(url);
    const req = http.request({ host: '127.0.0.1', port: u.hostname === 'playground.test' ? PORT : (u.port || 80), path: u.pathname + u.search, method: 'GET', headers: { Host: u.host } }, (res) => { res.resume(); res.on('end', resolve); });
    req.on('error', resolve);
    req.setTimeout(300000, () => { req.destroy(); resolve(); });
    req.end();
  });
  const visit = async (urls) => {
    await Promise.all((urls || []).map((u) => (u.kind === 'admin' ? null : anonGet(u.url))));
    for (const u of urls || []) if (u.kind === 'admin') await page.goto(u.url, { waitUntil: 'load' }).catch(() => {});
  };
  await visit(r1.data.urls);
  await page.goto(`${site.home}wp-admin/`, { waitUntil: 'domcontentloaded' });
  const step3 = (extra = {}) => post({ do: 'activate', scope: cfg.scope, blog_id: cfg.blog, id: cfg.id, nonce: r1.data.nonces.activate, sid: r1.data.sid, ...extra });
  let r3 = await step3();
  if (r3.success && r3.data && r3.data.retry) { await visit(r3.data.retry); r3 = await step3(); }
  const deadline = Date.now() + 60000;
  while (!r3.success && r3.data && r3.data.pending && Date.now() < deadline) { await sleep(1500); r3 = await step3(); }
  if (!r3.success && r3.data && r3.data.pending) r3 = await step3({ final: '1' });
  return r3;
}

async function state(page) {
  const s = await api(page, 'state');
  return s;
}

/** Comprobaciones del registro y la bitácora del paso en cada sitio tras cambiar al tema propio. */
function checkRecords(phase, st, sites) {
  for (const [id, site] of Object.entries(st.sites)) {
    const rec = site.record || {};
    const logw = ((site.log || {}).warnings || []).map((w) => w.code);
    result.state[`${phase}-${id}`] = { stylesheet: site.stylesheet, origin: rec.origin, warnings: (site.log || {}).warnings, child: site.child, css: site.css, mods: site.mods };
    if (sites[id].switched === false) continue;
    check(site.stylesheet === 'digitalisimo-theme', `${phase} sitio ${id}: tema activo ${site.stylesheet}`);
    check(rec.origin && rec.origin.family === 'hello', `${phase} sitio ${id}: origen hello en el registro (${JSON.stringify(rec.origin)})`);
    check(/tp-custom/.test(site.css || ''), `${phase} sitio ${id}: el CSS adicional del tema de origen llegó al tema propio`);
    check(site.mods && site.mods.tp_mod === `valor del ${site.kind}`, `${phase} sitio ${id}: los mods del origen llegaron (${JSON.stringify(site.mods && site.mods.tp_mod)})`);
    if (site.kind === 'child-php') {
      const php = ((site.log || {}).warnings || []).find((w) => w.code === 'child_php');
      check(php && /wp_head/.test(php.message) && /wp_footer/.test(php.message) && /add_shortcode|shortcode/.test(php.message), `${phase} sitio ${id}: la bitácora dice qué PHP del hijo no se trajo (${php ? php.message : logw.join(',')})`);
    }
  }
}

(async () => {
  const chrome = findPuppeteerChrome();
  const args = ['--no-sandbox', '--disable-dev-shm-usage', '--font-render-hinting=none', '--disable-lcd-text', '--force-color-profile=srgb', '--hide-scrollbars'];
  if (network) args.push(`--host-resolver-rules=MAP playground.test:80 127.0.0.1:${PORT}`);
  const browser = await puppeteer.launch({ executablePath: chrome, headless: true, args });
  const page = await browser.newPage();
  page.setDefaultNavigationTimeout(300000);
  try {
    await login(page);
    let st = await state(page);
    const sites = {};
    for (const [id, s] of Object.entries(st.sites)) sites[id] = { kind: s.kind, home: s.home, from: s.stylesheet };
    log(`Sitios: ${Object.entries(sites).map(([id, s]) => `${id}=${s.kind} (${s.from})`).join(', ')}`);
    const prepare = async () => { for (const s of Object.values(sites)) await api(page, 'prepare', '', s.home); };

    if (conf.via === 'apariencia') {
      await prepare();
      await capture(browser, 'A', sites);
      if (network) await networkEnable(page, 'digitalisimo-theme');
      for (const s of Object.values(sites)) await activate(page, s, 'digitalisimo-theme');
      st = await state(page);
      checkRecords('B', st, sites);
      await capture(browser, 'B', sites);
      await compare(browser, 'B', sites, { expectDiff: Object.keys(sites).filter((id) => sites[id].kind === 'child-php') });
      for (const s of Object.values(sites)) await activate(page, s, s.from);
      st = await state(page);
      for (const [id, s] of Object.entries(st.sites)) check(s.stylesheet === sites[id].from, `C sitio ${id}: vuelve a ${sites[id].from} (${s.stylesheet})`);
      await capture(browser, 'C', sites);
      await compare(browser, 'C', sites);
      for (const s of Object.values(sites)) await activate(page, s, 'digitalisimo-theme');
      checkRecords('B2', await state(page), sites);
      await capture(browser, 'B2', sites);
      await compare(browser, 'B2', sites, { expectDiff: Object.keys(sites).filter((id) => sites[id].kind === 'child-php') });
      for (const s of Object.values(sites)) {
        const r = await api(page, 'revert', '', s.home);
        check(r.ok && r.stylesheet === s.from, `R ${s.home}: la reversión vuelve a ${s.from}: ${JSON.stringify(r)}`);
      }
      await capture(browser, 'R', sites);
      await compare(browser, 'R', sites);
    } else {
      // «Migrar» por AJAX, con la sesión del administrador, como el botón.
      const screen = network ? `${base}/wp-admin/network/settings.php?page=digitalisimo-ep-migration` : `${base}/wp-admin/tools.php?page=digitalisimo-ep-migration`;
      await page.goto(screen, { waitUntil: 'domcontentloaded' });
      const nonce = await page.evaluate(() => { const b = document.querySelector('#digi-migrate'); return b ? b.getAttribute('data-nonce') : ''; });
      check(!!nonce, 'La pantalla de Migrar pinta el botón con su nonce.');
      const step = (data) => page.evaluate(async (ajax, data) => {
        const r = await fetch(ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(data).toString() });
        const t = await r.text();
        try { return JSON.parse(t); } catch (e) { return { success: false, data: `(${r.status}) ${t.slice(0, 200)}` }; }
      }, `${base}/wp-admin/admin-ajax.php`, data);
      const common = { action: 'digitalisimo_migration_step', _ajax_nonce: nonce || '' };
      if (network) common.network = '1';
      let r = await step({ ...common, start: '1', run: 'tpparidad' });
      let data = r.data || {};
      let prepared = false;
      let captured = false;
      const started = Date.now();
      let last = '';
      while (!(data && data.done) && Date.now() - started < 45 * 60 * 1000) {
        if (data.stage !== last) { last = data.stage; log(`  ${Math.round((Date.now() - started) / 1000)} s · ${last}`); }
        // Con Elements ya activo (la etapa de contenido no cambia nada visible aquí: no hay Element Pack), antes de
        // que la tarea llegue al tema: kit, cabecera y pie, y la fase A.
        if (!prepared && ['content', 'import', 'retire', 'theme'].includes(data.stage)) { prepared = true; await prepare(); }
        if (!captured && prepared) {
          captured = true;
          await capture(browser, 'A', sites);
          if (conf.break) await api(page, 'break');
        }
        if (network && data.sites && data.sites.length) {
          for (const site of data.sites) {
            let s = { stage: 'x' };
            for (let i = 0; i < 400 && !(s.done || s.wait_network || (s.idle && !s.stage)); i++) {
              const rs = await page.evaluate(async (ajax, nonce) => {
                const res = await fetch(ajax, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'digitalisimo_migration_step', _ajax_nonce: nonce, start: '' }).toString() });
                try { return JSON.parse(await res.text()); } catch (e) { return { success: false, data: 'respuesta no válida' }; }
              }, site.ajax, site.nonce);
              if (!rs.success) { fail(`paso del sitio ${site.name}: ${rs.data}`); break; }
              s = rs.data;
              if (s.busy) await sleep(2000);
            }
          }
        }
        r = await step({ ...common, start: '' });
        if (!r.success) { fail('paso rechazado: ' + JSON.stringify(r.data).slice(0, 300)); break; }
        data = r.data;
        if (data.busy || data.pause) await sleep(data.busy ? 2000 : 300);
      }
      check(data && data.done, 'Migrar terminó.');
      check(captured, 'Se capturó A antes de la etapa del tema.');
      st = await state(page);
      const mig = st.migration || {};
      result.migration = { failed: mig.failed, error: mig.error, theme: mig.theme, verify: mig.verify, verified: mig.verified, kept: mig.kept, deleted: mig.deleted };
      log('  Migrar: ' + JSON.stringify({ failed: mig.failed, error: mig.error, verified: mig.verified, theme: mig.theme }));
      for (const [id, s] of Object.entries(st.sites)) {
        const row = (mig.theme || {})[id] || {};
        if (sites[id].kind === 'child-php') {
          sites[id].switched = false;
          check(s.stylesheet === sites[id].from && !row.switched, `Migrar: el sitio ${id} (hijo con PHP) se queda con su tema (${s.stylesheet})`);
          check(/wp_head/.test(row.reason || '') && /wp_footer/.test(row.reason || '') && /Código PHP/.test(row.reason || ''), `Migrar: el informe dice qué impide cambiar el sitio ${id} y qué hacer (${row.reason})`);
        } else if (conf.break) {
          sites[id].switched = false;
          check(s.stylesheet === sites[id].from && row.reverted, `Migrar con la portada distinta: vuelve solo a ${sites[id].from} (${s.stylesheet}, ${JSON.stringify(row)})`);
          check(!mig.verified, 'Migrar con la portada distinta: no da la portada por verificada.');
          check((mig.verify || []).some((v) => v.ok === false && /CSS adicional|estilo|hoja/i.test(v.reason || '')), `Migrar con la portada distinta: la verificación dice qué cambió (${JSON.stringify(mig.verify)})`);
        } else {
          check(row.switched && s.stylesheet === 'digitalisimo-theme', `Migrar cambió el sitio ${id} (${sites[id].kind}) al tema propio (${s.stylesheet}, ${row.reason || ''})`);
        }
      }
      checkRecords('B', st, sites);
      await capture(browser, 'B', sites);
      await compare(browser, 'B', sites);
      // El hijo con PHP que se quedó, siguiendo el orden del informe con una activación real en Tools: activar su
      // fragmento con el hijo todavía activo falla (declararía otra vez sus funciones); cambiar de tema en Apariencia y
      // enseguida activarlo funciona, y el sitio vuelve a verse y comportarse como con el hijo.
      const phpId = Object.keys(sites).find((id) => sites[id].kind === 'child-php');
      if (phpId && !conf.break) {
        const site = sites[phpId];
        const index = (st.sites[phpId] || {}).tools || {};
        const itemId = Object.keys(index.items || {}).find((k) => /^digitalisimo-theme:/.test(index.items[k].origin_key || ''));
        check(!!itemId, `Migrar dejó el functions.php del hijo en Código PHP de Tools del sitio ${phpId}: ${JSON.stringify(Object.values(index.items || {}).map((x) => x.origin_key))}`);
        if (itemId) {
          check(index.items[itemId].review === true && !(index.items[itemId].status === 'active'), `El fragmento importado está inactivo y pide revisión: ${JSON.stringify(index.items[itemId])}`);
          const early = await toolsActivate(browser, page, site, itemId);
          result.toolsEarly = early;
          check(!early.success, `Con el hijo activo, Tools no deja activar su functions.php: ${JSON.stringify(early).slice(0, 300)}`);
          log(`  Tools con el hijo activo: ${String((early.data || {}).message || '').slice(0, 160)}`);
          await activate(page, site, 'digitalisimo-theme');
          const late = await toolsActivate(browser, page, site, itemId);
          result.toolsLate = late;
          check(late.success, `Después del cambio de tema, el fragmento se activa: ${JSON.stringify(late).slice(0, 300)}`);
          log(`  Tools tras cambiar de tema: ${String((late.data || {}).message || '').slice(0, 160)}`);
          const only = { [phpId]: site };
          await capture(browser, 'B3', only);
          const prev = result.phases.A;
          await compare(browser, 'B3', only);
          result.phases.A = prev;
        }
      }
    }
  } catch (e) {
    fail('excepción: ' + (e.stack || e));
  } finally {
    await browser.close();
  }
  result.problems = problems;
  result.notes = notes;
  fs.writeFileSync(path.join(OUT, 'theme-parity.json'), JSON.stringify(result, null, 1));
  for (const n of notes) log('  nota: ' + n);
  if (problems.length) { log(`FALLA theme-parity (${conf.scenario}, ${conf.via}${conf.break ? ', BREAK' : ''}): ${problems.length} problema(s)`); process.exit(1); }
  log(`ok theme-parity (${conf.scenario}, ${conf.via}${conf.break ? ', BREAK' : ''})`);
})();
