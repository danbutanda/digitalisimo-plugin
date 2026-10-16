#!/usr/bin/env bash
# Reglas de visibilidad heredadas en una portada estática real (Playground): con la opción de Element Pack guardada sin
# la clave de la extensión, el motor heredado y la conversión ocultan el título en la portada; un documento convertido
# que perdió las reglas se repara desde su copia original; con la extensión apagada las reglas se guardan sin aplicarse;
# un documento que sólo conserva un widget sin adaptador se cuenta aparte y no vuelve a entrar en los lotes.
# Uso: bash tests/playground/legacy-repair.sh   → resultados en $OUT (por defecto /tmp/digi-legacy-repair)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-legacy-repair}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
mkdir -p "$OUT" && rm -f "$OUT/result.json"
cp "$ROOT/tests/playground/legacy-repair/probe.php" "$OUT/probe.php"
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'digitalisimo-elements/pro-elements.php' ); activate_plugin( 'digitalisimo-migration/digitalisimo-migration.php' ); update_option( 'elementor_onboarded', true );"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe.php';"}]}
JSON
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$ROOT/digitalisimo-migration:/wordpress/wp-content/plugins/digitalisimo-migration" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; exit 1; }
node -e '
const fs = require("fs");
if (!fs.existsSync(process.argv[1])) { console.log("FALLA  la sonda no escribió resultados"); process.exit(1); }
const problems = JSON.parse(fs.readFileSync(process.argv[1], "utf8"));
for (const p of problems) console.log(`FALLA  ${p}`);
console.log(problems.length ? `\n${problems.length} comprobaciones fallan.` : "\nReglas heredadas en portada estática: motor heredado, conversión, reparación, extensión apagada y lotes correctos.");
process.exit(problems.length ? 1 : 0);
' "$OUT/result.json"
