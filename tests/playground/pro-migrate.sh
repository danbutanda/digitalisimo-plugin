#!/usr/bin/env bash
# Migración de los widgets de PRO de punta a punta (C0 de docs/PRO_WIDGETS_OBJECTIVE.md), en Playground con el núcleo
# incluido en DIGITALÍSIMO Elements y DIGITALÍSIMO Migración: un sitio con Blockquote de PRO en una página (uno anidado),
# en una plantilla de cabecera y en una revisión; el inventario de «Analizar», «Migrar» hasta terminar y --revert.
# Comprueba que la página y la cabecera pasan a la Cita (con el CSS personalizado traducido), que la revisión no se
# toca, que queda la copia original y que --revert devuelve el Blockquote de PRO.
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
ok(row.family === 'C2' && row.target === 'digitalisimo-blockquote' && row.elements === 3 && row.documents === 2 && row.revisions === 1 && inv.where && inv.where.page === 1 && inv.where.header === 1, `inventario: 3 Blockquote en 2 documentos (página y cabecera), familia C2, destino la Cita y la revisión aparte (${JSON.stringify(row).slice(0, 200)})`);
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
ok(first.state && first.state.counts.pro === 2 && first.state.counts.pro_elements === 3 && /digitalisimo-blockquote/.test(first.report || '') && !/No había nada que migrar/.test(first.report || ''), `la primera pasada convierte 3 widgets en 2 documentos y lo informa (${(first.report || '').replace(/\s+/g, ' ').slice(0, 300)})`);
ok(runs.length < 2 || /No había nada que migrar/.test(last.report || '') || (last.state && last.state.counts && !last.state.counts.pro), 'una segunda pasada no vuelve a convertir nada');
const rv = (by('revert')[0] || {}).types || {};
ok(rv.page && rv.page.q1 && rv.page.q1.startsWith('blockquote') && rv.page.q2 === 'blockquote' && rv.header && rv.header.q9 === 'blockquote', `--revert devuelve el Blockquote de PRO (${JSON.stringify(rv.page)})`);
fs.writeFileSync(require('path').join(require('path').dirname(process.argv[2]), '..', 'pro-migrate.json'), JSON.stringify({ steps, problems }, null, 1));
if (problems.length) { console.log(`${problems.length} problemas`); process.exit(1); }
console.log('pro-migrate: en verde');
JS
