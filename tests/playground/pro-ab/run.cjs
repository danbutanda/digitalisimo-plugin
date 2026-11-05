// A/B de pro-ab.sh en Chrome sin interfaz: por cada caso, la página A (widget de PRO) y la B (convertida) a 375, 768 y
// 1280 px. Firma del widget (palabras, enlaces, imágenes, nombres accesibles y roles, estilos calculados de los textos,
// de la caja y de los enlaces y botones), captura y diferencia de píxeles, hover y foco con teclado del primer enlace
// o botón, recursos de PRO que B no puede cargar, errores de JavaScript, 404 y peticiones a terceros. Toda diferencia
// de firma necesita un motivo declarado (cases.json: `declared`, con `reason` o con `code` del `drop`/`approx` del
// mapa); la de píxeles, quedar bajo el umbral. Resultado en $OUT/pro-ab.json y capturas en $OUT/shots/.
'use strict';
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');

const OUT = process.env.OUT;
const PROBE = process.env.PROBE || path.join(OUT, 'probe');
const BASE = process.env.BASE;
const HOST = new URL(BASE).host;
const SHOTS = path.join(OUT, 'shots');
const CONFIG = JSON.parse(fs.readFileSync(path.join(PROBE, 'cases.json'), 'utf8'));
const CONV = JSON.parse(fs.readFileSync(path.join(PROBE, 'conversion.json'), 'utf8'));
const WIDTHS = CONFIG.widths || [375, 768, 1280];
const PIXEL_MAX = process.env.PIXEL_MAX !== '' && process.env.PIXEL_MAX !== undefined ? Number(process.env.PIXEL_MAX) : Number((CONFIG.pixels || {}).max ?? 0.02);
// Umbral del estado con foco: el foco visible propio de un widget (criterio del estándar del front) dibuja otro contorno;
// sólo vale con su motivo en `pixels.focus_reason`.
const FOCUS_MAX = (CONFIG.pixels || {}).focus_reason ? Number((CONFIG.pixels || {}).focus_max ?? 0) : 0;
const PIXEL_TOLERANCE = process.env.PIXEL_TOLERANCE ? Number(process.env.PIXEL_TOLERANCE) : Number((CONFIG.pixels || {}).tolerance ?? 48);
const MARGIN = 12; // px alrededor del widget en cada captura (sombras y contorno del foco)
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const problems = [];
const notes = [];
fs.mkdirSync(SHOTS, { recursive: true });

const sameOrigin = (u) => { try { return new URL(u).host === HOST; } catch (e) { return false; } };

