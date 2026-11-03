// P9 · Reversión (f8.sh): «Migrar» del sitio de P2, una copia editada después, `--revert` sin el origen (se niega) y con
// el origen reactivado: documentos, plantillas, fuentes, iconos, fragmentos, kit y barra lateral como antes, salvo lo
// editado (omitido e informado); los plugins siguen desactivados y sin borrar.
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => async (s) => {
  const { ENV, RUN, check, note, control, cli, login, migrate } = api;
  const before = await control('snapshot');
  await login('admin');
  const res = await migrate('admin');
  check(s, 'P9.1', '«Migrar» termina', !!(res.last && res.last.data && res.last.data.done), JSON.stringify(res.steps.slice(-1)));
  const mid = await control('snapshot');
  const tb = await control('tb');
  const edited = tb.templates.find((x) => x.title === 'Plantilla de entrada');
  const e = await control('edit', { id: String(edited ? edited.id : 0) });
  check(s, 'P9.2', 'una copia del Theme Builder se edita después de migrar', e.ok, JSON.stringify(edited || null));
  const url = `--url=${ENV.BASE}/`;
  const refused = await cli(['digitalisimo-migration', '--revert', url, '--user=admin']);
  fs.writeFileSync(path.join(RUN, 'revert-refused.json'), JSON.stringify(refused, null, 1));
  check(s, 'P9.3', 'sin el origen activo, --revert se niega y no toca nada', /exige Element Pack activo/.test(refused.stderr + refused.stdout) && JSON.stringify(await control('snapshot')) === JSON.stringify(mid), (refused.stderr || refused.stdout || refused.fatal).slice(0, 300));
  await control('plugin', { state: 'on' });
  const out = await cli(['digitalisimo-migration', '--revert', url, '--user=admin']);
  fs.writeFileSync(path.join(RUN, 'revert.json'), JSON.stringify(out, null, 1));
  check(s, 'P9.4', 'con el origen reactivado, --revert termina sin errores', /Documentos restaurados/.test(out.stdout + out.stderr) && !out.fatal, (out.stdout + ' ' + out.stderr + ' ' + out.fatal).slice(0, 500));
  const after = await control('snapshot');
  fs.writeFileSync(path.join(RUN, 'snapshots.json'), JSON.stringify({ before, mid, after }, null, 1));
  const changed = Object.entries(before.docs).filter(([k, v]) => k.startsWith('page:') && after.docs[k] && after.docs[k].md5 !== v.md5).map(([k]) => k);
  const migrated = Object.entries(before.docs).filter(([k, v]) => k.startsWith('page:') && mid.docs[k] && mid.docs[k].md5 !== v.md5).length;
  check(s, 'P9.5', `los ${migrated} documentos que la migración cambió vuelven a su contenido original`, migrated > 0 && !changed.length, JSON.stringify(changed.map((k) => ({ k, before: before.docs[k].raw, mid: mid.docs[k].raw, after: after.docs[k].raw, metas: after.docs[k].metas }))));
  const copies = Object.entries(after.docs).filter(([k, v]) => k.startsWith('elementor_library:') && !before.docs[k]);
  const live = copies.filter(([, v]) => v.status === 'publish');
  const editedNow = copies.find(([, v]) => v.id === (edited && edited.id));
  check(s, 'P9.6', 'las copias del Theme Builder dejan de publicarse (papelera o borrador) y la editada se conserva', !live.length && !!editedNow && editedNow[1].status !== 'trash', JSON.stringify(copies.map(([k, v]) => `${k}:${v.status}`)));
  check(s, 'P9.7', 'la reversión informa de la copia editada que omitió', /omit|cambió después|editad/i.test(out.stdout + out.stderr), (out.stdout + out.stderr).slice(0, 400));
  check(s, 'P9.8', 'el kit y la barra lateral vuelven a como estaban', after.kit === before.kit && after.sidebars === before.sidebars, JSON.stringify({ kit: [before.kit, mid.kit, after.kit], sidebars: [before.sidebars, mid.sidebars, after.sidebars] }));
  const plat = await control('platform');
  check(s, 'P9.9', 'fuentes, iconos y CSS global creados por la migración retirados (papelera o borrador) y la copia de iconos sin alias', plat.fonts.every((f) => f.status !== 'publish') && plat.icons.every((f) => f.status !== 'publish') && plat.snippets.every((f) => f.status !== 'publish') && !plat.alias, JSON.stringify({ fonts: plat.fonts.map((f) => f.status), icons: plat.icons.map((f) => f.status), snippets: plat.snippets.map((f) => f.status), alias: plat.alias }));
  const st = await control('state');
  const off = ['bdthemes-element-pack-addon/bdthemes-element-pack-addon.php', 'bdthemes-prime-slider/bdthemes-prime-slider.php', 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php'];
  check(s, 'P9.10', 'los demás plugins siguen desactivados y ninguno se borró', off.every((p) => !st.plugins.site.includes(p)) && off.every((p) => plat.installed.includes(p)), JSON.stringify(st.plugins.site));
  note(s, `salida de --revert: ${(out.stdout || '').replace(/\s+/g, ' ').slice(0, 300)}`);
};
