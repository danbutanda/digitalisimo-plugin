// Medición del front de DIGITALÍSIMO Elements en Chrome sin interfaz (front-budget.sh): una página por caso, coste
// frente a la página vacía, razones de la puerta de PRO, comprobaciones funcionales y el `expect` de cada caso en el
// modo de la corrida. Sólo el modo normal se compara con la línea base; cada modo guarda front-budget-<modo>.json.
const fs = require('fs');
const path = require('path');
const http = require('http');
const puppeteer = require('puppeteer-core');

const OUT = process.env.OUT || '/tmp';
const BASE = process.env.BASE;
const PORT = +process.env.PORT;
const HOST = new URL(BASE).host;
const HOST_MAP = process.env.HOST_MAP || '';
const TOKEN = process.env.TOKEN || '';
const LABEL = process.env.MODE_LABEL || 'normal';
const PARTS = (process.env.MODE_PARTS || '').split(',').filter(Boolean);
const NORMAL = LABEL === 'normal';
const EXPECT = process.env.EXPECT !== '0';
const ONLY = (process.env.CASES || '').split(',').map((s) => s.trim()).filter(Boolean);
const BASELINE_FILE = process.env.BASELINE_FILE;
const WRITE_BASELINE = process.env.BASELINE === 'write';
const LIGHTHOUSE = !!process.env.LIGHTHOUSE;
const LIGHTHOUSE_PATH = process.env.LIGHTHOUSE_PATH || '';
const LIGHTHOUSE_VERSION = '13.5.0';
const NORMAL_FILE = process.env.NORMAL_FILE || '';
const MODE = JSON.parse(fs.readFileSync(path.join(OUT, 'mode.json'), 'utf8'));
const DEFAULTS = MODE.defaults || '';
const CACHE = !!MODE.cache;
const cases = JSON.parse(fs.readFileSync(path.join(OUT, 'cases.json'), 'utf8'));
const KB = (n) => Math.round(n / 102.4) / 10;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const SNAPSHOTS = ['pre', 'warm', 'before', 'after'];
const problems = [];
const notes = [];
const problem = (message) => { problems.push(message); console.log(`FALLA  ${message}`); };

// ── Casos y expectativas ──────────────────────────────────────────────────────────────────────────────────────────
function runsHere(slug) {
  const c = cases[slug];
  if (ONLY.length) return ONLY.includes(slug);
  if (c.pending) return false;
  if (!Array.isArray(c.modes)) return NORMAL;
  if (c.modes.includes(LABEL)) return true;
  return PARTS.length > 1 && PARTS.every((p) => c.modes.includes(p));
}
// Mismo orden que digi_fb_expect() de lib.php: «*», cada componente del modo y la combinación completa.
function expectation(c, at = '') {
  const e = c.expect || {};
  const keys = ['*', ...(PARTS.length ? PARTS : ['normal']), ...(PARTS.length > 1 ? [PARTS.join('+')] : [])];
  const out = {};
  for (const key of keys) {
    let level = e[key] && typeof e[key] === 'object' ? { ...e[key] } : {};
    if (key === '*') for (const s of SNAPSHOTS) if (e[s]) level[s] = { ...(level[s] || {}), ...e[s] };
    if (at === '' || at === 'before') for (const [k, v] of Object.entries(level)) if (!SNAPSHOTS.includes(k)) out[k] = v;
    if (at && level[at] && typeof level[at] === 'object') Object.assign(out, level[at]);
  }
  return out;
}
// Sólo lo declarado para una instantánea (`pre`, `warm`, `after`), sin las claves de la carga.
function explicitAt(c, at) {
  const e = c.expect || {};
  const keys = ['*', ...(PARTS.length ? PARTS : ['normal']), ...(PARTS.length > 1 ? [PARTS.join('+')] : [])];
  const out = {};
  if (e[at] && typeof e[at] === 'object') Object.assign(out, e[at]);
  for (const key of keys) if (e[key] && e[key][at] && typeof e[key][at] === 'object') Object.assign(out, e[key][at]);
  return out;
}
const wanted = (c, name) => SNAPSHOTS.concat(['']).some((at) => Object.prototype.hasOwnProperty.call(expectation(c, at), name));

function compare(actual, expected) {
  if (typeof expected === 'string') {
    const m = expected.match(/^(>=|<=|>|<|=)\s*(-?[\d.]+)$/);
    if (m) {
      const a = Number(actual), b = Number(m[2]);
      if (!Number.isFinite(a)) return false;
      return { '>=': a >= b, '<=': a <= b, '>': a > b, '<': a < b, '=': a === b }[m[1]];
    }
  }
  return JSON.stringify(actual) === JSON.stringify(expected);
}
// Comprueba una expectativa contra una instantánea; `base` es la instantánea de carga (para changed y grew).
function check(label, exp, snap, base) {
  const fails = [];
  for (const [key, want] of Object.entries(exp)) {
    if (key === 'reason') {
      const list = [].concat(want);
      if (!(snap.reasons || []).some((r) => list.some((w) => r.startsWith(w)))) fails.push(`razón ${list.join('|')} no está en [${(snap.reasons || []).join(', ')}]`);
    } else if (key === 'changed') {
      for (const f of want) if (JSON.stringify(snap[f]) === JSON.stringify(base[f])) fails.push(`${f} no cambió (${JSON.stringify(snap[f])})`);
    } else if (key === 'grew') {
      for (const f of want) if (!(Number(snap[f]) > Number(base[f]))) fails.push(`${f} no creció (${base[f]} → ${snap[f]})`);
    } else if (key === 'deferred') {
      for (const [handle, on] of Object.entries(want)) if (!!(snap.deferred || {})[handle] !== on) fails.push(`defer de ${handle}: ${!!(snap.deferred || {})[handle]} (se esperaba ${on})`);
    } else if (!compare(snap[key], want)) {
      fails.push(`${key}: ${JSON.stringify(snap[key])} (se esperaba ${JSON.stringify(want)})`);
    }
  }
  if (fails.length) problem(`${label} · ${fails.join('; ')}`);
  return fails;
}

