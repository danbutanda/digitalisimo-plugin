// B8 (sobrescrituras del Template) y B9 (etiquetas dinámicas dentro del texto) de f7b.sh: front, caché de elementos,
// permisos de las acciones AJAX del editor y el editor real (clave sólo en plantillas, lista de claves, «Insertar dato»).
'use strict';

module.exports = (api) => {
  const { check, pending, note, control, request, open, ids, url, login, eajax, editorNonce, openEditor, selectElement, activateSection, editorSettings, elementHtml, sleep, shot, normalize } = api;
  const text = (html) => String(html || '').replace(/<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>|<noscript[\s\S]*?<\/noscript>/g, ' ').replace(/<[^>]+>/g, ' ').replace(/&#0?38;|&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#8220;/g, '“').replace(/&#8221;/g, '”').replace(/\s+/g, ' ').trim();
  /** HTML de una instancia del Template sin el envoltorio del widget (lo que pinta la plantilla). */
  const inner = (html, id) => { const h = elementHtml(html, id); return h ? h.replace(/^<div[^>]*>/, '') : null; };

  async function B8(s) {
    const m = await ids(true);
    const page = await url('b8-page');
    await control('option', { name: 'elementor_element_cache_ttl', value: '24' });
    await control('clearcss');
    try {
      const visits = [];
      for (let i = 0; i < 2; i++) visits.push((await request('GET', page)).text);
      const a = elementHtml(visits[1], 'b8tpl01') || '';
      const t = text(a);
      check(s, 'B8.1a', '`text` sustituye el título como texto escapado y `html` el Editor de texto como HTML', /Nuevo <título>/.test(t) && a.includes('Nuevo &lt;título&gt;') && /<strong>Rico<\/strong>/.test(a) && !/Título original|Texto original/.test(t), t.slice(0, 300));
      const btn = (a.match(/<a [^>]*elementor-button[^>]*>/) || [''])[0];
      check(s, 'B8.1b', '`link` cambia la URL y abre en otra ventana, y conserva los atributos propios del botón (`data-x="1"`)', /href="https:\/\/example\.com\/nuevo"/.test(btn) && /target="_blank"/.test(btn) && /data-x="1"/.test(btn), btn);
      check(s, 'B8.1c', '`image` cambia la imagen por el otro adjunto; `link` del vídeo cambia la fuente; la caja de icono, su título', a.includes(m.media.image2.url.replace(/\.png$/, '')) && !a.includes(m.media.image.url.replace(/\.png$/, '')) && /digi-medicion\.mp4\?otra=1/.test(a) && /Caja nueva/.test(t), JSON.stringify({ img: (a.match(/<img [^>]*src="([^"]*)"/) || [])[1], video: (a.match(/<video[^>]*src="([^"]*)"/) || [])[1] }));
      check(s, 'B8.1d', '`hide` quita el elemento de la instancia', !/Se oculta con hide/.test(t) && !/elementor-element-b8thd02/.test(a));
      // La segunda instancia y la página con una sola instancia sin sobrescrituras: byte a byte iguales.
      const plain = (await request('GET', await url('b8-plain'))).text;
      const second = inner(visits[1], 'b8tpl02');
      const alone = inner(plain, 'b8tpl02');
      check(s, 'B8.2', 'la instancia sin sobrescrituras de la misma plantilla pinta byte a byte lo mismo que sola en otra página', !!second && second === alone && /Título original/.test(text(second)) && /Se oculta con hide/.test(text(second)), second === alone ? '' : `${(second || '').slice(0, 200)} ≠ ${(alone || '').slice(0, 200)}`);
      check(s, 'B8.2b', 'sin sufijos en los IDs HTML: las dos instancias repiten los IDs de la plantilla (como 4.3.0.168)', (visits[1].match(/elementor-element-b8thd01\b/g) || []).length === 2);
      const cacheMeta = await control('meta', { doc: 'b8-tpl', key: '_elementor_element_cache' });
      check(s, 'B8.3a', 'caché de elementos a 24 h: la caché de la plantilla existe y nunca contiene lo sobrescrito', cacheMeta.values.length > 0 && !/Nuevo|Rico|Caja nueva|example\.com\/nuevo/.test(cacheMeta.json), `${cacheMeta.json.length} B`);
      check(s, 'B8.3b', 'segunda visita con la caché llena: el mismo HTML', normalize(visits[0]) === normalize(visits[1]));
      // Plantilla dentro de plantilla: cada instancia sobrescribe sólo su plantilla.
      const nested = (await request('GET', await url('b8-nested'))).text;
      const n1 = text(elementHtml(nested, 'b8ntp01'));
      const n2 = text(elementHtml(nested, 'b8ntp02'));
      check(s, 'B8.4', 'Template dentro de otra plantilla: la instancia interior sobrescribe su plantilla y la directa la suya', /Desde la plantilla Q/.test(n1) && !/Instancia directa/.test(n1) && /Instancia directa/.test(n2) && !/Desde la plantilla Q/.test(n2), JSON.stringify({ n1: n1.slice(0, 120), n2: n2.slice(0, 120) }));
    } finally {
      await control('option', { name: 'elementor_element_cache_ttl', value: 'disable' });
    }
    // Permisos de las acciones AJAX (sólo lectura): suscriptor y autor ajeno.
    for (const user of ['f7b-sub', 'f7b-autor']) {
      await login(user);
      const nonce = await editorNonce(user, m.docs['b8-page']);
      const r = await eajax(user, nonce, m.docs['b8-page'], { a: { action: 'digitalisimo_f7b_slots', data: { template_id: m.docs['b8-tpl'] } }, b: { action: 'digitalisimo_f7b_slot_uses', data: {} } });
      check(s, `B8.5 ${user}`, `${user === 'f7b-sub' ? 'un suscriptor' : 'un autor ajeno'} no lee las claves ni los usos (403)`, !(r.responses.a || {}).success && !(r.responses.b || {}).success && [403, 0].includes(Number((r.responses.a || {}).code || 0)), JSON.stringify(r.responses).slice(0, 300));
    }
    // Editor real.
    await login('admin');
    let ed = null;
    try {
      ed = await openEditor('admin', m.docs['b8-tpl']);
      await selectElement(ed, 'b8thd01');
      await activateSection(ed, 'section_title', 'content');
      const lib = await ed.page.evaluate(() => { const c = document.querySelector('.elementor-control-_digitalisimo_slot'); return { body: document.body.classList.contains('digi-ovr-lib'), shown: !!c && getComputedStyle(c).display !== 'none', value: c && c.querySelector('input') ? c.querySelector('input').value : null }; });
      check(s, 'B8.6a', 'editor de la plantilla: la «Clave de sobrescritura» se ve, con su valor', lib.body && lib.shown && lib.value === 'titulo', JSON.stringify(lib));
      await ed.page.evaluate(() => window.$e.run('document/elements/duplicate', { container: window.elementor.getContainer('b8thd01') }));
      await sleep(1500);
      await selectElement(ed, 'b8thd01');
      await activateSection(ed, 'section_title', 'content');
      await sleep(800);
      const dup = await ed.page.evaluate(() => (document.querySelector('.elementor-control-digitalisimo_slot_note') || {}).textContent || '');
      check(s, 'B8.6b', 'al duplicar el encabezado avisa de que la clave se repite en 2 elementos', /se repite en 2/.test(dup), dup.trim().slice(0, 200));
      await ed.page.evaluate(() => window.$e.run('document/history/undo'));
      await ed.close();
      ed = await openEditor('admin', m.docs['b9-tags']);
      await selectElement(ed, 'b9hd001');
      await activateSection(ed, 'section_title', 'content');
      const notLib = await ed.page.evaluate(() => { const c = document.querySelector('.elementor-control-_digitalisimo_slot'); return { body: document.body.classList.contains('digi-ovr-lib'), shown: !!c && getComputedStyle(c).display !== 'none' }; });
      check(s, 'B8.6c', 'en una página la clave no se ve (sólo en documentos de la biblioteca)', !notLib.body && !notLib.shown, JSON.stringify(notLib));
      await ed.close();
      ed = await openEditor('admin', m.docs['b8-page']);
      await selectElement(ed, 'b8tpl01');
      await activateSection(ed, 'section_template', 'content');
      await sleep(2500);
      const rows = await ed.page.evaluate(() => {
        const r = document.querySelector('.elementor-control-digitalisimo_overrides');
        const items = r ? r.querySelectorAll('.elementor-repeater-fields') : [];
        const first = items[0];
        if (first) first.querySelector('.elementor-repeater-row-item-title')?.click();
        return { rows: items.length, notice: (document.querySelector('.elementor-control-digitalisimo_overrides_notice') || {}).textContent || '' };
      });
      await sleep(1500);
      const options = await ed.page.evaluate(() => [...document.querySelectorAll('.elementor-control-digitalisimo_overrides .elementor-control-slot select option')].map((o) => o.value).filter(Boolean));
      check(s, 'B8.6d', 'en la página, el repetidor «Sobrescrituras» del Template lista sus filas y el select «Clave» las claves de la plantilla', rows.rows === 7 && ['titulo', 'texto', 'boton', 'foto', 'video', 'caja', 'oculto'].every((k) => options.includes(k)), JSON.stringify({ rows, options }));
      check(s, 'B8.6e', 'sin errores de JavaScript en el editor', !ed.facts.errors.length, JSON.stringify(ed.facts.errors.slice(0, 3)));
    } catch (e) {
      check(s, 'B8.6x', 'editor real de las sobrescrituras', false, String(e.stack || e).slice(0, 500));
      if (ed) await shot(ed.page, 'b8-editor');
    } finally {
      if (ed) await ed.close();
    }
  }

  async function B9(s) {
    const m = await ids(true);
    const page = await url('b9-tags');
    const res = await request('GET', page + '?plan=pro&utm_source=google');
    const h = res.text;
    const t = (id) => text(elementHtml(h, id));
    check(s, 'B9.1a', '`{post:title}` con el valor escapado: las etiquetas HTML del título no se pintan', t('b9hd001') === 'Hola Etiquetas F7b & “comillas”' && !/<i>/.test(elementHtml(h, 'b9hd001')), t('b9hd001'));
    check(s, 'B9.1b', '`{query:plan|basico,pro}` imprime el valor de la lista que coincide', t('b9hd002') === 'Plan: pro', t('b9hd002'));
    const xss = await request('GET', page + '?plan=' + encodeURIComponent('<script>alert(1)</script>'));
    check(s, 'B9.1c', 'un valor fuera de la lista no se imprime (ni el HTML que llega en la URL)', text(elementHtml(xss.text, 'b9hd002')) === 'Plan:' && !/alert\(1\)/.test(elementHtml(xss.text, 'b9hd002')), text(elementHtml(xss.text, 'b9hd002')));
    check(s, 'B9.1d', '`{post:meta:api_key}` (lista negra por fragmentos) queda literal; `{post:meta:keywords}` se sustituye', t('b9hd003') === 'Meta [{post:meta:api_key}] [uno-dos-tres]', t('b9hd003'));
    check(s, 'B9.1e', 'en el Editor de texto, `{site:name}` y `{site:tagline}`; lo desconocido y `{acf:…}` sin ACF quedan literales', t('b9tx001') === 'Sitio Sitio F7b · Pruebas de F7b · {desconocido:x} · {acf:campo}', t('b9tx001'));
    const href = (elementHtml(h, 'b9bt001').match(/href="([^"]*)"/) || [])[1] || '';
    check(s, 'B9.1f', 'en el Botón se sustituye el texto, nunca el enlace', t('b9bt001') === 'Ir a Etiquetas F7b & “comillas”' && /query:plan\|basico,pro/.test(href) && !/q=pro\b/.test(href), JSON.stringify({ text: t('b9bt001'), href }));
    check(s, 'B9.1g', 'sin el interruptor el texto queda literal; `{query:utm_source|…}` no se sustituye', t('b9hd004') === 'Sin interruptor {post:title}' && t('b9hd005') === 'Campaña [{query:utm_source|google}]', JSON.stringify({ a: t('b9hd004'), b: t('b9hd005') }));
    // Caché de elementos: el elemento con etiquetas es dinámico (cambia por URL aun con la caché llena).
    await control('option', { name: 'elementor_element_cache_ttl', value: '24' });
    try {
      await request('GET', page + '?plan=basico');
      const one = await request('GET', page + '?plan=basico');
      const two = await request('GET', page + '?plan=pro');
      const cache = await control('meta', { doc: 'b9-tags', key: '_elementor_element_cache' });
      check(s, 'B9.2', 'caché de elementos llena: cada URL pinta su valor y la caché del documento no guarda ningún valor resuelto', text(elementHtml(one.text, 'b9hd002')) === 'Plan: basico' && text(elementHtml(two.text, 'b9hd002')) === 'Plan: pro' && cache.values.length > 0 && !/Hola Etiquetas|Plan: basico/.test(cache.json), JSON.stringify({ one: text(elementHtml(one.text, 'b9hd002')), two: text(elementHtml(two.text, 'b9hd002')), cache: cache.json.length }));
    } finally {
      await control('option', { name: 'elementor_element_cache_ttl', value: 'disable' });
    }
    const anon = await open(await url('b9-user'));
    const anonHeader = await anon.page.$eval('.elementor-element-b9uhd01', (el) => el.textContent.trim()).catch(() => null);
    check(s, 'B9.3a', '`{user:first_name|visitante}` en la cabecera: un visitante ve el respaldo y la página sigue en caché (sin `DONOTCACHEPAGE`)', anonHeader === 'Hola visitante' && anon.probe && anon.probe.donotcache === false, JSON.stringify({ anonHeader, probe: anon.probe && anon.probe.donotcache }));
    const sizes = { scripts: anon.probe && anon.probe.scripts, styles: anon.probe && anon.probe.styles };
    await anon.close();
    await login('f7b-autor');
    const user = await open(await url('b9-user'), { jar: 'f7b-autor' });
    const userHeader = await user.page.$eval('.elementor-element-b9uhd01', (el) => el.textContent.trim()).catch(() => null);
    check(s, 'B9.3b', 'con sesión: su nombre, y la página sale de la caché (`DONOTCACHEPAGE`)', userHeader === 'Hola Ana' && user.probe && user.probe.donotcache === true, JSON.stringify({ userHeader, probe: user.probe && user.probe.donotcache }));
    await user.close();
    const plain = await open(await url('b8-plain'));
    check(s, 'B9.3c', 'las etiquetas en el texto no añaden scripts ni hojas al visitante (mismos handles que una página sin ellas)', JSON.stringify(sizes.scripts) === JSON.stringify(plain.probe && plain.probe.scripts), JSON.stringify({ tags: sizes.scripts, plain: plain.probe && plain.probe.scripts }));
    await plain.close();
    // «Insertar dato» en el editor real.
    await login('admin');
    let ed = null;
    try {
      ed = await openEditor('admin', m.docs['b9-tags']);
      await selectElement(ed, 'b9hd002');
      await activateSection(ed, 'section_title', 'content');
      await sleep(1000);
      const ui = await ed.page.evaluate(() => { const b = document.querySelector('#elementor-panel [data-digi-inline-tags]'); return b ? { options: [...b.querySelectorAll('[data-digi-inline-tag] option')].map((o) => o.value), button: !!b.querySelector('[data-digi-inline-insert]') } : null; });
      check(s, 'B9.4a', '«Insertar dato» aparece con el interruptor, sólo con las etiquetas de la lista blanca', !!ui && ui.button && ui.options.includes('post:title') && ui.options.includes('site:name') && !ui.options.some((o) => /email|pass|login|role/.test(o)), JSON.stringify(ui));
      const before = (await editorSettings(ed, 'b9hd002')).title;
      await ed.page.select('#elementor-panel [data-digi-inline-tag]', 'site:name');
      await ed.page.click('#elementor-panel [data-digi-inline-insert]');
      await ed.page.waitForFunction(() => { const p = document.querySelector('#elementor-panel [data-digi-inline-preview]'); return p && p.textContent.trim(); }, { timeout: 30000 }).catch(() => {});
      const after = (await editorSettings(ed, 'b9hd002')).title;
      const preview = await ed.page.$eval('#elementor-panel [data-digi-inline-preview]', (p) => p.textContent.trim()).catch(() => '');
      check(s, 'B9.4b', 'insertar «Nombre del sitio» añade `{site:name}` al título y la vista previa dice su valor', after === before + '{site:name}' || (after.includes('{site:name}') && after.length > before.length), JSON.stringify({ before, after, preview }));
      check(s, 'B9.4c', 'la vista previa (role=status) muestra el valor resuelto', /Sitio F7b/.test(preview), preview);
      await ed.page.evaluate(() => window.$e.run('document/history/undo'));
      await sleep(800);
      check(s, 'B9.4d', 'un «Deshacer» devuelve el título', (await editorSettings(ed, 'b9hd002')).title === before);
      check(s, 'B9.4e', 'sin errores de JavaScript en el editor', !ed.facts.errors.length, JSON.stringify(ed.facts.errors.slice(0, 3)));
    } catch (e) {
      check(s, 'B9.4x', '«Insertar dato» en el editor real', false, String(e.stack || e).slice(0, 500));
      if (ed) await shot(ed.page, 'b9-editor');
    } finally {
      if (ed) await ed.close();
    }
  }

  return { B8, B9 };
};
