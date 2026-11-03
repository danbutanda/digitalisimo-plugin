// P0 · A/B de Prime Slider Lite 4.7.0 oficial (f8.sh). Los 22 widgets de Lite con `defaults` y `custom` (44 páginas).
// No cabe en una llamada de 10 min, así que se parte en tandas (`P0_PART`, con `APPEND=1` y el mismo OUT); cada tanda
// prepara su sitio desde cero (la preparación es determinista y la comparación es por página, no por ID):
//
//   a · A con Lite activo: firma de cada página (texto, enlaces, imágenes y fondos), estilos computados de las partes
//       y capturas en 360, 768 y 1280 px. Se guarda en $OUT/P0-data/A.json.
//   b · «Migrar» por el AJAX de la pantalla (Lite se convierte activo y se retira al final, nunca se borra). B: la misma
//       firma, comparada con A; toda diferencia tiene que estar declarada (abajo, con su motivo y la pérdida del mapa
//       de la familia). Imágenes de demostración en Medios, ninguna URL a la carpeta de Lite, ningún perfil ni vídeo
//       del proveedor.
//   c · Lo que E y F dejaron para J: el slider convertido se abre y se guarda en el editor real sin perder `isInner` ni
//       `isLocked`; la proporción del slider con su pista en tres anchos; copias SVG en Medios (subida sin filtrar
//       encendida); el Loop Carousel generado pinta imagen destacada, título y botón dinámicos; la puerta de PRO deja lo
//       que necesitan los widgets generados.
//
// Sin P0_PART corre a, b y c seguidas (sólo cabe con la máquina libre).
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => async (s) => {
  const { OUT, RUN, check, pending, note, control, login, migrate, request, open, shot, vendorTrace, sleep, text } = api;
  const PARTS = (process.env.P0_PART || 'abc').split('').filter((p) => 'abc'.includes(p));
  const DATA = path.join(OUT, 'P0-data');
  fs.mkdirSync(DATA, { recursive: true });
  const WIDTHS = [360, 768, 1280];

  // Las comprobaciones de las otras tandas se conservan (el resumen guarda un escenario por nombre).
  try {
    const prev = JSON.parse(fs.readFileSync(path.join(OUT, 'summary.json'), 'utf8')).scenarios.P0;
    if (prev && prev.checks) {
      const prevPart = (prev.facts && prev.facts.parts) || '?';
      for (const c of prev.checks) {
        const mine = PARTS.some((p) => c.id.startsWith(`P0.${p.toUpperCase()}`));
        if (mine) continue;
        if (/^P0\.(0|z1|z2|x)$/.test(c.id)) c.id = `${c.id} · tanda ${prevPart}`;
        if (/ · tanda /.test(c.id) && PARTS.some((p) => c.id.endsWith(`tanda ${p}`))) continue;
        s.checks.push(c);
        if (c.status === 'falla') s.problems.push(`${c.id} · ${c.what}`);
      }
      Object.assign(s.facts, Object.fromEntries(Object.entries(prev.facts || {}).filter(([k]) => !['site', 'php_notices', 'php_notices_top', 'parts'].includes(k))));
    }
  } catch (e) { /* primera tanda */ }
  s.facts.parts = PARTS.join('');

  const m = await control('ids');
  const ps = await control('psdocs');
  const docs = ps.docs;
  const keys = Object.keys(docs).sort();
  const lite = [...new Set(keys.map((k) => docs[k].widget))];

  // Firma de la página (dentro del documento): textos, enlaces, imágenes y fondos, y estilos de las partes con texto.
  const signature = (page) => page.evaluate(() => {
    const root = document.querySelector('[data-elementor-type="wp-page"]') || document.querySelector('main') || document.body;
    const skip = (node) => node.parentElement && node.parentElement.closest('script,style,svg,noscript,template');
    const texts = [];
    const styles = {};
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    for (let node = walker.nextNode(); node; node = walker.nextNode()) {
      const value = node.nodeValue.replace(/\s+/g, ' ').trim();
      if (!value || skip(node)) continue;
      texts.push(value);
      if (!styles[value] && Object.keys(styles).length < 40) {
        const cs = getComputedStyle(node.parentElement);
        styles[value] = { tag: node.parentElement.tagName, size: cs.fontSize, weight: cs.fontWeight, color: cs.color, transform: cs.textTransform, spacing: cs.letterSpacing, family: cs.fontFamily.split(',')[0].replace(/"/g, '') };
      }
    }
    const here = location.origin;
    const links = [...root.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).filter((h) => h && !/^#|^javascript:/i.test(h)).map((h) => h.replace(here, '').replace(/\/$/, '') || '/');
    const base = (u) => String(u || '').split('?')[0].split('#')[0].split('/').pop();
    const images = [];
    for (const img of root.querySelectorAll('img')) images.push(base(img.currentSrc || img.getAttribute('src') || img.getAttribute('data-src')));
    for (const el of root.querySelectorAll('*')) {
      const bg = getComputedStyle(el).backgroundImage;
      if (bg && bg !== 'none') for (const mm of bg.matchAll(/url\("?([^")]+)"?\)/g)) images.push(base(mm[1]));
    }
    const media = [...root.querySelectorAll('iframe[src], video[src], video source[src]')].map((e) => e.getAttribute('src'));
    return { texts: [...new Set(texts)], links: [...new Set(links)], images: [...new Set(images.filter(Boolean))], media, styles };
  });

  async function capture(label) {
    const out = {};
    const shots = path.join(DATA, `shots-${label}`);
    fs.mkdirSync(shots, { recursive: true });
    for (const key of keys) {
      const v = await open(m.urls[key], { width: 1280, before: (pg) => pg.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]) });
      await sleep(400);
      const sig = await signature(v.page);
      sig.status = v.status;
      sig.errors = v.facts.errors.slice(0, 5);
      sig.failed = v.facts.failed.slice(0, 5);
      sig.outside = v.facts.outside.slice(0, 5);
      for (const width of WIDTHS) {
        await v.page.setViewport({ width, height: 900 });
        await sleep(250);
        await v.page.screenshot({ path: path.join(shots, `${key}-${width}.png`) }).catch(() => {});
      }
      out[key] = sig;
      await v.close();
    }
    return out;
  }

  // ── a · A con Lite activo ───────────────────────────────────────────────────────────────────────────────────
  if (PARTS.includes('a')) {
    check(s, 'P0.A1', `Lite oficial 4.7.0 activo y cargado, con sus 22 widgets registrados y 44 páginas (defaults y custom)`, ps.lite.active && ps.lite.loaded && ps.lite.version === '4.7.0' && lite.length === 22 && keys.length === 44 && lite.every((w) => ps.registered.includes(w)), JSON.stringify({ lite: ps.lite, widgets: lite.length, docs: keys.length, missing: lite.filter((w) => !ps.registered.includes(w)) }));
    const t0 = Date.now();
    const A = await capture('A');
    fs.writeFileSync(path.join(DATA, 'A.json'), JSON.stringify({ at: new Date().toISOString(), run: api.ENV.RUN_ID, docs, pages: A }, null, 1));
    const broken = Object.entries(A).filter(([, v]) => v.status !== 200);
    check(s, 'P0.A2', `A: las 44 páginas responden 200 con Lite (firma y capturas en 360, 768 y 1280 px en ${Math.round((Date.now() - t0) / 1000)} s)`, !broken.length, JSON.stringify(broken.map(([k, v]) => `${k}: ${v.status}`)));
    const empty = Object.entries(A).filter(([, v]) => !v.texts.length && !v.images.length).map(([k]) => k);
    check(s, 'P0.A3', 'A: cada widget de Lite pinta contenido (texto o imágenes)', !empty.length, JSON.stringify(empty));
    const out = Object.fromEntries(Object.entries(A).filter(([, v]) => v.outside.length).map(([k, v]) => [k, v.outside]));
    s.facts.a_outside = Object.keys(out).length;
    note(s, `A: ${Object.keys(out).length} páginas de Lite intentaron recursos fuera del sitio (el navegador no sale): ${JSON.stringify(out).slice(0, 300)}`);
    const jsA = Object.entries(A).filter(([, v]) => v.errors.length).map(([k, v]) => `${k}: ${v.errors[0]}`);
    if (jsA.length) note(s, `A: errores de JavaScript con Lite (del origen, no de la migración): ${jsA.slice(0, 4).join(' | ')}`);
  }

  // ── «Migrar» (b y c) ──────────────────────────────────────────────────────────────────────────────────────────
  let migrated = false;
  async function doMigrate(prefix) {
    if (migrated) return;
    await login('admin');
    const t0 = Date.now();
    const res = await migrate('admin');
    fs.writeFileSync(path.join(RUN, `steps-${prefix}.json`), JSON.stringify(res.steps, null, 1));
    const last = res.last && res.last.data;
    check(s, `${prefix}1`, `«Migrar» termina por el AJAX de la pantalla (${res.steps.length} pasos, ${Math.round((Date.now() - t0) / 1000)} s) sin errores`, !!(last && last.done) && res.steps.every((x) => x.ok), JSON.stringify(res.steps.filter((x) => !x.ok).slice(0, 3).concat([res.steps[res.steps.length - 1]])));
    migrated = true;
  }

  // ── b · B tras migrar y comparación con A ─────────────────────────────────────────────────────────────────────
  if (PARTS.includes('b')) {
    let A = null;
    try { A = JSON.parse(fs.readFileSync(path.join(DATA, 'A.json'), 'utf8')); } catch (e) { /* sin A */ }
    await doMigrate('P0.B');
    const st = await control('psdocs');
    check(s, 'P0.B2', 'Lite desactivado al final (se convirtió activo y se retiró en la etapa tardía) y nunca borrado', !st.lite.active && st.lite.installed, JSON.stringify(st.lite));
    const info = (await control('psinfo', { full: '1' })).docs;
    const left = keys.filter((k) => !info[k] || info[k].legacy || info[k].markers);
    check(s, 'P0.B3', 'ningún widget de Lite queda en los 44 documentos (sin marcadores: los 22 tienen destino)', !left.length, JSON.stringify(left.map((k) => [k, info[k] && info[k].widgets])));
    const rawTrace = keys.filter((k) => info[k] && /\/plugins\/bdthemes-prime-slider/.test(JSON.stringify(info[k].data)));
    check(s, 'P0.B4', 'ningún documento convertido guarda una URL a la carpeta de Lite', !rawTrace.length, JSON.stringify(rawTrace));
    const media = (await control('psmedia')).media;
    const demo = media.filter((x) => /_digitalisimo_migration_demo/.test(x.meta));
    check(s, 'P0.B5', `imágenes de demostración copiadas a Medios (${demo.length}), con archivo y sin script`, demo.length > 0 && demo.every((x) => x.exists && !x.script && /\/uploads\//.test(x.url)), JSON.stringify(demo.slice(0, 6)));
    fs.writeFileSync(path.join(RUN, 'media.json'), JSON.stringify(media, null, 1));
    const t0 = Date.now();
    const B = await capture('B');
    fs.writeFileSync(path.join(DATA, 'B.json'), JSON.stringify({ at: new Date().toISOString(), run: api.ENV.RUN_ID, pages: B, info: Object.fromEntries(keys.map((k) => [k, info[k] && info[k].widgets])) }, null, 1));
    const broken = Object.entries(B).filter(([, v]) => v.status !== 200 || v.errors.length || v.failed.length);
    check(s, 'P0.B6', `B: las 44 páginas responden 200 sin errores de JavaScript ni recursos rotos (${Math.round((Date.now() - t0) / 1000)} s, capturas en tres anchos)`, !broken.length, JSON.stringify(broken.map(([k, v]) => ({ k, status: v.status, errors: v.errors, failed: v.failed })).slice(0, 6)));
    const trace = {};
    for (const key of keys) {
      const r = await request('GET', m.urls[key]);
      const found = vendorTrace(r.text);
      if (found.length) trace[key] = found;
    }
    check(s, 'P0.B7', 'B: ninguna página nombra un dominio, perfil, vídeo o carpeta del proveedor (HTML del servidor)', !Object.keys(trace).length, JSON.stringify(trace));
    const outsideB = Object.entries(B).filter(([, v]) => v.outside.length);
    check(s, 'P0.B8', 'B: el navegador no pide nada fuera del sitio', !outsideB.length, JSON.stringify(outsideB.map(([k, v]) => [k, v.outside])).slice(0, 600));
    if (!A) {
      pending(s, 'P0.B9', 'A/B: diferencias sólo las declaradas por familia', 'falta $OUT/P0-data/A.json (corre antes P0_PART=a con el mismo OUT)');
    } else {
      const diff = compare(A.pages, B, docs);
      fs.writeFileSync(path.join(DATA, 'diff.json'), JSON.stringify(diff, null, 1));
      const unexplained = Object.entries(diff).filter(([, d]) => d.unexplained.length);
      s.facts.ab = { pages: keys.length, declared: Object.values(diff).reduce((n, d) => n + d.declared.length, 0), unexplained: unexplained.length };
      check(s, 'P0.B9', `A/B de las 44 páginas: toda diferencia de texto, enlace o imagen está declarada (${s.facts.ab.declared} declaradas)`, !unexplained.length, JSON.stringify(unexplained.slice(0, 6).map(([k, d]) => [k, d.unexplained.slice(0, 6)])));
      const styleDiff = Object.values(diff).reduce((n, d) => n + d.styles, 0);
      note(s, `A/B: ${styleDiff} textos con estilo computado distinto; los estilos propios de cada widget no se trasladan (ps.loss.styles, informado por documento)`);
    }
  }

  // ── c · Editor, proporción, SVG, Loop Carousel y puerta de PRO ────────────────────────────────────────────────
  if (PARTS.includes('c')) {
    await doMigrate('P0.C');
    const info = (await control('psinfo', { full: '1' })).docs;
    const walkEls = (els, out = []) => { for (const e of els || []) { out.push(e); walkEls(e.elements, out); } return out; };
    // Slider anidado convertido (familia A, caso custom).
    const sliderKey = keys.find((k) => docs[k].family === 'A' && docs[k].case === 'custom' && info[k] && info[k].widgets['digitalisimo-nested-slider']);
    if (!sliderKey) {
      pending(s, 'P0.C2', 'el slider convertido en el editor real', 'ningún documento de la familia A con el slider anidado');
    } else {
      const id = m.ids[sliderKey];
      const flags = (data) => walkEls(data).filter((e) => e.elType === 'widget' && e.widgetType === 'digitalisimo-nested-slider').map((sl) => (sl.elements || []).map((c) => `${c.elType}:${c.isInner ? 'inner' : '-'}:${c.isLocked ? 'locked' : '-'}`));
      const before = flags(info[sliderKey].data);
      const ed = await openEditor(api, id);
      const model = ed.ok ? await ed.page.evaluate(() => { const doc = window.elementor.getPreviewContainer(); const out = []; const walk = (c) => { for (const ch of c.children || []) { const m = ch.model; if (m.get('widgetType') === 'digitalisimo-nested-slider') out.push((ch.children || []).map((x) => `${x.model.get('elType')}:${x.model.get('isInner') ? 'inner' : '-'}:${x.model.get('isLocked') ? 'locked' : '-'}`)); walk(ch); } }; walk(doc); return out; }).catch((e) => String(e)) : null;
      const preview = ed.ok ? await ed.frame.evaluate(() => { const r = document.querySelector('.digi-nslider'); return r ? { slides: r.querySelectorAll('.digi-nslider__track > .e-con').length, h: Math.round(r.getBoundingClientRect().height) } : null; }).catch(() => null) : null;
      const saved = ed.ok ? await ed.page.evaluate(async () => { try { await window.$e.run('document/save/update', { force: true }); return { ok: true }; } catch (e) { return { ok: false, error: String(e && e.message || e) }; } }) : { ok: false };
      const errs = ed.ok ? ed.facts.errors.filter((x) => !/Failed to load resource/.test(x)) : [ed.error];
      if (ed.ok) await shot(ed.page, `p0-editor-${sliderKey}`);
      if (ed.close) await ed.close();
      const after = flags((await control('doc', { key: sliderKey, full: '1' })).doc.data);
      const allIn = (list) => Array.isArray(list) && list.length > 0 && list.every((sl) => sl.length > 0 && sl.every((c) => c === 'container:inner:locked'));
      check(s, 'P0.C2a', `el slider convertido (${sliderKey}) nace con contenedores hijos isInner e isLocked`, allIn(before), JSON.stringify(before));
      check(s, 'P0.C2b', 'el editor real lo abre con esos indicadores y pinta sus diapositivas en la vista previa', allIn(model) && preview && preview.slides > 0 && preview.h > 0, JSON.stringify({ model, preview, errors: errs.slice(0, 3) }));
      check(s, 'P0.C2c', 'guardar desde el editor conserva isInner e isLocked y el front sigue pintando el slider', saved.ok && allIn(after) && /digi-nslider/.test((await request('GET', m.urls[sliderKey])).text), JSON.stringify({ saved, after }));
      // Proporción con su pista (tres anchos).
      const ratio = [];
      for (const width of WIDTHS) {
        const v = await open(m.urls[sliderKey], { width, before: (pg) => pg.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]) });
        ratio.push(await v.page.evaluate((w) => {
          const r = document.querySelector('.digi-nslider');
          const t = r && r.querySelector(':scope>.digi-nslider__track');
          const sl = t ? [...t.children].filter((x) => x.classList.contains('e-con')) : [];
          const box = (e) => e.getBoundingClientRect();
          return r ? { w, ratio: getComputedStyle(t).aspectRatio + ' | ' + getComputedStyle(sl[0] || t).aspectRatio, minH: getComputedStyle(sl[0] || t).minHeight, track: [Math.round(box(t).width), Math.round(box(t).height)], slide: sl[0] ? [Math.round(box(sl[0]).width), Math.round(box(sl[0]).height)] : null, scroll: document.documentElement.scrollWidth <= innerWidth } : null;
        }, width));
        await shot(v.page, `p0-ratio-${sliderKey}-${width}`);
        await v.close();
      }
      const okRatio = ratio.every((x) => x && x.slide && x.track[1] > 0 && Math.abs(x.slide[1] - x.track[1]) <= 2 && Math.abs(x.slide[0] - x.track[0]) <= 2 && x.scroll);
      check(s, 'P0.C3', 'la proporción del slider: cada diapositiva ocupa la pista entera en 360, 768 y 1280 px, sin desbordar la página', okRatio, JSON.stringify(ratio));
    }
    // Copias SVG de las imágenes de demostración (subida sin filtrar encendida).
    const media = (await control('psmedia')).media;
    const svg = media.filter((x) => x.mime === 'image/svg+xml');
    check(s, 'P0.C4', `las imágenes de demostración SVG pasan wp_check_filetype_and_ext() real y quedan en Medios saneadas (${svg.length})`, svg.length > 0 && svg.every((x) => x.exists && !x.script), JSON.stringify(svg.slice(0, 5)));
    // Loop Carousel generado (familias E, F y G).
    const loopKeys = keys.filter((k) => info[k] && info[k].widgets['loop-carousel'] && docs[k].case === 'defaults');
    const loopBad = {};
    const loopSeen = {};
    for (const key of loopKeys) {
      const v = await open(m.urls[key], { before: (pg) => pg.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]) });
      const facts = await v.page.evaluate(() => {
        const w = document.querySelector('.elementor-widget-loop-carousel');
        if (!w) return null;
        const items = [...w.querySelectorAll('.e-loop-item')];
        return {
          items: items.length,
          init: !!w.querySelector('.swiper-initialized, .swiper-wrapper'),
          titles: items.map((i) => (i.querySelector('h1,h2,h3,h4,h5,h6,.elementor-heading-title') || { textContent: '' }).textContent.trim()).slice(0, 4),
          images: items.map((i) => ((i.querySelector('img') || {}).currentSrc || '').split('/').pop()).slice(0, 4),
          buttons: items.map((i) => { const a = i.querySelector('a.elementor-button, .elementor-button-link, a[href]'); return a ? a.getAttribute('href') : ''; }).slice(0, 4),
        };
      });
      loopSeen[key] = facts;
      const dynamic = facts && facts.items > 0 && facts.titles.some((t) => /Noticia F8|F8$/.test(t)) && facts.images.some((i) => /noticia-|producto-/.test(i)) && facts.buttons.some((h) => /digi-f8-noticia-|\/product\/|producto|camisa|gorra|taza|libro/i.test(h));
      if (!dynamic || v.facts.errors.length) loopBad[key] = { facts, errors: v.facts.errors.slice(0, 3) };
      await shot(v.page, `p0-loop-${key}`);
      await v.close();
    }
    fs.writeFileSync(path.join(RUN, 'loop.json'), JSON.stringify(loopSeen, null, 1));
    if (!loopKeys.length) pending(s, 'P0.C5', 'Loop Carousel generado con datos dinámicos', 'ningún documento convertido a loop-carousel');
    else check(s, 'P0.C5', `el Loop Carousel generado (${loopKeys.length} páginas) pinta título, imagen destacada y botón dinámicos de cada entrada o producto, sin errores de JavaScript`, !Object.keys(loopBad).length, JSON.stringify(loopBad).slice(0, 1500));
    // Puerta de PRO: los paquetes que piden los widgets generados llegan al front.
    if (loopKeys.length) {
      const html = (await request('GET', m.urls[loopKeys[0]])).text;
      const scripts = [...html.matchAll(/<script[^>]*id="([^"]+)"/g)].map((x) => x[1]);
      const gate = scripts.filter((x) => /elementor-pro|pro-frontend|loop|swiper/i.test(x));
      check(s, 'P0.C6', 'la puerta de PRO no retira lo que pide el Loop Carousel generado (paquetes de PRO y Swiper en la página, carrusel iniciado)', gate.length > 0 && Object.values(loopSeen).every((f) => f && f.init), JSON.stringify({ gate, scripts: scripts.length }));
    }
  }
};