// ── HTTP al sitio (con su host) ───────────────────────────────────────────────────────────────────────────────────
function request(method, url, { headers = {}, body = null } = {}) {
  const u = new URL(url, BASE);
  return new Promise((resolve, reject) => {
    const req = http.request({ host: '127.0.0.1', port: PORT, method, path: u.pathname + u.search, headers: { Host: HOST, ...headers }, timeout: 300000 }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        const text = Buffer.concat(chunks).toString('utf8');
        let json = null;
        try { json = JSON.parse(text); } catch (e) { /* no es JSON */ }
        resolve({ status: res.statusCode, headers: res.headers, text, json });
      });
    });
    req.on('error', reject);
    req.on('timeout', () => req.destroy(new Error(`tiempo agotado en ${method} ${url}`)));
    if (body) req.write(body);
    req.end();
  });
}
const probe = async (what, extra = '') => (await request('GET', `/?digi_probe=${what}&token=${TOKEN}${extra}`)).json || {};
// El arnés ejecuta WP-Cron (la sonda apaga su disparo desde las visitas) hasta que no quede nada de la transición.
async function cronPasses(max = 8, label = 'cron') {
  let state = await probe('state');
  let passes = 0;
  for (; passes < max; passes++) {
    await request('GET', '/wp-cron.php');
    state = await probe('state');
    const due = (state.cron || []).filter((h) => /^digitalisimo_elements_(front_transition|icon_scan)@/.test(h));
    if (!state.pending && !due.length) { passes++; break; }
  }
  notes.push(`${label}: ${passes} pasadas de wp-cron.php · pendiente ${!!state.pending} · eventos ${JSON.stringify(state.cron || [])}`);
  return state;
}

// ── Medición en Chrome ────────────────────────────────────────────────────────────────────────────────────────────
const kind = (url, type) => {
  if (/\.css(\?|$)/.test(url) || type === 'stylesheet') return 'css';
  if (/\.(m?js)(\?|$)/.test(url) || type === 'script') return 'js';
  if (/\.(png|jpe?g|gif|webp|avif|svg)(\?|$)/.test(url) || type === 'image') return 'img';
  if (/\.(woff2?|ttf|otf|eot)(\?|$)/.test(url) || type === 'font') return 'font';
  if (type === 'document') return 'doc';
  return 'other';
};
const sameOrigin = (url) => { try { return new URL(url).host === HOST; } catch (e) { return false; } };

