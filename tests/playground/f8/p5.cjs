// P5 · presupuesto del front (f8.sh, delegado en front-budget.sh por f8/p5.sh). Lee las partes de $OUT/P5-data/: las
// referencias de 4.3.0.169, la comparación de los casos existentes (mismo HTML y CSS; `empty` y `all-own` con los mismos
// bytes, peticiones, handles y consultas) y los casos de F8 en su tope de §5 (CLS 0), también con CACHE=1, y el
// marcador de K sin coste ni conversión en el front (con MIGRATION=1).
'use strict';
const fs = require('fs');
const path = require('path');

module.exports = (api) => async (s) => {
  const { ENV, check, pending, note } = api;
  const DATA = path.join(ENV.OUT, 'P5-data');
  const read = (part) => {
    try {
      const done = JSON.parse(fs.readFileSync(path.join(DATA, part, 'done.json'), 'utf8'));
      const res = done.file && fs.existsSync(done.file) ? JSON.parse(fs.readFileSync(done.file, 'utf8')) : null;
      return { done, res };
    } catch (e) { return null; }
  };
  const parts = {};
  s.facts.parts = {};
  for (const part of ['ref-A', 'ref-B', 'tree-A', 'tree-B', 'tree-f8', 'ref-cache-A', 'ref-cache-B', 'tree-cache-A', 'tree-cache-B', 'tree-cache-f8', 'tree-migration']) {
    parts[part] = read(part);
    if (parts[part]) s.facts.parts[part] = { status: parts[part].done.status, seconds: parts[part].done.seconds, measured: parts[part].res ? Object.keys(parts[part].res.results || {}).length : 0, problems: parts[part].res ? (parts[part].res.problems || []).length : null, version: parts[part].res && parts[part].res.elements ? parts[part].res.elements.version : null };
  }
  const missing = (part, id, what) => { pending(s, id, what, `falta la parte ${part} (P5_PARTS=${part} con APPEND=1)`); };

  // Referencias de 4.3.0.169: cada página carga.
  for (const part of ['ref-A', 'ref-B', 'ref-cache-A', 'ref-cache-B']) {
    const p = parts[part];
    if (!p || !p.res) { missing(part, `P5.${part}`, `referencia 4.3.0.169 (${part})`); continue; }
    const failed = Object.entries(p.res.results || {}).filter(([, r]) => !r.ok).map(([k, r]) => `${k}: ${r.error}`);
    check(s, `P5.${part}`, `referencia ${p.res.elements.version} (${part}): ${Object.keys(p.res.results).length} páginas medidas sin fallos de carga`, p.res.elements.version === '4.3.0.169' && !failed.length, failed.join(' | '));
  }
  // Casos existentes: iguales a 4.3.0.169.
  for (const part of ['tree-A', 'tree-B', 'tree-cache-A', 'tree-cache-B']) {
    const p = parts[part];
    if (!p || !p.res) { missing(part, `P5.${part}`, `casos existentes iguales a 4.3.0.169 (${part})`); continue; }
    const cmp = p.res.compare;
    const ok = !!cmp && cmp.against && cmp.against.version === '4.3.0.169' && !cmp.different.length && !cmp.missing.length && cmp.same.length > 0;
    check(s, `P5.${part}`, `${part}: ${cmp ? cmp.same.length : 0} casos existentes con el mismo HTML y CSS que 4.3.0.169${cmp && Object.keys(cmp.strict || {}).length ? ` (estrictos: ${Object.keys(cmp.strict).join(', ')})` : ''}`, ok, cmp ? JSON.stringify({ different: cmp.different.slice(0, 4), missing: cmp.missing }) : 'sin bloque compare');
    // La comparación con la línea base corre después de escribir el JSON: sus «PEOR …» sólo están en el registro.
    let logged = [];
    try { logged = fs.readFileSync(path.join(DATA, part, 'front-budget.log'), 'utf8').split('\n').filter((l) => /^FALLA\s+PEOR /.test(l)).map((l) => l.replace(/^FALLA\s+/, '')); } catch (e) { /* sin registro */ }
    const own = (p.res.problems || []).filter((x) => !/^DISTINTO /.test(x)).concat(logged);
    check(s, `P5.${part}.e`, `${part}: sin problemas de expectativas ni regresiones frente a la línea base`, !own.length, own.slice(0, 5).join(' | '));
  }
  // Casos de F8 en su tope (modo normal y caché).
  const f8 = ['f8-tooltip', 'f8-badge', 'f8-nested-counter', 'f8-marker'];
  for (const [part, label] of [['tree-f8', 'normal'], ['tree-cache-f8', 'cache']]) {
    const p = parts[part];
    if (!p || !p.res) { missing(part, `P5.f8.${label}`, `casos de F8 en su tope (${label})`); continue; }
    for (const slug of f8) {
      const key = Object.keys(p.res.results || {}).find((k) => k === slug || k.startsWith(slug + '@'));
      const r = key ? p.res.results[key] : null;
      const probs = (p.res.problems || []).filter((x) => x.startsWith(slug + ' ') || x.startsWith(slug + '@') || x.startsWith(slug + ' (') || x.includes(` ${slug} `));
      const facts = r ? { delta: r.delta, cls: r.cls, assets: Object.fromEntries(Object.entries(r).filter(([k]) => k.startsWith('asset:'))), counts: Object.fromEntries(Object.entries(r).filter(([k]) => k.startsWith('count:'))), after: r.after ? { cls: r.after.cls, counts: Object.fromEntries(Object.entries(r.after).filter(([k]) => k.startsWith('count:'))) } : null, queries: r.queries, handles: (r.handles || []).filter((h) => /digitalisimo-(tooltip|badge|nested)/.test(h)) } : null;
      s.facts[`${slug}@${label}`] = facts;
      check(s, `P5.${slug}.${label}`, `${slug} (${label}): mide, cumple su «expect» (topes de §5, CLS 0, sin errores)`, !!r && r.ok && !probs.length, JSON.stringify({ problems: probs.slice(0, 3), facts }));
    }
  }
  const base = parts['tree-f8'] && parts['tree-f8'].res;
  if (base) {
    const r = base.results || {};
    const empty = r.empty;
    const marker = r['f8-marker'];
    if (empty && marker && empty.ok && marker.ok) {
      s.facts.marker_cost = { requests: marker.delta.requests, bytes: marker.delta.bytes, js: marker.delta.js, css: marker.delta.css, nodes: marker.delta.nodes, queries: [empty.queries, marker.queries] };
      note(s, `coste del marcador frente a «empty»: ${JSON.stringify(s.facts.marker_cost)}`);
    }
  }
  // Marcador con Migración activo: no se convierte en el front.
  const mig = parts['tree-migration'];
  if (!mig || !mig.res) missing('tree-migration', 'P5.marker.migration', 'f8-marker con MIGRATION=1');
  else {
    const r = (mig.res.results || {})['f8-marker'];
    const probs = (mig.res.problems || []).filter((x) => /f8-marker/.test(x));
    check(s, 'P5.marker.migration', 'f8-marker con Migración activo: nada se pinta ni se convierte en el front, sin coste', !!r && r.ok && !probs.length, JSON.stringify({ problems: probs, delta: r && r.delta }));
  }
  for (const part of Object.keys(parts)) if (parts[part] && parts[part].done.status !== 0) note(s, `${part} salió con ${parts[part].done.status} (ver ${path.join(DATA, part, 'front-budget.log')})`);
};
