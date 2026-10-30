// B12 de f7b.sh: vuelta a 4.3.0.168 (el ZIP publicado de dist/, comprobado por su sha256 en f7b.sh) instalada sobre el
// resultado, como una actualización in situ. Las tres regresiones declaradas de V.11 (lo oculto vuelve a verse, los
// campos condicionales reaparecen y obligatorios, `{post:title}` literal), nada roto, y los widgets y ajustes nuevos
// conservados al guardar desde el editor de 4.3.0.168 (guarda hacia delante desde 4.3.0.166).
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

module.exports = (api) => {
  const { ENV, OUT, check, pending, note, control, request, open, ids, url, login, openEditor, editorSave, elementHtml, loopIds, sleep, shot, logSize, serverLog } = api;
  const text = (html) => String(html || '').replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/g, ' ').replace(/<[^>]+>/g, ' ').replace(/&#0?38;|&amp;/g, '&').replace(/\s+/g, ' ').trim();
  const find = (els, id) => { for (const e of els || []) { if (e.id === id) return e; const f = find(e.elements, id); if (f) return f; } return null; };

  async function B12(s) {
    const m = await ids(true);
    if (!ENV.OLD_ELEMENTS || !fs.existsSync(path.join(ENV.OLD_ELEMENTS, 'pro-elements.php'))) { check(s, 'B12.0', 'el ZIP 4.3.0.168 está extraído', false, ENV.OLD_ELEMENTS); return; }
    const dst = ENV.SRC_ELEMENTS;
    if (!dst.startsWith(OUT + '/')) { check(s, 'B12.0', 'la carpeta montada es la copia fija de la corrida', false, dst); return; }
    const before = {};
    for (const key of ['b1-actions', 'b11-slider', 'b5-filters', 'b10-form']) before[key] = (await control('data', { doc: key })).elements;
    const fatal0 = logSize('fatal.log');
    const notice0 = logSize('php-errors.log');
    execFileSync('bash', ['-c', `find "${dst}" -mindepth 1 -maxdepth 1 -exec rm -rf {} + && cp -a "${ENV.OLD_ELEMENTS}/." "${dst}/"`]);
    const ping = await control('ping');
    check(s, 'B12.1', '4.3.0.168 cargado (sin F7b ni el módulo neutro de filtros)', !ping.f7b && !ping.filters && /Version: 4\.3\.0\.168/.test(fs.readFileSync(path.join(dst, 'pro-elements.php'), 'utf8')), JSON.stringify({ f7b: ping.f7b, filters: ping.filters, core: ping.core }));
    await control('clearcss');
    const pages = {};
    const bad = [];
    for (const key of Object.keys(m.docs)) {
      if (/^(f7b-loop|b1-popup|b3-single|b4-free|b6-|b8-tpl|b8-outer|b9-header)/.test(key) || !m.urls[key]) continue;
      const r = await request('GET', m.urls[key]);
      pages[key] = r.text;
      if (r.status !== 200 || /critical error|Fatal error/i.test(r.text)) bad.push(`${key}: ${r.status}`);
    }
    for (const key of ['p3', 'b6']) {
      const r = await request('GET', key === 'p3' ? m.urls.p3 : '/category/digi-f7b-a/');
      if (r.status !== 200 || /critical error|Fatal error/i.test(r.text)) bad.push(`${key}: ${r.status}`);
    }
    check(s, 'B12.2', 'cada página, la entrada individual y el archivo responden 200 sin error de PHP', !bad.length, bad.join(', '));
    // V.11: las tres regresiones visibles declaradas.
    const v = await open(m.urls['b1-actions']);
    const shown = await v.page.evaluate(() => ['b1pan01', 'b1tip01', 'b1grd01', 'b1wid01', 'b1col01'].map((id) => { const el = document.querySelector(`.elementor-element-${id}`); return [id, el ? getComputedStyle(el).display : null, el ? el.hidden : null]; }));
    check(s, 'B12.3a', 'V.11: lo que estaba «Oculto al cargar» vuelve a verse (sin `hidden` ni acciones)', shown.every(([, d, h]) => d && d !== 'none' && h === false) && !/data-digi-actions/.test(v.html), JSON.stringify(shown));
    check(s, 'B12.3a2', 'y la página de acciones no tiene errores de JavaScript', !v.facts.errors.length, JSON.stringify(v.facts.errors.slice(0, 3)));
    await v.close();
    const form = pages['b10-form'] || '';
    const rfc = (form.match(/<div class="[^"]*elementor-field-group-rfc\b[^"]*"[^>]*>/) || [''])[0];
    const rfcInput = (form.match(/<input[^>]*name="form_fields\[rfc\]"[^>]*>/) || [''])[0];
    check(s, 'B12.3b', 'V.11: los campos condicionales reaparecen y obligatorios', !/hidden/.test(rfc) && /required/.test(rfcInput) && !/disabled/.test(rfcInput) && !/forms-conditions/.test(form), `${rfc} ${rfcInput}`);
    check(s, 'B12.3c', 'V.11: `{post:title}` se imprime literal', text(elementHtml(pages['b9-tags'] || '', 'b9hd001')) === 'Hola {post:title}', text(elementHtml(pages['b9-tags'] || '', 'b9hd001')));
    const b8 = text(elementHtml(pages['b8-page'] || '', 'b8tpl01'));
    check(s, 'B12.3d', 'el primer Template pinta la plantilla sin sobrescribir y «oculto» vuelve a verse (declarado en el aviso de «Ocultar»)', /Título original/.test(b8) && /Se oculta con hide/.test(b8) && !/Nuevo/.test(b8), b8.slice(0, 200));
    const loop = loopIds(pages['b3-compare'] || '', 'b3lsav1');
    check(s, 'B12.3e', 'el bucle con consulta guardada vuelve a sus ajustes propios (el grupo de PRO por defecto: entradas recientes) sin romper', Array.isArray(loop) && loop.length > 0, JSON.stringify(loop));
    check(s, 'B12.3f', 'el slider anidado y los filtros nuevos no pintan nada (widgets desconocidos) y no rompen la página', !/digi-nslider/.test(pages['b11-slider'] || '') && !/digi-filter--search/.test(pages['b5-filters'] || '') && /Después de los sliders/.test(pages['b11-slider'] || ''));
    // Bucle en bucle con el grupo de PRO: la limitación de B3.3b ya estaba en 4.3.0.168.
    const ctrl = await open(m.urls['b3-nested-pro']);
    const outerPro = await ctrl.page.evaluate(() => { const root = document.querySelector('.elementor-element-b3outp1'); return [...root.querySelectorAll('.e-loop-item')].filter((el) => !el.parentElement.closest('.e-loop-item')).map((el) => ({ id: Number((String(el.className).match(/e-loop-item-(\d+)/) || [])[1]), template: el.getAttribute('data-elementor-id'), inner: !!el.querySelector('.elementor-element-b3innp1 .e-loop-item') })); });
    const sameLimit = outerPro.length === 4 && outerPro[0].inner && outerPro.slice(1).every((o) => !o.inner && o.template !== String(m.docs['f7b-loop-nested-pro']));
    check(s, 'B12.4', 'con 4.3.0.168, el bucle en bucle del grupo de PRO ya pintaba los elementos siguientes con la plantilla interior (limitación previa a F7b, B3.3b)', sameLimit || (api.summary.limits && api.summary.limits.nested && JSON.stringify(api.summary.limits.nested.pro) === JSON.stringify(outerPro)), JSON.stringify(outerPro));
    await ctrl.close();
    // Guardar desde el editor de 4.3.0.168 conserva los widgets y los ajustes nuevos.
    await login('admin');
    for (const key of ['b11-slider', 'b5-filters', 'b1-actions', 'b10-form']) {
      let ed = null;
      try {
        ed = await openEditor('admin', m.docs[key]);
        await ed.page.evaluate(() => { const c = window.elementor.documents.getCurrent(); c.editor.isChanged = true; window.elementor.saver && window.elementor.saver.setFlagEditorChange && window.elementor.saver.setFlagEditorChange(true); });
        const saved = await editorSave(ed);
        await sleep(1500);
        const after = (await control('data', { doc: key })).elements;
        let ok = saved.ok;
        let detail = '';
        if (key === 'b11-slider') {
          // Tal cual o dentro del marcador de la guarda hacia delante (`digitalisimo-unconverted`, con el original completo).
          const w = find(after, 'w1slide');
          const j = JSON.stringify(w || {});
          ok = ok && !!w && j.includes('digitalisimo-nested-slider') && ['w1s0001', 'w1s0002', 'w1s0003', 'w1b0003'].every((x) => j.includes(x)) && j.includes('Tres') && j.includes('Novedades');
          detail = JSON.stringify(w && { type: w.widgetType, children: (w.elements || []).length, bytes: j.length });
        } else if (key === 'b5-filters') {
          const w = find(after, 'b5colr1');
          const j = JSON.stringify(w || {});
          ok = ok && !!w && j.includes('digitalisimo-loop-meta-filter') && j.includes('"selected_element":"b5loop1"') && j.includes('verde');
          detail = JSON.stringify(w && { type: w.widgetType, bytes: j.length });
        } else if (key === 'b1-actions') {
          const w = find(after, 'b1btn01');
          const p = find(after, 'b1pan01');
          ok = ok && !!w && JSON.stringify(w.settings._digitalisimo_actions) === JSON.stringify(find(before[key], 'b1btn01').settings._digitalisimo_actions) && p && p.settings._digitalisimo_hidden_initial === 'yes';
          detail = JSON.stringify({ actions: w && w.settings._digitalisimo_actions, hidden: p && p.settings._digitalisimo_hidden_initial });
        } else {
          const f = (find(after, 'b10frm1') || { settings: {} }).settings.form_fields || [];
          const r = f.find((x) => x.custom_id === 'rfc') || {};
          ok = ok && r.digitalisimo_conditional === 'yes' && (r.digitalisimo_rules || []).length >= 1;
          detail = JSON.stringify({ cond: r.digitalisimo_conditional, rules: r.digitalisimo_rules });
        }
        check(s, `B12.5 ${key}`, `guardar ${key} desde el editor de 4.3.0.168 conserva lo nuevo (widgets con sus hijos y ajustes, acciones, reglas)`, ok, detail);
        if (key === 'b11-slider') check(s, 'B12.5b', 'el editor de 4.3.0.168 abre sin errores de JavaScript con los datos de F7b', !ed.facts.errors.length, JSON.stringify(ed.facts.errors.slice(0, 3)));
      } catch (e) {
        check(s, `B12.5 ${key}`, `guardar ${key} desde el editor de 4.3.0.168`, false, String(e.stack || e).slice(0, 400));
        if (ed) await shot(ed.page, `b12-${key}`);
      } finally {
        if (ed) await ed.close();
      }
    }
    const fatal = serverLog('fatal.log', fatal0);
    check(s, 'B12.6', 'sin errores fatales de PHP con 4.3.0.168', !fatal, fatal.slice(0, 400));
    const notices = serverLog('php-errors.log', notice0).split('\n').filter(Boolean);
    if (notices.length) note(s, `avisos de PHP de Elements con 4.3.0.168: ${notices.slice(0, 3).join(' | ')}`);
  }

  return { B12 };
};