function requestFacts(requests) {
  const sum = (filter) => requests.filter(filter).reduce((s, r) => s + r.bytes, 0);
  const has = (re, same = false) => requests.some((r) => (!same || r.same) && re.test(r.url));
  return {
    requests: requests.length,
    bytes: sum(() => true),
    bytesSame: sum((r) => r.same),
    bytesThird: sum((r) => !r.same),
    css: sum((r) => r.type === 'css'),
    js: sum((r) => r.type === 'js'),
    img: sum((r) => r.type === 'img'),
    font: sum((r) => r.type === 'font'),
    thirdHosts: [...new Set(requests.filter((r) => !r.same).map((r) => { try { return new URL(r.url).host; } catch (e) { return '?'; } }).filter(Boolean))].sort(),
    jquery: has(/\/jquery(\.min)?\.js(\?|$)/) || has(/\/jquery\/jquery/),
    proHandlers: has(/elements-handlers(\.min)?\.js/),
    proFrontend: has(/digitalisimo-elements\/assets\/js\/frontend(\.min)?\.js/),
    swiper: has(/swiper/),
    lottieLib: has(/assets\/lib\/lottie\/lottie(\.min)?\.js/),
    prism: has(/assets\/lib\/prism\//, true),
    prismAny: has(/prism/),
    prismCore: has(/assets\/lib\/prism\/components\/prism-core(\.min)?\.js/, true),
    prismLoader: has(/assets\/lib\/prism\/plugins\/autoloader\//, true),
    prismGrammar: has(/assets\/lib\/prism\/components\/prism-(?!core)[a-z0-9-]+(\.min)?\.js/, true),
    clipboardLocal: has(/assets\/lib\/prism\/.*clipboard[^/]*\.js/, true),
    lazyLoader: has(/digitalisimo-front\/assets\/lazy-libs\.js/, true),
    cdnjs: requests.some((r) => /(^|\.)cdnjs\.cloudflare\.com$/.test((() => { try { return new URL(r.url).host; } catch (e) { return ''; } })())),
    eicons: has(/\/eicons\/|elementor-icons(\.min)?\.css/),
    fontAwesome: has(/font-awesome|fontawesome|\/fa-/),
  };
}

// Hechos del DOM (se evalúa en la página).
function domFacts() {
  const shown = (el) => { if (!el) return false; const r = el.getBoundingClientRect(); if (!r.width || !r.height) return false; for (let n = el; n && n.nodeType === 1; n = n.parentElement) { const s = getComputedStyle(n); if (s.display === 'none' || s.visibility === 'hidden') return false; } return true; };
  const imgs = [...document.querySelectorAll('.elementor-widget img')];
  const iframes = [...document.querySelectorAll('.elementor-widget iframe')];
  const widget = document.querySelector('.elementor-widget');
  const zone = (img) => (img.closest('.elementor-popup-modal, [data-elementor-type="popup"]') ? 'popup' : img.closest('[data-elementor-type="footer"], footer') ? 'footer' : 'content');
  const lazy = { content: 0, footer: 0, popup: 0 };
  for (const img of document.querySelectorAll('img')) if (img.getAttribute('loading') === 'lazy') lazy[zone(img)]++;
  const codes = [...document.querySelectorAll('.elementor-widget-code-highlight')];
  const tokens = codes.map((w) => w.querySelectorAll('.token').length);
  const motion = document.querySelector('.elementor-motion-effects-element');
  const toggle = document.querySelector('.elementor-menu-toggle');
  const dropdown = document.querySelector('nav.elementor-nav-menu--dropdown');
  const deferred = {};
  for (const s of document.querySelectorAll('script[id$="-js"][src]')) deferred[s.id.replace(/-js$/, '')] = s.hasAttribute('defer') || s.getAttribute('data-wp-strategy') === 'defer';
  return {
    nodes: document.getElementsByTagName('*').length,
    widgetNodes: widget ? widget.getElementsByTagName('*').length : 0,
    images: imgs.length,
    imagesNoDimensions: imgs.filter((i) => !i.getAttribute('width') || !i.getAttribute('height')).length,
    imagesNoSrcset: imgs.filter((i) => !i.getAttribute('srcset') && !/\.svg(\?|$)/.test(i.currentSrc || i.src)).length,
    imagesLazy: imgs.filter((i) => i.getAttribute('loading') === 'lazy').length,
    imagesLazyZones: lazy,
    imagesNoAlt: imgs.filter((i) => !i.hasAttribute('alt')).length,
    imagesNoDecoding: imgs.filter((i) => i.getAttribute('decoding') !== 'async').length,
    iframes: iframes.length,
    iframesNotLazy: iframes.filter((f) => f.getAttribute('loading') !== 'lazy').length,
    iframesNoTitle: iframes.filter((f) => !f.getAttribute('title')).length,
    headings: [...document.querySelectorAll('.elementor-widget h1,.elementor-widget h2,.elementor-widget h3,.elementor-widget h4,.elementor-widget h5,.elementor-widget h6')].map((h) => h.tagName.toLowerCase()),
    linksNoName: [...document.querySelectorAll('.elementor-widget a')].filter((a) => !(a.textContent || '').trim() && !a.getAttribute('aria-label') && !a.querySelector('img[alt]:not([alt=""])')).length,
    jsonLd: document.querySelectorAll('script[type="application/ld+json"]').length,
    cls: Math.round(window.__digi.cls * 1000) / 1000,
    longTasks: window.__digi.longTasks,
    longTaskMs: Math.round(window.__digi.longTaskMs),
    inlineStyleBytes: [...document.querySelectorAll('style')].reduce((s, el) => s + el.textContent.length, 0),
    config: typeof window.ElementorProFrontendConfig,
    elementorFrontend: typeof window.elementorFrontend !== 'undefined',
    popupShown: [...document.querySelectorAll('.elementor-popup-modal')].some(shown),
    svgIcons: document.querySelectorAll('svg.e-font-icon-svg').length,
    iconsI: document.querySelectorAll('i.fa, i[class*="fa-"], i[class*="eicon-"]').length,
    iconsINew: [...document.querySelectorAll('i.fa, i[class*="fa-"], i[class*="eicon-"]')].filter((i) => !i.closest('.digi-fb-fa4')).length,
    formSuccess: !!document.querySelector('.elementor-message-success'),
    formMessage: (document.querySelector('.elementor-message') || {}).textContent || '',
    navExpanded: !!toggle && toggle.getAttribute('aria-expanded') === 'true' && shown(dropdown),
    subMenuShown: [...document.querySelectorAll('.elementor-nav-menu--main .sub-menu')].some(shown),
    smartMenus: !!window.jQuery && [...document.querySelectorAll('.elementor-nav-menu')].some((m) => !!window.jQuery(m).data('smartmenus')),
    loopItems: document.querySelectorAll('.e-loop-item').length,
    loopSig: [...document.querySelectorAll('.e-loop-item')].map((el) => (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 40)).join(' | '),
    motionElement: document.querySelectorAll('.elementor-motion-effects-element').length,
    motionTransform: motion ? `${getComputedStyle(motion).transform} ${motion.getAttribute('style') || ''}` : '',
    stickyActive: !!document.querySelector('.elementor-sticky--active'),
    codeBlocks: codes.length,
    tokensMin: tokens.length ? Math.min(...tokens) : 0,
    lottieSvg: !!document.querySelector('.e-lottie__animation svg'),
    lottieSvgMs: window.__digi.lottieAt ? Math.round(window.__digi.lottieAt) : null,
    headerShown: !!document.querySelector('[data-elementor-type="header"]'),
    fakeAddonScript: window.digiFbFakeAddon === 'object',
    fontLinks: [...document.querySelectorAll('link[rel="stylesheet"]')].filter((l) => /^elementor-gf-|google-?fonts|fonts\.googleapis/.test(`${l.id} ${l.href}`)).map((l) => ({ id: l.id, href: l.href })),
    deferred,
  };
}

async function fontDisplay(links) {
  const values = [];
  let copy = false;
  for (const link of links) {
    let value = '';
    try { value = new URL(link.href).searchParams.get('display') || ''; } catch (e) { /* sin URL */ }
    if (/\/digitalisimo-[^/]*\.css/.test(link.href)) copy = true;
    if (!value && sameOrigin(link.href)) {
      const css = await request('GET', link.href).catch(() => ({ text: '' }));
      const m = (css.text || '').match(/font-display\s*:\s*([a-z]+)/i);
      value = m ? m[1].toLowerCase() : 'auto';
    }
    values.push(value || 'auto');
  }
  return { fontDisplay: [...new Set(values)].join(',') , fontGuardCopy: copy };
}

async function runActions(page, actions) {
  const marks = {};
  for (const a of actions || []) {
    if (a.do === 'click') {
      await page.waitForSelector(a.selector, { timeout: a.ms || 10000 });
      await page.$eval(a.selector, (el) => el.scrollIntoView({ block: 'center' }));
      await sleep(200);
      await page.click(a.selector);
    } else if (a.do === 'scroll') {
      if (a.to === 'bottom') await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 400) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 60)); } window.scrollTo(0, document.body.scrollHeight); });
      else if (typeof a.to === 'number') await page.evaluate((y) => window.scrollTo(0, y), a.to);
      else await page.$eval(a.to, (el) => el.scrollIntoView({ block: 'center' }));
    } else if (a.do === 'wait') await sleep(a.ms || 500);
    else if (a.do === 'waitFor') await page.waitForSelector(a.selector, { timeout: a.ms || 10000 }).catch(() => {});
    else if (a.do === 'waitCount') await page.waitForFunction((sel, min) => document.querySelectorAll(sel).length >= min, { timeout: a.ms || 15000 }, a.selector, a.min).catch(() => {});
    else if (a.do === 'hover') {
      // El menú de PRO (SmartMenus) sólo trata el puntero como ratón tras ver moverse el cursor: primero se mueve.
      await page.waitForSelector(a.selector, { timeout: a.ms || 10000 });
      await page.mouse.move(5, 5);
      await page.mouse.move(40, 40, { steps: 5 });
      const box = await (await page.$(a.selector)).boundingBox();
      if (box) await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 8 });
    }
    else if (a.do === 'openSubmenu') await page.evaluate((sel) => { const a = document.querySelector(sel); const api = a && window.jQuery ? window.jQuery(a).closest('.elementor-nav-menu').data('smartmenus') : null; if (api) api.itemActivate(window.jQuery(a)); }, a.selector);
    else if (a.do === 'mark') marks[a.selector] = await page.$$eval(a.selector, (els) => els.map((el) => el.textContent.trim()).join('|'));
    else if (a.do === 'waitChange') await page.waitForFunction((sel, before) => [...document.querySelectorAll(sel)].map((el) => el.textContent.trim()).join('|') !== before, { timeout: a.ms || 15000 }, a.selector, marks[a.selector] || '').catch(() => {});
    else if (a.do === 'viewport') await page.setViewport({ width: a.width, height: a.height });
    else if (a.do === 'fill') { await page.waitForSelector(a.selector, { timeout: 10000 }); await page.type(a.selector, a.value); }
    else if (a.do === 'fillAll') await page.$$eval(a.selector, (els, v) => els.forEach((el) => { if (!el.value) { el.value = v; el.dispatchEvent(new Event('input', { bubbles: true })); } }), a.value);
    else throw new Error(`acción desconocida ${a.do}`);
  }
}

