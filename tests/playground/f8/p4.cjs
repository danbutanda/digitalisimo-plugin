// P4 · Marcadores de K (f8.sh): kit con widgets del origen importado sin Migración (marcadores); con Migración, el editor
// real los convierte al cargar (filtro puro de K) y al guardar desaparecen y K recuenta; «Migrar» convierte los
// guardados y el recuento baja a 0; un kit exportado después no lleva las metas de Migración (V.17 #18).
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

module.exports = (api) => async (s) => {
  const { ENV, RUN, BASE, check, note, control, cli, login, migrate, context, newPage, shot, sleep, log } = api;
  const imp = await cli(['digitalisimo-elements', 'kit', 'import', '/wordpress/wp-content/probe/kit/f8-marcadores.zip', '--user=admin', '--format=json']);
  fs.writeFileSync(path.join(RUN, 'kit-import.json'), JSON.stringify(imp, null, 1));
  const m0 = await control('markers');
  const pageOf = (st, title) => st.docs.find((d) => d.type === 'page' && d.title === title);
  const ed0 = pageOf(m0, 'Marcadores en el editor');
  const mg0 = pageOf(m0, 'Marcadores para Migrar');
  check(s, 'P4.1', 'el kit se importa sin Migración y sus widgets del origen quedan como marcadores (2 + 3) con el recuento de K', !imp.fatal && !!ed0 && !!mg0 && ed0.markers === 2 && mg0.markers === 3 && m0.count >= 2, JSON.stringify({ fatal: imp.fatal, ed0, mg0, count: m0.count, out: (imp.stdout || imp.stderr).slice(0, 300) }));
  const act = await control('migration');
  check(s, 'P4.2', 'Migración se activa', act.ok, act.error);

  // Editor real: la carga convierte los marcadores en memoria (conversión pura) y el guardado los quita.
  await login('admin');
  const ctx = await context('admin');
  const page = await newPage(ctx, { width: 1600, height: 1000 });
  let model = null;
  let saved = null;
  try {
    await page.goto(`${BASE}/wp-admin/post.php?post=${ed0.id}&action=elementor`, { waitUntil: 'domcontentloaded', timeout: 240000 });
    await page.waitForFunction(() => { const l = document.querySelector('#elementor-loading'); return window.elementor && window.$e && window.elementor.documents && window.elementor.documents.getCurrent() && (!l || getComputedStyle(l).display === 'none'); }, { timeout: 240000 });
    await sleep(2500);
    model = await page.evaluate(() => { const out = []; const walk = (c) => (c.children || []).forEach((ch) => { const t = ch.model.get('widgetType'); if (t) out.push(t); walk(ch); }); walk(window.elementor.getPreviewContainer()); return out; }).catch((e) => ['error: ' + e.message]);
    await shot(page, 'p4-editor');
    saved = await page.evaluate(async () => { try { window.$e.internal('document/save/set-is-modified', { status: true }); await window.$e.run('document/save/update', { force: true }); return { ok: true }; } catch (e) { return { ok: false, error: String(e && e.message || e) }; } });
    await sleep(2000);
  } catch (e) {
    model = model || [`error: ${e.message}`];
  }
  await ctx.close();
  fs.writeFileSync(path.join(RUN, 'editor.json'), JSON.stringify({ model, saved, errors: page.facts.errors.slice(0, 10) }, null, 1));
  check(s, 'P4.3', 'el editor carga el documento con los widgets del origen ya convertidos (sin marcadores del acordeón)', Array.isArray(model) && model.length > 0 && !model.some((t) => /^bdt-/.test(t)) && model.filter((t) => t === 'digitalisimo-unconverted').length < 2, JSON.stringify({ model, errors: page.facts.errors.slice(0, 3) }));
  const m1 = await control('markers');
  const ed1 = pageOf(m1, 'Marcadores en el editor');
  check(s, 'P4.4', 'al guardar desde el editor desaparecen los marcadores convertidos y K recuenta', !!saved && saved.ok && ed1.markers < ed0.markers && m1.count < m0.count, JSON.stringify({ saved, before: ed0.markers, after: ed1.markers, count: [m0.count, m1.count], widgets: ed1.widgets }));

  // «Migrar»: la etapa `markers` convierte los guardados.
  const res = await migrate('admin');
  check(s, 'P4.5', '«Migrar» termina', !!(res.last && res.last.data && res.last.data.done), JSON.stringify(res.steps.slice(-2)));
  const m2 = await control('markers');
  const left = m2.docs.filter((d) => d.type === 'page' && d.markers > 0);
  check(s, 'P4.6', 'tras «Migrar» no queda ningún marcador en las páginas y el recuento de K es 0', !left.length && m2.count === 0, JSON.stringify({ left, count: m2.count }));
  const mg2 = pageOf(m2, 'Marcadores para Migrar');
  check(s, 'P4.7', 'los widgets del origen de la segunda página pasan a widgets de Elements', !Object.keys(mg2.widgets).some((t) => /^(bdt-|prime-slider-)/.test(t)) && Object.keys(mg2.widgets).length > 0, JSON.stringify(mg2));

  // Un kit exportado después no lleva las metas de Migración (Export_Skip, C17).
  const exp = await cli(['digitalisimo-elements', 'kit', 'export', '/wordpress/wp-content/probe/kit/exportado.zip', '--user=admin']);
  const zip = path.join(RUN, 'kit', 'exportado.zip');
  let leaked = null;
  if (fs.existsSync(zip)) {
    const dir = path.join(RUN, 'kit', 'exportado');
    fs.mkdirSync(dir, { recursive: true });
    execFileSync('unzip', ['-o', '-q', zip, '-d', dir]);
    leaked = execFileSync('bash', ['-c', `grep -rlE "_digitalisimo_ep_|_digitalisimo_migration_" "${dir}" || true`]).toString().trim();
  }
  check(s, 'P4.8', 'el kit exportado tras migrar no lleva _digitalisimo_ep_* ni _digitalisimo_migration_*', leaked === '' && m2.docs.some((d) => d.metas.some((k) => /^_digitalisimo_ep_/.test(k))), JSON.stringify({ leaked, export: (exp.stdout + exp.stderr + exp.fatal).slice(0, 300), metas: m2.docs.map((d) => d.metas) }));
  note(s, `editor: ${JSON.stringify(model)}`);
};
