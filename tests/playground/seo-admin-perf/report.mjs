// Resumen en Markdown de seo-admin-perf.sh: node report.mjs <results.json> [<results.json de comparación>]
// Con un segundo archivo (otra corrida, p. ej. «antes»), añade la columna de lo que cambió con todo activo.
import { readFileSync } from 'node:fs';

const r = JSON.parse(readFileSync(process.argv[2], 'utf8'));
const prev = process.argv[3] ? JSON.parse(readFileSync(process.argv[3], 'utf8')) : null;
const f = (x, d = 0) => (x === null || x === undefined ? '—' : Number(x).toFixed(d));
const delta = (a, b, d = 0) => (a === null || a === undefined || b === null || b === undefined ? '—' : `${a - b >= 0 ? '+' : ''}${(a - b).toFixed(d)}`);
const out = [];
const s = r.setup;
out.push(`# seo-admin-perf · ${s.multisite ? 'red' : 'sitio individual'} · PHP ${s.php} · WordPress ${s.wp} · opciones ${s.options}${s.woocommerce ? ' · WooCommerce' : ''}`);
out.push('', `Medianas de ${r.loads} cargas por pantalla y conjunto (tiempo del servidor desde timer_start(), consultas, memoria pico). Δ = conjunto − ninguno.`, '');
const sets = r.sets.filter((x) => x !== 'none');
out.push(`| Pantalla | sin plugins ms / q / KB | con los tres ms / q / KB | Δ todos ms / q / KB | ${sets.filter((x) => x !== 'all').map((x) => `Δ ${x} ms / q`).join(' | ')} | HTTP | avisos PHP | recursos |${prev ? ' antes→ahora (todos) ms / q |' : ''}`);
out.push(`|---|---|---|---|${sets.filter((x) => x !== 'all').map(() => '---|').join('')}---|---|---|${prev ? '---|' : ''}`);
for (const [key, row] of Object.entries(r.screens)) {
  const n = row.none || {}, a = row.all || {};
  const per = sets.filter((x) => x !== 'all').map((x) => `${delta(row[x]?.ms, n.ms)} / ${delta(row[x]?.queries, n.queries)}`).join(' | ');
  const http = Object.entries(row).filter(([k]) => k !== 'detail' && k !== 'none').reduce((t, [, v]) => t + (v.http?.length || 0), 0);
  const errs = new Set((a.errors || []).filter((e) => e.plugin !== 'core').map((e) => `${e.plugin}:${e.origin}`)).size;
  const assets = (a.assets || []).filter((x) => x.plugin !== 'inline').map((x) => x.handle);
  const p = prev?.screens?.[key]?.all;
  out.push(`| ${key} | ${f(n.ms)} / ${f(n.queries)} / ${f(n.peak_kb)} | ${f(a.ms)} / ${f(a.queries)} / ${f(a.peak_kb)} | ${delta(a.ms, n.ms)} / ${delta(a.queries, n.queries)} / ${delta(a.peak_kb, n.peak_kb)} | ${per} | ${http} | ${errs} | ${assets.length ? assets.join(', ') : '0'} |${prev ? ` ${f(p?.ms)}→${f(a.ms)} / ${f(p?.queries)}→${f(a.queries)} |` : ''}`);
}
const esc = r.screens.escritorio;
if (esc) {
  const loads = Object.entries(esc.all?.load || {}).map(([k, v]) => `${k} ${f(v, 1)} ms`).join(', ');
  out.push('', `Escritorio: incluir el código de cada plugin (de un plugin_loaded al siguiente, todos activos): ${loads || '—'}. OPcache: ${esc.all?.opcache ? 'sí' : 'no'}.`);
  const m = (set, a, b) => (esc[set]?.marks?.[b] ?? null) - (esc[set]?.marks?.[a] ?? null);
  out.push(`Fases del Escritorio (ms, sin → con): hasta plugins_loaded ${f(esc.none?.marks?.plugins_loaded)} → ${f(esc.all?.marks?.plugins_loaded)}; plugins_loaded→init ${f(m('none', 'plugins_loaded', 'init'))} → ${f(m('all', 'plugins_loaded', 'init'))}; init→admin_init ${f(m('none', 'init', 'admin_init'))} → ${f(m('all', 'init', 'admin_init'))}; admin_init→admin_footer ${f(m('none', 'admin_init', 'admin_footer'))} → ${f(m('all', 'admin_init', 'admin_footer'))}.`);
}
out.push('', '## Consultas atribuidas a los plugins (petición con SAVEQUERIES, todos activos)', '');
for (const [key, row] of Object.entries(r.screens)) {
  const d = row.detail?.all; if (!d) continue;
  const parts = Object.entries(d).filter(([k]) => k !== 'core').map(([k, v]) => `${k} ${v.length}`);
  if (parts.length) out.push(`- ${key}: ${parts.join(', ')}`);
}
out.push('', '## Pantallas propias (todos activos, mediana de 3)', '', '| Pantalla | ms | consultas | KB | HTTP | avisos |', '|---|---|---|---|---|---|');
for (const [path, v] of Object.entries(r.own || {})) out.push(`| ${path} | ${f(v.ms)} | ${f(v.queries)} | ${f(v.peak_kb)} | ${v.http.length} | ${v.errors.length} |`);
out.push('', '## Peticiones HTTP salientes', '');
const httpRows = [];
for (const [key, row] of Object.entries(r.screens)) for (const [set, v] of Object.entries(row)) if (set !== 'detail') for (const h of v.http || []) httpRows.push(`${key} [${set}] ${h.method} ${h.url} timeout ${h.timeout}s ${h.blocking ? 'bloqueante' : 'no bloqueante'} · ${h.origin} (${h.hook})`);
for (const [k, v] of Object.entries(r.expired || {})) for (const h of v.http) httpRows.push(`cachés vencidas · ${k}: ${h.method} ${h.url} timeout ${h.timeout}s · ${h.origin}`);
for (const [k, v] of Object.entries(r.own || {})) for (const h of v.http) httpRows.push(`propia ${k}: ${h.method} ${h.url} timeout ${h.timeout}s · ${h.origin}`);
const counts = {}; for (const x of httpRows) counts[x] = (counts[x] || 0) + 1;
for (const [x, c] of Object.entries(counts)) out.push(`- ${c}× ${x}`);
if (r.cold) out.push('', `Primera carga del Escritorio tras activar: ${f(r.cold.ms)} ms, ${r.cold.queries} consultas, ${r.cold.http?.length || 0} HTTP.`);
out.push('', '## Avisos de PHP de los plugins', '');
const errs = {};
for (const [key, row] of Object.entries(r.screens)) for (const [set, v] of Object.entries(row)) if (set !== 'detail') for (const e of v.errors || []) if (e.plugin !== 'core') errs[`${e.type} ${e.origin}: ${e.message}`] = (errs[`${e.type} ${e.origin}: ${e.message}`] || 0) + 1;
for (const [k, v] of Object.entries(r.own || {})) for (const e of v.errors) if (e.plugin !== 'core') errs[`${e.type} ${e.origin}: ${e.message}`] = (errs[`${e.type} ${e.origin}: ${e.message}`] || 0) + 1;
out.push(...(Object.keys(errs).length ? Object.entries(errs).map(([k, c]) => `- ${c}× ${k}`) : ['- ninguno']));
out.push('', '## Autoload que añaden los plugins', '');
const added = Object.entries(r.autoload?.after || {}).filter(([k]) => !(k in (r.autoload?.before || {})) && !/^_?(site_)?transient/.test(k));
let total = 0; for (const [, b] of added) total += b;
out.push(`${added.length} opciones nuevas con autoload, ${(total / 1024).toFixed(1)} KB:`, '');
for (const [k, b] of added.sort((x, y) => y[1] - x[1])) out.push(`- ${k}: ${b} B`);
out.push('', '## WP-Cron', '');
const beforeHooks = new Set((r.cron?.before || []).map((e) => e.hook));
for (const e of (r.cron?.after || []).filter((x) => !beforeHooks.has(x.hook))) out.push(`- ${e.hook}: ${e.schedule || 'único'}${e.interval ? ` cada ${e.interval} s` : ''}, en ${e.in_s} s`);
for (const [hook, v] of Object.entries(r.cron || {})) if (hook !== 'before' && hook !== 'after') out.push(`- ejecutado ${hook}: ${f(v.ms)} ms, ${v.queries} consultas, ${v.http.length} HTTP${v.http.length ? ` (${[...new Set(v.http.map((h) => h.url))].join(', ')})` : ''}`);
if (r.plugin_postmeta) out.push('', `Metadatos de los plugins en postmeta: ${r.plugin_postmeta.map((x) => `${x.meta_key} ${x.n}`).join(', ')} (de ${r.postmeta_rows} filas).`);
if (r.chrome) {
  out.push('', '## Chrome', '');
  // Sólo lo que aparece con los plugins y no sin ellos; ERR_NAME_NOT_RESOLVED es la red cortada del arnés (gravatar, emoji).
  const norm = (e) => `[${e.kind}] ${e.text}`;
  let any = false;
  for (const [k, ev] of Object.entries(r.chrome.console)) {
    if (!k.startsWith('all ')) continue;
    const base = new Set((r.chrome.console[`none ${k.slice(4)}`] || []).map(norm));
    const extra = [...new Set(ev.map(norm).filter((x) => !base.has(x) && !/ERR_NAME_NOT_RESOLVED/.test(x)))];
    if (extra.length) { any = true; out.push(`- ${k.slice(4)}: ${extra.join(' · ')}`); }
  }
  if (!any) out.push(`- Ningún error de consola, de JavaScript ni respuesta ≥ 400 que no aparezca también sin los plugins (${Object.keys(r.chrome.console).length} cargas de página).`);
  for (const [set, runs] of Object.entries(r.chrome.save)) out.push(`- guardar desde el editor [${set}]: ${runs.map((x) => `${x.wall_ms} ms (${x.requests.map((q) => `${q.method} ${q.uri.replace(/\?.*$/, '')} ${f(q.ms)} ms/${q.queries} q`).join(' + ')})`).join(' · ')}`);
}
console.log(out.join('\n'));
