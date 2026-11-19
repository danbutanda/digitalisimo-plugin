#!/usr/bin/env bash
# Ajustes de los widgets de PRO que no son suyos y recuperación de lo que no pasó (caso real del 2026-11-18), en
# Playground con el núcleo incluido en DIGITALÍSIMO Elements: primero con la versión PUBLICADA de Elements y de Migración
# (ZIP de dist/ o rollback/, montados como `-publicado`) y después con la del repositorio, en el mismo sitio.
# Una página con los widgets y las claves del informe real (sintéticos): Cita con `content_width`, condiciones apagadas de
# otro plugin y los valores por defecto de las extensiones de Element Pack; otra Cita oculta por «otro plugin» de
# condiciones (un mu-plugin que no pinta el widget con `display_condition_enable` encendido); Lista de precios con precio
# anterior y distintivo en sus filas; Formulario con `dynamic` en un campo. Y una página con el carrito de WooCommerce de
# PRO sin WooCommerce.
# 1. Con la versión publicada: lo que se pinta antes, Migrar y lo que se pinta después (la cita oculta se ve: el fallo).
# 2. Con la del repositorio (y una página nueva sin migrar): Migrar otra vez recupera desde la copia lo que no pasó y
#    devuelve el widget de PRO con su condición encendida, que vuelve a ocultarse; la página nueva se convierte sin
#    perder nada y su cita con la condición encendida se queda como widget de PRO, oculta. El informe no tiene ningún
#    «ajuste que la conversión no reconoce» y, sin WooCommerce, no pide actualizar Elements.
# Uso: bash tests/playground/pro-settings.sh   (OUT, TMPDIR; OLD_ELEMENTS_ZIP y OLD_MIGRATION_ZIP eligen la versión
#      anterior, por defecto Elements 4.3.0.187 y Migración 1.0.28). Se corre en la PC con pc-run.sh.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/var/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/var/tmp/digi-pro-settings}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
pick() { for d in dist rollback; do [ -f "$ROOT/$d/$1" ] && { echo "$ROOT/$d/$1"; return 0; }; done; return 1; }
OLD_ELEMENTS_ZIP="${OLD_ELEMENTS_ZIP:-$(pick digitalisimo-elements-4.3.0.187.zip || true)}"
OLD_MIGRATION_ZIP="${OLD_MIGRATION_ZIP:-$(pick digitalisimo-migration-1.0.28.zip || true)}"
[ -f "$OLD_ELEMENTS_ZIP" ] && [ -f "$OLD_MIGRATION_ZIP" ] || { echo "Faltan los ZIP publicados (OLD_ELEMENTS_ZIP, OLD_MIGRATION_ZIP)" >&2; exit 1; }
rm -rf "$OUT" && mkdir -p "$OUT/probe" "$OUT/old"
cp "$ROOT/tests/playground/pro-settings/"*.php "$OUT/probe/"
unzip -q "$OLD_ELEMENTS_ZIP" -d "$OUT/old/e" && unzip -q "$OLD_MIGRATION_ZIP" -d "$OUT/old/m"
STEPS='{"step":"runPHP","code":"<?php require \"/wordpress/wp-content/probe/setup.php\";"}'
for s in render run render switch run render; do
  STEPS="$STEPS,{\"step\":\"writeFile\",\"path\":\"/wordpress/wp-content/probe/step.txt\",\"data\":\"$s\"},{\"step\":\"runPHP\",\"code\":\"<?php require \\\"/wordpress/wp-content/probe/check.php\\\";\"}"
done
printf '{"steps":[%s]}\n' "$STEPS" > "$OUT/blueprint.json"
trap 'playground_cleanup' EXIT
$CLI run-blueprint --blueprint="$OUT/blueprint.json" \
  --mount="$OUT/old/e/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements-publicado" \
  --mount="$OUT/old/m/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration-publicado" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$OUT/probe:/wordpress/wp-content/probe" > "$OUT/cli.log" 2>&1 || { tail -20 "$OUT/cli.log"; exit 1; }
