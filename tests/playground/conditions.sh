#!/usr/bin/env bash
# Condiciones de visualización en páginas reales (Playground, núcleo incluido): lo que debe ocultarse no está en el
# HTML y lo que debe verse sí, con los tres motores (nativo, propio y heredado), en widgets y contenedores con hijos,
# como visitante y con sesión, y con la caché de elementos de Elementor desactivada y activada; además, en un proceso
# limpio por página, la página con contenido por visitante pide quedar fuera de la caché de página antes de la salida
# y la que no tiene condiciones, o sólo de sesión, no.
# Uso: bash tests/playground/conditions.sh   → resultados en $OUT (por defecto /tmp/digi-conditions)
#   WOO=0|1   WooCommerce instalado y activo (las condiciones de existencias de F7a con productos reales); sin él, no
#             se cumplen nunca
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-conditions}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
WOO="${WOO:-0}"
case "$WOO" in 0|1) ;; *) echo "WOO debe ser 0 o 1"; exit 1;; esac
mkdir -p "$OUT" && rm -f "$OUT/result.json" "$OUT/cache-step"
cp "$ROOT/tests/playground/conditions/probe.php" "$OUT/probe.php"
cp "$ROOT/tests/playground/conditions/probe-save.php" "$OUT/probe-save.php"
cp "$ROOT/tests/playground/conditions/probe-cache.php" "$OUT/probe-cache.php"
# Un paso limpio de probe-cache.php por página de su lista ($order): 7 de los motores y 7 de F7a.
CACHE_STEPS=14
WOO_STEP=''
[ "$WOO" = 1 ] && WOO_STEP='{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"woocommerce"},"options":{"activate":true}},'
{
  printf '{"steps":[%s\n' "$WOO_STEP"
  printf '%s\n' '{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-load.php'"'"'; require_once ABSPATH . '"'"'wp-admin/includes/plugin.php'"'"'; activate_plugin( '"'"'digitalisimo-elements/pro-elements.php'"'"' ); activate_plugin( '"'"'digitalisimo-migration/digitalisimo-migration.php'"'"' ); update_option( '"'"'elementor_onboarded'"'"', true );"},'
  printf '%s\n' '{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-content/probe/probe.php'"'"';"},'
  printf '%s' '{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-content/probe/probe-save.php'"'"';"}'
  for _ in $(seq "$CACHE_STEPS"); do printf ',\n%s' '{"step":"runPHP","code":"<?php require '"'"'/wordpress/wp-content/probe/probe-cache.php'"'"';"}'; done
  printf ']}\n'
} > "$OUT/blueprint.json"
node -e 'JSON.parse(require("fs").readFileSync(process.argv[1], "utf8"))' "$OUT/blueprint.json" || { echo "blueprint.json no es JSON válido"; exit 1; }
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; exit 1; }
node -e '
const r = require(process.argv[1]);
const woo = process.argv[2] === "1";
let failed = 0, total = 0;
// F7a: WooCommerce cargado sólo con WOO=1 (si no, las existencias no se probarían con productos reales).
total++;
if (!r._f7a || r._f7a.woo !== woo) { failed++; console.log(`FALLA  F7a: WooCommerce ${r._f7a ? (r._f7a.woo ? "cargado" : "ausente") : "sin datos"} con WOO=${woo ? 1 : 0}`); }
delete r._f7a;
for (const s of ["sin-condiciones", "propio-widget-disable", "nativo-widget-disable", "heredado-widget-disable", "visitante-propio", "visitante-nativo", "visitante-heredado", "f7a-cookie", "f7a-visita", "f7a-existencias", "f7a-campo", "f7a-usuario", "f7a-autor", "f7a-busqueda"]) {
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
' "$OUT/result.json" "$WOO"
