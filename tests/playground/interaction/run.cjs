// Interacciones en navegador real (Chrome sin interfaz) sobre páginas con widgets heredados sin Element Pack.
const puppeteer = require('puppeteer-core');
const base = process.argv[2];
// FACADES=1: el sitio tiene las fachadas aplicadas (Fase 3 del front); sólo cambia lo esperado de los vídeos incrustados.
const FACADES = process.env.FACADES === '1';
// Espera del editor (ms): 180 s por defecto; `EDITOR_MS` la amplía en una máquina lenta (el editor pide unos 300 recursos
// y Playground los sirve con un solo proceso).
const EDITOR_MS = Number(process.env.EDITOR_MS || 180000);
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
      // Sin desplazamiento vertical el widget usa el ancla nativa con `scroll-behavior: smooth` en <html>: el
      // descenso de preparación debe ser instantáneo, porque un clic durante un desplazamiento suave en curso
      // no lo interrumpe en Chrome y la página terminaría abajo.
      window.scrollTo({ top: 2000, behavior: 'instant' });
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
// La galería lleva sus vídeos en el caso: una galería que nunca se editó ya no trae los vídeos de ejemplo de
// Element Pack y no se pinta. El escenario sólo contiene el vídeo actual (la preconexión va antes del reproductor),
// y cambiar de vídeo lo sustituye sin dejar el anterior debajo.
checks['bdt-video-gallery'] = async (page) => {
  const stage = () => page.$eval('.digi-video-player__stage', (s) => ({ children: s.children.length, tag: s.firstElementChild.tagName, src: s.firstElementChild.getAttribute('src'), facade: (s.firstElementChild.querySelector('[data-digi-facade-src]') || { getAttribute: () => '' }).getAttribute('data-digi-facade-src'), now: (document.querySelector('.digi-video-player__now-title') || {}).textContent }));
  const before = await stage();
  if (FACADES) {
    // Con las fachadas el primer vídeo es una portada con su botón: el iframe de YouTube sólo existe tras pulsarlo.
    const frames = await page.$$eval('iframe', (list) => list.length);
    if (before.children !== 1 || before.tag !== 'DIV' || !before.facade.includes('youtube-nocookie.com/embed/aqz-KE-bpKQ') || frames !== 0 || before.now !== 'Youtube Video') throw new Error(`al cargar debe verse la portada del primer vídeo, de YouTube, sin iframe: ${JSON.stringify(before)}`);
  } else if (before.children !== 1 || before.tag !== 'IFRAME' || !before.src.includes('youtube-nocookie.com/embed/aqz-KE-bpKQ') || before.now !== 'Youtube Video') throw new Error(`al cargar debe verse sólo el primer vídeo, de YouTube: ${JSON.stringify(before)}`);
  await page.click('li:nth-child(2) .digi-video-player__item');
  await page.waitForFunction(() => document.querySelector('.digi-video-player__now-title').textContent === 'Vimeo Video', { timeout: 3000 });
  const vimeo = await stage();
  if (vimeo.children !== 1 || vimeo.tag !== 'IFRAME' || !vimeo.src.startsWith('https://player.vimeo.com/video/1084537?dnt=1')) throw new Error(`el segundo botón debe cambiar el vídeo por el de Vimeo: ${JSON.stringify(vimeo)}`);
  await page.click('li:nth-child(3) .digi-video-player__item');
  await page.waitForFunction(() => document.querySelector('.digi-video-player__stage').firstElementChild.tagName === 'VIDEO', { timeout: 3000 });
  const file = await stage();
  if (file.children !== 1 || file.src !== 'https://example.com/intro.mp4') throw new Error(`el tercer botón debe cambiar el vídeo por el archivo: ${JSON.stringify(file)}`);
  const description = await page.$eval('.digi-video-player__now-description', (p) => p.textContent);
  if (!description.includes('Intro')) throw new Error('la descripción debe cambiar con el vídeo');
  return 'la galería cambia entre YouTube, Vimeo y un archivo MP4 sin dejar el vídeo anterior, y actualiza título y descripción';
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
// F7b: slider anidado ligero (teclado sobre los puntos, inert y aria-current), pestañas y acordeón con presentación
// (clase, foco visible, 24 px) y formulario condicional (ocultar, mostrar con required, Tab sin entrar en lo oculto).
checks['f7b-nested-slider'] = async (page) => {
  await page.waitForSelector('.digi-nslider--ready', { timeout: 8000 });
  const state = () => page.evaluate(() => {
    const r = document.querySelector('.digi-nslider'); const t = r.querySelector(':scope>.digi-nslider__track');
    const slides = [...t.children]; const dots = [...r.querySelectorAll(':scope>.digi-nslider__dots>*')];
    return { inert: slides.map((x) => (x.inert ? '-' : 'X')).join(''), current: dots.map((d) => (d.getAttribute('aria-current') === 'true' ? 'X' : '-')).join(''), label: r.getAttribute('aria-roledescription') };
  });
  const s0 = await state();
  if (s0.inert !== 'X--' || s0.current !== 'X--' || s0.label !== 'carrusel') throw new Error(`estado inicial ${JSON.stringify(s0)}`);
  await page.focus('.digi-nslider__dot');
  await page.keyboard.press('ArrowRight');
  await page.waitForFunction(() => document.querySelectorAll('.digi-nslider__dot')[1].getAttribute('aria-current') === 'true', { timeout: 4000 });
  await new Promise((r) => setTimeout(r, 400));
  const s1 = await state();
  if (s1.inert !== '-X-') throw new Error(`tras la flecha ${JSON.stringify(s1)}`);
  const sizes = await page.$$eval('.digi-nslider button', (l) => l.filter((b) => b.offsetParent).map((b) => { const r = b.getBoundingClientRect(); return Math.min(r.width, r.height); }));
  if (!sizes.length || sizes.some((x) => x < 24)) throw new Error(`botones de menos de 24 px: ${sizes}`);
  return 'el slider anidado ligero cambia de diapositiva con las flechas sobre los puntos (aria-current, inert) y sus botones miden 24 px o más';
};
// F8 (frente D, V.10.4–V.10.5): información emergente con teclado y Escape, distintivo en RTL y contador con autoplay.
checks['f8-tooltip'] = async (page) => {
  await page.waitForSelector('[data-digi-tooltip] .digi-tooltip[role="tooltip"]', { timeout: 5000 });
  const st = () => page.evaluate(() => { const t = document.querySelector('[data-digi-tooltip] .digi-tooltip'); const a = document.querySelector('[data-digi-tooltip] a'); const r = t.getBoundingClientRect(); return { open: t.classList.contains('digi-tooltip--open') || (!t.classList.contains('digi-tooltip--shut') && r.width > 2 && r.height > 2), shut: t.classList.contains('digi-tooltip--shut'), w: Math.round(r.width), described: (a.getAttribute('aria-describedby') || '').trim() === t.id && !!t.id, focus: document.activeElement === a, text: t.textContent.trim() }; });
  await page.evaluate(() => document.activeElement && document.activeElement.blur());
  await page.focus('[data-digi-tooltip] a');
  await new Promise((r) => setTimeout(r, 400));
  const s1 = await st();
  if (!s1.described || !s1.focus || s1.shut || s1.w < 3) throw new Error(`con el foco ${JSON.stringify(s1)}`);
  await page.keyboard.press('Escape');
  await new Promise((r) => setTimeout(r, 300));
  const s2 = await st();
  if (!s2.shut || !s2.focus) throw new Error(`tras Escape ${JSON.stringify(s2)}`);
  return `la información emergente se abre con el foco (aria-describedby → role=tooltip, «${s1.text}») y Escape la cierra sin mover el foco`;
};
checks['f8-badge-rtl'] = async (page) => {
  await page.waitForSelector('[data-digi-badge] > .digi-badge', { timeout: 5000 });
  const side = () => page.evaluate(() => { const h = document.querySelector('[data-digi-badge]'); const b = h.querySelector(':scope > .digi-badge'); const hr = h.getBoundingClientRect(); const br = b.getBoundingClientRect(); return { left: Math.round(br.left - hr.left), right: Math.round(hr.right - br.right), hidden: b.getAttribute('aria-hidden'), text: b.textContent.trim() }; });
  const ltr = await side();
  await page.evaluate(() => { document.documentElement.dir = 'rtl'; });
  await new Promise((r) => setTimeout(r, 200));
  const rtl = await side();
  if (!(ltr.left < ltr.right) || !(rtl.right < rtl.left) || ltr.hidden !== null || ltr.text !== 'Nuevo') throw new Error(`LTR ${JSON.stringify(ltr)} · RTL ${JSON.stringify(rtl)}`);
  return 'el distintivo «inicio-arriba» va a la izquierda en LTR y a la derecha en RTL (propiedades lógicas), con su texto legible';
};
checks['f8-nested-counter'] = async (page) => {
  await page.waitForSelector('.digi-nslider--ready .digi-nslider__count:not([hidden])', { timeout: 8000 });
  const count = () => page.$eval('.digi-nslider__count', (c) => c.textContent.replace(/\s+/g, ' ').trim());
  const c0 = await count();
  await page.waitForFunction((c) => document.querySelector('.digi-nslider__count').textContent.replace(/\s+/g, ' ').trim() !== c, { timeout: 6000 }, c0);
  const c1 = await count();
  // Foco en el punto actual (las diapositivas ocultas son `inert` y no lo aceptan): el foco dentro del slider lo pausa.
  await page.focus('.digi-nslider__dot[aria-current="true"]');
  await new Promise((r) => setTimeout(r, 400));
  const c2 = await count();
  await new Promise((r) => setTimeout(r, 3500));
  const c3 = await count();
  // El autoplay (1,5 s) puede haber avanzado antes de la primera lectura: la carga espera a que la red quede quieta y las
  // fuentes de terceros que el arnés corta tardan lo que tardan. Lo que se comprueba es el formato, el avance y la pausa.
  if (!/^[1-3] \/ 3$/.test(c0) || c1 === c0 || c3 !== c2) throw new Error(`contador ${JSON.stringify({ c0, c1, c2, c3 })}`);
  return `el contador sigue al autoplay («${c0}» → «${c1}») y se detiene con el foco dentro (WCAG 2.2.2: «${c2}» tras 3,5 s)`;
};
const presentation = (key, wrapper, title, cls) => async (page) => {
  const got = await page.$eval(wrapper, (el, c) => el.classList.contains(c), cls).catch(() => false);
  if (!got) throw new Error(`el envoltorio no lleva ${cls}`);
  const heights = await page.$$eval(title, (l) => l.map((t) => Math.round(t.getBoundingClientRect().height)));
  if (!heights.length || heights.some((h) => h < 24)) throw new Error(`títulos de menos de 24 px: ${heights}`);
  await page.focus(title);
  await page.keyboard.press('Tab');
  await page.keyboard.down('Shift'); await page.keyboard.press('Tab'); await page.keyboard.up('Shift');
  const ring = await page.evaluate(() => { const a = document.activeElement; const cs = getComputedStyle(a); return { style: cs.outlineStyle, width: parseFloat(cs.outlineWidth), same: cs.outlineColor === cs.color }; });
  if (ring.style === 'none' || ring.width < 2 || !ring.same) throw new Error(`foco visible ${JSON.stringify(ring)}`);
  return `${key}: clase de presentación, títulos de 24 px o más y foco visible con currentColor`;
};
checks['f7b-tabs-pills'] = async (page) => {
  const note = await presentation('pestañas «pills»', '.elementor-widget-n-tabs', '.e-n-tab-title', 'digi-nested-pills')(page);
  await page.focus('.e-n-tab-title');
  await page.keyboard.press('ArrowRight');
  await page.keyboard.press('Enter');
  await page.waitForFunction(() => document.querySelectorAll('.e-n-tab-title')[1].getAttribute('aria-selected') === 'true', { timeout: 3000 });
  return note + '; la flecha y Enter activan la segunda';
};
checks['f7b-accordion-boxed'] = async (page) => {
  const note = await presentation('acordeón «boxed»', '.elementor-widget-n-accordion', '.e-n-accordion-item-title', 'digi-nested-boxed')(page);
  await page.click('.e-n-accordion-item:nth-of-type(2) > .e-n-accordion-item-title');
  await page.waitForFunction(() => document.querySelectorAll('.e-n-accordion-item')[1].open, { timeout: 3000 });
  return note + '; el segundo elemento se abre al pulsar';
};
checks['f7b-form-conditions'] = async (page) => {
  const st = () => page.$eval('.elementor-field-group-rfc', (g) => ({ display: getComputedStyle(g).display, disabled: g.querySelector('input').disabled, required: g.querySelector('input').required }));
  const a = await st();
  if (a.display !== 'none' || !a.disabled) throw new Error(`RFC debería empezar oculto: ${JSON.stringify(a)}`);
  await page.click('#form-field-tipo-1');
  const b = await st();
  if (b.display === 'none' || b.disabled || !b.required) throw new Error(`«Empresa» debería mostrar RFC obligatorio: ${JSON.stringify(b)}`);
  await page.click('#form-field-tipo-0');
  const c = await st();
  if (c.display !== 'none' || !c.disabled || c.required) throw new Error(`«Persona» debería ocultarlo: ${JSON.stringify(c)}`);
  return 'el formulario condicional oculta y muestra el RFC (obligatorio sólo visible) sin recargar';
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
    // En una máquina lenta (`EDITOR_MS`), las peticiones REST del núcleo pueden pasar su tope de 40 s (axios,
    // ECONNABORTED): se anotan aparte y no cuentan como error de JavaScript. Cualquier otro rechazo sí cuenta.
    const slowRest = [];
    let slowUsed = 0;
    if (process.env.EDITOR_MS) {
      await page.evaluateOnNewDocument(() => window.addEventListener('unhandledrejection', (e) => { const r = e.reason || {}; if (r.name === 'AxiosError' && r.code === 'ECONNABORTED') console.log(`digi-slow-rest ${r.config && r.config.url}`); }));
      page.on('console', (m) => { if (m.type() === 'log' && m.text().startsWith('digi-slow-rest ')) slowRest.push(m.text().slice(15)); });
    }
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
      await page.goto(`${base}/wp-admin/post.php?post=${entry.id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: EDITOR_MS });
      await page.waitForSelector('#elementor-panel', { timeout: EDITOR_MS });
      const frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: EDITOR_MS })).contentFrame();
      await frame.waitForSelector('.elementor-widget', { timeout: EDITOR_MS });
      const core = await page.evaluate(() => (window.elementorCommon && window.elementorCommon.config && window.elementorCommon.config.version) || (window.ElementorConfig && window.ElementorConfig.version) || '');
      if (failedRequests.length) throw new Error('recursos no encontrados: ' + failedRequests.join(' | '));
      // Cada rechazo por tiempo de una petición REST anotado deja un «AxiosError» en `pageerror`: sólo esos se descuentan.
      for (let k; slowUsed < slowRest.length && (k = errors.indexOf('AxiosError')) >= 0; slowUsed++) errors.splice(k, 1);
      if (slowRest.length) console.log(`NOTA   editor · peticiones REST del núcleo por encima de 40 s (máquina lenta): ${slowRest.join(', ')}`);
      if (errors.length) throw new Error('errores de JavaScript: ' + errors.join(' | '));
      // F7b: en el panel, «diapositivas» encuentra el slider anidado ligero y no el Carrusel de PRO (palabras clave propias).
      const found = await page.evaluate(async () => {
        const input = document.querySelector('#elementor-panel-elements-search-input');
        if (!input) return null;
        const search = async (q) => { input.value = q; input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true })); await new Promise((r) => setTimeout(r, 1200)); return [...document.querySelectorAll('#elementor-panel-elements .elementor-element .title')].map((t) => t.textContent.trim()); };
        return { slides: await search('diapositivas'), carousel: await search('carousel') };
      });
      if (!found || !found.slides.some((t) => /Slider anidado ligero/.test(t))) throw new Error(`la búsqueda «diapositivas» del panel no encuentra el slider anidado ligero: ${JSON.stringify(found)}`);
      results.editorF7b = { ok: true, note: `panel: «diapositivas» → ${found.slides.join(', ')}; «carousel» → ${found.carousel.join(', ')}` };
      // Programa «sin widgets de PRO» (C0, C1, C2, C4, C5): cada widget de PRO ya sustituido sigue registrado pero fuera del panel, y
      // su búsqueda ofrece el widget propio; ningún widget de PRO sustituido aparece con su título.
      // C4: `doc` dice en qué editor se busca (por defecto esta página; `single`/`archive`, las plantillas del Theme
      // Builder de setup.php, donde existen las categorías «Individual» y «Archivo»); `ownHidden`, que el propio
      // tampoco va al panel (el buscador, como en PRO); `proOptional`, que PRO sólo lo registra con un requisito
      // (las migas, con Yoast).
      const SUBSTITUTED = [
        { pro: 'blockquote', own: 'digitalisimo-blockquote', title: 'Cita', query: 'cita' },
        { pro: 'nested-carousel', own: 'digitalisimo-nested-carousel', title: 'Carrusel anidado', query: 'carrusel' },
        { pro: 'media-carousel', own: 'digitalisimo-media-carousel', title: 'Carrusel multimedia', query: 'multimedia' },
        { pro: 'testimonial-carousel', own: 'digitalisimo-testimonial-carousel', title: 'Carrusel de testimonios', query: 'testimonios' },
        { pro: 'reviews', own: 'digitalisimo-reviews', title: 'Reseñas', query: 'reseñas' },
        { pro: 'slides', own: 'digitalisimo-slides', title: 'Diapositivas', query: 'diapositivas' },
        // C2 (contenido, docs/pro/c2.md).
        { pro: 'animated-headline', own: 'digitalisimo-animated-headline', title: 'Titular animado', query: 'titular' },
        { pro: 'code-highlight', own: 'digitalisimo-code-highlight', title: 'Código resaltado', query: 'resaltado' },
        { pro: 'table-of-contents', own: 'digitalisimo-table-of-contents', title: 'Tabla de contenido', query: 'tabla de contenido' },
        { pro: 'share-buttons', own: 'digitalisimo-share-buttons', title: 'Botones para compartir', query: 'compartir' },
        { pro: 'call-to-action', own: 'digitalisimo-call-to-action', title: 'Llamada a la acción con imagen', query: 'acción con imagen' },
        { pro: 'flip-box', own: 'digitalisimo-flip-box', title: 'Tarjeta giratoria', query: 'giratoria' },
        { pro: 'price-list', own: 'digitalisimo-price-list', title: 'Lista de precios', query: 'lista de precios' },
        { pro: 'price-table', own: 'digitalisimo-price-table', title: 'Tabla de precios', query: 'tabla de precios' },
        { pro: 'gallery', own: 'digitalisimo-gallery', title: 'Galería avanzada', query: 'galería avanzada' },
        { pro: 'hotspot', own: 'digitalisimo-hotspot', title: 'Puntos de interés', query: 'puntos de interés' },
        { pro: 'lottie', own: 'digitalisimo-lottie', title: 'Animación Lottie', query: 'lottie' },
        { pro: 'video-playlist', own: 'digitalisimo-video-playlist', title: 'Lista de videos', query: 'lista de videos' },
        { pro: 'countdown', own: 'digitalisimo-countdown', title: 'Cuenta regresiva', query: 'regresiva' },
        { pro: 'progress-tracker', own: 'digitalisimo-progress-tracker', title: 'Indicador de progreso', query: 'indicador de progreso' },
        // C4 (Theme Builder y elementos de tema, docs/pro/c4.md).
        { pro: 'theme-site-logo', own: 'digitalisimo-site-logo', title: 'Logotipo del sitio', query: 'logotipo' },
        { pro: 'theme-site-title', own: 'digitalisimo-site-title', title: 'Título del sitio', query: 'título del sitio' },
        { pro: 'theme-page-title', own: 'digitalisimo-page-title', title: 'Título de la página', query: 'título de la página' },
        { pro: 'theme-post-title', own: 'digitalisimo-post-title', title: 'Título de la entrada', query: 'título de la entrada', doc: 'single' },
        { pro: 'theme-archive-title', own: 'digitalisimo-archive-title', title: 'Título del archivo', query: 'título del archivo', doc: 'archive' },
        { pro: 'theme-post-excerpt', own: 'digitalisimo-post-excerpt', title: 'Extracto', query: 'extracto', doc: 'single' },
        { pro: 'theme-post-content', own: 'digitalisimo-post-content', title: 'Contenido de la entrada', query: 'contenido de la entrada', doc: 'single' },
        { pro: 'theme-post-featured-image', own: 'digitalisimo-featured-image', title: 'Imagen destacada', query: 'imagen destacada', doc: 'single' },
        { pro: 'post-info', own: 'digitalisimo-post-info', title: 'Información de la entrada', query: 'información de la entrada' },
        { pro: 'post-navigation', own: 'digitalisimo-post-navigation', title: 'Navegación entre entradas', query: 'navegación entre' },
        { pro: 'author-box', own: 'digitalisimo-author-box', title: 'Caja de autor', query: 'caja de autor' },
        { pro: 'post-comments', own: 'digitalisimo-post-comments', title: 'Comentarios', query: 'comentarios' },
        { pro: 'search-form', own: 'digitalisimo-search-form', title: 'Formulario de búsqueda', query: 'formulario de búsqueda', ownHidden: true },
        { pro: 'sitemap', own: 'digitalisimo-sitemap', title: 'Mapa del sitio', query: 'mapa del sitio' },
        { pro: 'breadcrumbs', own: 'digitalisimo-yoast-breadcrumbs', title: 'Migas (Yoast o DIGITALÍSIMO SEO)', query: 'migas', proOptional: true },
        // C3 (navegación y búsqueda, docs/pro/c3.md).
        { pro: 'nav-menu', own: 'digitalisimo-nav-menu', title: 'Menú de WordPress', query: 'menú de wordpress' },
        { pro: 'mega-menu', own: 'digitalisimo-mega-menu', title: 'Menú con submenús de contenido', query: 'submenús de contenido' },
        { pro: 'off-canvas', own: 'digitalisimo-nested-off-canvas', title: 'Panel fuera de lienzo', query: 'fuera de lienzo' },
        { pro: 'search', own: 'digitalisimo-search', title: 'Búsqueda', query: 'búsqueda' },
        // C5 (entradas y bucles, docs/pro/c5.md).
        { pro: 'posts', own: 'digitalisimo-posts', title: 'Entradas', query: 'entradas' },
        { pro: 'archive-posts', own: 'digitalisimo-archive-posts', title: 'Entradas del archivo', query: 'entradas del archivo', doc: 'archive' },
        { pro: 'portfolio', own: 'digitalisimo-portfolio', title: 'Portafolio', query: 'portafolio' },
        { pro: 'loop-grid', own: 'digitalisimo-loop-grid', title: 'Cuadrícula de bucle', query: 'bucle' },
        { pro: 'loop-carousel', own: 'digitalisimo-loop-carousel', title: 'Carrusel de bucle', query: 'carrusel de bucle' },
        { pro: 'taxonomy-filter', own: 'digitalisimo-taxonomy-filter', title: 'Filtro de taxonomías', query: 'filtro' },
      ];
      const inspect = (list) => page.evaluate(async (items) => {
        const cache = (window.elementor && window.elementor.widgetsCache) || {};
        const input = document.querySelector('#elementor-panel-elements-search-input');
        const search = async (q) => { input.value = q; input.dispatchEvent(new Event('input', { bubbles: true })); input.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true })); await new Promise((r) => setTimeout(r, 1200)); return [...document.querySelectorAll('#elementor-panel-elements .elementor-element .title')].map((t) => t.textContent.trim()); };
        const out = [];
        for (const w of items) {
          out.push({ ...w, registered: !!cache[w.pro], proTitle: cache[w.pro] ? cache[w.pro].title : '', shown: cache[w.pro] ? cache[w.pro].show_in_panel : null, ownRegistered: !!cache[w.own], ownShown: cache[w.own] ? cache[w.own].show_in_panel : null, found: input ? await search(w.query) : null });
        }
        return out;
      }, list);
      const pro = await inspect(SUBSTITUTED.filter((w) => !w.doc));
      // Las plantillas del Theme Builder de setup.php (templates.json en la carpeta de resultados).
      let templates = {};
      try { templates = JSON.parse(require('fs').readFileSync(`${process.env.OUT || '/tmp'}/templates.json`, 'utf8')); } catch (e) { templates = {}; }
      for (const doc of [...new Set(SUBSTITUTED.filter((w) => w.doc).map((w) => w.doc))]) {
        if (!templates[doc]) throw new Error(`setup.php no creó la plantilla «${doc}» del Theme Builder (templates.json)`);
        await page.goto(`${base}/wp-admin/post.php?post=${templates[doc]}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: EDITOR_MS });
        await page.waitForSelector('#elementor-panel-elements-search-input', { timeout: EDITOR_MS });
        const tplFrame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: EDITOR_MS })).contentFrame();
        await tplFrame.waitForSelector('.elementor-widget', { timeout: EDITOR_MS });
        pro.push(...(await inspect(SUBSTITUTED.filter((w) => w.doc === doc))));
      }
      const bad = pro.filter((w) => {
        if (!w.ownRegistered) return true;
        if (w.registered ? w.shown !== false : !w.proOptional) return true;
        if (w.proTitle && (w.found || []).includes(w.proTitle)) return true;
        if (w.ownHidden) return w.ownShown !== false || (w.found || []).includes(w.title);
        return !(w.found || []).includes(w.title);
      });
      if (bad.length) throw new Error(`cada widget de PRO sustituido debe seguir registrado y fuera del panel, con el propio en su lugar: ${JSON.stringify(bad)}`);
      for (let k; slowUsed < slowRest.length && (k = errors.indexOf('AxiosError')) >= 0; slowUsed++) errors.splice(k, 1);
      if (errors.length) throw new Error('errores de JavaScript en el editor de las plantillas: ' + errors.join(' | '));
      if (failedRequests.length) throw new Error('recursos no encontrados en el editor de las plantillas: ' + failedRequests.join(' | '));
      results.editorPro = { ok: true, note: `${pro.filter((w) => w.registered).length} widgets de PRO registrados y ocultos${pro.some((w) => !w.registered) ? ` (sin registrar: ${pro.filter((w) => !w.registered).map((w) => w.pro).join(', ')})` : ''}; ${pro.map((w) => `«${w.query}»${w.doc ? ` (${w.doc})` : ''} → ${w.ownHidden ? `${w.title} fuera del panel, como PRO` : w.title}`).join('; ')}` };
      console.log(`OK     editor-pro · ${results.editorPro.note}`);
      console.log(`OK     editor-f7b · ${results.editorF7b.note}`);
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
  // De dónde salió WooCommerce (tests/playground/woocommerce-pin.sh): la copia fijada de reference/ o wordpress.org.
  let woocommerce = null;
  try { woocommerce = JSON.parse(require('fs').readFileSync(`${process.env.OUT || '/tmp'}/woocommerce.json`, 'utf8')); } catch (e) { /* sin datos */ }
  require('fs').writeFileSync(`${process.env.OUT || '/tmp'}/interaction.json`, JSON.stringify({ woocommerce, ...results }, null, 2));
  process.exit(failed ? 1 : 0);
})();
