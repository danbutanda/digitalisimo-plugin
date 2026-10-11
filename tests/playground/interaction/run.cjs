// Interacciones en navegador real (Chrome sin interfaz) sobre páginas con widgets heredados sin Element Pack.
const puppeteer = require('puppeteer-core');
const base = process.argv[2];
const checks = {
  'bdt-accordion': async (page) => {
    const items = await page.$$('details.digi-accordion__item');
    if (items.length < 2) throw new Error('el acordeón no tiene elementos');
    await (await items[1].$('summary')).click();
    await page.waitForFunction(() => document.querySelectorAll('details.digi-accordion__item')[1].open, { timeout: 3000 });
    return 'el segundo elemento del acordeón se abre al pulsar';
  },
  'bdt-tabs': async (page) => {
    const tabs = await page.$$('[role="tab"]');
    if (tabs.length < 2) throw new Error('las pestañas no tienen botones');
    await tabs[1].click();
    await page.waitForFunction(() => {
      const tab = document.querySelectorAll('[role="tab"]')[1];
      const panel = document.getElementById(tab.getAttribute('aria-controls'));
      return tab.getAttribute('aria-selected') === 'true' && panel && !panel.hidden;
    }, { timeout: 3000 });
    return 'la segunda pestaña se selecciona y muestra su panel';
  },
  'bdt-static-carousel': async (page) => {
    await page.waitForSelector('[data-digi-carousel-next]', { timeout: 5000 });
    const before = await page.$eval('[data-digi-carousel-track]', (t) => t.scrollLeft);
    await page.click('[data-digi-carousel-next]');
    await page.waitForFunction((b) => document.querySelector('[data-digi-carousel-track]').scrollLeft > b, { timeout: 4000 }, before);
    await page.waitForFunction(() => document.querySelector('[data-digi-carousel-prev]').getAttribute('aria-disabled') !== 'true', { timeout: 4000 });
    return 'el deslizador propio avanza con la flecha y habilita «anterior»';
  },
  'bdt-custom-carousel': async (page) => {
    await page.waitForSelector('.swiper-initialized, .swiper-container-initialized', { timeout: 10000 });
    const before = await page.$eval('.swiper-slide-active', (s) => s.getAttribute('data-swiper-slide-index') ?? s.className);
    await page.click('.elementor-swiper-button-next');
    await page.waitForFunction((b) => {
      const a = document.querySelector('.swiper-slide-active');
      return a && (a.getAttribute('data-swiper-slide-index') ?? a.className) !== b;
    }, { timeout: 4000 }, before);
    return 'el carrusel de medios de PRO Elements avanza con la flecha';
  },
  'bdt-offcanvas': async (page) => {
    await page.waitForSelector('[data-digi-offcanvas-open]', { timeout: 5000 });
    await page.click('[data-digi-offcanvas-open]');
    await page.waitForFunction(() => {
      const panel = document.querySelector('.digi-offcanvas__panel');
      const button = document.querySelector('[data-digi-offcanvas-open]');
      return panel && !panel.hidden && panel.classList.contains('is-open') && button.getAttribute('aria-expanded') === 'true';
    }, { timeout: 4000 });
    await page.keyboard.press('Escape');
    await page.waitForFunction(() => document.querySelector('.digi-offcanvas__panel').hidden, { timeout: 4000 });
    return 'el panel lateral se abre con su botón y se cierra con Escape';
  },
};
(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage'] });
  const results = {};
  let failed = 0;
  for (const widget of Object.keys(checks)) {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 900 });
    const errors = [];
    const failedRequests = [];
    page.on('pageerror', (e) => errors.push(String(e.message || e)));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    page.on('response', (r) => { if (r.status() >= 400 && r.url().startsWith(base)) failedRequests.push(`${r.status()} ${r.url()}`); });
    const url = `${base}/digi-ix-${widget}/`;
    try {
      const response = await page.goto(url, { waitUntil: 'networkidle0', timeout: 90000 });
      if (!response || response.status() !== 200) throw new Error(`la página respondió ${response && response.status()}`);
      // Un widget que sigue oculto tras cargar (elementor-invisible) no puede recibir clics: Elementor lo
      // oculta cuando un ajuste `animation`/`_animation` anuncia una animación de entrada que nunca empieza.
      const invisible = await page.$$eval('.elementor-widget.elementor-invisible', (els) => els.map((e) => e.dataset.widget_type));
      if (invisible.length) throw new Error('widgets ocultos por una animación de entrada que no empieza: ' + invisible.join(', '));
      const note = await checks[widget](page);
      if (errors.length) throw new Error('errores de JavaScript: ' + errors.join(' | '));
      if (failedRequests.length) throw new Error('recursos no encontrados: ' + failedRequests.join(' | '));
      results[widget] = { ok: true, note };
      console.log(`OK     ${widget} · ${note}`);
    } catch (e) {
      failed++;
      results[widget] = { ok: false, error: String(e.message || e), errors, failedRequests };
      console.log(`FALLA  ${widget} · ${e.message}`);
      await page.screenshot({ path: `${process.env.OUT || '/tmp'}/${widget}.png`, fullPage: true }).catch(() => {});
    }
    await page.close();
  }
  await browser.close();
  require('fs').writeFileSync(`${process.env.OUT || '/tmp'}/interaction.json`, JSON.stringify(results, null, 2));
  process.exit(failed ? 1 : 0);
})();
