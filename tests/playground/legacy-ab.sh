#!/usr/bin/env bash
# A/B de adaptadores heredados: Element Pack activo frente a sólo DIGITALÍSIMO Elements.
# Requiere la copia local de referencia en reference/bdthemes-element-pack/ (no se versiona).
# Uso: bash tests/playground/legacy-ab.sh [bdt-accordion,bdt-otro]   → resultados en $OUT (por defecto /tmp/digi-legacy-ab)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${OUT:-/tmp/digi-legacy-ab}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ONLY="${1:-}"
mkdir -p "$OUT"
rm -f "$OUT"/phase*.json "$OUT/setup-error.txt"
BLUEPRINT="$OUT/blueprint.json"
# EXTRA_PLUGINS=bbpress,otro instala plugins reales de wordpress.org y los activa en la red. BLOG=1|2 limita las fases a un sitio.
# CORE=bundled no instala el plugin Elementor: todo (Element Pack incluido en la fase A) corre sobre el núcleo de Elementor
# incluido en DIGITALÍSIMO Elements, el escenario final de un sitio que ya retiró Elementor.
ELEMENTOR_STEP='{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":false}},'
[ "${CORE:-}" = "bundled" ] && ELEMENTOR_STEP=''
EXTRA_STEPS=""
EXTRA="${EXTRA_PLUGINS:-}"
for slug in ${EXTRA//,/ }; do
  EXTRA_STEPS="$EXTRA_STEPS{\"step\":\"installPlugin\",\"pluginData\":{\"resource\":\"wordpress.org/plugins\",\"slug\":\"$slug\"},\"options\":{\"activate\":false}},"
done
cat > "$BLUEPRINT" <<JSON
{"steps":[{"step":"enableMultisite"},
$ELEMENTOR_STEP
$EXTRA_STEPS
{"step":"runPHP","code":"<?php putenv('DIGI_EXTRA=${EXTRA_PLUGINS:-}'); require '/wordpress/wp-content/digi-tests/legacy-setup.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/legacy-content.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=A'); putenv('DIGI_ONLY=$ONLY'); putenv('DIGI_BLOG=${BLOG:-}'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/legacy-deactivate.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=B'); putenv('DIGI_ONLY=$ONLY'); putenv('DIGI_BLOG=${BLOG:-}'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=C'); putenv('DIGI_ONLY=$ONLY'); putenv('DIGI_BLOG=${BLOG:-}'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; deactivate_plugins( 'digitalisimo-migration/digitalisimo-migration.php', false, true );"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=E'); putenv('DIGI_ONLY=$ONLY'); putenv('DIGI_BLOG=${BLOG:-}'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php', '', true );"},
{"step":"runPHP","code":"<?php putenv('DIGI_PHASE=D'); putenv('DIGI_ONLY=$ONLY'); putenv('DIGI_BLOG=${BLOG:-}'); require '/wordpress/wp-content/digi-tests/legacy-ab.php';"}]}
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
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$ROOT/reference/bdthemes-element-pack:/wordpress/wp-content/plugins/bdthemes-element-pack" \
  --mount="$ROOT/tests/playground:/wordpress/wp-content/digi-tests" \
  --mount="$ROOT/tests/legacy:/wordpress/wp-content/digi-legacy-fixtures" \
  --mount="$OUT:/wordpress/wp-content/digi-ab" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; exit 1; }
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
node "$ROOT/tests/playground/legacy-compare.mjs" "$OUT/phaseA.json" "$OUT/phaseB.json" "$OUT/phaseC.json" "$OUT/phaseD.json" "$OUT/phaseE.json"
