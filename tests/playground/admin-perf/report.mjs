// Informe de la auditoría del escritorio: une las medidas del cliente (measures.json) con la sonda del servidor
// (requests.jsonl) y deja report.json y report.md en $OUT. Medianas de las cargas medidas (sin la de calentamiento).
import fs from 'node:fs';
import path from 'node:path';

const OUT = process.env.OUT || '/tmp/digi-admin-perf';
const read = (f, d = null) => { try { return JSON.parse(fs.readFileSync(path.join(OUT, f), 'utf8')); } catch (e) { return d; } };
const lines = fs.existsSync(path.join(OUT, 'requests.jsonl')) ? fs.readFileSync(path.join(OUT, 'requests.jsonl'), 'utf8').split('\n').filter(Boolean).map((l) => { try { return JSON.parse(l); } catch (e) { return null; } }).filter(Boolean) : [];
const byId = new Map(lines.filter((l) => l.id !== 'chrome').map((l) => [l.id, l]));
const { measures = [], notes = [], error } = read('measures.json', {});
const median = (a) => { const v = a.filter((x) => typeof x === 'number' && !Number.isNaN(x)).sort((x, y) => x - y); if (!v.length) return null; const m = Math.floor(v.length / 2); return v.length % 2 ? v[m] : (v[m - 1] + v[m]) / 2; };
const r1 = (x) => (x === null ? null : Math.round(x * 10) / 10);
const OURS = new Set(['nucleo', 'pro', 'propio', 'migracion']);

// ---------------------------------------------------------------- Medidas por etiqueta y conjunto
const groups = new Map();
for (const m of measures) {
  if (!m.kind || m.warm) continue;
  const k = `${m.label}\u0000${m.set}`;
  if (!groups.has(k)) groups.set(k, []);
  groups.get(k).push({ m, s: byId.get(m.id) || null });
}
const stats = {};
for (const [k, list] of groups) {
  const [label, set] = k.split('\u0000');
  const s = list.map((x) => x.s).filter(Boolean);
  const qOrigin = {};
  for (const o of ['wp', 'nucleo', 'pro', 'propio', 'migracion', 'sonda']) {
    qOrigin[o] = { n: median(s.map((x) => (x.q_origin[o] || {}).n || 0)), ms: r1(median(s.map((x) => (x.q_origin[o] || {}).ms || 0))) };
  }
  const errors = {}; const notices = {}; const http = {}; const assets = {}; const sql = {};
  for (const x of s) {
    for (const [e, v] of Object.entries(x.errors || {})) errors[e] = v.origin;
    for (const [e, v] of Object.entries(x.notices || {})) notices[e] = v.origin;
    for (const h of x.http || []) { const key = `${h.origin} → ${h.url} (${h.result}${h.blocking ? '' : ', no bloqueante'})`; http[key] = (http[key] || 0) + 1; }
    for (const a of x.assets || []) assets[`${a.kind} ${a.handle} (${a.origin})`] = { bytes: a.bytes, inline: a.inline };
    for (const [q, v] of Object.entries(x.sql || {})) { sql[q] = sql[q] || { n: [], ms: [], from: v.from }; sql[q].n.push(v.n); sql[q].ms.push(v.ms); }
  }
  for (const q of Object.keys(sql)) sql[q] = { n: median(sql[q].n), ms: r1(median(sql[q].ms)), from: sql[q].from };
  const marks = {};
  for (const x of s) for (const [h, v] of Object.entries(x.marks || {})) if (v && typeof v === 'object' && 'a' in v && 'b' in v) (marks[h] = marks[h] || []).push(v.b - v.a);
  const plugins = {};
  for (const x of s) for (const p of x.plugins || []) (plugins[p.file] = plugins[p.file] || []).push(p.ms);
  const files = {};
  for (const x of s) for (const [o, n] of Object.entries(x.files_by || {})) (files[o] = files[o] || []).push(n);
  stats[k] = {
    label, set, n: list.length, kind: list[0].m.kind, url: list[0].m.url,
    status: [...new Set(list.map((x) => x.m.status))],
    client_ms: r1(median(list.map((x) => x.m.client_ms))),
    wp_ms: r1(median(s.map((x) => x.wp_ms))),
    wp_ms_min: r1(Math.min(...s.map((x) => x.wp_ms))), wp_ms_max: r1(Math.max(...s.map((x) => x.wp_ms))),
    queries: median(s.map((x) => x.queries)),
    q_origin: qOrigin,
    mem_mb: r1(median(s.map((x) => x.mem_peak / 1048576))),
    bytes: median(list.map((x) => x.m.bytes)),
    files: median(s.map((x) => x.files)),
    files_by: Object.fromEntries(Object.entries(files).map(([o, v]) => [o, median(v)])),
    marks: Object.fromEntries(Object.entries(marks).map(([h, v]) => [h, r1(median(v))])),
    plugins: Object.fromEntries(Object.entries(plugins).map(([f, v]) => [f, r1(median(v))])),
    errors, notices, http, assets, sql,
    http_per_load: r1(s.length ? s.reduce((a, x) => a + (x.http || []).length, 0) / s.length : 0),
    fatal: [...new Set(s.map((x) => x.fatal).filter(Boolean))],
    opcache: s.some((x) => x.opcache), php: s[0] ? s[0].php : null,
    profile: s.some((x) => x.prof) ? mergeProfiles(s.map((x) => x.prof).filter(Boolean)) : null,
  };
}
function mergeProfiles(list) {
  const by = {}; const top = {};
  for (const p of list) {
    for (const [o, v] of Object.entries(p.by_origin || {})) (by[o] = by[o] || []).push(v.ms);
    for (const [c, v] of Object.entries(p.top || {})) (top[c] = top[c] || []).push(v.ms);
  }
  const t = Object.entries(top).map(([c, v]) => [c, r1(median(v))]).filter(([c]) => /^(nucleo|pro|propio|migracion) /.test(c)).sort((a, b) => b[1] - a[1]).slice(0, 25);
  return { by_origin: Object.fromEntries(Object.entries(by).map(([o, v]) => [o, r1(median(v))])), top: Object.fromEntries(t) };
}

