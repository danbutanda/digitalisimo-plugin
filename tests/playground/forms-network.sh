#!/usr/bin/env bash
# Formularios de Tools («Ajustes del sitio») y de Elements (clave de Google Reviews) en un WordPress real (Playground):
# una red con los sitios 1 y 2 (/sub/) y, aparte, un sitio individual. Cada paso es un proceso PHP propio: «screen»
# pinta la pantalla real (red o sitio) y guarda lo que enviaría un navegador (tests/lib/browser-submit.php); «post» lo
# entrega a admin-post.php del sitio que pintó el formulario (redirección capturada); el siguiente «screen» comprueba
# lo guardado al recargar. Paridad sitio/red (2026-11-22): guardar, recargar y ver que quedó, sin mezclar sitios.
# Uso: bash tests/playground/forms-network.sh   → $OUT/result.json (por defecto /tmp/digi-forms-network)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-forms-network}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
rm -rf "$OUT" && mkdir -p "$OUT"
cp "$ROOT/tests/playground/forms-network/probe.php" "$ROOT/tests/lib/browser-submit.php" "$OUT/"

run_blueprint() { # $1 archivo, $2 multisite (1/0), resto: pasos
  local file="$1" multi="$2"; shift 2
  local json='{"steps":[' first=1 step
  [ "$multi" = 1 ] && json="$json"'{"step":"enableMultisite"},'
  for step in "$@"; do
    [ $first = 1 ] || json="$json,"
    first=0
    json="$json{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); require '/wordpress/wp-content/probe/probe.php';\"}"
  done
  echo "$json]}" > "$OUT/$file"
  $CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/$file" \
    --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" \
    --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
    --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/${file%.json}.log" 2>&1 || { tail -30 "$OUT/${file%.json}.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
}

NET=(setup
  screen:tools:net:1 post screen:tools:site:2 post screen:tools:site:2 post screen:tools:site:2 check:tools-net
  screen:google:net:1 post screen:google:site:2 post screen:google:site:2 check:google-net)
run_blueprint blueprint-network.json 1 "${NET[@]}"
run_blueprint blueprint-single.json 0 setup screen:tools:site:1 post screen:tools:site:1 screen:google:site:1 post screen:google:site:1 check:single
status=0
node -e '
const r = require(process.argv[1]); let failed = 0;
delete r.pending;
for (const [k, v] of Object.entries(r)) { if (v.length) { failed++; console.log(`FALLA  ${k}: ${v.join(" | ")}`); } }
const n = Object.keys(r).length;
console.log(failed ? `\n${failed} de ${n} pasos fallan.` : `\nFormularios en una red real y en un sitio individual: ${n} pasos en verde.`);
process.exit(failed || n < 20 ? 1 : 0);
' "$OUT/result.json" || status=$?
if [ -s "$OUT/fatal.log" ]; then echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; fi
exit $status
