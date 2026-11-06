// Compara las firmas de contenido de las fases A (Element Pack) y B (adaptador propio).
//
// Firma de estilos (F8, frente B): además de texto, enlaces e imágenes, `signature.styles` guarda el estilo computado de
// las partes que el mapa traduce (`classes` de maps/<id>.php: la clase de Element Pack en la fase A, la propia en las
// demás). Se compara si las dos fases la traen; así un selector muerto deja de pasar inadvertido. La recoge en el
// navegador quien pinta las fases (Playground con Element Pack Lite oficial, P10) con `stylePairs()` y
// `page.evaluate( collectStyles, pairs, 'legacy' | 'own' )`; sin ella todo sigue como antes.
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

export const STYLE_PROPS = [
  'color', 'background-color', 'background-image', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-transform',
  'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
  'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width', 'border-top-style', 'border-top-color',
  'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius', 'box-shadow', 'text-shadow',
];

// Pares { key, legacy, own } de un mapa: la clase de Element Pack y su destino propio. Las entradas sin destino ('')
// no tienen parte que medir. El bloque `classes` es un literal (los mapas llevan closures, así que no se ejecutan).
export function stylePairs(mapPhp) {
  const block = /^\t'classes'\s*=>\s*(?:\$\w+\s*\+\s*)?array\(([\s\S]*?)\n?\t\),$/m.exec(mapPhp);
  if (!block) return [];
  return [...block[1].matchAll(/'((?:[^'\\]|\\.)*)'\s*=>\s*'((?:[^'\\]|\\.)*)'/g)]
    .map(([, legacy, own]) => ({ key: legacy, legacy: legacy.replace('{{WRAPPER}}', ''), own: own.replace('{{WRAPPER}}', '') }))
    .filter((pair) => pair.own.trim() && pair.legacy.trim());
}

