#!/usr/bin/env bash
# Condiciones de visualización en páginas reales (Playground, núcleo incluido): lo que debe ocultarse no está en el
# HTML y lo que debe verse sí, con los tres motores (nativo, propio y heredado), en widgets y contenedores con hijos,
# como visitante y con sesión, y con la caché de elementos de Elementor desactivada y activada; además, en un proceso
# limpio por página, la página condicionada pide quedar fuera de la caché de página antes de la salida y la que no
# tiene condiciones, no.
# Uso: bash tests/playground/conditions.sh   → resultados en $OUT (por defecto /tmp/digi-conditions)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-conditions}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
mkdir -p "$OUT" && rm -f "$OUT/result.json" "$OUT/cache-step"
cp "$ROOT/tests/playground/conditions/probe.php" "$OUT/probe.php"
cp "$ROOT/tests/playground/conditions/probe-save.php" "$OUT/probe-save.php"
cp "$ROOT/tests/playground/conditions/probe-cache.php" "$OUT/probe-cache.php"
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'digitalisimo-elements/pro-elements.php' ); activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php' ); update_option( 'elementor_onboarded', true );"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe-save.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe-cache.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe-cache.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe-cache.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe-cache.php';"}]}
JSON
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; exit 1; }
node -e '
const r = require(process.argv[1]);
let failed = 0, total = 0;
for (const s of ["sin-condiciones", "propio-widget-disable", "nativo-widget-disable", "heredado-widget-disable"]) {
  if (!r["no-cache"] || !(s in r["no-cache"])) { total++; failed++; console.log(`FALLA  caché de página · ${s}: sin resultado (la petición limpia no se ejecutó)`); }
}
for (const [cache, cases] of Object.entries(r)) {
  for (const [name, runs] of Object.entries(cases)) {
    if (cache === "no-cache" || cache === "tras-guardar") { total++; if (runs.length) { failed++; console.log(`FALLA  caché de página · ${name}: ${runs.join(", ")}`); } continue; }
    for (const [who, problems] of Object.entries(runs)) {
      total++;
      if (problems.length) { failed++; console.log(`FALLA  ${cache} · ${name} · ${who}: ${problems.join(", ")}`); }
    }
  }
}
console.log(failed ? `\n${failed} de ${total} comprobaciones fallan.` : `\nCondiciones de visualización: ${total} comprobaciones correctas.`);
process.exit(failed ? 1 : 0);
' "$OUT/result.json"
