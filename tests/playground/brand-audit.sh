#!/usr/bin/env bash
# Auditoría de marca: un sitio con DIGITALÍSIMO Elements y su núcleo incluido (sin el plugin Elementor), en español,
# recorrido en Chrome en español y en inglés: pantallas del constructor en la administración, escritorio, plugins,
# páginas, editor de bloques, kits, el editor del constructor (con su menú y la biblioteca de plantillas) y el frontal.
# Falla si un usuario ve «Elementor» (texto, atributos accesibles, títulos, imágenes o logotipos) o si el HTML público
# lo anuncia en la meta generator. Capturas y detalle en $OUT.
# Uso: bash tests/playground/brand-audit.sh   (LOCALES=es_MX para un solo idioma)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
. "$ROOT/tests/playground/playground-cleanup.sh"
OUT="${OUT:-/tmp/digi-brand-audit}"
PORT="${PORT:-9441}"
CLI="${PLAYGROUND_CLI:-npx --yes @wp-playground/cli@3.1.56}"
PUPPETEER_PATH="${PUPPETEER_PATH:-$(ls -d "$HOME"/.npm/_npx/*/node_modules 2>/dev/null | while read -r d; do [ -d "$d/puppeteer-core" ] && echo "$d" && break; done)}"
[ -n "$PUPPETEER_PATH" ] || { echo "No se encontró puppeteer-core: exportar PUPPETEER_PATH"; exit 1; }
mkdir -p "$OUT" && rm -f "$OUT"/*.png "$OUT/brand-audit.json" "$OUT/setup-error.txt" "$OUT/fatal.log"
cp "$ROOT/tests/playground/brand-audit/setup.php" "$OUT/setup.php"
cat > "$OUT/blueprint.json" <<JSON
{"steps":[{"step":"setSiteLanguage","language":"es_MX"},
{"step":"runPHP","code":"<?php require '/wordpress/wp-content/probe/setup.php';"}]}
JSON
fuser -k -n tcp "$PORT" >/dev/null 2>&1 || true
sleep 1
$CLI server --port="$PORT" --site-url="http://127.0.0.1:$PORT" --blueprint="$OUT/blueprint.json" \
  --mount="$ROOT/digitalisimo-elements:/wordpress/wp-content/plugins/digitalisimo-elements" \
  --mount="$OUT:/wordpress/wp-content/probe" > "$OUT/server.log" 2>&1 &
SERVER=$!
stop_server() { kill $SERVER 2>/dev/null || true; for i in 1 2 3; do fuser -k -n tcp "$PORT" >/dev/null 2>&1 || break; sleep 1; done; }
trap 'stop_server; playground_cleanup' EXIT
for i in $(seq 1 200); do
  sleep 3
  if ! kill -0 $SERVER 2>/dev/null; then tail -20 "$OUT/server.log"; exit 1; fi
  curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-marca/" 2>/dev/null | grep -q '^200$' && break
done
curl -s -m 20 -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/digi-marca/" | grep -q '^200$' || { echo 'El servidor no sirvió la página de prueba'; tail -20 "$OUT/server.log"; exit 1; }
[ -f "$OUT/setup-error.txt" ] && { echo "Preparación incompleta:"; cat "$OUT/setup-error.txt"; exit 1; }
status=0
OUT="$OUT" NODE_PATH="$PUPPETEER_PATH" node "$ROOT/tests/playground/brand-audit/run.cjs" "http://127.0.0.1:$PORT" || status=$?
[ -s "$OUT/fatal.log" ] && { echo "Errores fatales:"; cat "$OUT/fatal.log"; status=1; }
exit $status
