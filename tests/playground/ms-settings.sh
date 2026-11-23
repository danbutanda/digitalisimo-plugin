#!/usr/bin/env bash
# Ajustes de DIGITALÍSIMO SEO e IA Tools en una red Multisite real (Playground con run-blueprint, sin Chrome; paridad
# sitio/red del 2026-11-22): sitio principal con el SEO propio apagado y sus claves, sitio 2 en /sitio2/ que hereda.
# Cada paso pinta la pantalla real, la envía como un navegador y comprueba lo guardado en un proceso nuevo
# (ms-settings/probe.php). Uso: bash tests/playground/ms-settings.sh → $OUT/result.json (por defecto /tmp/digi-ms-settings)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
export TMPDIR="${TMPDIR:-/tmp}"
. "$ROOT/tests/playground/playground-cleanup.sh"
trap playground_cleanup EXIT
OUT="${OUT:-/tmp/digi-ms-settings}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
rm -rf "$OUT" && mkdir -p "$OUT"
cp "$ROOT/tests/playground/ms-settings/probe.php" "$ROOT/tests/lib/browser-form.php" "$OUT/"
json='{"steps":[{"step":"enableMultisite"}'
for item in setup:1 net-seo:1 site-seo:2 check-seo:2 net-ia:1 site-ia:2 check-ia:2; do
  json="$json,{\"step\":\"runPHP\",\"code\":\"<?php putenv('DIGI_STEP=${item%%:*}'); putenv('DIGI_BLOG=${item##*:}'); require '/wordpress/wp-content/probe/probe.php';\"}"
done
echo "$json]}" > "$OUT/blueprint.json"
$CLI run-blueprint --site-url=http://playground.test --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-seo:/wordpress/wp-content/plugins/digitalisimo-seo" \
  --mount="$ROOT/digitalisimo-ia-tools:/wordpress/wp-content/plugins/digitalisimo.chatbot" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/run.log" 2>&1 || { tail -30 "$OUT/run.log"; [ -s "$OUT/fatal.log" ] && cat "$OUT/fatal.log"; exit 1; }
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; exit 1; }
node -e 'const r=require(process.argv[1]);let f=0;for(const x of r){console.log((x.ok?"OK   ":"FALLA ")+x.step+": "+x.what);if(!x.ok)f++;}console.log(r.length+" comprobaciones, "+f+" fallas");process.exit(f?1:0)' "$OUT/result.json"
