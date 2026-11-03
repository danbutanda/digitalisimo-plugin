// P2 · Element Pack sintético en un sitio (f8.sh). Antes: el origen «cargado» pinta su cabecera y su pie (mu-ep-header.php).
// «Migrar» por el mismo AJAX que la pantalla, con un sondeo de la portada y de la página especial después de cada paso:
// exactamente una cabecera y un pie en cada respuesta. Al terminar: plugins desactivados y nunca borrados, copias del
// Theme Builder con sus condiciones y prioridades, plataforma (fuentes, iconos que se ven, kit, código, barra lateral),
// las 18 extensiones, informe sin secretos y sin rastro del proveedor. Con PRO=1, el conflicto de Elementor Pro.
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => async (s) => {
  const { ENV, RUN, check, pending, note, control, login, tool, migrate, request, open, shot, headers, vendorTrace, text, walk } = api;
  const PRO = ENV.PRO === '1';
  const m = await control('ids');
  const tb0 = await control('tb');
  const special = m.urls['digi-f8-especial'];
  const home = m.home;
  const H = tb0.tb;

  // ── Antes ────────────────────────────────────────────────────────────────────────────────────────────────────
  const st0 = await control('state');
  check(s, 'P2.1a', 'antes: el origen simulado está «cargado» (BDTEP_VER) y sus plugins falsos activos', st0.ep && ['bdthemes-element-pack/bdthemes-element-pack.php', 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php'].every((p) => st0.plugins.site.includes(p)), JSON.stringify(st0.plugins));
  if (PRO) check(s, 'P2.1p', 'PRO=1: «Elementor Pro» falso activo y el Theme Builder de Elements sin cargar (conflicto)', st0.plugins.site.includes('elementor-pro/elementor-pro.php') && !st0.pro, JSON.stringify({ pro: st0.pro }));
  const r0 = await request('GET', home);
  const r0s = await request('GET', special);
  const h0 = headers(r0.text);
  const h0s = headers(r0s.text);
  check(s, 'P2.1b', 'antes: la portada tiene la cabecera general y el pie del origen (uno de cada)', h0.header === 1 && h0.ep[0] === H.header && h0.footer === 1 && h0.epF[0] === H.footer, JSON.stringify(h0));
  check(s, 'P2.1c', 'antes: la página especial tiene la cabecera especial (la general la excluye)', h0s.header === 1 && h0s.ep[0] === H.header_special, JSON.stringify(h0s));

  // ── Pantalla: un solo botón y el análisis previo ────────────────────────────────────────────────────────────
  await login('admin');
  const t = await tool('admin');
  fs.writeFileSync(path.join(RUN, 'pages', 'tool-before.html'), t.html);
  check(s, 'P2.2a', 'la herramienta tiene un solo botón: «Migrar»', t.buttons.length === 1 && /^Migrar$/.test(t.buttons[0]), JSON.stringify(t.buttons));
  const secret = await control('secret');
  check(s, 'P2.2b', 'la herramienta no muestra el valor de la contraseña de aplicación ni la clave de API', !!secret.password && !t.html.includes(secret.password) && !t.html.includes('clave-ficticia-f8-no-mostrar'), secret.password ? '' : 'sin contraseña creada');

  // ── «Migrar» con sondeo en cada paso ──────────────────────────────────────────────────────────────────────────
  const polls = [];
  const bad = [];
  const res = await migrate('admin', {
    onStep: async (r, i) => {
      for (const [where, url] of [['portada', home], ['especial', special]]) {
        const p = await request('GET', url);
        const h = headers(p.text);
        const row = { i, stage: r.data && r.data.stage, where, status: p.status, ep: h.ep, el: h.el, epF: h.epF, elF: h.elF };
        polls.push(row);
        if (p.status !== 200 || h.header !== 1 || h.footer !== 1) bad.push(row);
      }
    },
  });
  fs.writeFileSync(path.join(RUN, 'steps.json'), JSON.stringify({ steps: res.steps, polls }, null, 1));
  s.facts.steps = res.steps.length;
  s.facts.stages = [...new Set(res.steps.map((x) => x.stage).filter(Boolean))];
  const last = res.last && res.last.data;
  check(s, 'P2.3a', '«Migrar» termina por el AJAX de la pantalla, sin errores', !!(last && last.done) && res.steps.every((x) => x.ok), JSON.stringify(res.steps.filter((x) => !x.ok).slice(0, 3).concat([{ last: res.steps[res.steps.length - 1] }])));
  check(s, 'P2.3b', `en cada paso (${res.steps.length}) la portada y la página especial tienen exactamente una cabecera y un pie`, polls.length > 0 && !bad.length, JSON.stringify(bad.slice(0, 6)));
  const switched = polls.filter((p) => p.where === 'portada' && p.el.length === 1);
  check(s, 'P2.3c', 'la cabecera pasa de la del origen a la copia de Elements sin ninguna respuesta intermedia sin cabecera', switched.length > 0 && polls.filter((p) => p.where === 'portada').every((p) => p.ep.length + p.el.length === 1), JSON.stringify({ first_copy_at: switched[0] && switched[0].i, stages: s.facts.stages }));
  fs.writeFileSync(path.join(RUN, 'pages', 'tool-after.html'), last && last.html ? last.html : '');

  // ── Después ──────────────────────────────────────────────────────────────────────────────────────────────────
  const st = await control('state', { registry: '1' });
  fs.writeFileSync(path.join(RUN, 'state-after.json'), JSON.stringify(st, null, 1));
  const site = st.plugins.site;
  const retired = ['bdthemes-element-pack/bdthemes-element-pack.php', 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php', 'bdthemes-prime-slider/bdthemes-prime-slider.php', 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php'].concat(PRO ? ['elementor-pro/elementor-pro.php'] : []);
  check(s, 'P2.4a', 'el grupo del origen, Prime Slider' + (PRO ? ' y Elementor Pro' : '') + ' quedan desactivados', retired.every((p) => !site.includes(p)) && !st.ep, JSON.stringify(site));
  const plat = await control('platform');
  check(s, 'P2.4b', 'ningún plugin se borró (siguen instalados)', retired.every((p) => plat.installed.includes(p)), JSON.stringify(plat.installed));
  check(s, 'P2.4c', 'Elements carga su Theme Builder y no hay conflicto', st.pro, '');

  const tb = await control('tb');
  fs.writeFileSync(path.join(RUN, 'tb-after.json'), JSON.stringify(tb, null, 1));
  const byTitle = Object.fromEntries(tb.templates.map((x) => [x.title, x]));
  const want = { 'Cabecera principal': 'header', 'Cabecera especial': 'header', 'Pie del sitio': 'footer', 'Plantilla de entrada': 'single-post', 'Plantilla de archivo': 'archive', 'Plantilla de búsqueda': 'search-results', 'Plantilla 404': 'error-404', 'Plantilla de tienda': 'product-archive' };
  const missing = Object.entries(want).filter(([title, type]) => !byTitle[title] || byTitle[title].type !== type);
  check(s, 'P2.5a', 'cada plantilla del origen tiene su copia con el tipo de destino', !missing.length, JSON.stringify({ missing, have: tb.templates.map((x) => `${x.title}:${x.type}:${x.status}`) }));
  const publishable = ['Cabecera principal', 'Cabecera especial', 'Pie del sitio'].map((x) => byTitle[x]).filter(Boolean);
  check(s, 'P2.5b', 'la cabecera, la especial y el pie quedan publicados', publishable.length === 3 && publishable.every((x) => x.status === 'publish' && x.filtered === 'publish'), JSON.stringify(publishable.map((x) => [x.title, x.status, x.filtered])));
  const hc = byTitle['Cabecera principal'];
  const conds = hc ? [].concat(hc.conditions || []) : [];
  check(s, 'P2.5c', 'condiciones traducidas: la general incluye todo y excluye la página especial', conds.some((c) => /^include\/general/.test(c)) && conds.some((c) => /^exclude\/singular\/page\/\d+/.test(c)), JSON.stringify(conds));
  const prios = tb.templates.filter((x) => x.status === 'publish').map((x) => x.priority);
  check(s, 'P2.5d', 'prioridad manual 1..n en toda copia publicada', prios.length > 0 && prios.every((p) => Number(p) >= 1 && Number(p) <= 99), JSON.stringify(tb.templates.map((x) => [x.title, x.status, x.priority])));
  const cart = byTitle['Plantilla de carrito'];
  check(s, 'P2.5e', 'el carrito (sin tipo equivalente) queda en borrador sin condiciones', !cart || (cart.status === 'draft' && !(cart.conditions || []).length), JSON.stringify(cart || null));

  const rH = await request('GET', home);
  const rS = await request('GET', special);
  const hH = headers(rH.text);
  const hS = headers(rS.text);
  check(s, 'P2.6a', 'después: la portada pinta la copia de la cabecera general y la del pie (Elements), sin las del origen', hH.ep.length === 0 && hH.el.length === 1 && hH.el[0] === (hc && hc.id) && hH.elF.length === 1, JSON.stringify(hH));
  check(s, 'P2.6b', 'después: la página especial pinta la copia de la cabecera especial', hS.el.length === 1 && hS.el[0] === (byTitle['Cabecera especial'] || {}).id, JSON.stringify(hS));
  const r404 = await request('GET', '/no-existe-f8/');
  check(s, 'P2.6c', 'el 404 usa la copia de la plantilla 404', r404.status === 404 && /data-elementor-type="error-404"/.test(r404.text) && /Plantilla 404/.test(text(r404.text)), `${r404.status}`);
  const rQ = await request('GET', '/?s=F8');
  check(s, 'P2.6d', 'la búsqueda usa la copia de la plantilla de búsqueda', /data-elementor-type="search-results"/.test(rQ.text), '');
  const rP = await request('GET', m.urls['digi-f8-entrada']);
  check(s, 'P2.6e', 'la entrada usa la copia de la plantilla de entrada', /data-elementor-type="single-post"/.test(rP.text), '');

  // Plataforma.
  fs.writeFileSync(path.join(RUN, 'platform-after.json'), JSON.stringify(plat, null, 1));
  const font = plat.fonts.find((f) => f.title === 'Letra Prueba');
  const siteCodes = ((st.report_f8 || {}).site || {}).items || [];
  if (PRO) {
    // El «Elementor Pro» falso sólo tiene la cabecera: mientras está activo no hay gestor de fuentes (ni el de Elements
    // ni el suyo), y la fuente falla cerrada con su aviso. Con el plugin real, su gestor sí existe.
    check(s, 'P2.7a', 'PRO=1: sin gestor de fuentes durante la preparación, la fuente falla cerrada e informada (plat.font.failed)', !font && siteCodes.some((c) => c.code === 'plat.font.failed'), JSON.stringify(siteCodes.map((c) => c.code)));
  } else {
  check(s, 'P2.7a', 'la fuente propia es una fuente de Elements publicada con su archivo', !!font && font.status === 'publish' && /woff/.test(JSON.stringify(font.files || '')), JSON.stringify(font || plat.fonts));
  const vf = await open(m.urls['digi-f8-fuente']);
  const fontUse = await vf.page.evaluate(async () => {
    const h = document.querySelector('[data-elementor-type="wp-page"] .elementor-heading-title');
    const faces = [];
    for (const sheet of document.styleSheets) { let rules = []; try { rules = [...sheet.cssRules]; } catch (e) { continue; } for (const r of rules) if (r.type === CSSRule.FONT_FACE_RULE) faces.push(r.style.getPropertyValue('font-family')); }
    const rules = [];
    for (const sheet of document.styleSheets) { let list = []; try { list = [...sheet.cssRules]; } catch (e) { continue; } for (const r of list) if (/font-family/.test(r.cssText) && /heading-title|Letra/.test(r.cssText)) rules.push(`${(sheet.href || 'inline').split('/').pop()} ${r.cssText.slice(0, 200)}`); }
    return { family: h ? getComputedStyle(h).fontFamily : null, faces, rules: rules.slice(0, 8), cls: h ? h.closest('.elementor-element').className : null };
  });
  check(s, 'P2.7b', 'la página con la fuente la declara con @font-face y el título la usa', !!fontUse.family && /Letra Prueba/.test(fontUse.family) && fontUse.faces.some((f) => /Letra Prueba/.test(f)), JSON.stringify(fontUse));
  await vf.close();
  }
  const iconDirs = Object.entries(plat.icon_dirs || {});
  check(s, 'P2.7c', 'el conjunto de iconos se copió a uploads/elementor/custom-icons/ con sus archivos', iconDirs.length === 1 && iconDirs[0][1].includes('style.css'), JSON.stringify(plat.icon_dirs));
  const doc = await control('doc', { key: 'digi-f8-iconos', full: '1' });
  const libs = walk(doc.doc.data).map((e) => JSON.stringify(e.settings || {})).join(' ');
  check(s, 'P2.7d', 'el documento con iconos ya no nombra la pestaña del origen', !/bdt-custom-/.test(libs), libs.slice(0, 300));
  const v = await open(m.urls['digi-f8-iconos']);
  const glyph = await v.page.evaluate(() => { const i = document.querySelector('.gl-estrella, [class*="gl-estrella"]'); if (!i) return null; const st = getComputedStyle(i, '::before'); return { family: st.fontFamily, content: st.content }; });
  check(s, 'P2.7e', 'el icono se ve: su glifo usa la fuente del conjunto copiado', !!glyph && /glifos/.test(glyph.family) && glyph.content && glyph.content !== 'none', JSON.stringify({ glyph, failed: v.facts.failed.slice(0, 3) }));
  await shot(v.page, 'p2-iconos');
  await v.close();
  check(s, 'P2.7f', 'el kit ya no tiene claves del origen y guarda su copia', plat.kit.length === 0 && plat.kit_backup, JSON.stringify({ kit: plat.kit, backup: plat.kit_backup }));
  const css = plat.snippets.find((x) => /aviso/.test(x.code || ''));
  const js = plat.snippets.find((x) => /js-migrado/.test(x.code || ''));
  check(s, 'P2.7g', 'el CSS global es Código personalizado publicado (administrador con unfiltered_html) y el JavaScript, un borrador', !!css && css.status === 'publish' && !!js && js.status === 'draft', JSON.stringify(plat.snippets.map((x) => [x.title, x.status, x.location])));
  const side = JSON.stringify(plat.sidebars['sidebar-1'] || []);
  check(s, 'P2.7h', 'la barra lateral usa el widget de plantilla de Elements', /elementor-library-/.test(side) && !/elementortemplate_widget/.test(side), side);

  // Extensiones: los 18 documentos sin claves del origen.
  const posts = (await control('posts', { types: 'page' })).posts;
  const extDocs = posts.filter((p) => /^digi-f8-ext-/.test(p.slug));
  const leftovers = extDocs.filter((p) => p.legacy);
  check(s, 'P2.8a', `las 18 extensiones: ${extDocs.length} documentos convertidos sin claves del origen`, extDocs.length === 18 && !leftovers.length, JSON.stringify(leftovers.map((p) => p.slug)));
  const tip = await control('doc', { key: 'digi-f8-ext-tooltip', full: '1' });
  const tipSettings = JSON.stringify(walk(tip.doc.data).map((e) => e.settings));
  check(s, 'P2.8b', 'la información emergente pasa al rasgo de Elements', /"digitalisimo_tooltip":"yes"/.test(tipSettings), tipSettings.slice(0, 300));
  const badge = await control('doc', { key: 'digi-f8-ext-global-badge', full: '1' });
  check(s, 'P2.8c', 'el distintivo pasa al rasgo de Elements', /"digitalisimo_badge":"yes"/.test(JSON.stringify(walk(badge.doc.data).map((e) => e.settings))), '');
  const vt = await open(m.urls['digi-f8-ext-tooltip']);
  const tipDom = await vt.page.evaluate(() => { const host = document.querySelector('[data-digi-tooltip], .digi-tooltip, [aria-describedby]'); return host ? host.outerHTML.slice(0, 300) : null; });
  check(s, 'P2.8d', 'la página de la información emergente la pinta sin errores de JavaScript', !!tipDom && !vt.facts.errors.length, JSON.stringify({ tipDom, errors: vt.facts.errors.slice(0, 3) }));
  await vt.close();

  // Informe y secretos.
  const report = st.report || {};
  const html = (last && last.html) || '';
  check(s, 'P2.9a', 'el informe final existe y la pantalla lo pinta', !!st.report && html.length > 200, '');
  check(s, 'P2.9b', 'el informe nombra la contraseña de aplicación del asistente sin su valor', /Element Pack AI/.test(html) && !html.includes(secret.password || '¬') && !html.includes('clave-ficticia-f8-no-mostrar'), '');
  const t2 = await tool('admin');
  fs.writeFileSync(path.join(RUN, 'pages', 'tool-final.html'), t2.html);
  check(s, 'P2.9c', 'la herramienta tras migrar sigue con un solo botón y sin secretos', t2.buttons.length === 1 && !t2.html.includes(secret.password || '¬'), JSON.stringify(t2.buttons));
  check(s, 'P2.9d', 'hay asuntos «Pendiente de revisar» (borradores del JavaScript y del carrito)', /Pendiente de revisar/i.test(t2.html), '');

  // Sin rastro del proveedor en lo publicado (P7).
  const trace = {};
  for (const key of ['digi-f8-inicio', 'digi-f8-especial', 'digi-f8-iconos', 'digi-f8-fuente', 'digi-f8-bdt-accordion', 'digi-f8-bdt-creative-button', ...extDocs.map((p) => p.slug)]) {
    if (!m.urls[key]) continue;
    const r = await request('GET', m.urls[key]);
    const found = vendorTrace(r.text);
    if (found.length || r.status !== 200) trace[key] = { status: r.status, found };
  }
  check(s, 'P2.10', 'P7: ninguna página migrada nombra un dominio, vídeo o carpeta del proveedor, y todas responden 200', !Object.keys(trace).length, JSON.stringify(trace));
  note(s, `pasos: ${res.steps.length}; etapas: ${s.facts.stages.join(', ')}`);
};