/** Comparación A/B por página con las diferencias declaradas (motivo y familia). */
function compare(A, B, docs) {
  const out = {};
  const stem = (b) => String(b || '').toLowerCase().replace(/\.(png|jpe?g|gif|webp|svg)$/, '').replace(/-\d+x\d+$/, '').replace(/-scaled$/, '');
  for (const key of Object.keys(docs)) {
    const a = A[key];
    const b = B[key];
    const d = { family: docs[key].family, widget: docs[key].widget, declared: [], unexplained: [], styles: 0 };
    out[key] = d;
    if (!a || !b) { d.unexplained.push('sin firma A o B'); continue; }
    const fam = docs[key].family;
    const bText = new Set(b.texts);
    const bLinks = new Set(b.links);
    const bImg = b.images.map(stem);
    for (const t of a.texts) {
      if (bText.has(t)) { if (a.styles[t] && b.styles[t] && JSON.stringify(a.styles[t]) !== JSON.stringify(b.styles[t])) d.styles++; continue; }
      const why = explainText(t, fam, docs[key]);
      (why ? d.declared : d.unexplained).push(why ? `texto «${t}»: ${why}` : `texto perdido «${t}»`);
    }
    for (const l of a.links) {
      if (bLinks.has(l)) continue;
      const why = explainLink(l);
      (why ? d.declared : d.unexplained).push(why ? `enlace ${l}: ${why}` : `enlace perdido ${l}`);
    }
    for (const i of a.images) {
      const st = stem(i);
      if (bImg.some((x) => x === st || x.startsWith(st + '-'))) continue;
      const why = explainImage(i, fam);
      (why ? d.declared : d.unexplained).push(why ? `imagen ${i}: ${why}` : `imagen perdida ${i}`);
    }
    const aText = new Set(a.texts);
    for (const t of b.texts) {
      if (aText.has(t)) continue;
      const why = explainExtra(t);
      (why ? d.declared : d.unexplained).push(why ? `texto nuevo «${t}»: ${why}` : `texto nuevo «${t}»`);
    }
  }
  return out;
}
// Motivos declarados (se completan tras ver cada diferencia; cada uno cita su origen).
function explainText(t, fam) {
  if (/^(facebook|twitter|instagram|linkedin|youtube|x|behance|dribbble|pinterest)$/i.test(t)) return 'red del perfil del proveedor retirada (Sanitizer, «ningún perfil del proveedor»)';
  if (/^(0?\d{1,2}|\/|\d+\s*\/\s*\d+)$/.test(t)) return 'navegación numerada del origen (pérdida declarada de la familia)';
  return '';
}
function explainLink(l) {
  if (/^https?:\/\/(www\.)?(facebook|twitter|instagram|linkedin|youtube)\.com\/[a-z]+\/?$/i.test(l)) return 'perfil del proveedor retirado (Sanitizer)';
  if (/youtu\.?be|vimeo/i.test(l)) return 'vídeo de demostración del proveedor retirado (Sanitizer)';
  return '';
}
function explainImage(i, fam) {
  return '';
}
function explainExtra(t) {
  return '';
}

/** Editor del constructor con la sesión de admin (como openEditor de f7b/run.cjs): `{ ok, page, frame, facts, close }`. */
async function openEditor(api, id) {
  const { context, newPage, BASE, sleep } = api;
  const ctx = await context('admin');
  const page = await newPage(ctx, { width: 1640, height: 1000 });
  try {
    await page.goto(`${BASE}/wp-admin/post.php?post=${id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 240000 });
    await page.waitForSelector('#elementor-panel', { timeout: 180000 });
    const frame = await (await page.waitForSelector('#elementor-preview-iframe', { timeout: 180000 })).contentFrame();
    await frame.waitForSelector('.elementor-element', { timeout: 180000 }).catch(() => {});
    await page.waitForFunction(() => { const l = document.querySelector('#elementor-loading'); return window.elementor && window.$e && (!l || getComputedStyle(l).display === 'none'); }, { timeout: 240000 }).catch(() => {});
    await sleep(2500);
    return { ok: true, page, frame, facts: page.facts, close: () => ctx.close().catch(() => {}) };
  } catch (e) {
    await ctx.close().catch(() => {});
    return { ok: false, error: e.message, close: null };
  }
}
