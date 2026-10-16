// Medición del front de DIGITALÍSIMO Elements en Chrome sin interfaz: una página por caso, coste frente a la página vacía.
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');
const base = process.argv[2];
const out = process.env.OUT || '/tmp';
const cases = JSON.parse(fs.readFileSync(path.join(out, 'cases.json'), 'utf8'));
const baselineFile = process.env.BASELINE_FILE;
const writeBaseline = process.env.BASELINE === 'write';
const lighthouseOn = !!process.env.LIGHTHOUSE;
const KB = (n) => Math.round(n / 102.4) / 10;

const kind = (url, type) => {
  if (/\.css(\?|$)/.test(url) || type === 'stylesheet') return 'css';
  if (/\.(m?js)(\?|$)/.test(url) || type === 'script') return 'js';
  if (/\.(png|jpe?g|gif|webp|avif|svg)(\?|$)/.test(url) || type === 'image') return 'img';
  if (/\.(woff2?|ttf|otf|eot)(\?|$)/.test(url) || type === 'font') return 'font';
  if (type === 'document') return 'doc';
  return 'other';
};

async function measure(browser, slug, caseDef) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  // Sin caché: cada página paga todos sus recursos, como la primera visita de un usuario.
  await page.setCacheEnabled(false);
  const requests = [];
  const errors = [];
  const failed = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('response', async (r) => {
    const url = r.url();
    if (url.startsWith('data:')) return;
    const same = url.startsWith(base);
    let bytes = parseInt(r.headers()['content-length'] || '0', 10);
    if (!bytes && r.status() === 200) { try { bytes = (await r.buffer()).length; } catch (e) { bytes = 0; } }
    if (r.status() >= 400 && same) failed.push(`${r.status()} ${url}`);
    requests.push({ url, type: kind(url, r.request().resourceType()), bytes, same, status: r.status() });
  });
  await page.evaluateOnNewDocument(() => {
    window.__digi = { cls: 0, longTasks: 0, longTaskMs: 0 };
    try {
      new PerformanceObserver((list) => { list.getEntries().forEach((e) => { if (!e.hadRecentInput) window.__digi.cls += e.value; }); }).observe({ type: 'layout-shift', buffered: true });
      new PerformanceObserver((list) => { list.getEntries().forEach((e) => { window.__digi.longTasks++; window.__digi.longTaskMs += e.duration; }); }).observe({ type: 'longtask', buffered: true });
    } catch (e) {}
  });
  const url = `${base}/digi-fb-${slug}/`;
  const result = { slug, title: caseDef.title || slug, ok: true };
  try {
    const response = await page.goto(url, { waitUntil: 'networkidle0', timeout: 120000 });
    if (!response || response.status() !== 200) throw new Error(`la página respondió ${response && response.status()}`);
    await new Promise((r) => setTimeout(r, 1500));
    const dom = await page.evaluate(() => {
      const imgs = [...document.querySelectorAll('.elementor-widget img')];
      const iframes = [...document.querySelectorAll('.elementor-widget iframe')];
      const widget = document.querySelector('.elementor-widget');
      return {
        nodes: document.getElementsByTagName('*').length,
        widgetNodes: widget ? widget.getElementsByTagName('*').length : 0,
        images: imgs.length,
        imagesNoDimensions: imgs.filter((i) => !i.getAttribute('width') || !i.getAttribute('height')).length,
        imagesNoSrcset: imgs.filter((i) => !i.getAttribute('srcset') && !/\.svg(\?|$)/.test(i.currentSrc || i.src)).length,
        imagesLazy: imgs.filter((i) => i.getAttribute('loading') === 'lazy').length,
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
      };
    });
    const sum = (filter) => requests.filter(filter).reduce((s, r) => s + r.bytes, 0);
    const handles = [...new Set(requests.filter((r) => r.same && /digitalisimo-elements\/(assets|modules)\//.test(r.url) && !/\/elementor\//.test(r.url)).map((r) => r.url.replace(/\?.*$/, '').split('/').pop()))].sort();
    Object.assign(result, {
      requests: requests.length,
      bytes: sum(() => true),
      bytesSame: sum((r) => r.same),
      bytesThird: sum((r) => !r.same),
      css: sum((r) => r.type === 'css'),
      js: sum((r) => r.type === 'js'),
      img: sum((r) => r.type === 'img'),
      font: sum((r) => r.type === 'font'),
      thirdHosts: [...new Set(requests.filter((r) => !r.same).map((r) => new URL(r.url).host))].sort(),
      jquery: requests.some((r) => /\/jquery(\.min)?\.js(\?|$)/.test(r.url) || /\/jquery\/jquery/.test(r.url)),
      proHandlers: requests.some((r) => /elements-handlers(\.min)?\.js/.test(r.url)),
      proFrontend: requests.some((r) => /digitalisimo-elements\/assets\/js\/frontend(\.min)?\.js/.test(r.url)),
      swiper: requests.some((r) => /swiper/.test(r.url)),
      lottie: requests.some((r) => /lottie/.test(r.url)),
      fontAwesome: requests.some((r) => /font-awesome|fontawesome|\/fa-/.test(r.url)),
      handles,
      failed,
      errors,
      ...dom,
    });
  } catch (e) {
    result.ok = false;
    result.error = String(e.message || e);
    await page.screenshot({ path: path.join(out, `${slug}.png`), fullPage: true }).catch(() => {});
  }
  await page.close();
  return result;
}

async function lighthouse(url, wsEndpoint) {
  let lh;
  try { lh = require('lighthouse'); } catch (e) { return { error: 'lighthouse no disponible en NODE_PATH' }; }
  const port = parseInt(new URL(wsEndpoint).port, 10);
  const runner = lh.default || lh;
  const r = await runner(url, { port, output: 'json', logLevel: 'silent', onlyCategories: ['performance'], formFactor: 'mobile', screenEmulation: { mobile: true, width: 412, height: 823, deviceScaleFactor: 1.75, disabled: false }, throttlingMethod: 'simulate' });
  const a = r.lhr.audits;
  return { score: Math.round((r.lhr.categories.performance.score || 0) * 100), lcp: Math.round(a['largest-contentful-paint'].numericValue), tbt: Math.round(a['total-blocking-time'].numericValue), cls: Math.round(a['cumulative-layout-shift'].numericValue * 1000) / 1000, bytes: Math.round(a['total-byte-weight'].numericValue) };
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--remote-debugging-port=0'] });
  const results = {};
  for (const slug of Object.keys(cases)) results[slug] = await measure(browser, slug, cases[slug]);
  const empty = results.empty || {};
  for (const slug of Object.keys(results)) {
    const r = results[slug];
    if (!r.ok || slug === 'empty') continue;
    r.delta = { requests: r.requests - (empty.requests || 0), bytes: r.bytes - (empty.bytes || 0), css: r.css - (empty.css || 0), js: r.js - (empty.js || 0), img: r.img - (empty.img || 0), third: r.bytesThird - (empty.bytesThird || 0), nodes: r.nodes - (empty.nodes || 0), cls: Math.round((r.cls - (empty.cls || 0)) * 1000) / 1000 };
  }
  if (lighthouseOn) {
    for (const slug of ['empty', 'all-own', 'pro-heavy']) {
      if (!results[slug]) continue;
      results[slug].lighthouse = await lighthouse(`${base}/digi-fb-${slug}/`, browser.wsEndpoint()).catch((e) => ({ error: String(e.message || e) }));
    }
  }
  await browser.close();
  fs.writeFileSync(path.join(out, 'front-budget.json'), JSON.stringify(results, null, 2));

  // Tabla legible: coste de cada widget frente a la página vacía.
  const widgets = Object.keys(results).filter((s) => results[s].ok && results[s].delta);
  console.log(`vacía: ${results.empty && results.empty.ok ? `${results.empty.requests} peticiones, ${KB(results.empty.bytes)} KB (JS ${KB(results.empty.js)} KB, CSS ${KB(results.empty.css)} KB), ${results.empty.nodes} nodos, jQuery ${results.empty.jquery ? 'sí' : 'no'}, handlers PRO ${results.empty.proHandlers ? 'sí' : 'no'}` : 'FALLA'}`);
  console.log('caso                          pet  KB    css   js    img   terc  nodos cls    tareas img/sin-dim/sin-srcset/lazy  iframes/sin-lazy  jq pro sw lo  errores');
  for (const slug of widgets) {
    const r = results[slug];
    const d = r.delta;
    console.log(`${slug.padEnd(30)}${String(d.requests).padStart(3)} ${KB(d.bytes).toFixed(1).padStart(6)} ${KB(d.css).toFixed(1).padStart(5)} ${KB(d.js).toFixed(1).padStart(5)} ${KB(d.img).toFixed(1).padStart(5)} ${KB(d.third).toFixed(1).padStart(5)} ${String(d.nodes).padStart(5)} ${d.cls.toFixed(3).padStart(6)} ${String(r.longTasks).padStart(5)}  ${String(r.images).padStart(3)}/${String(r.imagesNoDimensions).padEnd(3)}/${String(r.imagesNoSrcset).padEnd(3)}/${String(r.imagesLazy).padEnd(3)}        ${String(r.iframes).padStart(2)}/${String(r.iframesNotLazy).padEnd(2)}          ${r.jquery ? 'sí' : '- '} ${r.proHandlers ? 'sí' : '- '} ${r.swiper ? 'sí' : '- '} ${r.lottie ? 'sí' : '- '}  ${r.errors.length + r.failed.length ? r.errors.concat(r.failed).join(' | ').slice(0, 80) : ''}`);
  }
  for (const slug of Object.keys(results)) if (!results[slug].ok) console.log(`FALLA  ${slug} · ${results[slug].error}`);
  for (const slug of ['empty', 'all-own', 'pro-heavy']) if (results[slug] && results[slug].lighthouse) console.log(`lighthouse ${slug}: ${JSON.stringify(results[slug].lighthouse)}`);

  // Línea base: se guarda o se compara (bytes propios +10 %, una petición más, CLS, jQuery o paquetes nuevos, errores).
  let failures = Object.keys(results).filter((s) => !results[s].ok).length;
  if (writeBaseline && baselineFile) {
    const slim = {};
    for (const slug of Object.keys(results)) { const r = results[slug]; slim[slug] = r.ok ? { requests: r.requests, bytesSame: r.bytesSame, css: r.css, js: r.js, img: r.img, cls: r.cls, deltaCls: r.delta ? r.delta.cls : 0, nodes: r.nodes, jquery: r.jquery, proHandlers: r.proHandlers, swiper: r.swiper, lottie: r.lottie, imagesNoDimensions: r.imagesNoDimensions, iframesNotLazy: r.iframesNotLazy, errors: r.errors.length + r.failed.length } : { failed: true }; }
    fs.writeFileSync(baselineFile, JSON.stringify(slim, null, 2) + '\n');
    console.log(`línea base guardada en ${baselineFile}`);
  } else if (baselineFile && fs.existsSync(baselineFile)) {
    const baseline = JSON.parse(fs.readFileSync(baselineFile, 'utf8'));
    for (const slug of Object.keys(baseline)) {
      const b = baseline[slug]; const r = results[slug];
      if (!r || !r.ok || b.failed) continue;
      const worse = [];
      if (r.bytesSame > b.bytesSame * 1.1 + 2048) worse.push(`bytes propios ${KB(b.bytesSame)} → ${KB(r.bytesSame)} KB`);
      if (r.requests > b.requests + 1) worse.push(`peticiones ${b.requests} → ${r.requests}`);
      if (r.delta && (r.delta.cls > Math.max(0.01, (b.deltaCls || 0) + 0.005))) worse.push(`CLS ${b.deltaCls || 0} → ${r.delta.cls}`);
      if (r.jquery && !b.jquery) worse.push('jQuery nuevo');
      if (r.proHandlers && !b.proHandlers) worse.push('handlers de PRO nuevos');
      if (r.swiper && !b.swiper) worse.push('Swiper nuevo');
      if (r.imagesNoDimensions > b.imagesNoDimensions) worse.push(`imágenes sin dimensiones ${b.imagesNoDimensions} → ${r.imagesNoDimensions}`);
      if (r.iframesNotLazy > b.iframesNotLazy) worse.push(`iframes sin diferir ${b.iframesNotLazy} → ${r.iframesNotLazy}`);
      if (r.errors.length + r.failed.length > b.errors) worse.push('errores nuevos');
      if (worse.length) { failures++; console.log(`PEOR   ${slug} · ${worse.join('; ')}`); }
    }
    if (!failures) console.log('Sin regresiones frente a la línea base.');
  }
  process.exit(failures ? 1 : 0);
})();
