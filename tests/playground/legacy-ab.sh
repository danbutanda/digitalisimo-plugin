#!/usr/bin/env bash
# A/B de adaptadores heredados: Element Pack activo frente a sólo DIGITALÍSIMO Elements.
# Requiere la copia local de referencia en digitalisimo-elements/bdthemes-element-pack/ (no se versiona).
# Uso: bash tests/playground/legacy-ab.sh [bdt-accordion,bdt-otro]   → resultados en $OUT (por defecto /tmp/digi-legacy-ab)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${OUT:-/tmp/digi-legacy-ab}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ONLY="${1:-}"
mkdir -p "$OUT"
rm -f "$OUT"/phase*.json
BLUEPRINT="$OUT/blueprint.json"
# EXTRA_PLUGINS=bbpress,otro instala plugins reales de wordpress.org y los activa en la red.
EXTRA_STEPS=""
for slug in ${EXTRA_PLUGINS//,/ }; do
  EXTRA_STEPS="$EXTRA_STEPS{\"step\":\"installPlugin\",\"pluginData\":{\"resource\":\"wordpress.org/plugins\",\"slug\":\"$slug\"},\"options\":{\"activate\":false}},"
done
cat > "$BLUEPRINT" <<JSON
{"steps":[{"step":"enableMultisite"},
{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":false}},
$EXTRA_STEPS
{"step":"runPHP","code":"<?php putenv('DIGI_EXTRA=${EXTRA_PLUGINS:-}'); require '/wordpress/wp-content/digi-tests/legacy-setup.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/legacy-content.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=A'); putenv('DIGI_ONLY=$ONLY'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/legacy-deactivate.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=B'); putenv('DIGI_ONLY=$ONLY'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=C'); putenv('DIGI_ONLY=$ONLY'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=D'); putenv('DIGI_ONLY=$ONLY'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"}]}
JSON
# FAKE_PLUGINS=1 monta plugins de prueba en las rutas que Element Pack exige para sus widgets de integración.
FAKE_MOUNTS=()
if [ "${FAKE_PLUGINS:-}" = "1" ]; then
  for dir in "$ROOT"/tests/playground/fake-plugins/*/; do
    FAKE_MOUNTS+=("--mount=${dir%/}:/wordpress/wp-content/plugins/$(basename "$dir")")
  done
fi
$CLI run-blueprint --site-url=http://playground.test --blueprint="$BLUEPRINT" "${FAKE_MOUNTS[@]}" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-elements/bdthemes-element-pack:/wordpress/wp-content/plugins/bdthemes-element-pack" \
  --mount="$ROOT/tests/playground:/wordpress/wp-content/digi-tests" \
  --mount="$ROOT/tests/legacy:/wordpress/wp-content/digi-legacy-fixtures" \
  --mount="$OUT:/wordpress/wp-content/digi-ab" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; exit 1; }
node "$ROOT/tests/playground/legacy-compare.mjs" "$OUT/phaseA.json" "$OUT/phaseB.json" "$OUT/phaseC.json" "$OUT/phaseD.json"
