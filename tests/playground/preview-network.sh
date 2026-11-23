#!/usr/bin/env bash
# Enlace firmado de vista previa del MCP de DIGITALÍSIMO Elements en una red real (Playground multisitio, revisión del
# 2026-11-22): los sitios /sub/ y /otro/ tienen la misma página y la misma revisión (como un clon). Cada sitio emite su
# enlace con la herramienta real `elementor/create-preview-link` (Abilities API) y cada enlace se abre en una petición
# del front: en su sitio se ve la vista previa (el núcleo reescribe la consulta y `Preview_Link` la atiende) y en el otro
# no (el candado lo quita antes del núcleo). Un proceso PHP por paso.
# Uso: bash tests/playground/preview-network.sh   → $OUT/result.json (por defecto /tmp/digi-preview-network)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-preview-network}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
rm -rf "$OUT" && mkdir -p "$OUT"
cp "$ROOT/tests/playground/preview-network/probe.php" "$OUT/"
json='{"steps":[{"step":"enableMultisite"}'
for step in setup emit:2 emit:3 open:2:2 open:3:2 open:3:3 open:2:3; do
  json="$json,{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=$step'); require '/wordpress/wp-content/probe/probe.php';\"}"
done
echo "$json]}" > "$OUT/blueprint.json"
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/playground.log" 2>&1 || { tail -30 "$OUT/playground.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
status=0
node -e '
const r = require(process.argv[1]); delete r.state; let failed = 0;
for (const [k, v] of Object.entries(r)) { if (v.length) { failed++; console.log(`FALLA  ${k}: ${v.join(" | ")}`); } }
const n = Object.keys(r).length;
console.log(failed ? `\n${failed} de ${n} pasos fallan.` : `\nVista previa del MCP en una red real: ${n} pasos en verde.`);
process.exit(failed || n < 7 ? 1 : 0);
' "$OUT/result.json" || status=$?
if [ -s "$OUT/fatal.log" ]; then echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; fi
exit $status
