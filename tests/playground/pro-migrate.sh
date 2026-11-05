#!/usr/bin/env bash
# Migración de los widgets de PRO de punta a punta (C0 de docs/PRO_WIDGETS_OBJECTIVE.md), en Playground con el núcleo
# incluido en DIGITALÍSIMO Elements y DIGITALÍSIMO Migración: un sitio con Blockquote de PRO en una página (uno anidado),
# en una plantilla de cabecera y en una revisión; el inventario de «Analizar», «Migrar» hasta terminar y --revert.
# Comprueba que la página y la cabecera pasan a la Cita (con el CSS personalizado traducido), que la revisión no se
# toca, que queda la copia original y que --revert devuelve el Blockquote de PRO. C1: los cinco carruseles de PRO. C2: los
# catorce widgets de contenido de PRO y una cuenta regresiva de Element Pack (`bdt-countdown`) que F8 lleva a la de PRO y
# la etapa `pro` a la propia en la misma pasada; --revert devuelve los de PRO y el de Element Pack.
# Uso: bash tests/playground/pro-migrate.sh   (OUT, TMPDIR como los demás arneses)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/var/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/var/tmp/digi-pro-migrate}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
rm -rf "$OUT" && mkdir -p "$OUT/probe"
cp "$ROOT/tests/playground/pro-migrate/"*.php "$OUT/probe/"
STEPS='{"step":"runPHP","code":"<?php require \"/wordpress/wp-content/probe/setup.php\";"}'
for s in inventory run run revert; do
  STEPS="$STEPS,{\"step\":\"writeFile\",\"path\":\"/wordpress/wp-content/probe/step.txt\",\"data\":\"$s\"},{\"step\":\"runPHP\",\"code\":\"<?php require \\\"/wordpress/wp-content/probe/check.php\\\";\"}"
