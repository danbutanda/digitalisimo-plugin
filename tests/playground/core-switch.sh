#!/usr/bin/env bash
# Cambio de núcleo en una red Multisite: crea páginas con widgets de Elementor, Pro y DIGITALÍSIMO bajo el plugin
# Elementor, borra Elementor (y con él sus traducciones) y comprueba que el núcleo incluido en DIGITALÍSIMO Elements
# las pinta igual (texto, enlaces, imágenes y CSS), en español, con un kit por sitio y aceptando los complementos
# que declaran «Requires Plugins: elementor». Con Elementor instalado pero desactivado, la lista de plugins lo sigue
# llamando Elementor (sus cabeceras, con una nota), no «DIGITALÍSIMO Elements», y se puede borrar desde esa petición
# (con el núcleo incluido cargado) sin que WordPress cargue su archivo principal.
# Elementor se instala en la versión fijada ELEMENTOR_PIN (por defecto 4.3.5, la del núcleo incluido) desde
# downloads.wordpress.org. phase.php comprueba además que el valor por defecto de `font-display` de DIGITALÍSIMO Elements
# (`default_option_elementor_font_display`, F-front) no existe con el plugin Elementor y sí con el núcleo incluido tras la
# transición (D3). Se monta una copia fija de digitalisimo-elements/ ($OUT/src; LIVE=1 monta el árbol).
# Uso: bash tests/playground/core-switch.sh   → resultados en $OUT (por defecto /tmp/digi-core-switch)
#      ELEMENTOR_PIN=4.3.4 bash tests/playground/core-switch.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-core-switch}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
ELEMENTOR_PIN="${ELEMENTOR_PIN:-4.3.5}"
mkdir -p "$OUT" && rm -f "$OUT"/*.json
if [ "${LIVE:-0}" = 1 ]; then SRC="$ROOT/digitalisimo-elements"; else SRC="$OUT/src/digitalisimo-elements"; rm -rf "$OUT/src"; mkdir -p "$OUT/src"; cp -a "$ROOT/digitalisimo-elements" "$SRC"; fi
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"enableMultisite"},
{"step":"installPlugin","pluginData":{"resource":"url","url":"https://downloads.wordpress.org/plugin/elementor.$ELEMENTOR_PIN.zip"},"options":{"activate":false}},
{"step":"setSiteLanguage","language":"es_MX"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/core-switch/setup.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CORE_PHASE=elementor'); putenv('DIGI_CORE_BLOG=1'); require '/wordpress/wp-content/digi-tests/core-switch/phase.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CORE_PHASE=elementor'); putenv('DIGI_CORE_BLOG=2'); require '/wordpress/wp-content/digi-tests/core-switch/phase.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/core-switch/deactivate-elementor.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/core-switch/rows.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/digi-tests/core-switch/remove-elementor.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CORE_PHASE=bundled'); putenv('DIGI_CORE_BLOG=1'); require '/wordpress/wp-content/digi-tests/core-switch/phase.php';"},
{"step":"runPHP","code":"<?php putenv('DIGI_CORE_PHASE=bundled'); putenv('DIGI_CORE_BLOG=2'); require '/wordpress/wp-content/digi-tests/core-switch/phase.php';"}]}
JSON
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$SRC:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/tests/playground/core-switch/digi-fake-addon:/wordpress/wp-content/plugins/digi-fake-addon" \
  --mount="$ROOT/tests/playground:/wordpress/wp-content/digi-tests" \
  --mount="$OUT:/wordpress/wp-content/digi-core" > "$OUT/playground.log" 2>&1 || { tail -30 "$OUT/playground.log"; exit 1; }
status=0
node "$ROOT/tests/playground/core-switch/compare.mjs" "$OUT" || status=$?
# F-front (D3): el valor por defecto de font-display de Elements sólo existe con el núcleo incluido, tras la transición.
node -e '
const fs = require("fs");
const [dir, pin] = process.argv.slice(1);
const problems = [];
for (const blog of [1, 2]) {
  const read = (phase) => { try { return JSON.parse(fs.readFileSync(`${dir}/${phase}-${blog}.json`, "utf8")).checks.front || {}; } catch (e) { problems.push(`${phase}-${blog}.json: ${e.message}`); return {}; } };
  const a = read("elementor"), b = read("bundled");
  if (a.elementor_version !== pin) problems.push(`sitio ${blog}: Elementor ${a.elementor_version} en la fase elementor (se fijó ${pin})`);
  if (a.has_filter !== false || a.has_filter_after !== false) problems.push(`sitio ${blog}: con el plugin Elementor no debe haber default_option_elementor_font_display (${JSON.stringify(a)})`);
  if (b.has_filter_after !== true) problems.push(`sitio ${blog}: con el núcleo incluido, tras la transición, debe existir default_option_elementor_font_display (${JSON.stringify(b)})`);
  if (b.font_display !== "swap") problems.push(`sitio ${blog}: con el núcleo incluido font-display debe valer swap (${JSON.stringify(b)})`);
}
console.log(problems.length ? problems.map((p) => `PROBLEMA ${p}`).join("\n") : "font-display por defecto: ausente con el plugin Elementor y presente con el núcleo incluido.");
process.exit(problems.length ? 1 : 0);
' "$OUT" "$ELEMENTOR_PIN" || status=$?
exit $status