node - "$OUT/probe/steps.jsonl" <<'JS'
const fs = require('fs');
const steps = fs.readFileSync(process.argv[2], 'utf8').trim().split('\n').map((l) => JSON.parse(l));
const problems = [];
const ok = (c, m) => { console.log(`${c ? 'OK   ' : 'FALLA'}  ${m}`); if (!c) problems.push(m); };
for (const s of steps) if (s.error) problems.push(`${s.step}: ${s.error}`);
const renders = steps.filter((s) => s.step === 'render');
const runs = steps.filter((s) => s.step === 'run');
const [before, oldAfter, newAfter] = renders;
const [oldRun, newRun] = runs;
const painted = (r, k) => ((r || {}).painted || {})[k] || {};
const saved = (r, k, id) => (((r || {}).saved || {})[k] || {})[id] || {};
ok(steps.length === 7 && before && oldAfter && newAfter && oldRun && newRun, `los siete pasos (${steps.map((s) => s.step).join(', ')})`);
ok(String((before || {}).active).includes('publicado') && String((newAfter || {}).active).includes('digitalisimo-migration/digitalisimo-migration.php') && !String((newAfter || {}).active).includes('publicado'), 'primero la versión publicada; después, la del repositorio');
// 1. Versión publicada.
ok(painted(before, 'pagina').q1 && !painted(before, 'pagina').q2 && painted(before, 'pagina').l1 && painted(before, 'pagina').f1, `antes de migrar: la cita oculta por el otro plugin no se pinta y el resto sí (${JSON.stringify(painted(before, 'pagina'))})`);
ok(oldRun && oldRun.state && oldRun.state.done, 'la versión publicada termina Migrar');
const oldQ1 = saved(oldAfter, 'pagina', 'q1');
ok(oldQ1.type === 'digitalisimo-blockquote' && !('content_width' in (oldQ1.settings || {})) && !('display_condition_list' in (oldQ1.settings || {})), `la versión publicada pierde content_width y las condiciones apagadas (${JSON.stringify(Object.keys(oldQ1.settings || {}))})`);
ok(painted(oldAfter, 'pagina').q2, `FALLO REPRODUCIDO: con la versión publicada la cita oculta se ve tras migrar (${JSON.stringify(painted(oldAfter, 'pagina'))})`);
ok((oldRun.codes || []).some((c) => c.includes(':pro.unmapped:')) && /Actualiza DIGITALÍSIMO Elements/.test(oldRun.report || ''), 'la versión publicada informa «no reconoce» y pide actualizar Elements por el carrito');
// 2. Versión del repositorio.
ok(newRun && newRun.state && newRun.state.done, 'la versión del repositorio termina Migrar');
const q1 = saved(newAfter, 'pagina', 'q1');
const q2 = saved(newAfter, 'pagina', 'q2');
const l1 = saved(newAfter, 'pagina', 'l1');
const f1 = saved(newAfter, 'pagina', 'f1');
ok(q1.type === 'digitalisimo-blockquote' && q1.settings.content_width === 'boxed' && Array.isArray(q1.settings.display_condition_list), `recupera de la copia content_width y las condiciones apagadas (${JSON.stringify(q1.settings || {}).slice(0, 300)})`);
ok(q2.type === 'blockquote' && q2.settings.display_condition_enable === 'yes', `devuelve el widget de PRO con su condición encendida (${JSON.stringify(q2).slice(0, 200)})`);
ok(l1.type === 'digitalisimo-price-list' && l1.settings.price_list[0].old_price === '$12' && l1.settings.price_list[0].item_badge === 'Oferta', 'recupera el precio anterior y el distintivo en su fila');
ok(f1.type === 'digitalisimo-form' && JSON.stringify(f1.settings.form_fields[0].dynamic) === '{"active":"yes"}', 'recupera «dynamic» en el campo del formulario');
ok(painted(newAfter, 'pagina').q1 === 'digitalisimo-blockquote.default' && !painted(newAfter, 'pagina').q2 && painted(newAfter, 'pagina').l1 && painted(newAfter, 'pagina').f1, `se pinta la cita propia, el resto, y la oculta vuelve a ocultarse (${JSON.stringify(painted(newAfter, 'pagina'))})`);
ok(/Cita visible/.test(((newAfter || {}).text || {}).pagina || '') && !/Cita oculta/.test(((newAfter || {}).text || {}).pagina || '') && /Plan básico/.test(((newAfter || {}).text || {}).pagina || ''), 'el texto pintado: la cita visible y la lista, sin la oculta');
const n1 = saved(newAfter, 'nueva', 'n1');
const n2 = saved(newAfter, 'nueva', 'n2');
ok(n1.type === 'digitalisimo-blockquote' && n1.settings.content_width === 'boxed' && Array.isArray(n1.settings.display_condition_list) && n2.type === 'blockquote', `la página nueva: convierte sin perder nada y deja como widget de PRO la de la condición encendida (${JSON.stringify([n1.type, n2.type])})`);
ok(painted(newAfter, 'nueva').n1 && !painted(newAfter, 'nueva').n2, `la página nueva se pinta sin la oculta (${JSON.stringify(painted(newAfter, 'nueva'))})`);
const codes = newRun.codes || [];
ok(!codes.some((c) => c.includes(':pro.unmapped:')), `ningún «ajuste que la conversión no reconoce» (${codes.filter((c) => c.includes('unmapped')).join(', ')})`);
ok(codes.some((c) => c.includes(':pro.recovered:blockquote:content_width')) && codes.some((c) => c.includes(':pro.condition_restored:blockquote:')) && codes.some((c) => c.includes(':pro.condition_kept:blockquote:')), 'el informe dice qué se recuperó, qué volvió a ser de PRO y qué se queda');
ok(codes.some((c) => c.includes(':pro.target_dependency:woocommerce-cart:WooCommerce')) && !/Actualiza DIGITALÍSIMO Elements/.test(newRun.report || '') && /WooCommerce/.test(newRun.report || ''), 'sin WooCommerce: lo dice así y no pide actualizar Elements');
if (problems.length) { console.log(`\nFALLO pro-settings: ${problems.length}\n- ${problems.join('\n- ')}`); process.exit(1); }
console.log('\npro-settings: todo en verde');
JS
