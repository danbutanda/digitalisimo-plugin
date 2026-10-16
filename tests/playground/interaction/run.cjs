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
// Widgets fusionados y sus alias: el contenido guardado con el esquema de antes se pinta con el widget nuevo y conserva sus clases.
const classes = (page, selector) => page.$eval(selector, (el) => el.className);
checks['digitalisimo-logos'] = async (page) => {
  if (!(await classes(page, '.digi-logos')).includes('digi-logos--carousel') || (await page.$('.digi-brand-carousel'))) throw new Error('el widget nuevo imprime sólo sus clases en carrusel');
  if ((await page.$$('.digi-logos__link-text')).length !== 1) throw new Error('el texto del enlace va dentro de la tarjeta enlazada');
  return (await checks['bdt-static-carousel'](page)).replace('el deslizador propio', 'Logotipos en carrusel');
};
checks['digitalisimo-brand-carousel'] = async (page) => {
  const root = await classes(page, '.digi-logos');
  if (!root.includes('digi-brand-carousel') || !(await page.$('a.digi-logos__card.digi-brand-carousel__card')) || !(await page.$('.digi-brand-carousel__website'))) throw new Error(`el alias no conserva sus clases: ${root}`);
  return (await checks['bdt-static-carousel'](page)).replace('el deslizador propio', 'el carrusel de marcas (alias) con sus clases de antes');
};
checks['digitalisimo-brand-grid'] = async (page) => {
  const items = await page.$$('details.digi-logos__details.digi-brand-grid__details');
  if (items.length !== 2) throw new Error('la cuadrícula de marcas al abrir debe tener dos detalles con sus clases de antes');
  await (await items[0].$('summary')).click();
  await page.waitForFunction(() => document.querySelector('details.digi-brand-grid__details').open, { timeout: 3000 });
  if (!(await page.$('details[open] a.digi-logos__link.digi-brand-grid__link'))) throw new Error('al abrir debe verse el enlace de texto');
  return 'la cuadrícula de marcas (alias) abre sus detalles sin JavaScript y conserva sus clases';
};
checks['digitalisimo-logo-grid'] = async (page) => {
  const caption = await page.$('.digi-logos__content--tooltip.digi-logo-grid__caption--tooltip');
  if (!caption || !(await classes(page, '.digi-logos')).includes('digi-logos--border')) throw new Error('el pie con enlace debe ser un globo y el diseño de bordes conservarse');
  if ((await page.$$('.digi-logo-grid__caption')).length !== 1) throw new Error('el logotipo sin la opción no muestra pie');
  // Chrome sin interfaz no declara un dispositivo que apunte (hover:none) y la hoja deja el globo visible, como la de antes;
  // con un puntero el globo queda oculto hasta apuntar.
  const hoverable = await page.evaluate(() => matchMedia('(hover: hover)').matches);
  const opacity = await caption.evaluate((el) => getComputedStyle(el).opacity);
  if (!hoverable) {
    if (opacity !== '1') throw new Error(`sin dispositivo que apunte el globo debe verse (opacidad ${opacity})`);
    return 'la cuadrícula de logotipos (alias) muestra el globo estático sin puntero y conserva sus clases';
  }
  if (opacity !== '0') throw new Error(`el globo debe estar oculto hasta apuntar (opacidad ${opacity})`);
  await page.hover('a.digi-logos__card');
  await page.waitForFunction(() => getComputedStyle(document.querySelector('.digi-logos__content--tooltip')).opacity === '1', { timeout: 3000 });
  return 'la cuadrícula de logotipos (alias) muestra el globo al apuntar y conserva sus clases';
};
checks['digitalisimo-creative-button'] = async (page) => {
  const got = await page.$eval('a.digi-advanced-button', (el) => ({ cls: el.className, padding: parseFloat(getComputedStyle(el).paddingLeft), font: parseFloat(getComputedStyle(el).fontSize), border: getComputedStyle(el).borderTopWidth, icon: !!el.querySelector('.digi-advanced-button__icon svg, .digi-advanced-button__icon i') }));
  if (!got.cls.includes('digi-creative-button') || !got.cls.includes('digi-advanced-button--effect-g') || !got.icon) throw new Error(`el botón creativo debe ser un botón con elevación y su clase de antes: ${JSON.stringify(got)}`);
  if (Math.abs(got.padding - 1.3 * got.font) > 1 || got.border !== '2px') throw new Error(`el aspecto de antes (relleno 1.3em, borde 2px) debe conservarse: ${JSON.stringify(got)}`);
  return 'el botón creativo (alias) conserva su aspecto, su efecto y su clase';
};
checks['digitalisimo-advanced-button'] = async (page) => {
  const got = await page.$eval('a.digi-advanced-button', (el) => ({ cls: el.className, bg: getComputedStyle(el).backgroundColor }));
  if (!got.cls.includes('digi-advanced-button--effect-outline') || got.cls.includes('digi-creative-button') || got.bg !== 'rgba(0, 0, 0, 0)') throw new Error(`el efecto contorno debe aplicarse sin clases ajenas: ${JSON.stringify(got)}`);
  return 'el botón usa el efecto «Contorno» heredado del creativo';
};
checks['digitalisimo-icon-mobile-menu'] = async (page) => {
  const got = await page.$eval('nav.digi-icon-nav', (el) => ({ cls: el.className, links: el.querySelectorAll('a.digi-icon-nav__link.digi-icon-mobile-menu__item').length, radius: getComputedStyle(el.querySelector('a.digi-icon-nav__link')).borderRadius, label: el.querySelector('a').getAttribute('aria-label') }));
  if (!got.cls.includes('digi-icon-nav--bar') || !got.cls.includes('digi-icon-mobile-menu--style-4') || got.links !== 2 || got.radius !== '50%' || got.label !== 'Inicio') throw new Error(`el menú móvil debe ser la barra circular con sus clases: ${JSON.stringify(got)}`);
  return 'el menú móvil (alias) se pinta como barra horizontal circular con sus clases de antes';
};
checks['digitalisimo-icon-nav'] = async (page) => {
  const got = await page.$eval('nav.digi-icon-nav', (el) => ({ cls: el.className, rail: !!el.querySelector('.digi-icon-nav__rail'), border: getComputedStyle(el.querySelector('a.digi-icon-nav__link')).borderTopWidth, texts: el.querySelectorAll('.digi-icon-nav__text').length }));
  if (!got.cls.includes('digi-icon-nav--style-2') || got.rail || got.border !== '1px' || got.texts !== 2 || got.cls.includes('digi-icon-mobile-menu')) throw new Error(`la barra horizontal con bordes debe pintarse sin raíl ni clases ajenas: ${JSON.stringify(got)}`);
  return 'la navegación con iconos en barra horizontal con bordes';
};
checks['digitalisimo-content-switcher'] = async (page) => {
  const got = await page.$eval('.digi-fancy-tabs', (el) => ({ cls: el.className, selected: el.dataset.digiSelected, tabs: el.querySelectorAll('.digi-fancy-tabs__tab.digi-content-switcher__tab').length, active: el.querySelector('[aria-selected="true"]').textContent.trim() }));
  if (!got.cls.includes('digi-fancy-tabs--switcher') || !got.cls.includes('digi-content-switcher') || got.tabs !== 2 || got.selected !== '1' || got.active !== 'Anual') throw new Error(`el alternador debe ser pestañas en línea con «Anual» activa: ${JSON.stringify(got)}`);
  await page.click('[role="tab"]');
  await page.waitForFunction(() => document.querySelectorAll('[role="tab"]')[0].getAttribute('aria-selected') === 'true' && !document.querySelectorAll('[role="tabpanel"]')[0].hidden, { timeout: 3000 });
  return 'el alternador (alias) arranca en su opción activa y cambia al pulsar';
};
checks['digitalisimo-fancy-tabs'] = async (page) => {
  const got = await page.$eval('.digi-fancy-tabs', (el) => ({ cls: el.className, strong: el.querySelector('.digi-fancy-tabs__label strong') && getComputedStyle(el.querySelector('.digi-fancy-tabs__label strong')).fontWeight }));
  if (!got.cls.includes('digi-fancy-tabs--switcher') || got.cls.includes('digi-content-switcher')) throw new Error(`las pestañas en línea no llevan clases ajenas: ${JSON.stringify(got)}`);
  await page.click('[role="tab"]:nth-child(2)');
  await page.waitForFunction(() => { const p = document.querySelectorAll('[role="tabpanel"]')[1]; return p && !p.hidden && p.querySelector('.elementor-widget-heading'); }, { timeout: 4000 });
  return 'las pestañas en línea muestran una plantilla publicada en su panel';
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
  // El editor de Elementor carga como administrador, con su panel y la vista previa de la página, sin errores de
  // JavaScript ni respuestas de error (con CORE=bundled, sus archivos vienen de la carpeta del plugin).
  {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    const errors = [];
    const failedRequests = [];
    page.on('pageerror', (e) => errors.push(String(e.message || e)));
    page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
    const bodies = [];
    page.on('response', (r) => {
      if (r.status() >= 400 && r.url().startsWith(base)) {
        failedRequests.push(`${r.status()} ${r.url()}`);
        r.text().then((text) => bodies.push(`${r.url()} → ${text.replace(/\s+/g, ' ').slice(0, 400)}`)).catch(() => {});
      }
    });
    try {
      await page.goto(`${base}/wp-login.php`, { waitUntil: 'networkidle0', timeout: 90000 });
      await page.type('#user_login', 'admin');
      await page.type('#user_pass', 'password');
      await Promise.all([page.waitForNavigation({ timeout: 90000 }), page.click('#wp-submit')]);
      if (process.env.CORE === 'bundled') {
        // Una dirección guardada con el plugin Elementor (ya no instalado) se redirige al núcleo incluido.
        const old = await page.evaluate(async (url) => { const r = await fetch(url); return `${r.status} ${r.url}`; }, `${base}/wp-content/plugins/elementor/assets/images/placeholder.png`);
        if (!old.startsWith('200 ') || !old.includes('/digitalisimo-elements/elementor/assets/images/placeholder.png')) throw new Error(`la ruta antigua de Elementor debe llevar al núcleo incluido: ${old}`);
      }
      const [entry] = await page.evaluate(async (url) => (await fetch(url)).json(), `${base}/wp-json/wp/v2/pages?slug=digi-ix-bdt-accordion`);
      await page.goto(`${base}/wp-admin/post.php?post=${entry.id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 180000 });
      await page.waitForSelector('#elementor-panel', { timeout: 180000 });
      const frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: 180000 })).contentFrame();
      await frame.waitForSelector('.elementor-widget', { timeout: 180000 });
      const core = await page.evaluate(() => (window.elementorCommon && window.elementorCommon.config && window.elementorCommon.config.version) || (window.ElementorConfig && window.ElementorConfig.version) || '');
      if (failedRequests.length) throw new Error('recursos no encontrados: ' + failedRequests.join(' | '));
      if (errors.length) throw new Error('errores de JavaScript: ' + errors.join(' | '));
      results.editor = { ok: true, note: `el editor de Elementor ${core} carga con panel y vista previa` };
      console.log(`OK     editor · ${results.editor.note}`);
    } catch (e) {
      failed++;
      const preview = await page.$eval('#elementor-preview-iframe', (f) => f.src).catch(() => '');
      const probe = preview ? await page.evaluate(async (url) => { const r = await fetch(url, { credentials: 'include' }); return `${r.status} ${(await r.text()).replace(/\s+/g, ' ').slice(0, 600)}`; }, preview).catch((err) => String(err)) : '';
      results.editor = { ok: false, error: String(e.message || e), errors, failedRequests, bodies, preview, probe };
      console.log(`FALLA  editor · ${e.message}`);
      await page.screenshot({ path: `${process.env.OUT || '/tmp'}/editor.png` }).catch(() => {});
    }
    await page.close();
  }
  await browser.close();
  require('fs').writeFileSync(`${process.env.OUT || '/tmp'}/interaction.json`, JSON.stringify(results, null, 2));
  process.exit(failed ? 1 : 0);
})();