// Se ejecuta en la página (page.evaluate): estilo computado del primer elemento de cada parte, dentro de cada widget.
export function collectStyles(pairs, side, props = null) {
  const list = props || ['color', 'background-color', 'background-image', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'text-transform', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left', 'border-top-width', 'border-right-width', 'border-bottom-width', 'border-left-width', 'border-top-style', 'border-top-color', 'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius', 'box-shadow', 'text-shadow'];
  const out = {};
  for (const widget of document.querySelectorAll('.elementor-widget')) {
    for (const pair of pairs) {
      if (out[pair.key]) continue;
      const selector = (side === 'legacy' ? pair.legacy : pair.own).trim();
      let el = null;
      try { el = widget.matches(selector) ? widget : widget.querySelector(selector); } catch (error) { el = null; }
      if (!el) { out[pair.key] = null; continue; }
      const cs = getComputedStyle(el);
      out[pair.key] = Object.fromEntries(list.map((p) => [p, cs.getPropertyValue(p)]));
    }
  }
  return out;
}

// Diferencias de la firma de estilos entre dos fases: [ 'clave propiedad: A → B' ]. Una parte que falta en una fase
// se informa aparte (missing), porque puede ser un estado que esa fase no pinta.
export function compareStyles(stylesA, stylesB) {
  const diffs = [];
  const missing = [];
  for (const [key, valuesA] of Object.entries(stylesA || {})) {
    const valuesB = (stylesB || {})[key];
    if (!valuesA || !valuesB) { if (valuesA || valuesB) missing.push(key); continue; }
    for (const [prop, value] of Object.entries(valuesA)) {
      if (prop in valuesB && valuesB[prop] !== value) diffs.push(`${key} ${prop}: ${value} → ${valuesB[prop]}`);
    }
  }
  return { diffs, missing };
}

const main = process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href;
const [a, b, c, d, e] = main ? process.argv.slice(2).map((f) => JSON.parse(readFileSync(f, 'utf8'))) : [];
let failed = 0;
const norm = (list) => list.map((s) => s.normalize('NFC'));
// `_known.alt` (sin `_known.images`): sólo cambia el texto alternativo, una diferencia deliberada que se ve igual (una
// imagen decorativa junto a su título visible); el archivo de cada imagen se sigue comparando.
const altOnly = (entry) => Boolean(entry?.known?.alt) && !entry?.known?.images;
// Firma de un tipo (`text`, `links`, `images`) tal como se compara: con `_known.alt`, las imágenes sin su ALT.
export const comparable = (entryA, key, list) => (key === 'images' && altOnly(entryA) ? list.map((image) => image.replace(/ \| alt=[\s\S]*$/, '')) : list);
// Nota de una fase cuyo ALT difiere con `_known.alt` declarado (null si no hay nada que anotar).
export const altNote = (entryA, other, phase) => (altOnly(entryA) && JSON.stringify(norm(entryA.signature.images)) !== JSON.stringify(norm(other?.signature?.images ?? [])) ? `${phase}: texto alternativo distinto (conocido: ${entryA.known.alt})` : null);
for (const [widget, cases] of Object.entries(main ? a.widgets : {})) {
  for (const [name, blogs] of Object.entries(cases)) {
    for (const [blog, entryA] of Object.entries(blogs)) {
      const entryB = b.widgets?.[widget]?.[name]?.[blog];
      const problems = [];
      const notes = [];
      // Element Pack sólo registra sus widgets de integración con su plugin activo: sin él, la página no muestra nada.
      // Si tras migrar tampoco se pinta nada (el widget Shortcode con «Ocultar si falta el shortcode»), es lo mismo.
      const silent = (entry) => !!entry && ['text', 'links', 'images'].every((key) => !(entry.signature?.[key] ?? []).length);
      const later = [entryB, c?.widgets?.[widget]?.[name]?.[blog], e?.widgets?.[widget]?.[name]?.[blog]].filter(Boolean);
      if (!entryA.registered) {
        if (silent(entryA) && entryB?.registered && later.every(silent)) notes.push('Element Pack no lo registra sin su plugin; tampoco se muestra tras migrar');
        else problems.push('Element Pack no registró el widget en la fase A');
      }
      if (!entryB?.registered) problems.push('el adaptador no registró el widget en la fase B');
      for (const key of ['text', 'links', 'images']) {
        const ta = norm(comparable(entryA, key, entryA.signature[key])); const tb = norm(comparable(entryA, key, entryB?.signature?.[key] ?? []));
        const missing = ta.filter((x) => !tb.includes(x)); const extra = tb.filter((x) => !ta.includes(x));
        const target = entryA.known?.[key] ? notes : problems;
        const why = entryA.known?.[key] ? ` (conocido: ${entryA.known[key]})` : '';
        if (missing.length) target.push(`${key} ausente: ${JSON.stringify(missing)}${why}`);
        if (extra.length) target.push(`${key} adicional: ${JSON.stringify(extra)}${why}`);
      }
      for (const [phase, entry] of [['B', entryB], ['C', c?.widgets?.[widget]?.[name]?.[blog]], ['E', e?.widgets?.[widget]?.[name]?.[blog]]]) {
        const note = entry ? altNote(entryA, entry, phase) : null;
        if (note) notes.push(note);
      }
      if (entryB?.editor_resave && !entryB.editor_resave.ok) problems.push(`tras guardar desde el editor: ${JSON.stringify(entryB.editor_resave)}`);
      // Firma de estilos de las partes mapeadas: A (Element Pack) frente a B (adaptador) y, si la hay, C (convertido).
      for (const [phase, entry] of [['B', entryB], ['C', c?.widgets?.[widget]?.[name]?.[blog]]]) {
        if (!entryA.signature?.styles || !entry?.signature?.styles) continue;
        const { diffs, missing } = compareStyles(entryA.signature.styles, entry.signature.styles);
        const target = entryA.known?.styles ? notes : problems;
        const why = entryA.known?.styles ? ` (conocido: ${entryA.known.styles})` : '';
        if (diffs.length) target.push(`estilos (${phase}) distintos: ${JSON.stringify(diffs.slice(0, 8))}${diffs.length > 8 ? ` y ${diffs.length - 8} más` : ''}${why}`);
        if (missing.length) notes.push(`estilos (${phase}) sin la parte: ${missing.join(', ')}`);
      }
      const entryC = c?.widgets?.[widget]?.[name]?.[blog];
      const entryD = d?.widgets?.[widget]?.[name]?.[blog];
      if (entryC) {
        for (const key of ['text', 'links', 'images']) {
          const ta = norm(comparable(entryA, key, entryA.signature[key])); const tc = norm(comparable(entryA, key, entryC.signature[key]));
          if (!entryA.known?.[key] && (ta.length !== tc.length || ta.some((x) => !tc.includes(x)))) problems.push(`tras la migración cambia ${key}: ${JSON.stringify(tc)}`);
        }
        notes.push(`migración: ${JSON.stringify(entryC.migration)} → editor ${entryC.editor_widget}`);
      }
      const entryE = e?.widgets?.[widget]?.[name]?.[blog];
      if (entryE) {
        // Fase E: el documento convertido se pinta sólo con el núcleo, sin capa heredada.
        for (const key of ['text', 'links', 'images']) {
          const ta = norm(comparable(entryA, key, entryA.signature[key])); const te = norm(comparable(entryA, key, entryE.signature[key]));
          if (!entryA.known?.[key] && (ta.length !== te.length || ta.some((x) => !te.includes(x)))) problems.push(`convertido y sin capa heredada cambia ${key}: ${JSON.stringify(te)}`);
        }
        if (entryE.legacy_left?.length) problems.push(`quedan identificadores heredados: ${JSON.stringify(entryE.legacy_left)}`);
        if (entryE.html_missing?.length) problems.push(`convertido, HTML sin: ${JSON.stringify(entryE.html_missing)}`);
        if (entryE.css_missing?.length) problems.push(`convertido, CSS sin: ${JSON.stringify(entryE.css_missing)}`);
        notes.push(`convertido: ${entryE.editor_widget}`);
      }
      const migrated = entryC && ((entryC.migration?.native ?? 0) + (entryC.migration?.adapter ?? 0)) > 0;
      if (entryD && migrated && (!entryD.reverted || entryD.data_md5 !== entryA.data_md5)) problems.push('restaurar no devolvió el documento original');
      if (entryD && !migrated && entryD.data_md5 !== entryA.data_md5) problems.push('el documento cambió sin migración');
      if (entryB && entryB.wrapper_ok === false) problems.push('el envoltorio no se presenta como el widget de destino');
      if (entryB?.html_missing?.length) problems.push(`HTML sin: ${JSON.stringify(entryB.html_missing)}`);
      if (entryB?.css_missing?.length) problems.push(`CSS regenerado sin: ${JSON.stringify(entryB.css_missing)}`);
      if ((widget.startsWith('bdt-') || widget === 'fooevents-calendar' || widget === 'lightbox') && entryB && entryB.editor_settings && !entryB.editor_settings.includes('_digitalisimo_legacy')) problems.push('el editor no recibe los ajustes traducidos');
      const status = problems.length ? 'DIFERENCIAS' : 'IGUAL';
      if (problems.length) failed++;
      console.log(`${status.padEnd(11)} ${widget} · ${name} · sitio ${blog}`);
      for (const p of problems) console.log(`    - ${p}`);
      for (const n of notes) console.log(`    · ${n}`);
    }
  }
}
if (main) {
  console.log(failed ? `\n${failed} combinaciones con diferencias.` : '\nTodas las combinaciones coinciden.');
  process.exitCode = failed ? 1 : 0;
}
