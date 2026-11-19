// Resumen de una corrida de admin-perf-all.sh (raw.json + baseline.json) en Markdown, y summary.json con las medianas.
// Uso: node report.cjs <carpeta de la corrida>
const fs = require('fs');
const path = require('path');
const dir = process.argv[2];
const raw = JSON.parse(fs.readFileSync(path.join(dir, 'raw.json'), 'utf8'));
const baseline = fs.existsSync(path.join(dir, 'probe', 'baseline.json')) ? JSON.parse(fs.readFileSync(path.join(dir, 'probe', 'baseline.json'), 'utf8')) : null;
const median = (a) => { const v = a.filter((x) => typeof x === 'number').sort((x, y) => x - y); return v.length ? v[Math.floor(v.length / 2)] : null; };
const fmt = (v, d = 0) => (v === null || v === undefined ? '—' : Number(v).toFixed(d));
const delta = (v, b, d = 0) => (v === null || b === null ? '—' : `${v - b >= 0 ? '+' : ''}${(v - b).toFixed(d)}`);
const out = [];
const summary = { mode: raw.mode, screens: {}, ajax: {} };
const OWNED = ['backups', 'tools', 'theme', 'seo', 'ia', 'hosting', 'elements', 'migration'];

// Medianas por pantalla y conjunto.
const stats = {};
for (const [key, runs] of Object.entries(raw.screens)) {
  const [set, screen] = key.split('|');
  const plain = runs.filter((r) => !r.profile && r.main);
  stats[screen] = stats[screen] || {};
  stats[screen][set] = {
    ms: median(plain.map((r) => r.main.ms)), q: median(plain.map((r) => r.main.queries)), mem: median(plain.map((r) => r.main.mem_peak_kb)),
    files: median(plain.map((r) => r.main.included)), status: plain.map((r) => r.status),
    phases: plain[0] ? plain[0].main.marks : {},
  };
}
summary.screens = stats;
const sets = raw.sets;
out.push(`# admin-perf · ${raw.mode} · PHP ${(Object.values(raw.screens)[0] || [{}])[0].main ? Object.values(raw.screens)[0][0].main.php : '?'}`);
out.push('');
out.push(`Mediana de ${raw.loads} cargas (tras una de calentamiento), tiempo del servidor desde \`$timestart\` hasta el final, consultas SQL y memoria pico (KB). Δ frente a «none» (ningún plugin DIGITALÍSIMO; tema Twenty Twenty-Five).`);
out.push('');
out.push(`| Pantalla | none ms | none consultas | none KB | ${sets.filter((s) => s !== 'none').map((s) => `${s} Δms / Δq / ΔKB`).join(' | ')} |`);
out.push(`|---|---:|---:|---:|${sets.filter((s) => s !== 'none').map(() => '---:').join('|')}|`);
for (const [screen, bySet] of Object.entries(stats)) {
  const b = bySet.none || { ms: null, q: null, mem: null };
  const cells = sets.filter((s) => s !== 'none').map((s) => (bySet[s] ? (bySet.none ? `${delta(bySet[s].ms, b.ms)} / ${delta(bySet[s].q, b.q)} / ${delta(bySet[s].mem, b.mem)}` : `${fmt(bySet[s].ms)} / ${fmt(bySet[s].q)} / ${fmt(bySet[s].mem)}`) : ''));
  out.push(`| ${screen} | ${fmt(b.ms)} | ${fmt(b.q)} | ${fmt(b.mem)} | ${cells.join(' | ')} |`);
}

