#!/usr/bin/env bash
# «Instalar todos» del catálogo Digitalisimo → Plugins en un WordPress real (Playground): la cadena de admin-post con
# nonces reales y el instalador de WordPress, con ZIP de prueba locales (sin GitHub). Primero con el catálogo de Tools,
# después con el de SEO y el ZIP de IA Tools ausente (debe seguir con los demás e informarlo). DIGITALÍSIMO Hosting es
# privado (2026-11-27): el índice y los ZIP lo traen, pero ningún catálogo lo muestra, lo instala ni acepta instalarlo a
# mano; en el escenario de SEO ya está instalado a mano y tampoco se muestra.
# Uso: bash tests/playground/catalog-install-all.sh   → resultados en $OUT (por defecto /tmp/digi-catalog-install-all)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-catalog-install-all}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
mkdir -p "$OUT/zips" && rm -f "$OUT/result.json" "$OUT/scenario" "$OUT"/zips/*.zip
cp "$ROOT/tests/playground/catalog-install-all/probe.php" "$OUT/probe.php"
python3 - "$OUT/zips" <<'PY'
import sys, zipfile
out = sys.argv[1]
plugins = {
  'digitalisimo-ia-tools-1.0.60': ('digitalisimo.chatbot/digitalisimo-chatbot.php', 'DIGITALÍSIMO IA Tools'),
  'digitalisimo-hosting-1.0.30': ('digitalisimo-hosting/digitalisimo-hosting.php', 'DIGITALÍSIMO Hosting'),
  'digitalisimo-elements-4.3.0.187': ('digitalisimo-elements/pro-elements.php', 'DIGITALÍSIMO Elements'),
  'digitalisimo-migration-1.0.28': ('digitalisimo-migration/digitalisimo-migration.php', 'DIGITALÍSIMO Migración'),
}
for name, (path, title) in plugins.items():
  with zipfile.ZipFile(f'{out}/{name}.zip', 'w') as z:
    z.writestr(path, f"<?php\n/**\n * Plugin Name: {title} (prueba)\n * Version: 1.0.0\n */\n")
PY
cat > "$OUT/blueprint.json" <<'JSON'
{"steps":[
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; activate_plugin( 'digitalisimo-tools/digitalisimo-tools.php' ); file_put_contents( '/wordpress/wp-content/probe/scenario', 'tools' );"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/probe.php';"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/plugin.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; WP_Filesystem(); foreach ( array( 'digitalisimo.chatbot', 'digitalisimo-hosting', 'digitalisimo-elements', 'digitalisimo-migration' ) as $dir ) $GLOBALS['wp_filesystem']->delete( WP_PLUGIN_DIR . '/' . $dir, true ); unlink( '/wordpress/wp-content/probe/zips/digitalisimo-ia-tools-1.0.60.zip' ); wp_mkdir_p( WP_PLUGIN_DIR . '/digitalisimo-hosting' ); file_put_contents( WP_PLUGIN_DIR . '/digitalisimo-hosting/digitalisimo-hosting.php', \"<?php\\n/**\\n * Plugin Name: DIGITALÍSIMO Hosting (instalado a mano)\\n * Version: 1.0.0\\n */\\n\" ); activate_plugin( 'digitalisimo-seo/digitalisimo-integrations.php' ); file_put_contents( '/wordpress/wp-content/probe/scenario', 'seo' );"},
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
for (const s of ["tools", "seo"]) {
  if (!r[s]) { failed++; console.log(`FALLA  ${s}: sin resultado`); continue; }
  if (r[s].length) { failed++; console.log(`FALLA  ${s}: ${r[s].join(" | ")}`); }
}
console.log(failed ? `\n${failed} escenarios fallan.` : "\nInstalar todos: Tools instala los tres que faltan sin Hosting; SEO sigue sin el ZIP de IA Tools y lo informa; ninguno muestra ni instala Hosting.");
process.exit(failed ? 1 : 0);
' "$OUT/result.json"
