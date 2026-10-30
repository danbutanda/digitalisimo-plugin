// B3 (consultas guardadas y contextos, B3+B5 con `digi_ctx`) y B4 («Desvincular» en el editor real y borrado bloqueado)
// de f7b.sh. Los conjuntos esperados salen de las entradas de prueba (`?digi_f7b=posts`) o de un WP_Query directo.
'use strict';

module.exports = (api) => {
  const { check, pending, note, control, request, open, ids, url, login, eajax, editorNonce, openEditor, selectElement, activateSection, editorSave, editorSettings, loopIds, domLoopIds, sleep, jars, BASE, context, newPage, shot } = api;
  const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
  const byDateDesc = (list) => [...list].sort((a, b) => (a.date < b.date ? 1 : a.date > b.date ? -1 : b.id - a.id));
  let POSTS = null;
  const summary_limits = api.summary.limits = api.summary.limits || {};
  const published = () => Object.values(POSTS).filter((p) => p.status === 'publish');

  async function B3(s) {
    const m = await ids(true);
    POSTS = await control('posts');
    const pub = published();
    // B3.1 · Misma selección que el grupo de PRO con los mismos ajustes (Loop Grid y Posts).
    const v = await open(await url('b3-compare'));
    const got = Object.fromEntries(['b3lsav1', 'b3lpro1', 'b3lsav2', 'b3lpro2', 'b3psav1', 'b3ppro1', 'b3lnone'].map((w) => [w, loopIds(v.html, w)]));
    check(s, 'B3.1a', 'Loop Grid con consulta guardada (términos incluidos, título A-Z, tope 6) = el grupo de PRO con los mismos ajustes', got.b3lsav1 && got.b3lsav1.length > 0 && same(got.b3lsav1, got.b3lpro1), JSON.stringify({ guardada: got.b3lsav1, pro: got.b3lpro1 }));
    check(s, 'B3.1b', 'autores excluidos, fecha descendente: mismos resultados que el grupo de PRO', got.b3lsav2 && got.b3lsav2.length > 0 && same(got.b3lsav2, got.b3lpro2), JSON.stringify({ guardada: got.b3lsav2, pro: got.b3lpro2 }));
    check(s, 'B3.1c', 'el widget Posts con la consulta guardada = Posts con el grupo de PRO', got.b3psav1 && got.b3psav1.length > 0 && same(got.b3psav1, got.b3ppro1), JSON.stringify({ guardada: got.b3psav1, pro: got.b3ppro1 }));
    const direct = await control('wpquery', { args: JSON.stringify({ post_type: 'post', cat: m.terms['digi-f7b-a'], orderby: 'title', order: 'ASC', posts_per_page: 6 }) });
    check(s, 'B3.1d', 'y = WP_Query directo con esos argumentos', same(got.b3lsav1, direct.ids), JSON.stringify({ loop: got.b3lsav1, wpquery: direct.ids }));
    check(s, 'B3.1e', 'un bucle sin consulta guardada no lleva `data-digi-ctx` ni cambia', !/data-digi-ctx[^>]*elementor-element-b3lnone/.test(v.html) && got.b3lnone.length === 4, JSON.stringify(got.b3lnone));
    await v.close();

    // B3.2 · Contexto `current` y relaciones en la plantilla «Entrada individual».
    const target = POSTS.p3;
    const r = await open(m.urls.p3);
    const want = {
      b3sauth: byDateDesc(pub.filter((p) => p.author === target.author && p.id !== target.id)).slice(0, 5).map((p) => p.id),
      b3scat1: byDateDesc(pub.filter((p) => p.id !== target.id && p.cats.some((c) => target.cats.includes(c)))).slice(0, 5).map((p) => p.id),
      b3smeta: byDateDesc(pub.filter((p) => p.id !== target.id && p.color === target.color)).slice(0, 5).map((p) => p.id),
    };
    const seen = { b3sauth: loopIds(r.html, 'b3sauth'), b3scat1: loopIds(r.html, 'b3scat1'), b3smeta: loopIds(r.html, 'b3smeta'), b3spost: loopIds(r.html, 'b3spost') };
    check(s, 'B3.2a', 'relación «mismo autor» con el contexto actual (la entrada consultada), sin ella', same(seen.b3sauth, want.b3sauth), JSON.stringify({ seen: seen.b3sauth, want: want.b3sauth }));
    check(s, 'B3.2b', 'relación «mismos términos de Categorías» con el contexto actual', same(seen.b3scat1, want.b3scat1), JSON.stringify({ seen: seen.b3scat1, want: want.b3scat1 }));
    check(s, 'B3.2c', 'relación «mismo valor del campo color» con el contexto actual', same(seen.b3smeta, want.b3smeta), JSON.stringify({ seen: seen.b3smeta, want: want.b3smeta }));
    check(s, 'B3.2d', 'el widget Posts con la misma consulta de contexto da lo mismo que el Loop Grid', same(seen.b3spost, want.b3scat1), JSON.stringify({ seen: seen.b3spost, want: want.b3scat1 }));
    const privateIn = Object.values(seen).flat().includes(POSTS.pp.id) || Object.values(seen).flat().includes(POSTS.pd.id);
    check(s, 'B3.2e', 'nunca aparecen la entrada privada ni el borrador (`post_status` siempre `publish`)', !privateIn);
    await r.close();

    // B3.3 · Bucle en bucle: el bucle interior toma como contexto la entrada del bucle exterior.
    const n = await open(await url('b3-nested'));
    const outer = await n.page.evaluate(() => {
      const root = document.querySelector('.elementor-element-b3outer');
      const items = [...root.querySelectorAll('.e-loop-item')].filter((el) => !el.parentElement.closest('.e-loop-item'));
      return items.map((el) => {
        const id = Number((String(el.className).match(/e-loop-item-(\d+)/) || [])[1]);
        const inner = el.querySelector('.elementor-element-b3innr1');
        const innerIds = inner ? [...inner.querySelectorAll('.e-loop-item')].map((x) => Number((String(x.className).match(/e-loop-item-(\d+)/) || [])[1])) : null;
        return { id, template: el.getAttribute('data-elementor-id'), inner: innerIds };
      });
    });
    for (const o of outer) { const p = Object.values(POSTS).find((x) => x.id === o.id); o.want = p ? byDateDesc(pub.filter((x) => x.author === p.author && x.id !== p.id)).slice(0, 5).map((x) => x.id) : null; }
    check(s, 'B3.3a', 'bucle en bucle: el bucle interior del primer elemento toma su contexto (la entrada del bucle exterior: mismo autor, sin ella)', outer.length > 0 && same(outer[0].inner, outer[0].want), JSON.stringify(outer[0]));
    const allNested = outer.length === 4 && outer.every((o) => same(o.inner, o.want));
    if (allNested) check(s, 'B3.3b', 'y cada elemento siguiente pinta el suyo', true);
    else pending(s, 'B3.3b', 'cada elemento siguiente del bucle exterior pinta su bucle interior', `limitación del Loop Grid de PRO, no de F7b: tras el primer bucle interior, el bucle exterior pinta los elementos siguientes con la plantilla del interior y sin bucle; lo mismo sin consulta guardada (B3.3c) y con 4.3.0.168 (B12) · ${JSON.stringify(outer.map((o) => ({ id: o.id, plantilla: o.template, interior: o.inner })))}`);
    await n.close();
    // Control: el mismo bucle en bucle sin consulta guardada (grupo de PRO) en 4.3.0.168 y aquí.
    const ctrl = await open(await url('b3-nested-pro'));
    const outerPro = await ctrl.page.evaluate(() => {
      const root = document.querySelector('.elementor-element-b3outp1');
      const items = [...root.querySelectorAll('.e-loop-item')].filter((el) => !el.parentElement.closest('.e-loop-item'));
      return items.map((el) => ({ id: Number((String(el.className).match(/e-loop-item-(\d+)/) || [])[1]), template: el.getAttribute('data-elementor-id'), inner: !!el.querySelector('.elementor-element-b3innp1 .e-loop-item') }));
    });
    const nestedTemplate = String(m.docs['f7b-loop-nested-pro']);
    const proOk = outerPro.length === 4 && outerPro.every((o) => o.inner && o.template === nestedTemplate);
    note(s, `control sin consulta guardada (bucle en bucle con el grupo de PRO): ${JSON.stringify(outerPro)}`);
    check(s, 'B3.3c', 'control sin F7b: el mismo bucle en bucle con el grupo de PRO se comporta igual que con la consulta guardada (F7b no cambia el bucle en bucle)', proOk === allNested && outerPro.length === outer.length && outerPro.map((o) => o.template === nestedTemplate).join() === outer.map((o) => o.template === String(m.docs['f7b-loop-nested'])).join(), JSON.stringify({ pro: outerPro, f7b: outer.map((o) => ({ id: o.id, template: o.template })) }));
    summary_limits.nested = { f7b: outer, pro: outerPro };
    await ctrl.close();

    // B3.4 · Contexto `root` en el nivel superior y `data-digi-ctx` en el envoltorio.
    const page = m.docs['b3-ctx'];
    const c = await open(await url('b3-ctx'));
    const ctxAttr = (c.html.match(/data-digi-ctx="(\d+)"[^>]*elementor-element-b3xloop|elementor-element-b3xloop[^>]*data-digi-ctx="(\d+)"/) || []).slice(1).find(Boolean);
    const wantRoot = (await control('wpquery', { args: JSON.stringify({ post_type: 'post', author: 1, orderby: 'title', order: 'ASC', posts_per_page: 10 }) })).ids;
    const seenRoot = await domLoopIds(c.page, 'b3xloop');
    check(s, 'B3.4a', 'el bucle con contexto `root` pinta `data-digi-ctx` con el ID de la página', Number(ctxAttr) === page, String(ctxAttr));
    check(s, 'B3.4b', 'contexto raíz = la página consultada: entradas de su autor', same(seenRoot, wantRoot), JSON.stringify({ seen: seenRoot, want: wantRoot }));

    // B3+B5 · El filtro por REST envía `digi_ctx` y el bucle sigue con su contexto.
    const posts = [];
    c.page.on('request', (rq) => { if (/refresh-loop/.test(rq.url())) posts.push({ url: rq.url(), body: rq.postData() || '', headers: rq.headers() }); });
    const gamma = POSTS.p3;
    await c.page.type('.elementor-element-b3xsrc1 input[type="search"]', 'Gamma');
    await c.page.click('.elementor-element-b3xsrc1 button[type="submit"]');
    await c.page.waitForFunction(() => !document.querySelector('.elementor-element-b3xloop[aria-busy="true"], .elementor-element-b3xloop .e-load-more-spinner'), { timeout: 30000 }).catch(() => {});
    await sleep(2500);
    const afterSearch = await domLoopIds(c.page, 'b3xloop');
    const sent = posts[0] ? posts[0].body : '';
    let sentCtx = null;
    try { sentCtx = JSON.parse(sent).digi_ctx; } catch (e) { const mm = sent.match(/digi_ctx=(\d+)/); sentCtx = mm ? Number(mm[1]) : null; }
    check(s, 'B3+B5a', 'la petición `refresh-loop` del filtro lleva `digi_ctx` con el ID del bucle', Number(sentCtx) === page, sent.slice(0, 400));
    check(s, 'B3+B5b', 'con la búsqueda el bucle conserva su contexto raíz (sólo «Gamma F7b», del autor de la página)', same(afterSearch, [gamma.id]), JSON.stringify({ afterSearch, gamma: gamma.id }));
    // Petición forjada: `digi_ctx` de una entrada privada → falla cerrado (vacío).
    if (posts[0]) {
      const replay = (ctxValue) => c.page.evaluate(async (req, value) => {
        let body;
        try { body = JSON.parse(req.body); } catch (e) { return { error: 'cuerpo no JSON' }; }
        if (value !== undefined) body.digi_ctx = value;
        const headers = {};
        for (const [k, v] of Object.entries(req.headers || {})) if (/^(content-type|x-wp-nonce)$/i.test(k)) headers[k] = v;
        const res = await fetch(req.url, { method: 'POST', headers, body: JSON.stringify(body), credentials: 'same-origin' });
        const text = await res.text();
        let data = null;
        try { data = JSON.parse(text); } catch (e) { /* */ }
        const html = data && (data.data || data.html || '') || text;
        return { status: res.status, ids: [...String(typeof html === 'string' ? html : JSON.stringify(html)).matchAll(/e-loop-item-(\d+)/g)].map((x) => Number(x[1])) };
      }, posts[0], ctxValue);
      const same0 = await replay(undefined);
      const forged = await replay(POSTS.pp.id);
      const draft = await replay(POSTS.pd.id);
      check(s, 'B3.5', '`digi_ctx` de una entrada privada o de un borrador en una petición forjada: falla cerrado (sin entradas); la misma petición sin tocar sí trae la suya', same0.status === 200 && same0.ids.length > 0 && forged.status === 200 && forged.ids.length === 0 && draft.ids.length === 0, JSON.stringify({ same0, forged, draft }));
    } else pending(s, 'B3.5', '`digi_ctx` privado en una petición forjada', 'el filtro no envió ninguna petición refresh-loop');
    check(s, 'B3.6', 'sin errores de JavaScript', !c.facts.errors.length, JSON.stringify(c.facts.errors.slice(0, 3)));
    await c.close();
  }

  async function B4(s) {
    const m = await ids(true);
    POSTS = POSTS || await control('posts');
    await login('admin');
    // B4.1 · «Desvincular» en el editor real.
    const page = m.docs['b4-unlink'];
    let ed = null;
    try {
      ed = await openEditor('admin', page);
      await selectElement(ed, 'b4loop1');
      await activateSection(ed, 'section_query', 'content');
      await ed.page.waitForFunction(() => { const b = document.querySelector('#elementor-panel [data-digi-sq-summary]'); return b && b.textContent.trim() && !/Cargando/.test(b.textContent); }, { timeout: 60000 }).catch(() => {});
      const nonceEd = await ed.page.evaluate(() => window.elementorCommon && elementorCommon.config && elementorCommon.config.ajax && elementorCommon.config.ajax.nonce);
      const read = await eajax('admin', nonceEd, page, { r: { action: 'digitalisimo_f7b_query_settings', data: { id: m.queries['q-basic'] } } });
      note(s, `lectura de «Desvincular» (digitalisimo_f7b_query_settings): ${JSON.stringify(read.responses.r).slice(0, 600)}`);
      const panel = await ed.page.evaluate(() => {
        const root = document.querySelector('#elementor-panel');
        const select = root.querySelector('.elementor-control-digitalisimo_saved_query select');
        const summary = root.querySelector('[data-digi-sq-summary]');
        const button = root.querySelector('.elementor-control-digitalisimo_saved_query_unlink button');
        const group = root.querySelector('.elementor-control-post_query_post_type');
        return { select: select ? select.value : null, options: select ? select.options.length : 0, summary: summary ? summary.textContent.trim() : null, button: button ? button.textContent.trim() : null, groupHidden: !group || group.classList.contains('elementor-hidden-control') || getComputedStyle(group).display === 'none' };
      });
      check(s, 'B4.1a', 'panel: select «Consulta guardada» con la consulta, resumen de sólo lectura con «Editar en Consultas (N usos)», botón «Desvincular» y el grupo de PRO oculto', panel.select === String(m.queries['q-basic']) && /Noticias F7b/.test(panel.summary || '') && /usos?\)/.test(panel.summary || '') && panel.button === 'Desvincular' && panel.groupHidden, JSON.stringify(panel));
      const visibleDialog = () => ed.page.waitForFunction(() => [...document.querySelectorAll('#digitalisimo-saved-query-unlink')].find((d) => d.offsetParent !== null || getComputedStyle(d).display !== 'none'), { timeout: 20000 }).then((h) => h.evaluate((d) => d.textContent.replace(/\s+/g, ' ').trim())).catch(() => '');
      const clickVisible = (selector) => ed.page.evaluate((sl) => { const b = [...document.querySelectorAll(sl)].find((x) => x.offsetParent !== null); if (b) b.click(); return !!b; }, selector);
      await ed.page.click('.elementor-control-digitalisimo_saved_query_unlink button');
      const dialog = await visibleDialog();
      check(s, 'B4.1b', 'confirmación con el texto de «Desvincular» (sin aviso de cambios: la consulta no usa contexto)', /dejará de usarla/.test(dialog) && !/resultados cambiarán/.test(dialog), dialog.slice(0, 300));
      await clickVisible('#digitalisimo-saved-query-unlink .dialog-confirm-ok, #digitalisimo-saved-query-unlink .dialog-ok');
      await sleep(1500);
      const after = await editorSettings(ed, 'b4loop1');
      const copied = after && after.digitalisimo_saved_query === '' && after.post_query_post_type === 'post' && JSON.stringify(after.post_query_include) === '["terms"]' && (after.post_query_include_term_ids || []).map(String).includes(String(m.tt['digi-f7b-a'])) && after.post_query_orderby === 'post_title' && after.post_query_order === 'asc' && Number(after.posts_per_page) <= 6;
      check(s, 'B4.1c', 'tras confirmar: el select en «Ninguna» y los ajustes de la consulta copiados al grupo de PRO (con su tope de cantidad)', copied, JSON.stringify(after && { q: after.digitalisimo_saved_query, pt: after.post_query_post_type, inc: after.post_query_include, terms: after.post_query_include_term_ids, ob: after.post_query_orderby, o: after.post_query_order, ppp: after.posts_per_page }));
      const history = await ed.page.evaluate(() => { const items = window.elementor.documents.getCurrent().history.getItems(); return items.map ? items.map((i) => `${i.get('title')} · ${i.get('subTitle') || ''} · ${i.get('type')}`) : String(items); }).catch((e) => String(e));
      await ed.page.evaluate(() => window.$e.run('document/history/undo'));
      await sleep(1200);
      const undone = await editorSettings(ed, 'b4loop1');
      check(s, 'B4.1d', 'un solo «Deshacer» devuelve el vínculo y los ajustes anteriores', undone && undone.digitalisimo_saved_query === String(m.queries['q-basic']) && undone.post_query_orderby !== 'post_title', JSON.stringify({ history, q: undone && undone.digitalisimo_saved_query, ob: undone && undone.post_query_orderby }));
      // Otra vez «Desvincular» (y no «Rehacer»: el «Rehacer» del núcleo refresca el panel y es otra comprobación, B4.1i).
      await selectElement(ed, 'b4loop1');
      await activateSection(ed, 'section_query', 'content');
      await ed.page.waitForFunction(() => { const b = document.querySelector('#elementor-panel [data-digi-sq-summary]'); return b && b.textContent.trim() && !/Cargando/.test(b.textContent); }, { timeout: 60000 }).catch(() => {});
      await ed.page.click('.elementor-control-digitalisimo_saved_query_unlink button');
      await visibleDialog();
      await clickVisible('#digitalisimo-saved-query-unlink .dialog-confirm-ok, #digitalisimo-saved-query-unlink .dialog-ok');
      await sleep(2500);
      const again = await editorSettings(ed, 'b4loop1');
      check(s, 'B4.1i', 'desvincular otra vez tras deshacer deja los términos copiados (el panel se vuelve a abrir y los carga)', again && again.digitalisimo_saved_query === '' && (again.post_query_include_term_ids || []).map(String).includes(String(m.tt['digi-f7b-a'])), JSON.stringify(again && { q: again.digitalisimo_saved_query, terms: again.post_query_include_term_ids }));
      // La consulta con relación avisa de que los resultados cambiarán.
      await selectElement(ed, 'b4loop2');
      await activateSection(ed, 'section_query', 'content');
      await sleep(1200);
      await ed.page.waitForFunction(() => { const b = document.querySelector('#elementor-panel [data-digi-sq-summary]'); return b && b.textContent.trim() && !/Cargando/.test(b.textContent); }, { timeout: 60000 }).catch(() => {});
      await ed.page.click('.elementor-control-digitalisimo_saved_query_unlink button');
      await sleep(500);
      const dialog2 = await visibleDialog();
      check(s, 'B4.1e', 'con una consulta de contexto o relación, la confirmación dice que los resultados cambiarán', /resultados cambiarán/.test(dialog2), dialog2.slice(0, 300));
      await clickVisible('#digitalisimo-saved-query-unlink .dialog-cancel, #digitalisimo-saved-query-unlink .dialog-confirm-cancel');
      await sleep(500);
      const saved = await editorSave(ed);
      await sleep(1500);
      const data = await control('data', { doc: 'b4-unlink' });
      const find = (els, id) => { for (const e of els || []) { if (e.id === id) return e; const f = find(e.elements, id); if (f) return f; } return null; };
      const l1 = find(data.elements, 'b4loop1');
      const l2 = find(data.elements, 'b4loop2');
      check(s, 'B4.1f', 'guardar y recargar: el primero queda desvinculado con sus ajustes; el segundo (cancelado) sigue vinculado', saved.ok && l1 && !l1.settings.digitalisimo_saved_query && l1.settings.post_query_orderby === 'post_title' && (l1.settings.post_query_include_term_ids || []).map(String).includes(String(m.tt['digi-f7b-a'])) && l2 && l2.settings.digitalisimo_saved_query === String(m.queries['q-cat']), JSON.stringify({ saved, l1: l1 && l1.settings, l2: l2 && l2.settings.digitalisimo_saved_query }));
      const front = await open(await url('b4-unlink'));
      const ids1 = loopIds(front.html, 'b4loop1');
      const base = await open(await url('b3-compare'));
      check(s, 'B4.1g', 'el bucle desvinculado da en el front lo mismo que con la consulta', same(ids1, loopIds(base.html, 'b3lsav1')), JSON.stringify({ desvinculado: ids1, consulta: loopIds(base.html, 'b3lsav1') }));
      await front.close();
      await base.close();
      check(s, 'B4.1h', 'sin errores de JavaScript en el editor', !ed.facts.errors.length, JSON.stringify(ed.facts.errors.slice(0, 3)));
    } catch (e) {
      check(s, 'B4.1x', '«Desvincular» en el editor real', false, String(e.stack || e).slice(0, 600));
      if (ed) await shot(ed.page, 'b4-editor');
    } finally {
      if (ed) await ed.close();
      await control('save', { doc: 'b4-unlink' });
    }

    // B4.2 · Borrado bloqueado: «En uso (N)» en edit.php, en «Consultas» y en la biblioteca del editor.
    const tpl = m.docs['b8-tpl'];
    const list = await request('GET', '/wp-admin/edit.php?post_type=elementor_library&posts_per_page=100', { jar: 'admin' });
    const row = (list.text.match(new RegExp(`<tr id="post-${tpl}"[\\s\\S]*?</tr>`)) || [''])[0];
    const inUse = row.match(/En uso \((\d+)\)/);
    check(s, 'B4.2a', 'edit.php: la plantilla usada muestra «En uso (N)» enlazado en lugar de «Papelera»', !!inUse && Number(inUse[1]) >= 2 && !/class="submitdelete"/.test(row) && /page=digitalisimo-elements-uses/.test(row), row.replace(/\s+/g, ' ').slice(0, 600));
    const free = m.docs['f7b-loop-mini'];
    const rowFree = (list.text.match(new RegExp(`<tr id="post-${free}"[\\s\\S]*?</tr>`)) || [''])[0];
    check(s, 'B4.2b', 'una plantilla sin usos conserva «Papelera»', /class="submitdelete"/.test(rowFree) && !/En uso/.test(rowFree), rowFree.replace(/\s+/g, ' ').slice(0, 300));
    const trashed = await control('trash', { id: tpl });
    check(s, 'B4.2c', '`wp_trash_post()` de la plantilla usada se detiene (`pre_trash_post`) y la plantilla sigue publicada', trashed.result === false && trashed.status === 'publish', JSON.stringify(trashed));
    const usesPage = await request('GET', `/wp-admin/admin.php?page=digitalisimo-elements-uses&kind=t&id=${tpl}`, { jar: 'admin' });
    check(s, 'B4.2d', 'la pantalla «Dónde se usa» lista los documentos que la usan', usesPage.status === 200 && usesPage.text.includes('B8 · dos instancias') && usesPage.text.includes('digitalisimo-admin'), `${usesPage.status}`);
    const queries = await request('GET', '/wp-admin/admin.php?page=digitalisimo-elements-queries', { jar: 'admin' });
    const qRow = (queries.text.match(/<tr[^>]*>(?:(?!<\/tr>)[\s\S])*Noticias por título \(F7b\)[\s\S]*?<\/tr>/) || [''])[0];
    const qFree = (queries.text.match(/<tr[^>]*>(?:(?!<\/tr>)[\s\S])*Consulta sin usos \(F7b\)[\s\S]*?<\/tr>/) || [''])[0];
    check(s, 'B4.2e', '«Consultas»: la consulta usada muestra «En uso (N)» sin «Borrar»; la que no se usa, «Borrar»', /En uso \(\d+\)/.test(qRow) && !/digitalisimo-sq-delete/.test(qRow) && /digitalisimo-sq-delete/.test(qFree), JSON.stringify({ usada: qRow.replace(/\s+/g, ' ').slice(0, 400), libre: qFree.replace(/\s+/g, ' ').slice(0, 200) }));
    const qTrash = await control('trash', { id: m.queries['q-basic'] });
    check(s, 'B4.2f', 'borrar la consulta usada se detiene', qTrash.result === false && qTrash.status === 'publish', JSON.stringify(qTrash));
    const nonce = await editorNonce('admin', m.docs['b8-page']);
    const del = await eajax('admin', nonce, m.docs['b8-page'], { d1: { action: 'delete_template', data: { source: 'local', template_id: tpl } } });
    const d1 = del.responses.d1 || {};
    check(s, 'B4.2g', 'biblioteca del editor: `delete_template` de una plantilla usada devuelve un error con la lista de usos', !d1.success && /B8/.test(JSON.stringify(d1.data || '')) && (await control('trash', { id: tpl, dry: '1' })).status === 'publish', JSON.stringify(d1).slice(0, 400));
    const delFree = await eajax('admin', nonce, m.docs['b8-page'], { d2: { action: 'delete_template', data: { source: 'local', template_id: m.docs['b4-free'] } } });
    const statusFree = (await control('trash', { id: m.docs['b4-free'], dry: '1' })).status;
    check(s, 'B4.2h', 'y una plantilla sin usos se borra como siempre (función original del núcleo)', !!(delFree.responses.d2 || {}).success && statusFree !== 'publish', JSON.stringify({ r: delFree.responses.d2, status: statusFree }));
    // Permisos de las acciones AJAX de sólo lectura con un suscriptor.
    await login('f7b-sub');
    const subNonce = await editorNonce('f7b-sub', page);
    const sub = await eajax('f7b-sub', subNonce, page, { q1: { action: 'digitalisimo_f7b_queries', data: {} }, q2: { action: 'digitalisimo_f7b_query_settings', data: { id: m.queries['q-basic'] } }, q3: { action: 'delete_template', data: { source: 'local', template_id: tpl } } });
    check(s, 'B4.3', 'un suscriptor no lee consultas ni borra plantillas por AJAX del editor', !(sub.responses.q1 || {}).success && !(sub.responses.q2 || {}).success && !(sub.responses.q3 || {}).success, JSON.stringify(sub).slice(0, 400));
  }

  return { B3, B4 };
};
