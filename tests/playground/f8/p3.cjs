// P3 · Red (f8.sh): «Migrar toda la red» con la barrera de V.6, en http://playground.test. Casos en f8/p3.php.
// `main`: un clic en la pantalla de red (Chrome) y la pantalla se cierra; todo sigue con el WP-Cron de cada sitio y el
// del sitio principal, con un sondeo de las portadas en cada vuelta: ningún sitio sin cabecera ni con dos.
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => async (s) => {
  const { ENV, RUN, BASE, check, pending, note, control, login, tool, step, request, context, newPage, headers, sleep, jars, log } = api;
  const CASE = ENV.P3_CASE || 'main';
  const info = await control('sites');
  const paths = Object.fromEntries(info.sites.map((x) => [x.path, x.id]));
  const prefix = (p) => p.replace(/\/$/, '');
  const withHeader = ['/', '/dos/'];
  const lastLog = [];

  /** Cabeceras de las portadas con plantillas; devuelve las filas que no tienen exactamente una cabecera y un pie. */
  async function poll(label, allowNone = []) {
    const bad = [];
    for (const p of withHeader) {
      const r = await request('GET', p);
      const h = headers(r.text);
      lastLog.push({ label, site: p, status: r.status, ep: h.ep, el: h.el });
      if (allowNone.includes(p) && h.header === 0) continue;
      if (r.status !== 200 || h.header !== 1 || h.footer !== 1) bad.push({ label, site: p, status: r.status, ep: h.ep, el: h.el, epF: h.epF, elF: h.elF });
    }
    return bad;
  }
  const netJob = async () => (await control('state')).network_job || {};
  /** WP-Cron de un sitio: adelanta sus eventos de Migración y pide /wp-cron.php (como una visita con WP-Cron real). */
  async function cron(site, hook) {
    await control('due', { hook }, { site: prefix(site) });
    const r = await request('GET', `${prefix(site)}/?digi_f8=cron&token=${ENV.TOKEN}`);
    return r.status;
  }

  // ── Antes ────────────────────────────────────────────────────────────────────────────────────────────────────
  check(s, 'P3.1a', `red con ${info.sites.length} sitios (uno archivado) y el origen activado en la red según el caso «${CASE}»`, info.sites.some((x) => x.archived === 1) && info.sites.length >= 4, JSON.stringify(info));
  const bad0 = await poll('antes');
  check(s, 'P3.1b', 'antes: el principal y /dos/ tienen la cabecera y el pie del origen', !bad0.length && lastLog.slice(-2).every((x) => x.ep.length === 1), JSON.stringify(bad0.length ? bad0 : lastLog.slice(-2)));

  // ── Un clic en la pantalla de red ───────────────────────────────────────────────────────────────────────────
  await login('admin');
  const t = await tool('admin', { network: true });
  check(s, 'P3.2a', 'la pantalla de red tiene un solo botón: «Migrar toda la red»', t.buttons.length === 1 && /Migrar toda la red/.test(t.buttons[0]), JSON.stringify(t.buttons));
  const bad = [];
  let releasedBy = '';
  if (CASE === 'main') {
    // Chrome: clic y cierre de la pantalla en cuanto la red arranca (la tarea de red existe).
    const ctx = await context('admin');
    const page = await newPage(ctx);
    await page.goto(`${BASE}/wp-admin/network/settings.php?page=digitalisimo-ep-migration`, { waitUntil: 'networkidle0', timeout: 180000 });
    await page.click('#digi-migrate .digi-migrate__button');
    let started = false;
    for (let i = 0; i < 60 && !started; i++) { await sleep(1000); const j = await netJob(); started = !!(j && j.started); }
    await ctx.close();
    check(s, 'P3.2b', 'el clic arranca la tarea de red y la pantalla se cierra', started, '');
  } else {
    const r = await step('admin', t, { start: true, network: true });
    check(s, 'P3.2b', 'el paso de red arranca la tarea', r.ok, JSON.stringify(r.data || r.raw).slice(0, 300));
  }
  const j0 = await netJob();
  fs.writeFileSync(path.join(RUN, 'network-start.json'), JSON.stringify(j0, null, 1));
  const expectBarrier = [paths['/'], paths['/dos/']];
  check(s, 'P3.3a', 'barrera: sólo los sitios con algo que preparar; el archivado y el que no tiene plantillas, fuera', !!j0.barrier && JSON.stringify([...(j0.barrier_sites || [])].sort()) === JSON.stringify(expectBarrier.sort()) && (j0.excluded || []).includes(paths['/archivado/']), JSON.stringify({ barrier: j0.barrier, barrier_sites: j0.barrier_sites, excluded: j0.excluded, sites: j0.sites }));
  bad.push(...await poll('tras el arranque'));

  // ── Sin pantalla: WP-Cron de cada sitio y del principal ─────────────────────────────────────────────────────
  const drive = CASE === 'deadline' ? ['/', '/tres/'] : info.sites.filter((x) => !x.archived).map((x) => x.path);
  const t0 = Date.now();
  let j = j0;
  let rounds = 0;
  const limit = CASE === 'blocked' ? 150000 : 330000;
  while (Date.now() - t0 < limit) {
    rounds++;
    const row = { round: rounds };
    for (const p of drive) {
      const code = await cron(p, 'digitalisimo_migration_continue');
      const sx = await control('state', {}, { site: prefix(p) });
      row[p] = `${code} ${sx.job ? `${sx.job.stage}${sx.job.done ? ' done' : ''}${sx.job.pause ? ' pausa' : ''}${sx.job.wait_network ? ' espera' : ''}` : (sx.report ? 'terminada' : 'sin tarea')} ${(sx.crons || []).map((c) => `${c.hook.replace('digitalisimo_migration_', '')}@${c.in}`).join(',')}`;
    }
    lastLog.push(row);
    log(`  ${JSON.stringify(row)}`);
    bad.push(...await poll(`vuelta ${rounds}`, CASE === 'deadline' && j.released ? ['/dos/'] : []));
    const before = !!j.released;
    await cron('/', 'digitalisimo_migration_network_tick');
    j = await netJob();
    if (!before && j.released) releasedBy = `vuelta ${rounds}`;
    if (j.done || (CASE === 'blocked' && j.stopped)) break;
    // Con /dos/ sin responder la red no puede terminar: tras la liberación por plazo se sale y /dos/ «vuelve».
    if (CASE === 'deadline' && j.released && drive.every((p) => /terminada/.test(String(row[p])))) break;
    if (CASE === 'deadline' && !j.released) await sleep(6000);
  }
  fs.writeFileSync(path.join(RUN, 'network-end.json'), JSON.stringify(j, null, 1));
  fs.writeFileSync(path.join(RUN, 'polls.json'), JSON.stringify(lastLog, null, 1));
  s.facts.rounds = rounds;
  s.facts.released_by = releasedBy;
  log(`red: ${rounds} vueltas, liberada en ${releasedBy || '—'}, done=${!!j.done}`);

  let st1 = await control('state');
  let plugins = st1.plugins;
  if (CASE === 'blocked') {
    check(s, 'P3.4a', 'sitio bloqueado: la barrera no se libera y el origen sigue activo en la red', !j.released && plugins.network.includes('bdthemes-element-pack/bdthemes-element-pack.php'), JSON.stringify({ released: j.released, network: plugins.network }));
    const html = (await tool('admin', { network: true })).html;
    check(s, 'P3.4b', 'la tarea de red queda detenida con el aviso del sitio bloqueado (core.barrier_blocked)', /bloque/i.test(html) || JSON.stringify(j).includes('barrier_blocked'), JSON.stringify({ stopped: j.stopped, status: (j.status || []).map((x) => x.status) }));
    check(s, 'P3.4c', 'ningún sitio se quedó sin cabecera ni con dos en ninguna vuelta', !bad.length, JSON.stringify(bad.slice(0, 6)));
    return;
  }
  if (CASE !== 'deadline') check(s, 'P3.4a', 'la barrera se libera sin la pantalla (sólo WP-Cron) y la red termina', !!j.released && !!j.done, JSON.stringify({ released: j.released, done: j.done, rounds, unprepared: j.unprepared }));
  else check(s, 'P3.4a', 'la barrera se libera por el plazo relativo, sin la pantalla (sólo WP-Cron)', !!j.released, JSON.stringify({ released: j.released, rounds, unprepared: j.unprepared }));
  if (CASE === 'deadline') {
    check(s, 'P3.4b', 'plazo relativo: /dos/ (que no respondió) queda listado como sin preparar', (j.unprepared || []).includes(paths['/dos/']), JSON.stringify(j.unprepared));
    check(s, 'P3.4c', 'el principal nunca se quedó sin cabecera ni con dos; /dos/ sólo sin la suya tras la liberación (ventana declarada)', !bad.length, JSON.stringify(bad.slice(0, 6)));
    // /dos/ migra cuando vuelve a responder: se conduce ahora.
    for (let i = 0; i < 12; i++) { await cron('/dos/', 'digitalisimo_migration_continue'); const sd = await control('state', {}, { site: '/dos' }); if ((sd.job && sd.job.done) || (!sd.job && sd.report)) break; }
    for (let i = 0; i < 3 && !j.done; i++) { await cron('/', 'digitalisimo_migration_network_tick'); j = await netJob(); }
    check(s, 'P3.4d', 'cuando /dos/ vuelve, migra y la red termina', !!j.done, JSON.stringify({ done: j.done, status: j.status }));
    st1 = await control('state');
    plugins = st1.plugins;
  } else {
    check(s, 'P3.4b', 'ningún sitio se quedó sin cabecera ni con dos en ninguna vuelta', !bad.length, JSON.stringify(bad.slice(0, 6)));
  }
  const retired = ['bdthemes-element-pack/bdthemes-element-pack.php', 'bdthemes-element-pack-addon/bdthemes-element-pack-addon.php', 'bdthemes-prime-slider/bdthemes-prime-slider.php', 'bdthemes-prime-slider-lite/bdthemes-prime-slider.php'];
  check(s, 'P3.5a', 'el origen y Prime Slider (activados en la red) quedan desactivados en la red; Prime Slider al final', retired.every((p) => !plugins.network.includes(p)), JSON.stringify(plugins.network));
  const per = {};
  for (const x of info.sites.filter((y) => !y.archived)) {
    const sx = await control('state', {}, { site: prefix(x.path) });
    // Una tarea terminada se cierra: queda el informe (`report`) y no la tarea.
    per[x.path] = { done: !!((sx.job && sx.job.done) || (!sx.job && sx.report)), site: sx.plugins.site.filter((p) => /bdthemes/.test(p)), ep: sx.ep };
  }
  check(s, 'P3.5b', 'cada sitio no archivado terminó su tarea y no le queda nada del origen activo por sitio', Object.values(per).every((x) => x.done && !x.site.length && !x.ep), JSON.stringify(per));
  const arch = await control('state', {}, { site: '/archivado' });
  check(s, 'P3.5c', 'el sitio archivado no se tocó (sin tarea)', !arch.job, JSON.stringify(arch.job || null).slice(0, 200));
  const after = await poll('después');
  check(s, 'P3.6', 'después: el principal y /dos/ pintan la copia de su cabecera y pie (Elements)', !after.length && lastLog.slice(-2).every((x) => x.el.length === 1 && !x.ep.length), JSON.stringify(after.length ? after : lastLog.slice(-2)));
  const html = (await tool('admin', { network: true })).html;
  fs.writeFileSync(path.join(RUN, 'pages', 'network-final.html'), html);
  check(s, 'P3.7', 'la pantalla de red muestra el resumen con una columna por sitio y sigue con un solo botón', /Migración de la red completada|completada/i.test(html) && (await tool('admin', { network: true })).buttons.length === 1, '');
  note(s, `vueltas de WP-Cron: ${rounds}; liberada en ${releasedBy}`);
};
