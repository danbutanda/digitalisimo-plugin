// P8 · Vuelta atrás (f8.sh). `P8_CASE=migration`: Migración 1.0.20 hasta pasar `deactivate`, actualización a 1.0.21
// (copias preparadas tarde, tb.late) y vuelta a 1.0.20 con las copias publicadas (siguen publicadas sin el origen; con
// el origen reactivado, dos cabeceras: límite declarado de V.18). `P8_CASE=elements`: Elements 4.3.0.169 con una
// tarea en curso (pausa con core.elements_old, sin cerrar) y con los rasgos guardados (nada se rompe, no se ven).
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

module.exports = (api) => async (s) => {
  const { ENV, RUN, check, note, control, login, tool, step, migrate, request, headers, sleep } = api;
  const CASE = ENV.P8_CASE || 'migration';
  const mnt = path.join(RUN, 'mnt');
  const swap = (plugin, from) => {
    const dst = path.join(mnt, plugin);
    if (!dst.startsWith(RUN + '/') || !fs.existsSync(path.join(from, plugin === 'digitalisimo-elements' ? 'pro-elements.php' : 'digitalisimo-migration.php'))) throw new Error(`copia no válida: ${from}`);
    execFileSync('bash', ['-c', `find "${dst}" -mindepth 1 -maxdepth 1 -exec rm -rf {} + && cp -a "${from}/." "${dst}/"`]);
  };
  const OLD_MIG = path.join(ENV.VENDOR, 'migration-1020', 'digitalisimo-migration');
  const OLD_ELE = path.join(ENV.VENDOR, 'elements-169', 'digitalisimo-elements');
  const NEW_MIG = path.join(ENV.SRC, 'digitalisimo-migration');
  const NEW_ELE = path.join(ENV.SRC, 'digitalisimo-elements');
  const m = await control('ids');
  const front = async () => headers((await request('GET', m.home)).text);
  await login('admin');

  if (CASE === 'migration') {
    swap('digitalisimo-migration', OLD_MIG);
    const p0 = await control('ping');
    check(s, 'P8.m1', 'Migración 1.0.20 (ZIP publicado) cargada sobre el sitio', p0.migration === '1.0.20', p0.migration);
    const t = await tool('admin');
    const run = Date.now().toString(36);
    const steps = [];
    let r = await step('admin', t, { start: true, run });
    steps.push(r.data && r.data.stage);
    // La primera respuesta con una etapa posterior a `deactivate` (1.0.20 desactiva y pausa) deja la tarea a medias.
    for (let i = 0; i < 20 && r.ok && !(r.data.done) && ['prepare', 'deactivate', ''].includes(String(r.data.stage || '')); i++) { await sleep(300); r = await step('admin', t, { run }); steps.push(r.data && r.data.stage); }
    const h120 = await front();
    const st0 = await control('state');
    check(s, 'P8.m2', 'con 1.0.20 la tarea pasa `deactivate` (el origen ya no está) y queda a medias', !st0.ep && st0.job && !st0.job.done && st0.job.version === '1.0.20', JSON.stringify({ steps, stage: st0.job && st0.job.stage }));
    note(s, `1.0.20 tras desactivar: la portada tiene ${h120.header} cabeceras (1.0.20 no copia el Theme Builder: es el hueco que F8 cierra)`);
    swap('digitalisimo-migration', NEW_MIG);
    const p1 = await control('ping');
    const res = await migrate('admin', { start: false });
    const st1 = await control('state', { registry: '1' });
    const codes = (((st1.report_f8 || {}).site || {}).items || []).map((c) => c.code);
    check(s, 'P8.m3', `actualizada a ${p1.migration}, la tarea de 1.0.20 sigue sin repetir y termina`, p1.migration === ENV.MIGRATION_VERSION && !!(res.last && res.last.data && res.last.data.done), JSON.stringify(res.steps.map((x) => x.stage)));
    check(s, 'P8.m4', 'prepara las copias tarde, las publica sin el origen e informa tb.late', codes.includes('tb.late'), JSON.stringify(codes));
    const h121 = await front();
    check(s, 'P8.m5', 'la portada vuelve a tener exactamente una cabecera y un pie (las copias)', h121.el.length === 1 && h121.elF.length === 1 && !h121.ep.length, JSON.stringify(h121));
    swap('digitalisimo-migration', OLD_MIG);
    const hBack = await front();
    check(s, 'P8.m6', 'vuelta a 1.0.20 con las copias publicadas: siguen publicadas y se ven sin el origen', (await control('ping')).migration === '1.0.20' && hBack.el.length === 1 && !hBack.ep.length, JSON.stringify(hBack));
    await control('plugin', { state: 'on' });
    const hBoth = await front();
    check(s, 'P8.m7', 'límite declarado (V.18): con el origen reactivado y sin 1.0.21, se pintan las dos cabeceras', hBoth.ep.length === 1 && hBoth.el.length === 1, JSON.stringify(hBoth));
    swap('digitalisimo-migration', NEW_MIG);
    const hFixed = await front();
    check(s, 'P8.m8', 'con 1.0.21 otra vez, la copia vuelve a borrador mientras el origen esté cargado (una sola cabecera)', hFixed.header === 1 && hFixed.ep.length === 1, JSON.stringify(hFixed));
    return;
  }

  // Elements 4.3.0.169.
  const r0 = await request('GET', m.urls['digi-f8-rasgos']);
  check(s, 'P8.e1', `con ${ENV.ELEMENTS_VERSION} la página de rasgos pinta la información emergente, el distintivo y el contador`, /data-digi-tooltip/.test(r0.text) && /digi-badge/.test(r0.text) && /digi-nslider__count/.test(r0.text), '');
  const t = await tool('admin');
  const run = Date.now().toString(36);
  const r1 = await step('admin', t, { start: true, run });
  check(s, 'P8.e2', 'una tarea en curso (primer paso)', r1.ok && !r1.data.done, JSON.stringify(r1.data && { stage: r1.data.stage, pause: r1.data.pause }));
  swap('digitalisimo-elements', OLD_ELE);
  const p = await control('ping');
  check(s, 'P8.e3', 'Elements 4.3.0.169 (ZIP publicado) instalado sobre el sitio', p.elements === '4.3.0.169', p.elements);
  const stepsOld = [];
  for (let i = 0; i < 3; i++) { const r = await step('admin', t, { run }); stepsOld.push({ ok: r.ok, stage: r.data && r.data.stage, pause: r.data && r.data.pause, done: r.data && r.data.done }); await sleep(500); }
  const st = await control('state');
  const codes = (((st.report_f8 || {}).site || {}).items || []).map((c) => c.code);
  check(s, 'P8.e4', 'con 4.3.0.169 la tarea se pausa con core.elements_old y nunca se cierra', stepsOld.every((x) => x.ok && x.pause && !x.done) && st.job && !st.job.done && codes.includes('core.elements_old'), JSON.stringify({ stepsOld, codes }));
  const t2 = await tool('admin');
  const start = await step('admin', t2, { start: true, run: 'nuevo' + run });
  check(s, 'P8.e5', 'el botón se niega con 4.3.0.169 (error 409 con el motivo) sin crear otra tarea ni tocar la que hay', !start.ok && start.http === 409 && (await control('state')).job.started === st.job.started, JSON.stringify({ http: start.http, data: start.data, raw: start.raw }).slice(0, 300));
  const bad = [];
  for (const key of Object.keys(m.urls)) {
    const r = await request('GET', m.urls[key]);
    if (r.status !== 200 || /critical error|Fatal error/i.test(r.text)) bad.push(`${key}: ${r.status}`);
    if (key === 'digi-f8-rasgos') {
      check(s, 'P8.e6', 'con 4.3.0.169 los rasgos guardados no se ven (regresión declarada) y la página no se rompe', !/data-digi-tooltip|digi-badge|digi-nslider__count/.test(r.text) && /Después de los rasgos/.test(r.text) && /Diapositiva uno/.test(r.text), '');
    }
  }
  check(s, 'P8.e7', 'todas las páginas responden 200 sin error de PHP con 4.3.0.169', !bad.length, bad.join(', '));
  swap('digitalisimo-elements', NEW_ELE);
  const res = await migrate('admin', { start: false });
  check(s, 'P8.e8', `con ${ENV.ELEMENTS_VERSION} otra vez, la tarea pausada continúa y termina`, !!(res.last && res.last.data && res.last.data.done), JSON.stringify(res.steps.map((x) => x.stage)));
  const r9 = await request('GET', m.urls['digi-f8-rasgos']);
  check(s, 'P8.e9', 'y los rasgos vuelven a verse sin haberse perdido nada', /data-digi-tooltip/.test(r9.text) && /digi-badge/.test(r9.text) && /digi-nslider__count/.test(r9.text), '');
};