// Suma de los aislados frente a todos juntos (interferencias).
const singles = ['backups', 'tools', 'theme', 'seo', 'ia', 'hosting', 'elements', 'migration'].filter((s) => sets.includes(s));
if (sets.includes('all') && singles.length) {
  out.push('');
  out.push('## Suma de cada pieza sola frente a todas juntas');
  out.push('');
  out.push('Migración se mide con Elements (lo requiere): su parte es «migration − elements». Interferencia = todos − suma.');
  out.push('');
  out.push('| Pantalla | suma Δms | todos Δms | interferencia ms | suma Δq | todos Δq | interferencia q |');
  out.push('|---|---:|---:|---:|---:|---:|---:|');
  for (const [screen, bySet] of Object.entries(stats)) {
    if (!bySet.none || !bySet.all) continue;
    let sms = 0; let sq = 0; let ok = true;
    for (const s of singles) {
      if (!bySet[s]) { ok = false; break; }
      const base = s === 'migration' ? bySet.elements : bySet.none;
      if (!base) { ok = false; break; }
      sms += bySet[s].ms - base.ms; sq += bySet[s].q - base.q;
    }
    if (!ok) continue;
    const ams = bySet.all.ms - bySet.none.ms; const aq = bySet.all.q - bySet.none.q;
    out.push(`| ${screen} | ${fmt(sms)} | ${fmt(ams)} | ${fmt(ams - sms)} | ${fmt(sq)} | ${fmt(aq)} | ${fmt(aq - sq)} |`);
  }
}

// Peticiones de fondo.
out.push('');
out.push('## Peticiones de fondo');
out.push('');
out.push('| Petición | Conjunto | ms | consultas | KB |');
out.push('|---|---|---:|---:|---:|');
for (const [key, runs] of Object.entries(raw.ajax)) {
  const [set, name] = key.split('|');
  const rows = runs.map((r) => r.rows[0]).filter(Boolean);
  summary.ajax[key] = { ms: median(rows.map((r) => r.ms)), q: median(rows.map((r) => r.queries)), mem: median(rows.map((r) => r.mem_peak_kb)) };
  out.push(`| ${name} | ${set} | ${fmt(summary.ajax[key].ms)} | ${fmt(summary.ajax[key].q)} | ${fmt(summary.ajax[key].mem)} |`);
}

// HTTP saliente en cargas normales.
const http = {};
const addHttp = (where, list) => { for (const h of list || []) { const k = `${h.by} → ${h.url}${h.blocking ? '' : ' (no bloqueante)'}`; http[k] = http[k] || { n: 0, where: new Set() }; http[k].n++; http[k].where.add(where); } };
for (const [key, runs] of Object.entries(raw.screens)) for (const r of runs) { if (r.main) addHttp(key, r.main.http); for (const o of r.others) addHttp(`${key} (secundaria)`, o.http); }
for (const [key, runs] of Object.entries(raw.ajax)) for (const r of runs) for (const row of r.rows) addHttp(key, row.http);
out.push('');
out.push('## HTTP saliente durante las cargas normales (fuera de los escenarios de actualizadores)');
out.push('');
if (!Object.keys(http).length) out.push('Ninguna.');
for (const [k, v] of Object.entries(http)) out.push(`- ${k}: ${v.n} vez/veces en ${[...v.where].slice(0, 8).join(', ')}${v.where.size > 8 ? '…' : ''}`);

// Actualizadores.
out.push('');
out.push('## Actualizadores y catálogos');
out.push('');
for (const [set, steps] of Object.entries(raw.updaters || {})) {
  out.push(`### ${set}`);
  out.push('');
  out.push('| Paso | ms | consultas | HTTP (quién → qué) |');
  out.push('|---|---:|---:|---|');
  for (const s of steps) {
    const list = [...(s.http || []), ...(s.others || [])];
    const by = {};
    for (const h of list) { const k = `${h.by}→${h.url.replace('github.com/danbutanda/digitalisimo-plugin/releases/latest/download/', 'gh:')}${h.result === 'error' ? ' ✗' : ''}`; by[k] = (by[k] || 0) + 1; }
    out.push(`| ${s.label} | ${fmt(s.ms)} | ${fmt(s.queries)} | ${list.length}: ${Object.entries(by).map(([k, n]) => `${k}×${n}`).join(', ')} |`);
  }
  out.push('');
}

