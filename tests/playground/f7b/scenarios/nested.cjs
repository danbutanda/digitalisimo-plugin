// B11 (slider anidado ligero y presentación de pestañas y acordeón) de f7b.sh, sobre la página real: lo mismo que
// tests/elements-f7b-nested-slider-chrome.mjs hace con el fixture (teclado, autoplay y sus pausas, movimiento reducido,
// tres anchos, CLS, árbol de accesibilidad), más la caché de elementos, la presentación y el editor real.
'use strict';

module.exports = (api) => {
  const { check, pending, note, control, request, open, ids, url, login, openEditor, selectElement, editorSave, sleep, shot, normalize, WIDTHS, QUICK } = api;
  const IDS = ['w1slide', 'w2fade1', 'w3scale'];
  const state = (page, id) => page.evaluate((wid) => {
    const r = document.querySelector(`[data-id="${wid}"] .digi-nslider`);
    if (!r) return null;
    const t = r.querySelector(':scope>.digi-nslider__track');
    const slides = [...t.children].filter((x) => x.classList.contains('e-con') || x.classList.contains('elementor-element'));
    const dotsBox = r.querySelector(':scope>.digi-nslider__dots');
    const dots = dotsBox ? [...dotsBox.children] : [];
    const rt = t.getBoundingClientRect();
    return {
      inert: slides.map((s) => (s.inert ? '-' : 'X')).join(''),
      current: dots.map((d) => (d.getAttribute('aria-current') === 'true' ? 'X' : '-')).join(''),
      visible: slides.map((s) => { const b = s.getBoundingClientRect(); const cs = getComputedStyle(s); return Math.abs(b.left - rt.left) < 2 && cs.visibility !== 'hidden' && +cs.opacity > 0.5 ? 'X' : '-'; }).join(''),
      live: t.getAttribute('aria-live'),
      pause: (r.querySelector(':scope>.digi-nslider__pause') || { getAttribute: () => null }).getAttribute('aria-label'),
      height: Math.round(r.getBoundingClientRect().height),
      heights: slides.map((s) => s.offsetHeight),
    };
  }, id);
  const settle = (page) => page.waitForFunction(() => [...document.querySelectorAll('.digi-nslider__track')].every((t) => { const v = t.scrollLeft; const same = t.__last === v; t.__last = v; return same; }), { polling: 150, timeout: 5000 }).catch(() => {});
  const shift = (page) => page.evaluateOnNewDocument(() => { window.__shift = 0; new PerformanceObserver((list) => { for (const e of list.getEntries()) if (!e.hadRecentInput) window.__shift += e.value; }).observe({ type: 'layout-shift', buffered: true }); });

  async function B11(s) {
    const m = await ids(true);
    const page = await url('b11-slider');
    const html = (await request('GET', page)).text;
    const css = html.match(/<link[^>]*id=['"]digitalisimo-f7b-nested-slider-css['"][^>]*>/);
    const js = html.match(/<script[^>]*id="digitalisimo-f7b-nested-slider-js"[^>]*>/);
    check(s, 'B11.1a', 'hoja y motor propios (`nested-slider.css`/`.js` con `defer`), sin Swiper ni la hoja del Carrusel de PRO', !!css && !!js && /\bdefer\b/.test(js[0]) && !/swiper/i.test(html.match(/<(?:script|link)[^>]*>/g).join(' ')), JSON.stringify({ css: !!css, js: js && js[0] }));
    const sec = (html.match(/<section[^>]*class="digi-nslider[^"]*"[^>]*>/g) || []);
    check(s, 'B11.1b', 'marcado: `section` con `aria-roledescription` y nombre, diapositivas «N de M»', sec.length === 3 && sec.every((x) => /aria-roledescription="carrusel"/.test(x) && /aria-label="[^"]+"/.test(x)) && (html.match(/aria-label="1 de 3"/g) || []).length >= 3, sec.join(' ').slice(0, 400));
    // Sin JavaScript: legible y desplazable, nada inerte.
    {
      const v = await open(page, { js: false, wait: 'load' });
      const info = await v.page.evaluate(() => { const t = document.querySelector('[data-id="w1slide"] .digi-nslider__track'); const cs = getComputedStyle(t); return { snap: cs.scrollSnapType, overflow: cs.overflowX, scrollable: t.scrollWidth > t.clientWidth, inert: document.querySelectorAll('.digi-nslider [inert]').length, pause: getComputedStyle(document.querySelector('[data-id="w2fade1"] .digi-nslider__pause')).display }; });
      check(s, 'B11.2', 'sin JavaScript: pista con scroll-snap que se desplaza, nada inerte, sin botón de pausa', info.snap === 'x mandatory' && info.scrollable && info.inert === 0 && info.pause === 'none', JSON.stringify(info));
      await v.close();
    }
    for (const width of WIDTHS) {
      const v = await open(page, { width, before: async (pg) => { await pg.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]); await shift(pg); } });
      const pg = v.page;
      await pg.waitForFunction(() => document.querySelectorAll('.digi-nslider--ready').length === 3, { timeout: 20000 }).catch(() => {});
      const [s1, s2, s3] = await Promise.all(IDS.map((id) => state(pg, id)));
      check(s, `B11.3a ${width}`, `${width} px: estado inicial (primera visible, otras inertes, aria-current, región viva polite); con movimiento reducido no arranca solo («Reanudar»)`, s1 && s1.inert === 'X--' && s1.current === 'X--' && s1.visible === 'X--' && s1.live === 'polite' && s2.pause === 'Reanudar' && s2.live === 'polite' && s3.visible === 'X--', JSON.stringify({ s1, s2, s3 }));
      const sizes = await pg.evaluate(() => [...document.querySelectorAll('.digi-nslider button')].filter((b) => b.offsetParent).map((b) => { const r = b.getBoundingClientRect(); return [Math.round(r.width), Math.round(r.height)]; }));
      check(s, `B11.3b ${width}`, `${width} px: botones de 24 × 24 px como mínimo y sin desplazamiento horizontal de la página`, sizes.length >= 10 && sizes.every(([w, h]) => w >= 24 && h >= 24) && await pg.evaluate(() => document.documentElement.scrollWidth <= innerWidth), JSON.stringify(sizes));
      // Orden del foco: dentro de cada slider, pausa primero, controles antes que el contenido, sólo la visible.
      const order = [];
      await pg.focus('body').catch(() => {});
      for (let k = 0; k < 40; k++) { await pg.keyboard.press('Tab'); order.push(await pg.evaluate(() => { const a = document.activeElement; const w = a && a.closest('[data-id^="w"]'); return w ? `${w.dataset.id}:${a.getAttribute('aria-label') || a.textContent.trim()}` : ''; })); }
      const inSliders = order.filter(Boolean).filter((x) => /^w[123]/.test(x));
      const want = ['w1slide:Diapositiva anterior', 'w1slide:Ir a la diapositiva 1', 'w1slide:Ir a la diapositiva 2', 'w1slide:Ir a la diapositiva 3', 'w1slide:Diapositiva siguiente', 'w1slide:Enlace uno',
        'w2fade1:Reanudar', 'w2fade1:Diapositiva anterior', 'w2fade1:Ir a la diapositiva 1', 'w2fade1:Ir a la diapositiva 2', 'w2fade1:Ir a la diapositiva 3', 'w2fade1:Diapositiva siguiente', 'w2fade1:Ver uno',
        'w3scale:Diapositiva anterior', 'w3scale:Diapositiva siguiente', 'w3scale:Leer uno'];
      check(s, `B11.3c ${width}`, `${width} px: orden del foco (pausa primero, controles antes que el contenido, sólo la diapositiva visible)`, inSliders.slice(0, want.length).join('|') === want.join('|'), inSliders.join('|'));
      await pg.focus('[data-id="w1slide"] .digi-nslider__dot');
      await pg.keyboard.press('ArrowRight');
      await settle(pg);
      const k1 = await state(pg, 'w1slide');
      await pg.keyboard.press('End');
      await settle(pg);
      const k2 = await state(pg, 'w1slide');
      await pg.focus('[data-id="w1slide"] .e-con:last-child a');
      await pg.keyboard.press('ArrowLeft');
      await settle(pg);
      const k3 = await state(pg, 'w1slide');
      check(s, `B11.3d ${width}`, `${width} px: flecha derecha y Fin sobre los puntos cambian de diapositiva; desde el contenido, las flechas no`, k1.visible === '-X-' && k1.current === '-X-' && k2.visible === '--X' && k3.current === '--X', JSON.stringify({ k1, k2, k3 }));
      const s1b = await state(pg, 'w1slide');
      check(s, `B11.3e ${width}`, `${width} px: alturas iguales entre pases y ningún layout-shift`, s1b.height === s1.height && new Set(s1b.heights).size === 1 && (await pg.evaluate(() => window.__shift)) === 0, JSON.stringify({ before: s1.height, after: s1b.height, heights: s1b.heights, cls: await pg.evaluate(() => window.__shift) }));
      if (width === WIDTHS[WIDTHS.length - 1]) check(s, 'B11.3f', 'sin errores de JavaScript ni respuestas de error', !v.facts.errors.length && !v.facts.failed.length, JSON.stringify({ errors: v.facts.errors.slice(0, 3), failed: v.facts.failed.slice(0, 3) }));
      await v.close();
    }
    // Reproducción automática, pausas y región viva (sin movimiento reducido).
    {
      const v = await open(page, { width: 1024, before: shift });
      const pg = v.page;
      await pg.waitForFunction(() => document.querySelectorAll('.digi-nslider--ready').length === 3, { timeout: 20000 }).catch(() => {});
      let st = await state(pg, 'w2fade1');
      check(s, 'B11.4a', 'con autoplay: «Pausar» visible y región viva `off` mientras rota', st.pause === 'Pausar' && st.live === 'off', JSON.stringify(st));
      await sleep(2900);
      st = await state(pg, 'w2fade1');
      check(s, 'B11.4b', 'a los 2 s pasa a la segunda sin desplazar nada', st.visible === '-X-' && (await pg.evaluate(() => window.__shift)) === 0, JSON.stringify(st));
      const box = await (await pg.$('[data-id="w2fade1"] .digi-nslider__track')).boundingBox();
      await pg.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
      await sleep(2900);
      const hover = (await state(pg, 'w2fade1')).visible;
      await pg.mouse.move(2, 2);
      await pg.click('[data-id="w2fade1"] .digi-nslider__pause');
      const paused = await state(pg, 'w2fade1');
      await sleep(2900);
      check(s, 'B11.4c', 'con el puntero encima no avanza; «Pausar» la detiene, dice «Reanudar» y la región viva pasa a `polite`', hover === '-X-' && paused.pause === 'Reanudar' && paused.live === 'polite' && (await state(pg, 'w2fade1')).visible === paused.visible, JSON.stringify({ hover, paused }));
      await pg.evaluate(() => { const d = document.createElement('div'); d.style.height = '3000px'; document.body.append(d); });
      await pg.click('[data-id="w2fade1"] .digi-nslider__pause');
      await pg.focus('body').catch(() => {});
      await pg.evaluate(() => scrollTo(0, document.body.scrollHeight));
      await sleep(300);
      const away = (await state(pg, 'w2fade1')).visible;
      await sleep(2900);
      check(s, 'B11.4d', 'fuera de la pantalla no avanza (un solo IntersectionObserver)', (await state(pg, 'w2fade1')).visible === away, away);
      // Árbol de accesibilidad.
      await pg.evaluate(() => scrollTo(0, 0));
      const tree = await pg.accessibility.snapshot({ root: await pg.$('[data-id="w1slide"] .digi-nslider'), interestingOnly: false });
      const flat = [];
      const walk = (n) => { if (!n) return; flat.push(n); (n.children || []).forEach(walk); };
      walk(tree);
      const groups = flat.filter((n) => n.role === 'group' && /de 3$/.test(n.name || ''));
      check(s, 'B11.4e', 'árbol de accesibilidad: región «Novedades» (carrusel), botones con nombre y sólo la diapositiva visible («1 de 3»)', tree && tree.role === 'region' && tree.name === 'Novedades' && tree.roledescription === 'carrusel' && groups.length === 1 && groups[0].name === '1 de 3' && !flat.some((n) => /Enlace dos|Enlace tres/.test(n.name || '')), JSON.stringify({ role: tree && tree.role, name: tree && tree.name, rd: tree && tree.roledescription, groups: groups.map((g) => g.name) }));
      await v.close();
    }
    // Caché de elementos: segunda visita igual (HTML y handles).
    await control('option', { name: 'elementor_element_cache_ttl', value: '24' });
    try {
      const a = await open(page);
      const b = await open(page);
      check(s, 'B11.5', 'caché de elementos llena: la segunda visita da el mismo HTML y los mismos handles', normalize(a.html) === normalize(b.html) && JSON.stringify(a.probe.scripts) === JSON.stringify(b.probe.scripts) && JSON.stringify(a.probe.styles) === JSON.stringify(b.probe.styles), JSON.stringify({ a: a.probe.styles, b: b.probe.styles }).slice(0, 300));
      await a.close();
      await b.close();
    } finally {
      await control('option', { name: 'elementor_element_cache_ttl', value: 'disable' });
    }

    // Presentación de pestañas y acordeón.
    const nested = await open(await url('b11-nested'));
    const np = nested.page;
    const pres = await np.evaluate(() => ({
      tabs: document.querySelector('.elementor-element-b11tab1').className,
      acc: document.querySelector('.elementor-element-b11acc1').className,
      none: document.querySelector('.elementor-element-b11tab2').className,
    }));
    const presCss = nested.html.match(/<link[^>]*id=['"]digitalisimo-f7b-nested-presentation-css['"][^>]*>/g) || [];
    check(s, 'B11.6a', 'la clase `digi-nested-<estilo>` en el envoltorio elegido, ninguna en el que no tiene presentación, y la hoja una sola vez', /digi-nested-pills/.test(pres.tabs) && /digi-nested-boxed/.test(pres.acc) && !/digi-nested-/.test(pres.none) && presCss.length === 1, JSON.stringify({ pres, presCss: presCss.length }));
    const titles = await np.evaluate(() => [...document.querySelectorAll('.elementor-element-b11tab1 [role="tab"], .elementor-element-b11acc1 summary')].map((t) => Math.round(t.getBoundingClientRect().height)));
    check(s, 'B11.6b', 'títulos de pestañas y acordeón de 24 px de alto como mínimo', titles.length >= 5 && titles.every((h) => h >= 24), JSON.stringify(titles));
    await np.focus('.elementor-element-b11tab1 [role="tab"]');
    await np.keyboard.press('ArrowRight');
    await sleep(500);
    const tabState = await np.evaluate(() => { const a = document.activeElement; const cs = getComputedStyle(a); return { text: a.textContent.trim(), selected: a.getAttribute('aria-selected'), outline: cs.outlineStyle, width: cs.outlineWidth, color: cs.outlineColor === cs.color }; });
    check(s, 'B11.6c', 'teclado en las pestañas con presentación: la flecha pasa a «Segunda» con foco visible (contorno sólido de `currentColor`)', tabState.text === 'Segunda' && tabState.selected === 'true' && tabState.outline !== 'none' && tabState.color, JSON.stringify(tabState));
    const plain = await request('GET', await url('b8-plain'));
    check(s, 'B11.6d', 'las páginas sin presentación no cargan la hoja', !/digitalisimo-f7b-nested-presentation/.test(plain.text));
    await nested.close();

    // Editor real: insertar, añadir, elegir, duplicar y borrar diapositivas; cambiar la transición.
    await login('admin');
    let ed = null;
    try {
      ed = await openEditor('admin', m.docs['b11-slider']);
      const panel = await ed.page.evaluate(() => { const w = window.elementor.widgetsCache || (window.elementor.config && window.elementor.config.widgets) || {}; const own = w['digitalisimo-nested-slider']; const pro = w['nested-carousel']; return { own: own && own.title, keywords: own && own.keywords, pro: pro && pro.title }; });
      check(s, 'B11.7a', 'en el panel: «Slider anidado ligero» con sus palabras clave (diapositivas) y el Carrusel de PRO aparte', /Slider anidado ligero/.test(panel.own || '') && (panel.keywords || []).includes('diapositivas') && !!panel.pro, JSON.stringify(panel));
      const dots = () => ed.frame.$$eval('[data-id="w1slide"] .digi-nslider__dot', (l) => l.length);
      const d0 = await dots();
      await ed.page.evaluate(() => window.$e.run('document/repeater/insert', { container: window.elementor.getContainer('w1slide'), name: 'slides', model: { slide_title: 'Cuatro' } }));
      await sleep(2500);
      const d1 = await dots();
      const lab = await ed.frame.$$eval('[data-id="w1slide"] .digi-nslider__track > .e-con', (l) => l.map((x) => x.getAttribute('aria-label')));
      check(s, 'B11.7b', '«Añadir diapositiva»: 4 puntos y la nueva etiquetada «4 de 4»', d0 === 3 && d1 === 4 && lab[3] === '4 de 4', JSON.stringify({ d0, d1, lab }));
      await ed.page.evaluate(() => window.$e.run('document/repeater/duplicate', { container: window.elementor.getContainer('w1slide'), name: 'slides', index: 0 }));
      await sleep(2500);
      const lab2 = await ed.frame.$$eval('[data-id="w1slide"] .digi-nslider__track > .e-con', (l) => l.map((x) => x.getAttribute('aria-label')));
      check(s, 'B11.7c', 'duplicar: los puntos y las etiquetas se renumeran 1..N', (await dots()) === 5 && lab2.join() === '1 de 5,2 de 5,3 de 5,4 de 5,5 de 5', JSON.stringify(lab2));
      await ed.page.evaluate(() => window.$e.run('document/repeater/remove', { container: window.elementor.getContainer('w1slide'), name: 'slides', index: 4 }));
      await ed.page.evaluate(() => window.$e.run('document/repeater/remove', { container: window.elementor.getContainer('w1slide'), name: 'slides', index: 1 }));
      await sleep(2500);
      check(s, 'B11.7d', 'borrar dos: 3 puntos', (await dots()) === 3);
      await ed.page.evaluate(() => window.$e.run('document/elements/settings', { container: window.elementor.getContainer('w1slide'), settings: { effect: 'fade' } }));
      await sleep(2500);
      const fx = await ed.frame.$eval('[data-id="w1slide"] .digi-nslider', (r) => r.className);
      check(s, 'B11.7e', 'cambiar «Transición» a «Fundido» repinta el slider', /fade/.test(fx), fx);
      check(s, 'B11.7f', 'sin errores de JavaScript ni 404 en el editor', !ed.facts.errors.length && !ed.facts.failed.length, JSON.stringify({ errors: ed.facts.errors.slice(0, 3), failed: ed.facts.failed.slice(0, 3) }));
    } catch (e) {
      check(s, 'B11.7x', 'slider anidado en el editor real', false, String(e.stack || e).slice(0, 500));
      if (ed) await shot(ed.page, 'b11-editor');
    } finally {
      if (ed) await ed.close();
    }
  }

  return { B11 };
};
