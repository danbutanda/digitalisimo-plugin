#!/usr/bin/env bash
# Catálogo Digitalisimo → Plugins de Tools en un WordPress real (Playground): con Tools solo crea el menú una vez y
# pinta el catálogo; con SEO activo cede el sitio al catálogo de SEO sin duplicar entradas; con Backups (que crea el
# menú padre sin el núcleo de SEO) el menú «Digitalisimo» sigue siendo uno solo.
# Uso: bash tests/playground/tools-catalog.sh   → resultados en $OUT (por defecto /tmp/digi-tools-catalog)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-tools-catalog}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
mkdir -p "$OUT" && rm -f "$OUT/result.json" "$OUT/scenario"
cp "$ROOT/tests/playground/tools-catalog/probe.php" "$OUT/probe.php"
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'digitalisimo-tools/digitalisimo-tools.php' ); file_put_contents( '/wordpress/wp-content/probe/scenario', 'tools' );"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'digitalisimo-seo/digitalisimo-integrations.php' ); file_put_contents( '/wordpress/wp-content/probe/scenario', 'seo' );"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; deactivate_plugins( 'digitalisimo-seo/digitalisimo-integrations.php' ); activate_plugin( 'digitalisimo-backups/digitalisimo-backups.php' ); file_put_contents( '/wordpress/wp-content/probe/scenario', 'backups' );"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe.php';"}]}
JSON
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-tools:/wordpress/wp-content/plugins/digitalisimo-tools" \
  --mount="$ROOT/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo" \
  --mount="$ROOT/digitalisimo-backups:/wordpress/wp-content/plugins/digitalisimo-backups" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/playground.log" 2>&1 || { tail -20 "$OUT/playground.log"; exit 1; }
node -e '
const r = require(process.argv[1]);
let failed = 0;
for (const s of ["tools", "seo", "backups"]) {
  if (!r[s]) { failed++; console.log(`FALLA  ${s}: sin resultado`); continue; }
  if (r[s].length) { failed++; console.log(`FALLA  ${s}: ${r[s].join(", ")}`); }
}
console.log(failed ? `\n${failed} escenarios fallan.` : "\nCatálogo de plugins de Tools: menú único y catálogo completo, solo, con SEO y con Backups.");
process.exit(failed ? 1 : 0);
' "$OUT/result.json"