// WP-Cron.
out.push('## Vuelta de WP-Cron con los eventos periódicos vencidos');
out.push('');
for (const [set, c] of Object.entries(raw.cron || {})) {
  for (const r of c.rows) out.push(`- ${set}: ${(c.moved || []).length} eventos (${[...new Set(c.moved || [])].join(', ')}): ${fmt(r.ms)} ms, ${r.queries} consultas, ${r.mem_peak_kb} KB, HTTP ${r.http.length} (${r.http.map((h) => `${h.by}→${h.url}`).join(', ')})`);
}

// Eventos de cron y autoload.
const st = raw.state;
if (st) {
  out.push('');
  out.push('## WP-Cron: eventos de los plugins');
  out.push('');
  out.push('| Evento | Frecuencia | Intervalo (s) |');
  out.push('|---|---|---:|');
  const seen = new Set();
  for (const e of st.cron.filter((c) => c.ours)) { const k = e.hook + e.schedule; if (seen.has(k)) continue; seen.add(k); out.push(`| ${e.hook} | ${e.schedule} | ${e.interval || ''} |`); }
  out.push('');
  out.push('## Opciones con carga automática');
  out.push('');
  const before = baseline ? baseline.options : {};
  const added = st.options.filter((o) => o.auto && !(before[o.name] && before[o.name][1]));
  const total = added.reduce((n, o) => n + o.size, 0);
  out.push(`Total con autoload: ${st.autoload_count} opciones, ${(st.autoload_total / 1024).toFixed(1)} KB. Nuevas desde la foto sin plugins: ${added.length}, ${(total / 1024).toFixed(1)} KB.`);
  out.push('');
  out.push('| Opción | Bytes |');
  out.push('|---|---:|');
  for (const o of added.sort((a, b) => b.size - a.size)) out.push(`| ${o.name} | ${o.size} |`);
  const big = st.options.filter((o) => !o.auto && o.ours && o.size > 4096).sort((a, b) => b.size - a.size).slice(0, 15);
  if (big.length) { out.push(''); out.push('Opciones propias sin autoload de más de 4 KB: ' + big.map((o) => `${o.name} (${o.size})`).join(', ')); }
  if (st.sitemeta && st.sitemeta.length) { out.push(''); out.push('sitemeta propia: ' + st.sitemeta.filter((o) => o.ours).sort((a, b) => b.size - a.size).map((o) => `${o.name} (${o.size})`).join(', ')); }
}

// Recursos en pantallas ajenas.
out.push('');
out.push('## Scripts y hojas de los plugins en pantallas que no son suyas');
out.push('');
const own = /page=digitalisimo|digitalisimo-ep-migration/;
const assets = {};
for (const [key, runs] of Object.entries(raw.screens)) {
  const [set, screen] = key.split('|');
  const r = runs.find((x) => x.main);
  if (!r || own.test(r.main.uri)) continue;
  for (const a of r.main.assets || []) { const k = `${a.owner} · ${a.kind} · ${a.handle}${a.inline ? ` (inline ${a.inline})` : ''}`; assets[k] = assets[k] || new Set(); assets[k].add(`${set}:${screen}`); }
}
if (!Object.keys(assets).length) out.push('Ninguno.');
for (const [k, v] of Object.entries(assets)) out.push(`- ${k}: ${[...v].join(', ')}`);

// Avisos de PHP.
out.push('');
out.push('## Avisos de PHP (por dueño)');
out.push('');
const errors = {};
const addErr = (where, e) => { for (const [k, n] of Object.entries(e || {})) { errors[k] = errors[k] || { n: 0, where: new Set() }; errors[k].n += n; errors[k].where.add(where); } };
for (const [key, runs] of Object.entries(raw.screens)) for (const r of runs) { if (r.main) addErr(key, r.main.errors); for (const o of r.others) addErr(key, o.errors); }
for (const [key, runs] of Object.entries(raw.ajax)) for (const r of runs) for (const row of r.rows) addErr(key, row.errors);
for (const [set, f] of Object.entries(raw.functional || {})) for (const row of [...(f.saveRows || []), ...(f.uploadRows || []), ...(f.rows || [])]) addErr(`funcional ${set}`, row.errors);
if (!Object.keys(errors).length) out.push('Ninguno.');
for (const [k, v] of Object.entries(errors)) { const [type, owner, msg, at] = k.split('|'); out.push(`- [${owner}] (${type}) ${msg} @ ${at} — ${v.n} vez/veces; ${[...v.where].slice(0, 6).join(', ')}${v.where.size > 6 ? '…' : ''}`); }