async function visit(context, slug, url, { measure = true } = {}) {
  const c = cases[slug] || {};
  const page = await context.newPage();
  const vp = c.viewport || { width: 1280, height: 900 };
  await page.setViewport({ width: vp.width, height: vp.height });
  // Sin caché: cada página paga todos sus recursos, como la primera visita de un usuario.
  await page.setCacheEnabled(false);
  const requests = [];
  const errors = [];
  const failed = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon/.test(m.text())) errors.push(m.text()); });
  page.on('response', async (r) => {
    const u = r.url();
    if (u.startsWith('data:')) return;
    const same = sameOrigin(u);
    let bytes = parseInt(r.headers()['content-length'] || '0', 10);
    if (!bytes && r.status() === 200) { try { bytes = (await r.buffer()).length; } catch (e) { bytes = 0; } }
    if (r.status() >= 400 && same && r.request().resourceType() !== 'document') failed.push(`${r.status()} ${u}`);
    requests.push({ url: u, type: kind(u, r.request().resourceType()), bytes, same, status: r.status() });
  });
  await page.evaluateOnNewDocument(() => {
    window.__digi = { cls: 0, longTasks: 0, longTaskMs: 0, lottieAt: 0 };
    try {
      new PerformanceObserver((list) => { list.getEntries().forEach((e) => { if (!e.hadRecentInput) window.__digi.cls += e.value; }); }).observe({ type: 'layout-shift', buffered: true });
      new PerformanceObserver((list) => { list.getEntries().forEach((e) => { window.__digi.longTasks++; window.__digi.longTaskMs += e.duration; }); }).observe({ type: 'longtask', buffered: true });
      new MutationObserver(() => { if (!window.__digi.lottieAt && document.querySelector('.e-lottie__animation svg')) window.__digi.lottieAt = performance.now(); }).observe(document, { childList: true, subtree: true });
    } catch (e) { /* sin observadores */ }
  });
  const result = { slug, url, title: c.title || slug, ok: true };
  try {
    const response = await page.goto(url, { waitUntil: 'networkidle0', timeout: 180000 });
    const status = response ? response.status() : 0;
    const allowed = /no-existe/.test(url) ? 404 : 200;
    if (status !== allowed) throw new Error(`la página respondió ${status}`);
    const html = await response.text().catch(() => '');
    // La vista 404 de THEME=1 responde 404 a propósito: Chrome lo anota en la consola una vez.
    if (allowed === 404) { const i = errors.findIndex((e) => /status of 404/.test(e)); if (i >= 0) errors.splice(i, 1); }
    await sleep(1500);
    if (!measure) { await page.close(); return result; }
    const gate = (html.match(/<!-- digitalisimo-pro-gate (\{.*?\}) -->/) || [])[1];
    const parsed = gate ? JSON.parse(gate) : {};
    const snap = async () => {
      const dom = await page.evaluate(domFacts);
      const fonts = await fontDisplay(dom.fontLinks);
      return { ...requestFacts(requests), ...dom, ...fonts, reasons: parsed.reasons || [], gate: !!parsed.gate, errors: errors.length + failed.length, failed: failed.length, errorList: errors.concat(failed) };
    };
    const load = await snap();
    if (wanted(c, 'countdownTicks')) {
      const read = () => page.$eval('.elementor-countdown-seconds', (el) => el.textContent).catch(() => null);
      const first = await read();
      await sleep(2200);
      const second = await read();
      load.countdownTicks = first !== null && second !== null && first !== second;
    }
    if (wanted(c, 'jsFallback')) {
      const st = await probe('state');
      const fb = (st.options || {}).digitalisimo_performance_js_fallback;
      load.jsFallback = !!(fb && fb.at);
    }
    Object.assign(result, load);
    if ((c.actions || []).length) {
      await runActions(page, c.actions);
      result.after = await snap();
      result.after.countdownTicks = load.countdownTicks;
    }
  } catch (e) {
    result.ok = false;
    result.error = String(e.message || e);
    await page.screenshot({ path: path.join(OUT, `${slug.replace(/[^a-z0-9-]/gi, '_')}.png`), fullPage: true }).catch(() => {});
  }
  await page.close().catch(() => {});
  return result;
}

