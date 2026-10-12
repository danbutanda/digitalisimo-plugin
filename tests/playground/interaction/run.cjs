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
  'bdt-marquee': async (page) => {
    await page.waitForSelector('[data-digi-marquee-toggle]', { timeout: 5000 });
    await page.click('[data-digi-marquee-toggle]');
    await page.waitForFunction(() => document.querySelector('[data-digi-marquee-toggle]').getAttribute('aria-pressed') === 'true' && document.querySelector('[data-digi-marquee]').classList.contains('is-paused'), { timeout: 3000 });
    return 'la marquesina se pausa con su botón';
  },
  'bdt-image-compare': async (page) => {
    await page.waitForSelector('.digi-compare__range', { timeout: 5000 });
    const before = await page.$eval('.digi-compare__range', (r) => Number(r.value));
    await page.focus('.digi-compare__range');
    for (let i = 0; i < 5; i++) await page.keyboard.press('ArrowRight');
    await page.waitForFunction((b) => Number(document.querySelector('.digi-compare__range').value) > b && (document.querySelector('[data-digi-compare]').getAttribute('style') || '').includes('--digi-compare-pos'), { timeout: 3000 }, before);
    return 'el comparador de imágenes se mueve con el teclado';
  },
  'bdt-vertical-menu': async (page) => {
    await page.waitForSelector('[data-digi-menu-toggle]', { timeout: 5000 });
    await page.click('[data-digi-menu-toggle]');
    await page.waitForFunction(() => document.querySelector('[data-digi-menu-toggle]').getAttribute('aria-expanded') === 'true', { timeout: 3000 });
    return 'el submenú del menú vertical se despliega con su botón';
  },
  'bdt-scroll-button': async (page) => {
    await page.waitForSelector('.digi-scroll-button__link', { timeout: 5000 });
    // El botón desplaza hasta el elemento cuyo ID lleva en su enlace: la página de prueba lo añade al principio.
    await page.evaluate(() => {
      const id = decodeURIComponent(document.querySelector('.digi-scroll-button__link').hash.slice(1));
      if (id && !document.getElementById(id)) document.body.insertAdjacentHTML('afterbegin', `<div id="${id}"></div>`);
      document.body.style.minHeight = '4000px';
      window.scrollTo(0, 2000);
    });
    await page.waitForFunction(() => window.scrollY > 1000, { timeout: 3000 });
    await page.evaluate(() => document.querySelector('.digi-scroll-button__link').click());
    await page.waitForFunction(() => window.scrollY < 50, { timeout: 5000 });
    return 'el botón de desplazamiento vuelve al inicio';
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
// El reloj del navegador va 12 horas por delante del servidor: el estado y la hora que pintó el servidor ya no
// valen y el script del Horario debe recalcularlos en la zona del negocio (UTC en este caso).
const SHIFT = 12 * 3600 * 1000;
const prepare = {
  'bdt-business-hours': (page) => page.evaluateOnNewDocument((shift) => {
    const Real = Date;
    function Shifted(...args) { return args.length ? new Real(...args) : new Real(Real.now() + shift); }
    Shifted.prototype = Real.prototype; Shifted.now = () => Real.now() + shift; Shifted.UTC = Real.UTC; Shifted.parse = Real.parse;
    window.Date = Shifted;
  }, SHIFT),
};
checks['bdt-toggle'] = async (page) => {
  const visible = (selector) => page.$eval(selector, (el) => el.getClientRects().length > 0);
  if (!(await visible('.digi-accordion__closed-label')) || (await visible('.digi-accordion__open-label'))) throw new Error('cerrado debe verse sólo «Ver más»');
  await page.click('summary.digi-accordion__summary');
  await page.waitForFunction(() => document.querySelector('details.digi-accordion__item').open, { timeout: 3000 });
  if ((await visible('.digi-accordion__closed-label')) || !(await visible('.digi-accordion__open-label'))) throw new Error('abierto debe verse sólo «Ver menos»');
  return 'el toggle cambia a «Ver menos» al abrirse';
};
checks['bdt-business-hours'] = async (page) => {
  const expected = () => {
    const now = new Date(Date.now() + SHIFT);
    const h = now.getUTCHours(), m = now.getUTCMinutes(), total = h * 60 + m;
    return { state: total >= 540 && total < 1020 ? 'open' : 'closed', clock: `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h < 12 ? 'am' : 'pm'}` };
  };
  const before = expected();
  const got = await page.$eval('.digi-hours', (root) => ({
    state: root.querySelector('.digi-hours__status').dataset.state,
    text: root.querySelector('.digi-hours__status').textContent.trim(),
    alt: root.querySelector('template.digi-hours__alt').content.textContent.trim(),
    clock: root.querySelector('.digi-hours__clock').textContent.trim(),
    format: root.dataset.timeFormat,
  }));
  const after = expected();
  if (got.state !== after.state && got.state !== before.state) throw new Error(`estado ${got.state}, se esperaba ${after.state}`);
  if (got.text !== (got.state === 'open' ? 'Abierto ahora' : 'Cerrado ahora') || got.alt !== (got.state === 'open' ? 'Cerrado ahora' : 'Abierto ahora')) throw new Error(`mensajes intercambiados mal: «${got.text}» / «${got.alt}»`);
  if (got.format === 'g:i a' && got.clock !== before.clock && got.clock !== after.clock) throw new Error(`hora ${got.clock}, se esperaba ${after.clock}`);
  return `el horario recalcula en el navegador el estado (${got.state === 'open' ? 'abierto' : 'cerrado'}) y la hora (${got.clock})`;
};
checks['bdt-table'] = async (page) => {
  const visibleRows = () => page.$$eval('.digi-table tbody tr', (rows) => rows.filter((r) => !r.hidden).map((r) => r.cells[0].textContent.trim() + '|' + r.cells[1].textContent.trim()));
  const text = (selector) => page.$eval(selector, (el) => el.textContent.trim());
  let rows = await visibleRows();
  if (rows.length !== 10 || (await text('.digi-table__page')) !== 'Página 1 de 3' || (await text('.digi-table__info')) !== 'Filas 1–10 de 25') throw new Error('la primera página debe mostrar 10 de 25 filas');
  await page.click('.digi-table__pager button:last-child');
  rows = await visibleRows();
  if (rows[0].split('|')[0] !== 'Plan 11' || (await text('.digi-table__page')) !== 'Página 2 de 3') throw new Error('«Siguiente» debe mostrar la segunda página');
  await page.click('.digi-table thead th:nth-child(2) .digi-table__sort');
  const sorted = await page.$$eval('.digi-table tbody tr', (all) => all.map((r) => parseInt(r.cells[1].textContent.replace(/\D/g, ''), 10)));
  if (sorted.some((v, i) => i && v < sorted[i - 1])) throw new Error('el precio debe ordenarse como número');
  if ((await page.$eval('.digi-table thead th:nth-child(2)', (th) => th.getAttribute('aria-sort'))) !== 'ascending') throw new Error('falta aria-sort');
  await page.type('.digi-table__search input', 'plan 2');
  rows = await visibleRows();
  if (rows.length !== 6 || !(await text('.digi-table__info')).includes('(filtradas de 25)')) throw new Error(`la búsqueda debe dejar 6 filas (Plan 20 a 25), deja ${rows.length}`);
  await page.type('.digi-table__search input', 'zzz');
  if ((await visibleRows()).length || (await page.$eval('.digi-table__empty', (el) => el.hidden))) throw new Error('sin coincidencias debe avisarlo');
  return 'la tabla pagina, ordena por número y filtra con su buscador';
};
checks['bdt-hover-video'] = async (page) => {
  const state = () => page.$eval('.digi-video-player', (p) => ({ src: p.querySelector('video').currentSrc || p.querySelector('video').getAttribute('src') || (p.querySelector('video source') || {}).src, label: p.querySelector('video').getAttribute('aria-label'), pressed: [...p.querySelectorAll('.digi-video-player__item')].map((b) => b.getAttribute('aria-pressed')).join(',') }));
  const before = await state();
  if (!String(before.src).includes('recorrido.mp4') || before.pressed !== 'true,false') throw new Error('el primer vídeo debe estar activo al cargar');
  await page.hover('.digi-video-player__item:nth-of-type(1)');
  await page.hover('li:nth-child(2) .digi-video-player__item');
  await page.waitForFunction(() => document.querySelector('.digi-video-player video').getAttribute('aria-label') === 'Instalación', { timeout: 3000 });
  const after = await state();
  if (!String(after.src).includes('instalacion.mp4') || after.pressed !== 'false,true') throw new Error(`el segundo título debe activar su vídeo: ${JSON.stringify(after)}`);
  if (!(await page.$eval('.digi-video-player video', (v) => v.muted))) throw new Error('al pasar el cursor el vídeo debe ir silenciado');
  return 'la lista de vídeos cambia de vídeo al pasar el cursor por su título, silenciado';
};
checks['bdt-video-gallery'] = async (page) => {
  const stage = () => page.$eval('.digi-video-player__stage', (s) => ({ tag: s.firstElementChild.tagName, src: s.firstElementChild.getAttribute('src'), now: (document.querySelector('.digi-video-player__now-title') || {}).textContent }));
  const before = await stage();
  if (before.tag !== 'IFRAME' || !before.src.includes('youtube-nocookie.com/embed/') || before.now !== 'Youtube Video') throw new Error(`al cargar debe verse el primer vídeo de YouTube: ${JSON.stringify(before)}`);
  await page.click('li:nth-child(2) .digi-video-player__item');
  await page.waitForFunction(() => document.querySelector('.digi-video-player__now-title').textContent === 'Vimeo Video', { timeout: 3000 });
  const vimeo = await stage();
  if (vimeo.tag !== 'IFRAME' || !vimeo.src.startsWith('https://player.vimeo.com/video/258349022?dnt=1')) throw new Error(`el segundo botón debe cargar Vimeo: ${JSON.stringify(vimeo)}`);
  await page.click('li:nth-child(5) .digi-video-player__item');
  await page.waitForFunction(() => document.querySelector('.digi-video-player__stage').firstElementChild.tagName === 'VIDEO', { timeout: 3000 });
  const description = await page.$eval('.digi-video-player__now-description', (p) => p.textContent);
  if (!description.includes('Intro')) throw new Error('la descripción debe cambiar con el vídeo');
  return 'la galería cambia entre YouTube, Vimeo y un archivo MP4, y actualiza título y descripción';
};
checks['bdt-wc-carousel'] = async (page) => {
  await page.waitForSelector('.digi-products-carousel [data-digi-carousel-next]', { timeout: 5000 });
  const layout = await page.$eval('.digi-products-carousel', (root) => {
    const items = [...root.querySelectorAll('ul.products > li.product')];
    const viewport = root.querySelector('[data-digi-carousel-track]');
    const cs = getComputedStyle(items[0]); return { count: items.length, rows: new Set(items.map((li) => Math.round(li.getBoundingClientRect().top))).size, visible: items.filter((li) => { const r = li.getBoundingClientRect(); const v = viewport.getBoundingClientRect(); return r.left >= v.left - 1 && r.right <= v.right + 1; }).length, scroll: viewport.scrollLeft, viewport: Math.round(viewport.getBoundingClientRect().width), lefts: items.map((li) => Math.round(li.getBoundingClientRect().left)), widths: items.map((li) => Math.round(li.getBoundingClientRect().width)), flex: cs.flex, pad: cs.padding, margin: cs.margin, gap: getComputedStyle(root.querySelector('ul.products')).gap, vis: getComputedStyle(root).getPropertyValue('--digi-carousel-visible'), ul: getComputedStyle(root.querySelector('ul.products')).display };
  });
  if (layout.count !== 6 || layout.rows !== 1 || layout.visible !== 3) throw new Error(`se esperaban 6 productos en una fila con 3 visibles: ${JSON.stringify(layout)}`);
  await page.click('.digi-products-carousel [data-digi-carousel-next]');
  await page.waitForFunction(() => document.querySelector('.digi-products-carousel [data-digi-carousel-track]').scrollLeft > 0, { timeout: 3000 });
  return 'el carrusel de productos muestra 3 de 6 fichas en una fila y avanza con la flecha';
};
checks['bdt-wc-products'] = async (page) => {
  const rows = () => page.$$eval('.digi-products-table tbody tr', (all) => all.filter((r) => !r.hidden).map((r) => r.querySelector('.digi-products-table__title').textContent.trim()));
  if ((await rows()).length !== 6) throw new Error('la tabla debe listar los 6 productos');
  await page.click('.digi-products-table thead th.digi-products-table__price .digi-table__sort');
  const prices = await page.$$eval('.digi-products-table tbody tr .digi-products-table__price', (all) => all.map((td) => parseFloat(td.textContent.replace(/[^\d.]/g, ''))));
  if (prices.some((v, i) => i && v < prices[i - 1])) throw new Error(`el precio debe ordenarse de menor a mayor: ${prices}`);
  await page.type('.digi-table__search input', 'taza');
  if ((await rows()).join() !== 'Taza IX') throw new Error(`la búsqueda debe dejar sólo la taza: ${await rows()}`);
  const quantity = await page.evaluate(() => {
    const row = [...document.querySelectorAll('.digi-products-table tbody tr')].find((r) => !r.hidden);
    const input = row.querySelector('input.qty');
    input.value = '3';
    input.dispatchEvent(new Event('change', { bubbles: true }));
    const button = row.querySelector('.add_to_cart_button');
    return { data: button.getAttribute('data-quantity'), href: button.getAttribute('href') };
  });
  if (quantity.data !== '3' || !quantity.href.includes('quantity=3')) throw new Error(`la cantidad debe viajar en el botón: ${JSON.stringify(quantity)}`);
  return 'la tabla de productos ordena por precio, filtra y pasa la cantidad al botón de compra';
};
checks['bdt-logo-carousel'] = async (page) => (await checks['bdt-static-carousel'](page)).replace('deslizador propio', 'carrusel de logos');
checks['bdt-slider'] = async (page) => (await checks['bdt-custom-carousel'](page)).replace('carrusel de medios', 'deslizador de diapositivas');
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
    if (prepare[widget]) await prepare[widget](page);
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