// Errores de JavaScript.
out.push('');
out.push('## Errores de JavaScript y respuestas ≥ 400 (Chrome)');
out.push('');
const jsErr = {};
// Lo que Chrome no pudo cargar de otros dominios (avatares, noticias: el ensayo no tiene red) no es un error del escritorio.
const foreign = (e) => e.kind === 'console' && e.url && !/admin-perf/.test(e.url);
for (const [key, runs] of Object.entries(raw.screens)) for (const r of runs) for (const e of r.js) { if (foreign(e)) continue; const k = `${e.kind}: ${e.text}`; jsErr[k] = jsErr[k] || new Set(); jsErr[k].add(key); }
for (const [set, f] of Object.entries(raw.functional || {})) for (const e of [...(f.saveJs || []), ...(f.uploadJs || []), ...(f.js || [])]) { const k = `${e.kind}: ${e.text}`; jsErr[k] = jsErr[k] || new Set(); jsErr[k].add(`funcional ${set}`); }
if (!Object.keys(jsErr).length) out.push('Ninguno.');
for (const [k, v] of Object.entries(jsErr)) out.push(`- ${k} — ${[...v].slice(0, 8).join(', ')}${v.size > 8 ? '…' : ''}`);

// Funcional.
out.push('');
out.push('## Acciones');
out.push('');
for (const [set, f] of Object.entries(raw.functional || {})) {
  if (set === 'update') { out.push(`- Actualizar un plugin desde Plugins (todos): ${JSON.stringify(f.result)} en ${f.seconds} s`); continue; }
  const save = (f.saveRows || []).find((r) => r.method === 'POST' || r.method === 'PUT');
  const up = (f.uploadRows || []).find((r) => /async-upload/.test(r.uri));
  out.push(`- ${set}: guardar ${f.save && f.save.ok ? 'bien' : 'MAL ' + JSON.stringify(f.save)}${save ? ` (${fmt(save.ms)} ms, ${save.queries} consultas)` : ''}; subir ${f.upload && f.upload.ok ? 'bien' : 'MAL ' + JSON.stringify(f.upload)}${up ? ` (${fmt(up.ms)} ms, ${up.queries} consultas, ${up.mem_peak_kb} KB)` : ''}`);
}

// Perfil: callbacks propios más caros por pantalla y consultas por dueño.
out.push('');
out.push('## Perfil (una carga con el perfil por pantalla en mine y all): callbacks propios ≥ 1 ms y consultas por dueño');
out.push('');
for (const [key, runs] of Object.entries(raw.screens)) {
  const p = runs.find((r) => r.profile && r.main);
  if (!p) continue;
  const cbs = Object.entries(p.main.cb || {}).filter(([, v]) => v[0] >= 1 || v[2] >= 2).slice(0, 12);
  const owners = Object.entries(p.main.by_owner || {}).map(([o, v]) => `${o} ${v.n}`).join(', ');
  if (!cbs.length && !owners) continue;
  out.push(`- **${key}** (${fmt(p.main.ms)} ms con perfil): consultas por dueño: ${owners || '—'}`);
  for (const [k, v] of cbs) out.push(`  - ${k}: ${v[0]} ms, ${v[1]} llamadas, ${v[2]} consultas`);
}
fs.writeFileSync(path.join(dir, 'summary.json'), JSON.stringify(summary, null, 1));
process.stdout.write(out.join('\n') + '\n');
