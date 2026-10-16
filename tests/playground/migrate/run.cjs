/*
 * «Migrar toda la red» en Chrome: firma de cada página de los dos sitios antes y después (textos, enlaces e imágenes;
 * en los alias, además, el estilo calculado de cada elemento del widget), un solo clic en la pantalla de red y las
 * comprobaciones de estado de cada sitio (plugins, núcleo incluido, documentos, revisiones, informe y cerrojo).
 */
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const base = 'http://playground.test';
const port = process.argv[2];
const out = process.env.OUT || '/tmp';
const pages = JSON.parse(fs.readFileSync(`${out}/pages.json`, 'utf8'));
const blogs = { 1: '', 2: '/sub' };
const results = { checks: [], failures: [] };
const ok = (cond, message, detail) => {
  (cond ? results.checks : results.failures).push(detail === undefined ? message : `${message}: ${typeof detail === 'string' ? detail : JSON.stringify(detail)}`);
  console.log(`${cond ? 'OK   ' : 'FALLA'}  ${message}${cond || detail === undefined ? '' : ` · ${typeof detail === 'string' ? detail : JSON.stringify(detail).slice(0, 600)}`}`);
};

async function newPage(context) {
  const page = await context.newPage();
  await page.setViewport({ width: 1280, height: 900 });
  await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
  await page.setRequestInterception(true);
  // Sólo el sitio de prueba: las imágenes de ejemplo externas no cambian nada y ralentizan la carga.
  page.on('request', (r) => (r.url().startsWith(base) || r.url().startsWith('data:') ? r.continue() : r.abort()));
  page.errors = [];
  page.on('pageerror', (e) => page.errors.push(String(e.message || e)));
  page.on('console', (m) => { if (m.type() === 'error' && !/net::ERR_FAILED|ERR_BLOCKED|Failed to load resource/.test(m.text())) page.errors.push(m.text()); });
  // Recursos del sitio que responden con error, con su dirección y la página que los pidió.
  page.on('response', (r) => { if (r.status() >= 400 && r.url().startsWith(base) && !r.url().includes('digi-migrate-probe')) page.errors.push(`${r.status()} ${r.url()} (desde ${page.url()})`); });
  return page;
}

async function signature(page, alias) {
  return page.evaluate((alias) => {
    const root = document.querySelector('[data-elementor-type="wp-page"]') || document.body;
    const widget = [...root.querySelectorAll('.elementor-widget')].pop();
    const skip = (node) => node.parentElement && node.parentElement.closest('script,style,svg,noscript,template');
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    const text = [];
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
      const value = node.nodeValue.replace(/\s+/g, ' ').trim();
      if (value && !skip(node)) text.push(value);
    }
    const links = [...root.querySelectorAll('a[href]')].map((a) => a.getAttribute('href') + (a.getAttribute('target') ? ` →${a.getAttribute('target')}` : ''));
    const images = [...root.querySelectorAll('img')].map((img) => `${(img.getAttribute('src') || '').split('?')[0].split('/').pop()} | alt=${(img.getAttribute('alt') || '').trim()}`);
    let styles = null;
    if (alias && widget) {
      const props = ['display', 'position', 'color', 'background-color', 'font-size', 'font-weight', 'letter-spacing', 'line-height', 'text-align', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'border-top-width', 'border-top-color', 'border-bottom-width', 'border-bottom-color', 'border-radius', 'outline-style', 'outline-width', 'outline-color', 'opacity', 'gap', 'grid-template-columns', 'width', 'height', 'visibility'];
      styles = [...widget.querySelectorAll('*')].filter((el) => !(el instanceof SVGElement) && !['SCRIPT', 'STYLE'].includes(el.tagName)).map((el) => {
        const cs = getComputedStyle(el);
        return `${el.tagName}:${props.map((p) => cs.getPropertyValue(p)).join(',')}`;
      });
    }
    // Diagnóstico (no se compara): ancho del contenedor y su relleno, que vienen del CSS del kit de Elementor.
    const box = root.querySelector('.e-con');
    const layout = box ? `${getComputedStyle(box).width} · relleno ${getComputedStyle(box).paddingLeft} · ${[...document.querySelectorAll('link[rel=stylesheet]')].map((l) => l.href.split('/').pop().split('?')[0]).filter((h) => /^post-/.test(h)).join(',')}` : '';
    return { text, links, images, styles, layout, widget: widget ? widget.getAttribute('data-widget_type') : '' };
  }, alias);
}

async function snapshot(context, label) {
  const shots = {};
  const page = await newPage(context);
  for (const [blog, path] of Object.entries(blogs)) {
    for (const [slug, info] of Object.entries(pages)) {
      const url = `${base}${path}/${slug}/`;
      const response = await page.goto(url, { waitUntil: 'networkidle0', timeout: 180000 });
      await page.evaluate(() => document.fonts && document.fonts.ready);
      ok(response && response.status() === 200, `${label} · ${url} responde 200`, response && String(response.status()));
      shots[`${blog}:${slug}`] = await signature(page, info.alias);
    }
  }
  ok(!page.errors.length, `${label} · sin errores de JavaScript en las páginas`, page.errors);
  await page.close();
  return shots;
}

