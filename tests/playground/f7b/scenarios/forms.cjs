// B10 (formulario condicional) de f7b.sh: marcado del servidor, la regla [hidden] del CSS del documento, Chrome
// (ocultar, mostrar, foco, envío real y reinicio tras el éxito), envíos colados por admin-admin-ajax.php (los correos y
// el webhook los captura la sonda) y el editor real con el repetidor de reglas dentro del repetidor de campos.
'use strict';

module.exports = (api) => {
  const { check, pending, note, control, request, open, ids, url, login, openEditor, selectElement, activateSection, editorSave, editorSettings, elementHtml, sleep, shot, form, FORM } = api;
  const RULE = ':is(.elementor-element,.elementor-column)[hidden]{display:none!important}';
  const group = (html, id) => (html.match(new RegExp(`<div class="[^"]*elementor-field-group-${id}\\b[^"]*"[^>]*>`)) || [''])[0];

  async function send(m, fields) {
    const body = { action: 'elementor_pro_forms_send_form', post_id: String(m.docs['b10-form']), form_id: 'b10frm1', referer_title: 'B10', queried_id: String(m.docs['b10-form']) };
    for (const [k, v] of Object.entries(fields)) body[`form_fields[${k}]`] = v;
    const res = await request('POST', '/wp-admin/admin-ajax.php', { body: form(body), headers: FORM });
    return res.json || { raw: res.text.slice(0, 300), status: res.status };
  }

  async function B10(s) {
    const m = await ids(true);
    const page = await url('b10-form');
    const res = await request('GET', page);
    const h = res.text;
    const rfc = group(h, 'rfc');
    const razon = group(h, 'razon');
    check(s, 'B10.1a', 'estado inicial pintado por el servidor: los grupos condicionales con `hidden` y `data-digi-cond` saneado', /hidden/.test(rfc) && /data-digi-cond="\{&quot;i&quot;:&quot;rfc&quot;/.test(rfc) && /hidden/.test(razon) && !/hidden/.test(group(h, 'tipo')), `${rfc} · ${razon}`);
    const inputs = h.match(/<input[^>]*name="form_fields\[(?:rfc|razon)\]"[^>]*>/g) || [];
    check(s, 'B10.1b', 'y los controles de los grupos ocultos con `disabled` (no se envían, no se validan, no reciben foco)', inputs.length === 2 && inputs.every((i) => /disabled/.test(i)), inputs.join(' '));
    const script = h.match(/<script[^>]*id="digitalisimo-f7b-forms-conditions-js"[^>]*>/);
    check(s, 'B10.1c', '`forms-conditions.js` con `defer` en la página con reglas', !!script && /\bdefer\b/.test(script[0]), script ? script[0] : 'sin script');
    const cssUrl = (h.match(/href=['"]([^'"]*\/uploads\/elementor\/css\/post-(\d+)\.css[^'"]*)['"]/g) || []).map((x) => x.match(/href=['"]([^'"]*)['"]/)[1]).find((u) => u.includes(`post-${m.docs['b10-form']}.css`));
    const css = cssUrl ? (await request('GET', cssUrl)).text : '';
    check(s, 'B10.1d', 'la regla `[hidden]` una sola vez en el CSS del documento', css.split(RULE).length - 1 === 1, cssUrl || 'sin hoja del documento');
    const plain = (await request('GET', await url('b10-plain'))).text;
    check(s, 'B10.1e', 'un formulario sin reglas no trae ni el script ni `data-digi-cond`', !/forms-conditions/.test(plain) && !/data-digi-cond/.test(plain));

    // Chrome: ocultar y mostrar, obligatorio, foco, envío real y reinicio.
    const v = await open(page);
    const pg = v.page;
    const disp = () => pg.evaluate(() => Object.fromEntries(['rfc', 'razon'].map((id) => { const g = document.querySelector(`.elementor-field-group-${id}`); const i = g.querySelector('input'); return [id, { display: getComputedStyle(g).display, hidden: g.hidden, disabled: i.disabled, required: i.required, mark: g.classList.contains('elementor-mark-required') }]; })));
    const d0 = await disp();
    check(s, 'B10.2a', 'oculto: `display:none` con la regla del CSS del documento', d0.rfc.display === 'none' && d0.razon.display === 'none', JSON.stringify(d0));
    await pg.click('#form-field-tipo-1');
    await sleep(200);
    const d1 = await disp();
    const after = await pg.$eval('.elementor-field-group-rfc label', (l) => getComputedStyle(l, '::after').content).catch(() => '');
    check(s, 'B10.2b', 'elegir «Empresa» muestra RFC (obligatorio, con el asterisco) y Razón social (regla «alguna»)', d1.rfc.display !== 'none' && !d1.rfc.disabled && d1.rfc.required && d1.rfc.mark && d1.razon.display !== 'none' && after && after !== 'none', JSON.stringify({ d1, after }));
    await pg.click('#form-field-tipo-0');
    await sleep(200);
    const d2 = await disp();
    check(s, 'B10.2c', 'volver a «Persona» los oculta otra vez y quita `required`', d2.rfc.display === 'none' && d2.rfc.disabled && !d2.rfc.required, JSON.stringify(d2));
    await pg.focus('#form-field-tipo-0');
    const seen = [];
    for (let i = 0; i < 4; i++) { await pg.keyboard.press('Tab'); seen.push(await pg.evaluate(() => document.activeElement && (document.activeElement.name || document.activeElement.textContent.trim()))); }
    check(s, 'B10.2d', 'Tab no entra en los campos ocultos', !seen.some((x) => /rfc|razon/.test(x || '')), JSON.stringify(seen));
    await control('outbox', { clear: '1' });
    await pg.$eval('#form-field-email', (i) => { i.value = 'ana@example.invalid'; });
    const resp = pg.waitForResponse((r) => /admin-ajax\.php/.test(r.url()) && r.request().method() === 'POST', { timeout: 60000 });
    await pg.click('.elementor-element-b10frm1 button[type="submit"]');
    const r = await resp.then((x) => x.json()).catch((e) => ({ error: String(e) }));
    await sleep(1800);
    const d3 = await disp();
    const radio = await pg.$eval('#form-field-tipo-0', (i) => i.checked);
    check(s, 'B10.2e', 'envío real por AJAX con «Persona» y RFC vacío (obligatorio pero oculto): éxito', r && r.success === true, JSON.stringify(r).slice(0, 300));
    check(s, 'B10.2f', 'tras el éxito PRO reinicia el formulario y el grupo vuelve a su estado inicial (oculto)', !radio && d3.rfc.display === 'none' && d3.rfc.disabled, JSON.stringify({ radio, d3 }));
    check(s, 'B10.2g', 'sin errores de JavaScript ni respuestas de error', !v.facts.errors.length && !v.facts.failed.length, JSON.stringify({ errors: v.facts.errors.slice(0, 3), failed: v.facts.failed.slice(0, 3) }));
    await v.close();

    // Caché de elementos (24 h, el valor del núcleo): la visita servida desde la caché del documento encola el script.
    await control('option', { name: 'elementor_element_cache_ttl', value: '24' });
    try {
      await request('GET', page);
      const c = await open(page);
      const has = /id="digitalisimo-f7b-forms-conditions-js"/.test(c.html);
      await c.page.click('#form-field-tipo-1');
      await sleep(300);
      const shownC = await c.page.$eval('.elementor-field-group-rfc', (g) => getComputedStyle(g).display !== 'none' && !g.querySelector('input').disabled);
      check(s, 'B10.2h', 'con la caché de elementos llena, la visita servida desde la caché trae `forms-conditions.js` y «Empresa» muestra el RFC', has && shownC, JSON.stringify({ script: has, shown: shownC }));
      await c.close();
    } finally {
      await control('option', { name: 'elementor_element_cache_ttl', value: 'disable' });
    }

    // Envíos colados (como un navegador que ignora el script): el servidor recalcula las reglas.
    await control('outbox', { clear: '1' });
    const colado = await send(m, { tipo: 'persona', rfc: 'XAXX010101000', razon: 'Empresa colada', email: 'ana@example.invalid' });
    const box = await control('outbox');
    const outText = JSON.stringify(box);
    check(s, 'B10.3a', '`tipo=persona` con RFC y razón social colados: éxito, y ni el correo ni el webhook los llevan', colado.success === true && (box.mail || []).length >= 1 && (box.http || []).length >= 1 && !/XAXX010101000|Empresa colada/.test(outText), JSON.stringify({ colado, mails: (box.mail || []).length, http: (box.http || []).map((x) => x.url) }).slice(0, 500));
    const empty = await send(m, { tipo: 'empresa', rfc: '', email: 'ana@example.invalid' });
    check(s, 'B10.3b', '`tipo=empresa` sin RFC: error del campo `rfc` (visible y obligatorio)', empty.success === false && !!(empty.data && empty.data.errors && empty.data.errors.rfc), JSON.stringify(empty).slice(0, 300));
    const hiddenEmpty = await send(m, { tipo: 'persona', rfc: '', email: 'ana@example.invalid' });
    check(s, 'B10.3c', '`tipo=persona` sin RFC: éxito (obligatorio, oculto y vacío)', hiddenEmpty.success === true, JSON.stringify(hiddenEmpty).slice(0, 300));
    const visibleOk = await send(m, { tipo: 'empresa', rfc: 'EMP010101AB1', razon: 'Razón visible', email: 'ana@example.invalid' });
    const box2 = await control('outbox');
    check(s, 'B10.3d', '`tipo=empresa` con RFC: éxito y el RFC sí llega a las acciones', visibleOk.success === true && /EMP010101AB1/.test(JSON.stringify(box2)), JSON.stringify(visibleOk).slice(0, 200));
    // Con el contador a 0 (antes de que el recorrido lo cuente) el envío colado sigue sin llegar (D-J6).
    const counts = (await control('ping')).counts;
    await control('option', { name: 'digitalisimo_elements_f7b_counts', value: JSON.stringify({ ...counts, form_conditions: 0 }) });
    await control('outbox', { clear: '1' });
    const colado0 = await send(m, { tipo: 'persona', rfc: 'XAXX010101000', email: 'ana@example.invalid' });
    const box3 = await control('outbox');
    check(s, 'B10.3e', 'con el contador `form_conditions` a 0, lo colado tampoco llega a las acciones', colado0.success === true && !/XAXX010101000/.test(JSON.stringify(box3)), JSON.stringify({ colado0, box: JSON.stringify(box3).slice(0, 200) }));
    await control('option', { name: 'digitalisimo_elements_f7b_counts', value: JSON.stringify(counts) });

    // Editor real: reglas dentro del repetidor de campos.
    await login('admin');
    let ed = null;
    try {
      ed = await openEditor('admin', m.docs['b10-form']);
      await selectElement(ed, 'b10frm1');
      await activateSection(ed, 'section_form_fields', 'content');
      await sleep(1500);
      // Abre la fila del campo «RFC» (la segunda) y su repetidor de reglas.
      const opened = await ed.page.evaluate(() => {
        const rows = document.querySelectorAll('.elementor-control-form_fields > .elementor-control-content .elementor-repeater-fields-wrapper > .elementor-repeater-fields');
        const row = rows[1];
        if (!row) return { rows: rows.length };
        row.querySelector('.elementor-repeater-row-item-title').click();
        return { rows: rows.length };
      });
      await sleep(1500);
      const ui = await ed.page.evaluate(() => {
        const row = document.querySelectorAll('.elementor-control-form_fields .elementor-repeater-fields-wrapper > .elementor-repeater-fields')[1];
        const sw = row && row.querySelector('.elementor-control-digitalisimo_conditional');
        const rules = row && row.querySelector('.elementor-control-digitalisimo_rules');
        return { switch: !!sw, rules: !!rules, ruleRows: rules ? rules.querySelectorAll('.elementor-repeater-fields').length : 0, add: !!(rules && rules.querySelector('.elementor-repeater-add')), notice: (row && row.textContent || '').includes('código de la página') };
      });
      check(s, 'B10.4a', 'en el panel, el campo RFC tiene «Mostrar sólo si…», su repetidor de reglas (1 regla) y el aviso de que los valores se ven en el código', ui.switch && ui.rules && ui.ruleRows === 1 && ui.add && ui.notice, JSON.stringify({ opened, ui }));
      const count = () => ed.page.evaluate(() => { const c = window.elementor.getContainer('b10frm1'); const f = c.settings.get('form_fields'); const row = f.models ? f.models[1] : f[1]; const r = row.get ? row.get('digitalisimo_rules') : row.digitalisimo_rules; return r && r.models ? r.models.length : (r || []).length; });
      const n0 = await count();
      await ed.page.evaluate(() => { const row = document.querySelectorAll('.elementor-control-form_fields .elementor-repeater-fields-wrapper > .elementor-repeater-fields')[1]; row.querySelector('.elementor-control-digitalisimo_rules .elementor-repeater-add').click(); });
      await sleep(800);
      await ed.page.evaluate(() => { const row = document.querySelectorAll('.elementor-control-form_fields .elementor-repeater-fields-wrapper > .elementor-repeater-fields')[1]; row.querySelector('.elementor-control-digitalisimo_rules .elementor-repeater-add').click(); });
      await sleep(800);
      const n1 = await count();
      // Escribe en la última regla: mira al campo «email» (posterior) para que salga el aviso.
      await ed.page.evaluate(() => {
        const row = document.querySelectorAll('.elementor-control-form_fields .elementor-repeater-fields-wrapper > .elementor-repeater-fields')[1];
        const rules = row.querySelectorAll('.elementor-control-digitalisimo_rules .elementor-repeater-fields');
        const last = rules[rules.length - 1];
        const t = last.querySelector('.elementor-repeater-row-item-title');
        if (t && !last.querySelector('.elementor-repeater-row-controls.editable')) t.click();
      });
      await sleep(600);
      const typed = await ed.page.evaluate(() => {
        const row = document.querySelectorAll('.elementor-control-form_fields .elementor-repeater-fields-wrapper > .elementor-repeater-fields')[1];
        const rules = row.querySelectorAll('.elementor-control-digitalisimo_rules .elementor-repeater-fields');
        const last = rules[rules.length - 1];
        const field = last.querySelector('.elementor-control-field input[data-setting="field"]');
        const value = last.querySelector('.elementor-control-value input[data-setting="value"]');
        if (!field || !value) return false;
        field.value = 'email'; field.dispatchEvent(new Event('input', { bubbles: true }));
        value.value = 'x@example.invalid'; value.dispatchEvent(new Event('input', { bubbles: true }));
        return true;
      });
      await sleep(1200);
      const warn = await ed.page.evaluate(() => (document.querySelectorAll('.elementor-control-form_fields .elementor-repeater-fields-wrapper > .elementor-repeater-fields')[1] || {}).textContent || '');
      check(s, 'B10.4b', 'añadir dos reglas en el repetidor anidado y escribir; una regla hacia un campo posterior muestra su aviso', n1 === n0 + 2 && typed && /posterior|después/.test(warn), JSON.stringify({ n0, n1, typed, warn: warn.replace(/\s+/g, ' ').match(/[^.]*(?:posterior|después)[^.]*/) }));
      // Borrar la última, deshacer el borrado, guardar y recargar.
      await ed.page.evaluate(() => { const row = document.querySelectorAll('.elementor-control-form_fields .elementor-repeater-fields-wrapper > .elementor-repeater-fields')[1]; const rules = row.querySelectorAll('.elementor-control-digitalisimo_rules .elementor-repeater-fields'); rules[rules.length - 1].querySelector('.elementor-repeater-tool-remove').click(); });
      await sleep(800);
      const n2 = await count();
      await ed.page.evaluate(() => window.$e.run('document/history/undo'));
      await sleep(1000);
      const n3 = await count();
      check(s, 'B10.4c', 'borrar una regla y deshacerlo con el «Deshacer» del núcleo', n2 === n1 - 1 && n3 === n1, JSON.stringify({ n1, n2, n3 }));
      const saved = await editorSave(ed);
      await sleep(1500);
      const data = await control('data', { doc: 'b10-form' });
      const find = (els, id) => { for (const e of els || []) { if (e.id === id) return e; const f = find(e.elements, id); if (f) return f; } return null; };
      const fieldsSaved = (find(data.elements, 'b10frm1') || { settings: {} }).settings.form_fields || [];
      const rfcSaved = fieldsSaved.find((f) => f.custom_id === 'rfc') || {};
      const rules = rfcSaved.digitalisimo_rules || [];
      check(s, 'B10.4d', 'guardar: las reglas quedan en `_elementor_data` (la original y las dos nuevas, con el campo y el valor escritos)', saved.ok && rules.length === n1 && rules[0].field === 'tipo' && rules.some((r) => r.field === 'email' && r.value === 'x@example.invalid') && rfcSaved.digitalisimo_conditional === 'yes', JSON.stringify({ saved, rules }));
      await ed.close();
      ed = await openEditor('admin', m.docs['b10-form']);
      const reloaded = await ed.page.evaluate(() => { const c = window.elementor.getContainer('b10frm1'); const f = c.settings.get('form_fields'); const row = f.models[1]; const r = row.get('digitalisimo_rules'); return r.models.map((x) => ({ field: x.get('field'), operator: x.get('operator'), value: x.get('value') })); });
      check(s, 'B10.4e', 'al recargar el editor las reglas vuelven iguales', JSON.stringify(reloaded.map((r) => [r.field, r.value])) === JSON.stringify(rules.map((r) => [r.field, r.value])), JSON.stringify(reloaded));
      check(s, 'B10.4f', 'sin errores de JavaScript en el editor', !ed.facts.errors.length, JSON.stringify(ed.facts.errors.slice(0, 3)));
    } catch (e) {
      check(s, 'B10.4x', 'reglas en el editor real', false, String(e.stack || e).slice(0, 500));
      if (ed) await shot(ed.page, 'b10-editor');
    } finally {
      if (ed) await ed.close();
      await control('save', { doc: 'b10-form' });
    }
  }

  return { B10 };
};
