// Compara las firmas de contenido de las fases A (Element Pack) y B (adaptador propio).
import { readFileSync } from 'node:fs';
const [a, b, c, d] = process.argv.slice(2).map((f) => JSON.parse(readFileSync(f, 'utf8')));
let failed = 0;
const norm = (list) => list.map((s) => s.normalize('NFC'));
for (const [widget, cases] of Object.entries(a.widgets)) {
  for (const [name, blogs] of Object.entries(cases)) {
    for (const [blog, entryA] of Object.entries(blogs)) {
      const entryB = b.widgets?.[widget]?.[name]?.[blog];
      const problems = [];
      if (!entryA.registered) problems.push('Element Pack no registró el widget en la fase A');
      if (!entryB?.registered) problems.push('el adaptador no registró el widget en la fase B');
      const notes = [];
      for (const key of ['text', 'links', 'images']) {
        const ta = norm(entryA.signature[key]); const tb = norm(entryB?.signature?.[key] ?? []);
        const missing = ta.filter((x) => !tb.includes(x)); const extra = tb.filter((x) => !ta.includes(x));
        const target = entryA.known?.[key] ? notes : problems;
        const why = entryA.known?.[key] ? ` (conocido: ${entryA.known[key]})` : '';
        if (missing.length) target.push(`${key} ausente: ${JSON.stringify(missing)}${why}`);
        if (extra.length) target.push(`${key} adicional: ${JSON.stringify(extra)}${why}`);
      }
      const entryC = c?.widgets?.[widget]?.[name]?.[blog];
      const entryD = d?.widgets?.[widget]?.[name]?.[blog];
      if (entryC) {
        for (const key of ['text', 'links', 'images']) {
          const ta = norm(entryA.signature[key]); const tc = norm(entryC.signature[key]);
          if (!entryA.known?.[key] && (ta.length !== tc.length || ta.some((x) => !tc.includes(x)))) problems.push(`tras la migración cambia ${key}: ${JSON.stringify(tc)}`);
        }
        notes.push(`migración: ${JSON.stringify(entryC.migration)} → editor ${entryC.editor_widget}`);
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
console.log(failed ? `\n${failed} combinaciones con diferencias.` : '\nTodas las combinaciones coinciden.');
process.exitCode = failed ? 1 : 0;
