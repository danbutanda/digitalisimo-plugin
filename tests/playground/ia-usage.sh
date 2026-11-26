#!/usr/bin/env bash
# Registro de uso de IA (docs/IA_USAGE.md) en una red Multisite real de WordPress Playground (run-blueprint, sin
# Chrome): IA Tools activo en la red, sitio principal y sitio 2 en /sitio2/, cada uno con su clave. Cada paso corre en
# un proceso PHP nuevo (ia-usage/probe.php): llamadas de verdad por wp_remote_post() (contenido y Asistente IA con
# caché en el sitio 1, chatbot de un visitante en el sitio 2), y se compara lo registrado con el bloque `usage` que
# devolvió el proveedor y con el costo calculado a mano con los precios publicados; después, el aislamiento entre los
# dos sitios, la red, la Ability digitalisimo-ia/usage (administrador sí, editora no), el CSV, la pausa por
# presupuesto (sin salir la llamada) y que nada guardado contenga la clave ni el texto.
#   MODE=simulado (por defecto): un proveedor simulado con el formato real de Anthropic (no gasta).
#   MODE=real: la API de Anthropic de verdad con claude-haiku-5-5 (centavos). NO va en la CI. KEY_FILE: archivo con la
#     clave; se copia a la sonda, el primer paso la lee y la borra, y el último la quita de las opciones de los sitios.
#     Desde el servidor, la clave viaja a la PC sólo por STDIN (ver tests/playground/ia-assistant-real.sh).
# Uso: bash tests/playground/ia-usage.sh → $OUT/result.json (por defecto /tmp/digi-ia-usage)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-ia-usage}"
MODE="${MODE:-simulado}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
scrub() { rm -f "$OUT/probe/k"; }
trap 'scrub; playground_cleanup' EXIT
rm -rf "$OUT" && mkdir -p "$OUT/probe"
cp "$ROOT/tests/playground/ia-usage/probe.php" "$OUT/probe/"
echo "$MODE" > "$OUT/probe/mode"
if [ "$MODE" = real ]; then
  [ -s "${KEY_FILE:-}" ] || { echo "MODE=real necesita KEY_FILE"; exit 2; }
  ( umask 077; cp "$KEY_FILE" "$OUT/probe/k" )
fi
json='{"steps":[{"step":"enableMultisite"}'
for item in setup:1 llamadas:1 visitante:2 revisar:1 pausa:2 limpiar:1; do
  json="$json,{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=${item%%:*}'); putenv('DIGI_BLOG=${item##*:}'); require '/wordpress/wp-content/probe/probe.php';\"}"
done
echo "$json]}" > "$OUT/blueprint.json"
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-ia-tools:/wordpress/wp-content/plugins/digitalisimo.chatbot" \
  --mount="$OUT/probe:/wordpress/wp-content/probe" > "$OUT/run.log" 2>&1 || { tail -30 "$OUT/run.log"; [ -s "$OUT/probe/fatal.log" ] && cat "$OUT/probe/fatal.log"; exit 1; }
scrub
[ -s "$OUT/probe/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/probe/fatal.log"; exit 1; }
cp "$OUT/probe/result.json" "$OUT/result.json"
[ -f "$OUT/probe/usage.json" ] && cp "$OUT/probe/usage.json" "$OUT/usage.json"
if [ "$MODE" = real ] && [ -s "${KEY_FILE:-}" ] && grep -rqF -f "$KEY_FILE" "$OUT"; then echo "FALLA: la clave aparece en los resultados"; exit 1; fi
node -e 'const r=require(process.argv[1]);let f=0;for(const x of r){console.log((x.ok?"OK   ":"FALLA ")+x.step+": "+x.what);if(!x.ok)f++;}console.log(r.length+" comprobaciones, "+f+" fallas");process.exit(f?1:0)' "$OUT/result.json"
