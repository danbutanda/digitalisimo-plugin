#!/usr/bin/env bash
# Informe de Element Pack en la red y en el sitio (Playground Multisite). Uso: bash tests/playground/migration-report.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${OUT:-/tmp/digi-migration-report}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
mkdir -p "$OUT" && rm -f "$OUT/result.txt"
cp "$ROOT/tests/playground/migration-report/probe.php" "$OUT/probe.php"
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[{"step":"enableMultisite"},{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":true}},{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe.php';"}]}
JSON
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; exit 1; }
for expected in 'bdt-weather (1)' 'Sin adaptador: no se mostrará sin Element Pack. Clima' 'Compatible: se muestra con digitalisimo-accordion' 'http://playground.test/sub/wp-admin/tools.php?page=digitalisimo-ep-migration'; do
  grep -qF "$expected" "$OUT/result.txt" || { echo "Falta en el informe: $expected"; cat "$OUT/result.txt"; exit 1; }
done
echo 'Informe de migración de red y de sitio validado.'
