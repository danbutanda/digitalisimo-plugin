#!/usr/bin/env node
// Marca propia (docs/MARCA_PROPIA.md), PROTOTIPO de la fase 0: el espejo de los assets del front generado al empaquetar.
//
// Copia transformada (reglas de `digitalisimo-elements/marca/reglas.json`, scripts/marca-transform.mjs) de:
//   - núcleo: los CSS y JS del front, TODOS los trozos que carga su webpack (`js/chunks/`, por `urls.assets`) y los CSS y
//     JS de `lib/` (los carga `assetsLoader` con la misma base) → <out>/motor/<ruta bajo elementor/assets/>;
//   - derivado de PRO y módulos propios: los CSS y JS del front que contienen «elementor» → <out>/<ruta bajo el plugin>.
// Los binarios (fuentes, imágenes) no se copian: los `url()` relativos de cada hoja se reescriben hacia su sitio
// original en el paquete (con la carpeta del motor `--motor` = motor, D-9, o elementor). No se toca el repositorio salvo
// la carpeta de salida.
//
// Uso: node scripts/marca-espejo.mjs --out <carpeta> [--motor motor|elementor] [--json]
//   Escribe el espejo y un resumen (archivos, bytes, gzip, «elementor» que queda por archivo).
import { readFileSync, writeFileSync, mkdirSync, readdirSync, statSync, existsSync, rmSync } from 'node:fs';
import { join, relative, dirname, posix } from 'node:path';
import { gzipSync } from 'node:zlib';
import { loadRules, transformCss, transformJs, count, mirrorName } from './marca-transform.mjs';

const ROOT = new URL('..', import.meta.url).pathname;
const EL = join(ROOT, 'digitalisimo-elements');
const argv = process.argv.slice(2);
const arg = (k, d) => { const i = argv.indexOf(k); return i >= 0 ? argv[i + 1] : d; };
const OUT = arg('--out');
const MOTOR = arg('--motor', 'motor');
if (!OUT) { console.error('uso: node scripts/marca-espejo.mjs --out <carpeta> [--motor motor|elementor]'); process.exit(2); }
const rules = loadRules();

function walk(dir, out = []) {
  if (!existsSync(dir)) return out;
  for (const n of readdirSync(dir)) { const p = join(dir, n); statSync(p).isDirectory() ? walk(p, out) : out.push(p); }
  return out;
}
const EDITOR = /(^|[/-])(admin|editor|app|common|onboarding|ai|kit-library|kit-elements|notes|packages|web-cli|new-template|beta-tester|dev-tools|import-export|element-manager|checklist|announcements|styleguide|cloud|container-converter|responsive-bar|preview|screenshot|custom-code|display-conditions|atomic-form-editor|editor-interactions|form-submission-admin|swatches-admin|theme-light|theme-dark|editor-one|mcp|role-manager|wp-one)([./-]|$)/;
const isAsset = (f) => /\.(css|js)$/.test(f) && !/\.map$/.test(f);

const engineAssets = join(EL, 'elementor/assets');
const engine = walk(engineAssets).filter((f) => {
  const rel = relative(engineAssets, f);
  if (!isAsset(f)) return false;
  if (rel.startsWith('lib/') || rel.startsWith('js/chunks/')) return true;
  return !EDITOR.test(rel) && /^(css\/(frontend|widget-|conditionals\/|modules\/|e-swiper|apple-webkit|lightbox|dialog|shapes)|js\/(frontend|webpack\.runtime|[^/]*handler))/.test(rel);
});
// `css/templates/` no se sirve: el núcleo la lee del disco para compilar los puntos de corte propios en uploads (fase 2).
const ours = [...walk(join(EL, 'assets')), ...walk(join(EL, 'modules')).filter((f) => f.includes('/assets/'))]
  .filter((f) => !f.includes('/css/templates/') && isAsset(f) && !EDITOR.test(relative(EL, f)) && !f.includes('/assets/digitalisimo/') && /elementor/i.test(readFileSync(f, 'utf8')));

// Sólo lo que escribe el espejo: `reglas.json` (si la salida es `marca/`) se queda.
for (const d of ['motor', 'assets', 'modules', 'espejo.json']) rmSync(join(OUT, d), { recursive: true, force: true });
const summary = { motor: { archivos: 0, bytes: 0, gzip: 0, antes: 0, despues: 0 }, propio: { archivos: 0, bytes: 0, gzip: 0, antes: 0, despues: 0 }, quedan: [] };

// Ruta en el paquete del original (para los url() relativos): el motor con su carpeta del ZIP.
const pkgPath = (f) => { const rel = relative(EL, f); return rel.startsWith('elementor/') ? MOTOR + rel.slice('elementor'.length) : rel; };

function rewriteUrls(css, original, mirrorRel) {
  return css.replace(/url\(\s*(['"]?)(?!data:|https?:|\/\/|#|\/)([^'")]+)\1\s*\)/g, (m, q, u) => {
    const target = posix.normalize(posix.join(posix.dirname(pkgPath(original)), u.split(/[?#]/)[0]));
    const rest = u.slice(u.split(/[?#]/)[0].length);
    const fromDir = posix.dirname(posix.join('marca', mirrorRel));
    return `url(${q}${posix.relative(fromDir, target)}${rest}${q})`;
  });
}

for (const [list, group, base, prefix] of [[engine, 'motor', engineAssets, 'motor/'], [ours, 'propio', EL, '']]) {
  for (const f of list) {
    const src = readFileSync(f, 'utf8');
    const rel = mirrorName(prefix + relative(base, f).split('\\').join('/'), rules);
    let out = f.endsWith('.css') ? transformCss(src, rules) : transformJs(src, rules);
    if (f.endsWith('.css')) out = rewriteUrls(out, f, rel);
    const dest = join(OUT, rel);
    mkdirSync(dirname(dest), { recursive: true });
    writeFileSync(dest, out);
    const s = summary[group];
    s.archivos++; s.bytes += Buffer.byteLength(out); s.gzip += gzipSync(out, { level: 9 }).length; s.antes += count(src); s.despues += count(out);
    if (count(out)) summary.quedan.push([rel, count(out)]);
  }
}
summary.quedan.sort((a, b) => b[1] - a[1]);
writeFileSync(join(OUT, 'espejo.json'), JSON.stringify(summary, null, 1));
if (argv.includes('--json')) console.log(JSON.stringify(summary, null, 1));
else {
  for (const g of ['motor', 'propio']) {
    const s = summary[g];
    console.log(`${g}: ${s.archivos} archivos, ${(s.bytes / 1048576).toFixed(2)} MB (${(s.gzip / 1048576).toFixed(2)} MB gzip); «elementor» ${s.antes} → ${s.despues}`);
  }
  console.log(`Archivos con «elementor» que quedan: ${summary.quedan.length}${summary.quedan.length ? ' · ' + summary.quedan.slice(0, 8).map((q) => q.join(':')).join(' ') : ''}`);
}
