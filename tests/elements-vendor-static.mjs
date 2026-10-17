// Código editable de DIGITALÍSIMO Elements sin dominios de los plugins de origen: ningún enlace, API, recurso o vídeo
// hacia los hosts de tests/playground/brand-audit/vendors.json (hosts y futuros) en core/, modules/, license/,
// assets/js (fuente y minificado), plugin.php, pro-elements.php y digitalisimo-*.php. El núcleo incluido (elementor/)
// no se revisa: no se edita a mano y su salida la cubren la guarda del producto y tests/playground/brand-audit.sh.
// Los comentarios no cuentan (no llegan al usuario). Las excepciones, con su motivo, están en
// tests/elements-vendor-static.allow.json; una excepción sin uso hace fallar la prueba.
import { readFileSync, readdirSync, statSync, existsSync } from 'node:fs';
import { join, relative, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const BASE = join(ROOT, 'digitalisimo-elements');
// La lista de vídeos del proveedor se regenera desde su código (vendors.cjs), como en la auditoría en Playground.
const V = createRequire(import.meta.url)(join(ROOT, 'tests/playground/brand-audit/vendors.cjs')).load(ROOT);
const allowFile = join(ROOT, 'tests/elements-vendor-static.allow.json');
const allow = (existsSync(allowFile) ? JSON.parse(readFileSync(allowFile, 'utf8')).excepciones || [] : []).map((a) => {
  if (!a.archivo || !a.motivo) throw new Error(`Excepción sin archivo o sin motivo en ${relative(ROOT, allowFile)}: ${JSON.stringify(a)}`);
  return { ...a, file: new RegExp(a.archivo), line: a.patron ? new RegExp(a.patron) : null, used: 0 };
});
const hosts = [...V.hosts, ...(V.future || [])];
const re = new RegExp(`(?<![a-z0-9-])((?:[a-z0-9-]+\\.)*(?:${hosts.map((h) => h.replace(/\./g, '\\.')).join('|')}))(?![a-z0-9-])`, 'gi');
// Vídeos del proveedor (de muestra, de ayuda o por defecto) en un host de vídeo.
const videoHost = new RegExp(V.videoHosts.replace(/^\(\^\|\\\.\)/, '(?<![a-z0-9-])').replace(/\$$/, ''), 'i');
const videoRe = new RegExp(`(${V.vendorVideos.map((v) => v.replace(/[-]/g, '\\-')).join('|')})(?![A-Za-z0-9_-])`, 'g');
const TEXT = /\.(php|js|mjs|cjs|jsx|ts|tsx|css|json|html|twig|svg|txt)$/i;

const files = [];
const walk = (dir) => {
  for (const name of readdirSync(dir)) {
    const p = join(dir, name);
    const st = statSync(p);
    if (st.isDirectory()) { if (!['node_modules', '.git'].includes(name)) walk(p); } else if (TEXT.test(name)) files.push(p);
  }
};
for (const d of ['core', 'modules', 'license', 'assets/js']) if (existsSync(join(BASE, d))) walk(join(BASE, d));
for (const name of readdirSync(BASE)) if (/^(plugin|pro-elements|digitalisimo-[\w-]+)\.php$/.test(name)) files.push(join(BASE, name));

// Comentarios: líneas de docblock o de comentario y comentarios /* */ y // de una línea.
const stripComments = (line) => {
  const t = line.trim();
  if (/^(\*|\/\*|\/\/|#(?!\w*\s*\[))/.test(t)) return '';
  return line.replace(/\/\*.*?\*\//g, '').replace(/(^|[\s;{}(),])\/\/(?!\/).*$/, '$1');
};

const found = [];
for (const file of files) {
  const rel = relative(BASE, file);
  const text = readFileSync(file, 'utf8');
  re.lastIndex = 0;
  if (!re.test(text) && !videoHost.test(text)) { re.lastIndex = 0; continue; }
  re.lastIndex = 0;
  const minified = /\.min\.js$/.test(file) || text.length / Math.max(1, text.split('\n').length) > 1000;
  const lines = text.split('\n');
  lines.forEach((raw, i) => {
    const line = minified ? raw : stripComments(raw);
    const matches = [...line.matchAll(re)];
    if (videoHost.test(line)) for (const m of line.matchAll(videoRe)) matches.push(Object.assign([m[0], `vídeo ${m[1]}`], { index: m.index }));
    for (const m of matches) {
      const at = Math.max(0, m.index - 60);
      const snippet = line.slice(at, m.index + m[0].length + 60).replace(/\s+/g, ' ').trim();
      const ex = allow.find((a) => a.file.test(rel) && (!a.line || a.line.test(line)));
      if (ex) { ex.used++; continue; }
      found.push({ rel, line: i + 1, host: m[1].startsWith('vídeo ') ? m[1] : m[1].toLowerCase(), snippet });
    }
  });
}

const problems = [];
if (found.length) {
  const byFile = new Map();
  for (const f of found) {
    if (!byFile.has(f.rel)) byFile.set(f.rel, []);
    byFile.get(f.rel).push(f);
  }
  const hostsCount = {};
  for (const f of found) hostsCount[f.host] = (hostsCount[f.host] || 0) + 1;
  problems.push(`${found.length} menciones de dominios de los plugins de origen en ${byFile.size} archivos de digitalisimo-elements/ (${Object.entries(hostsCount).sort((a, b) => b[1] - a[1]).map(([h, n]) => `${h} ×${n}`).join(', ')}):`);
  for (const [rel, list] of [...byFile].sort((a, b) => b[1].length - a[1].length)) {
    problems.push(`  ${rel} (${list.length})`);
    for (const f of list.slice(0, 4)) problems.push(`    :${f.line} ${f.host} · ${f.snippet.slice(0, 160)}`);
    if (list.length > 4) problems.push(`    (+${list.length - 4})`);
  }
}
for (const a of allow.filter((x) => !x.used)) problems.push(`Excepción sin uso en tests/elements-vendor-static.allow.json (quitarla): ${a.archivo}${a.patron ? ' · ' + a.patron : ''} — ${a.motivo}`);
if (problems.length) {
  console.error(problems.join('\n'));
  process.exit(1);
}
console.log(`Sin dominios de los plugins de origen en ${files.length} archivos editables de DIGITALÍSIMO Elements (${allow.length} excepciones con motivo).`);