done
printf '{"steps":[%s]}\n' "$STEPS" > "$OUT/blueprint.json"
trap 'playground_cleanup' EXIT
$CLI run-blueprint --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$OUT/probe:/wordpress/wp-content/probe" > "$OUT/cli.log" 2>&1 || { tail -20 "$OUT/cli.log"; exit 1; }
node - "$OUT/probe/steps.jsonl" <<'JS'
const fs = require('fs');
const steps = fs.readFileSync(process.argv[2], 'utf8').trim().split('\n').map((l) => JSON.parse(l));
const by = (s) => steps.filter((x) => x.step === s);
const problems = [];
const ok = (c, m) => { console.log(`${c ? 'OK   ' : 'FALLA'}  ${m}`); if (!c) problems.push(m); };
for (const s of steps) if (s.error) problems.push(`${s.step}: ${s.error}`);
const inv = (by('inventory')[0] || {}).inventory || {};
const row = (inv.widgets || []).find((w) => w.widget === 'blockquote') || {};
ok(row.family === 'C2' && row.target === 'digitalisimo-blockquote' && row.elements === 3 && row.documents === 2 && row.revisions === 1 && inv.where && inv.where.page === 3 && inv.where.header === 1, `inventario: 3 Blockquote en 2 documentos (página y cabecera), familia C2, destino la Cita y la revisión aparte; widgets de PRO en 3 páginas (citas, carruseles y contenido) y una cabecera (${JSON.stringify(row).slice(0, 200)})`);
const c1 = ['nested-carousel', 'slides', 'media-carousel', 'testimonial-carousel', 'reviews'].map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c1.every((r) => r.family === 'C1' && r.elements === 1 && r.documents === 1 && r.target === `digitalisimo-${r.widget}`), `inventario: los cinco carruseles de PRO, familia C1, cada uno con su widget propio de destino (${JSON.stringify(c1.map((r) => [r.widget, r.family, r.target, r.elements]))})`);
const C2 = { e1: 'animated-headline', e2: 'code-highlight', e3: 'table-of-contents', e4: 'share-buttons', e5: 'call-to-action', e6: 'flip-box', e7: 'price-list', e8: 'price-table', e9: 'gallery', e10: 'hotspot', e11: 'lottie', e12: 'video-playlist', e13: 'countdown', e14: 'progress-tracker' };
const c2 = Object.values(C2).map((w) => (inv.widgets || []).find((x) => x.widget === w) || { widget: w });
ok(c2.every((r) => r.family === 'C2' && r.elements === 1 && r.documents === 1 && r.target === `digitalisimo-${r.widget}`), `inventario: los catorce widgets de contenido de PRO, familia C2, cada uno con su widget propio de destino (${JSON.stringify(c2.filter((r) => !(r.family === 'C2' && r.elements === 1 && r.target === `digitalisimo-${r.widget}`)).map((r) => [r.widget, r.family, r.target, r.elements]))})`);
const runs = by('run');
const last = runs[runs.length - 1] || {};
ok(runs.some((r) => r.state && r.state.done), `Migrar termina (${runs.map((r) => JSON.stringify(r.state)).join(' | ').slice(0, 300)})`);
const t = last.types || {};
ok(t.page && t.page.q1 && t.page.q1.startsWith('digitalisimo-blockquote') && t.page.q2 === 'digitalisimo-blockquote' && t.page.h1 === 'heading', `la página pasa a la Cita, también la anidada, y el encabezado no cambia (${JSON.stringify(t.page)})`);
ok(t.page && /digi-blockquote__content/.test(t.page.q1) && !/elementor-blockquote/.test(t.page.q1), 'el CSS personalizado del elemento usa las clases de la Cita');
ok(t.header && t.header.q9 === 'digitalisimo-blockquote', 'la plantilla de cabecera también se convierte');
ok(t.revision && t.revision.q1 && t.revision.q1.startsWith('blockquote') && t.revision.q2 === 'blockquote', 'la revisión se queda con el Blockquote de PRO');
ok(last.backup === true, 'queda la copia original de la página');
const first = runs.find((r) => r.state && r.state.counts && r.state.counts.pro) || {};
ok(first.state && first.state.counts.pro === 4 && first.state.counts.pro_elements === 23 && /digitalisimo-blockquote/.test(first.report || '') && !/No había nada que migrar/.test(first.report || ''), `la primera pasada convierte 23 widgets en 4 documentos (8 de C0 y C1, los 14 de C2 y la cuenta regresiva que F8 trae de Element Pack) y lo informa (${(first.report || '').replace(/\s+/g, ' ').slice(0, 300)})`);
ok(runs.length < 2 || /No había nada que migrar/.test(last.report || '') || (last.state && last.state.counts && !last.state.counts.pro), 'una segunda pasada no vuelve a convertir nada');
const own = { n1: 'digitalisimo-nested-carousel', s1: 'digitalisimo-slides', m1: 'digitalisimo-media-carousel', t1: 'digitalisimo-testimonial-carousel', r1: 'digitalisimo-reviews' };
ok(t.carousels && Object.entries(own).every(([id, type]) => (t.carousels[id] || '').startsWith(type)) && t.carousels.n1ah === 'heading' && t.carousels.n1bh === 'heading', `C1: los cinco carruseles pasan a los propios y el contenido de las diapositivas anidadas no cambia (${JSON.stringify(t.carousels)})`);
ok(JSON.stringify(last.nested_children) === JSON.stringify(['n1a:container', 'n1b:container']), `C1: el Carrusel anidado conserva sus contenedores hijos con su id (${JSON.stringify(last.nested_children)})`);
ok(t.carousels && /digi-crs__slide/.test(t.carousels.n1 || '') && !/swiper-slide/.test(t.carousels.n1 || ''), 'C1: el CSS personalizado del carrusel anidado usa las clases del motor propio');
ok(t.content && Object.entries(C2).every(([id, w]) => (t.content[id] || '').split(' ')[0] === `digitalisimo-${w}`), `C2: los catorce widgets de contenido pasan a los propios (${JSON.stringify(t.content)})`);
ok(t.content && (t.content.ep1 || '').split(' ')[0] === 'digitalisimo-countdown', `C2: la cuenta regresiva de Element Pack llega a la propia en la misma pasada (F8 → PRO → propia: ${t.content && t.content.ep1})`);
ok(t.content && /digi-flip-box__front/.test(t.content.e6 || '') && !/elementor-flip-box/.test(t.content.e6 || '') && /digi-price-table__heading/.test(t.content.e8 || ''), 'C2: el CSS personalizado de la tarjeta giratoria y de la tabla de precios usa las clases propias');
const rv = (by('revert')[0] || {}).types || {};
ok(rv.content && Object.entries(C2).every(([id, w]) => (rv.content[id] || '').split(' ')[0] === w), `C2: --revert devuelve los catorce widgets de PRO (${JSON.stringify(rv.content)})`);
ok(rv.content && (rv.content.ep1 || '').split(' ')[0] === 'bdt-countdown', `C2: --revert devuelve la cuenta regresiva de Element Pack (${rv.content && rv.content.ep1})`);
ok(rv.carousels && Object.entries({ n1: 'nested-carousel', s1: 'slides', m1: 'media-carousel', t1: 'testimonial-carousel', r1: 'reviews' }).every(([id, type]) => (rv.carousels[id] || '').split(' ')[0] === type), `C1: --revert devuelve los carruseles de PRO (${JSON.stringify(rv.carousels)})`);
ok(rv.page && rv.page.q1 && rv.page.q1.startsWith('blockquote') && rv.page.q2 === 'blockquote' && rv.header && rv.header.q9 === 'blockquote', `--revert devuelve el Blockquote de PRO (${JSON.stringify(rv.page)})`);
fs.writeFileSync(require('path').join(require('path').dirname(process.argv[2]), '..', 'pro-migrate.json'), JSON.stringify({ steps, problems }, null, 1));
if (problems.length) { console.log(`${problems.length} problemas`); process.exit(1); }
console.log('pro-migrate: en verde');
JS
