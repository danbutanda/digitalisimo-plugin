// Compara las páginas pintadas con el plugin Elementor y con el núcleo incluido, y las comprobaciones de cada fase.
import { readFileSync } from 'node:fs';
const [dir] = process.argv.slice(2);
const read = (name) => JSON.parse(readFileSync(`${dir}/${name}.json`, 'utf8'));
// Cada sitio se pintó en su propia petición: se juntan sus páginas; las comprobaciones generales son las del sitio 1.
const merge = (phase) => { const one = read(`${phase}-1`); const two = read(`${phase}-2`); return { checks: { ...two.checks, ...one.checks }, pages: { ...one.pages, ...two.pages } }; };
const a = merge('elementor');
const b = merge('bundled');
const removed = read('removed');
const problems = [];
const expect = (ok, message) => { if (!ok) problems.push(message); };
expect(a.checks.core_bundled === '' && a.checks.elementor_path.startsWith('/elementor/'), `con Elementor activo debe mandar el plugin Elementor: ${JSON.stringify(a.checks)}`);
expect(removed.deleted && !removed.still_there && removed.global_lang.length === 0, `Elementor y sus traducciones deben quedar borrados: ${JSON.stringify(removed)}`);
expect(b.checks.core_bundled === b.checks.elementor && b.checks.elementor_path.startsWith('/digitalisimo-elements/elementor/'), `sin Elementor debe cargar el núcleo incluido: ${JSON.stringify(b.checks)}`);
expect(b.checks.pro_loaded, 'las funciones Pro deben cargar sobre el núcleo incluido');
for (const [phase, data] of [['elementor', a], ['bundled', b]]) {
  expect(data.checks.addon_unmet === false && data.checks.addon_activate === 'ok', `${phase}: un complemento que exige Elementor debe poder activarse (${data.checks.addon_activate})`);
  expect(data.checks.js_text === 'ok', `${phase}: los scripts de Elementor deben encontrar su traducción`);
  expect(data.checks.kit_1 && data.checks.kit_2, `${phase}: cada sitio debe tener su kit`);
}
expect(a.checks.php_text !== 'Edit with Elementor' && b.checks.php_text === a.checks.php_text, `el español debe seguir tras borrar Elementor: «${a.checks.php_text}» / «${b.checks.php_text}»`);
expect(Array.isArray(b.checks.rollback) && b.checks.rollback.length === 0, 'la reversión de versiones de Elementor debe quedar vacía con el núcleo incluido');
expect(b.checks.kit_new_site === true, 'un sitio nuevo de la red debe recibir su kit');
// La imagen de ejemplo se guardó con la ruta del plugin Elementor; con el núcleo incluido debe apuntar a su carpeta.
expect(a.checks.image_src_1.startsWith('/wp-content/plugins/elementor/assets/'), `con Elementor la imagen de ejemplo usa su carpeta: ${a.checks.image_src_1}`);
for (const blog of [1, 2]) expect(String(b.checks[`image_src_${blog}`]).startsWith('/wp-content/plugins/digitalisimo-elements/elementor/assets/'), `sitio ${blog}: la imagen de ejemplo guardada con Elementor debe servirse desde el núcleo incluido: ${b.checks[`image_src_${blog}`]}`);
let same = 0;
for (const blog of Object.keys(a.pages)) {
  for (const [widget, pa] of Object.entries(a.pages[blog])) {
    const pb = b.pages[blog]?.[widget];
    if (!pb || pa.missing || pb.missing) { problems.push(`sitio ${blog} · ${widget}: falta la página`); continue; }
    if (pa.error || pb.error) { problems.push(`sitio ${blog} · ${widget}: error ${JSON.stringify(pa.error ? pa : pb)}`); continue; }
    const diffs = [];
    for (const key of ['text', 'links', 'images']) if (JSON.stringify(pa.signature[key]) !== JSON.stringify(pb.signature[key])) diffs.push(`${key}: ${JSON.stringify(pa.signature[key]).slice(0, 160)} → ${JSON.stringify(pb.signature[key]).slice(0, 160)}`);
    if (pa.css !== pb.css) diffs.push(`CSS distinto (${pa.css_bytes} → ${pb.css_bytes} bytes)`);
    if (!pa.bytes) diffs.push('la página no pintó nada');
    if (diffs.length) problems.push(`sitio ${blog} · ${widget}: ${diffs.join(' · ')}`); else same++;
  }
}
console.log(`Páginas iguales con el plugin Elementor y con el núcleo incluido: ${same}`);
console.log(problems.length ? problems.map((p) => `PROBLEMA ${p}`).join('\n') : 'Todas las comprobaciones pasan.');
process.exit(problems.length ? 1 : 0);