async function probe(page, blog) {
  const response = await page.goto(`${base}${blogs[blog]}/?digi-migrate-probe=1`, { waitUntil: 'domcontentloaded', timeout: 180000 });
  return JSON.parse(await response.text());
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', `--host-resolver-rules=MAP playground.test 127.0.0.1:${port}`] });
  try {
    // 1. Antes: Element Pack activo en cada sitio y Elementor (plugin) en toda la red.
    const guest = await browser.createBrowserContext();
    const probePage = await newPage(guest);
    for (const blog of Object.keys(blogs)) {
      const state = await probe(probePage, blog);
      ok(state.ep_loaded && !state.bundled && state.network.includes('elementor/elementor.php') && state.active.includes('bdthemes-element-pack/bdthemes-element-pack.php'), `antes · sitio ${blog}: Element Pack activo y Elementor (plugin ${state.elementor}) en la red`, state);
    }
    results.elementorPlugin = (await probe(probePage, 1)).elementor;
    await probePage.close();
    const before = await snapshot(guest, 'antes');
    await guest.close();

    // 2. Un clic: Red → Ajustes → Migrar a DIGITALÍSIMO Elements → «Migrar toda la red».
    const admin = await browser.createBrowserContext();
    const page = await newPage(admin);
    await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 180000 });
    await page.type('#user_login', 'admin');
    await page.type('#user_pass', 'password');
    await Promise.all([page.waitForNavigation({ timeout: 180000 }), page.click('#wp-submit')]);
    await page.goto(`${base}/wp-admin/network/settings.php?page=digitalisimo-ep-migration`, { waitUntil: 'networkidle0', timeout: 180000 });
    const buttons = await page.$$eval('#digi-migrate button, .wrap form button, .wrap form input[type=submit]', (els) => els.map((el) => (el.textContent || el.value || '').trim()));
    ok(buttons.length === 1 && buttons[0] === 'Migrar toda la red', 'la pantalla de red tiene un solo botón: «Migrar toda la red»', buttons);
    const started = Date.now();
    await page.click('#digi-migrate .digi-migrate__button');
    await page.waitForFunction(() => { const done = document.querySelector('#digi-migrate .digi-migrate__done'); return done && /Migración de la red completada/.test(done.textContent); }, { timeout: 1500000, polling: 2000 });
    results.seconds = Math.round((Date.now() - started) / 1000);
    const summary = await page.$eval('#digi-migrate .digi-migrate__status', (el) => el.innerText);
    results.networkSummary = summary;
    ok(/Ya no están activos en ningún sitio/.test(summary) && /Element Pack Pro/.test(summary) && /Elementor/.test(summary), 'el resumen de red dice qué plugins ya pueden borrarse', summary);
    ok(!page.errors.length, 'la pantalla de red no tuvo errores de JavaScript', page.errors);

    // 3. Estado de cada sitio tras migrar.
    for (const blog of Object.keys(blogs)) {
      const state = await probe(page, blog);
      ok(!state.ep_loaded && state.bundled && !state.network.includes('elementor/elementor.php') && !state.active.includes('bdthemes-element-pack/bdthemes-element-pack.php') && !state.active.includes('elementor/elementor.php'), `después · sitio ${blog}: sin Element Pack ni Elementor, con el núcleo incluido ${state.elementor}`, { active: state.active, network: state.network, bundled: state.bundled });
      ok(!state.job && !state.lock && state.report && state.report.done, `después · sitio ${blog}: tarea terminada, sin cerrojo, con informe`, { job: state.job, lock: state.lock });
      results[`css-after-migration-${blog}`] = { kit: state.kit, mode: state.mode, css: state.css };
      for (const [slug, info] of Object.entries(pages)) {
        const doc = state.pages[slug] || {};
        const widgets = doc.widgets || [];
        const keepAlias = blog === '2' && info.widget === 'digitalisimo-brand-grid';
        const legacy = widgets.filter((w) => /^bdt-/.test(w) || ['digitalisimo-brand-carousel', 'digitalisimo-logo-carousel', 'digitalisimo-brand-grid', 'digitalisimo-logo-grid', 'digitalisimo-creative-button', 'digitalisimo-icon-mobile-menu', 'digitalisimo-content-switcher'].includes(w));
        if (keepAlias) {
          ok(widgets.includes('digitalisimo-brand-grid'), `después · sitio 2 · ${slug}: el alias se queda, porque el CSS adicional nombra sus clases`, widgets);
        } else {
          ok(!legacy.length && !doc.legacy_keys && doc.backup, `después · sitio ${blog} · ${slug}: sólo widgets propios, con copia del original`, { widgets, legacy_keys: doc.legacy_keys, backup: doc.backup });
        }
      }
      const revisions = Object.entries(state.pages).filter(([key]) => key.startsWith('revision-of-digi-mg-accordion-')).map(([, doc]) => doc);
      ok(revisions.length === 2 && revisions.every((doc) => doc.widgets && !doc.widgets.some((w) => /^bdt-/.test(w)) && doc.backup), `después · sitio ${blog}: las dos revisiones con Element Pack también se convirtieron`, revisions);
      const counts = state.report.counts;
      ok(counts.deactivated.includes('Element Pack Pro') && counts.converted >= 3 && counts.revisions >= 2 && counts.aliases >= (blog === '2' ? 4 : 5) && !counts.failed.length, `después · sitio ${blog}: el informe cuenta lo hecho, sin documentos fallidos`, counts);
      ok(!(state.report.warnings || []).length, `después · sitio ${blog}: sin avisos`, state.report.warnings);
    }

    // 4. El informe del subsitio explica el alias que se quedó.
    await page.goto(`${base}/sub/wp-admin/tools.php?page=digitalisimo-ep-migration`, { waitUntil: 'networkidle0', timeout: 180000 });
    const report = await page.$eval('#digi-migrate', (el) => el.innerText);
    ok(/Migración completada/.test(report) && /digitalisimo-brand-grid siguen como alias/.test(report) && /CSS adicional/.test(report), 'el informe del subsitio dice qué alias se quedó y dónde está el CSS', report.slice(0, 1500));
    const siteButtons = await page.$$eval('#digi-migrate button', (els) => els.map((el) => el.textContent.trim()));
    ok(siteButtons.length === 1 && siteButtons[0] === 'Migrar', 'la herramienta del sitio tiene un solo botón: «Migrar»', siteButtons);
    await admin.close();

    // 5. Después: las mismas páginas, como visitante.
    const visitor = await browser.createBrowserContext();
    const after = await snapshot(visitor, 'después');
    const cssProbe = await newPage(visitor);
    for (const blog of Object.keys(blogs)) {
      const state = await probe(cssProbe, blog);
      const missing = Object.entries(state.css || {}).filter(([, v]) => /file · sin archivo/.test(v));
      results[`css-after-pages-${blog}`] = state.css;
      ok(!missing.length, `después · sitio ${blog}: cada CSS de Elementor marcado como archivo tiene su archivo`, missing);
    }
    await cssProbe.close();
    await visitor.close();
    for (const [key, sig] of Object.entries(after)) {
      const [blog, slug] = key.split(':');
      const info = pages[slug];
      const was = before[key];
      if (info.alias) {
        const keep = blog === '2' && info.widget === 'digitalisimo-brand-grid';
        ok(sig.widget === (keep ? 'digitalisimo-brand-grid.default' : (info.widget.match(/logo|brand/) ? 'digitalisimo-logos.default' : info.widget === 'digitalisimo-creative-button' ? 'digitalisimo-advanced-button.default' : info.widget === 'digitalisimo-icon-mobile-menu' ? 'digitalisimo-icon-nav.default' : 'digitalisimo-fancy-tabs.default')), `${key}: se pinta con ${sig.widget}`, sig.widget);
        ok(JSON.stringify(sig.text) === JSON.stringify(was.text) && JSON.stringify(sig.links) === JSON.stringify(was.links) && JSON.stringify(sig.images) === JSON.stringify(was.images), `${key}: mismos textos, enlaces e imágenes`, { before: was, after: sig });
        const diff = (sig.styles || []).map((s, i) => (s === (was.styles || [])[i] ? null : { i, before: (was.styles || [])[i], after: s })).filter(Boolean);
        ok((sig.styles || []).length > 0, `${key}: el widget pinta elementos que comparar`, sig);
        ok((sig.styles || []).length === (was.styles || []).length && !diff.length, `${key}: el mismo estilo calculado en cada elemento (${(sig.styles || []).length})`, { layout: [was.layout, sig.layout], diff: diff.slice(0, 3) });
      } else {
        ok(!/^bdt-/.test(sig.widget), `${key}: se pinta con un widget propio (${sig.widget})`, sig.widget);
        if (info.text) {
          ok(JSON.stringify(sig.text) === JSON.stringify(was.text) && JSON.stringify(sig.links) === JSON.stringify(was.links), `${key}: mismos textos y enlaces que con Element Pack`, { before: was, after: sig });
        }
        // Los carruseles de Element Pack clonan diapositivas para el bucle: se comparan las imágenes distintas.
        const set = (list) => JSON.stringify([...new Set(list)].sort());
        ok(set(sig.images) === set(was.images), `${key}: mismas imágenes que con Element Pack`, { before: was.images, after: sig.images });
      }
    }
  } catch (e) {
    ok(false, 'la prueba no pudo completarse', String(e.stack || e));
  } finally {
    await browser.close();
    fs.writeFileSync(`${out}/migrate.json`, JSON.stringify(results, null, 2));
  }
  console.log(`\n${results.checks.length} comprobaciones en verde, ${results.failures.length} fallas${results.seconds ? `; la migración de la red tardó ${results.seconds} s` : ''}.`);
  process.exit(results.failures.length ? 1 : 0);
})();
