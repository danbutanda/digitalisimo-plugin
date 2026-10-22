// Comparación de dos corridas de front-budget.sh (F7a, criterios 1 y 2 de la especificación): cada caso que ya
// existía (sin `since`) da el mismo HTML y el mismo CSS normalizados y, en los casos estrictos (por defecto empty y
// all-own), además los mismos bytes, peticiones, handles y $wpdb->num_queries.
//
// La usan run.cjs (COMPARE_FILE, al final de una corrida) y compare.cjs (dos corridas ya hechas). Las huellas se
// recalculan siempre desde las páginas guardadas en <corrida>/pages con la normalización de aquí, así que una
// referencia guardada con una normalización anterior se compara igual.
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const sha = (s) => crypto.createHash('sha256').update(s).digest('hex');

// Origen del Playground (`http://127.0.0.1:<puerto>`, también escapado en JSON): la referencia y la corrida actual
// pueden levantarse en puertos distintos del rango de front-budget.
const normalizeOrigin = (s) => s.replace(/https?:(\\?\/\\?\/)(?:127\.0\.0\.1|localhost)(?::\d+)?(?=[\\/"'\s)?#]|$)/g, 'http:$1ORIGEN');

// Lo que el widget de horario (business-hours) calcula con la hora del servidor: el reloj, la fecha, `data-today` y
// el estado abierto/cerrado con su alternativa. Los dos textos del estado se conservan, ordenados, para que un
// cambio de esos textos siga contando como diferencia.
function normalizeHours(s) {
  return s
    .replace(/(<time class="digi-hours__clock">)[^<]*(<\/time>)/g, '$1«hora»$2')
    .replace(/(<span class="digi-hours__date">)[^<]*(<\/span>)/g, '$1«fecha»$2')
    .replace(/(\bdata-today=")[^"]*(")/g, '$1«fecha»$2')
    .replace(/<div class="digi-hours__status[^"]*" role="status" data-state="[^"]*">([\s\S]*?)<\/div><template class="digi-hours__alt" data-state="[^"]*">([\s\S]*?)<\/template>/g,
      (m, shown, hidden) => `<div class="digi-hours__status" role="status" data-state="«estado»">${[shown, hidden].sort().join('«|»')}</div>`);
}

// Normalización del HTML: fuera el comentario de la sonda, el origen, el valor de `ver=` (versión del plugin o fecha
// de la hoja del documento), los nonces y tokens, que cambian con las sales de cada Playground, y lo que el horario
// calcula con la hora; `volatile` son expresiones extra de lo que depende de la hora (cada caso puede declararlas en
// cases.json).
function normalizeHtml(html, volatile = []) {
  let out = normalizeHours(normalizeOrigin(String(html || '')))
    .replace(/\n?<!-- digitalisimo-pro-gate \{.*?\} -->\n?/g, '')
    .replace(/([?&](?:#038;|amp;)?ver=)[^&"'\s)]+/g, '$1V')
    .replace(/(nonce[A-Za-z_]*\\?["']?\s*[:=]\s*\\?["']?)[a-f0-9]{10}\b/gi, '$1N')
    .replace(/(_wpnonce=)[a-f0-9]{10}\b/g, '$1N')
    // Objetos `"nonces":{…}` (elementorFrontendConfig y otros): cada valor es un nonce, sea cual sea su clave.
    .replace(/(\\?"nonces\\?"\s*:\s*\{)([^{}]*)\}/g, (m, open, body) => `${open}${body.replace(/(:\s*\\?")[a-f0-9]{10}(\\?")/g, '$1N$2')}}`)
    // Campos ocultos de formulario con un nonce o un token (`name="_wpnonce" value="…"`, en cualquier orden).
    .replace(/(<input\b[^>]*\bname="[^"]*(?:nonce|token)[^"]*"[^>]*\bvalue=")[A-Za-z0-9_-]{10,}(")/gi, '$1N$2')
    .replace(/(<input\b[^>]*\bvalue=")[A-Za-z0-9_-]{10,}("[^>]*\bname="[^"]*(?:nonce|token)[^"]*")/gi, '$1N$2')
    // Claves JSON o atributos `data-*` de token (`"…token":"…"`, `data-…-token="…"`).
    .replace(/(\\?"[A-Za-z_-]*token\\?"\s*:\s*\\?")[A-Za-z0-9_.-]{10,}(\\?")/gi, '$1N$2')
    .replace(/(\bdata-[a-z-]*token=")[A-Za-z0-9_.-]{10,}(")/gi, '$1N$2');
  for (const re of volatile || []) out = out.replace(new RegExp(re, 'g'), '«volátil»');
  return out;
}
const normalizeCss = (css) => normalizeOrigin(String(css || '')).replace(/([?&]ver=)[^&"'\s)]+/g, '$1V');
const cssPath = (u) => { try { return new URL(u).pathname; } catch (e) { return String(u); } };

// Página y CSS normalizados de una carga (lo que run.cjs guarda en pages/<base>.html y .css) y sus huellas.
function hashes(html, cssBodies, volatile = []) {
  const files = Object.entries(cssBodies).map(([u, text]) => ({ path: cssPath(u), bytes: Buffer.byteLength(text), text: normalizeCss(text) }))
    .map((f) => ({ ...f, sha: sha(f.text) }))
    .sort((a, b) => (a.path < b.path ? -1 : a.path > b.path ? 1 : 0));
  const page = normalizeHtml(html, volatile);
  return { html: sha(page), css: sha(files.map((f) => `${f.path} ${f.sha}`).join('\n')), cssFiles: files.map(({ path: p, bytes, sha: s }) => ({ path: p, bytes, sha: s })), page, cssText: files.map((f) => `/* ${f.path} */\n${f.text}`).join('\n') };
}

const readOr = (file) => { try { return fs.readFileSync(file, 'utf8'); } catch (e) { return null; } };

// Secciones `/* ruta */\n<texto>` del CSS guardado de una página.
function cssSections(text) {
  const map = new Map();
  if (text === null) return map;
  const re = /(?:^|\n)\/\* (\/[^\n]*?) \*\/\n/g;
  const marks = [];
  let m;
  while ((m = re.exec(text))) marks.push({ path: m[1], start: m.index, body: m.index + m[0].length });
  marks.forEach((x, i) => map.set(x.path, text.slice(x.body, i + 1 < marks.length ? marks[i + 1].start : text.length)));
  return map;
}

// Primera diferencia entre dos textos, con contexto.
function firstDiff(a, b) {
  if (a === null || b === null) return 'sin la página normalizada de una de las corridas';
  let i = 0;
  while (i < a.length && i < b.length && a[i] === b[i]) i++;
  const from = Math.max(0, i - 80);
  const clip = (s) => JSON.stringify(s.slice(from, i + 160));
  return `en el carácter ${i} (${a.length} → ${b.length}): antes ${clip(a)} · ahora ${clip(b)}`;
}

const pageBase = (key, r) => (r && r.pageFile) || key.replace(/[^a-z0-9-]/gi, '_');

/**
 * @param {object} o
 * @param {object} o.ref      front-budget.json de la referencia (p. ej. 4.3.0.163 con ELEMENTS_DIR)
 * @param {string} o.refDir   carpeta de la referencia (con pages/)
 * @param {object} o.now      { results, mode, elements } de la corrida actual
 * @param {string} o.nowDir   carpeta de la corrida actual (con pages/)
 * @param {object} o.cases    cases.json
 * @param {string[]} o.strict casos con bytes, peticiones, handles y consultas iguales
 * @returns {{ out: object, problems: string[] }}
 */
function compareRuns({ ref, refDir, now, nowDir, cases, strict }) {
  const problems = [];
  const out = { against: ref.elements || {}, mode: ref.mode, same: [], different: [], missing: [], strict: {} };
  if (ref.mode !== now.mode) problems.push(`COMPARE_FILE: la referencia es del modo ${ref.mode} y esta corrida del modo ${now.mode}`);
  for (const [key, b] of Object.entries(ref.results || {})) {
    const slug = b.slug || key.split('@')[0];
    const c = cases[slug];
    if (!c || c.since) continue;
    const r = (now.results || {})[key];
    if (!r) { out.missing.push(key); continue; }
    const diffs = [];
    if (!r.ok || !b.ok) diffs.push(`la carga falló (${b.ok ? 'ahora' : 'en la referencia'}: ${(b.ok ? r : b).error})`);
    else {
      const volatile = c.volatile || [];
      const before = readOr(path.join(refDir, 'pages', `${pageBase(key, b)}.html`));
      const after = readOr(path.join(nowDir, 'pages', `${pageBase(key, r)}.html`));
      if (before === null || after === null) diffs.push('sin la página normalizada de una de las corridas');
      else {
        const nb = normalizeHtml(before, volatile);
        const na = normalizeHtml(after, volatile);
        if (nb !== na) diffs.push(`HTML ${firstDiff(nb, na)}`);
      }
      const cssBefore = cssSections(readOr(path.join(refDir, 'pages', `${pageBase(key, b)}.css`)));
      const cssAfter = cssSections(readOr(path.join(nowDir, 'pages', `${pageBase(key, r)}.css`)));
      const list = [];
      let detail = '';
      for (const [p, text] of cssAfter) {
        if (!cssBefore.has(p)) list.push(`+${p} (${Buffer.byteLength(text)} B)`);
        else if (normalizeCss(cssBefore.get(p)) !== normalizeCss(text)) {
          list.push(`${p} ${Buffer.byteLength(cssBefore.get(p))} → ${Buffer.byteLength(text)} B`);
          if (!detail) detail = ` · ${firstDiff(normalizeCss(cssBefore.get(p)), normalizeCss(text))}`;
        }
      }
      for (const [p, text] of cssBefore) if (!cssAfter.has(p)) list.push(`−${p} (${Buffer.byteLength(text)} B)`);
      if (list.length) diffs.push(`CSS ${list.join(', ')}${detail}`);
      if (strict.includes(slug)) {
        const fields = ['bytes', 'bytesSame', 'css', 'js', 'img', 'font', 'requests', 'htmlBytes', 'queries', 'queriesWarm'];
        const row = {};
        for (const f of fields) {
          row[f] = [b[f], r[f]];
          if (JSON.stringify(r[f]) !== JSON.stringify(b[f])) diffs.push(`${f} ${JSON.stringify(b[f])} → ${JSON.stringify(r[f])}`);
        }
        const added = (r.handles || []).filter((x) => !(b.handles || []).includes(x));
        const removed = (b.handles || []).filter((x) => !(r.handles || []).includes(x));
        row.handles = { count: [(b.handles || []).length, (r.handles || []).length], added, removed };
        if (!b.handles) diffs.push('la referencia no tiene handles');
        else if (added.length || removed.length) diffs.push(`handles +[${added.join(', ')}] −[${removed.join(', ')}]`);
        out.strict[slug] = row;
      }
    }
    if (diffs.length) { out.different.push({ key, diffs }); problems.push(`DISTINTO ${key} frente a ${(ref.elements || {}).version || 'la referencia'} · ${diffs.join(' | ')}`.slice(0, 1600)); }
    else out.same.push(key);
  }
  return { out, problems };
}

function summaryLines(file, out) {
  const lines = [`Comparación con ${file} (${(out.against || {}).version || '?'}): ${out.same.length} iguales, ${out.different.length} distintos, ${out.missing.length} sin medir ahora`];
  for (const [slug, row] of Object.entries(out.strict)) lines.push(`  ${slug}: ${Object.entries(row).map(([k, v]) => `${k} ${JSON.stringify(v)}`).join(' · ')}`);
  return lines;
}

module.exports = { sha, normalizeHtml, normalizeCss, hashes, compareRuns, summaryLines };
