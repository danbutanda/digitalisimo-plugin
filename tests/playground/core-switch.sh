#!/usr/bin/env bash
# Cambio de núcleo en una red Multisite: crea páginas con widgets de Elementor, Pro y DIGITALÍSIMO bajo el plugin
# Elementor, borra Elementor (y con él sus traducciones) y comprueba que el núcleo incluido en DIGITALÍSIMO Elements
# las pinta igual (texto, enlaces, imágenes y CSS), en español, con un kit por sitio y aceptando los complementos
# que declaran «Requires Plugins: elementor».
# Uso: bash tests/playground/core-switch.sh   → resultados en $OUT (por defecto /tmp/digi-core-switch)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
OUT="${OUT:-/tmp/digi-core-switch}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
mkdir -p "$OUT" && rm -f "$OUT"/*.json
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[{"step":"enableMultisite"},
{"step":"installPlugin","pluginData":{"resource":"wordpress.org/plugins","slug":"elementor"},"options":{"activate":false}},
{"step":"setSiteLanguage","language":"es_MX"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/core-switch/setup.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CORE_PHASE=elementor'); putenv('DIGI_CORE_BLOG=1'); require '/wordpress/wp-content/digi-tests/core-switch/phase.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CORE_PHASE=elementor'); putenv('DIGI_CORE_BLOG=2'); require '/wordpress/wp-content/digi-tests/core-switch/phase.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/core-switch/remove-elementor.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CORE_PHASE=bundled'); putenv('DIGI_CORE_BLOG=1'); require '/wordpress/wp-content/digi-tests/core-switch/phase.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CORE_PHASE=bundled'); putenv('DIGI_CORE_BLOG=2'); require '/wordpress/wp-content/digi-tests/core-switch/phase.php';"}]}
JSON
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/tests/playground/core-switch/digi-fake-addon:/wordpress/wp-content/plugins/digi-fake-addon" \
  --mount="$ROOT/tests/playground:/wordpress/wp-content/digi-tests" \
  --mount="$OUT:/wordpress/wp-content/digi-core" > "$OUT/playground.log" 2>&1 || { tail -30 "$OUT/playground.log"; exit 1; }
node "$ROOT/tests/playground/core-switch/compare.mjs" "$OUT"