// ── Firma del widget (se evalúa en la página) ─────────────────────────────────────────────────────────────────────
function pageSignature(sel, slug) {
  const root = document.querySelector(sel);
  if (!root) return null;
  const clean = (s) => String(s || '').replace(/\s+/g, ' ').trim();
  // Destino canónico: la consulta se decodifica y se vuelve a codificar igual en A y en B (`+` y `%20` son el mismo
  // espacio en una consulta; un carácter sin codificar, el mismo carácter). El destino no cambia.
  const canon = (v) => {
    const i = v.indexOf('?');
    if (i < 0) return v;
    const hash = v.indexOf('#', i);
    const query = hash < 0 ? v.slice(i + 1) : v.slice(i + 1, hash);
    try {
      const pairs = [...new URLSearchParams(query)].map(([k, x]) => `${encodeURIComponent(k)}=${encodeURIComponent(x)}`);
      return v.slice(0, i) + '?' + pairs.join('&') + (hash < 0 ? '' : v.slice(hash));
    } catch (e) { return v; }
  };
  const href = (h) => {
    let v = canon(String(h || ''));
    v = v.split(location.origin).join('').split(encodeURIComponent(location.origin)).join('__SITE__');
    return v.replace(new RegExp(`pro-ab-${slug}-[ab]`, 'g'), `pro-ab-${slug}-X`);
  };
  const skip = (node) => node.parentElement && node.parentElement.closest('script,style,svg,noscript,template');
  const visible = (el) => (el.checkVisibility ? el.checkVisibility({ checkOpacity: true, checkVisibilityCSS: true }) : true);
  // Texto sólo para lectores de pantalla (recorte de 1 px): no se ve; cuenta en el nombre accesible, no en las palabras.
  const srOnly = (el) => { const r = el.getBoundingClientRect(); const cs = getComputedStyle(el); return (r.width <= 1 && r.height <= 1) || /rect\(0(px)?,? 0(px)?,? 0(px)?,? 0(px)?\)/.test(cs.clip) || cs.clipPath === 'inset(50%)'; };
  const transparent = (c) => !c || c === 'transparent' || /rgba\(\d+, \d+, \d+, 0\)/.test(c);
  // Fondo efectivo: el primero no transparente desde el elemento hasta la raíz del widget.
  const background = (el) => { for (let e = el; e && root.contains(e); e = e.parentElement) { const c = getComputedStyle(e).backgroundColor; if (!transparent(c)) return c; } return 'transparent'; };
  const sides = ['top', 'right', 'bottom', 'left'];
  const boxStyle = (el) => {
    const cs = getComputedStyle(el);
    const out = { background: transparent(cs.backgroundColor) ? 'transparent' : cs.backgroundColor, 'background-image': cs.backgroundImage === 'none' ? 'none' : 'sí' };
    for (const s of sides) {
      const w = cs.getPropertyValue(`border-${s}-width`);
      const st = cs.getPropertyValue(`border-${s}-style`);
      out[`border-${s}`] = st === 'none' || st === 'hidden' || parseFloat(w) === 0 ? 'none' : `${w} ${st} ${cs.getPropertyValue(`border-${s}-color`)}`;
    }
    out.radius = ['top-left', 'top-right', 'bottom-right', 'bottom-left'].map((c) => cs.getPropertyValue(`border-${c}-radius`)).join(' ');
    out.padding = sides.map((s) => cs.getPropertyValue(`padding-${s}`)).join(' ');
    out['box-shadow'] = cs.boxShadow;
    out['text-align'] = cs.textAlign;
    return out;
  };
  const decorated = (el) => { const b = boxStyle(el); return b.background !== 'transparent' || b['background-image'] !== 'none' || sides.some((s) => b[`border-${s}`] !== 'none') || b.padding !== '0px 0px 0px 0px' || b.radius !== '0px 0px 0px 0px' || b['box-shadow'] !== 'none'; };

  const texts = [];
  const hidden = [];
  const styles = {};
  const anchors = [];
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
  for (let node = walker.nextNode(); node; node = walker.nextNode()) {
    const value = clean(node.nodeValue);
    if (!value || skip(node)) continue;
    const el = node.parentElement;
    if (!visible(el) || srOnly(el)) { hidden.push(value); continue; }
    texts.push(value);
    anchors.push(el);
    if (!styles[value]) {
      const cs = getComputedStyle(el);
      styles[value] = { color: cs.color, size: cs.fontSize, weight: cs.fontWeight, style: cs.fontStyle, align: cs.textAlign, background: background(el), transform: cs.textTransform, spacing: cs.letterSpacing, family: cs.fontFamily.split(',')[0].replace(/["']/g, '').trim() };
    }
  }
  const focusables = [...root.querySelectorAll('a[href], button, [role="button"], [role="link"], input, select, textarea, [tabindex]:not([tabindex="-1"])')].filter(visible);
  // Caja: el primer elemento decorado entre la raíz del widget (excluida) y el antecesor común de sus textos y controles.
  const members = anchors.concat(focusables);
  let lca = members[0] || root;
  while (lca !== root && !members.every((m) => lca.contains(m))) lca = lca.parentElement;
  const chain = [];
  for (let e = lca; e && e !== root; e = e.parentElement) chain.unshift(e);
  const box = chain.find(decorated) || lca;
  const links = [...root.querySelectorAll('a[href]')].map((a) => href(a.getAttribute('href')) + (a.getAttribute('target') ? ` (target=${a.getAttribute('target')})` : ''));
  const base = (u) => String(u || '').split('?')[0].split('#')[0].split('/').pop();
  const images = [];
  for (const img of root.querySelectorAll('img')) images.push(base(img.currentSrc || img.getAttribute('src') || img.getAttribute('data-src')));
  for (const el of root.querySelectorAll('*')) {
    const bg = getComputedStyle(el).backgroundImage;
    if (bg && bg !== 'none') for (const m of bg.matchAll(/url\("?([^")]+)"?\)/g)) images.push(base(m[1]));
  }
  const controls = focusables.map((el) => {
    const s = boxStyle(el);
    const cs = getComputedStyle(el);
    delete s['text-align'];
    const svg = el.querySelector('svg');
    // Función del control, para emparejar A y B por lo que hacen y no por su orden en el documento (el motor propio
    // pone los controles antes de la pista; Swiper, después): anterior, siguiente, punto, pausa o el resto por etiqueta.
    const cls = `${el.className && el.className.baseVal !== undefined ? el.className.baseVal : el.className} ${el.getAttribute('aria-label') || ''}`.toLowerCase();
    const role = /prev|anterior/.test(cls) ? 'prev' : /next|siguiente/.test(cls) ? 'next' : /bullet|__dot|-dot\b/.test(cls) ? 'dot' : /pause|pausar|reanudar/.test(cls) ? 'pause' : /__clear\b|borrar/.test(cls) ? 'clear' : el.tagName.toLowerCase();
    return { tag: el.tagName.toLowerCase(), role, color: cs.color, size: cs.fontSize, ...s, ...(svg ? { 'icon-fill': getComputedStyle(svg).fill } : {}) };
  });
  const r = root.getBoundingClientRect();
  return { texts: [...new Set(texts)], hidden: [...new Set(hidden)], styles, links: [...new Set(links)], images: [...new Set(images.filter(Boolean))], box: boxStyle(box), boxTag: box.tagName.toLowerCase(), controls, focusables: focusables.length, height: Math.round(r.height) };
}

// Estilos de estado (hover o foco) del control y de la caja.
// `ctl` (caso `control`, C3 · frente F): el control que se mide, cuando el primero del widget no es el comparable.
function stateStyles(sel, ctl) {
  const root = document.querySelector(sel);
  const el = root && ((ctl && root.querySelector(ctl)) || root.querySelector('a[href], button, [role="button"], [role="link"]') || root);
  if (!el) return null;
  const cs = getComputedStyle(el);
  const pick = (c) => ({ color: c.color, background: c.backgroundColor, 'border-color': c.borderColor, 'border-width': c.borderWidth, outline: `${c.outlineStyle} ${c.outlineWidth} ${c.outlineColor}`, 'outline-offset': c.outlineOffset, 'box-shadow': c.boxShadow, 'text-decoration': c.textDecorationLine, opacity: c.opacity, transform: c.transform });
  const out = { control: pick(cs) };
  const svg = el.querySelector('svg');
  if (svg) out.control.fill = getComputedStyle(svg).fill;
  const label = [...el.querySelectorAll('span')].find((s) => s.textContent.trim() && s.getBoundingClientRect().width > 1);
  if (label) out.control['label-color'] = getComputedStyle(label).color;
  return out;
}

// ── Una página ────────────────────────────────────────────────────────────────────────────────────────────────────
// Espera a que los carruseles del widget hayan arrancado (Swiper en A, el motor `digi-crs` en B) y, si el caso lo pide,
// a un selector propio (`ready.a` / `ready.b`).
async function settle(page, sel, extra) {
  const ok = await page.waitForFunction((s, x) => {
    const root = document.querySelector(s);
    if (!root) return true;
    if (x && !root.querySelector(x)) return false;
    // Swiper sólo arranca con más de una diapositiva: con una (o ninguna), PRO no lo inicia y no hay nada que esperar.
    const waiting = [...root.querySelectorAll('.swiper:not(.swiper-initialized), .swiper-container:not(.swiper-initialized):not(.swiper-container-initialized)')];
    if (waiting.some((sw) => sw.querySelectorAll('.swiper-slide').length > 1)) return false;
    return !root.querySelector('.digi-crs:not(.digi-crs--ready)');
  }, { timeout: 8000, polling: 100 }, sel, extra || '').then(() => true).catch(() => false);
  await sleep(400);
  return ok;
}

async function measure(browser, url, sel, slug, side, forbid, c = {}) {
  const context = await browser.createBrowserContext();
  const page = await context.newPage();
  await page.setCacheEnabled(false);
  await page.setRequestInterception(true);
  const third = [];
  const errors = [];
  const failed = [];
  const requested = [];
  page.on('request', (r) => {
    const u = r.url();
    if (u.startsWith('data:') || u.startsWith('blob:')) { r.continue().catch(() => {}); return; }
    if (sameOrigin(u)) { requested.push(u); r.continue().catch(() => {}); return; }
    // El navegador no sale del Playground: toda petición a otro origen se anota y se corta (A y B igual).
    third.push(u.split('?')[0]);
    r.abort().catch(() => {});
  });
  page.on('pageerror', (e) => errors.push(String(e.message || e)));
  page.on('console', (m) => { if (m.type() === 'error' && !/favicon|Failed to load resource/.test(m.text())) errors.push(m.text()); });
  const sheets = [];
  const transient = [];
  page.on('response', (r) => {
    if (r.status() >= 400 && sameOrigin(r.url())) failed.push(`${r.status()} ${r.url()}`);
    // Una hoja o un script del sitio que llega con redirección o como HTML no es el recurso (Playground lo sirvió por
    // WordPress): la medición de esa página no vale y se repite.
    const type = r.request().resourceType();
    if (sameOrigin(r.url()) && ['stylesheet', 'script'].includes(type) && (r.status() >= 300 && r.status() < 400 || /text\/html/.test(r.headers()['content-type'] || ''))) transient.push(`${r.status()} ${r.url()}`);
    if (sameOrigin(r.url()) && type === 'stylesheet') sheets.push(r.buffer().then((b) => `${new URL(r.url()).pathname} ${r.status()} ${b.length}`).catch(() => `${new URL(r.url()).pathname} ${r.status()} ?`));
  });
  // Una imagen abortada por el propio navegador (al cambiar de ancho elige otra de su `srcset`) no es un fallo del sitio.
  page.on('requestfailed', (r) => { const why = (r.failure() || {}).errorText || 'falló'; if (sameOrigin(r.url()) && !(why === 'net::ERR_ABORTED' && r.resourceType() === 'image')) failed.push(`${why} ${r.url()}`); });
  const result = { url, status: 0, widths: {}, assets: {}, errors, failed, third: [] };
  try {
    await page.setViewport({ width: WIDTHS[WIDTHS.length - 1], height: 900 });
    // `reducedMotion` del caso (C2): A y B con «reducir movimiento», para comparar el estado final de una animación.
    if (c.reducedMotion) await page.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]);
    // `clock` del caso (C2): reloj del navegador fijado (Date y Date.now) en esa fecha ISO, para comparar una cuenta
    // regresiva sin depender del segundo en que se mide cada página.
    if (c.clock) {
      await page.evaluateOnNewDocument((t) => {
        const D = Date;
        class Fixed extends D { constructor(...a) { super(...(a.length ? a : [t])); } static now() { return t; } }
        window.Date = Fixed;
      }, Date.parse(c.clock));
    }
    const response = await page.goto(url, { waitUntil: 'networkidle0', timeout: 180000 });
    result.status = response ? response.status() : 0;
    const html = response ? await response.text().catch(() => '') : '';
    fs.mkdirSync(path.join(OUT, 'html'), { recursive: true });
    fs.writeFileSync(path.join(OUT, 'html', `${slug}-${side}.html`), html);
    await page.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
    await sleep(800);
    const readySel = (c.ready || {})[side] || '';
    if (!(await settle(page, sel, readySel))) result.unsettled = true;
    for (const f of forbid) {
      const byId = html.includes(`id="${f.id}"`) || html.includes(`id='${f.id}'`);
      const byUrl = requested.some((u) => new RegExp(f.url).test(new URL(u).pathname));
      result.assets[f.name] = byId || byUrl;
    }
    for (const width of WIDTHS) {
      await page.setViewport({ width, height: 900 });
      await page.mouse.move(0, 0);
      await page.evaluate(() => { if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur(); });
      await sleep(700);
      const w = { signature: await page.evaluate(pageSignature, sel, slug) };
      const handle = await page.$(sel);
      if (!w.signature || !handle) { result.widths[width] = w; continue; }
      // Rol y nombre accesible de cada enlace o botón, del árbol de accesibilidad de Chrome.
      const names = [];
      for (const el of await handle.$$('a[href], button, [role="button"], [role="link"]')) {
        const n = await page.accessibility.snapshot({ root: el, interestingOnly: false }).catch(() => null);
        if (n) names.push(`${n.role} «${String(n.name || '').replace(/\s+/g, ' ').trim()}»`);
      }
      w.signature.names = names;
      const shot = async (state) => {
        // `shot: "viewport"` (caso): la ventana entera, para lo que el widget pinta fuera de su caja (un panel fijo o un
        // desplegable de resultados, C3 · frente F).
        const clip = c.shot === 'viewport'
          ? await page.evaluate(() => ({ x: scrollX, y: scrollY, width: document.documentElement.clientWidth, height: innerHeight }))
          : await page.evaluate((s, m) => { const r = document.querySelector(s).getBoundingClientRect(); const x = Math.max(0, r.left + scrollX - m); const y = Math.max(0, r.top + scrollY - m); return { x, y, width: Math.min(document.documentElement.scrollWidth - x, r.width + 2 * m), height: r.height + 2 * m }; }, sel, MARGIN);
        const file = path.join(SHOTS, `${slug}-${side}-${width}${state ? '-' + state : ''}.png`);
        if (clip.width < 1 || clip.height < 1) return null;
        await page.screenshot({ path: file, clip, captureBeyondViewport: true });
        return file;
      };
      w.shot = await shot('');
      // Hover: sobre el primer enlace o botón (si no hay, sobre la caja del widget).
      const ctl = (c.control || {})[side] || '';
      const target = (ctl && (await handle.$(ctl))) || (await handle.$('a[href], button, [role="button"], [role="link"]')) || handle;
      await target.hover().catch(() => {});
      await sleep(700);
      w.hover = { styles: await page.evaluate(stateStyles, sel, ctl), shot: await shot('hover') };
      await page.mouse.move(0, 0);
      await sleep(700);
      // Foco con teclado (Tab desde el principio del documento, como :focus-visible).
      if (w.signature.focusables) {
        await page.evaluate(() => { if (document.activeElement) document.activeElement.blur(); window.scrollTo(0, 0); });
        let inside = false;
        for (let i = 0; i < 30 && !inside; i++) {
          await page.keyboard.press('Tab');
          inside = await page.evaluate((s) => { const r = document.querySelector(s); return !!(r && document.activeElement && r.contains(document.activeElement)); }, sel);
        }
        await sleep(500);
        w.focus = { reached: inside, styles: inside ? await page.evaluate(stateStyles, sel, ctl) : null, shot: inside ? await shot('focus') : null };
        await page.evaluate(() => document.activeElement && document.activeElement.blur());
      }
      // Acciones del caso (`actions`: `{ label, a, b, selector, key, type, hover, stay, scroll, page, focus, wait, widths }`):
      // pulsar el selector del lado (`a`/`b`, o `selector` común, relativo al widget), con `key` enfocarlo y teclear, con
      // `type` escribir un texto, con `hover` pasar el puntero por encima o, con `scroll` (C2: píxeles o `end`), desplazar la
      // página; con `scroll: true` (C5), llevar el selector a la vista. Tras
      // cada una, sin foco ni puntero encima (salvo con `stay`), firma y captura. Son acumulativas dentro de un ancho;
      // después se recarga la página.
      const actions = (c.actions || []).filter((act) => !act.widths || act.widths.map(Number).includes(width));
      if (actions.length) {
        w.actions = [];
        for (const act of actions) {
          const target = act[side] || act.selector || '';
          const entry = { label: act.label || target || act.key, done: true };
          try {
            // `page`: el selector es de la página, no del widget (un disparador fuera de él, C3 · frente F).
            const el = target ? await (act.page ? page : handle).$(target) : handle;
            if (!el) throw new Error(`no existe «${target}»`);
            // `scroll: true` (C5, desplazamiento infinito): lleva el selector a la vista en lugar de pulsarlo.
            if (act.scroll === true) await el.evaluate((n) => n.scrollIntoView({ block: 'center' }));
            // `scroll` (C2): desplaza la página (píxeles, o `end` hasta el final) en lugar de pulsar.
            else if (act.scroll !== undefined) await page.evaluate((y) => window.scrollTo(0, y === 'end' ? document.documentElement.scrollHeight : Number(y)), act.scroll);
            else if (act.key) { await el.focus(); await page.keyboard.press(act.key); } else if (act.type) { await el.focus(); await page.keyboard.type(String(act.type), { delay: 60 }); } else if (act.hover) {
              // Dos movimientos cortos tras llegar: un ratón real (SmartMenus de PRO sólo abre con el ratón si lo detecta).
              await el.hover();
              const bb = await el.boundingBox();
              if (bb) for (const d of [1, 2]) await page.mouse.move(bb.x + bb.width / 2 + d, bb.y + bb.height / 2 + d);
            } else await el.click();
          } catch (e) { entry.done = false; entry.error = String(e.message || e); }
          await sleep(Number(act.wait || 900));
          // `focus`: dónde queda el foco tras la acción (en el widget, fuera o en el cuerpo), comparado entre A y B.
          if (act.focus) entry.focus = await page.evaluate((s) => { const a = document.activeElement; const r = document.querySelector(s); if (!a || a === document.body) return 'cuerpo'; return `${r && r.contains(a) ? 'widget' : 'página'} ${a.tagName.toLowerCase()} «${String(a.getAttribute('aria-label') || a.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40)}»`; }, sel);
          // `stay`: el puntero y el foco se quedan donde los dejó la acción (menús que se cierran al salir el ratón).
          if (!act.stay) {
            await page.mouse.move(0, 0);
            await page.evaluate(() => { if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur(); });
          }
          await sleep(300);
          entry.signature = await page.evaluate(pageSignature, sel, slug);
          entry.shot = await shot(`act${w.actions.length + 1}`);
          w.actions.push(entry);
        }
        if (width !== WIDTHS[WIDTHS.length - 1]) {
          await page.goto(url, { waitUntil: 'networkidle0', timeout: 180000 });
          await sleep(500);
          await settle(page, sel, readySel);
        }
      }
      result.widths[width] = w;
    }
  } catch (e) {
    result.error = String(e.message || e);
  }
  result.third = [...new Set(third)];
  result.sheets = await Promise.all(sheets);
  result.transient = transient;
  await context.close().catch(() => {});
  return result;
}

// ── Diferencia de píxeles (en una página de Chrome, con canvas) ───────────────────────────────────────────────────
async function pixelDiff(diffPage, fileA, fileB, out, tolerance) {
  if (!fileA || !fileB || !fs.existsSync(fileA) || !fs.existsSync(fileB)) return null;
  const a = fs.readFileSync(fileA).toString('base64');
  const b = fs.readFileSync(fileB).toString('base64');
  const r = await diffPage.evaluate(async (a, b, tol) => {
    const load = async (s) => createImageBitmap(await (await fetch(`data:image/png;base64,${s}`)).blob());
    const [ia, ib] = await Promise.all([load(a), load(b)]);
    const w = Math.max(ia.width, ib.width);
    const h = Math.max(ia.height, ib.height);
    const draw = (img) => { const c = new OffscreenCanvas(w, h); const x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, w, h); x.drawImage(img, 0, 0); return x.getImageData(0, 0, w, h).data; };
    const da = draw(ia);
    const db = draw(ib);
    const out = new OffscreenCanvas(w, h);
    const ctx = out.getContext('2d');
    const img = ctx.createImageData(w, h);
    let diff = 0;
    for (let i = 0; i < da.length; i += 4) {
      const d = Math.max(Math.abs(da[i] - db[i]), Math.abs(da[i + 1] - db[i + 1]), Math.abs(da[i + 2] - db[i + 2]));
      if (d > tol) { diff++; img.data[i] = 230; img.data[i + 1] = 0; img.data[i + 2] = 60; img.data[i + 3] = 255; } else { const g = 255 - Math.round((255 - (da[i] + da[i + 1] + da[i + 2]) / 3) * 0.25); img.data[i] = img.data[i + 1] = img.data[i + 2] = g; img.data[i + 3] = 255; }
    }
    ctx.putImageData(img, 0, 0);
    const blob = await out.convertToBlob({ type: 'image/png' });
    const bytes = new Uint8Array(await blob.arrayBuffer());
    let bin = '';
    for (let i = 0; i < bytes.length; i += 0x8000) bin += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    return { width: w, height: h, a: [ia.width, ia.height], b: [ib.width, ib.height], diff, ratio: diff / (w * h), png: btoa(bin) };
  }, a, b, tolerance);
  fs.writeFileSync(out, Buffer.from(r.png, 'base64'));
  delete r.png;
  r.ratio = Math.round(r.ratio * 10000) / 10000;
  r.file = path.relative(OUT, out);
  return r;
}