// ---------------------------------------------------------------- Chrome (pantallas, editor de bloques y constructor)
const chrome = read('chrome.json', { screens: [], editor: [], builder: [] });
const chromeServer = {};
for (const l of lines.filter((x) => x.id === 'chrome')) {
  const k = `${l.label}\u0000${l.set}`;
  const c = chromeServer[k] = chromeServer[k] || { requests: 0, wp_ms: 0, queries: 0, mem_mb: 0, errors: {}, notices: {}, http: 0, uris: [] };
  c.requests++; c.wp_ms += l.wp_ms || 0; c.queries += l.queries || 0; c.mem_mb = Math.max(c.mem_mb, (l.mem_peak || 0) / 1048576); c.http += (l.http || []).length;
  for (const [e, v] of Object.entries(l.errors || {})) c.errors[e] = v.origin;
  for (const [e, v] of Object.entries(l.notices || {})) c.notices[e] = v.origin;
  c.uris.push(`${l.method} ${l.uri.slice(0, 120)} ${Math.round(l.wp_ms)} ms ${l.queries} q ${Math.round(l.mem_peak / 1048576)} MB`);
}
const builder = {};
for (const b of chrome.builder || []) {
  const o = chromeServer[`chrome constructor-abrir ${b.rep}\u0000${b.set}`] || {};
  const s = chromeServer[`chrome constructor-guardar ${b.rep}\u0000${b.set}`] || {};
  (builder[b.set] = builder[b.set] || []).push({ rep: b.rep, ready: b.ready, openMs: b.openMs, saveOk: b.save && b.save.ok, saveMs: b.save && b.save.ms, open_server_ms: r1(o.wp_ms), open_requests: o.requests, open_queries: o.queries, open_mem_mb: r1(o.mem_mb), save_server_ms: r1(s.wp_ms), save_queries: s.queries, save_mem_mb: r1(s.mem_mb), console: b.open.console.concat(b.saveState.console), pageerrors: b.open.pageerrors.concat(b.saveState.pageerrors), failed: b.open.failed.concat(b.saveState.failed), error: b.error });
}

