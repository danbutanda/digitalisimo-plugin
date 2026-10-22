#!/usr/bin/env node
// Biblioteca propia de máscaras y teselas de DIGITALÍSIMO Elements (F7a, frente G).
//
// Lee las fuentes de digitalisimo-elements/modules/digitalisimo-design/backgrounds/svg/{masks,patterns}/*.svg, las
// valida y genera, en la misma carpeta `backgrounds/`:
// - library.php: `[ 'masks' => [ slug => url("data:…") ], 'patterns' => [ … ] ]`, para que en ejecución no se lea
//   ningún archivo;
// - MANIFEST.json: sha256, bytes, autoría y fecha de cada fuente.
//
// La codificación es la de Design\Css::svg_data_uri() sin el saneador del núcleo (las fuentes son propias y las vigila
// su sha256): espacios minimizados, comillas dobles a simples si no hay simples y rawurlencode() (en JS,
// encodeURIComponent() más !'()*). Así ninguna regla lleva `;` ni `#` (V-B3).
//
// Uso:
//   node scripts/design-library.mjs            comprueba que library.php y MANIFEST.json están al día (sale con 1 si no)
//   node scripts/design-library.mjs --write    los regenera; conserva la fecha de las fuentes que no cambiaron
//
// tests/elements-design-backgrounds.php repite la generación en PHP y exige el mismo resultado.
import { createHash } from 'node:crypto';
import { existsSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('..', import.meta.url));
const dir = join(root, 'digitalisimo-elements/modules/digitalisimo-design/backgrounds');
const KINDS = ['masks', 'patterns'];
const AUTHOR = 'DIGITALÍSIMO, GPL-3.0-or-later';
const MAX_BYTES = 1536;
const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
// Css::SVG_DENIED, más lo que una primitiva de un solo relleno nunca necesita.
const DENIED = ['<script', '<foreignobject', '<image', '<iframe', '<embed', '<object', '@import', 'url(', 'javascript:', '<!entity', '<?php',
  '<!--', '<?xml', '<!doctype', '<style', 'style=', 'stroke', 'opacity', 'filter', 'gradient', '<pattern', '<mask', '<clippath', '<use', '<text', 'class=', 'transform'];
const FILL = /\sfill="([^"]*)"/g;

export function encode(svg) {
  let min = svg.trim().replace(/>\s+</g, '><').replace(/\s+/g, ' ').trim();
  if (!min.includes("'")) min = min.replace(/"/g, "'");
  return 'url("data:image/svg+xml,' + encodeURIComponent(min).replace(/[!'()*]/g, (c) => '%' + c.charCodeAt(0).toString(16).toUpperCase()) + '")';
}

/** Errores de una fuente: tamaño, raíz con `xmlns` y `viewBox`, lista de rechazo, `on*`, `href` y un solo relleno opaco. */
export function problems(name, svg) {
  const out = [];
  const lower = svg.toLowerCase();
  if (Buffer.byteLength(svg) > MAX_BYTES) out.push(`${name}: pasa de ${MAX_BYTES} B`);
  if (!/^<svg xmlns="http:\/\/www\.w3\.org\/2000\/svg" viewBox="[0-9. ]+">/.test(svg)) out.push(`${name}: la raíz debe ser <svg xmlns=… viewBox=…>`);
  if (!svg.trimEnd().endsWith('</svg>')) out.push(`${name}: no termina en </svg>`);
  for (const needle of DENIED) if (lower.includes(needle)) out.push(`${name}: contiene ${needle}`);
  if (/[\s"'/]on[a-z]+\s*=/i.test(svg) || /href\s*=/i.test(svg)) out.push(`${name}: atributos de evento o enlaces`);
  const fills = new Set([...svg.matchAll(FILL)].map((m) => m[1].toLowerCase()));
  if (fills.size > 1 || [...fills].some((f) => !['#000', 'black', 'currentcolor'].includes(f))) out.push(`${name}: más de un relleno o un relleno no opaco`);
  if (/[;#{}%]/.test(svg.replace(/^<svg[^>]*>/, ''))) out.push(`${name}: caracteres que no necesita una primitiva (; # { } %)`);
  return out;
}

export function sources() {
  const out = {};
  for (const kind of KINDS) {
    out[kind] = {};
    for (const file of readdirSync(join(dir, 'svg', kind)).filter((f) => f.endsWith('.svg')).sort()) {
      out[kind][file.slice(0, -4)] = readFileSync(join(dir, 'svg', kind, file), 'utf8');
    }
  }
  return out;
}

export function library(src) {
  const lines = [
    '<?php',
    '/**',
    ' * Biblioteca propia de máscaras y teselas de F7a: geometría DIGITALÍSIMO, GPL-3.0-or-later.',
    ' *',
    ' * Generada por `scripts/design-library.mjs` desde `svg/`; no se edita a mano. `node scripts/design-library.mjs',
    ' * --write` la regenera y `tests/elements-design-backgrounds.php` comprueba que coincide con las fuentes.',
    ' */',
    '',
    "defined( 'ABSPATH' ) || exit;",
    '',
    'return array(',
  ];
  for (const kind of KINDS) {
    lines.push(`\t'${kind}' => array(`);
    for (const [slug, svg] of Object.entries(src[kind])) lines.push(`\t\t'${slug}' => '${encode(svg)}',`);
    lines.push('\t),');
  }
  lines.push(');', '');
  return lines.join('\n');
}

export function manifest(src, previous = {}, today = new Date().toISOString().slice(0, 10)) {
  const files = {};
  for (const kind of KINDS) {
    for (const [slug, svg] of Object.entries(src[kind])) {
      const key = `${kind}/${slug}.svg`;
      const sha256 = createHash('sha256').update(svg).digest('hex');
      const old = previous.files?.[key];
      files[key] = { sha256, bytes: Buffer.byteLength(svg), author: AUTHOR, date: old?.sha256 === sha256 && old.date ? old.date : today };
    }
  }
  return JSON.stringify({ generator: 'scripts/design-library.mjs', author: AUTHOR, files }, null, 2) + '\n';
}

function main() {
  const src = sources();
  const errors = [];
  for (const kind of KINDS) {
    for (const [slug, svg] of Object.entries(src[kind])) {
      if (!SLUG.test(slug)) errors.push(`${kind}/${slug}.svg: nombre no válido`);
      errors.push(...problems(`${kind}/${slug}.svg`, svg));
    }
  }
  if (errors.length) {
    console.error(errors.join('\n'));
    process.exit(1);
  }
  const manifestPath = join(dir, 'MANIFEST.json');
  const previous = existsSync(manifestPath) ? JSON.parse(readFileSync(manifestPath, 'utf8')) : {};
  const outputs = { [join(dir, 'library.php')]: library(src), [manifestPath]: manifest(src, previous) };
  if (process.argv.includes('--write')) {
    for (const [path, text] of Object.entries(outputs)) writeFileSync(path, text);
    console.log(`Biblioteca regenerada: ${Object.keys(src.masks).length} máscaras y ${Object.keys(src.patterns).length} teselas.`);
    return;
  }
  const stale = Object.entries(outputs).filter(([path, text]) => !existsSync(path) || readFileSync(path, 'utf8') !== text).map(([path]) => path);
  if (stale.length) {
    console.error(`Desactualizado (node scripts/design-library.mjs --write): ${stale.join(', ')}`);
    process.exit(1);
  }
  console.log('Biblioteca de máscaras y teselas al día.');
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) main();
