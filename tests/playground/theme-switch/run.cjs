// A/B del tema en Chrome sin interfaz: Hello 3.5.1 (A) frente al tema DIGITALÍSIMO (B) con el mismo contenido, por
// escenario (S1-S5, S3c, S3h), URL y ancho; después la reversión (C), la ida y vuelta (C2), el modo base (D), el sitio
// sin constructor (E), la vista previa del editor (F) y el bloqueo del asistente (F2). Las diferencias aceptadas están
// en known.json con su motivo: una que no se use en la corrida hace fallar la prueba.
// Uso: node run.cjs <url del servidor>       (lo lanza theme-switch.sh)
//      MODE=measure BASE=<url> node run.cjs  (medición en staging, sólo con autorización; nunca cambia el tema)
'use strict';
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const puppeteer = require('puppeteer-core');

const OUT = process.env.OUT || '/tmp/digi-theme-switch';
const ROOT = process.env.ROOT || path.resolve(__dirname, '../../..');
const MODE = process.env.MODE || 'ab';
const CHROME = process.env.CHROME_PATH || '/usr/bin/google-chrome';
const VIEWPORTS = [{ w: 360, h: 800 }, { w: 768, h: 1024 }, { w: 1280, h: 900 }];
const ALL_SCENARIOS = ['S1', 'S2', 'S3', 'S3c', 'S3h', 'S4', 'S5'];
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const vendorsLib = require(path.join(ROOT, 'tests/playground/brand-audit/vendors.cjs'));

// ---------------------------------------------------------------------------------------------------------------
// Utilidades
// ---------------------------------------------------------------------------------------------------------------

const base = (process.argv[2] || '').replace(/\/$/, '');
const token = MODE === 'ab' ? fs.readFileSync(path.join(OUT, 'token'), 'utf8').trim() : '';
const WOO = (process.env.WOO || '1') !== '0';
const QUICK = process.env.QUICK === '1';
const SCENARIOS = (process.env.SCENARIOS || ALL_SCENARIOS.join(',')).split(',').map((s) => s.trim()).filter(Boolean);
const issues = [];
const accepted = [];
const report = { base, woo: WOO, quick: QUICK, scenarios: {}, phases: {}, issues, accepted, unusedKnown: [] };
// De dónde salió WooCommerce (tests/playground/woocommerce-pin.sh): la copia fijada de reference/ o wordpress.org.
if (WOO) { try { report.woocommerce = JSON.parse(fs.readFileSync(path.join(OUT, 'woocommerce.json'), 'utf8')); } catch (e) { report.woocommerce = null; } }
let known = { entries: [] };

function log(...args) { console.log(...args); }

async function op(name, params = {}, cookies = '') {
  const q = new URLSearchParams({ 'digi-ab': name, token, ...params });
  for (let attempt = 0; attempt < 3; attempt++) {
    try {
      const res = await fetch(`${base}/?${q}`, { headers: cookies ? { cookie: cookies } : {}, redirect: 'manual', signal: AbortSignal.timeout(300000) });
      const text = await res.text();
      try { return JSON.parse(text); } catch (e) { return { error: `respuesta no JSON (${res.status}): ${text.slice(0, 300)}` }; }
    } catch (e) {
      if (attempt === 2) return { error: `op ${name}: ${e.message}` };
      await sleep(3000);
    }
  }
  return { error: `op ${name}: sin respuesta` };
}

/** Anota una diferencia. Si una entrada de known.json la declara, queda como aceptada; si no, la prueba falla.
 *  `amount` (opcional) es la magnitud de la diferencia: una entrada con `max` sólo la acepta hasta ese valor. */
function issue(scenario, phase, url, width, check, detail, amount) {
  const row = { scenario, phase, url, width, check, detail: String(detail) };
  if (amount !== undefined) row.amount = amount;
  const entry = known.entries.find((k) => matches(k, row));
  if (entry) { entry.__used = (entry.__used || 0) + 1; accepted.push({ ...row, known: entry.id, motivo: entry.motivo }); return; }
  issues.push(row);
}

function listMatch(list, value) {
  if (list === undefined || list === '*') return true;
  return (Array.isArray(list) ? list : [list]).map(String).includes(String(value));
}

function matches(k, row) {
  if (!listMatch(k.scenarios, row.scenario) || !listMatch(k.phases, row.phase) || !listMatch(k.urls, row.url) || !listMatch(k.widths, row.width)) return false;
  if (k.check && !new RegExp(k.check).test(row.check)) return false;
  if (k.detail && !new RegExp(k.detail, 'i').test(row.detail)) return false;
  if (k.max !== undefined && !(row.amount !== undefined && row.amount <= k.max)) return false;
  return true;
}

/** Diferencia de dos listas ordenadas (LCS): lo que sólo está en A (-) y lo que sólo está en B (+). */
function diffList(a, b) {
  const n = a.length, m = b.length;
  if (n * m > 4e6) return { removed: a.filter((x) => !b.includes(x)), added: b.filter((x) => !a.includes(x)) };
  const dp = Array.from({ length: n + 1 }, () => new Uint16Array(m + 1));
  for (let i = n - 1; i >= 0; i--) for (let j = m - 1; j >= 0; j--) dp[i][j] = a[i] === b[j] ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
  const removed = [], added = [];
  let i = 0, j = 0;
  while (i < n && j < m) {
    if (a[i] === b[j]) { i++; j++; } else if (dp[i + 1][j] >= dp[i][j + 1]) removed.push(a[i++]); else added.push(b[j++]);
  }
  while (i < n) removed.push(a[i++]);
  while (j < m) added.push(b[j++]);
  return { removed, added };
}

function describeDiff(d) {
  const parts = [];
  for (const x of d.removed.slice(0, 4)) parts.push(`-${x}`);
  for (const x of d.added.slice(0, 4)) parts.push(`+${x}`);
  const extra = d.removed.length + d.added.length - parts.length;
  return parts.join(' | ') + (extra > 0 ? ` | (+${extra} más)` : '');
}

const fileBytes = new Map();
async function assetBytes(url) {
  if (!fileBytes.has(url)) {
    try {
      const res = await fetch(url, { signal: AbortSignal.timeout(120000) });
      const buf = Buffer.from(await res.arrayBuffer());
      fileBytes.set(url, { bytes: buf.length, gzip: zlib.gzipSync(buf, { level: 9 }).length, status: res.status });
    } catch (e) {
      fileBytes.set(url, { bytes: 0, gzip: 0, status: 0 });
    }
  }
  return fileBytes.get(url);
}

function readJsonl(file) {
  if (!fs.existsSync(file)) return [];
  return fs.readFileSync(file, 'utf8').split('\n').filter(Boolean).map((l) => { try { return JSON.parse(l); } catch (e) { return null; } }).filter(Boolean);
}

// ---------------------------------------------------------------------------------------------------------------
// Recogida en la página (se serializa y corre en Chrome)
// ---------------------------------------------------------------------------------------------------------------

