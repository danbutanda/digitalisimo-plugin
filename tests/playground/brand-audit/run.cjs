// Auditoría de marca en Chrome: recorre administración, editor y frontal con el núcleo incluido y anota cada
// aparición de «Elementor» que vea un usuario (texto, atributos accesibles, títulos, imágenes y logotipos).
// Las clases CSS, los identificadores, las URL internas y los datos no cuentan: son la compatibilidad.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const base = process.argv[2];
const OUT = process.env.OUT || '/tmp';
const LOCALES = (process.env.LOCALES || 'es_MX,en_US').split(',');
const BRAND = /elementor/i;

// Se ejecuta dentro de cada documento (y de sus iframes del mismo origen).
function collect() {
  const re = /elementor/i;
  const out = [];
  const shown = (el) => {
    for (let n = el; n && n.nodeType === 1; n = n.parentElement) {
      const s = getComputedStyle(n);
      if (s.display === 'none' || s.visibility === 'hidden' || s.opacity === '0') return false;
    }
    const r = el.getBoundingClientRect();
    return r.width > 0 && r.height > 0;
  };
  const where = (el) => {
    const parts = [];
    for (let n = el; n && n.nodeType === 1 && parts.length < 4; n = n.parentElement) {
      parts.unshift(n.tagName.toLowerCase() + (n.id ? '#' + n.id : '') + (typeof n.className === 'string' && n.className.trim() ? '.' + n.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
    }
    return parts.join(' > ');
  };
  if (re.test(document.title)) out.push({ kind: 'título', text: document.title, shown: true, where: 'title' });
  const walker = document.createTreeWalker(document.body || document.documentElement, NodeFilter.SHOW_TEXT);
  while (walker.nextNode()) {
    const node = walker.currentNode;
    const text = (node.nodeValue || '').replace(/\s+/g, ' ').trim();
    const parent = node.parentElement;
    if (!text || !re.test(text) || !parent || ['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEMPLATE', 'TEXTAREA', 'CODE', 'PRE'].includes(parent.tagName)) continue;
    // Contenido técnico de diagnóstico (registro de errores con rutas y clases PHP): son identificadores, no marca.
    if (/\/wp-content\/|\bElementor\\[A-Z]|\[(notice|error|warning|deprecated|info) X \d+\]/.test(text)) continue;
    out.push({ kind: 'texto', text: text.slice(0, 200), shown: shown(parent), where: where(parent) });
  }
  document.querySelectorAll('[title],[alt],[aria-label],[placeholder],[data-tooltip],input[type=submit],input[type=button],option').forEach((el) => {
    for (const attr of ['title', 'alt', 'aria-label', 'placeholder', 'data-tooltip', 'value']) {
      const value = el.getAttribute(attr);
      if (!value || !re.test(value)) continue;
      if (attr === 'value' && !(el.tagName === 'INPUT' && ['submit', 'button'].includes(el.type))) continue;
      out.push({ kind: 'atributo ' + attr, text: value.slice(0, 200), shown: shown(el), where: where(el) });
    }
  });
  // Logotipos: imágenes de los recursos de marca y el glifo del logotipo en la fuente de iconos.
  document.querySelectorAll('img, [style*="background-image"]').forEach((el) => {
    const src = el.tagName === 'IMG' ? el.currentSrc || el.src : getComputedStyle(el).backgroundImage;
    if (src && /elementor[^/]*logo|\/elementor\/assets\/images\/logo|(^|[^a-z])e-logo|elementor-icon/i.test(src)) out.push({ kind: 'imagen', text: src.slice(0, 200), shown: shown(el), where: where(el) });
  });
  // El glifo del logotipo (\ue812, \ue813 de la fuente eicons) cuenta sólo si se sigue dibujando.
  document.querySelectorAll('[class*="eicon-elementor"], #adminmenu .wp-menu-image, i[class*="eicon"]').forEach((el) => {
    const before = getComputedStyle(el, '::before');
    if (/eicons/i.test(before.fontFamily) && /[\ue812\ue813]/.test(before.content || '')) out.push({ kind: 'logotipo', text: (typeof el.className === 'string' ? el.className : el.tagName).slice(0, 160), shown: shown(el), where: where(el) });
  });
  document.querySelectorAll('.elementor-loader-boxes, [class*="elementor-logo"], svg[class*="logo" i], [data-testid*="logo" i]').forEach((el) => {
    out.push({ kind: 'logotipo', text: (typeof el.className === 'string' ? el.className : el.getAttribute('class') || el.tagName).slice(0, 160), shown: shown(el), where: where(el) });
  });
  // Icono del menú: el núcleo lo dibuja con una máscara de su logotipo sobre el pseudo-elemento.
  document.querySelectorAll('#adminmenu li[id^="toplevel_page_elementor"] > a .wp-menu-image').forEach((el) => {
    const before = getComputedStyle(el, '::before');
    const mask = before.webkitMaskImage || before.maskImage || 'none';
    if (mask !== 'none' || !/icono\.png/.test(before.backgroundImage)) out.push({ kind: 'logotipo', text: 'icono del menú sin símbolo propio', shown: shown(el), where: where(el) });
  });
  // Logotipos SVG de las barras superiores: deben llevar el símbolo propio (si la estructura cambia, se avisa).
  for (const sel of ['#editor-one-top-bar header svg', '#elementor-editor-wrapper-v2 header button svg']) {
    const svg = document.querySelector(sel);
    if (svg && !/icono\.png/.test(getComputedStyle(svg).backgroundImage)) out.push({ kind: 'logotipo', text: 'SVG sin símbolo propio: ' + sel, shown: shown(svg), where: where(svg) });
  }
  return out;
}

async function audit(page, label, locale, findings) {
  const frames = page.frames().filter((f) => f.url().startsWith(base));
  for (const frame of frames) {
    let items = [];
    try { items = await frame.evaluate(collect); } catch (e) { continue; }
    for (const item of items) findings.push({ locale, screen: label, frame: frame === page.mainFrame() ? '' : 'iframe', ...item });
  }
  const file = `${OUT}/${locale}-${label.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '').slice(0, 80)}.png`;
  await page.screenshot({ path: file, fullPage: false }).catch(() => {});
  // Marcado de las barras superiores (administración y editor), donde el logotipo es un SVG: sirve para revisar.
  const bars = await page.evaluate(() => ['#editor-one-top-bar', '#elementor-editor-wrapper-v2 header', '.eps-app__header'].map((s) => document.querySelector(s)).filter(Boolean).map((el) => el.outerHTML.slice(0, 6000)).join('\n\n')).catch(() => '');
  if (bars) fs.writeFileSync(file.replace(/\.png$/, '.bars.html'), bars);
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 120000 });
  await page.type('#user_login', 'admin');
  await page.type('#user_pass', 'password');
  await Promise.all([page.waitForNavigation({ timeout: 120000 }), page.click('#wp-submit')]);
  const [entry] = await page.evaluate(async (url) => (await fetch(url)).json(), `${base}/wp-json/wp/v2/pages?slug=digi-marca`);
  const findings = [];
  const visited = [];
  for (const locale of LOCALES) {
    await page.setCookie({ name: 'digi_locale', value: locale, url: base });
    await page.goto(`${base}/wp-admin/index.php`, { waitUntil: 'networkidle2', timeout: 120000 });
    // Todas las pantallas del menú de administración que pertenecen al constructor.
    const menu = await page.$$eval('#adminmenu a[href]', (links) => [...new Set(links.map((a) => a.href))]);
    const screens = menu.filter((href) => /elementor|e-landing-page|e-floating|page=e-|digitalisimo-elements/i.test(href));
    const fixed = [
      'wp-admin/index.php',
      'wp-admin/plugins.php',
      'wp-admin/edit.php?post_type=page',
      `wp-admin/post.php?post=${entry.id}&action=edit`,
      'wp-admin/admin.php?page=elementor-app#/kit-library',
      'wp-admin/admin.php?page=elementor-app#/import',
      'wp-admin/admin.php?page=elementor-app#/export',
      'wp-admin/admin.php?page=elementor-app#/site-editor',
    ].map((path) => `${base}/${path}`);
    for (const url of [...new Set([...fixed, ...screens])]) {
      try {
        await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });
        await new Promise((r) => setTimeout(r, 1500));
        // El editor de bloques puede abrir su guía de bienvenida: se cierra para ver la barra superior.
        await page.$$eval('.components-modal__screen-overlay button[aria-label]', (b) => b.forEach((x) => x.click())).catch(() => {});
        await audit(page, url.replace(base, ''), locale, findings);
        visited.push(`${locale} ${url.replace(base, '')}`);
      } catch (e) {
        findings.push({ locale, screen: url.replace(base, ''), kind: 'error', text: String(e.message || e), shown: true, where: '' });
      }
    }
    // Editor: panel, barra superior y su menú, y la biblioteca de plantillas.
    try {
      await page.goto(`${base}/wp-admin/post.php?post=${entry.id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 180000 });
      await new Promise((r) => setTimeout(r, 800));
      await audit(page, 'editor-cargando', locale, findings);
      await page.waitForSelector('#elementor-panel', { timeout: 180000 });
      const frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: 180000 })).contentFrame();
      await frame.waitForSelector('.elementor-widget', { timeout: 180000 });
      await page.waitForFunction(() => { const l = document.querySelector('#elementor-loading'); return !l || getComputedStyle(l).display === 'none' || l.getBoundingClientRect().width === 0; }, { timeout: 120000 }).catch(() => {});
      await new Promise((r) => setTimeout(r, 4000));
      await audit(page, 'editor', locale, findings);
      // Menú principal del editor (botón con el logotipo en la barra superior).
      const opened = await page.evaluate(() => {
        const button = document.querySelector('#elementor-editor-wrapper-v2 header button, #elementor-panel-header-menu-button, [aria-label*="menu" i]');
        if (button) { button.click(); return true; }
        return false;
      });
      if (opened) { await new Promise((r) => setTimeout(r, 1500)); await audit(page, 'editor-menu', locale, findings); await page.keyboard.press('Escape'); }
      const library = await frame.evaluate(() => { const b = document.querySelector('.elementor-add-template-button'); if (b) { b.click(); return true; } return false; });
      if (library) {
        await new Promise((r) => setTimeout(r, 1000));
        await audit(page, 'editor-biblioteca-cargando', locale, findings);
        await new Promise((r) => setTimeout(r, 8000));
        await audit(page, 'editor-biblioteca', locale, findings);
      }
      visited.push(`${locale} editor`);
    } catch (e) {
      findings.push({ locale, screen: 'editor', kind: 'error', text: String(e.message || e), shown: true, where: '' });
    }
    // Frontal como administrador (barra de administración) y como visitante (meta generator).
    await page.goto(`${base}/digi-marca/`, { waitUntil: 'networkidle2', timeout: 120000 });
    await audit(page, 'frontal-admin', locale, findings);
    const html = await page.evaluate(async (url) => (await fetch(url, { credentials: 'omit' })).text(), `${base}/digi-marca/`);
    for (const match of html.match(/<meta[^>]+generator[^>]*>/gi) || []) {
      if (BRAND.test(match)) findings.push({ locale, screen: 'frontal-código', kind: 'meta generator', text: match, shown: true, where: 'head' });
    }
  }
  await browser.close();
  const shown = findings.filter((f) => f.shown);
  fs.writeFileSync(`${OUT}/brand-audit.json`, JSON.stringify({ visited, findings, errors }, null, 2));
  const groups = new Map();
  for (const f of shown) {
    const key = `${f.kind} · ${f.text}`;
    if (!groups.has(key)) groups.set(key, new Set());
    groups.get(key).add(`${f.locale} ${f.screen}`);
  }
  for (const [key, where] of groups) console.log(`VISIBLE  ${key}\n         en: ${[...where].slice(0, 4).join(' | ')}${where.size > 4 ? ` (+${where.size - 4})` : ''}`);
  // Lo que está en el DOM sin verse (diálogos, menús cerrados) aparece al interactuar: se lista aparte.
  const hidden = new Map();
  for (const f of findings.filter((x) => !x.shown)) {
    const key = `${f.kind} · ${f.text}`;
    if (!hidden.has(key)) hidden.set(key, `${f.locale} ${f.screen}`);
  }
  for (const [key, where] of hidden) console.log(`OCULTO   ${key}  (${where})`);
  console.log(`\nPantallas revisadas: ${visited.length}. Apariciones visibles: ${shown.length} (${groups.size} distintas); en el DOM pero ocultas: ${findings.length - shown.length}.`);
  process.exit(shown.length ? 1 : 0);
})();