// Etiqueta de una acción sin `label`: su selector de A (o el común), como en measure().
const side_a = (x) => (x.a ? 'a' : 'selector');

// ── Comparación de firmas ─────────────────────────────────────────────────────────────────────────────────────────
const words = (list) => new Set(list.join(' ').toLowerCase().split(/[^\p{L}\p{N}]+/u).filter(Boolean));
function diffSignatures(sa, sb, width) {
  const out = [];
  const add = (kind, detail) => out.push({ kind, width, detail });
  if (!sa || !sb) { add('widget', `sin widget en ${!sa ? 'A' : 'B'}`); return out; }
  const wa = words(sa.texts);
  const wb = words(sb.texts);
  for (const w of wa) if (!wb.has(w)) add('palabra', `perdida «${w}»`);
  for (const w of wb) if (!wa.has(w)) add('palabra', `nueva «${w}»`);
  for (const [kind, list] of [['enlace', 'links'], ['imagen', 'images'], ['nombre', 'names']]) {
    const a = new Set(sa[list] || []);
    const b = new Set(sb[list] || []);
    for (const v of a) if (!b.has(v)) add(kind, `perdido ${kind === 'nombre' ? v : `«${v}»`}`);
    for (const v of b) if (!a.has(v)) add(kind, `nuevo ${kind === 'nombre' ? v : `«${v}»`}`);
  }
  for (const t of sa.texts) {
    if (!sa.styles[t] || !sb.styles[t]) continue;
    for (const [p, v] of Object.entries(sa.styles[t])) if (sb.styles[t][p] !== v) add('estilo', `«${t.slice(0, 50)}» ${p}: ${v} → ${sb.styles[t][p]}`);
  }
  for (const [p, v] of Object.entries(sa.box)) if (sb.box[p] !== v) add('caja', `${p}: ${v} → ${sb.box[p]}`);
  // Controles emparejados por función y número de orden dentro de ella (`prev 1`, `dot 3`, `a 2`…).
  const keyed = (list) => { const seen = {}; const out = new Map(); for (const c of list) { const r = c.role || c.tag; seen[r] = (seen[r] || 0) + 1; out.set(`${r} ${seen[r]}`, c); } return out; };
  const ka = keyed(sa.controls);
  const kb = keyed(sb.controls);
  for (const key of new Set([...ka.keys(), ...kb.keys()])) {
    const a = ka.get(key);
    const b = kb.get(key);
    if (!a || !b) { add('control', `control ${key} sólo en ${a ? 'A' : 'B'} (${(a || b).tag})`); continue; }
    for (const [p, v] of Object.entries(a)) if (p !== 'tag' && p !== 'role' && b[p] !== v) add('control', `control ${key} ${p}: ${v} → ${b[p]}`);
  }
  return out;
}
function diffStates(kind, a, b, width) {
  const out = [];
  if (!a && !b) return out;
  if (!a || !b) return [{ kind, width, detail: `estado sólo en ${a ? 'A' : 'B'}` }];
  for (const [p, v] of Object.entries(a.control || {})) if ((b.control || {})[p] !== v) out.push({ kind, width, detail: `${p}: ${v} → ${(b.control || {})[p]}` });
  return out;
}

