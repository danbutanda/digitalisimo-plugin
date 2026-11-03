// P1 · Prime Slider Pro sintético (f8.sh): los 35 IDs de Pro en documentos (casos `defaults` y `custom`) con Lite oficial
// activo y sin Pro instalado. «Migrar» por el AJAX de la pantalla: conversión sin errores de PHP, cada destino pinta
// (o el respaldo declarado), los descartes declarados quedan como marcadores, nada del proveedor en lo convertido,
// Lite se retira al final sin borrarse, y el slider anidado responde al teclado y respeta el movimiento reducido.
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => async (s) => {
  const { RUN, check, pending, note, control, login, migrate, request, open, shot, vendorTrace, sleep } = api;
  const m = await control('ids');
  const before = await control('psdocs');
  const docs = before.docs;
  const keys = Object.keys(docs).sort();
  const pro = [...new Set(keys.map((k) => docs[k].widget))];
  check(s, 'P1.1a', `documentos con los 35 IDs de Pro (${keys.length} páginas: defaults y custom)`, pro.length === 35 && keys.length === 70, JSON.stringify({ ids: pro.length, docs: keys.length }));
  check(s, 'P1.1b', 'Lite oficial 4.7.0 activo y cargado; ningún widget de Pro registrado (Pro no está instalado)', before.lite.active && before.lite.loaded && before.lite.version === '4.7.0' && !before.registered.some((t) => pro.includes(t)), JSON.stringify({ lite: before.lite, registered: before.registered.length }));

  await login('admin');
  const t0 = Date.now();
  const res = await migrate('admin');
  fs.writeFileSync(path.join(RUN, 'steps.json'), JSON.stringify(res.steps, null, 1));
  const last = res.last && res.last.data;
  check(s, 'P1.2', `«Migrar» termina por el AJAX de la pantalla (${res.steps.length} pasos, ${Math.round((Date.now() - t0) / 1000)} s) sin respuestas de error`, !!(last && last.done) && res.steps.every((x) => x.ok), JSON.stringify(res.steps.filter((x) => !x.ok).slice(0, 3).concat([res.steps[res.steps.length - 1]])));

  // Documento a documento (una sola petición): sin widgets de Pro sueltos; descartes declarados como marcadores.
  const info = (await control('psinfo')).docs;
  const rows = {};
  const loose = [];
  const declaredOk = [];
  const isDeclared = (k) => docs[k].decision === 'DESCARTAR' || docs[k].widget === 'prime-slider-event-calendar';
  for (const key of keys) {
    const d = info[key];
    if (!d) { loose.push(`${key}: sin documento`); continue; }
    const ps = Object.keys(d.widgets).filter((w) => /^prime-slider-/.test(w));
    rows[key] = { widgets: d.widgets, markers: d.markers, legacy: d.legacy };
    if (isDeclared(key)) { if (d.markers >= 1) declaredOk.push(key); else loose.push(`${key}: sin marcador`); continue; }
    if (ps.length) loose.push(`${key}: ${ps.join(',')}`);
    if (d.markers) loose.push(`${key}: marcador inesperado`);
  }
  fs.writeFileSync(path.join(RUN, 'docs-after.json'), JSON.stringify(rows, null, 1));
  check(s, 'P1.3a', 'ningún widget de Pro queda sin convertir (salvo los descartes declarados)', !loose.length, JSON.stringify(loose.slice(0, 10)));
  const declaredKeys = keys.filter(isDeclared);
  check(s, 'P1.3b', `los descartes declarados (familia M y event-calendar sin The Events Calendar) quedan como marcadores (${declaredOk.length}/${declaredKeys.length})`, declaredOk.length === declaredKeys.length && declaredKeys.length === 10, JSON.stringify(declaredKeys));

  // Cada documento pinta su destino (o el respaldo declarado) sin excepciones ni rastro del proveedor: el HTML del
  // constructor de los 70, en lotes (una petición por lote); después Chrome con un documento de cada destino.
  const bad = [];
  const trace = {};
  const targets = {};
  const html = {};
  for (let i = 0; i < keys.length; i += 15) {
    const batch = keys.slice(i, i + 15);
    const out = (await control('psrender', { keys: batch.join(',') })).render;
    for (const key of batch) {
      const r = out[key] || { error: 'sin respuesta' };
      if (r.error) { bad.push(`${key}: ${r.error}`); continue; }
      html[key] = r.html;
      const found = vendorTrace(r.html);
      if (found.length) trace[key] = found;
      if (isDeclared(key)) { if (r.bytes > 0 && /digitalisimo-unconverted/.test(r.html)) bad.push(`${key}: el marcador pinta algo`); continue; }
      const t = docs[key];
      // Familia H: una galería con texto pasa a Logotipos en carrusel (V.19.3 a, Static_Families::plan).
      if (t.family === 'H' && /elementor-widget-digitalisimo-logos\b/.test(r.html)) { targets[key] = 'digitalisimo-logos (galería con texto)'; continue; }
      const want = t.target === 'container' ? /class="[^"]*\be-con\b/ : new RegExp(`elementor-widget-${t.target}\\b`);
      const fb = t.fallback ? new RegExp(`elementor-widget-${t.fallback}\\b`) : null;
      const hit = want.test(r.html) ? t.target : (fb && fb.test(r.html) ? `${t.fallback} (respaldo)` : '');
      targets[key] = hit;
      if (!hit) bad.push(`${key}: sin ${t.target}`);
    }
  }
  fs.writeFileSync(path.join(RUN, 'targets.json'), JSON.stringify(targets, null, 1));
  check(s, 'P1.4a', 'cada documento convertido pinta su destino del mapa (o su respaldo), sin excepciones', !bad.length, JSON.stringify(bad.slice(0, 12)));
  const fallbacks = Object.entries(targets).filter(([, v]) => /respaldo/.test(v));
  if (fallbacks.length) note(s, `con respaldo: ${fallbacks.map(([k, v]) => `${k} → ${v}`).join(', ')}`);
  check(s, 'P1.4b', 'ningún documento convertido nombra un dominio, perfil, vídeo o carpeta del proveedor', !Object.keys(trace).length, JSON.stringify(trace));

  const st = await control('psdocs');
  check(s, 'P1.5', 'Prime Slider (Lite) desactivado al final (retirada tardía) y nunca borrado', !st.lite.active && st.lite.installed, JSON.stringify(st.lite));

  // Chrome: un destino de cada tipo, sin errores de JavaScript ni recursos rotos.
  const sample = {};
  for (const key of keys) { const tg = targets[key]; if (tg && !sample[tg]) sample[tg] = key; }
  const jsBad = {};
  for (const [tg, key] of Object.entries(sample)) {
    const v = await open(m.urls[key], { before: (pg) => pg.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]) });
    if (v.status !== 200 || v.facts.errors.length || v.facts.failed.length) jsBad[key] = { status: v.status, errors: v.facts.errors.slice(0, 3), failed: v.facts.failed.slice(0, 3) };
    await shot(v.page, `p1-${key}`);
    await v.close();
  }
  check(s, 'P1.6', `un destino de cada tipo (${Object.keys(sample).length}) responde 200 y pinta en Chrome sin errores de JavaScript ni recursos rotos`, !Object.keys(jsBad).length, JSON.stringify({ sample, jsBad }));

  // Slider anidado: teclado y movimiento reducido.
  const nestedKey = keys.find((k) => targets[k] === 'digitalisimo-nested-slider' && docs[k].case === 'custom' && /<button[^>]*digi-nslider__dot/.test(html[k] || '')) || keys.find((k) => targets[k] === 'digitalisimo-nested-slider');
  if (!nestedKey) { pending(s, 'P1.7', 'slider anidado con teclado y movimiento reducido', 'ningún documento convertido al slider anidado'); return; }
  const state = (pg) => pg.evaluate(() => {
    const r = document.querySelector('.digi-nslider');
    if (!r) return null;
    const t = r.querySelector(':scope>.digi-nslider__track');
    const slides = [...t.children].filter((x) => x.classList.contains('e-con') || x.classList.contains('elementor-element'));
    const dotsBox = r.querySelector(':scope>.digi-nslider__dots');
    const dots = dotsBox ? [...dotsBox.children] : [];
    const rt = t.getBoundingClientRect();
    return {
      n: slides.length,
      ready: r.classList.contains('digi-nslider--ready'),
      current: dots.map((d) => (d.getAttribute('aria-current') === 'true' ? 'X' : '-')).join(''),
      visible: slides.map((x) => { const b = x.getBoundingClientRect(); const cs = getComputedStyle(x); return Math.abs(b.left - rt.left) < 2 && cs.visibility !== 'hidden' && +cs.opacity > 0.5 ? 'X' : '-'; }).join(''),
      inert: slides.map((x) => (x.inert ? '-' : 'X')).join(''),
      pause: (r.querySelector(':scope>.digi-nslider__pause') || { getAttribute: () => null }).getAttribute('aria-label'),
      live: t.getAttribute('aria-live'),
    };
  });
  const settle = (pg) => pg.waitForFunction(() => [...document.querySelectorAll('.digi-nslider__track')].every((t) => { const v = t.scrollLeft; const same = t.__last === v; t.__last = v; return same; }), { polling: 150, timeout: 5000 }).catch(() => {});
  const v = await open(m.urls[nestedKey], { before: (pg) => pg.emulateMediaFeatures([{ name: 'prefers-reduced-motion', value: 'reduce' }]) });
  await v.page.waitForFunction(() => document.querySelector('.digi-nslider--ready'), { timeout: 20000 }).catch(() => {});
  const s0 = await state(v.page);
  await sleep(3500);
  const s1 = await state(v.page);
  check(s, 'P1.7a', `movimiento reducido (${nestedKey}): no rota solo y, si tiene reproducción automática, ofrece «Reanudar»`, !!s0 && s0.ready && s1.visible === s0.visible && (s0.pause === null || s0.pause === 'Reanudar') && s0.live === 'polite', JSON.stringify({ s0, s1 }));
  // El primer punto visible (o, sin puntos, «Diapositiva siguiente»).
  const control0 = await v.page.evaluate(() => {
    const r = document.querySelector('.digi-nslider');
    const dot = [...r.querySelectorAll('.digi-nslider__dot')].find((d) => d.offsetParent);
    if (dot) { dot.setAttribute('data-f8-focus', '1'); return 'dot'; }
    const next = [...r.querySelectorAll('button')].find((b) => b.offsetParent && /siguiente/i.test(b.getAttribute('aria-label') || ''));
    if (next) { next.setAttribute('data-f8-focus', '1'); return 'next'; }
    return '';
  });
  if (control0 === 'dot') {
    await v.page.focus('[data-f8-focus]');
    await v.page.keyboard.press('ArrowRight');
    await settle(v.page);
    const k1 = await state(v.page);
    await v.page.keyboard.press('End');
    await settle(v.page);
    const k2 = await state(v.page);
    const active = await v.page.evaluate(() => (document.activeElement && document.activeElement.className) || '');
    check(s, 'P1.7b', 'teclado: flecha derecha y Fin sobre los puntos cambian de diapositiva (aria-current e inert al día)', k1.current.indexOf('X') === 1 && k2.current.endsWith('X') && k2.inert === k2.current, JSON.stringify({ s0, k1, k2, active }));
  } else if (control0 === 'next') {
    await v.page.focus('[data-f8-focus]');
    await v.page.keyboard.press('Enter');
    await settle(v.page);
    const k1 = await state(v.page);
    check(s, 'P1.7b', 'teclado: Intro sobre «Diapositiva siguiente» pasa a la segunda', k1.visible.indexOf('X') === 1, JSON.stringify({ s0, k1 }));
  } else {
    pending(s, 'P1.7b', 'teclado del slider anidado', `${nestedKey} sin puntos ni flechas visibles`);
  }
  check(s, 'P1.7c', 'sin errores de JavaScript en el slider anidado', !v.facts.errors.length, JSON.stringify(v.facts.errors.slice(0, 3)));
  await shot(v.page, `p1-teclado-${nestedKey}`);
  await v.close();
  note(s, `pasos de «Migrar»: ${res.steps.length}; etapas: ${[...new Set(res.steps.map((x) => x.stage).filter(Boolean))].join(', ')}`);
};