// ---------------------------------------------------------------- Opciones con carga automática y WP-Cron
const auto0 = read('autoload-0.json', { options: {} }).options;
const auto1 = read('autoload-1.json', { options: {} }).options;
const autoSets = {};
for (const f of fs.readdirSync(OUT).filter((f) => /^autoload-[a-z-]+\.json$/.test(f))) autoSets[f.slice(9, -5)] = (read(f, { options: {} }) || { options: {} }).options;
const lastAuto = autoSets.el || autoSets.elmig || auto1;
const autoloadAdded = Object.entries(lastAuto).filter(([k]) => !(k in auto0) && !/^_(site_)?transient_(timeout_)?(wp_|doing_cron|health|dirsize|feed|update_|theme_roots|community)/.test(k)).sort((a, b) => b[1] - a[1]);
const cron0 = (read('cron-0.json', { events: [] }).events || []).map((e) => e.hook);
const cron1 = read('cron-1.json', { events: [] }).events || [];
const cronSet = (read('cron-el.json', null) || read('cron-elmig.json', null) || { events: [] }).events || [];
const cronAdded = [...cron1, ...cronSet].filter((e) => !cron0.includes(e.hook)).reduce((acc, e) => { acc[e.hook] = { schedule: e.schedule, interval: e.interval }; return acc; }, {});