// Motivo de una diferencia: la primera declaración del caso o global (con `widget`) que la cubre.
function explain(d, declared, reasons, codes) {
  for (const rule of declared) {
    if (rule.kind && rule.kind !== d.kind) continue;
    if (rule.width && Number(rule.width) !== Number(d.width)) continue;
    if (rule.match && !new RegExp(rule.match, 'u').test(d.detail)) continue;
    rule.__used = true;
    if (rule.code) {
      const why = reasons.drop[rule.code] || reasons.approx[rule.code];
      if (!why) return { why: '', bad: `«${rule.code}» no está en el drop ni en el approx del mapa` };
      const inLog = codes.some((c) => c.key === rule.code);
      return { why: `${reasons.drop[rule.code] ? 'drop' : 'approx'} ${rule.code}: ${why}${inLog ? '' : ' (sin código en el registro de este caso)'}` };
    }
    if (rule.reason) return { why: String(rule.reason) };
  }
  return { why: '' };
}

const line = (d) => `${d.kind} ${d.width} ${d.detail}`;

(async () => {
  const browser = await puppeteer.launch({ executablePath: '/usr/bin/google-chrome', headless: 'new', args: ['--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--hide-scrollbars', '--font-render-hinting=none', '--force-color-profile=srgb', '--disable-lcd-text'] });
  const diffPage = await browser.newPage();
  await diffPage.goto('about:blank');
  const summary = { started: new Date(+process.env.STARTED * 1000).toISOString(), elements: process.env.ELEMENTS_VERSION, git: process.env.GIT_SHA, elementor: CONV.elementor, map_override: CONV.override || '', widths: WIDTHS, pixels: { max: PIXEL_MAX, tolerance: PIXEL_TOLERANCE }, cases: {}, problems, notes };
  summary.front = CONV.front || {};
  if (!(CONV.front || {}).pro_conditional) problems.push(`ajustes de rendimiento sin aplicar (pro_conditional: ${JSON.stringify((CONV.front || {}).pro_conditional)}; pendiente ${!!CONV.front_pending}): la puerta de PRO no actúa y la comprobación de los paquetes de B no vale`);
  if (CONV.override) notes.push(`MAP: mapa de prueba fijado con Registry::set() para «${CONV.override}» (no es el mapa del repositorio)`);
  const slugs = Object.keys(CONV.cases);
  const thirdAll = new Set();
  console.log(`pro-ab · ${slugs.length} casos · ${WIDTHS.join(', ')} px · umbral de píxeles ${(PIXEL_MAX * 100).toFixed(2)} % (tolerancia ${PIXEL_TOLERANCE})`);
  for (const slug of slugs) {
    const c = CONFIG.cases[slug] || {};
    const e = CONV.cases[slug];
    const sel = `[data-id="${e.element}"]`;
    const codes = [].concat(...(e.log || []).map((l) => l.codes || []));
    const own = [];
    const fail = (m) => { own.push(m); problems.push(`${slug} · ${m}`); };
    const res = { title: c.title || slug, widget: e.widget, target: e.target, map: e.map ? e.status : 'sin mapa', registered: e.registered, css: { a: e.css_a, b: e.css_b }, codes, pages: { a: `/pro-ab-${slug}-a/`, b: `/pro-ab-${slug}-b/` }, context: e.context ? e.context.label : '', widths: {}, problems: own };
    summary.cases[slug] = res;
    if (e.error) fail(e.error);
    if (!e.registered.a) fail(`el widget de PRO «${e.widget}» no está registrado: A no pinta nada`);
    for (const code of codes.filter((x) => x.section === 'review')) fail(`${code.code} ${code.key}: ${code.reason}`);
    const forbid = (c.forbid || CONFIG.forbid_b || []).map((f) => ({ name: f.name.split('{widget}').join(e.widget), id: f.id.split('{widget}').join(e.widget), url: f.url.split('{widget}').join(e.widget) }));
    const once = async (side) => {
      let r = await measure(browser, `${BASE}/pro-ab-${slug}-${side}/`, sel, slug, side, forbid, c);
      for (let i = 0; i < 2 && r.transient.length; i++) {
        notes.push(`${slug}-${side}: recursos servidos con redirección o como HTML (${r.transient[0]}); se repite la medición`);
        r = await measure(browser, `${BASE}/pro-ab-${slug}-${side}/`, sel, slug, side, forbid, c);
      }
      if (r.unsettled) fail(`${side.toUpperCase()}: el carrusel no arrancó en 8 s (Swiper, digi-crs o ready.${side})`);
      if (r.transient.length) fail(`${side.toUpperCase()}: recursos del sitio servidos con redirección o como HTML: ${r.transient.slice(0, 3).join(' | ')}`);
      return r;
    };
    const A = await once('a');
    const B = await once('b');
    res.assets = { a: A.assets, b: B.assets };
    res.errors = { a: A.errors.concat(A.failed), b: B.errors.concat(B.failed) };
    res.third = { a: A.third, b: B.third };
    res.sheets = { a: A.sheets, b: B.sheets };
    for (const [side, r] of [['A', A], ['B', B]]) {
      if (r.error) fail(`${side}: ${r.error}`);
      if (r.status !== 200) fail(`${side}: la página respondió ${r.status}`);
      if (r.errors.length) fail(`${side}: errores de JavaScript: ${r.errors.slice(0, 3).join(' | ')}`);
      if (r.failed.length) fail(`${side}: peticiones fallidas del sitio: ${r.failed.slice(0, 3).join(' | ')}`);
    }
    for (const [name, loaded] of Object.entries(B.assets)) if (loaded) fail(`B carga ${name}`);
    const newThird = B.third.filter((u) => !A.third.includes(u));
    if (newThird.length) fail(`B pide recursos de terceros que A no pedía: ${newThird.slice(0, 4).join(', ')}`);
    for (const u of A.third.concat(B.third)) thirdAll.add(u);
    const widgetCss = Object.keys(A.assets).find((n) => /^widget-/.test(n));
    if (widgetCss && !A.assets[widgetCss]) notes.push(`${slug}: A no cargó ${widgetCss} (¿la página A es representativa?)`);

    const reasons = e.reasons || { drop: {}, approx: {} };
    const declared = (c.declared || []).concat((CONFIG.declared || []).filter((d) => !d.widget || d.widget === e.widget));
    const pix = c.pixels || {};
    const max = pix.max !== undefined ? Number(pix.max) : PIXEL_MAX;
    const tol = pix.tolerance !== undefined ? Number(pix.tolerance) : PIXEL_TOLERANCE;
    if (max > PIXEL_MAX && !pix.reason) fail(`pixels.max ${max} sube el umbral sin «reason»`);
    // Umbral de un ancho (`pixels.widths: { "375": { max, reason } }`, C2): sólo ese ancho, siempre con su motivo.
    const wmax = (width) => { const x = (pix.widths || {})[width]; if (!x || x.max === undefined) return max; if (!x.reason) fail(`pixels.widths.${width}.max sin «reason»`); return Number(x.max); };
    let declaredCount = 0;
    let unexplainedCount = 0;
    for (const width of WIDTHS) {
      const wa = A.widths[width] || {};
      const wb = B.widths[width] || {};
      const sa = wa.signature;
      const sb = wb.signature;
      // `empty` (caso, con su motivo): el widget no pinta nada a la vista hasta una acción (un panel cerrado, C3 · frente F).
      // `shape` del caso (C2, con su motivo): un widget que al cargar sólo dibuja una forma (un indicador de progreso a
      // cero) vale si su caja tiene alto; el texto llega con las acciones.
      // `blank` (motivo): un widget que por diseño no pinta texto ni imágenes (un buscador con botón de icono); entonces
      // basta con que A tenga controles (`focusables`), que se comparan igual.
      if (width === WIDTHS[WIDTHS.length - 1] && sa && !sa.texts.length && !sa.images.length && !c.empty && !(c.shape && sa.height > 0) && !(c.blank && sa.focusables)) fail('A no pinta texto ni imágenes en el widget');
      let diffs = diffSignatures(sa, sb, width);
      if (c.interact !== false && sa && sb) {
        diffs = diffs.concat(diffStates('hover', (wa.hover || {}).styles, (wb.hover || {}).styles, width));
        if (sa.focusables || sb.focusables) {
          const fa = wa.focus || {};
          const fb = wb.focus || {};
          if (!fa.reached || !fb.reached) diffs.push({ kind: 'foco', width, detail: `el teclado no llega al control en ${!fa.reached ? 'A' : ''}${!fa.reached && !fb.reached ? ' ni en ' : ''}${!fb.reached ? 'B' : ''}` });
          else diffs = diffs.concat(diffStates('foco', fa.styles, fb.styles, width));
        }
      }
      const w = { declared: [], unexplained: [], pixels: {}, height: { a: sa ? sa.height : null, b: sb ? sb.height : null }, box: { a: sa ? sa.boxTag : null, b: sb ? sb.boxTag : null } };
      for (const d of diffs) {
        const ex = explain(d, declared, reasons, codes);
        if (ex.bad) fail(`declaración no válida para «${line(d)}»: ${ex.bad}`);
        if (ex.why) w.declared.push(`${line(d)} — ${ex.why}`);
        else w.unexplained.push(line(d));
      }
      declaredCount += w.declared.length;
      unexplainedCount += w.unexplained.length;
      for (const u of w.unexplained) fail(`diferencia sin motivo: ${u}`);
      // Acciones: la misma comparación de firma y de píxeles en el estado que deja cada una.
      const acts = Math.max((wa.actions || []).length, (wb.actions || []).length);
      w.actions = [];
      for (let i = 0; i < acts; i++) {
        const xa = (wa.actions || [])[i] || {};
        const xb = (wb.actions || [])[i] || {};
        const label = xa.label || xb.label || String(i + 1);
        if (!xa.done || !xb.done) fail(`acción ${i + 1} (${label}) no se pudo hacer en ${!xa.done ? 'A' : 'B'}: ${(!xa.done ? xa : xb).error || 'sin datos'}`);
        const ad = diffSignatures(xa.signature, xb.signature, width).map((d) => ({ ...d, detail: `[acción ${i + 1} ${label}] ${d.detail}` }));
        if ((xa.focus || xb.focus) && xa.focus !== xb.focus) ad.push({ kind: 'foco', width, detail: `[acción ${i + 1} ${label}] foco en ${xa.focus} → ${xb.focus}` });
        const entry = { label, declared: [], unexplained: [], pixels: null };
        for (const d of ad) {
          const ex = explain(d, declared, reasons, codes);
          if (ex.bad) fail(`declaración no válida para «${line(d)}»: ${ex.bad}`);
          if (ex.why) entry.declared.push(`${line(d)} — ${ex.why}`);
          else { entry.unexplained.push(line(d)); fail(`diferencia sin motivo: ${line(d)}`); }
        }
        declaredCount += entry.declared.length;
        unexplainedCount += entry.unexplained.length;
        const p = await pixelDiff(diffPage, xa.shot, xb.shot, path.join(SHOTS, `${slug}-diff-${width}-act${i + 1}.png`), tol);
        entry.pixels = p || { error: 'sin captura de A o de B' };
        // Umbral propio de una acción (`pixels: { max, reason }` en la acción), con su motivo como el del caso.
        const conf = (c.actions || []).find((x) => (x.label || x[side_a(x)] || x.key) === label) || {};
        const amax = conf.pixels && conf.pixels.max !== undefined ? Number(conf.pixels.max) : wmax(width);
        if ((conf.pixels || {}).max !== undefined && amax > max && !(conf.pixels || {}).reason) fail(`acción ${label}: pixels.max ${amax} sube el umbral sin «reason»`);
        if (!p) fail(`píxeles ${width} acción ${i + 1} (${label}): sin captura de A o de B`);
        else if (p.ratio > amax) fail(`píxeles ${width} acción ${i + 1} (${label}): ${(p.ratio * 100).toFixed(2)} % distintos (umbral ${(amax * 100).toFixed(2)} %; A ${p.a.join('×')}, B ${p.b.join('×')})`);
        w.actions.push(entry);
      }
      for (const state of ['', 'hover', 'focus']) {
        const fa = state ? (wa[state] || {}).shot : wa.shot;
        const fb = state ? (wb[state] || {}).shot : wb.shot;
        if (!fa && !fb) continue;
        // `pixels.states.<hover|focus>.skip` (con `reason`): el estado no se compara en píxeles, p. ej. cuando sólo B tiene
        // algo enfocable (un control de teclado que PRO no ofrecía) y A no tiene captura con el foco.
        if (state && ((pix.states || {})[state] || {}).skip) { if (!pix.states[state].reason) fail(`pixels.states.${state}.skip sin «reason»`); continue; }
        const p = await pixelDiff(diffPage, fa, fb, path.join(SHOTS, `${slug}-diff-${width}${state ? '-' + state : ''}.png`), tol);
        const key = state || 'normal';
        w.pixels[key] = p || { error: 'sin captura de A o de B' };
        if (!p) fail(`píxeles ${width} ${key}: sin captura de A o de B`);
        // `pixels.states.<hover|focus>`: umbral propio de un estado (con `reason`), p. ej. cuando B muestra con el foco del
        // teclado lo que A sólo mostraba al pasar el ratón.
        else if (key !== 'normal' && (pix.states || {})[key] && (pix.states[key].max !== undefined)) { if (!pix.states[key].reason) fail(`pixels.states.${key}.max sin «reason»`); else if (p.ratio > Number(pix.states[key].max)) fail(`píxeles ${width} ${key}: ${(p.ratio * 100).toFixed(2)} % distintos (umbral del estado ${(Number(pix.states[key].max) * 100).toFixed(2)} %; A ${p.a.join('×')}, B ${p.b.join('×')})`); }
        else if (p.ratio > (key === 'focus' && FOCUS_MAX > wmax(width) ? FOCUS_MAX : wmax(width))) fail(`píxeles ${width} ${key}: ${(p.ratio * 100).toFixed(2)} % distintos (umbral ${(wmax(width) * 100).toFixed(2)} %; A ${p.a.join('×')}, B ${p.b.join('×')})`);
      }
      w.signature = { a: sa, b: sb };
      w.states = { a: { hover: (wa.hover || {}).styles || null, focus: (wa.focus || {}).styles || null }, b: { hover: (wb.hover || {}).styles || null, focus: (wb.focus || {}).styles || null } };
      res.widths[width] = w;
    }
    for (const rule of declared) if (!rule.__used && (c.declared || []).includes(rule)) notes.push(`${slug}: declaración sin uso (${rule.kind || '*'} ${rule.match || ''})`);
    for (const rule of declared) delete rule.__used;
    res.summary = { declared: declaredCount, unexplained: unexplainedCount, pixels: Object.fromEntries(WIDTHS.map((w) => [w, ((res.widths[w].pixels.normal || {}).ratio ?? null)])) };
    const px = WIDTHS.map((w) => { const r = (res.widths[w].pixels.normal || {}).ratio; return `${w}:${r === undefined ? '—' : (r * 100).toFixed(2) + '%'}`; }).join(' ');
    console.log(`${own.length ? 'FALLA' : 'OK   '}  ${slug} → ${e.target || '—'} · píxeles ${px} · declaradas ${declaredCount}, sin motivo ${unexplainedCount}${own.length ? ` · ${own.length} fallas` : ''}`);
    for (const m of own.slice(0, 8)) console.log(`         ${m}`);
    if (own.length > 8) console.log(`         … y ${own.length - 8} más (pro-ab.json)`);
  }
  await browser.close();
  if (thirdAll.size) notes.push(`peticiones a terceros cortadas por el arnés (A y B): ${[...thirdAll].slice(0, 5).join(', ')}`);
  summary.duration_s = Math.round(Date.now() / 1000 - +process.env.STARTED);
  fs.writeFileSync(path.join(OUT, 'pro-ab.json'), JSON.stringify(summary, null, 1));
  for (const n of [...new Set(notes)].slice(0, 12)) console.log(`nota: ${n}`);
  console.log(problems.length ? `FALLA: ${problems.length} problemas en ${Object.values(summary.cases).filter((c) => c.problems.length).length} de ${slugs.length} casos (${path.join(OUT, 'pro-ab.json')})` : `OK: ${slugs.length} casos sin diferencias sin motivo (${path.join(OUT, 'pro-ab.json')})`);
  process.exit(problems.length ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