const pageUrl = (slug) => `${BASE}/digi-fb-${slug}/`;

async function measureCase(browser, slug, snapshot) {
  const c = cases[slug];
  const views = c.views ? c.views.map((v) => `${BASE}${v}`) : [pageUrl(slug)];
  if (c.header) {
    const r = await probe('header', `&key=${encodeURIComponent(slug)}`);
    if (!r.headers || !r.headers[slug]) problem(`${slug}: la sonda no activó su cabecera (${JSON.stringify(r)})`);
  }
  const out = [];
  for (const url of views) {
    let context = browser.defaultBrowserContext();
    let own = null;
    // Una secuencia (pro-popup-views) se visita en un perfil limpio: sus contadores no dependen de otros casos.
    if (c.sequence) {
      own = await browser.createBrowserContext();
      context = own;
      for (const s of c.sequence) await visit(context, s, pageUrl(s), { measure: false });
    }
    // CACHE=1: la primera visita llena la caché de elementos; se mide la segunda.
    if (CACHE) await visit(context, slug, url, { measure: false });
    const r = await visit(context, slug, url);
    r.snapshot = snapshot;
    if (own) await own.close();
    out.push(r);
  }
  return out;
}

async function measureAll(browser, slugs, snapshot) {
  const results = {};
  for (const slug of slugs) {
    const list = await measureCase(browser, slug, snapshot);
    list.forEach((r, i) => { results[list.length > 1 ? `${slug}@${i}` : slug] = r; });
    const r = list[0];
    console.log(`${snapshot.padEnd(6)} ${slug.padEnd(22)} ${list.map((x) => (x.ok ? `${x.requests} pet ${KB(x.js)} KB js · PRO ${x.proHandlers ? 'sí' : 'no'} · ${(x.reasons || []).join(' ').slice(0, 70)}` : `FALLA ${x.error}`)).join(' || ')}`);
  }
  return results;
}