// ---------------------------------------------------------------- Informe
const sets = [...new Set(measures.filter((m) => m.kind).map((m) => m.set))];
const labels = [...new Set(measures.filter((m) => m.kind && m.kind !== 'profile' && m.kind !== 'github').map((m) => m.label))];
const get = (label, set) => stats[`${label}\u0000${set}`];
const fmt = (v, suf = '') => (v === null || v === undefined ? '—' : `${v}${suf}`);
let md = `# Auditoría del escritorio · ${new Date().toISOString().slice(0, 16).replace('T', ' ')} UTC\n\n`;
const any = Object.values(stats)[0];
md += `PHP ${any ? any.php : '?'} · opcache ${any && any.opcache ? 'sí' : 'no'} · conjuntos: ${sets.join(', ')} · ${measures.filter((m) => m.kind && !m.warm).length} cargas medidas\n\n`;
if (error) md += `**Error del conductor:** ${error}\n\n`;
if (notes.length) md += `Notas: ${notes.join(' · ')}\n\n`;
md += '## Pantallas (mediana del servidor: ms · consultas · MB pico)\n\n';
md += `| Pantalla | ${sets.join(' | ')} | Δ ms el | Δ consultas el |\n| --- | ${sets.map(() => '---').join(' | ')} | --- | --- |\n`;
for (const label of labels) {
  const row = sets.map((s) => { const x = get(label, s); return x ? `${fmt(x.wp_ms)} · ${fmt(x.queries)} · ${fmt(x.mem_mb)}${x.status.some((c) => c >= 400) ? ' ⚠' + x.status.join('/') : ''}` : '—'; });
  const n = get(label, 'none'); const e = get(label, 'el');
  md += `| ${label} | ${row.join(' | ')} | ${n && e ? r1(e.wp_ms - n.wp_ms) : '—'} | ${n && e ? e.queries - n.queries : '—'} |\n`;
}
md += '\n## Consultas de los plugins por pantalla (conjunto el / elmig: origen n · ms)\n\n';
for (const label of labels) {
  for (const s of sets.filter((x) => x !== 'none')) {
    const x = get(label, s); if (!x) continue;
    const parts = Object.entries(x.q_origin).filter(([o, v]) => OURS.has(o) && v.n).map(([o, v]) => `${o} ${v.n} · ${v.ms} ms`);
    if (parts.length) md += `- ${label} (${s}): ${parts.join(', ')}\n`;
  }
}
md += '\n## Inclusión de plugins (ms, mediana en Escritorio)\n\n';
for (const s of sets) { const x = get('escritorio', s) || get('red-escritorio', s); if (x) md += `- ${s}: ${Object.entries(x.plugins).map(([f, v]) => `${f} ${v} ms`).join(', ') || '—'} · archivos PHP ${x.files} (${Object.entries(x.files_by).map(([o, n]) => `${o} ${n}`).join(', ')})\n`; }
md += '\n## Fases (ms, mediana en Escritorio)\n\n';
for (const s of sets) { const x = get('escritorio', s) || get('red-escritorio', s); if (x) md += `- ${s}: ${Object.entries(x.marks).filter(([, v]) => v >= 1).map(([h, v]) => `${h} ${v}`).join(', ')}\n`; }
md += '\n## Scripts y estilos de los plugins en pantallas que no son suyas\n\n';
for (const label of labels.filter((l) => !/^propia /.test(l))) {
  for (const s of sets.filter((x) => x !== 'none')) {
    const x = get(label, s); if (!x) continue;
    const mine = Object.entries(x.assets).filter(([k]) => !/\(inline\)$/.test(k));
    if (mine.length) md += `- ${label} (${s}): ${mine.map(([k, v]) => `${k} ${v.bytes ? Math.round(v.bytes / 1024) + ' KB' : ''}${v.inline ? ' +' + v.inline + ' B en línea' : ''}`).join(', ')}\n`;
  }
}
md += '\n## Bytes del HTML (mediana) sin y con plugins\n\n';
for (const label of labels) { const n = get(label, 'none'); const e = get(label, 'el'); if (n && e && n.bytes && e.bytes) md += `- ${label}: ${n.bytes} → ${e.bytes} (${e.bytes - n.bytes >= 0 ? '+' : ''}${e.bytes - n.bytes})\n`; }
md += '\n## Peticiones HTTP salientes (por carga)\n\n';
for (const [k, x] of Object.entries(stats)) { const ours = Object.entries(x.http); if (ours.length) md += `- ${x.label} (${x.set}): ${ours.map(([h, n]) => `${h} ×${n}/${x.n}`).join('; ')}\n`; }
md += '\n### GitHub caído / al día (cargas seguidas tras vaciar las cachés)\n\n';
const gh = lines.filter((l) => /^github-/.test(l.label || ''));
for (const l of gh) md += `- ${l.label} (${l.set}): ${Math.round(l.wp_ms)} ms, ${l.http.length} peticiones${l.http.length ? ': ' + l.http.map((h) => `${h.origin.split(' ')[0]} ${h.url} [${h.result}${h.timeout ? ', espera ' + h.timeout + ' s' : ''}]`).join(', ') : ''}\n`;
md += '\n## Avisos de PHP (todas las peticiones medidas)\n\n';
const allErr = {};
for (const l of lines) for (const [e, v] of Object.entries(l.errors || {})) { const k = `${v.origin} · ${e}`; allErr[k] = allErr[k] || new Set(); allErr[k].add(l.set + ' ' + (l.label || '').split(' ')[0]); }
for (const [e, where] of Object.entries(allErr)) md += `- ${e.slice(0, 400)} — en: ${[...where].slice(0, 6).join(', ')}\n`;
const allNot = {};
for (const l of lines) for (const [e, v] of Object.entries(l.notices || {})) { const k = `${v.origin} · ${e}`; allNot[k] = allNot[k] || new Set(); allNot[k].add(l.set + ' ' + (l.label || '').split(' ')[0]); }
for (const [e, where] of Object.entries(allNot)) md += `- ${e.slice(0, 400)} — en: ${[...where].slice(0, 6).join(', ')}\n`;
if (!Object.keys(allErr).length && !Object.keys(allNot).length) md += 'Ninguno.\n';
md += '\n## Opciones con carga automática añadidas (bytes)\n\n';
md += autoloadAdded.length ? autoloadAdded.map(([k, v]) => `- ${k}: ${v}`).join('\n') + `\n\nTotal: ${autoloadAdded.reduce((a, [, v]) => a + v, 0)} B en ${autoloadAdded.length} opciones\n` : 'Ninguna.\n';
md += '\n## Eventos de WP-Cron añadidos\n\n';
md += Object.keys(cronAdded).length ? Object.entries(cronAdded).map(([h, v]) => `- ${h}: ${v.schedule}${v.interval ? ' (' + v.interval + ' s)' : ''}`).join('\n') + '\n' : 'Ninguno.\n';
md += '\n## Perfil por origen (ms de tiempo propio en ganchos, mediana)\n\n';
for (const [k, x] of Object.entries(stats).filter(([, x]) => x.profile)) {
  md += `- ${x.label} (${x.set}): ${Object.entries(x.profile.by_origin).map(([o, v]) => `${o} ${v}`).join(', ')}\n`;
  for (const [c, v] of Object.entries(x.profile.top).slice(0, 8)) md += `  - ${v} ms ${c}\n`;
}
md += '\n## Consultas SQL de los plugins (Escritorio, Entradas, Medios)\n\n';
for (const label of ['escritorio', 'entradas', 'medios', 'editar-entrada', 'heartbeat']) {
  for (const s of sets.filter((x) => x !== 'none')) { const x = get(label, s); if (!x) continue; for (const [q, v] of Object.entries(x.sql).slice(0, 25)) md += `- ${label} (${s}) ×${v.n} ${v.ms} ms: \`${q.slice(0, 200)}\` ← ${v.from}\n`; }
}
md += '\n## Chrome: errores de consola y peticiones fallidas que no aparecen sin los plugins\n\n';
const base = {};
for (const sc of (chrome.screens || []).filter((x) => x.set === 'none')) base[sc.label] = new Set([...sc.console, ...sc.pageerrors, ...sc.failed]);
for (const sc of (chrome.screens || []).filter((x) => x.set !== 'none')) {
  const extra = [...sc.console, ...sc.pageerrors, ...sc.failed].filter((e) => !(base[sc.label] || new Set()).has(e));
  if (extra.length || sc.status >= 400) md += `- ${sc.label} (${sc.set}) HTTP ${sc.status}: ${extra.join(' | ').slice(0, 600)}\n`;
}
md += '\n### Navegador por pantalla (segunda visita, caché caliente: script ms · tareas ms · JS KB · CSS KB · DOMContentLoaded ms · montón MB)\n\n';
const chromeSets = [...new Set((chrome.screens || []).map((x) => x.set))];
md += `| Pantalla | ${chromeSets.join(' | ')} |\n| --- | ${chromeSets.map(() => '---').join(' | ')} |\n`;
for (const label of [...new Set((chrome.screens || []).map((x) => x.label))]) {
  md += `| ${label} | ${chromeSets.map((s) => { const x = (chrome.screens || []).find((y) => y.label === label && y.set === s); return x && x.perf ? `${x.perf.script_ms} · ${x.perf.task_ms} · ${x.perf.js_kb} · ${x.perf.css_kb} · ${x.perf.dcl} · ${x.perf.heap_mb}` : '—'; }).join(' | ')} |\n`;
}
md += '\n### Editor de bloques\n\n';
for (const e of chrome.editor || []) md += `- ${e.set}: listo ${e.ready}, guardado ${JSON.stringify(e.save)}, ${e.loadRequests.length} peticiones REST/AJAX al abrir y ${e.saveRequests.length} al guardar; errores: ${[...e.console, ...e.pageerrors, ...e.failed, ...e.afterSave.console, ...e.afterSave.pageerrors, ...e.afterSave.failed].join(' | ').slice(0, 500) || 'ninguno'}\n`;
md += '\n### Editor del constructor (abrir y guardar)\n\n';
for (const [s, list] of Object.entries(builder)) {
  md += `- ${s}: abrir ${median(list.map((b) => b.openMs))} ms en Chrome (servidor ${median(list.map((b) => b.open_server_ms))} ms en ${median(list.map((b) => b.open_requests))} peticiones, ${median(list.map((b) => b.open_queries))} consultas, ${median(list.map((b) => b.open_mem_mb))} MB pico); guardar ${median(list.map((b) => b.saveMs))} ms (servidor ${median(list.map((b) => b.save_server_ms))} ms, ${median(list.map((b) => b.save_queries))} consultas, ${median(list.map((b) => b.save_mem_mb))} MB); listo ${list.filter((b) => b.ready).length}/${list.length}, guardado ${list.filter((b) => b.saveOk).length}/${list.length}\n`;
  const errs = [...new Set(list.flatMap((b) => [...b.console, ...b.pageerrors, ...b.failed]))];
  if (errs.length) md += `  - errores: ${errs.join(' | ').slice(0, 800)}\n`;
}
const fatals = Object.values(stats).flatMap((x) => x.fatal);
if (fatals.length) md += `\n## Errores fatales\n\n${[...new Set(fatals)].map((f) => '- ' + f).join('\n')}\n`;
fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify({ stats, builder, chromeServer, autoloadAdded, cronAdded, notes, error }, null, 1));
fs.writeFileSync(path.join(OUT, 'report.md'), md);
console.log(md.slice(0, 6000));