function collect(site) {
  const norm = (u) => {
    try {
      const x = new URL(u, location.href);
      for (const k of ['ver', '_wpnonce', 'nonce', '_', 'elementor-preview']) x.searchParams.delete(k);
      return (x.origin === location.origin ? '' : x.origin) + x.pathname + x.search + x.hash;
    } catch (e) { return String(u); }
  };
  // Visible de verdad: con tamaño, sin display/visibility/opacity que lo oculten, fuera de [inert] y aria-hidden, y no
  // recortado por un antepasado con overflow (el desplegable móvil de Hello sigue en el DOM con altura 0).
  const clippedOut = (r, el) => {
    for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
      const st = getComputedStyle(p);
      if (st.overflowX === 'visible' && st.overflowY === 'visible') continue;
      const c = p.getBoundingClientRect();
      if (c.width <= 0 || c.height <= 0 || r.right <= c.left || r.left >= c.right || r.bottom <= c.top || r.top >= c.bottom) return true;
    }
    return false;
  };
  const visible = (el) => {
    const r = el.getBoundingClientRect();
    if (r.width <= 0 || r.height <= 0) return false;
    if (el.closest('[inert],[aria-hidden="true"]')) return false;
    if (el.checkVisibility && !el.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true })) return false;
    const s = getComputedStyle(el);
    if (s.visibility === 'hidden' || s.display === 'none' || parseFloat(s.opacity || '1') <= 0) return false;
    return !clippedOut(r, el);
  };
  const clean = (s) => (s || '').replace(/\s+/g, ' ').trim();
  const text = [];
  if (document.body) {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    const range = document.createRange();
    const cache = new Map();
    for (let n = walker.nextNode(); n; n = walker.nextNode()) {
      const t = clean(n.textContent);
      const parent = n.parentElement;
      if (!t || !parent) continue;
      if (!cache.has(parent)) cache.set(parent, visible(parent));
      if (!cache.get(parent)) continue;
      range.selectNodeContents(n);
      const r = range.getBoundingClientRect();
      if (r.width <= 0 || r.height <= 0 || clippedOut(r, n)) continue;
      text.push(t);
    }
  }
  const links = [...document.querySelectorAll('a[href]')].filter(visible).map((a) => `${norm(a.getAttribute('href'))} «${clean(a.innerText)}»`);
  const images = [...document.querySelectorAll('img')].filter(visible).map((i) => norm(i.currentSrc || i.src).replace(/-\d+x\d+(?=\.[a-z]+$)/i, ''));
  const head = {
    metas: [...document.querySelectorAll('head meta[name]')].map((m) => m.getAttribute('name').toLowerCase()).sort(),
    links: [...document.querySelectorAll('head link[rel]')].map((l) => l.getAttribute('rel').toLowerCase()).filter((r) => !/(^|\s)(stylesheet|preload|modulepreload|prefetch|preconnect|dns-prefetch)(\s|$)/.test(r)).sort(),
  };
  const box = (el) => {
    if (!el) return null;
    const r = el.getBoundingClientRect();
    return { x: Math.round(r.left + scrollX), y: Math.round(r.top + scrollY), w: Math.round(r.width), h: Math.round(r.height) };
  };
  const headerRoot = document.querySelector('#site-header, .elementor-location-header');
  const findText = (root, wanted) => {
    if (!root || !wanted) return null;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (let n = walker.nextNode(); n; n = walker.nextNode()) {
      if (clean(n.textContent) === wanted && n.parentElement && visible(n.parentElement)) return n.parentElement;
    }
    return null;
  };
  const style = (el) => {
    if (!el) return null;
    const s = getComputedStyle(el);
    return { color: s.color, fontSize: s.fontSize, fontWeight: s.fontWeight, fontFamily: s.fontFamily };
  };
  const navLink = headerRoot ? [...headerRoot.querySelectorAll('nav a[href]')].find(visible) : null;
  const skip = document.querySelector('a.skip-link');
  const href = skip ? skip.getAttribute('href') : null;
  const targetId = href && href.startsWith('#') ? href.slice(1) : '';
  const targets = targetId ? document.querySelectorAll(`[id="${CSS.escape(targetId)}"]`) : [];
  const pagination = document.querySelector('nav.pagination, .pagination');
  let paginationRow = null;
  if (pagination) {
    const items = [...pagination.querySelectorAll('a')].filter(visible).map((a) => a.getBoundingClientRect());
    paginationRow = items.length >= 2 ? Math.abs(items[0].top - items[items.length - 1].top) <= 2 && items[items.length - 1].left > items[0].left : null;
  }
  const describe = (el) => el.tagName.toLowerCase() + (el.id ? `#${el.id}` : '') + (el.classList.length ? `.${[...el.classList].slice(0, 2).join('.')}` : '');
  const overflow = [];
  if (document.scrollingElement.scrollWidth > innerWidth && document.body) {
    for (const el of document.body.querySelectorAll('*')) {
      const r = el.getBoundingClientRect();
      if (r.width > 0 && r.right > innerWidth + 1) overflow.push(`${describe(el)}→${Math.round(r.right)}`);
      if (overflow.length >= 4) break;
    }
  }
  const urls = new Set();
  for (const el of document.querySelectorAll('*')) {
    for (const attr of el.attributes) {
      const v = attr.value || '';
      if (/^(https?:)?\/\//i.test(v.trim())) urls.add(v.trim());
      else if (attr.name === 'srcset' || attr.name === 'style') for (const m of v.matchAll(/(https?:)?\/\/[^\s"'),]+/gi)) urls.add(m[0]);
    }
  }
  return {
    title: document.title,
    bodyClass: document.body ? document.body.className : '',
    signature: { text, links, images },
    head,
    metaDescription: document.querySelectorAll('meta[name="description"]').length,
    h1: document.querySelectorAll('h1').length,
    boxes: {
      header: box(headerRoot),
      main: box(document.querySelector('main')),
      footer: box(document.querySelector('#site-footer, .elementor-location-footer')),
      title: box(document.querySelector('.page-header h1')),
    },
    scroll: { sw: document.scrollingElement.scrollWidth, iw: innerWidth, overflow },
    nodes: document.getElementsByTagName('*').length,
    stylesheets: [...document.querySelectorAll('link[rel~="stylesheet"]')].map((l) => ({ id: l.id, href: l.href })),
    computed: {
      title: style(findText(headerRoot, site.name)),
      tagline: style(findText(headerRoot, site.tagline)),
      menu: style(navLink),
    },
    sidebar: !!document.querySelector('#sidebar'),
    skip: { present: !!skip, href, count: targets.length, tag: targets[0] ? targets[0].tagName.toLowerCase() : null },
    contentIds: document.querySelectorAll('[id="content"]').length,
    paginationRow,
    canvas: document.body ? document.body.classList.contains('digitalisimo-canvas') : false,
    pageHeader: !!document.querySelector('.page-header'),
    dep: !!document.getElementById('digi-ab-dep-css'),
    domUrls: [...urls].slice(0, 2000),
  };
}

// ---------------------------------------------------------------------------------------------------------------
// Carga de páginas
// ---------------------------------------------------------------------------------------------------------------

async function newVisitor(browser, phaseTag) {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await page.setCookie({ name: 'digi_ab_phase', value: phaseTag, url: base });
  return { context, page };
}

async function loadUrl(page, item, vp, opts) {
  const errors = [];
  const failed = [];
  const requests = [];
  const target = base + item.path;
  const onConsole = (m) => {
    if (m.type() !== 'error') return;
    const where = (m.location() || {}).url || '';
    if (where && !where.startsWith(base)) return; // Recurso externo: la prueba no tiene red (se revisa en «vendor»).
    errors.push(m.text().slice(0, 300));
  };
  const onPageError = (e) => errors.push(String(e.message || e).slice(0, 300));
  const onResponse = (r) => {
    const u = r.url();
    if (u.startsWith('data:')) return;
    requests.push(u);
    if (r.status() >= 400 && u.startsWith(base) && u.split('#')[0] !== target) failed.push(`${r.status()} ${u}`);
  };
  const onFailed = (r) => { const u = r.url(); if (!u.startsWith('data:')) requests.push(u); };
  page.on('console', onConsole);
  page.on('pageerror', onPageError);
  page.on('response', onResponse);
  page.on('requestfailed', onFailed);
  const result = { key: item.key, path: item.path, width: vp.w };
  try {
    await page.setViewport({ width: vp.w, height: vp.h });
    const response = await page.goto(target, { waitUntil: 'networkidle0', timeout: 180000 });
    result.status = response ? response.status() : 0;
    await page.evaluate(() => (document.fonts ? document.fonts.ready : null)).catch(() => {});
    // Las imágenes diferidas (loading="lazy") fuera de la vista pueden no haberse pedido aún: la captura de página
    // completa las mostraría en blanco en una fase y no en la otra. Se fuerzan y se espera a que terminen (tope 15 s).
    await page.evaluate(() => Promise.race([
      Promise.all([...document.images].map((img) => {
        if (img.loading === 'lazy') img.loading = 'eager';
        return img.complete ? null : new Promise((resolve) => { img.addEventListener('load', resolve, { once: true }); img.addEventListener('error', resolve, { once: true }); });
      })).then(() => Promise.all([...document.images].map((img) => (img.decode ? img.decode().catch(() => {}) : null)))),
      new Promise((resolve) => setTimeout(resolve, 15000)),
    ])).catch(() => {});
    // Con un solo worker, una imagen puede fallar por un tropiezo del servidor y quedar rota en una fase y no en la
    // otra: se vuelve a pedir una vez (tope 15 s) antes de capturar.
    await page.evaluate(() => Promise.race([
      Promise.all([...document.images].filter((img) => img.complete && img.currentSrc && 0 === img.naturalWidth).map((img) => new Promise((resolve) => {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
        const src = img.src; img.removeAttribute('srcset'); img.src = ''; img.src = src;
      }))),
      new Promise((resolve) => setTimeout(resolve, 15000)),
    ])).catch(() => {});
    // Con un solo worker, una imagen puede fallar por un tropiezo del servidor y quedar rota en una fase y no en la
    // otra: se vuelve a pedir una vez (tope 15 s) antes de capturar.
    await page.evaluate(() => Promise.race([
      Promise.all([...document.images].filter((img) => img.complete && img.currentSrc && 0 === img.naturalWidth).map((img) => new Promise((resolve) => {
        img.addEventListener('load', resolve, { once: true });
        img.addEventListener('error', resolve, { once: true });
        const src = img.src; img.removeAttribute('srcset'); img.src = ''; img.src = src;
      }))),
      new Promise((resolve) => setTimeout(resolve, 15000)),
    ])).catch(() => {});
    await sleep(opts.settle || 700);
    Object.assign(result, await page.evaluate(collect, opts.site));
    if (opts.shot) {
      result.shot = path.join(OUT, 'shots', `${opts.shot}-${item.key}-${vp.w}.png`);
      await page.screenshot({ path: result.shot, fullPage: true, captureBeyondViewport: true });
    }
    // El enlace de salto debe verse al recibir el foco (después de la firma y la captura, para no cambiarlas): con el
    // constructor cargado, su `.screen-reader-text` (clip, top:-10000em y 1 px) le gana a cualquier regla en :where().
    result.skipFocus = await page.evaluate(() => {
      const a = document.querySelector('a.skip-link');
      if (!a) return null;
      a.focus({ preventScroll: true });
      const r = a.getBoundingClientRect();
      const s = getComputedStyle(a);
      const out = { focused: document.activeElement === a, w: Math.round(r.width), h: Math.round(r.height), top: Math.round(r.top + scrollY), clip: s.clip, clipPath: s.clipPath };
      out.visible = out.focused && out.w > 1 && out.h > 1 && out.top >= 0 && ['auto', ''].includes(s.clip) && ['none', ''].includes(s.clipPath) && s.visibility !== 'hidden' && s.display !== 'none';
      a.blur();
      return out;
    }).catch(() => null);
  } catch (e) {
    result.status = result.status || 0;
    result.error = String(e.message || e).slice(0, 300);
  }
  page.off('console', onConsole);
  page.off('pageerror', onPageError);
  page.off('response', onResponse);
  page.off('requestfailed', onFailed);
  result.errors = errors;
  result.failed = failed;
  result.requests = [...new Set(requests)];
  return result;
}

async function capture(browser, scenario, phase, urls, viewports, opts = {}) {
  await op('phase', { name: `${scenario}-${phase}` });
  const { context, page } = await newVisitor(browser, `${scenario}-${phase}`);
  if (WOO && opts.ids.woo && opts.ids.woo.productId && urls.some((u) => u.woo)) {
    // Carrito con un producto por el AJAX de WooCommerce (wc-ajax=add_to_cart), que no deja el aviso «añadido al
    // carrito» en la sesión: con ?add-to-cart= el aviso aparecía en la primera vista de la tienda de cada fase, y una
    // fase con otros anchos que A lo mostraba en otro ancho. La página de origen sólo da el contexto de la cookie.
    await page.goto(`${base}/?digi-ab=ping&token=${token}`, { waitUntil: 'domcontentloaded', timeout: 180000 }).catch(() => {});
    const added = await page.evaluate(async (id) => {
      try {
        const res = await fetch('/?wc-ajax=add_to_cart', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `product_id=${id}&quantity=1` });
        const json = await res.json().catch(() => null);
        return { status: res.status, ok: !!(json && json.fragments), error: json && json.error ? 'error' : '' };
      } catch (e) { return { status: 0, ok: false, error: String(e.message || e) }; }
    }, opts.ids.woo.productId).catch((e) => ({ status: 0, ok: false, error: String(e.message || e) }));
    if (!added.ok) issue(scenario, phase, 'cart', '', 'cart.add', `no se pudo añadir el producto al carrito: ${JSON.stringify(added)}`);
  }
  const results = {};
  const started = Date.now();
  for (const item of urls) {
    results[item.key] = {};
    for (const vp of viewports) {
      results[item.key][vp.w] = await loadUrl(page, item, vp, { site: opts.ids.site, shot: opts.shots ? `${phase}-${scenario}` : '' });
    }
  }
  await context.close();
  log(`   ${scenario}·${phase}: ${urls.length} URL × ${viewports.length} anchos en ${Math.round((Date.now() - started) / 1000)} s`);
  return results;
}

async function pixelDiff(page, fileA, fileB, diffFile) {
  const a = fs.readFileSync(fileA).toString('base64');
  const b = fs.readFileSync(fileB).toString('base64');
  const out = await page.evaluate(async (a, b, wantDiff) => {
    const load = (src) => new Promise((resolve, reject) => { const i = new Image(); i.onload = () => resolve(i); i.onerror = () => reject(new Error('imagen')); i.src = `data:image/png;base64,${src}`; });
    const [ia, ib] = await Promise.all([load(a), load(b)]);
    const w = Math.max(ia.width, ib.width), h = Math.max(ia.height, ib.height);
    const draw = (img) => { const c = document.createElement('canvas'); c.width = w; c.height = h; const x = c.getContext('2d'); x.fillStyle = '#ff00ff'; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0); return x.getImageData(0, 0, w, h); };
    const da = draw(ia).data, dbImg = draw(ib), db = dbImg.data;
    let diff = 0;
    for (let i = 0; i < da.length; i += 4) {
      const changed = Math.abs(da[i] - db[i]) > 16 || Math.abs(da[i + 1] - db[i + 1]) > 16 || Math.abs(da[i + 2] - db[i + 2]) > 16;
      if (changed) { diff++; if (wantDiff) { db[i] = 255; db[i + 1] = 0; db[i + 2] = 0; db[i + 3] = 255; } }
    }
    let png = '';
    if (wantDiff && diff / (w * h) > 0.005) { const c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').putImageData(dbImg, 0, 0); png = c.toDataURL('image/png').split(',')[1]; }
    return { diff, total: w * h, ratio: diff / (w * h), a: `${ia.width}x${ia.height}`, b: `${ib.width}x${ib.height}`, png };
  }, a, b, !!diffFile);
  if (out.png && diffFile) fs.writeFileSync(diffFile, Buffer.from(out.png, 'base64'));
  delete out.png;
  return out;
}

// ---------------------------------------------------------------------------------------------------------------
// Comparaciones A/B por URL y ancho (comprobaciones 1-14)
// ---------------------------------------------------------------------------------------------------------------

function themeSheets(r) {
  return (r.stylesheets || []).filter((s) => /\/wp-content\/themes\//.test(s.href));
}

async function compare(scenario, phase, item, w, A, B, opts) {
  const at = (check, detail, amount) => issue(scenario, phase, item.key, w, check, detail, amount);
  if (!A || !B) { at('carga', 'falta la captura'); return; }
  if (A.error || B.error) { if (B.error) at('carga', `B: ${B.error}`); if (A.error) at('carga', `A: ${A.error}`); return; }
  // 1. Estado HTTP.
  if (A.status !== B.status) at('status', `A ${A.status} B ${B.status}`);
  // 2. Firma: texto visible, enlaces, imágenes y la cabecera <head>.
  for (const kind of ['text', 'links', 'images']) {
    const d = diffList(A.signature[kind], B.signature[kind]);
    if (d.removed.length || d.added.length) at(`signature.${kind}`, describeDiff(d));
  }
  for (const kind of ['metas', 'links']) {
    const d = diffList(A.head[kind], B.head[kind]);
    if (d.removed.length || d.added.length) at('head', `${kind === 'links' ? 'link rel' : 'meta name'}: ${describeDiff(d)}`);
  }
  if (opts.signatureOnly) return;
  // 3. Meta description: el mismo número que en A.
  if (A.metaDescription !== B.metaDescription) at('meta.description', `A=${A.metaDescription} B=${B.metaDescription}`);
  // 4. Encabezados h1.
  if (A.h1 !== B.h1) at('h1', `A=${A.h1} B=${B.h1}`);
  // 5. Cajas (±2 px).
  for (const name of ['header', 'main', 'footer', 'title']) {
    const a = A.boxes[name], b = B.boxes[name];
    if (!a && !b) continue;
    if (!a || !b) { at(`box.${name}`, `${name}: ${a ? 'sólo en A' : 'sólo en B'}`); continue; }
    const delta = Math.max(Math.abs(a.x - b.x), Math.abs(a.y - b.y), Math.abs(a.w - b.w), Math.abs(a.h - b.h));
    if (delta > 2) at(`box.${name}`, `${name}: A ${JSON.stringify(a)} B ${JSON.stringify(b)}`);
  }
  // 6. Sin desplazamiento horizontal.
  if (B.scroll.sw > B.scroll.iw) at('scroll', `scrollWidth ${B.scroll.sw} > ${B.scroll.iw} (${(B.scroll.overflow || []).join(', ')})`);
  // 7. Consola y 404 propios.
  const errA = new Set(A.errors);
  for (const e of B.errors) if (!errA.has(e)) at('console', e);
  for (const f of B.failed) at('http404', f);
  // 8. Píxeles (salvo páginas con deslizadores).
  if (!item.slider && A.shot && B.shot && fs.existsSync(A.shot) && fs.existsSync(B.shot)) {
    const px = await pixelDiff(opts.diffPage, A.shot, B.shot, path.join(OUT, 'shots', `D-${scenario}-${item.key}-${w}.png`));
    B.pixels = px;
    if (px.ratio > 0.005) at('pixels', `${(px.ratio * 100).toFixed(2)} % (A ${px.a}, B ${px.b})`);
  }
  // 9. Hojas del tema en B (sólo digitalisimo-theme*, no más bytes que en A) y nodos del DOM.
  let bytesA = 0, bytesB = 0;
  for (const s of themeSheets(A)) bytesA += (await assetBytes(s.href)).bytes;
  for (const s of themeSheets(B)) {
    bytesB += (await assetBytes(s.href)).bytes;
    if (!/^digitalisimo-theme/.test(s.id) || !/\/themes\/digitalisimo-theme\//.test(s.href)) at('styles.handles', `hoja del tema ajena: ${s.id} ${s.href.replace(base, '')}`);
  }
  if (bytesB > bytesA) at('styles.bytes', `B ${bytesB} B > A ${bytesA} B`);
  if (B.nodes > A.nodes + 5) at('nodes', `B ${B.nodes} > A ${A.nodes} + 5`, B.nodes - A.nodes);
  if (A.dep && !B.dep) at('deps', 'digi-ab-dep (depende de hello-elementor-theme-style) sólo en A');
  // 10. Ninguna URL hacia un plugin de origen (DOM y red).
  for (const u of new Set([...(B.domUrls || []), ...(B.requests || [])])) {
    const v = vendorsLib.classify(opts.vendors, u, { content: true });
    if (v) at('vendor', `${u.slice(0, 160)} (${v.why})`);
  }
  // 12. Cabecera dinámica: estilos calculados del título, el lema y el primer enlace del menú.
  if (opts.computed) {
    for (const name of ['title', 'tagline', 'menu']) {
      const a = A.computed[name], b = B.computed[name];
      if (!a && !b) continue;
      if (!a || !b) { at(`computed.${name}`, `${name}: ${a ? 'sólo en A' : 'sólo en B'}`); continue; }
      for (const prop of ['color', 'fontSize', 'fontWeight', 'fontFamily']) if (a[prop] !== b[prop]) at(`computed.${name}`, `${name} ${prop}: A ${a[prop]} B ${b[prop]}`);
    }
  }
  // 13. Tienda y producto sin la barra lateral de theme-compat.
  if (item.woo && B.sidebar) at('sidebar', '#sidebar en B');
  // 14. Un solo #content y el enlace de salto llega a él.
  if (B.skip.present) {
    if (B.skip.count !== 1) at('skip', A.skip.present && A.skip.count === 0 ? `B sin destino ${B.skip.href} (A tampoco tenía destino)` : `B: ${B.skip.count} destinos para ${B.skip.href}`);
    else if (B.skip.tag === 'span' && !(A.skip.count === 1 && A.skip.tag === 'span')) at('skip', `B <span#content> que A no tenía (A ${A.skip.count ? A.skip.tag : 'sin destino'})`);
  } else if (A.skip.present) {
    at('skip', 'B sin enlace de salto');
  }
  if (B.skip.present && B.skip.href === '#content' && B.contentIds > 1) at('skip', `#content repetido (${B.contentIds})`);
  // 15. El enlace de salto se ve al recibir el foco, como en A.
  if (A.skipFocus && A.skipFocus.visible && !(B.skipFocus && B.skipFocus.visible)) at('skip.focus', `B: el enlace de salto no se ve con el foco ${JSON.stringify(B.skipFocus)}`);
}

// ---------------------------------------------------------------------------------------------------------------
// Fases y escenarios
// ---------------------------------------------------------------------------------------------------------------

function buildUrls(ids) {
  const p = ids.pages || {};
  let urls = [
    { key: 'home', path: '/', slider: true },
    { key: 'builder', path: p['digi-ts-constructor'] },
    { key: 'post', path: ids.post },
    { key: 'category', path: ids.category },
    { key: 'blog', path: p['digi-ts-blog'] },
    { key: 'search', path: '/?s=prueba' },
    { key: '404', path: '/digi-ts-no-existe/' },
    { key: 'canvas', path: p['digi-ts-canvas'] },
    { key: 'full', path: p['digi-ts-ancho-completo'] },
    { key: 'carousel', path: p['digi-ts-carrusel'], slider: true },
    { key: 'short', path: p['digi-ts-corta'] },
    { key: 'notitle', path: p['digi-ts-sin-titulo'] },
    { key: 'child', path: p['digi-ts-hijo'] },
  ];
  if (WOO) {
    const w = ids.woo || {};
    urls.push({ key: 'shop', path: w.shop, woo: true }, { key: 'product', path: w.product, woo: true }, { key: 'cart', path: w.cart, woo: true });
  }
  const missing = urls.filter((u) => !u.path).map((u) => u.key);
  if (missing.length) throw new Error(`Faltan URL de prueba: ${missing.join(', ')}`);
  if (QUICK) urls = urls.filter((u) => ['home', 'post', 'blog', 'full', 'short', 'notitle', 'child', 'shop', 'product', 'cart'].includes(u.key));
  return urls;
}

async function adminLogin(browser) {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 180000 });
  await page.type('#user_login', 'admin');
  await page.type('#user_pass', 'password');
  await Promise.all([page.waitForNavigation({ timeout: 180000, waitUntil: 'domcontentloaded' }), page.click('#wp-submit')]);
  // Las peticiones de la prueba salen de una segunda pestaña de la misma sesión, quieta en la ruta de prueba (JSON, sin
  // scripts): la del escritorio puede navegar sola (redirecciones de asistentes) y dejar el evaluate en un marco suelto.
  const fetcher = await context.newPage();
  await fetcher.goto(`${base}/?digi-ab=ping&token=${token}`, { waitUntil: 'domcontentloaded', timeout: 180000 });
  return { context, page, fetcher };
}

async function adminFetch(admin, url, init = {}) {
  for (let attempt = 0; ; attempt++) {
    try {
      return await admin.fetcher.evaluate(async (url, init) => {
        const res = await fetch(url, { credentials: 'include', ...init });
        return { status: res.status, text: await res.text() };
      }, url, init);
    } catch (e) {
      if (attempt >= 2) return { status: 0, text: `adminFetch: ${String(e.message || e).slice(0, 200)}` };
      await sleep(3000);
      await admin.fetcher.goto(`${base}/?digi-ab=ping&token=${token}`, { waitUntil: 'domcontentloaded', timeout: 180000 }).catch(() => {});
    }
  }
}

async function warmAdmin(admin) {
  await admin.page.goto(`${base}/wp-admin/index.php`, { waitUntil: 'domcontentloaded', timeout: 180000 }).catch((e) => log(`   (calentamiento: ${e.message})`));
  await sleep(1500);
}

async function adminCookies(admin) {
  return (await admin.page.cookies(`${base}/wp-admin/`)).map((c) => `${c.name}=${c.value}`).join('; ');
}

const parts = (rec) => (rec && rec.record && rec.record.header_footer) || {};
const generatedOf = (rec, part) => (rec.generated || []).filter((g) => g.part === `hello_${part}`);

function expectRecordB(name, rec, at) {
  const r = rec.record;
  if (!r) { at('record', 'no hay registro digitalisimo_theme_switch tras el cambio'); return; }
  if (!r.origin || r.origin.family !== 'hello') at('record.origin', `origin.family = ${r.origin && r.origin.family}`);
  if (name === 'S4' && !(r.origin && r.origin.child)) at('record.origin', 'origin.child debería ser true (hijo de Hello)');
  if (rec.parity !== true) at('record.parity', `digitalisimo_theme_parity() = ${rec.parity}`);
  if (rec.recordBytes >= 1024) at('record.size', `${rec.recordBytes} B serializados (máximo 1 KB, P-2)`);
  const autoload = rec.autoload || {};
  if (autoload.digitalisimo_theme_switch && !/^(on|yes|auto-on|auto)$/.test(autoload.digitalisimo_theme_switch.autoload)) at('record.autoload', `registro con autoload ${autoload.digitalisimo_theme_switch.autoload}`);
  if (autoload.digitalisimo_theme_switch_log && !/^(off|no|auto-off)$/.test(autoload.digitalisimo_theme_switch_log.autoload)) at('record.autoload', `bitácora con autoload ${autoload.digitalisimo_theme_switch_log.autoload}`);
  const expected = { S1: ['classic', 'classic'], S5: ['classic', 'classic'], S4: ['classic', 'classic'], S2: ['tb', 'tb'], S3: ['dynamic', 'dynamic'], S3c: ['dynamic', 'dynamic'], S3h: ['hidden', 'dynamic'] }[name];
  const hf = parts(rec);
  if (expected && (hf.header !== expected[0] || hf.footer !== expected[1])) at('record.header_footer', `header=${hf.header} footer=${hf.footer} (se esperaba ${expected.join('/')})`);
  if (/^S3/.test(name)) {
    for (const part of ['header', 'footer']) {
      const want = !(name === 'S3h' && part === 'header');
      const list = generatedOf(rec, part);
      if (want && !(list.length === 1 && list[0].status === 'publish')) at('record.generated', `${part}: ${JSON.stringify(list.map((g) => g.status))} (se esperaba una plantilla publicada)`);
      if (!want && list.length) at('record.generated', `${part} oculto: no debería generarse plantilla (${list.length})`);
    }
    if (hf.pending) at('record.pending', `header_footer.pending sigue en true (blocked: ${hf.blocked || '—'})`);
  }
}

async function runScenario(ctx, name) {
  const { browser, admin, ids, urls, vendors } = ctx;
  const viewports = QUICK ? VIEWPORTS.filter((v) => v.w !== 768) : VIEWPORTS;
  const at = (phase, check, detail) => issue(name, phase, '', '', check, detail);
  const S = report.scenarios[name] = { started: new Date().toISOString() };
  log(`\n== ${name}`);
  const setup = await op('scenario', { name });
  S.setup = setup;
  if (setup.error) { at('setup', 'setup', setup.error); return; }
  if ((setup.notes || []).length) log(`   notas: ${setup.notes.join('; ')}`);
  if (name === 'S2' && Object.values(setup.templates || {}).some((v) => typeof v !== 'number')) at('setup', 'setup', `plantillas del constructor: ${JSON.stringify(setup.templates)}`);
  await warmAdmin(admin);

  // Inventario previo de Tools (FR-8, FR-10): sólo lectura, con Hello o su hijo activo.
  if (name === 'S4' || name === 'S5') {
    const page = await adminFetch(admin, `${base}/wp-admin/admin.php?page=digitalisimo-plugins`);
    const html = page.text;
    const start = html.search(/Antes de cambiar de tema/i);
    S.inventory = start >= 0 ? html.slice(start, start + 6000).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 1500) : '';
    if (page.status !== 200) at('A', 'tools.inventory', `Digitalisimo → Plugins respondió ${page.status}`);
    else if (start < 0) at('A', 'tools.inventory', 'falta la sección «Antes de cambiar de tema» en la sección Tema de Tools');
    else if (name === 'S4' && !/digi-hello-child|DIGI Hello Child/i.test(S.inventory)) at('A', 'tools.inventory', 'el inventario no nombra el hijo activo');
    else if (name === 'S4' && !/PHP del hijo no se carga/i.test(S.inventory)) at('A', 'tools.inventory', 'falta el aviso «El código PHP del hijo no se carga…»');
    else if (name === 'S5' && !/description/i.test(S.inventory)) at('A', 'tools.inventory', 'falta el aviso de la meta description de Hello sin SEO (FR-10)');
  }

  // A · Hello.
  const A = await capture(browser, name, 'A', urls, viewports, { ids, shots: true });
  const hashA = await op('mods-hash');
  S.hashA = hashA;

  // B · cambio al tema y calentamiento (como administrador; en S3c, primero una visita anónima y luego el cron).
  await op('phase', { name: `${name}-B` });
  const sw = await op('switch', { to: 'digitalisimo-theme' });
  if (sw.error || sw.stylesheet !== 'digitalisimo-theme') { at('B', 'switch', sw.error || `stylesheet=${sw.stylesheet}`); return; }
  if (name === 'S3c') {
    const visit = await fetch(`${base}/`, { signal: AbortSignal.timeout(180000) }).then((r) => r.status).catch((e) => e.message);
    const pending = await op('record');
    S.pending = { visit, header_footer: parts(pending), scheduled: pending.scheduled };
    if (!parts(pending).pending) at('B', 'S3c.pending', `tras la visita anónima pending=${parts(pending).pending} (se esperaba true)`);
    if (!pending.scheduled) at('B', 'S3c.scheduled', 'no quedó programado digitalisimo_theme_convert_hello');
    const cron = await op('cron', { hook: 'digitalisimo_theme_convert_hello' });
    S.cron = cron;
    if (!(cron.ran || []).includes('digitalisimo_theme_convert_hello')) at('B', 'S3c.cron', `el cron no ejecutó la conversión: ${JSON.stringify(cron)}`);
  } else {
    await warmAdmin(admin);
  }
  const recB = await op('record');
  S.recordB = recB;
  expectRecordB(name, recB, (check, detail) => at('B', check, detail));
  const missingContracts = Object.entries(recB.functions || {}).filter(([, v]) => !v).map(([k]) => k);
  if (missingContracts.length) at('B', 'contracts', `faltan funciones o clases de los contratos: ${missingContracts.join(', ')}`);

  if (name === 'S4') {
    const lg = recB.log || {};
    const child = lg.child || {};
    if (!child || !Array.isArray(child.php_files) || !child.php_files.length) at('B', 'log.child', `bitácora child sin archivos PHP: ${JSON.stringify(child).slice(0, 200)}`);
    if (!(child.style_bytes > 0)) at('B', 'log.child', 'bitácora child sin bytes de style.css');
    if (!child.enqueues_hello_deps) at('B', 'log.child', 'bitácora child: enqueues_hello_deps debería ser true');
    if (!lg.filters || !(lg.filters.hello_elementor_page_title >= 1)) at('B', 'log.filters', `bitácora filters sin hello_elementor_page_title: ${JSON.stringify(lg.filters)}`);
    if (!Array.isArray(lg.warnings) || !lg.warnings.length) at('B', 'log.warnings', 'la pérdida del hijo no quedó declarada en warnings');
  }
  if (name === 'S5') {
    const lg = recB.log || {};
    const codes = JSON.stringify(lg.warnings || []);
    if (!/description_lost/.test(codes)) at('B', 'log.description', `falta el aviso description_lost: ${codes.slice(0, 200)}`);
    if (!(lg.description && lg.description.hello_printed === true && lg.description.seo_prints === false)) at('B', 'log.description', `description = ${JSON.stringify(lg.description)}`);
  }

  const B = await capture(browser, name, 'B', urls, viewports, { ids, shots: true });
  report.phases[`${name}-A`] = A;
  report.phases[`${name}-B`] = B;
  const diffCtx = await browser.createBrowserContext();
  const diffPage = await diffCtx.newPage();
  for (const item of urls) for (const vp of viewports) await compare(name, 'B', item, vp.w, A[item.key][vp.w], B[item.key][vp.w], { diffPage, vendors, computed: name === 'S3' || name === 'S3c' });
  await diffCtx.close();
  // S3h: con todos los interruptores apagados no hay cabecera ni con Hello ni con el tema, y el pie es el mismo
  // contenedor vacío en los dos (Hello lo imprime vacío; el tema lo genera vacío): sin texto y con su relleno.
  if (name === 'S3h') {
    for (const item of urls) {
      if (['canvas'].includes(item.key)) continue;
      for (const vp of viewports) {
        const a = A[item.key][vp.w], b = B[item.key][vp.w];
        if (a && a.boxes && a.boxes.header) issue(name, 'A', item.key, vp.w, 'S3h.header', 'Hello pintó una cabecera con los interruptores apagados (la prueba no reproduce el modo hidden)');
        if (b && b.boxes && b.boxes.header) issue(name, 'B', item.key, vp.w, 'S3h.header', 'el tema pintó una cabecera en modo hidden');
        if (a && !a.error && !(a.boxes && a.boxes.footer && a.boxes.footer.h > 0)) issue(name, 'A', item.key, vp.w, 'S3h.footer', 'Hello no pintó el pie vacío (la prueba no reproduce el pie con todo oculto)');
        if (b && !b.error && !(b.boxes && b.boxes.footer && b.boxes.footer.h > 0)) issue(name, 'B', item.key, vp.w, 'S3h.footer', 'el tema no pintó el contenedor vacío del pie');
      }
    }
  }

  // S4: «import --child-css» copia el style.css del hijo con url() absolutas (FR-8).
  if (name === 'S4') {
    const imp = await op('import-child-css');
    S.importChildCss = { ok: imp.ok, message: imp.message, error: imp.error };
    const css = String(imp.css || '');
    if (imp.error || !imp.ok) at('B', 'child_css', imp.error || imp.message || 'import falló');
    else if (!/digitalisimo-theme: hijo digi-hello-child/.test(css)) at('B', 'child_css', 'el CSS adicional no lleva el bloque delimitado del hijo');
    else if (!css.includes(`url(${imp.childUri}img/dot.svg)`) && !css.includes(`url("${imp.childUri}img/dot.svg")`) && !css.includes(`url('${imp.childUri}img/dot.svg')`)) at('B', 'child_css', `url() relativa sin reescribir a ${imp.childUri}img/dot.svg`);
  }

  // F · vista previa del editor de la cabecera (S2: plantilla del constructor; S3: plantilla generada).
  if (name === 'S2' || name === 'S3') {
    const headerId = name === 'S2' ? (recB.tb && recB.tb.header && recB.tb.header.id) : (generatedOf(recB, 'header')[0] || {}).id;
    if (!headerId) at('F', 'editor', 'no hay plantilla de cabecera que previsualizar');
    else {
      const url = (await op('preview-url', { id: headerId })).url;
      const res = url ? await adminFetch(admin, url) : { status: 0, text: '' };
      const count = (res.text.match(new RegExp(`data-elementor-id="${headerId}"`, 'g')) || []).length;
      S.editor = { url, status: res.status, count };
      if (res.status !== 200 || count !== 1) at('F', 'editor', `vista previa ${res.status}, marcado de la cabecera ${count} veces (se esperaba 1)`);
    }
  }

  // F2 · el asistente no puede volver a Hello con el tema activo (AJAX y REST, FR-16).
  if (name === 'S1') {
    const cookies = await adminCookies(admin);
    const nonce = await op('nonce', {}, cookies);
    // AJAX del asistente clásico (el que carga el núcleo por defecto): llamaría a switch_theme( 'hello-elementor' ).
    const ajax = await adminFetch(admin, `${base}/wp-admin/admin-ajax.php`, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: `action=elementor_activate_hello_theme&_nonce=${encodeURIComponent(nonce.ajax || '')}` });
    // REST del asistente nuevo: sólo se registra con el experimento oculto e_onboarding activo. Se confirma la ruta
    // registrada (la regex del bloqueo de Elements depende de ella, §10.2) y se pide como lo haría el asistente.
    await op('experiment', { name: 'e_onboarding', state: 'active' });
    const index = await adminFetch(admin, `${base}/wp-json/elementor/v1?_wpnonce=${encodeURIComponent(nonce.rest || '')}`);
    let routes = [];
    try { routes = Object.keys(JSON.parse(index.text).routes || {}).filter((r) => /onboarding/.test(r)); } catch (e) { routes = [`índice REST ${index.status}`]; }
    S.onboardingRoutes = routes;
    log(`   F2: rutas REST del asistente nuevo: ${routes.join(', ') || 'ninguna'}`);
    // Sin ruta registrada (p. ej. si el núcleo deja de traer el asistente) sólo se informa; si existe con otro nombre,
    // el bloqueo de Elements no la cubriría y la prueba falla. Se pide la ruta esperada y cualquier otra «install-theme».
    const installRoutes = routes.filter((r) => /install-theme/.test(r));
    if (installRoutes.length && !installRoutes.some((r) => /^\/elementor\/v1\/onboarding\/install-theme\/?$/.test(r))) at('F2', 'onboarding.route', `la ruta del asistente que instala el tema es otra: ${installRoutes.join(', ')}`);
    if (!installRoutes.length) log('   F2: el núcleo no registra la ruta REST que instala el tema (sólo se prueba el bloqueo)');
    let rest = { status: 0, text: '' };
    for (const route of [...new Set(['/elementor/v1/onboarding/install-theme', ...installRoutes])]) {
      const res = await adminFetch(admin, `${base}/wp-json${route}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce.rest || '' }, body: JSON.stringify({ theme_slug: 'hello-elementor' }) });
      if (!rest.status || res.status !== 403) rest = { ...res, route };
    }
    await op('experiment', { name: 'e_onboarding', state: 'default' });
    const after = await op('record');
    S.onboarding = { ajax: ajax.status, rest: rest.status, stylesheet: after.stylesheet, ajaxBody: ajax.text.slice(0, 200), restBody: rest.text.slice(0, 200) };
    if (ajax.status !== 403) at('F2', 'onboarding.ajax', `AJAX elementor_activate_hello_theme respondió ${ajax.status}: ${ajax.text.slice(0, 120)}`);
    if (rest.status !== 403) at('F2', 'onboarding.rest', `REST onboarding/install-theme respondió ${rest.status}: ${rest.text.slice(0, 120)}`);
    if (after.stylesheet !== 'digitalisimo-theme') {
      at('F2', 'onboarding.theme', `el tema activo pasó a ${after.stylesheet}`);
      await op('switch', { to: 'digitalisimo-theme' });
      await warmAdmin(admin);
    }
  }

  // C · reversión: vuelve al tema anterior con las mismas firmas, mods y ajustes de Hello.
  await op('phase', { name: `${name}-C` });
  const rev = await op('revert');
  S.revert = rev;
  const origin = name === 'S4' ? 'digi-hello-child' : 'hello-elementor';
  if (rev.error || !rev.ok) at('C', 'revert', rev.error || `${rev.code}: ${rev.message}`);
  else if (rev.stylesheet !== origin) at('C', 'revert', `stylesheet=${rev.stylesheet} (se esperaba ${origin})`);
  await warmAdmin(admin);
  const recC = await op('record');
  S.recordC = recC;
  if (recC.record && recC.record.state !== 'reverted') at('C', 'record.state', `state=${recC.record.state} (se esperaba reverted)`);
  // C compara firmas: basta el ancho móvil y el de escritorio (con QUICK, sólo escritorio).
  const viewportsC = QUICK ? [VIEWPORTS[2]] : [VIEWPORTS[0], VIEWPORTS[2]];
  const C = await capture(browser, name, 'C', urls, viewportsC, { ids, shots: false });
  report.phases[`${name}-C`] = C;
  for (const item of urls) for (const vp of viewportsC) await compare(name, 'C', item, vp.w, A[item.key][vp.w], C[item.key][vp.w], { signatureOnly: true });
  const hashC = await op('mods-hash');
  S.hashC = hashC;
  const modsKey = name === 'S4' ? 'child' : 'hello';
  if (!hashA[modsKey] || !hashC[modsKey] || hashA[modsKey].md5 !== hashC[modsKey].md5) at('C', 'mods', `md5 de theme_mods_${origin} sin sidebars_widgets: A ${hashA[modsKey] && hashA[modsKey].md5} C ${hashC[modsKey] && hashC[modsKey].md5} (claves A ${JSON.stringify(hashA[modsKey] && hashA[modsKey].keys)} C ${JSON.stringify(hashC[modsKey] && hashC[modsKey].keys)})`);
  if (hashA.settings !== hashC.settings) at('C', 'hello_settings', `hello_elementor_settings_* cambiaron: A ${JSON.stringify(hashA.settingsRaw)} C ${JSON.stringify(hashC.settingsRaw)}`);
  if (/^S3/.test(name)) {
    for (const part of ['header', 'footer']) {
      if (name === 'S3h' && part === 'header') continue;
      const list = generatedOf(recC, part);
      if (!(list.length === 1 && list[0].status === 'trash')) at('C', 'generated.trash', `${part}: ${JSON.stringify(list.map((g) => g.status))} (se esperaba en la papelera)`);
    }
  }
  if (name === 'S2') for (const [type, t] of Object.entries(recC.tb || {})) if (t.status !== 'publish') at('C', 'tb', `la plantilla ${type} quedó en ${t.status}`);

  // C2 · ida y vuelta: otra vez al tema; las plantillas generadas vuelven a publicarse sin duplicados (FR-6b).
  await op('phase', { name: `${name}-C2` });
  const sw2 = await op('switch', { to: 'digitalisimo-theme' });
  if (sw2.error) at('C2', 'switch', sw2.error);
  await warmAdmin(admin);
  const recC2 = await op('record');
  S.recordC2 = recC2;
  if (!recC2.record || !recC2.record.origin || recC2.record.origin.family !== 'hello') at('C2', 'record.origin', `origin=${JSON.stringify(recC2.record && recC2.record.origin)}`);
  if (recC2.record && recC2.record.reverted) at('C2', 'record.reverted', 'reverted sigue en el registro tras volver al tema');
  if (recC2.parity !== true) at('C2', 'record.parity', `digitalisimo_theme_parity() = ${recC2.parity}`);
  if (/^S3/.test(name)) {
    for (const part of ['header', 'footer']) {
      if (name === 'S3h' && part === 'header') continue;
      const list = generatedOf(recC2, part);
      if (!(list.length === 1 && list[0].status === 'publish')) at('C2', 'generated.restore', `${part}: ${JSON.stringify(list.map((g) => g.status))} (se esperaba una sola plantilla publicada)`);
    }
  }
  const C2 = await capture(browser, name, 'C2', urls.filter((u) => ['home', 'post'].includes(u.key)), [VIEWPORTS[2]], { ids, shots: false });
  for (const key of Object.keys(C2)) await compare(name, 'C2', urls.find((u) => u.key === key), 1280, B[key][1280], C2[key][1280], { signatureOnly: true });

  if (name === 'S1') await phaseD(ctx, ids);
  S.finished = new Date().toISOString();
}

/** D · modo base: un rodeo por el tema por defecto conserva origin = hello; con la paridad apagada, sólo base.css. */
async function phaseD(ctx, ids) {
  const { browser, admin } = ctx;
  const at = (check, detail, url = '', width = '') => issue('S1', 'D', url, width, check, detail);
  log('   D: rodeo por el tema por defecto y modo base');
  if (!ids.defaultOk) { at('D.default', `no existe WP_DEFAULT_THEME (${ids.defaultTheme})`); return; }
  await op('phase', { name: 'S1-D' });
  await op('switch', { to: ids.defaultTheme });
  await warmAdmin(admin);
  await op('switch', { to: 'digitalisimo-theme' });
  await warmAdmin(admin);
  const rec = await op('record');
  report.scenarios.S1.recordD = rec;
  if (!rec.record || !rec.record.origin || rec.record.origin.family !== 'hello') at('D.origin', `origin=${JSON.stringify(rec.record && rec.record.origin)} (FR-11)`);
  if (rec.record && rec.record.last && rec.record.last.stylesheet !== ids.defaultTheme) at('D.last', `last.stylesheet=${rec.record.last.stylesheet}`);
  await op('parity-off', { on: '1' });
  const p = ids.pages || {};
  const blog2 = `${p['digi-ts-blog']}page/2/`;
  const urls = [{ key: 'blog2', path: blog2 }, { key: 'post', path: ids.post }, { key: 'builder', path: p['digi-ts-constructor'] }, { key: 'home', path: '/' }];
  const D = await capture(browser, 'S1', 'D', urls, VIEWPORTS, { ids, shots: false });
  report.phases['S1-D'] = D;
  await op('parity-off', { on: '0' });
  for (const item of urls) {
    for (const vp of VIEWPORTS) {
      const r = D[item.key][vp.w];
      if (r.error) { at('carga', r.error, item.key, vp.w); continue; }
      if (r.status !== 200) at('status', `estado ${r.status}`, item.key, vp.w);
      const sheets = themeSheets(r);
      if (sheets.some((s) => /\/parity\//.test(s.href))) at('D.parity', `hojas de paridad con la paridad apagada: ${sheets.map((s) => s.id).join(', ')}`, item.key, vp.w);
      const baseCss = sheets.find((s) => /\/assets\/css\/base\.css/.test(s.href));
      if (!baseCss) at('D.base', `sin base.css (hojas del tema: ${sheets.map((s) => s.id).join(', ') || 'ninguna'})`, item.key, vp.w);
      else {
        const size = await assetBytes(baseCss.href);
        if (size.bytes > 5827 || size.gzip > 1399) at('D.base', `base.css ${size.bytes} B / ${size.gzip} B gzip (techo 5827 / 1399)`, item.key, vp.w);
        if (baseCss.id !== 'digitalisimo-theme-css') at('D.base', `handle de base.css: ${baseCss.id}`, item.key, vp.w);
      }
      if (vp.w === 360 && r.scroll.sw > r.scroll.iw) at('D.scroll', `scrollWidth ${r.scroll.sw} > ${r.scroll.iw} (${(r.scroll.overflow || []).join(', ')})`, item.key, vp.w);
      // Modo base con el constructor cargado: base.css (en :where()) debe ganar al `.screen-reader-text` del núcleo.
      if (r.skip && r.skip.present && !(r.skipFocus && r.skipFocus.visible)) at('D.skip.focus', `el enlace de salto no se ve con el foco: ${JSON.stringify(r.skipFocus)}`, item.key, vp.w);
      if (item.key === 'blog2' && r.paginationRow !== true) at('D.pagination', `paginación en fila: ${r.paginationRow}`, item.key, vp.w);
      for (const e of r.errors) at('console', e, item.key, vp.w);
      for (const f of r.failed) at('http404', f, item.key, vp.w);
    }
  }
}

/** E · sin constructor: respaldos propios, aviso en Temas y ningún error fatal. */
async function phaseE(ctx, ids) {
  const { browser, admin } = ctx;
  const at = (check, detail, url = '', width = '') => issue('E', 'E', url, width, check, detail);
  log('\n== E · sin DIGITALÍSIMO Elements');
  await op('phase', { name: 'E' });
  const rec = await op('record');
  if (rec.stylesheet !== 'digitalisimo-theme') { await op('switch', { to: 'digitalisimo-theme' }); await warmAdmin(admin); }
  const off = await op('elements', { on: '0' });
  if (off.active) { at('E.elements', `no se pudo desactivar Elements: ${off.error}`); return; }
  const p = ids.pages || {};
  const urls = [{ key: 'home', path: '/' }, { key: 'post', path: ids.post }, { key: 'canvas', path: p['digi-ts-canvas'] }, { key: 'full', path: p['digi-ts-ancho-completo'] }, { key: 'blog', path: p['digi-ts-blog'] }, { key: '404', path: '/digi-ts-no-existe/' }];
  if (WOO) urls.push({ key: 'shop', path: ids.woo.shop, woo: true });
  const E = await capture(browser, 'E', 'E', urls, [VIEWPORTS[0], VIEWPORTS[2]], { ids, shots: false });
  report.phases.E = E;
  for (const item of urls) {
    for (const w of [360, 1280]) {
      const r = E[item.key][w];
      if (r.error) { at('carga', r.error, item.key, w); continue; }
      const want = item.key === '404' ? 404 : 200;
      if (r.status !== want) at('status', `estado ${r.status} (se esperaba ${want})`, item.key, w);
      if (item.key === 'canvas' && !r.canvas) at('E.canvas', 'la página Canvas no usa fallback/canvas.php (sin body.digitalisimo-canvas)', item.key, w);
      if ((item.key === 'canvas' || item.key === 'full') && r.contentIds !== 1) at('E.content', `#content: ${r.contentIds}`, item.key, w);
      if (item.key === 'full' && r.pageHeader) at('E.full', 'fallback/full-width.php no debe pintar el título', item.key, w);
      if (r.scroll.sw > r.scroll.iw) at('scroll', `scrollWidth ${r.scroll.sw} > ${r.scroll.iw} (${(r.scroll.overflow || []).join(', ')})`, item.key, w);
      if (r.skip && r.skip.present && !(r.skipFocus && r.skipFocus.visible)) at('E.skip.focus', `el enlace de salto no se ve con el foco: ${JSON.stringify(r.skipFocus)}`, item.key, w);
      for (const f of r.failed) at('http404', f, item.key, w);
    }
  }
  const themes = await adminFetch(admin, `${base}/wp-admin/themes.php`);
  if (themes.status !== 200 || !/shows simple fallbacks until DIGITALÍSIMO Elements is active/.test(themes.text)) at('E.notice', `themes.php ${themes.status} sin el aviso de respaldos`);
  const on = await op('elements', { on: '1' });
  if (!on.active) at('E.elements', `no se pudo reactivar Elements: ${on.error}`);
}

function phpLogChecks() {
  const rows = readJsonl(path.join(OUT, 'php.jsonl'));
  const sig = (r) => `${r.kind}|${r.msg}|${r.file}|${r.line}`;
  const byPhase = {};
  for (const r of rows) (byPhase[r.phase] = byPhase[r.phase] || new Set()).add(sig(r));
  const reported = new Set();
  for (const r of rows) {
    const m = /^(S\w+)-(B|C|C2|D)$/.exec(r.phase || '');
    const fromTheme = /\/themes\/digitalisimo-theme\//.test(String(r.file)) || /\/themes\/digitalisimo-theme\//.test(String(r.msg));
    const compat = /theme-compat|Theme without/i.test(`${r.msg} ${r.file}`) && r.theme === 'digitalisimo-theme';
    let fail = fromTheme || compat;
    if (!fail && m) {
      const a = byPhase[`${m[1]}-A`] || new Set();
      fail = !a.has(sig(r));
    }
    if (!fail || reported.has(sig(r))) continue;
    reported.add(sig(r));
    const scenario = m ? m[1] : (r.phase || '—');
    issue(scenario, m ? m[2] : (r.phase || ''), '', '', compat ? 'sidebar.compat' : 'php', `${r.kind}: ${String(r.msg).slice(0, 220)} (${String(r.file).replace(/^\/wordpress\//, '')}:${r.line}) ${r.uri}`);
  }
  report.php = { total: rows.length, byKind: rows.reduce((acc, r) => { acc[r.kind] = (acc[r.kind] || 0) + 1; return acc; }, {}) };
  const fatal = fs.existsSync(path.join(OUT, 'fatal.log')) ? fs.readFileSync(path.join(OUT, 'fatal.log'), 'utf8').split('\n').filter(Boolean) : [];
  for (const line of fatal) issue((/^\[(S\w+|E)-?/.exec(line) || [])[1] || '—', '', '', '', 'fatal', line.slice(0, 300));
  const http = readJsonl(path.join(OUT, 'http.jsonl'));
  report.http = http.reduce((acc, r) => { acc[r.host] = (acc[r.host] || 0) + 1; return acc; }, {});
}

async function main() {
  if (!base) throw new Error('Falta la URL del servidor');
  known = JSON.parse(fs.readFileSync(path.join(__dirname, 'known.json'), 'utf8'));
  fs.mkdirSync(path.join(OUT, 'shots'), { recursive: true });
  const vendors = vendorsLib.load(ROOT);
  const ids = await op('ids');
  if (ids.error) throw new Error(ids.error);
  log(`WordPress ${ids.wp}, PHP ${ids.php}, Hello ${ids.helloVersion}, tema DIGITALÍSIMO ${ids.themeVersion}; WOO=${WOO ? 1 : 0}${QUICK ? ', QUICK=1' : ''}`);
  const urls = buildUrls(ids);
  const browser = await puppeteer.launch({
    executablePath: CHROME,
    headless: 'new',
    args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', '--font-render-hinting=none', `--host-resolver-rules=MAP * ~NOTFOUND, EXCLUDE ${new URL(base).hostname}`],
  });
  const admin = await adminLogin(browser);
  const ctx = { browser, admin, ids, urls, vendors };
  let serverDown = false;
  for (const name of SCENARIOS) {
    if (!ALL_SCENARIOS.includes(name)) throw new Error(`Escenario desconocido: ${name}`);
    try {
      await runScenario(ctx, name);
    } catch (e) {
      issue(name, '', '', '', 'excepción', String(e && e.stack || e).slice(0, 400));
    }
    // Si el servidor de Playground dejó de responder, los escenarios siguientes sólo repetirían «fetch failed».
    const alive = await op('ping');
    if (alive.error) {
      issue(name, '', '', '', 'servidor', `el servidor de Playground dejó de responder tras ${name} (ver server.log): ${alive.error}`);
      serverDown = true;
      break;
    }
  }
  if (!process.env.SKIP_E && !serverDown) {
    try { await phaseE(ctx, ids); } catch (e) { issue('E', 'E', '', '', 'excepción', String(e && e.stack || e).slice(0, 400)); }
  }
  await browser.close();
  phpLogChecks();

  // Excepciones declaradas que no se usaron (sólo las de escenarios que corrieron y, con WOO=0, sin las de la tienda).
  const ran = new Set(process.env.SKIP_E ? SCENARIOS : [...SCENARIOS, 'E']);
  for (const k of known.entries) {
    const scen = k.scenarios === undefined || k.scenarios === '*' ? [...ran] : [].concat(k.scenarios);
    if (!scen.some((s) => ran.has(s))) continue;
    if (!WOO && k.woo) continue;
    if (QUICK && k.full) continue;
    if (!k.__used) report.unusedKnown.push({ id: k.id, motivo: k.motivo });
  }

  const slim = JSON.parse(JSON.stringify(report, (key, value) => (key === 'domUrls' || key === 'requests' ? undefined : value)));
  fs.writeFileSync(path.join(OUT, 'theme-switch.json'), JSON.stringify(slim, null, 1));

  // Resumen legible.
  log('\nResultado por escenario:');
  const shown = process.env.SKIP_E ? [...SCENARIOS] : [...SCENARIOS, 'E'];
  for (const name of shown) {
    const own = issues.filter((i) => i.scenario === name);
    const ok = accepted.filter((i) => i.scenario === name);
    log(`  ${name.padEnd(4)} ${own.length ? `FALLA (${own.length})` : 'PASA'}${ok.length ? ` · ${ok.length} diferencias declaradas` : ''}`);
  }
  const grouped = {};
  for (const i of issues) {
    const key = `${i.scenario} ${i.phase} ${i.check}`;
    (grouped[key] = grouped[key] || []).push(i);
  }
  for (const [key, list] of Object.entries(grouped)) {
    log(`\nFALLA ${key} (${list.length})`);
    for (const i of list.slice(0, 6)) log(`   ${i.url || '—'} ${i.width || ''} · ${i.detail.slice(0, 260)}`);
    if (list.length > 6) log(`   … ${list.length - 6} más`);
  }
  for (const k of report.unusedKnown) log(`\nFALLA excepción sin usar en known.json: ${k.id} (${k.motivo})`);
  const failed = issues.length + report.unusedKnown.length;
  const complete = !QUICK && WOO && !process.env.SKIP_E && ALL_SCENARIOS.every((s) => SCENARIOS.includes(s));
  log(failed ? `\n${failed} fallos. Detalle en ${path.join(OUT, 'theme-switch.json')}` : `\nA/B del tema en verde: ${shown.join(', ')}${complete ? ' (corrida de aceptación completa)' : ' (corrida parcial: no vale para la aceptación)'}.`);
  process.exit(failed ? 1 : 0);
}

// ---------------------------------------------------------------------------------------------------------------
// MODE=measure: medición en staging (sólo con autorización). No usa la ruta de prueba ni cambia el tema.
// ---------------------------------------------------------------------------------------------------------------

async function measure() {
  const target = (process.env.BASE || '').replace(/\/$/, '');
  if (!/^https?:\/\//.test(target)) throw new Error('MODE=measure exige BASE=<url del sitio de staging>');
  const paths = (process.env.PATHS || '/').split(',').map((p) => p.trim()).filter(Boolean);
  const runs = parseInt(process.env.RUNS || '5', 10);
  const browser = await puppeteer.launch({ executablePath: CHROME, headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  const median = (xs) => { const s = [...xs].sort((a, b) => a - b); return s.length ? s[Math.floor(s.length / 2)] : null; };
  const result = { base: target, runs, pages: {} };
  for (const p of paths) {
    const samples = { cold: [], warm: [] };
    for (const kind of ['cold', 'warm']) {
      const context = await browser.createBrowserContext();
      const page = await context.newPage();
      await page.setViewport({ width: 1280, height: 900 });
      await page.evaluateOnNewDocument(() => {
        window.__m = { lcp: 0, cls: 0 };
        try {
          new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__m.lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
          new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__m.cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
        } catch (e) { /* navegador sin la API */ }
      });
      if (kind === 'cold') await page.setCacheEnabled(false);
      else await page.goto(target + p, { waitUntil: 'networkidle0', timeout: 180000 });
      for (let i = 0; i < runs; i++) {
        const handles = {};
        const onResponse = async (r) => {
          const req = r.request();
          if (!['stylesheet', 'script'].includes(req.resourceType())) return;
          const len = parseInt(r.headers()['content-length'] || '0', 10);
          handles[r.url().replace(/\?.*$/, '')] = len;
        };
        page.on('response', onResponse);
        await page.goto(target + p, { waitUntil: 'networkidle0', timeout: 180000 });
        await sleep(1500);
        const m = await page.evaluate(() => {
          const nav = performance.getEntriesByType('navigation')[0];
          return { ttfb: nav ? Math.round(nav.responseStart) : null, lcp: Math.round(window.__m.lcp), cls: Math.round(window.__m.cls * 1000) / 1000, nodes: document.getElementsByTagName('*').length, sheets: [...document.querySelectorAll('link[rel~="stylesheet"],script[src]')].map((e) => e.id || e.src || e.href) };
        });
        page.off('response', onResponse);
        samples[kind].push({ ...m, bytesByAsset: handles });
      }
      await context.close();
    }
    result.pages[p] = {};
    for (const kind of ['cold', 'warm']) {
      const s = samples[kind];
      result.pages[p][kind] = { ttfb: median(s.map((x) => x.ttfb)), lcp: median(s.map((x) => x.lcp)), cls: median(s.map((x) => x.cls)), nodes: median(s.map((x) => x.nodes)), bytesByAsset: s[s.length - 1] ? s[s.length - 1].bytesByAsset : {} };
      log(`${p} ${kind}: TTFB ${result.pages[p][kind].ttfb} ms · LCP ${result.pages[p][kind].lcp} ms · CLS ${result.pages[p][kind].cls} · nodos ${result.pages[p][kind].nodes}`);
    }
  }
  await browser.close();
  fs.mkdirSync(OUT, { recursive: true });
  fs.writeFileSync(path.join(OUT, 'theme-measure.json'), JSON.stringify(result, null, 1));
  log(`Medición en ${path.join(OUT, 'theme-measure.json')}`);
}

if (MODE === 'measure') {
  measure().catch((e) => { console.error(e); process.exit(1); });
} else {
  main().catch((e) => { console.error(e && e.stack || e); process.exit(1); });
}