// «Aplicar» por la acción real: sesión de administrador, la pantalla «Rendimiento» y su formulario con nonce.
async function applyIcons(browser) {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.goto(`${BASE}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 180000 });
  await page.type('#user_login', 'admin');
  await page.type('#user_pass', 'password');
  await Promise.all([page.waitForNavigation({ timeout: 180000 }), page.click('#wp-submit')]);
  await page.goto(`${BASE}/wp-admin/admin.php?page=digitalisimo-elements-front`, { waitUntil: 'networkidle0', timeout: 180000 });
  const form = await page.$('form.digitalisimo-front-aplicar');
  if (!form) { problem('DEFAULTS: la pantalla Rendimiento no muestra «Aplicar»'); await context.close(); return ''; }
  await Promise.all([page.waitForNavigation({ timeout: 180000 }), page.click('form.digitalisimo-front-aplicar button[type="submit"]')]);
  const landed = page.url();
  await context.close();
  return landed;
}

async function lighthouse(url, wsEndpoint) {
  let lh;
  try {
    const pkg = JSON.parse(fs.readFileSync(path.join(LIGHTHOUSE_PATH, 'lighthouse', 'package.json'), 'utf8'));
    if (pkg.version !== LIGHTHOUSE_VERSION) return { error: `lighthouse ${pkg.version} en ${LIGHTHOUSE_PATH}: se exige ${LIGHTHOUSE_VERSION}` };
    lh = await import(path.join(LIGHTHOUSE_PATH, 'lighthouse', pkg.exports && typeof pkg.exports === 'string' ? pkg.exports : 'core/index.js'));
  } catch (e) { return { error: `lighthouse no disponible en ${LIGHTHOUSE_PATH}: ${e.message}` }; }
  const port = parseInt(new URL(wsEndpoint).port, 10);
  const runner = lh.default || lh;
  const r = await runner(url, { port, output: 'json', logLevel: 'silent', onlyCategories: ['performance'], formFactor: 'mobile', screenEmulation: { mobile: true, width: 412, height: 823, deviceScaleFactor: 1.75, disabled: false }, throttlingMethod: 'simulate' });
  if (!r || !r.lhr) return { error: 'Lighthouse no devolvió resultado' };
  if (r.lhr.lighthouseVersion !== LIGHTHOUSE_VERSION) return { error: `lhr.lighthouseVersion ${r.lhr.lighthouseVersion}` };
  if (r.lhr.runtimeError) return { error: `${r.lhr.runtimeError.code}: ${r.lhr.runtimeError.message}` };
  const a = r.lhr.audits;
  return { version: r.lhr.lighthouseVersion, score: Math.round((r.lhr.categories.performance.score || 0) * 100), lcp: Math.round(a['largest-contentful-paint'].numericValue), tbt: Math.round(a['total-blocking-time'].numericValue), cls: Math.round(a['cumulative-layout-shift'].numericValue * 1000) / 1000, bytes: Math.round(a['total-byte-weight'].numericValue) };
}

(async () => {
  const args = ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--remote-debugging-port=0'];
  if (HOST_MAP) args.push(`--host-resolver-rules=MAP ${HOST_MAP} 127.0.0.1:${PORT}`);
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args });
  const slugs = Object.keys(cases).filter(runsHere);
  const skipped = Object.keys(cases).filter((s) => cases[s].pending).map((s) => `${s} (${cases[s].pending.slice(0, 60)}…)`);
  console.log(`modo ${LABEL} · ${slugs.length} casos${ONLY.length ? ' (CASES)' : ''}${skipped.length ? ` · omitidos: ${skipped.join(', ')}` : ''}`);
  const summary = { mode: LABEL, parts: PARTS, elements: { dir: process.env.ELEMENTS_DIR, version: process.env.ELEMENTS_VERSION, sha: process.env.ELEMENTS_SHA }, git: process.env.GIT_SHA, expect: EXPECT, started: new Date(+process.env.STARTED * 1000).toISOString(), states: {} };

  // Estado previo: la sonda, la primera visita (agenda la transición) y, en los DEFAULTS, la medición previa.
  summary.states.initial = await probe('state');
  let pre = null;
  if (DEFAULTS) {
    pre = await measureAll(browser, slugs.filter((s) => !cases[s].views), 'pre');
  } else {
    await request('GET', '/digi-fb-empty/');
  }
  if (MODE.seo === 'guard') {
    // El sitio parte con el blindaje sirviendo sus copias (las fuentes locales se descargaron en la medición previa).
    summary.states.fontGuard = await probe('fontguard');
    const page = await request('GET', '/digi-fb-empty/');
    summary.states.fontGuardBefore = (page.text.match(/<link[^>]+id=['"]elementor-gf-[^'"]+['"][^>]*>/g) || []).map((l) => (l.match(/href=['"]([^'"]+)/) || [])[1]);
    if (EXPECT && !summary.states.fontGuardBefore.some((h) => /\/digitalisimo-[^/]*\.css/.test(h || ''))) problem(`guard: antes de la transición el blindaje no sirve copias (${JSON.stringify(summary.states.fontGuard).slice(0, 300)})`);
  }
  summary.states.firstVisit = await probe('state');
  if (EXPECT && DEFAULTS && summary.states.firstVisit.module && !summary.states.firstVisit.scheduled?.transition) problem(`${LABEL}: tras la primera visita no quedó agendado digitalisimo_elements_front_transition`);
  summary.states.transition = await cronPasses(8, 'transición');
  if (MODE.seo === 'guard') {
    // Las fuentes locales se vuelven a descargar en la visita siguiente; SEO recalcula en sus dos pasadas de cron.
    await request('GET', '/digi-fb-empty/');
    // SEO agenda su recálculo a time() + 10: cada pasada espera a que venza su evento y ejecuta wp-cron.php.
    for (let pass = 0; pass < 2; pass++) {
      const st = await probe('state');
      const waits = (st.cron || []).filter((h) => /^digitalisimo_performance_/.test(h)).map((h) => +h.split('@')[1]);
      if (waits.length) await sleep(Math.max(0, Math.max(...waits)) * 1000 + 1500);
      await request('GET', '/wp-cron.php');
      notes.push(`guard: pasada ${pass + 1} de cron tras esperar ${waits.length ? Math.max(...waits) : 0} s (${JSON.stringify(st.cron || [])})`);
    }
    summary.states.guard = await probe('state');
  }
  let warm = null;
  if (DEFAULTS === 'old-applied' || DEFAULTS === 'old-inactive') {
    if (CACHE) warm = await measureAll(browser, slugs.filter((s) => s === 'icons'), 'warm');
    summary.applyLanded = await applyIcons(browser);
    summary.states.applied = await probe('state');
    if (EXPECT && !/front_notice=icons-applied/.test(summary.applyLanded)) problem(`${LABEL}: «Aplicar» terminó en ${summary.applyLanded}`);
  }
  const results = await measureAll(browser, slugs, 'load');
  summary.states.final = await probe('state');

  // Diferencias con la página vacía de esta corrida (CLS relativo, §5.4).
  const empty = results.empty || {};
  for (const r of Object.values(results)) {
    if (!r.ok || r.slug === 'empty' || !empty.ok) continue;
    r.delta = { requests: r.requests - empty.requests, bytes: r.bytes - empty.bytes, css: r.css - empty.css, js: r.js - empty.js, img: r.img - empty.img, third: r.bytesThird - empty.bytesThird, nodes: r.nodes - empty.nodes, cls: Math.round((r.cls - empty.cls) * 1000) / 1000 };
  }
  if (LIGHTHOUSE) {
    for (const slug of ['empty', 'all-own', 'pro-heavy']) {
      if (!results[slug]) continue;
      results[slug].lighthouse = await lighthouse(pageUrl(slug), browser.wsEndpoint()).catch((e) => ({ error: String(e.message || e) }));
      if (results[slug].lighthouse.error) problem(`lighthouse ${slug}: ${results[slug].lighthouse.error}`);
    }
  }
  await browser.close();

  // Expectativas por caso, por instantánea y por modo.
  let normalRef = null;
  if (EXPECT) {
    const ref = NORMAL_FILE || path.join(OUT, 'front-budget.json');
    try { normalRef = JSON.parse(fs.readFileSync(ref, 'utf8')).results; } catch (e) { normalRef = null; }
    for (const [key, r] of Object.entries(results)) {
      const slug = r.slug;
      const c = cases[slug];
      if (!r.ok) { problem(`${key}: ${r.error}`); continue; }
      const load = expectation(c, '');
      if ('clsVsNormal' in load) {
        const n = normalRef && normalRef[slug];
        if (!n || !n.ok) { problem(`${key}: falta la medición normal de ${slug} para comparar el CLS (NORMAL_FILE)`); delete load.clsVsNormal; }
        else { r.clsVsNormal = Math.round((r.cls - n.cls) * 1000) / 1000; }
      }
      check(`${key}`, load, r, r);
      const after = explicitAt(c, 'after');
      if ('errors' in load) after.errors = load.errors;
      if (Object.keys(explicitAt(c, 'after')).length) {
        if (!r.after) problem(`${key}: el caso no tiene acciones y espera «after»`);
        else check(`${key} (después de las acciones)`, after, r.after, r);
      }
      const prev = explicitAt(c, 'pre');
      if (pre && pre[slug] && Object.keys(prev).length) check(`${key} (antes de la transición)`, prev, pre[slug], pre[slug]);
      const hot = explicitAt(c, 'warm');
      if (warm && warm[slug] && Object.keys(hot).length) check(`${key} (caché caliente, antes de aplicar)`, hot, warm[slug], warm[slug]);
    }
    modeChecks(summary.states);
  }

  const duration = Math.round(Date.now() / 1000 - +process.env.STARTED);
  summary.duration = duration;
  summary.problems = problems;
  summary.notes = notes;
  const file = path.join(OUT, NORMAL ? 'front-budget.json' : `front-budget-${LABEL}.json`);
  fs.writeFileSync(file, JSON.stringify({ ...summary, results, pre, warm }, null, 1));

  // Tabla legible: coste de cada caso frente a la página vacía.
  if (empty.ok) console.log(`vacía: ${empty.requests} peticiones, ${KB(empty.bytes)} KB (JS ${KB(empty.js)} KB, CSS ${KB(empty.css)} KB), ${empty.nodes} nodos, jQuery ${empty.jquery ? 'sí' : 'no'}, handlers PRO ${empty.proHandlers ? 'sí' : 'no'}, frontend PRO ${empty.proFrontend ? 'sí' : 'no'}`);
  console.log('caso                          pet  KB    css   js    img   terc  nodos cls    PRO lo pr fa ei  razones / errores');
  for (const [key, r] of Object.entries(results)) {
    if (!r.ok || !r.delta) continue;
    const d = r.delta;
    console.log(`${key.padEnd(30)}${String(d.requests).padStart(3)} ${KB(d.bytes).toFixed(1).padStart(6)} ${KB(d.css).toFixed(1).padStart(5)} ${KB(d.js).toFixed(1).padStart(5)} ${KB(d.img).toFixed(1).padStart(5)} ${KB(d.third).toFixed(1).padStart(5)} ${String(d.nodes).padStart(5)} ${d.cls.toFixed(3).padStart(6)}  ${r.proHandlers ? 'sí' : '- '}  ${r.lottieLib ? 'sí' : '- '} ${r.prism ? 'sí' : '- '} ${r.fontAwesome ? 'sí' : '- '} ${r.eicons ? 'sí' : '- '}  ${(r.reasons || []).join(' ').slice(0, 50)} ${r.errorList.length ? r.errorList.join(' | ').slice(0, 80) : ''}`);
  }
  for (const slug of ['empty', 'all-own', 'pro-heavy']) if (results[slug] && results[slug].lighthouse) console.log(`lighthouse ${slug}: ${JSON.stringify(results[slug].lighthouse)}`);
  for (const n of notes) console.log(`nota   ${n}`);

  // Línea base (sólo modo normal): se guarda o se compara.
  if (NORMAL && WRITE_BASELINE && BASELINE_FILE) {
    const slim = {};
    for (const [slug, r] of Object.entries(results)) {
      slim[slug] = r.ok ? { requests: r.requests, bytesSame: r.bytesSame, css: r.css, js: r.js, img: r.img, cls: r.cls, deltaCls: r.delta ? r.delta.cls : 0, nodes: r.nodes, jquery: r.jquery, proHandlers: r.proHandlers, proFrontend: r.proFrontend, swiper: r.swiper, lottie: r.lottieLib, lottieLib: r.lottieLib, prism: r.prism, eicons: r.eicons, fontAwesome: r.fontAwesome, fontDisplay: r.fontDisplay, thirdHosts: r.thirdHosts, imagesNoDimensions: r.imagesNoDimensions, iframesNotLazy: r.iframesNotLazy, errors: r.errors } : { failed: true };
      if (r.lighthouse && !r.lighthouse.error) slim[slug].lighthouse = r.lighthouse;
    }
    fs.writeFileSync(BASELINE_FILE, JSON.stringify(slim, null, 2) + '\n');
    console.log(`línea base guardada en ${BASELINE_FILE}`);
  } else if (NORMAL && EXPECT && BASELINE_FILE && fs.existsSync(BASELINE_FILE)) {
    const baseline = JSON.parse(fs.readFileSync(BASELINE_FILE, 'utf8'));
    let worse = 0;
    for (const slug of Object.keys(baseline)) {
      const b = baseline[slug]; const r = results[slug];
      if (!r || !r.ok || b.failed) continue;
      const w = [];
      if (r.bytesSame > b.bytesSame * 1.1 + 2048) w.push(`bytes propios ${KB(b.bytesSame)} → ${KB(r.bytesSame)} KB`);
      if (r.requests > b.requests + 1) w.push(`peticiones ${b.requests} → ${r.requests}`);
      if (r.delta && (r.delta.cls > Math.max(0.01, (b.deltaCls || 0) + 0.005))) w.push(`CLS ${b.deltaCls || 0} → ${r.delta.cls}`);
      if (r.jquery && !b.jquery) w.push('jQuery nuevo');
      if (r.proHandlers && !b.proHandlers) w.push('handlers de PRO nuevos');
      if (r.proFrontend && b.proFrontend === false) w.push('frontend de PRO nuevo');
      if (r.swiper && !b.swiper) w.push('Swiper nuevo');
      if (r.lottieLib && b.lottieLib === false) w.push('Lottie nuevo');
      if (r.prism && b.prism === false) w.push('Prism nuevo');
      if (r.fontAwesome && b.fontAwesome === false) w.push('Font Awesome nuevo');
      if (r.eicons && b.eicons === false) w.push('eicons nuevos');
      if (Array.isArray(b.thirdHosts)) { const nw = r.thirdHosts.filter((h) => !b.thirdHosts.includes(h)); if (nw.length) w.push(`hosts externos nuevos ${nw.join(', ')}`); }
      if (r.imagesNoDimensions > b.imagesNoDimensions) w.push(`imágenes sin dimensiones ${b.imagesNoDimensions} → ${r.imagesNoDimensions}`);
      if (r.iframesNotLazy > b.iframesNotLazy) w.push(`iframes sin diferir ${b.iframesNotLazy} → ${r.iframesNotLazy}`);
      if (r.errors > b.errors) w.push('errores nuevos');
      if (w.length) { worse++; problem(`PEOR ${slug} · ${w.join('; ')}`); }
    }
    if (!worse) console.log('Sin regresiones frente a la línea base.');
  }
  console.log(`\n${file} · modo ${LABEL} · ${Object.keys(results).length} mediciones · ${problems.length} problemas · ${duration} s`);
  process.exit(problems.length && (EXPECT || Object.values(results).some((r) => !r.ok)) ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });

// Comprobaciones del modo sobre el estado de la sonda (§5.3).
function modeChecks(states) {
  const s = states.final || {};
  const t = states.transition || {};
  if (!s.module) { problem(`${LABEL}: el módulo digitalisimo-front no está cargado`); return; }
  if (!t.applied || !t.applied.hash) problem(`${LABEL}: la transición no escribió el valor aplicado (${JSON.stringify(t.applied)})`);
  if (s.pending) problem(`${LABEL}: la transición sigue pendiente al terminar`);
  const applied = s.applied || {};
  if (MODE.core === 'plugin') {
    if (s.bundled || s.elementor !== MODE.elementor_pin) problem(`plugin: se esperaba el plugin Elementor ${MODE.elementor_pin} y no el núcleo incluido (${s.elementor}, incluido ${s.bundled})`);
    for (const [option, on] of Object.entries(s.filters || {})) if (on) problem(`plugin: hay un filtro default_option_${option} con el constructor original`);
    if (s.core_defaults) problem('plugin: Core_Defaults::active() debe ser falso con el constructor original');
  } else if (!s.bundled) problem(`${LABEL}: se esperaba el núcleo incluido`);
  if (!DEFAULTS && !MODE.lazy && applied.pro_conditional !== true) problem(`${LABEL}: pro_conditional no quedó aplicado`);
  if (MODE.lazy && applied.lazy_libs !== true) problem('lazy: lazy_libs no quedó aplicado');
  if (DEFAULTS === 'old' || DEFAULTS === 'old-dirty' || DEFAULTS === 'old-inactive' || DEFAULTS === 'old-applied') {
    if (applied.font_swap !== true) problem(`${LABEL}: font_swap no quedó aplicado (fila elementor_font_display ${s.rows && s.rows.elementor_font_display})`);
    if (!(s.filters || {}).elementor_font_display) problem(`${LABEL}: falta el filtro default_option_elementor_font_display`);
  }
  if (DEFAULTS === 'old' && applied.svg_icons) problem('old: los iconos en línea no deben aplicarse solos');
  if (DEFAULTS === 'old-applied' && applied.svg_icons !== true) problem('old-applied: «Aplicar» no dejó svg_icons aplicado');
  if (DEFAULTS === 'old-inactive' && applied.svg_icons) problem('old-inactive: con el experimento guardado inactivo, «Aplicar» no debe cambiar nada');
  if (DEFAULTS === 'old-dirty') {
    const scan = s.scan || {};
    if (!s.inherits_svg) problem('old-dirty: el sitio debe heredar svg_icons de la red');
    if (applied.svg_icons) problem('old-dirty: con hallazgos, los iconos heredados no deben aplicarse');
    if (scan.verdict !== 'findings') problem(`old-dirty: veredicto ${scan.verdict} (se esperaba findings)`);
    if (!JSON.stringify(scan.findings || []).includes('fa-star')) problem(`old-dirty: el hallazgo de .fa-star no aparece en ?digi_probe=state (${JSON.stringify(scan.findings || []).slice(0, 200)})`);
  }
  if (DEFAULTS === 'off') for (const key of ['pro_conditional', 'font_swap', 'svg_icons', 'lazy_libs', 'element_cache_off', 'image_loading_off']) if (applied[key]) problem(`off: ${key} quedó aplicado`);
  if (MODE.seo === '1') { const fb = (s.options || {}).digitalisimo_performance_js_fallback; if (fb && fb.at) problem(`seo: digitalisimo_performance_js_fallback no está vacía (${JSON.stringify(fb)})`); }
}
