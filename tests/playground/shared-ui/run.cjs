// Diseño compartido en Chrome: cada pantalla propia con su plugin solo y con los siete activos.
const puppeteer = require('puppeteer-core');
const fs = require('fs');
const base = process.argv[2];
const OUT = process.env.OUT || '/tmp';
const PLUGINS = {
  seo: 'digitalisimo-seo/digitalisimo-integrations.php',
  ia: 'digitalisimo.chatbot/digitalisimo-chatbot.php',
  hosting: 'digitalisimo-hosting/digitalisimo-hosting.php',
  backups: 'digitalisimo-backups/digitalisimo-backups.php',
  tools: 'digitalisimo-tools/digitalisimo-tools.php',
  elements: 'digitalisimo-elements/pro-elements.php',
  migration: 'digitalisimo-migration/digitalisimo-migration.php',
};
const SCREENS = {
  seo: ['admin.php?page=digitalisimo-seo'],
  ia: ['admin.php?page=digitalisimo-ai'],
  hosting: ['admin.php?page=digitalisimo-hosting'],
  backups: ['admin.php?page=digitalisimo-backups'],
  tools: ['admin.php?page=digitalisimo-tools-elementor-templates', 'admin.php?page=digitalisimo-tools-elementor-templates&tab=php'],
  // «Rendimiento» (F-front): la pantalla de sitio; la de red la cubre front-network.sh (este arnés es de sitio único).
  elements: ['admin.php?page=digitalisimo-elements-about', 'admin.php?page=digitalisimo-elements-front'],
  migration: ['tools.php?page=digitalisimo-ep-migration'],
};
// Migración requiere Elements (Requires Plugins); los demás se prueban solos.
const ALONE = Object.keys(SCREENS).map((key) => ({ name: key, plugins: key === 'migration' ? [PLUGINS.elements, PLUGINS.migration] : [PLUGINS[key]], screens: SCREENS[key] }));
const RUNS = [...ALONE, { name: 'todos', plugins: Object.values(PLUGINS), screens: Object.values(SCREENS).flat() }];

function inspect() {
  const palette = [[208, 4, 212], [243, 186, 253], [192, 11, 185], [147, 0, 63], [240, 171, 252], [146, 0, 59], [231, 60, 246], [29, 221, 191], [94, 234, 212], [88, 208, 245]];
  const near = (value) => { const rgb = (value.match(/rgba?\(([^)]+)\)/g) || []).map((c) => c.match(/[\d.]+/g).map(Number)); return rgb.some((c) => (c.length < 4 || c[3] > 0) && palette.some((p) => Math.hypot(p[0] - c[0], p[1] - c[1], p[2] - c[2]) < 40)); };
  const shown = (el) => { const r = el.getBoundingClientRect(); if (!r.width || !r.height) return false; for (let n = el; n && n.nodeType === 1; n = n.parentElement) { const s = getComputedStyle(n); if (s.display === 'none' || s.visibility === 'hidden') return false; } return true; };
  const elementorColors = [];
  for (const el of document.querySelectorAll('#wpbody-content *')) {
    if (!shown(el)) continue;
    const s = getComputedStyle(el);
    for (const prop of ['color', 'background-color', 'background-image', 'border-top-color', 'border-bottom-color']) {
      if (prop.startsWith('border') && parseFloat(s[prop.replace('-color', '-width')]) === 0) continue;
      if (near(s.getPropertyValue(prop))) elementorColors.push(`${el.tagName.toLowerCase()}.${String(el.className).split(/\s+/).slice(0, 2).join('.')} ${prop}: ${s.getPropertyValue(prop).slice(0, 60)}`);
    }
  }
  const primary = [...document.querySelectorAll('#wpbody-content .button-primary')].find(shown);
  const root = getComputedStyle(document.documentElement);
  return {
    title: document.title,
    bodyClass: document.body.className.includes('digitalisimo-admin'),
    sheets: [...document.querySelectorAll('link[rel=stylesheet]')].map((l) => l.id).filter((id) => /^digitalisimo/.test(id)),
    sharedHref: (document.getElementById('digitalisimo-ui-css') || {}).href || '',
    gradient: root.getPropertyValue('--digi-gradient').trim(),
    primary: primary ? { text: primary.value || primary.textContent.trim(), image: getComputedStyle(primary).backgroundImage, color: getComputedStyle(primary).color } : null,
    elementorColors,
    // F7a: la sección «Privacidad» de «Rendimiento», visible, con su casilla (la revisión de colores de arriba ya la cubre).
    privacy: (() => { const h = document.getElementById('digitalisimo-front-privacy'); return !!h && shown(h); })(),
    privacyField: !!document.querySelector('input[name="digitalisimo_elements_front[page_visits_no_consent]"]'),
  };
}

(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  const page = await browser.newPage();
  await page.setViewport({ width: 1440, height: 900 });
  await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 120000 });
  await page.type('#user_login', 'admin');
  await page.type('#user_pass', 'password');
  await Promise.all([page.waitForNavigation({ timeout: 120000 }), page.click('#wp-submit')]);
  const results = [];
  const problems = [];
  for (const run of RUNS) {
    await page.setCookie({ name: 'digi_ui_plugins', value: run.plugins.join(','), url: base });
    for (const screen of run.screens) {
      const response = await page.goto(`${base}/wp-admin/${screen}`, { waitUntil: 'networkidle2', timeout: 120000 });
      const facts = await page.evaluate(inspect);
      const label = `${run.name} · ${screen}`;
      const file = `${OUT}/${run.name}-${screen.replace(/[^a-z0-9]+/gi, '-').slice(0, 60)}.png`;
      // «Rendimiento» se captura entera: la sección «Privacidad» (F7a) queda debajo del primer pantallazo.
      await page.screenshot({ path: file, fullPage: /page=digitalisimo-elements-front\b/.test(screen) });
      const fail = [];
      if (!response || response.status() >= 400) fail.push(`respuesta ${response ? response.status() : 'nula'}`);
      if (!facts.bodyClass) fail.push('sin la clase de body digitalisimo-admin');
      if (facts.sheets.filter((id) => id === 'digitalisimo-ui-css').length !== 1) fail.push(`hojas compartidas: ${facts.sheets.join(', ')}`);
      if (facts.sheets.includes('digitalisimo-shared-ui-css')) fail.push(`hoja antigua de IA u Hosting: ${facts.sheets.join(', ')}`);
      if (!/linear-gradient/.test(facts.gradient)) fail.push(`token --digi-gradient inválido: «${facts.gradient}»`);
      if (facts.primary && !/linear-gradient/.test(facts.primary.image)) fail.push(`botón primario «${facts.primary.text}» sin degradado (${facts.primary.image})`);
      if (facts.elementorColors.length) fail.push(`paleta del constructor: ${facts.elementorColors.slice(0, 3).join(' | ')}`);
      if (/page=digitalisimo-elements-front\b/.test(screen) && (!facts.privacy || !facts.privacyField)) fail.push(`sin la sección «Privacidad» visible o sin la casilla page_visits_no_consent (${facts.privacy}/${facts.privacyField})`);
      results.push({ label, ...facts, fail });
      console.log(`${fail.length ? 'FALLA' : 'ok   '} ${label}${fail.length ? ' — ' + fail.join('; ') : ''}${facts.primary ? '' : ' (sin botón primario visible)'}`);
      if (fail.length) problems.push(label);
    }
  }
  fs.writeFileSync(`${OUT}/shared-ui.json`, JSON.stringify(results, null, 2));
  await browser.close();
  console.log(`\nPantallas: ${results.length}. Con problemas: ${problems.length}.`);
  process.exit(problems.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
