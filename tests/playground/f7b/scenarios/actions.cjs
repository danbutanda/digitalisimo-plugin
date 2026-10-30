// B1 (acciones con destino) y B2 (interacciones PRO) de f7b.sh. Sólo la forma pública: el HTML que pinta el servidor,
// lo que ve y hace Chrome (estilo calculado, foco, teclado, puntero, árbol de accesibilidad) y el estado del sitio.
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => {
  const { OUT, WIDTHS, QUICK, check, pending, note, control, request, open, ids, url, docId, login, context, newPage, BASE, sleep, normalize, shot, logSize, serverLog } = api;
  const RULE = ':is(.elementor-element,.elementor-column)[hidden]{display:none!important}';
  const sel = (id) => `.elementor-element-${id}`;
  const disp = (page, ids) => page.evaluate((list) => Object.fromEntries(list.map((id) => { const el = document.querySelector(`.elementor-element-${id}`); return [id, el ? getComputedStyle(el).display : null]; })), ids);
  const visible = (page, id) => page.evaluate((s) => { const el = document.querySelector(s); return !!el && !el.hidden && getComputedStyle(el).display !== 'none'; }, sel(id));
  const active = (page) => page.evaluate(() => { const a = document.activeElement; if (!a) return ''; const w = a.closest('[data-id]'); return `${a.localName}${w ? '@' + w.getAttribute('data-id') : ''}`; });
  /** Pulsa Tab hasta que el foco esté en un elemento que cumple `want` (descriptor `etiqueta@data-id`); como mucho `max` veces. */
  async function tabTo(page, want, max = 40) {
    for (let i = 0; i < max; i++) {
      await page.keyboard.press('Tab');
      const a = await active(page);
      if (want.test(a)) return { ok: true, tabs: i + 1, at: a };
    }
    return { ok: false, at: await active(page) };
  }
  const tagOf = (html, id) => { const m = String(html).match(new RegExp(`<[^>]*elementor-element-${id}[^>]*>`)); return m ? m[0] : ''; };

  async function B1(s) {
    const m = await ids(true);
    const page1 = await url('b1-actions');
    const popupId = m.docs['b1-popup'];

    // B1.1 · Marcado del servidor.
    const v = await open(page1);
    const html = v.html;
    const actionsOf = (id) => { const t = tagOf(html, id); const a = t.match(/data-digi-actions="([^"]*)"/); return a ? JSON.parse(a[1].replace(/&quot;/g, '"')) : null; };
    check(s, 'B1.1a', 'cada disparador lleva `data-digi-actions` con su fila saneada (destino del documento, popup, clase, atributo, retraso)',
      JSON.stringify(actionsOf('b1btn01')) === '[{"t":"click","a":"toggle","g":"b1pan01"}]' && actionsOf('b1pop01')?.[0]?.p === popupId && actionsOf('b1hd001')?.length === 2 && actionsOf('b1lod01')?.[0]?.d === 300 && actionsOf('b1end01')?.[0]?.t === 'scroll_in',
      JSON.stringify({ btn: actionsOf('b1btn01'), pop: actionsOf('b1pop01'), popupId, hd: actionsOf('b1hd001'), lod: actionsOf('b1lod01') }));
    check(s, 'B1.1b', 'filas fuera de las listas cerradas descartadas en el servidor (`aria-hidden`, clase `elementor-`, `show` con «al cargar»): sin atributo', actionsOf('b1bad01') === null, tagOf(html, 'b1bad01'));
    check(s, 'B1.1c', '`click` en un contenedor con dos botones dentro: el servidor no lo registra ni lo convierte en botón', actionsOf('b1mul01') === null && !/role="button"/.test(tagOf(html, 'b1mul01')), tagOf(html, 'b1mul01'));
    check(s, 'B1.1d', 'sin descendientes interactivos el envoltorio es botón (`role="button" tabindex="0"`); con un único enlace, el control es el enlace', /role="button" tabindex="0"/.test(tagOf(html, 'b1hd001')) && !/role=/.test(tagOf(html, 'b1btn01')), `${tagOf(html, 'b1hd001')} · ${tagOf(html, 'b1btn01')}`);
    const hiddenIds = ['b1pan01', 'b1tip01', 'b1grd01', 'b1wid01', 'b1col01'];
    check(s, 'B1.1e', '«Oculto al cargar» sale del servidor con `hidden`', hiddenIds.every((id) => / hidden(>| )/.test(tagOf(html, id))), hiddenIds.map((id) => `${id}:${/ hidden/.test(tagOf(html, id))}`).join(' '));
    const ns = (id) => { const mm = html.match(new RegExp(`<noscript><style>\\.elementor-element\\.elementor-element-${id}\\[hidden\\]\\{display:([^!]+)!important\\}</style></noscript>`)); return mm ? mm[1] : null; };
    const want = { b1pan01: 'var(--display)', b1tip01: 'var(--display)', b1grd01: 'var(--display)', b1wid01: 'block', b1col01: 'flex' };
    const got = Object.fromEntries(Object.keys(want).map((id) => [id, ns(id)]));
    check(s, 'B1.1f', '`<noscript>` por destino oculto con el `display` de su tipo (contenedor, columna, widget)', JSON.stringify(got) === JSON.stringify(want), JSON.stringify(got));
    const nsBytes = (html.match(/<noscript><style>\.elementor-element\.elementor-element-[a-z0-9]+\[hidden\]\{display:[^}]+\}<\/style><\/noscript>/g) || []).map((x) => x.length);
    check(s, 'B1.1g', 'cada `<noscript>` ≤ 130 B (V.13)', nsBytes.length === 5 && nsBytes.every((n) => n <= 130), JSON.stringify(nsBytes));
    const script = html.match(/<script[^>]*id="digitalisimo-f7b-actions-js"[^>]*>/);
    check(s, 'B1.1h', '`actions.js` encolado con `defer`, sin jQuery como dependencia', !!script && /\bdefer\b/.test(script[0]) && !/jquery/.test(script[0]), script ? script[0] : 'sin script');
    const jsFile = path.join(api.ENV.SRC_ELEMENTS, 'modules/digitalisimo-f7b/assets/js/actions.js');
    const jsBytes = fs.statSync(jsFile).size;
    check(s, 'B1.1i', '`actions.js` ≤ 6 KB (techo de V.13)', jsBytes <= 6144, `${jsBytes} B`);
    const probe = v.probe || {};
    check(s, 'B1.1j', 'puerta de PRO activa: la acción la pide (`require:f7b-action`) y el popup sin condiciones se imprime (`add_popup_to_location`)', (probe.gate || []).includes('require:f7b-action') && new RegExp(`data-elementor-type="popup"[^>]*data-elementor-id="${popupId}"|data-elementor-id="${popupId}"[^>]*data-elementor-type="popup"`).test(html) && (probe.scripts || []).includes('elementor-pro-frontend') && (probe.scripts || []).includes('pro-elements-handlers'), JSON.stringify({ gate: probe.gate, scripts: probe.scripts }));
    await v.close();

    // B1.2 · Regla [hidden] en el CSS del documento, una vez.
    const cssLinks = (h) => (h.match(/href=['"]([^'"]*\/uploads\/elementor\/css\/post-\d+\.css[^'"]*)['"]/g) || []).map((x) => x.match(/href=['"]([^'"]*)['"]/)[1]);
    const cssUrl = cssLinks(html).find((u) => u.includes(`post-${m.docs['b1-actions']}.css`));
    const css = cssUrl ? (await request('GET', cssUrl)).text : '';
    check(s, 'B1.2', 'la regla `[hidden]` está una sola vez en el CSS del documento (0 peticiones extra)', css.split(RULE).length - 1 === 1, cssUrl || 'sin hoja del documento');
    const other = await request('GET', await url('b3-compare'));
    const otherCss = cssLinks(other.text);
    let leaked = false;
    for (const u of otherCss) if ((await request('GET', u)).text.includes(RULE)) leaked = true;
    check(s, 'B1.2b', 'un documento sin ocultar ni formularios condicionales no lleva la regla', !leaked, otherCss.join(' '));

    // B1.3 · display:none en flex, grid, columna y widget, y lo que muestra cada disparador, en cada ancho.
    for (const width of WIDTHS) {
      const p = await open(page1, { width });
      const before = await disp(p.page, ['b1pan01', 'b1grd01', 'b1col01', 'b1wid01']);
      for (const btn of ['b1btn01', 'b1btg01', 'b1btw01', 'b1btc01']) await p.page.click(`${sel(btn)} a`);
      await sleep(200);
      const after = await disp(p.page, ['b1pan01', 'b1grd01', 'b1col01', 'b1wid01']);
      const hash = await p.page.evaluate(() => location.hash);
      check(s, `B1.3 ${width}`, `${width} px: ocultos con display:none (flex, grid, columna, widget) y visibles con su display al pulsar; los enlaces «#» no cambian la dirección`,
        Object.values(before).every((x) => x === 'none') && after.b1pan01 === 'flex' && after.b1grd01 === 'grid' && after.b1col01 === 'flex' && after.b1wid01 === 'block' && hash === '',
        JSON.stringify({ before, after, hash }));
      if (width === WIDTHS[WIDTHS.length - 1]) check(s, 'B1.3b', 'sin errores de JavaScript ni respuestas de error del sitio; nada sale del sitio', !p.facts.errors.length && !p.facts.failed.length && !p.facts.outside.length, JSON.stringify({ errors: p.facts.errors.slice(0, 3), failed: p.facts.failed.slice(0, 3), outside: p.facts.outside.slice(0, 3) }));
      await p.close();
    }

    // B1.4 · Sin JavaScript: el <noscript> muestra cada destino con su display.
    {
      const p = await open(page1, { js: false, wait: 'load' });
      const d = await disp(p.page, ['b1pan01', 'b1grd01', 'b1col01', 'b1wid01', 'b1tip01']);
      check(s, 'B1.4', 'sin JavaScript se ven (flex, grid, flex, block): el `<noscript>` de cada destino', d.b1pan01 === 'flex' && d.b1grd01 === 'grid' && d.b1col01 === 'flex' && d.b1wid01 === 'block' && d.b1tip01 === 'flex', JSON.stringify(d));
      await p.close();
    }

    // B1.5 · Recorrido sólo con teclado.
    {
      const p = await open(page1);
      const pg = p.page;
      const y0 = await pg.evaluate(() => window.scrollY);
      const t1 = await tabTo(pg, /^a@b1btn01$/);
      check(s, 'B1.5a', 'con Tab se llega al enlace «Mostrar panel» (el único control del Botón)', t1.ok, JSON.stringify(t1));
      const aria0 = await pg.$eval(`${sel('b1btn01')} a`, (a) => ({ exp: a.getAttribute('aria-expanded'), ctl: a.getAttribute('aria-controls') }));
      await pg.keyboard.press('Enter');
      await sleep(150);
      const aria1 = await pg.$eval(`${sel('b1btn01')} a`, (a) => ({ exp: a.getAttribute('aria-expanded'), ctl: a.getAttribute('aria-controls'), target: document.getElementById(a.getAttribute('aria-controls'))?.getAttribute('data-id') }));
      check(s, 'B1.5b', 'Intro abre el panel; `aria-expanded` y `aria-controls` en el control (no en un div), con `id` asignado al destino', aria0.exp === 'false' && aria1.exp === 'true' && aria1.target === 'b1pan01' && await visible(pg, 'b1pan01'), JSON.stringify({ aria0, aria1 }));
      const t2 = await tabTo(pg, /^a@b1cls01$/, 3);
      check(s, 'B1.5c', 'el siguiente Tab entra en el panel abierto («Cerrar panel»)', t2.ok, JSON.stringify(t2));
      await pg.keyboard.press('Enter');
      await sleep(150);
      const back = await active(pg);
      check(s, 'B1.5d', 'Intro en «Cerrar panel» (dentro de lo que oculta) oculta el panel y el foco vuelve al control que lo abre', !(await visible(pg, 'b1pan01')) && back === 'a@b1btn01', back);
      const t3 = await tabTo(pg, /^div@b1hov01$/, 5);
      check(s, 'B1.5e', 'el contenedor con `hover` recibe el foco y muestra su destino (foco = puntero)', t3.ok && await visible(pg, 'b1tip01'), JSON.stringify(t3));
      await pg.keyboard.press('Escape');
      await sleep(100);
      check(s, 'B1.5f', 'Escape cierra lo que mostró `hover` sin mover el foco (1.4.13)', !(await visible(pg, 'b1tip01')) && (await active(pg)) === 'div@b1hov01', await active(pg));
      const t4 = await tabTo(pg, /^div@b1hd001$/, 5);
      await pg.keyboard.press('Enter');
      await sleep(100);
      const st1 = await pg.$eval(sel('b1hd001'), (el) => ({ cls: el.classList.contains('resaltado'), pressed: el.getAttribute('aria-pressed') }));
      await pg.keyboard.press(' ');
      await sleep(100);
      const st2 = await pg.$eval(sel('b1hd001'), (el) => ({ cls: el.classList.contains('resaltado'), pressed: el.getAttribute('aria-pressed') }));
      check(s, 'B1.5g', 'el envoltorio-botón responde a Intro y Espacio: alterna la clase y pone `aria-pressed`', t4.ok && st1.cls && st1.pressed === 'true' && !st2.cls, JSON.stringify({ t4, st1, st2 }));
      const y1 = await pg.evaluate(() => window.scrollY);
      check(s, 'B1.5h', 'el recorrido no desplaza la página ni salta a «#»', Math.abs(y1 - y0) < 400 && (await pg.evaluate(() => location.hash)) === '', JSON.stringify({ y0, y1 }));
      // Árbol de accesibilidad: el control dice si su panel está abierto y el envoltorio es un botón con nombre.
      const snapA = await pg.accessibility.snapshot({ root: await pg.$(`${sel('b1btn01')} a`) });
      const snapB = await pg.accessibility.snapshot({ root: await pg.$(sel('b1hd001')) });
      check(s, 'B1.5i', 'árbol de accesibilidad: «Mostrar panel» con su estado (`expanded`) y «Resaltar» como botón con nombre', snapA && snapA.expanded === false && snapB && snapB.role === 'button' && /Resaltar/.test(snapB.name || ''), JSON.stringify({ a: snapA && { role: snapA.role, name: snapA.name, expanded: snapA.expanded }, b: snapB && { role: snapB.role, name: snapB.name } }));
      await p.close();
    }

    // B1.6 · hover con el puntero: margen de 300 ms y paso al destino.
    {
      const p = await open(page1);
      const pg = p.page;
      await pg.hover(sel('b1hovh1'));
      await sleep(100);
      const shown = await visible(pg, 'b1tip01');
      await pg.mouse.move(5, 5);
      await sleep(120);
      const during = await visible(pg, 'b1tip01');
      await sleep(450);
      const after = await visible(pg, 'b1tip01');
      await pg.hover(sel('b1hovh1'));
      await sleep(80);
      await pg.hover(sel('b1tiph1'));
      await sleep(500);
      const onTarget = await visible(pg, 'b1tip01');
      check(s, 'B1.6', '`hover` con el puntero: se muestra, sigue 300 ms al salir, se oculta después y sigue visible sobre el destino', shown && during && !after && onTarget, JSON.stringify({ shown, during, after, onTarget }));
      // El contenedor con dos botones no tiene `click`: pulsar uno no muestra nada.
      await pg.mouse.move(5, 5);
      await sleep(600);
      await pg.$eval(`${sel('b1mula1')} a`, (a) => { a.addEventListener('click', (e) => e.preventDefault(), { once: true }); a.click(); });
      await sleep(150);
      check(s, 'B1.6b', 'pulsar un botón dentro del contenedor con varios controles no dispara la acción descartada', !(await visible(pg, 'b1tip01')));
      await p.close();
    }

    // B1.7 · popup y panel lateral por `runAction()` con la puerta de PRO activa; cargar más; ir a; al entrar; al cargar.
    {
      const p = await open(page1);
      const pg = p.page;
      await pg.click(`${sel('b1pop01')} a`);
      const popupShown = await pg.waitForFunction((id) => { const m = document.querySelector(`#elementor-popup-modal-${id}`); return m && getComputedStyle(m).display !== 'none' && /Popup F7b/.test(m.textContent); }, { timeout: 8000 }, popupId).then(() => true).catch(() => false);
      check(s, 'B1.7a', '«Abrir popup» abre el popup sin condiciones (urlActions del núcleo)', popupShown);
      if (popupShown) {
        await sleep(1200);
        const diag = await pg.evaluate((id) => {
          const btn = document.querySelector(`#elementor-popup-modal-${id} .elementor-element-b1ppcl1`);
          const a = btn && btn.querySelector('a');
          const r = a && a.getBoundingClientRect();
          const hit = r && document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
          return { btn: !!btn, ready: btn && btn.hasAttribute('data-digi-actions-ready'), inPopup: !!(a && a.closest('[data-elementor-type="popup"]')), hit: hit ? hit.outerHTML.slice(0, 120) : null, doc: !!(window.elementorFrontend && elementorFrontend.documentsManager.documents[id]) };
        }, popupId);
        await pg.click(`#elementor-popup-modal-${popupId} ${sel('b1ppcl1')} a`);
        const closed = await pg.waitForFunction((id) => { const m = document.querySelector(`#elementor-popup-modal-${id}`); return !m || getComputedStyle(m).display === 'none'; }, { timeout: 8000 }, popupId).then(() => true).catch(() => false);
        check(s, 'B1.7b', '«Cerrar popup» (acción dentro del popup) lo cierra', closed, JSON.stringify({ diag, errors: p.facts.errors.slice(0, 3) }));
        if (!closed) { await pg.keyboard.press('Escape'); await sleep(800); }
      }
      await pg.click(`${sel('b1off01')} a`);
      const ocOpen = await pg.waitForFunction(() => { const o = document.querySelector('#off-canvas-b1ofc01'); return o && o.getAttribute('aria-hidden') === 'false' && !o.hasAttribute('inert'); }, { timeout: 8000 }).then(() => true).catch(() => false);
      check(s, 'B1.7c', '«Abrir panel lateral» abre el Off-Canvas por su ID', ocOpen, await pg.$eval('#off-canvas-b1ofc01', (o) => o.outerHTML.slice(0, 200)).catch(() => 'sin panel'));
      await pg.keyboard.press('Escape');
      await sleep(600);
      const ocClosed = await pg.$eval('#off-canvas-b1ofc01', (o) => o.getAttribute('aria-hidden') === 'true').catch(() => false);
      if (!ocClosed) await pg.evaluate(() => document.querySelector('.elementor-element-b1off01 a').click());
      await sleep(400);
      const n0 = await pg.$$eval(`${sel('b1loop1')} .e-loop-item`, (l) => l.length);
      await pg.click(`${sel('b1more1')} a`);
      const more = await pg.waitForFunction((s0) => document.querySelectorAll('.elementor-element-b1loop1 .e-loop-item').length > s0, { timeout: 20000 }, n0).then(() => true).catch(() => false);
      const n1 = await pg.$$eval(`${sel('b1loop1')} .e-loop-item`, (l) => l.length);
      check(s, 'B1.7d', '«Más entradas» pulsa el «Cargar más» del Loop Grid destino', more && n0 === 2 && n1 === 4, JSON.stringify({ n0, n1 }));
      const lod = await pg.$eval(sel('b1lod01'), (el) => el.getAttribute('data-digi-state'));
      check(s, 'B1.7e', '«al cargar» con retraso pone `data-digi-state` (lista cerrada)', lod === 'cargado', String(lod));
      const seen0 = await pg.$eval(sel('b1end01'), (el) => el.classList.contains('visto'));
      await pg.click(`${sel('b1scr01')} a`);
      await sleep(1500);
      const sc = await pg.evaluate(() => { const t = document.querySelector('.elementor-element-b1end01'); const r = t.getBoundingClientRect(); return { top: Math.round(r.top), h: innerHeight, focus: document.activeElement === t, tabindex: t.getAttribute('tabindex'), seen: t.classList.contains('visto') }; });
      check(s, 'B1.7f', '«Ir al final» desplaza al destino y le pasa el foco (`tabindex="-1"`)', sc.top >= -2 && sc.top < sc.h && sc.focus && sc.tabindex === '-1', JSON.stringify(sc));
      check(s, 'B1.7g', '«al entrar en pantalla» actúa una vez, al verse', !seen0 && sc.seen, JSON.stringify({ seen0, seen: sc.seen }));
      check(s, 'B1.7h', 'sin errores de JavaScript ni respuestas de error', !p.facts.errors.length && !p.facts.failed.length, JSON.stringify({ errors: p.facts.errors.slice(0, 3), failed: p.facts.failed.slice(0, 3) }));
      await p.close();
    }

    // B1.8 · Movimiento reducido: `scroll_to` sin animación.
    {
      const p = await open(page1, { before: (pg) => pg.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]) });
      const pg = p.page;
      const y0 = await pg.evaluate(() => window.scrollY);
      await pg.$eval(`${sel('b1scr01')} a`, (a) => a.click());
      const y1 = await pg.evaluate(() => window.scrollY);
      await sleep(1500);
      const y2 = await pg.evaluate(() => window.scrollY);
      const sb = await pg.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior);
      check(s, 'B1.8', 'con `prefers-reduced-motion` el desplazamiento es inmediato (`behavior: auto`, sin `scroll-behavior: smooth`)', y1 > y0 && y1 === y2, JSON.stringify({ y0, y1, y2, scrollBehavior: sb }));
      await p.close();
    }

    // B1.9 · Segunda visita con la caché de elementos llena: mismo HTML, mismos handles, el popup y la puerta.
    {
      await control('option', { name: 'elementor_element_cache_ttl', value: '24' });
      await control('clearcss');
      const a = await open(page1);
      const b = await open(page1);
      const metaCache = await control('meta', { doc: 'b1-actions', key: '_elementor_element_cache' });
      const same = normalize(a.html) === normalize(b.html);
      check(s, 'B1.9a', 'caché de elementos llena: la segunda visita da el mismo HTML que la primera', same && metaCache.values.length > 0, same ? `caché ${metaCache.json.length} B` : firstDiff(normalize(a.html), normalize(b.html)));
      const pa = a.probe || {};
      const pb = b.probe || {};
      check(s, 'B1.9b', 'y los mismos handles, con la puerta de PRO abierta por la acción también desde la caché', JSON.stringify(pa.scripts) === JSON.stringify(pb.scripts) && JSON.stringify(pa.styles) === JSON.stringify(pb.styles) && (pb.gate || []).includes('require:f7b-action') && (pb.scripts || []).includes('pro-elements-handlers'), JSON.stringify({ a: pa.gate, b: pb.gate, scriptsA: pa.scripts, scriptsB: pb.scripts }));
      await b.page.click(`${sel('b1pop01')} a`);
      const shown = await b.page.waitForFunction((id) => { const m = document.querySelector(`#elementor-popup-modal-${id}`); return m && getComputedStyle(m).display !== 'none'; }, { timeout: 8000 }, popupId).then(() => true).catch(() => false);
      check(s, 'B1.9c', 'el popup se abre en la visita servida desde la caché', shown);
      await a.close();
      await b.close();
      await control('option', { name: 'elementor_element_cache_ttl', value: 'disable' });
    }
  }

  function firstDiff(a, b) {
    let i = 0;
    while (i < a.length && a[i] === b[i]) i++;
    return `difieren en ${i}: «${a.slice(Math.max(0, i - 120), i + 120)}» ≠ «${b.slice(Math.max(0, i - 120), i + 120)}»`;
  }

  async function B2(s) {
    const m = await ids(true);
    const b2 = m.docs['b2-v4'];
    let ping = await control('ping');
    const v4Before = ping.v4;
    const inv0 = await control('scan');
    check(s, 'B2.1', 'tras el recorrido por lotes, un sitio con interacciones de PRO guardadas queda en `pending` y su documento en el inventario', inv0.interactions === 'pending' && ((inv0.inventory || {}).docs || []).includes(b2), JSON.stringify({ state: inv0.interactions, inventory: inv0.inventory }));
    // v4 sólo en este Playground efímero (nunca en un sitio real): el experimento de los elementos atómicos.
    await control('option', { name: 'elementor_experiment-e_atomic_elements', value: 'active' });
    ping = await control('ping');
    if (ping.v4 !== 'active') { pending(s, 'B2.2', 'v4 activado en el Playground efímero', `no se pudo activar el experimento: ${ping.v4}`); return; }
    try {
      let v = await open(await url('b2-v4'));
      const s0 = (v.probe || {}).scripts || [];
      check(s, 'B2.2', 'en `pending` las interacciones de PRO siguen apagadas: sin `elementor-interactions-pro`', !s0.includes('elementor-interactions-pro'), JSON.stringify(s0));
      await v.close();
      await login('admin');
      const ctx = await context('admin');
      const pg = await newPage(ctx, { width: 1280 });
      await pg.goto(`${BASE}/wp-admin/admin.php?page=digitalisimo-elements-front`, { waitUntil: 'load', timeout: 180000 });
      const notice = await pg.$eval('.digitalisimo-interactions-notice', (n) => ({ text: n.textContent.replace(/\s+/g, ' ').trim(), links: [...n.querySelectorAll('a')].map((a) => a.getAttribute('href')), button: !!n.querySelector('form[action*="admin-post.php"] button[type="submit"]'), nonce: !!n.querySelector('input[name="_wpnonce"]') })).catch(() => null);
      check(s, 'B2.3', 'aviso persistente en la pantalla de DIGITALÍSIMO con la lista de documentos (enlace al editor) y el botón con nonce', !!notice && notice.button && notice.nonce && notice.links.some((h) => h.includes(`post=${b2}`) && h.includes('action=elementor')), JSON.stringify(notice));
      const other = await (await newPage(ctx)).goto(`${BASE}/wp-admin/index.php`, { waitUntil: 'load' }).then((r) => r.text());
      check(s, 'B2.3b', 'el aviso no sale fuera de las pantallas de DIGITALÍSIMO (escritorio)', !other.includes('digitalisimo-interactions-notice'));
      if (notice) {
        await Promise.all([pg.waitForNavigation({ waitUntil: 'load', timeout: 180000 }), pg.click('.digitalisimo-interactions-notice button[type="submit"]')]);
        const done = await pg.$eval('.notice-success', (n) => n.textContent.trim()).catch(() => '');
        ping = await control('ping');
        check(s, 'B2.4', '«Reproducir las interacciones guardadas» pasa el sitio a `ack` y avisa del resultado', ping.interactions === 'ack' && /ya se reproducen/.test(done), JSON.stringify({ state: ping.interactions, done, url: pg.url() }));
      }
      await ctx.close();
      const forged = await request('POST', '/wp-admin/admin-post.php', { body: api.form({ action: 'digitalisimo_elements_interactions_play' }), headers: api.FORM });
      check(s, 'B2.4b', 'la acción sin sesión ni nonce no cambia nada', forged.status !== 200 || !/played/.test(forged.headers.location || ''), `${forged.status} ${forged.headers.location || ''}`);
      v = await open(await url('b2-v4'));
      const s1 = (v.probe || {}).scripts || [];
      const inline = /<script[^>]*id="motion-js-after"[^>]*>[^<]*skipAnimations=true/.test(v.html);
      check(s, 'B2.5', 'en `ack` se reproducen: `elementor-interactions-pro` y `motion-js` con el script de movimiento reducido en línea (≈ 150 B)', s1.includes('elementor-interactions-pro') && inline, JSON.stringify({ scripts: s1.filter((x) => /motion|interaction/.test(x)), inline }));
      const bytes = (v.html.match(/<script[^>]*id="motion-js-after"[^>]*>([\s\S]*?)<\/script>/) || ['', ''])[1].trim().length;
      check(s, 'B2.5b', 'el script en línea pesa ≤ 150 B', bytes > 0 && bytes <= 150, `${bytes} B`);
      const skipNormal = await v.page.evaluate(() => !!(window.Motion && window.Motion.MotionGlobalConfig && window.Motion.MotionGlobalConfig.skipAnimations));
      await v.close();
      const r = await open(await url('b2-v4'), { before: (p) => p.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]) });
      const skipReduced = await r.page.evaluate(() => !!(window.Motion && window.Motion.MotionGlobalConfig && window.Motion.MotionGlobalConfig.skipAnimations));
      check(s, 'B2.6', 'con movimiento reducido emulado, `Motion.MotionGlobalConfig.skipAnimations` = true; sin él, false', skipReduced && !skipNormal, JSON.stringify({ skipNormal, skipReduced }));
      check(s, 'B2.6b', 'sin errores de JavaScript en la página v4 con interacciones', !r.facts.errors.length, JSON.stringify(r.facts.errors.slice(0, 3)));
      await r.close();
      const plain = await open(await url('b3-compare'));
      check(s, 'B2.7', 'una página sin interacciones no carga `motion-js` ni el script de PRO (0 bytes)', !((plain.probe || {}).scripts || []).some((x) => /motion|interactions/.test(x)), JSON.stringify((plain.probe || {}).scripts));
      await plain.close();
      // `clear`: un sitio cuyo recorrido no encuentra interacciones de PRO (el documento las pierde y vuelve a `pending`).
      const tree = await control('data', { doc: 'b2-v4' });
      const stripped = (tree.elements || []).map((el) => { const c = { ...el }; delete c.interactions; return c; });
      await control('save_raw', {}, { post: { doc: 'b2-v4', elements: JSON.stringify(stripped) } });
      await control('option', { name: 'digitalisimo_elements_interactions_state', value: 'pending' });
      const inv1 = await control('scan');
      check(s, 'B2.8', 'sin interacciones de PRO en ningún documento, el recorrido pasa `pending` a `clear`', inv1.interactions === 'clear', JSON.stringify({ state: inv1.interactions, inventory: inv1.inventory }));
      await control('save_raw', {}, { post: { doc: 'b2-v4', elements: JSON.stringify(tree.elements) } });
    } finally {
      await control('option', { name: 'elementor_experiment-e_atomic_elements', value: v4Before === null ? 'null' : JSON.stringify(v4Before) });
    }
  }

  return { B1, B2 };
};
